<?php
add_action( 'wp_head', function () {
	$base = 'https://sids-ponds.com/wp-content/uploads/2026/08';

	// LCP fix: slide 1's background image is the largest thing on screen, but
	// as a CSS background the browser gives it the LOWEST download priority —
	// measured starting ~3s in and finishing near 20s on throttled mobile.
	// Preloading with fetchpriority=high starts it at byte one, ahead of the
	// script queue. Same URL serves all breakpoints (verified: no mobile
	// variant rule), so one preload covers every device.
	echo '<link rel="preload" href="https://sids-ponds.com/wp-content/uploads/2023/03/Sids-Ponds-Secure-Outdoor-Storage-Banner-min.png" as="image" fetchpriority="high">' . "\n";

	// Preload the above-the-fold fonts (body text, hero headlines, and the
	// free-shipping ticker) so font-display:optional always has them ready.
	foreach ( array( 'lato-400.woff2', 'archivo-black-400.woff2', 'lato-700-italic.woff2' ) as $critical ) {
		printf(
			'<link rel="preload" href="%s/%s" as="font" type="font/woff2" crossorigin>' . "\n",
			$base,
			$critical
		);
	}

	// Declared AFTER the Google Fonts CSS in the head, so these faces win the
	// cascade: browsers use our self-hosted files and never download Google's.
	// font-display:optional means a font that misses its window is skipped for
	// that page view instead of swapped in late.
	// Weights not declared here (100/300) fall through to Google unchanged,
	// and if any file ever goes missing the same fallback applies: fail-safe.
	$faces = array(
		array( 'Lato', 'normal', 400, 'lato-400.woff2' ),
		array( 'Lato', 'normal', 700, 'lato-700.woff2' ),
		array( 'Lato', 'normal', 900, 'lato-900.woff2' ),
		array( 'Lato', 'italic', 400, 'lato-400-italic.woff2' ),
		array( 'Lato', 'italic', 700, 'lato-700-italic.woff2' ),
		array( 'Archivo Black', 'normal', 400, 'archivo-black-400.woff2' ),
	);
	echo '<style id="sp-selfhost-fonts">';
	foreach ( $faces as $f ) {
		printf(
			"@font-face{font-family:'%s';font-style:%s;font-weight:%d;font-display:optional;src:url(%s/%s) format('woff2');}",
			$f[0],
			$f[1],
			$f[2],
			$base,
			$f[3]
		);
	}
	echo "</style>\n";
}, 99 );