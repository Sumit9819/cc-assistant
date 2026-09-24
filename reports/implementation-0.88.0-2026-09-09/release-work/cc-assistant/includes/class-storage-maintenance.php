<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified storage retention for cc-assistant tables (v0.33.0).
 *
 * The plugin creates ~11 custom tables. Before v0.33.0 only three of them had
 * active retention:
 *
 *   ✓ wp_cc_gsc_queries        — tiered prune + 250 MB cap (class-gsc.php)
 *   ✓ wp_cc_activity_log       — 2-day cutoff (class-activity-log.php)
 *   ✓ wp_cc_llm_crawls         — has cutoff prune (class-llm-tracker.php)
 *
 * Three high-bloat tables had NO retention at all:
 *
 *   ✗ wp_cc_snapshots          — LONGTEXT post_content + meta + elementor_data
 *                                per snapshot. Grows by ~50–300 KB per edit.
 *   ✗ wp_cc_pending_changes    — LONGTEXT current_value + proposed_value per
 *                                row. Settled rows grew without bound.
 *   ✗ wp_cc_edits              — metadata-only, smaller, but still append-only.
 *
 * This class closes those gaps with a single daily cron event that prunes all
 * three using time-based windows, then logs total bytes recovered to the
 * activity log. Retention windows are overridable per-table via the
 * `cc_assistant_storage_retention` option so operators on tight storage can
 * tighten further.
 *
 * Designed for low-storage hosting (e.g. 1 GB database total). Defaults are
 * deliberately conservative to leave headroom for WP core, other plugins, and
 * media-related postmeta.
 */
class CC_Assistant_Storage_Maintenance {

	const CRON_HOOK   = 'cc_assistant_storage_maintenance';
	const OPTION_KEY  = 'cc_assistant_storage_retention';
	const LAST_RUN    = 'cc_assistant_storage_last_maintain';

	/**
	 * Default retention in days, per logical bucket. Override via the
	 * cc_assistant_storage_retention option:
	 *
	 *   update_option( 'cc_assistant_storage_retention', array(
	 *       'cc_snapshots'       => 30,
	 *       'cc_pending_settled' => 30,
	 *       'cc_edits'           => 90,
	 *   ) );
	 *
	 * Smaller values = more aggressive deletion. Set any value to 0 to disable
	 * that prune (rarely a good idea; the unbounded growth comes back).
	 */
	private static function default_retention() {
		return array(
			'cc_snapshots'       => 90,  // pre/post snapshots older than this
			'cc_pending_settled' => 60,  // settled (non-pending) rows older than this
			'cc_edits'           => 365, // edit history rows
		);
	}

	public static function get_retention() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::default_retention(), $saved );
	}

	/**
	 * Register the admin-side "Run maintenance now" handler.
	 *
	 * Note: the CRON_HOOK action is intentionally NOT registered here. The
	 * canonical cron-handler registration lives in cc-assistant.php so it gets
	 * wrapped by CC_Assistant_Error_Log::wrap() — keeps cron failures captured
	 * + visible in Settings → Health instead of fatal-ing the cron tick.
	 * Registering the action in both places would cause run() to execute twice.
	 */
	public static function init() {
		add_action( 'admin_post_cc_storage_maintain_now', array( __CLASS__, 'handle_manual_run' ) );
	}

	/**
	 * Idempotent cron schedule. Called from the activator and from the
	 * lazy-schedule path in cc-assistant.php so a missed activation hook still
	 * gets the cron registered on the next admin request.
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Run at 00:30 UTC daily — 30 min after typical midnight GSC sync
			// so the two big maintenance passes don't fight for the same lock.
			$first = strtotime( 'tomorrow 00:30:00' );
			wp_schedule_event( $first, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule_cron() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Main entry — runs all prunes, captures stats, persists the run report,
	 * and logs a one-line summary to the activity log.
	 */
	/**
	 * v0.60.1: delete EXPIRED cc_* transients. Win-audit competitor caches
	 * use per-URL keys that are never read again, so WordPress's read-time
	 * GC never fires and multi-MB dead rows accumulate in wp_options forever
	 * on installs without a persistent object cache.
	 */
	private static function sweep_expired_transients() {
		global $wpdb;
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE t, v FROM {$wpdb->options} t
				 JOIN {$wpdb->options} v ON v.option_name = CONCAT('_transient_cc_', SUBSTRING(t.option_name, 23))
				 WHERE t.option_name LIKE '\\_transient\\_timeout\\_cc\\_%' AND t.option_value < %d",
				time()
			)
		);
		return array( 'deleted' => $deleted );
	}

	public static function run() {
		$ret = self::get_retention();
		$stats = array(
			'snapshots'       => self::prune_snapshots( (int) $ret['cc_snapshots'] ),
			'pending_settled' => self::prune_pending_settled( (int) $ret['cc_pending_settled'] ),
			'edits'           => self::prune_edits( (int) $ret['cc_edits'] ),
			'transients'      => self::sweep_expired_transients(),
		);

		// v0.60.1: flip admin-only text blobs out of alloptions (they were
		// written with autoload on; up to ~40KB deserialized on every
		// anonymous page view). Idempotent one-row updates.
		global $wpdb;
		$wpdb->query(
			"UPDATE {$wpdb->options} SET autoload = 'no' WHERE autoload NOT IN ('no','off') AND option_name IN (
				'cc_assistant_site_notes', 'cc_assistant_style_guide', 'cc_assistant_competitor_domains',
				'cc_assistant_authority_domains', 'cc_assistant_gsc_last_maintain'
			)"
		);

		update_option(
			self::LAST_RUN,
			array(
				'ts'    => current_time( 'mysql' ),
				'stats' => $stats,
			),
			false
		);

		if ( class_exists( 'CC_Assistant_Activity_Log' ) ) {
			$summary = sprintf(
				'storage_maintain: snapshots=%d, pending_settled=%d, edits=%d',
				(int) $stats['snapshots']['deleted'],
				(int) $stats['pending_settled']['deleted'],
				(int) $stats['edits']['deleted']
			);
			try {
				CC_Assistant_Activity_Log::record( 'db_maintenance', null, null, $summary );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Activity logger may be unavailable in early-boot or error states.
				// Maintenance succeeded; the log entry is informational only.
			}
		}

		return $stats;
	}

	/**
	 * Drop snapshot rows older than $days. Snapshots are pre_apply/post_apply
	 * rollback points and stored LONGTEXT post_content + meta + elementor_data,
	 * which is by far the largest per-row payload in the plugin.
	 *
	 * Trade-off: we lose the ability to roll an edit back to its state before
	 * a 90+ day old write. New edits still create fresh pre_apply snapshots,
	 * so per-edit rollback continues to work for anything edited within the
	 * window.
	 */
	public static function prune_snapshots( $days ) {
		$days = max( 0, (int) $days );
		if ( $days <= 0 ) {
			return array( 'deleted' => 0, 'final_mb' => 0, 'skipped' => true );
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_snapshots';
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
		$deleted = (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < %s",
			$cutoff
		) );
		return array(
			'deleted'  => $deleted,
			'final_mb' => self::table_size_mb( $table ),
		);
	}

	/**
	 * Drop fully-settled (approved / rejected / superseded) pending_changes
	 * rows older than $days. Currently 'pending' status rows are NEVER pruned —
	 * the inbox is the human reviewer's queue and must stay intact.
	 *
	 * The COALESCE(reviewed_at, created_at) accounts for rows where reviewed_at
	 * may be null (e.g. superseded rows from older plugin versions); falls back
	 * to created_at so the prune always has a sensible timestamp.
	 */
	public static function prune_pending_settled( $days ) {
		$days = max( 0, (int) $days );
		if ( $days <= 0 ) {
			return array( 'deleted' => 0, 'final_mb' => 0, 'skipped' => true );
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_pending_changes';
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
		$deleted = (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table}
			 WHERE status <> 'pending'
			   AND COALESCE(reviewed_at, created_at) < %s",
			$cutoff
		) );
		return array(
			'deleted'  => $deleted,
			'final_mb' => self::table_size_mb( $table ),
		);
	}

	/**
	 * Drop cc_edits rows older than $days. This table is metadata-only (no
	 * post body), so each row is much smaller — but the table is append-only
	 * and would otherwise grow forever on an active site.
	 */
	public static function prune_edits( $days ) {
		$days = max( 0, (int) $days );
		if ( $days <= 0 ) {
			return array( 'deleted' => 0, 'final_mb' => 0, 'skipped' => true );
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_edits';
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
		$deleted = (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE applied_at < %s",
			$cutoff
		) );
		return array(
			'deleted'  => $deleted,
			'final_mb' => self::table_size_mb( $table ),
		);
	}

	/**
	 * Per-table size lookup against information_schema. Wraps DB_NAME so the
	 * caller doesn't need to know the schema name (matters on shared hosting
	 * with prefixed databases).
	 */
	public static function table_size_mb( $table ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT (data_length + index_length) AS size_bytes
			 FROM information_schema.TABLES
			 WHERE table_schema = %s AND table_name = %s
			 LIMIT 1",
			DB_NAME,
			$table
		) );
		if ( ! $row || empty( $row->size_bytes ) ) {
			return 0.0;
		}
		return round( (float) $row->size_bytes / 1024 / 1024, 2 );
	}

	/**
	 * Returns a per-table snapshot of size and row count for every cc_*
	 * table the plugin manages. Used by the Database Health admin page.
	 */
	public static function table_sizes() {
		global $wpdb;
		$logical_tables = array(
			'cc_gsc_queries',
			'cc_snapshots',
			'cc_pending_changes',
			'cc_embeddings',
			'cc_edits',
			'cc_llm_crawls',
			'cc_link_graph',
			'cc_topic_clusters',
			'cc_cluster_members',
			'cc_cannibalization_trends',
			'cc_activity_log',
		);
		$out = array();
		foreach ( $logical_tables as $logical ) {
			$full = $wpdb->prefix . $logical;
			// COUNT(*) on tables that don't exist yet would warn — short-circuit.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) );
			if ( $exists !== $full ) {
				$out[ $logical ] = array(
					'size_mb' => 0,
					'rows'    => 0,
					'exists'  => false,
				);
				continue;
			}
			$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$full}" );
			$out[ $logical ] = array(
				'size_mb' => self::table_size_mb( $full ),
				'rows'    => $rows,
				'exists'  => true,
			);
		}
		return $out;
	}

	/**
	 * Total bytes-on-disk for all plugin-owned tables in MB. Single number
	 * for the Database Health page's headline "Plugin storage budget".
	 */
	public static function total_size_mb() {
		$sizes = self::table_sizes();
		$total = 0.0;
		foreach ( $sizes as $row ) {
			$total += (float) $row['size_mb'];
		}
		return round( $total, 2 );
	}

	public static function get_last_run() {
		$row = get_option( self::LAST_RUN, null );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Manual "Run maintenance now" form handler. Runs the cron callback
	 * synchronously and redirects back with a success flag.
	 */
	public static function handle_manual_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run maintenance.', 'cc-assistant' ) );
		}
		check_admin_referer( 'cc_storage_maintain_now' );
		self::run();
		// Also run the existing GSC table maintain for one-shot completeness.
		if ( class_exists( 'CC_Assistant_GSC' ) && method_exists( 'CC_Assistant_GSC', 'maintain' ) ) {
			try {
				CC_Assistant_GSC::maintain();
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// GSC maintain failure must not break the redirect.
			}
		}
		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'admin.php?page=cc-assistant-db-health' );
		}
		wp_safe_redirect( add_query_arg( 'cc_storage_maintained', '1', $back ) );
		exit;
	}
}
