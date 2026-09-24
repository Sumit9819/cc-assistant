<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly advisor + brief generator REST routes. Lives in its own class
 * (parking-lot pattern) so it can be added without touching
 * class-rest-api.php.
 */
class CC_Assistant_REST_Advisor {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/advisor/priorities',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_priorities' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'force' => array( 'default' => false ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/advisor/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_dismiss' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'item_id' => array( 'required' => true ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/advisor/clear-dismissed',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_clear_dismissed' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/advisor/brief',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_brief' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'keyword'     => array( 'required' => true ),
					'intent_hint' => array( 'default' => '' ),
					'days'        => array( 'default' => 28 ),
				),
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

	private static function wrap( $data ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		return array(
			'site' => array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url(),
				'fingerprint' => CC_Assistant_Site_Identity::fingerprint(),
			),
			'data' => $data,
		);
	}

	public static function handle_priorities( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
		$force = filter_var( $req->get_param( 'force' ), FILTER_VALIDATE_BOOLEAN );
		return self::wrap( CC_Assistant_Weekly_Advisor::priorities( array( 'force' => $force ) ) );
	}

	public static function handle_dismiss( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
		$ok = CC_Assistant_Weekly_Advisor::dismiss( (string) $req->get_param( 'item_id' ) );
		return self::wrap( array( 'dismissed' => (bool) $ok ) );
	}

	public static function handle_clear_dismissed() {
		require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
		CC_Assistant_Weekly_Advisor::clear_dismissed();
		return self::wrap( array( 'cleared' => true ) );
	}

	public static function handle_brief( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
		$result = CC_Assistant_SEO_Tools::brief_for_keyword( array(
			'keyword'     => (string) $req->get_param( 'keyword' ),
			'intent_hint' => (string) $req->get_param( 'intent_hint' ),
			'days'        => (int) $req->get_param( 'days' ),
		) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return self::wrap( $result );
	}
}
