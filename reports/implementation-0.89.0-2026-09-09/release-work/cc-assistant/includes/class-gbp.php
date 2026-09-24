<?php
/**
 * Google Business Profile (GBP) integration.
 *
 * OAuth 2.0 (BYO Google Cloud client), mirroring the Search Console flow in
 * class-gsc.php: token storage is AES-GCM encrypted at rest, the OAuth handler
 * runs on admin_init only when the callback params are present, and no external
 * HTTP fires during normal admin renders (location data is cached in an option,
 * refreshed on demand or by cron).
 *
 * One OAuth client + one scope (business.manage) covers every enabled GBP API:
 *   - My Business Account Management API  -> accounts
 *   - My Business Business Information API -> locations + categories
 *   - Business Profile Performance API     -> KPI time series
 * (Reviews live in the legacy Business Profile API v4, which requires a separate
 *  Google access-approval; this class is structured to add them once granted.)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_GBP {

	const OPT_CLIENT_ID     = 'cc_assistant_gbp_client_id';
	const OPT_CLIENT_SECRET = 'cc_assistant_gbp_client_secret';
	const OPT_TOKENS        = 'cc_assistant_gbp_tokens';
	const OPT_LAST_ERROR    = 'cc_assistant_gbp_last_error';
	const OPT_STATE         = 'cc_assistant_gbp_oauth_state';
	const OPT_REDIRECT_URI  = 'cc_assistant_gbp_redirect_override';
	const OPT_LOCATIONS     = 'cc_assistant_gbp_locations';
	const OPT_LAST_FETCH    = 'cc_assistant_gbp_last_fetch';

	const SCOPE     = 'https://www.googleapis.com/auth/business.manage';
	const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	const ACCOUNTS_URL    = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';
	const LOCATIONS_URL   = 'https://mybusinessbusinessinformation.googleapis.com/v1/%s/locations';
	const PERFORMANCE_URL = 'https://businessprofileperformance.googleapis.com/v1/%s:fetchMultiDailyMetricsTimeSeries';

	// Fields requested for each location. readMask is REQUIRED by the Business
	// Information API or it 400s.
	const LOCATION_READ_MASK = 'name,title,storefrontAddress,categories,phoneNumbers,websiteUri,metadata';

	/* ---------------------------------------------------------------------
	 * Config / status
	 * ------------------------------------------------------------------- */

	public static function redirect_uri() {
		$override = trim( (string) get_option( self::OPT_REDIRECT_URI, '' ) );
		if ( $override ) {
			return $override;
		}
		return self::default_redirect_uri();
	}

	public static function default_redirect_uri() {
		return admin_url( 'admin.php?page=cc-assistant-settings&tab=gbp&cc_gbp_oauth=callback' );
	}

	/**
	 * True if the auto-derived redirect URI uses a TLD Google won't accept
	 * (.local, .test, etc.). Mirrors the GSC local-only detection.
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

	public static function get_locations() {
		$blob = get_option( self::OPT_LOCATIONS, array() );
		return is_array( $blob ) ? $blob : array();
	}

	public static function status() {
		$last_err   = get_option( self::OPT_LAST_ERROR, null );
		$last_fetch = get_option( self::OPT_LAST_FETCH, null );
		$locations  = self::get_locations();

		return array(
			'configured'     => self::is_configured(),
			'connected'      => self::is_connected(),
			'redirect_uri'   => self::redirect_uri(),
			'location_count' => count( $locations ),
			'locations'      => $locations,
			'last_fetch_at'  => $last_fetch ? gmdate( 'c', (int) $last_fetch ) : null,
			'last_error'     => $last_err,
		);
	}

	/* ---------------------------------------------------------------------
	 * OAuth flow (mirrors class-gsc.php)
	 * ------------------------------------------------------------------- */

	public static function maybe_handle_oauth() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['cc_gbp_oauth'] ) ) {
			return;
		}

		$action = sanitize_key( $_GET['cc_gbp_oauth'] );

		if ( 'connect' === $action ) {
			self::handle_connect();
			return;
		}
		if ( 'callback' === $action ) {
			self::handle_callback();
			return;
		}
		if ( 'disconnect' === $action ) {
			check_admin_referer( 'cc_gbp_disconnect' );
			delete_option( self::OPT_TOKENS );
			delete_option( self::OPT_LOCATIONS );
			delete_option( self::OPT_LAST_FETCH );
			delete_option( self::OPT_LAST_ERROR );
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'disconnected' ) ) );
			exit;
		}
		if ( 'refresh_locations' === $action ) {
			check_admin_referer( 'cc_gbp_refresh_locations' );
			$res = self::fetch_locations();
			$msg = is_wp_error( $res ) ? 'fetch_error' : 'locations_refreshed';
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => $msg ) ) );
			exit;
		}
	}

	private static function settings_url( $extra = array() ) {
		$url = admin_url( 'admin.php?page=cc-assistant-settings&tab=gbp' );
		if ( ! empty( $extra ) ) {
			$url = add_query_arg( $extra, $url );
		}
		return $url;
	}

	private static function handle_connect() {
		check_admin_referer( 'cc_gbp_connect' );

		if ( ! self::is_configured() ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'missing_creds' ) ) );
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
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'state_mismatch' ) ) );
			exit;
		}

		if ( isset( $_GET['error'] ) ) {
			update_option( self::OPT_LAST_ERROR, 'OAuth: ' . sanitize_text_field( wp_unslash( $_GET['error'] ) ), false );
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'oauth_error' ) ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( ! $code ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'oauth_error' ) ) );
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
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'oauth_error' ) ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : wp_remote_retrieve_body( $response );
			update_option( self::OPT_LAST_ERROR, 'Token exchange: ' . substr( (string) $msg, 0, 500 ), false );
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'oauth_error' ) ) );
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
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'oauth_error' ) ) );
			exit;
		}
		delete_option( self::OPT_LAST_ERROR );

		// Best-effort first fetch so the panel shows locations immediately.
		self::fetch_locations();

		wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'connected' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Token storage (AES-GCM at rest, keyed off wp_salt — same as GSC)
	 * ------------------------------------------------------------------- */

	private static function encryption_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|cc-gbp', true );
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
			return new WP_Error( 'gbp_not_connected', 'Not connected to Google Business Profile.' );
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
			return new WP_Error( 'gbp_refresh_failed', $msg );
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
	 * API calls
	 * ------------------------------------------------------------------- */

	/**
	 * Authenticated GET. Retries once after a forced token refresh on a 401
	 * (covers an access token that expired mid-flight).
	 */
	private static function api_get( $url, $retry = true ) {
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
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code && $retry ) {
			$refresh = self::refresh_access_token();
			if ( is_wp_error( $refresh ) ) {
				// Surface the real reason (e.g. revoked/expired refresh token =
				// "needs reconnect") instead of swallowing it and returning a
				// generic HTTP 401 that's undiagnosable from the Last error panel.
				return $refresh;
			}
			return self::api_get( $url, false );
		}
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
			return new WP_Error( 'gbp_api_error', $msg, array( 'status' => $code ) );
		}
		return is_array( $body ) ? $body : array();
	}

	/**
	 * List GBP accounts the connected user can manage. Returns array of
	 * { name: "accounts/123", account_name, type } or WP_Error.
	 */
	public static function list_accounts() {
		$out  = array();
		$page = '';
		do {
			$url = self::ACCOUNTS_URL . '?pageSize=100' . ( $page ? '&pageToken=' . rawurlencode( $page ) : '' );
			$res = self::api_get( $url );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			foreach ( (array) ( $res['accounts'] ?? array() ) as $a ) {
				$out[] = array(
					'name'         => isset( $a['name'] ) ? (string) $a['name'] : '',
					'account_name' => isset( $a['accountName'] ) ? (string) $a['accountName'] : '',
					'type'         => isset( $a['type'] ) ? (string) $a['type'] : '',
				);
			}
			$page = isset( $res['nextPageToken'] ) ? (string) $res['nextPageToken'] : '';
		} while ( $page );
		return $out;
	}

	/**
	 * List locations under one account name ("accounts/123"). Returns array of
	 * normalized location rows or WP_Error.
	 */
	public static function list_locations( $account_name ) {
		$account_name = (string) $account_name;
		if ( '' === $account_name ) {
			return new WP_Error( 'gbp_bad_account', 'account name is required.' );
		}
		$out  = array();
		$page = '';
		do {
			// Do NOT rawurlencode the resource name into the path: "accounts/123"
			// is a multi-segment path-template position ({name=accounts/*}) where
			// the slash must stay literal. Encoding it to %2F breaks routing.
			$url = sprintf( self::LOCATIONS_URL, $account_name )
				. '?readMask=' . rawurlencode( self::LOCATION_READ_MASK )
				. '&pageSize=100'
				. ( $page ? '&pageToken=' . rawurlencode( $page ) : '' );
			$res = self::api_get( $url );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			foreach ( (array) ( $res['locations'] ?? array() ) as $loc ) {
				$out[] = self::normalize_location( $loc, $account_name );
			}
			$page = isset( $res['nextPageToken'] ) ? (string) $res['nextPageToken'] : '';
		} while ( $page );
		return $out;
	}

	private static function normalize_location( $loc, $account_name ) {
		$primary = '';
		$additional = array();
		if ( isset( $loc['categories']['primaryCategory']['displayName'] ) ) {
			$primary = (string) $loc['categories']['primaryCategory']['displayName'];
		}
		if ( ! empty( $loc['categories']['additionalCategories'] ) && is_array( $loc['categories']['additionalCategories'] ) ) {
			foreach ( $loc['categories']['additionalCategories'] as $c ) {
				if ( isset( $c['displayName'] ) ) {
					$additional[] = (string) $c['displayName'];
				}
			}
		}
		$address = '';
		if ( ! empty( $loc['storefrontAddress'] ) && is_array( $loc['storefrontAddress'] ) ) {
			$lines   = isset( $loc['storefrontAddress']['addressLines'] ) ? (array) $loc['storefrontAddress']['addressLines'] : array();
			$city    = isset( $loc['storefrontAddress']['locality'] ) ? $loc['storefrontAddress']['locality'] : '';
			$region  = isset( $loc['storefrontAddress']['administrativeArea'] ) ? $loc['storefrontAddress']['administrativeArea'] : '';
			$zip     = isset( $loc['storefrontAddress']['postalCode'] ) ? $loc['storefrontAddress']['postalCode'] : '';
			$address = trim( implode( ', ', array_filter( array( implode( ' ', $lines ), $city, trim( $region . ' ' . $zip ) ) ) ) );
		}
		return array(
			'name'                  => isset( $loc['name'] ) ? (string) $loc['name'] : '',
			'account'               => $account_name,
			'title'                 => isset( $loc['title'] ) ? (string) $loc['title'] : '',
			'primary_category'      => $primary,
			'additional_categories' => $additional,
			'address'               => $address,
			'phone'                 => isset( $loc['phoneNumbers']['primaryPhone'] ) ? (string) $loc['phoneNumbers']['primaryPhone'] : '',
			'website'               => isset( $loc['websiteUri'] ) ? (string) $loc['websiteUri'] : '',
			'place_id'              => isset( $loc['metadata']['placeId'] ) ? (string) $loc['metadata']['placeId'] : '',
			'maps_uri'              => isset( $loc['metadata']['mapsUri'] ) ? (string) $loc['metadata']['mapsUri'] : '',
		);
	}

	/**
	 * Fetch every location across every managed account and cache the result in
	 * an option. Returns the location list or WP_Error (and records last_error).
	 */
	public static function fetch_locations() {
		$accounts = self::list_accounts();
		if ( is_wp_error( $accounts ) ) {
			update_option( self::OPT_LAST_ERROR, 'List accounts: ' . $accounts->get_error_message(), false );
			return $accounts;
		}
		$all = array();
		foreach ( $accounts as $acct ) {
			if ( empty( $acct['name'] ) ) {
				continue;
			}
			$locs = self::list_locations( $acct['name'] );
			if ( is_wp_error( $locs ) ) {
				update_option( self::OPT_LAST_ERROR, 'List locations (' . $acct['name'] . '): ' . $locs->get_error_message(), false );
				return $locs;
			}
			foreach ( $locs as $l ) {
				$l['account_name'] = $acct['account_name'];
				$all[]             = $l;
			}
		}
		update_option( self::OPT_LOCATIONS, $all, false );
		update_option( self::OPT_LAST_FETCH, time(), false );
		if ( isset( $saved ) && is_wp_error( $saved ) ) {
			wp_safe_redirect( self::settings_url( array( 'cc_gbp_msg' => 'oauth_error' ) ) );
			exit;
		}
		delete_option( self::OPT_LAST_ERROR );
		return $all;
	}

	/**
	 * Daily performance metrics for one location over the trailing N days.
	 * location_name = "locations/456". Returns { metric => total } or WP_Error.
	 * Defaults to the high-signal local-pack actions.
	 */
	public static function fetch_performance( $location_name, $days = 30, $metrics = array() ) {
		$location_name = (string) $location_name;
		if ( '' === $location_name ) {
			return new WP_Error( 'gbp_bad_location', 'location name is required.' );
		}
		// Clamp to the Performance API's ~18-month retention minus the 3-day lag
		// pad (start = -(days+3)); a larger window 400s on an out-of-range start.
		$days = max( 1, min( (int) $days, 537 ) );
		if ( empty( $metrics ) ) {
			$metrics = array(
				'CALL_CLICKS',
				'WEBSITE_CLICKS',
				'BUSINESS_DIRECTION_REQUESTS',
				'BUSINESS_IMPRESSIONS_DESKTOP_MAPS',
				'BUSINESS_IMPRESSIONS_MOBILE_MAPS',
				'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH',
				'BUSINESS_IMPRESSIONS_MOBILE_SEARCH',
			);
		}
		// GBP performance data lags ~2-3 days; end the window a few days back.
		$end   = strtotime( '-3 days' );
		$start = strtotime( '-' . ( (int) $days + 3 ) . ' days' );

		$args = array();
		foreach ( $metrics as $m ) {
			$args[] = 'dailyMetrics=' . rawurlencode( $m );
		}
		$args[] = 'dailyRange.start_date.year=' . gmdate( 'Y', $start );
		$args[] = 'dailyRange.start_date.month=' . gmdate( 'n', $start );
		$args[] = 'dailyRange.start_date.day=' . gmdate( 'j', $start );
		$args[] = 'dailyRange.end_date.year=' . gmdate( 'Y', $end );
		$args[] = 'dailyRange.end_date.month=' . gmdate( 'n', $end );
		$args[] = 'dailyRange.end_date.day=' . gmdate( 'j', $end );

		// Resource name "locations/456" sits in the {location=locations/*} path
		// template — keep the slash literal (no rawurlencode), or routing fails.
		$url = sprintf( self::PERFORMANCE_URL, $location_name ) . '?' . implode( '&', $args );
		$res = self::api_get( $url );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$totals = array();
		foreach ( (array) ( $res['multiDailyMetricTimeSeries'] ?? array() ) as $series_group ) {
			foreach ( (array) ( $series_group['dailyMetricTimeSeries'] ?? array() ) as $series ) {
				$metric = isset( $series['dailyMetric'] ) ? (string) $series['dailyMetric'] : 'UNKNOWN';
				$sum    = 0;
				foreach ( (array) ( $series['timeSeries']['datedValues'] ?? array() ) as $dv ) {
					$sum += (int) ( $dv['value'] ?? 0 );
				}
				$totals[ $metric ] = ( $totals[ $metric ] ?? 0 ) + $sum;
			}
		}
		return array(
			'location'    => $location_name,
			'days'        => (int) $days,
			'metrics'     => $totals,
			'window_end'  => gmdate( 'Y-m-d', $end ),
		);
	}
}
