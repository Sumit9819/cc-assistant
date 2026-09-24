<?php
/**
 * Edit outcome tracking.
 *
 * Records every approved + applied pending change, then computes a before/after
 * comparison from the cached GSC data so the user can see what an edit moved.
 *
 * Pure SQL on demand — no external HTTP, no LLM. Reads from wp_cc_gsc_queries
 * and wp_cc_edits.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Edit_Outcomes {

	const BASELINE_DAYS = 14;
	const MEASURE_DAYS  = 14;
	const SETTLE_DAYS   = 3;   // GSC has 2-3 day lag; ignore the first few days post-edit
	const READY_DAYS    = 7;   // minimum elapsed days before we call an outcome measurable

	/**
	 * If the pending change has no success_metrics set, synthesize a default
	 * from the post's focus keyword (Rank Math / Yoast / AIOSEO) and the
	 * page's current weighted-average GSC position for that query. This
	 * makes the edit measurable against intent even when the operator
	 * forgot to pass target_query / target_position — the dashboard
	 * "What's Working" card no longer buckets it under no_metrics.
	 *
	 * Conservative defaults — never overwrites operator-provided metrics,
	 * never invents targets when GSC has no data, never extrapolates the
	 * target to an unreachable rank. The synthesized blob is tagged with
	 * source=auto so the UI can disambiguate from human-supplied metrics.
	 *
	 * Returns true if a default was written, false otherwise.
	 */
	public static function maybe_default_success_metrics( $pending_change_id, $post_id ) {
		global $wpdb;
		$pending_change_id = (int) $pending_change_id;
		$post_id           = (int) $post_id;
		if ( $pending_change_id < 1 || $post_id < 1 ) {
			return false;
		}

		// Bail if metrics already set — never overwrite operator intent.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT success_metrics FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d",
			$pending_change_id
		) );
		if ( ! empty( $existing ) ) {
			$decoded = json_decode( (string) $existing, true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				return false;
			}
		}

		$focus = self::focus_keyword_for_post( $post_id );
		if ( '' === $focus ) {
			return false;
		}

		$current_pos = self::current_weighted_position( $post_id, $focus );
		if ( null === $current_pos ) {
			// GSC has no impressions for this query at this URL. Still set
			// target_query so future GSC syncs can score it, but skip the
			// position target.
			$metrics = array(
				'source'           => 'auto',
				'target_query'     => $focus,
				'eval_window_days' => 21,
				'hypothesis'       => 'Auto-synthesized: focus keyword detected on the post but GSC had no impressions for this query at apply time. Score CTR only if the query starts ranking.',
			);
		} else {
			$target_pos = max( 1.0, round( $current_pos - 3.0, 1 ) );
			$metrics = array(
				'source'           => 'auto',
				'target_query'     => $focus,
				'target_position'  => $target_pos,
				'baseline_position'=> round( $current_pos, 1 ),
				'eval_window_days' => 21,
				'hypothesis'       => sprintf(
					'Auto-synthesized from focus keyword "%s". Baseline weighted-avg position %0.1f over the last 14 days; aiming for position %0.1f or better.',
					$focus,
					$current_pos,
					$target_pos
				),
			);
		}

		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'success_metrics' => wp_json_encode( $metrics ) ),
			array( 'id' => $pending_change_id ),
			array( '%s' ),
			array( '%d' )
		);
		return true;
	}

	/**
	 * Resolve the SEO focus keyword for a post by reading the postmeta keys
	 * each major SEO plugin uses. Returns the first non-empty value (lower-
	 * cased, trimmed). Empty string if none found.
	 */
	private static function focus_keyword_for_post( $post_id ) {
		// Rank Math: comma-separated list, primary is first.
		$rm = (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		if ( '' !== $rm ) {
			$parts = array_map( 'trim', explode( ',', $rm ) );
			$first = isset( $parts[0] ) ? mb_strtolower( $parts[0] ) : '';
			if ( '' !== $first ) {
				return $first;
			}
		}
		// Yoast.
		$yoast = (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		if ( '' !== $yoast ) {
			return mb_strtolower( trim( $yoast ) );
		}
		// AIOSEO 4.x stores keyphrases as JSON.
		$aioseo = (string) get_post_meta( $post_id, '_aioseo_keyphrases', true );
		if ( '' !== $aioseo ) {
			$decoded = json_decode( $aioseo, true );
			if ( is_array( $decoded ) && ! empty( $decoded['focus']['keyphrase'] ) ) {
				return mb_strtolower( trim( (string) $decoded['focus']['keyphrase'] ) );
			}
		}
		return '';
	}

	/**
	 * Weighted-average GSC position over the last 14 days for a single query
	 * on the post's canonical URL. Null if no impressions in that window.
	 * Mirrors the per-query weighting used in compute_outcome so the baseline
	 * is comparable to the post-edit measurement.
	 */
	private static function current_weighted_position( $post_id, $query ) {
		global $wpdb;
		$page = (string) get_permalink( $post_id );
		if ( '' === $page ) {
			return null;
		}
		$page_hash  = sha1( $page );
		// Same canonical-vs-exact hash trap as measure(); see the note there.
		require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
		$canon_hash = CC_Assistant_Query_Tagger::canonical_hash( $page );
		if ( '' === $canon_hash ) {
			$canon_hash = $page_hash;
		}
		$gsc_lag    = time() - 2 * DAY_IN_SECONDS;
		$end_date   = gmdate( 'Y-m-d', $gsc_lag );
		$start_date = gmdate( 'Y-m-d', strtotime( $end_date . ' -13 days' ) );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM(impressions) AS imp,
			        SUM(position * impressions) AS pos_weighted
			 FROM {$wpdb->prefix}cc_gsc_queries
			 WHERE (page_canonical_hash = %s OR page_hash = %s)
			   AND query = %s
			   AND date BETWEEN %s AND %s",
			$canon_hash,
			$page_hash,
			$query,
			$start_date,
			$end_date
		) );
		if ( ! $row || empty( $row->imp ) || (int) $row->imp <= 0 ) {
			return null;
		}
		return (float) $row->pos_weighted / (float) $row->imp;
	}

	public static function record_edit( $post_id, $pending_change_id, $change_type, $change_summary, $applied_by ) {
		global $wpdb;
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			return false;
		}
		$page = (string) get_permalink( $post_id );
		if ( ! $page ) {
			return false;
		}
		$wpdb->insert(
			$wpdb->prefix . 'cc_edits',
			array(
				'post_id'           => $post_id,
				'pending_change_id' => $pending_change_id ? (int) $pending_change_id : null,
				'change_type'       => substr( (string) $change_type, 0, 50 ),
				'change_summary'    => (string) $change_summary,
				'page_url'          => mb_substr( $page, 0, 500 ),
				'page_hash'         => sha1( $page ),
				'applied_at'        => current_time( 'mysql', true ), // GMT
				'applied_by'        => $applied_by ? (int) $applied_by : null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function list_recent( $limit = 25 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_edits';
		$limit = max( 1, min( 200, (int) $limit ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY applied_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Page hashes (sha1 of canonical URL) for posts that have had any
	 * cc_edits row applied within the last $days. Used by every advisor /
	 * recommendation source as a "recently optimized — stop flagging it"
	 * suppression filter, so the dashboard does not keep recommending
	 * pages we just touched while we wait for GSC + outcome data to settle.
	 *
	 * 14 days is the default window: Google needs ~3 days to recrawl, then
	 * ~10 days of post-edit data accumulates before outcomes can be scored
	 * (READY_DAYS = 7 + a few days slack). After 14 days, the recommendations
	 * source can flag the page again if it is still genuinely underperforming
	 * — outcome scoring will tell the operator whether the prior fix worked.
	 */
	public static function recent_page_hashes( $days = 14 ) {
		global $wpdb;
		$days   = max( 1, min( 90, (int) $days ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$rows   = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT page_hash FROM {$wpdb->prefix}cc_edits WHERE applied_at >= %s",
			$cutoff
		) );
		return array_values( array_filter( (array) $rows ) );
	}

	/**
	 * Post ids that have had any cc_edits row applied within the last $days.
	 * Same purpose as recent_page_hashes but keyed by post_id for callers
	 * that work with WP post objects rather than URL hashes.
	 */
	public static function recent_post_ids( $days = 14 ) {
		global $wpdb;
		$days   = max( 1, min( 90, (int) $days ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$rows   = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->prefix}cc_edits WHERE applied_at >= %s AND post_id IS NOT NULL",
			$cutoff
		) );
		return array_values( array_map( 'intval', array_filter( (array) $rows ) ) );
	}

	/**
	 * Last applied_at (GMT) for a single post. Used by drift detection in
	 * post_dossier — if the post's post_modified_gmt is meaningfully later
	 * than this timestamp, the post was edited outside the plugin and the
	 * model should re-read before proposing more changes.
	 */
	public static function last_applied_at_for_post( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			return null;
		}
		$row = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(applied_at) FROM {$wpdb->prefix}cc_edits WHERE post_id = %d",
			$post_id
		) );
		return $row ?: null;
	}

	/**
	 * Find posts modified outside the plugin since their last cc_edits row.
	 * Returns up to $limit rows, sorted by drift_seconds desc so the most
	 * recently drifted posts come first. The 120-second grace absorbs the
	 * apply-then-save_post sequence — apply_pending writes content which
	 * itself bumps post_modified_gmt, and the gap between the two is small
	 * but non-zero on slow hosts.
	 */
	public static function drifted_posts( $limit = 10 ) {
		global $wpdb;
		$limit  = max( 1, min( 50, (int) $limit ) );
		$grace  = 120;
		// Subquery: latest applied_at per post_id we've ever touched.
		// Outer: join WP posts, keep only those whose post_modified_gmt
		// exceeds the last applied_at by more than the grace window.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.post_id,
			        e.last_applied_at,
			        p.post_title,
			        p.post_modified_gmt,
			        TIMESTAMPDIFF(SECOND, e.last_applied_at, p.post_modified_gmt) AS drift_seconds
			 FROM (
			   SELECT post_id, MAX(applied_at) AS last_applied_at
			   FROM {$wpdb->prefix}cc_edits
			   GROUP BY post_id
			 ) e
			 INNER JOIN {$wpdb->posts} p ON p.ID = e.post_id
			 WHERE p.post_status = 'publish'
			   AND TIMESTAMPDIFF(SECOND, e.last_applied_at, p.post_modified_gmt) > %d
			 ORDER BY p.post_modified_gmt DESC
			 LIMIT %d",
			$grace,
			$limit
		) );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'post_id'           => (int) $r->post_id,
				'post_title'        => (string) $r->post_title,
				'last_applied_at'   => (string) $r->last_applied_at,
				'post_modified_gmt' => (string) $r->post_modified_gmt,
				'drift_seconds'     => (int) $r->drift_seconds,
			);
		}
		return $out;
	}

	/**
	 * Total count of drifted posts. Cheap aggregate for whoami's session_recap
	 * so the model knows "N posts changed outside the plugin" without paying
	 * for the full list.
	 */
	public static function drifted_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM (
			   SELECT post_id, MAX(applied_at) AS last_applied_at
			   FROM {$wpdb->prefix}cc_edits
			   GROUP BY post_id
			 ) e
			 INNER JOIN {$wpdb->posts} p ON p.ID = e.post_id
			 WHERE p.post_status = 'publish'
			   AND TIMESTAMPDIFF(SECOND, e.last_applied_at, p.post_modified_gmt) > %d",
			120
		) );
	}

	public static function get( $edit_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cc_edits WHERE id = %d", (int) $edit_id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Compute the outcome of a recorded edit.
	 *
	 * Compares two equal-length GSC windows around the edit date:
	 *   - baseline: BASELINE_DAYS ending the day before applied_at
	 *   - after:    SETTLE_DAYS after applied_at, lasting up to MEASURE_DAYS
	 *
	 * Returns per-query deltas plus an aggregate verdict. If the after-window
	 * has not yet accumulated READY_DAYS of GSC data, returns status=pending.
	 */
	public static function compute_outcome( $edit_id ) {
		global $wpdb;

		// Per-edit cache. compute_outcome runs a heavy GROUP BY on cc_gsc_queries
		// and was the N+1 hotspot when called from recent_with_status. Outcome is
		// deterministic until new GSC data arrives, so a 30-min cache is safe.
		// Bumped to v2 with the addition of target_score scoring against
		// success_metrics so older v1 cache entries get invalidated.
		//
		// Bucket the cache by 6-hour windows so a `pending` outcome's
		// "ready in N days" countdown can't show a stale value for up to 1h
		// after the wall clock crosses a measurable-day boundary. The GROUP BY
		// is still cheap; the bucket only forces a recompute on the boundary.
		$bucket    = (int) floor( time() / ( 6 * HOUR_IN_SECONDS ) );
		$cache_key = 'cc_outcome_v2_' . (int) $edit_id . '_' . $bucket;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$edit = self::get( $edit_id );
		if ( ! $edit ) {
			return new WP_Error( 'edit_not_found', 'Edit not found.' );
		}

		$applied_ts   = strtotime( $edit['applied_at'] . ' UTC' );
		$gsc_lag_end  = time() - 2 * DAY_IN_SECONDS;
		$days_elapsed = max( 0, (int) floor( ( $gsc_lag_end - $applied_ts ) / DAY_IN_SECONDS ) );

		$applied_date  = gmdate( 'Y-m-d', $applied_ts );
		$baseline_end  = gmdate( 'Y-m-d', strtotime( $applied_date . ' -1 day' ) );
		$baseline_start = gmdate( 'Y-m-d', strtotime( $baseline_end . ' -' . ( self::BASELINE_DAYS - 1 ) . ' days' ) );

		$after_start = gmdate( 'Y-m-d', strtotime( $applied_date . ' +' . self::SETTLE_DAYS . ' days' ) );
		$after_end_target = gmdate( 'Y-m-d', strtotime( $after_start . ' +' . ( self::MEASURE_DAYS - 1 ) . ' days' ) );
		$after_end_actual = gmdate( 'Y-m-d', $gsc_lag_end );
		if ( strtotime( $after_end_actual ) > strtotime( $after_end_target ) ) {
			$after_end_actual = $after_end_target;
		}

		$measurable_days = max( 0, (int) floor( ( strtotime( $after_end_actual ) - strtotime( $after_start ) ) / DAY_IN_SECONDS ) + 1 );

		if ( $measurable_days < self::READY_DAYS ) {
			$pending = array(
				'edit'             => self::edit_summary( $edit ),
				'status'           => 'pending',
				'days_elapsed'     => $days_elapsed,
				'measurable_days'  => $measurable_days,
				'ready_in_days'    => max( 0, self::READY_DAYS - $measurable_days ),
				'message'          => sprintf(
					'Too early to measure. %d day%s of post-edit GSC data so far; need %d.',
					$measurable_days,
					1 === $measurable_days ? '' : 's',
					self::READY_DAYS
				),
			);
			// Pending outcomes change as time passes — cache for 1h, not 30m.
			set_transient( $cache_key, $pending, HOUR_IN_SECONDS );
			return $pending;
		}

		$table = $wpdb->prefix . 'cc_gsc_queries';
		$page_hash = $edit['page_hash'];
		// v0.71.0: page_hash is sha1 of the exact permalink string captured at
		// apply time. Search Console reports its own spelling of the URL, so a
		// trailing slash, a www prefix, or percent-encoding made the join return
		// zero rows and the edit read as "No GSC data" forever. The ingest
		// already stores an indexed page_canonical_hash for exactly this — it
		// was simply never used here. Both are matched so rows written before
		// the canonical column was populated still resolve.
		require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
		$canon_hash = isset( $edit['page_url'] ) && '' !== (string) $edit['page_url']
			? CC_Assistant_Query_Tagger::canonical_hash( (string) $edit['page_url'] )
			: '';
		if ( '' === $canon_hash ) {
			$canon_hash = $page_hash;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query,
					SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS before_clicks,
					SUM(CASE WHEN date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS after_clicks,
					SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) AS before_impressions,
					SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) AS after_impressions,
					CASE WHEN SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) > 0
						THEN SUM(CASE WHEN date BETWEEN %s AND %s THEN position * impressions ELSE 0 END)
							 / SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END)
						ELSE NULL END AS before_position,
					CASE WHEN SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END) > 0
						THEN SUM(CASE WHEN date BETWEEN %s AND %s THEN position * impressions ELSE 0 END)
							 / SUM(CASE WHEN date BETWEEN %s AND %s THEN impressions ELSE 0 END)
						ELSE NULL END AS after_position
				FROM {$table}
				WHERE (page_canonical_hash = %s OR page_hash = %s) AND date BETWEEN %s AND %s
				GROUP BY query
				HAVING before_impressions > 0 OR after_impressions > 0",
				$baseline_start, $baseline_end,
				$after_start, $after_end_actual,
				$baseline_start, $baseline_end,
				$after_start, $after_end_actual,
				$baseline_start, $baseline_end,
				$baseline_start, $baseline_end,
				$baseline_start, $baseline_end,
				$after_start, $after_end_actual,
				$after_start, $after_end_actual,
				$after_start, $after_end_actual,
				$canon_hash,
				$page_hash,
				$baseline_start, $after_end_actual
			),
			ARRAY_A
		);

		$movers = array();
		$totals = array(
			'before_clicks'      => 0,
			'after_clicks'       => 0,
			'before_impressions' => 0,
			'after_impressions'  => 0,
		);

		// Normalize windows to per-day average so an asymmetric after-window doesn't bias the comparison.
		$baseline_days_actual = self::BASELINE_DAYS;
		$after_days_actual    = $measurable_days;
		$norm                 = $after_days_actual > 0 ? ( $baseline_days_actual / $after_days_actual ) : 1.0;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$before_clicks = (int) $r['before_clicks'];
				$after_clicks  = (int) $r['after_clicks'];
				$before_imp    = (int) $r['before_impressions'];
				$after_imp     = (int) $r['after_impressions'];
				$before_pos    = null !== $r['before_position'] ? (float) $r['before_position'] : null;
				$after_pos     = null !== $r['after_position'] ? (float) $r['after_position'] : null;

				$totals['before_clicks']      += $before_clicks;
				$totals['after_clicks']       += $after_clicks;
				$totals['before_impressions'] += $before_imp;
				$totals['after_impressions']  += $after_imp;

				$pos_delta = ( null !== $before_pos && null !== $after_pos ) ? ( $after_pos - $before_pos ) : null;
				$click_delta_norm = (int) round( $after_clicks * $norm ) - $before_clicks;
				$imp_delta_norm   = (int) round( $after_imp * $norm ) - $before_imp;

				$movers[] = array(
					'query'              => $r['query'],
					'before_position'    => null === $before_pos ? null : round( $before_pos, 2 ),
					'after_position'     => null === $after_pos ? null : round( $after_pos, 2 ),
					'position_delta'     => null === $pos_delta ? null : round( $pos_delta, 2 ),
					'before_clicks'      => $before_clicks,
					'after_clicks'       => $after_clicks,
					'click_delta_norm'   => $click_delta_norm,
					'before_impressions' => $before_imp,
					'after_impressions'  => $after_imp,
					'impression_delta_norm' => $imp_delta_norm,
				);
			}
		}

		usort(
			$movers,
			function ( $a, $b ) {
				$a_score = is_null( $a['position_delta'] ) ? 0 : -$a['position_delta'];
				$b_score = is_null( $b['position_delta'] ) ? 0 : -$b['position_delta'];
				return $b_score <=> $a_score;
			}
		);

		// Aggregate verdict
		$click_delta_norm = (int) round( $totals['after_clicks'] * $norm ) - $totals['before_clicks'];
		$imp_delta_norm   = (int) round( $totals['after_impressions'] * $norm ) - $totals['before_impressions'];

		// "flat" requires actual GSC signal to compare against. When the page
		// had effectively no impressions in EITHER window (GSC not connected,
		// not yet synced this URL, page too new to rank, or the URL was just
		// minted by a publish_draft apply), the older code returned verdict=
		// 'flat' which read as "edit did nothing" — but the truth was "we
		// can't tell." Distinguish that case so the dashboard surfaces the
		// real bottleneck (GSC coverage) instead of mislabeling intent.
		$has_signal = ( $totals['before_impressions'] + $totals['after_impressions'] ) >= 10;
		if ( ! $has_signal ) {
			$verdict = 'no_data';
		} else {
			$verdict = 'flat';
			if ( $click_delta_norm > 0 && $click_delta_norm > 0.1 * max( 1, $totals['before_clicks'] ) ) {
				$verdict = 'positive';
			} elseif ( $click_delta_norm < 0 && abs( $click_delta_norm ) > 0.1 * max( 1, $totals['before_clicks'] ) ) {
				$verdict = 'negative';
			}
		}

		// Score against the operator's stated intent (success_metrics on the
		// linked pending change). Returns a target_score block with hit/miss
		// per metric plus a meta-tag-bottleneck flag for the canonical case
		// where position improved but CTR did not (i.e., the body rewrite
		// landed but the title/description is still the limiter).
		$target_score = self::score_against_metrics( $edit, $movers, $totals, $norm );

		// v0.71.0: an after-window shorter than the baseline is scaled up by
		// $norm (14/7 = 2.0 at the earliest possible read). The arithmetic is
		// right, but it assumes clicks arrive uniformly across the window, and
		// nothing previously said so — a first read showed double the real
		// clicks and then visibly shrank as the window filled, which reads like
		// the tool is broken. Say it out loud instead.
		$is_provisional = $after_days_actual < $baseline_days_actual;
		$confidence     = array(
			'level'                => $is_provisional ? 'provisional' : 'full',
			'normalization_factor' => round( $norm, 2 ),
			'after_days'           => $after_days_actual,
			'baseline_days'        => $baseline_days_actual,
		);
		if ( $is_provisional ) {
			$confidence['caveat'] = sprintf(
				'PROVISIONAL: %d measured day%s scaled by %.2fx to compare against a %d-day baseline. Normalized figures assume an even daily spread and will move as the window fills. Re-read at %d days before acting on this verdict.',
				$after_days_actual,
				1 === $after_days_actual ? '' : 's',
				$norm,
				$baseline_days_actual,
				$baseline_days_actual
			);
		}

		$measured = array(
			'edit'    => self::edit_summary( $edit ),
			'status'  => 'measured',
			'verdict' => $verdict,
			'confidence' => $confidence,
			'windows' => array(
				'baseline' => array( 'start' => $baseline_start, 'end' => $baseline_end, 'days' => $baseline_days_actual ),
				'after'    => array( 'start' => $after_start, 'end' => $after_end_actual, 'days' => $after_days_actual ),
				'days_elapsed' => $days_elapsed,
			),
			'totals'  => array_merge(
				$totals,
				array(
					'click_delta_norm'      => $click_delta_norm,
					'impression_delta_norm' => $imp_delta_norm,
				)
			),
			'top_movers'  => array_slice( $movers, 0, 10 ),
			'top_decayed' => array_slice( array_reverse( $movers ), 0, 5 ),
			'query_count' => count( $movers ),
			'target_score' => $target_score,
		);
		// Cache measured outcome for 30 min. Invalidated implicitly by cache_key
		// versioning (cc_outcome_v2_*) — bump the prefix on schema changes.
		set_transient( $cache_key, $measured, 30 * MINUTE_IN_SECONDS );
		return $measured;
	}

	/**
	 * Pull success_metrics off the linked pending change and score the
	 * after-window measurements against the operator's stated intent.
	 *
	 * Returns one of:
	 *   - { has_metrics: false, message: 'No success_metrics set on this edit.' }
	 *   - { has_metrics: true, overall_hit: 'hit' | 'partial' | 'missed' | 'no_data',
	 *       target_query, measured_position, measured_ctr, position_hit, ctr_hit,
	 *       meta_tag_bottleneck, message }
	 *
	 * The meta_tag_bottleneck flag fires when position_hit=true but ctr_hit=false:
	 * the body rewrite moved the page where you wanted, but the snippet still
	 * isn't earning its clicks. That diagnoses "the body change worked, propose
	 * a meta title/description rewrite next."
	 */
	private static function score_against_metrics( $edit, $movers, $totals, $norm ) {
		global $wpdb;
		$pending_id = isset( $edit['pending_change_id'] ) ? (int) $edit['pending_change_id'] : 0;
		if ( $pending_id < 1 ) {
			return array(
				'has_metrics' => false,
				'message'     => 'No linked pending change — cannot read success_metrics.',
			);
		}
		$row = $wpdb->get_var( $wpdb->prepare(
			"SELECT success_metrics FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d",
			$pending_id
		) );
		if ( empty( $row ) ) {
			return array(
				'has_metrics' => false,
				'message'     => 'success_metrics was not set on this change. The verdict above is the only signal — set target_query / target_position / target_ctr on future changes so future outcomes can be scored against intent.',
			);
		}
		$metrics = json_decode( (string) $row, true );
		if ( ! is_array( $metrics ) || empty( $metrics ) ) {
			return array(
				'has_metrics' => false,
				'message'     => 'success_metrics blob exists but is empty or malformed.',
			);
		}

		$target_query    = isset( $metrics['target_query'] ) ? (string) $metrics['target_query'] : '';
		$target_position = isset( $metrics['target_position'] ) ? (float) $metrics['target_position'] : null;
		$target_ctr      = isset( $metrics['target_ctr'] ) ? (float) $metrics['target_ctr'] : null;

		// Find the row in $movers matching the target_query (case-insensitive).
		$matched = null;
		if ( '' !== $target_query ) {
			$tq_lc = mb_strtolower( $target_query );
			foreach ( (array) $movers as $m ) {
				if ( mb_strtolower( (string) $m['query'] ) === $tq_lc ) {
					$matched = $m;
					break;
				}
			}
		}

		if ( null === $matched ) {
			// Target query isn't in the after-window data. Could mean the page
			// dropped out of the SERP entirely, or the operator misspelled it,
			// or GSC just doesn't have that query for this URL in this window.
			return array(
				'has_metrics'    => true,
				'target_query'   => $target_query,
				'target_position' => $target_position,
				'target_ctr'     => $target_ctr,
				'overall_hit'    => 'no_data',
				'message'        => sprintf(
					'Target query "%s" did not appear in the post-edit window. Either the page dropped out of the SERP for this query, the query was misspelled, or there is not enough GSC data yet.',
					$target_query
				),
			);
		}

		$measured_position = isset( $matched['after_position'] ) ? $matched['after_position'] : null;
		$after_clicks      = isset( $matched['after_clicks'] ) ? (int) $matched['after_clicks'] : 0;
		$after_imp         = isset( $matched['after_impressions'] ) ? (int) $matched['after_impressions'] : 0;
		$measured_ctr      = $after_imp > 0 ? round( $after_clicks / $after_imp, 4 ) : null;
		$before_imp        = isset( $matched['before_impressions'] ) ? (int) $matched['before_impressions'] : 0;
		// Normalize impression growth to the same window length so an asymmetric
		// after-window does not bias the lift number.
		$after_imp_norm    = (int) round( $after_imp * $norm );
		$impression_growth = $before_imp > 0 ? ( ( $after_imp_norm - $before_imp ) / $before_imp ) : null;

		$position_hit   = null;
		$ctr_hit        = null;
		$impression_hit = null;
		$hits           = 0;
		$total_targets  = 0;

		if ( null !== $target_position && null !== $measured_position ) {
			$total_targets++;
			// Lower position is better. "Hit" means measured <= target.
			$position_hit = ( $measured_position <= $target_position );
			if ( $position_hit ) {
				$hits++;
			}
		}
		if ( null !== $target_ctr && null !== $measured_ctr ) {
			$total_targets++;
			// Higher CTR is better. "Hit" means measured >= target.
			$ctr_hit = ( $measured_ctr >= $target_ctr );
			if ( $ctr_hit ) {
				$hits++;
			}
		}
		// Impression growth is a bonus credit, not a target on its own. If the
		// page doubled its impressions for the target query but didn't move
		// rank, that's still genuine progress (more eyeballs on the same SERP
		// slot) and the verdict shouldn't read as "missed" just because
		// position didn't move. Counts as a hit when normalized after-window
		// impressions grew at least 25% over baseline.
		if ( null !== $impression_growth ) {
			$impression_hit = ( $impression_growth >= 0.25 );
			if ( $impression_hit ) {
				// Pre-increment $hits but ONLY if we already have at least one
				// real target to compare against — otherwise impression growth
				// alone could flip a no-targets outcome into "hit" which would
				// be misleading.
				if ( $total_targets > 0 ) {
					$hits++;
					$total_targets++;
				}
			}
		}

		$overall = 'no_data';
		if ( $total_targets > 0 ) {
			if ( $hits === $total_targets ) {
				$overall = 'hit';
			} elseif ( 0 === $hits ) {
				$overall = 'missed';
			} else {
				$overall = 'partial';
			}
		}

		// Meta-tag bottleneck: position landed but CTR did not. The body rewrite
		// did its job (page is where it should be); the snippet (title +
		// description) is now the limiter. Suggest a draft_update_seo_meta.
		$meta_tag_bottleneck = ( true === $position_hit && false === $ctr_hit );

		// Build a human-readable message that names the gap explicitly.
		$message_parts = array();
		if ( null !== $position_hit ) {
			$message_parts[] = $position_hit
				? sprintf( 'Position target hit (%.1f vs %.1f).', (float) $measured_position, $target_position )
				: sprintf( 'Position missed by %.1f (measured %.1f vs target %.1f).', $measured_position - $target_position, (float) $measured_position, $target_position );
		}
		if ( null !== $ctr_hit ) {
			$message_parts[] = $ctr_hit
				? sprintf( 'CTR target hit (%.2f%% vs %.2f%%).', $measured_ctr * 100, $target_ctr * 100 )
				: sprintf( 'CTR missed by %.2f points (measured %.2f%% vs target %.2f%%).', ( $target_ctr - $measured_ctr ) * 100, $measured_ctr * 100, $target_ctr * 100 );
		}
		if ( null !== $impression_growth ) {
			$message_parts[] = sprintf(
				'Impressions %s%.0f%% vs baseline (%d → %d normalized).',
				$impression_growth >= 0 ? '+' : '',
				$impression_growth * 100,
				$before_imp,
				$after_imp_norm
			);
		}
		if ( $meta_tag_bottleneck ) {
			$message_parts[] = 'BOTTLENECK: position landed but the snippet is not earning its clicks. The body rewrite worked; the meta title or meta description is now the limiter. Propose a draft_update_seo_meta change next.';
		}

		return array(
			'has_metrics'         => true,
			'target_query'        => $target_query,
			'target_position'     => $target_position,
			'target_ctr'          => $target_ctr,
			'measured_position'   => $measured_position,
			'measured_ctr'        => $measured_ctr,
			'measured_impressions_growth' => null === $impression_growth ? null : round( $impression_growth, 3 ),
			'position_hit'        => $position_hit,
			'ctr_hit'             => $ctr_hit,
			'impression_hit'      => $impression_hit,
			'overall_hit'         => $overall,
			'meta_tag_bottleneck' => $meta_tag_bottleneck,
			'message'             => implode( ' ', $message_parts ),
		);
	}

	private static function edit_summary( $edit ) {
		$post_id   = (int) $edit['post_id'];
		$post      = $post_id > 0 ? get_post( $post_id ) : null;
		$post_kept = ( null !== $post );
		return array(
			'id'             => (int) $edit['id'],
			'post_id'        => $post_id,
			'post_exists'    => $post_kept,
			'change_type'    => $edit['change_type'],
			'change_summary' => $edit['change_summary'],
			'page_url'       => $edit['page_url'],
			'applied_at'     => $edit['applied_at'],
			'edit_url'       => $post_kept ? get_edit_post_link( $post_id, 'raw' ) : null,
		);
	}

	/**
	 * All edits within the last $days whose outcome is still status=pending
	 * (i.e. inside the measurement window). Unlike recent_with_status, this
	 * does NOT slice by recency — every in-flight edit appears, so the
	 * dashboard "Measuring" card can show the full backlog instead of
	 * silently dropping older still-measuring edits off a top-5 view.
	 *
	 * Returns rows sorted by ready_in_days ASC then applied_at DESC so the
	 * edits closest to having a verdict surface first.
	 */
	public static function pending_outcomes( $days = null, $hard_limit = 50 ) {
		global $wpdb;
		// An edit can only be `pending` while measurable_days < READY_DAYS.
		// That bounds the applied_at window precisely:
		//   applied_at > now - (SETTLE_DAYS + READY_DAYS + GSC_lag_days + slack)
		// With SETTLE=3, READY=7, GSC lag=2, +2 days slack → ~14 days.
		// Anything older is GUARANTEED measured; pre-filtering at the SQL
		// layer skips the per-row compute_outcome we'd otherwise pay just
		// to discard. The caller can still pass a larger window explicitly
		// for diagnostics; we cap below at the safe upper bound.
		$max_window = self::SETTLE_DAYS + self::READY_DAYS + 4; // ≈14
		$days       = ( null === $days ) ? $max_window : (int) $days;
		$days       = max( 7, min( $max_window, $days ) );
		$hard_limit = max( 5, min( 200, (int) $hard_limit ) );
		$cutoff     = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$edits = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cc_edits
			 WHERE applied_at >= %s
			 ORDER BY applied_at DESC
			 LIMIT %d",
			$cutoff,
			$hard_limit
		), ARRAY_A );

		$results = array();
		foreach ( (array) $edits as $edit ) {
			$outcome = self::compute_outcome( (int) $edit['id'] );
			if ( is_wp_error( $outcome ) || 'pending' !== ( $outcome['status'] ?? '' ) ) {
				continue;
			}
			$results[] = array(
				'edit'            => self::edit_summary( $edit ),
				'status'          => 'pending',
				'days_elapsed'    => isset( $outcome['days_elapsed'] ) ? (int) $outcome['days_elapsed'] : 0,
				'measurable_days' => isset( $outcome['measurable_days'] ) ? (int) $outcome['measurable_days'] : 0,
				'ready_in_days'   => isset( $outcome['ready_in_days'] ) ? (int) $outcome['ready_in_days'] : 0,
				'message'         => isset( $outcome['message'] ) ? (string) $outcome['message'] : '',
			);
		}
		usort( $results, function ( $a, $b ) {
			if ( $a['ready_in_days'] === $b['ready_in_days'] ) {
				return strcmp( $b['edit']['applied_at'], $a['edit']['applied_at'] );
			}
			return $a['ready_in_days'] <=> $b['ready_in_days'];
		} );
		return $results;
	}

	/**
	 * Lightweight list for the dashboard: recent edits with a quick verdict
	 * if measurable, otherwise a "still measuring" status. Includes
	 * target_score so the dashboard "What's Working" card can render
	 * hit / partial / missed / bottleneck verdicts against intent.
	 */
	public static function recent_with_status( $limit = 5 ) {
		$edits   = self::list_recent( $limit );
		$results = array();
		foreach ( $edits as $edit ) {
			$outcome = self::compute_outcome( (int) $edit['id'] );
			if ( is_wp_error( $outcome ) ) {
				continue;
			}
			$results[] = array(
				'edit'         => self::edit_summary( $edit ),
				'status'       => $outcome['status'],
				'verdict'      => isset( $outcome['verdict'] ) ? $outcome['verdict'] : null,
				'totals'       => isset( $outcome['totals'] ) ? $outcome['totals'] : null,
				'message'      => isset( $outcome['message'] ) ? $outcome['message'] : null,
				'target_score' => isset( $outcome['target_score'] ) ? $outcome['target_score'] : null,
			);
		}
		return $results;
	}

	/**
	 * Grouped variant of `pending_outcomes` keyed by post_id. The raw per-edit
	 * list is fine for the get_edit_outcome workflow but bad for the dashboard:
	 * a single page that we edited 6 times surfaces as 6 rows, drowning out
	 * other still-measuring posts and burying older pending edits.
	 *
	 * Returns one row per post with: pending_count, oldest_pending (the edit
	 * about to clear — sorted to the top), newest_pending (most recent edit
	 * applied), and the full `edits` array if the UI wants to expand. The
	 * outer list is sorted by oldest_pending.ready_in_days ASC so the post
	 * with a verdict landing soonest shows first.
	 */
	public static function pending_outcomes_by_post( $days = null, $hard_limit = 200 ) {
		$rows = self::pending_outcomes( $days, $hard_limit );
		if ( empty( $rows ) ) {
			return array();
		}

		$by_post = array();
		foreach ( $rows as $row ) {
			$post_id = isset( $row['edit']['post_id'] ) ? (int) $row['edit']['post_id'] : 0;
			$key     = $post_id > 0 ? (string) $post_id : ( 'edit_' . (int) $row['edit']['id'] );

			if ( ! isset( $by_post[ $key ] ) ) {
				$post              = $post_id > 0 ? get_post( $post_id ) : null;
				$post_title        = $post ? $post->post_title : '';
				$by_post[ $key ]   = array(
					'post_id'        => $post_id,
					'post_title'     => $post_title,
					'page_url'       => $row['edit']['page_url'],
					'edit_url'       => isset( $row['edit']['edit_url'] ) ? $row['edit']['edit_url'] : null,
					'post_exists'    => isset( $row['edit']['post_exists'] ) ? (bool) $row['edit']['post_exists'] : true,
					'pending_count'  => 0,
					'oldest_pending' => null,
					'newest_pending' => null,
					'edits'          => array(),
				);
			}

			$by_post[ $key ]['pending_count']++;
			$by_post[ $key ]['edits'][] = $row;

			$cur_oldest = $by_post[ $key ]['oldest_pending'];
			if ( null === $cur_oldest || (int) $row['ready_in_days'] < (int) $cur_oldest['ready_in_days'] ) {
				$by_post[ $key ]['oldest_pending'] = $row;
			}

			$cur_newest = $by_post[ $key ]['newest_pending'];
			if ( null === $cur_newest || strcmp( $row['edit']['applied_at'], $cur_newest['edit']['applied_at'] ) > 0 ) {
				$by_post[ $key ]['newest_pending'] = $row;
			}
		}

		$out = array_values( $by_post );
		usort(
			$out,
			function ( $a, $b ) {
				$cmp = (int) $a['oldest_pending']['ready_in_days'] <=> (int) $b['oldest_pending']['ready_in_days'];
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return strcmp( $b['newest_pending']['edit']['applied_at'], $a['newest_pending']['edit']['applied_at'] );
			}
		);
		return $out;
	}

	/**
	 * Grouped variant of `recent_with_status` keyed by post_id. Solves the same
	 * problem as pending_outcomes_by_post: many edits on one post stop drowning
	 * out other posts in the dashboard "What's Working" table.
	 *
	 * Per post: latest_edit (the verdict we display as the headline), edit_count
	 * (badge in the Page column), verdict_breakdown (small counts when the post
	 * has mixed verdicts across multiple edits), and the full edits array for
	 * the expandable detail view. Sorted by latest_edit.applied_at DESC.
	 *
	 * Oversamples the underlying per-edit list by 5x so a target of N distinct
	 * posts isn't undershot when a few posts dominate the recent activity log.
	 */
	public static function recent_with_status_by_post( $limit = 12 ) {
		$limit = max( 1, min( 200, (int) $limit ) );
		$rows  = self::recent_with_status( $limit * 5 );
		if ( empty( $rows ) ) {
			return array();
		}

		$by_post = array();
		foreach ( $rows as $row ) {
			$post_id = isset( $row['edit']['post_id'] ) ? (int) $row['edit']['post_id'] : 0;
			$key     = $post_id > 0 ? (string) $post_id : ( 'edit_' . (int) $row['edit']['id'] );

			if ( ! isset( $by_post[ $key ] ) ) {
				$post                  = $post_id > 0 ? get_post( $post_id ) : null;
				$post_title            = $post ? $post->post_title : '';
				$by_post[ $key ]       = array(
					'post_id'           => $post_id,
					'post_title'        => $post_title,
					'page_url'          => $row['edit']['page_url'],
					'edit_url'          => isset( $row['edit']['edit_url'] ) ? $row['edit']['edit_url'] : null,
					'post_exists'       => isset( $row['edit']['post_exists'] ) ? (bool) $row['edit']['post_exists'] : true,
					'edit_count'        => 0,
					'latest_edit'       => null,
					'verdict_breakdown' => array(
						'positive' => 0,
						'flat'     => 0,
						'negative' => 0,
						'pending'  => 0,
						'no_data'  => 0,
					),
					'edits'             => array(),
				);
			}

			$by_post[ $key ]['edit_count']++;
			$by_post[ $key ]['edits'][] = $row;

			$cur_latest = $by_post[ $key ]['latest_edit'];
			if ( null === $cur_latest || strcmp( $row['edit']['applied_at'], $cur_latest['edit']['applied_at'] ) > 0 ) {
				$by_post[ $key ]['latest_edit'] = $row;
			}

			$status  = isset( $row['status'] ) ? $row['status'] : 'pending';
			$verdict = isset( $row['verdict'] ) ? $row['verdict'] : null;
			if ( 'pending' === $status ) {
				$by_post[ $key ]['verdict_breakdown']['pending']++;
			} elseif ( 'positive' === $verdict ) {
				$by_post[ $key ]['verdict_breakdown']['positive']++;
			} elseif ( 'negative' === $verdict ) {
				$by_post[ $key ]['verdict_breakdown']['negative']++;
			} elseif ( 'no_data' === $verdict ) {
				$by_post[ $key ]['verdict_breakdown']['no_data']++;
			} else {
				$by_post[ $key ]['verdict_breakdown']['flat']++;
			}
		}

		$out = array_values( $by_post );
		usort(
			$out,
			function ( $a, $b ) {
				return strcmp( $b['latest_edit']['edit']['applied_at'], $a['latest_edit']['edit']['applied_at'] );
			}
		);
		return array_slice( $out, 0, $limit );
	}

	/**
	 * 30-day rollup against intent for the dashboard "What's Working" card.
	 * Iterates recently-applied edits, computes target_score on each, and
	 * returns aggregate counts: total / hit / partial / missed / bottleneck /
	 * pending / no_metrics. The card uses these to surface "5 of 8 edits hit
	 * their stated target; 2 are bottlenecked by meta tags" at a glance.
	 *
	 * Bounded scan (default 30 days, max 50 edits) so the dashboard never
	 * blocks on a long history. Uses each edit's own outcome cache, so
	 * repeated calls are cheap.
	 */
	public static function rollup_against_targets( $days = 30, $limit = 50 ) {
		global $wpdb;
		$days  = max( 1, min( 90, (int) $days ) );
		$limit = max( 1, min( 200, (int) $limit ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$edits = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cc_edits
			 WHERE applied_at >= %s
			 ORDER BY applied_at DESC
			 LIMIT %d",
			$cutoff,
			$limit
		), ARRAY_A );

		$summary = array(
			'window_days'    => $days,
			'total'          => 0,
			'measured'       => 0,
			'pending'        => 0,
			'hit'            => 0,
			'partial'        => 0,
			'missed'         => 0,
			'no_data'        => 0,
			'bottleneck'     => 0,
			'no_metrics'     => 0,
			'top_bottleneck' => array(),
			'top_hits'       => array(),
		);

		foreach ( (array) $edits as $edit ) {
			$summary['total']++;
			$outcome = self::compute_outcome( (int) $edit['id'] );
			if ( is_wp_error( $outcome ) ) {
				continue;
			}
			if ( 'pending' === $outcome['status'] ) {
				$summary['pending']++;
				continue;
			}
			$summary['measured']++;
			$ts = isset( $outcome['target_score'] ) ? $outcome['target_score'] : null;
			if ( ! is_array( $ts ) || empty( $ts['has_metrics'] ) ) {
				$summary['no_metrics']++;
				continue;
			}
			$overall = isset( $ts['overall_hit'] ) ? $ts['overall_hit'] : 'no_data';
			if ( isset( $summary[ $overall ] ) ) {
				$summary[ $overall ]++;
			}
			if ( ! empty( $ts['meta_tag_bottleneck'] ) ) {
				$summary['bottleneck']++;
				if ( count( $summary['top_bottleneck'] ) < 5 ) {
					$summary['top_bottleneck'][] = array(
						'edit_id'      => (int) $edit['id'],
						'post_id'      => (int) $edit['post_id'],
						'change_summary' => $edit['change_summary'],
						'target_query' => $ts['target_query'],
						'message'      => $ts['message'],
					);
				}
			} elseif ( 'hit' === $overall ) {
				if ( count( $summary['top_hits'] ) < 5 ) {
					$summary['top_hits'][] = array(
						'edit_id'      => (int) $edit['id'],
						'post_id'      => (int) $edit['post_id'],
						'change_summary' => $edit['change_summary'],
						'target_query' => $ts['target_query'],
						'message'      => $ts['message'],
					);
				}
			}
		}
		return $summary;
	}
}
