<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Playbook-fit audit. Compares the matched industry overlay's vocabulary
 * against the site's actual content vocabulary so a wrong-niche overlay
 * (or an overlay whose copy uses the wrong domain noun for this facility)
 * gets flagged BEFORE the model acts on it.
 *
 * Why this exists: when v0.28 first shipped, the "medical clinic" overlay
 * was auto-matched to a freestanding ER. Schema typing was right (the
 * detector picked Healthcare), but the overlay COPY kept saying "clinic"
 * — wrong domain noun for an ER. The operator caught it, not the AI.
 * This audit moves that catch upstream: every session bootstrap surfaces
 * a coverage score + the specific overlay terms that don't appear on the
 * site (likely wrong-fit) and the site terms missing from the overlay
 * (likely a gap the overlay should grow to address).
 *
 * The audit is cheap (cached in a 24h transient, reads only postmeta +
 * cached schema fetch) and runs whenever whoami fires.
 */
class CC_Assistant_Playbook_Fit {

	const TRANSIENT_KEY = 'cc_assistant_playbook_fit';
	const TTL_SECONDS   = 86400; // 24h

	/**
	 * Stop-word list — generic English plus playbook-meta words that
	 * appear in every overlay regardless of industry. Trimming these
	 * stops a trivial "page" or "site" hit from dragging coverage up.
	 */
	const STOP_WORDS = array(
		'the','and','for','with','from','this','that','your','our','their',
		'page','pages','site','sites','content','google','seo','schema',
		'rank','rules','must','need','always','never','before','after',
		'use','using','only','also','about','into','over','than','very',
		'when','where','what','how','why','which','who','top','rule',
		'should','would','could','may','might','will','can','one','two',
		'are','was','were','has','have','had','been','being','any','all',
	);

	/**
	 * Run the audit. Returns:
	 *   - industry: the slug audited
	 *   - status:   'matched' | 'thin' | 'drift_detected'
	 *   - coverage: 0..100 — share of significant overlay terms also frequent on site
	 *   - unused_overlay_terms: overlay terms NOT found on the site
	 *       (likely wrong-fit copy — overlay says X, site doesn't talk about X)
	 *   - missing_from_overlay: site terms NOT in the overlay
	 *       (likely a gap — site talks about Y heavily, overlay never mentions it)
	 *   - hint: a one-line read for the AI / operator
	 */
	public static function audit( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-playbook.php';

		$industry = CC_Assistant_Industry_Profile::industry_slug();
		$overlays = CC_Assistant_SEO_Playbook::overlays();
		$overlay  = isset( $overlays[ $industry ] ) ? $overlays[ $industry ] : $overlays['general'];

		$overlay_vocab = self::extract_overlay_vocab( $overlay );
		$site_vocab    = self::extract_site_vocab();

		$result = self::score( $industry, $overlay_vocab, $site_vocab );

		set_transient( self::TRANSIENT_KEY, $result, self::TTL_SECONDS );
		return $result;
	}

	/**
	 * Pull significant terms from the overlay text. We tokenize every
	 * `top_rules` + `sections[].rules[]` string, lowercase, drop short
	 * tokens, drop stop-words, and count frequencies. Terms appearing 2+
	 * times in the overlay are "significant" — the rest are incidental.
	 */
	private static function extract_overlay_vocab( $overlay ) {
		$bag = '';
		if ( ! empty( $overlay['top_rules'] ) && is_array( $overlay['top_rules'] ) ) {
			$bag .= ' ' . implode( ' ', $overlay['top_rules'] );
		}
		if ( ! empty( $overlay['sections'] ) && is_array( $overlay['sections'] ) ) {
			foreach ( $overlay['sections'] as $section ) {
				if ( ! empty( $section['name'] ) ) {
					$bag .= ' ' . $section['name'];
				}
				if ( ! empty( $section['why_matters'] ) ) {
					$bag .= ' ' . $section['why_matters'];
				}
				if ( ! empty( $section['rules'] ) && is_array( $section['rules'] ) ) {
					$bag .= ' ' . implode( ' ', $section['rules'] );
				}
			}
		}
		$tokens = self::tokenize( $bag );
		$freq   = array_count_values( $tokens );
		arsort( $freq );
		// Keep terms that appear 2+ times — once is incidental, multiple
		// hits says the overlay is actually leaning on this term.
		return array_filter( $freq, function ( $count ) { return $count >= 2; } );
	}

	/**
	 * Pull significant terms from the site. Source: published post titles
	 * + recent post excerpts + JSON-LD @type values seen on the homepage.
	 * Site terms appearing 3+ times are "significant" — anything rarer
	 * is one-off content and shouldn't drive overlay-fit conclusions.
	 */
	private static function extract_site_vocab() {
		global $wpdb;

		$bag = '';
		// Titles + excerpts of recently modified published content.
		$rows = $wpdb->get_results(
			"SELECT post_title, post_excerpt FROM {$wpdb->posts}
			 WHERE post_status='publish' AND post_type IN ('page','post')
			 ORDER BY post_modified DESC LIMIT 300"
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$bag .= ' ' . (string) $row->post_title;
				$bag .= ' ' . (string) $row->post_excerpt;
			}
		}

		// Schema @type values from the homepage. Treat each @type as a
		// vocabulary token so "EmergencyService" or "Attorney" surfaces
		// as a site-vocab signal even if the word doesn't appear in copy.
		$schema_types = self::fetch_homepage_schema_types();
		foreach ( $schema_types as $type => $hits ) {
			$bag .= ' ' . str_repeat( ' ' . strtolower( $type ) . ' ', max( 1, (int) $hits ) );
		}

		$tokens = self::tokenize( $bag );
		$freq   = array_count_values( $tokens );
		arsort( $freq );
		// Site vocab signal threshold: 3+ hits. Titles repeat brand /
		// service nouns naturally; 3+ is the inflection where a term
		// goes from "one post" to "a theme on this site."
		return array_filter( $freq, function ( $count ) { return $count >= 3; } );
	}

	private static function tokenize( $text ) {
		$text = strtolower( (string) $text );
		// Treat - and / as word separators so "rank-math" doesn't show up
		// as one weird token. Hyphens in schema names are uncommon enough
		// that this is safe.
		$text = preg_replace( '/[\-\/]+/', ' ', $text );
		// Strip punctuation.
		$text = preg_replace( '/[^a-z0-9 ]+/', ' ', $text );
		$parts = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$out = array();
		foreach ( (array) $parts as $p ) {
			if ( strlen( $p ) < 4 ) {
				continue;
			}
			if ( in_array( $p, self::STOP_WORDS, true ) ) {
				continue;
			}
			$out[] = $p;
		}
		return $out;
	}

	private static function fetch_homepage_schema_types() {
		$cache_key = 'cc_assistant_playbook_fit_schema';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$bucket = array();
		$resp   = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'    => 8,
				'user-agent' => CC_ASSISTANT_HTTP_UA,
				'sslverify'  => true,
				'redirection' => 0,
				'limit_response_size' => 2097152,
			)
		);
		if ( ! is_wp_error( $resp ) ) {
			$html = wp_remote_retrieve_body( $resp );
			if ( $html && preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m ) ) {
				foreach ( $m[1] as $block ) {
					$json = json_decode( trim( $block ), true );
					if ( is_array( $json ) ) {
						self::collect_schema_types_recursive( $json, $bucket );
					}
				}
			}
		}
		set_transient( $cache_key, $bucket, self::TTL_SECONDS );
		return $bucket;
	}

	private static function collect_schema_types_recursive( $node, array &$bucket ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		if ( isset( $node['@type'] ) ) {
			$types = (array) $node['@type'];
			foreach ( $types as $t ) {
				$t = (string) $t;
				if ( '' !== $t ) {
					$bucket[ $t ] = isset( $bucket[ $t ] ) ? $bucket[ $t ] + 1 : 1;
				}
			}
		}
		foreach ( $node as $v ) {
			if ( is_array( $v ) ) {
				self::collect_schema_types_recursive( $v, $bucket );
			}
		}
	}

	/**
	 * Score overlay-vs-site fit. Coverage = the share of the SITE's top
	 * vocabulary that the overlay actually addresses. We score this way
	 * (instead of "share of overlay terms that appear on site") because
	 * the right question is "does the overlay understand what this site
	 * is about?" — not "does the site talk about every overlay term?"
	 * (no site does, the overlay is jargon-heavy).
	 *
	 * "Unused" = overlay terms with no site signal (overlay is leaning on
	 * vocabulary the site never uses — possibly wrong-niche copy).
	 * "Missing" = high-frequency site terms with no overlay signal
	 * (overlay never addresses themes the site is heavy on).
	 *
	 * Both lists cap at 12 so the whoami payload stays compact.
	 */
	private static function score( $industry, array $overlay_vocab, array $site_vocab ) {
		if ( empty( $overlay_vocab ) ) {
			return array(
				'industry' => $industry,
				'status'   => 'thin',
				'coverage' => 0,
				'unused_overlay_terms' => array(),
				'missing_from_overlay' => array(),
				'hint'     => 'Overlay vocabulary is empty (likely industry=general). Set a specific industry from Settings → Industry to enable fit auditing.',
			);
		}
		if ( empty( $site_vocab ) ) {
			return array(
				'industry' => $industry,
				'status'   => 'thin',
				'coverage' => 0,
				'unused_overlay_terms' => array(),
				'missing_from_overlay' => array(),
				'hint'     => 'Site has too little published content to audit vocabulary fit. The overlay will be used as-is until the site grows.',
			);
		}

		// Top-N site terms — the things the site is genuinely about. Coverage
		// asks how many of these the overlay addresses. 30 is a sweet spot:
		// big enough to capture the site's themes, small enough that one or
		// two overlay matches move the needle in a meaningful way.
		$top_n         = 30;
		$top_site_terms = array_slice( array_keys( $site_vocab ), 0, $top_n );
		$overlay_set   = array_flip( array_keys( $overlay_vocab ) );
		$site_set      = array_flip( array_keys( $site_vocab ) );

		$matched_top_count = 0;
		foreach ( $top_site_terms as $term ) {
			if ( isset( $overlay_set[ $term ] ) ) {
				$matched_top_count++;
			}
		}
		$coverage = (int) round( ( $matched_top_count / max( 1, count( $top_site_terms ) ) ) * 100 );

		// Find overlay terms with no site signal — possibly wrong-niche copy.
		$unused = array();
		foreach ( $overlay_vocab as $term => $count ) {
			if ( ! isset( $site_set[ $term ] ) ) {
				$unused[ $term ] = (int) $count;
			}
		}
		// Find site terms with no overlay signal — gaps the overlay should grow.
		$missing = array();
		foreach ( $site_vocab as $term => $count ) {
			if ( ! isset( $overlay_set[ $term ] ) ) {
				$missing[ $term ] = (int) $count;
			}
		}
		arsort( $unused );
		arsort( $missing );
		$unused  = array_slice( array_keys( $unused ), 0, 12 );
		$missing = array_slice( array_keys( $missing ), 0, 12 );

		// Verdict thresholds. Coverage is now "share of top-30 site terms the
		// overlay addresses" — directly answers "does the overlay understand
		// what this site is about?" Realistic ranges:
		//   <20%  = drift_detected (most of what the site is about is invisible to the overlay)
		//   20-50% = thin (partial; log specific gaps via update_site_memory_notes as they come up)
		//   50%+ = matched (overlay covers the majority of site themes)
		if ( $coverage < 20 ) {
			$status = 'drift_detected';
			$hint   = sprintf(
				'Active overlay "%s" only matches %d%% of the site\'s vocabulary. The site talks about: %s. Consider switching industry from Settings, or treat this overlay as partial guidance and verify each industry-specific recommendation against actual site content before proposing it.',
				$industry,
				$coverage,
				implode( ', ', array_slice( $missing, 0, 6 ) )
			);
		} elseif ( $coverage < 50 ) {
			$status = 'thin';
			$hint   = sprintf(
				'Active overlay "%s" matches %d%% of site vocabulary. Mostly aligned but the site uses these terms heavily that the overlay does not address: %s. When relevant to the current task, log the gap via update_site_memory_notes so the next overlay refresh can pick it up.',
				$industry,
				$coverage,
				implode( ', ', array_slice( $missing, 0, 6 ) )
			);
		} else {
			$status = 'matched';
			$hint   = sprintf(
				'Active overlay "%s" matches %d%% of site vocabulary. Fit looks healthy — use the industry rules with confidence.',
				$industry,
				$coverage
			);
		}

		return array(
			'industry'             => $industry,
			'status'               => $status,
			'coverage'             => $coverage,
			'unused_overlay_terms' => $unused,
			'missing_from_overlay' => $missing,
			'hint'                 => $hint,
		);
	}
}
