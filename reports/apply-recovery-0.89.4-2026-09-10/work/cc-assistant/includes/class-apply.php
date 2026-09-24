<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies a pending change to the live site after human approval.
 *
 * Snapshots the prior state to wp_cc_snapshots before any write, so every
 * change has a one-click rollback path. Never publishes new posts on apply
 * unless the change_type explicitly says publish_post.
 */
require_once __DIR__ . '/class-integrity.php';
require_once __DIR__ . '/class-evidence-gate.php';
require_once __DIR__ . '/class-elementor-validation.php';

require_once __DIR__ . '/class-recovery.php';
require_once __DIR__ . '/class-write-lock.php';

class CC_Assistant_Apply {

	const ALLOWED_POST_FIELDS = array( 'post_title', 'post_excerpt', 'post_name', 'post_status', 'post_author' );

	/** Publication and trashing have their own reviewed handlers and recovery semantics. */
	public static function validate_field_status( $value ) {
		if ( ! in_array( $value, array( 'draft', 'pending', 'private' ), true ) ) {
			return new WP_Error( 'status_transition_requires_dedicated_proposal', 'Generic post fields only support draft, pending or private status. Publication requires a publish_draft proposal and its quality checks; refresh an existing proposal with refresh_publish_proposal when needed. Use the dedicated trash proposal to remove a post. Scheduled publication is not supported.', array( 'status' => 422 ) );
		}
		return true;
	}

	/**
	 * Apply a single pending change.
	 *
	 * @param int  $pending_id  Pending row id.
	 * @param int  $reviewer_id WP user id approving the change.
	 * @param bool $bulk_mode   When true, skip the trailing cache-invalidation
	 *                          block and the per-apply Elementor cache clear.
	 *                          The bulk caller must invoke run_post_apply_cleanup()
	 *                          once after the loop. Saves ~10 DB hits per applied
	 *                          change in a bulk batch (the freeze cause when 8+
	 *                          changes are bulk-approved).
	 */
	public static function apply_pending( $pending_id, $reviewer_id, $bulk_mode = false, $batch = null ) {
		$lock = CC_Assistant_Write_Lock::acquire();
		if ( is_wp_error( $lock ) ) { return $lock; }
		// Shutdown handling also covers fatal errors/timeouts that cannot reach catch.
		$finished = false;
		$claimed = false;
		register_shutdown_function( static function () use ( $pending_id, &$finished, &$claimed ) {
			if ( $claimed && ! $finished ) { self::fail_apply( $pending_id, 'Operation interrupted; inspect its recovery snapshot before retrying.' ); }
		} );
		try {
			$result = self::apply_pending_impl( $pending_id, $reviewer_id, $bulk_mode, $claimed, $batch );
			if ( $claimed && is_wp_error( $result ) ) { self::fail_apply( $pending_id, $result->get_error_message() ); }
			return $result;
		} catch ( \Throwable $e ) {
			$row = CC_Assistant_Pending_Changes::get( $pending_id );
			if ( $row && 'approved' === $row->status ) {
				return array( 'success' => true, 'snapshot_id' => CC_Assistant_Integrity::baseline( $row )['snapshot_id'] ?? null,
					'warnings' => array( 'Content was applied, but follow-up processing failed: ' . $e->getMessage() ) );
			}
			if ( $claimed ) { self::fail_apply( $pending_id, $e->getMessage() ); }
			return new WP_Error( 'apply_interrupted', 'Apply failed: ' . $e->getMessage(), array( 'status' => 500 ) );
		} finally { $finished = true; CC_Assistant_Write_Lock::release(); }
	}

	private static function fail_apply( $id, $message ) {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'cc_pending_changes',
			array( 'status' => 'apply_failed', 'review_note' => substr( '[apply_failed] ' . $message, 0, 1000 ) ),
			array( 'id' => (int) $id, 'status' => 'applying' ) );
	}

	/** Caller supplies the reviewed order and performs one cache cleanup afterward. */
	public static function apply_pending_batch( array $ids, $reviewer_id ) {
		require_once __DIR__ . '/class-approval-batch.php';
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$batch = new CC_Assistant_Approval_Batch( $ids );
		$results = array();
		foreach ( $ids as $id ) { $results[$id] = self::apply_pending( $id, $reviewer_id, true, $batch ); }
		return $results;
	}

	private static function apply_pending_impl( $pending_id, $reviewer_id, $bulk_mode, &$claimed, $batch = null ) {
		$pending = CC_Assistant_Pending_Changes::get( $pending_id );
		if ( ! $pending ) {
			return new WP_Error( 'not_found', 'Pending change not found.' );
		}
		if ( 'pending' !== $pending->status ) {
			return new WP_Error( 'not_pending', 'Pending change has already been reviewed.' );
		}
		if ( ! empty( $pending->superseded_by ) ) {
			return new WP_Error( 'proposal_superseded', 'A newer proposal replaces this change. Refresh the inbox and review the replacement.', array( 'status' => 409 ) );
		}
		if ( $batch && ! $batch->matches( $pending ) ) { return new WP_Error( 'batch_proposal_changed', 'This proposal changed during bulk approval. Refresh the inbox and review it again.', array( 'status' => 409 ) ); }

		$proposed = json_decode( $pending->proposed_value, true );
		if ( ! is_array( $proposed ) ) {
			return new WP_Error( 'invalid_proposed', 'Proposed value is not valid JSON.' );
		}

		$proof = CC_Assistant_Evidence_Gate::validate_apply( $pending );
		if ( is_wp_error( $proof ) ) { return $proof; }
		$valid = CC_Assistant_Elementor_Validation::validate_payload( (array) $pending );
		if ( is_wp_error( $valid ) ) { return $valid; }

		// Atomic claim: flip status pending → approved in a single UPDATE that
		// only succeeds when status is still pending. Two concurrent Approve
		// clicks would otherwise both pass the status check above and both
		// record duplicate cc_edits rows, double-counting outcome scoring.
		// Loser of the race returns early with a clear error.
		if ( ! CC_Assistant_Pending_Changes::claim_for_apply( $pending_id, $reviewer_id ) ) {
			return new WP_Error( 'race_lost', 'Another reviewer applied or rejected this change first. Refresh the inbox to see the latest state.' );
		}

		$claimed = true;

		// Cluster change types and rewrite_outline approvals don't touch post
		// content (cluster tables for the former, no-op record-keeping for
		// the latter), so a post snapshot would be useless. Skip the snapshot
		// for those.
		$no_post_snapshot = in_array(
			$pending->change_type,
			array( 'cluster_create', 'cluster_assign', 'rewrite_outline', 'term_update', 'bulk_term_assign', 'plugin_setting_update', 'kit_setting_update' ),
			true
		);
		$is_cluster_change = $no_post_snapshot;

		$snapshot_id = null;
		if ( $pending->post_id && ! $is_cluster_change ) {
			$snapshot_id = CC_Assistant_Snapshots::snapshot_post(
				$pending->post_id,
				'pre_apply',
				sprintf( 'Pending #%d (%s)', $pending->id, $pending->change_type )
			);
		}

		if ( is_wp_error( $snapshot_id ) ) { return $snapshot_id; }
		$recovery = CC_Assistant_Recovery::capture( $pending, $proposed, $snapshot_id );
		if ( is_wp_error( $recovery ) ) { return $recovery; }

		// 0.16: post_modified conflict guard. If the post was modified outside
		// the plugin between queue time and apply time (typical case: an
		// Elementor editor session was open in another tab and saved while
		// this pending sat in the inbox), apply right now would either fail
		// to find the target widget id (because it was renamed/moved) OR get
		// clobbered by the next editor save. Refuse with a clear conflict
		// error so the reviewer can re-queue against the current state.
		// Skip when the change type doesn't read/write post body (cluster ops).
		//
		// 0.18.1: the baseline for "what's old enough to flag" is
		// max(pending.created_at, _cc_assistant_last_internal_apply). Without
		// the second term, a bulk-approve where publish_draft (or any sibling
		// pending) runs first bumps post_modified, and every remaining sibling
		// pending whose queued_at predates the publish then trips this guard,
		// even though the bumper was the plugin itself. The postmeta marker
		// records the plugin's own most recent apply so we can subtract it.
		// External edits (Elementor save in another tab) bump post_modified
		// but do not write the marker, so the guard still catches them.
		// trash_post intentionally ignores the modified-conflict guard: we are
		// retiring the page, so content drift on it is irrelevant (and would
		// otherwise block trashing an old page someone edited).
		if ( $pending->post_id && ! $is_cluster_change && 'trash_post' !== $pending->change_type ) {
			$post_obj = get_post( (int) $pending->post_id );
			if ( $post_obj && ! empty( $post_obj->post_modified ) ) {
				// v0.35 TZ-correctness fix: compare apples-to-apples in UTC.
				// - post_modified is local TZ; post_modified_gmt is GMT.
				// - New queue records carry queued_at_gmt in their baseline.
				//   Historical created_at values use the WordPress timezone.
				// - _cc_assistant_last_internal_apply is stamped with
				//   current_time('mysql', 1) (also GMT).
				// Pre-v0.35 this compared post_modified (local) to the GMT
				// strings as if they were the same TZ, so on a non-UTC host
				// the guard drifted by gmt_offset hours and rejected stale
				// pendings as "modified after queue" even when they weren't.
				$post_modified_gmt   = get_post_field( 'post_modified_gmt', (int) $pending->post_id );
				$post_modified_disp  = ! empty( $post_modified_gmt ) ? $post_modified_gmt : $post_obj->post_modified;
				$current_mtime       = $post_modified_gmt ? strtotime( $post_modified_gmt . ' UTC' ) : strtotime( $post_obj->post_modified . ' UTC' );
				$queued_mtime        = CC_Assistant_Integrity::queued_timestamp( $pending );
				$last_internal       = get_post_meta( (int) $pending->post_id, '_cc_assistant_last_internal_apply', true );
				$last_internal_mtime = $last_internal ? strtotime( $last_internal . ' UTC' ) : 0;
				$baseline_mtime      = max( $queued_mtime, $last_internal_mtime );
				// Allow a 5-second clock-skew window. Anything beyond that is
				// a real concurrent edit we want to flag.
				$baseline_state = CC_Assistant_Integrity::baseline( $pending );
				$live_hash = CC_Assistant_Integrity::post_hash( (int) $pending->post_id );
				$last_hash = get_post_meta( (int) $pending->post_id, '_cc_assistant_last_internal_hash', true );
				$hash_conflict = ! empty( $baseline_state['post_hash'] )
					&& ! hash_equals( $baseline_state['post_hash'], $live_hash )
					&& ( ! is_string( $last_hash ) || ! hash_equals( $last_hash, $live_hash ) );
				if ( $hash_conflict || $current_mtime > $baseline_mtime ) {
					global $wpdb;
					$wpdb->update(
						$wpdb->prefix . 'cc_pending_changes',
						array(
							'status'      => 'apply_failed',
							'review_note' => sprintf(
								'[apply_failed] post_modified_conflict: post was modified (likely via Elementor editor) at %s GMT, AFTER this change was queued at %s GMT (last internal apply: %s GMT). Re-query the page state and queue a fresh change.',
								$post_modified_disp,
								$pending->created_at,
								$last_internal ? $last_internal : 'never'
							),
						),
						array( 'id' => (int) $pending_id )
					);
					return new WP_Error(
						'post_modified_conflict',
						sprintf(
							'Post %d was modified outside the plugin at %s GMT, after this pending change was queued at %s GMT and after the last plugin apply at %s GMT. Refusing apply to avoid clobbering or being clobbered by the concurrent edit. Re-query the current widget state and re-queue.',
							(int) $pending->post_id,
							$post_modified_disp,
							$pending->created_at,
							$last_internal ? $last_internal : 'never'
						),
						array( 'status' => 409 )
					);
				}
			}
		}

		$result = null;
		switch ( $pending->change_type ) {
			case 'meta_update':
				$result = self::apply_post_field( $pending->post_id, $proposed );
				break;
			case 'postmeta_update':
				$result = self::apply_post_meta( $pending->post_id, $proposed );
				break;
			case 'rank_math_schema_update':
				$result = self::apply_rank_math_schema( $pending->post_id, $proposed );
				break;
			case 'term_update':
				$result = self::apply_term_update( $proposed );
				break;
			case 'elementor_widget_update':
				$result = self::apply_elementor_widget( $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'elementor_widget_add':
				$result = self::apply_elementor_widget_add( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'elementor_widget_remove':
				$result = self::apply_elementor_widget_remove( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'elementor_container_add':
				$result = self::apply_elementor_container_add( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'elementor_section_rebuild':
				$result = self::apply_elementor_section_rebuild( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'elementor_accordion_item_add':
				$result = self::apply_elementor_accordion_item_add( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'elementor_accordion_item_remove':
				$result = self::apply_elementor_accordion_item_remove( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'post_content_update':
				$result = self::apply_post_content( $pending->post_id, $proposed );
				break;
			case 'snapshot_restore':
				$result = self::apply_snapshot_restore( $pending->id, $pending->post_id, $proposed );
				break;
			case 'publish_draft':
				$result = self::apply_publish_draft( $pending->post_id );
				break;
			case 'trash_post':
				$result = self::apply_trash_post( $pending->post_id );
				break;
			case 'cluster_create':
				$result = self::apply_cluster_create( $pending->id, $proposed );
				break;
			case 'cluster_assign':
				$result = self::apply_cluster_assign( $proposed );
				break;
			case 'rewrite_outline':
				// Approving an outline doesn't write anything to the post —
				// the outline is just a structural plan the human signed off
				// on. The downstream draft_update_post_content endpoint reads
				// cc_pending_changes for an approved row of this type within
				// the last 7 days to decide whether a body rewrite is gated.
				$result = true;
				break;
			// v0.71.0: the URL resolver caches a normalized redirect map for 15
			// minutes. Any write to the redirect table has to invalidate it, or
			// analytics attributes traffic using a map that no longer matches
			// what the server actually serves.
			case 'create_redirect':
				$result = self::apply_create_redirect( $proposed );
				CC_Assistant_URL_Resolver::flush();
				break;
			case 'delete_redirect':
				$result = self::apply_delete_redirect( $proposed );
				CC_Assistant_URL_Resolver::flush();
				break;
			case 'untrash_redirect':
				$result = self::apply_untrash_redirect( $proposed );
				CC_Assistant_URL_Resolver::flush();
				break;
			case 'emergency_service_schema':
				$result = self::apply_emergency_service_schema( $pending->post_id, $proposed );
				break;
			case 'category_update':
				$result = self::apply_categories( $pending->post_id, $proposed );
				break;
			case 'elementor_full_import':
				require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';
				$result = CC_Assistant_Elementor_IO::apply_import( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'elementor_section_content_replace':
				require_once CC_ASSISTANT_DIR . 'includes/class-sections.php';
				$result = CC_Assistant_Sections::apply_section_content_replace( $pending->id, $pending->post_id, $proposed, $bulk_mode );
				break;
			case 'asset_reference_replace':
				$result = self::apply_asset_reference_replace( (int) $pending->id, $proposed );
				break;
			case 'bulk_term_assign':
				$result = self::apply_bulk_term_assign( (int) $pending->id, $proposed );
				break;
			case 'plugin_setting_update':
				require_once CC_ASSISTANT_DIR . 'includes/class-setting-writer.php';
				$result = CC_Assistant_Setting_Writer::apply_plan( $proposed );
				break;
			case 'kit_setting_update':
				require_once CC_ASSISTANT_DIR . 'includes/class-kit-writer.php';
				$result = CC_Assistant_Kit_Writer::apply_plan( $proposed );
				break;
			default:
				return new WP_Error( 'unknown_type', sprintf( 'Unknown change_type: %s', $pending->change_type ) );
		}

		if ( is_wp_error( $result ) || false === $result ) {
			// Apply failed AFTER the atomic status flip. Persist the error message
			// to the row so the dashboard can surface "approved but failed" instead
			// of silently showing the row as applied. Snapshot above is the
			// recovery point.
			$err_msg = is_wp_error( $result )
				? $result->get_error_code() . ': ' . $result->get_error_message()
				: 'apply_returned_false: handler returned false (no specific error).';
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'cc_pending_changes',
				array(
					'status'      => 'apply_failed',
					'review_note' => substr( '[apply_failed] ' . $err_msg, 0, 1000 ),
				),
				array( 'id' => (int) $pending_id )
			);
			if ( ! $bulk_mode ) {
				delete_transient( 'cc_assistant_dashboard_summary' );
				require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
				CC_Assistant_Weekly_Advisor::invalidate();
			}
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return new WP_Error( 'apply_failed', 'Apply returned false. Check the post and the proposed value.' );
		}

		$recorded = CC_Assistant_Recovery::complete( $pending, $proposed, $result, $snapshot_id );
		if ( is_wp_error( $recorded ) ) { return $recorded; }
		global $wpdb;
		$done = $wpdb->update( $wpdb->prefix . 'cc_pending_changes', array( 'status' => 'approved' ),
			array( 'id' => (int) $pending_id, 'status' => 'applying' ) );
		if ( 1 !== $done ) { return new WP_Error( 'apply_completion_failed', 'Writes completed but the completion record failed. Inspect recovery before retrying.' ); }

		// Full front-end regeneration for Elementor-touching applies. The
		// flush_elementor_css_cache() calls inside the individual handlers
		// clear Elementor's GLOBAL file cache + page caches, but they never
		// regenerate THIS post's CSS, never drop its per-post element
		// (markup) cache, and only purge SiteGround site-wide. That gap is
		// why operators had to manually run Elementor "Regenerate CSS &
		// Data" + an SG purge after every approved full import before the
		// front end stopped serving stale markup. Full imports take the
		// heavy path (global Elementor file-cache clear + site-wide SG
		// purge); every other Elementor op takes the cheap per-post path.
		// The method is fully guarded internally — a missing Elementor or
		// SG Optimizer install can never fatal the apply.
		if ( $pending->post_id && ! $is_cluster_change ) {
			$elementor_types = array(
				'elementor_widget_update',
				'elementor_widget_add',
				'elementor_widget_remove',
				'elementor_container_add',
				'elementor_section_rebuild',
				'elementor_accordion_item_add',
				'elementor_accordion_item_remove',
				'elementor_section_content_replace',
				'elementor_full_import',
				// v0.60.1: an applied revert rewrites content too — without the
				// heavy cache path an SG-cached Elementor page serves
				// pre-revert markup right after approval.
				'snapshot_restore',
			);
			if ( in_array( $pending->change_type, $elementor_types, true ) ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-cache.php';
				// A Theme Builder template (Elementor or ElementsKit) affects every
				// page that uses it, so a per-post CSS regen is not enough — force
				// the heavy/global flush (files_manager + site-wide SG purge).
				$tpl_types     = class_exists( 'CC_Assistant_REST_API' )
					? CC_Assistant_REST_API::template_post_types()
					: array( 'elementor_library', 'elementskit_template', 'elementskit_content' );
				$is_template   = in_array( get_post_type( (int) $pending->post_id ), $tpl_types, true );
				$heavy_flush   = $is_template || 'elementor_full_import' === $pending->change_type;
				CC_Assistant_Cache::regenerate_elementor_post(
					(int) $pending->post_id,
					$heavy_flush
				);
				// v0.51.6: any apply touching a template also refreshes Pro's
				// conditions cache — a template edited through the queue must
				// keep serving its location without a manual re-save.
				if ( $is_template ) {
					self::regenerate_theme_builder_conditions();
				}
			}
		}

		$edit_id = null;
		if ( $pending->post_id && ! $is_cluster_change ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
			// Synthesize success_metrics if the operator did not supply them.
			// Derives target_query from the focus keyword (Rank Math/Yoast/
			// AIOSEO postmeta) and target_position from current weighted-avg
			// GSC position minus 3 (capped at 1). Updates the pending row in
			// place so compute_outcome can score the verdict against intent
			// once the measurement window closes. No-op if metrics already
			// set, focus keyword missing, or GSC unavailable for the URL.
			CC_Assistant_Edit_Outcomes::maybe_default_success_metrics( (int) $pending->id, (int) $pending->post_id );
			$edit_id = CC_Assistant_Edit_Outcomes::record_edit(
				$pending->post_id,
				$pending->id,
				$pending->change_type,
				$pending->change_summary,
				$reviewer_id
			);
		}

		// Fire the after-apply action so post-apply verifier (and any future
		// listeners) can react. Body-content change types only — meta/cluster
		// changes don't move cosine and the verifier no-ops on them anyway,
		// but we keep the firing condition explicit here so the event log is
		// clean. Verifier listener schedules a 30s cron tick — does NOT block.
		if ( in_array( $pending->change_type, array( 'post_content_update', 'elementor_widget_update', 'elementor_widget_add', 'elementor_widget_remove', 'elementor_container_add', 'elementor_section_rebuild', 'elementor_accordion_item_add', 'elementor_accordion_item_remove', 'elementor_full_import' ), true ) ) {
			do_action( 'cc_assistant_after_apply', (int) $pending_id, (int) $pending->post_id, (string) $pending->change_type );
		}

		// 0.14: record this apply in the rolling activity log so the next
		// session bootstrap surfaces "what happened in the last 48 h" without
		// needing to scan pending_changes + edits + snapshots separately.
		// Fire for EVERY change type, not just body-content — meta/title/category
		// changes are exactly the kind of thing a returning operator wants to see.
		require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
		CC_Assistant_Activity_Log::record(
			CC_Assistant_Activity_Log::TYPE_EDIT_APPLIED,
			sprintf( '[%s] %s', $pending->change_type, mb_substr( (string) $pending->change_summary, 0, 400 ) ),
			$pending->post_id ? (int) $pending->post_id : null,
			(int) $pending->id,
			'human'
		);

		if ( ! $bulk_mode ) {
			$is_text_change = in_array( $pending->change_type, array( 'post_content_update', 'elementor_widget_update', 'elementor_widget_add', 'elementor_widget_remove', 'elementor_container_add', 'elementor_section_rebuild', 'elementor_accordion_item_add', 'elementor_accordion_item_remove', 'meta_update' ), true );
			self::run_post_apply_cleanup( $is_text_change, (bool) $edit_id );
		}

		// 0.18.1: stamp the plugin's most recent apply on this post so the
		// post_modified conflict guard above can distinguish our own writes
		// from an external edit on subsequent sibling pendings. Without this,
		// bulk-approving a publish_draft alongside meta/slug/content siblings
		// on the same post causes every sibling queued before the publish to
		// trip the guard, because publish bumps post_modified above their
		// queued_at. Skip on cluster changes (no post_id) and on apply paths
		// that did not touch this post.
		if ( $pending->post_id && ! $is_cluster_change ) {
			update_post_meta( (int) $pending->post_id, '_cc_assistant_last_internal_apply', current_time( 'mysql', 1 ) );
			update_post_meta( (int) $pending->post_id, '_cc_assistant_last_internal_hash', CC_Assistant_Integrity::post_hash( (int) $pending->post_id ) );

			// v0.51.5: Elementor-level applies write postmeta only, so the post
			// row's post_modified stayed frozen at the last editor save no matter
			// how much real content changed — leaving the freshness score AND the
			// sitemap <lastmod> stale (pages heavily edited in June/July reported
			// "modified 265 days ago"). Bump it here so one point covers every
			// elementor_* change type. Safe with the race guard: its baseline is
			// max(queued_at, last_internal_apply), stamped this same second.
			if ( 0 === strpos( (string) $pending->change_type, 'elementor_' ) ) {
				self::bump_post_modified( (int) $pending->post_id );
			}
		}

		// v0.75.0: recapture the RENDERED page and verify the intended change
		// is actually there. An apply that reports success while the page is
		// unchanged (the noindex serialization bug) becomes verdict=FAILED
		// instead of a trusted success. Never allowed to break the apply.
		$page_facts_verification = null;
		// v0.76: a template, popup, or kit change has no page of its own to
		// read back. Verify it where it renders: a page the template's display
		// conditions cover, or the front page for a kit change. Without this
		// every Theme Builder edit reported success with zero evidence.
		$verify_post_id = (int) $pending->post_id;
		$verify_note    = '';
		try {
			if ( 'kit_setting_update' === $pending->change_type ) {
				$verify_post_id = (int) get_option( 'page_on_front', 0 );
				$verify_note    = $verify_post_id ? 'verified on the front page (kit change)' : '';
			} elseif ( $verify_post_id > 0 ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-rest-api.php';
				if ( CC_Assistant_REST_API::is_template_post_type( get_post_type( $verify_post_id ) ) ) {
					require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
					$sample         = CC_Assistant_Page_Facts::sample_page_for_template( $verify_post_id );
					$verify_note    = $sample ? sprintf( 'template #%d verified on covered page #%d', $verify_post_id, $sample ) : sprintf( 'template #%d: no published page matches its display conditions, so no rendered verification was possible', $verify_post_id );
					$verify_post_id = (int) $sample;
				}
			}
		} catch ( \Throwable $e ) {
			$verify_post_id = 0;
			$verify_note    = 'verification target lookup threw: ' . $e->getMessage();
		}
		if ( $verify_post_id && ! $is_cluster_change && 'trash_post' !== $pending->change_type ) {
			try {
				require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
				$page_facts_verification = CC_Assistant_Page_Facts::after_apply( $verify_post_id, $pending, $proposed );
				if ( is_array( $page_facts_verification ) && '' !== $verify_note ) {
					$page_facts_verification['verified_on_post'] = $verify_post_id;
					$page_facts_verification['note'] = $verify_note . '. ' . ( isset( $page_facts_verification['note'] ) ? $page_facts_verification['note'] : '' );
				}
			} catch ( \Throwable $e ) {
				$page_facts_verification = array( 'verdict' => 'unavailable', 'note' => 'page_facts threw: ' . $e->getMessage() );
			}
		} elseif ( '' !== $verify_note ) {
			$page_facts_verification = array( 'verdict' => 'unavailable', 'note' => $verify_note );
		}

		return array(
			'success'     => true,
			'snapshot_id' => $snapshot_id,
			'edit_id'     => $edit_id,
			'change_type' => $pending->change_type,
			'result'      => $result,
			'page_facts_verification' => $page_facts_verification,
		);
	}

	/**
	 * v0.58: apply an approved revert — restore the pre-edit snapshot queued
	 * by propose_revert. force=true is deliberate: the drift the restore
	 * guard would flag IS the applied edit being reverted (the human approved
	 * this restore knowing exactly that), and restore_snapshot takes its own
	 * pre-restore snapshot first, so the revert itself stays reversible.
	 */
	private static function apply_snapshot_restore( $pending_id, $post_id, $proposed ) {
		global $wpdb;
		$snapshot_id = is_array( $proposed ) && isset( $proposed['snapshot_id'] ) ? (int) $proposed['snapshot_id'] : 0;
		if ( $snapshot_id <= 0 ) {
			return new WP_Error( 'invalid_payload', 'snapshot_restore requires a snapshot_id in the payload.' );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
		$restored = CC_Assistant_Snapshots::restore_snapshot( $snapshot_id, true );
		if ( is_wp_error( $restored ) ) {
			return $restored;
		}

		// v0.60.1: record the pre-restore snapshot restore_snapshot() just
		// captured into this pending row's current_value — that is what the
		// inbox Rollback button restores if the human undoes the revert.
		$pre_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cc_snapshots WHERE post_id = %d AND snapshot_type = 'pre_restore' ORDER BY id DESC LIMIT 1",
				(int) $post_id
			)
		);
		if ( $pre_id > 0 ) {
			$wpdb->update(
				$wpdb->prefix . 'cc_pending_changes',
				array( 'current_value' => wp_json_encode( array( 'pre_restore_snapshot_id' => $pre_id ) ) ),
				array( 'id' => (int) $pending_id )
			);
		}

		return array(
			'restored_snapshot_id'    => $snapshot_id,
			'pre_restore_snapshot_id' => $pre_id > 0 ? $pre_id : null,
			'post_id'                 => (int) $post_id,
			'reverted_edit_id'        => is_array( $proposed ) && isset( $proposed['edit_id'] ) ? (int) $proposed['edit_id'] : null,
		);
	}

	/**
	 * Set post_modified/post_modified_gmt to now without wp_update_post (which
	 * would re-save content, fire save hooks, and spawn a revision). Direct row
	 * update + cache clean is the minimal truthful freshness bump.
	 */
	private static function bump_post_modified( $post_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			array( 'ID' => (int) $post_id )
		);
		clean_post_cache( (int) $post_id );
	}

	/**
	 * Cache-invalidation work that normally runs at the end of a single apply.
	 * In bulk-apply mode the caller defers it and invokes this once after the
	 * loop instead of N times during the loop. Saves ~10 DB queries per applied
	 * change (transient deletes + a SELECT-and-loop over trends keys) — the
	 * dominant cost on bulk-approve clicks with 8+ changes.
	 *
	 * @param bool $had_text_change True if any applied change was a text-touching
	 *                              type (post_content_update, elementor_widget_update,
	 *                              meta_update). Drives link-graph rebuild scheduling
	 *                              and content-audits cache bust.
	 * @param bool $had_edit_record True if at least one apply recorded a cc_edits
	 *                              row (i.e. the batch wasn't entirely cluster_create
	 *                              / cluster_assign / rewrite_outline). Drives the
	 *                              outcomes-rollup + trends transient invalidation.
	 */
	public static function run_post_apply_cleanup( $had_text_change, $had_edit_record ) {
		if ( $had_text_change ) {
			if ( ! wp_next_scheduled( 'cc_assistant_link_graph_rebuild' ) ) {
				wp_schedule_single_event( time() + 30, 'cc_assistant_link_graph_rebuild' );
			}
			require_once CC_ASSISTANT_DIR . 'includes/class-content-audits.php';
			CC_Assistant_Content_Audits::bust_cache();
			delete_transient( 'cc_inbox_integrity_banners' );
		}

		if ( $had_edit_record ) {
			delete_transient( 'cc_assistant_outcomes_rollup_30d' );
			delete_transient( 'cc_assistant_recent_edit_outcomes' );
			delete_transient( 'cc_assistant_pending_outcomes' );
			// v0.25.0 renamed these to the by-post variants. Delete both keys
			// so an upgraded site doesn't sit on stale dashboard data for 30 min
			// after the first apply, and pre-upgrade installs stay clean too.
			delete_transient( 'cc_assistant_recent_edits_by_post_v2' );
			delete_transient( 'cc_assistant_pending_outcomes_by_post_v2' );
			// Trends cache that refresh_queue reads from also goes stale once
			// any new cc_edits row exists (the row's page_hash now belongs to
			// the recently-edited suppression set).
			global $wpdb;
			$keys = $wpdb->get_col(
				"SELECT option_name FROM {$wpdb->options}
				 WHERE option_name LIKE '_transient_cc_seo_tools_trends_%'"
			);
			foreach ( (array) $keys as $k ) {
				$transient_name = str_replace( '_transient_', '', $k );
				delete_transient( $transient_name );
			}
		}

		// Pending-list and weekly-advisor caches always go stale on apply
		// regardless of change_type because the inbox count + advisor inputs
		// shift even when no cc_edits row is recorded.
		delete_transient( 'cc_assistant_dashboard_summary' );
		require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
		CC_Assistant_Weekly_Advisor::invalidate();
		delete_transient( 'cc_assistant_advisor_recompute_lock' );
		wp_cache_delete( 'cc_pending_count', 'cc-assistant' );
	}

	/**
	 * Clear Elementor's CSS cache. Called once after a bulk apply touches one
	 * or more elementor_widget_update changes, instead of once per apply
	 * inside apply_elementor_widget() — Elementor regenerates CSS lazily on
	 * the next page load anyway, so only the final clear matters.
	 */
	public static function flush_elementor_css_cache() {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$plugin = \Elementor\Plugin::instance();
			if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
				$plugin->files_manager->clear_cache();
			}
		}

		// 0.16: aggressive multi-cache bust. The Elementor file-cache clear
		// above only invalidates the generated CSS files — the rendered HTML
		// fragment cached by page-cache plugins or by Rank Math's schema
		// transient stays stale, and operators see the OLD page even after a
		// successful apply. Try each known cache plugin in turn (each guarded
		// behind a class/function check so we don't fail when the plugin
		// isn't installed).

		// 1. WordPress object cache
		wp_cache_flush();

		// 2. Rank Math schema transients per-post (keyed by post URL)
		delete_transient( 'rank_math_schema_cache' );

		// 3. WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// 4. W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// 5. WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// 6. LiteSpeed Cache — purges everything; plugin handles its own
		// granularity if a specific URL is needed.
		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// 7. SiteGround SuperCacher
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// 8. Cloudflare via the Super Page Cache for Cloudflare plugin
		if ( class_exists( 'SW_CLOUDFLARE_PAGECACHE' ) && function_exists( 'sw_cloudflare_pagecache' ) ) {
			$cf = sw_cloudflare_pagecache();
			if ( is_object( $cf ) && method_exists( $cf, 'purge_cache' ) ) {
				$cf->purge_cache();
			}
		}

		// 9. WP Engine cache
		if ( class_exists( 'WpeCommon' ) ) {
			if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
				WpeCommon::purge_memcached();
			}
			if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
				WpeCommon::purge_varnish_cache();
			}
		}

		// 10. Kinsta cache
		if ( class_exists( 'Kinsta\\Cache' ) && function_exists( 'KinstaCache' ) ) {
			$kc = KinstaCache();
			if ( is_object( $kc ) && isset( $kc->kinsta_cache_purge ) && method_exists( $kc->kinsta_cache_purge, 'purge_complete_caches' ) ) {
				$kc->kinsta_cache_purge->purge_complete_caches();
			}
		}

		// 11. Generic action other cache plugins listen to.
		do_action( 'cc_assistant_after_cache_flush' );
	}

	private static function apply_post_field( $post_id, $proposed ) {
		if ( empty( $proposed['field'] ) || ! in_array( $proposed['field'], self::ALLOWED_POST_FIELDS, true ) ) {
			return new WP_Error( 'field_not_allowed', 'Field is not in the allowed list.' );
		}
		$value = isset( $proposed['value'] ) ? $proposed['value'] : '';
		if ( 'post_status' === $proposed['field'] ) {
			$valid = self::validate_field_status( $value );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}

		// post_author stores a numeric user ID (resolved at queue time). Verify
		// the user still exists at apply time — an account can be deleted
		// between queue and approval, and wp_update_post() would happily write
		// a dangling author ID into schema/dataLayer output.
		if ( 'post_author' === $proposed['field'] ) {
			if ( ! ctype_digit( (string) $value ) || ! get_user_by( 'id', (int) $value ) ) {
				return new WP_Error( 'author_user_missing', 'Proposed post_author user no longer exists — requeue with a valid user.' );
			}
			$value = (int) $value;
		}

		// Capture old permalink BEFORE the update so we can create a 301 if the
		// slug actually moved. Rank Math's own Watcher only registers its
		// post_updated hook when the general.redirections_post_redirect setting
		// is on, which users frequently leave off — so we belt-and-suspender
		// the redirect ourselves.
		$old_url = null;
		if ( 'post_name' === $proposed['field'] ) {
			$old_url = get_permalink( $post_id );
		}

		$result = wp_update_post(
			array(
				'ID'                => $post_id,
				$proposed['field'] => wp_slash( $value ),
			),
			true
		);

		if ( ! is_wp_error( $result ) && 'post_name' === $proposed['field'] ) {
			// The Custom Permalinks plugin (and similar) stores a per-post URL
			// override in postmeta `custom_permalink` and applies it via a
			// filter on get_permalink(). It does NOT auto-sync when post_name
			// changes through wp_update_post(), leaving a stale custom URL in
			// place forever — defeats canonical detection, _wp_old_slug
			// redirects, every cache flush. Clear it so WP falls back to the
			// natural slug-based URL. No-op if the postmeta doesn't exist.
			delete_post_meta( $post_id, 'custom_permalink' );

			// Bust the post cache so the get_permalink() call below returns
			// the fresh new URL. Without this, persistent object caches
			// (memcached, Redis) or filter-layer caches like Polylang's URL
			// translation cache will return the OLD URL, the $old !== $new
			// check below sees them equal, and we silently skip creating the
			// redirect that should have been the whole point of this branch.
			clean_post_cache( $post_id );

			if ( $old_url ) {
				$new_url = get_permalink( $post_id );
				if ( $new_url && $old_url !== $new_url ) {
					self::create_slug_change_redirect( $post_id, $old_url, $new_url );
				}
			}
		}

		return $result;
	}

	/**
	 * Create a 301 from $old_url to $new_url using whichever SEO plugin is
	 * active. Silently no-ops if none is supported. Failures here do NOT fail
	 * the slug change — the caller's post_updated has already succeeded.
	 */
	private static function create_slug_change_redirect( $post_id, $old_url, $new_url ) {
		$old_path = wp_make_link_relative( $old_url );
		if ( '' === $old_path || '/' === $old_path ) {
			return;
		}
		// Defensive path-level self-loop guard. Caller already checks
		// $old_url !== $new_url, but URL-string differences can hide path
		// equality (full URL vs relative, trailing slash variance, host
		// canonicalization differences). Compare the relative paths after
		// normalization; if they collapse to the same path, skip — creating
		// a redirect from a URL to its own canonical path is what produced
		// the eroflufkin.com /contact-us/ + /insurance-billing-info/ + /blog/
		// loop class on 2026-05-08.
		$new_path = wp_make_link_relative( $new_url );
		if ( ltrim( (string) $old_path, '/' ) === ltrim( (string) $new_path, '/' ) ) {
			return;
		}
		// Rank Math stores sources without the leading slash.
		$source_pattern = ltrim( $old_path, '/' );

		if ( class_exists( '\RankMath\Redirections\Redirection' ) ) {
			try {
				$redirection = \RankMath\Redirections\Redirection::from(
					array(
						'url_to'      => $new_url,
						'header_code' => 301,
					)
				);
				$redirection->set_nocache( true );
				$redirection->add_source( $source_pattern, 'exact' );
				$redirection->add_destination( $new_url );
				$redirection->save();
				if ( class_exists( '\RankMath\Redirections\Cache' ) ) {
					\RankMath\Redirections\Cache::purge_by_object_id( (int) $post_id, 'post' );
				}
				// A slug change writes a redirect as a side effect, so the
				// resolver's map is stale here too.
				CC_Assistant_URL_Resolver::flush();
			} catch ( \Throwable $e ) {
				if ( function_exists( 'error_log' ) ) {
					error_log( '[CC Assistant] Rank Math redirect creation failed: ' . $e->getMessage() );
				}
			}
		}
		// Yoast Premium and AIOSEO Pro have their own APIs; skip until a user
		// actually needs them — both require paid tiers.
	}

	/**
	 * Normalize a redirect source or destination to a /-prefixed path for
	 * loop-detection comparison. Local helper — class-rest-api has its own
	 * normalize_redirect_source(), but this class doesn't load that file
	 * during apply, so we replicate the relevant behavior here.
	 */
	private static function normalize_path_for_loop_check( $url_or_path ) {
		$value = trim( (string) $url_or_path );
		if ( '' === $value ) {
			return '';
		}
		if ( '/' === substr( $value, 0, 1 ) ) {
			return $value;
		}
		$relative = wp_make_link_relative( $value );
		if ( '' !== $relative && '/' === substr( $relative, 0, 1 ) ) {
			return $relative;
		}
		return '/' . ltrim( $value, '/' );
	}

	/**
	 * v0.53 — apply (or revert) a term_update payload: taxonomy term
	 * description via wp_update_term, and Rank Math term SEO title /
	 * description via update_term_meta. Only keys present in the payload are
	 * touched, so the queued current_value snapshot doubles as the exact
	 * revert payload. An empty seo_* value deletes the term meta row rather
	 * than storing a zero-byte string.
	 */
	/**
	 * Swap a media URL everywhere it is stored.
	 *
	 * Not post-scoped: one approval can touch post_content, postmeta, options
	 * and termmeta at once, which is the whole point — the URL is the unit of
	 * work, not the post.
	 *
	 * Partial success is the expected outcome when a row drifted after
	 * queueing, so a run that writes SOME rows still returns true and records
	 * what it skipped. Only a run that wrote nothing is a failure.
	 */
	private static function apply_asset_reference_replace( $pending_id, $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['targets'] ) ) {
			return new WP_Error( 'asset_plan_invalid', 'Stored plan has no targets.' );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-asset-references.php';
		$outcome = CC_Assistant_Asset_References::apply_plan( $payload );
		if ( is_wp_error( $outcome ) ) {
			return $outcome;
		}

		// A run that wrote SOME rows is a success, but silently reporting plain
		// "applied" hides which ones it missed. Real incident: a swap wrote 2 of
		// 5 locations, skipped the homepage, and reported success — the operator
		// only found out by viewing the page. Record the shortfall on the row so
		// the inbox shows it.
		$skipped = array_merge(
			isset( $outcome['drifted'] ) ? $outcome['drifted'] : array(),
			isset( $outcome['failed'] ) ? $outcome['failed'] : array()
		);
		if ( ! empty( $outcome['written'] ) && ! empty( $skipped ) ) {
			$lines = array();
			foreach ( array_slice( $skipped, 0, 6 ) as $entry ) {
				$lines[] = $entry['label'] . ' — ' . $entry['reason'];
			}
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'cc_pending_changes',
				array(
					'review_note' => substr(
						sprintf(
							'[partial] Wrote %d location(s), skipped %d: %s',
							count( $outcome['written'] ),
							count( $skipped ),
							implode( '; ', $lines )
						),
						0,
						1000
					),
				),
				array( 'id' => (int) $pending_id )
			);
		}

		if ( empty( $outcome['written'] ) ) {
			$reasons = array();
			foreach ( array_merge( $outcome['drifted'], $outcome['failed'] ) as $entry ) {
				$reasons[] = $entry['label'] . ' — ' . $entry['reason'];
			}
			return new WP_Error(
				'asset_replace_wrote_nothing',
				'No location could be rewritten. ' . ( $reasons ? implode( '; ', array_slice( $reasons, 0, 5 ) ) : 'No reason recorded.' )
			);
		}

		// apply_plan() already invalidates each row it wrote (post cache, meta
		// cache, the specific option plus alloptions). A wp_cache_flush() here
		// would additionally drop every other plugin's cache site-wide, which
		// on a install with persistent Redis/Memcached is a real load spike for
		// no benefit. Only the page/CSS caches still need clearing.
		self::flush_elementor_css_cache();

		return true;
	}

	/**
	 * v0.69 — assign one taxonomy term across a planned set of posts.
	 *
	 * Partial success is success, same doctrine as the asset swap: a product
	 * deleted between queueing and approval should not block the other 300.
	 * The shortfall is written to review_note so the inbox shows it rather
	 * than reporting a clean "applied".
	 */
	private static function apply_bulk_term_assign( $pending_id, $payload ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-bulk-terms.php';
		$outcome = CC_Assistant_Bulk_Terms::apply_plan( $payload );
		if ( is_wp_error( $outcome ) ) {
			return $outcome;
		}
		if ( ! empty( $outcome['skipped'] ) ) {
			$lines = array();
			foreach ( array_slice( $outcome['skipped'], 0, 6 ) as $s ) {
				$lines[] = '#' . $s['id'] . ' — ' . $s['reason'];
			}
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'cc_pending_changes',
				array(
					'review_note' => substr(
						sprintf(
							'[partial] Tagged %d post(s), skipped %d: %s',
							(int) $outcome['written'],
							count( $outcome['skipped'] ),
							implode( '; ', $lines )
						),
						0,
						1000
					),
				),
				array( 'id' => (int) $pending_id )
			);
		}
		return true;
	}

	private static function apply_term_update( $payload ) {
		$term_id  = isset( $payload['term_id'] ) ? (int) $payload['term_id'] : 0;
		$taxonomy = isset( $payload['taxonomy'] ) ? sanitize_key( $payload['taxonomy'] ) : '';
		if ( $term_id <= 0 || '' === $taxonomy ) {
			return new WP_Error( 'term_payload_invalid', 'term_id and taxonomy are required in the payload.' );
		}
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'term_not_found', sprintf( 'Term %d not found in taxonomy "%s" at apply time.', $term_id, $taxonomy ) );
		}

		if ( array_key_exists( 'description', $payload ) ) {
			// WP's default 'pre_term_description' filter is wp_filter_kses with
			// the MINIMAL tag set — it strips h2/h3/p/ul/li, the exact tags a
			// category description needs, whenever the applying user lacks
			// unfiltered_html (multisite, editor-role reviewer, some REST auth
			// contexts). The payload already passed the plugin's own lint and
			// the human approval gate, so upgrade sanitization to POST-level
			// kses (still sanitized: no scripts/iframes) for this one write,
			// then restore whatever was there.
			$swapped = false;
			if ( has_filter( 'pre_term_description', 'wp_filter_kses' ) ) {
				remove_filter( 'pre_term_description', 'wp_filter_kses' );
				add_filter( 'pre_term_description', 'wp_filter_post_kses' );
				$swapped = true;
			}
			$updated = wp_update_term( $term_id, $taxonomy, array( 'description' => (string) $payload['description'] ) );
			if ( $swapped ) {
				remove_filter( 'pre_term_description', 'wp_filter_post_kses' );
				add_filter( 'pre_term_description', 'wp_filter_kses' );
			}
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
		foreach ( array( 'seo_title' => 'rank_math_title', 'seo_description' => 'rank_math_description' ) as $field => $meta_key ) {
			if ( ! array_key_exists( $field, $payload ) ) {
				continue;
			}
			$value = (string) $payload[ $field ];
			if ( '' === trim( $value ) ) {
				delete_term_meta( $term_id, $meta_key );
			} else {
				update_term_meta( $term_id, $meta_key, $value );
			}
		}
		clean_term_cache( $term_id, $taxonomy );
		return true;
	}

	private static function apply_post_meta( $post_id, $proposed ) {
        if ( '_cc_assistant_content_workflow' === ( $proposed['key'] ?? '' ) ) { return new WP_Error( 'workflow_binding_protected', 'Workflow bindings are server-owned.', array( 'status' => 403 ) ); }
		if ( empty( $proposed['key'] ) ) {
			return new WP_Error( 'key_missing', 'Meta key is required.' );
		}
		$value = isset( $proposed['value'] ) ? $proposed['value'] : '';

		// Plugin-managed schema keys never store garbage. An empty value means
		// "remove the emitter" — delete the row instead of leaving a 0-byte
		// meta behind (five location pages on one install carried exactly
		// those no-op rows). A non-empty value must parse as JSON; this is the
		// last line of defense behind the queue-time check and protects
		// against rows queued by older plugin versions.
		$schema_keys = array( '_cc_assistant_schema_jsonld', '_cc_emergency_service_schema' );
		if ( in_array( (string) $proposed['key'], $schema_keys, true ) ) {
			if ( ! is_string( $value ) || '' === trim( (string) $value ) ) {
				delete_post_meta( $post_id, $proposed['key'] );
				return true;
			}
			$schema_decoded = json_decode( trim( (string) $value ), true );
			if ( ! is_array( $schema_decoded ) ) {
				return new WP_Error(
					'invalid_schema_json',
					'Refusing to store invalid JSON-LD in ' . $proposed['key'] . ' (' . json_last_error_msg() . '). Re-propose the schema with a valid payload.'
				);
			}
		}

		// v0.71.0: clearing a value that is already clear is a success, not a
		// failure. update_post_meta() returns false for "no change", and the
		// scalar guard below deliberately excludes empty proposed values, so a
		// clear-an-empty-key pending reported apply_failed while the site was
		// already in exactly the requested state (hit on erofirving 981/982).
		// Deleting rather than storing '' also avoids leaving a 0-byte row,
		// matching the delete-on-empty semantics the schema keys get above.
		if ( is_scalar( $value ) && '' === trim( (string) $value ) ) {
			$stored_now = get_post_meta( $post_id, $proposed['key'], true );
			if ( ! is_scalar( $stored_now ) || '' === trim( (string) $stored_now ) ) {
				delete_post_meta( $post_id, $proposed['key'] );
				return true;
			}
		}

		$result = update_post_meta( $post_id, $proposed['key'], wp_slash( $value ) );
		if ( false !== $result && get_post_meta( $post_id, $proposed['key'], true ) != $value ) {
			return new WP_Error( 'meta_verification_failed', 'Stored metadata does not match the approved value.' );
		}
		if ( false !== $result ) {
			return $result;
		}
		// update_post_meta() returns false when the stored value already
		// equals the proposed value (a no-op, not a failure). Approving a
		// pending whose value is already live must be idempotent — v0.48.0's
		// rebuild-in-place queues SEO metas that are often already correct
		// and three of them "failed" on the first live run for exactly this.
		$stored = get_post_meta( $post_id, $proposed['key'], true );
		// Only treat as an idempotent no-op when both sides are scalar and the
		// proposed value is non-empty. Guards against an "Array to string"
		// warning on serialized meta, and against a genuine empty-value write
		// failure being reported as success.
		if ( is_scalar( $stored ) && is_scalar( $value ) && '' !== (string) $value
			&& (string) $stored === (string) $value ) {
			return true;
		}
		// v0.71.4: array-valued meta (rank_math_robots) reaches here too. The
		// scalar guard above skips arrays entirely, so re-approving a pending
		// whose array value is already live returned false and reported
		// apply_failed on a write that was correct. Compare arrays properly.
		if ( is_array( $stored ) && is_array( $value ) && $stored == $value ) {
			return true;
		}
		return false;
	}

	/**
	 * Write a merged Rank Math Schema Builder row. The queue stored the WHOLE
	 * resulting array under 'full', so this handler never re-derives the merge
	 * — it writes what the reviewer approved, byte for byte, and the same
	 * shape on current_value makes the revert path a straight re-apply.
	 *
	 * Deliberately does NOT touch rank_math_rich_snippet: that legacy scalar is
	 * a master switch and setting it "off" suppresses Rank Math's entire
	 * @graph, not just the node being edited.
	 */
	private static function apply_rank_math_schema( $post_id, $payload, $check_drift = true ) {
		$post_id  = (int) $post_id;
		$meta_key = isset( $payload['meta_key'] ) ? (string) $payload['meta_key'] : '';
		if ( $post_id <= 0 || ! preg_match( '/^rank_math_schema_[A-Za-z][A-Za-z0-9_]*$/', $meta_key ) ) {
			return new WP_Error( 'schema_payload_invalid', 'A post_id and a rank_math_schema_<Type> meta_key are required in the payload.' );
		}
		if ( ! isset( $payload['full'] ) || ! is_array( $payload['full'] ) || empty( $payload['full'] ) ) {
			return new WP_Error( 'schema_payload_invalid', sprintf( 'Payload for "%s" carries no full schema array. Refusing to write.', $meta_key ) );
		}

		// The row must still exist. This tool merges into schema Rank Math
		// already renders; it never authors a new node, because a real Schema
		// Builder template also carries shortcode/isPrimary/metadata a merge
		// payload has no way to supply.
		$existing = maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) );
		if ( ! is_array( $existing ) ) {
			return new WP_Error( 'schema_row_missing', sprintf( '"%s" no longer holds a schema array on post %d. It was changed or removed after this was queued — re-read and re-queue.', $meta_key, $post_id ) );
		}

		// Saving schema in Rank Math's own UI does not reliably bump
		// post_modified, so the plugin's usual race guard is blind to it.
		// base_hash was stamped from the row we merged onto; if the live row
		// has moved, the merge is stale and applying it would silently discard
		// whatever the human changed. Skipped on revert, where the live row is
		// deliberately the post-merge state.
		if ( $check_drift && ! empty( $payload['base_hash'] ) ) {
			$live_hash = md5( (string) maybe_serialize( $existing ) );
			if ( $live_hash !== (string) $payload['base_hash'] ) {
				return new WP_Error(
					'schema_drifted',
					sprintf( '"%s" on post %d changed after this was queued (likely edited in Rank Math). Refusing to overwrite. Re-read with get_rank_math_schema and re-queue.', $meta_key, $post_id )
				);
			}
		}

		// update_post_meta() wp_unslash()es what it is given, so pass slashed
		// data or a literal backslash in any leaf would be eaten.
		$result = update_post_meta( $post_id, $meta_key, wp_slash( $payload['full'] ) );

		// JSON-LD is rendered into the page HTML, so a schema change is a
		// front-end change and the page cache must go. The centralized flush
		// in apply() only fires for Elementor change types, and the rollback
		// path has no generic flush at all — so purge here, where BOTH
		// directions pass through. Without this, render_probe (a loopback
		// fetch) verifies the pre-change HTML and reports the fix as failed.
		require_once CC_ASSISTANT_DIR . 'includes/class-cache.php';
		CC_Assistant_Cache::regenerate_elementor_post( $post_id, false );
		delete_transient( 'rank_math_schema_cache' );

		if ( false !== $result ) {
			return true;
		}
		// false also means "already identical" — idempotent, not a failure.
		$stored = maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) );
		return is_array( $stored ) && $stored == $payload['full']; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- key order is not meaningful in a schema array.
	}

	/**
	 * Replace post_content with a proposed value.
	 *
	 * Belt-and-suspenders rollback: the snapshot table already captured the
	 * current state in apply_pending(), and we also save a native WordPress
	 * post revision so the editor's Revisions panel can roll back too.
	 */
	private static function apply_post_content( $post_id, $proposed ) {
		if ( ! isset( $proposed['content'] ) ) {
			return new WP_Error( 'content_missing', 'Proposed content is required.' );
		}
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}

		// Native WP revision of the current state, in addition to the cc_snapshots row.
		if ( function_exists( 'wp_save_post_revision' ) ) {
			wp_save_post_revision( (int) $post_id );
		}

		$permalink_token = self::capture_custom_permalink( $post_id );

		$result = wp_update_post(
			array(
				'ID'           => (int) $post_id,
				'post_content' => wp_slash( (string) $proposed['content'] ),
			),
			true
		);

		self::restore_custom_permalink( $permalink_token );

		return $result;
	}

	/**
	 * Snapshot a post's `custom_permalink` before a wp_update_post() call.
	 *
	 * The Custom Permalinks plugin recomputes a post's URL override inside the
	 * save hooks that wp_update_post() fires. On a BULK approve — several
	 * pendings applied in one PHP request — that recomputation leaks the FIRST
	 * post's path onto every later post in the batch, de-duplicated with a -N
	 * suffix. Each of those posts then 404s at its real URL while still serving
	 * at the leaked one, and `get_post` keeps reporting the correct URL, so
	 * nothing surfaces the breakage until the front end is probed.
	 *
	 * Observed on erofirving 2026-07-27: seven one-character content patches
	 * approved together: the first post kept its URL and the remaining six were
	 * rewritten to that post's path with suffixes -2 … -7, in exact pending-id
	 * order. The same signature appeared 2026-07-06 with a different first post,
	 * which is what rules out stale per-post data and identifies it as
	 * per-request bleed.
	 *
	 * Returns a token for restore_custom_permalink() to consume.
	 */
	private static function capture_custom_permalink( $post_id ) {
		$post_id  = (int) $post_id;
		$existing = get_post_meta( $post_id, 'custom_permalink', true );

		return array(
			'post_id' => $post_id,
			'existed' => metadata_exists( 'post', $post_id, 'custom_permalink' ),
			'value'   => is_scalar( $existing ) ? (string) $existing : '',
		);
	}

	/**
	 * Undo any custom_permalink change the save hooks made.
	 *
	 * No-op when the value is unchanged, so sites without Custom Permalinks —
	 * and posts whose URL genuinely did not move — pay nothing. A post that had
	 * no override before keeps having none; a post that had one keeps exactly
	 * the one it had. Deliberate slug moves are NOT routed through here: those
	 * go through apply_post_field(), which clears the override on purpose.
	 */
	private static function restore_custom_permalink( $token ) {
		if ( ! is_array( $token ) || empty( $token['post_id'] ) ) {
			return;
		}

		$post_id = (int) $token['post_id'];
		$current = get_post_meta( $post_id, 'custom_permalink', true );
		$current = is_scalar( $current ) ? (string) $current : '';

		if ( $current === $token['value'] ) {
			return;
		}

		if ( ! empty( $token['existed'] ) ) {
			update_post_meta( $post_id, 'custom_permalink', $token['value'] );
		} else {
			delete_post_meta( $post_id, 'custom_permalink' );
		}

		// The override feeds get_permalink() through a filter, so the cached
		// post object must go or the rest of this request keeps the wrong URL.
		clean_post_cache( $post_id );
	}

	private static function apply_elementor_widget( $post_id, $proposed, $bulk_mode = false, $restoring = false ) {
		if ( empty( $proposed['widget_id'] ) || ! isset( $proposed['settings'] ) || ! is_array( $proposed['settings'] ) ) {
			return new WP_Error( 'invalid_payload', 'widget_id and settings (object) are required.' );
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return new WP_Error( 'no_elementor_data', 'Post has no Elementor data.' );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_elementor_data', 'Could not parse _elementor_data JSON.' );
		}

		$found = self::walk_and_update( $data, $proposed['widget_id'], $proposed['settings'], $restoring );
		if ( ! $found ) {
			return new WP_Error( 'widget_not_found', 'Widget id not found in this post.' );
		}

		// wp_json_encode returns false on encoding errors (non-UTF-8 bytes,
		// recursion, etc). If we wrote false back as the meta value, the
		// post's Elementor data would be silently blanked.
		$json = wp_json_encode( $data );
		if ( false === $json ) {
			return new WP_Error( 'encode_failed', 'Could not re-encode Elementor data — aborting to avoid corrupting the post.' );
		}
		require_once __DIR__ . '/class-elementor-builder.php';
		$saved = CC_Assistant_Elementor_Builder::save_tree( $post_id, $data, $restoring );
		if ( is_wp_error( $saved ) ) { return $saved; }

		// Clear Elementor's CSS cache so the change shows on the front-end.
		// In bulk mode the caller flushes once after the whole batch — a single
		// cache clear is enough since Elementor regenerates CSS lazily on the
		// next page load.
		if ( ! $bulk_mode ) {
			self::flush_elementor_css_cache();
		}

		return true;
	}

	private static function walk_and_update( &$elements, $target_id, $new_settings, $replace = false ) {
		foreach ( $elements as &$el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( isset( $el['id'] ) && $el['id'] === $target_id ) {
				if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
					$el['settings'] = array();
				}
				$el['settings'] = $replace ? $new_settings : array_merge( $el['settings'], $new_settings );
				return true;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				if ( self::walk_and_update( $el['elements'], $target_id, $new_settings, $replace ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Apply elementor_widget_add: inserts a new widget into a parent container.
	 * proposed: { parent_id, widget_type, settings, position? }
	 * Persists the assigned new widget id back into proposed_value so the
	 * rollback handler knows what to splice out.
	 */
	private static function apply_elementor_widget_add( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		if ( empty( $proposed['parent_id'] ) || empty( $proposed['widget_type'] ) || ! isset( $proposed['settings'] ) || ! is_array( $proposed['settings'] ) ) {
			return new WP_Error( 'invalid_payload', 'parent_id, widget_type, and settings are required.' );
		}
		// Allow empty tree: a brand-new page created via draft_create_post has
		// no _elementor_data yet; appending the first widget should bootstrap
		// the tree rather than refusing with no_elementor_data.
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id, true );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$new_id = CC_Assistant_Elementor_Builder::add_widget(
			$tree,
			(string) $proposed['parent_id'],
			(string) $proposed['widget_type'],
			(array) $proposed['settings'],
			isset( $proposed['position'] ) ? (int) $proposed['position'] : null
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $post_id, $tree );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		// Persist the assigned new_widget_id so rollback can find it.
		global $wpdb;
		$proposed['_applied_widget_id'] = $new_id;
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'proposed_value' => wp_json_encode( $proposed ) ),
			array( 'id' => (int) $pending_id )
		);
		if ( ! $bulk_mode ) {
			self::flush_elementor_css_cache();
		}
		return array( 'new_widget_id' => $new_id );
	}

	/**
	 * Apply elementor_widget_remove: splice a widget out of the tree.
	 * proposed: { widget_id }
	 * Stashes the removed node + the original parent_id + original index
	 * inside proposed_value so rollback can put it back at the same spot.
	 */
	private static function apply_elementor_widget_remove( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		if ( empty( $proposed['widget_id'] ) ) {
			return new WP_Error( 'invalid_payload', 'widget_id is required.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		// Snapshot parent + index for rollback BEFORE we remove.
		$located = CC_Assistant_Elementor_Builder::find_node( $tree, (string) $proposed['widget_id'] );
		if ( null === $located ) {
			return new WP_Error( 'widget_not_found', sprintf( 'Widget id %s not found in this post.', $proposed['widget_id'] ) );
		}
		$parent_id_for_rollback = '';
		// Walk the tree once more to find the parent's id (find_node gives us the
		// parent_elements array but not its container id). The walk is cheap.
		$parent_id_for_rollback = self::find_parent_id( $tree, (string) $proposed['widget_id'], '' );
		$orig_index = $located['index'];

		$removed = CC_Assistant_Elementor_Builder::remove_node( $tree, (string) $proposed['widget_id'] );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $post_id, $tree );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		// 0.16: post-apply VERIFICATION. Re-load the freshly-saved tree from
		// the database (NOT from our in-memory copy) and confirm the target
		// widget is actually gone. Catches the race condition where another
		// process (Elementor editor) wrote back stale data between our
		// save_tree() call and the verification read — in that case, our
		// remove was clobbered and we must NOT report success.
		$reloaded = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $reloaded ) ) {
			return new WP_Error( 'verify_failed', 'Could not reload tree to verify removal: ' . $reloaded->get_error_message() );
		}
		$still_there = CC_Assistant_Elementor_Builder::find_node( $reloaded, (string) $proposed['widget_id'] );
		if ( null !== $still_there ) {
			return new WP_Error(
				'remove_clobbered',
				sprintf(
					'Widget %s was removed from the tree but the post DB still contains it — a concurrent write (likely Elementor editor save) clobbered the change. Close any open Elementor editor for this post and re-queue.',
					(string) $proposed['widget_id']
				),
				array( 'status' => 409 )
			);
		}

		global $wpdb;
		$proposed['_removed_node']        = $removed;
		$proposed['_removed_parent_id']   = $parent_id_for_rollback;
		$proposed['_removed_orig_index']  = $orig_index;
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'proposed_value' => wp_json_encode( $proposed ) ),
			array( 'id' => (int) $pending_id )
		);
		if ( ! $bulk_mode ) {
			self::flush_elementor_css_cache();
		}
		return array( 'removed_widget_id' => $proposed['widget_id'] );
	}

	/**
	 * Find a parent's id by walking the tree. Returns '' if target is at root.
	 * Helper for apply_elementor_widget_remove's rollback metadata.
	 */
	private static function find_parent_id( $tree, $target_id, $current_parent_id ) {
		if ( ! is_array( $tree ) ) {
			return '';
		}
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) && $node['id'] === $target_id ) {
				return $current_parent_id;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$found = self::find_parent_id( $node['elements'], $target_id, isset( $node['id'] ) ? $node['id'] : '' );
				if ( '' !== $found || self::tree_contains( $node['elements'], $target_id ) ) {
					return $found;
				}
			}
		}
		return '';
	}

	private static function tree_contains( $tree, $target_id ) {
		if ( ! is_array( $tree ) ) {
			return false;
		}
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) && $node['id'] === $target_id ) {
				return true;
			}
			if ( ! empty( $node['elements'] ) && self::tree_contains( $node['elements'], $target_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Apply elementor_container_add: inserts a new section/container with
	 * optional pre-built children. proposed: { parent_id, el_type?, settings,
	 * position?, children? }
	 */
	private static function apply_elementor_container_add( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		if ( ! isset( $proposed['parent_id'] ) || ! isset( $proposed['settings'] ) ) {
			return new WP_Error( 'invalid_payload', 'parent_id and settings are required (parent_id can be empty string for root).' );
		}
		// Allow empty tree: appending a root container ("" parent_id) to a
		// freshly-created page should bootstrap the tree. This was the
		// failure mode in pre-v0.18 where the first 8 sections queued on
		// post 9983 all failed with no_elementor_data even though parent_id="".
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id, true );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$new_id = CC_Assistant_Elementor_Builder::add_container(
			$tree,
			(string) $proposed['parent_id'],
			isset( $proposed['settings'] ) && is_array( $proposed['settings'] ) ? $proposed['settings'] : array(),
			isset( $proposed['position'] ) ? (int) $proposed['position'] : null,
			isset( $proposed['children'] ) && is_array( $proposed['children'] ) ? $proposed['children'] : array(),
			isset( $proposed['el_type'] ) ? (string) $proposed['el_type'] : 'container',
			(int) $post_id
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $post_id, $tree );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		global $wpdb;
		$proposed['_applied_container_id'] = $new_id;
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'proposed_value' => wp_json_encode( $proposed ) ),
			array( 'id' => (int) $pending_id )
		);
		if ( ! $bulk_mode ) {
			self::flush_elementor_css_cache();
		}
		return array( 'new_container_id' => $new_id );
	}

	/**
	 * v0.44: Apply elementor_section_rebuild — atomic "add new section + remove
	 * old section" in a SINGLE read-modify-write. The page never lands in a
	 * transient duplicate state and never half-applies: the new section is built
	 * and the old one removed on the same in-memory tree, and we WRITE ONCE only
	 * after BOTH succeed. If either step errors, nothing is written.
	 * proposed: { parent_id?, el_type?, settings, position?, children?, remove_id }.
	 */
	private static function apply_elementor_section_rebuild( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		if ( ! isset( $proposed['settings'] ) || empty( $proposed['remove_id'] ) ) {
			return new WP_Error( 'invalid_payload', 'settings and remove_id are required.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		// Capture the old section's root index BEFORE touching anything so
		// rollback can restore it at the same spot (root sections = the 99% case).
		$old_root_index = -1;
		foreach ( $tree as $i => $node ) {
			if ( isset( $node['id'] ) && (string) $node['id'] === (string) $proposed['remove_id'] ) {
				$old_root_index = (int) $i;
				break;
			}
		}
		// 1) Add the new section into the in-memory tree.
		$new_id = CC_Assistant_Elementor_Builder::add_container(
			$tree,
			isset( $proposed['parent_id'] ) ? (string) $proposed['parent_id'] : '',
			is_array( $proposed['settings'] ) ? $proposed['settings'] : array(),
			isset( $proposed['position'] ) ? (int) $proposed['position'] : null,
			isset( $proposed['children'] ) && is_array( $proposed['children'] ) ? $proposed['children'] : array(),
			isset( $proposed['el_type'] ) ? (string) $proposed['el_type'] : 'container',
			(int) $post_id
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id; // nothing written yet — atomic abort
		}
		// 2) Remove the old section from the SAME in-memory tree.
		$removed = CC_Assistant_Elementor_Builder::remove_node( $tree, (string) $proposed['remove_id'] );
		if ( is_wp_error( $removed ) ) {
			return $removed; // still nothing written — no orphan add can land
		}
		// 3) Single write — both operations, or neither.
		$save = CC_Assistant_Elementor_Builder::save_tree( $post_id, $tree );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		global $wpdb;
		$proposed['_applied_container_id'] = $new_id;
		$proposed['_removed_node']         = $removed;
		$proposed['_removed_root_index']   = $old_root_index;
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'proposed_value' => wp_json_encode( $proposed ) ),
			array( 'id' => (int) $pending_id )
		);
		if ( ! $bulk_mode ) {
			self::flush_elementor_css_cache();
		}
		return array( 'new_container_id' => $new_id, 'removed_id' => (string) $proposed['remove_id'] );
	}

	/**
	 * Apply elementor_accordion_item_add: adds an item to a nested-accordion.
	 * proposed: { accordion_widget_id, title, content_html, position? }
	 */
	private static function apply_elementor_accordion_item_add( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		if ( empty( $proposed['accordion_widget_id'] ) || empty( $proposed['title'] ) ) {
			return new WP_Error( 'invalid_payload', 'accordion_widget_id and title are required.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$new_item_id = CC_Assistant_Elementor_Builder::add_accordion_item(
			$tree,
			(string) $proposed['accordion_widget_id'],
			(string) $proposed['title'],
			isset( $proposed['content_html'] ) ? (string) $proposed['content_html'] : '',
			isset( $proposed['position'] ) ? (int) $proposed['position'] : null
		);
		if ( is_wp_error( $new_item_id ) ) {
			return $new_item_id;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $post_id, $tree );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		global $wpdb;
		$proposed['_applied_item_id'] = $new_item_id;
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'proposed_value' => wp_json_encode( $proposed ) ),
			array( 'id' => (int) $pending_id )
		);
		if ( ! $bulk_mode ) {
			self::flush_elementor_css_cache();
		}
		return array( 'new_item_id' => $new_item_id );
	}

	/**
	 * Apply elementor_accordion_item_remove: deletes one item (Q + A) from a
	 * nested-accordion's settings.items[] AND the matching answer container in
	 * the accordion's elements[]. Both must be removed together — leaving the
	 * settings.items entry with no matching content container makes the FAQ
	 * render an empty body when that title is clicked.
	 *
	 * proposed: { accordion_widget_id, item_id }
	 * Stashes the removed item (including its `_inner_container`) into
	 * proposed_value so rollback can splice it back at the same index.
	 */
	private static function apply_elementor_accordion_item_remove( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		if ( empty( $proposed['accordion_widget_id'] ) || empty( $proposed['item_id'] ) ) {
			return new WP_Error( 'invalid_payload', 'accordion_widget_id and item_id are required.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		// Capture pre-remove index so rollback can put the item back at the same
		// position rather than appending it at the end.
		$located = CC_Assistant_Elementor_Builder::find_node( $tree, (string) $proposed['accordion_widget_id'] );
		$orig_index = -1;
		if ( null !== $located && isset( $located['node']['settings']['items'] ) && is_array( $located['node']['settings']['items'] ) ) {
			foreach ( $located['node']['settings']['items'] as $i => $it ) {
				if ( isset( $it['_id'] ) && (string) $it['_id'] === (string) $proposed['item_id'] ) {
					$orig_index = (int) $i;
					break;
				}
			}
		}
		$removed_item = CC_Assistant_Elementor_Builder::remove_accordion_item(
			$tree,
			(string) $proposed['accordion_widget_id'],
			(string) $proposed['item_id']
		);
		if ( is_wp_error( $removed_item ) ) {
			return $removed_item;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $post_id, $tree );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		global $wpdb;
		$proposed['_removed_item']       = $removed_item;
		$proposed['_removed_orig_index'] = $orig_index;
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'proposed_value' => wp_json_encode( $proposed ) ),
			array( 'id' => (int) $pending_id )
		);
		if ( ! $bulk_mode ) {
			self::flush_elementor_css_cache();
		}
		return array( 'removed_item_id' => (string) $proposed['item_id'] );
	}

	/**
	 * Apply a cluster_create proposal: create the cluster + add members.
	 * Stores the new cluster_id back into proposed_value so rollback can find
	 * what to delete.
	 */
	private static function apply_cluster_create( $pending_id, $proposed ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();

		if ( empty( $proposed['name'] ) ) {
			return new WP_Error( 'name_missing', 'Cluster name is required.' );
		}
		$cluster_id = CC_Assistant_Topic_Clusters::create_cluster( array(
			'name'           => (string) $proposed['name'],
			'description'    => (string) ( $proposed['description'] ?? '' ),
			'pillar_post_id' => isset( $proposed['pillar_post_id'] ) && $proposed['pillar_post_id'] ? (int) $proposed['pillar_post_id'] : null,
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $cluster_id ) ) {
			return $cluster_id;
		}

		foreach ( (array) ( $proposed['supporting_post_ids'] ?? array() ) as $sp_id ) {
			$sp_id = (int) $sp_id;
			if ( $sp_id > 0 && $sp_id !== (int) ( $proposed['pillar_post_id'] ?? 0 ) ) {
				CC_Assistant_Topic_Clusters::add_member( $cluster_id, $sp_id, CC_Assistant_Topic_Clusters::ROLE_SUPPORTING, 'claude' );
			}
		}

		// Persist the new cluster_id into proposed_value so rollback can target it.
		global $wpdb;
		$proposed['_applied_cluster_id'] = $cluster_id;
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'proposed_value' => wp_json_encode( $proposed ) ),
			array( 'id' => (int) $pending_id )
		);
		return array( 'cluster_id' => $cluster_id );
	}

	private static function apply_cluster_assign( $proposed ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();

		$cluster_id = (int) ( $proposed['cluster_id'] ?? 0 );
		$post_id    = (int) ( $proposed['post_id'] ?? 0 );
		$role       = ( ( $proposed['role'] ?? '' ) === CC_Assistant_Topic_Clusters::ROLE_PILLAR )
			? CC_Assistant_Topic_Clusters::ROLE_PILLAR
			: CC_Assistant_Topic_Clusters::ROLE_SUPPORTING;

		if ( $cluster_id <= 0 || $post_id <= 0 ) {
			return new WP_Error( 'invalid_payload', 'cluster_id and post_id are required.' );
		}
		if ( ! CC_Assistant_Topic_Clusters::get_cluster( $cluster_id ) ) {
			return new WP_Error( 'cluster_not_found', 'Cluster no longer exists.' );
		}
		CC_Assistant_Topic_Clusters::add_member( $cluster_id, $post_id, $role, 'claude' );
		return true;
	}

	private static function apply_publish_draft( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		if ( 'publish' === $post->post_status ) {
			return new WP_Error( 'already_published', 'Post is already published.' );
		}

		// v0.39 page-quality gate. Refuse to publish a page carrying defects that
		// are never acceptable live (placeholder / lorem text, duplicate-template
		// cards). Warnings (no images, flat-list IA, thin content) do NOT block —
		// legitimate text-only pages still publish. Override per-post by setting
		// the _cc_publish_gate_override postmeta.
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		if ( method_exists( 'CC_Assistant_Pre_Publish', 'evaluate_publish_gate' ) ) {
			$gate = CC_Assistant_Pre_Publish::evaluate_publish_gate( $post_id );
			if ( empty( $gate['pass'] ) ) {
				$msgs = array();
				foreach ( (array) $gate['blocking'] as $b ) {
					if ( ! empty( $b['message'] ) ) {
						$msgs[] = $b['message'];
					}
				}
				return new WP_Error(
					'publish_gate_blocked',
					'Publish blocked by the page-quality gate: ' . implode( ' | ', $msgs )
						. ' Fix these, or set postmeta _cc_publish_gate_override=1 on the post to bypass.',
					array( 'status' => 422, 'gate' => $gate )
				);
			}
		}

		// Same per-request custom_permalink bleed guard as apply_post_content():
		// publishing fires the identical save hooks, so a draft published in a
		// bulk approve can have another post's path written onto it.
		$permalink_token = self::capture_custom_permalink( $post_id );

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		self::restore_custom_permalink( $permalink_token );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// v0.10.31: re-apply any Polylang translation pair stored on insert.
		// Polylang's post_translations taxonomy term can drop the pair when
		// the post status transitions from draft to publish (the term gets
		// re-evaluated against a now-published row and silently fails to
		// re-link). draft_create_post wrote the intended pair_map to
		// _cc_assistant_polylang_pair postmeta; replay it here so the pair
		// survives the publish transition. Idempotent: re-linking an already-
		// paired post is a no-op. We clear the meta after a successful
		// re-link so manual edits to the pair (via WP admin) take precedence
		// over the original draft intent on later apply runs.
		$pair_map = get_post_meta( $post_id, '_cc_assistant_polylang_pair', true );
		if ( is_array( $pair_map ) && ! empty( $pair_map ) && class_exists( 'CC_Assistant_Multilingual' ) && CC_Assistant_Multilingual::is_active() ) {
			$pair_result = CC_Assistant_Multilingual::link_translations( array( $pair_map ) );
			if ( ! is_wp_error( $pair_result ) && empty( $pair_result['failed'] ) ) {
				delete_post_meta( $post_id, '_cc_assistant_polylang_pair' );
			}
			// On failure, leave the meta in place so a later retry (manual or
			// scripted) can use it. Failure here does NOT block the publish
			// itself — the post is already live; the worst case is the
			// reviewer sees a "+" icon in the language column and re-pairs
			// manually.
		}

		// v0.51.5 — a newly published Theme Builder template only takes effect
		// once Elementor Pro's conditions cache is regenerated (the cache
		// indexes _elementor_conditions of PUBLISHED templates only, so the
		// draft-time conditions written by create_draft_post were dormant
		// until now). Guarded: a cache-regen failure must never roll back a
		// publish that already succeeded.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-api.php';
		if ( CC_Assistant_REST_API::is_template_post_type( get_post_type( $post_id ) ) ) {
			// v0.51.7 — replay the template identity AFTER publish. The
			// wp_update_post above re-fires Elementor's save hooks, which
			// reset _elementor_template_type to "page" and delete
			// _elementor_conditions. Restamping from the intent recorded at
			// create time makes the approved identity durable.
			$intent = get_post_meta( $post_id, '_cc_assistant_template_intent', true );
			if ( is_array( $intent ) && ! empty( $intent['template_type'] ) ) {
				self::stamp_template_identity(
					$post_id,
					(string) $intent['template_type'],
					isset( $intent['conditions'] ) ? (array) $intent['conditions'] : array()
				);
				delete_post_meta( $post_id, '_cc_assistant_template_intent' );
			}
			self::regenerate_theme_builder_conditions();
			try {
				require_once CC_ASSISTANT_DIR . 'includes/class-cache.php';
				CC_Assistant_Cache::regenerate_elementor_post( (int) $post_id, true );
			} catch ( \Throwable $e ) {
				// Swallow: template is live; operator can re-save conditions in
				// Theme Builder if the cache regen API shape changed.
			}
		}

		return $result;
	}

	/**
	 * v0.51.7 — write a Theme Builder template's full identity: type meta,
	 * builder edit mode, the elementor_library_type taxonomy term, and its
	 * display conditions (empty array deletes the conditions meta). Shared by
	 * template create, the post-publish replay, and the repair path on
	 * /theme-templates/refresh-conditions.
	 */
	public static function stamp_template_identity( $post_id, $template_type, $conditions = array() ) {
		$template_type = sanitize_key( (string) $template_type );
		if ( '' === $template_type ) {
			return false;
		}
		update_post_meta( $post_id, '_elementor_template_type', $template_type );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		if ( 'elementor_library' === get_post_type( $post_id ) && taxonomy_exists( 'elementor_library_type' ) ) {
			wp_set_object_terms( (int) $post_id, $template_type, 'elementor_library_type' );
		}
		$clean = array();
		foreach ( (array) $conditions as $cond ) {
			$cond = trim( (string) $cond );
			if ( '' !== $cond && preg_match( '/^(include|exclude)\/[a-z0-9_\/-]+$/i', $cond ) ) {
				$clean[] = $cond;
			}
		}
		if ( ! empty( $clean ) ) {
			update_post_meta( $post_id, '_elementor_conditions', $clean );
		}
		return true;
	}

	/**
	 * v0.51.6 — regenerate the Elementor Pro Theme Builder conditions cache.
	 * The cache (option elementor_pro_theme_builder_conditions) is Pro's
	 * runtime index of which template serves which location; it goes stale
	 * when templates are created, edited, trashed, or re-conditioned outside
	 * Pro's own save flow. Every guard is defensive: this must never throw
	 * into a caller that just finished a successful apply.
	 *
	 * @return bool True when a regenerate actually ran.
	 */
	public static function regenerate_theme_builder_conditions() {
		try {
			if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
				$tb_module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
				if ( method_exists( $tb_module, 'get_conditions_manager' ) ) {
					$cond_mgr = $tb_module->get_conditions_manager();
					if ( method_exists( $cond_mgr, 'get_cache' ) && method_exists( $cond_mgr->get_cache(), 'regenerate' ) ) {
						$cond_mgr->get_cache()->regenerate();
						return true;
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Fall through to false.
		}
		return false;
	}

	/**
	 * Apply a trash_post proposal: move a page/post to the Trash (reversible —
	 * restorable from WP Trash). Used so the assistant can RETIRE an old page
	 * (e.g. a page being replaced by a rebuild) through the approval inbox instead
	 * of the operator hand-deleting it in wp-admin. Plugin-scoped: only trashes
	 * post types in the allowlist. A pre_apply snapshot is taken in apply_pending.
	 */
	private static function apply_trash_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'already_trashed', 'Post is already in the Trash.' );
		}
		$allowed = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		if ( ! in_array( $post->post_type, (array) $allowed, true ) ) {
			return new WP_Error( 'post_type_not_allowed', sprintf( 'Refusing to trash post type "%s" (not in allowlist).', $post->post_type ) );
		}
		$result = wp_trash_post( $post_id );
		if ( ! $result ) {
			return new WP_Error( 'trash_failed', 'wp_trash_post failed.' );
		}
		return array( 'trashed' => (int) $post_id );
	}

	/**
	 * Apply a create_redirect proposal: write a 301 (or other code) to whichever
	 * SEO plugin's redirect manager is active. Currently supports Rank Math.
	 */
	private static function apply_create_redirect( $proposed ) {
		$source      = isset( $proposed['source'] ) ? (string) $proposed['source'] : '';
		$destination = isset( $proposed['destination'] ) ? (string) $proposed['destination'] : '';
		$http_code   = isset( $proposed['http_code'] ) ? (int) $proposed['http_code'] : 301;

		if ( ! in_array( $http_code, array( 301, 302, 307, 410, 451 ), true ) ) {
			$http_code = 301;
		}
		// 410/451 are "gone" statuses: Rank Math stores them without a
		// destination (url_to is ignored), so only 3xx codes require one.
		$needs_destination = ! in_array( $http_code, array( 410, 451 ), true );
		if ( '' === $source || ( $needs_destination && '' === $destination ) ) {
			return new WP_Error( 'redirect_invalid_payload', $needs_destination ? 'source and destination are required.' : 'source is required.' );
		}
		if ( ! class_exists( '\RankMath\Redirections\Redirection' ) ) {
			return new WP_Error( 'rank_math_unavailable', 'Rank Math Redirections module is not active. Enable it in Rank Math > Dashboard > Modules before approving redirect-create pendings.' );
		}

		// Apply-time self-loop guard. The queue-time check in handle_draft_redirect
		// catches new pendings, but a pending row created on an older plugin
		// version (or proposed via SQL/import) might still reach apply. Refuse
		// here too — there is no scenario in which approving a source==destination
		// redirect is correct; it can only break the URL.
		$src_path = self::normalize_path_for_loop_check( $source );
		$dst_path = '' !== $destination ? self::normalize_path_for_loop_check( $destination ) : '';
		if ( '' !== $src_path && '' !== $dst_path && $src_path === $dst_path ) {
			return new WP_Error(
				'redirect_self_loop',
				sprintf( 'Refusing to apply self-redirect: source path "%s" matches destination "%s". A redirect from a URL to itself produces an infinite loop.', $src_path, $destination )
			);
		}

		try {
			$redirection = \RankMath\Redirections\Redirection::from(
				array(
					'url_to'      => $destination,
					'header_code' => (string) $http_code,
				)
			);
			$redirection->set_nocache( true );

			// Strip protocol/host so the source matches Rank Math's relative-path expectation.
			$relative = wp_make_link_relative( $source );
			$source_pattern = '' !== $relative ? ltrim( $relative, '/' ) : ltrim( $source, '/' );
			if ( '' === $source_pattern ) {
				return new WP_Error( 'redirect_invalid_source', 'Source resolves to root after normalization. Refusing to redirect the homepage.' );
			}

			$redirection->add_source( $source_pattern, 'exact' );
			$redirection->add_destination( $destination );
			$redirection_id = $redirection->save();

			// Rank Math has no purge_all() method — it has purge( $ids ) and
			// purge_by_object_id(). For a brand-new redirect, purging by the
			// new ID is a no-op (nothing's cached yet). Skip — Rank Math's
			// natural cache eviction handles it.

			return array(
				'redirection_id' => $redirection_id,
				'source'         => $source_pattern,
				'destination'    => $destination,
				'http_code'      => $http_code,
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'redirect_save_failed', 'Failed to save redirect: ' . $e->getMessage() );
		}
	}

	/**
	 * Apply a delete_redirect proposal: permanently remove a Rank Math redirect
	 * by id. Rank Math's DB::delete() also purges its redirection cache for the
	 * ids, so the rule stops firing immediately. Closes the gap where the
	 * assistant could create redirects but never remove a stale, wrong, or
	 * looping one (which previously forced a manual Rank Math step).
	 */
	private static function apply_delete_redirect( $proposed ) {
		$id = isset( $proposed['id'] ) ? (int) $proposed['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'redirect_invalid_payload', 'A redirect id is required to delete.' );
		}
		if ( ! class_exists( '\RankMath\Redirections\DB' ) ) {
			return new WP_Error( 'rank_math_unavailable', 'Rank Math Redirections module is not active.' );
		}
		try {
			$deleted = \RankMath\Redirections\DB::delete( array( $id ) );
			if ( ! $deleted ) { return new WP_Error( 'redirect_delete_failed', 'The redirect could not be deleted. Its recovery record was retained.' ); }
			if ( class_exists( '\RankMath\Redirections\Cache' ) ) {
				\RankMath\Redirections\Cache::purge( array( $id ) );
			}
			return array(
				'deleted_id'    => $id,
				'rows_affected' => (int) $deleted,
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'redirect_delete_failed', 'Failed to delete redirect: ' . $e->getMessage() );
		}
	}

	/**
	 * Apply an untrash_redirect proposal: flip a trashed/inactive Rank Math
	 * redirect back to active by id, then purge its cache so it fires again.
	 */
	private static function apply_untrash_redirect( $proposed ) {
		$id = isset( $proposed['id'] ) ? (int) $proposed['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'redirect_invalid_payload', 'A redirect id is required to untrash.' );
		}
		if ( ! class_exists( '\RankMath\Redirections\DB' ) ) {
			return new WP_Error( 'rank_math_unavailable', 'Rank Math Redirections module is not active.' );
		}
		try {
			$updated = \RankMath\Redirections\DB::change_status( array( $id ), 'active' );
			if ( false === $updated ) {
				return new WP_Error( 'redirect_untrash_failed', 'Rank Math rejected the status change (invalid status value).' );
			}
			if ( class_exists( '\RankMath\Redirections\Cache' ) ) {
				\RankMath\Redirections\Cache::purge( array( $id ) );
			}
			return array(
				'untrashed_id'  => $id,
				'status'        => 'active',
				'rows_affected' => (int) $updated,
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'redirect_untrash_failed', 'Failed to untrash redirect: ' . $e->getMessage() );
		}
	}

	/**
	 * Apply a category-assignment proposal. proposed.category_ids is an array
	 * of term IDs; proposed.append controls whether existing categories are
	 * preserved (true) or replaced (false, default — wp_set_post_terms default).
	 *
	 * Validates each term still exists at apply time so a stale pending
	 * doesn't silently no-op. Returns the resulting term-id list on success.
	 */
	private static function apply_categories( $post_id, $proposed ) {
		if ( empty( $proposed['category_ids'] ) || ! is_array( $proposed['category_ids'] ) ) {
			return new WP_Error( 'category_ids_required', 'category_ids array is required.' );
		}
		$ids = array();
		foreach ( $proposed['category_ids'] as $cid ) {
			$cid = (int) $cid;
			if ( $cid > 0 ) {
				$ids[] = $cid;
			}
		}
		if ( empty( $ids ) ) {
			return new WP_Error( 'category_ids_empty', 'No valid category ids in proposal.' );
		}
		// Re-validate at apply time — a term may have been deleted between
		// queue and apply. Refuse rather than silently dropping ids.
		$missing = array();
		foreach ( $ids as $cid ) {
			if ( ! get_term( $cid, 'category' ) ) {
				$missing[] = $cid;
			}
		}
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'category_not_found',
				sprintf( 'Category term(s) no longer exist: %s', implode( ', ', $missing ) )
			);
		}

		$append = ! empty( $proposed['append'] );
		$result = wp_set_post_terms( (int) $post_id, $ids, 'category', $append );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// wp_set_post_terms returns array of term taxonomy IDs (or false on
		// failure for any reason other than is_wp_error). Treat false as failure.
		if ( false === $result ) {
			return new WP_Error( 'category_set_failed', 'wp_set_post_terms returned false.' );
		}
		return array(
			'post_id'      => (int) $post_id,
			'category_ids' => $ids,
			'append'       => $append,
		);
	}

	/**
	 * Apply an emergency_service_schema proposal: store the schema JSON in
	 * postmeta. cc-assistant.php's wp_head hook reads this key and injects the
	 * JSON-LD on the front-end. Stored as a JSON string for easy diff/rollback.
	 */
	private static function apply_emergency_service_schema( $post_id, $proposed ) {
		if ( empty( $proposed ) || ! is_array( $proposed ) ) {
			return new WP_Error( 'schema_invalid_payload', 'Proposed schema payload is empty or not an object.' );
		}
		if ( empty( $proposed['@context'] ) || empty( $proposed['@type'] ) ) {
			return new WP_Error( 'schema_invalid_jsonld', 'Schema must include @context and @type.' );
		}
		$json = wp_json_encode( $proposed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new WP_Error( 'schema_encode_failed', 'Could not encode schema to JSON.' );
		}
		update_post_meta( (int) $post_id, '_cc_emergency_service_schema', wp_slash( $json ) );
		return array( 'post_id' => (int) $post_id, 'schema_set' => true, 'bytes' => strlen( $json ) );
	}

	/**
	 * Roll back a previously approved change by re-applying its current_value
	 * (the state before the change). Snapshots first so the rollback itself
	 * can be undone.
	 */
	public static function rollback_pending( $pending_id, $reviewer_id ) {
		global $wpdb;
		$lock = CC_Assistant_Write_Lock::acquire();
		if ( is_wp_error( $lock ) ) { return $lock; }
		$claimed = $wpdb->update( $wpdb->prefix . 'cc_pending_changes', array( 'status' => 'rolling_back' ),
			array( 'id' => (int) $pending_id, 'status' => 'approved' ) );
		if ( 1 !== $claimed ) { CC_Assistant_Write_Lock::release(); return new WP_Error( 'rollback_conflict', 'Another operation has reviewed or claimed this change.', array( 'status' => 409 ) ); }
		$finished = false;
		register_shutdown_function( static function () use ( $pending_id, &$finished ) {
			if ( ! $finished ) { self::fail_rollback( $pending_id, 'Rollback interrupted; inspect the recovery snapshot.' ); }
		} );
		try {
			$result = self::rollback_pending_impl( $pending_id, $reviewer_id );
			if ( is_wp_error( $result ) ) {
				self::fail_rollback( $pending_id, $result->get_error_message() );
			}
			return $result;
		} catch ( \Throwable $e ) {
			$row = CC_Assistant_Pending_Changes::get( $pending_id );
			if ( $row && 'rolled_back' === $row->status ) {
				return array( 'success' => true, 'warnings' => array( 'Rollback completed, but follow-up processing failed: ' . $e->getMessage() ) );
			}
			self::fail_rollback( $pending_id, $e->getMessage() );
			return new WP_Error( 'rollback_interrupted', 'Rollback failed: ' . $e->getMessage() );
		} finally { $finished = true; CC_Assistant_Write_Lock::release(); }
	}

	private static function fail_rollback( $id, $message ) {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'cc_pending_changes',
			array( 'status' => 'rollback_failed', 'review_note' => substr( '[rollback_failed] ' . $message, 0, 1000 ) ),
			array( 'id' => (int) $id, 'status' => 'rolling_back' ) );
	}

	private static function rollback_pending_impl( $pending_id, $reviewer_id ) {
		global $wpdb;
		$pending = CC_Assistant_Pending_Changes::get( $pending_id );
		if ( ! $pending ) {
			return new WP_Error( 'not_found', 'Pending change not found.' );
		}
		if ( 'rolling_back' !== $pending->status ) {
			return new WP_Error( 'not_approved', 'Only approved changes can be rolled back.' );
		}

		$recovery_types = array( 'asset_reference_replace', 'category_update', 'create_redirect', 'delete_redirect', 'elementor_full_import', 'elementor_section_content_replace', 'emergency_service_schema', 'trash_post', 'untrash_redirect' );
		$current = json_decode( $pending->current_value, true );
		if ( ! is_array( $current ) && in_array( $pending->change_type, $recovery_types, true ) ) { $current = array(); }
		if ( ! is_array( $current ) ) {
			return new WP_Error( 'invalid_current', 'Stored current_value is not valid JSON.' );
		}

		// Cluster operations and rewrite_outline approvals don't touch post
		// content, so a pre_rollback snapshot would be useless. Same set the
		// apply path uses.
		$no_post_snapshot = in_array(
			$pending->change_type,
			array( 'cluster_create', 'cluster_assign', 'rewrite_outline', 'term_update', 'bulk_term_assign', 'plugin_setting_update', 'kit_setting_update' ),
			true
		);
		$is_cluster_change = $no_post_snapshot;

		$snapshot_id = null;
		if ( $pending->post_id && ! $is_cluster_change ) {
			$snapshot_id = CC_Assistant_Snapshots::snapshot_post(
				$pending->post_id,
				'pre_rollback',
				sprintf( 'Rollback of pending #%d', $pending->id )
			);
		}

		if ( is_wp_error( $snapshot_id ) ) { return $snapshot_id; }

		$result = null;
		switch ( $pending->change_type ) {
			case 'meta_update':
				$result = self::apply_post_field( $pending->post_id, $current );
				break;
			case 'postmeta_update':
				$result = self::apply_post_meta( $pending->post_id, $current );
				break;
			case 'rank_math_schema_update':
				// current_value carries the pre-merge array in full, so the
				// revert is the identical write in the other direction. Drift
				// check off: the live row is the post-merge state by design.
				$result = self::apply_rank_math_schema( $pending->post_id, $current, false );
				break;
			case 'term_update':
				// Revert = re-apply the queued current_value snapshot (the
				// prior description / term SEO metas for the same keys).
				$result = self::apply_term_update( $current );
				break;
			case 'plugin_setting_update':
				// Restores the ENTIRE prior option value, not just the leaf: a
				// partial restore on a serialized settings blob is how a
				// plugin's configuration gets corrupted.
				require_once CC_ASSISTANT_DIR . 'includes/class-setting-writer.php';
				$result = CC_Assistant_Setting_Writer::revert_plan( $current );
				break;
			case 'kit_setting_update':
				require_once CC_ASSISTANT_DIR . 'includes/class-kit-writer.php';
				$result = CC_Assistant_Kit_Writer::revert_plan( $current );
				break;
			case 'bulk_term_assign':
				// Each target carries its prior term ids, so the rollback puts
				// every post back exactly as it was — including posts that had
				// no term at all, which a naive "remove the term we added"
				// would get right only by luck.
				require_once CC_ASSISTANT_DIR . 'includes/class-bulk-terms.php';
				$result = CC_Assistant_Bulk_Terms::revert_plan( $current );
				break;
			case 'elementor_widget_update':
				$result = self::apply_elementor_widget( $pending->post_id, $current, false, true );
				break;
			case 'elementor_widget_add':
				// Roll back an add by removing the widget we created.
				$result = self::rollback_widget_add( $pending );
				break;
			case 'elementor_widget_remove':
				// Roll back a remove by re-inserting the stashed node.
				$result = self::rollback_widget_remove( $pending );
				break;
			case 'elementor_container_add':
				$result = self::rollback_container_add( $pending );
				break;
			case 'elementor_section_rebuild':
				$result = self::rollback_section_rebuild( $pending );
				break;
			case 'elementor_accordion_item_add':
				$result = self::rollback_accordion_item_add( $pending );
				break;
			case 'elementor_accordion_item_remove':
				$result = self::rollback_accordion_item_remove( $pending );
				break;
			case 'post_content_update':
				$result = self::apply_post_content( $pending->post_id, $current );
				break;
			case 'publish_draft':
				$result = wp_update_post(
					array(
						'ID'          => $pending->post_id,
						'post_status' => 'draft',
					),
					true
				);
				break;
			case 'cluster_create':
				$result = self::rollback_cluster_create( $pending );
				break;
			case 'cluster_assign':
				$result = self::rollback_cluster_assign( $pending );
				break;
			case 'rewrite_outline':
				// Rolling back an outline approval is a no-op: nothing was
				// written to the post on apply. Flipping status to
				// rolled_back means the outline no longer counts toward the
				// 7-day gate on draft_update_post_content, which is the
				// useful semantic — the operator is saying "actually, the
				// editorial plan was wrong, do not unblock body rewrites."
				$result = true;
				break;
			case 'snapshot_restore':
				// v0.60.1: rolling back an applied revert = restore the
				// pre-restore snapshot that restore_snapshot() captured at
				// apply time (its id was written into current_value then).
				$pre_id = isset( $current['pre_restore_snapshot_id'] ) ? (int) $current['pre_restore_snapshot_id'] : 0;
				if ( $pre_id <= 0 ) {
					return new WP_Error( 'no_pre_restore', 'No pre-restore snapshot id recorded on this revert — restore it manually from the Snapshots screen.' );
				}
				require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
				$result = CC_Assistant_Snapshots::restore_snapshot( $pre_id, true );
				break;
			case 'asset_reference_replace':
			case 'category_update':
			case 'create_redirect':
			case 'delete_redirect':
			case 'elementor_full_import':
			case 'elementor_section_content_replace':
			case 'emergency_service_schema':
			case 'trash_post':
			case 'untrash_redirect':
				$result = CC_Assistant_Recovery::rollback( $pending );
				break;
			default:
				return new WP_Error( 'unknown_type', 'Unknown change type for rollback.' );
		}

		if ( is_wp_error( $result ) || false === $result ) {
			return is_wp_error( $result ) ? $result : new WP_Error( 'rollback_failed', 'Rollback could not be applied.' );
		}

		$completed = $wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array(
				'status'      => 'rolled_back',
				'reviewed_at' => current_time( 'mysql' ),
				'reviewed_by' => $reviewer_id,
			),
			array( 'id' => $pending_id, 'status' => 'rolling_back' )
		);

		if ( 1 !== $completed ) { return new WP_Error( 'rollback_completion_failed', 'Rollback writes completed but recording completion failed.' ); }
		self::run_post_apply_cleanup( true, true );
		self::flush_elementor_css_cache();
		if ( $pending->post_id ) {
			update_post_meta( (int) $pending->post_id, '_cc_assistant_last_internal_apply', current_time( 'mysql', true ) );
			update_post_meta( (int) $pending->post_id, '_cc_assistant_last_internal_hash', CC_Assistant_Integrity::post_hash( (int) $pending->post_id ) );
		}
		return array( 'success' => true, 'snapshot_id' => $snapshot_id );
	}

	/**
	 * Rollback for cluster_create: delete the cluster that apply created.
	 * The new cluster_id was stored back into proposed_value at apply time.
	 */
	/**
	 * Rollback elementor_widget_add: read _applied_widget_id from proposed
	 * (stashed by apply) and remove that node from the tree.
	 */
	private static function rollback_widget_add( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$proposed = json_decode( $pending->proposed_value, true );
		if ( ! is_array( $proposed ) || empty( $proposed['_applied_widget_id'] ) ) {
			return new WP_Error( 'no_applied_id', 'No _applied_widget_id stashed on apply — cannot roll back.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $pending->post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$removed = CC_Assistant_Elementor_Builder::remove_node( $tree, (string) $proposed['_applied_widget_id'] );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $pending->post_id, $tree, true );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		self::flush_elementor_css_cache();
		return true;
	}

	/**
	 * Rollback elementor_widget_remove: re-insert the stashed node at its
	 * original parent and index.
	 */
	private static function rollback_widget_remove( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$proposed = json_decode( $pending->proposed_value, true );
		if ( ! is_array( $proposed ) || empty( $proposed['_removed_node'] ) ) {
			return new WP_Error( 'no_removed_node', 'No _removed_node stashed on apply — cannot roll back.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $pending->post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$node         = $proposed['_removed_node'];
		$parent_id    = isset( $proposed['_removed_parent_id'] ) ? (string) $proposed['_removed_parent_id'] : '';
		$orig_index   = isset( $proposed['_removed_orig_index'] ) ? (int) $proposed['_removed_orig_index'] : 0;
		if ( '' === $parent_id ) {
			array_splice( $tree, $orig_index, 0, array( $node ) );
		} else {
			$located = &CC_Assistant_Elementor_Builder::find_node( $tree, $parent_id );
			if ( null === $located ) {
				return new WP_Error( 'parent_gone', 'Original parent no longer exists — cannot reinsert. Try restoring the snapshot.' );
			}
			$parent = &$located['node'];
			if ( ! isset( $parent['elements'] ) || ! is_array( $parent['elements'] ) ) {
				$parent['elements'] = array();
			}
			array_splice( $parent['elements'], $orig_index, 0, array( $node ) );
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $pending->post_id, $tree, true );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		self::flush_elementor_css_cache();
		return true;
	}

	/**
	 * v0.44: Roll back elementor_section_rebuild — remove the section we added
	 * and re-insert the old one we removed at its original root index, in a
	 * single write. A pre_apply snapshot is also taken as the exact recovery
	 * point, so even a nested/edge case is recoverable from the inbox.
	 */
	private static function rollback_section_rebuild( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$proposed = json_decode( $pending->proposed_value, true );
		if ( ! is_array( $proposed ) ) {
			return new WP_Error( 'invalid_rollback', 'Cannot decode proposed_value for section-rebuild rollback.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $pending->post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		if ( ! empty( $proposed['_applied_container_id'] ) ) {
			CC_Assistant_Elementor_Builder::remove_node( $tree, (string) $proposed['_applied_container_id'] );
		}
		if ( ! empty( $proposed['_removed_node'] ) && is_array( $proposed['_removed_node'] ) ) {
			$idx = isset( $proposed['_removed_root_index'] ) ? (int) $proposed['_removed_root_index'] : -1;
			if ( $idx >= 0 && $idx <= count( $tree ) ) {
				array_splice( $tree, $idx, 0, array( $proposed['_removed_node'] ) );
			} else {
				$tree[] = $proposed['_removed_node'];
			}
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $pending->post_id, $tree, true );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		self::flush_elementor_css_cache();
		return true;
	}

	private static function rollback_container_add( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$proposed = json_decode( $pending->proposed_value, true );
		if ( ! is_array( $proposed ) || empty( $proposed['_applied_container_id'] ) ) {
			return new WP_Error( 'no_applied_id', 'No _applied_container_id stashed on apply — cannot roll back.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $pending->post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$removed = CC_Assistant_Elementor_Builder::remove_node( $tree, (string) $proposed['_applied_container_id'] );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $pending->post_id, $tree, true );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		self::flush_elementor_css_cache();
		return true;
	}

	private static function rollback_accordion_item_add( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$proposed = json_decode( $pending->proposed_value, true );
		if ( ! is_array( $proposed ) || empty( $proposed['_applied_item_id'] ) || empty( $proposed['accordion_widget_id'] ) ) {
			return new WP_Error( 'no_applied_id', 'No _applied_item_id stashed on apply — cannot roll back.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $pending->post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$removed = CC_Assistant_Elementor_Builder::remove_accordion_item( $tree, (string) $proposed['accordion_widget_id'], (string) $proposed['_applied_item_id'] );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $pending->post_id, $tree, true );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		self::flush_elementor_css_cache();
		return true;
	}

	/**
	 * Rollback for elementor_accordion_item_remove: re-insert the stashed item
	 * + inner-container back at its original index. Mirrors
	 * rollback_widget_remove's "stash on apply, splice on rollback" pattern so
	 * the FAQ ends up looking exactly like it did before approval.
	 */
	private static function rollback_accordion_item_remove( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$proposed = json_decode( $pending->proposed_value, true );
		if ( ! is_array( $proposed ) || empty( $proposed['accordion_widget_id'] ) || empty( $proposed['_removed_item'] ) ) {
			return new WP_Error( 'no_stashed_item', 'No _removed_item stashed on apply — cannot roll back.' );
		}
		$tree = CC_Assistant_Elementor_Builder::load_tree( $pending->post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$located = &CC_Assistant_Elementor_Builder::find_node( $tree, (string) $proposed['accordion_widget_id'] );
		if ( null === $located ) {
			return new WP_Error( 'accordion_not_found', 'Accordion no longer exists on the post.' );
		}
		$widget = &$located['node'];
		if ( ! isset( $widget['settings']['items'] ) || ! is_array( $widget['settings']['items'] ) ) {
			$widget['settings']['items'] = array();
		}
		if ( ! isset( $widget['elements'] ) || ! is_array( $widget['elements'] ) ) {
			$widget['elements'] = array();
		}
		// Reconstruct the title + inner container from the stash. _inner_container
		// was tucked onto the item by remove_accordion_item; we peel it back off
		// to land both pieces in their respective arrays.
		$removed_item       = $proposed['_removed_item'];
		$inner_container    = isset( $removed_item['_inner_container'] ) ? $removed_item['_inner_container'] : null;
		unset( $removed_item['_inner_container'] );

		$index = isset( $proposed['_removed_orig_index'] ) ? (int) $proposed['_removed_orig_index'] : -1;
		$item_count = count( $widget['settings']['items'] );
		if ( $index < 0 || $index >= $item_count ) {
			$widget['settings']['items'][] = $removed_item;
			if ( null !== $inner_container ) {
				$widget['elements'][] = $inner_container;
			}
		} elseif ( $index === 0 ) {
			array_unshift( $widget['settings']['items'], $removed_item );
			if ( null !== $inner_container ) {
				array_unshift( $widget['elements'], $inner_container );
			}
		} else {
			array_splice( $widget['settings']['items'], $index, 0, array( $removed_item ) );
			if ( null !== $inner_container ) {
				array_splice( $widget['elements'], $index, 0, array( $inner_container ) );
			}
		}
		$save = CC_Assistant_Elementor_Builder::save_tree( $pending->post_id, $tree, true );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		self::flush_elementor_css_cache();
		return true;
	}

	private static function rollback_cluster_create( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		$proposed   = json_decode( $pending->proposed_value, true );
		$cluster_id = isset( $proposed['_applied_cluster_id'] ) ? (int) $proposed['_applied_cluster_id'] : 0;
		if ( $cluster_id <= 0 ) {
			return new WP_Error( 'no_applied_id', 'Cannot rollback: applied cluster id was not recorded.' );
		}
		CC_Assistant_Topic_Clusters::delete_cluster( $cluster_id );
		return true;
	}

	/**
	 * Rollback for cluster_assign: remove the post from the cluster.
	 */
	private static function rollback_cluster_assign( $pending ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		$proposed   = json_decode( $pending->proposed_value, true );
		$cluster_id = (int) ( $proposed['cluster_id'] ?? 0 );
		$post_id    = (int) ( $proposed['post_id'] ?? 0 );
		if ( $cluster_id <= 0 || $post_id <= 0 ) {
			return new WP_Error( 'invalid_payload', 'Cannot rollback: missing cluster or post id.' );
		}
		CC_Assistant_Topic_Clusters::remove_member( $cluster_id, $post_id );
		return true;
	}

	/**
	 * Create a draft post in WP and queue a corresponding pending entry.
	 * The draft is visible in wp-admin immediately so the user can preview
	 * before approving (which publishes it).
	 */
	public static function create_draft_post( $args ) {
        if ( ! empty( $args['workflow_id'] ) ) {
            require_once __DIR__ . '/class-workflow-verifier.php';
            $workflow = CC_Assistant_Workflow_Verifier::load( (string) $args['workflow_id'], true );
            if ( is_wp_error( $workflow ) ) { return $workflow; }
            if ( ( $args['post_type'] ?? 'page' ) !== 'post' ) { return new WP_Error( 'workflow_post_type', 'A new_blog workflow requires post_type=post.', array( 'status' => 422 ) ); }
        }
		$identity = CC_Assistant_Evidence_Gate::identity_check();
		if ( is_wp_error( $identity ) ) { return $identity; }
		if ( isset( $args['elementor_data'] ) ) {
			require_once __DIR__ . '/class-elementor-builder.php';
			$valid = CC_Assistant_Elementor_Builder::validate_tree( $args['elementor_data'] );
			if ( is_wp_error( $valid ) ) { return $valid; }
			$valid = CC_Assistant_Elementor_Validation::validate_tree( array(), $args['elementor_data'] );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}
		$allowed_post_types = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$post_type          = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'page';

		// v0.51.5 — Theme Builder template CREATION. The v0.49 opt-in
		// (cc_assistant_allow_template_editing) covered reading/editing existing
		// templates but this create path still enforced the raw page/post
		// allowlist, so a 404 template could never be composed via the tool.
		// Template CPTs now ride the same opt-in. Drafts are inert: a template
		// only affects the site once the publish_draft pending is approved AND
		// its display conditions are registered (apply_publish_draft handles
		// the Elementor Pro conditions-cache regen).
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-api.php';
		$is_template = CC_Assistant_REST_API::is_template_post_type( $post_type );
		if ( $is_template ) {
			if ( ! get_option( 'cc_assistant_allow_template_editing', false ) ) {
				return new WP_Error( 'post_type_not_allowed', 'Theme Builder templates require the "Allow editing Theme Builder templates" setting (CC Assistant > Settings).' );
			}
		} elseif ( ! in_array( $post_type, $allowed_post_types, true ) ) {
			return new WP_Error( 'post_type_not_allowed', 'Post type not in allowlist.' );
		}

		// Optional explicit slug. sanitize_title() returns '' for an unset/null
		// input, in which case we omit post_name and let WP derive it from the
		// title (legacy behaviour).
		$author = null;
		if ( in_array( $post_type, array( 'post', 'page' ), true ) || ! empty( $args['author_id'] ) ) {
			require_once __DIR__ . '/class-content-authors.php';
			$author = CC_Assistant_Content_Authors::resolve( (int) ( $args['author_id'] ?? 0 ) );
			if ( is_wp_error( $author ) ) { return $author; }
		}
		$post_args = array(
			'post_title'   => isset( $args['title'] ) ? wp_kses_post( $args['title'] ) : 'Untitled draft',
			'post_content' => isset( $args['content'] ) ? wp_kses_post( $args['content'] ) : '',
			'post_excerpt' => isset( $args['excerpt'] ) ? sanitize_textarea_field( $args['excerpt'] ) : '',
			'post_status'  => 'draft',
			'post_type'    => $post_type,
		);
		if ( $author ) { $post_args['post_author'] = $author['id']; }
		if ( ! empty( $args['slug'] ) ) {
			$post_args['post_name'] = sanitize_title( (string) $args['slug'] );
		}
		$post_id = wp_insert_post( wp_slash( $post_args ), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// If Elementor data was provided, attach it.
		if ( ! empty( $args['elementor_data'] ) && is_array( $args['elementor_data'] ) ) {
			require_once __DIR__ . '/class-elementor-builder.php';
			$saved = CC_Assistant_Elementor_Builder::save_tree( $post_id, $args['elementor_data'] );
			if ( is_wp_error( $saved ) ) { wp_delete_post( $post_id, true ); return $saved; }
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		}

		// Theme Builder templates need their type + display conditions stamped
		// at create time or the Theme Builder screen shows an untyped orphan.
		// Conditions written here are dormant until the template is published:
		// Elementor Pro's conditions cache only picks up publish-status
		// templates, and apply_publish_draft regenerates that cache on approve.
		if ( $is_template && ! empty( $args['template_type'] ) ) {
			$template_type = sanitize_key( (string) $args['template_type'] );
			$conditions    = array();
			if ( ! empty( $args['display_conditions'] ) && is_array( $args['display_conditions'] ) ) {
				foreach ( $args['display_conditions'] as $cond ) {
					$cond = trim( (string) $cond );
					if ( '' !== $cond && preg_match( '/^(include|exclude)\/[a-z0-9_\/-]+$/i', $cond ) ) {
						$conditions[] = $cond;
					}
				}
			}
			self::stamp_template_identity( $post_id, $template_type, $conditions );
			// v0.51.7 — persist the intent. Elementor's save_post hooks re-fire
			// on the later publish transition and RESET the template type to
			// "page" and delete _elementor_conditions (observed in production:
			// a correctly stamped error-404 template arrived published as a
			// typeless "page" with no conditions). apply_publish_draft replays
			// this intent AFTER publish so the plugin's stamp always wins.
			update_post_meta(
				$post_id,
				'_cc_assistant_template_intent',
				array(
					'template_type' => $template_type,
					'conditions'    => $conditions,
				)
			);
		}

		// Categories: set immediately on the draft so the post never lands
		// uncategorized. Two input shapes are merged:
		//   - $args['category_ids']: legacy integer-only param.
		//   - $args['categories']:   names (strings, auto-created) or IDs.
		// Only applied when the post type supports the "category" taxonomy.
		$resolved_cat_ids = array();

		if ( ! empty( $args['category_ids'] ) && is_array( $args['category_ids'] ) ) {
			foreach ( $args['category_ids'] as $cid ) {
				$cid = (int) $cid;
				if ( $cid > 0 && get_term( $cid, 'category' ) ) {
					$resolved_cat_ids[] = $cid;
				}
			}
		}

		if ( ! empty( $args['categories'] ) && is_array( $args['categories'] ) && is_object_in_taxonomy( $post_type, 'category' ) ) {
			foreach ( $args['categories'] as $entry ) {
				if ( is_int( $entry ) || ( is_string( $entry ) && ctype_digit( $entry ) ) ) {
					$cid = (int) $entry;
					if ( $cid > 0 && get_term( $cid, 'category' ) ) {
						$resolved_cat_ids[] = $cid;
					}
					continue;
				}
				$name = trim( (string) $entry );
				if ( '' === $name ) {
					continue;
				}
				$existing = get_term_by( 'name', $name, 'category' );
				if ( $existing && ! is_wp_error( $existing ) ) {
					$resolved_cat_ids[] = (int) $existing->term_id;
					continue;
				}
				$created = wp_insert_term( $name, 'category' );
				if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
					$resolved_cat_ids[] = (int) $created['term_id'];
				}
			}
		}

		if ( ! empty( $resolved_cat_ids ) && is_object_in_taxonomy( $post_type, 'category' ) ) {
			wp_set_post_terms( $post_id, array_values( array_unique( $resolved_cat_ids ) ), 'category', false );
		}

		// Tags: array of strings; wp_set_post_tags auto-creates missing terms.
		if ( ! empty( $args['tags'] ) && is_array( $args['tags'] ) && is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
			$tag_names = array_filter( array_map( 'trim', array_map( 'strval', $args['tags'] ) ) );
			if ( ! empty( $tag_names ) ) {
				wp_set_post_tags( $post_id, $tag_names, false );
			}
		}

		// Featured image: only if the attachment exists. Skip silently otherwise
		// so a stale ID doesn't break the create.
		if ( ! empty( $args['featured_image_id'] ) ) {
			$attachment_id = (int) $args['featured_image_id'];
			if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		// Multilingual: assign language and (optionally) link as translation of
		// an existing source post. Both operations are no-ops on single-language
		// sites. Errors are collected and returned alongside the result so the
		// caller can surface them but the post still exists.
		$multilingual_warnings = array();
		$lang_arg              = isset( $args['lang'] ) ? trim( (string) $args['lang'] ) : '';
		$translation_of        = isset( $args['polylang_translation_of'] ) ? (int) $args['polylang_translation_of'] : 0;

		if ( ( '' !== $lang_arg || $translation_of > 0 ) && class_exists( 'CC_Assistant_Multilingual' ) ) {
			if ( ! CC_Assistant_Multilingual::is_active() ) {
				$multilingual_warnings[] = 'lang/polylang_translation_of provided but no multilingual engine is active; skipping.';
			} else {
				// Resolve the effective lang: explicit `lang` wins; otherwise
				// derive from the source post's "other" language. For a 2-lang
				// site this is unambiguous; for 3+-lang sites caller must pass
				// `lang` explicitly.
				$effective_lang = $lang_arg;
				if ( '' === $effective_lang && $translation_of > 0 ) {
					$source_lang = CC_Assistant_Multilingual::language_of( $translation_of );
					$all_langs   = CC_Assistant_Multilingual::all_languages();
					if ( $source_lang && count( $all_langs ) === 2 ) {
						$other_langs = array_values( array_diff( $all_langs, array( $source_lang ) ) );
						if ( ! empty( $other_langs ) ) {
							$effective_lang = $other_langs[0];
						}
					}
					if ( '' === $effective_lang ) {
						$multilingual_warnings[] = sprintf(
							'polylang_translation_of=%d set but no `lang` provided and site has %d languages — cannot guess. Pass `lang` explicitly.',
							$translation_of,
							count( $all_langs )
						);
					}
				}

				if ( '' !== $effective_lang ) {
					$set = CC_Assistant_Multilingual::set_language( $post_id, $effective_lang );
					if ( is_wp_error( $set ) ) {
						$multilingual_warnings[] = 'set_language: ' . $set->get_error_message();
					}
				}

				if ( $translation_of > 0 && '' !== $effective_lang && empty( $multilingual_warnings ) ) {
					if ( ! get_post( $translation_of ) ) {
						$multilingual_warnings[] = sprintf( 'polylang_translation_of: source post %d does not exist.', $translation_of );
					} else {
						$source_lang = CC_Assistant_Multilingual::language_of( $translation_of );
						if ( ! $source_lang ) {
							$multilingual_warnings[] = sprintf( 'polylang_translation_of: source post %d has no language assigned.', $translation_of );
						} elseif ( $source_lang === $effective_lang ) {
							$multilingual_warnings[] = sprintf(
								'polylang_translation_of: source post %d is already in language "%s"; new post would share that language and cannot be a translation.',
								$translation_of,
								$effective_lang
							);
						} else {
							// Preserve any existing translations of the source so
							// pll_save_post_translations doesn't drop them. If the
							// source is already in a 3-way pair (en/es/fr) and we
							// add an "es" translation, the en and fr links must
							// survive. Existing entry for $effective_lang gets
							// replaced by the new post_id.
							$existing = CC_Assistant_Multilingual::translations_of( $translation_of );
							$pair_map = is_array( $existing ) && ! empty( $existing )
								? $existing
								: array( $source_lang => $translation_of );
							$pair_map[ $effective_lang ] = $post_id;

							$pair_result = CC_Assistant_Multilingual::link_translations( array( $pair_map ) );
							if ( is_wp_error( $pair_result ) ) {
								$multilingual_warnings[] = 'link_translations: ' . $pair_result->get_error_message();
							} elseif ( ! empty( $pair_result['failed'] ) ) {
								$first = isset( $pair_result['results'][0]['errors'] ) ? implode( '; ', (array) $pair_result['results'][0]['errors'] ) : 'unknown error';
								$multilingual_warnings[] = 'link_translations failed: ' . $first;
							}

							// v0.10.31: persist the pair_map as postmeta so we can
							// re-apply it on apply_publish_draft. Polylang's
							// post_translations taxonomy term can drop the pair
							// when the post status transitions from draft to
							// publish (the term gets re-evaluated and the unsaved-
							// publish-yet post sometimes loses the term). Storing
							// the intent here gives apply_publish_draft a place
							// to re-link from. Idempotent: re-linking an already-
							// paired post returns ok with no harm.
							update_post_meta( $post_id, '_cc_assistant_polylang_pair', $pair_map );
						}
					}
				}
			}
		}

		$summary = isset( $args['summary'] ) ? $args['summary'] : sprintf( 'New draft "%s" awaiting publish approval.', get_the_title( $post_id ) );
		$publication_basis = ! empty( $workflow ) ? array(
			'publication_workflow_id' => (string) $args['workflow_id'],
			'publication_workflow_basis' => array_intersect_key( $workflow, array_flip( array( 'source_basis', 'scope_revision', 'context' ) ) ),
		) : null;

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => $post_id,
				'change_type'     => 'publish_draft',
				'change_summary'  => $summary,
				'pre_check_baseline' => $publication_basis,
				'current_value'   => wp_json_encode( array( 'post_status' => 'draft' ) ),
				'proposed_value'  => wp_json_encode( array( 'post_status' => 'publish' ) ),
				'reasoning'       => isset( $args['reasoning'] ) ? $args['reasoning'] : '',
				'lint_report'     => isset( $args['lint_report'] ) ? $args['lint_report'] : null,
				'success_metrics' => isset( $args['success_metrics'] ) ? $args['success_metrics'] : null,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) {
            return new WP_Error( 'draft_review_queue_failed', 'The draft was saved but its publish proposal failed. Inspect this draft before retrying; do not claim a complete review package.', array( 'status' => 500, 'post_id' => $post_id, 'cause' => $pending_id->get_error_code() ) );
        }
        if ( ! empty( $args['workflow_id'] ) && ! CC_Assistant_Workflow_Verifier::bind( (string) $args['workflow_id'], $post_id, $pending_id ) ) {
            return new WP_Error( 'workflow_binding_failed', 'Draft and proposal exist, but workflow binding could not be saved. Inspect these records before retrying.', array( 'status' => 500, 'post_id' => $post_id, 'pending_id' => $pending_id ) );
        }

		$out = array(
			'post_id'     => $post_id,
			'pending_id'  => $pending_id,
			'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
			'preview_url' => get_preview_post_link( $post_id ),
		);
		if ( ! empty( $multilingual_warnings ) ) {
			$out['multilingual_warnings'] = $multilingual_warnings;
		}
		return $out;
	}
}
