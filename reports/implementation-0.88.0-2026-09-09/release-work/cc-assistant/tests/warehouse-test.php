<?php
/**
 * Warehouse functional test with a stubbed site proxy. Covers: sync with
 * pagination, idempotent re-pull, read-only SQL + guards, truncation
 * bookkeeping, and the corrupt/zero-byte failure paths that must return
 * clean errors instead of killing the MCP process.
 * Run: php -d extension=sqlite3 tests/warehouse-test.php
 */
error_reporting( E_ALL );
putenv( 'CC_WAREHOUSE_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . '.tmp-warehouse' );
putenv( 'CC_WP_URL=https://smoke-test.example' );

function wp_rest_call( $endpoint, $method = 'GET', $body = null, $params = array() ) {
	if ( '/gsc-export/status' === $endpoint ) {
		return array( 'connected' => true, 'property' => 'sc-domain:smoke-test.example', 'freshest_date' => '2026-07-27', 'export_capable' => true );
	}
	if ( '/gsc-export' === $endpoint ) {
		$date  = $params['date'];
		$start = (int) $params['start_row'];
		if ( '2026-07-27' === $date && 0 === $start ) {
			return array(
				'date' => $date, 'start_row' => 0, 'property' => 'sc-domain:smoke-test.example',
				'rows' => array(
					array( 'https://smoke-test.example/a', 'er near me', 5, 100, 8.4 ),
					array( 'https://smoke-test.example/a', 'emergency room', 2, 50, 12.1 ),
					array( 'https://smoke-test.example/b', 'chest pain er', 1, 30, 4.0 ),
				),
				'row_count' => 3, 'next_start_row' => 3,
			);
		}
		if ( '2026-07-27' === $date && 3 === $start ) {
			return array(
				'date' => $date, 'start_row' => 3, 'property' => 'sc-domain:smoke-test.example',
				'rows' => array( array( 'https://smoke-test.example/c', 'ivs for dehydration', 0, 9, 44.0 ) ),
				'row_count' => 1, 'next_start_row' => null,
			);
		}
		return array(
			'date' => $date, 'start_row' => $start, 'property' => 'sc-domain:smoke-test.example',
			'rows' => array(
				array( 'https://smoke-test.example/a', 'er near me', 1, 40, 9.0 ),
				array( 'https://smoke-test.example/b', 'urgent care', 0, 12, 22.5 ),
			),
			'row_count' => 2, 'next_start_row' => null,
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

@mkdir( __DIR__ . DIRECTORY_SEPARATOR . '.tmp-warehouse', 0777, true );
$db_path = cc_wh_db_path();
foreach ( array( $db_path, $db_path . '-journal' ) as $f ) {
	if ( file_exists( $f ) ) { unlink( $f ); }
}

check( 'sqlite available', true === cc_wh_available() );
check( 'site key derived from host', 'smoke-test-example' === cc_wh_site_key(), cc_wh_site_key() );

$r = cc_wh_tool_sync( array( 'max_dates' => 3, 'refresh_days' => 3, 'backfill' => false ) );
check( 'sync no error', ! isset( $r['error'] ), json_encode( $r ) );
check( 'sync 3 dates', 3 === $r['synced_dates'], json_encode( $r ) );
check( 'sync rows 4+2+2', 8 === $r['rows_fetched'], (string) $r['rows_fetched'] );
check( 'coverage latest = freshest', '2026-07-27' === $r['coverage']['latest'], json_encode( $r['coverage'] ) );
check( 'coverage earliest = freshest-2', '2026-07-25' === $r['coverage']['earliest'], json_encode( $r['coverage'] ) );

$r2 = cc_wh_tool_sync( array( 'max_dates' => 3, 'refresh_days' => 3, 'backfill' => false ) );
check( 're-sync no error', ! isset( $r2['error'] ) );
$q = cc_wh_tool_query( array( 'sql' => 'SELECT COUNT(*) FROM gsc_daily' ) );
check( 'no duplicates after re-sync', 8 === (int) $q['rows'][0][0], json_encode( $q ) );

$q = cc_wh_tool_query( array(
	'sql'    => 'SELECT query, SUM(impressions) AS imp, SUM(clicks) AS ck FROM gsc_daily WHERE date >= ? GROUP BY query ORDER BY imp DESC',
	'params' => array( '2026-07-25' ),
) );
check( 'aggregate query works', ! isset( $q['error'] ) && 'er near me' === $q['rows'][0][0], json_encode( $q ) );
check( 'er near me imps 100+40+40', 180 === (int) $q['rows'][0][1], json_encode( $q['rows'][0] ) );
check( 'columns surfaced', array( 'query', 'imp', 'ck' ) === $q['columns'], json_encode( $q['columns'] ) );

$g1 = cc_wh_tool_query( array( 'sql' => 'DELETE FROM gsc_daily' ) );
check( 'write rejected', isset( $g1['error'] ) && 'read_only' === $g1['error'], json_encode( $g1 ) );
$g2 = cc_wh_tool_query( array( 'sql' => 'SELECT 1; DROP TABLE gsc_daily' ) );
check( 'multi-statement rejected', isset( $g2['error'] ) && 'multi_statement' === $g2['error'], json_encode( $g2 ) );
$g3 = cc_wh_tool_query( array( 'sql' => 'SELECT nope FROM missing_table' ) );
check( 'sql error surfaced cleanly', isset( $g3['error'] ) && 'sql_error' === $g3['error'], json_encode( $g3 ) );
$g4 = cc_wh_tool_query( array( 'sql' => 'WITH t AS (SELECT 1 AS x) SELECT x FROM t' ) );
check( 'WITH allowed', ! isset( $g4['error'] ) && 1 === (int) $g4['rows'][0][0], json_encode( $g4 ) );
$q = cc_wh_tool_query( array( 'sql' => "SELECT COUNT(*) FROM gsc_daily WHERE query LIKE '%;%'" ) );
check( 'semicolon in literal allowed', ! isset( $q['error'] ), json_encode( $q ) );
$q = cc_wh_tool_query( array( 'sql' => "SELECT 1 WHERE 'a' = ';'; DROP TABLE gsc_daily" ) );
check( 'real multi-statement still rejected', isset( $q['error'] ) && 'multi_statement' === $q['error'], json_encode( $q ) );

$t = cc_wh_tool_query( array( 'sql' => 'SELECT * FROM gsc_daily', 'max_rows' => 3 ) );
check( 'truncation flag', true === $t['truncated'] && 3 === $t['row_count'], json_encode( array( $t['row_count'], $t['truncated'] ) ) );

$s = cc_wh_tool_status();
check( 'status rows', 8 === $s['total_rows'], json_encode( $s ) );
$real_freshest = gmdate( 'Y-m-d', time() - 2 * 86400 );
$expect_stale  = '2026-07-27' < $real_freshest;
check( 'status stale hint matches real clock', $expect_stale === ( null !== $s['stale_hint'] ), json_encode( array( $real_freshest, $s['stale_hint'] ) ) );
check( 'status property', 'sc-domain:smoke-test.example' === $s['property'], json_encode( $s['property'] ) );

$e = cc_wh_friendly_transport_error( array( 'error' => 'http_error', 'status' => 404, 'body' => '{"code":"rest_no_route","message":"..."}' ) );
check( 'rest_no_route mapped to upgrade hint', 'site_plugin_outdated' === $e['error'], json_encode( $e ) );

$dbh = cc_wh_open( false );
$dbh->exec( "INSERT OR REPLACE INTO sync_log (date, rows, fetched_at, complete) VALUES ('2026-07-20', 600000, 1, 0)" );
$dbh->close();
$s2 = cc_wh_tool_status();
check( 'incomplete date counted', 1 === $s2['incomplete_dates'], json_encode( $s2 ) );

unlink( $db_path );
file_put_contents( $db_path, '' );
$s3 = cc_wh_tool_status();
check( 'zero-byte db -> clean warehouse_empty', isset( $s3['error'] ) && 'warehouse_empty' === $s3['error'], json_encode( $s3 ) );

file_put_contents( $db_path, 'this is not a sqlite database, it is a text file pretending' . str_repeat( 'x', 4096 ) );
$s4 = cc_wh_tool_status();
check( 'corrupt db status -> clean error', isset( $s4['error'] ), json_encode( array_slice( (array) $s4, 0, 2 ) ) );
$s5 = cc_wh_tool_sync( array( 'max_dates' => 1, 'refresh_days' => 1, 'backfill' => false ) );
check( 'corrupt db sync -> clean error', isset( $s5['error'] ), json_encode( array_slice( (array) $s5, 0, 2 ) ) );


/* ------------------------------------------------------------------ *
 * Inflated-impressions guard (v0.76.3). Google over-reported Search
 * Console impressions 2025-05-13 to 2026-04-27 and did not restate
 * history. The WP-side table prunes at 60 days so it can never hold
 * contaminated rows — but THIS warehouse keeps everything, and on
 * 2026-08-27 all four live warehouses started inside the window
 * (earliest 2025-11-11 to 2025-12-15). A DiD baseline drawn from that
 * period and compared against a post-fix window invents a decline.
 * ------------------------------------------------------------------ */
check( 'bug window constants are defined',
	defined( 'CC_WH_IMPRESSION_BUG_START' ) && defined( 'CC_WH_IMPRESSION_BUG_END' ) );
check( 'bug window matches the Google anomalies entry',
	'2025-05-13' === CC_WH_IMPRESSION_BUG_START && '2026-04-27' === CC_WH_IMPRESSION_BUG_END );

// A range that starts after the fix is clean and must stay silent, or the
// warning becomes permanent noise that everyone learns to ignore.
check( 'range entirely after the fix -> null',
	null === cc_wh_impressions_integrity( '2026-05-01', '2026-07-01' ) );
check( 'site-table 60d retention window -> null',
	null === cc_wh_impressions_integrity( '2026-06-28' ) );
check( 'gsc_trends 7d window -> null',
	null === cc_wh_impressions_integrity( '2026-08-12', '2026-08-18' ) );
check( 'empty date -> null (never guess on missing data)',
	null === cc_wh_impressions_integrity( '' ) );

// A range inside the window is flagged.
$in = cc_wh_impressions_integrity( '2026-01-10', '2026-03-10' );
check( 'range inside the window is flagged', is_array( $in ) );
check( 'flag carries the machine-readable code',
	is_array( $in ) && 'gsc_impressions_inflated' === $in['code'] );
check( 'flag states clicks are unaffected',
	is_array( $in ) && true === $in['clicks_ok'] );
check( 'flag names the source doc',
	is_array( $in ) && false !== strpos( $in['source'], 'support.google.com' ) );
check( 'a wholly-inside range is NOT described as straddling',
	is_array( $in ) && false === strpos( $in['note'], 'STRADDLES' ) );

// The dangerous case: baseline inside, comparison after. This is the shape
// that manufactures a fake collapse.
$straddle = cc_wh_impressions_integrity( '2026-04-01', '2026-06-01' );
check( 'a straddling range is flagged', is_array( $straddle ) );
check( 'a straddling range says so explicitly',
	is_array( $straddle ) && false !== strpos( $straddle['note'], 'STRADDLES' ) );

// The real warehouse spans measured on 2026-08-27 must all flag.
foreach ( array(
	'erofirving-com'           => '2025-12-05',
	'eroflufkin-com'           => '2025-12-15',
	'erofwhiterock-com'        => '2025-12-05',
	'irvingwellnessclinic-com' => '2025-11-11',
) as $site => $earliest ) {
	check( "live warehouse flagged: $site (from $earliest)",
		is_array( cc_wh_impressions_integrity( $earliest, '2026-08-22' ) ) );
}

// Structural: the guard must be wired into the two reads that can reach the
// window, and must NOT be bolted onto tools whose windows cannot.
$wh_src = file_get_contents( dirname( __DIR__ ) . '/bin/warehouse.php' );
check( 'warehouse status reports data_integrity',
	false !== strpos( $wh_src, "'data_integrity' => cc_wh_impressions_integrity(" ) );
check( 'outcome_report flags per-edit baselines',
	false !== strpos( $wh_src, "\$item['data_integrity'] = cc_wh_impressions_integrity( \$pre_start, \$post_end );" ) );
check( 'outcome_report carries a report-level read',
	false !== strpos( $wh_src, "'data_integrity'     => cc_wh_impressions_integrity( \$wh_min, \$wh_max )," ) );
check( 'the helper stays self-contained (no plugin dependency)',
	false === strpos( $wh_src, 'CC_Assistant_GSC::impressions_integrity' ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
