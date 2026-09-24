<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Reviewed native frontend toggles. No hosting, CAPTCHA or .htaccess controls. */
class CC_Assistant_SiteGround_Adapter {
	const ID = 'siteground_frontend_v1';
	const KEYS = array( 'optimize_css', 'optimize_javascript', 'combine_javascript', 'optimize_javascript_async', 'optimize_html', 'optimize_web_fonts', 'remove_query_strings', 'disable_emojis', 'lazyload_images' );
	// Official WordPress.org 7.8.1 source. Fail closed when the native implementation changes.
	const FILES = array(
		'core/Options/Options.php' => '6201de921ca3e1e1ab72d6fb5a22ef5a726945737986b548fc185ae9cf4f7cff',
		'core/Rest/Rest.php' => '1d7e930265ef50bb6cc70bd72594a0df524990f99ed66e1a909efb0b35a87a77',
		'core/Rest/Rest_Helper_Options.php' => 'f30afd938195b1c35db836cf6ba38d41397fb44daf80c422fa253019c0d4e767',
	);
	public static function capability() {
		$base = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR . '/sg-cachepress/' : '';
		$available = '' !== $base && function_exists( 'is_multisite' ) && ! is_multisite() && function_exists( 'is_plugin_active' ) && is_plugin_active( 'sg-cachepress/sg-cachepress.php' ) && is_callable( array( 'SiteGround_Optimizer\\Options\\Options', 'change_option' ) );
		foreach ( self::FILES as $file => $hash ) {
			if ( ! is_readable( $base . $file ) || ! hash_equals( $hash, hash_file( 'sha256', $base . $file ) ) ) { $available = false; }
		}
		$registered = class_exists( 'SiteGround_Optimizer\\Rest\\Rest' ) && property_exists( 'SiteGround_Optimizer\\Rest\\Rest', 'toggle_options' ) ? \SiteGround_Optimizer\Rest\Rest::$toggle_options : array();
		if ( array_diff( self::KEYS, (array) $registered ) ) { $available = false; }
		return array( 'adapter' => self::ID, 'available' => $available, 'reviewed_source' => 'Speed Optimizer 7.8.1 native Options::change_option and frontend toggle routing',
			'keys' => self::KEYS, 'accepted_values' => array( 0, 1 ), 'tool' => 'draft_update_plugin_setting',
			'instruction' => 'Inspect get_plugin_settings first. Use adapter=siteground_frontend_v1, an observed siteground_optimizer_ option_name, empty path, value 0 or 1. Queue one change for review, then inspect actual served pages after approval.',
			'limits' => 'Native change_option performs its cache purge. Completion of upstream cache invalidation and visual/performance improvement remain unverified. Hosting CAPTCHA, dynamic cache, memcached, gzip, browser-cache rules, multisite and unreviewed native source are excluded.' );
	}
	private static function validate( $args ) {
		$key = (string) ( $args['option_name'] ?? '' );
		if ( self::ID !== ( $args['adapter'] ?? '' ) || ! in_array( $key, array_map( static function ( $s ) { return 'siteground_optimizer_' . $s; }, self::KEYS ), true ) || ! empty( $args['path'] ) || ! empty( $args['allow_create'] ) || ! in_array( $args['value'] ?? null, array( 0, 1 ), true ) ) {
			return new WP_Error( 'native_setting_invalid', 'Use an observed supported frontend option and integer 0 or 1. Arbitrary paths and option creation are not supported.', array( 'status' => 422 ) );
		}
		if ( ! self::capability()['available'] ) { return new WP_Error( 'native_adapter_unavailable', 'The active native implementation does not match the reviewed adapter, or this is multisite. No setting was changed.', array( 'status' => 422 ) ); }
		return true;
	}
	private static function bit( $value ) { return in_array( $value, array( 0, 1, '0', '1', false, true ), true ); }
	public static function build_plan( $args ) {
		$valid = self::validate( $args ); if ( is_wp_error( $valid ) ) { return $valid; }
		$prior = get_option( $args['option_name'], null );
		if ( ! self::bit( $prior ) ) { return new WP_Error( 'native_setting_unobserved', 'The stored boolean option is missing or has an unexpected value. Inspect the installed control.', array( 'status' => 422 ) ); }
		if ( (int) $prior === $args['value'] ) { return new WP_Error( 'no_change', 'The observed native option already has this value.', array( 'status' => 409 ) ); }
		return array( 'adapter' => self::ID, 'option_name' => $args['option_name'], 'path' => '', 'value' => $args['value'],
			'prior_value' => $prior, 'option_snapshot' => $prior, 'created_path' => false,
			'validation' => array( 'status' => 'reviewed_native_frontend_toggle', 'feature_effect' => 'native_cache_purge_invoked_on_apply; served_output_requires_verification' ) );
	}
	public static function apply_plan( $payload, $revert = false ) {
		$valid = self::validate( $payload ); if ( is_wp_error( $valid ) ) { return $valid; }
		if ( ! array_key_exists( 'option_snapshot', $payload ) || ! self::bit( $payload['option_snapshot'] ) ) { return new WP_Error( 'native_snapshot_invalid', 'A valid prior native option snapshot is required.' ); }
		$current = get_option( $payload['option_name'], null );
		$expected = $revert ? $payload['value'] : $payload['option_snapshot'];
		$target = $revert ? (int) $payload['option_snapshot'] : $payload['value'];
		if ( ! self::bit( $current ) || (int) $current !== (int) $expected ) { return new WP_Error( 'setting_conflict', 'Native option changed since the recorded basis. Inspect it before proposing a new change or rollback.' ); }
		try {
			\SiteGround_Optimizer\Options\Options::change_option( $payload['option_name'], $target );
		} catch ( Throwable $e ) {
			return new WP_Error( 'native_apply_incomplete', 'Native execution did not complete. The option or cache may have changed; read the actual state before retrying.' );
		}
		$now = get_option( $payload['option_name'], null );
		return self::bit( $now ) && (int) $now === $target ? true : new WP_Error( 'native_write_failed', 'Native option read-back does not match the requested value. Inspect current state.' );
	}
}
