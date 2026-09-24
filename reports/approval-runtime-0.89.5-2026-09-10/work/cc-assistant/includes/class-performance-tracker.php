<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-post / per-page GSC performance tracker (v0.32.0).
 *
 * Adds two views on top of the existing wp_cc_gsc_queries table:
 *
 *   1. Inline columns on the wp-admin Posts / Pages list — sortable, with
 *      a 7-day clicks / impressions / position snapshot and a 7d-vs-prior-7d
 *      impression trend arrow.
 *
 *   2. A dedicated "Page Performance" admin page (CC Assistant → Page
 *      Performance) with a fuller table: configurable date range, sortable
 *      columns, top-query per page, CTR, and a per-row "queries" drilldown
 *      that lists every query the page ranks for in the selected window.
 *
 * No new data fetching — all aggregation runs against the already-synced
 * wp_cc_gsc_queries table. Per-request caches avoid the N+1 problem on the
 * Posts list (one bulk SELECT for the currently-visible post IDs, then column
 * renders read from a static map).
 *
 * Loads ONLY on admin requests, and only does meaningful work on the Posts /
 * Pages list screen or the dedicated performance page. Front-end pays nothing.
 */
class CC_Assistant_Performance_Tracker {

	const MENU_SLUG        = 'cc-assistant-performance';
	const URL_MAP_TTL      = HOUR_IN_SECONDS;
	const DEFAULT_DAYS     = 7;
	const TREND_THRESHOLD  = 0.10; // 10% impression change triggers ▲ / ▼.
	const URL_MAP_MAX_POSTS = 5000; // Hard cap on URL→post_id map size to
	                                // protect the transient + memory on very
	                                // large sites (1 KB per entry ≈ 5 MB cap).

	/**
	 * Per-request cache: post_id => metrics array. Filled by prefetch on
	 * Posts/Pages list load so column callbacks can render from memory.
	 */
	private static $metrics_cache = array();

	/**
	 * Per-request URL → post_id map. Built lazily, transient-cached for 1 hour.
	 */
	private static $url_to_post_id = null;

	public static function init() {
		// Posts/Pages list columns.
		add_filter( 'manage_posts_columns', array( __CLASS__, 'add_columns' ) );
		add_filter( 'manage_pages_columns', array( __CLASS__, 'add_columns' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );

		// NOTE: columns intentionally NOT marked sortable. Sorting by an aggregate
		// of a join'd table requires an expensive posts_clauses handler with a
		// JOIN + GROUP BY rewrite. Cheaper + cleaner UX: point users at the
		// dedicated dashboard (CC Assistant → Page Performance) which sorts the
		// already-aggregated row set in PHP.

		// Prefetch metrics once per list page load (before columns render).
		add_action( 'pre_get_posts', array( __CLASS__, 'maybe_prefetch_for_listing' ) );

		// Submenu registration.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 15 );
	}

	/**
	 * Register the dedicated dashboard page under CC Assistant menu.
	 */
	public static function register_admin_page() {
		// Stays VISIBLE. Reports (v0.64.0) sits above it and is the better entry
		// point, but Reports requires edit_others_posts while this screen runs at
		// edit_posts — hiding this one would silently strip Authors and
		// Contributors of the only performance screen they can reach.
		add_submenu_page(
			'cc-assistant',
			__( 'Page Performance', 'cc-assistant' ),
			__( 'Page Performance', 'cc-assistant' ),
			'edit_posts',
			self::MENU_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'cc-assistant' ) );
		}
		$days = isset( $_GET['cc_days'] ) ? max( 1, min( 90, (int) $_GET['cc_days'] ) ) : self::DEFAULT_DAYS;
		$sort = isset( $_GET['cc_sort'] ) ? sanitize_key( $_GET['cc_sort'] ) : 'clicks';
		$dir  = isset( $_GET['cc_dir'] ) && 'asc' === $_GET['cc_dir'] ? 'asc' : 'desc';
		$trend_filter = isset( $_GET['cc_trend'] ) ? sanitize_key( $_GET['cc_trend'] ) : 'all';

		$rows = self::get_all_post_metrics( $days, $sort, $dir, 200 );

		// Apply trend filter in PHP (small dataset, simpler than SQL gymnastics).
		if ( 'all' !== $trend_filter ) {
			$rows = array_filter(
				$rows,
				function ( $r ) use ( $trend_filter ) {
					return $r['trend'] === $trend_filter;
				}
			);
		}

		include CC_ASSISTANT_DIR . 'admin/views/page-performance.php';
	}

	/**
	 * Add the 4 new columns to the Posts / Pages list. Inserted right after
	 * the Title column so they're visible without horizontal scroll.
	 */
	public static function add_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['cc_clicks'] = __( 'Clicks (7d)', 'cc-assistant' );
				$new['cc_imp']    = __( 'Impr (7d)', 'cc-assistant' );
				$new['cc_pos']    = __( 'Pos (7d)', 'cc-assistant' );
				$new['cc_trend']  = __( 'Trend', 'cc-assistant' );
			}
		}
		return $new;
	}

	/**
	 * Render one of our columns. Reads from the prefetch cache; if not
	 * prefetched (e.g. quick-edit AJAX), falls back to a single-post query.
	 */
	public static function render_column( $column, $post_id ) {
		if ( ! in_array( $column, array( 'cc_clicks', 'cc_imp', 'cc_pos', 'cc_trend' ), true ) ) {
			return;
		}
		if ( ! isset( self::$metrics_cache[ $post_id ] ) ) {
			self::$metrics_cache[ $post_id ] = self::get_post_metrics_with_delta( $post_id, self::DEFAULT_DAYS );
		}
		$m = self::$metrics_cache[ $post_id ];

		switch ( $column ) {
			case 'cc_clicks':
				echo (int) $m['clicks'];
				break;
			case 'cc_imp':
				echo (int) $m['impressions'];
				break;
			case 'cc_pos':
				if ( $m['impressions'] <= 0 ) {
					echo '—';
				} else {
					$pos_str = esc_html( number_format( (float) $m['position'], 1 ) );
					$delta_render = self::render_position_delta(
						isset( $m['position_delta'] ) ? $m['position_delta'] : null
					);
					echo $pos_str . ' ' . $delta_render['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- delta html is built with esc_* helpers above.
				}
				break;
			case 'cc_trend':
				$arrow = self::trend_arrow( $m['trend'] );
				$tip   = sprintf(
					/* translators: 1: current impressions 2: prior impressions */
					__( '%1$d impr vs %2$d prior 7d', 'cc-assistant' ),
					(int) $m['impressions'],
					(int) $m['prev_impressions']
				);
				printf(
					'<span class="cc-trend cc-trend-%s" title="%s">%s</span>',
					esc_attr( $m['trend'] ),
					esc_attr( $tip ),
					$arrow
				);
				break;
		}
	}

	/**
	 * Called via pre_get_posts. Only does anything when we're on the
	 * Posts/Pages list admin screen. Bulk-fetches metrics for the visible
	 * post IDs in one query and stashes them in the cache.
	 */
	public static function maybe_prefetch_for_listing( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}
		// Defer the prefetch until after pre_get_posts has resolved the IDs
		// for the visible list. We hook the_posts to grab the actual ID set
		// without re-querying.
		add_filter( 'the_posts', array( __CLASS__, 'prefetch_from_posts' ), 10, 1 );
	}

	public static function prefetch_from_posts( $posts ) {
		remove_filter( 'the_posts', array( __CLASS__, 'prefetch_from_posts' ), 10 );
		if ( empty( $posts ) ) {
			return $posts;
		}
		$ids = array();
		foreach ( $posts as $p ) {
			if ( isset( $p->ID ) ) {
				$ids[] = (int) $p->ID;
			}
		}
		self::bulk_prefetch( $ids, self::DEFAULT_DAYS );
		return $posts;
	}

	/**
	 * Bulk metrics for an array of post IDs. One SELECT per period (current +
	 * prior). Caches results in self::$metrics_cache keyed by post_id.
	 */
	public static function bulk_prefetch( $post_ids, $days = 7 ) {
		global $wpdb;
		if ( empty( $post_ids ) ) {
			return;
		}
		$url_to_id = self::get_url_to_post_id_map();
		// Build reverse map: post_id => array of permalinks (a post may have
		// 1-2 canonical-ish URLs in GSC: with/without trailing slash).
		$id_to_urls = array();
		foreach ( $url_to_id as $url => $pid ) {
			if ( in_array( (int) $pid, $post_ids, true ) ) {
				$id_to_urls[ (int) $pid ][] = $url;
			}
		}
		if ( empty( $id_to_urls ) ) {
			// Initialize cache with zeros so we don't re-query on render.
			foreach ( $post_ids as $pid ) {
				self::$metrics_cache[ $pid ] = self::empty_metrics();
			}
			return;
		}

		$all_urls = array();
		foreach ( $id_to_urls as $urls ) {
			$all_urls = array_merge( $all_urls, $urls );
		}
		$all_urls = array_unique( $all_urls );

		$placeholders = implode( ',', array_fill( 0, count( $all_urls ), '%s' ) );
		$table        = $wpdb->prefix . 'cc_gsc_queries';

		$current_start = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );
		$prior_start   = gmdate( 'Y-m-d', strtotime( '-' . ( 2 * (int) $days ) . ' days' ) );
		$prior_end     = $current_start;

		// Current period.
		$current_sql = $wpdb->prepare(
			"SELECT page,
				SUM(clicks) AS clicks,
				SUM(impressions) AS impressions,
				SUM(position*impressions) AS pos_weight
			FROM {$table}
			WHERE date >= %s AND page IN ($placeholders)
			GROUP BY page",
			array_merge( array( $current_start ), $all_urls )
		);
		$current_rows = $wpdb->get_results( $current_sql, ARRAY_A );

		// Prior period (same length, immediately preceding). Now also sums
		// pos_weight so we can compute prior_position + position_delta.
		$prior_sql = $wpdb->prepare(
			"SELECT page,
				SUM(clicks) AS clicks,
				SUM(impressions) AS impressions,
				SUM(position*impressions) AS pos_weight
			FROM {$table}
			WHERE date >= %s AND date < %s AND page IN ($placeholders)
			GROUP BY page",
			array_merge( array( $prior_start, $prior_end ), $all_urls )
		);
		$prior_rows = $wpdb->get_results( $prior_sql, ARRAY_A );

		// Index by URL for fast lookup.
		$current_by_url = array();
		foreach ( $current_rows as $r ) {
			$current_by_url[ $r['page'] ] = $r;
		}
		$prior_by_url = array();
		foreach ( $prior_rows as $r ) {
			$prior_by_url[ $r['page'] ] = $r;
		}

		// Walk requested post IDs, aggregate across their URLs, compute delta.
		foreach ( $post_ids as $pid ) {
			$pid = (int) $pid;
			if ( empty( $id_to_urls[ $pid ] ) ) {
				self::$metrics_cache[ $pid ] = self::empty_metrics();
				continue;
			}
			$clicks         = 0;
			$impressions    = 0;
			$pos_weight     = 0;
			$prev_clicks    = 0;
			$prev_imp       = 0;
			$prev_pos_weight = 0;
			foreach ( $id_to_urls[ $pid ] as $url ) {
				if ( isset( $current_by_url[ $url ] ) ) {
					$clicks      += (int) $current_by_url[ $url ]['clicks'];
					$impressions += (int) $current_by_url[ $url ]['impressions'];
					$pos_weight  += (float) $current_by_url[ $url ]['pos_weight'];
				}
				if ( isset( $prior_by_url[ $url ] ) ) {
					$prev_clicks    += (int) $prior_by_url[ $url ]['clicks'];
					$prev_imp       += (int) $prior_by_url[ $url ]['impressions'];
					$prev_pos_weight += (float) $prior_by_url[ $url ]['pos_weight'];
				}
			}
			$position      = $impressions > 0 ? $pos_weight / $impressions : 0;
			$prev_position = $prev_imp > 0 ? $prev_pos_weight / $prev_imp : 0;
			// Position delta: positive number = "got worse" (rank moved deeper);
			// negative number = "improved" (rank moved up). UI inverts the sign
			// when rendering so the arrow direction matches user intuition.
			$position_delta_raw = ( $impressions > 0 && $prev_imp > 0 )
				? ( $position - $prev_position )
				: null;
			$imp_delta = $impressions - $prev_imp;
			$trend     = self::classify_trend( $impressions, $prev_imp );
			self::$metrics_cache[ $pid ] = array(
				'clicks'             => $clicks,
				'impressions'        => $impressions,
				'position'           => $position,
				'prev_clicks'        => $prev_clicks,
				'prev_impressions'   => $prev_imp,
				'prev_position'      => $prev_position,
				'impression_delta'   => $imp_delta,
				'position_delta'     => $position_delta_raw, // null when prior or current has no impressions
				'trend'              => $trend,
				'ctr'                => $impressions > 0 ? $clicks / $impressions : 0,
			);
		}
	}

	/**
	 * Build (and cache) URL → post_id map for all published posts/pages.
	 * GSC's `page` column stores permalinks, so this lookup is the bridge
	 * to wp_posts. Cached as transient to avoid 78 get_permalink() calls
	 * per admin request.
	 */
	public static function get_url_to_post_id_map() {
		if ( null !== self::$url_to_post_id ) {
			return self::$url_to_post_id;
		}
		$cached = get_transient( 'cc_assistant_url_to_post_id' );
		if ( is_array( $cached ) ) {
			self::$url_to_post_id = $cached;
			return $cached;
		}

		$post_types = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		// numberposts capped at URL_MAP_MAX_POSTS so a site with 50k+ posts
		// does not balloon the transient or admin memory. Large sites still
		// get useful columns for their most-recent N posts; the dashboard
		// applies its own limit on top.
		$ids        = get_posts(
			array(
				'post_type'        => $post_types,
				'post_status'      => 'publish',
				'numberposts'      => self::URL_MAP_MAX_POSTS,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		$map = array();
		foreach ( $ids as $pid ) {
			$url = (string) get_permalink( $pid );
			if ( '' === $url ) {
				continue;
			}
			// Index both with-trailing-slash and without — GSC sometimes
			// reports the same canonical both ways.
			$canonical = untrailingslashit( $url );
			$map[ $canonical ]      = (int) $pid;
			$map[ $canonical . '/' ] = (int) $pid;
		}
		set_transient( 'cc_assistant_url_to_post_id', $map, self::URL_MAP_TTL );
		self::$url_to_post_id = $map;
		return $map;
	}

	/**
	 * Single-post metrics + prior-period delta. Used by render_column when the
	 * prefetch cache missed (rare — quick-edit AJAX or direct call).
	 */
	public static function get_post_metrics_with_delta( $post_id, $days = 7 ) {
		$post_id = (int) $post_id;
		self::bulk_prefetch( array( $post_id ), $days );
		return isset( self::$metrics_cache[ $post_id ] )
			? self::$metrics_cache[ $post_id ]
			: self::empty_metrics();
	}

	/**
	 * Site-wide table for the dashboard page. Returns array of rows enriched
	 * with post title, permalink, edit URL, and the top query for the period.
	 */
	public static function get_all_post_metrics( $days = 7, $sort = 'clicks', $dir = 'desc', $limit = 200 ) {
		$post_types = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$ids        = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'numberposts'    => $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'suppress_filters' => true,
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		self::bulk_prefetch( $ids, $days );
		$top_queries = self::get_top_queries_bulk( $ids, $days );

		$rows = array();
		foreach ( $ids as $pid ) {
			$pid = (int) $pid;
			$m   = isset( self::$metrics_cache[ $pid ] ) ? self::$metrics_cache[ $pid ] : self::empty_metrics();
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}
			$rows[] = array_merge(
				$m,
				array(
					'post_id'   => $pid,
					'title'     => $post->post_title,
					'permalink' => get_permalink( $pid ),
					'edit_url'  => get_edit_post_link( $pid, 'raw' ),
					'top_query' => isset( $top_queries[ $pid ] ) ? $top_queries[ $pid ] : null,
				)
			);
		}

		// Sort in PHP — small dataset, lets us mix any field including computed ones.
		$dir_mul = ( 'asc' === $dir ) ? 1 : -1;
		usort(
			$rows,
			function ( $a, $b ) use ( $sort, $dir_mul ) {
				$av = isset( $a[ $sort ] ) ? $a[ $sort ] : 0;
				$bv = isset( $b[ $sort ] ) ? $b[ $sort ] : 0;
				if ( $av === $bv ) {
					return 0;
				}
				return ( $av < $bv ? -1 : 1 ) * $dir_mul;
			}
		);

		return $rows;
	}

	/**
	 * For each post_id, return the single top query (most impressions) in the
	 * window. One SQL query for all posts.
	 */
	public static function get_top_queries_bulk( $post_ids, $days = 7 ) {
		global $wpdb;
		if ( empty( $post_ids ) ) {
			return array();
		}
		$url_to_id = self::get_url_to_post_id_map();
		$urls      = array();
		$url_to_pid = array();
		foreach ( $url_to_id as $url => $pid ) {
			if ( in_array( (int) $pid, $post_ids, true ) ) {
				$urls[]            = $url;
				$url_to_pid[ $url ] = (int) $pid;
			}
		}
		if ( empty( $urls ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $urls ), '%s' ) );
		$table        = $wpdb->prefix . 'cc_gsc_queries';
		$start        = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page, query, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
					SUM(position*impressions)/NULLIF(SUM(impressions),0) AS position
				FROM {$table}
				WHERE date >= %s AND page IN ($placeholders)
				GROUP BY page, query
				ORDER BY impressions DESC",
				array_merge( array( $start ), $urls )
			),
			ARRAY_A
		);

		$top = array();
		foreach ( $rows as $r ) {
			$pid = isset( $url_to_pid[ $r['page'] ] ) ? $url_to_pid[ $r['page'] ] : 0;
			if ( $pid <= 0 || isset( $top[ $pid ] ) ) {
				continue; // We've already captured the top (rows are pre-sorted desc).
			}
			$top[ $pid ] = array(
				'query'       => $r['query'],
				'impressions' => (int) $r['impressions'],
				'clicks'      => (int) $r['clicks'],
				'position'    => round( (float) $r['position'], 1 ),
			);
		}
		return $top;
	}

	/**
	 * v0.33.1 — Query-level position decay (and improvement) lens.
	 *
	 * Surfaces individual (page, query) pairs whose average position changed
	 * significantly between the current $days window and the prior $days
	 * window. This catches the gap that gsc_trends couldn't: queries that
	 * dropped in rank (e.g. pos 8 → pos 25) while still ranking, so they
	 * never appeared in the "lost" lens and didn't move enough total clicks
	 * to register as a page-level decay.
	 *
	 * The single SQL joins two GROUPed subqueries (current vs prior) at the
	 * (page, query) grain. Each side must have ≥ $min_impressions in the
	 * window so single-impression noise doesn't flood the result. The delta
	 * filter is two-sided: $min_drop=5 returns ranks that dropped 5+ spots
	 * OR improved 5+ spots, ordered by absolute delta descending.
	 *
	 * @param int $days            Window length per side. Default 7.
	 * @param int $min_drop        Min absolute position change in either
	 *                             direction to include a row. Default 5.
	 * @param int $min_impressions Min impressions per side. Default 3.
	 * @param int $limit           Max rows. Default 50.
	 * @return array Each row: page, post_id, post_title, query, prior_position,
	 *               current_position, position_delta, prior_impressions,
	 *               current_impressions, direction (declined|improved).
	 */
	public static function get_query_position_decay( $days = 7, $min_drop = 5, $min_impressions = 3, $limit = 50 ) {
		global $wpdb;
		$days            = max( 1, (int) $days );
		$min_drop        = max( 1, (int) $min_drop );
		$min_impressions = max( 1, (int) $min_impressions );
		$limit           = max( 1, min( 500, (int) $limit ) );

		$table         = $wpdb->prefix . 'cc_gsc_queries';
		$current_start = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$prior_start   = gmdate( 'Y-m-d', strtotime( '-' . ( 2 * $days ) . ' days' ) );
		$prior_end     = $current_start;

		// Compare aggregates at the (page, query) grain across the two windows.
		// NULLIF guards the position division. Two-sided delta filter via abs().
		$sql = $wpdb->prepare(
			"SELECT
				c.page AS page,
				c.query AS query,
				c.position AS current_position,
				c.impressions AS current_impressions,
				p.position AS prior_position,
				p.impressions AS prior_impressions,
				(c.position - p.position) AS position_delta
			FROM (
				SELECT page, query,
					SUM(position * impressions) / NULLIF(SUM(impressions), 0) AS position,
					SUM(impressions) AS impressions
				FROM {$table}
				WHERE date >= %s
				GROUP BY page, query
				HAVING SUM(impressions) >= %d
			) c
			INNER JOIN (
				SELECT page, query,
					SUM(position * impressions) / NULLIF(SUM(impressions), 0) AS position,
					SUM(impressions) AS impressions
				FROM {$table}
				WHERE date >= %s AND date < %s
				GROUP BY page, query
				HAVING SUM(impressions) >= %d
			) p ON c.page = p.page AND c.query = p.query
			WHERE ABS(c.position - p.position) >= %d
			ORDER BY ABS(c.position - p.position) DESC
			LIMIT %d",
			$current_start,
			$min_impressions,
			$prior_start,
			$prior_end,
			$min_impressions,
			$min_drop,
			$limit
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		// Enrich each row with post_id + post_title via the existing URL map.
		$url_to_id = self::get_url_to_post_id_map();
		$out       = array();
		foreach ( $rows as $r ) {
			$pid   = isset( $url_to_id[ $r['page'] ] ) ? (int) $url_to_id[ $r['page'] ] : 0;
			$title = '';
			if ( $pid > 0 ) {
				$post = get_post( $pid );
				if ( $post ) {
					$title = $post->post_title;
				}
			}
			$delta = (float) $r['position_delta'];
			$out[] = array(
				'page'                => $r['page'],
				'post_id'             => $pid,
				'post_title'          => $title,
				'query'               => $r['query'],
				'prior_position'      => round( (float) $r['prior_position'], 1 ),
				'current_position'    => round( (float) $r['current_position'], 1 ),
				'position_delta'      => round( $delta, 1 ),
				'prior_impressions'   => (int) $r['prior_impressions'],
				'current_impressions' => (int) $r['current_impressions'],
				'direction'           => $delta > 0 ? 'declined' : 'improved',
			);
		}
		return $out;
	}

	/**
	 * All queries for a single post in the window. Used by the dashboard
	 * drilldown row expander.
	 */
	public static function get_post_queries( $post_id, $days = 28 ) {
		global $wpdb;
		$url_to_id = self::get_url_to_post_id_map();
		$urls      = array();
		foreach ( $url_to_id as $url => $pid ) {
			if ( (int) $pid === (int) $post_id ) {
				$urls[] = $url;
			}
		}
		if ( empty( $urls ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $urls ), '%s' ) );
		$table        = $wpdb->prefix . 'cc_gsc_queries';
		$start        = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
					SUM(position*impressions)/NULLIF(SUM(impressions),0) AS position
				FROM {$table}
				WHERE date >= %s AND page IN ($placeholders)
				GROUP BY query
				ORDER BY impressions DESC
				LIMIT 50",
				array_merge( array( $start ), $urls )
			),
			ARRAY_A
		);
	}

	/**
	 * Trend classification: ▲ rising, ▼ decaying, → flat, ◇ new, — empty.
	 *
	 *   - "empty":  no impressions in either period
	 *   - "new":    prior == 0 AND current >= 10 (genuinely new visibility)
	 *   - "flat":   prior == 0 AND current 1..9 (sub-noise, not a trend)
	 *   - "rise":   delta / max(prior, 1) >= +10% (with prior > 0)
	 *   - "decay":  delta / max(prior, 1) <= -10% (with prior > 0)
	 *   - "flat":   else
	 *
	 * The prior==0 + current<10 → 'flat' case is the key correctness fix.
	 * Without it, 0→1 to 0→9 impression jumps were flagged 'rise' and cluttered
	 * the Rising filter with statistical noise on brand-new low-visibility
	 * queries that haven't earned a "new" classification yet.
	 */
	public static function classify_trend( $current_imp, $prior_imp ) {
		$current_imp = (int) $current_imp;
		$prior_imp   = (int) $prior_imp;
		if ( $current_imp <= 0 && $prior_imp <= 0 ) {
			return 'empty';
		}
		if ( $prior_imp <= 0 ) {
			return $current_imp >= 10 ? 'new' : 'flat';
		}
		$ratio = ( $current_imp - $prior_imp ) / $prior_imp;
		if ( $ratio >= self::TREND_THRESHOLD ) {
			return 'rise';
		}
		if ( $ratio <= -self::TREND_THRESHOLD ) {
			return 'decay';
		}
		return 'flat';
	}

	public static function trend_arrow( $trend ) {
		switch ( $trend ) {
			case 'rise':
				return '▲';
			case 'decay':
				return '▼';
			case 'new':
				return '◇';
			case 'flat':
				return '→';
			default:
				return '—';
		}
	}

	public static function empty_metrics() {
		return array(
			'clicks'           => 0,
			'impressions'      => 0,
			'position'         => 0,
			'prev_clicks'      => 0,
			'prev_impressions' => 0,
			'prev_position'    => 0,
			'impression_delta' => 0,
			'position_delta'   => null,
			'trend'            => 'empty',
			'ctr'              => 0,
		);
	}

	/**
	 * Format position-delta for inline display. Inverts numeric sign so the
	 * UI arrow reflects USER INTUITION (▲ = improved/lower number) rather
	 * than the raw math (positive delta = numerically higher position = worse).
	 * Returns empty string when delta cannot be computed (no prior or current).
	 *
	 * @param float|null $delta Raw position delta (current - prior).
	 * @return array { html: rendered span, class: pos_better|pos_worse|pos_flat|pos_na }
	 */
	public static function render_position_delta( $delta ) {
		if ( null === $delta ) {
			return array( 'html' => '', 'class' => 'pos_na' );
		}
		$delta = (float) $delta;
		if ( abs( $delta ) < 0.5 ) {
			return array(
				'html'  => '<span class="cc-posdelta cc-posdelta-flat">→</span>',
				'class' => 'pos_flat',
			);
		}
		// Negative delta = current position is LOWER number = ranking improved.
		if ( $delta < 0 ) {
			$abs = number_format( abs( $delta ), 1 );
			return array(
				'html'  => sprintf( '<span class="cc-posdelta cc-posdelta-better" title="Improved by %s positions">▲%s</span>', esc_attr( $abs ), esc_html( $abs ) ),
				'class' => 'pos_better',
			);
		}
		$abs = number_format( $delta, 1 );
		return array(
			'html'  => sprintf( '<span class="cc-posdelta cc-posdelta-worse" title="Declined by %s positions">▼%s</span>', esc_attr( $abs ), esc_html( $abs ) ),
			'class' => 'pos_worse',
		);
	}
}
