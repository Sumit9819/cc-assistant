<?php
error_reporting( E_ALL );
$dir = sys_get_temp_dir() . '/cc-aeo-' . bin2hex( random_bytes( 6 ) );
putenv( 'CC_WAREHOUSE_DIR=' . $dir ); putenv( 'CC_WP_URL=https://aeo-fixture.invalid' );
function wp_rest_call( $endpoint, $method = 'GET', $body = null, $params = array() ) { return array( 'error' => 'not_connected' ); }
require dirname( __DIR__ ) . '/bin/warehouse.php';
function check( $s, $v ) { if ( ! $v ) { throw new RuntimeException( $s ); } echo "PASS $s\n"; }
try {
    $db = cc_wh_open(); $today = gmdate( 'Y-m-d' ); $yesterday = gmdate( 'Y-m-d', time() - 86400 );
    $db->exec( "INSERT INTO gsc_daily (date,page,query,clicks,impressions,position) VALUES ('$today','/repair','tire repair',0,400,2)" );
    $db->exec( 'CREATE TABLE aeo_snapshots (snapshot_date TEXT PRIMARY KEY, data TEXT NOT NULL)' );
    $db->exec( "INSERT INTO aeo_snapshots VALUES ('$yesterday', '{\"absorbed_28d\":{\"queries\":5,\"impressions\":500}}')" ); $db->close();
    $r = cc_wh_tool_aeo_snapshot( array() );
    check( 'low CTR is a candidate with unknown cause', 1 === $r['snapshot']['low_ctr_candidates_28d']['queries'] && 'unknown' === $r['snapshot']['ai_attribution'] );
    check( 'legacy AI attribution is not compared as equivalent measurement', 'not_comparable_legacy_measurement' === $r['previous_comparison'] && ! isset( $r['vs_previous'] ) );
    check( 'missing crawler evidence stays unavailable', isset( $r['snapshot']['llm_crawls_7d']['unavailable'] ) );
    $db = cc_wh_open(); $data = SQLite3::escapeString( json_encode( $r['snapshot'] ) );
    $db->exec( "UPDATE aeo_snapshots SET data='$data' WHERE snapshot_date='$yesterday'" ); $db->close();
    $r = cc_wh_tool_aeo_snapshot( array() ); check( 'only compatible snapshots have candidate deltas', 0 === $r['vs_previous']['low_ctr_queries_delta'] );
} finally {
    foreach ( glob( $dir . '/*' ) as $file ) { unlink( $file ); } rmdir( $dir );
}
echo "All AEO observation checks passed.\n";
