<?php
/**
 * Commodity router + AEO snapshot test. Pure classifier rules, the full
 * commodity_audit flow (warehouse + stubbed content signals), and snapshot
 * storage with previous-snapshot deltas.
 * Run: php -d extension=sqlite3 tests/commodity-test.php
 */
error_reporting( E_ALL );
putenv( 'CC_WAREHOUSE_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . '.tmp-commodity' );
putenv( 'CC_WP_URL=https://commodity-test.example' );

function wp_rest_call( $endpoint, $method = 'GET', $body = null, $params = array() ) {
	if ( '/commodity/signals' === $endpoint ) {
		$rows = array();
		foreach ( $body['urls'] as $url ) {
			if ( false !== strpos( $url, '/ghost-' ) ) {
				$rows[] = array( 'url' => $url, 'post_id' => 0, 'found' => false );
				continue;
			}
			$is_service = false !== strpos( $url, '/service-' );
			$rows[] = array(
				'url'               => $url,
				'post_id'           => 100 + crc32( $url ) % 100,
				'found'             => true,
				'intent_family'     => $is_service ? 'action' : 'research',
				'page_type'         => $is_service ? 'service' : 'blog',
				'info_gain_signals' => ( false !== strpos( $url, '/rich-' ) ) ? array( 'data_table', 'authority_citation' ) : array(),
			);
		}
		return array( 'rows' => $rows, 'count' => count( $rows ) );
	}
	if ( '/llm-crawls' === $endpoint ) {
		return array( 'site' => array( 'name' => 'X' ), 'data' => array( 'total' => 42, 'by_bot' => array( 'GPTBot' => 30, 'ClaudeBot' => 12 ) ) );
	}
	if ( '/gsc/ai-overview' === $endpoint ) {
		return array( 'site' => array( 'name' => 'X' ), 'data' => array( 'aio_pages' => 7 ) );
	}
	return array( 'error' => 'http_error', 'status' => 404, 'body' => '{"code":"rest_no_route"}' );
}

require dirname( __DIR__ ) . '/bin/warehouse.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label  $extra\n"; }
}

// --- Pure classifier rules ---
$r = cc_commodity_classify( array( 'impressions' => 5000, 'clicks' => 1, 'position' => 2.0, 'intent_family' => 'research' ) );
check( 'low CTR is a review candidate with unknown AI attribution', 'REVIEW_CTR' === $r['verdict'] && null === $r['absorbed'], json_encode( $r ) );
$r = cc_commodity_classify( array( 'impressions' => 500, 'clicks' => 0, 'position' => 8.0, 'intent_family' => 'research' ) );
check( 'low volume never authorizes STOP', 'REVIEW_CTR' === $r['verdict'], json_encode( $r ) );
$r = cc_commodity_classify( array( 'impressions' => 5000, 'clicks' => 1, 'position' => 2.0, 'intent_family' => 'action' ) );
check( 'service page gets diagnosis instead of unconditional investment', 'REVIEW_CTR' === $r['verdict'] );
$r = cc_commodity_classify( array( 'impressions' => 3000, 'clicks' => 90, 'position' => 6.0, 'intent_family' => 'research' ) );
check( 'healthy research CTR is observed without an automatic link prescription', 'OBSERVE' === $r['verdict'] && null === $r['absorbed'], json_encode( $r ) );
$r = cc_commodity_classify( array( 'impressions' => 150, 'clicks' => 0, 'position' => 2.0, 'intent_family' => 'research' ) );
check( 'below impression floor leaves AI attribution unknown', null === $r['absorbed'] && 'OBSERVE' === $r['verdict'] );

$below = cc_commodity_classify( array( 'impressions' => 999, 'clicks' => 0, 'position' => 3, 'intent_family' => 'research' ) );
$above = cc_commodity_classify( array( 'impressions' => 1000, 'clicks' => 0, 'position' => 3, 'intent_family' => 'research' ) );
check( 'one impression at 1000 never changes strategic action', $below['verdict'] === $above['verdict'] && null === $above['absorbed'] );
$missing = cc_commodity_classify( array( 'impressions' => 0, 'clicks' => 0, 'position' => 0 ) );
check( 'missing metrics have explicit data-review state', 'REVIEW_DATA' === $missing['verdict'] && null === $missing['expected_ctr'] );

// --- Full audit flow on a seeded warehouse ---
@mkdir( __DIR__ . DIRECTORY_SEPARATOR . '.tmp-commodity', 0777, true );
$db_path = cc_wh_db_path();
if ( file_exists( $db_path ) ) { unlink( $db_path ); }
$db = cc_wh_open( false );
if ( is_array( $db ) ) { echo 'FAIL open: ' . json_encode( $db ) . "\n"; exit( 1 ); }
$ins = $db->prepare( 'INSERT INTO gsc_daily (date,page,query,clicks,impressions,position) VALUES (?,?,?,?,?,?)' );
$seed = function ( $page, $clicks, $imp, $pos ) use ( $ins ) {
	$d = gmdate( 'Y-m-d', time() - 5 * 86400 );
	$ins->bindValue( 1, $d, SQLITE3_TEXT );
	$ins->bindValue( 2, $page, SQLITE3_TEXT );
	$ins->bindValue( 3, 'q-' . md5( $page ), SQLITE3_TEXT );
	$ins->bindValue( 4, $clicks, SQLITE3_INTEGER );
	$ins->bindValue( 5, $imp, SQLITE3_INTEGER );
	$ins->bindValue( 6, $pos, SQLITE3_FLOAT );
	$ins->execute();
	$ins->reset();
};
$seed( 'https://commodity-test.example/service-iv/', 10, 2000, 3.0 );        // action -> INVEST
$seed( 'https://commodity-test.example/absorbed-guide/', 1, 8000, 2.0 );     // absorbed big -> CITE_PLAY (no info gain)
$seed( 'https://commodity-test.example/rich-absorbed/', 1, 4000, 2.0 );      // absorbed big + info gain -> CITE_PLAY (no fix line)
$seed( 'https://commodity-test.example/healthy-post/', 120, 3000, 5.0 );     // research healthy -> BRIDGE
$seed( 'https://commodity-test.example/tiny-dead/', 0, 600, 9.0 );           // absorbed small -> STOP
$seed( 'https://commodity-test.example/ghost-service/', 1, 3000, 2.0 );      // unresolvable URL -> REVIEW, never STOP
$db->exec( "INSERT INTO sync_log (date, rows, fetched_at, complete) VALUES ('" . gmdate( 'Y-m-d', time() - 5 * 86400 ) . "', 5, 1, 1)" );
$db->close();

$a = cc_wh_tool_commodity_audit( array( 'days' => 28 ) );
check( 'audit no error', ! isset( $a['error'] ), json_encode( $a ) );
$by_page = array();
foreach ( $a['pages'] as $p ) { $by_page[ $p['page'] ] = $p; }
check( 'service receives CTR review', 'REVIEW_CTR' === $by_page['https://commodity-test.example/service-iv/']['verdict'] );
check( 'low CTR does not generate an unsupported rewrite prescription', 'REVIEW_CTR' === $by_page['https://commodity-test.example/absorbed-guide/']['verdict'] && ! isset( $by_page['https://commodity-test.example/absorbed-guide/']['fix'] ), json_encode( $by_page['https://commodity-test.example/absorbed-guide/'] ) );
check( 'markup does not change strategic eligibility', 'REVIEW_CTR' === $by_page['https://commodity-test.example/rich-absorbed/']['verdict'] && ! isset( $by_page['https://commodity-test.example/rich-absorbed/']['fix'] ) );
check( 'healthy activity is observed', 'OBSERVE' === $by_page['https://commodity-test.example/healthy-post/']['verdict'] );
check( 'small low-CTR page is reviewed rather than stopped', 'REVIEW_CTR' === $by_page['https://commodity-test.example/tiny-dead/']['verdict'] );
check( 'summary counts add up', 4 === $a['summary']['REVIEW_CTR'] && 1 === $a['summary']['OBSERVE'] && 1 === $a['summary']['REVIEW'], json_encode( $a['summary'] ) );
check( 'unresolvable URL = REVIEW with note (never blind STOP)', 'REVIEW' === $by_page['https://commodity-test.example/ghost-service/']['verdict'] && isset( $by_page['https://commodity-test.example/ghost-service/']['note'] ) && 1 === $a['summary']['REVIEW'], json_encode( $by_page['https://commodity-test.example/ghost-service/'] ) );

// --- AEO snapshot: store, then delta vs a seeded previous day ---
$s1 = cc_wh_tool_aeo_snapshot( array() );
check( 'snapshot no error', ! isset( $s1['error'] ), json_encode( $s1 ) );
check( 'snapshot has low CTR observations with unknown cause', isset( $s1['snapshot']['low_ctr_candidates_28d']['queries'] ) && 'unknown' === $s1['snapshot']['ai_attribution'], json_encode( $s1 ) );
check( 'snapshot unwraps llm_crawls envelope', isset( $s1['snapshot']['llm_crawls_7d']['total'] ) && 42 === $s1['snapshot']['llm_crawls_7d']['total'], json_encode( $s1['snapshot']['llm_crawls_7d'] ) );
check( 'no vs_previous on first run', ! isset( $s1['vs_previous'] ) );

$db = cc_wh_open( false );
$db->exec( "INSERT OR REPLACE INTO aeo_snapshots (snapshot_date, data) VALUES ('" . gmdate( 'Y-m-d', time() - 7 * 86400 ) . "', '" . json_encode( array( 'measurement_version' => 'observations-2', 'low_ctr_candidates_28d' => array( 'queries' => 1, 'impressions' => 1000 ) ) ) . "')" );
$db->close();
$s2 = cc_wh_tool_aeo_snapshot( array() );
check( 'compatible low CTR observations compare', isset( $s2['vs_previous']['low_ctr_queries_delta'] ), json_encode( isset( $s2['vs_previous'] ) ? $s2['vs_previous'] : null ) );

/* ---------------------------------------------------------------------------
 * v0.71 redirect folding. A consolidated URL's impressions belong to the post
 * that replaced it. Before this, they sat on a separate "REVIEW / classify
 * manually" row and the destination looked smaller than it really was.
 * ------------------------------------------------------------------------ */
$pages = array(
	array( 'page' => 'https://x.test/live/',    'impressions' => 6501, 'clicks' => 6, 'position' => 18.4 ),
	array( 'page' => 'https://x.test/retired/', 'impressions' => 789,  'clicks' => 0, 'position' => 32.2 ),
	array( 'page' => 'https://x.test/other/',   'impressions' => 3000, 'clicks' => 2, 'position' => 14.0 ),
);
$signals = array(
	'https://x.test/live/'    => array( 'url' => 'https://x.test/live/', 'post_id' => 6356, 'found' => true, 'via' => 'direct' ),
	'https://x.test/retired/' => array( 'url' => 'https://x.test/retired/', 'post_id' => 6356, 'found' => true, 'via' => 'redirect', 'redirect_code' => 301 ),
	'https://x.test/other/'   => array( 'url' => 'https://x.test/other/', 'post_id' => 9465, 'found' => true, 'via' => 'direct' ),
);
$folded = cc_commodity_fold_redirects( $pages, $signals );
check( 'folding removes the redirected row', 2 === count( $folded ), json_encode( $folded ) );
$live = null;
foreach ( $folded as $f ) {
	if ( 'https://x.test/live/' === $f['page'] ) {
		$live = $f;
	}
}
check( 'destination gains the impressions', $live && 7290 === (int) $live['impressions'], json_encode( $live ) );
check( 'destination gains the clicks', $live && 6 === (int) $live['clicks'], json_encode( $live ) );
check( 'position re-averaged impression-weighted', $live && $live['position'] > 18.4 && $live['position'] < 20.0, json_encode( $live ) );
check( 'folded_from records provenance', $live && isset( $live['folded_from'][0]['url'] ) && 789 === (int) $live['folded_from'][0]['impressions'], json_encode( $live['folded_from'] ?? null ) );
check( 'untouched row survives intact', 3000 === (int) $folded[1]['impressions'] || 3000 === (int) $folded[0]['impressions'], json_encode( $folded ) );

// A redirect whose destination is NOT in the window must not vanish — dropping
// it would silently delete impressions from the report.
$orphan_pages = array(
	array( 'page' => 'https://x.test/retired/', 'impressions' => 500, 'clicks' => 1, 'position' => 30.0 ),
);
$orphan_sig = array(
	'https://x.test/retired/' => array( 'url' => 'https://x.test/retired/', 'post_id' => 6356, 'found' => true, 'via' => 'redirect', 'redirect_code' => 301 ),
);
$kept = cc_commodity_fold_redirects( $orphan_pages, $orphan_sig );
check( 'redirect with no destination row is kept, not dropped', 1 === count( $kept ) && 500 === (int) $kept[0]['impressions'], json_encode( $kept ) );

// No redirects at all: the function must be a pure pass-through.
$plain = cc_commodity_fold_redirects( $pages, array(
	'https://x.test/live/'    => array( 'post_id' => 1, 'found' => true, 'via' => 'direct' ),
	'https://x.test/retired/' => array( 'post_id' => 2, 'found' => true, 'via' => 'direct' ),
	'https://x.test/other/'   => array( 'post_id' => 3, 'found' => true, 'via' => 'direct' ),
) );
check( 'no redirects -> unchanged row count', 3 === count( $plain ), json_encode( $plain ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
