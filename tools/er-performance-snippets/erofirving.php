<?php
/**
 * ER of Irving: performance snippet (built 2026-09-17).
 *
 * HOW TO USE: paste everything BELOW the "<?php" line into a new PHP snippet in
 * Code Snippets, "Run snippet everywhere", then Save Changes and Activate.
 * Nothing here edits the theme, so theme updates cannot remove it.
 *
 * Measured before this snippet (Google Lighthouse, US, one run each):
 * desktop LCP 1.5 s; mobile first paint 6.3 s, LCP 8.3 s; 127 KB unused CSS.
 *
 * What it does:
 * - Phones stop downloading the 162 KB desktop hero; each screen preloads only its own hero.
 * - Removes leftover head tags (WordPress version, RSD, shortlink, REST link) and the Rank Math credit.
 * - Instant page navigation, tracking tags that no longer land on the first mouse move, smooth page change.
 * - Removes jQuery Migrate and the unused Gutenberg global styles (the old child theme did this; it was removed on 2026-09-17).
 */

/* ---------------------------------------------------------------------------
 * Head cleanup: remove tags that browsers and visitors never use.
 * Safe to run alongside any theme code that already removes some of them.
 * ------------------------------------------------------------------------- */
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );  // newer WordPress hooks it here
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 ); // older WordPress hooks it here
add_filter( 'rank_math/frontend/remove_credit_notice', '__return_true' );

/* ---------------------------------------------------------------------------
 * jQuery Migrate and Gutenberg CSS
 *
 * jQuery Migrate only prints a compatibility banner here: tested 2026-09-17 on
 * the homepage and Contact page with it removed, no JavaScript errors, mobile
 * menu still opens. The site is built in Elementor with the Classic Editor, so
 * the block-editor CSS is unused.
 * ------------------------------------------------------------------------- */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
		$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
	}
} );
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
}, 100 );

/* ---------------------------------------------------------------------------
 * Homepage hero image: preload the right file for the screen size
 *
 * The CC Assistant plugin preloads the DESKTOP hero (2560 px, 162 KB) for every
 * screen, so phones download it on top of their own mobile hero. Elementor
 * switches to the mobile image at 767 px and below (checked at 766/767/768).
 * These two preloads load only the one each screen uses; the plugin's preload is
 * removed on the homepage only.
 * If the homepage hero image is changed in Elementor, update both URLs here.
 * ------------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<link rel="preload" as="image" fetchpriority="high" type="image/webp" href="https://erofirving.com/wp-content/uploads/2025/01/Fast-Expert-Care-scaled.webp" media="(min-width: 768px)">' . "\n";
	echo '<link rel="preload" as="image" fetchpriority="high" type="image/webp" href="https://erofirving.com/wp-content/uploads/2026/06/ER-of-Irving-Mobile-Background-Image-of-Hero-Section.webp" media="(max-width: 767px)">' . "\n";
}, 1 );

add_action( 'template_redirect', function () {
	if ( is_front_page() && class_exists( 'CC_Assistant_Hero_Preload' ) ) {
		remove_action( 'wp_head', array( 'CC_Assistant_Hero_Preload', 'emit' ), 2 );
	}
} );

/* ---------------------------------------------------------------------------
 * Instant page navigation (Speculation Rules, prerender)
 *
 * When a visitor hovers a link (or presses it on a phone) Chrome and Edge build
 * that page in the background, so the click shows it straight away. Replaces
 * WordPress's own slower "prefetch" rules. Built with wp_json_encode and no
 * backslashes: a hand-typed version lost a backslash on a sister site, which
 * made the JSON invalid and Chrome silently ignored it.
 * ------------------------------------------------------------------------- */
add_filter( 'wp_speculation_rules_configuration', '__return_null' );

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
	echo '<script type="speculationrules">' . wp_json_encode( $rules, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}, 1 );

/* ---------------------------------------------------------------------------
 * Tracking tags (Flying Scripts): load a moment after the visitor starts
 *
 * Flying Scripts holds back the Google Tag Manager snippet and releases it on
 * the first mouse move, which drops Tag Manager, Meta and Clarity on the page
 * exactly as the person starts reading. This takes over its trigger (its loader
 * prints at wp_print_footer_scripts priority 10; this runs at 20):
 *   - first interaction  -> wait 2 s, then load when the browser is idle
 *   - no interaction     -> load after 5 s, as before
 *   - page being built in the background (instant navigation) -> nothing loads
 *     until the visitor opens it, so a hover never counts as a visit
 * Trade-off: a visit that ends within about 3 s of the first interaction may
 * not be counted. Flying Scripts only holds back Tag Manager on these sites, so
 * no menu or page feature waits on this.
 * ------------------------------------------------------------------------- */
add_action( 'wp_print_footer_scripts', function () {
	?>
	<script id="er-tag-scheduler">
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
 * Smooth page change (View Transitions)
 *
 * The old page stays on screen until the new one is ready, then a short
 * crossfade swaps them, so the header looks like it never reloads. Chrome, Edge
 * and Safari 18.2+; other browsers navigate normally. Off for reduced motion.
 * ------------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	echo '<style id="er-view-transitions">@view-transition{navigation:auto}::view-transition-old(root),::view-transition-new(root){animation-duration:.18s}@media (prefers-reduced-motion:reduce){@view-transition{navigation:none}}</style>' . "\n";
}, 2 );
