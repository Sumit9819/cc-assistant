<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AEO / commodity REST routes (v0.60). One endpoint: content-side signals
 * for the bridge's commodity_audit — per URL, the intent classification and
 * the non-commodity (info-gain) elements present in the stored content.
 * Lives in its own class so the file can be added without touching
 * class-rest-api.php (where parallel work happens).
 */
class CC_Assistant_REST_AEO {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/commodity/signals',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_signals' ),
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
	 * POST /commodity/signals {urls: [...]} — for each URL (max 50): the
	 * resolved post, its intent family (action = service/conversion,
	 * research = blog/informational), and the info-gain signals in its
	 * content. For Elementor posts the body lives in _elementor_data, so the
	 * scan covers post_content AND the raw Elementor JSON (heading/text
	 * settings included — good enough for signal detection).
	 */
	public static function handle_signals( $request ) {
		$params = $request->get_json_params();
		$urls   = isset( $params['urls'] ) && is_array( $params['urls'] ) ? array_slice( $params['urls'], 0, 50 ) : array();
		if ( empty( $urls ) ) {
			return new WP_Error( 'invalid_payload', 'urls (array of page URLs, max 50) is required.', array( 'status' => 400 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-page-intent.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-url-resolver.php';

		$out = array();
		foreach ( $urls as $url ) {
			$url = (string) $url;
			// Not url_to_postid(): a consolidated URL that 301s to its
			// replacement is not "unresolved", it belongs to the destination.
			// Resolving it here is what folds its impressions into the right
			// post instead of dropping them.
			$res     = CC_Assistant_URL_Resolver::resolve( $url );
			$post_id = (int) $res['post_id'];
			$row     = array(
				'url'     => $url,
				'post_id' => $post_id,
				'via'     => $res['via'],
			);
			if ( 'redirect' === $res['via'] ) {
				$row['redirect_code'] = (int) $res['code'];
				$row['resolved_url']  = (string) $res['final_url'];
			}
			if ( $post_id <= 0 ) {
				$row['found']  = false;
				$row['reason'] = CC_Assistant_URL_Resolver::explain( $res );
				$out[]         = $row;
				continue;
			}
			$row['found'] = true;

			$intent               = CC_Assistant_Page_Intent::classify_page( $post_id );
			$row['intent_family'] = $intent['family'];
			$row['page_type']     = $intent['page_type'];

			$content = (string) get_post_field( 'post_content', $post_id );
			$edata   = (string) get_post_meta( $post_id, '_elementor_data', true );
			$scan    = $content . ' ' . $edata;

			$ig                       = CC_Assistant_REST_API::info_gain_signals( $scan );
			$row['info_gain_signals'] = $ig['signals'];
			$out[]                    = $row;
		}

		return rest_ensure_response( array( 'rows' => $out, 'count' => count( $out ) ) );
	}
}
