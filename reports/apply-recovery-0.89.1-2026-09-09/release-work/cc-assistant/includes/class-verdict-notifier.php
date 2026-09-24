<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Edit-outcome verdict notifier.
 *
 * Edits get scored 14+ days after they're applied (GSC reporting lag plus
 * post-edit window). Without a notifier the user has to remember to check.
 * This class runs daily, finds edits whose verdict_status flipped from
 * 'pending' to a real verdict, and queues a CC Assistant admin notice via
 * class-admin-notices.
 *
 * v0.20.2: aggregate per-post. Prior version emitted one notice per edit
 * which produced walls of identical "scored flat" notices when a post had
 * multiple recent edits — exactly the failure mode the eroflufkin operator
 * hit (4 notices for the same URL). Now we group recent verdicts by post_id
 * and emit ONE notice per post with the count and the worst/most-actionable
 * verdict surfaced. The notice id is `verdict_post_<id>` so the same notice
 * row gets REPLACED in place on subsequent runs (instead of stacking).
 */
class CC_Assistant_Verdict_Notifier {

	const CRON_HOOK    = 'cc_assistant_verdict_notify';
	const SEEN_OPTION  = 'cc_assistant_verdict_notified_ids';

	public static function bootstrap() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 30 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function run() {
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-admin-notices.php';

		// recent_with_status returns the latest 25 edits annotated with verdicts.
		// We trust that — no need to scan the whole edits table here.
		$recent = CC_Assistant_Edit_Outcomes::recent_with_status( 25 );
		$seen   = (array) get_option( self::SEEN_OPTION, array() );
		// Prune seen ids older than 60 days.
		$cutoff = time() - 60 * DAY_IN_SECONDS;
		foreach ( $seen as $id => $ts ) {
			if ( (int) $ts < $cutoff ) {
				unset( $seen[ $id ] );
			}
		}

		// Group recent NON-pending edits by post_id. We need at least one
		// edit per post that the user hasn't already been notified about,
		// otherwise we skip (re-emitting the same aggregate would just be
		// noise after the user dismissed the previous one).
		$groups = array(); // post_id => { path, edits: [{id, verdict, target_score}], has_new }
		foreach ( (array) $recent as $row ) {
			$status = $row['status'] ?? '';
			if ( 'pending' === $status ) {
				continue;
			}
			$edit_id = isset( $row['edit']['id'] ) ? (int) $row['edit']['id'] : 0;
			$post_id = isset( $row['edit']['post_id'] ) ? (int) $row['edit']['post_id'] : 0;
			$page    = isset( $row['edit']['page_url'] ) ? (string) $row['edit']['page_url'] : '';
			if ( ! $edit_id || ! $post_id ) {
				continue;
			}
			$path = wp_parse_url( $page, PHP_URL_PATH ) ?: $page;
			if ( ! isset( $groups[ $post_id ] ) ) {
				$groups[ $post_id ] = array(
					'path'    => $path,
					'edits'   => array(),
					'has_new' => false,
				);
			}
			$is_new = ! isset( $seen[ $edit_id ] );
			$groups[ $post_id ]['edits'][] = array(
				'id'           => $edit_id,
				'verdict'      => $row['verdict'] ?? 'flat',
				'target_score' => isset( $row['target_score'] ) && is_array( $row['target_score'] ) ? $row['target_score'] : null,
				'is_new'       => $is_new,
			);
			if ( $is_new ) {
				$groups[ $post_id ]['has_new'] = true;
			}
		}

		foreach ( $groups as $post_id => $group ) {
			if ( ! $group['has_new'] ) {
				// No new edits since last notification — don't re-emit. The
				// existing notice (if not dismissed) is still visible; if
				// dismissed, snooze will prevent re-add.
				continue;
			}
			$notice = self::build_aggregate_notice( $group );
			CC_Assistant_Admin_Notices::add(
				'verdict_post_' . $post_id,
				$notice['type'],
				$notice['message'],
				array(
					'url'   => admin_url( 'admin.php?page=cc-assistant' ),
					'label' => __( 'Open dashboard', 'cc-assistant' ),
				)
			);
			// Mark every edit in this group as seen so we don't re-emit the
			// aggregate every cron run.
			foreach ( $group['edits'] as $e ) {
				$seen[ $e['id'] ] = time();
			}
		}

		update_option( self::SEEN_OPTION, $seen, false );

		// A.2 No-data canary. When edits keep landing with verdict=no_data
		// well past their measurement window, the bottleneck is GSC coverage,
		// not the edits themselves — flag it once per cron run.
		self::maybe_emit_no_data_canary();

		// C.4 Abandoned-pending alert. Pendings unreviewed for more than 7
		// days are noise — the operator either changed their mind or never
		// saw them. Surface as a single notice so they decide.
		self::maybe_emit_abandoned_pending();
	}

	/**
	 * Find recent edits whose outcome resolves to verdict=no_data more than
	 * 14 days after apply. That's the canonical signal that GSC has no data
	 * for the URL (either GSC isn't connected, never synced this page hash,
	 * or the page itself never ranked) — not a verdict on the edit. Emits
	 * one aggregate notice instead of per-edit so the inbox stays calm.
	 */
	private static function maybe_emit_no_data_canary() {
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		$recent = CC_Assistant_Edit_Outcomes::recent_with_status( 25 );
		$old_no_data = array();
		$cutoff = time() - 14 * DAY_IN_SECONDS;
		foreach ( (array) $recent as $row ) {
			if ( 'no_data' !== ( $row['verdict'] ?? '' ) ) {
				continue;
			}
			$applied_at = isset( $row['edit']['applied_at'] ) ? strtotime( $row['edit']['applied_at'] . ' UTC' ) : 0;
			if ( $applied_at && $applied_at < $cutoff ) {
				$old_no_data[] = $row;
			}
		}
		if ( count( $old_no_data ) < 2 ) {
			// One stale no_data row could be a one-off URL with low GSC
			// signal; two-plus is a coverage pattern worth surfacing.
			return;
		}
		CC_Assistant_Admin_Notices::add(
			'gsc_no_data_canary',
			'warning',
			sprintf(
				/* translators: %d: count of stale no_data edits */
				_n(
					'%d recent edit landed with no Search Console data after its measurement window closed. Verify GSC is connected, the property URL matches, and the affected URLs are getting impressions — the position checker cannot score these edits without it.',
					'%d recent edits landed with no Search Console data after their measurement windows closed. Verify GSC is connected, the property URL matches, and the affected URLs are getting impressions — the position checker cannot score these edits without it.',
					count( $old_no_data ),
					'cc-assistant'
				),
				count( $old_no_data )
			),
			array(
				'url'   => admin_url( 'admin.php?page=cc-assistant-settings' ),
				'label' => __( 'Open settings', 'cc-assistant' ),
			)
		);
	}

	/**
	 * Find pending changes that have been sitting in the inbox unreviewed
	 * for more than 7 days. The operator either forgot about them or
	 * changed their mind. Either way, the inbox is the wrong place for
	 * them. Emits a single notice so the operator decides whether to bulk-
	 * reject or finally act.
	 */
	private static function maybe_emit_abandoned_pending() {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cc_pending_changes
			 WHERE status = 'pending'
			   AND superseded_by IS NULL
			   AND created_at < %s",
			$cutoff
		) );
		if ( $count < 1 ) {
			return;
		}
		CC_Assistant_Admin_Notices::add(
			'abandoned_pending',
			'info',
			sprintf(
				/* translators: %d: count of abandoned pendings */
				_n(
					'%d pending change has been unreviewed for more than 7 days. Either approve, reject, or bulk-clear from the inbox so the queue reflects current intent.',
					'%d pending changes have been unreviewed for more than 7 days. Either approve, reject, or bulk-clear from the inbox so the queue reflects current intent.',
					$count,
					'cc-assistant'
				),
				$count
			),
			array(
				'url'   => admin_url( 'admin.php?page=cc-assistant-pending' ),
				'label' => __( 'Open inbox', 'cc-assistant' ),
			)
		);
	}

	/**
	 * Build the aggregated message for one post. Prefers the most actionable
	 * signal in priority order:
	 *   1. Any 'meta_tag_bottleneck' (body landed, snippet leaking clicks)
	 *      — this has a concrete next action.
	 *   2. Any 'missed' target — actionable but less specific.
	 *   3. Any 'negative' verdict — needs investigation / revert.
	 *   4. Any 'positive' verdict — worth replicating the pattern.
	 *   5. All flat — generic.
	 *
	 * Includes the edit count so the operator knows multiple changes scored
	 * the same way.
	 */
	private static function build_aggregate_notice( $group ) {
		$path  = $group['path'];
		$edits = $group['edits'];
		$count = count( $edits );
		$path_html = '<code>' . esc_html( $path ) . '</code>';

		// Tier 1: meta_tag_bottleneck has the best next-step signal.
		foreach ( $edits as $e ) {
			$ts = $e['target_score'];
			if ( $ts && ! empty( $ts['meta_tag_bottleneck'] ) ) {
				return array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: 1: page path, 2: target_score message, 3: edit count suffix */
						__( '%1$s body landed but the snippet is leaking clicks. %2$s Propose a draft_update_seo_meta change to close the gap.%3$s', 'cc-assistant' ),
						$path_html,
						esc_html( (string) ( $ts['message'] ?? '' ) ),
						$count > 1 ? sprintf( ' (%d edits scored)', $count ) : ''
					),
				);
			}
		}

		// Tier 2: any missed-target verdict.
		foreach ( $edits as $e ) {
			$ts = $e['target_score'];
			if ( $ts && ! empty( $ts['has_metrics'] ) && 'missed' === ( $ts['overall_hit'] ?? '' ) ) {
				return array(
					'type'    => 'warning',
					'message' => sprintf(
						__( '%1$s missed its stated target. %2$s%3$s', 'cc-assistant' ),
						$path_html,
						esc_html( (string) ( $ts['message'] ?? '' ) ),
						$count > 1 ? sprintf( ' (%d total edits scored)', $count ) : ''
					),
				);
			}
		}

		// Tier 3: negative verdict.
		foreach ( $edits as $e ) {
			if ( 'negative' === $e['verdict'] ) {
				return array(
					'type'    => 'warning',
					'message' => sprintf(
						__( 'Edit on %1$s caused a measurable drop in clicks. Consider reverting from the Snapshots page or investigating which queries lost ground.%2$s', 'cc-assistant' ),
						$path_html,
						$count > 1 ? sprintf( ' (%d total edits scored on this post)', $count ) : ''
					),
				);
			}
		}

		// Tier 4: positive verdict.
		foreach ( $edits as $e ) {
			if ( 'positive' === $e['verdict'] ) {
				return array(
					'type'    => 'success',
					'message' => sprintf(
						__( 'Edit on %1$s improved traffic vs its baseline. Worth identifying what worked and applying the same pattern to similar pages.%2$s', 'cc-assistant' ),
						$path_html,
						$count > 1 ? sprintf( ' (%d total edits scored)', $count ) : ''
					),
				);
			}
		}

		// Tier 5: all flat / no metrics.
		return array(
			'type'    => 'info',
			'message' => $count > 1
				? sprintf(
					__( '%1$d edits on %2$s scored flat. Either the changes were too small to register, or the levers chosen did not move the needle. Set target_query / target_position / target_ctr on future changes for sharper verdicts.', 'cc-assistant' ),
					$count,
					$path_html
				)
				: sprintf(
					__( 'Edit on %1$s scored flat. Either the change was too small to register, or the levers chosen did not move the needle. (No success_metrics were set on this change, so the score is generic — set target_query / target_position / target_ctr on future changes for sharper verdicts.)', 'cc-assistant' ),
					$path_html
				),
		);
	}
}
