<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GSC raw-export REST routes for the desktop warehouse (v0.54).
 *
 * The site is a pass-through proxy: each request makes at most ONE Google
 * searchAnalytics API call and writes NOTHING to the WP database. The
 * full-fidelity (date, page, query) archive lives in SQLite on the
 * operator's machine (bin/warehouse.php), so the 250MB wp_cc_gsc_queries
 * cap and impression-floor pruning no longer limit what we can keep.
 *
 * Lives in its own class so the file can be added without touching
 * class-rest-api.php (where parallel work happens).
 */
class CC_Assistant_REST_Warehouse {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc-export',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_export' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'date'      => array( 'required' => true ),
					'start_row' => array( 'default' => 0 ),
					'row_limit' => array( 'default' => 25000 ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc-export/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_status' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to use CC Assistant.', array( 'status' => 403 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	/**
	 * GET /gsc-export?date=YYYY-MM-DD&start_row=0&row_limit=25000
	 *
	 * One page of raw (page, query) rows for one date. The bridge loops
	 * start_row until next_start_row is null, then moves to the next date.
	 */
	public static function handle_export( $request ) {
		$date = trim( (string) $request->get_param( 'date' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'invalid_date', 'date must be YYYY-MM-DD.', array( 'status' => 400 ) );
		}
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
		if ( ! checkdate( $m, $d, $y ) ) {
			return new WP_Error( 'invalid_date', 'date is not a real calendar date.', array( 'status' => 400 ) );
		}

		$start_row = max( 0, (int) $request->get_param( 'start_row' ) );
		$row_limit = max( 1, min( 25000, (int) $request->get_param( 'row_limit' ) ) );

		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		$page = CC_Assistant_GSC::raw_export_page( $date, $start_row, $row_limit );
		if ( is_wp_error( $page ) ) {
			$page->add_data( array( 'status' => 400 ) );
			return $page;
		}

		return rest_ensure_response(
			array(
				'date'           => $date,
				'start_row'      => $start_row,
				'row_limit'      => $row_limit,
				'property'       => $page['property'],
				'row_count'      => $page['row_count'],
				'columns'        => array( 'page', 'query', 'clicks', 'impressions', 'position' ),
				'rows'           => $page['rows'],
				'next_start_row' => $page['complete'] ? null : $start_row + $page['api_row_count'],
			)
		);
	}

	/**
	 * GET /gsc-export/status — cheap preflight for the bridge: is GSC
	 * connected, which property, and the freshest date worth requesting
	 * (GSC finalizes data ~2 days behind).
	 */
	public static function handle_status( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		return rest_ensure_response(
			array(
				'connected'       => CC_Assistant_GSC::is_connected(),
				'property'        => CC_Assistant_GSC::get_property(),
				'freshest_date'   => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
				'plugin_version'  => defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : '',
				'export_capable'  => true,
			)
		);
	}
}
