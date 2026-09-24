<?php
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
 *          so they never matched a single link on the live site. Now relative
 *          "prerender" (instant pages), with a guard (section 6) so tracking tags and
 *          the chat widget never run on a page built in the background.
 * - ADDED  View Transitions (section 7): no blank flash between pages, header stays put.
 * - ADDED  Tags (section 6) and chat (section 8) load a few seconds after the first
 *          interaction instead of on the first mouse move.
 * - ADDED  Stops Hello Elementor printing its own meta description. The old file had
 *          deleted that function; the stock theme brings it back, and it would
 *          duplicate Rank Math's description on any page that has an excerpt.
 * - ADDED  Turns off the CC Assistant plugin's own homepage hero preload, which had no
 *          screen-size condition and made phones download the desktop hero image.
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

// The CC Assistant plugin also preloads the homepage hero, but with no screen-size
// condition, so phones download the desktop image as well as their own. The two
// preloads above already cover both sizes correctly, so remove the plugin's one on
// the homepage only. Other pages keep the plugin's preload.
add_action( 'template_redirect', function () {
	if ( is_front_page() && class_exists( 'CC_Assistant_Hero_Preload' ) ) {
		remove_action( 'wp_head', array( 'CC_Assistant_Hero_Preload', 'emit' ), 2 );
	}
} );

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
 * 5. Instant page navigation (Speculation Rules, prerender)
 *
 * When a visitor hovers a link (or presses it on a phone), Chrome and Edge build
 * that page invisibly in the background, so the click shows it immediately.
 * Safari and Firefox ignore this and navigate normally.
 *
 * This replaces WordPress's own built-in rules (a slower "prefetch" on click), so
 * the page does not carry two sets of rules.
 *
 * Ad and analytics tags are protected: see section 6. A page built in the
 * background does not load them until the visitor actually opens it.
 * ------------------------------------------------------------------------- */
add_filter( 'wp_speculation_rules_configuration', '__return_null' );

// Built with wp_json_encode (like WordPress's own rules) and written without any
// backslashes. A hand-typed version with "\?" lost one backslash on the live site,
// which made the JSON invalid, and Chrome silently ignores invalid rules.
add_action( 'wp_footer', function () {
	$rules = array(
		'prerender' => array(
			array(
				'where'     => array(
					'and' => array(
						array( 'href_matches' => '/*' ),
						array( 'not' => array( 'href_matches' => '/wp-admin/*' ) ),
						array( 'not' => array( 'href_matches' => '/wp-login.php*' ) ),
						array( 'not' => array( 'href_matches' => '/wp-content/*' ) ),
						array( 'not' => array( 'selector_matches' => "a[href*='?'], a[rel~='nofollow'], .no-prerender, .no-prerender a" ) ),
					),
				),
				'eagerness' => 'moderate',
			),
		),
	);
	echo '<script type="speculationrules">' . wp_json_encode( $rules, JSON_UNESCAPED_SLASHES ) . '</script>' . "
";
}, 1 );

/* ---------------------------------------------------------------------------
 * 6. Tracking tags: load a moment AFTER the visitor starts, not ON their first move
 *
 * Flying Scripts (1.2.4) loads GTM, Meta, TikTok, Snapchat, Clarity and Google Ads
 * the instant the mouse first moves. On a first visit that was 66 requests /
 * 1.18 MB landing exactly when the person starts reading. This takes over its
 * trigger (its loader prints at wp_print_footer_scripts priority 10; this runs
 * at 20, using its global names loadScripts, loadScriptsTimer,
 * triggerScriptLoader, userInteractionEvents):
 *   - first interaction  -> wait 2 s, then load when the browser is idle (at most 1 s more)
 *   - no interaction     -> load after 5 s, same as before
 *   - page being built in the background by instant navigation -> nothing loads
 *     until the visitor actually opens it (otherwise a hover would count a visit)
 * Trade-off: a visit shorter than about 3 s after the first interaction may not
 * be counted by the ad and analytics tags.
 * ------------------------------------------------------------------------- */
add_action( 'wp_print_footer_scripts', function () {
	?>
	<script id="iwc-tag-scheduler">
	(function () {
		if (typeof loadScripts !== 'function' || typeof triggerScriptLoader !== 'function' || typeof userInteractionEvents === 'undefined') return;
		userInteractionEvents.forEach(function (ev) { window.removeEventListener(ev, triggerScriptLoader, { passive: true }); });
		try { clearTimeout(loadScriptsTimer); } catch (e) {}

		var done = false, fallback = null;
		function go() { if (done) return; done = true; clearTimeout(fallback); loadScripts(); }
		function whenIdle() {
			if ('requestIdleCallback' in window) { requestIdleCallback(go, { timeout: 1000 }); } else { setTimeout(go, 300); }
		}
		function onFirstInteraction() {
			userInteractionEvents.forEach(function (ev) { window.removeEventListener(ev, onFirstInteraction, { passive: true }); });
			setTimeout(whenIdle, 2000);
		}
		function arm() {
			userInteractionEvents.forEach(function (ev) { window.addEventListener(ev, onFirstInteraction, { passive: true }); });
			fallback = setTimeout(go, 5000);
		}
		if (document.prerendering) {
			document.addEventListener('prerenderingchange', arm, { once: true });
		} else {
			arm();
		}
	})();
	</script>
	<?php
}, 20 );

/* ---------------------------------------------------------------------------
 * 7. Smooth page change (View Transitions)
 *
 * The old page stays on screen until the new one is ready, then a short
 * crossfade swaps them. The header is identical on both pages, so it looks like
 * it never moves or reloads. Chrome, Edge and Safari 18.2+; other browsers
 * simply navigate normally. Turned off for visitors who ask for reduced motion.
 * ------------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	echo '<style id="iwc-view-transitions">@view-transition{navigation:auto}::view-transition-old(root),::view-transition-new(root){animation-duration:.18s}@media (prefers-reduced-motion:reduce){@view-transition{navigation:none}}</style>' . "\n";
}, 2 );

/* ---------------------------------------------------------------------------
 * 8. LeadConnector chat widget: delayed load
 *
 * First page of a visit: loads 4 s after the first interaction (when the
 * browser is idle), or after 8 s with no interaction, so it never lands on top
 * of the tracking tags in section 6.
 * Later pages in the same visit: loads straight away, so the chat bubble does
 * not pop in late on every page change.
 * Never loads on a page being built in the background by instant navigation.
 * ------------------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	?>
	<script>
	(function () {
		var loaded = false;
		var EVENTS = ['mouseover', 'keydown', 'touchstart', 'scroll', 'click'];
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
		function whenIdle() {
			if ('requestIdleCallback' in window) { requestIdleCallback(loadLeadConnector, { timeout: 1500 }); } else { setTimeout(loadLeadConnector, 300); }
		}
		function onFirstInteraction() {
			EVENTS.forEach(function (e) { document.removeEventListener(e, onFirstInteraction, { passive: true }); });
			setTimeout(whenIdle, 4000);
		}
		function start() {
			var alreadySeen = false;
			try { alreadySeen = sessionStorage.getItem('lc_loaded') === '1'; } catch (e) {}
			if (alreadySeen) {
				loadLeadConnector();
				return;
			}
			EVENTS.forEach(function (e) { document.addEventListener(e, onFirstInteraction, { passive: true }); });
			setTimeout(loadLeadConnector, 8000);
		}
		if (document.prerendering) {
			document.addEventListener('prerenderingchange', start, { once: true });
		} else {
			start();
		}
	})();
	</script>
	<?php
}, 99 );
