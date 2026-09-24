<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight content-audit checks that run on demand from the inbox or the
 * Site Health screen. Each method reads but never writes — flagging issues
 * for a human to resolve through the normal Pending Changes flow.
 *
 * Three checks live here today:
 *   - cross-site references (template paste from another tenant's content)
 *   - AI-generation slug leaks (slugs containing prompt-context tokens)
 *   - legacy "Related guides" appendix sections (user prefers inline links)
 */
class CC_Assistant_Content_Audits {

	/**
	 * Tokens that signal a slug came out of an AI prompt by accident.
	 * These have shown up in the wild on auto-generated drafts.
	 *
	 * Tokens are matched as substrings so they need to be specific enough to
	 * avoid false positives — e.g. plain "gpt-" would flag a legitimate
	 * "gpt-3-overview" tutorial slug. Each entry below is unique to AI
	 * tooling/prompt-leak patterns, not topical AI content.
	 */
	const AI_LEAK_SLUG_TOKENS = array(
		'humanizer',
		'select-87-more-words',
		'select-more-words',
		'to-run-humanizer',
		'rewrite-this',
		'rewrite-text-below',
		'paste-here',
		'paste-content-here',
		'prompt-context',
		'system-prompt',
		'as-an-ai-language-model',
		'click-here-to',
	);

	/**
	 * Phrases that mark a paragraph as the start of a "Related guides" appendix
	 * section. Editorial preference is inline links only — these get flagged
	 * for conversion.
	 */
	const APPENDIX_HEADERS = array(
		'related guides',
		'related reading',
		'helpful guides',
		'further reading',
		'related articles',
		'see also',
		'you may also like',
		'read more',
		'related posts',
		'explore more',
	);

	const CACHE_KEY  = 'cc_content_audits_v1';
	const CACHE_TTL  = 6 * HOUR_IN_SECONDS;

	/**
	 * Run every audit and return a flat issue list. Each issue has a
	 * stable `code` so an admin notice / dashboard card can group them.
	 *
	 * Cached for 6 hours by limit parameter — full-site scans are O(N) over
	 * published posts and content drift is slow. Pass force=true to bypass.
	 */
	public static function run_all( $args = array() ) {
		$limit = isset( $args['limit'] ) ? max( 1, min( 1000, (int) $args['limit'] ) ) : 200;
		$force = ! empty( $args['force'] );

		$cache_key = self::CACHE_KEY . '_' . $limit;
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && isset( $cached['issues'] ) ) {
				$cached['cached'] = true;
				return $cached;
			}
		}

		$post_types = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );

		$issues = array();
		$issues = array_merge( $issues, self::find_cross_site_references( $post_types, $limit ) );
		$issues = array_merge( $issues, self::find_ai_leak_slugs( $post_types, $limit ) );
		$issues = array_merge( $issues, self::find_appendix_sections( $post_types, $limit ) );

		$out = array(
			'issues'       => $issues,
			'count'        => count( $issues ),
			'generated_at' => current_time( 'mysql' ),
			'cached'       => false,
		);
		set_transient( $cache_key, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * Clear cached audit results. Called from places that change content
	 * (Apply, post save) so the next audit run reflects fresh state.
	 */
	public static function bust_cache() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . self::CACHE_KEY . '_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_' . self::CACHE_KEY . '_%'
			)
		);
	}

	/**
	 * 0.17: detect canonical-locale tokens from this site's identity so the
	 * cross_site_reference check does not flag the operator's own city as a
	 * sister-brand mention. Pulls from site URL slug + site title.
	 *
	 * Example: site_url=irvingwellnessclinic.com, site_title="Irving Health
	 * and Wellness Clinic" → returns ['irving', 'wellness', 'clinic'].
	 *
	 * The check is permissive: any single-word locale token that appears in
	 * EITHER the host name or the site title is added to the safe list.
	 * Operators can extend via the cc_assistant_brand_terms option.
	 *
	 * Cached as a static so repeated calls in the same request are free.
	 */
	private static function detect_canonical_locale_tokens() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$tokens = array();
		$host = parse_url( get_site_url(), PHP_URL_HOST );
		if ( $host ) {
			$host = preg_replace( '/^www\./i', '', (string) $host );
			$host = preg_replace( '/\.[a-z]{2,6}$/i', '', $host );
			foreach ( preg_split( '/[\s\-_]+/', mb_strtolower( $host ) ) as $tok ) {
				if ( '' !== $tok && mb_strlen( $tok ) >= 3 ) {
					$tokens[] = $tok;
				}
			}
		}
		$title = get_bloginfo( 'name' );
		if ( $title ) {
			foreach ( preg_split( '/[\s\-_,&]+/', mb_strtolower( $title ) ) as $tok ) {
				$tok = trim( $tok );
				if ( '' !== $tok && mb_strlen( $tok ) >= 3 ) {
					$tokens[] = $tok;
				}
			}
		}
		$cached = array_values( array_unique( $tokens ) );
		return $cached;
	}

	/**
	 * Detect text that names another tenant's brand. Pulls the configured
	 * "own brand" list from cc_assistant_brand_terms; flags any site name
	 * from cc_assistant_known_sister_brands that appears in body content.
	 *
	 * Surfaced this session: Huntington geo page mentioning "Grapevine" and
	 * "ER of Irving" because the template was pasted from a sister site.
	 */
	public static function find_cross_site_references( $post_types, $limit ) {
		// Default list contains SISTER BRAND names only — NOT generic city
		// names. Earlier defaults included bare "Irving" and "Grapevine",
		// which flagged every page on the irvingwellnessclinic.com site as a
		// false positive (76 hits in one audit). Real sister-brand strings
		// like "ER of Irving" stay because they identify a tenant's brand.
		$known_sister = (array) get_option(
			'cc_assistant_known_sister_brands',
			array(
				'ER of Irving',
				'ER of Plano',
				'ER of Frisco',
				'ER of Mansfield',
				'ER of Mesquite',
				'ER of Arlington',
				'ER of Grapevine',
			)
		);
		$own_terms = (array) get_option( 'cc_assistant_brand_terms', array() );

		// 0.17: auto-derive the site's canonical city/locale tokens from the
		// site URL + name so cross_site checks never flag the canonical city
		// as cross-tenant. The site "Irving Health and Wellness Clinic" should
		// not flag posts that mention "Irving" — that IS the site's city.
		$canonical_city_tokens = self::detect_canonical_locale_tokens();

		// Never flag the ACTIVE site's OWN brand as a sister reference. The default
		// $known_sister list ships generic ER brand names (including "ER of Irving");
		// on erofirving.com that IS this site's own name, which previously flagged
		// EVERY page (93 false positives) and could bury a real sister-site hit.
		// Match the site's own brand two ways — by site title, and by alnum-folded
		// host — so "ER of Irving" == bloginfo name == erofirving.com.
		$own_brand_lc    = mb_strtolower( trim( (string) get_bloginfo( 'name' ) ) );
		$site_host       = preg_replace( '/^www\./i', '', (string) parse_url( get_site_url(), PHP_URL_HOST ) );
		$site_host_alnum = preg_replace( '/[^a-z0-9]/', '', mb_strtolower( (string) $site_host ) );

		$issues = array();
		if ( empty( $known_sister ) ) {
			return $issues;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_type IN ($placeholders)
				LIMIT %d",
				array_merge( $post_types, array( $limit ) )
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $r ) {
			$haystack = (string) $r['post_content'] . "\n" . self::flatten_elementor( (int) $r['ID'] );
			$haystack_lc = mb_strtolower( $haystack );

			foreach ( $known_sister as $term ) {
				$term = trim( (string) $term );
				if ( '' === $term ) {
					continue;
				}
				$needle = mb_strtolower( $term );
				if ( false === mb_strpos( $haystack_lc, $needle ) ) {
					continue;
				}
				// Skip the ACTIVE site's own brand (see note above). Match by site
				// title (substring both ways) OR by alnum-folded host containment,
				// so "er of irving" -> "erofirving" is found inside "erofirving.com".
				$needle_alnum = preg_replace( '/[^a-z0-9]/', '', $needle );
				if (
					( '' !== $own_brand_lc && ( $needle === $own_brand_lc || false !== mb_strpos( $own_brand_lc, $needle ) ) )
					|| ( '' !== $needle_alnum && '' !== $site_host_alnum && false !== mb_strpos( $site_host_alnum, $needle_alnum ) )
				) {
					continue;
				}
				// Skip if the term is also in the own-brand list (e.g. shared
				// city name across tenants would generate noise).
				$is_own = false;
				foreach ( $own_terms as $own ) {
					if ( mb_strtolower( (string) $own ) === $needle ) {
						$is_own = true;
						break;
					}
				}
				if ( $is_own ) {
					continue;
				}
				// 0.17: also skip if the term matches the site's canonical
				// locale tokens (derived from site URL + name). Belt-and-
				// suspenders against the city-name-as-sister-brand pitfall.
				if ( in_array( $needle, $canonical_city_tokens, true ) ) {
					continue;
				}
				$issues[] = array(
					'code'       => 'cross_site_reference',
					'post_id'    => (int) $r['ID'],
					'post_title' => $r['post_title'],
					'term'       => $term,
					'message'    => sprintf( 'Page references "%s" — likely template paste from a sister site.', $term ),
					'edit_url'   => get_edit_post_link( (int) $r['ID'], 'raw' ),
				);
				break; // One issue per post is enough; the operator can grep the page.
			}
		}
		return $issues;
	}

	/**
	 * Slug quality check: flag posts whose slug contains a token that signals
	 * the slug came out of an AI prompt context, e.g. "...select-87-more-words-
	 * to-run-humanizer". Slugs are immutable once indexed without redirects,
	 * so these need attention.
	 */
	public static function find_ai_leak_slugs( $post_types, $limit ) {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_type IN ($placeholders)
				LIMIT %d",
				array_merge( $post_types, array( $limit ) )
			),
			ARRAY_A
		);

		$issues = array();
		foreach ( (array) $rows as $r ) {
			$slug = (string) $r['post_name'];
			if ( '' === $slug ) {
				continue;
			}
			foreach ( self::AI_LEAK_SLUG_TOKENS as $tok ) {
				if ( false !== strpos( $slug, $tok ) ) {
					$issues[] = array(
						'code'       => 'ai_leak_slug',
						'post_id'    => (int) $r['ID'],
						'post_title' => $r['post_title'],
						'slug'       => $slug,
						'token'      => $tok,
						'message'    => sprintf( 'Slug contains "%s" — looks like AI prompt context leaked into the URL.', $tok ),
						'edit_url'   => get_edit_post_link( (int) $r['ID'], 'raw' ),
					);
					break;
				}
			}
		}
		return $issues;
	}

	/**
	 * Detect "Related guides / Related reading / See also" appendix sections.
	 * The current editorial preference is inline links only — these need to be
	 * unwound and re-woven into the surrounding sentences.
	 */
	public static function find_appendix_sections( $post_types, $limit ) {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_type IN ($placeholders)
				LIMIT %d",
				array_merge( $post_types, array( $limit ) )
			),
			ARRAY_A
		);

		$issues = array();
		foreach ( (array) $rows as $r ) {
			$haystack = (string) $r['post_content'] . "\n" . self::flatten_elementor( (int) $r['ID'] );

			foreach ( self::APPENDIX_HEADERS as $header ) {
				// Match only inside heading tags (h2/h3/h4) or bold-only paragraphs
				// — naked phrases like "see also CDC for more" inside a sentence
				// are not appendices.
				$pattern = '#<(h[2-6]|strong|b)[^>]*>\s*' . preg_quote( $header, '#' ) . '\s*</\1>#i';
				if ( preg_match( $pattern, $haystack ) ) {
					$issues[] = array(
						'code'       => 'appendix_section',
						'post_id'    => (int) $r['ID'],
						'post_title' => $r['post_title'],
						'header'     => $header,
						'message'    => sprintf( 'Has "%s" appendix section. Editorial preference is inline links only.', $header ),
						'edit_url'   => get_edit_post_link( (int) $r['ID'], 'raw' ),
					);
					break;
				}
			}
		}
		return $issues;
	}

	private static function flatten_elementor( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return '';
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$out = '';
		array_walk_recursive(
			$data,
			function ( $value ) use ( &$out ) {
				if ( is_string( $value ) && false !== mb_strpos( $value, '<' ) ) {
					$out .= ' ' . $value;
				}
			}
		);
		return $out;
	}
}
