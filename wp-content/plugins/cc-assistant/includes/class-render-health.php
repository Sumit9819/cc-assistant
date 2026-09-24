<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.68 — Render Health Guard: does the page still WORK after a change?
 *
 * Built from a specific failure. A queued attribute edit on a Divi Plus
 * product carousel passed lint, passed human review, applied cleanly — and
 * the module's product query silently broke. The storefront rendered
 * "The products you requested could not be found." for hours. Every existing
 * check verified the WRITE (the stored bytes matched the proposal); nothing
 * verified the OUTCOME (the rendered page lost its products). The post-apply
 * audit even fetched the broken page — and only looked at its JSON-LD.
 *
 * This class captures a small set of rendered-page FACTS at queue time and
 * compares them after apply. Facts, not judgements: HTTP status, byte and
 * text length, builder empty-state strings, leaked shortcode syntax, PHP
 * error strings, and markup-anchored element counts.
 *
 * Two rules the incident taught, encoded here:
 *
 * 1. COMPARE DELTAS, NEVER ABSOLUTE PRESENCE. A localized "No products were
 *    found" string can legitimately sit inside an inline script bundle on a
 *    healthy page. The signal is a count that INCREASED across an apply, not
 *    the string existing.
 *
 * 2. COUNT MARKUP, NOT SUBSTRINGS. During diagnosis, a substring count found
 *    "24 products" on a page rendering zero — the matches were CSS selectors
 *    (.dipl_single_woo_product) inside <style> blocks. Element counts here
 *    require the token to appear inside a tag's class attribute, so a
 *    selector in a stylesheet can never masquerade as a rendered element.
 *
 * Per the automation boundary: findings are REPORTED (activity log, pending
 * row, dashboard banner). Nothing auto-reverts. The operator decides.
 */
class CC_Assistant_Render_Health {

	/**
	 * Matches render_probe's proven timeout. The original 8s was tighter than
	 * the one loopback fetch on this codebase known to work in production;
	 * an uncached builder page can render slower than that.
	 */
	const HTTP_TIMEOUT = 15;

	/**
	 * Outcome of the most recent capture() in this request, for the REST
	 * response: 'captured', or 'failed: <reason>'. Queue-time capture used to
	 * fail into a silent null — indistinguishable from success until the
	 * post-apply audit had nothing to compare, which was itself silent.
	 */
	private static $last_capture_status = '';

	public static function last_capture_status() {
		return self::$last_capture_status;
	}

	/**
	 * Queue-time captures are cached briefly so a batch of pending changes
	 * against the same post (six banner swaps in one sitting is the observed
	 * real case) costs one loopback fetch, not six.
	 */
	const CAPTURE_CACHE_TTL = 120;

	/** Baseline shrink worse than this fraction flags content_shrunk. */
	const SHRINK_RATIO = 0.70;

	/** ...and only when the absolute drop also exceeds this many chars. */
	const SHRINK_MIN_CHARS = 5000;

	/** An element token needs at least this many baseline instances before
	 *  its disappearance is treated as CRITICAL — one-off elements come and
	 *  go legitimately; a POPULATION vanishing is the failure signal. */
	const VANISH_MIN_BASELINE = 3;

	/* ---------------------------------------------------------------------
	 * Dictionaries
	 * ------------------------------------------------------------------ */

	/**
	 * Builder empty-state strings. Each is the literal prose a builder prints
	 * where content should have been — the exact text an operator eventually
	 * sees, and the highest-value needle this guard owns.
	 *
	 * "The products you requested could not be found" is verified from the
	 * live incident page (Divi Plus). The rest are the standard WooCommerce /
	 * Divi / theme no-results strings; matching is delta-based so a needle
	 * that legitimately lives on a page (search widget, script bundle) never
	 * fires on its own.
	 */
	public static function empty_state_needles() {
		return array(
			'The products you requested could not be found',
			'No products were found',
			'No Results Found',
			'Nothing Found',
			"we can't find what you're looking for",
			"we can\xE2\x80\x99t find what you\xE2\x80\x99re looking for",
		);
	}

	/**
	 * Shortcode open-tags that must never be visible in rendered output.
	 * Their presence as text means a builder module failed to parse and the
	 * raw shortcode is printing. Counted as literal text occurrences — in a
	 * HEALTHY render these strings do not exist at all (Divi renders them
	 * away), so any increase is meaningful.
	 */
	public static function shortcode_leak_needles() {
		return array( '[et_pb_section', '[et_pb_row', '[dipl_', '[difl_', '[dsm_', '[dvmd_' );
	}

	/**
	 * PHP error strings as display_errors prints them. Conservative set —
	 * plain "Warning:" appears in legitimate prose, so only unambiguous
	 * engine-error prefixes are counted.
	 */
	public static function php_error_needles() {
		return array(
			'Fatal error:',
			'Parse error:',
			'Uncaught Error',
			'Uncaught TypeError',
			'Warning: Undefined',
		);
	}

	/**
	 * Class-attribute tokens whose rendered POPULATION is tracked. Chosen for
	 * being the load-bearing repeated elements on commerce/builder pages —
	 * the things whose count dropping to zero means a section died.
	 */
	public static function element_tokens() {
		return array(
			'type-product',              // WooCommerce loop items
			'dipl_single_woo_product',   // Divi Plus product cards (the incident)
			'swiper-slide',              // any carousel slide
			'et_pb_module',              // every Divi module
			'elementor-widget',          // every Elementor widget
		);
	}

	/* ---------------------------------------------------------------------
	 * Capture (queue time)
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch the post's rendered front end and reduce it to comparable facts.
	 *
	 * NEVER returns null for a valid post id. Failure returns an array with
	 * an 'error' key instead — that error rides the pending row to the
	 * post-apply audit, which turns it into a visible "not checked" verdict.
	 * v0.68.1's smoke test failed with NO pill and no way to tell WHICH of
	 * three silent paths ate it; this closes the first of them. Failure
	 * still never blocks queueing.
	 */
	public static function capture( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			self::$last_capture_status = 'failed: no post id';
			return null;
		}

		try {
			$cache_key = 'cc_rh_baseline_' . $post_id;
			$cached    = get_transient( $cache_key );
			if ( is_array( $cached ) && isset( $cached['facts'] ) ) {
				self::$last_capture_status = 'captured (cached)';
				return $cached;
			}

			$url = get_permalink( $post_id );
			if ( empty( $url ) ) {
				self::$last_capture_status = 'failed: post has no permalink';
				return array(
					'captured_at' => current_time( 'mysql' ),
					'error'       => 'post has no permalink',
				);
			}

			$resp = self::fetch( $url );
			if ( ! is_array( $resp ) || '' === $resp['body'] ) {
				self::$last_capture_status = 'failed: loopback fetch returned no body';
				return array(
					'captured_at' => current_time( 'mysql' ),
					'url'         => $url,
					'error'       => 'loopback fetch failed or returned an empty body',
				);
			}

			$baseline = array(
				'captured_at' => current_time( 'mysql' ),
				'url'         => $url,
				'cache_state' => $resp['cache'],
				'facts'       => self::scan( $resp['body'], $resp['code'] ),
			);

			set_transient( $cache_key, $baseline, self::CAPTURE_CACHE_TTL );
			self::$last_capture_status = 'captured';
			return $baseline;
		} catch ( \Throwable $e ) {
			self::$last_capture_status = 'failed: ' . $e->getMessage();
			return array(
				'captured_at' => function_exists( 'current_time' ) ? current_time( 'mysql' ) : '',
				'error'       => 'exception during capture: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Drop the queue-time baseline cache for a post. Called after every apply:
	 * the page just changed, so a change queued moments later must capture a
	 * FRESH baseline, not the pre-apply one. Without this, change B queued
	 * within the cache TTL after change A applied would be compared against
	 * the pre-A page and blamed for A's differences.
	 */
	public static function bust_baseline_cache( $post_id ) {
		delete_transient( 'cc_rh_baseline_' . (int) $post_id );
	}

	/**
	 * Loopback fetch, cache-busted the way render_probe does it: buster args
	 * plus no-cache request headers, because SiteGround's Dynamic Cache keys
	 * on PATH and ignores query strings. The browser-shaped UA is the one the
	 * existing post-apply audit already uses successfully on SG-hosted sites.
	 */
	public static function fetch( $url ) {
		$bust = add_query_arg(
			array(
				'cc_rh'    => (string) time(),
				'cc_nonce' => uniqid( '', true ),
			),
			$url
		);
		$args = array(
			'timeout'     => self::HTTP_TIMEOUT,
			'redirection' => 0,
			'sslverify'   => true,
			'limit_response_size' => 2097152,
			'user-agent'  => CC_ASSISTANT_HTTP_UA,
			'headers'     => array(
				'Accept'        => 'text/html',
				'Cache-Control' => 'no-cache, no-store, max-age=0',
				'Pragma'        => 'no-cache',
			),
		);

		$resp = wp_remote_get( $bust, $args );
		if ( is_wp_error( $resp ) ) {
			// Custom Permalinks can 404 the buster arg; retry the clean URL.
			$resp = wp_remote_get( $url, $args );
		}
		if ( is_wp_error( $resp ) ) {
			return null;
		}

		$headers = wp_remote_retrieve_headers( $resp );
		$cache   = array();
		foreach ( array( 'x-proxy-cache', 'sg-f-cache', 'x-cache', 'x-cache-enabled' ) as $h ) {
			$v = is_object( $headers ) || is_array( $headers ) ? ( isset( $headers[ $h ] ) ? $headers[ $h ] : '' ) : '';
			if ( '' !== $v ) {
				$cache[ $h ] = is_array( $v ) ? implode( ',', $v ) : (string) $v;
			}
		}

		return array(
			'code'  => (int) wp_remote_retrieve_response_code( $resp ),
			'body'  => (string) wp_remote_retrieve_body( $resp ),
			'cache' => $cache,
		);
	}

	/* ---------------------------------------------------------------------
	 * Scan (pure — no WordPress calls, unit-tested in isolation)
	 * ------------------------------------------------------------------ */

	/**
	 * Reduce rendered HTML to the comparable fact set.
	 */
	public static function scan( $html, $http_code ) {
		$html = (string) $html;

		$empty_states = array();
		foreach ( self::empty_state_needles() as $needle ) {
			$n = substr_count( $html, $needle );
			if ( $n > 0 ) {
				$empty_states[ $needle ] = $n;
			}
		}

		$leaks = 0;
		foreach ( self::shortcode_leak_needles() as $needle ) {
			$leaks += substr_count( $html, $needle );
		}

		$php_errors = 0;
		foreach ( self::php_error_needles() as $needle ) {
			$php_errors += substr_count( $html, $needle );
		}

		$elements = array();
		foreach ( self::element_tokens() as $token ) {
			$elements[ $token ] = self::count_elements( $html, $token );
		}

		// strip_tags() keeps the CONTENT of script/style blocks, so on a
		// builder page "text length" would be dominated by inline CSS and JS,
		// not visible prose — a stylesheet change could then mask or fake a
		// content shrink. Remove those blocks before measuring.
		$visible = preg_replace( '#<script\b[^>]*>.*?</script>#is', ' ', $html );
		$visible = preg_replace( '#<style\b[^>]*>.*?</style>#is', ' ', (string) $visible );
		$visible = preg_replace( '#<noscript\b[^>]*>.*?</noscript>#is', ' ', (string) $visible );

		return array(
			'http_code'    => (int) $http_code,
			'bytes'        => strlen( $html ),
			'text_len'     => strlen( trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $visible ) ) ) ),
			'body_sha1'    => sha1( $html ),
			'empty_states' => $empty_states,
			'shortcode_leaks' => $leaks,
			'php_errors'   => $php_errors,
			'elements'     => $elements,
		);
	}

	/**
	 * Count RENDERED elements carrying a class token — the token must appear
	 * inside a tag's class attribute. A CSS selector like
	 * `.dipl_single_woo_product{...}` in a <style> block does not match, which
	 * is the entire point: substring counting mistook 24 selector hits for 24
	 * rendered products on a page rendering zero.
	 */
	public static function count_elements( $html, $token ) {
		$quoted = preg_quote( $token, '/' );
		if ( ! preg_match_all(
			'/<[a-zA-Z][a-zA-Z0-9-]*[^>]*\sclass\s*=\s*["\'][^"\']*(?<![\w-])' . $quoted . '(?![\w-])[^"\']*["\']/',
			(string) $html,
			$m
		) ) {
			return 0;
		}
		return count( $m[0] );
	}

	/* ---------------------------------------------------------------------
	 * Compare (pure — no WordPress calls, unit-tested in isolation)
	 * ------------------------------------------------------------------ */

	/**
	 * Diff two fact sets into findings: {code, severity, message, ...}.
	 *
	 * $expect_change: true on the post-apply side, where the stored content
	 * WAS mutated — a byte-identical body then means the fetch was probably
	 * served from cache and the whole comparison is unreliable, which is
	 * reported instead of silently passing.
	 */
	public static function compare( $baseline_facts, $now_facts, $expect_change = false ) {
		$findings = array();
		if ( ! is_array( $baseline_facts ) || ! is_array( $now_facts ) ) {
			return $findings;
		}

		$b = $baseline_facts;
		$n = $now_facts;

		// Stale-fetch caveat first: identical bytes after a mutation means we
		// are probably looking at cached pre-change HTML. Every other check
		// would vacuously pass, so say that instead of reporting healthy.
		if ( $expect_change
			&& isset( $b['body_sha1'], $n['body_sha1'] )
			&& '' !== $b['body_sha1']
			&& $b['body_sha1'] === $n['body_sha1'] ) {
			$findings[] = array(
				'code'     => 'possibly_stale_fetch',
				'severity' => 'warn',
				'message'  => 'Rendered HTML is byte-identical to the pre-change baseline even though content was modified. The fetch was likely served from a page cache; this comparison proves nothing either way. Purge the cache and re-check the page manually.',
			);
			return $findings;
		}

		if ( isset( $b['http_code'], $n['http_code'] )
			&& $b['http_code'] >= 200 && $b['http_code'] < 300
			&& ( $n['http_code'] < 200 || $n['http_code'] >= 300 ) ) {
			$findings[] = array(
				'code'     => 'http_status_changed',
				'severity' => 'critical',
				'message'  => sprintf( 'Page returned HTTP %d before the change and HTTP %d after.', $b['http_code'], $n['http_code'] ),
			);
		}

		$b_states = isset( $b['empty_states'] ) && is_array( $b['empty_states'] ) ? $b['empty_states'] : array();
		$n_states = isset( $n['empty_states'] ) && is_array( $n['empty_states'] ) ? $n['empty_states'] : array();
		foreach ( $n_states as $needle => $count ) {
			$before = isset( $b_states[ $needle ] ) ? (int) $b_states[ $needle ] : 0;
			if ( (int) $count > $before ) {
				$findings[] = array(
					'code'     => 'empty_state_appeared',
					'severity' => 'critical',
					'needle'   => $needle,
					'message'  => sprintf(
						'Builder empty-state text "%s" appears %d time(s) on the page, up from %d before the change — a module that used to show content is now showing its empty state.',
						$needle,
						(int) $count,
						$before
					),
				);
			}
		}

		if ( isset( $n['shortcode_leaks'] ) && (int) $n['shortcode_leaks'] > (int) ( $b['shortcode_leaks'] ?? 0 ) ) {
			$findings[] = array(
				'code'     => 'shortcode_leakage',
				'severity' => 'critical',
				'message'  => sprintf(
					'Raw builder shortcode syntax is visible in the rendered page (%d occurrence(s), up from %d) — a module is failing to parse.',
					(int) $n['shortcode_leaks'],
					(int) ( $b['shortcode_leaks'] ?? 0 )
				),
			);
		}

		if ( isset( $n['php_errors'] ) && (int) $n['php_errors'] > (int) ( $b['php_errors'] ?? 0 ) ) {
			$findings[] = array(
				'code'     => 'php_error_appeared',
				'severity' => 'critical',
				'message'  => sprintf(
					'PHP error text is visible in the rendered page (%d occurrence(s), up from %d).',
					(int) $n['php_errors'],
					(int) ( $b['php_errors'] ?? 0 )
				),
			);
		}

		$b_el = isset( $b['elements'] ) && is_array( $b['elements'] ) ? $b['elements'] : array();
		$n_el = isset( $n['elements'] ) && is_array( $n['elements'] ) ? $n['elements'] : array();
		foreach ( $b_el as $token => $before ) {
			$after = isset( $n_el[ $token ] ) ? (int) $n_el[ $token ] : 0;
			if ( (int) $before >= self::VANISH_MIN_BASELINE && 0 === $after ) {
				$findings[] = array(
					'code'     => 'elements_vanished',
					'severity' => 'critical',
					'token'    => $token,
					'message'  => sprintf(
						'The page rendered %d "%s" element(s) before the change and renders 0 now — that section\'s content is gone.',
						(int) $before,
						$token
					),
				);
			}
		}

		if ( isset( $b['text_len'], $n['text_len'] ) && (int) $b['text_len'] > 0 ) {
			$drop = (int) $b['text_len'] - (int) $n['text_len'];
			if ( $drop > self::SHRINK_MIN_CHARS
				&& (int) $n['text_len'] < (int) $b['text_len'] * self::SHRINK_RATIO ) {
				$findings[] = array(
					'code'     => 'content_shrunk',
					'severity' => 'warn',
					'message'  => sprintf(
						'Visible text shrank from %d to %d characters (-%d%%). Expected for a deliberate large deletion; verify it was intended.',
						(int) $b['text_len'],
						(int) $n['text_len'],
						(int) round( 100 * $drop / max( 1, (int) $b['text_len'] ) )
					),
				);
			}
		}

		return $findings;
	}

	/**
	 * True when any finding is severity critical.
	 */
	public static function has_critical( $findings ) {
		foreach ( (array) $findings as $f ) {
			if ( isset( $f['severity'] ) && 'critical' === $f['severity'] ) {
				return true;
			}
		}
		return false;
	}
}
