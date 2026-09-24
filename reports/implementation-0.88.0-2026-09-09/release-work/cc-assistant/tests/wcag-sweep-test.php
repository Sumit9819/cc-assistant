<?php
/**
 * wcag_sweep: the pure parts - sitemap parsing, URL filtering, and the
 * summary the assistant reads (rule ranking, vendor labelling, contrast
 * grouping, keyboard findings).
 *
 * Run: php tests/wcag-sweep-test.php
 */
error_reporting( E_ALL );
putenv( 'CC_WP_URL=https://wcag.test' );
putenv( 'CC_WCAG_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . '.tmp-wcag' );
function cc_wh_dir() { return __DIR__ . '/.tmp-warehouse'; }
function cc_wh_site_key() { return 'wcag_test'; }

require dirname( __DIR__ ) . '/bin/wcag-sweep.php';

$fails = 0;
function check( $label, $cond, $detail = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n"; }
}

echo "--- sitemap + filtering ---\n";
$xml = '<?xml version="1.0"?><urlset><url><loc>https://wcag.test/</loc></url><url><loc> https://wcag.test/a-page/ </loc></url><url><loc>https://wcag.test/category/x/</loc></url><url><loc>https://wcag.test/locations.kml</loc></url><url><loc>https://wcag.test/post-sitemap.xml</loc></url></urlset>';
$locs = cc_wcag_parse_sitemap_xml( $xml );
check( 'parses 5 loc entries, trimmed', 5 === count( $locs ) && 'https://wcag.test/a-page/' === $locs[1] );
$f = cc_wcag_filter_urls( array_merge( $locs, array( 'https://wcag.test/a-page', 'not a url', 'https://wcag.test/feed/' ) ) );
check( 'filters archives, kml, xml, feed and de-dupes trailing slash', array( 'https://wcag.test/', 'https://wcag.test/a-page/' ) === $f, json_encode( $f ) );

echo "--- summary ---\n";
$data = array(
	'ran_at' => '2026-08-25T00:00:00Z',
	'pages'  => array(
		array( 'url' => 'https://wcag.test/', 'status' => 200, 'error' => null, 'violations' => array(
			array( 'id' => 'color-contrast', 'impact' => 'serious', 'wcag' => array( 'wcag2aa', 'wcag143' ), 'help' => 'contrast', 'count' => 3, 'nodes' => array(
				array( 'target' => 'a', 'html' => '<span class="elementor-post-date elementor-element-abc">d</span>', 'summary' => 'Element has insufficient color contrast of 2.24 (foreground color: #adadad, background color: #ffffff' ),
				array( 'target' => 'b', 'html' => '<span class="elementor-post-date elementor-element-def">d</span>', 'summary' => 'Element has insufficient color contrast of 2.24 (foreground color: #adadad, background color: #ffffff' ),
			) ),
			array( 'id' => 'aria-allowed-attr', 'impact' => 'critical', 'wcag' => array( 'wcag2a', 'wcag412' ), 'help' => 'aria', 'count' => 1, 'nodes' => array( array( 'target' => 'h3', 'html' => '<h3 role="button">', 'summary' => '' ) ) ),
		), 'keyboard' => array( 'skip_link' => array( 'present' => false ), 'focus_invisible' => array( array( 'tag' => 'input', 'text' => '', 'cls' => 'nhw-input' ) ), 'forms' => array( array( 'form' => 0, 'fields' => 2, 'required' => 0, 'result' => 'none' ) ), 'popup' => array( 'open' => true, 'focus_inside' => false, 'escape_closes' => true ), 'tab_stops' => 12 ) ),
		array( 'url' => 'https://wcag.test/clean/', 'status' => 200, 'error' => null, 'violations' => array(), 'keyboard' => array( 'skip_link' => array( 'present' => true, 'target_exists' => false ), 'focus_invisible' => array(), 'forms' => array(), 'popup' => array( 'open' => false ), 'tab_stops' => 5 ) ),
		array( 'url' => 'https://wcag.test/broken/', 'status' => null, 'error' => 'timeout', 'violations' => array(), 'keyboard' => null ),
	),
);
$s = cc_wcag_summarize( $data );
check( 'page counts', 2 === $s['pages_audited'] && 1 === $s['pages_errored'] && 1 === $s['pages_clean'], json_encode( array( $s['pages_audited'], $s['pages_errored'], $s['pages_clean'] ) ) );
check( 'critical rule ranks first', 'aria-allowed-attr' === $s['rules'][0]['rule'] );
check( 'vendor rule is labelled', ! empty( $s['rules'][0]['vendor'] ) );
check( 'node totals aggregate', 3 === $s['rules'][1]['nodes'] && 1 === $s['rules'][1]['pages'] );
check( 'contrast groups collapse by element+colour', 1 === count( $s['contrast_groups'] ) && 2 === $s['contrast_groups'][0]['sampled_nodes'] && '#adadad' === $s['contrast_groups'][0]['fg'] && '2.24' === $s['contrast_groups'][0]['ratio'], json_encode( $s['contrast_groups'] ) );
check( 'contrast element strips per-widget ids', false === strpos( $s['contrast_groups'][0]['element'], 'elementor-element-' ) );
$k = $s['keyboard'];
check( 'keyboard: skip-link states', 1 === $k['skip_link_missing'] && 1 === $k['skip_link_target_missing'] );
check( 'keyboard: invisible focus surfaced', 1 === $k['focus_invisible_pages'] && 'nhw-input' === $k['focus_invisible_examples'][0]['elements'][0]['cls'] );
check( 'keyboard: silent form surfaced', 1 === count( $k['forms_silent'] ) );
check( 'keyboard: popup without focus is a trap', 1 === count( $k['popup_traps'] ) );

echo "--- report without a run ---\n";
@mkdir( __DIR__ . '/.tmp-wcag', 0777, true );
@unlink( __DIR__ . '/.tmp-wcag/wcag_test.json' );
$r = cc_tool_wcag_sweep( array( 'action' => 'report' ) );
check( 'report says no_report before any run', isset( $r['error'] ) && 'no_report' === $r['error'] );
file_put_contents( __DIR__ . '/.tmp-wcag/wcag_test.json', json_encode( $data ) );
$r = cc_tool_wcag_sweep( array( 'action' => 'report' ) );
check( 'report reads the stored sweep', isset( $r['summary']['pages_audited'] ) && 2 === $r['summary']['pages_audited'] );
@unlink( __DIR__ . '/.tmp-wcag/wcag_test.json' );
@rmdir( __DIR__ . '/.tmp-wcag' );

echo "\n" . ( $fails ? "$fails FAILURES\n" : "ALL PASS\n" );
exit( $fails ? 1 : 0 );
