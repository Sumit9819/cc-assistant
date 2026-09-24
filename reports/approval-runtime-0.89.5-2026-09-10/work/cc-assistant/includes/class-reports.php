<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports (v0.64.0) — the measurement surface the plugin was missing.
 *
 * Before this class the operator could see a verdict pill and a handful of
 * rolled-up integers, but could not answer three basic questions:
 *
 *   1. "How is the site doing overall?"   -> no aggregate anywhere.
 *   2. "How is THIS page doing?"          -> a query list, with no history
 *                                            and no link to what we changed.
 *   3. "Can I have that as a file?"       -> nothing in the plugin exported
 *                                            anything (verified: zero matches
 *                                            for fputcsv / Content-Disposition
 *                                            plugin-wide before this release).
 *
 * Everything here reads data the plugin ALREADY stores. No new sync, no new
 * table, no new remote call:
 *
 *   {prefix}cc_gsc_queries  — daily x page x query x appearance rows. Note the
 *                             appearance split is a PARTITION of the query row
 *                             (impressions are divided across buckets in
 *                             class-gsc.php::store_rows_for_date), so summing
 *                             every row for a page is correct and does not
 *                             double-count.
 *   {prefix}cc_edits        — the applied-change ledger (post_id, change_type,
 *                             change_summary, applied_at GMT).
 *   {prefix}cc_lead_events  — Elementor Pro form submissions, aggregated per
 *                             (day, post, form). Captured since v0.58 and,
 *                             until now, never rendered anywhere in wp-admin.
 *
 * Honesty rule: every number on the Reports screen is either real or absent.
 * When Search Console is not connected, or the window has no finalized data,
 * the screen says so and explains why instead of rendering zeros that look
 * like a dead site. That specific failure (Page Performance rendering 200 rows
 * of "0 clicks / 0 impressions / — position" with no connection check) is the
 * defect this release exists to fix.
 */
class CC_Assistant_Reports {

	const MENU_SLUG    = 'cc-assistant-reports';
	const EXPORT_ACTION = 'cc_assistant_export_report';
	const NONCE         = 'cc_assistant_reports';
	const DEFAULT_DAYS  = 28;
	const MAX_ROWS      = 500;

	/**
	 * Capability for viewing AND exporting reports.
	 *
	 * Deliberately NOT `edit_posts`: that is held by Contributors, and these
	 * screens expose site-wide traffic plus the complete ledger of every change
	 * ever applied — and hand it over as a downloadable file. `edit_others_posts`
	 * is the Editor tier, which is who legitimately needs content-performance
	 * data. Used for the menu, the screen, and the export endpoint alike so
	 * there is no back door through a copied nonce URL.
	 */
	const CAP = 'edit_others_posts';

	/** Ranges offered in the picker. Keep inside the GSC retention window (60d). */
	public static function ranges() {
		return array(
			7  => __( 'Last 7 days', 'cc-assistant' ),
			14 => __( 'Last 14 days', 'cc-assistant' ),
			28 => __( 'Last 28 days', 'cc-assistant' ),
			60 => __( 'Last 60 days', 'cc-assistant' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 15 );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * VISIBLE submenu — deliberately.
	 *
	 * The operator's complaint was that they could not get data out of the
	 * measurement screens at all. Shipping the fix behind a tab on a
	 * differently-named page would have repeated that mistake, so this becomes
	 * its own door, listed above Page Performance. That is one more menu item
	 * than the 6-item IA target: a conscious trade, because the alternative was
	 * either hiding the screen that answers the complaint, or hiding Page
	 * Performance and stripping Authors of the only performance view they can
	 * reach (Reports needs a higher capability — see self::CAP).
	 */
	public static function register_admin_page() {
		add_submenu_page(
			'cc-assistant',
			__( 'Reports', 'cc-assistant' ),
			__( 'Reports', 'cc-assistant' ),
			self::CAP,
			self::MENU_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'cc-assistant' ) );
		}

		$days = self::sanitize_days( isset( $_GET['cc_days'] ) ? $_GET['cc_days'] : null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only report filter.
		$view = isset( $_GET['cc_view'] ) ? sanitize_key( $_GET['cc_view'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $view, array( 'overview', 'pages', 'activity' ), true ) ) {
			$view = 'overview';
		}
		$drill_id = isset( $_GET['cc_post'] ) ? (int) $_GET['cc_post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$health = self::data_health( $days );

		$summary  = null;
		$pages    = array();
		$activity = array();
		$drill    = null;

		if ( $drill_id > 0 ) {
			$drill = self::page_report( $drill_id, $days );
		} elseif ( 'pages' === $view ) {
			$pages = self::pages( $days );
		} elseif ( 'activity' === $view ) {
			$activity = self::edit_activity( $days );
		} else {
			$summary  = self::summary( $days );
			$pages    = array_slice( self::pages( $days ), 0, 10 );
			$activity = array_slice( self::edit_activity( $days ), 0, 10 );
		}

		include CC_ASSISTANT_DIR . 'admin/views/reports.php';
	}

	public static function sanitize_days( $raw ) {
		$days = (int) $raw;
		if ( ! array_key_exists( $days, self::ranges() ) ) {
			$days = self::DEFAULT_DAYS;
		}
		return $days;
	}

	/* ---------------------------------------------------------------------
	 * Data health — why a report might legitimately be empty.
	 * ------------------------------------------------------------------ */

	/**
	 * Everything needed to render an honest empty state. The operator should
	 * never have to guess whether "0" means "no traffic" or "no data".
	 *
	 * @return array {
	 *   @type bool   $connected     GSC OAuth tokens present.
	 *   @type bool   $configured    Client id/secret saved.
	 *   @type bool   $has_rows      Any rows at all in the cache table.
	 *   @type bool   $covers_window Cache actually reaches into the window.
	 *   @type string $latest_date   Newest date present (Y-m-d) or ''.
	 *   @type int    $lag_days      Days between latest_date and today.
	 *   @type bool   $ready         Safe to render numbers.
	 *   @type string $reason        Machine key for the blocking condition.
	 * }
	 */
	public static function data_health( $days = self::DEFAULT_DAYS ) {
		global $wpdb;

		$out = array(
			'connected'     => false,
			'configured'    => false,
			'has_rows'      => false,
			'covers_window' => false,
			'latest_date'   => '',
			'earliest_date' => '',
			'lag_days'      => 0,
			'rows_cached'   => 0,
			'last_sync_at'  => null,
			'last_error'    => null,
			'property'      => '',
			'ready'         => false,
			'reason'        => 'ok',
		);

		if ( ! class_exists( 'CC_Assistant_GSC' ) ) {
			$out['reason'] = 'not_configured';
			return $out;
		}

		$status              = CC_Assistant_GSC::status();
		$out['connected']    = ! empty( $status['connected'] );
		$out['configured']   = ! empty( $status['configured'] );
		$out['rows_cached']  = isset( $status['rows_cached'] ) ? (int) $status['rows_cached'] : 0;
		$out['last_sync_at'] = isset( $status['last_sync_at'] ) ? $status['last_sync_at'] : null;
		$out['last_error']   = isset( $status['last_error'] ) ? $status['last_error'] : null;
		$out['property']     = isset( $status['property'] ) ? (string) $status['property'] : '';

		if ( ! $out['configured'] ) {
			$out['reason'] = 'not_configured';
			return $out;
		}
		if ( ! $out['connected'] ) {
			$out['reason'] = 'not_connected';
			return $out;
		}

		$table = $wpdb->prefix . 'cc_gsc_queries';
		$bounds = $wpdb->get_row( "SELECT MIN(date) AS earliest, MAX(date) AS latest FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a trusted prefix concat.

		if ( empty( $bounds['latest'] ) ) {
			$out['reason'] = 'no_rows';
			return $out;
		}

		$out['has_rows']      = true;
		$out['latest_date']   = (string) $bounds['latest'];
		$out['earliest_date'] = (string) $bounds['earliest'];

		$latest_ts       = strtotime( $out['latest_date'] . ' 00:00:00 UTC' );
		$today_ts        = strtotime( gmdate( 'Y-m-d' ) . ' 00:00:00 UTC' );
		$out['lag_days'] = $latest_ts ? max( 0, (int) round( ( $today_ts - $latest_ts ) / DAY_IN_SECONDS ) ) : 0;

		$window_start          = self::window_start( $days );
		$out['covers_window']  = ( $out['latest_date'] >= $window_start );
		$out['ready']          = $out['covers_window'];
		$out['reason']         = $out['ready'] ? 'ok' : 'window_empty';

		return $out;
	}

	public static function window_start( $days ) {
		return gmdate( 'Y-m-d', time() - ( (int) $days * DAY_IN_SECONDS ) );
	}

	/* ---------------------------------------------------------------------
	 * Aggregate ("in sum")
	 * ------------------------------------------------------------------ */

	/**
	 * Site-wide totals for the window, with the immediately-preceding window of
	 * the same length as the comparison. This is the number the operator was
	 * missing entirely: there was no "how is the site doing" figure anywhere.
	 */
	public static function summary( $days = self::DEFAULT_DAYS ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'cc_gsc_queries';
		$start   = self::window_start( $days );
		$p_start = self::window_start( $days * 2 );

		$sql = "SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions,
					SUM(position*impressions) AS pos_weight,
					COUNT(DISTINCT page) AS pages,
					COUNT(DISTINCT query) AS queries
				FROM {$table} WHERE date >= %s";

		$cur = $wpdb->get_row( $wpdb->prepare( $sql, $start ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$prev = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions,
					SUM(position*impressions) AS pos_weight,
					COUNT(DISTINCT page) AS pages,
					COUNT(DISTINCT query) AS queries
				FROM {$table} WHERE date >= %s AND date < %s",
				$p_start,
				$start
			),
			ARRAY_A
		);

		$mk = function ( $row ) {
			$clicks = isset( $row['clicks'] ) ? (int) $row['clicks'] : 0;
			$imp    = isset( $row['impressions'] ) ? (int) $row['impressions'] : 0;
			$weight = isset( $row['pos_weight'] ) ? (float) $row['pos_weight'] : 0.0;
			return array(
				'clicks'      => $clicks,
				'impressions' => $imp,
				'ctr'         => $imp > 0 ? ( $clicks / $imp ) : 0.0,
				'position'    => $imp > 0 ? ( $weight / $imp ) : 0.0,
				'pages'       => isset( $row['pages'] ) ? (int) $row['pages'] : 0,
				'queries'     => isset( $row['queries'] ) ? (int) $row['queries'] : 0,
			);
		};

		$current  = $mk( is_array( $cur ) ? $cur : array() );
		$previous = $mk( is_array( $prev ) ? $prev : array() );

		return array(
			'days'      => (int) $days,
			'start'     => $start,
			'end'       => gmdate( 'Y-m-d' ),
			'current'   => $current,
			'previous'  => $previous,
			'delta'     => array(
				'clicks'      => $current['clicks'] - $previous['clicks'],
				'impressions' => $current['impressions'] - $previous['impressions'],
				'ctr'         => $current['ctr'] - $previous['ctr'],
				// Position: lower is better, so invert the sign for display sanity.
				'position'    => $previous['position'] > 0 ? ( $previous['position'] - $current['position'] ) : 0.0,
			),
			'edits'     => self::edit_counts( $days ),
			'leads'     => self::lead_total( $days ),
		);
	}

	/** Applied-edit counts for the window, by change_type. */
	public static function edit_counts( $days = self::DEFAULT_DAYS ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_edits';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( (int) $days * DAY_IN_SECONDS ) );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE applied_at >= %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM {$table} WHERE applied_at >= %s AND post_id IS NOT NULL", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$by_type = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT change_type, COUNT(*) AS n FROM {$table} WHERE applied_at >= %s GROUP BY change_type ORDER BY n DESC",
				$cutoff
			),
			ARRAY_A
		);

		return array(
			'total'   => $total,
			'posts'   => $posts,
			'by_type' => is_array( $by_type ) ? $by_type : array(),
		);
	}

	/**
	 * Lead events in the window. Returns null when the table is absent (the
	 * feature needs Elementor Pro) so the view can hide the tile rather than
	 * print a misleading zero.
	 */
	public static function lead_total( $days = self::DEFAULT_DAYS ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_lead_events';
		if ( ! self::table_exists( $table ) ) {
			return null;
		}
		$start = self::window_start( $days );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(count),0) FROM {$table} WHERE event_date >= %s", $start ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function table_exists( $table ) {
		global $wpdb;
		static $cache = array();
		if ( isset( $cache[ $table ] ) ) {
			return $cache[ $table ];
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$cache[ $table ] = ( $found === $table );
		return $cache[ $table ];
	}

	/* ---------------------------------------------------------------------
	 * Per-page ("individually")
	 * ------------------------------------------------------------------ */

	/**
	 * One row per page that has GSC data in the window, enriched with how many
	 * edits we applied to it and how many leads it produced. Driven off the GSC
	 * table (not the post list) so pages with traffic always appear, and joined
	 * back to posts where the URL resolves.
	 */
	public static function pages( $days = self::DEFAULT_DAYS, $limit = self::MAX_ROWS ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'cc_gsc_queries';
		$start   = self::window_start( $days );
		$p_start = self::window_start( $days * 2 );
		$limit   = max( 1, min( self::MAX_ROWS, (int) $limit ) );

		$cur = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT page, SUM(clicks) AS clicks, SUM(impressions) AS impressions,
					SUM(position*impressions) AS pos_weight
				FROM {$table}
				WHERE date >= %s
				GROUP BY page
				ORDER BY clicks DESC, impressions DESC
				LIMIT %d",
				$start,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $cur ) ) {
			return array();
		}

		$prev_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT page, SUM(clicks) AS clicks, SUM(impressions) AS impressions,
					SUM(position*impressions) AS pos_weight
				FROM {$table}
				WHERE date >= %s AND date < %s
				GROUP BY page",
				$p_start,
				$start
			),
			ARRAY_A
		);
		$prev = array();
		foreach ( (array) $prev_rows as $r ) {
			$prev[ $r['page'] ] = $r;
		}

		$url_map    = self::url_to_post_map();
		$edit_map   = self::edits_per_post( $days );
		$lead_map   = self::leads_per_post( $days );

		$out = array();
		foreach ( $cur as $r ) {
			$url     = (string) $r['page'];
			$clicks  = (int) $r['clicks'];
			$imp     = (int) $r['impressions'];
			$pos     = $imp > 0 ? ( (float) $r['pos_weight'] / $imp ) : 0.0;

			$p_clicks = 0;
			$p_imp    = 0;
			$p_pos    = 0.0;
			if ( isset( $prev[ $url ] ) ) {
				$p_clicks = (int) $prev[ $url ]['clicks'];
				$p_imp    = (int) $prev[ $url ]['impressions'];
				$p_pos    = $p_imp > 0 ? ( (float) $prev[ $url ]['pos_weight'] / $p_imp ) : 0.0;
			}

			$post_id = isset( $url_map[ $url ] ) ? (int) $url_map[ $url ] : 0;
			$title   = '';
			if ( $post_id > 0 ) {
				$post  = get_post( $post_id );
				$title = $post ? $post->post_title : '';
			}

			$out[] = array(
				'post_id'          => $post_id,
				'title'            => $title,
				'url'              => $url,
				'clicks'           => $clicks,
				'impressions'      => $imp,
				'ctr'              => $imp > 0 ? ( $clicks / $imp ) : 0.0,
				'position'         => $pos,
				'prev_clicks'      => $p_clicks,
				'prev_impressions' => $p_imp,
				'prev_position'    => $p_pos,
				'clicks_delta'     => $clicks - $p_clicks,
				// Lower position is better: positive means improved.
				'position_delta'   => ( $p_pos > 0 && $pos > 0 ) ? ( $p_pos - $pos ) : null,
				'edits'            => ( $post_id > 0 && isset( $edit_map[ $post_id ] ) ) ? (int) $edit_map[ $post_id ] : 0,
				'leads'            => ( $post_id > 0 && isset( $lead_map[ $post_id ] ) ) ? (int) $lead_map[ $post_id ] : 0,
			);
		}

		return $out;
	}

	/**
	 * Full report for ONE page: totals, its queries, its applied edits, and its
	 * leads. This is the "see a report individually" the operator asked for —
	 * previously the drilldown showed a query list and nothing else.
	 */
	public static function page_report( $post_id, $days = self::DEFAULT_DAYS ) {
		global $wpdb;

		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		// get_post() happily returns drafts, private posts, and other authors'
		// work. Never build a report — or an export — for something this user
		// is not allowed to read.
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return null;
		}

		$urls = self::urls_for_post( $post_id );
		$table = $wpdb->prefix . 'cc_gsc_queries';
		$start = self::window_start( $days );
		$p_start = self::window_start( $days * 2 );

		$totals  = array( 'clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0 );
		$prev    = array( 'clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0 );
		$queries = array();

		if ( ! empty( $urls ) ) {
			$ph   = implode( ',', array_fill( 0, count( $urls ), '%s' ) );
			$args = array_merge( array( $start ), $urls );

			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position*impressions) AS pos_weight
					 FROM {$table} WHERE date >= %s AND page IN ($ph)",
					$args
				),
				ARRAY_A
			);
			if ( $row ) {
				$totals['clicks']      = (int) $row['clicks'];
				$totals['impressions'] = (int) $row['impressions'];
				$totals['ctr']         = $totals['impressions'] > 0 ? ( $totals['clicks'] / $totals['impressions'] ) : 0.0;
				$totals['position']    = $totals['impressions'] > 0 ? ( (float) $row['pos_weight'] / $totals['impressions'] ) : 0.0;
			}

			$prow = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position*impressions) AS pos_weight
					 FROM {$table} WHERE date >= %s AND date < %s AND page IN ($ph)",
					array_merge( array( $p_start, $start ), $urls )
				),
				ARRAY_A
			);
			if ( $prow ) {
				$prev['clicks']      = (int) $prow['clicks'];
				$prev['impressions'] = (int) $prow['impressions'];
				$prev['ctr']         = $prev['impressions'] > 0 ? ( $prev['clicks'] / $prev['impressions'] ) : 0.0;
				$prev['position']    = $prev['impressions'] > 0 ? ( (float) $prow['pos_weight'] / $prev['impressions'] ) : 0.0;
			}

			$queries = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT query, SUM(clicks) AS clicks, SUM(impressions) AS impressions,
						SUM(position*impressions)/NULLIF(SUM(impressions),0) AS position
					 FROM {$table}
					 WHERE date >= %s AND page IN ($ph) AND query <> ''
					 GROUP BY query
					 ORDER BY impressions DESC
					 LIMIT 100",
					$args
				),
				ARRAY_A
			);
		}

		return array(
			'post_id'  => $post_id,
			'title'    => $post->post_title,
			'permalink'=> get_permalink( $post_id ),
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'days'     => (int) $days,
			'totals'   => $totals,
			'previous' => $prev,
			'queries'  => is_array( $queries ) ? $queries : array(),
			'edits'    => self::edits_for_post( $post_id, $days ),
			'leads'    => self::leads_for_post( $post_id, $days ),
			'urls'     => $urls,
		);
	}

	/* ---------------------------------------------------------------------
	 * Edit activity
	 * ------------------------------------------------------------------ */

	/**
	 * Applied edits grouped BY POST for the window.
	 *
	 * Grouping is the point. A 39-change batch on one page previously rendered
	 * as 39 near-identical rows that pushed every other page out of the visible
	 * window — the operator could not see that anything else had happened. One
	 * row per post, with the change count, fixes that.
	 */
	public static function edit_activity( $days = self::DEFAULT_DAYS, $limit = self::MAX_ROWS ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'cc_edits';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( (int) $days * DAY_IN_SECONDS ) );
		$limit  = max( 1, min( self::MAX_ROWS, (int) $limit ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT post_id,
					COUNT(*) AS edit_count,
					MIN(applied_at) AS first_applied,
					MAX(applied_at) AS last_applied,
					COUNT(DISTINCT change_type) AS type_count
				 FROM {$table}
				 WHERE applied_at >= %s AND post_id IS NOT NULL AND post_id > 0
				 GROUP BY post_id
				 ORDER BY last_applied DESC
				 LIMIT %d",
				$cutoff,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$lead_map = self::leads_per_post( $days );

		$out = array();
		foreach ( $rows as $r ) {
			$post_id = (int) $r['post_id'];
			$post    = get_post( $post_id );

			$out[] = array(
				'post_id'       => $post_id,
				'title'         => $post ? $post->post_title : sprintf( /* translators: %d: post id */ __( 'Post %d (deleted)', 'cc-assistant' ), $post_id ),
				'exists'        => (bool) $post,
				'permalink'     => $post ? get_permalink( $post_id ) : '',
				'edit_count'    => (int) $r['edit_count'],
				'type_count'    => (int) $r['type_count'],
				'first_applied' => (string) $r['first_applied'],
				'last_applied'  => (string) $r['last_applied'],
				'leads'         => isset( $lead_map[ $post_id ] ) ? (int) $lead_map[ $post_id ] : 0,
				'days_since'    => self::days_since( (string) $r['last_applied'] ),
			);
		}

		return $out;
	}

	/** Individual edit rows for one post inside the window. */
	public static function edits_for_post( $post_id, $days = self::DEFAULT_DAYS ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_edits';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( (int) $days * DAY_IN_SECONDS ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT id, change_type, change_summary, applied_at, applied_by
				 FROM {$table}
				 WHERE post_id = %d AND applied_at >= %s
				 ORDER BY applied_at DESC
				 LIMIT 200",
				(int) $post_id,
				$cutoff
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	private static function edits_per_post( $days ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_edits';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( (int) $days * DAY_IN_SECONDS ) );
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT post_id, COUNT(*) AS n FROM {$table} WHERE applied_at >= %s AND post_id IS NOT NULL GROUP BY post_id",
				$cutoff
			),
			ARRAY_A
		);
		$map = array();
		foreach ( (array) $rows as $r ) {
			$map[ (int) $r['post_id'] ] = (int) $r['n'];
		}
		return $map;
	}

	private static function leads_per_post( $days ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_lead_events';
		if ( ! self::table_exists( $table ) ) {
			return array();
		}
		$start = self::window_start( $days );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT post_id, SUM(count) AS n FROM {$table} WHERE event_date >= %s GROUP BY post_id",
				$start
			),
			ARRAY_A
		);
		$map = array();
		foreach ( (array) $rows as $r ) {
			$map[ (int) $r['post_id'] ] = (int) $r['n'];
		}
		return $map;
	}

	private static function leads_for_post( $post_id, $days ) {
		$map = self::leads_per_post( $days );
		return isset( $map[ (int) $post_id ] ) ? (int) $map[ (int) $post_id ] : 0;
	}

	private static function days_since( $gmt_datetime ) {
		if ( empty( $gmt_datetime ) ) {
			return null;
		}
		$ts = strtotime( $gmt_datetime . ' UTC' );
		if ( ! $ts ) {
			return null;
		}
		return max( 0, (int) floor( ( time() - $ts ) / DAY_IN_SECONDS ) );
	}

	/* ---------------------------------------------------------------------
	 * URL <-> post mapping
	 * ------------------------------------------------------------------ */

	/**
	 * Reuse the Performance tracker's cached URL map when it is available so
	 * both screens resolve URLs identically; fall back to a local build.
	 */
	private static function url_to_post_map() {
		if ( class_exists( 'CC_Assistant_Performance_Tracker' ) && method_exists( 'CC_Assistant_Performance_Tracker', 'get_url_to_post_id_map' ) ) {
			$map = CC_Assistant_Performance_Tracker::get_url_to_post_id_map();
			if ( is_array( $map ) ) {
				return $map;
			}
		}
		return array();
	}

	/** Every GSC-shaped URL that could belong to this post. */
	private static function urls_for_post( $post_id ) {
		$urls = array();
		$permalink = get_permalink( (int) $post_id );
		if ( $permalink ) {
			$urls[] = $permalink;
			// GSC stores the canonical it saw; tolerate the trailing-slash twin.
			$urls[] = ( '/' === substr( $permalink, -1 ) ) ? rtrim( $permalink, '/' ) : $permalink . '/';
		}
		foreach ( self::url_to_post_map() as $url => $pid ) {
			if ( (int) $pid === (int) $post_id ) {
				$urls[] = $url;
			}
		}
		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/* ---------------------------------------------------------------------
	 * CSV export — the plugin's first.
	 * ------------------------------------------------------------------ */

	public static function export_url( $report, $days ) {
		return wp_nonce_url(
			admin_url(
				'admin-post.php?action=' . self::EXPORT_ACTION
				. '&cc_report=' . rawurlencode( $report )
				. '&cc_days=' . (int) $days
			),
			self::NONCE
		);
	}

	/**
	 * Stream a CSV. Deliberately streams to php://output rather than building a
	 * string so a 500-row export never doubles memory, and sends no-cache
	 * headers so a re-export after a sync is never served stale.
	 */
	public static function handle_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to export reports.', 'cc-assistant' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$report = isset( $_GET['cc_report'] ) ? sanitize_key( $_GET['cc_report'] ) : 'pages';
		$days   = self::sanitize_days( isset( $_GET['cc_days'] ) ? $_GET['cc_days'] : null );
		$post_id = isset( $_GET['cc_post'] ) ? (int) $_GET['cc_post'] : 0;

		// sanitize_title already strips CR/LF and quotes, so the filename cannot
		// break out of the Content-Disposition header; sanitize_file_name is a
		// second belt for any character it lets through on odd locales.
		$site = sanitize_title( get_bloginfo( 'name' ) );
		if ( '' === $site ) {
			$site = 'site';
		}
		$filename = sanitize_file_name( sprintf( 'cc-%s-%s-%dd-%s.csv', $site, $report, $days, gmdate( 'Ymd' ) ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Could not open the output stream for export.', 'cc-assistant' ) );
		}

		// UTF-8 BOM so Excel opens accented titles correctly instead of mojibake.
		fwrite( $out, "\xEF\xBB\xBF" );

		switch ( $report ) {
			case 'summary':
				self::export_summary( $out, $days );
				break;
			case 'activity':
				self::export_activity( $out, $days );
				break;
			case 'page':
				self::export_page( $out, $post_id, $days );
				break;
			case 'pages':
			default:
				self::export_pages( $out, $days );
				break;
		}

		fclose( $out );
		exit;
	}

	private static function export_summary( $out, $days ) {
		$s = self::summary( $days );
		self::putrow( $out, array( 'Report', 'Site summary' ) );
		self::putrow( $out, array( 'Window', sprintf( '%d days (%s to %s)', $s['days'], $s['start'], $s['end'] ) ) );
		self::putrow( $out, array( 'Generated (UTC)', gmdate( 'Y-m-d H:i:s' ) ) );
		self::putrow( $out, array() );
		self::putrow( $out, array( 'Metric', 'Current', 'Previous period', 'Change' ) );
		self::putrow( $out, array( 'Clicks', $s['current']['clicks'], $s['previous']['clicks'], $s['delta']['clicks'] ) );
		self::putrow( $out, array( 'Impressions', $s['current']['impressions'], $s['previous']['impressions'], $s['delta']['impressions'] ) );
		self::putrow( $out, array( 'CTR', round( $s['current']['ctr'] * 100, 2 ) . '%', round( $s['previous']['ctr'] * 100, 2 ) . '%', round( $s['delta']['ctr'] * 100, 2 ) . '%' ) );
		self::putrow( $out, array( 'Avg position', round( $s['current']['position'], 1 ), round( $s['previous']['position'], 1 ), round( $s['delta']['position'], 1 ) ) );
		self::putrow( $out, array( 'Pages with data', $s['current']['pages'], $s['previous']['pages'], $s['current']['pages'] - $s['previous']['pages'] ) );
		self::putrow( $out, array( 'Queries', $s['current']['queries'], $s['previous']['queries'], $s['current']['queries'] - $s['previous']['queries'] ) );
		self::putrow( $out, array() );
		self::putrow( $out, array( 'Changes applied', $s['edits']['total'] ) );
		self::putrow( $out, array( 'Pages changed', $s['edits']['posts'] ) );
		if ( null !== $s['leads'] ) {
			self::putrow( $out, array( 'Form leads', $s['leads'] ) );
		}
		if ( ! empty( $s['edits']['by_type'] ) ) {
			self::putrow( $out, array() );
			self::putrow( $out, array( 'Change type', 'Count' ) );
			foreach ( $s['edits']['by_type'] as $t ) {
				self::putrow( $out, array( self::type_label( $t['change_type'] ), (int) $t['n'] ) );
			}
		}
	}

	private static function export_pages( $out, $days ) {
		self::putrow(
			$out,
			array(
				'Page title', 'URL', 'Clicks', 'Impressions', 'CTR %', 'Avg position',
				'Prev clicks', 'Prev impressions', 'Prev position',
				'Clicks change', 'Position change', 'Changes applied', 'Leads',
			)
		);
		foreach ( self::pages( $days ) as $r ) {
			self::putrow(
				$out,
				array(
					$r['title'],
					$r['url'],
					$r['clicks'],
					$r['impressions'],
					round( $r['ctr'] * 100, 2 ),
					$r['position'] > 0 ? round( $r['position'], 1 ) : '',
					$r['prev_clicks'],
					$r['prev_impressions'],
					$r['prev_position'] > 0 ? round( $r['prev_position'], 1 ) : '',
					$r['clicks_delta'],
					null === $r['position_delta'] ? '' : round( $r['position_delta'], 1 ),
					$r['edits'],
					$r['leads'],
				)
			);
		}
	}

	private static function export_activity( $out, $days ) {
		self::putrow( $out, array( 'Page title', 'Post ID', 'URL', 'Changes applied', 'Change types', 'First applied (UTC)', 'Last applied (UTC)', 'Days since last', 'Leads' ) );
		foreach ( self::edit_activity( $days ) as $r ) {
			self::putrow(
				$out,
				array(
					$r['title'],
					$r['post_id'],
					$r['permalink'],
					$r['edit_count'],
					$r['type_count'],
					$r['first_applied'],
					$r['last_applied'],
					null === $r['days_since'] ? '' : $r['days_since'],
					$r['leads'],
				)
			);
		}
	}

	private static function export_page( $out, $post_id, $days ) {
		$rep = self::page_report( $post_id, $days );
		if ( ! $rep ) {
			self::putrow( $out, array( 'Error', 'Post not found' ) );
			return;
		}
		self::putrow( $out, array( 'Report', 'Page report' ) );
		self::putrow( $out, array( 'Page', $rep['title'] ) );
		self::putrow( $out, array( 'URL', $rep['permalink'] ) );
		self::putrow( $out, array( 'Window', sprintf( '%d days', $rep['days'] ) ) );
		self::putrow( $out, array( 'Generated (UTC)', gmdate( 'Y-m-d H:i:s' ) ) );
		self::putrow( $out, array() );
		self::putrow( $out, array( 'Metric', 'Current', 'Previous period' ) );
		self::putrow( $out, array( 'Clicks', $rep['totals']['clicks'], $rep['previous']['clicks'] ) );
		self::putrow( $out, array( 'Impressions', $rep['totals']['impressions'], $rep['previous']['impressions'] ) );
		self::putrow( $out, array( 'CTR %', round( $rep['totals']['ctr'] * 100, 2 ), round( $rep['previous']['ctr'] * 100, 2 ) ) );
		self::putrow( $out, array( 'Avg position', round( $rep['totals']['position'], 1 ), round( $rep['previous']['position'], 1 ) ) );
		self::putrow( $out, array( 'Leads', $rep['leads'], '' ) );
		self::putrow( $out, array() );
		self::putrow( $out, array( 'Query', 'Clicks', 'Impressions', 'CTR %', 'Avg position' ) );
		foreach ( $rep['queries'] as $q ) {
			$imp = (int) $q['impressions'];
			self::putrow(
				$out,
				array(
					$q['query'],
					(int) $q['clicks'],
					$imp,
					$imp > 0 ? round( ( (int) $q['clicks'] / $imp ) * 100, 2 ) : 0,
					round( (float) $q['position'], 1 ),
				)
			);
		}
		self::putrow( $out, array() );
		self::putrow( $out, array( 'Applied changes' ) );
		self::putrow( $out, array( 'Applied (UTC)', 'Type', 'Summary' ) );
		foreach ( $rep['edits'] as $e ) {
			self::putrow( $out, array( $e['applied_at'], self::type_label( $e['change_type'] ), $e['change_summary'] ) );
		}
	}

	/**
	 * Write one CSV row with every cell neutralised against formula injection.
	 *
	 * Page titles and change summaries are author-supplied text. A title that
	 * begins with = + - @ (or a tab / carriage return, which Excel strips back
	 * to the leading character) is interpreted as a FORMULA when the export is
	 * opened in Excel, LibreOffice, or Google Sheets — the classic CSV-injection
	 * path to DDE command execution on the reviewer's machine. Prefixing a
	 * single quote forces the cell to text; every spreadsheet honours it and
	 * hides the quote in the cell view.
	 *
	 * Applied centrally so no future column can forget it.
	 */
	private static function putrow( $handle, array $row ) {
		fputcsv( $handle, array_map( array( __CLASS__, 'csv_cell' ), $row ) );
	}

	public static function csv_cell( $value ) {
		if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) || null === $value ) {
			return $value;
		}
		$value = (string) $value;
		if ( '' === $value ) {
			return $value;
		}
		// Leading whitespace/control chars are stripped by spreadsheets before
		// the formula test, so test the first NON-stripped character.
		$probe = ltrim( $value, "\t\r\n " );
		if ( '' !== $probe && false !== strpos( "=+-@", $probe[0] ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/** Human label for a change_type slug, reusing the admin helper when loaded. */
	public static function type_label( $slug ) {
		if ( function_exists( 'cc_change_type_label' ) ) {
			return cc_change_type_label( $slug );
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $slug ) );
	}
}
