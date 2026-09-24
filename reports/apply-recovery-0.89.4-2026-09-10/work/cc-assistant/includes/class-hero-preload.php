<?php
/**
 * Hero background preload (v0.48.0).
 *
 * Elementor heroes set their image as a CSS background, so the browser only
 * discovers it after CSS parses — on service/location pages that image IS the
 * LCP element, costing several hundred ms. This emits
 * <link rel="preload" as="image" fetchpriority="high"> in wp_head for the
 * first root container's background image.
 *
 * Performance contract (see feedback_plugin_performance): the front-end path
 * does ONE get_post_meta read (meta cache is primed with the post, so zero
 * extra queries). The _elementor_data JSON is parsed at most once per post
 * EVER — the result (or 'none') is cached in postmeta and invalidated on
 * save_post / Elementor save / cc-assistant apply.
 *
 * Toggle: option cc_assistant_hero_preload_enabled (default true).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Hero_Preload {

	const META_KEY = '_cc_hero_preload';

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'emit' ), 2 );
		// Invalidate the cached URL whenever the page can change.
		add_action( 'save_post', array( __CLASS__, 'invalidate' ) );
		add_action( 'elementor/editor/after_save', array( __CLASS__, 'invalidate' ) );
	}

	public static function invalidate( $post_id ) {
		delete_post_meta( (int) $post_id, self::META_KEY );
	}

	public static function emit() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		if ( ! get_option( 'cc_assistant_hero_preload_enabled', true ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$info = self::resolve( $post_id );
		if ( ! is_array( $info ) || empty( $info['url'] ) ) {
			return;
		}
		$media = ! empty( $info['desktop_only'] ) ? ' media="(min-width: 768px)"' : '';
		printf(
			'<link rel="preload" as="image" href="%s" fetchpriority="high"%s id="cc-hero-preload">' . "\n",
			esc_url( $info['url'] ),
			$media // phpcs:ignore WordPress.Security.EscapeOutput -- static attribute string.
		);
	}

	/**
	 * Returns array{url:string, desktop_only:bool} or null. Cached in postmeta;
	 * the sentinel 'none' means "parsed before, no hero background".
	 */
	private static function resolve( $post_id ) {
		$cached = get_post_meta( $post_id, self::META_KEY, true );
		if ( 'none' === $cached ) {
			return null;
		}
		if ( is_string( $cached ) && '' !== $cached ) {
			$decoded = json_decode( $cached, true );
			if ( is_array( $decoded ) && ! empty( $decoded['url'] ) ) {
				return $decoded;
			}
		}

		$info = self::parse_hero( $post_id );
		if ( null === $info ) {
			update_post_meta( $post_id, self::META_KEY, 'none' );
			return null;
		}
		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $info ) );
		return $info;
	}

	private static function parse_hero( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) || empty( $tree[0] ) || ! is_array( $tree[0] ) ) {
			return null;
		}
		$settings = isset( $tree[0]['settings'] ) && is_array( $tree[0]['settings'] ) ? $tree[0]['settings'] : array();
		if ( ! isset( $settings['background_background'] ) || 'classic' !== $settings['background_background'] ) {
			return null;
		}
		$url = isset( $settings['background_image']['url'] ) ? trim( (string) $settings['background_image']['url'] ) : '';
		// Accept absolute http(s) and protocol-relative (//host/img) URLs; skip
		// anything else (empty, data:, relative).
		if ( '' === $url || ( 0 !== strpos( $url, 'http' ) && 0 !== strpos( $url, '//' ) ) ) {
			return null;
		}
		// Site convention: heroes explicitly blank background_image_mobile and
		// show a solid color on phones — do not preload the image there.
		$desktop_only = isset( $settings['background_image_mobile'] )
			&& is_array( $settings['background_image_mobile'] )
			&& ( ! isset( $settings['background_image_mobile']['url'] ) || '' === trim( (string) $settings['background_image_mobile']['url'] ) );

		return array(
			'url'          => $url,
			'desktop_only' => (bool) $desktop_only,
		);
	}
}
