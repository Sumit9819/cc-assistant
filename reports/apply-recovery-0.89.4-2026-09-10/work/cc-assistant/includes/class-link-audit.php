<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link audit: classifies every link on a page by domain class and citation rule,
 * flags weak anchor text, and (optionally) checks for broken external links.
 */
class CC_Assistant_Link_Audit {

	const WEAK_ANCHORS = array( 'click here', 'here', 'read more', 'learn more', 'this', 'this link', 'link', 'more' );

	public static function audit_post( $post_id, $check_external = false ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}

		$links = self::collect_links( $post_id );
		if ( empty( $links ) ) {
			$empty = array(
				'post_id'      => $post_id,
				'post_title'   => $post->post_title,
				'total'        => 0,
				'links'        => array(),
				'summary'      => array(
					'internal'    => 0,
					'external'    => 0,
					'allowed'     => 0,
					'blocked'     => 0,
					'unclassified' => 0,
					'weak_anchor' => 0,
					'broken'      => 0,
				),
			);
			// "Zero links" is precisely the claim that must never rest on a
			// parser alone. Cross-check the rendered page before returning it.
			$empty['rendered_cross_check'] = self::rendered_cross_check( $post_id, array() );
			return $empty;
		}

		$competitor_domains = array_map( 'strtolower', (array) get_option( 'cc_assistant_competitor_domains', array() ) );
		$authority_domains  = array_map( 'strtolower', (array) get_option( 'cc_assistant_authority_domains', array() ) );

		$audited = array();
		$summary = array(
			'internal'     => 0,
			'external'     => 0,
			'allowed'      => 0,
			'blocked'      => 0,
			'unclassified' => 0,
			'weak_anchor'  => 0,
			'broken'       => 0,
		);

		foreach ( $links as $link ) {
			$audit = self::classify_link( $link, $competitor_domains, $authority_domains );

			if ( $check_external && ! empty( $audit['url'] ) && empty( $audit['is_internal'] ) && false === strpos( $audit['url'], 'tel:' ) && false === strpos( $audit['url'], 'mailto:' ) ) {
				$audit['broken'] = self::is_broken( $audit['url'] );
				if ( $audit['broken'] ) {
					$summary['broken']++;
				}
			}

			$audited[] = $audit;
			if ( ! empty( $audit['is_internal'] ) ) {
				$summary['internal']++;
			} else {
				$summary['external']++;
			}
			if ( 'allowed' === $audit['citation_status'] ) {
				$summary['allowed']++;
			} elseif ( 'blocked' === $audit['citation_status'] ) {
				$summary['blocked']++;
			} elseif ( 'unclassified' === $audit['citation_status'] ) {
				$summary['unclassified']++;
			}
			if ( ! empty( $audit['weak_anchor'] ) ) {
				$summary['weak_anchor']++;
			}
		}

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'total'      => count( $audited ),
			'summary'    => $summary,
			'links'      => $audited,
			'rendered_cross_check' => self::rendered_cross_check( $post_id, $audited ),
		);
	}

	/**
	 * v0.74.1: compare what the static parser found against what the page
	 * ACTUALLY renders, and say so in the result.
	 *
	 * Exists because the parser had a blind spot (container-level links) and
	 * reported a linked card grid as "links to nothing", and that false gap was
	 * acted on. A parser can prove a link is present; it can never prove one is
	 * absent, because absence may just mean "I do not know how to read this
	 * structure". The rendered DOM is the only ground truth for absence, so the
	 * tool now checks itself instead of trusting the caller to notice.
	 *
	 * @param int   $post_id Post being audited.
	 * @param array $audited Parser-derived link rows (with url / is_internal).
	 * @return array {verdict, dom_internal, parsed_internal, missed_by_parser[], parser_only[], note}
	 */
	private static function rendered_cross_check( $post_id, $audited ) {
		if ( ! class_exists( 'CC_Assistant_Render_Probe' ) ) {
			$rp = CC_ASSISTANT_DIR . 'includes/class-render-probe.php';
			if ( file_exists( $rp ) ) {
				require_once $rp;
			}
		}
		if ( ! class_exists( 'CC_Assistant_Render_Probe' ) || ! method_exists( 'CC_Assistant_Render_Probe', 'live_internal_links' ) ) {
			return array( 'verdict' => 'unavailable', 'note' => 'Render probe not available; parsed links are UNVERIFIED against the live page.' );
		}
		$post = get_post( (int) $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array( 'verdict' => 'skipped', 'note' => 'Post is not published; there is no live page to compare against. Parsed links are UNVERIFIED.' );
		}

		// v0.75.0: read from Page Facts (refreshes itself when stale) so the
		// audit and every other tool share ONE rendered-page reader. Fall
		// back to a direct live fetch only if the facts store is unavailable.
		$live = null;
		if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-page-facts.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
			$pf = CC_Assistant_Page_Facts::get( $post_id );
			if ( ! is_wp_error( $pf ) && ! empty( $pf['facts']['links']['items'] ) ) {
				$hrefs = array();
				foreach ( $pf['facts']['links']['items'] as $it ) {
					if ( ! empty( $it['internal'] ) && ! empty( $it['path'] ) && ! isset( $hrefs[ $it['path'] ] ) ) {
						$hrefs[ $it['path'] ] = (string) $it['anchor'] . ( 'content' === $it['location'] ? '' : ' [' . $it['location'] . ']' );
					}
				}
				$live = array( 'hrefs' => $hrefs, 'cache_state' => $pf['cache_state'], 'source' => 'page_facts@' . $pf['captured_at'] . ( ! empty( $pf['stale'] ) ? ' (STALE)' : '' ) );
			}
		}
		if ( null === $live ) {
			$live = CC_Assistant_Render_Probe::live_internal_links( $post_id );
			if ( is_wp_error( $live ) ) {
				return array( 'verdict' => 'unavailable', 'note' => 'Live fetch failed (' . $live->get_error_message() . '). Parsed links are UNVERIFIED against the rendered page.' );
			}
			$live['source'] = 'live_fetch';
		}

		$norm = function ( $url ) {
			$p    = wp_parse_url( (string) $url );
			$path = is_array( $p ) && isset( $p['path'] ) ? $p['path'] : '/';
			$path = '/' . trim( $path, '/' );
			return '/' === $path ? '/' : $path . '/';
		};
		$parsed = array();
		foreach ( (array) $audited as $row ) {
			if ( empty( $row['is_internal'] ) || empty( $row['url'] ) ) {
				continue;
			}
			$u = (string) $row['url'];
			if ( '#' === substr( $u, 0, 1 ) ) {
				continue;
			}
			$parsed[ $norm( $u ) ] = true;
		}
		$dom = (array) $live['hrefs'];

		// Site chrome (menus, footer, breadcrumbs) is in the DOM but not in the
		// post body, so DOM-only is EXPECTED to be non-empty. What matters is
		// whether the parser missed links that sit in this post's own content:
		// every DOM-only path is reported with its accessible name so the caller
		// can tell "footer nav" from "a card the parser could not read".
		$missed = array();
		foreach ( $dom as $path => $name ) {
			if ( ! isset( $parsed[ $path ] ) ) {
				$missed[] = array( 'path' => $path, 'accessible_name' => $name );
			}
		}
		$parser_only = array();
		foreach ( array_keys( $parsed ) as $path ) {
			if ( ! isset( $dom[ $path ] ) ) {
				$parser_only[] = $path;
			}
		}

		$verdict = empty( $parser_only ) ? 'consistent' : 'divergent';
		$note    = 'DOM-only paths include site chrome (nav/footer), which is normal. Treat any DOM-only path whose accessible_name matches a heading or card in THIS post as a parser blind spot: the link exists on the page even though the parser did not report it. Never claim a link is missing from this page unless it is absent from the DOM list.';
		if ( ! empty( $parser_only ) ) {
			$note .= ' parser_only paths are in the stored structure but NOT rendered: stale post_content, a hidden widget, or a draft-only edit.';
		}
		return array(
			'verdict'          => $verdict,
			'source'           => isset( $live['source'] ) ? $live['source'] : 'live_fetch',
			'cache_state'      => $live['cache_state'],
			'dom_internal'     => count( $dom ),
			'parsed_internal'  => count( $parsed ),
			'missed_by_parser' => array_slice( $missed, 0, 60 ),
			'parser_only'      => $parser_only,
			'note'             => $note,
		);
	}

	private static function collect_links( $post_id ) {
		$links = array();

		if ( get_post_meta( $post_id, '_elementor_data', true ) ) {
			$parsed = CC_Assistant_Elementor_Parser::parse( $post_id );
			if ( $parsed && ! empty( $parsed['all_links'] ) ) {
				$links = $parsed['all_links'];
			}
		} else {
			$post = get_post( $post_id );
			if ( $post && preg_match_all( '#<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $post->post_content, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $m ) {
					$rel_match = array();
					preg_match( '#rel=["\']([^"\']+)["\']#i', $m[0], $rel_match );
					$rel = isset( $rel_match[1] ) ? strtolower( $rel_match[1] ) : '';
					$links[] = array(
						'url'         => $m[1],
						'anchor'      => trim( wp_strip_all_tags( $m[2] ) ),
						'is_external' => self::is_external_url( $m[1] ),
						'is_internal' => self::is_internal_url( $m[1] ),
						'nofollow'    => false !== strpos( $rel, 'nofollow' ),
					);
				}
			}
		}

		return $links;
	}

	private static function classify_link( $link, $competitor_domains, $authority_domains ) {
		$url    = isset( $link['url'] ) ? $link['url'] : '';
		$anchor = isset( $link['anchor'] ) ? $link['anchor'] : '';
		$host   = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
		$host   = $host ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';

		$tld = '';
		if ( $host ) {
			$parts = explode( '.', $host );
			$tld   = end( $parts );
		}

		$domain_class    = 'other';
		$citation_status = 'allowed';

		if ( ! empty( $link['is_internal'] ) || self::is_internal_url( $url ) ) {
			$domain_class    = 'internal';
			$citation_status = 'allowed';
		} elseif ( 'gov' === $tld ) {
			$domain_class    = 'gov';
			$citation_status = 'allowed';
		} elseif ( 'edu' === $tld ) {
			$domain_class    = 'edu';
			$citation_status = 'allowed';
		} elseif ( in_array( $host, $competitor_domains, true ) ) {
			$domain_class    = 'competitor';
			$citation_status = 'blocked';
		} elseif ( in_array( $host, $authority_domains, true ) ) {
			$domain_class    = 'authority';
			$citation_status = 'allowed';
		} elseif ( 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, 'mailto:' ) ) {
			$domain_class    = 'contact';
			$citation_status = 'allowed';
		} else {
			$domain_class    = 'other_com';
			$citation_status = 'unclassified';
		}

		$weak_anchor = $anchor && in_array( strtolower( trim( $anchor ) ), self::WEAK_ANCHORS, true );

		return array(
			'url'             => $url,
			'anchor'          => $anchor,
			'host'            => $host,
			'domain_class'    => $domain_class,
			'citation_status' => $citation_status,
			'is_internal'     => ! empty( $link['is_internal'] ),
			'is_external'     => ! empty( $link['is_external'] ),
			'nofollow'        => ! empty( $link['nofollow'] ),
			'weak_anchor'     => $weak_anchor,
		);
	}

	private static function is_internal_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, '/' ) || 0 === strpos( $url, '?' ) ) {
			return true;
		}
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$link_host = wp_parse_url( $url, PHP_URL_HOST );
		return $site_host && $link_host && $site_host === $link_host;
	}

	private static function is_external_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, '/' ) ) {
			return false;
		}
		return ! self::is_internal_url( $url );
	}

	private static function is_broken( $url ) {
		$response = wp_safe_remote_head(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				'user-agent'  => 'CC-Assistant-LinkAudit/1.0',
			)
		);
		if ( is_wp_error( $response ) ) {
			return true;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 400;
	}
}
