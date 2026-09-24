<?php
/**
 * Sid's Ponds - Performance round 1 (2026-09-17)
 *
 * Paste everything BELOW the <?php line into a new Code Snippets snippet,
 * "Run snippet everywhere". Measured against the live site before writing;
 * see D:\cc-assistant\tools\sp-performance-snippets\NOTES.md.
 */

if ( is_admin() ) {
	return;
}

/*
 * 1. Divi prints its own Google Fonts CSS (Lato 100-900 + Archivo Black, plus
 *    legacy woff/ttf/svg faces). The "Self Hosting Fonts for Speed" snippet
 *    already serves the same Lato and Archivo Black files from uploads, so every
 *    page downloaded both copies: 8-9 extra font requests, ~220-245 KB. On
 *    category pages Divi prints the block in the body, after our faces, so
 *    Google's copy even won the cascade. Removing the block rendered pixel-
 *    identical text on category, product and cart pages, phone and desktop.
 */
add_action( 'template_redirect', function () {
	if ( wp_doing_ajax() || is_feed() || isset( $_GET['et_fb'] ) ) {
		return;
	}
	ob_start( function ( $html ) {
		$html = preg_replace( '#<style id=["\']?et-builder-googlefonts(?:-cached)?-inline["\']?>.*?</style>#s', '', $html );
		// See 4 below: the global styles block is printed before a dequeue can reach it.
		return preg_replace( '#<style id=["\']?global-styles-inline-css["\']?>.*?</style>#s', '', $html, 1 );
	} );
}, 2 );

/*
 * 2. Head clutter nobody uses on a shop front end.
 */
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
add_filter( 'rank_math/frontend/remove_credit_notice', '__return_true' );

/*
 * 3. jQuery Migrate. Logged no deprecation warnings on home, category, product
 *    or cart, and removing it added no JS errors on any of them.
 */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( isset( $scripts->registered['jquery'] ) ) {
		$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
	}
} );

/*
 * 4. Block theme global styles. Divi does not use them; removing the inline
 *    block changed nothing on screen. A wp_dequeue_style() at priority 100 did
 *    NOT remove it on the live site (verified 2026-09-17), so it is stripped in
 *    the output buffer in 1 above.
 */

/*
 * 5. Link prefetch. WordPress's default is "conservative" (fetch on mouse
 *    down). "moderate" starts on hover, so the next page is usually ready by
 *    the click. Prefetch only downloads the HTML: no scripts run, so tracking
 *    and analytics plugins do not record a visit. Cart, checkout and account
 *    pages are excluded; add-to-cart and logout links carry a query string,
 *    which WordPress already excludes.
 */
add_filter( 'wp_speculation_rules_configuration', function ( $config ) {
	return array(
		'mode'      => 'prefetch',
		'eagerness' => 'moderate',
	);
} );
add_filter( 'wp_speculation_rules_href_exclude_paths', function ( $paths ) {
	return array_merge( $paths, array( '/cart/*', '/checkout/*', '/my-account/*' ) );
} );
