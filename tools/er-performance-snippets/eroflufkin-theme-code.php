<?php
/**
 * ER of Lufkin: theme customisations safety net (built 2026-09-17).
 *
 * WHY: Lufkin's Hello Elementor functions.php (3.4.5) has two custom additions: it
 * removes the RSS feed links from the page head and 301-redirects any /feed/ URL
 * back to its page. A theme update deletes them. This snippet holds an exact copy
 * and stays SILENT while the theme still contains them (it checks after the theme
 * has loaded), so it is safe to activate now and the theme can then be updated
 * to the current Hello Elementor without losing either behaviour.
 *
 * Paste below the <?php line into Code Snippets, Run everywhere, Save and Activate.
 */
add_action( 'after_setup_theme', function () {
	// The theme's functions.php still has this code: do nothing.
	if ( function_exists( 'disable_feed_links' ) ) {
		return;
	}

	// Remove feed URLs from the header
	function disable_feed_links() {
	remove_action('wp_head', 'feed_links', 2);
	remove_action('wp_head', 'feed_links_extra', 3);
	}
	add_action('init', 'disable_feed_links');
	// Redirect feed requests to the original page
	function redirect_feed_requests_to_original_page($query) {
	if ($query->is_feed) {
	global $wp;
	$current_url = home_url(add_query_arg(array(), $wp->request));
	$original_url = preg_replace('/\/feed(\/.*|$)/', '', $current_url);
	wp_redirect($original_url, 301);
	exit;
	}
	}
	add_action('parse_query', 'redirect_feed_requests_to_original_page');
}, 1 );
