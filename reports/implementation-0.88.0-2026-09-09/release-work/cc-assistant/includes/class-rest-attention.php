<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attention-flow REST routes (v0.55): archetype specs + the predicted
 * attention audit. Lives in its own class so the file can be added without
 * touching class-rest-api.php (where parallel work happens).
 */
class CC_Assistant_REST_Attention {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/attention/spec',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_spec' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'post_id'   => array( 'default' => 0 ),
					'archetype' => array( 'default' => '' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/attention/audit/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'archetype' => array( 'default' => '' ),
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

	/**
	 * GET /attention/spec — all archetypes + the universal composition
	 * checklist; with post_id, also resolves which archetype that post gets.
	 */
	public static function handle_spec( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-attention-spec.php';

		$out = array(
			'archetypes' => CC_Assistant_Attention_Spec::archetypes(),
			'checklist'  => CC_Assistant_Attention_Spec::checklist(),
		);

		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 ) {
			if ( ! get_post( $post_id ) ) {
				return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
			}
			$resolved            = CC_Assistant_Attention_Spec::for_post( $post_id, (string) $request->get_param( 'archetype' ) );
			$out['resolved_for'] = array(
				'post_id'   => $post_id,
				'archetype' => $resolved['archetype'],
				'source'    => $resolved['source'],
			);
		}

		return rest_ensure_response( $out );
	}

	/** GET /attention/audit/{id} — the predicted-heatmap audit. */
	public static function handle_audit( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-attention-audit.php';

		$result = CC_Assistant_Attention_Audit::audit(
			(int) $request['id'],
			(string) $request->get_param( 'archetype' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}
}
