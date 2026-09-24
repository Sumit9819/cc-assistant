<?php
/**
 * keyword_targets test: the per-keyword win loop against a fixture warehouse.
 *
 * Pins the verdict logic, because each verdict maps to a different next
 * action and a wrong verdict wastes a month:
 *   won / gaining / stalled(with rivals) / stalled(no rivals) / losing /
 *   hijacked / absorbed / not_ranking
 *
 * Run: php -d extension=sqlite3 tests/keyword-targets-test.php
 */
error_reporting( E_ALL );
putenv( 'CC_WAREHOUSE_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . '.tmp-kw' );
putenv( 'CC_WP_URL=https://kw-test.example' );

function wp_rest_call( $endpoint, $method = 'GET', $body = null, $params = array() ) {
	if ( '/commodity/signals' === $endpoint ) {
		// Resolver fixture: /old-iv/ 301s to /iv/ (same post 77); everything
		// else is a plain page of its own.
		$rows = array();
		foreach ( (array) $body['urls'] as $u ) {
			if ( false !== strpos( $u, '/old-iv' ) ) {
				$rows[] = array( 'url' => $u, 'found' => true, 'post_id' => 77, 'via' => 'redirect', 'redirect_code' => 301, 'resolved_url' => 'https://kw-test.example/iv/' );
			} elseif ( false !== strpos( $u, '/iv' ) ) {
				$rows[] = array( 'url' => $u, 'found' => true, 'post_id' => 77, 'via' => 'direct' );
			} else {
				$rows[] = array( 'url' => $u, 'found' => true, 'post_id' => crc32( $u ) % 100000 + 1000, 'via' => 'direct' );
			}
		}
		return array( 'rows' => $rows, 'count' => count( $rows ) );
	}
	return array( 'error' => 'http_error', 'status' => 404, 'body' => '{"code":"rest_no_route"}' );
}

require dirname( __DIR__ ) . '/bin/warehouse.php';
require dirname( __DIR__ ) . '/bin/site-status.php';
require dirname( __DIR__ ) . '/bin/keyword-targets.php';

$fails = 0;
function check( $label, $cond, $detail = '' ) {
	global $fails;
	if ( $cond ) {
		echo "PASS  $label\n";
	} else {
		$fails++;
		echo "FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n";
	}
}

@mkdir( __DIR__ . '/.tmp-kw', 0777, true );
$db = cc_wh_open( false );
if ( is_array( $db ) ) {
	echo "SKIP: warehouse unavailable (" . json_encode( $db ) . ")\n";
	exit( 0 );
}
$db->exec( 'DELETE FROM gsc_daily' );
$db->exec( 'DROP TABLE IF EXISTS kw_targets' );

/* Fixture: 28-day "now" = 2026-08-01..08-28, "prior" = 07-04..07-31.
 * One row per (window, page, query) with a whole-window position. */
$B = 'https://kw-test.example/';
function seed( $db, $win, $page, $q, $imp, $ck, $pos ) {
	$date = ( 'now' === $win ) ? '2026-08-15' : '2026-07-15';
	$stmt = $db->prepare( 'INSERT INTO gsc_daily (date,page,query,clicks,impressions,position) VALUES (:d,:p,:q,:c,:i,:o)' );
	$stmt->bindValue( ':d', $date, SQLITE3_TEXT );
	$stmt->bindValue( ':p', $page, SQLITE3_TEXT );
	$stmt->bindValue( ':q', $q, SQLITE3_TEXT );
	$stmt->bindValue( ':c', $ck, SQLITE3_INTEGER );
	$stmt->bindValue( ':i', $imp, SQLITE3_INTEGER );
	$stmt->bindValue( ':o', $pos, SQLITE3_FLOAT );
	$stmt->execute();
}
// won: owner at 2.1
seed( $db, 'now',   $B . 'won/',     'won query',     500, 60, 2.1 );
seed( $db, 'prior', $B . 'won/',     'won query',     480, 55, 2.4 );
// gaining: 9.0 -> 6.0
seed( $db, 'now',   $B . 'gain/',    'gain query',    300, 6, 6.0 );
seed( $db, 'prior', $B . 'gain/',    'gain query',    280, 3, 9.0 );
// losing: 5.0 -> 9.5
seed( $db, 'now',   $B . 'lose/',    'lose query',    300, 4, 9.5 );
seed( $db, 'prior', $B . 'lose/',    'lose query',    320, 12, 5.0 );
// stalled WITH rivals: owner 8.2 flat, two other own pages
seed( $db, 'now',   $B . 'own/',     'split query',   200, 2, 8.2 );
seed( $db, 'prior', $B . 'own/',     'split query',   210, 2, 8.0 );
seed( $db, 'now',   $B . 'rival-a/', 'split query',   150, 0, 14.0 );
seed( $db, 'now',   $B . 'rival-b/', 'split query',   90,  0, 22.0 );
// stalled WITHOUT rivals: owner 7.9 flat, alone
seed( $db, 'now',   $B . 'alone/',   'alone query',   200, 3, 7.9 );
seed( $db, 'prior', $B . 'alone/',   'alone query',   200, 3, 8.1 );
// hijacked: owner has NO rows, another own page ranks
seed( $db, 'now',   $B . 'wrong/',   'hijack query',  400, 5, 6.5 );
// absorbed: position 3, 1000 imp, 1 click (expected CTR ~9% -> 90 clicks)
seed( $db, 'now',   $B . 'absorb/',  'absorbed query', 1000, 1, 3.0 );
seed( $db, 'prior', $B . 'absorb/',  'absorbed query', 900,  1, 3.1 );
// low_data: owner "moves" 30 positions on 3 impressions - must NOT be gaining
seed( $db, 'now',   $B . 'thin/',    'thin query',    3,   0, 9.0 );
seed( $db, 'prior', $B . 'thin/',    'thin query',    2,   0, 40.0 );
// outranked_internally: owner ranks 19.5, its own parent ranks 7.3
seed( $db, 'now',   $B . 'child/',   'parent query',  60,  0, 19.5 );
seed( $db, 'prior', $B . 'child/',   'parent query',  55,  0, 19.0 );
seed( $db, 'now',   $B . 'parent/',  'parent query',  120, 1, 7.3 );
// sitelink noise: owner 6.0 on 300 imp, a rival at 1.0 on ONE impression must NOT flip it
seed( $db, 'now',   $B . 'steady/',  'sitelink query', 300, 9, 6.0 );
seed( $db, 'prior', $B . 'steady/',  'sitelink query', 300, 9, 6.2 );
seed( $db, 'now',   $B . 'book/',    'sitelink query', 1,   0, 1.0 );
// tracking variant: owner at 1.5, its ?utm_source=gmb variant at 9.0 (a
// different surface). The variant must NOT drag the owner to ~5; it is
// reported separately and its clicks still count for the owner overall.
seed( $db, 'now',   $B . 'fold/',              'fold query', 100, 10, 1.5 );
seed( $db, 'now',   $B . 'fold/?utm_source=x', 'fold query', 100, 2, 9.0 );
// redirect residue: the owner's OLD URL still reports in GSC at a better
// position than the live URL. Without folding this reads as the owner being
// outranked by its own retired address.
seed( $db, 'now',   $B . 'iv/',     'iv query', 300,  20,  7.1 );
seed( $db, 'prior', $B . 'iv/',     'iv query', 300,  20,  7.5 );
seed( $db, 'now',   $B . 'old-iv/', 'iv query', 1700, 100, 6.9 );
$db->close();

/* ---- set / list ---- */
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'Won Query', 'owner_page' => $B . 'won/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'gain query', 'owner_page' => $B . 'gain/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'lose query', 'owner_page' => $B . 'lose/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'split query', 'owner_page' => $B . 'own/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'alone query', 'owner_page' => $B . 'alone/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'hijack query', 'owner_page' => $B . 'right/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'absorbed query', 'owner_page' => $B . 'absorb/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'ghost query', 'owner_page' => $B . 'ghost/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'fold query', 'owner_page' => $B . 'fold/?utm_source=x' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'thin query', 'owner_page' => $B . 'thin/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'parent query', 'owner_page' => $B . 'child/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'sitelink query', 'owner_page' => $B . 'steady/' ) );
cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => 'iv query', 'owner_page' => $B . 'iv/' ) );

$l = cc_wh_tool_keyword_targets( array( 'action' => 'list' ) );
check( 'set stores 13 targets', 13 === (int) $l['count'], json_encode( $l['count'] ) );
$lc = array_column( $l['targets'], 'query' );
check( 'query is lower-cased on set', in_array( 'won query', $lc, true ) && ! in_array( 'Won Query', $lc, true ) );
$fold_owner = '';
foreach ( $l['targets'] as $t ) { if ( 'fold query' === $t['query'] ) { $fold_owner = $t['owner_page']; } }
check( 'owner_page is canonicalised on set', false === strpos( $fold_owner, 'utm_source' ), $fold_owner );

$bad = cc_wh_tool_keyword_targets( array( 'action' => 'set', 'query' => '', 'owner_page' => '' ) );
check( 'set refuses empty args', isset( $bad['error'] ) );

/* ---- report ---- */
$r = cc_wh_tool_keyword_targets( array( 'action' => 'report', 'days' => 28 ) );
$by = array();
foreach ( $r['targets'] as $row ) { $by[ $row['query'] ] = $row; }

check( 'won',         'won'         === $by['won query']['verdict'],      $by['won query']['verdict'] );
check( 'gaining',     'gaining'     === $by['gain query']['verdict'],     $by['gain query']['verdict'] );
check( 'losing',      'losing'      === $by['lose query']['verdict'],     $by['lose query']['verdict'] );
check( 'stalled',     'stalled'     === $by['split query']['verdict'],    $by['split query']['verdict'] );
check( 'stalled overlap discusses consolidation as one alternative', false !== stripos( $by['split query']['next_action'], 'consolidat' ), $by['split query']['next_action'] );
check( 'stalled lists 2 own rivals', 2 === count( $by['split query']['own_rivals'] ), json_encode( $by['split query']['own_rivals'] ) );
check( 'rivals sorted best position first', 14.0 === (float) $by['split query']['own_rivals'][0]['position'] );
check( 'stalled alone requests research rather than a guessed cause', false !== stripos( $by['alone query']['next_action'], 'content_research' ), $by['alone query']['next_action'] );
check( 'hijacked',    'hijacked'    === $by['hijack query']['verdict'],   $by['hijack query']['verdict'] );
check( 'hijacked names the page that actually ranks', false !== strpos( $by['hijack query']['next_action'], 'wrong' ), $by['hijack query']['next_action'] );
check( 'absorbed',    'low_ctr_review' === $by['absorbed query']['verdict'], $by['absorbed query']['verdict'] );
check( 'AI attribution remains unknown', null === $by['absorbed query']['absorbed'] );
check( 'low CTR calls for diagnosis', false !== stripos( $by['absorbed query']['next_action'], 'Diagnose' ), $by['absorbed query']['next_action'] );
check( 'not_ranking', 'not_ranking' === $by['ghost query']['verdict'],    $by['ghost query']['verdict'] );

check( 'low_data, not gaining, on a 3-impression sample',
	'low_data' === $by['thin query']['verdict'],
	$by['thin query']['verdict'] . ' - a 30-position "gain" on 3 impressions is noise' );
check( 'outranked_internally when own parent ranks above owner',
	'outranked_internally' === $by['parent query']['verdict'], $by['parent query']['verdict'] );
check( 'outranked_internally names the page that wins instead', false !== strpos( $by['parent query']['next_action'], 'parent' ), $by['parent query']['next_action'] );
check( 'outranked sorts before stalled', array_search( 'parent query', array_column( $r['targets'], 'query' ) ) < array_search( 'split query', array_column( $r['targets'], 'query' ) ) );

check( 'a 1-impression rival at position 1 does NOT flip the verdict',
	'stalled' === $by['sitelink query']['verdict'],
	$by['sitelink query']['verdict'] . ' - sitelink noise must not read as outranked_internally' );
check( 'but the noisy rival is still listed', 1 === count( $by['sitelink query']['own_rivals'] ) );

// Folding: the owner's two URL variants must combine (200 imp, 4 clicks) and
// the variant must NOT appear as a rival of itself.
check( 'owner position excludes the tracking variant (1.5, not ~5.2)', 1.5 === (float) $by['fold query']['position']['now'], json_encode( $by['fold query']['position'] ) );
check( 'owner impressions exclude the variant', 100 === (int) $by['fold query']['impressions_now'], json_encode( $by['fold query']['impressions_now'] ) );
check( 'tracking variant is reported separately', 100 === (int) $by['fold query']['tracking_variant']['impressions'] && 9.0 === (float) $by['fold query']['tracking_variant']['position'], json_encode( $by['fold query']['tracking_variant'] ) );
check( 'utm variant is not reported as a rival', 0 === count( $by['fold query']['own_rivals'] ), json_encode( $by['fold query']['own_rivals'] ) );
check( 'owner at 1.5 with target 3 is WON, not outranked', 'won' === $by['fold query']['verdict'], $by['fold query']['verdict'] );
check( 'redirect fold ran', 'applied' === $r['redirect_fold']['status'] && 1 === (int) $r['redirect_fold']['redirected'], json_encode( $r['redirect_fold'] ) );
check( 'a 301 source is NOT an own rival', 0 === count( $by['iv query']['own_rivals'] ), json_encode( $by['iv query']['own_rivals'] ) );
check( 'redirect rows fold into the owner (impressions)', 2000 === (int) $by['iv query']['impressions_now'], json_encode( $by['iv query']['impressions_now'] ) );
check( 'folded position is impression-weighted (6.9, not 7.1)', 6.9 === (float) $by['iv query']['position']['now'], json_encode( $by['iv query']['position'] ) );
check( 'fold is reported with provenance', 'https://kw-test.example/old-iv' === $by['iv query']['folded_redirects'][0]['url'] && 1700 === (int) $by['iv query']['folded_redirects'][0]['impressions'], json_encode( $by['iv query']['folded_redirects'] ) );
check( 'verdict is stalled (competitive), not outranked_internally', 'stalled' === $by['iv query']['verdict'], $by['iv query']['verdict'] );

// Ordering: worst news first.
check( 'hijacked sorts before won', array_search( 'hijack query', array_column( $r['targets'], 'query' ) ) < array_search( 'won query', array_column( $r['targets'], 'query' ) ) );
check( 'summary counts verdicts', 1 === (int) $r['summary']['hijacked'] && 2 === (int) $r['summary']['won'], json_encode( $r['summary'] ) );

/* ---- remove ---- */
cc_wh_tool_keyword_targets( array( 'action' => 'remove', 'query' => 'ghost query' ) );
$l2 = cc_wh_tool_keyword_targets( array( 'action' => 'list' ) );
check( 'remove deletes the target', 12 === (int) $l2['count'] );

/* ---- empty state teaches the loop ---- */
$db = cc_wh_open( false ); $db->exec( 'DELETE FROM kw_targets' ); $db->close();
$e = cc_wh_tool_keyword_targets( array() );
check( 'empty registry returns a seeding hint', isset( $e['hint'] ) && false !== strpos( $e['hint'], 'site_status' ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
