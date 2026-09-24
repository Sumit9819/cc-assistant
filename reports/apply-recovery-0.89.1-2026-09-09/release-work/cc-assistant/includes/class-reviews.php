<?php
/**
 * Google reviews — data layer + isolated admin page (Phase 1: Places API).
 *
 * Reads the Google Places API (Place Details) on a daily cron with a server
 * API key + Place ID — no OAuth, no access approval. The public page render
 * NEVER calls Google (the plugin stays off the front-end path).
 *
 * STORES METRICS ONLY (v0.76.11): rating, review count, and the newest review
 * timestamp. Review text and author details are deliberately NOT persisted —
 * Maps Platform Terms 3.2.3 forbids saving user reviews. Those three numbers
 * are what review_health scores against (20-review floor, 4.5 rating, 90-day
 * recency), so nothing of value is lost.
 *
 * Measured 2026-08-28: Places API (New) returns 200 with the `reviews` field
 * ABSENT for these listings even when requested alone, while rating and
 * userRatingCount return normally. Review bodies are therefore unavailable
 * here regardless of the terms question. Full review history, text and reply
 * status need Business Profile API v4 access, which is free but allowlisted.
 *
 * Deliberately self-contained: its own submenu page (not the shared
 * settings.php) so the experiment can be added/removed without touching other
 * admin UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Reviews {

	const OPT_API_KEY   = 'cc_assistant_reviews_api_key';
	const OPT_PLACE_ID  = 'cc_assistant_reviews_place_id';
	const OPT_CACHE     = 'cc_assistant_reviews_cache';
	const OPT_LAST_ERR  = 'cc_assistant_reviews_last_error';
	const OPT_SAVE_DIAG = 'cc_assistant_reviews_save_diag';
	const OPT_FETCH_DIAG = 'cc_assistant_reviews_fetch_diag';
	const OPT_PROBE     = 'cc_assistant_reviews_probe';
	const CRON_HOOK     = 'cc_assistant_reviews_refresh';
	const PLACES_URL    = 'https://places.googleapis.com/v1/places/%s';

	/* ---------------------------------------------------------------------
	 * Config
	 * ------------------------------------------------------------------- */

	public static function get_api_key() {
		return trim( (string) get_option( self::OPT_API_KEY, '' ) );
	}

	public static function get_place_id() {
		return trim( (string) get_option( self::OPT_PLACE_ID, '' ) );
	}

	public static function is_configured() {
		return '' !== self::get_api_key() && '' !== self::get_place_id();
	}

	/**
	 * v0.76.9. Drop this class's options from the object cache so the next
	 * read comes from the database. Needed because these options are written
	 * and then read back inside the SAME request; with a stale external object
	 * cache the read returns the pre-write value, which makes a successful
	 * write look like a no-op with no error anywhere.
	 */
	private static function bust_option_cache() {
		if ( ! function_exists( 'wp_cache_delete' ) ) {
			return;
		}
		foreach ( array( self::OPT_API_KEY, self::OPT_PLACE_ID, self::OPT_CACHE, self::OPT_LAST_ERR, self::OPT_FETCH_DIAG, self::OPT_SAVE_DIAG, self::OPT_PROBE ) as $opt ) {
			wp_cache_delete( $opt, 'options' );
		}
		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * A "leave a review" deep link for the configured place. Safe to render
	 * even before any reviews are fetched.
	 */
	public static function write_review_url() {
		$pid = self::get_place_id();
		return $pid ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $pid ) : '';
	}

	/* ---------------------------------------------------------------------
	 * Cached read (the ONLY method the front-end widget calls)
	 * ------------------------------------------------------------------- */

	/**
	 * Returns the cached payload:
	 *   array(
	 *     'rating'       => float,   // overall place rating
	 *     'total'        => int,     // userRatingCount
	 *     'reviews'      => array[], // normalized review rows
	 *     'fetched_at'   => int,     // unix ts
	 *   )
	 * Empty array if never fetched.
	 */
	public static function get_cache() {
		$blob = get_option( self::OPT_CACHE, array() );
		return is_array( $blob ) ? $blob : array();
	}

	/**
	 * Always empty from v0.76.11. Review bodies are no longer stored (Maps
	 * Platform Terms 3.2.3 forbids saving user reviews), and Places API (New)
	 * was in any case returning no review content for these listings. Kept so
	 * the Elementor widget keeps rendering its empty state instead of fataling.
	 */
	public static function get_reviews() {
		return array();
	}

	public static function last_error() {
		$e = get_option( self::OPT_LAST_ERR, '' );
		return is_string( $e ) ? $e : '';
	}

	/* ---------------------------------------------------------------------
	 * Fetch + cache
	 * ------------------------------------------------------------------- */

	/**
	 * Pull reviews from Google and store them. Returns the cache payload on
	 * success or WP_Error on failure (also recorded in OPT_LAST_ERR).
	 */
	public static function refresh() {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'reviews_not_configured', 'Add a Places API key and a Place ID first.' );
		}

		$raw = self::fetch_from_google( self::get_api_key(), self::get_place_id() );
		if ( is_wp_error( $raw ) ) {
			update_option( self::OPT_LAST_ERR, $raw->get_error_message(), false );
			self::bust_option_cache();
			return $raw;
		}

		$raw_reviews = isset( $raw['reviews'] ) ? (array) $raw['reviews'] : array();

		// v0.76.11 COMPLIANCE: store METRICS ONLY, never review content.
		// Maps Platform Terms 3.2.3: "Customer will not... copy and save
		// business names, addresses, or user reviews." Caching review bodies in
		// an option — which this class did from v0.46 — is not permitted.
		// A rating, a count and a timestamp are measurements, not reviews, and
		// they are all review_health needs (20-review floor, 4.5 rating,
		// 90-day recency). Author names, avatars and review text are read for
		// the newest-timestamp calculation and then discarded.
		$newest = 0;
		foreach ( $raw_reviews as $r ) {
			if ( isset( $r['publishTime'] ) ) {
				$ts = strtotime( (string) $r['publishTime'] );
				if ( $ts && $ts > $newest ) {
					$newest = (int) $ts;
				}
			}
		}
		$payload = array(
			'rating'              => isset( $raw['rating'] ) ? (float) $raw['rating'] : 0.0,
			'total'               => isset( $raw['userRatingCount'] ) ? (int) $raw['userRatingCount'] : 0,
			'newest_published_at' => $newest,
			'sampled'             => count( $raw_reviews ),
			'fetched_at'          => time(),
		);

		// v0.76.8: record WHICH fields Google actually returned. A 200 that
		// silently omits `reviews` is indistinguishable from a place with no
		// reviews, and both present as "rating and count fine, recency
		// unknown" — observed on erofirving (4.8 / 518 ratings, zero review
		// bodies). Field names only, never review content or the key.
		update_option(
			self::OPT_FETCH_DIAG,
			array(
				'at'              => time(),
				'fields_returned' => array_keys( $raw ),
				'has_rating'      => isset( $raw['rating'] ),
				'has_count'       => isset( $raw['userRatingCount'] ),
				'has_reviews_key' => isset( $raw['reviews'] ),
				'raw_reviews'     => count( $raw_reviews ),
				'with_timestamp'  => $newest ? 1 : 0,
				'stored'          => 'metrics only (rating, count, newest timestamp) — review bodies are never persisted, per Maps Platform Terms 3.2.3',
			),
			false
		);

		update_option( self::OPT_CACHE, $payload, false );
		delete_option( self::OPT_LAST_ERR );

		// v0.76.9: bust the object cache after writing, for the same reason
		// handle_save() does. On a site with a stale external object cache the
		// write lands in the DB but the very next get_option() returns the old
		// empty value, so a SUCCESSFUL fetch reports rating 0, total 0 and
		// fetched_at null with no error — which reads as "fetch did nothing".
		// Observed on eroflufkin, where wp_using_ext_object_cache() is true.
		self::bust_option_cache();
		return $payload;
	}

	/**
	 * Places API (v1) Place Details call. Field mask is REQUIRED by the API or
	 * it 400s. Returns the decoded place object or WP_Error.
	 */
	private static function fetch_from_google( $api_key, $place_id ) {
		$url = sprintf( self::PLACES_URL, rawurlencode( $place_id ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'X-Goog-Api-Key'   => $api_key,
					'X-Goog-FieldMask' => 'id,displayName,rating,userRatingCount,googleMapsUri,reviews',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
			return new WP_Error( 'reviews_api_error', $msg, array( 'status' => $code ) );
		}
		return is_array( $body ) ? $body : array();
	}


	/* ---------------------------------------------------------------------
	 * Cron
	 * ------------------------------------------------------------------- */

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function cron_refresh() {
		if ( self::is_configured() ) {
			self::refresh();
		}
	}

	/* ---------------------------------------------------------------------
	 * Isolated admin page (CC Assistant -> Reviews)
	 * ------------------------------------------------------------------- */

	public static function init_admin() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_post_cc_reviews_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_cc_reviews_fetch', array( __CLASS__, 'handle_fetch' ) );
		add_action( 'admin_post_cc_reviews_diag', array( __CLASS__, 'handle_diag' ) );
	}

	/**
	 * v0.76.10 "Run diagnostic": ask Google for ONE field at a time and show
	 * exactly what comes back for each. A combined field mask that returns 200
	 * minus `reviews` tells you nothing about why; asking for `reviews` alone
	 * forces Google to either return it or state a reason. Built into the page
	 * so diagnosing this never requires a terminal.
	 */
	public static function handle_diag() {
		check_admin_referer( 'cc_reviews_diag' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		$key = self::get_api_key();
		$pid = self::get_place_id();
		$out = array( 'at' => time(), 'probes' => array() );

		if ( '' === $key || '' === $pid ) {
			$out['probes'][] = array( 'mask' => '-', 'note' => 'API key or Place ID not set.' );
			update_option( self::OPT_PROBE, $out, false );
			self::bust_option_cache();
			wp_safe_redirect( self::admin_url( array( 'cc_msg' => 'diag' ) ) );
			exit;
		}

		foreach ( array( 'rating', 'userRatingCount', 'reviews' ) as $mask ) {
			$res = wp_remote_get(
				sprintf( self::PLACES_URL, rawurlencode( $pid ) ),
				array(
					'timeout' => 20,
					'headers' => array(
						'X-Goog-Api-Key'   => $key,
						'X-Goog-FieldMask' => $mask,
					),
				)
			);
			if ( is_wp_error( $res ) ) {
				$out['probes'][] = array( 'mask' => $mask, 'http' => 0, 'error' => $res->get_error_message() );
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			$row  = array( 'mask' => $mask, 'http' => $code );
			if ( isset( $body['error']['message'] ) ) {
				$row['error']  = (string) $body['error']['message'];
				$row['status'] = isset( $body['error']['status'] ) ? (string) $body['error']['status'] : '';
			} else {
				$row['returned'] = is_array( $body ) ? array_keys( $body ) : array();
				if ( 'reviews' === $mask ) {
					$row['review_count_returned'] = isset( $body['reviews'] ) ? count( (array) $body['reviews'] ) : 0;
				}
			}
			$out['probes'][] = $row;
		}

		update_option( self::OPT_PROBE, $out, false );
		self::bust_option_cache();
		wp_safe_redirect( self::admin_url( array( 'cc_msg' => 'diag' ) ) );
		exit;
	}

	/**
	 * v0.76.7: read-only REST surface so review_health can be read from the
	 * bridge. Registered outside the admin bootstrap because REST requests are
	 * not admin requests. Never returns the API key.
	 */
	public static function register_routes() {
		register_rest_route(
			'cc-assistant/v1',
			'/reviews',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_health' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
			)
		);
	}

	public static function rest_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to use CC Assistant.', array( 'status' => 403 ) );
		}
		return true;
	}

	public static function rest_health( $request ) {
		$refresh_result = null;
		if ( $request instanceof WP_REST_Request && $request->get_param( 'refresh' ) ) {
			$res = self::refresh();
			// Report what THIS call did, rather than inferring it from options
			// that a stale object cache may serve back pre-write.
			$refresh_result = is_wp_error( $res )
				? array( 'ok' => false, 'error' => $res->get_error_message() )
				: array(
					'ok'      => true,
					'rating'  => isset( $res['rating'] ) ? $res['rating'] : null,
					'total'   => isset( $res['total'] ) ? $res['total'] : null,
					'reviews' => isset( $res['reviews'] ) ? count( $res['reviews'] ) : 0,
				);
		}
		$health            = self::health();
		$health['reviews'] = self::get_reviews();
		if ( null !== $refresh_result ) {
			$health['refresh_result'] = $refresh_result;
		}
		return rest_ensure_response( $health );
	}

	public static function register_menu() {
		// v0.76.7: real menu entry. v0.63 hid this page (parent null) on the
		// theory that a Settings link was enough door. It was not — the only
		// route in was a secondary button labelled "Google Reviews widget" on
		// the Health tab, which reads as Elementor docs, so the operator could
		// not find where to enter the API key at all. Reviews are the largest
		// controllable local-pack factor; the page earns a menu row.
		add_submenu_page(
			'cc-assistant',
			'Google Reviews',
			'Reviews',
			'manage_options',
			'cc-assistant-reviews',
			array( __CLASS__, 'render_page' )
		);
	}

	private static function admin_url( $extra = array() ) {
		$url = admin_url( 'admin.php?page=cc-assistant-reviews' );
		return empty( $extra ) ? $url : add_query_arg( $extra, $url );
	}

	public static function handle_save() {
		check_admin_referer( 'cc_reviews_save' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		$key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		$pid = sanitize_text_field( wp_unslash( $_POST['place_id'] ?? '' ) );

		update_option( self::OPT_API_KEY, $key, false );
		update_option( self::OPT_PLACE_ID, $pid, false );

		// v0.76.8: verify the write instead of assuming it. A silently failed
		// option write (persistent object cache serving a stale value, or
		// another plugin filtering pre_update_option) looks EXACTLY like a
		// successful save: the redirect fires, "Settings saved" appears, and
		// the fields come back empty. Observed on eroflufkin, where repeated
		// saves never persisted and reported no error.
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPT_API_KEY, 'options' );
			wp_cache_delete( self::OPT_PLACE_ID, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}
		$saved_key = self::get_api_key();
		$saved_pid = self::get_place_id();
		$ok        = ( $saved_key === $key && $saved_pid === $pid );

		// Record lengths, never the secret itself. This separates the two
		// failure modes that are otherwise identical from the outside:
		// nothing arrived in the POST vs the write did not stick.
		update_option(
			self::OPT_SAVE_DIAG,
			array(
				'at'            => time(),
				'sent_key_len'  => strlen( $key ),
				'sent_pid_len'  => strlen( $pid ),
				'read_key_len'  => strlen( $saved_key ),
				'read_pid_len'  => strlen( $saved_pid ),
				'ok'            => $ok,
				'object_cache'  => (bool) wp_using_ext_object_cache(),
			),
			false
		);

		wp_safe_redirect( self::admin_url( array( 'cc_msg' => $ok ? 'saved' : 'save_failed' ) ) );
		exit;
	}

	public static function handle_fetch() {
		check_admin_referer( 'cc_reviews_fetch' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		$res = self::refresh();
		$msg = is_wp_error( $res ) ? 'fetch_error' : 'fetched';
		wp_safe_redirect( self::admin_url( array( 'cc_msg' => $msg ) ) );
		exit;
	}

	/**
	 * v0.76.7. Review health against the consumer thresholds that actually
	 * gate local-pack choice, so the admin page and the review_health tool
	 * cannot disagree.
	 *
	 * Thresholds are BrightLocal Local Consumer Review Survey 2026
	 * (published 2026-02-11, n=1,002 US adults, STATED INTENT not observed
	 * behaviour — treat as direction, not physics):
	 *   - 47% will not use a business with under 20 reviews
	 *   - 31% will only use a business rated 4.5 or better
	 *   - 74% want reviews written in the last three months
	 * Recency is the binding one: Whitespark 2026 puts reviews at roughly a
	 * quarter of local-pack weight with recency, not total, doing the work.
	 *
	 * HARD LIMIT: the Places API returns at most 5 reviews and no owner
	 * replies, so `total` and `rating` are exact but velocity is estimated
	 * from those 5 and response rate is unknowable here. Full history and
	 * replies need Business Profile API v4 access.
	 */
	const MIN_REVIEWS      = 20;
	const MIN_RATING       = 4.5;
	const MAX_NEWEST_DAYS  = 90;

	public static function health() {
		$out = array(
			'configured'   => self::is_configured(),
			'has_api_key'  => '' !== self::get_api_key(),
			'has_place_id' => '' !== self::get_place_id(),
			'last_error'   => self::last_error(),
			'thresholds'   => array(
				'min_reviews'     => self::MIN_REVIEWS,
				'min_rating'      => self::MIN_RATING,
				'max_newest_days' => self::MAX_NEWEST_DAYS,
				'source'          => 'BrightLocal Local Consumer Review Survey 2026 (n=1,002, stated intent)',
			),
			'limits'       => 'Places API returns max 5 reviews and no owner replies; total and rating are exact, velocity is estimated, response rate is unavailable.',
		);

		$save_diag  = get_option( self::OPT_SAVE_DIAG, array() );
		$fetch_diag = get_option( self::OPT_FETCH_DIAG, array() );
		if ( ! empty( $save_diag ) ) {
			$out['last_save'] = $save_diag;
		}
		if ( ! empty( $fetch_diag ) ) {
			$out['last_fetch_fields'] = $fetch_diag;
		}
		$probe = get_option( self::OPT_PROBE, array() );
		if ( ! empty( $probe ) ) {
			$out['field_probe'] = $probe;
		}

		if ( ! $out['configured'] ) {
			$out['verdict'] = 'not_configured';
			$out['issues']  = array( 'Add a Places API key and a Place ID on CC Assistant -> Reviews.' );
			// Distinguish "never entered" from "entered but the write failed".
			if ( ! empty( $save_diag ) && empty( $save_diag['ok'] ) && ! empty( $save_diag['sent_key_len'] ) ) {
				$out['issues'][] = sprintf(
					'A save WAS attempted (key %d chars, Place ID %d chars submitted) but read back empty. The option write is being lost%s — suspect a persistent object cache or another plugin filtering pre_update_option.',
					(int) $save_diag['sent_key_len'],
					(int) $save_diag['sent_pid_len'],
					! empty( $save_diag['object_cache'] ) ? ' (external object cache IS active on this site)' : ''
				);
			}
			return $out;
		}

		$cache   = self::get_cache();
		$rating  = isset( $cache['rating'] ) ? (float) $cache['rating'] : 0.0;
		$total   = isset( $cache['total'] ) ? (int) $cache['total'] : 0;
		$fetched = isset( $cache['fetched_at'] ) ? (int) $cache['fetched_at'] : 0;

		$out['rating']         = $rating;
		$out['total']          = $total;
		$out['sampled_reviews'] = isset( $cache['sampled'] ) ? (int) $cache['sampled'] : 0;
		$out['fetched_at']     = $fetched ? gmdate( 'c', $fetched ) : null;
		$out['fetch_age_hours'] = $fetched ? round( ( time() - $fetched ) / 3600, 1 ) : null;

		// Recency from the stored newest timestamp. Only a timestamp is kept;
		// review bodies are never persisted (Maps Platform Terms 3.2.3).
		$newest      = isset( $cache['newest_published_at'] ) ? (int) $cache['newest_published_at'] : 0;
		$newest_days = null;
		if ( $newest > 0 ) {
			$newest_days            = (int) floor( ( time() - $newest ) / DAY_IN_SECONDS );
			$out['newest_at']       = gmdate( 'c', $newest );
			$out['newest_age_days'] = $newest_days;
		}
		$out['storage_policy'] = 'Metrics only: rating, review count and the newest review timestamp. Review text and author details are never stored.';

		$issues = array();
		$checks = array();

		$checks['review_count'] = array(
			'value' => $total,
			'floor' => self::MIN_REVIEWS,
			'pass'  => $total >= self::MIN_REVIEWS,
		);
		if ( $total < self::MIN_REVIEWS ) {
			$issues[] = sprintf( '%d reviews, %d short of the 20 that 47%% of consumers treat as a floor.', $total, self::MIN_REVIEWS - $total );
		}

		$checks['rating'] = array(
			'value' => $rating,
			'floor' => self::MIN_RATING,
			'pass'  => $rating >= self::MIN_RATING,
		);
		if ( $rating > 0 && $rating < self::MIN_RATING ) {
			$issues[] = sprintf( 'Rated %.1f, below the 4.5 that 31%% of consumers require.', $rating );
		}

		$checks['recency'] = array(
			'value' => $newest_days,
			'floor' => self::MAX_NEWEST_DAYS,
			'pass'  => ( null !== $newest_days && $newest_days <= self::MAX_NEWEST_DAYS ),
		);
		if ( null === $newest_days ) {
			// Say WHY it is unknown. "Re-fetch" is useless advice when the
			// re-fetch already happened and Google returned no review bodies.
			if ( ! empty( $fetch_diag ) && empty( $fetch_diag['has_reviews_key'] ) ) {
				$issues[] = 'Google returned rating and review count but NO review bodies (the "reviews" field was absent from the response), so recency cannot be measured. Returned fields: ' . implode( ', ', (array) $fetch_diag['fields_returned'] ) . '. Usually means the project is not billed for the Places SKU that includes review content.';
			} elseif ( ! empty( $fetch_diag ) && ! empty( $fetch_diag['raw_reviews'] ) && empty( $fetch_diag['with_timestamp'] ) ) {
				$issues[] = sprintf( 'Google returned %d review(s) but none carried a publish time, so recency cannot be measured.', (int) $fetch_diag['raw_reviews'] );
			} else {
				$issues[] = 'Newest-review age unknown — re-fetch to store publish times.';
			}
		} elseif ( $newest_days > self::MAX_NEWEST_DAYS ) {
			$issues[] = sprintf( 'Newest review is %d days old; 74%% of consumers want reviews under 90 days. Recency is the binding constraint.', $newest_days );
		}

		$out['checks'] = $checks;
		$out['issues'] = $issues;

		$failed = 0;
		foreach ( $checks as $c ) {
			if ( ! $c['pass'] ) {
				$failed++;
			}
		}
		$out['verdict'] = 0 === $failed ? 'pass' : ( $failed >= 2 ? 'fail' : 'warn' );

		if ( '' !== $out['last_error'] ) {
			$out['verdict'] = 'error';
		}
		return $out;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$cache  = self::get_cache();
		$count  = count( self::get_reviews() );
		$msg    = isset( $_GET['cc_msg'] ) ? sanitize_key( $_GET['cc_msg'] ) : '';
		?>
		<div class="wrap">
			<h1>Google Reviews</h1>
			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
			<?php elseif ( 'fetched' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p>Fetched <?php echo (int) $count; ?> review(s) from Google.</p></div>
			<?php elseif ( 'fetch_error' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p>Fetch failed: <?php echo esc_html( self::last_error() ); ?></p></div>
			<?php elseif ( 'diag' === $msg ) : ?>
				<div class="notice notice-info is-dismissible"><p>Diagnostic complete. Results are below the setup form.</p></div>
			<?php elseif ( 'save_failed' === $msg ) :
				$sd = get_option( self::OPT_SAVE_DIAG, array() );
				?>
				<div class="notice notice-error">
					<p><strong>Save did not stick.</strong> The form was submitted but the values read back empty, so nothing was stored.</p>
					<?php if ( ! empty( $sd ) ) : ?>
						<p class="description">
							Submitted: key <?php echo (int) ( $sd['sent_key_len'] ?? 0 ); ?> characters, Place ID <?php echo (int) ( $sd['sent_pid_len'] ?? 0 ); ?> characters.
							Read back: <?php echo (int) ( $sd['read_key_len'] ?? 0 ); ?> and <?php echo (int) ( $sd['read_pid_len'] ?? 0 ); ?>.
							<?php if ( ! empty( $sd['object_cache'] ) ) : ?>
								An external object cache is active on this site, which is the usual cause — flush it (SiteGround: Speed Optimizer &rarr; Caching &rarr; Flush Memcached) and save again.
							<?php else : ?>
								No external object cache detected, so suspect another plugin filtering <code>pre_update_option</code>, or a read-only database user.
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			$h       = self::health();
			$badge   = array(
				'pass'           => array( '#2e7d32', 'Healthy' ),
				'warn'           => array( '#b26a00', 'Needs attention' ),
				'fail'           => array( '#c62828', 'Below thresholds' ),
				'error'          => array( '#c62828', 'Fetch error' ),
				'not_configured' => array( '#666', 'Not set up yet' ),
			);
			$b = isset( $badge[ $h['verdict'] ] ) ? $badge[ $h['verdict'] ] : $badge['not_configured'];
			?>

			<div class="cc-card cc-card-wide" style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:16px 0;max-width:900px;">
				<h2 style="margin-top:0;">
					Status
					<span style="font-size:12px;font-weight:600;color:<?php echo esc_attr( $b[0] ); ?>;border:1px solid <?php echo esc_attr( $b[0] ); ?>;border-radius:2px;padding:2px 8px;margin-left:8px;vertical-align:middle;"><?php echo esc_html( $b[1] ); ?></span>
				</h2>
				<?php if ( ! $h['configured'] ) : ?>
					<p>Add a Places API key and a Place ID below, then press <strong>Fetch reviews now</strong>.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:860px;">
						<tbody>
							<tr>
								<td style="width:230px;"><strong>Rating</strong></td>
								<td><?php echo esc_html( number_format_i18n( (float) $h['rating'], 1 ) ); ?> out of 5</td>
								<td><?php echo $h['checks']['rating']['pass'] ? '<span style="color:#2e7d32;">Meets 4.5</span>' : '<span style="color:#c62828;">Below 4.5</span>'; ?></td>
							</tr>
							<tr>
								<td><strong>Total reviews</strong></td>
								<td><?php echo (int) $h['total']; ?></td>
								<td><?php echo $h['checks']['review_count']['pass'] ? '<span style="color:#2e7d32;">Above 20</span>' : '<span style="color:#c62828;">Under 20</span>'; ?></td>
							</tr>
							<tr>
								<td><strong>Newest review</strong></td>
								<td><?php echo ( null === $h['checks']['recency']['value'] ) ? 'Unknown' : (int) $h['checks']['recency']['value'] . ' days ago'; ?></td>
								<td><?php echo $h['checks']['recency']['pass'] ? '<span style="color:#2e7d32;">Within 90 days</span>' : '<span style="color:#c62828;">Older than 90 days</span>'; ?></td>
							</tr>
							<?php if ( isset( $h['velocity_per_month_est'] ) ) : ?>
							<tr>
								<td><strong>Recent pace</strong></td>
								<td>about <?php echo esc_html( $h['velocity_per_month_est'] ); ?> reviews/month</td>
								<td><span class="description">estimate, from the newest few only</span></td>
							</tr>
							<?php endif; ?>
							<tr>
								<td><strong>Last fetched</strong></td>
								<td><?php echo esc_html( null === $h['fetch_age_hours'] ? 'never' : $h['fetch_age_hours'] . ' hours ago' ); ?></td>
								<td><span class="description">refreshes daily on cron</span></td>
							</tr>
						</tbody>
					</table>
					<?php if ( ! empty( $h['issues'] ) ) : ?>
						<ul style="margin-top:12px;">
							<?php foreach ( $h['issues'] as $iss ) : ?>
								<li style="list-style:disc;margin-left:18px;"><?php echo esc_html( $iss ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="description" style="margin-top:10px;">
						Thresholds are consumer research (BrightLocal 2026, 1,002 US adults, stated intent). Google returns at most 5 reviews here, so rating and total are exact but pace is an estimate, and owner replies are not available without Business Profile API access.
					</p>
				<?php endif; ?>
			</div>

			<div class="cc-card cc-card-wide" style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:16px 0;max-width:900px;">
				<h2 style="margin-top:0;">Setup</h2>
				<p>This uses the Google <strong>Places API</strong>, which needs only an API key. It is separate from the Business Profile API and needs no application or approval. Reviews are cached and refreshed daily; the public page never calls Google.</p>
				<ol>
					<li><strong>API key</strong> — in Google Cloud Console, enable <strong>Places API (New)</strong>, then Credentials &rarr; Create credentials &rarr; API key. Restrict it to the Places API. Billing must be attached, but four locations checked daily sit inside the free monthly allowance. One key works for every site.</li>
					<li><strong>Place ID</strong> — look the business up in <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">Google's Place ID finder</a> and copy the <code>ChIJ...</code> value. It is specific to this location.</li>
					<li>Paste both below, <strong>Save</strong>, then <strong>Fetch reviews now</strong>.</li>
				</ol>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'cc_reviews_save' ); ?>
					<input type="hidden" name="action" value="cc_reviews_save" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cc_api_key">Places API key</label></th>
							<td>
								<input name="api_key" id="cc_api_key" type="text" class="regular-text" value="<?php echo esc_attr( self::get_api_key() ); ?>" autocomplete="off" placeholder="AIza..." />
								<p class="description">
									<?php echo '' !== self::get_api_key() ? 'A key is saved. Re-entering replaces it.' : 'Not set yet.'; ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cc_place_id">Place ID</label></th>
							<td>
								<input name="place_id" id="cc_place_id" type="text" class="regular-text" value="<?php echo esc_attr( self::get_place_id() ); ?>" autocomplete="off" placeholder="ChIJ..." />
								<p class="description">Usually begins <code>ChIJ</code>. One per location, so each site needs its own.</p>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Save' ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:4px;">
					<?php wp_nonce_field( 'cc_reviews_fetch' ); ?>
					<input type="hidden" name="action" value="cc_reviews_fetch" />
					<?php submit_button( 'Fetch reviews now', 'secondary', 'submit', false ); ?>
					<span class="description" style="margin-left:8px;">Pulls from Google immediately instead of waiting for the daily refresh.</span>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
					<?php wp_nonce_field( 'cc_reviews_diag' ); ?>
					<input type="hidden" name="action" value="cc_reviews_diag" />
					<?php submit_button( 'Run diagnostic', 'secondary', 'submit', false ); ?>
					<span class="description" style="margin-left:8px;">Asks Google for one field at a time and reports exactly what each returns. Use this when the fetch succeeds but data is missing.</span>
				</form>

				<?php
				$probe = get_option( self::OPT_PROBE, array() );
				if ( ! empty( $probe['probes'] ) ) :
					?>
					<h3 style="margin-top:18px;">Diagnostic result</h3>
					<p class="description">Run <?php echo esc_html( human_time_diff( (int) $probe['at'] ) ); ?> ago. Each row is a separate request asking Google for that single field.</p>
					<table class="widefat striped" style="max-width:860px;">
						<thead><tr><th>Field requested</th><th>HTTP</th><th>Result</th></tr></thead>
						<tbody>
						<?php foreach ( $probe['probes'] as $p ) : ?>
							<tr>
								<td><code><?php echo esc_html( $p['mask'] ); ?></code></td>
								<td><?php echo isset( $p['http'] ) ? (int) $p['http'] : '-'; ?></td>
								<td>
									<?php if ( ! empty( $p['error'] ) ) : ?>
										<span style="color:#c62828;"><?php echo esc_html( $p['error'] ); ?></span>
										<?php if ( ! empty( $p['status'] ) ) : ?><br /><code><?php echo esc_html( $p['status'] ); ?></code><?php endif; ?>
									<?php elseif ( 'reviews' === $p['mask'] ) : ?>
										<?php if ( ! empty( $p['review_count_returned'] ) ) : ?>
											<span style="color:#2e7d32;">Returned <?php echo (int) $p['review_count_returned']; ?> review(s) — review data IS available to this project.</span>
										<?php else : ?>
											<span style="color:#b26a00;">200 OK but the field was absent. Google accepted the request and returned no reviews and no reason.</span>
										<?php endif; ?>
									<?php else : ?>
										<span style="color:#2e7d32;">OK — returned <?php echo esc_html( implode( ', ', (array) ( $p['returned'] ?? array() ) ) ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description">
						If <code>reviews</code> returns an <em>error</em>, the message names the cause. If it returns <strong>200 with nothing</strong>, the request was valid and accepted (Google rejects a field mask it cannot parse) and there is simply no review content to return for this Place ID through this API. That is not a key, Place ID, or billing problem: <code>rating</code> and <code>userRatingCount</code> are billed at the Enterprise tier and they returned, which proves billing works. Review bodies are only reliably available through the Business Profile API, which needs Google's access approval.
					</p>
				<?php endif; ?>

				<?php if ( '' !== $h['last_error'] ) : ?>
					<div class="notice notice-error inline" style="margin-top:12px;">
						<p><strong>Last fetch failed:</strong> <?php echo esc_html( $h['last_error'] ); ?></p>
						<p class="description">
							<code>REQUEST_DENIED</code> usually means Places API (New) is not enabled on the project, or the key is restricted to a different API.
							<code>INVALID_ARGUMENT</code> usually means the Place ID is wrong.
							A billing message means the project has no billing account attached.
						</p>
					</div>
				<?php endif; ?>
			</div>

			<h2>Cached</h2>
			<?php if ( empty( $cache ) ) : ?>
				<p>No reviews cached yet.</p>
			<?php else : ?>
				<p>
					Overall: <strong><?php echo esc_html( number_format_i18n( (float) ( $cache['rating'] ?? 0 ), 1 ) ); ?>★</strong>
					across <strong><?php echo (int) ( $cache['total'] ?? 0 ); ?></strong> ratings ·
					<?php echo (int) $count; ?> review(s) cached ·
					last fetched <?php echo esc_html( isset( $cache['fetched_at'] ) ? human_time_diff( (int) $cache['fetched_at'] ) . ' ago' : 'never' ); ?>.
				</p>
				<ul style="max-width:760px">
					<?php foreach ( self::get_reviews() as $rev ) : ?>
						<li style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee">
							<strong><?php echo esc_html( $rev['author'] ); ?></strong>
							— <?php echo esc_html( str_repeat( '★', max( 0, min( 5, (int) $rev['rating'] ) ) ) ); ?>
							<?php if ( $rev['when'] ) : ?><em>(<?php echo esc_html( $rev['when'] ); ?>)</em><?php endif; ?>
							<br /><?php echo esc_html( $rev['text'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
