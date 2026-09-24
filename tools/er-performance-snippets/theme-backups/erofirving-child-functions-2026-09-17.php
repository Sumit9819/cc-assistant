<?php
/**
 * Hello Elementor Child — functions.php
 * --------------------------------------------------------------
 * This file is SAFE from parent theme updates. Add your custom code here.
 *
 * IMPORTANT — avoid double-handling:
 *   SG Speed Optimizer already handles emoji removal, Heartbeat control,
 *   defer JS, and CSS/JS minify. If those are ENABLED in the plugin, do
 *   NOT also enable them here. Running the same optimization in two
 *   places causes conflicts. Pick ONE place per optimization.
 * --------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the child theme stylesheet after the parent theme's styles.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'hello-elementor-child',
		get_stylesheet_uri(),
		[ 'hello-elementor-theme-style' ],
		wp_get_theme()->get( 'Version' )
	);
}, 20 );


/* =====================================================================
 * PERFORMANCE SNIPPETS
 * ===================================================================== */

/**
 * 1. Remove WordPress emoji scripts/styles.
 *    DELETE this block if SG Optimizer is already removing emojis.
 */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	add_filter( 'tiny_mce_plugins', function ( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
	} );

	add_filter( 'emoji_svg_url', '__return_false' );
} );

/**
 * 2. Disable WordPress oEmbed / wp-embed.js.
 *    Safe on nearly all sites that don't auto-embed other WordPress posts.
 */
add_action( 'init', function () {
	remove_action( 'rest_api_init', 'wp_oembed_register_route' );
	remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	add_filter( 'embed_oembed_discover', '__return_false' );
}, 9999 );

add_action( 'wp_footer', function () {
	wp_dequeue_script( 'wp-embed' );
} );

/**
 * 3. Remove jQuery Migrate from the front end.
 *    Safe on modern themes/plugins. After enabling, click through the site
 *    (especially Elementor forms/sliders) to confirm nothing breaks.
 */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
		$jquery = $scripts->registered['jquery'];
		if ( ! empty( $jquery->deps ) ) {
			$jquery->deps = array_diff( $jquery->deps, [ 'jquery-migrate' ] );
		}
	}
} );

/**
 * 4. Dequeue Dashicons CSS for visitors who aren't logged in.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
} );

/**
 * 5. Remove Gutenberg block CSS on the front end.
 *
 *    ⚠️ ONLY KEEP THIS IF the whole site is built in Elementor and NO page
 *    uses Gutenberg blocks. If any page renders a block, DELETE this block.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' ); // theme.json inline CSS variables
}, 100 );
