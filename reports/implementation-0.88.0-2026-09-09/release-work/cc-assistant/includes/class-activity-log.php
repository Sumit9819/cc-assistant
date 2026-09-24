<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rolling 2-day activity log for cc-assistant.
 *
 * Why this exists: the `Sessions` section of the site-memory blob is a
 * free-form append-only string that grows unbounded and eventually gets
 * tail-truncated. That's fine for "what was Claude thinking last week"
 * but useless for "what concrete actions happened in the last 48 hours."
 * This log is the structured complement — one row per discrete event
 * (edit applied, pending rejected, note appended, audit run), capped at
 * 48 hours so token-cost of recap stays flat regardless of activity volume.
 *
 * Retention policy: hard-pruned at 48 hours by `prune_older_than`. The
 * site-memory Sessions section keeps the longer-form narrative; this
 * table keeps the time-ordered event ticker.
 *
 * Performance: single indexed table, write happens inside the same
 * transaction the action originates from (apply hook is already
 * post-commit). No FE queries. Read on whoami is one SELECT with a
 * WHERE on the indexed `ts` column and LIMIT.
 */
class CC_Assistant_Activity_Log {

	const RETENTION_HOURS = 48;
	const RECAP_LIMIT     = 30;

	/**
	 * Event type taxonomy. Keep this short — the activity log is for
	 * structural events, not the full event bus.
	 *
	 *  - edit_applied   : a pending change was approved + applied
	 *  - edit_rejected  : a pending change was rejected by the reviewer
	 *  - edit_rollback  : an applied change was rolled back
	 *  - note_added     : a session note was appended to site memory
	 *  - rule_added     : a rule was appended to the Rules section (rare; surfaces in next session bootstrap)
	 *  - audit_run      : a heavy audit (link graph, advisor, etc.) finished
	 *  - drift_detected : a post was modified outside the plugin
	 */
	const TYPE_EDIT_APPLIED   = 'edit_applied';
	const TYPE_EDIT_REJECTED  = 'edit_rejected';
	const TYPE_EDIT_ROLLBACK  = 'edit_rollback';
	const TYPE_NOTE_ADDED     = 'note_added';
	const TYPE_RULE_ADDED     = 'rule_added';
	const TYPE_AUDIT_RUN      = 'audit_run';
	const TYPE_DRIFT_DETECTED = 'drift_detected';

	/**
	 * Table name including the WP prefix. Reads $wpdb->prefix every call so
	 * test installs with custom prefixes work without code changes.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cc_activity_log';
	}

	/**
	 * Append one event row. Truncates the summary to 500 chars so a runaway
	 * caller can't inflate the table. Returns the inserted id or false.
	 *
	 * @param string   $type     One of the TYPE_* constants.
	 * @param string   $summary  Free-form one-line description.
	 * @param int|null $post_id  Optional related post.
	 * @param int|null $change_id Optional related cc_pending_changes id.
	 * @param string   $actor    'claude' | 'human' | 'system'. Default 'system'.
	 */
	public static function record( $type, $summary, $post_id = null, $change_id = null, $actor = 'system' ) {
		global $wpdb;
		$tbl = self::table_name();
		$summary = (string) $summary;
		if ( mb_strlen( $summary ) > 500 ) {
			$summary = mb_substr( $summary, 0, 497 ) . '…';
		}
		$ok = $wpdb->insert(
			$tbl,
			array(
				'ts'        => current_time( 'mysql' ),
				'type'      => (string) $type,
				'post_id'   => $post_id ? (int) $post_id : null,
				'change_id' => $change_id ? (int) $change_id : null,
				'actor'     => (string) $actor,
				'summary'   => $summary,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Return the most recent events within the trailing $hours window.
	 * Result rows are compact arrays the whoami response can ship without
	 * further shaping. Limit defaults to 30 to keep tokens predictable.
	 */
	public static function recent_window( $hours = self::RETENTION_HOURS, $limit = self::RECAP_LIMIT ) {
		global $wpdb;
		$tbl   = self::table_name();
		$hours = max( 1, min( (int) $hours, 168 ) ); // cap at 1 week even if caller asks for more
		$limit = max( 1, min( (int) $limit, 100 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $hours * HOUR_IN_SECONDS );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, ts, type, post_id, change_id, actor, summary
				 FROM {$tbl}
				 WHERE ts >= %s
				 ORDER BY ts DESC
				 LIMIT %d",
				$cutoff,
				$limit
			),
			ARRAY_A
		);
		if ( ! $rows ) {
			return array();
		}
		// Cast types so the JSON response stays clean.
		foreach ( $rows as &$r ) {
			$r['id']        = (int) $r['id'];
			$r['post_id']   = isset( $r['post_id'] ) && '' !== $r['post_id'] ? (int) $r['post_id'] : null;
			$r['change_id'] = isset( $r['change_id'] ) && '' !== $r['change_id'] ? (int) $r['change_id'] : null;
		}
		unset( $r );
		return $rows;
	}

	/**
	 * Delete rows older than $days. Called from the daily cron job.
	 * Returns the number of rows removed.
	 */
	public static function prune_older_than( $days = 2 ) {
		global $wpdb;
		$tbl = self::table_name();
		$days = max( 1, min( (int) $days, 30 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $days * DAY_IN_SECONDS );
		$count = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tbl} WHERE ts < %s",
				$cutoff
			)
		);

		// Orphaned compute_outcome transient sweep. The cc_outcome_v2_<id>_<bucket>
		// cache key was bucketed in v0.22 so the "ready in N days" countdown
		// can't drift past a measurable-day boundary — but bucketing means
		// each new 6-hour window mints a fresh key while old keys sit in the
		// options table until their TTL is read. WP's built-in GC only fires
		// on access; orphan buckets are never read again. Drop expired rows
		// here so the options table doesn't accumulate them indefinitely.
		$now = time();
		$expired = (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			   AND CAST(option_value AS UNSIGNED) < %d",
			$wpdb->esc_like( '_transient_timeout_cc_outcome_v2_' ) . '%',
			$now
		) );
		foreach ( $expired as $timeout_name ) {
			$tname = (string) $timeout_name;
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $tname ) );
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s",
				str_replace( '_transient_timeout_', '_transient_', $tname )
			) );
		}

		return $count;
	}

	/**
	 * Snapshot of activity volume in the trailing window. Used by whoami to
	 * give the model a quick "this site has been active recently" headline.
	 */
	public static function summary_window( $hours = self::RETENTION_HOURS ) {
		global $wpdb;
		$tbl   = self::table_name();
		$hours = max( 1, min( (int) $hours, 168 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $hours * HOUR_IN_SECONDS );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT type, COUNT(*) AS n FROM {$tbl} WHERE ts >= %s GROUP BY type ORDER BY n DESC",
				$cutoff
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (string) $r['type'] ] = (int) $r['n'];
		}
		return $out;
	}
}
