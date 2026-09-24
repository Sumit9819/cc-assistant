<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly advisor: synthesises every signal the plugin already collects
 * (cannibalization, decay, click depth, cluster health, orphans, CTR misses)
 * into a ranked, dismissible top-N list of "what to do this week."
 *
 * The hard constraint here is that this card sits on the dashboard and
 * runs on every load. So all aggregation is read-through-cache and most
 * inputs are existing transient/option blobs; we never re-run heavy
 * GROUP BYs from this class.
 *
 * Score model is intentionally conservative: each signal yields at most
 * one item per category, and items are dismissible so users who
 * disagree with a recommendation can clear it without a settings trip.
 */
class CC_Assistant_Weekly_Advisor {

	const TRANSIENT_KEY     = 'cc_weekly_advisor_priorities_v85';
	const TRANSIENT_TTL     = HOUR_IN_SECONDS;
	const DISMISSED_OPTION  = 'cc_weekly_advisor_dismissed';
	const CATEGORY_DISMISS_OPTION = 'cc_weekly_advisor_category_dismiss';
	const MAX_ITEMS         = 8;

	/**
	 * Public entry point. Returns the cached priorities payload. On cache
	 * miss, schedules a one-shot cron to recompute and returns a "computing"
	 * placeholder so the dashboard render never blocks on the heavy
	 * aggregation pass (cannibalization + decay + click depth + cluster
	 * health + orphans + low CTR + unclustered = 5-10s on real sites).
	 *
	 * Pass force=true to bypass cache entirely (cron handler does this).
	 */
	public static function priorities( $args = array() ) {
		$force = ! empty( $args['force'] );
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return self::filter_dismissed( $cached );
			}
			// Cache miss in a non-force call (i.e. user-facing request like
			// dashboard render). Schedule async recompute and return empty
			// placeholder. Idempotent — wp_next_scheduled prevents duplicate jobs.
			if ( ! wp_next_scheduled( 'cc_assistant_advisor_recompute' ) ) {
				wp_schedule_single_event( time() + 5, 'cc_assistant_advisor_recompute' );
			}
			return array(
				'generated_at' => null,
				'count'        => 0,
				'items'        => array(),
				'computing'    => true,
			);
		}

		$items = array();
		$items = array_merge( $items, self::from_cannibalization() );
		$items = array_merge( $items, self::from_refresh_queue() );
		$items = array_merge( $items, self::from_click_depth() );
		$items = array_merge( $items, self::from_cluster_health() );
		$items = array_merge( $items, self::from_orphans() );
		$items = array_merge( $items, self::from_low_ctr() );
		$items = array_merge( $items, self::from_unclustered() );

		// Apply per-category dismissal-decay multiplier. If you keep dismissing
		// "orphans" the advisor will quietly down-rank that category over time.
		foreach ( $items as &$it ) {
			$mult = self::score_multiplier( $it['category'] );
			if ( $mult < 1.0 ) {
				$it['priority']   = (int) round( $it['priority'] * $mult );
				$it['_decayed']   = true;
			}
		}
		unset( $it );

		usort( $items, function ( $a, $b ) {
			return ( $b['priority'] <=> $a['priority'] );
		} );

		// Cap to MAX_ITEMS so the card stays scannable.
		$items = array_slice( $items, 0, self::MAX_ITEMS );

		$payload = array(
			'generated_at' => gmdate( 'Y-m-d H:i:s' ),
			'count'        => count( $items ),
			'items'        => $items,
		);
		set_transient( self::TRANSIENT_KEY, $payload, self::TRANSIENT_TTL );
		return self::filter_dismissed( $payload );
	}

	/**
	 * Mark a priority as dismissed by its stable id. Stored in an option
	 * keyed by the user's site so dismissals persist across cache rebuilds.
	 * Auto-prunes after 30 days so re-emerging issues can resurface.
	 */
	public static function dismiss( $item_id ) {
		$item_id = sanitize_text_field( (string) $item_id );
		if ( '' === $item_id ) {
			return false;
		}
		$dismissed = (array) get_option( self::DISMISSED_OPTION, array() );
		$cutoff    = time() - 30 * DAY_IN_SECONDS;
		foreach ( $dismissed as $id => $ts ) {
			if ( (int) $ts < $cutoff ) {
				unset( $dismissed[ $id ] );
			}
		}
		$dismissed[ $item_id ] = time();
		update_option( self::DISMISSED_OPTION, $dismissed, false );

		// Learning signal: track per-category dismissal counts so the
		// advisor can down-rank categories the user keeps dismissing.
		// Decay over time so an old dismissal pattern doesn't permanently
		// suppress a category if circumstances change.
		$cat = self::category_for_item_id( $item_id );
		if ( $cat ) {
			$cat_counts = (array) get_option( self::CATEGORY_DISMISS_OPTION, array() );
			$cat_counts[ $cat ] = isset( $cat_counts[ $cat ] ) ? array(
				'count'      => (int) $cat_counts[ $cat ]['count'] + 1,
				'last_at'    => time(),
			) : array( 'count' => 1, 'last_at' => time() );
			update_option( self::CATEGORY_DISMISS_OPTION, $cat_counts, false );
		}
		return true;
	}

	/**
	 * Recover the category from a stored item id. The id is built per-source
	 * with a stable prefix (e.g. 'cannib_*', 'decay_*'). We map prefix → category.
	 */
	private static function category_for_item_id( $item_id ) {
		$map = array(
			'cannib_'      => 'cannibalization',
			'decay_'       => 'decay',
			'depth_'       => 'click_depth',
			'cluster_'     => 'cluster_health',
			'orphans_'     => 'orphans',
			'lowctr_'      => 'low_ctr',
			'unclustered_' => 'unclustered',
		);
		foreach ( $map as $prefix => $cat ) {
			if ( 0 === strpos( $item_id, $prefix ) ) {
				return $cat;
			}
		}
		return null;
	}

	/**
	 * Score multiplier per category, derived from past dismissals.
	 * Returns 1.0 by default; falls toward 0.5 for heavily-dismissed
	 * categories. Decays over 30 days so the suppression isn't permanent.
	 */
	private static function score_multiplier( $category ) {
		$cat_counts = (array) get_option( self::CATEGORY_DISMISS_OPTION, array() );
		if ( empty( $cat_counts[ $category ] ) ) {
			return 1.0;
		}
		$entry = $cat_counts[ $category ];
		$count = (int) ( $entry['count'] ?? 0 );
		$age   = max( 0, time() - (int) ( $entry['last_at'] ?? time() ) );
		if ( $count <= 0 ) {
			return 1.0;
		}
		$age_decay = max( 0.0, 1.0 - ( $age / ( 30 * DAY_IN_SECONDS ) ) );
		// Each dismissal removes 15% of weight, capped at 50% suppression,
		// then aged-decayed back toward 1.0.
		$suppress  = min( 0.5, $count * 0.15 ) * $age_decay;
		return max( 0.5, 1.0 - $suppress );
	}

	public static function clear_dismissed() {
		delete_option( self::DISMISSED_OPTION );
		// Also clear the per-category decay counts — leaving them strands the
		// suppression in the multiplier path even after the user reset.
		delete_option( self::CATEGORY_DISMISS_OPTION );
		delete_transient( self::TRANSIENT_KEY );
		return true;
	}

	/**
	 * Bust just the priorities cache. Call this from any code path that
	 * mutates one of the advisor's input signals (link graph rebuilt, new
	 * pending change applied, etc.) so the dashboard cards re-render with
	 * fresh data on the next view instead of waiting an hour for the TTL.
	 *
	 * Always use this method (or the constant) — never hardcode the cache
	 * key string. A typo will silently fail to bust anything and the user
	 * sees a stale number for an hour with no error to debug.
	 */
	public static function invalidate() {
		delete_transient( self::TRANSIENT_KEY );
	}

	private static function filter_dismissed( $payload ) {
		$dismissed = (array) get_option( self::DISMISSED_OPTION, array() );
		if ( empty( $dismissed ) ) {
			return $payload;
		}
		$filtered = array();
		foreach ( $payload['items'] as $item ) {
			if ( ! isset( $dismissed[ $item['id'] ] ) ) {
				$filtered[] = $item;
			}
		}
		$payload['items'] = $filtered;
		$payload['count'] = count( $filtered );
		return $payload;
	}

	/* ---------- signal sources ---------- */

	private static function from_cannibalization() {
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
		$result = CC_Assistant_SEO_Tools::cannibalization( array( 'limit' => 5 ) );
		if ( empty( $result['conflicts'] ) ) {
			return array();
		}
		$top = $result['conflicts'][0];
		// Score: capped, leans on total impressions split across competing pages.
		$score = min( 100, 40 + (int) round( log( max( 1, $top['total_impressions'] ) ) * 5 ) );
		return array( array(
			'id'         => 'cannib_' . md5( $top['query'] ),
			'category'   => 'cannibalization',
			'severity'   => 'high',
			'priority'   => $score,
			'headline'   => sprintf(
				/* translators: 1: query 2: page count */
				__( '%1$d pages competing for "%2$s"', 'cc-assistant' ),
				(int) $top['page_count'],
				$top['query']
			),
			'reason'     => sprintf(
				/* translators: %s: total impressions */
				__( '%s impressions are being split. Consolidating into one canonical page or differentiating intent typically recovers most of them.', 'cc-assistant' ),
				number_format_i18n( (int) $top['total_impressions'] )
			),
			'next_step'  => __( 'Inspect distinct reader tasks with content_decision. Keep/link, differentiate or consolidate only when evidence supports the action.', 'cc-assistant' ),
			'evidence'   => array_slice( $top['pages'], 0, 3 ),
			'mcp_prompt' => sprintf(
				'Use cc-assistant tools (gsc_cannibalization, post_dossier). The query "%s" has %d pages competing on this site. Inspect distinct reader tasks and use content_decision. Query overlap alone does not establish harm or justify a merge.',
				$top['query'],
				(int) $top['page_count']
			),
		) );
	}

	private static function from_refresh_queue() {
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
		$result = CC_Assistant_SEO_Tools::refresh_queue( array( 'limit' => 5 ) );
		if ( empty( $result['queue'] ) ) {
			return array();
		}
		$top = $result['queue'][0];
		$drop = abs( (int) ( $top['click_delta'] ?? 0 ) );
		$score = min( 95, 35 + $drop * 2 );
		return array( array(
			'id'         => 'decay_' . md5( $top['page'] ),
			'category'   => 'decay',
			'severity'   => $drop >= 20 ? 'high' : 'medium',
			'priority'   => $score,
			'headline'   => sprintf(
				/* translators: %s: page title */
				__( '"%s" is losing clicks', 'cc-assistant' ),
				$top['title'] ?: $top['page']
			),
			'reason'     => sprintf(
				/* translators: 1: click drop 2: age days */
				__( 'Down %1$d clicks vs the prior window. Last updated %2$d days ago — refreshing decayed pages typically recovers 60-80%% of lost traffic.', 'cc-assistant' ),
				$drop,
				(int) ( $top['age_days'] ?? 0 )
			),
			'next_step'  => __( 'Run post_dossier on this page, identify which queries dropped, then refresh the relevant sections.', 'cc-assistant' ),
			'evidence'   => array(
				'page'        => $top['page'],
				'click_delta' => $top['click_delta'],
				'age_days'    => $top['age_days'],
				'edit_url'    => $top['edit_url'],
			),
			'mcp_prompt' => sprintf(
				'Use cc-assistant tools. Refresh the post at %s — it dropped %d clicks. Run post_dossier %d, identify decayed queries, and propose targeted body updates.',
				$top['page'],
				$drop,
				(int) ( $top['post_id'] ?? 0 )
			),
		) );
	}

	private static function from_click_depth() {
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
		$result = CC_Assistant_SEO_Tools::click_depth( array( 'buried_only' => true ) );
		$buried = isset( $result['pages'] ) ? $result['pages'] : array();
		if ( count( $buried ) < 3 ) {
			return array();
		}
		$score = min( 80, 30 + count( $buried ) );
		$top   = $buried[0];
		return array( array(
			'id'         => 'depth_' . md5( wp_json_encode( wp_list_pluck( array_slice( $buried, 0, 5 ), 'post_id' ) ) ),
			'category'   => 'click_depth',
			'severity'   => count( $buried ) >= 10 ? 'high' : 'medium',
			'priority'   => $score,
			'headline'   => sprintf(
				/* translators: %d: count */
				_n( '%d page is buried 4+ clicks deep', '%d pages are buried 4+ clicks deep', count( $buried ), 'cc-assistant' ),
				count( $buried )
			),
			'reason'     => __( 'Pages this far from the homepage get less crawler attention from Google. Adding them to a hub page or relevant cluster pillar usually lifts crawl rate and rankings within 2-4 weeks.', 'cc-assistant' ),
			'next_step'  => __( 'Pick the highest-traffic candidates first. Use links_audit_post and propose inbound links from high-authority pages.', 'cc-assistant' ),
			'evidence'   => array_slice( $buried, 0, 3 ),
			'mcp_prompt' => sprintf(
				'Use cc-assistant tools (click_depth_audit, links_audit_post). I have %d buried pages. Starting with "%s", propose inbound internal links from pages closer to the homepage.',
				count( $buried ),
				$top['title'] ?? ''
			),
		) );
	}

	private static function from_cluster_health() {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();
		$clusters = CC_Assistant_Topic_Clusters::list_clusters();
		if ( empty( $clusters ) ) {
			return array();
		}
		$weak = array();
		foreach ( $clusters as $c ) {
			$h = CC_Assistant_Topic_Clusters::cluster_health( (int) $c->id );
			if ( ! $h ) {
				continue;
			}
			if ( in_array( $h['verdict'], array( 'weak_linking', 'no_pillar' ), true ) ) {
				$weak[] = array( 'cluster' => $c, 'health' => $h );
			}
		}
		if ( empty( $weak ) ) {
			return array();
		}
		// Pick the worst: no_pillar > weak_linking with most missing supports.
		usort( $weak, function ( $a, $b ) {
			$av = 'no_pillar' === $a['health']['verdict'] ? 100 : ( $a['health']['supporting_count'] - $a['health']['supporting_linking_pillar'] );
			$bv = 'no_pillar' === $b['health']['verdict'] ? 100 : ( $b['health']['supporting_count'] - $b['health']['supporting_linking_pillar'] );
			return $bv - $av;
		} );
		$worst = $weak[0];
		$score = 'no_pillar' === $worst['health']['verdict'] ? 70 : 55;
		$gap   = $worst['health']['supporting_count'] - $worst['health']['supporting_linking_pillar'];
		return array( array(
			'id'         => 'cluster_' . (int) $worst['cluster']->id,
			'category'   => 'cluster_health',
			'severity'   => 'medium',
			'priority'   => $score,
			'headline'   => 'no_pillar' === $worst['health']['verdict']
				? sprintf( /* translators: %s: cluster name */ __( 'Cluster "%s" has no pillar', 'cc-assistant' ), $worst['cluster']->name )
				: sprintf(
					/* translators: 1: gap 2: cluster name */
					__( '%1$d pages in "%2$s" don\'t link to the pillar', 'cc-assistant' ),
					$gap,
					$worst['cluster']->name
				),
			'reason'     => 'no_pillar' === $worst['health']['verdict']
				? __( 'Without a pillar, supporting pages have nowhere central to consolidate authority. Pick or write a canonical page.', 'cc-assistant' )
				: __( 'Cluster authority compounds when supporting pages link to the pillar. Each missing link is roughly one ranking position lost.', 'cc-assistant' ),
			'next_step'  => __( 'Open the cluster in Topic Clusters and add the missing pillar links.', 'cc-assistant' ),
			'evidence'   => array(
				'cluster_id'  => (int) $worst['cluster']->id,
				'verdict'     => $worst['health']['verdict'],
				'supporting'  => $worst['health']['supporting_count'],
				'linking'     => $worst['health']['supporting_linking_pillar'],
			),
			'mcp_prompt' => sprintf(
				'Use cc-assistant tools (get_topic_cluster, links_audit_post). Cluster %d ("%s") is %s. Read its members, then propose pending changes to fix the linking gap.',
				(int) $worst['cluster']->id,
				$worst['cluster']->name,
				$worst['health']['verdict']
			),
		) );
	}

	private static function from_orphans() {
		require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
		$summary = CC_Assistant_Internal_Links::summary();
		$orphan_count = (int) $summary['orphan_count'];
		if ( $orphan_count < 3 ) {
			return array();
		}
		$pct  = (float) $summary['orphan_percent'];
		$score = $pct >= 25 ? 75 : ( $pct >= 10 ? 50 : 35 );
		$orphans = CC_Assistant_Internal_Links::find_orphans( array( 'limit' => 5 ) );
		return array( array(
			'id'         => 'orphans_' . $orphan_count,
			'category'   => 'orphans',
			'severity'   => $pct >= 25 ? 'high' : 'medium',
			'priority'   => $score,
			'headline'   => sprintf(
				/* translators: 1: count 2: percent */
				_n( '%1$d page (%2$s%%) has no inbound links', '%1$d pages (%2$s%%) have no inbound links', $orphan_count, 'cc-assistant' ),
				$orphan_count,
				number_format_i18n( $pct, 1 )
			),
			'reason'     => __( 'Orphans get crawled rarely and rank weakly. Even one inbound link from a relevant page typically lifts impressions within a month.', 'cc-assistant' ),
			'next_step'  => __( 'Pick orphans you actually want indexed. For each, find a related page and add a contextual link.', 'cc-assistant' ),
			'evidence'   => $orphans,
			'mcp_prompt' => __( 'Use cc-assistant tools (links_orphans, links_audit_post). Pick my top 5 orphan pages and propose 3 internal links each. Queue as pending changes.', 'cc-assistant' ),
		) );
	}

	private static function from_low_ctr() {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		$status = CC_Assistant_GSC::status();
		if ( empty( $status['connected'] ) || (int) $status['rows_cached'] < 1 ) {
			return array();
		}
		// Cache the raw low_ctr() call (heavy GROUP BY on big GSC tables).
		$ck = 'cc_advisor_lowctr_28';
		$rows = get_transient( $ck );
		if ( false === $rows ) {
			$rows = CC_Assistant_GSC::low_ctr( array( 'min_impressions' => 500, 'max_ctr' => 0.02, 'limit' => 5, 'days' => 28, 'check_live' => false ) );
			set_transient( $ck, $rows, 30 * MINUTE_IN_SECONDS );
		}
		if ( empty( $rows ) ) {
			return array();
		}
		$top = $rows[0];
		// Only fire when there's real signal — at least 500 impressions on top miss.
		if ( (int) $top['impressions'] < 500 ) {
			return array();
		}
		$ctr_pct = round( (float) $top['ctr'] * 100, 2 );
		$score   = min( 70, 30 + (int) round( log( max( 1, (int) $top['impressions'] ) ) * 4 ) );
		return array( array(
			'id'         => 'lowctr_' . md5( $top['page'] ),
			'category'   => 'low_ctr',
			'severity'   => 'medium',
			'priority'   => $score,
			'headline'   => sprintf(
				/* translators: 1: ctr percent 2: page path */
				__( '%1$s%% CTR on a high-impression page', 'cc-assistant' ),
				number_format_i18n( $ctr_pct, 2 )
			),
			'reason'     => sprintf(
				/* translators: %s: impressions */
				__( '%s impressions but barely any clicks — title or meta description is failing the snippet test. Rewriting both typically lifts CTR 30-60%%.', 'cc-assistant' ),
				number_format_i18n( (int) $top['impressions'] )
			),
			'next_step'  => __( 'Run gsc_page_queries on this URL to see what searchers expected. Rewrite title and meta to match.', 'cc-assistant' ),
			'evidence'   => array(
				'page'        => $top['page'],
				'impressions' => (int) $top['impressions'],
				'clicks'      => (int) $top['clicks'],
				'ctr'         => $ctr_pct,
			),
			'mcp_prompt' => sprintf(
				'Use cc-assistant tools (gsc_page_queries, draft_update_seo_meta). Rewrite the title and meta description for %s — currently %s%% CTR on %s impressions. Match what searchers actually expect.',
				$top['page'],
				number_format_i18n( $ctr_pct, 2 ),
				number_format_i18n( (int) $top['impressions'] )
			),
		) );
	}

	private static function from_unclustered() {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();
		$count = CC_Assistant_Topic_Clusters::count_clusters();
		if ( 0 === $count ) {
			// Cold-start: don't push clustering until they have content + GSC data.
			return array();
		}
		// Lightweight count: posts not in any cluster, capped at 200.
		$ids = CC_Assistant_Topic_Clusters::unclustered_post_ids( 'page', 200 );
		$post_ids = CC_Assistant_Topic_Clusters::unclustered_post_ids( 'post', 200 );
		$total = count( array_unique( array_merge( $ids, $post_ids ) ) );
		if ( $total < 5 ) {
			return array();
		}
		return array( array(
			'id'         => 'unclustered_' . $total,
			'category'   => 'unclustered',
			'severity'   => 'low',
			'priority'   => 30,
			'headline'   => sprintf(
				/* translators: %d: count */
				_n( '%d page is not in any cluster', '%d pages are not in any cluster', $total, 'cc-assistant' ),
				$total
			),
			'reason'     => __( 'Clustering pages signals topical authority to Google. Unclustered pages also force Claude to read the whole site instead of just the relevant cluster — slower and pricier.', 'cc-assistant' ),
			'next_step'  => __( 'Run propose_cluster_assignment on the first batch. Pages that fit no cluster need scope review; useful new niche topics do not require an existing cluster.', 'cc-assistant' ),
			'evidence'   => array( 'count' => $total, 'sample_ids' => array_slice( $ids, 0, 5 ) ),
			'mcp_prompt' => sprintf(
				'Use cc-assistant tools (list_topic_clusters, propose_cluster_assignment). I have %d unclustered pages. Read existing clusters, then queue cluster_assign proposals for pages that clearly fit. Flag any that need new clusters.',
				$total
			),
		) );
	}
}
