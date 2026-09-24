<?php
/**
 * keyword_targets: the per-keyword win loop.
 *
 * WHY THIS EXISTS. The plugin could audit a page (win_audit), measure an edit
 * (outcome engine) and synthesise a site (site_status) - but nothing persisted
 * the INTENT to win a specific query with a specific page. success_metrics die
 * with their edit; win_audit scores are point-in-time; every session re-derived
 * which queries matter and nobody tracked progress toward winning them. That is
 * the difference between having information and running a campaign.
 *
 * The loop this enables:
 *   1. set    - declare "query Q belongs to page P" (once).
 *   2. report - every later session gets the battle state per target: position
 *               trend, whether the RIGHT page is ranking, rival own-pages,
 *               absorbed/cited status, and a verdict with the next action.
 *   3. act    - fix what the verdict names (consolidate rivals, reroute
 *               anchors, win_audit vs real competitor URLs, reformat for
 *               citation), queue via the normal pending flow.
 *   4. loop   - the next report shows whether it moved. Nothing is forgotten
 *               between sessions because the registry lives in the warehouse.
 *
 * Verdicts are deliberately opinionated. "stalled at position 8 with 3 rival
 * own-pages" has a different next action than "stalled, absorbed by an AI
 * Overview", and conflating them wastes months - we measured both patterns on
 * live sites before writing this.
 *
 * Storage: kw_targets table in the local warehouse SQLite (per site). Reads
 * gsc_daily for all measurement. No REST round trips.
 *
 * @package CC_Assistant
 */

function cc_kw_ensure_table( $db ) {
	$db->exec(
		'CREATE TABLE IF NOT EXISTS kw_targets (
			query           TEXT PRIMARY KEY,
			owner_page      TEXT NOT NULL,
			target_position REAL NOT NULL DEFAULT 3.0,
			notes           TEXT NOT NULL DEFAULT "",
			created_at      TEXT NOT NULL
		)'
	);
}

/**
 * Measure one target against the warehouse.
 *
 * @param SQLite3 $db     Warehouse handle.
 * @param array   $t      Target row (query, owner_page, target_position, notes).
 * @param string  $start  Current-window start date.
 * @param string  $end    Current-window end date.
 * @param string  $pstart Prior-window start date.
 * @return array Report row.
 */
function cc_kw_measure_target( $db, $t, $start, $end, $pstart, $redirects = array() ) {
	$owner_key = cc_ss_page_key( $t['owner_page'] );
	if ( isset( $redirects[ $owner_key ] ) ) {
		// The registered owner itself now 301s: judge the destination.
		$owner_key = $redirects[ $owner_key ];
	}

	// Every page ranking for this query, both windows, canonicalised.
	$stmt = $db->prepare(
		'SELECT page,
		        SUM(CASE WHEN date >= :s THEN impressions ELSE 0 END) imp_now,
		        SUM(CASE WHEN date >= :s THEN clicks ELSE 0 END) ck_now,
		        SUM(CASE WHEN date >= :s THEN position*impressions ELSE 0 END) posw_now,
		        SUM(CASE WHEN date <  :s THEN impressions ELSE 0 END) imp_prior,
		        SUM(CASE WHEN date <  :s THEN position*impressions ELSE 0 END) posw_prior
		 FROM gsc_daily WHERE date BETWEEN :p AND :e AND query = :q
		 GROUP BY page'
	);
	$stmt->bindValue( ':s', $start, SQLITE3_TEXT );
	$stmt->bindValue( ':p', $pstart, SQLITE3_TEXT );
	$stmt->bindValue( ':e', $end, SQLITE3_TEXT );
	$stmt->bindValue( ':q', $t['query'], SQLITE3_TEXT );
	$res = $stmt->execute();

	$pages   = array();
	$variant = array( 'imp_now' => 0, 'ck_now' => 0, 'posw_now' => 0.0, 'urls' => array() );
	$folded  = array();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$k = cc_ss_page_key( $r['page'] );
		// v0.75.3: a retired URL that 301s keeps reporting in Search Console
		// for months. Measured: /blog/iv-for-dehydration (301 -> the root URL
		// since 2026-07-27) showed as an own rival at 6.9 beating the owner at
		// 7.1 and produced a false outranked_internally. The redirect's rows
		// belong to the destination, exactly as commodity_audit folds them.
		if ( isset( $redirects[ $k ] ) && $redirects[ $k ] !== $k ) {
			if ( ! isset( $folded[ $k ] ) ) {
				$folded[ $k ] = array( 'url' => $k, 'into' => $redirects[ $k ], 'impressions' => 0, 'clicks' => 0 );
			}
			$folded[ $k ]['impressions'] += (int) $r['imp_now'];
			$folded[ $k ]['clicks']      += (int) $r['ck_now'];
			$k = $redirects[ $k ];
		}
		// v0.75.2: a tracking variant of the OWNER (?utm_source=gmb is the URL
		// the Business Profile links to) is reported by Search Console as its
		// own page with its own position, usually from a different surface
		// (knowledge panel). Folding it into the owner dragged the owner from
		// 1.5 to 2.2 on a brand query and produced a false outranked_internally.
		// Its clicks belong to the owner; its position must not weigh in.
		if ( $k === $owner_key && preg_match( '/[?&]utm_/i', (string) $r['page'] ) ) {
			$variant['imp_now']  += (int) $r['imp_now'];
			$variant['ck_now']   += (int) $r['ck_now'];
			$variant['posw_now'] += (float) $r['posw_now'];
			$variant['urls'][ (string) $r['page'] ] = true;
			continue;
		}
		if ( ! isset( $pages[ $k ] ) ) {
			$pages[ $k ] = array( 'imp_now' => 0, 'ck_now' => 0, 'posw_now' => 0.0, 'imp_prior' => 0, 'posw_prior' => 0.0 );
		}
		$pages[ $k ]['imp_now']    += (int) $r['imp_now'];
		$pages[ $k ]['ck_now']     += (int) $r['ck_now'];
		$pages[ $k ]['posw_now']   += (float) $r['posw_now'];
		$pages[ $k ]['imp_prior']  += (int) $r['imp_prior'];
		$pages[ $k ]['posw_prior'] += (float) $r['posw_prior'];
	}

	$owner   = isset( $pages[ $owner_key ] ) ? $pages[ $owner_key ] : null;
	$pos_now = ( $owner && $owner['imp_now'] > 0 ) ? round( $owner['posw_now'] / $owner['imp_now'], 1 ) : null;
	$pos_pri = ( $owner && $owner['imp_prior'] > 0 ) ? round( $owner['posw_prior'] / $owner['imp_prior'], 1 ) : null;

	// Rivals: OTHER own pages with impressions on this query now.
	$rivals = array();
	$best_rival = null;
	foreach ( $pages as $k => $p ) {
		if ( $k === $owner_key || $p['imp_now'] <= 0 ) {
			continue;
		}
		$rp = round( $p['posw_now'] / $p['imp_now'], 1 );
		$rivals[] = array( 'page' => $k, 'impressions' => $p['imp_now'], 'clicks' => $p['ck_now'], 'position' => $rp );
		// A rival can only flip the verdict on a real sample. /book-appointment
		// at position 1.0 on ONE impression is Google testing a sitelink, not
		// an own page outranking the owner; it still appears in own_rivals.
		if ( $p['imp_now'] >= 10 && ( null === $best_rival || $rp < $best_rival['position'] ) ) {
			$best_rival = array( 'page' => $k, 'position' => $rp );
		}
	}
	usort( $rivals, function ( $a, $b ) { return $a['position'] <=> $b['position']; } );

	// Absorption: owner ranks in the top 20 but earns under a quarter of the
	// positional CTR expectation on real volume - an AI answer is eating it.
	$absorbed = false;
	if ( $owner && null !== $pos_now && $pos_now <= 20 && $owner['imp_now'] >= 100 ) {
		$ctr = $owner['ck_now'] / max( 1, $owner['imp_now'] );
		$absorbed = $ctr < cc_expected_ctr( $pos_now ) * 0.25;
	}
	$is_fanout = cc_ss_is_fanout( $t['query'] );

	// ----- Verdict + next action -----
	$target_pos = (float) $t['target_position'];
	if ( null === $pos_now ) {
		if ( ! empty( $rivals ) ) {
			$verdict = 'hijacked';
			$action  = sprintf(
				'The nominated owner has no observed impressions, while %d other URLs do (best: %s at %.1f). Inspect their reader tasks; this does not establish that Google chose the wrong page or justify merging.',
				count( $rivals ),
				$best_rival['page'],
				$best_rival['position']
			);
		} else {
			$verdict = 'not_ranking';
			$action  = 'No matching site observations in this window. Demand and coverage are unmeasured, not absent. Use plan_blog_content to explore supported niche needs without requiring GSC impressions.';
		}
	} elseif ( $owner['imp_now'] < 20 ) {
		// v0.74.0: a 2-impression sample "moving 35 positions" is noise, and
		// reporting it as GAINING sends the operator to replicate luck. Refuse
		// to trend below a sample floor; the target stays registered.
		$verdict = 'low_data';
		$action  = sprintf( 'Only %d impressions on the nominated owner; this sample is insufficient to judge a trend. Inspect reader relevance and other demand evidence; do not rule out new niche content.', $owner['imp_now'] );
	} elseif ( null !== $best_rival && $best_rival['position'] < $pos_now ) {
		// The owner ranks, but ANOTHER own page ranks better. This is the
		// parent-outranks-child pattern (pillar at 7.3, service page at 19.5)
		// and it is the most fixable verdict on the list: it is an internal
		// authority-routing problem, not a competitive one.
		$verdict = 'outranked_internally';
		$action  = sprintf(
			'Another own page %s has observed position %.1f, ahead of the nominated owner at %.1f. Compare reader tasks and relevant query segments before deciding whether any content or link change is useful.',
			$best_rival['page'], $best_rival['position'], $pos_now
		);
	} elseif ( $absorbed ) {
		$verdict = 'low_ctr_review';
		$action  = 'CTR is below a heuristic expectation. Diagnose query mix, demand, technical state and observed search presentation. AI attribution is not established; no automatic rewrite or stop decision.';
	} elseif ( $pos_now <= $target_pos ) {
		$verdict = 'won';
		$action  = 'Holding target position. Defend: keep freshness substantive, keep the inbound anchors, and do not cannibalise it with new pages on the same intent.';
	} else {
		$delta   = ( null !== $pos_pri ) ? round( $pos_pri - $pos_now, 1 ) : null; // positive = improved
		if ( null !== $delta && $delta >= 1.5 ) {
			$verdict = 'gaining';
			$action  = sprintf( 'Moved %.1f positions toward target in one window. Keep the current line; re-check next window before adding more changes (attribution first).', $delta );
		} elseif ( null !== $delta && $delta <= -1.5 ) {
			$verdict = 'losing';
			$action  = 'Position worsened. Check outcome_report for an edit landing just before the drop, then check whether a rival own-page or a new competitor took the slot.';
		} else {
			$verdict = 'stalled';
			if ( count( $rivals ) >= 2 ) {
				$action = sprintf(
					'Stalled with %d other observed URLs. Use content_decision to inspect distinct tasks and preservation needs; consolidation is only one alternative and requires evidence.',
					count( $rivals )
				);
			} else {
				$action = 'No clear movement in this sample. Investigate technical state, demand, reader usefulness and comparable pages through content_research with explicit comparable URLs. Missing overlap does not prove a competitive cause or a content gap.';
			}
		}
	}

	return array(
		'query'            => $t['query'],
		'owner_page'       => $owner_key,
		'target_position'  => $target_pos,
		'verdict'          => $verdict,
		'position'         => array( 'now' => $pos_now, 'prior' => $pos_pri ),
		'impressions_now'  => $owner ? $owner['imp_now'] : 0,
		'clicks_now'       => $owner ? $owner['ck_now'] : 0,
		'tracking_variant' => $variant['imp_now'] > 0 ? array(
			'impressions' => $variant['imp_now'],
			'clicks'      => $variant['ck_now'],
			'position'    => round( $variant['posw_now'] / $variant['imp_now'], 1 ),
			'urls'        => array_keys( $variant['urls'] ),
			'note'        => 'Owner URL with tracking params (e.g. the Business Profile link). Clicks are the owner\'s; position is from a different surface and is excluded from the owner position above.',
		) : null,
		'absorbed'         => null,
		'low_ctr_candidate' => $absorbed,
		'ai_attribution' => 'not_established',
		'is_fanout_query'  => $is_fanout,
		'own_rivals'       => array_slice( $rivals, 0, 6 ),
		'folded_redirects' => array_values( $folded ),
		'next_action'      => $action,
		'notes'            => (string) $t['notes'],
	);
}

/**
 * Resolve every page that ranks on any target query through the site's URL
 * resolver (/commodity/signals) and return a map of retired URL => the URL
 * that replaced it, both canonicalised with cc_ss_page_key().
 *
 * One call per 50 URLs. If the site cannot be reached the map stays empty and
 * the report says so in redirect_fold, rather than silently reporting
 * redirect residue as rivals.
 *
 * @param SQLite3 $db        Open warehouse.
 * @param array   $targets   kw_targets rows.
 * @param string  $pstart    Earliest date read (prior-window start).
 * @param string  $end       Latest date read.
 * @param array   $redirects Out: source key => destination key.
 * @return array {status: applied|unavailable|none, urls_checked, redirected, note?}
 */
function cc_kw_redirect_map( $db, $targets, $pstart, $end, &$redirects ) {
	$queries = array_values( array_unique( array_map( function ( $t ) { return (string) $t['query']; }, $targets ) ) );
	if ( empty( $queries ) ) {
		return array( 'status' => 'none', 'urls_checked' => 0, 'redirected' => 0 );
	}
	$marks = implode( ',', array_fill( 0, count( $queries ), '?' ) );
	$stmt  = $db->prepare( "SELECT DISTINCT page FROM gsc_daily WHERE date BETWEEN ? AND ? AND query IN ($marks)" );
	$stmt->bindValue( 1, $pstart, SQLITE3_TEXT );
	$stmt->bindValue( 2, $end, SQLITE3_TEXT );
	foreach ( $queries as $i => $q ) {
		$stmt->bindValue( $i + 3, $q, SQLITE3_TEXT );
	}
	$res  = $stmt->execute();
	$keys = array();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$keys[ cc_ss_page_key( $r['page'] ) ] = true;
	}
	$keys = array_keys( $keys );
	if ( empty( $keys ) ) {
		return array( 'status' => 'none', 'urls_checked' => 0, 'redirected' => 0 );
	}

	$by_url = array();
	foreach ( array_chunk( $keys, 50 ) as $chunk ) {
		$resp = wp_rest_call( '/commodity/signals', 'POST', array( 'urls' => $chunk ) );
		if ( ! is_array( $resp ) || isset( $resp['error'] ) ) {
			return array(
				'status'       => 'unavailable',
				'urls_checked' => 0,
				'redirected'   => 0,
				'note'         => 'The site could not resolve URLs (/commodity/signals), so retired URLs that 301 may still appear as own rivals. Treat any rival that is a known redirect as the owner.',
			);
		}
		foreach ( ( isset( $resp['rows'] ) && is_array( $resp['rows'] ) ? $resp['rows'] : array() ) as $row ) {
			$by_url[ cc_ss_page_key( (string) $row['url'] ) ] = $row;
		}
	}

	// Destination per post id, from rows that serve content directly.
	$dest_by_pid = array();
	foreach ( $by_url as $k => $row ) {
		if ( ! empty( $row['found'] ) && (int) $row['post_id'] > 0 && 'redirect' !== (string) ( $row['via'] ?? '' ) ) {
			$pid = (int) $row['post_id'];
			if ( ! isset( $dest_by_pid[ $pid ] ) ) {
				$dest_by_pid[ $pid ] = $k;
			}
		}
	}
	foreach ( $by_url as $k => $row ) {
		if ( 'redirect' !== (string) ( $row['via'] ?? '' ) ) {
			continue;
		}
		$dest = '';
		if ( ! empty( $row['resolved_url'] ) ) {
			$dest = cc_ss_page_key( (string) $row['resolved_url'] );
		} elseif ( (int) ( $row['post_id'] ?? 0 ) > 0 && isset( $dest_by_pid[ (int) $row['post_id'] ] ) ) {
			$dest = $dest_by_pid[ (int) $row['post_id'] ];
		}
		if ( '' !== $dest && $dest !== $k ) {
			$redirects[ $k ] = $dest;
		}
	}
	return array( 'status' => 'applied', 'urls_checked' => count( $keys ), 'redirected' => count( $redirects ) );
}

/**
 * keyword_targets tool entry point.
 *
 * @param array $arguments action (report|set|remove|list), query, owner_page,
 *                         target_position, notes, days.
 * @return array
 */
function cc_wh_tool_keyword_targets( $arguments ) {
	$action = isset( $arguments['action'] ) ? strtolower( (string) $arguments['action'] ) : 'report';

	$db = cc_wh_open( false );
	if ( is_array( $db ) ) {
		return $db;
	}
	cc_kw_ensure_table( $db );

	if ( 'set' === $action ) {
		$q = isset( $arguments['query'] ) ? trim( strtolower( (string) $arguments['query'] ) ) : '';
		$p = isset( $arguments['owner_page'] ) ? trim( (string) $arguments['owner_page'] ) : '';
		if ( '' === $q || '' === $p ) {
			$db->close();
			return array( 'error' => 'invalid_args', 'message' => 'set requires query and owner_page.' );
		}
		$stmt = $db->prepare(
			'INSERT INTO kw_targets (query, owner_page, target_position, notes, created_at)
			 VALUES (:q, :p, :t, :n, :c)
			 ON CONFLICT(query) DO UPDATE SET owner_page = :p, target_position = :t, notes = :n'
		);
		$stmt->bindValue( ':q', $q, SQLITE3_TEXT );
		$stmt->bindValue( ':p', cc_ss_page_key( $p ), SQLITE3_TEXT );
		$stmt->bindValue( ':t', isset( $arguments['target_position'] ) ? (float) $arguments['target_position'] : 3.0, SQLITE3_FLOAT );
		$stmt->bindValue( ':n', isset( $arguments['notes'] ) ? (string) $arguments['notes'] : '', SQLITE3_TEXT );
		$stmt->bindValue( ':c', gmdate( 'Y-m-d H:i:s' ), SQLITE3_TEXT );
		$stmt->execute();
		$db->close();
		return array( 'ok' => true, 'action' => 'set', 'query' => $q, 'hint' => 'Run keyword_targets (report) to get the battle state for every target.' );
	}

	if ( 'remove' === $action ) {
		$q = isset( $arguments['query'] ) ? trim( strtolower( (string) $arguments['query'] ) ) : '';
		$stmt = $db->prepare( 'DELETE FROM kw_targets WHERE query = :q' );
		$stmt->bindValue( ':q', $q, SQLITE3_TEXT );
		$stmt->execute();
		$db->close();
		return array( 'ok' => true, 'action' => 'remove', 'query' => $q );
	}

	// list + report both need the rows.
	$targets = array();
	$res     = $db->query( 'SELECT * FROM kw_targets ORDER BY created_at ASC' );
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$targets[] = $r;
	}

	if ( 'list' === $action ) {
		$db->close();
		return array( 'count' => count( $targets ), 'targets' => $targets );
	}

	// ----- report -----
	if ( empty( $targets ) ) {
		$db->close();
		return array(
			'count'   => 0,
			'targets' => array(),
			'hint'    => 'No keyword targets set. Pick the queries this site must WIN (from site_status self_competition evidence, gsc_opportunities, or the operator\'s own list), then keyword_targets {action:"set", query, owner_page}. From then on every session gets the battle state.',
		);
	}

	$days   = isset( $arguments['days'] ) ? max( 7, min( 90, (int) $arguments['days'] ) ) : 28;
	$latest = $db->querySingle( 'SELECT MAX(date) FROM gsc_daily' );
	if ( ! $latest ) {
		$db->close();
		return array( 'error' => 'no_data', 'message' => 'Warehouse is empty. Run gsc_warehouse_sync first.' );
	}
	$end    = $latest;
	$start  = cc_wh_date_sub( $end, $days - 1 );
	$pstart = cc_wh_date_sub( $start, $days );

	$redirects     = array();
	$redirect_fold = cc_kw_redirect_map( $db, $targets, $pstart, $end, $redirects );

	$rows    = array();
	$summary = array();
	foreach ( $targets as $t ) {
		$row = cc_kw_measure_target( $db, $t, $start, $end, $pstart, $redirects );
		$summary[ $row['verdict'] ] = ( $summary[ $row['verdict'] ] ?? 0 ) + 1;
		$rows[] = $row;
	}
	$db->close();

	// Worst news first: the rows demanding action lead.
	$order = array( 'hijacked' => 0, 'outranked_internally' => 1, 'losing' => 2, 'stalled' => 3, 'not_ranking' => 4, 'low_ctr_review' => 5, 'low_data' => 6, 'gaining' => 7, 'won' => 8 );
	usort( $rows, function ( $a, $b ) use ( $order ) {
		return ( $order[ $a['verdict'] ] ?? 9 ) <=> ( $order[ $b['verdict'] ] ?? 9 );
	} );

	return array(
		'window'        => array( 'days' => $days, 'start' => $start, 'end' => $end ),
		'summary'       => $summary,
		'redirect_fold' => $redirect_fold,
		'targets'       => $rows,
		'loop'    => 'set targets once -> report each session -> act on next_action via the pending queue -> the next report shows whether it moved. Re-run win_audit with real competitor URLs (Ubersuggest MCP serp_analysis) on any target stalled without self-competition.',
	);
}
