<?php
/**
 * Win-audit (v0.41) — part 3 of the page-quality engine.
 *
 * Scores a page against the evidence-verified 2026 winning criteria AND
 * against its top live competitor pages, returning a prioritized
 * "to beat them, do X" action list.
 *
 * Research grounding (verified 2025-2026, multi-source):
 *   - Fan-out/subtopic coverage is the strongest AI-citation predictor
 *     (+161% citation likelihood) -> fanout_coverage dimension.
 *   - Information gain (unique stats, quotes, cited primary sources) lifts
 *     visibility +25-40%, and position-5 pages gain +115% from it while
 *     position-1 pages gain ~0 -> info_gain carries the top weight.
 *   - Freshness is first-order for AI citation (cited content 25.7% fresher;
 *     <3mo ~3x more likely cited) - substantive updates only.
 *   - Schema gives NO citation lift (Ahrefs n=1885: -4.6% vs control) ->
 *     hygiene-only, weight 2. Word count and keyword density are refuted ->
 *     never scored, never recommended.
 *   - Citation patterns DRIFT (top-10 share of AIO citations 76%->38% in 6
 *     months) -> weights live in the cc_assistant_win_audit_weights option,
 *     not code.
 *
 * The plugin has no SERP API: the CALLER supplies 2-5 competitor URLs
 * (the assistant finds them via its own web search). Competitor fetches are
 * cached in transients (6h) so re-audits don't re-fetch.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-content-evidence.php';

class CC_Assistant_Win_Audit {

	const FETCH_TIMEOUT   = 15;
	const MAX_BODY_BYTES  = 2097152; // 2 MB
	const CACHE_TTL       = 6 * HOUR_IN_SECONDS;
	const MAX_COMPETITORS = 5;

	/**
	 * Default dimension weights (sum 100). Operator-tunable via the
	 * cc_assistant_win_audit_weights option + filter because citation
	 * patterns drift quarter to quarter.
	 */
	/** Below this word count a fetched competitor is a JS shell / cookie wall. */
	const MIN_COMPETITOR_WORDS = 100;

	/** Raw <img> counts include icons and pixels; cap the comparison. */
	const MAX_CREDIBLE_IMAGES = 25;

	/** Same for <video>/<iframe> embeds. */
	const MAX_CREDIBLE_VIDEOS = 5;

	public static function weights() {
		$defaults = array(
			'info_gain'        => 18,
			'intent_format'    => 15,
			'fanout_coverage'  => 15,
			'freshness'        => 10,
			'extractability'   => 10,
			'eeat'             => 8,
			'effort_media'     => 8,
			'title_ctr'        => 8,
			'internal_support' => 6,
			'schema_hygiene'   => 2,
		);
		$opt = get_option( 'cc_assistant_win_audit_weights', array() );
		if ( is_array( $opt ) ) {
			foreach ( $opt as $k => $v ) {
				if ( isset( $defaults[ $k ] ) && is_numeric( $v ) ) {
					$defaults[ $k ] = (float) $v;
				}
			}
		}
		return apply_filters( 'cc_assistant_win_audit_weights', $defaults );
	}

	/* ---------------------------------------------------------------------
	 * Entry point
	 * ------------------------------------------------------------------- */

	/**
	 * @param array $args { post_id (req), query?, competitor_urls?[], days? }
	 * @return array|WP_Error
	 */
	public static function audit( array $args ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';

		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$query   = isset( $args['query'] ) ? trim( (string) $args['query'] ) : '';
		$days    = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;
		$urls    = isset( $args['competitor_urls'] ) && is_array( $args['competitor_urls'] ) ? array_slice( array_filter( array_map( 'strval', $args['competitor_urls'] ) ), 0, self::MAX_COMPETITORS ) : array();

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return new WP_Error( 'no_permalink', 'Post has no permalink.', array( 'status' => 422 ) );
		}

		$notes = array();

		// ----- Own page fingerprint (rendered HTML = what Google/AI sees) -----
		$own_html = self::fetch( $permalink, true );
		if ( is_wp_error( $own_html ) ) {
			return new WP_Error( 'own_fetch_failed', 'Could not fetch the page\'s own rendered HTML: ' . $own_html->get_error_message(), array( 'status' => 502 ) );
		}
		$own = self::fingerprint( $own_html, $permalink );
		$own['title'] = (string) $post->post_title;
		$seo_title    = (string) get_post_meta( $post_id, 'rank_math_title', true );
		if ( '' === $seo_title ) {
			$seo_title = (string) get_post_meta( $post_id, '_yoast_wpseo_title', true );
		}
		$own['seo_title'] = $seo_title;

		// ----- Competitor fingerprints -----
		$competitors = array();
		foreach ( $urls as $u ) {
			$check = self::guard_external_url( $u );
			if ( is_wp_error( $check ) ) {
				$competitors[] = array( 'url' => $u, 'error' => $check->get_error_message() );
				continue;
			}
			$html = self::fetch( $u, false );
			if ( is_wp_error( $html ) ) {
				$competitors[] = array( 'url' => $u, 'error' => $html->get_error_message() );
				continue;
			}
			$fp        = self::fingerprint( $html, $u );
			$fp['url'] = $u;
			$competitors[] = $fp;
		}
		// v0.73.0: a competitor URL that returns a JS app shell or a cookie wall
		// fetches "successfully" and fingerprints as a near-empty page. Left in
		// the comparative set it makes this page look far ahead of the field and
		// pollutes fan-out with junk headings ("Page Not Found", cookie notices).
		// Observed on erofwhiterock: ACEP came back with 4 words, Ally with 18,
		// and the resulting score was materially wrong in our favour.
		$shells  = array();
		$fetched = array();
		foreach ( $competitors as $c ) {
			if ( ! empty( $c['error'] ) ) {
				continue;
			}
			if ( (int) ( $c['word_count'] ?? 0 ) < self::MIN_COMPETITOR_WORDS ) {
				$shells[] = array( 'url' => $c['url'], 'word_count' => (int) ( $c['word_count'] ?? 0 ) );
				continue;
			}
			$fetched[] = $c;
		}
		if ( ! empty( $shells ) ) {
			$labels = array();
			foreach ( $shells as $sh ) {
				$labels[] = sprintf( '%s (%d words)', $sh['url'], $sh['word_count'] );
			}
			$notes[] = sprintf(
				'EXCLUDED %d competitor URL(s) from comparative scoring: under %d words, so the fetch returned a JS shell or cookie wall rather than the article: %s. Comparative dimensions are scored against the remaining %d.',
				count( $shells ),
				self::MIN_COMPETITOR_WORDS,
				implode( '; ', $labels ),
				count( $fetched )
			);
		}
		if ( empty( $fetched ) && ! empty( $urls ) ) {
			$notes[] = 'No competitor page could be fetched or all were shells (see competitors[].error and the exclusion note). Scores below are absolute-only.';
		}
		if ( empty( $urls ) ) {
			$notes[] = 'No competitor_urls supplied — absolute-mode audit. For the full "beat them" analysis, web-search the target query and pass the top 2-5 ranking URLs.';
		}

		// ----- GSC grounding (own queries for this page) -----
		$gsc_rows = array();
		if ( class_exists( 'CC_Assistant_GSC' ) || file_exists( CC_ASSISTANT_DIR . 'includes/class-gsc.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
			if ( method_exists( 'CC_Assistant_GSC', 'page_queries' ) && CC_Assistant_GSC::is_connected() ) {
				$pq = CC_Assistant_GSC::page_queries( array( 'post_id' => $post_id, 'days' => $days, 'limit' => 100 ) );
				if ( ! is_wp_error( $pq ) && ! empty( $pq['queries'] ) && is_array( $pq['queries'] ) ) {
					$gsc_rows = $pq['queries'];
				} elseif ( is_array( $pq ) && ! empty( $pq['rows'] ) ) {
					$gsc_rows = $pq['rows'];
				}
			}
		}
		if ( empty( $gsc_rows ) ) {
			$notes[] = 'No GSC query rows available for this page (not connected, or no data in window) — title_ctr and the GSC side of fanout_coverage are skipped.';
		}

		// ----- Intent classification (doctrine: one page = one intent) -----
		require_once CC_ASSISTANT_DIR . 'includes/class-page-intent.php';
		$page_intent  = CC_Assistant_Page_Intent::classify_page( $post_id, array( 'format' => $own['format'] ) );
		$query_intent = CC_Assistant_Page_Intent::classify_query( $query );

		// ----- Score the dimensions -----
		$weights    = self::weights();
		$dimensions = array();
		$actions    = array();

		$dimensions['info_gain']        = self::dim_info_gain( $own, $fetched, $actions );
		$dimensions['intent_format']    = self::dim_intent_format( $own, $fetched, $query, $page_intent, $query_intent, $actions );
		$dimensions['fanout_coverage']  = self::dim_fanout( $own, $fetched, $gsc_rows, $actions );
		$dimensions['freshness']        = self::dim_freshness( $own, $fetched, $actions );
		$dimensions['extractability']   = self::dim_extractability( $own, $actions );
		$dimensions['eeat']             = self::dim_eeat( $own, $fetched, $actions );
		$dimensions['effort_media']     = self::dim_effort_media( $own, $fetched, $actions );
		$dimensions['title_ctr']        = self::dim_title_ctr( $own, $gsc_rows, $actions );
		$dimensions['internal_support'] = self::dim_internal_support( $post_id, $actions );
		$dimensions['schema_hygiene']   = self::dim_schema_hygiene( $own, $actions );

		// Weighted score over AVAILABLE dimensions only (a skipped dimension
		// must not silently drag or inflate the total).
		$total_weight = 0.0;
		$weighted_sum = 0.0;
		foreach ( $dimensions as $key => $d ) {
			if ( ! empty( $d['skipped'] ) ) {
				continue;
			}
			$w             = isset( $weights[ $key ] ) ? (float) $weights[ $key ] : 0.0;
			$total_weight += $w;
			$weighted_sum += $w * (float) $d['score'];
		}
		$score = $total_weight > 0 ? (int) round( $weighted_sum / $total_weight ) : 0;

		// Prioritize actions: weight x normalized gap, computed when each
		// dimension pushed its actions (priority already attached). Sort desc.
		usort( $actions, function ( $a, $b ) {
			return ( $b['priority'] <=> $a['priority'] );
		} );

		$verdict = 'heuristic_review';

		return array(
			'post_id'      => $post_id,
			'url'          => $permalink,
			'query'        => $query,
			'query_intent' => $query_intent,
			'page_intent'  => $page_intent,
			'score'       => $score,
			'verdict'     => $verdict,
			'assessment_type' => 'local_heuristic_checklist_not_ranking_prediction',
			'policy_version' => CC_Assistant_Content_Evidence::POLICY_VERSION,
			'dimensions'  => $dimensions,
			'actions'     => array_slice( $actions, 0, 25 ),
			'competitors' => array_map( array( __CLASS__, 'competitor_summary' ), $competitors ),
			'weights'     => $weights,
			'notes'       => array_merge( $notes, array(
				'These local heuristics cannot predict ranking or explain why a competitor ranks. GEO experiments measured generative-engine visibility, not effects on organic search rankings. Source: https://arxiv.org/html/2311.09735v3',
			) ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Fetching
	 * ------------------------------------------------------------------- */

	/**
	 * SSRF/abuse guard for caller-supplied competitor URLs: http(s) only, a
	 * resolvable public host, not this site, no bare IPs / local TLDs.
	 */
	private static function guard_external_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'bad_url', 'Competitor URL must be absolute http(s).' );
		}
		$host = strtolower( $parts['host'] );
		if ( filter_var( trim( $host, '[]' ), FILTER_VALIDATE_IP ) ) {
			return new WP_Error( 'bad_url', 'IP-literal URLs are not allowed.' );
		}
		foreach ( array( '.local', '.test', '.localhost', '.invalid', '.internal' ) as $tld ) {
			if ( 'localhost' === $host || substr( $host, -strlen( $tld ) ) === $tld ) {
				return new WP_Error( 'bad_url', 'Local/internal hosts are not allowed.' );
			}
		}
		$own_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( $host === $own_host || 'www.' . $own_host === $host || $host === 'www.' . $own_host ) {
			return new WP_Error( 'bad_url', 'Competitor URL points at this site itself.' );
		}
		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'bad_url', 'Competitor URL must resolve to a public HTTP(S) destination on an allowed port.' );
		}
		return true;
	}

	/**
	 * Fetch a page body with the plugin's browser UA, size cap, and a 6h
	 * transient cache (skipped for the own-page fetch, which must be fresh).
	 */
	private static function fetch( $url, $is_own ) {
		$cache_key = 'cc_winaudit_' . md5( $url );
		if ( ! $is_own ) {
			$cached = get_transient( $cache_key );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}
		$ua       = defined( 'CC_ASSISTANT_HTTP_UA' ) ? CC_ASSISTANT_HTTP_UA : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
		$fetcher = $is_own ? 'wp_remote_get' : 'wp_safe_remote_get';
		$response = $fetcher(
			$url,
			array(
				'timeout'             => self::FETCH_TIMEOUT,
				'redirection'         => 3,
				'user-agent'          => $ua,
				'limit_response_size' => self::MAX_BODY_BYTES,
				'sslverify'           => ! $is_own, // own .local dev hosts lack a CA chain
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'fetch_http_' . $code, sprintf( 'HTTP %d fetching %s', $code, $url ) );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return new WP_Error( 'fetch_empty', 'Empty body from ' . $url );
		}
		if ( ! $is_own ) {
			set_transient( $cache_key, $body, self::CACHE_TTL );
		}
		return $body;
	}

	/* ---------------------------------------------------------------------
	 * Fingerprinting (works on ANY html string — own page or competitor)
	 * ------------------------------------------------------------------- */

	public static function fingerprint( $html, $url = '' ) {
		// Strip site chrome so nav/footer text doesn't pollute counts.
		$main = $html;
		if ( method_exists( 'CC_Assistant_Pre_Publish', 'isolate_main_content' ) ) {
			$isolated = CC_Assistant_Pre_Publish::isolate_main_content( $html );
			if ( is_string( $isolated ) && strlen( $isolated ) > 500 ) {
				$main = $isolated;
			}
		}
		$plain      = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $main ) ) );
		$word_count = $plain ? str_word_count( $plain ) : 0;

		// Headings.
		$headings = array();
		if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $main, $hm, PREG_SET_ORDER ) ) {
			foreach ( $hm as $h ) {
				$txt = trim( wp_strip_all_tags( $h[2] ) );
				if ( '' !== $txt ) {
					$headings[] = array( 'level' => (int) $h[1], 'text' => $txt );
				}
			}
		}
		$question_headings = 0;
		foreach ( $headings as $h ) {
			if ( preg_match( '/\?|^(?:how|what|why|when|where|which|who|can|should|is|are|does|do)\b/i', $h['text'] ) ) {
				$question_headings++;
			}
		}

		// Counts.
		$lists       = preg_match_all( '/<(?:ul|ol)\b/i', $main );
		$tables      = preg_match_all( '/<table\b/i', $main );
		$images      = preg_match_all( '/<img\b/i', $main );
		$videos      = preg_match_all( '#<(?:video\b|iframe[^>]+(?:youtube\.com|youtu\.be|vimeo\.com|wistia))#i', $main );
		$blockquotes = preg_match_all( '/<(?:blockquote|q)\b/i', $main );

		// Statistics density: numbers with units/percent/currency/ratios.
		// Note: the unit group ends with (?!\w), NOT \b — a \b after "%" (a
		// non-word char) never matches, which silently dropped every "13%"-style
		// stat (caught by the v0.41 smoke tests).
		$stats_count = preg_match_all( '/\b\d{1,4}(?:[,.]\d+)?\s?(?:%|percent|mg|mmHg|ml|kg|lb|million|billion|x|times|out of \d+|in \d+)(?!\w)|\$\s?\d/iu', $plain );

		// Years mentioned (freshness-of-data proxy) + newest visible date.
		$current_year = (int) gmdate( 'Y' );
		$recent_year_mentions = 0;
		if ( preg_match_all( '/\b(20\d{2})\b/', $plain, $ym ) ) {
			foreach ( $ym[1] as $y ) {
				if ( (int) $y >= $current_year - 1 ) {
					$recent_year_mentions++;
				}
			}
		}
		$newest_date = '';
		if ( preg_match_all( '/datetime=["\'](\d{4}-\d{2}-\d{2})/i', $html, $dm ) ) {
			$newest_date = max( $dm[1] );
		}
		if ( preg_match_all( '/"date(?:Modified|Published)"\s*:\s*"(\d{4}-\d{2}-\d{2})/i', $html, $jm ) ) {
			$jd = max( $jm[1] );
			if ( '' === $newest_date || $jd > $newest_date ) {
				$newest_date = $jd;
			}
		}

		// Outbound links + authority citations.
		$own_host        = strtolower( (string) wp_parse_url( '' !== $url ? $url : home_url(), PHP_URL_HOST ) );
		$outbound        = 0;
		$authority_links = 0;
		$auth_hosts      = method_exists( 'CC_Assistant_SEO_Tools', 'authority_hosts' ) ? CC_Assistant_SEO_Tools::authority_hosts() : array( '.gov', '.edu' );
		if ( preg_match_all( '/<a\b[^>]+href=["\'](https?:\/\/[^"\']+)["\']/i', $main, $am ) ) {
			foreach ( $am[1] as $href ) {
				$h = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
				if ( '' === $h || $h === $own_host ) {
					continue;
				}
				$outbound++;
				foreach ( $auth_hosts as $ah ) {
					if ( CC_Assistant_Content_Evidence::host_matches( $h, $ah ) ) {
						$authority_links++;
						break;
					}
				}
			}
		}

		// Byline / reviewed-by markers (Unicode + Spanish, mirrors eeat audit).
		$byline = (bool) preg_match( '/\b(?:by|written by|reviewed by|medically reviewed by|author[:\s]|por|escrito por|revisado por|redactado por)[\s:]*\p{Lu}[\p{L}\.\-]+\s+\p{Lu}[\p{L}\.\-]+/u', $plain );

		// Schema types present.
		$schema_types = array();
		$schema_valid = true;
		if ( preg_match_all( '#<script[^>]+application/ld\+json[^>]*>([\s\S]*?)</script>#i', $html, $sm ) ) {
			foreach ( $sm[1] as $blob ) {
				$decoded = json_decode( trim( $blob ), true );
				if ( null === $decoded ) {
					$schema_valid = false;
					continue;
				}
				$walk = function ( $node ) use ( &$walk, &$schema_types ) {
					if ( ! is_array( $node ) ) {
						return;
					}
					if ( isset( $node['@type'] ) ) {
						foreach ( (array) $node['@type'] as $t ) {
							if ( is_string( $t ) ) {
								$schema_types[ $t ] = true;
							}
						}
					}
					foreach ( $node as $v ) {
						if ( is_array( $v ) ) {
							$walk( $v );
						}
					}
				};
				$walk( $decoded );
			}
		}

		// First content paragraph (answer-first check).
		$first_para_words = 0;
		if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', $main, $pm ) ) {
			$ptxt             = trim( wp_strip_all_tags( $pm[1] ) );
			$first_para_words = $ptxt ? str_word_count( $ptxt ) : 0;
		}

		// Term set for coverage diffs.
		$terms = method_exists( 'CC_Assistant_Similarity', 'terms_for_text' )
			? CC_Assistant_Similarity::terms_for_text( $plain )
			: array();

		return array(
			'word_count'           => $word_count,
			'headings'             => array_slice( $headings, 0, 60 ),
			'h2_count'             => count( array_filter( $headings, function ( $h ) { return 2 === $h['level']; } ) ),
			'question_headings'    => $question_headings,
			'lists'                => (int) $lists,
			'tables'               => (int) $tables,
			'images'               => (int) $images,
			'videos'               => (int) $videos,
			'blockquotes'          => (int) $blockquotes,
			'stats_count'          => (int) $stats_count,
			'stats_per_1k'         => $word_count > 0 ? round( $stats_count / max( 1, $word_count ) * 1000, 2 ) : 0,
			'recent_year_mentions' => $recent_year_mentions,
			'newest_date'          => $newest_date,
			'outbound_links'       => $outbound,
			'authority_links'      => $authority_links,
			'byline'               => $byline,
			'schema_types'         => array_keys( $schema_types ),
			'schema_valid'         => $schema_valid,
			'first_para_words'     => $first_para_words,
			'terms'                => $terms,
			'format'               => self::classify_format( $headings, $lists, $tables, $plain ),
		);
	}

	private static function classify_format( $headings, $lists, $tables, $plain ) {
		$h2s      = array_filter( $headings, function ( $h ) { return 2 === $h['level']; } );
		$numbered = 0;
		foreach ( $h2s as $h ) {
			if ( preg_match( '/^\d+[\.\)]\s/', $h['text'] ) ) {
				$numbered++;
			}
		}
		if ( $numbered >= 3 ) {
			return 'listicle';
		}
		// Service signals FIRST: a service page that embeds a decision table or
		// "X vs Y" guidance is still a service page. (v0.42.1 fix: the old order
		// reclassified a service hub as "comparison" the moment it gained a
		// comparison table, tanking intent_format on a page that had improved.)
		if ( preg_match( '/\b(?:call (?:us|now)|book (?:an|your)|schedule|contact us|get directions|visit us)\b/i', $plain ) ) {
			return 'service';
		}
		if ( preg_match( '/\bstep \d|\bstep-by-step\b/i', $plain ) ) {
			return 'howto';
		}
		if ( $tables >= 1 && preg_match( '/\bvs\.?\b|\bversus\b|\bcompared? (?:to|with)\b/i', $plain ) ) {
			return 'comparison';
		}
		return 'article';
	}

	private static function competitor_summary( $c ) {
		if ( ! empty( $c['error'] ) ) {
			return array( 'url' => $c['url'], 'error' => $c['error'] );
		}
		return array(
			'url'               => $c['url'],
			'format'            => $c['format'],
			'word_count'        => $c['word_count'],
			'h2_count'          => $c['h2_count'],
			'stats_per_1k'      => $c['stats_per_1k'],
			'images'            => $c['images'],
			'videos'            => $c['videos'],
			'tables'            => $c['tables'],
			'authority_links'   => $c['authority_links'],
			'byline'            => $c['byline'],
			'newest_date'       => $c['newest_date'],
			'question_headings' => $c['question_headings'],
		);
	}

	/* ---------------------------------------------------------------------
	 * Dimensions. Each returns {score 0-100, detail, skipped?} and may push
	 * prioritized actions: {dimension, priority, action, evidence}.
	 * ------------------------------------------------------------------- */

	private static function push_action( &$actions, $dimension, $score, $action, $evidence ) {
		$weights  = self::weights();
		$w        = isset( $weights[ $dimension ] ) ? (float) $weights[ $dimension ] : 1.0;
		$actions[] = array(
			'assessment_type' => 'heuristic_suggestion_requires_review',
			'dimension' => $dimension,
			'priority'  => round( $w * ( 100 - $score ) / 100, 1 ),
			'action'    => $action,
			'evidence'  => $evidence,
		);
	}

	private static function best( $fetched, $key ) {
		$max = 0;
		foreach ( $fetched as $c ) {
			if ( isset( $c[ $key ] ) && $c[ $key ] > $max ) {
				$max = $c[ $key ];
			}
		}
		return $max;
	}

	private static function dim_info_gain( $own, $fetched, &$actions ) {
        return array( 'score' => null, 'skipped' => true, 'assessment' => 'requires_claim_comparison',
            'detail' => array( 'stats_per_1k' => $own['stats_per_1k'], 'quotes' => $own['blockquotes'],
                'authority_links' => $own['authority_links'], 'competitors_examined' => count( $fetched ) ),
            'reason' => 'Counts are surface observations. They do not establish source support, expertise, first-party ownership, novelty or reader value. Use content_research and a sourced contribution brief.' );
    }

	private static function dim_intent_format( $own, $fetched, $query, $page_intent, $query_intent, &$actions ) {
		$pf = isset( $page_intent['family'] ) ? $page_intent['family'] : 'unknown';
		$qf = isset( $query_intent['family'] ) ? $query_intent['family'] : 'unknown';

		// DOCTRINE GUARD (v0.43): if the QUERY intent and the PAGE intent are
		// different families, this is a mis-targeted audit. A service/conversion
		// page should NOT be penalized for facing informational-article
		// competitors on an informational query — that query belongs on a blog
		// spoke, not this page. Warn (neutral 65), do not tank to 35.
		if ( '' !== $query && 'unknown' !== $qf && 'unknown' !== $pf && $qf !== $pf ) {
			$align = CC_Assistant_Page_Intent::families_align( $qf, $pf );
			self::push_action(
				$actions,
				'intent_format',
				65,
				sprintf( 'Query/page intent mismatch: "%s" is %s-intent (%s) but this is a %s page (%s-intent). %s', $query, $qf, $query_intent['intent'], $page_intent['page_type'], $pf, $align['advice'] ),
				'One page = one intent. Chasing a research query with a conversion page (or vice-versa) ranks for neither; for informational "what/how/why/when" queries the AI Overview also eats the click.'
			);
			return array(
				'score'   => 65,
				'detail'  => array(
					'own_format'   => $own['format'],
					'query_intent' => $query_intent['intent'],
					'page_intent'  => $page_intent['natural_intent'],
					'note'         => 'Query and page are different intent families — scored neutral (not penalized). Build a spoke for this query and target the page at same-intent queries.',
				),
				'skipped' => false,
			);
		}

		if ( empty( $fetched ) ) {
			return array( 'score' => 50, 'detail' => array( 'own_format' => $own['format'], 'query_intent' => $query_intent['intent'], 'page_intent' => $page_intent['natural_intent'], 'note' => 'No competitors fetched - format match unknown, neutral 50.' ), 'skipped' => false );
		}
		$counts = array();
		foreach ( $fetched as $c ) {
			$counts[ $c['format'] ] = ( $counts[ $c['format'] ] ?? 0 ) + 1;
		}
		arsort( $counts );
		$dominant = (string) array_key_first( $counts );
		$match    = ( $own['format'] === $dominant );
		$score    = $match ? 95 : 35;
		if ( ! $match ) {
			self::push_action( $actions, 'intent_format', $score, sprintf( 'Format mismatch: the pages winning this query are "%s" but yours is "%s". This usually needs a format change, not copy tweaks%s.', $dominant, $own['format'], '' !== $query ? sprintf( ' (query: "%s")', $query ) : '' ), 'Intent/format match is a top-3 verified differentiator; 40.9% of commercial-query AI citations are listicles vs 45.5% of informational citations being articles.' );
		}
		return array( 'score' => $score, 'detail' => array( 'own_format' => $own['format'], 'dominant_competitor_format' => $dominant, 'competitor_formats' => $counts, 'query_intent' => $query_intent['intent'], 'page_intent' => $page_intent['natural_intent'] ) );
	}

	/**
	 * Headings that are site furniture, not subtopics.
	 *
	 * v0.73.0: fan-out previously treated every competitor h2/h3 as a content
	 * gap, so "Post navigation", "Related Posts" and "Proudly Supporting Our
	 * Community" were reported as subtopics this page was missing. That both
	 * depressed the score and produced action items which, if followed, would
	 * have added navigation furniture as if it were content.
	 */
	private static function is_boilerplate_heading( $text ) {
		$t = trim( mb_strtolower( wp_strip_all_tags( (string) $text ) ) );
		if ( '' === $t ) {
			return true;
		}
		$exact = array(
			'post navigation', 'posts navigation', 'related posts', 'related articles',
			'recent posts', 'recent articles', 'you may also like', 'more from this site',
			'leave a comment', 'leave a reply', 'comments', 'post comment',
			'share this', 'share this post', 'follow us', 'newsletter', 'subscribe',
			'categories', 'archives', 'tags', 'search', 'menu', 'main menu', 'footer',
			'resources', 'quick links', 'useful links', 'sitemap', 'breadcrumb',
			'page not found', 'error 404', '404', 'not found',
			'privacy policy', 'terms of service', 'terms and conditions', 'cookie policy',
			'contact us', 'about us', 'careers', 'locations', 'hours',
			'frequently asked questions', 'faq', 'faqs',
			'table of contents', 'in this article', 'on this page',
			'proudly supporting our community', 'testimonials', 'reviews',
		);
		if ( in_array( $t, $exact, true ) ) {
			return true;
		}
		// Cookie/consent and legal furniture, wherever it appears.
		if ( preg_match( '/\b(cookie|consent|gdpr|accept all|manage preferences)\b/', $t ) ) {
			return true;
		}
		// A single word is a label, not a subtopic.
		if ( count( preg_split( '/\s+/', $t ) ) < 2 ) {
			return true;
		}
		return false;
	}

	private static function dim_fanout( $own, $fetched, $gsc_rows, &$actions ) {
		$own_terms = array_flip( $own['terms'] );
		$missing_subtopics = array();

		if ( ! empty( $fetched ) ) {
			// Union of competitor HEADING terms not covered by our body.
			$counts = array();
			foreach ( $fetched as $c ) {
				foreach ( (array) $c['headings'] as $h ) {
					if ( $h['level'] < 2 || $h['level'] > 3 ) {
						continue;
					}
					if ( self::is_boilerplate_heading( $h['text'] ) ) {
						continue;
					}
					$h_terms = method_exists( 'CC_Assistant_Similarity', 'terms_for_text' ) ? CC_Assistant_Similarity::terms_for_text( $h['text'] ) : array();
					$covered = 0;
					foreach ( $h_terms as $t ) {
						if ( isset( $own_terms[ $t ] ) ) {
							$covered++;
						}
					}
					if ( ! empty( $h_terms ) && $covered / count( $h_terms ) < 0.5 ) {
						$key = $h['text'];
						$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
					}
				}
			}
			arsort( $counts );
			$missing_subtopics = array_slice( array_keys( $counts ), 0, 15 );
		}

		// GSC: queries with impressions whose tokens we barely cover.
		$missing_queries = array();
		foreach ( $gsc_rows as $row ) {
			$q   = isset( $row['query'] ) ? (string) $row['query'] : '';
			$imp = isset( $row['impressions'] ) ? (int) $row['impressions'] : 0;
			if ( '' === $q || $imp < 10 ) {
				continue;
			}
			$q_terms = method_exists( 'CC_Assistant_Similarity', 'terms_for_text' ) ? CC_Assistant_Similarity::terms_for_text( $q ) : array();
			if ( empty( $q_terms ) ) {
				continue;
			}
			$covered = 0;
			foreach ( $q_terms as $t ) {
				if ( isset( $own_terms[ $t ] ) ) {
					$covered++;
				}
			}
			if ( $covered / count( $q_terms ) < 0.6 ) {
				$missing_queries[] = array( 'query' => $q, 'impressions' => $imp );
			}
		}
		$missing_queries = array_slice( $missing_queries, 0, 10 );

		$gap_count = count( $missing_subtopics ) + count( $missing_queries );
		$score     = max( 0, 100 - $gap_count * 8 );

		if ( ! empty( $missing_subtopics ) ) {
			self::push_action( $actions, 'fanout_coverage', $score, sprintf( 'Competitors cover subtopics your page does not: %s. Add a section for each that fits this page\'s intent (or a linked supporting page).', implode( ' | ', array_slice( $missing_subtopics, 0, 6 ) ) ), 'Pages covering fan-out subtopics are 161% more likely to be AI-cited (Surfer 10k-keyword study).' );
		}
		if ( ! empty( $missing_queries ) ) {
			self::push_action( $actions, 'fanout_coverage', $score, sprintf( 'Google already shows this page for queries it barely addresses: %s. Work those phrasings into existing sections.', implode( ', ', array_map( function ( $m ) { return '"' . $m['query'] . '"'; }, array_slice( $missing_queries, 0, 4 ) ) ) ), 'Own GSC impressions = demand already attributed to this URL.' );
		}
		return array( 'score' => $score, 'detail' => array( 'missing_subtopics' => $missing_subtopics, 'missing_queries' => $missing_queries ) );
	}

	private static function dim_freshness( $own, $fetched, &$actions ) {
        $stamp = ! empty( $own['newest_date'] ) ? strtotime( $own['newest_date'] ) : false;
        return array( 'score' => null, 'skipped' => true, 'assessment' => 'requires_fact_review',
            'detail' => array( 'newest_date' => $own['newest_date'], 'future_date' => $stamp && $stamp > time(),
                'recent_year_mentions' => $own['recent_year_mentions'] ),
            'reason' => 'A date or current-year mention does not establish factual freshness. Check important claims against dated sources before recommending an update.' );
    }

	private static function dim_extractability( $own, &$actions ) {
		$score = 0;
		// Answer-first: a tight first paragraph (15-70 words) reads as a direct answer.
		if ( $own['first_para_words'] >= 15 && $own['first_para_words'] <= 70 ) {
			$score += 30;
		} elseif ( $own['first_para_words'] > 0 ) {
			$score += 10;
		}
		if ( $own['question_headings'] >= 2 ) {
			$score += 30;
		} elseif ( $own['question_headings'] >= 1 ) {
			$score += 15;
		}
		if ( $own['lists'] >= 1 ) {
			$score += 20;
		}
		if ( $own['tables'] >= 1 ) {
			$score += 10;
		}
		if ( $own['h2_count'] >= 3 ) {
			$score += 10;
		}
		$score = min( 100, $score );
		if ( $score < 60 ) {
			self::push_action( $actions, 'extractability', $score, 'Restructure for extraction: open with a direct 2-4 sentence answer, use question-shaped H2/H3s with self-contained 40-60 word answers beneath, and convert dense prose to lists/tables.', 'Answer-first, self-contained chunks are the consistent AEO pattern (mechanism: passage-level retrieval).' );
		}
		return array( 'score' => $score, 'detail' => array( 'first_para_words' => $own['first_para_words'], 'question_headings' => $own['question_headings'], 'lists' => $own['lists'], 'tables' => $own['tables'] ) );
	}

	private static function dim_eeat( $own, $fetched, &$actions ) {
		$no_bylines = (bool) get_option( 'cc_assistant_policy_no_bylines', false );
		$score      = 0;
		if ( $no_bylines ) {
			// Site policy bans visible bylines (e.g. pending physician consent):
			// score on citations only, never recommend adding a byline.
			$score = min( 100, $own['authority_links'] * 25 );
			if ( $own['authority_links'] < 2 ) {
				self::push_action( $actions, 'eeat', $score, 'Add citations to recognized authorities (the byline path is disabled by site policy, so sourcing carries the full E-E-A-T load here).', 'Outbound authority citations are the E-E-A-T artifact available to this site.' );
			}
			return array( 'score' => $score, 'detail' => array( 'policy_no_bylines' => true, 'authority_links' => $own['authority_links'] ) );
		}
		$score += $own['byline'] ? 50 : 0;
		$score += min( 50, $own['authority_links'] * 15 );
		$score  = min( 100, $score );

		$comp_with_byline = 0;
		foreach ( $fetched as $c ) {
			if ( ! empty( $c['byline'] ) ) {
				$comp_with_byline++;
			}
		}
		if ( ! $own['byline'] && $comp_with_byline > 0 ) {
			self::push_action( $actions, 'eeat', $score, sprintf( '%d of %d competitors show a named author/reviewer and you do not. Add a credentialed byline or "Medically reviewed by" line (with consent).', $comp_with_byline, count( $fetched ) ), 'YMYL case studies show large gains from credentialed review (e.g. +156% after adding MD review). Indirect but consistently supported.' );
		}
		return array( 'score' => $score, 'detail' => array( 'byline' => $own['byline'], 'authority_links' => $own['authority_links'], 'competitors_with_byline' => $comp_with_byline ) );
	}

	private static function dim_effort_media( $own, $fetched, &$actions ) {
		$score = min( 100, $own['images'] * 20 + $own['videos'] * 30 + $own['tables'] * 15 );
		if ( ! empty( $fetched ) ) {
			// v0.73.0: cap the competitor comparison. A raw <img> count includes
			// icons, logos, avatars and tracking pixels, so Penn Highlands
			// fingerprinted at 124 "images" and made every page look
			// under-illustrated. No real article carries more than ~25 content
			// images, so anything above that is chrome, not effort.
			$bi = min( self::MAX_CREDIBLE_IMAGES, self::best( $fetched, 'images' ) );
			$bv = min( self::MAX_CREDIBLE_VIDEOS, self::best( $fetched, 'videos' ) );
			if ( $bi > $own['images'] || $bv > $own['videos'] ) {
				$score = max( 0, $score - 15 );
				self::push_action( $actions, 'effort_media', $score, sprintf( 'Competitors out-media you (best: %d images / %d videos vs your %d / %d). Add ORIGINAL visuals - photos, a short video, or a data table; stock filler does not count as effort.', $bi, $bv, $own['images'], $own['videos'] ), 'Original multimedia is a verified content-effort signal; video is the most-cited format in AI Overviews (Surfer 46M citations).' );
			}
		}
		if ( 0 === $own['images'] ) {
			self::push_action( $actions, 'effort_media', $score, 'Page has zero images. Add at least one relevant, original image with descriptive alt text.', 'Image-free pages read as low-effort to both users and effort-scoring systems.' );
		}
		return array( 'score' => $score, 'detail' => array( 'images' => $own['images'], 'videos' => $own['videos'], 'tables' => $own['tables'] ) );
	}

	private static function dim_title_ctr( $own, $gsc_rows, &$actions ) {
		if ( empty( $gsc_rows ) ) {
			return array( 'score' => 0, 'detail' => array( 'note' => 'No GSC rows - skipped.' ), 'skipped' => true );
		}
		$title = '' !== $own['seo_title'] ? $own['seo_title'] : $own['title'];
		$title_terms = array_flip( method_exists( 'CC_Assistant_Similarity', 'terms_for_text' ) ? CC_Assistant_Similarity::terms_for_text( $title ) : array() );

		// Impression-weighted token coverage of the top queries.
		$token_imp = array();
		foreach ( array_slice( $gsc_rows, 0, 25 ) as $row ) {
			$q   = isset( $row['query'] ) ? (string) $row['query'] : '';
			$imp = isset( $row['impressions'] ) ? (int) $row['impressions'] : 0;
			foreach ( method_exists( 'CC_Assistant_Similarity', 'terms_for_text' ) ? CC_Assistant_Similarity::terms_for_text( $q ) : array() as $t ) {
				$token_imp[ $t ] = ( $token_imp[ $t ] ?? 0 ) + $imp;
			}
		}
		arsort( $token_imp );
		$top     = array_slice( $token_imp, 0, 10, true );
		$total   = array_sum( $top );
		$covered = 0;
		$missing = array();
		foreach ( $top as $t => $imp ) {
			if ( isset( $title_terms[ $t ] ) ) {
				$covered += $imp;
			} else {
				$missing[] = $t;
			}
		}
		$score = $total > 0 ? (int) round( $covered / $total * 100 ) : 0;
		if ( ! empty( $missing ) && $score < 75 ) {
			self::push_action( $actions, 'title_ctr', $score, sprintf( 'Title misses high-impression tokens: %s. Weave them in WITHOUT dropping any token currently in the title (never trade away high-impression tokens).', implode( ', ', array_slice( $missing, 0, 5 ) ) ), 'Impression-weighted token coverage; existing house rule: audit full GSC distribution before any title pivot.' );
		}
		return array( 'score' => $score, 'detail' => array( 'title_used' => $title, 'coverage_pct' => $score, 'missing_tokens' => array_slice( $missing, 0, 8 ) ) );
	}

	private static function dim_internal_support( $post_id, &$actions ) {
		global $wpdb;
		// v0.73.1: this queried `cc_internal_links`, a table that is created
		// NOWHERE in the plugin. The real link graph is `cc_link_graph`. The
		// existence check therefore always failed and this dimension returned
		// skipped=true on every page of every site since it shipped - it has
		// never once contributed to a win_audit score.
		//
		// Delegating to the link-graph class rather than re-issuing the query
		// keeps the counting rule in one place: DISTINCT source_post_id, so ten
		// links from one page count as one supporting page, not ten.
		require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
		$table  = $wpdb->prefix . 'cc_link_graph';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		if ( ! $exists ) {
			return array( 'score' => 0, 'detail' => array( 'note' => 'Internal link graph table not present - run links_rebuild. Skipped.' ), 'skipped' => true );
		}
		$inbound = (int) CC_Assistant_Internal_Links::inbound_count( $post_id );
		$score   = min( 100, $inbound * 25 );
		if ( $inbound < 2 ) {
			self::push_action( $actions, 'internal_support', $score, sprintf( 'Only %d internal link(s) point here. Add inline links from related high-authority pages (woven into existing sentences - never "Related guides" blocks).', $inbound ), 'Cluster support is how a site ranks for fan-out queries; inline-only linking is the house rule.' );
		}
		return array( 'score' => $score, 'detail' => array( 'inbound_internal_links' => $inbound, 'source' => 'cc_link_graph (DISTINCT source pages)' ) );
	}

	private static function dim_schema_hygiene( $own, &$actions ) {
		$score = 0;
		if ( ! empty( $own['schema_types'] ) ) {
			$score += 60;
		}
		if ( $own['schema_valid'] ) {
			$score += 40;
		}
		if ( ! $own['schema_valid'] ) {
			self::push_action( $actions, 'schema_hygiene', $score, 'A JSON-LD block on the page does not parse. Fix the syntax (validity only - schema is NOT an AI-citation lever, so do not invest beyond hygiene).', 'Ahrefs n=1885: adding schema produced no citation uplift (-4.6% vs control).' );
		}
		return array( 'score' => min( 100, $score ), 'detail' => array( 'schema_types' => $own['schema_types'], 'schema_valid' => $own['schema_valid'] ) );
	}
}
