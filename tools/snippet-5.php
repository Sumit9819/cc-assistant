/**
 * Irving Wellness Clinic: performance and head cleanup.
 *
 * Moved out of hello-elementor/functions.php (2026-09-17). That file belongs to the
 * parent theme, so every Hello Elementor update overwrites it. A snippet survives.
 *
 * HOW TO USE
 * - Paste everything BELOW the "<?php" line into a PHP snippet (Code Snippets or
 *   WPCode), set to run everywhere. Those plugins add the opening tag themselves.
 * - Then restore the stock functions.php (reinstall/update Hello Elementor).
 * - Do NOT keep this code in functions.php as well, and do NOT paste the whole old
 *   functions.php into a snippet: it declares hello_maybe_update_theme_version_in_db(),
 *   which the stock theme also declares, and that is a fatal "Cannot redeclare" error.
 *
 * WHAT CHANGED FROM THE OLD FILE (verified against the live site 2026-09-17)
 * - FIXED  Hero image preload now runs on the homepage only. It ran on every page,
 *          so every other page downloaded two hero images it never shows.
 * - FIXED  Speculation rules pointed at the old staging domain jayard33.sg-host.com,
 *          so they never matched a single link on the live site. Now relative, and
 *          "prefetch" instead of "prerender" (prerender runs page JavaScript, so the
 *          TikTok, Snapchat, Meta and Clarity tags could count visits that never happen).
 * - ADDED  Stops Hello Elementor printing its own meta description. The old file had
 *          deleted that function; the stock theme brings it back, and it would
 *          duplicate Rank Math's description on any page that has an excerpt.
 * - ADDED  Removes the shortlink HTTP header too (the old code removed only the tag).
 * - ADDED  Removes oEmbed discovery links at priority 4 as well as 10. Newer WordPress
 *          hooks them at 4, so the old priority-10-only line stops working after a core update.
 * - DROPPED Heartbeat 60s filter: it overrode Speed Optimizer's own Heartbeat control
 *          (post editor was set to 120s there). Manage Heartbeat in Speed Optimizer.
 * - DROPPED eicons font-display rule: icons render as inline SVG (Elementor
 *          "Inline Font Icons"), the eicons font is never loaded, and an @font-face
 *          without a src does not change the real face anyway.
 * - DROPPED script defer filter: it targeted wp-embed and hello-elementor-child,
 *          neither of which is loaded on this site.
 * - DROPPED sgo_css_combine_exclude: it excluded Speed Optimizer's combined file from
 *          being combined, which does nothing.
 * - KEPT AS-IS everything else, including the two Rank Math schema filters. Page and
 *          homepage schema now comes from the managed schema blocks; removing these
 *          filters would bring Rank Math's copy back and duplicate it.
 */

/* ---------------------------------------------------------------------------
 * 1. Scripts
 * ------------------------------------------------------------------------- */

// Remove jQuery Migrate on the front end.
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
		$scripts->registered['jquery']->deps = array_diff(
			$scripts->registered['jquery']->deps,
			array( 'jquery-migrate' )
		);
	}
} );

/* ---------------------------------------------------------------------------
 * 2. Cleanup and bloat removal
 * ------------------------------------------------------------------------- */

// Emojis (Speed Optimizer's "Disable Emojis" also does this; harmless to keep both).
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

// Head cleanup.
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );  // newer WordPress hooks it here
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 ); // older WordPress hooks it here
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

// Hello Elementor's own meta description would duplicate Rank Math's.
add_filter( 'hello_elementor_description_meta_tag', '__return_false' );

// Disable XML-RPC methods.
add_filter( 'xmlrpc_enabled', '__return_false' );

// Gutenberg CSS (Elementor handles styling).
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
}, 100 );

// Dashicons only for logged-in users.
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_user_logged_in() ) {
		wp_deregister_style( 'dashicons' );
	}
}, 100 );

// No self-pingbacks.
add_action( 'pre_ping', function ( &$links ) {
	if ( ! is_array( $links ) ) {
		return;
	}
	$home = get_option( 'home' );
	foreach ( $links as $l => $link ) {
		if ( is_string( $link ) && 0 === strpos( $link, $home ) ) {
			unset( $links[ $l ] );
		}
	}
} );

// Keep at most 5 revisions per post.
add_filter( 'wp_revisions_to_keep', function ( $num, $post ) {
	return 5;
}, 10, 2 );

/* ---------------------------------------------------------------------------
 * 3. Homepage hero preload (HOMEPAGE ONLY)
 *
 * These two files are the homepage's largest visible image (desktop and mobile).
 * If the homepage hero image is ever replaced in Elementor, update both URLs here,
 * or the browser will preload a file the page no longer uses.
 * ------------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<link rel="preload" as="image" fetchpriority="high" type="image/webp" href="https://irvingwellnessclinic.com/wp-content/uploads/2026/03/Untitled-design-18-1024x480-9.webp" media="(min-width: 768px)">' . "\n";
	echo '<link rel="preload" as="image" fetchpriority="high" type="image/webp" href="https://irvingwellnessclinic.com/wp-content/uploads/2026/04/Untitled-design-6.webp" media="(max-width: 767px)">' . "\n";
}, 1 );

/* ---------------------------------------------------------------------------
 * 4. Plugin-specific
 * ------------------------------------------------------------------------- */

// Rank Math: remove the credit comment.
add_filter( 'rank_math/frontend/remove_credit_notice', '__return_true' );

// Elementor: never print Google Fonts (Elementor's own setting is also off).
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );

// Speed Optimizer: keep core scripts out of JS combine/async if those are ever turned on.
add_filter( 'sgo_javascript_combine_exclude', function ( $list ) {
	return array_merge( (array) $list, array( 'jquery-core', 'elementor-frontend', 'elementor-pro-frontend' ) );
} );
add_filter( 'sgo_js_async_exclude', function ( $list ) {
	return array_merge( (array) $list, array( 'jquery-core', 'elementor-frontend', 'elementor-pro-frontend' ) );
} );

// Rank Math schema off on homepage, pages and archives; kept on blog posts.
// Page schema is supplied by the managed schema blocks instead.
add_filter( 'rank_math/snippet/rich_snippet_data', function ( $data, $json_ld ) {
	if ( is_home() || is_front_page() || is_page() || is_archive() ) {
		return array();
	}
	return $data;
}, 10, 2 );
add_filter( 'rank_math/json_ld', function ( $json_ld ) {
	if ( is_front_page() || is_page() ) {
		return array();
	}
	return $json_ld;
}, 9999 );

/* ---------------------------------------------------------------------------
 * 5. Faster next page (Speculation Rules, prefetch)
 *
 * Chrome downloads a linked page's HTML when the visitor hovers a link, so the
 * click feels instant. "prefetch" only downloads; it does not run the page's
 * scripts, so no ad or analytics tag fires for a page the visitor never opens.
 * ------------------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	?>
	<script type="speculationrules">
	{
		"prefetch": [
			{
				"where": {
					"and": [
						{ "href_matches": "/*" },
						{ "not": { "href_matches": "/wp-admin/*" } },
						{ "not": { "href_matches": "/wp-login.php*" } },
						{ "not": { "href_matches": "/*\\?*" } }
					]
				},
				"eagerness": "moderate"
			}
		]
	}
	</script>
	<?php
}, 1 );

/* ---------------------------------------------------------------------------
 * 6. LeadConnector chat widget: delayed load (unchanged)
 *
 * First page: loads on first interaction or after 5 seconds.
 * Later pages in the same visit: loads after 0.5 seconds.
 * ------------------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	?>
	<script>
	(function () {
		var loaded = false;
		function loadLeadConnector() {
			if (loaded) return;
			loaded = true;
			try { sessionStorage.setItem('lc_loaded', '1'); } catch (e) {}
			var s = document.createElement('script');
			s.src = 'https://widgets.leadconnectorhq.com/loader.js';
			s.setAttribute('data-resources-url', 'https://widgets.leadconnectorhq.com/chat-widget/loader.js');
			s.setAttribute('data-widget-id', '69a84edae62eed25308280b5');
			document.body.appendChild(s);
		}
		var alreadySeen = false;
		try { alreadySeen = sessionStorage.getItem('lc_loaded') === '1'; } catch (e) {}
		if (alreadySeen) {
			setTimeout(loadLeadConnector, 500);
		} else {
			['mouseover', 'keydown', 'touchstart', 'scroll'].forEach(function (e) {
				document.addEventListener(e, loadLeadConnector, { once: true, passive: true });
			});
			setTimeout(loadLeadConnector, 5000);
		}
	})();
	</script>
	<?php
}, 99 );