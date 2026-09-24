<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Automation may propose; approving requires an interactive administrator. */
class CC_Assistant_Access {
	const ROLE = 'cc_assistant_operator';
	const CAPABILITY = 'cc_assistant_use';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'ensure_role' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'scope_operator' ), 5, 3 );
	}

	public static function ensure_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, 'CC Assistant Operator', array( 'read' => true, self::CAPABILITY => true ) );
		}
	}

	public static function can_use() {
		return current_user_can( 'manage_options' ) || current_user_can( self::CAPABILITY );
	}

	public static function can_review( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'review_forbidden', 'Only a human administrator can review changes.', array( 'status' => 403 ) );
		}
		// Application Password requests do not carry the browser review session.
		$app = function_exists( 'rest_get_authenticated_app_password' ) ? rest_get_authenticated_app_password() : null;
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $app || ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'human_review_required', 'Approve or reject in the WordPress Pending Changes inbox using your administrator browser session.', array( 'status' => 403 ) );
		}
		return true;
	}

	public static function scope_operator( $result, $server, $request ) {
		if ( current_user_can( self::CAPABILITY ) && ! current_user_can( 'manage_options' )
			&& 0 !== strpos( $request->get_route(), '/cc-assistant/v1/' ) ) {
			return new WP_Error( 'operator_scope', 'The CC Assistant operator can only access CC Assistant REST routes.', array( 'status' => 403 ) );
		}
		return $result;
	}
}
