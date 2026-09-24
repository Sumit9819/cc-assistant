<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post-apply duplication regression check.
 *
 * Pre-check baseline is captured at queue time (in class-rest-api.php) and
 * stored on cc_pending_changes.pre_check_baseline. Apply fires the
 * cc_assistant_after_apply action; we listen, schedule a 30s-delayed cron,
 * and re-run the same find_clusters() comparison. The delta is stored on the
 * pending row's verification_result column AND on the touched post's
 * _cc_last_verification postmeta so the inbox + post-edit screen can surface it.
 *
 * Per the cc-assistant automation boundary: this REPORTS the regression. It
 * does NOT auto-queue a v2 rewrite. Operator decides what to do.
 */
class CC_Assistant_Post_Apply_Verifier {

	const ACTION_AFTER_APPLY = 'cc_assistant_after_apply';
	const ACTION_RUN         = 'cc_assistant_run_verification';
	const POSTMETA_KEY       = '_cc_last_verification';
	const SIBLINGS_LIMIT     = 5;
	const COSINE_THRESHOLD   = 0.5;
	// Per-pair status thresholds. delta = after - before (negative = improved).
	const IMPROVED_DELTA = -0.05;
	const REGRESSED_DELTA = 0.05;

	public static function init() {
		add_action( self::ACTION_AFTER_APPLY, array( __CLASS__, 'schedule_verification' ), 10, 3 );
		add_action( self::ACTION_RUN, array( __CLASS__, 'run_verification' ), 10, 1 );
	}

	/**
	 * Decide whether this apply event needs verification, and schedule a
	 * delayed cron if so. Verification only runs for body-content-changing
	 * change types — meta/cluster/redirect changes don't move cosine.
	 *
	 * Coalesced so two near-simultaneous applies on the same pending row do
	 * not duplicate work.
	 */
	public static function schedule_verification( $pending_id, $post_id, $change_type ) {
		if ( ! in_array( $change_type, array( 'post_content_update', 'elementor_widget_update' ), true ) ) {
			return;
		}
		$pending_id = (int) $pending_id;
		if ( $pending_id <= 0 ) {
			return;
		}
		// 30s delay so the apply transaction commits cleanly first and the
		// embeddings cache (which keys off content hash) sees the new content.
		if ( ! wp_next_scheduled( self::ACTION_RUN, array( $pending_id ) ) ) {
			wp_schedule_single_event( time() + 30, self::ACTION_RUN, array( $pending_id ) );
		}
	}

	/**
	 * Cron handler: read the baseline, re-run cosine, store the delta.
	 */
	public static function run_verification( $pending_id ) {
		$pending_id = (int) $pending_id;
		if ( $pending_id <= 0 ) {
			return;
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';

		$pending = CC_Assistant_Pending_Changes::get( $pending_id );
		if ( ! $pending || empty( $pending->pre_check_baseline ) ) {
			return;
		}

		$baseline = json_decode( (string) $pending->pre_check_baseline, true );
		if ( ! is_array( $baseline ) || empty( $baseline['post_ids'] ) ) {
			return;
		}

		$post_ids  = array_map( 'intval', $baseline['post_ids'] );
		$threshold = isset( $baseline['threshold'] ) ? (float) $baseline['threshold'] : self::COSINE_THRESHOLD;

		// Re-run the same comparison. find_clusters() will pick up the freshly
		// applied content via embedding cache invalidation (content_hash changed).
		$now = CC_Assistant_Similarity::find_clusters( $post_ids, $threshold );

		$baseline_pairs = self::index_pairs( isset( $baseline['pairs'] ) ? $baseline['pairs'] : array() );
		$now_pairs      = self::index_pairs( self::flatten_cluster_pairs( $now['clusters'] ?? array() ) );

		// To compute drop-below-threshold pairs we ALSO need to re-check pairs
		// that were in baseline but no longer appear above threshold — those
		// are the cleanest wins (cannibalization fully resolved).
		$comparisons = self::compare( $baseline_pairs, $now_pairs, $threshold );

		$summary = self::summarize( $comparisons );

		$result = array(
			'baseline_at' => isset( $baseline['captured_at'] ) ? $baseline['captured_at'] : null,
			'verified_at' => current_time( 'mysql' ),
			'threshold'   => $threshold,
			'post_ids'    => $post_ids,
			'pairs'       => $comparisons,
			'summary'     => $summary,
		);

		global $wpdb;

		// v0.68: MERGE, don't overwrite. The post-apply audit writes its
		// render_health / rendered_schema_audit keys into the same column on
		// its own ~30s cron; both single events fire in the same wp-cron pass
		// in nondeterministic order, and a blind overwrite here silently
		// erased whichever audit ran first.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT verification_result FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d",
			$pending_id
		) );
		$payload = is_string( $existing ) && '' !== $existing ? json_decode( $existing, true ) : array();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		foreach ( $result as $k => $v ) {
			$payload[ $k ] = $v;
		}

		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'verification_result' => wp_json_encode( $payload ) ),
			array( 'id' => $pending_id )
		);

		if ( $pending->post_id ) {
			update_post_meta( (int) $pending->post_id, self::POSTMETA_KEY, wp_json_encode( $result ) );
		}

		// Inbox cache: same set of transients class-pending-changes::queue()
		// busts, so the verification pill shows up on the next render.
		delete_transient( 'cc_assistant_dashboard_summary' );
		delete_transient( 'cc_inbox_integrity_banners' );
	}

	/**
	 * Capture a baseline snapshot at queue time. Returns the data structure
	 * that should be stored in cc_pending_changes.pre_check_baseline.
	 *
	 * Cluster-sibling auto-detection: if the post belongs to one or more
	 * clusters, we pull up to SIBLINGS_LIMIT siblings from the SMALLEST cluster
	 * the post is in (smaller cluster = tighter topical bind = more relevant
	 * cannibalization comparison). Falls back to no-op if the post is
	 * unclustered, so the verifier just won't run for that pending row.
	 */
	public static function capture_baseline( $post_id, $threshold = self::COSINE_THRESHOLD ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return null;
		}

		$siblings = self::auto_detect_siblings( $post_id );
		if ( count( $siblings ) < 1 ) {
			return null;
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';

		$post_ids = array_values( array_unique( array_merge( array( $post_id ), $siblings ) ) );
		$result   = CC_Assistant_Similarity::find_clusters( $post_ids, $threshold );

		return array(
			'captured_at' => current_time( 'mysql' ),
			'threshold'   => $threshold,
			'post_ids'    => array_map( 'intval', $post_ids ),
			'pairs'       => self::flatten_cluster_pairs( $result['clusters'] ?? array() ),
		);
	}

	/**
	 * Pull cluster siblings of $post_id, capped at SIBLINGS_LIMIT. Picks from
	 * the smallest cluster the post belongs to so the comparison set is
	 * topically tight, not a wide net.
	 */
	private static function auto_detect_siblings( $post_id ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();

		global $wpdb;
		$members_table  = $wpdb->prefix . 'cc_cluster_members';
		$clusters_table = $wpdb->prefix . 'cc_topic_clusters';

		// Find clusters this post belongs to, sorted by smallest first.
		$cluster_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.cluster_id
				 FROM $members_table m
				 WHERE m.post_id = %d
				 ORDER BY (SELECT COUNT(*) FROM $members_table m2 WHERE m2.cluster_id = m.cluster_id) ASC",
				$post_id
			)
		);

		if ( empty( $cluster_ids ) ) {
			return array();
		}

		$siblings = array();
		foreach ( $cluster_ids as $cid ) {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM $members_table WHERE cluster_id = %d AND post_id <> %d",
					(int) $cid,
					$post_id
				)
			);
			foreach ( $rows as $sib ) {
				$sib = (int) $sib;
				if ( $sib > 0 && ! in_array( $sib, $siblings, true ) ) {
					$siblings[] = $sib;
				}
				if ( count( $siblings ) >= self::SIBLINGS_LIMIT ) {
					break 2;
				}
			}
		}

		return $siblings;
	}

	/**
	 * Re-key pair list to "min-max" canonical form so {a:5, b:10} and
	 * {a:10, b:5} hash to the same key for comparison.
	 */
	private static function index_pairs( $pairs ) {
		$out = array();
		foreach ( (array) $pairs as $p ) {
			$a = (int) ( $p['a'] ?? 0 );
			$b = (int) ( $p['b'] ?? 0 );
			if ( $a <= 0 || $b <= 0 ) {
				continue;
			}
			$key = ( $a < $b ) ? "$a-$b" : "$b-$a";
			$out[ $key ] = array(
				'a'          => min( $a, $b ),
				'b'          => max( $a, $b ),
				'similarity' => isset( $p['similarity'] ) ? (float) $p['similarity'] : 0.0,
			);
		}
		return $out;
	}

	private static function flatten_cluster_pairs( $clusters ) {
		$flat = array();
		foreach ( (array) $clusters as $c ) {
			foreach ( (array) ( $c['pairs'] ?? array() ) as $p ) {
				$flat[] = array(
					'a'          => (int) $p['a'],
					'b'          => (int) $p['b'],
					'similarity' => (float) $p['similarity'],
				);
			}
		}
		return $flat;
	}

	/**
	 * For each baseline pair, find the matching now pair and compute delta.
	 * Pairs that were above threshold pre but not post = "resolved" (best
	 * outcome). Pairs that appeared post but not pre = "introduced"
	 * (regression worth surfacing).
	 */
	private static function compare( $baseline, $now, $threshold ) {
		$out = array();

		foreach ( $baseline as $key => $b ) {
			if ( isset( $now[ $key ] ) ) {
				$after = (float) $now[ $key ]['similarity'];
				$delta = $after - (float) $b['similarity'];
				$out[] = array(
					'a'      => $b['a'],
					'b'      => $b['b'],
					'before' => round( (float) $b['similarity'], 4 ),
					'after'  => round( $after, 4 ),
					'delta'  => round( $delta, 4 ),
					'status' => self::status_for( $delta ),
				);
			} else {
				// Was above threshold before, is no longer = resolved.
				$out[] = array(
					'a'      => $b['a'],
					'b'      => $b['b'],
					'before' => round( (float) $b['similarity'], 4 ),
					'after'  => null,
					'delta'  => null,
					'status' => 'resolved',
				);
			}
		}

		foreach ( $now as $key => $n ) {
			if ( ! isset( $baseline[ $key ] ) ) {
				// New duplicate appeared after the rewrite = regression.
				$out[] = array(
					'a'      => $n['a'],
					'b'      => $n['b'],
					'before' => null,
					'after'  => round( (float) $n['similarity'], 4 ),
					'delta'  => null,
					'status' => 'introduced',
				);
			}
		}

		return $out;
	}

	private static function status_for( $delta ) {
		if ( $delta <= self::IMPROVED_DELTA ) {
			return 'improved';
		}
		if ( $delta >= self::REGRESSED_DELTA ) {
			return 'regressed';
		}
		return 'stable';
	}

	private static function summarize( $comparisons ) {
		$counts = array(
			'resolved'   => 0,
			'improved'   => 0,
			'stable'     => 0,
			'regressed'  => 0,
			'introduced' => 0,
		);
		foreach ( $comparisons as $c ) {
			$s = $c['status'] ?? 'stable';
			if ( isset( $counts[ $s ] ) ) {
				$counts[ $s ]++;
			}
		}

		// Headline status for the pill: worst case wins. Regressions or
		// introductions outrank improvements visually because they're the
		// thing the operator needs to act on.
		if ( $counts['regressed'] > 0 || $counts['introduced'] > 0 ) {
			$headline = 'regressed';
		} elseif ( $counts['resolved'] > 0 || $counts['improved'] > 0 ) {
			$headline = 'improved';
		} else {
			$headline = 'stable';
		}

		return array(
			'counts'   => $counts,
			'headline' => $headline,
			'total'    => count( $comparisons ),
		);
	}

	/**
	 * Public renderer for the inbox pill. Returns a small HTML span — caller
	 * wraps in whatever cell they want. Returns empty string if no
	 * verification result is present yet.
	 */
	public static function render_pill( $verification_json ) {
		if ( empty( $verification_json ) ) {
			return '';
		}
		$v = is_array( $verification_json ) ? $verification_json : json_decode( (string) $verification_json, true );
		if ( ! is_array( $v ) ) {
			return '';
		}

		// v0.68: render-health verdict pill. This must NOT depend on the
		// cosine summary existing — most posts are unclustered, the cosine
		// verifier never runs for them, and requiring 'summary' made a
		// CRITICAL page-broken finding completely invisible in the inbox.
		$rh_pill = '';
		if ( isset( $v['render_health'] ) && is_array( $v['render_health'] ) ) {
			$rh       = $v['render_health'];
			$findings = isset( $rh['findings'] ) && is_array( $rh['findings'] ) ? $rh['findings'] : array();
			// v0.68.2: rows where the check could not run carry checked=false
			// plus the reason. Grey pill — visibly different from both a clean
			// pass and an alarm, so a broken guard can never hide. (Rows
			// written before this key existed are treated as checked.)
			$checked = array_key_exists( 'checked', $rh ) ? ! empty( $rh['checked'] ) : true;
			if ( ! $checked ) {
				$rh_pill = sprintf(
					'<span title="%s" style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; line-height:16px; background:#e5e7eb; color:#374151; font-weight:600;">%s</span>',
					esc_attr( (string) ( $rh['reason'] ?? '' ) ),
					esc_html__( 'Page check: not run', 'cc-assistant' )
				);
			} elseif ( empty( $findings ) ) {
				// v0.68.1: a clean check renders as a POSITIVE verdict — the
				// operator (and the assistant) can tell "checked, page fine"
				// apart from "check never ran", which shows no pill at all.
				$rh_pill = sprintf(
					'<span title="%s" style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; line-height:16px; background:#d1fae5; color:#065f46; font-weight:600;">%s</span>',
					esc_attr( sprintf( __( 'Rendered page compared against its pre-change baseline at %s: no regressions.', 'cc-assistant' ), (string) ( $rh['audited_at'] ?? '' ) ) ),
					esc_html__( 'Page check: OK', 'cc-assistant' )
				);
			} else {
				$rh_critical = ! empty( $rh['critical'] );
				$rh_msgs     = array();
				foreach ( array_slice( $findings, 0, 3 ) as $f ) {
					if ( ! empty( $f['message'] ) ) {
						$rh_msgs[] = (string) $f['message'];
					}
				}
				$rh_pill = sprintf(
					'<span title="%s" style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; line-height:16px; background:%s; color:%s; font-weight:600;">%s</span>',
					esc_attr( implode( ' ', $rh_msgs ) ),
					$rh_critical ? '#fee2e2' : '#fef3c7',
					$rh_critical ? '#991b1b' : '#92400e',
					$rh_critical
						? esc_html__( 'Page check: BROKEN?', 'cc-assistant' )
						: esc_html__( 'Page check: warning', 'cc-assistant' )
				);
			}
		}

		if ( empty( $v['summary'] ) ) {
			return $rh_pill;
		}

		$headline = (string) ( $v['summary']['headline'] ?? 'stable' );
		$counts   = (array) ( $v['summary']['counts'] ?? array() );

		$colors = array(
			'improved'  => array( 'bg' => '#d1fae5', 'fg' => '#065f46', 'label' => 'Differentiated' ),
			'stable'    => array( 'bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'Stable' ),
			'regressed' => array( 'bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => 'Regressed' ),
		);
		$style = isset( $colors[ $headline ] ) ? $colors[ $headline ] : $colors['stable'];

		$tooltip_parts = array();
		foreach ( array( 'resolved', 'improved', 'stable', 'regressed', 'introduced' ) as $k ) {
			$n = (int) ( $counts[ $k ] ?? 0 );
			if ( $n > 0 ) {
				$tooltip_parts[] = "$n $k";
			}
		}
		$tooltip = $tooltip_parts ? implode( ', ', $tooltip_parts ) : 'no overlapping pairs';

		$cosine_pill = sprintf(
			'<span title="%s" style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; line-height:16px; background:%s; color:%s; font-weight:600;">%s</span>',
			esc_attr( $tooltip ),
			esc_attr( $style['bg'] ),
			esc_attr( $style['fg'] ),
			esc_html( $style['label'] )
		);

		return '' !== $rh_pill ? $rh_pill . ' ' . $cosine_pill : $cosine_pill;
	}
}
