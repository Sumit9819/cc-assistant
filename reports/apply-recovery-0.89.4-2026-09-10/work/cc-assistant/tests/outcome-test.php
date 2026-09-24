<?php
/**
 * DiD outcome-engine fixture test. Hand-checkable scenario:
 * apply date 2026-06-29, symmetric 28-day windows.
 *  - /win-page/: 2 clicks/day pre, 4/day post  -> page +100%
 *  - /control-page/: 10/day flat               -> page 0%
 *  - /tiny-page/: ~0 clicks                    -> low_data
 *  - site grows ~17%, driven almost entirely by /win-page/ doubling.
 *
 * The site-wide control EXCLUDES the page being measured (2026-08-21), so
 * /win-page/'s own growth is no longer subtracted from itself: the rest of the
 * site is nearly flat once it is removed, and did lands just under its +100%
 * page delta instead of the old ~+83. /control-page/ is still regressed - the
 * rest of the site really did grow around it.
 * Stubs mirror the REAL response shapes: /edits uses the {site, data:{...}}
 * envelope (stubbing the unwrapped shape previously hid a shipped bug).
 */
error_reporting( E_ALL );
putenv( 'CC_WAREHOUSE_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . '.tmp-outcome' );
putenv( 'CC_WP_URL=https://outcome-test.example' );

function wp_rest_call( $endpoint, $method = 'GET', $body = null, $params = array() ) {
	if ( '/edits' === $endpoint ) {
		$mk = function ( $id, $post_id, $url, $applied, $summary ) {
			return array( 'edit' => array(
				'id' => $id, 'post_id' => $post_id, 'post_exists' => true,
				'change_type' => 'elementor_widget_update', 'change_summary' => $summary,
				'page_url' => $url, 'applied_at' => $applied, 'edit_url' => null,
			) );
		};
		return array( 'site' => array( 'name' => 'Outcome Test' ), 'data' => array( 'count' => 5, 'edits' => array(
			$mk( 1, 101, 'https://outcome-test.example/win-page/', '2026-06-29 14:00:00', 'rewrote hero' ),
			$mk( 2, 102, 'https://outcome-test.example/control-page/', '2026-06-29 15:00:00', 'meta tweak' ),
			$mk( 3, 103, 'https://outcome-test.example/tiny-page/', '2026-06-29 16:00:00', 'alt text' ),
			$mk( 4, 104, 'https://outcome-test.example/late-page/', '2026-07-25 09:00:00', 'fresh edit' ),
			$mk( 5, 105, 'https://outcome-test.example/', '2026-06-29 17:00:00', 'homepage cta' ),
		) ) );
	}
	if ( '/leads' === $endpoint ) {
		// /leads is a rest_ensure_response route: NO data envelope.
		return array(
			'columns' => array( 'date', 'post_id', 'form_name', 'count' ),
			'rows'    => array(
				array( '2026-06-10', 101, 'contact', 2 ),
				array( '2026-07-05', 101, 'contact', 5 ),
				array( '2026-07-06', 999, 'contact', 3 ),
				array( '2026-07-07', 0, 'contact', 9 ),
			),
			'total'   => 19,
		);
	}
	return array( 'error' => 'http_error', 'status' => 404, 'body' => '{"code":"rest_no_route"}' );
}

require dirname( __DIR__ ) . '/bin/warehouse.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label  $extra\n"; }
}

@mkdir( __DIR__ . DIRECTORY_SEPARATOR . '.tmp-outcome', 0777, true );
$db_path = cc_wh_db_path();
if ( file_exists( $db_path ) ) { unlink( $db_path ); }
$db = cc_wh_open( false );
if ( is_array( $db ) ) { echo 'FAIL open: ' . json_encode( $db ) . "\n"; exit( 1 ); }

$ins = $db->prepare( 'INSERT INTO gsc_daily (date,page,query,clicks,impressions,position) VALUES (?,?,?,?,?,?)' );
$seed = function ( $date, $page, $clicks ) use ( $ins ) {
	$ins->bindValue( 1, $date, SQLITE3_TEXT );
	$ins->bindValue( 2, $page, SQLITE3_TEXT );
	$ins->bindValue( 3, 'q-' . $date, SQLITE3_TEXT );
	$ins->bindValue( 4, $clicks, SQLITE3_INTEGER );
	$ins->bindValue( 5, $clicks * 20, SQLITE3_INTEGER );
	$ins->bindValue( 6, 8.0, SQLITE3_FLOAT );
	$ins->execute();
	$ins->reset();
};

$db->exec( 'BEGIN' );
for ( $d = '2026-06-01'; $d <= '2026-07-28'; $d = cc_wh_date_add( $d, 1 ) ) {
	$pre = ( $d <= '2026-06-28' );
	$seed( $d, 'https://outcome-test.example/win-page/', $pre ? 2 : 4 );
	$seed( $d, 'https://outcome-test.example/control-page/', 10 );
	if ( in_array( $d, array( '2026-06-05', '2026-07-05' ), true ) ) {
		$seed( $d, 'https://outcome-test.example/tiny-page/', $pre ? 5 : 8 );
	}
}
$db->exec( "INSERT INTO sync_log (date, rows, fetched_at, complete) VALUES ('2026-06-01', 1, 1, 1), ('2026-07-28', 1, 1, 1)" );
$db->exec( 'COMMIT' );
$db->close();

$r = cc_wh_tool_outcome_report( array( 'window' => 28 ) );
check( 'report has no error', ! isset( $r['error'] ), json_encode( $r ) );
check( 'edits were unwrapped from data envelope', 5 === count( $r['edits'] ), (string) count( $r['edits'] ) );

$by_id = array();
foreach ( $r['edits'] as $e ) { $by_id[ $e['edit_id'] ] = $e; }

check( 'win page verdict = win', 'win' === $by_id[1]['verdict'], json_encode( $by_id[1] ) );
// With /win-page/ removed from the control the rest of the site barely moves,
// so did should sit just below the page's own +100%. The OLD value (~83) was the
// page's own growth being subtracted from itself via a control that contained it.
check( 'win did just under page delta (control excludes the page)',
	$by_id[1]['did_delta_pct'] >= 95 && $by_id[1]['did_delta_pct'] <= 100, (string) $by_id[1]['did_delta_pct'] );
check( 'win site_delta is now the REST of the site, near flat',
	abs( $by_id[1]['site_delta_pct'] ) < 5.0, (string) $by_id[1]['site_delta_pct'] );
check( 'win windows symmetric 28', 28 === $by_id[1]['days_each_side'] );
check( 'win page clicks 56 -> 112', 56 === $by_id[1]['page_clicks']['pre'] && 112 === $by_id[1]['page_clicks']['post'], json_encode( $by_id[1]['page_clicks'] ) );
check( 'control verdict = regressed (site grew, page flat)', 'regressed' === $by_id[2]['verdict'], json_encode( $by_id[2] ) );
check( 'tiny verdict = low_data', 'low_data' === $by_id[3]['verdict'], json_encode( $by_id[3] ) );
check( 'late verdict = too_early', 'too_early' === $by_id[4]['verdict'], json_encode( $by_id[4] ) );
// The homepage used to be hard-skipped as no_url because page matching was a
// suffix LIKE where '/' matched every URL. Matching is exact now, so it is
// scored like any other page. This fixture seeds no homepage traffic, so the
// honest verdict is low_data - but it must have gone through real scoring.
check( 'homepage is NO LONGER skipped as no_url', 'no_url' !== $by_id[5]['verdict'], json_encode( $by_id[5] ) );
check( 'homepage was actually scored (symmetric windows present)', 28 === $by_id[5]['days_each_side'], json_encode( $by_id[5] ) );
check( 'homepage verdict = low_data (no traffic seeded for it)', 'low_data' === $by_id[5]['verdict'], json_encode( $by_id[5] ) );
check( 'regressed sorted first', 'regressed' === $r['edits'][0]['verdict'], json_encode( array_column( $r['edits'], 'verdict' ) ) );
check( 'summary counts', 1 === $r['summary']['win'] && 1 === $r['summary']['regressed'] && 2 === $r['summary']['low_data'] && 1 === $r['summary']['too_early'] && 0 === $r['summary']['no_url'], json_encode( $r['summary'] ) );
check( 'leads folded into win edit (2 pre, 5 post)', isset( $by_id[1]['leads'] ) && 2 === $by_id[1]['leads']['pre'] && 5 === $by_id[1]['leads']['post'], json_encode( isset( $by_id[1]['leads'] ) ? $by_id[1]['leads'] : null ) );
check( 'no leads key when page has none', ! isset( $by_id[2]['leads'] ) );
check( 'unattributed leads surfaced', 9 === $r['leads_unattributed'] && isset( $r['leads_note'] ), json_encode( isset( $r['leads_unattributed'] ) ? $r['leads_unattributed'] : null ) );

// ---- direct, hand-checkable proof of the exclusion mode ----------------
// Pre window 2026-06-01..2026-06-28 seeds: win 2/day x28 = 56, control
// 10/day x28 = 280, tiny 5 (one day only). Total 341.
$db2      = cc_wh_open( false );
$all      = cc_wh_window_totals( $db2, '2026-06-01', '2026-06-28' );
$no_win   = cc_wh_window_totals( $db2, '2026-06-01', '2026-06-28', null, '/win-page/' );
$only_win = cc_wh_window_totals( $db2, '2026-06-01', '2026-06-28', '/win-page/' );
$db2->close();
check( 'all-pages total is 341 clicks', 341 === $all['clicks'], (string) $all['clicks'] );
check( 'win-page alone is 56 clicks', 56 === $only_win['clicks'], (string) $only_win['clicks'] );
check( 'excluding win-page gives 285', 285 === $no_win['clicks'], (string) $no_win['clicks'] );
check( 'exclusion is exactly complementary (all - excluded == page)',
	$all['clicks'] - $no_win['clicks'] === $only_win['clicks'] );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
