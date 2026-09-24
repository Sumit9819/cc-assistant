<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema.org JSON-LD generator. Produces ready-to-inject Article /
 * FAQPage / HowTo / BreadcrumbList markup for a post based on its
 * content, headings, author, and intent fingerprint.
 *
 * Output is a single JSON-LD blob (one or more @graph nodes). The
 * MCP tool can either return the JSON for review or queue a
 * postmeta update under a configurable meta key (Yoast / Rank Math
 * already inject their own; this is for sites where neither is set
 * up to emit the right schema, or where the user wants supplementary
 * @type entries).
 */
class CC_Assistant_Schema_Generator {

	const META_KEY = '_cc_assistant_schema_jsonld';

	/**
	 * Generate schema graph for a post. Returns array with 'jsonld' (the
	 * encoded string) and 'types' (which schema types were emitted).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Optional. Supported keys:
	 *   - include_breadcrumb (bool, default false): force a BreadcrumbList node
	 *     even when the active SEO plugin (Yoast / Rank Math) already emits one
	 *     site-wide. When false, the BreadcrumbList is emitted only on sites
	 *     where no breadcrumb-emitting SEO plugin is active — two BreadcrumbList
	 *     @graphs on one page is a duplicate-breadcrumb defect.
	 */
	public static function generate( $post_id, $args = array() ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$args = is_array( $args ) ? $args : array();
		$include_breadcrumb = ! empty( $args['include_breadcrumb'] );

		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
		$structure = CC_Assistant_SEO_Tools::analyze_post_structure( (int) $post_id );

		$nodes = array();
		$types = array();

		// 1) Article / WebPage base node — almost always useful. Article is for
		// POSTS only; a static page gets a WebPage node instead (Article on a
		// service page is a schema-type mismatch).
		$is_page      = ( 'page' === $post->post_type );
		$article_type = $is_page ? 'WebPage' : 'Article';
		// Fingerprint-aware article subtype (posts only).
		if ( ! $is_page && in_array( 'how-to', (array) ( $structure['intent_fingerprint'] ?? array() ), true ) ) {
			$article_type = 'HowToArticle';
		}

		$image = get_the_post_thumbnail_url( (int) $post_id, 'large' );

		// Canonical URL: always the permalink. Never $post->guid or a hand-built
		// ?page_id=N URL — those leak the ugly query form into rich results.
		$permalink = get_permalink( $post_id );

		if ( $is_page ) {
			$article_node = array(
				'@type' => $article_type,
				'@id'   => $permalink . '#webpage',
				'name'  => wp_strip_all_tags( $post->post_title ),
				'url'   => $permalink,
			);
		} else {
			$article_node = array(
				'@type'            => $article_type,
				'@id'              => $permalink . '#article',
				'headline'         => wp_strip_all_tags( $post->post_title ),
				'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $permalink ),
			);
		}

		// Description: prefer the SEO plugin's meta description, fall back to
		// the excerpt, and omit the field entirely when both are empty. An
		// empty-string description is worse than no description.
		$description = self::meta_description( (int) $post_id );
		if ( '' === $description ) {
			$description = trim( wp_strip_all_tags( (string) get_the_excerpt( $post ) ) );
		}
		if ( '' !== $description ) {
			$article_node['description'] = $description;
		}

		// Dates: use the GMT-aware core getters and omit the field when the
		// stored date is missing/invalid. mysql2date on an empty or zeroed
		// post_date_gmt produced garbage like "-001-11-30T00:00:00+00:00".
		$published_ts = (int) get_post_time( 'U', true, $post );
		$published    = get_post_time( 'c', true, $post );
		if ( $published && $published_ts > 0 ) {
			$article_node['datePublished'] = $published;
		}
		$modified_ts = (int) get_post_modified_time( 'U', true, $post );
		$modified    = get_post_modified_time( 'c', true, $post );
		if ( $modified && $modified_ts > 0 ) {
			$article_node['dateModified'] = $modified;
		}

		if ( $image ) {
			$article_node['image'] = $image;
		}
		// Author: always the site Organization. The WP post_author is an admin
		// login (often named after the business), which produced org-as-Person
		// nodes like {"@type":"Person","name":"ER of Irving"}. This plugin's
		// sites have no human author byline system, so Organization is correct.
		$article_node['author'] = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url(),
		);
		$publisher_logo = get_site_icon_url( 512 );
		$article_node['publisher'] = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url(),
		);
		if ( $publisher_logo ) {
			$article_node['publisher']['logo'] = array( '@type' => 'ImageObject', 'url' => $publisher_logo );
		}
		$nodes[] = $article_node;
		$types[] = $article_type;

		// 2) FAQPage if structure has FAQ shape — extract Q/A pairs from headings.
		if ( ! empty( $structure['has_faq_shape'] ) || ( ! empty( $structure['h2_questions'] ) && (int) $structure['h2_questions'] >= 2 ) ) {
			$faqs = self::extract_faqs( $post->post_content );
			// Also walk Elementor widgets if present. Use the same transient
			// the parser already caches against post_modified_gmt so we don't
			// re-parse the JSON on top of analyze_post_structure's parse above.
			if ( get_post_meta( (int) $post_id, '_elementor_data', true ) ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';
				$parse_ck = 'cc_schema_parsed_' . (int) $post_id . '_' . md5( (string) get_post_field( 'post_modified_gmt', (int) $post_id ) );
				$parsed   = get_transient( $parse_ck );
				if ( false === $parsed ) {
					$parsed = CC_Assistant_Elementor_Parser::parse( (int) $post_id, 'full' );
					set_transient( $parse_ck, $parsed, 15 * MINUTE_IN_SECONDS );
				}
				if ( ! empty( $parsed['widgets'] ) ) {
					foreach ( $parsed['widgets'] as $w ) {
						if ( isset( $w['settings']['editor'] ) ) {
							$faqs = array_merge( $faqs, self::extract_faqs( (string) $w['settings']['editor'] ) );
						}
						// Elementor has a built-in FAQ accordion widget.
						if ( ! empty( $w['settings']['tabs'] ) && is_array( $w['settings']['tabs'] ) ) {
							foreach ( $w['settings']['tabs'] as $tab ) {
								if ( ! empty( $tab['tab_title'] ) && ! empty( $tab['tab_content'] ) ) {
									$faqs[] = array(
										'q' => wp_strip_all_tags( (string) $tab['tab_title'] ),
										'a' => wp_strip_all_tags( (string) $tab['tab_content'] ),
									);
								}
							}
						}
					}
				}
			}
			$faqs = array_slice( array_values( $faqs ), 0, 12 );
			if ( ! empty( $faqs ) ) {
				$main_entity = array();
				foreach ( $faqs as $f ) {
					$q = trim( (string) $f['q'] );
					$a = trim( (string) $f['a'] );
					if ( '' === $q || '' === $a ) {
						continue;
					}
					$main_entity[] = array(
						'@type'          => 'Question',
						'name'           => $q,
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $a,
						),
					);
				}
				if ( ! empty( $main_entity ) ) {
					$nodes[] = array(
						'@type'      => 'FAQPage',
						'@id'        => get_permalink( $post_id ) . '#faq',
						'mainEntity' => $main_entity,
					);
					$types[] = 'FAQPage';
				}
			}
		}

		// 3) BreadcrumbList — homepage → post (or homepage → category → post).
		// Skipped by default when the active SEO plugin (Yoast / Rank Math)
		// already emits a BreadcrumbList site-wide: a second one from us is a
		// duplicate-breadcrumb defect on the live page. Pass
		// include_breadcrumb=true to force it anyway.
		if ( $include_breadcrumb || ! self::seo_plugin_emits_breadcrumb() ) {
			$breadcrumb = array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => __( 'Home', 'cc-assistant' ),
					'item'     => home_url( '/' ),
				),
			);
			// Primary category if any.
			$cats = get_the_category( (int) $post_id );
			$pos = 2;
			if ( ! empty( $cats ) ) {
				$breadcrumb[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $cats[0]->name,
					'item'     => get_category_link( $cats[0]->term_id ),
				);
			}
			$breadcrumb[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => wp_strip_all_tags( $post->post_title ),
				'item'     => $permalink,
			);
			$nodes[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $permalink . '#breadcrumb',
				'itemListElement' => $breadcrumb,
			);
			$types[] = 'BreadcrumbList';
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);

		return array(
			'post_id' => (int) $post_id,
			'types'   => $types,
			'jsonld'  => wp_json_encode( $graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		);
	}

	/**
	 * Whether the active SEO plugin already injects a BreadcrumbList on the
	 * front end. Yoast and Rank Math both emit BreadcrumbList site-wide as
	 * part of their JSON-LD graph, so adding our own would duplicate it.
	 * Uses the same detection the rest of the plugin relies on.
	 */
	public static function seo_plugin_emits_breadcrumb() {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		$seo    = CC_Assistant_Site_Memory::detect_seo();
		$plugin = isset( $seo['plugin'] ) ? (string) $seo['plugin'] : '';
		return in_array( $plugin, array( 'yoast', 'rank-math' ), true );
	}

	/**
	 * SEO meta description for a post, routed through the detected SEO
	 * plugin's meta key. Returns '' when no SEO plugin is active or the
	 * field is unset.
	 */
	private static function meta_description( $post_id ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		$seo = CC_Assistant_Site_Memory::detect_seo();
		if ( empty( $seo['meta_keys']['description'] ) ) {
			return '';
		}
		$value = get_post_meta( (int) $post_id, (string) $seo['meta_keys']['description'], true );
		return is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
	}

	/**
	 * Extract FAQ pairs from arbitrary HTML by walking H2/H3/H4 tags
	 * that look like questions and pairing them with the text that
	 * follows up to the next heading.
	 */
	private static function extract_faqs( $html ) {
		$out = array();
		if ( ! $html ) {
			return $out;
		}
		if ( preg_match_all( '/<h([2-4])[^>]*>(.*?)<\/h\1>([\s\S]*?)(?=<h[1-6][^>]*>|\z)/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$q = trim( wp_strip_all_tags( $m[2] ) );
				if ( '' === $q ) {
					continue;
				}
				$is_q = false !== strrpos( $q, '?' )
					|| (bool) preg_match( '/^(what|why|how|when|where|who|which|is|are|can|does|do|should|will)\b/i', $q );
				if ( ! $is_q ) {
					continue;
				}
				$a = trim( wp_strip_all_tags( $m[3] ) );
				if ( strlen( $a ) < 20 ) {
					continue;
				}
				$out[] = array( 'q' => $q, 'a' => $a );
			}
		}
		return $out;
	}

	/**
	 * Queue a pending change that writes the generated JSON-LD into
	 * post meta. The reviewer sees the JSON in the inbox before approval.
	 *
	 * Validates FAQ items in the proposal against the actual post body
	 * before queueing — Google treats FAQ schema for content not visible on
	 * the page as rich-results spam, so we refuse those proposals outright.
	 */
	public static function propose_for_post( $post_id, $reasoning = '', $success_metrics = null, $args = array() ) {
		$result = self::generate( $post_id, is_array( $args ) ? $args : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';

		$lint = self::lint_schema_proposal( (int) $post_id, $result );
		if ( ! empty( $lint['hard_violations'] ) ) {
			return new WP_Error(
				'schema_lint_hard_violation',
				'Schema proposal failed validation: ' . implode( ', ', $lint['hard_violations'] ) . '. Schema for content not visible on the page is rich-results spam — fix the body or drop the offending @type.',
				array( 'status' => 422, 'lint' => $lint )
			);
		}

		$current = get_post_meta( (int) $post_id, self::META_KEY, true );
		return CC_Assistant_Pending_Changes::queue( array(
			'post_id'         => (int) $post_id,
			'change_type'     => 'postmeta_update',
			'change_summary'  => 'Schema.org JSON-LD: ' . implode( ' + ', $result['types'] ),
			'current_value'   => wp_json_encode( array( 'key' => self::META_KEY, 'value' => $current ) ),
			'proposed_value'  => wp_json_encode( array( 'key' => self::META_KEY, 'value' => $result['jsonld'] ) ),
			'reasoning'       => $reasoning ?: 'Adds Schema.org markup for richer SERP results. Types: ' . implode( ', ', $result['types'] ) . '.',
			'lint_report'     => $lint,
			'success_metrics' => $success_metrics,
		) );
	}

	/**
	 * Validate a schema proposal against the actual post body. Today this
	 * focuses on FAQPage entries — every Question name must appear in the
	 * post HTML, and every Answer text must appear there too. We also
	 * detect duplicate-FAQPage situations where the active SEO plugin
	 * (Yoast/Rank Math) is already injecting FAQ schema into the page.
	 */
	public static function lint_schema_proposal( $post_id, $result ) {
		$checks   = array();
		$post     = get_post( (int) $post_id );
		$body_lc  = $post ? mb_strtolower( wp_strip_all_tags( (string) $post->post_content ) ) : '';
		$body_lc  = preg_replace( '/\s+/u', ' ', $body_lc );

		$jsonld = isset( $result['jsonld'] ) ? $result['jsonld'] : '';
		// jsonld is the raw HTML <script> wrapper our generator emits; strip the
		// tags to get the JSON we can parse for FAQ items.
		$json_text = preg_replace( '#<script[^>]*>|</script>#i', '', (string) $jsonld );
		$decoded   = json_decode( trim( $json_text ), true );
		$faq_items = self::find_faq_items_in_jsonld( $decoded );

		if ( ! empty( $faq_items ) ) {
			$missing_q = array();
			$missing_a = array();
			foreach ( $faq_items as $i => $item ) {
				$q = mb_strtolower( (string) ( $item['name'] ?? '' ) );
				$a = mb_strtolower( wp_strip_all_tags( (string) ( $item['acceptedAnswer']['text'] ?? '' ) ) );
				$q = preg_replace( '/\s+/u', ' ', trim( $q ) );
				$a = preg_replace( '/\s+/u', ' ', trim( $a ) );
				if ( '' !== $q && false === strpos( $body_lc, $q ) ) {
					$missing_q[] = (string) ( $item['name'] ?? '' );
				}
				// For answers, accept "first 60 chars match somewhere in the body"
				// since exact-match is too strict and Google's check is fuzzy.
				$a_probe = mb_substr( $a, 0, 60 );
				if ( '' !== $a_probe && false === strpos( $body_lc, $a_probe ) ) {
					$missing_a[] = (string) ( $item['name'] ?? '(question ' . ( $i + 1 ) . ')' );
				}
			}
			$checks['faq_questions_in_body'] = array(
				'pass'    => empty( $missing_q ),
				'missing' => array_slice( $missing_q, 0, 5 ),
				'message' => empty( $missing_q )
					? sprintf( 'All %d FAQ question(s) appear in the post body.', count( $faq_items ) )
					: sprintf( '%d FAQ question(s) are not visible in the post body. Google treats this as spam.', count( $missing_q ) ),
			);
			$checks['faq_answers_in_body'] = array(
				'pass'    => empty( $missing_a ),
				'missing' => array_slice( $missing_a, 0, 5 ),
				'message' => empty( $missing_a )
					? 'All FAQ answers match content in the body.'
					: sprintf( '%d FAQ answer(s) are not in the post body. Add the content first, then re-propose schema.', count( $missing_a ) ),
			);
		}

		// Duplicate-FAQ guard: Yoast and Rank Math both inject Article/WebPage
		// schema, but only some setups inject FAQPage. We can't fully detect
		// duplicates without rendering the page, so we record an advisory.
		$has_faq_type = false;
		$types        = isset( $result['types'] ) ? (array) $result['types'] : array();
		foreach ( $types as $t ) {
			if ( 'FAQPage' === $t ) {
				$has_faq_type = true;
				break;
			}
		}
		if ( $has_faq_type ) {
			$checks['faq_duplicate_advisory'] = array(
				'pass'    => true, // advisory, not a fail
				'message' => 'FAQPage type added. Verify your SEO plugin is not also injecting FAQ schema (run a Google Rich Results Test) — two FAQPage @graphs on one page can suppress the rich result entirely.',
			);
		}

		$pass = 0;
		$fail = 0;
		$hard = array();
		foreach ( $checks as $name => $c ) {
			if ( ! empty( $c['pass'] ) ) {
				$pass++;
			} else {
				$fail++;
				if ( in_array( $name, array( 'faq_questions_in_body', 'faq_answers_in_body' ), true ) ) {
					$hard[] = $name;
				}
			}
		}
		return array(
			'pass'            => 0 === $fail,
			'pass_count'      => $pass,
			'fail_count'      => $fail,
			'total'           => count( $checks ),
			'hard_violations' => $hard,
			'checks'          => $checks,
			'generated_at'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Walk a decoded JSON-LD payload (single graph or array, with optional
	 * @graph wrapper) and extract every Question entry from FAQPage.mainEntity.
	 */
	private static function find_faq_items_in_jsonld( $decoded ) {
		if ( empty( $decoded ) ) {
			return array();
		}
		$out  = array();
		$walk = function ( $node ) use ( &$walk, &$out ) {
			if ( is_array( $node ) ) {
				if ( isset( $node['@type'] ) && 'FAQPage' === $node['@type'] && ! empty( $node['mainEntity'] ) ) {
					foreach ( (array) $node['mainEntity'] as $q ) {
						if ( is_array( $q ) ) {
							$out[] = $q;
						}
					}
				}
				if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
					foreach ( $node['@graph'] as $child ) {
						$walk( $child );
					}
				}
				foreach ( $node as $k => $v ) {
					if ( '@graph' === $k ) {
						continue; // already walked
					}
					if ( is_array( $v ) ) {
						$walk( $v );
					}
				}
			}
		};
		$walk( $decoded );
		return $out;
	}
}
