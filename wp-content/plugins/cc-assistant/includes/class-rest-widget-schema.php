<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget-schema REST routes (v0.56). Lives in its own class so the file can
 * be added without touching class-rest-api.php (where parallel work happens).
 */
class CC_Assistant_REST_Widget_Schema {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/widget-schema',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/widget-schema/(?P<type>[a-zA-Z0-9_\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_schema' ),
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

	public static function handle_list( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-widget-schema.php';
		$post_id = (int) $request->get_param( 'post_id' );
		$widget_id = (string) $request->get_param( 'widget_id' );
		if ( $post_id > 0 || '' !== $widget_id ) {
			if ( $post_id < 1 || '' === $widget_id ) { return new WP_Error( 'element_target_incomplete', 'Supply both post_id and widget_id to inspect a saved element.', array( 'status' => 400 ) ); }
			$element = CC_Assistant_Widget_Schema::effective_for_post_element( $post_id, $widget_id );
			if ( is_wp_error( $element ) ) { return $element; }
			$request['type'] = $element['element_type'];
			return self::handle_schema( $request );
		}
		$result = CC_Assistant_Widget_Schema::list_types();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public static function handle_schema( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-widget-schema.php';
		$result = CC_Assistant_Widget_Schema::schema(
			(string) $request['type'],
			(string) $request->get_param( 'section' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// v0.80: stored vs effective for one saved element on a post.
		$post_id   = (int) $request->get_param( 'post_id' );
		$widget_id = (string) $request->get_param( 'widget_id' );
		if ( ( $post_id > 0 ) !== ( '' !== $widget_id ) ) { return new WP_Error( 'element_target_incomplete', 'Supply both post_id and widget_id to inspect a saved element.', array( 'status' => 400 ) ); }
		if ( $post_id > 0 && '' !== $widget_id ) {
			$eff = CC_Assistant_Widget_Schema::effective_for_post_element( $post_id, $widget_id );
			if ( is_wp_error( $eff ) ) {
				return $eff;
			}
			if ( $eff['element_type'] !== $result['widget_type'] ) {
				return new WP_Error( 'type_mismatch', 'Element ' . $widget_id . ' is a "' . $eff['element_type'] . '", not "' . $result['widget_type'] . '". Call widget_schema with widget_type="' . $eff['element_type'] . '".', array( 'status' => 422 ) );
			}
			$result['element'] = $eff;
		}
		return rest_ensure_response( $result );
	}
}
