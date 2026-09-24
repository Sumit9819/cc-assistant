<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cannibalization trend tracker.
 *
 * Stores one row per audit (default weekly) capturing the count of
 * conflicts and the total leaked impressions, so the user can see
 * "is content cannibalization on this site getting better or worse?"
 *
 * Tiny table: 3 columns + id + auto-pruned at 52 rows (1 year). Read
 * cost is one fast SELECT; write cost is once a week.
 */
class CC_Assistant_Cannibalization_Trends {

	const TABLE     = 'cc_cannibalization_trends';
	const CRON_HOOK = 'cc_assistant_cannib_trends';
	const MAX_ROWS  = 52;

	public static function bootstrap() {
		// Register 'weekly' interval if WP doesn't already have it. WP 5.4+
		// ships it by default, but the filter is idempotent and cheap.
		add_filter( 'cron_schedules', function ( $schedules ) {
			if ( ! isset( $schedules['weekly'] ) ) {
				$schedules['weekly'] = array(
					'interval' => 7 * DAY_IN_SECONDS,
					'display'  => __( 'Once weekly', 'cc-assistant' ),
				);
			}
			return $schedules;
		} );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	public static function ensure_table() {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE;
		$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $exists === $table ) {
			return;
		}
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE $table (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				captured_at DATETIME NOT NULL,
				conflict_count INT UNSIGNED NOT NULL DEFAULT 0,
				leak_impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
				top_query VARCHAR(255) NULL,
				PRIMARY KEY (id),
				KEY captured_at (captured_at)
			) $charset;"
		);
	}

	public static function run() {
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
		self::ensure_table();
		global $wpdb;
		$table  = $wpdb->prefix . self::TABLE;
		$result = CC_Assistant_SEO_Tools::cannibalization( array( 'limit' => 100 ) );
		$leak   = 0;
		foreach ( (array) $result['conflicts'] as $c ) {
			$leak += (int) $c['total_impressions'];
		}
		$top_query = ! empty( $result['conflicts'][0]['query'] ) ? (string) $result['conflicts'][0]['query'] : null;
		$wpdb->insert( $table, array(
			'captured_at'      => current_time( 'mysql', 1 ),
			'conflict_count'   => (int) $result['count'],
			'leak_impressions' => $leak,
			'top_query'        => $top_query,
		) );
		// Prune to last MAX_ROWS so the table stays tiny. Subquery DELETE is
		// fragile on older MariaDB — use a two-step IDs-then-DELETE instead.
		$keep_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM $table ORDER BY captured_at DESC LIMIT %d",
			self::MAX_ROWS
		) );
		if ( ! empty( $keep_ids ) ) {
			$keep_ids = array_map( 'intval', $keep_ids );
			$placeholders = implode( ',', array_fill( 0, count( $keep_ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM $table WHERE id NOT IN ($placeholders)",
				$keep_ids
			) );
		}
	}

	public static function rows( $limit = 12 ) {
		global $wpdb;
		self::ensure_table();
		$table = $wpdb->prefix . self::TABLE;
		$limit = max( 1, min( 52, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table ORDER BY captured_at DESC LIMIT %d", $limit )
		);
	}

	/**
	 * Compare last vs prior reading for trend direction.
	 * Returns ['direction' => 'better'|'worse'|'flat', 'delta_count' => int, 'delta_impr' => int]
	 */
	public static function direction() {
		$rows = self::rows( 2 );
		if ( count( $rows ) < 2 ) {
			return array( 'direction' => 'flat', 'delta_count' => 0, 'delta_impr' => 0 );
		}
		$latest = $rows[0];
		$prior  = $rows[1];
		$dc = (int) $latest->conflict_count - (int) $prior->conflict_count;
		$di = (int) $latest->leak_impressions - (int) $prior->leak_impressions;
		$direction = 'flat';
		if ( $dc < -1 || $di < -100 ) {
			$direction = 'fewer_candidates';
		} elseif ( $dc > 1 || $di > 100 ) {
			$direction = 'more_candidates';
		}
		return array( 'direction' => $direction, 'delta_count' => $dc, 'delta_impr' => $di, 'meaning' => 'Candidate exposure changed. This does not measure lost traffic or prove improvement.', 'legacy_column' => 'leak_impressions stores candidate impressions, not loss' );
	}
}
