<?php
/**
 * Google Search Console integration.
 *
 * OAuth 2.0 (BYO Google Cloud client), daily sync via WP-Cron, indexed
 * local cache table for insights. No external HTTP during admin renders;
 * insight queries hit the cached table only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_GSC {

	const OPT_CLIENT_ID     = 'cc_assistant_gsc_client_id';
	const OPT_CLIENT_SECRET = 'cc_assistant_gsc_client_secret';
	const OPT_TOKENS        = 'cc_assistant_gsc_tokens';
	const OPT_PROPERTY      = 'cc_assistant_gsc_property';
	const OPT_LAST_SYNC     = 'cc_assistant_gsc_last_sync';
	const OPT_LAST_ERROR    = 'cc_assistant_gsc_last_error';
	const OPT_STATE         = 'cc_assistant_gsc_oauth_state';
	const OPT_REDIRECT_URI  = 'cc_assistant_gsc_redirect_override';
	const OPT_INSIGHTS_BLOB = 'cc_assistant_gsc_insights_blob';

	const SCOPE          = 'https://www.googleapis.com/auth/webmasters.readonly';
	const AUTH_URL       = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL      = 'https://oauth2.googleapis.com/token';
	const SITES_URL      = 'https://www.googleapis.com/webmasters/v3/sites';
	const ANALYTICS_URL  = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';
	const INSPECT_URL    = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

	const SYNC_DAYS      = 16;   // daily incremental window (catches GSC late-arriving data)
	const BACKFILL_DAYS  = 90;
	// Retention is tiered (see maintain()). RETENTION_DAYS is the absolute
	// floor — everything older is dropped regardless of impression count.
	// 60 days = 30 current + 30 previous (resolve_windows clamps to 30).
	const RETENTION_DAYS = 60;

	// Tiered noise filters. Most rows in cc_gsc_queries are single-impression
	// long-tail queries that no tool reads — dropping them aggressively keeps
	// the table from filling host DB quotas without losing any actionable data.
	const IMP1_RETENTION_DAYS = 14;   // impressions=1 → drop after 14 days
	const IMP2_RETENTION_DAYS = 30;   // impressions<=2 → drop after 30 days

	// Hard ceiling. If the table still exceeds this after tiered pruning, walk
	// the retention window inward in 7-day steps until under cap. Default 250 MB
	// — generous for the data tools actually need; trips long before a 1 GB
	// host quota does.
	const MAX_TABLE_MB = 250;

	/**
	 * How many recommendation rows get a live HTTP status check per call.
	 * The GSC cache table trails reality by weeks, so deliberately pruned
	 * (404/410) pages keep surfacing as "optimization candidates" — checking
	 * the first N rows catches that without hammering the site. Per-URL
	 * results are transient-cached for 6 hours (LIVE_CHECK_TTL).
	 */
	const LIVE_CHECK_MAX = 20;
	const LIVE_CHECK_TTL = 21600; // 6 * HOUR_IN_SECONDS

	/**
	 * Default suppression window for "recently edited" filtering on every
	 * recommendation source. 14 days = ~3 days for Google to recrawl + ~10
	 * days for outcome scoring to mature. After that, if the page is still
	 * flagged we want to see it again.
	 */
	const RECENT_EDIT_DAYS = 14;

	/**
	 * Last-call instrumentation: how many rows the most recent recommendation
	 * call hid because the page had a cc_edits row within RECENT_EDIT_DAYS.
	 * Read by REST handlers so the response payload can show
	 * "Hidden: N posts recently optimized — outcomes still measuring."
	 */
	public static $last_recent_filtered = 0;

	/**
	 * Filter rows whose `page` URL hashes to a value in the recent-edit set,
	 * tracking the count of removed rows on the static instrumentation field.
	 * Skipped (returns rows untouched) when $args['include_recent_edited']
	 * is true — the caller deliberately wants to see already-edited posts.
	 */
	private static function suppress_recently_edited( $rows, $args = array() ) {
		self::$last_recent_filtered = 0;
		$include = ! empty( $args['include_recent_edited'] );
		if ( $include || empty( $rows ) ) {
			return $rows;
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		$days   = isset( $args['recent_edit_days'] ) ? (int) $args['recent_edit_days'] : self::RECENT_EDIT_DAYS;
		$hashes = CC_Assistant_Edit_Outcomes::recent_page_hashes( $days );
		if ( empty( $hashes ) ) {
			return $rows;
		}
		$hashset = array_flip( $hashes );
		$before  = count( $rows );
		$kept    = array();
		foreach ( $rows as $r ) {
			$page = isset( $r['page'] ) ? (string) $r['page'] : '';
			if ( '' === $page ) {
				$kept[] = $r;
				continue;
			}
			if ( ! isset( $hashset[ sha1( $page ) ] ) ) {
				$kept[] = $r;
			}
		}
		self::$last_recent_filtered = $before - count( $kept );
		return $kept;
	}

	public static function bootstrap() {
		add_action( 'cc_assistant_gsc_sync', array( __CLASS__, 'cron_sync' ) );
		add_action( 'cc_assistant_gsc_sync_now', array( __CLASS__, 'cron_sync' ) );
		add_action( 'cc_assistant_gsc_backfill_chunk', array( __CLASS__, 'backfill_chunk' ), 10, 2 );
		add_action( 'cc_assistant_recompute_insights', array( __CLASS__, 'recompute_insights' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_oauth' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_cron_scheduled' ) );
	}

	/**
	 * Compute every dashboard lens once and store as a single option.
	 *
	 * Called from cron after each sync + each backfill chunk. The dashboard
	 * just reads this blob — no aggregate queries on render. With ~500k rows
	 * the full recompute takes a few seconds but only runs in background.
	 */
	public static function recompute_insights() {
		if ( ! self::is_connected() ) {
			return;
		}
		@set_time_limit( 120 );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$blob = array(
			'computed_at'   => time(),
			'opportunities' => self::opportunities( array( 'limit' => 5, 'days' => 28, 'check_live' => false ) ),
			'trends'        => self::trends_summary( 7 ),
			'intent'        => self::intent_breakdown( array( 'days' => 28 ) ),
		);
		update_option( self::OPT_INSIGHTS_BLOB, $blob, false );
	}

	public static function get_insights_blob() {
		$blob = get_option( self::OPT_INSIGHTS_BLOB, null );
		return is_array( $blob ) ? $blob : null;
	}

	/**
	 * Schedule a one-shot insights recompute if one is not already pending,
	 * and not too recent. Idempotent — safe to call from anywhere.
	 */
	public static function schedule_recompute( $delay_seconds = 30 ) {
		// Transient lock closes the race window between wp_next_scheduled and
		// wp_schedule_single_event. Without it, two near-simultaneous callers
		// could both pass the check and both schedule.
		$lock_key = 'cc_recompute_schedule_lock';
		if ( ! set_transient( $lock_key, 1, 30 ) ) {
			// Couldn't acquire (another scheduler is mid-flight) — that's fine,
			// they'll do the work.
			return false;
		}
		if ( wp_next_scheduled( 'cc_assistant_recompute_insights' ) ) {
			delete_transient( $lock_key );
			return false;
		}
		wp_schedule_single_event( time() + max( 5, (int) $delay_seconds ), 'cc_assistant_recompute_insights' );
		delete_transient( $lock_key );
		return true;
	}

	public static function ensure_cron_scheduled() {
		if ( ! wp_next_scheduled( 'cc_assistant_gsc_sync' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cc_assistant_gsc_sync' );
		}
	}

	public static function redirect_uri() {
		$override = trim( (string) get_option( self::OPT_REDIRECT_URI, '' ) );
		if ( $override ) {
			return $override;
		}
		return admin_url( 'admin.php?page=cc-assistant-settings&tab=gsc&cc_gsc_oauth=callback' );
	}

	public static function default_redirect_uri() {
		return admin_url( 'admin.php?page=cc-assistant-settings&tab=gsc&cc_gsc_oauth=callback' );
	}

	/**
	 * True if the auto-derived redirect URI uses a TLD Google won't accept
	 * (.local, .test, .localhost, .invalid, .example) — these need a tunnel
	 * or a public site to authorize OAuth.
	 */
	public static function host_is_local_only( $url = '' ) {
		if ( '' === $url ) {
			$url = self::default_redirect_uri();
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$host = mb_strtolower( $host );
		// Google does accept literal localhost and 127.0.0.1 — those are fine.
		if ( 'localhost' === $host || '127.0.0.1' === $host ) {
			return false;
		}
		foreach ( array( '.local', '.test', '.localhost', '.invalid', '.example' ) as $tld ) {
			if ( substr( $host, -strlen( $tld ) ) === $tld ) {
				return true;
			}
		}
		return false;
	}

	public static function is_configured() {
		return get_option( self::OPT_CLIENT_ID ) && get_option( self::OPT_CLIENT_SECRET );
	}

	public static function is_connected() {
		$tokens = self::get_tokens();
		return ! empty( $tokens['refresh_token'] );
	}

	public static function get_property() {
		return (string) get_option( self::OPT_PROPERTY, '' );
	}

	public static function status() {
		$last_sync = get_option( self::OPT_LAST_SYNC, null );
		$last_err  = get_option( self::OPT_LAST_ERROR, null );

		return array(
			'configured'     => self::is_configured(),
			'connected'      => self::is_connected(),
			'property'       => self::get_property(),
			'redirect_uri'   => self::redirect_uri(),
			'last_sync_at'   => $last_sync ? gmdate( 'c', (int) $last_sync ) : null,
			'last_error'     => $last_err,
			'next_scheduled' => wp_next_scheduled( 'cc_assistant_gsc_sync' ),
			'rows_cached'    => self::count_rows(),
            'measurement' => array(
                'version' => get_option( 'cc_assistant_gsc_measurement_version', 'legacy_unverified' ),
                'raw_dates_resynced' => get_option( 'cc_assistant_gsc_raw_dates', array() ),
                'retention_note' => 'Raw at ingestion; low-impression pruning, API limits and retention can make historical query coverage incomplete.',
                'history_note' => 'Only listed dates were re-fetched without inferred appearance allocation. Older unlisted dates may contain legacy estimates; re-sync the desired reporting window.',
                'appearance_note' => 'Page/appearance observations are stored independently and are not additive to query/page totals or evidence of AI attribution.',
            ),
			// Null unless the stored history reaches into the inflated-impressions
			// window, so this clears itself once the warehouse ages past it.
			'data_integrity' => self::impressions_integrity( self::earliest_row_date() ),
		);
	}

	public static function count_rows() {
		// 5-minute transient cache. count_rows is read on every dashboard render
		// via status(); COUNT(*) on a multi-hundred-thousand-row table is not
		// free even on InnoDB. Cache busts naturally when sync runs.
		$cached = get_transient( 'cc_assistant_gsc_row_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		set_transient( 'cc_assistant_gsc_row_count', $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	public static function earliest_row_date() {
		$cached = get_transient( 'cc_assistant_gsc_earliest_date' );
		if ( false !== $cached ) {
			return (string) $cached;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';
		$min   = (string) $wpdb->get_var( "SELECT MIN(date) FROM {$table}" );
		set_transient( 'cc_assistant_gsc_earliest_date', $min, 5 * MINUTE_IN_SECONDS );
		return $min;
	}

	/**
	 * v0.76.3. Google over-reported impressions in Search Console from
	 * 2025-05-13 until 2026-04-27 — a logging error, since fixed, with
	 * history NOT restated. Clicks were unaffected.
	 *
	 * Two ways this silently corrupts a verdict:
	 *  - CTR is clicks over an inflated denominator, so pages look worse than
	 *    they are and aio_ctr_drop_alert over-flags absorption.
	 *  - A window-over-window comparison that straddles 2026-04-27 reads the
	 *    correction as a traffic decline and invents a decay story.
	 *
	 * Source: support.google.com/webmasters/answer/6211453 ("April 3" entry).
	 * Returns null once the window being read starts after the fix, so this
	 * stops appearing on its own rather than becoming permanent noise.
	 */
	const IMPRESSION_BUG_START = '2025-05-13';
	const IMPRESSION_BUG_END   = '2026-04-27';

	public static function impressions_integrity( $window_start = null ) {
		$start = (string) $window_start;
		if ( '' === $start ) {
			return null;
		}
		if ( strcmp( $start, self::IMPRESSION_BUG_END ) > 0 ) {
			return null;
		}
		return array(
			'code'          => 'gsc_impressions_inflated',
			'bug_window'    => self::IMPRESSION_BUG_START . ' to ' . self::IMPRESSION_BUG_END,
			'window_starts' => $start,
			'affects'       => 'impressions, CTR, and any comparison crossing ' . self::IMPRESSION_BUG_END,
			'clicks_ok'     => true,
			'note'          => 'Google over-reported impressions for this period and did not restate history after fixing it on ' . self::IMPRESSION_BUG_END . '. Judge these pages on CLICKS. An impression or CTR change that begins right after the fix date is partly the correction being removed, not lost visibility.',
			'source'        => 'https://support.google.com/webmasters/answer/6211453',
		);
	}

	/* ---------------------------------------------------------------------
	 * OAuth flow
	 * ------------------------------------------------------------------- */

	public static function maybe_handle_oauth() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['cc_gsc_oauth'] ) ) {
			return;
		}

		$action = sanitize_key( $_GET['cc_gsc_oauth'] );

		if ( 'connect' === $action ) {
			self::handle_connect();
			return;
		}

		if ( 'callback' === $action ) {
			self::handle_callback();
			return;
		}

		if ( 'disconnect' === $action ) {
			check_admin_referer( 'cc_gsc_disconnect' );
			delete_option( self::OPT_TOKENS );
			delete_option( self::OPT_PROPERTY );
			delete_option( self::OPT_LAST_SYNC );
			delete_option( self::OPT_LAST_ERROR );
			delete_transient( 'cc_assistant_gsc_properties' );
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'disconnected' ) ) );
			exit;
		}

		if ( 'sync_now' === $action ) {
			check_admin_referer( 'cc_gsc_sync_now' );
			wp_schedule_single_event( time() + 5, 'cc_assistant_gsc_sync_now' );
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'sync_queued' ) ) );
			exit;
		}

		if ( 'set_property' === $action ) {
			check_admin_referer( 'cc_gsc_set_property' );
			$prop = isset( $_POST['cc_gsc_property'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_gsc_property'] ) ) : '';
			if ( $prop ) {
				$previous = get_option( self::OPT_PROPERTY, '' );
				update_option( self::OPT_PROPERTY, $prop, false );
				// On first set or property change, queue a 90-day backfill chunk-by-date.
				if ( $previous !== $prop ) {
					self::queue_backfill();
				}
			}
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'property_set' ) ) );
			exit;
		}

		if ( 'backfill' === $action ) {
			check_admin_referer( 'cc_gsc_backfill' );
			self::queue_backfill();
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'backfill_queued' ) ) );
			exit;
		}
	}

	private static function settings_url( $extra = array() ) {
		$url = admin_url( 'admin.php?page=cc-assistant-settings&tab=gsc' );
		if ( ! empty( $extra ) ) {
			$url = add_query_arg( $extra, $url );
		}
		return $url;
	}

	private static function handle_connect() {
		check_admin_referer( 'cc_gsc_connect' );

		if ( ! self::is_configured() ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'missing_creds' ) ) );
			exit;
		}

		$state = wp_generate_password( 32, false, false );
		set_transient( self::OPT_STATE, $state, 10 * MINUTE_IN_SECONDS );

		$params = array(
			'response_type'          => 'code',
			'client_id'              => get_option( self::OPT_CLIENT_ID ),
			'redirect_uri'           => self::redirect_uri(),
			'scope'                  => self::SCOPE,
			'access_type'            => 'offline',
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
			'state'                  => $state,
		);

		wp_redirect( self::AUTH_URL . '?' . http_build_query( $params ) );
		exit;
	}

	private static function handle_callback() {
		$state_in    = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$state_saved = get_transient( self::OPT_STATE );
		delete_transient( self::OPT_STATE );

		if ( ! $state_in || ! $state_saved || ! hash_equals( (string) $state_saved, $state_in ) ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'state_mismatch' ) ) );
			exit;
		}

		if ( isset( $_GET['error'] ) ) {
			update_option( self::OPT_LAST_ERROR, 'OAuth: ' . sanitize_text_field( wp_unslash( $_GET['error'] ) ), false );
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'oauth_error' ) ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( ! $code ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'oauth_error' ) ) );
			exit;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => get_option( self::OPT_CLIENT_ID ),
					'client_secret' => get_option( self::OPT_CLIENT_SECRET ),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			update_option( self::OPT_LAST_ERROR, 'Token exchange: ' . $response->get_error_message(), false );
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'oauth_error' ) ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : wp_remote_retrieve_body( $response );
			update_option( self::OPT_LAST_ERROR, 'Token exchange: ' . substr( (string) $msg, 0, 500 ), false );
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'oauth_error' ) ) );
			exit;
		}

		$saved = self::set_tokens(
			array(
				'access_token'  => $body['access_token'],
				'refresh_token' => $body['refresh_token'],
				'expires_at'    => time() + (int) ( isset( $body['expires_in'] ) ? $body['expires_in'] : 3600 ) - 60,
			)
		);
		if ( isset( $saved ) && is_wp_error( $saved ) ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'oauth_error' ) ) );
			exit;
		}
		delete_option( self::OPT_LAST_ERROR );
		delete_transient( 'cc_assistant_gsc_properties' );

		wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'connected' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Token storage (encrypted at rest using wp_salt)
	 * ------------------------------------------------------------------- */

	private static function encryption_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|cc-gsc', true );
	}

	private static function encrypt( $plaintext ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new WP_Error( 'token_encryption_failed', 'OpenSSL AES-GCM is required to store Google tokens securely.' );
		}
		try { $iv = random_bytes( 12 ); }
		catch ( \Throwable $e ) { return new WP_Error( 'token_encryption_failed', 'Secure randomness is unavailable.' ); }
		$tag        = '';
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $ciphertext || strlen( $tag ) !== 16 ) {
			return new WP_Error( 'token_encryption_failed', 'OpenSSL AES-GCM is required to store Google tokens securely.' );
		}
		return 'gcm1:' . base64_encode( $iv . $tag . $ciphertext );
	}

	private static function decrypt( $payload ) {
		if ( strpos( $payload, 'gcm1:' ) !== 0 ) {
			// Legacy fallback for tokens written before AES-GCM. Use strict mode
			// so non-base64 input returns false instead of garbage, then guard.
			$decoded = base64_decode( $payload, true );
			return false === $decoded ? '' : $decoded;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) { return ''; }
		$raw = base64_decode( substr( $payload, 5 ), true );
		if ( false === $raw || strlen( $raw ) < 28 ) {
			return '';
		}
		$iv         = substr( $raw, 0, 12 );
		$tag        = substr( $raw, 12, 16 );
		$ciphertext = substr( $raw, 28 );
		$plaintext  = openssl_decrypt( $ciphertext, 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $plaintext ? '' : $plaintext;
	}

	private static function set_tokens( $tokens ) {
		$blob = self::encrypt( wp_json_encode( $tokens ) );
		if ( is_wp_error( $blob ) ) { update_option( self::OPT_LAST_ERROR, $blob->get_error_message(), false ); return $blob; }
		if ( ! update_option( self::OPT_TOKENS, $blob, false ) && get_option( self::OPT_TOKENS ) !== $blob ) {
			$error = new WP_Error( 'token_storage_failed', 'Could not save Google tokens.' );
			update_option( self::OPT_LAST_ERROR, $error->get_error_message(), false );
			return $error;
		}
		return true;
	}

	public static function get_tokens() {
		$blob = get_option( self::OPT_TOKENS, '' );
		if ( ! $blob ) {
			return array();
		}
		$plain = self::decrypt( $blob );
		$data  = json_decode( $plain, true );
		return is_array( $data ) ? $data : array();
	}

	private static function refresh_access_token() {
		$tokens = self::get_tokens();
		if ( empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'gsc_not_connected', 'Not connected to Google.' );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => get_option( self::OPT_CLIENT_ID ),
					'client_secret' => get_option( self::OPT_CLIENT_SECRET ),
					'refresh_token' => $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : 'Unknown token refresh error';
			return new WP_Error( 'gsc_refresh_failed', $msg );
		}

		$tokens['access_token'] = $body['access_token'];
		$tokens['expires_at']   = time() + (int) ( isset( $body['expires_in'] ) ? $body['expires_in'] : 3600 ) - 60;
		$saved = self::set_tokens( $tokens );
		return is_wp_error( $saved ) ? $saved : $tokens['access_token'];
	}

	private static function get_access_token() {
		$tokens = self::get_tokens();
		if ( empty( $tokens['access_token'] ) || empty( $tokens['expires_at'] ) || time() >= $tokens['expires_at'] ) {
			return self::refresh_access_token();
		}
		return $tokens['access_token'];
	}

	/* ---------------------------------------------------------------------
	 * Google API calls
	 * ------------------------------------------------------------------- */

	private static function api_get( $url ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);
		return self::handle_api_response( $response, $url, 'GET', null );
	}

	private static function api_post( $url, $body ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		return self::handle_api_response( $response, $url, 'POST', $body );
	}

	private static function handle_api_response( $response, $url, $method, $body, $retried = false ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );

		if ( 401 === $code && ! $retried ) {
			$refreshed = self::refresh_access_token();
			if ( is_wp_error( $refreshed ) ) {
				return $refreshed;
			}
			$args = array(
				'timeout' => 60,
				'headers' => array( 'Authorization' => 'Bearer ' . $refreshed ),
			);
			if ( 'POST' === $method ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $body );
				$retry                           = wp_remote_post( $url, $args );
			} else {
				$retry = wp_remote_get( $url, $args );
			}
			return self::handle_api_response( $retry, $url, $method, $body, true );
		}

		if ( $code >= 400 ) {
			return new WP_Error( 'gsc_api_error', sprintf( 'GSC API %d: %s', $code, substr( (string) $raw_body, 0, 500 ) ) );
		}

		$decoded = json_decode( $raw_body, true );
		return null === $decoded ? array() : $decoded;
	}

	/**
	 * URL Inspection API call. Returns the indexing status of a single URL on
	 * the connected property. Cached 1h per URL because the Inspection API has
	 * a 2,000/day quota per property — repeated calls during a session would
	 * exhaust it fast.
	 *
	 * Response shape (we surface a normalized subset):
	 *   index_status: PASS | FAIL | NEUTRAL
	 *   verdict: PASS | PARTIAL | FAIL | NEUTRAL
	 *   coverage_state: human-readable ("Submitted and indexed", "Discovered - currently not indexed", etc.)
	 *   robots_txt_state: ALLOWED | DISALLOWED | DISALLOWED_REDIRECT_INELIGIBLE | ROBOTS_TXT_STATE_UNSPECIFIED
	 *   indexing_state: INDEXING_ALLOWED | BLOCKED_BY_META_TAG | BLOCKED_BY_HTTP_HEADER | etc.
	 *   page_fetch_state: SUCCESSFUL | SOFT_404 | BLOCKED_ROBOTS_TXT | NOT_FOUND | ACCESS_DENIED | SERVER_ERROR | REDIRECT_ERROR | ACCESS_FORBIDDEN | BLOCKED_4XX | INTERNAL_CRAWL_ERROR | INVALID_URL
	 *   last_crawl_time: ISO timestamp
	 *   crawled_as: DESKTOP | MOBILE
	 *   user_canonical: the canonical URL declared by the page
	 *   google_canonical: the canonical Google selected (mismatch = duplicate-content issue)
	 *   referring_urls: array of URLs Google found this from (helps diagnose orphan pages)
	 *   sitemap: array of sitemap URLs containing this page
	 *   inspection_link: deep-link into Search Console
	 *   suggested_fix: one-line guidance derived from the verdict + states
	 */
	public static function inspect_url( $url, $force_refresh = false ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return new WP_Error( 'inspect_invalid_url', 'URL is required.' );
		}

		$site_url = self::get_property();
		if ( '' === $site_url ) {
			return new WP_Error( 'gsc_no_property', 'No GSC property selected. Connect Search Console in cc-assistant settings first.' );
		}

		$cache_key = 'cc_gsc_inspect_' . md5( $url );
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$resp = self::api_post(
			self::INSPECT_URL,
			array(
				'inspectionUrl' => $url,
				'siteUrl'       => $site_url,
				'languageCode'  => 'en-US',
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$result   = isset( $resp['inspectionResult'] ) && is_array( $resp['inspectionResult'] ) ? $resp['inspectionResult'] : array();
		$index    = isset( $result['indexStatusResult'] ) && is_array( $result['indexStatusResult'] ) ? $result['indexStatusResult'] : array();

		$normalized = array(
			'url'                => $url,
			'site_url'           => $site_url,
			'verdict'            => isset( $index['verdict'] ) ? (string) $index['verdict'] : 'VERDICT_UNSPECIFIED',
			'coverage_state'     => isset( $index['coverageState'] ) ? (string) $index['coverageState'] : '',
			'robots_txt_state'   => isset( $index['robotsTxtState'] ) ? (string) $index['robotsTxtState'] : '',
			'indexing_state'     => isset( $index['indexingState'] ) ? (string) $index['indexingState'] : '',
			'page_fetch_state'   => isset( $index['pageFetchState'] ) ? (string) $index['pageFetchState'] : '',
			'last_crawl_time'    => isset( $index['lastCrawlTime'] ) ? (string) $index['lastCrawlTime'] : null,
			'crawled_as'         => isset( $index['crawledAs'] ) ? (string) $index['crawledAs'] : '',
			'user_canonical'     => isset( $index['userCanonical'] ) ? (string) $index['userCanonical'] : '',
			'google_canonical'   => isset( $index['googleCanonical'] ) ? (string) $index['googleCanonical'] : '',
			'referring_urls'     => isset( $index['referringUrls'] ) && is_array( $index['referringUrls'] ) ? $index['referringUrls'] : array(),
			'sitemap'            => isset( $index['sitemap'] ) && is_array( $index['sitemap'] ) ? $index['sitemap'] : array(),
			'inspection_link'    => isset( $result['inspectionResultLink'] ) ? (string) $result['inspectionResultLink'] : '',
		);

		// Mobile-usability + AMP + rich-results sub-blocks: pass through their verdicts.
		if ( isset( $result['mobileUsabilityResult']['verdict'] ) ) {
			$normalized['mobile_usability_verdict'] = (string) $result['mobileUsabilityResult']['verdict'];
		}
		if ( isset( $result['richResultsResult']['verdict'] ) ) {
			$normalized['rich_results_verdict'] = (string) $result['richResultsResult']['verdict'];
		}
		if ( isset( $result['ampResult']['verdict'] ) ) {
			$normalized['amp_verdict'] = (string) $result['ampResult']['verdict'];
		}

		$normalized['suggested_fix'] = self::indexing_suggested_fix( $normalized );
		$normalized['has_issue']     = ( 'PASS' !== $normalized['verdict'] || ! empty( $normalized['suggested_fix'] ) );

		// 1-hour TTL respects the API's 2,000/day quota during repeated checks.
		set_transient( $cache_key, $normalized, HOUR_IN_SECONDS );

		return $normalized;
	}

	/**
	 * Map the URL Inspection result fields to a one-line plain-English fix
	 * suggestion. Returns empty string when nothing to fix.
	 */
	private static function indexing_suggested_fix( $r ) {
		// Robots blocks first — those override everything.
		if ( 'DISALLOWED' === $r['robots_txt_state'] ) {
			return 'Page is blocked by robots.txt. Check your robots.txt rules — if this page should be indexed, remove the disallow rule.';
		}
		if ( 'BLOCKED_BY_META_TAG' === $r['indexing_state'] ) {
			return 'Page has a noindex meta tag. Remove the noindex if this page should be indexed (Rank Math > Page settings, or theme template).';
		}
		if ( 'BLOCKED_BY_HTTP_HEADER' === $r['indexing_state'] ) {
			return 'Page is blocked by an X-Robots-Tag HTTP header. Check server config or hosting-level rules.';
		}

		// Page-fetch level errors.
		switch ( $r['page_fetch_state'] ) {
			case 'SOFT_404':
				return 'Soft 404 — Google sees this as an empty/error page. Add real content or set a proper 404 status.';
			case 'NOT_FOUND':
				return 'Page returns 404. If unintentional, restore the page or add a 301 redirect from this URL to its replacement.';
			case 'BLOCKED_4XX':
				return 'Page returns a 4xx error other than 404. Check server logs and fix the access rule.';
			case 'SERVER_ERROR':
				return 'Page returns a 5xx error. Check server logs (PHP, hosting, plugin conflicts).';
			case 'ACCESS_DENIED':
			case 'ACCESS_FORBIDDEN':
				return 'Access denied. Check authentication / firewall rules — Googlebot may be blocked.';
			case 'REDIRECT_ERROR':
				return 'Redirect loop or chain error. Check redirect rules — limit chains to a single 301.';
			case 'INTERNAL_CRAWL_ERROR':
			case 'INVALID_URL':
				return 'Crawl error reported by Google. Re-submit via Search Console URL Inspection > Request Indexing after verifying the page loads.';
		}

		// Canonical mismatches.
		if ( ! empty( $r['google_canonical'] ) && ! empty( $r['user_canonical'] ) && $r['google_canonical'] !== $r['user_canonical'] ) {
			return sprintf(
				'Canonical mismatch — you declared %s but Google chose %s. Either accept Google\'s pick (the page is duplicate-content) or strengthen the user-canonical signal (link more, differentiate content, fix internal-link consistency).',
				$r['user_canonical'],
				$r['google_canonical']
			);
		}

		// Coverage states (verdict not PASS but no fetch error).
		$coverage = strtolower( (string) $r['coverage_state'] );
		if ( false !== strpos( $coverage, 'discovered' ) && false !== strpos( $coverage, 'not indexed' ) ) {
			return 'Discovered but not indexed. Google found the URL but never crawled it. Add inbound internal links from authoritative pages (homepage / hub) to lift crawl priority.';
		}
		if ( false !== strpos( $coverage, 'crawled' ) && false !== strpos( $coverage, 'not indexed' ) ) {
			return 'Crawled but not indexed. Usually a content-quality signal — page is too thin, too duplicative, or too low-value vs alternatives. Improve content depth, originality, and E-E-A-T signals.';
		}
		if ( false !== strpos( $coverage, 'duplicate' ) ) {
			return 'Duplicate content detected. Consolidate via 301 to the canonical version, or differentiate this page\'s content + intent so it stands on its own.';
		}
		if ( false !== strpos( $coverage, 'redirect' ) ) {
			return 'Page is a redirect; Google indexed the destination. Usually fine — verify the destination is the intended canonical.';
		}
		if ( false !== strpos( $coverage, 'noindex' ) ) {
			return 'Page is excluded by noindex. Verify this is intentional.';
		}

		// PASS or no obvious problem.
		return '';
	}

	public static function list_properties( $force_refresh = false ) {
		$cache_key = 'cc_assistant_gsc_properties';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$result = self::api_get( self::SITES_URL );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$out = array();
		if ( ! empty( $result['siteEntry'] ) && is_array( $result['siteEntry'] ) ) {
			foreach ( $result['siteEntry'] as $site ) {
				$out[] = array(
					'siteUrl'         => isset( $site['siteUrl'] ) ? $site['siteUrl'] : '',
					'permissionLevel' => isset( $site['permissionLevel'] ) ? $site['permissionLevel'] : '',
				);
			}
		}
		set_transient( $cache_key, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Sync (cron handler)
	 * ------------------------------------------------------------------- */

	public static function cron_sync() {
		if ( ! self::is_connected() ) {
			update_option( self::OPT_LAST_ERROR, 'Sync skipped: not connected.', false );
			return;
		}
		$property = self::get_property();
		if ( ! $property ) {
			update_option( self::OPT_LAST_ERROR, 'Sync skipped: no property selected.', false );
			return;
		}

		// Self-heal: recreate the GSC table if a DBA dropped it. Cheap (one
		// SHOW TABLES on the hot path; full dbDelta only fires on actual drop).
		self::ensure_table();

		@set_time_limit( 300 );

		// Daily incremental: pull a 16-day window per date (catches GSC late-arriving data).
		$end_date   = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$start_date = gmdate( 'Y-m-d', strtotime( '-' . self::SYNC_DAYS . ' days' ) );

		$end_ts = strtotime( $end_date );
		for ( $ts = strtotime( $start_date ); $ts <= $end_ts; $ts += DAY_IN_SECONDS ) {
			$date = gmdate( 'Y-m-d', $ts );
			$err  = self::sync_one_date( $property, $date );
			if ( $err ) {
				update_option( self::OPT_LAST_ERROR, $err, false );
				return;
			}
		}

		update_option( self::OPT_LAST_SYNC, time(), false );
		if ( isset( $saved ) && is_wp_error( $saved ) ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gsc_msg' => 'oauth_error' ) ) );
			exit;
		}
		delete_option( self::OPT_LAST_ERROR );
		self::prune_old_rows();
		// Bust dashboard cache + row-count cache + queue async recompute of trends.
		delete_transient( 'cc_assistant_dashboard_insights' );
		delete_transient( 'cc_assistant_gsc_row_count' );
		self::schedule_recompute( 30 );
	}

	/**
	 * Queue a 90-day backfill: one chunk per cron tick, 7 days each, spaced
	 * by ~5 minutes so a property change does not stall daily sync.
	 */
	public static function queue_backfill() {
		// Self-heal before any backfill chunks run, so a fresh TRUNCATE/DROP +
		// "Run backfill" flow works without needing plugin reactivation.
		self::ensure_table();

		$end_ts   = strtotime( gmdate( 'Y-m-d', strtotime( '-2 days' ) ) );
		$start_ts = $end_ts - ( self::BACKFILL_DAYS - 1 ) * DAY_IN_SECONDS;
		$chunk    = 7 * DAY_IN_SECONDS;
		$delay    = 30;
		$tick     = 0;

		for ( $cs = $start_ts; $cs <= $end_ts; $cs += $chunk ) {
			$ce = min( $cs + $chunk - DAY_IN_SECONDS, $end_ts );
			wp_schedule_single_event(
				time() + $delay + $tick * 5 * MINUTE_IN_SECONDS,
				'cc_assistant_gsc_backfill_chunk',
				array( gmdate( 'Y-m-d', $cs ), gmdate( 'Y-m-d', $ce ) )
			);
			$tick++;
		}
	}

	public static function backfill_chunk( $start_date, $end_date ) {
		if ( ! self::is_connected() ) {
			return;
		}
		$property = self::get_property();
		if ( ! $property ) {
			return;
		}

		@set_time_limit( 300 );

		$end_ts = strtotime( $end_date );
		for ( $ts = strtotime( $start_date ); $ts <= $end_ts; $ts += DAY_IN_SECONDS ) {
			$date = gmdate( 'Y-m-d', $ts );
			$err  = self::sync_one_date( $property, $date );
			if ( $err ) {
				update_option( self::OPT_LAST_ERROR, 'Backfill ' . $date . ': ' . $err, false );
				return;
			}
		}
		delete_transient( 'cc_assistant_dashboard_insights' );
		delete_transient( 'cc_assistant_gsc_row_count' );
		self::schedule_recompute( 60 );
	}

	/**
	 * Pull one date with searchAppearance + (date, page, query) dimensions.
	 * Returns null on success or an error string.
	 */
	private static function sync_one_date( $property, $date ) {
		$url = sprintf( self::ANALYTICS_URL, rawurlencode( $property ) );

		// Two passes per date: one to get searchAppearance buckets, one for query/page totals.
		// GSC API limitation: searchAppearance can't combine with query/page in a single request.
		$rows_default = self::fetch_rows( $url, $date, $date, array( 'date', 'page', 'query' ) );
		if ( is_wp_error( $rows_default ) ) {
			return $rows_default->get_error_message();
		}

		$rows_appearance = self::fetch_rows( $url, $date, $date, array( 'date', 'page', 'searchAppearance' ) );
		if ( is_wp_error( $rows_appearance ) ) {
			// Not all properties support this dimension; tolerate failure quietly.
			$rows_appearance = array();
		}

		$stored = self::store_rows_for_date( $date, $rows_default, $rows_appearance );
		if ( is_wp_error( $stored ) ) { return $stored; }
		return null;
	}

	private static function fetch_rows( $url, $start_date, $end_date, $dimensions ) {
		$collected = array();
		$start     = 0;
		$limit     = 25000;
		do {
			$payload = array(
				'startDate'  => $start_date,
				'endDate'    => $end_date,
				'dimensions' => $dimensions,
				'rowLimit'   => $limit,
				'startRow'   => $start,
			);
			$result = self::api_post( $url, $payload );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( empty( $result['rows'] ) || ! is_array( $result['rows'] ) ) {
				break;
			}
			$collected = array_merge( $collected, $result['rows'] );
			$count     = count( $result['rows'] );
			$start    += $count;
			if ( $count < $limit ) {
				break;
			}
		} while ( true );
		return $collected;
	}

	/**
	 * One raw searchAnalytics page for the desktop warehouse export (v0.54).
	 *
	 * Unlike fetch_rows() this does NOT drain every page in one request — the
	 * MCP bridge paginates via start_row so each HTTP request to this site
	 * stays small and bounded (one Google API call per request). Rows are
	 * returned as compact positional arrays [page, query, clicks, impressions,
	 * position] to roughly halve the JSON payload vs keyed objects. Nothing is
	 * written to the WP database: the site is a pass-through proxy and the
	 * full-fidelity archive lives in SQLite on the operator's machine.
	 *
	 * Returns array{property, rows, row_count, api_row_count, complete} or WP_Error.
	 * `complete` is computed from the UNFILTERED Google row count, so a page
	 * of malformed rows can never be mistaken for the end of the data.
	 */
	public static function raw_export_page( $date, $start_row = 0, $row_limit = 25000 ) {
		if ( ! self::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'GSC is not connected on this site. Connect Search Console in cc-assistant settings first.' );
		}
		$property = self::get_property();
		if ( ! $property ) {
			return new WP_Error( 'gsc_no_property', 'No GSC property selected. Pick one in cc-assistant settings first.' );
		}

		$row_limit = max( 1, min( 25000, (int) $row_limit ) );
		$start_row = max( 0, (int) $start_row );

		$url    = sprintf( self::ANALYTICS_URL, rawurlencode( $property ) );
		$result = self::api_post(
			$url,
			array(
				'startDate'  => $date,
				'endDate'    => $date,
				'dimensions' => array( 'date', 'page', 'query' ),
				'rowLimit'   => $row_limit,
				'startRow'   => $start_row,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$api_rows = ( ! empty( $result['rows'] ) && is_array( $result['rows'] ) ) ? $result['rows'] : array();
		$out      = array();
		foreach ( $api_rows as $row ) {
			if ( empty( $row['keys'] ) || count( $row['keys'] ) < 3 ) {
				continue;
			}
			$out[] = array(
				(string) $row['keys'][1],
				(string) $row['keys'][2],
				(int) ( isset( $row['clicks'] ) ? $row['clicks'] : 0 ),
				(int) ( isset( $row['impressions'] ) ? $row['impressions'] : 0 ),
				round( (float) ( isset( $row['position'] ) ? $row['position'] : 0 ), 2 ),
			);
		}

		return array(
			'property'      => $property,
			'rows'          => $out,
			'row_count'     => count( $out ),
			'api_row_count' => count( $api_rows ),
			'complete'      => count( $api_rows ) < $row_limit,
		);
	}

	private static function store_rows_for_date( $date, $rows_default, $rows_appearance ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_gsc_queries';
        $appearance_table = $wpdb->prefix . 'cc_gsc_appearances';
        require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
        // Keep incompatible dimensions in separate tables. Never allocate page appearance shares to queries.
        if ( false === $wpdb->query( "CREATE TABLE IF NOT EXISTS {$appearance_table} LIKE {$table}" ) ) {
            return new WP_Error( 'gsc_measurement_storage', 'Cannot create the independent appearance dataset.' );
        }
        foreach ( array( $table, $appearance_table ) as $target ) {
            $storage = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $target ) ), ARRAY_A );
            if ( 'innodb' !== strtolower( (string) ( $storage['Engine'] ?? '' ) ) ) {
                return new WP_Error( 'gsc_transactional_storage_required', 'GSC replacement requires verified InnoDB tables; existing data was not changed.' );
            }
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return new WP_Error( 'gsc_measurement_transaction', 'Cannot start the GSC replacement transaction.' );
        }
        foreach ( array( $table, $appearance_table ) as $target ) {
            if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$target} WHERE date = %s", $date ) ) ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'gsc_measurement_storage', 'Cannot replace the GSC date.' );
            }
        }
        foreach ( array( array( $table, $rows_default, false ), array( $appearance_table, $rows_appearance, true ) ) as $dataset ) {
            $values = array(); $placeholders = array();
            foreach ( (array) $dataset[1] as $row ) {
                if ( empty( $row['keys'] ) || count( $row['keys'] ) < 3 ) { continue; }
                $page = (string) $row['keys'][1];
                $query = $dataset[2] ? '' : (string) $row['keys'][2];
                $appearance = $dataset[2] ? (string) $row['keys'][2] : '';
                self::collect_row( $placeholders, $values, $date, $page, $query, $appearance,
                    (int) ( $row['clicks'] ?? 0 ), (int) ( $row['impressions'] ?? 0 ),
                    (float) ( $row['ctr'] ?? 0 ), (float) ( $row['position'] ?? 0 ),
                    sha1( $page ), sha1( mb_strtolower( $query ) ), CC_Assistant_Query_Tagger::canonical_hash( $page ) );
                if ( count( $placeholders ) >= 500 ) {
                    if ( false === self::flush_insert( $dataset[0], $placeholders, $values ) ) {
                        $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'gsc_measurement_storage', 'GSC insertion failed; the prior date was preserved.' );
                    }
                    $values = array(); $placeholders = array();
                }
            }
            if ( ! empty( $placeholders ) && false === self::flush_insert( $dataset[0], $placeholders, $values ) ) {
                $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'gsc_measurement_storage', 'GSC insertion failed; the prior date was preserved.' );
            }
        }
        if ( false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'gsc_measurement_commit', 'GSC commit failed; verify storage before retrying.' );
        }
        $dates = (array) get_option( 'cc_assistant_gsc_raw_dates', array() );
        $dates[] = $date; $dates = array_values( array_unique( $dates ) ); sort( $dates );
        update_option( 'cc_assistant_gsc_raw_dates', array_slice( $dates, -600 ), false );
        update_option( 'cc_assistant_gsc_measurement_version', 'raw_query_page_v1_with_legacy_history', false );
        return true;
    }

	private static function collect_row( &$placeholders, &$values, $date, $page, $query, $appearance, $clicks, $imp, $ctr, $pos, $page_h, $query_h, $canon_h ) {
		$placeholders[] = '(%s,%s,%s,%s,%d,%d,%f,%f,%s,%s,%s)';
		$values[]       = $date;
		$values[]       = mb_substr( (string) $page, 0, 500 );
		$values[]       = mb_substr( (string) $query, 0, 500 );
		$values[]       = mb_substr( (string) $appearance, 0, 50 );
		$values[]       = $clicks;
		$values[]       = $imp;
		$values[]       = $ctr;
		$values[]       = $pos;
		$values[]       = $page_h;
		$values[]       = $query_h;
		$values[]       = $canon_h;
	}

	private static function flush_insert( $table, $placeholders, $values ) {
		global $wpdb;
		$sql = "INSERT INTO {$table} (date, page, query, search_appearance, clicks, impressions, ctr, position, page_hash, query_hash, page_canonical_hash) VALUES " . implode( ',', $placeholders );
		return $wpdb->query( $wpdb->prepare( $sql, $values ) );
	}

	private static function prune_old_rows() {
		self::maintain();
	}

	/**
	 * Tiered prune + hard size ceiling for wp_cc_gsc_queries.
	 *
	 * Runs at the end of every daily GSC sync. Three tiers (cheap first):
	 *   1. Drop impressions=1 rows older than IMP1_RETENTION_DAYS.
	 *   2. Drop impressions<=2 rows older than IMP2_RETENTION_DAYS.
	 *   3. Drop everything older than RETENTION_DAYS.
	 * Then checks `data_length + index_length` from information_schema. If still
	 * over MAX_TABLE_MB, walks the retention cutoff inward 7 days at a time
	 * (max 8 iterations) until under the cap.
	 *
	 * Outcome logged to cc_activity_log with rows-deleted + final MB.
	 *
	 * @return array Stats: deleted_imp1, deleted_imp2, deleted_old, deleted_cap, final_mb.
	 */
	public static function maintain() {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$cut_imp1 = gmdate( 'Y-m-d', strtotime( '-' . self::IMP1_RETENTION_DAYS . ' days' ) );
		$cut_imp2 = gmdate( 'Y-m-d', strtotime( '-' . self::IMP2_RETENTION_DAYS . ' days' ) );
		$cut_old  = gmdate( 'Y-m-d', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );
        $appearance_table = $wpdb->prefix . 'cc_gsc_appearances';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $appearance_table ) ) ) === $appearance_table ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$appearance_table} WHERE date < %s", $cut_old ) );
        }


		$d1 = (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE impressions = 1 AND date < %s",
			$cut_imp1
		) );
		$d2 = (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE impressions <= 2 AND date < %s",
			$cut_imp2
		) );
		$d3 = (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE date < %s",
			$cut_old
		) );

		$d_cap     = 0;
		$cap_bytes = self::MAX_TABLE_MB * 1024 * 1024;
		$size      = self::table_size_bytes( $table );
		$iters     = 0;
		while ( $size > $cap_bytes && $iters < 8 ) {
			$iters++;
			$days_to_keep = max( 7, self::RETENTION_DAYS - ( $iters * 7 ) );
			$cutoff       = gmdate( 'Y-m-d', strtotime( "-{$days_to_keep} days" ) );
			$d_cap       += (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE date < %s",
				$cutoff
			) );
			$size = self::table_size_bytes( $table );
		}

		$final_mb    = round( $size / 1024 / 1024, 1 );
		$total_dropped = $d1 + $d2 + $d3 + $d_cap;
		update_option( 'cc_assistant_gsc_last_maintain', array(
			'ts'            => current_time( 'mysql' ),
			'deleted_imp1'  => $d1,
			'deleted_imp2'  => $d2,
			'deleted_old'   => $d3,
			'deleted_cap'   => $d_cap,
			'cap_iterations' => $iters,
			'final_mb'      => $final_mb,
		), false );

		if ( $total_dropped > 0 && class_exists( 'CC_Assistant_Activity_Log' ) ) {
			$summary = sprintf(
				'gsc_prune: dropped %d rows (imp1:%d imp2:%d old:%d cap:%d), table now %s MB',
				$total_dropped, $d1, $d2, $d3, $d_cap, $final_mb
			);
			CC_Assistant_Activity_Log::record( 'db_maintenance', $summary );
		}

		return array(
			'deleted_imp1' => $d1,
			'deleted_imp2' => $d2,
			'deleted_old'  => $d3,
			'deleted_cap'  => $d_cap,
			'final_mb'     => $final_mb,
		);
	}

	/**
	 * Self-heal: recreate wp_cc_gsc_queries if it has been dropped (e.g. by an
	 * admin clearing the table via PHPMyAdmin to reclaim DB space). Idempotent
	 * — SHOW TABLES short-circuits when the table is already present, so the
	 * hot path on every sync is one cheap query.
	 *
	 * Schema must stay in lockstep with class-activator.php::create_tables().
	 * Called from cron_sync() + queue_backfill() so any code path that writes
	 * GSC rows is self-healing.
	 */
	public static function ensure_table() {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_gsc_queries';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $exists === $table ) {
			return;
		}
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE $table (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				date DATE NOT NULL,
				page VARCHAR(500) NOT NULL,
				query VARCHAR(500) NOT NULL,
				search_appearance VARCHAR(50) NOT NULL DEFAULT '',
				clicks INT UNSIGNED NOT NULL DEFAULT 0,
				impressions INT UNSIGNED NOT NULL DEFAULT 0,
				ctr DECIMAL(6,5) NOT NULL DEFAULT 0,
				position DECIMAL(6,2) NOT NULL DEFAULT 0,
				page_hash CHAR(40) NOT NULL,
				query_hash CHAR(40) NOT NULL,
				page_canonical_hash CHAR(40) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				UNIQUE KEY uniq_row (date, page_hash, query_hash, search_appearance),
				KEY date (date),
				KEY page_hash (page_hash),
				KEY page_canonical_hash (page_canonical_hash),
				KEY search_appearance (search_appearance),
				KEY impressions (impressions),
				KEY date_page (date, page_hash),
				KEY date_query (date, query_hash)
			) $charset;"
		);
		if ( class_exists( 'CC_Assistant_Activity_Log' ) ) {
			CC_Assistant_Activity_Log::record(
				'db_maintenance',
				'gsc_queries table was missing; recreated via ensure_table()'
			);
		}
	}

	/**
	 * Live size of a table in bytes (data + index). Returns 0 on failure so
	 * the cap check degrades to "skip" rather than mass-delete.
	 */
	private static function table_size_bytes( $table ) {
		global $wpdb;
		$bytes = $wpdb->get_var( $wpdb->prepare(
			"SELECT data_length + index_length
			 FROM information_schema.TABLES
			 WHERE table_schema = DATABASE() AND table_name = %s",
			$table
		) );
		return null === $bytes ? 0 : (int) $bytes;
	}

	/* ---------------------------------------------------------------------
	 * Insights — read from cached table, no external HTTP
	 * ------------------------------------------------------------------- */

	public static function opportunities( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$min_pos     = isset( $args['min_position'] ) ? max( 1.0, (float) $args['min_position'] ) : 5.0;
		$max_pos     = isset( $args['max_position'] ) ? max( $min_pos, (float) $args['max_position'] ) : 15.0;
		$min_imp     = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 50;
		$limit       = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 100;
		$days        = isset( $args['days'] ) ? max( 1, min( 90, (int) $args['days'] ) ) : 28;
		$exclude_brand = ! isset( $args['exclude_brand'] ) ? true : (bool) $args['exclude_brand'];
		// We over-fetch when filtering brand client-side, then trim to $limit.
		$fetch_limit = $exclude_brand ? min( 2000, $limit * 4 ) : $limit;

		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

		$sql = $wpdb->prepare(
			"SELECT page, query,
				SUM(impressions) AS impressions,
				SUM(clicks) AS clicks,
				CASE WHEN SUM(impressions) > 0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS ctr,
				SUM(position * impressions)/SUM(impressions) AS position
			FROM {$table}
			WHERE date >= %s
			GROUP BY page, query
			HAVING position BETWEEN %f AND %f AND impressions >= %d
			ORDER BY impressions DESC
			LIMIT %d",
			$cutoff,
			$min_pos,
			$max_pos,
			$min_imp,
			$fetch_limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = self::format_rows( $rows );
		if ( $exclude_brand ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
			$brand_terms = CC_Assistant_Query_Tagger::brand_terms();
			$out         = array_values( array_filter(
				$out,
				function ( $r ) use ( $brand_terms ) {
					return ! CC_Assistant_Query_Tagger::is_brand_query( isset( $r['query'] ) ? $r['query'] : '', $brand_terms );
				}
			) );
		}
		$out = self::suppress_recently_edited( $out, $args );
		$out = array_slice( $out, 0, $limit );
		// Live-URL annotation (see annotate_live_status). Background callers
		// (recompute_insights cron) pass check_live=false so the blob build
		// stays free of loopback HTTP.
		if ( ! isset( $args['check_live'] ) || false !== $args['check_live'] ) {
			$out = self::annotate_live_status( $out );
		}
		return $out;
	}

	public static function low_ctr( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$min_imp = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 1000;
		$max_ctr = isset( $args['max_ctr'] ) ? max( 0.0, min( 1.0, (float) $args['max_ctr'] ) ) : 0.02;
		$limit   = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 100;
		$days    = isset( $args['days'] ) ? max( 1, min( 90, (int) $args['days'] ) ) : 28;

		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

		$sql = $wpdb->prepare(
			"SELECT page,
				SUM(impressions) AS impressions,
				SUM(clicks) AS clicks,
				CASE WHEN SUM(impressions) > 0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS ctr,
				SUM(position * impressions)/SUM(impressions) AS position
			FROM {$table}
			WHERE date >= %s
			GROUP BY page
			HAVING impressions >= %d AND ctr <= %f
			ORDER BY impressions DESC
			LIMIT %d",
			$cutoff,
			$min_imp,
			$max_ctr,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = self::format_rows( $rows, true );
		$out  = self::suppress_recently_edited( $out, $args );
		// Live-URL annotation: deliberately pruned (404/410) pages kept
		// surfacing as top "optimization candidates" because the GSC cache
		// table trails reality by weeks. Flag them instead of recommending.
		// Background callers (weekly-advisor cron) pass check_live=false so
		// no loopback HTTP ever runs on the cron path.
		if ( ! isset( $args['check_live'] ) || false !== $args['check_live'] ) {
			$out = self::annotate_live_status( $out );
		}
		return $out;
	}

	public static function page_queries( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$page    = isset( $args['page'] ) ? (string) $args['page'] : '';
		$limit   = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 100;
		$days    = isset( $args['days'] ) ? max( 1, min( 90, (int) $args['days'] ) ) : 28;

		if ( $post_id > 0 && ! $page ) {
			$page = (string) get_permalink( $post_id );
		}
		if ( ! $page ) {
			return new WP_Error( 'invalid_args', 'Provide post_id or page.' );
		}

		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

		$sql = $wpdb->prepare(
			"SELECT query,
				SUM(impressions) AS impressions,
				SUM(clicks) AS clicks,
				CASE WHEN SUM(impressions) > 0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS ctr,
				SUM(position * impressions)/SUM(impressions) AS position
			FROM {$table}
			WHERE date >= %s AND page_hash = %s
			GROUP BY query
			ORDER BY impressions DESC
			LIMIT %d",
			$cutoff,
			sha1( $page ),
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return array(
			'page'    => $page,
			'days'    => $days,
			'count'   => count( $rows ),
			'queries' => self::format_query_rows( $rows ),
		);
	}

	public static function missing_mentions( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$min_imp = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 100;
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$days    = isset( $args['days'] ) ? max( 1, min( 90, (int) $args['days'] ) ) : 28;

		$candidates = self::opportunities(
			array(
				'min_position'    => 1,
				'max_position'    => 30,
				'min_impressions' => $min_imp,
				'limit'           => $limit * 5,
				'days'            => $days,
				// Annotations are not part of this method's output contract;
				// skip up to 20 loopback HEAD requests.
				'check_live'      => false,
			)
		);

		$results = array();
		foreach ( $candidates as $row ) {
			// url_to_postid() returned 0 for any consolidated URL, and this
			// loop silently `continue`d — so a query ranking on a redirected
			// URL never got analyzed at all.
			$post_id = CC_Assistant_URL_Resolver::to_post_id( $row['page'] );
			if ( ! $post_id ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$haystack = mb_strtolower( wp_strip_all_tags( $post->post_title . ' ' . $post->post_content ) );
			$needle   = mb_strtolower( $row['query'] );
			if ( '' === $needle ) {
				continue;
			}
			if ( mb_strpos( $haystack, $needle ) === false ) {
				$row['post_id']    = $post_id;
				$row['post_title'] = $post->post_title;
				$row['edit_url']   = get_edit_post_link( $post_id, 'raw' );
				$results[]         = $row;
				if ( count( $results ) >= $limit ) {
					break;
				}
			}
		}
		return $results;
	}

	private static function format_rows( $rows, $page_only = false ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $r ) {
			$entry = array(
				'page'        => isset( $r['page'] ) ? $r['page'] : '',
				'impressions' => (int) ( isset( $r['impressions'] ) ? $r['impressions'] : 0 ),
				'clicks'      => (int) ( isset( $r['clicks'] ) ? $r['clicks'] : 0 ),
				'ctr'         => round( (float) ( isset( $r['ctr'] ) ? $r['ctr'] : 0 ), 4 ),
				'position'    => round( (float) ( isset( $r['position'] ) ? $r['position'] : 0 ), 2 ),
			);
			if ( ! $page_only && isset( $r['query'] ) ) {
				$entry['query'] = $r['query'];
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Human-readable explanation of the live-URL annotation, surfaced as a
	 * note field on the REST responses that carry annotated rows.
	 */
	public static function live_check_note() {
		return sprintf(
			'The first %d rows were checked against the live site (loopback HEAD, no redirects followed): http_status is the current response code (0 = request failed), live is true only for HTTP 200, and redirects_to is included for 3xx responses. Rows with http_status null were not checked. Results are cached per URL for 6 hours. Treat non-live rows (404/410 = deliberately pruned pages) as NOT optimization candidates.',
			self::LIVE_CHECK_MAX
		);
	}

	/**
	 * Annotate recommendation rows with the URL's CURRENT HTTP status.
	 *
	 * Why: gsc_low_ctr once returned three pages that now 404/410 (they were
	 * deliberately pruned) as the top "optimization candidates" — the cached
	 * GSC table reflects impressions from weeks ago, not whether the page
	 * still exists. The first LIVE_CHECK_MAX rows get:
	 *   - http_status  (int)  current response code, 0 when the request failed
	 *   - live         (bool) true only for HTTP 200
	 *   - redirects_to (string, 3xx only) the Location header target
	 * Rows beyond the cap get http_status = null (unchecked).
	 *
	 * Uses the plugin's browser UA (v0.35.3, CC_ASSISTANT_HTTP_UA — SG WAF
	 * 403s bot-token UAs) with HEAD, timeout 3, redirection 0. Per-URL
	 * results are transient-cached for 6 h so repeated tool calls don't
	 * re-hit the site.
	 */
	private static function annotate_live_status( $rows ) {
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return is_array( $rows ) ? $rows : array();
		}
		$checked = 0;
		foreach ( $rows as $i => $row ) {
			$url = isset( $row['page'] ) ? (string) $row['page'] : '';
			if ( '' === $url || $checked >= self::LIVE_CHECK_MAX ) {
				$rows[ $i ]['http_status'] = null;
				continue;
			}
			$checked++;
			$status = self::url_live_status( $url );
			$code   = isset( $status['code'] ) ? (int) $status['code'] : 0;

			$rows[ $i ]['http_status'] = $code;
			$rows[ $i ]['live']        = ( 200 === $code );
			if ( $code >= 300 && $code < 400 && ! empty( $status['location'] ) ) {
				$rows[ $i ]['redirects_to'] = (string) $status['location'];
			}
		}
		return $rows;
	}

	/**
	 * Resolve a URL's current HTTP status via a loopback-safe HEAD request,
	 * transient-cached per URL. Returns array{code:int, location:string};
	 * code 0 means the request itself failed (cached only 15 min so a
	 * transient network hiccup doesn't stick for 6 h).
	 */
	private static function url_live_status( $url ) {
		$key    = 'cc_gsc_live_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && array_key_exists( 'code', $cached ) ) {
			return $cached;
		}

		$ua = defined( 'CC_ASSISTANT_HTTP_UA' )
			? CC_ASSISTANT_HTTP_UA
			: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

		// Same local-TLD sslverify relaxation as class-page-robustness.php —
		// Local by Flywheel's self-signed certs otherwise fail every check.
		$own_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$is_local = ( $host === $own_host ) && (bool) preg_match( '/\.(local|test|localhost|dev)$/', $host );

		$request_args = array(
			'timeout'     => 3,
			'redirection' => 0,
			'user-agent'  => $ua,
			'sslverify'   => ! $is_local,
		);

		$response = wp_remote_head( $url, $request_args );

		// Some stacks reject HEAD (405/501) while the page is perfectly
		// live — retry once with a tiny GET before declaring a status.
		if ( ! is_wp_error( $response ) ) {
			$head_code = (int) wp_remote_retrieve_response_code( $response );
			if ( in_array( $head_code, array( 405, 501 ), true ) ) {
				$request_args['limit_response_size'] = 1024;
				$response = wp_remote_get( $url, $request_args );
			}
		}

		if ( is_wp_error( $response ) ) {
			$result = array( 'code' => 0, 'location' => '' );
			set_transient( $key, $result, 15 * MINUTE_IN_SECONDS );
			return $result;
		}

		$result = array(
			'code'     => (int) wp_remote_retrieve_response_code( $response ),
			'location' => (string) wp_remote_retrieve_header( $response, 'location' ),
		);
		set_transient( $key, $result, self::LIVE_CHECK_TTL );
		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Trend lenses — week-over-week comparisons of cached data
	 * ------------------------------------------------------------------- */

	/**
	 * Resolve two equal-length windows: a "current" window of $window_days
	 * ending today, and a prior window of the same length immediately before it.
	 * Returns [curr_start, curr_end, prev_start, prev_end] as YYYY-MM-DD.
	 */
	private static function resolve_windows( $window_days ) {
		$window_days = max( 1, min( 30, (int) $window_days ) );
		$curr_end    = gmdate( 'Y-m-d', strtotime( '-2 days' ) ); // GSC has 2-day lag
		$curr_start  = gmdate( 'Y-m-d', strtotime( $curr_end . ' -' . ( $window_days - 1 ) . ' days' ) );
		$prev_end    = gmdate( 'Y-m-d', strtotime( $curr_start . ' -1 day' ) );
		$prev_start  = gmdate( 'Y-m-d', strtotime( $prev_end . ' -' . ( $window_days - 1 ) . ' days' ) );
		return array( $curr_start, $curr_end, $prev_start, $prev_end );
	}

	public static function decayed_pages( $args = array() ) {
		return self::page_movement( $args, 'decay' );
	}

	public static function rising_pages( $args = array() ) {
		return self::page_movement( $args, 'rise' );
	}

	private static function page_movement( $args, $direction ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$window  = isset( $args['window_days'] ) ? (int) $args['window_days'] : 7;
		$min_imp = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 100;
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;

		list( $curr_start, $curr_end, $prev_start, $prev_end ) = self::resolve_windows( $window );

		$sql = $wpdb->prepare(
			"SELECT page,
				SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS curr_clicks,
				SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS prev_clicks,
				SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) AS curr_impressions,
				SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) AS prev_impressions,
				CASE WHEN SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) > 0
					THEN SUM(CASE WHEN date BETWEEN %s AND %s THEN position * impressions ELSE 0 END)
						 / SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END)
					ELSE 0 END AS curr_position,
				CASE WHEN SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) > 0
					THEN SUM(CASE WHEN date BETWEEN %s AND %s THEN position * impressions ELSE 0 END)
						 / SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END)
					ELSE 0 END AS prev_position
			FROM {$table}
			WHERE date BETWEEN %s AND %s
			GROUP BY page
			HAVING (curr_impressions + prev_impressions) >= %d",
			$curr_start, $curr_end,
			$prev_start, $prev_end,
			$curr_start, $curr_end,
			$prev_start, $prev_end,
			$curr_start, $curr_end,
			$curr_start, $curr_end,
			$curr_start, $curr_end,
			$prev_start, $prev_end,
			$prev_start, $prev_end,
			$prev_start, $prev_end,
			$prev_start, $curr_end,
			$min_imp
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$curr_clicks = (int) $r['curr_clicks'];
			$prev_clicks = (int) $r['prev_clicks'];
			$curr_imp    = (int) $r['curr_impressions'];
			$prev_imp    = (int) $r['prev_impressions'];
			$curr_pos    = (float) $r['curr_position'];
			$prev_pos    = (float) $r['prev_position'];

			// Need data in both windows to be a real movement.
			if ( $prev_imp < 1 || $curr_imp < 1 ) {
				continue;
			}

			$click_delta = $curr_clicks - $prev_clicks;
			$imp_delta   = $curr_imp - $prev_imp;
			$pos_delta   = $curr_pos - $prev_pos; // positive = ranking dropped

			$is_decay = ( $click_delta < 0 || $pos_delta > 0.5 );
			$is_rise  = ( $click_delta > 0 || $pos_delta < -0.5 );

			if ( 'decay' === $direction && ! $is_decay ) {
				continue;
			}
			if ( 'rise' === $direction && ! $is_rise ) {
				continue;
			}

			$out[] = array(
				'page'             => $r['page'],
				'curr_clicks'      => $curr_clicks,
				'prev_clicks'      => $prev_clicks,
				'click_delta'      => $click_delta,
				'curr_impressions' => $curr_imp,
				'prev_impressions' => $prev_imp,
				'impression_delta' => $imp_delta,
				'curr_position'    => round( $curr_pos, 2 ),
				'prev_position'    => round( $prev_pos, 2 ),
				'position_delta'   => round( $pos_delta, 2 ),
			);
		}

		usort(
			$out,
			function ( $a, $b ) use ( $direction ) {
				if ( 'decay' === $direction ) {
					return $a['click_delta'] <=> $b['click_delta']; // most negative first
				}
				return $b['click_delta'] <=> $a['click_delta']; // most positive first
			}
		);

		$out = self::suppress_recently_edited( $out, $args );
		return array_slice( $out, 0, $limit );
	}

	/**
	 * Queries that newly entered position 5..15 in the current window
	 * (were either absent or outside that band in the prior window).
	 *
	 * Single query with a LEFT JOIN against the prior window aggregate, instead
	 * of N+1 lookups per striking-distance pair.
	 */
	public static function new_striking_distance( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$window  = isset( $args['window_days'] ) ? (int) $args['window_days'] : 7;
		$min_imp = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 20;
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$min_pos = 5.0;
		$max_pos = 15.0;

		list( $curr_start, $curr_end, $prev_start, $prev_end ) = self::resolve_windows( $window );

		// Build current striking-distance set + prior-period aggregate in one query.
		$sql = $wpdb->prepare(
			"SELECT curr.page, curr.query,
				curr.impressions AS curr_impressions,
				curr.clicks      AS curr_clicks,
				curr.position    AS curr_position,
				prev.impressions AS prev_impressions,
				prev.position    AS prev_position
			FROM (
				SELECT page, query, page_hash, query_hash,
					SUM(impressions) AS impressions,
					SUM(clicks)      AS clicks,
					SUM(position * impressions) / SUM(impressions) AS position
				FROM {$table}
				WHERE date BETWEEN %s AND %s
				GROUP BY page_hash, query_hash, page, query
				HAVING position BETWEEN %f AND %f AND impressions >= %d
			) curr
			LEFT JOIN (
				SELECT page_hash, query_hash,
					SUM(impressions) AS impressions,
					SUM(position * impressions) / SUM(impressions) AS position
				FROM {$table}
				WHERE date BETWEEN %s AND %s
				GROUP BY page_hash, query_hash
			) prev
				ON prev.page_hash = curr.page_hash AND prev.query_hash = curr.query_hash
			WHERE prev.impressions IS NULL OR prev.position > %f
			ORDER BY curr.impressions DESC
			LIMIT %d",
			$curr_start,
			$curr_end,
			$min_pos,
			$max_pos,
			$min_imp,
			$prev_start,
			$prev_end,
			$max_pos,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$prev_pos = null === $r['prev_position'] || null === $r['prev_impressions'] || (int) $r['prev_impressions'] === 0 ? null : (float) $r['prev_position'];
			$out[] = array(
				'page'             => $r['page'],
				'query'            => $r['query'],
				'curr_position'    => round( (float) $r['curr_position'], 2 ),
				'prev_position'    => null === $prev_pos ? null : round( $prev_pos, 2 ),
				'curr_impressions' => (int) $r['curr_impressions'],
				'prev_impressions' => null === $r['prev_impressions'] ? 0 : (int) $r['prev_impressions'],
				'curr_clicks'      => (int) $r['curr_clicks'],
				'is_brand_new'     => ( null === $prev_pos ),
			);
		}
		return self::suppress_recently_edited( $out, $args );
	}

	/**
	 * Queries that ranked in the prior window but disappeared in the current window.
	 */
	public static function lost_queries( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$window  = isset( $args['window_days'] ) ? (int) $args['window_days'] : 7;
		$min_imp = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 50;
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;

		list( $curr_start, $curr_end, $prev_start, $prev_end ) = self::resolve_windows( $window );

		$sql = $wpdb->prepare(
			"SELECT prev.page, prev.query,
				SUM(prev.impressions) AS prev_impressions,
				SUM(prev.clicks) AS prev_clicks,
				SUM(prev.position * prev.impressions)/SUM(prev.impressions) AS prev_position
			FROM {$table} prev
			WHERE prev.date BETWEEN %s AND %s
				AND NOT EXISTS (
					SELECT 1 FROM {$table} curr
					WHERE curr.date BETWEEN %s AND %s
						AND curr.page_hash = prev.page_hash
						AND curr.query_hash = prev.query_hash
				)
			GROUP BY prev.page_hash, prev.query_hash, prev.page, prev.query
			HAVING prev_impressions >= %d
			ORDER BY prev_impressions DESC
			LIMIT %d",
			$prev_start,
			$prev_end,
			$curr_start,
			$curr_end,
			$min_imp,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'page'             => $r['page'],
				'query'            => $r['query'],
				'prev_impressions' => (int) $r['prev_impressions'],
				'prev_clicks'      => (int) $r['prev_clicks'],
				'prev_position'    => round( (float) $r['prev_position'], 2 ),
			);
		}
		return $out;
	}

	/**
	 * Anomaly detection — z-score of the last $window_days vs trailing baseline.
	 *
	 * Per-page click totals: take last N daily samples in baseline, compute mean
	 * + stddev, then score each of the recent N days. Pages where the z-score is
	 * worse than -1.5 (decay) or better than +1.5 (rise) get flagged. Reduces
	 * the false-positive rate of fixed-threshold "click_delta < 0" decay.
	 */
	public static function anomalous_pages( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$window  = isset( $args['window_days'] ) ? max( 3, min( 14, (int) $args['window_days'] ) ) : 7;
		$baseline = isset( $args['baseline_days'] ) ? max( 14, min( 60, (int) $args['baseline_days'] ) ) : 28;
		$min_imp = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 50;
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$direction = isset( $args['direction'] ) ? (string) $args['direction'] : 'both';
		$z_thresh = 1.5;

		$today_ts   = strtotime( gmdate( 'Y-m-d', strtotime( '-2 days' ) ) );
		$window_start = gmdate( 'Y-m-d', $today_ts - ( $window - 1 ) * DAY_IN_SECONDS );
		$baseline_end = gmdate( 'Y-m-d', $today_ts - $window * DAY_IN_SECONDS );
		$baseline_start = gmdate( 'Y-m-d', $today_ts - ( $window + $baseline - 1 ) * DAY_IN_SECONDS );

		// Daily totals per page over the full range.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page, page_hash, date, SUM(clicks) AS clicks, SUM(impressions) AS impressions
				FROM {$table}
				WHERE date BETWEEN %s AND %s
				GROUP BY page_hash, page, date",
				$baseline_start,
				gmdate( 'Y-m-d', $today_ts )
			),
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return array();
		}

		// Bucket by page_hash.
		$series = array();
		foreach ( $rows as $r ) {
			$ph = $r['page_hash'];
			if ( ! isset( $series[ $ph ] ) ) {
				$series[ $ph ] = array( 'page' => $r['page'], 'clicks' => array(), 'impressions' => 0 );
			}
			$series[ $ph ]['clicks'][ $r['date'] ] = (int) $r['clicks'];
			$series[ $ph ]['impressions']         += (int) $r['impressions'];
		}

		$out = array();
		foreach ( $series as $ph => $data ) {
			if ( $data['impressions'] < $min_imp ) {
				continue;
			}
			$baseline_samples = array();
			$window_samples   = array();
			foreach ( $data['clicks'] as $date => $val ) {
				if ( $date < $baseline_start ) {
					continue;
				}
				if ( $date <= $baseline_end ) {
					$baseline_samples[] = $val;
				} elseif ( $date >= $window_start ) {
					$window_samples[] = $val;
				}
			}
			if ( count( $baseline_samples ) < 7 || empty( $window_samples ) ) {
				continue;
			}

			$mean = array_sum( $baseline_samples ) / count( $baseline_samples );
			$var  = 0.0;
			foreach ( $baseline_samples as $v ) {
				$var += ( $v - $mean ) * ( $v - $mean );
			}
			$std = sqrt( $var / count( $baseline_samples ) );
			if ( $std < 0.5 ) {
				$std = 0.5; // floor to avoid div-by-zero blowups on flat series
			}

			$window_mean = array_sum( $window_samples ) / count( $window_samples );
			$z           = ( $window_mean - $mean ) / $std;

			$is_decay = $z <= -$z_thresh;
			$is_rise  = $z >= $z_thresh;

			if ( 'decay' === $direction && ! $is_decay ) {
				continue;
			}
			if ( 'rise' === $direction && ! $is_rise ) {
				continue;
			}
			if ( 'both' === $direction && ! $is_decay && ! $is_rise ) {
				continue;
			}

			$out[] = array(
				'page'           => $data['page'],
				'baseline_mean'  => round( $mean, 2 ),
				'baseline_stddev' => round( $std, 2 ),
				'window_mean'    => round( $window_mean, 2 ),
				'z_score'        => round( $z, 2 ),
				'direction'      => $is_decay ? 'decay' : 'rise',
				'baseline_days'  => count( $baseline_samples ),
				'window_days'    => count( $window_samples ),
			);
		}

		usort(
			$out,
			function ( $a, $b ) {
				return abs( $b['z_score'] ) <=> abs( $a['z_score'] );
			}
		);

		return array_slice( $out, 0, $limit );
	}

	/**
	 * Pages getting AI Overview / SGE impressions. Helps separate "title is bad"
	 * from "Google is summarizing the answer and eating clicks".
	 */
	public static function ai_overview_pages( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_appearances';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return array(); }

		$min_imp = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 100;
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$days    = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;

		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

		// Surface any non-empty, non-"Web" search_appearance bucket: AI Overview, Featured snippet, Rich result, etc.
		$sql = $wpdb->prepare(
			"SELECT page, search_appearance,
				SUM(impressions) AS impressions,
				SUM(clicks) AS clicks,
				CASE WHEN SUM(impressions) > 0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS ctr,
				SUM(position * impressions)/NULLIF(SUM(impressions), 0) AS position
			FROM {$table}
			WHERE date >= %s AND search_appearance <> '' AND search_appearance <> 'Web'
			GROUP BY page, search_appearance
			HAVING impressions >= %d
			ORDER BY impressions DESC
			LIMIT %d",
			$cutoff,
			$min_imp,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $r ) {
			$out[] = array(
				'page'              => $r['page'],
				'search_appearance' => $r['search_appearance'],
				'measurement_type' => 'observed_search_appearance_not_ai_attribution',
				'ai_overview_confirmed' => false,
				'impressions'       => (int) $r['impressions'],
				'clicks'            => (int) $r['clicks'],
				'ctr'               => round( (float) $r['ctr'], 4 ),
				'position'          => round( (float) ( null === $r['position'] ? 0 : $r['position'] ), 2 ),
			);
		}
		return $out;
	}

	/**
	 * AIO CTR-drop alert: flag pages ranking top-N (default position ≤5)
	 * where the actual CTR is sharply below the industry expected curve.
	 * This is the signature of AI Overview cannibalization — the page
	 * ranks but Google's AIO has already answered the query, eating the
	 * click. Different intervention than a title rewrite.
	 *
	 * Four converging 2025-2026 studies (Ahrefs 58%, Seer 49-65%,
	 * Authoritas 47.5%, Indig 50%+) measured 47-65% CTR loss at position
	 * 1 when an AIO is present. Default threshold (max_ratio 0.5) catches
	 * pages running at half of expected CTR or worse.
	 *
	 * Cross-references the search_appearance breakdown: pages with
	 * recorded non-Web impressions are marked confirmed; others suspected.
	 */
	public static function aio_ctr_drop_alert( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$min_imp      = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 500;
		$max_position = isset( $args['max_position'] ) ? max( 1.0, min( 10.0, (float) $args['max_position'] ) ) : 5.0;
		$max_ratio    = isset( $args['max_ratio'] ) ? max( 0.0, min( 1.0, (float) $args['max_ratio'] ) ) : 0.5;
		$limit        = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$days         = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;

		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

		// Page-level aggregates: impressions, clicks, ctr, position.
		$sql = $wpdb->prepare(
			"SELECT page,
				SUM(impressions) AS impressions,
				SUM(clicks) AS clicks,
				CASE WHEN SUM(impressions) > 0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS ctr,
				CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions)/SUM(impressions) ELSE 0 END AS position
			FROM {$table}
			WHERE date >= %s
			GROUP BY page
			HAVING impressions >= %d AND position <= %f
			ORDER BY impressions DESC",
			$cutoff,
			$min_imp,
			$max_position
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		// Search-appearance labels do not identify causal AI click loss.
        $aio_map = array();

		$flagged = array();
		foreach ( $rows as $r ) {
			$position = (float) $r['position'];
			$ctr      = (float) $r['ctr'];
			$expected = self::expected_ctr_for_position( $position );
			if ( $expected <= 0 ) {
				continue;
			}
			$ratio = $ctr / $expected;
			if ( $ratio > $max_ratio ) {
				continue;
			}

			$page         = (string) $r['page'];
			$impressions  = (int) $r['impressions'];
			$aio_impr     = isset( $aio_map[ $page ] ) ? (int) $aio_map[ $page ] : 0;
			$aio_share    = $impressions > 0 ? $aio_impr / $impressions : 0;
			$verdict      = 'not_established';
			$lost_clicks  = max( 0, (int) round( ( $expected - $ctr ) * $impressions ) );
			$entry        = array(
				'page'                          => $page,
				'impressions'                   => $impressions,
				'clicks'                        => (int) $r['clicks'],
				'ctr'                           => round( $ctr, 4 ),
				'expected_ctr'                  => round( $expected, 4 ),
				'ratio'                         => round( $ratio, 3 ),
				'position'                      => round( $position, 2 ),
				'aio_impressions'               => null,
				'aio_share'                     => null,
				'suspected_aio_cannibalization' => $verdict,
				'estimated_clicks_lost'         => null,
				'heuristic_click_shortfall' => $lost_clicks,
				'assessment_type' => 'low_ctr_candidate_not_causal_attribution',
			);

			// Enrich with post_id + title + edit_url (same pattern as missing_mentions).
			$post_id = CC_Assistant_URL_Resolver::to_post_id( $page );
			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$entry['post_id']    = $post_id;
					$entry['post_title'] = $post->post_title;
					$entry['edit_url']   = get_edit_post_link( $post_id, 'raw' );
				}
			}

			$flagged[] = $entry;
		}

		// Highest impact first: most estimated clicks lost.
		usort(
			$flagged,
			function ( $a, $b ) {
				return $b['estimated_clicks_lost'] - $a['estimated_clicks_lost'];
			}
		);
		$out = array_slice( $flagged, 0, $limit );
		return self::suppress_recently_edited( $out, $args );
	}

	/**
	 * Industry baseline expected CTR by SERP position. Conservative
	 * 2024 Backlinko + Advanced Web Ranking averages — the values are
	 * intentionally lower than the original Backlinko numbers so the
	 * drop-ratio comparison surfaces genuine outliers, not normal noise.
	 * Linear interpolation between integer positions.
	 */
	private static function expected_ctr_for_position( $position ) {
		static $curve = array(
			1  => 0.317,
			2  => 0.247,
			3  => 0.187,
			4  => 0.136,
			5  => 0.095,
			6  => 0.062,
			7  => 0.042,
			8  => 0.031,
			9  => 0.026,
			10 => 0.024,
		);
		if ( $position < 1.0 ) {
			return $curve[1];
		}
		if ( $position >= 10.0 ) {
			return $curve[10];
		}
		$lo   = (int) floor( $position );
		$hi   = $lo + 1;
		$frac = $position - $lo;
		return ( $curve[ $lo ] * ( 1 - $frac ) ) + ( $curve[ $hi ] * $frac );
	}

	/**
	 * Group the queries a page (or all pages) ranks for by intent.
	 * Useful for "is this page covering all the intents around its topic".
	 */
	public static function intent_breakdown( $args = array() ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';

		$days     = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;
		$post_id  = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$limit    = isset( $args['limit'] ) ? max( 10, min( 1000, (int) $args['limit'] ) ) : 500;
		$cutoff   = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

		$where_extra = '';
		$args_extra  = array();
		if ( $post_id > 0 ) {
			$page = (string) get_permalink( $post_id );
			if ( $page ) {
				$where_extra = ' AND page_hash = %s';
				$args_extra  = array( sha1( $page ) );
			}
		}

		$sql = $wpdb->prepare(
			"SELECT query, SUM(impressions) AS impressions, SUM(clicks) AS clicks
			FROM {$table}
			WHERE date >= %s {$where_extra}
			GROUP BY query
			ORDER BY impressions DESC
			LIMIT %d",
			array_merge( array( $cutoff ), $args_extra, array( $limit ) )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return array(
				'days'    => $days,
				'post_id' => $post_id,
				'buckets' => array(),
			);
		}

		$brand_terms = CC_Assistant_Query_Tagger::brand_terms();
		$buckets     = array();
		foreach ( $rows as $r ) {
			$query  = (string) $r['query'];
			$intent = CC_Assistant_Query_Tagger::classify_intent( $query );
			if ( ! isset( $buckets[ $intent ] ) ) {
				$buckets[ $intent ] = array(
					'intent'      => $intent,
					'queries'     => 0,
					'impressions' => 0,
					'clicks'      => 0,
					'samples'     => array(),
				);
			}
			$buckets[ $intent ]['queries']     += 1;
			$buckets[ $intent ]['impressions'] += (int) $r['impressions'];
			$buckets[ $intent ]['clicks']      += (int) $r['clicks'];
			if ( count( $buckets[ $intent ]['samples'] ) < 5 ) {
				$buckets[ $intent ]['samples'][] = $query;
			}
		}

		usort(
			$buckets,
			function ( $a, $b ) {
				return $b['impressions'] <=> $a['impressions'];
			}
		);

		return array(
			'days'    => $days,
			'post_id' => $post_id,
			'buckets' => array_values( $buckets ),
		);
	}

	public static function trends_summary( $window_days = 7 ) {
		list( $curr_start, $curr_end, $prev_start, $prev_end ) = self::resolve_windows( $window_days );
		return array(
			'window_days'   => (int) $window_days,
			'current'       => array( 'start' => $curr_start, 'end' => $curr_end ),
			'previous'      => array( 'start' => $prev_start, 'end' => $prev_end ),
			// Every sub-report below is a window-over-window comparison, which
			// is exactly the read the inflated-impressions bug fabricates.
			'data_integrity' => self::impressions_integrity( $prev_start ),
			'decayed'       => self::decayed_pages( array( 'window_days' => $window_days, 'limit' => 10 ) ),
			'rising'        => self::rising_pages( array( 'window_days' => $window_days, 'limit' => 10 ) ),
			'new_striking'  => self::new_striking_distance( array( 'window_days' => $window_days, 'limit' => 10 ) ),
			'lost'          => self::lost_queries( array( 'window_days' => $window_days, 'limit' => 10 ) ),
			'anomalies'     => self::anomalous_pages( array( 'window_days' => $window_days, 'limit' => 10 ) ),
			'ai_overview'   => self::ai_overview_pages( array( 'limit' => 10, 'days' => 28 ) ),
		);
	}

	private static function format_query_rows( $rows ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $r ) {
			$out[] = array(
				'query'       => isset( $r['query'] ) ? $r['query'] : '',
				'impressions' => (int) ( isset( $r['impressions'] ) ? $r['impressions'] : 0 ),
				'clicks'      => (int) ( isset( $r['clicks'] ) ? $r['clicks'] : 0 ),
				'ctr'         => round( (float) ( isset( $r['ctr'] ) ? $r['ctr'] : 0 ), 4 ),
				'position'    => round( (float) ( isset( $r['position'] ) ? $r['position'] : 0 ), 2 ),
			);
		}
		return $out;
	}
}
