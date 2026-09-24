<?php
/**
 * ER of White Rock: performance snippet (built 2026-09-17).
 *
 * HOW TO USE: paste everything BELOW the "<?php" line into a new PHP snippet in
 * Code Snippets, "Run snippet everywhere", then Save Changes and Activate.
 * Nothing here edits the theme, so theme updates cannot remove it.
 *
 * Measured before this snippet (Google Lighthouse, US, one run each):
 * desktop LCP 0.72 s; mobile first paint 2.0 s, LCP 3.2 s.
 *
 * What it does:
 * - Removes jQuery Migrate (tested: no errors) and the unused Gutenberg global styles.
 * - Removes the remaining head tags and the Rank Math credit.
 * - Instant page navigation, tracking tags that no longer land on the first mouse move, smooth page change.
 * - (The theme functions.php already removes emoji, WordPress version, RSD and REST links; leave it as is.)
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
