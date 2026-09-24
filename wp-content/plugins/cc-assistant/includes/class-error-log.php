<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight error log surfaced inside the plugin admin UI.
 *
 * WordPress already writes to wp-content/debug.log when WP_DEBUG_LOG is
 * on, but a normal site owner never sees that. This class captures the
 * recent ~25 plugin-internal failures (cron explosions, REST 5xx,
 * MCP-side anomalies) into a small option blob so the Settings page can
 * surface them.
 *
 * Plus a wrap() helper that callers can use to invoke risky code
 * defensively — catches Throwable, records the failure, and re-throws
 * (or returns null, depending on $rethrow).
 */
class CC_Assistant_Error_Log {

	const OPT_KEY  = 'cc_assistant_error_log';
	const MAX_ROWS = 25;

	/**
	 * Record a failure. $context is a short identifier (e.g. 'site_audit',
	 * 'warm_rendered:1234'). $message is the human reason; $details
	 * optional.
	 */
	public static function record( $context, $message, $details = null ) {
		$rows = (array) get_option( self::OPT_KEY, array() );
		array_unshift( $rows, array(
			'at'       => time(),
			'context'  => (string) $context,
			'message'  => (string) $message,
			'details'  => $details,
		) );
		$rows = array_slice( $rows, 0, self::MAX_ROWS );
		update_option( self::OPT_KEY, $rows, false );

		// Also surface as an admin notice if it's a high-signal failure
		// (cron handler crashed). Idempotent: keyed by context, so repeated
		// failures of the same handler update the existing notice instead
		// of stacking.
		require_once CC_ASSISTANT_DIR . 'includes/class-admin-notices.php';
		CC_Assistant_Admin_Notices::add(
			'error_' . md5( $context ),
			'error',
			sprintf(
				/* translators: 1: context 2: message */
				__( '"%1$s" failed: %2$s. Open Settings → Health for details.', 'cc-assistant' ),
				esc_html( $context ),
				esc_html( $message )
			),
			array(
				'url'   => admin_url( 'admin.php?page=cc-assistant-settings&tab=health' ),
				'label' => __( 'Open Health', 'cc-assistant' ),
			)
		);
	}

	public static function recent( $limit = 25 ) {
		$rows = (array) get_option( self::OPT_KEY, array() );
		return array_slice( $rows, 0, max( 1, min( self::MAX_ROWS, (int) $limit ) ) );
	}

	public static function clear() {
		delete_option( self::OPT_KEY );
	}

	/**
	 * Defensive wrapper for risky cron handlers. Usage:
	 *   CC_Assistant_Error_Log::wrap('site_audit', function () {
	 *       CC_Assistant_Site_Audit::run();
	 *   });
	 *
	 * Catches every Throwable, records it, and returns null. Cron handlers
	 * usually have nothing meaningful to return so swallowing failures is
	 * the right choice — re-throwing would crash the entire wp-cron run
	 * and starve other plugins.
	 */
	public static function wrap( $context, callable $fn ) {
		try {
			return $fn();
		} catch ( \Throwable $e ) {
			self::record(
				$context,
				$e->getMessage(),
				array(
					'file' => str_replace( ABSPATH, '', $e->getFile() ),
					'line' => $e->getLine(),
				)
			);
			return null;
		}
	}
}
