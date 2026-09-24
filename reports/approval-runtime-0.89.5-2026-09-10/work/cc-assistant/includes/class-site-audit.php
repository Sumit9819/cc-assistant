<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide content audit. Runs CC_Assistant_Pre_Publish::check_post() over
 * every published post in the allowlist, aggregates failures by check name,
 * and stores the rollup in an option. Cron-driven so the Check Up admin
 * view never runs the heavy aggregation on render.
 *
 * Rollup shape:
 *   {
 *     computed_at: 1714,
 *     posts_total: 62,
 *     posts_passing_all: 14,
 *     by_check: { em_dashes: { fail_count: 18, pass_count: 44 }, ... },
 *     worst_posts: [ {post_id, title, fail_count, fail_checks: []}, ... 10 ]
 *   }
 *
 * Heavy-loop guard: caps at AUDIT_BATCH_LIMIT posts per run so the cron
 * job stays under PHP execution time on big sites. The list is sorted by
 * post_modified_gmt DESC so freshly-edited content gets re-audited first.
 */
class CC_Assistant_Site_Audit {

	const OPT_KEY            = 'cc_assistant_site_audit_blob';
	const CRON_HOOK          = 'cc_assistant_site_audit';
	const AUDIT_BATCH_LIMIT  = 200;
	const RESULT_TTL_SECONDS = 7 * DAY_IN_SECONDS;

	public static function bootstrap() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		// Schedule a daily run if not already scheduled. Idempotent.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function get_blob() {
		$blob = get_option( self::OPT_KEY, null );
		return is_array( $blob ) ? $blob : null;
	}

	/**
	 * Manual kick — used by an admin button. Returns true if scheduled,
	 * false if a run is already pending within the next minute.
	 */
	public static function schedule_now() {
		if ( wp_next_scheduled( self::CRON_HOOK ) && ( wp_next_scheduled( self::CRON_HOOK ) - time() < 60 ) ) {
			return false;
		}
		wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		return true;
	}

	public static function run() {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		// Cache-only mode for the rendered-HTML check. The single-post path
		// (editor sidebar / Check Up page) fetches and caches; the cron run
		// reads those caches but never fires HTTP itself (200 posts × ~5s
		// would exhaust PHP execution time and hammer the site). Posts that
		// haven't been visited in their refresh window get parser-only checks
		// in the rollup — close enough for trend purposes.
		if ( ! defined( 'CC_PRE_PUBLISH_CACHE_ONLY' ) ) {
			define( 'CC_PRE_PUBLISH_CACHE_ONLY', true );
		}
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$args    = array(
			'post_type'        => $allowed,
			'post_status'      => 'publish',
			'posts_per_page'   => self::AUDIT_BATCH_LIMIT,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'suppress_filters' => true,
		);
		$post_ids = get_posts( $args );

		$by_check        = array();
		$worst           = array();
		$passing_all     = 0;
		$total           = 0;
		$skipped = 0;
		// Time budget: stop iterating once we've spent ~25 seconds in the
		// loop. WP cron typically inherits PHP max_execution_time (often 30s
		// on shared hosts), so a 25s budget leaves headroom for the cleanup
		// and option write below. Posts beyond the budget get audited in the
		// next nightly run — partial coverage is fine because the rollup
		// caps at 200 anyway.
		$start_ts        = microtime( true );
		$time_budget_sec = 25;
		$budget_exceeded = false;
		foreach ( $post_ids as $pid ) {
			if ( ( microtime( true ) - $start_ts ) > $time_budget_sec ) {
				$budget_exceeded = true;
				break;
			}
			$pid = (int) $pid;
			// Wrap each post in try/catch so one bad post (corrupt
			// _elementor_data, missing meta, parser explosion) doesn't kill
			// the whole audit. Site audit aggregates over many posts; partial
			// blob is much worse than skipping one.
			try {
				$result = CC_Assistant_Pre_Publish::check_post( $pid );
			} catch ( \Throwable $e ) {
				$skipped++;
				error_log( '[cc-assistant] site_audit skipped post ' . $pid . ': ' . $e->getMessage() );
				continue;
			}
			if ( is_wp_error( $result ) ) {
				$skipped++;
				continue;
			}
			$total++;
			$fail_checks = array();
			foreach ( $result['checks'] as $name => $c ) {
				if ( ! isset( $by_check[ $name ] ) ) {
					$by_check[ $name ] = array( 'pass_count' => 0, 'fail_count' => 0 );
				}
				if ( ! empty( $c['pass'] ) ) {
					$by_check[ $name ]['pass_count']++;
				} else {
					$by_check[ $name ]['fail_count']++;
					$fail_checks[] = $name;
				}
			}
			if ( empty( $fail_checks ) ) {
				$passing_all++;
				continue;
			}
			$worst[] = array(
				'post_id'     => $pid,
				'title'       => get_the_title( $pid ),
				'edit_url'    => get_edit_post_link( $pid, 'raw' ),
				'fail_count'  => count( $fail_checks ),
				'fail_checks' => $fail_checks,
			);
		}
		usort( $worst, function ( $a, $b ) { return $b['fail_count'] - $a['fail_count']; } );
		$worst = array_slice( $worst, 0, 15 );

		$blob = array(
			'computed_at'       => time(),
			'posts_total'       => $total,
			'posts_passing_all' => $passing_all,
			'posts_skipped'     => $skipped,
			'budget_exceeded'   => $budget_exceeded,
			'elapsed_sec'       => round( microtime( true ) - $start_ts, 2 ),
			'by_check'          => $by_check,
			'worst_posts'       => $worst,
		);
		update_option( self::OPT_KEY, $blob, false );

		// Trip alerts if E-E-A-T or schema failure rates cross thresholds.
		// These are content-quality signals; alerts only — no auto-action.
		require_once CC_ASSISTANT_DIR . 'includes/class-admin-notices.php';
		if ( $total > 0 ) {
			$eeat_keys = array( 'authority_links', 'author_bio', 'source_density' );
			$failing_eeat = 0;
			foreach ( $eeat_keys as $k ) {
				$failing_eeat += isset( $by_check[ $k ]['fail_count'] ) ? (int) $by_check[ $k ]['fail_count'] : 0;
			}
			$denominator = $total * count( $eeat_keys );
			$pct = $denominator > 0 ? round( ( $failing_eeat / $denominator ) * 100 ) : 0;
			if ( $pct >= 30 ) {
				CC_Assistant_Admin_Notices::add(
					'site_audit_eeat',
					'warning',
					sprintf( /* translators: %d: percent */ __( '%d%% of your posts are failing one or more E-E-A-T checks (author bio, authority sources, source density). See the Check Up page for the worst offenders.', 'cc-assistant' ), $pct ),
					array( 'url' => admin_url( 'admin.php?page=cc-assistant-checkup' ), 'label' => __( 'Open Check Up', 'cc-assistant' ) )
				);
			} else {
				CC_Assistant_Admin_Notices::dismiss( 'site_audit_eeat' );
			}
		}
		return $blob;
	}
}
