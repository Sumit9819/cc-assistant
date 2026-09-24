<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-integrity.php';
require_once __DIR__ . '/class-evidence-gate.php';
require_once __DIR__ . '/class-elementor-validation.php';


class CC_Assistant_Pending_Changes {

	/**
	 * Claim-removal warnings produced by queue() calls in this request,
	 * keyed by the new pending id. queue() returns a bare int (every caller
	 * depends on that), so the REST layer reads the warning payload back via
	 * claim_removal_warnings( $pending_id ) and attaches it to its response.
	 */
	private static $claim_removal_warnings = array();

	/**
	 * Warnings the claim-removal guard attached to a pending id queued in
	 * this request. Empty array when the guard found nothing (or did not run).
	 */
	public static function claim_removal_warnings( $pending_id ) {
		$pending_id = (int) $pending_id;
		return isset( self::$claim_removal_warnings[ $pending_id ] )
			? self::$claim_removal_warnings[ $pending_id ]
			: array();
	}

	/**
	 * Pendings this request auto-superseded, keyed by the NEW pending id.
	 *
	 * Superseding is deliberate — v2 of an intent should hide v1 — but it used
	 * to happen in total silence. `post_content_update` supersedes on post_id
	 * ALONE (supersede_subkey() returns '' for it), so ANY second content
	 * pending on a post hides the first, and the hidden row keeps
	 * status='pending' while the inbox filters on superseded_by IS NULL. The
	 * reviewer therefore never sees it and cannot approve it.
	 *
	 * Real incident, sids-ponds post 75 on 2026-08-26: #382 added a title to
	 * the About Us map iframe, #383 changed a testimonial colour. Two
	 * INDEPENDENT module edits, not two drafts of one intent. Queueing #383
	 * silently buried #382; the operator approved everything they could see and
	 * the accessibility fix was simply gone, with no error and no cc_edits row.
	 *
	 * queue() returns a bare int and every caller depends on that, so the REST
	 * layer reads this back the same way it reads claim_removal_warnings().
	 */
	private static $superseded_notices = array();

	/**
	 * Pendings that queueing $pending_id auto-superseded in this request.
	 * Empty when nothing was hidden.
	 */
	public static function superseded_notices( $pending_id ) {
		$pending_id = (int) $pending_id;
		return isset( self::$superseded_notices[ $pending_id ] )
			? self::$superseded_notices[ $pending_id ]
			: array();
	}

	public static function queue( $args ) {
		global $wpdb;
		$defaults = array(
			'post_id'            => null,
			'change_type'        => 'unknown',
			'change_summary'     => '',
			'current_value'      => '',
			'proposed_value'     => '',
			'reasoning'          => '',
			'lint_report'        => null,
			'success_metrics'    => null,
			'pre_check_baseline' => null,
			'status'             => 'pending',
			'created_by'         => 'claude',
		);
		$args = wp_parse_args( $args, $defaults );
		$evidence = CC_Assistant_Evidence_Gate::validate_queue( $args );
		if ( is_wp_error( $evidence ) ) { return $evidence; }
		$valid = CC_Assistant_Elementor_Validation::validate_payload( $args );
		if ( is_wp_error( $valid ) ) { return $valid; }

		// v0.68 (Render Health Guard): capture a rendered-page baseline for
		// every post-scoped change, piggybacked on the pre_check_baseline
		// column the cosine verifier already owns. Done HERE, in the one
		// place all change types funnel through, so no handler can forget it.
		// The verifier tolerates the extra key (it only reads post_ids), and
		// capture() is transient-cached so a batch of changes against the
		// same post costs one loopback fetch. Failure never blocks queueing.
		if ( ! empty( $args['post_id'] ) ) {
			try {
				require_once CC_ASSISTANT_DIR . 'includes/class-render-health.php';
				$render = CC_Assistant_Render_Health::capture( (int) $args['post_id'] );
				if ( null !== $render ) {
					$baseline = $args['pre_check_baseline'];
					if ( is_string( $baseline ) && '' !== $baseline ) {
						$decoded  = json_decode( $baseline, true );
						$baseline = is_array( $decoded ) ? $decoded : null;
					}
					if ( null === $baseline ) {
						$baseline = array();
					}
					// Never clobber the cosine baseline; only add alongside it.
					if ( is_array( $baseline ) ) {
						$baseline['render']          = $render;
						$args['pre_check_baseline'] = $baseline;
					}
				}
			} catch ( \Throwable $e ) {
				// Baseline capture is an enhancement, never a queue blocker.
			}
		}

		$baseline = $args['pre_check_baseline'];
		$baseline = is_string( $baseline ) ? json_decode( $baseline, true ) : $baseline;
		$baseline = is_array( $baseline ) ? $baseline : array();
		unset( $baseline['evidence'] ); // Never accept a client-supplied proof.
		if ( $evidence ) { $baseline['evidence'] = $evidence; }
		$baseline['queued_at_gmt'] = current_time( 'mysql', true );
		if ( ! empty( $args['post_id'] ) ) {
			$baseline['post_hash'] = CC_Assistant_Integrity::post_hash( (int) $args['post_id'] );
		}
		$args['pre_check_baseline'] = $baseline;
		$row = array(
			'post_id'        => $args['post_id'],
			'change_type'    => $args['change_type'],
			'change_summary' => $args['change_summary'],
			'current_value'  => $args['current_value'],
			'proposed_value' => $args['proposed_value'],
			'reasoning'      => $args['reasoning'],
			'status'         => $args['status'],
			'created_at'     => current_time( 'mysql' ),
			'created_by'     => $args['created_by'],
		);
		if ( null !== $args['lint_report'] ) {
			$row['lint_report'] = is_string( $args['lint_report'] )
				? $args['lint_report']
				: wp_json_encode( $args['lint_report'] );
		}
		if ( null !== $args['success_metrics'] ) {
			$row['success_metrics'] = is_string( $args['success_metrics'] )
				? $args['success_metrics']
				: wp_json_encode( $args['success_metrics'] );
		}
		if ( null !== $args['pre_check_baseline'] ) {
			$row['pre_check_baseline'] = is_string( $args['pre_check_baseline'] )
				? $args['pre_check_baseline']
				: wp_json_encode( $args['pre_check_baseline'] );
		}

		$inserted = $wpdb->insert( $wpdb->prefix . 'cc_pending_changes', $row );
		if ( false === $inserted || (int) $wpdb->insert_id <= 0 ) { return new WP_Error( 'queue_write_failed', 'The proposal could not be stored. No change was queued.', array( 'status' => 500 ) ); }
		$new_id = (int) $wpdb->insert_id;

		// Auto-supersede older same-target pending rows. Resolves the duplicate
		// inbox row pattern where v1 (e.g. failed lint) and v2 (clean) of the
		// same intent both stay active, forcing the reviewer to manually reject
		// the older one. Only supersede-able change_types participate; redirects,
		// outlines, and post creates are not subject to dedup-by-target because
		// their natural key isn't (post_id, change_type) alone.
		if ( $new_id > 0 ) {
			self::mark_older_as_superseded( $new_id, $args );

			// Claim-removal guard (warn-only, never blocks the queue). Real
			// incident: pending #675 deliberately added an MRI claim after
			// operator confirmation, yet later sessions queued — and shipped —
			// changes deleting that claim with no warning. If this change
			// removes text that an earlier APPROVED pending on the same post
			// deliberately added/confirmed/restored, attach a warning the
			// draft_* endpoints surface in their response. Wrapped so a guard
			// failure can never break queueing.
			try {
				$claim_warnings = self::detect_claim_removal( $new_id, $args );
				if ( ! empty( $claim_warnings ) ) {
					self::$claim_removal_warnings[ $new_id ] = $claim_warnings;
				}
			} catch ( \Throwable $e ) {
				// Guard is best-effort; swallow and continue.
			}
		}

		// Bust admin-side caches so the inbox UI does not show a stale list on
		// the next page load. The dashboard summary card, the weekly advisor,
		// and the inbox integrity banners all key off pending counts. Some
		// hosts (Kinsta, page-cache plugins) ignore wp_cache_delete on the
		// non-persistent backend, so we also clear the transient flavor as
		// a belt-and-suspenders.
		delete_transient( 'cc_assistant_dashboard_summary' );
		require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
		CC_Assistant_Weekly_Advisor::invalidate();
		delete_transient( 'cc_inbox_integrity_banners' );
		delete_transient( 'cc_pending_count' );
		wp_cache_delete( 'cc_pending_count', 'cc-assistant' );

		return $new_id;
	}

	/**
	 * Mark older same-target pending rows as superseded by $new_id.
	 *
	 * "Same target" for most change_types means same (post_id, change_type).
	 * For change_types where multiple distinct objects share that key (e.g.
	 * elementor_widget_update where each widget_id is independent, or
	 * postmeta_update where each meta_key is independent), we additionally
	 * match on a sub-key parsed from proposed_value. See supersede_subkey().
	 *
	 * Returns the count of rows marked superseded. Zero is normal — most
	 * queues are first-time, not v2 of an existing intent.
	 */
	public static function mark_older_as_superseded( $new_id, $args ) {
		$change_type = isset( $args['change_type'] ) ? (string) $args['change_type'] : '';
		$post_id     = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( '' === $change_type || $post_id <= 0 ) {
			return 0;
		}
		if ( ! self::change_type_supports_supersede( $change_type ) ) {
			return 0;
		}

		$new_subkey = self::supersede_subkey( $change_type, isset( $args['proposed_value'] ) ? (string) $args['proposed_value'] : '' );

		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';

		// Pull all candidate older pendings with the same (post_id, change_type).
		// We then filter by sub-key in PHP because the sub-key is JSON-encoded
		// inside proposed_value and a SQL LIKE would be both slower and noisier.
		$candidates = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, proposed_value
			 FROM {$table}
			 WHERE id <> %d
			   AND post_id = %d
			   AND change_type = %s
			   AND status = 'pending'
			   AND superseded_by IS NULL",
			$new_id,
			$post_id,
			$change_type
		) );
		if ( empty( $candidates ) ) {
			return 0;
		}

		$ids_to_mark = array();
		foreach ( $candidates as $c ) {
			$cand_subkey = self::supersede_subkey( $change_type, (string) $c->proposed_value );
			if ( $cand_subkey === $new_subkey ) {
				$ids_to_mark[] = (int) $c->id;
			}
		}
		if ( empty( $ids_to_mark ) ) {
			return 0;
		}

		// Single UPDATE for all matched candidates. We deliberately do not
		// change status — the row stays 'pending' so existing per-status
		// counts (counts_by_status) still see it; the superseded_by IS NULL
		// filter on the inbox query is what hides it from active review.
		$placeholders = implode( ',', array_fill( 0, count( $ids_to_mark ), '%d' ) );
		$sql_args     = array_merge( array( (int) $new_id ), $ids_to_mark );
		$updated      = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET superseded_by = %d WHERE id IN ({$placeholders}) AND superseded_by IS NULL",
			$sql_args
		) );

		// Record WHAT was hidden so the caller can be told. Without this the
		// only trace is a superseded_by column nobody reads until work goes
		// missing. Summaries are pulled so the notice names the change in the
		// reviewer's own words rather than a bare id.
		if ( $updated > 0 ) {
			$sum_ph  = implode( ',', array_fill( 0, count( $ids_to_mark ), '%d' ) );
			$rows    = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, change_summary FROM {$table} WHERE id IN ({$sum_ph})",
				$ids_to_mark
			) );
			$notices = array();
			foreach ( (array) $rows as $r ) {
				$notices[] = array(
					'pending_id' => (int) $r->id,
					'summary'    => (string) $r->change_summary,
					'message'    => sprintf(
						'Queueing this change HID pending #%d ("%s"). It is no longer visible in the inbox and can never be approved. '
						. 'If that was v1 of this same intent, nothing is lost. If it was a SEPARATE change, it is gone: apply this one, '
						. 're-read the post, then queue the other again. post_content_update supersedes on post_id alone, so any two '
						. 'content changes on one post collide even when they touch different modules.',
						(int) $r->id,
						(string) $r->change_summary
					),
				);
			}
			if ( ! empty( $notices ) ) {
				self::$superseded_notices[ (int) $new_id ] = $notices;
			}
		}

		return (int) $updated;
	}

	/**
	 * Whitelist of change_types that participate in auto-supersede. Anything
	 * not on this list goes through the queue untouched. Keeping this opt-in
	 * avoids surprising the operator on change_types where two pendings on
	 * the same post are deliberately distinct objects (e.g. two redirects
	 * with different sources, or two widget edits on different widgets).
	 */
	public static function change_type_supports_supersede( $change_type ) {
		static $list = array(
			'post_content_update',
			'meta_update',
			'postmeta_update',
			'elementor_widget_update',
			'elementor_widget_remove',
			'emergency_service_schema',
			'propose_schema',
			'cluster_assign',
			'category_update',
			'term_update',
			'rank_math_schema_update',
		);
		return in_array( (string) $change_type, $list, true );
	}

	/**
	 * Compute a sub-key for supersede matching. For change_types whose natural
	 * key is (post_id, change_type) alone, returns ''. For change_types where
	 * each instance targets a distinct object (widget, meta key, post field),
	 * returns a string parsed from proposed_value. Non-array proposed_values
	 * fall through to '' which is fine: malformed JSON is the queue's problem.
	 */
	public static function supersede_subkey( $change_type, $proposed_value ) {
		// post_content_update / propose_schema / emergency_service_schema /
		// cluster_assign — only one per post is meaningful, so post_id alone
		// is enough; no sub-key needed.
		if ( in_array( $change_type, array( 'post_content_update', 'propose_schema', 'emergency_service_schema', 'cluster_assign' ), true ) ) {
			return '';
		}
		$decoded = json_decode( (string) $proposed_value, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		switch ( $change_type ) {
			case 'meta_update':
				// Core post field: post_title / post_name / post_excerpt / post_status.
				return 'field:' . ( isset( $decoded['field'] ) ? (string) $decoded['field'] : '' );
			case 'postmeta_update':
				// proposed_value for postmeta_update is built by draft_update_postmeta /
				// draft_update_seo_meta as {key: <meta_key>, value: <new_value>}. The
				// JSON field is 'key', NOT 'meta_key' — accept both for safety, but
				// prefer 'key' since that is what queue endpoints actually write.
				// Earlier versions looked only for 'meta_key' which never exists,
				// returning the same empty subkey for every postmeta update and
				// collapsing distinct title/description/focus_keyword pendings into
				// mutual supersede chains (the newest postmeta on a post would hide
				// every other postmeta pending on the same post regardless of which
				// meta field it targeted).
				$mk = '';
				if ( isset( $decoded['key'] ) ) {
					$mk = (string) $decoded['key'];
				} elseif ( isset( $decoded['meta_key'] ) ) {
					$mk = (string) $decoded['meta_key'];
				}
				return 'meta_key:' . $mk;
			case 'elementor_widget_update':
				return 'widget_id:' . ( isset( $decoded['widget_id'] ) ? (string) $decoded['widget_id'] : '' );
			case 'elementor_widget_remove':
				// Two removes targeting the same widget_id are duplicates;
				// the second supersedes the first. Add and container_add are
				// deliberately NOT in the supersede whitelist because two adds
				// into the same parent are usually distinct widgets, not v2 of
				// the same intent.
				return 'widget_id:' . ( isset( $decoded['widget_id'] ) ? (string) $decoded['widget_id'] : '' );
			case 'term_update':
				// All term updates share post_id=0, so the term is the natural
				// supersede key: a v2 description for the same category replaces
				// the queued v1, while updates to different categories coexist.
				return 'term:' . ( isset( $decoded['term_id'] ) ? (string) (int) $decoded['term_id'] : '' );
			case 'rank_math_schema_update':
				// The subkey is the schema ROW, not the field, on purpose. Each
				// row's payload carries the whole merged array, so two pendings
				// built off the same base would have the second silently undo
				// the first's field. One live pending per row; to change several
				// fields, pass them together in one set{}.
				return 'schema:' . ( isset( $decoded['meta_key'] ) ? (string) $decoded['meta_key'] : '' );
		}
		return '';
	}

	public static function list_pending( $status = 'pending', $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE status = %s AND superseded_by IS NULL ORDER BY created_at DESC LIMIT %d",
				$status,
				$limit
			)
		);
	}

	public static function count_pending() {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending' AND superseded_by IS NULL" );
	}

	public static function counts_by_status() {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		// Filter superseded out of the per-status counts so the dashboard
		// summary matches what the operator sees in the inbox tabs. A row
		// only ever gets superseded_by set while it is still 'pending', so
		// this filter primarily moves rows out of the 'pending' bucket.
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM $table WHERE superseded_by IS NULL GROUP BY status" );
		$out   = array(
			'pending'     => 0,
			'approved'    => 0,
			'rejected'    => 0,
			'rolled_back' => 0,
			'applying' => 0, 'apply_failed' => 0, 'rolling_back' => 0, 'rollback_failed' => 0,
		);
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->c;
		}
		return $out;
	}

	/**
	 * Single-shot fetch of all changes for the inbox UI. Returns rows keyed
	 * by status with referenced post IDs and reviewer user IDs already cached
	 * (one query for posts, one for users), eliminating the N+1 pattern when
	 * each row would otherwise call get_the_title / get_userdata individually.
	 */
	public static function fetch_all_for_inbox( $per_status_limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';

		$by_status = array(
			'pending'     => array(),
			'approved'    => array(),
			'rejected'    => array(),
			'rolled_back' => array(),
			'applying' => array(), 'apply_failed' => array(), 'rolling_back' => array(), 'rollback_failed' => array(),
		);

		// One query per status with LIMIT, all UNION'd. MySQL handles UNION efficiently.
		// superseded_by IS NULL hides rows that have been replaced by a newer
		// pending change targeting the same (post_id, change_type[, sub-key]).
		// Without this filter the inbox shows v1 + v2 of the same intent and
		// the operator has to manually triage which to reject. See
		// CC_Assistant_Pending_Changes::mark_older_as_superseded().
		$query = '';
		foreach ( array_keys( $by_status ) as $status ) {
			$piece = $wpdb->prepare(
				"(SELECT * FROM $table WHERE status = %s AND superseded_by IS NULL ORDER BY COALESCE(reviewed_at, created_at) DESC LIMIT %d)",
				$status,
				$per_status_limit
			);
			$query .= ( $query ? ' UNION ALL ' : '' ) . $piece;
		}
		$rows = $wpdb->get_results( $query );

		$post_ids     = array();
		$reviewer_ids = array();
		foreach ( $rows as $r ) {
			if ( isset( $by_status[ $r->status ] ) ) {
				$by_status[ $r->status ][] = $r;
			}
			if ( ! empty( $r->post_id ) ) {
				$post_ids[ (int) $r->post_id ] = true;
			}
			if ( ! empty( $r->reviewed_by ) ) {
				$reviewer_ids[ (int) $r->reviewed_by ] = true;
			}
		}

		// Prime the post cache so subsequent get_the_title / get_permalink / get_edit_post_link
		// calls do not each hit the DB.
		if ( ! empty( $post_ids ) ) {
			_prime_post_caches( array_keys( $post_ids ), false, false );
		}

		$reviewers = array();
		if ( ! empty( $reviewer_ids ) ) {
			$user_query = new WP_User_Query(
				array(
					'include' => array_keys( $reviewer_ids ),
					'fields'  => array( 'ID', 'user_login', 'display_name' ),
				)
			);
			foreach ( $user_query->get_results() as $u ) {
				$reviewers[ (int) $u->ID ] = $u->user_login;
			}
		}

		return array(
			'by_status' => $by_status,
			'reviewers' => $reviewers,
		);
	}

	public static function get( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	public static function approve( $id, $reviewer_id ) {
		global $wpdb;
		return $wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array(
				'status'      => 'approved',
				'reviewed_at' => current_time( 'mysql' ),
				'reviewed_by' => $reviewer_id,
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Atomic compare-and-swap: flip status from 'pending' to 'approved' in a
	 * single UPDATE that only affects the row when status is still 'pending'.
	 * Used by apply_pending so two reviewers clicking Approve simultaneously
	 * cannot both proceed past the status check — only the writer that lands
	 * the row first wins and proceeds with apply + record_edit. The loser
	 * sees affected_rows = 0 and returns false so the caller surfaces a
	 * "race lost" error instead of double-applying and double-recording the
	 * outcome (which would silently double-count GSC scoring later).
	 *
	 * Returns true when the lock was claimed, false when another worker won.
	 */
	public static function claim_for_apply( $id, $reviewer_id ) {
		global $wpdb;
		// Refuse to apply a row that has been superseded by a newer pending.
		// The superseded_by guard prevents the v1-after-v2-already-applied
		// race where a reviewer eyeballs an old row and clicks Approve while
		// v2 is mid-apply, which would leave the post in v1's state instead
		// of v2's (FIFO apply means whichever lands second wins). With this
		// filter, v1 is unclaimable so v2 always wins.
		$rows = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}cc_pending_changes
			 SET status = 'applying', reviewed_at = %s, reviewed_by = %d
			 WHERE id = %d AND status = 'pending' AND superseded_by IS NULL",
			current_time( 'mysql' ),
			(int) $reviewer_id,
			(int) $id
		) );
		return ( $rows >= 1 );
	}

	public static function reject( $id, $reviewer_id, $note = '' ) {
		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array(
				'status'      => 'rejected',
				'reviewed_at' => current_time( 'mysql' ),
				'reviewed_by' => $reviewer_id,
				'review_note' => $note,
			),
			array( 'id' => $id, 'status' => 'pending' )
		);
		// Activity log: record the reject so the next session can read the
		// reviewer's verdict pattern without having to query rejected rows
		// separately. The recap loop already surfaces last 3 rejected; the
		// activity log surfaces the full timeline within the 48h window.
		if ( $result && class_exists( 'CC_Assistant_Activity_Log' ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT post_id, change_type, change_summary FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d",
					(int) $id
				)
			);
			if ( $row ) {
				$reason = trim( (string) $note );
				$msg = sprintf( '%s rejected%s', $row->change_type, '' !== $reason ? ': ' . mb_substr( $reason, 0, 200 ) : '' );
				CC_Assistant_Activity_Log::record(
					CC_Assistant_Activity_Log::TYPE_EDIT_REJECTED,
					$msg,
					$row->post_id ? (int) $row->post_id : null,
					(int) $id,
					'human'
				);
			}
		}
		return $result;
	}

	/**
	 * Count pending rows queued in the last $minutes against a given post.
	 * Used at queue time to flag the "model dumped 14 changes on one post"
	 * pattern. Filtered to created_by='claude' so manual admin batches don't
	 * trip the warning.
	 *
	 * NOTE: cc_pending_changes.created_at is written via current_time('mysql')
	 * — site-local time, NOT GMT. The cutoff therefore must also be in
	 * site-local; using gmdate(time() - $delta) would shift the comparison
	 * by the site's UTC offset and silently break the gate on non-UTC sites.
	 */
	public static function recent_for_post( $post_id, $minutes = 60 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_pending_changes';
		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $minutes * 60 ) );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			 WHERE post_id = %d
			   AND created_by = 'claude'
			   AND status = 'pending'
			   AND superseded_by IS NULL
			   AND created_at >= %s",
			(int) $post_id,
			$cutoff
		) );
	}

	/**
	 * Look for an existing pending row with the same post_id + change_type
	 * whose proposed_value SHA-1 hash matches the proposed payload. Cheap
	 * exact-dup detector — catches the case where the model retries an
	 * identical queue call seconds apart. We do NOT do fuzzy match here
	 * because false positives are worse than letting two near-identical
	 * proposals coexist (the human reviewer can spot those).
	 */
	public static function find_similar_recent( $post_id, $change_type, $proposed_value, $minutes = 60 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_pending_changes';
		// Site-local cutoff to match the convention of cc_pending_changes.created_at;
		// see the note on recent_for_post() for the rationale.
		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $minutes * 60 ) );
		$hash   = sha1( (string) $proposed_value );
		// Use plain `=` rather than the null-safe `<=>` operator. `<=>` was
		// added in MySQL 8.0 and silently returns NULL on 5.7 — many shared
		// hosts still ship 5.7 and would have duplicate detection break
		// without any error. find_similar_recent is only called from queue
		// handlers where post_id is always a valid int, so plain `=` is safe.
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, change_summary, created_at, proposed_value
			 FROM {$table}
			 WHERE post_id = %d
			   AND change_type = %s
			   AND status = 'pending'
			   AND superseded_by IS NULL
			   AND created_at >= %s",
			$post_id,
			$change_type,
			$cutoff
		) );
		$matches = array();
		foreach ( (array) $rows as $r ) {
			if ( sha1( (string) $r->proposed_value ) === $hash ) {
				$matches[] = array(
					'id'             => (int) $r->id,
					'change_summary' => $r->change_summary,
					'created_at'     => $r->created_at,
				);
			}
		}
		return $matches;
	}

	/**
	 * Retroactive style-guide scan over the existing pending queue. Reads
	 * each pending row's proposed_value, runs lint_html_block over the
	 * text-bearing fields, and returns rows that fail em_dashes / ai_tells
	 * / style_guide. Used by the inbox banner to flag rows queued before
	 * the queue-time validator was added (Fix #5 in 0.2.1).
	 */
	public static function scan_pending_for_lint_violations( $limit = 200 ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, post_id, change_type, change_summary, proposed_value
			 FROM {$table}
			 WHERE status = 'pending' AND superseded_by IS NULL
			 ORDER BY id DESC
			 LIMIT %d",
			(int) $limit
		) );
		$violations = array();
		foreach ( (array) $rows as $r ) {
			$proposed = json_decode( (string) $r->proposed_value, true );
			$text = '';
			if ( is_array( $proposed ) ) {
				if ( isset( $proposed['settings'] ) && is_array( $proposed['settings'] ) ) {
					foreach ( array( 'editor', 'text', 'title', 'description_text_a', 'description_text_b' ) as $f ) {
						if ( isset( $proposed['settings'][ $f ] ) && is_string( $proposed['settings'][ $f ] ) ) {
							$text .= "\n" . $proposed['settings'][ $f ];
						}
					}
				}
				if ( isset( $proposed['content'] ) && is_string( $proposed['content'] ) ) {
					$text .= "\n" . $proposed['content'];
				}
				if ( isset( $proposed['value'] ) && is_string( $proposed['value'] ) ) {
					$text .= "\n" . $proposed['value'];
				}
			}
			if ( '' === trim( $text ) ) {
				continue;
			}
			$lint = CC_Assistant_Pre_Publish::lint_html_block( $text );
			$hard = array();
			foreach ( array( 'em_dashes', 'ai_tells', 'style_guide', 'wall_of_text', 'address_consistency', 'hospital_comparison' ) as $name ) {
				if ( isset( $lint[ $name ] ) && empty( $lint[ $name ]['pass'] ) ) {
					$hard[] = $name;
				}
			}
			if ( ! empty( $hard ) ) {
				$violations[] = array(
					'id'              => (int) $r->id,
					'post_id'         => (int) $r->post_id,
					'change_type'     => $r->change_type,
					'change_summary'  => $r->change_summary,
					'hard_violations' => $hard,
				);
			}
		}
		return $violations;
	}

	/**
	 * Find pairs of pending rows that target the same post_id + widget_id.
	 * Used by the inbox to surface "approve in queued order" warnings for
	 * the conflicts that existed before the conflict-detection-on-queue
	 * fix landed.
	 */
	public static function find_pending_widget_conflicts() {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		$rows = $wpdb->get_results(
			"SELECT id, post_id, change_summary, proposed_value, created_at
			 FROM {$table}
			 WHERE status = 'pending' AND superseded_by IS NULL AND change_type = 'elementor_widget_update'
			 ORDER BY id ASC"
		);
		// Group by post_id + widget_id (parsed from proposed_value JSON).
		$groups = array();
		foreach ( (array) $rows as $r ) {
			$decoded = json_decode( (string) $r->proposed_value, true );
			$wid     = is_array( $decoded ) && isset( $decoded['widget_id'] ) ? (string) $decoded['widget_id'] : '';
			if ( '' === $wid ) {
				continue;
			}
			$key = (int) $r->post_id . '|' . $wid;
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'post_id'   => (int) $r->post_id,
					'widget_id' => $wid,
					'pending'   => array(),
				);
			}
			$groups[ $key ]['pending'][] = array(
				'id'             => (int) $r->id,
				'change_summary' => $r->change_summary,
				'created_at'     => $r->created_at,
			);
		}
		// Only return groups with 2+ pending rows — those are real conflicts.
		$out = array();
		foreach ( $groups as $g ) {
			if ( count( $g['pending'] ) >= 2 ) {
				$out[] = $g;
			}
		}
		return $out;
	}

	/**
	 * Change types the claim-removal guard inspects. Only content-modifying
	 * types participate — redirects, trashes, category updates etc. do not
	 * "remove text" in the sense the guard cares about.
	 */
	private static function claim_guard_change_types() {
		return array(
			'elementor_widget_update',
			'post_content_update',
			'postmeta_update',
			'elementor_full_import',
		);
	}

	/**
	 * Claim-removal detector. Diffs the old payload (what the change will
	 * replace) against the new payload, reduces the removed text to
	 * significant tokens, and checks whether any of this post's APPROVED
	 * pending rows deliberately added/confirmed/restored that text (summary
	 * matches confirm/restore/revert/"add back" AND shares a removed token).
	 *
	 * Returns an array of warning entries (possibly empty). Warn-only: the
	 * caller never blocks the queue on this.
	 */
	private static function detect_claim_removal( $new_id, $args ) {
		$change_type = isset( $args['change_type'] ) ? (string) $args['change_type'] : '';
		if ( ! in_array( $change_type, self::claim_guard_change_types(), true ) ) {
			return array();
		}
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id <= 0 ) {
			return array();
		}

		$new_text = self::payload_text( isset( $args['proposed_value'] ) ? (string) $args['proposed_value'] : '' );
		$old_text = self::payload_text( isset( $args['current_value'] ) ? (string) $args['current_value'] : '' );
		if ( '' === trim( $old_text ) ) {
			// Old value not stored on the row (e.g. elementor_full_import only
			// stores length metadata) — fetch the live value the apply path
			// would overwrite.
			$old_text = self::fetch_live_old_text( $post_id, $change_type, isset( $args['proposed_value'] ) ? (string) $args['proposed_value'] : '' );
		}
		if ( '' === trim( $old_text ) ) {
			return array();
		}

		$old_tokens = self::significant_tokens( $old_text );
		$new_tokens = self::significant_tokens( $new_text );
		$removed    = array_values( array_diff( $old_tokens, $new_tokens ) );
		if ( empty( $removed ) ) {
			return array();
		}
		$removed = array_slice( $removed, 0, 200 );

		// What counts as EVIDENCE that this change undoes a sibling change.
		//
		// This used to probe every removed token of length >= 4 and fire if any
		// ONE of them appeared anywhere in a sibling's summary or payload. One
		// ordinary word in common is not evidence. Real false positives, 2026-08:
		// a homepage title swapping "Garden Supply" for "Garden Store" removed the
		// lone token SUPPLY and matched an unrelated product-carousel revert; on
		// mammothmachinery the tokens BEST and MODELS fired the same way and were
		// hand-dismissed, which is precisely how a guard teaches people to ignore
		// it.
		//
		// Evidence now means either:
		//   1. a removed PHRASE — 2+ significant tokens that sat ADJACENT in the
		//      old text and are all absent from the new one. That is the guard's
		//      literal claim: "text you deliberately added has been removed".
		//   2. a DISTINCTIVE single token — an ALL-CAPS acronym (MRI, EKG, DKA:
		//      the capability claims this guard was built for, cf. page 852),
		//      anything containing a digit (prices, 24/7, model numbers), or a
		//      word of 8+ chars, which is rarely incidental.
		//
		// Both paths stay advisory; the cost of a miss is a warning that does not
		// appear, the cost of the old behaviour was every warning being noise.
		$acronyms = array();
		if ( preg_match_all( '/\b[A-Z]{3}\b/', (string) $old_text, $acr_m ) ) {
			$acronyms = array_fill_keys( $acr_m[0], true );
		}
		$removed_set = array_fill_keys( $removed, true );

		// Ordered, NOT de-duplicated token stream of the old text so adjacency
		// survives — significant_tokens() returns a set, which loses it. No
		// stopword filtering is needed here: stopwords were already excluded from
		// both token sets, so they are never in $removed_set and simply break a
		// run, which is the conservative direction.
		$phrases = array();
		$run     = array();
		if ( preg_match_all( '/[A-Z0-9]{3,}/', strtoupper( (string) $old_text ), $ord_m ) ) {
			foreach ( $ord_m[0] as $tok ) {
				if ( isset( $removed_set[ $tok ] ) ) {
					$run[] = $tok;
					continue;
				}
				if ( count( $run ) >= 2 ) {
					$phrases[] = $run;
				}
				$run = array();
			}
		}
		if ( count( $run ) >= 2 ) {
			$phrases[] = $run;
		}

		$probe = array();
		foreach ( $phrases as $ph ) {
			$probe[] = $ph;
		}
		foreach ( $removed as $tk ) {
			if ( isset( $acronyms[ $tk ] ) || preg_match( '/[0-9]/', $tk ) || strlen( $tk ) >= 8 ) {
				$probe[] = array( $tk );
			}
		}
		if ( empty( $probe ) ) {
			return array();
		}
		$probe = array_slice( $probe, 0, 200 );

		global $wpdb;
		$table    = $wpdb->prefix . 'cc_pending_changes';
		$siblings = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, change_summary, proposed_value
			 FROM {$table}
			 WHERE post_id = %d
			   AND id <> %d
			   AND status = 'approved'
			 ORDER BY id DESC
			 LIMIT 50",
			$post_id,
			(int) $new_id
		) );
		if ( empty( $siblings ) ) {
			return array();
		}

		$warnings = array();
		foreach ( (array) $siblings as $s ) {
			$summary = (string) $s->change_summary;
			// Deliberate-addition markers: "operator confirmed", "confirm",
			// "restore", "revert", "add back" / "added back".
			if ( ! preg_match( '/(operator\s+)?confirm|restore|revert|add(ed)?\s+back/i', $summary ) ) {
				continue;
			}
			$haystack = strtoupper( $summary . ' ' . (string) $s->proposed_value );
			$matched  = array();
			foreach ( $probe as $ph ) {
				// A phrase must appear with its words still ADJACENT (punctuation
				// or whitespace between them is fine), not merely scattered
				// somewhere in the sibling payload.
				$parts = array();
				foreach ( $ph as $w ) {
					$parts[] = preg_quote( $w, '/' );
				}
				$rx = '/(?<![A-Z0-9])' . implode( '[^A-Z0-9]{1,4}', $parts ) . '(?![A-Z0-9])/';
				if ( preg_match( $rx, $haystack ) ) {
					$matched[] = implode( ' ', $ph );
				}
			}
			if ( empty( $matched ) ) {
				continue;
			}
			$warnings[] = array(
				'pending_id'     => (int) $s->id,
				'summary'        => $summary,
				'matched_tokens' => array_slice( $matched, 0, 20 ),
				'message'        => sprintf(
					'This change removes text that pending #%d deliberately added/confirmed ("%s"). Verify with the operator before approving.',
					(int) $s->id,
					$summary
				),
			);
		}
		return $warnings;
	}

	/**
	 * Flatten a stored pending payload (JSON string, usually) to plain text.
	 * Collects every string leaf of the decoded structure (values only, not
	 * keys) and strips tags. Non-JSON strings are stripped and returned as-is.
	 */
	private static function payload_text( $raw ) {
		$raw = (string) $raw;
		if ( '' === trim( $raw ) ) {
			return '';
		}
		// Bound the work on pathological payloads.
		if ( strlen( $raw ) > 400000 ) {
			$raw = substr( $raw, 0, 400000 );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return wp_strip_all_tags( $raw );
		}
		$parts = array();
		$walk  = function ( $node ) use ( &$walk, &$parts ) {
			if ( is_string( $node ) ) {
				$parts[] = $node;
			} elseif ( is_array( $node ) ) {
				foreach ( $node as $v ) {
					$walk( $v );
				}
			}
		};
		$walk( $decoded );
		return wp_strip_all_tags( implode( ' ', $parts ) );
	}

	/**
	 * Fetch the live value a change will replace, the same way the apply
	 * path resolves it, for rows that don't store the old payload.
	 */
	private static function fetch_live_old_text( $post_id, $change_type, $proposed_value ) {
		switch ( $change_type ) {
			case 'post_content_update':
				return wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );

			case 'postmeta_update':
				$decoded = json_decode( (string) $proposed_value, true );
				$key     = '';
				if ( is_array( $decoded ) ) {
					if ( isset( $decoded['key'] ) ) {
						$key = (string) $decoded['key'];
					} elseif ( isset( $decoded['meta_key'] ) ) {
						$key = (string) $decoded['meta_key'];
					}
				}
				if ( '' === $key ) {
					return '';
				}
				$val = get_post_meta( $post_id, $key, true );
				return is_scalar( $val )
					? wp_strip_all_tags( (string) $val )
					: self::payload_text( (string) wp_json_encode( $val ) );

			case 'elementor_widget_update':
			case 'elementor_full_import':
				$raw = (string) get_post_meta( $post_id, '_elementor_data', true );
				if ( '' === $raw ) {
					return '';
				}
				if ( 'elementor_widget_update' === $change_type ) {
					$decoded = json_decode( (string) $proposed_value, true );
					$wid     = is_array( $decoded ) && isset( $decoded['widget_id'] ) ? (string) $decoded['widget_id'] : '';
					if ( '' !== $wid ) {
						$node = self::find_element_by_id( json_decode( $raw, true ), $wid );
						if ( null !== $node ) {
							return self::payload_text( (string) wp_json_encode( $node ) );
						}
					}
				}
				return self::payload_text( $raw );
		}
		return '';
	}

	/**
	 * Depth-first search of a decoded Elementor tree for the element whose
	 * id matches. Returns the element array or null.
	 */
	private static function find_element_by_id( $tree, $element_id ) {
		if ( ! is_array( $tree ) ) {
			return null;
		}
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) && (string) $node['id'] === (string) $element_id ) {
				return $node;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$found = self::find_element_by_id( $node['elements'], $element_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Reduce text to distinct significant tokens: uppercase-normalized
	 * alphanumeric words of length >= 3, minus a stopword list. Capped so a
	 * full-page import can't balloon the diff.
	 */
	private static function significant_tokens( $text ) {
		$text = strtoupper( (string) $text );
		if ( '' === trim( $text ) ) {
			return array();
		}
		if ( ! preg_match_all( '/[A-Z0-9]{3,}/', $text, $m ) ) {
			return array();
		}
		static $stop = array(
			'THE', 'AND', 'FOR', 'ARE', 'BUT', 'NOT', 'YOU', 'ALL', 'CAN', 'HAD', 'HAS', 'HER', 'HIM', 'HIS',
			'HOW', 'ITS', 'MAY', 'NEW', 'NOW', 'OLD', 'ONE', 'OUR', 'OUT', 'SEE', 'SHE', 'TWO', 'WAS', 'WAY',
			'WHO', 'ANY', 'GET', 'USE', 'DAY', 'PER', 'VIA', 'YES', 'YET',
			'THIS', 'THAT', 'WITH', 'FROM', 'HAVE', 'THEY', 'BEEN', 'WERE', 'WHAT', 'WHEN', 'YOUR', 'MORE',
			'WILL', 'THAN', 'THEM', 'SOME', 'INTO', 'ONLY', 'OVER', 'SUCH', 'ALSO', 'MOST', 'LIKE', 'JUST',
			'THEN', 'EACH', 'VERY', 'HERE', 'DOES', 'BOTH', 'MADE', 'MANY', 'MUCH', 'MUST', 'NEED', 'SAME',
			'THERE', 'WHERE', 'WHICH', 'THEIR', 'ABOUT', 'WOULD', 'COULD', 'SHOULD', 'OTHER', 'AFTER',
			'BEFORE', 'BECAUSE', 'WHILE', 'THESE', 'THOSE', 'BEING', 'EVERY', 'STILL', 'SINCE', 'UNTIL',
			// Markup/URL residue that survives tag stripping.
			'NBSP', 'AMP', 'QUOT', 'HTTP', 'HTTPS', 'WWW', 'COM', 'HTML', 'HREF', 'SPAN', 'CLASS', 'STYLE',
			'TRUE', 'FALSE', 'NULL', 'PX', 'REM',
		);
		$out   = array();
		$count = 0;
		foreach ( $m[0] as $token ) {
			if ( in_array( $token, $stop, true ) ) {
				continue;
			}
			if ( isset( $out[ $token ] ) ) {
				continue;
			}
			$out[ $token ] = true;
			$count++;
			if ( $count >= 2000 ) {
				break;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Last N rejected pending changes with their reviewer note. Used by
	 * whoami to surface "the reviewer rejected your last work because X"
	 * so the next session learns from past patterns instead of repeating
	 * them.
	 */
	public static function recent_rejected_with_notes( $limit = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, post_id, change_type, change_summary, review_note, reviewed_at
			 FROM {$table}
			 WHERE status = 'rejected'
			   AND review_note IS NOT NULL
			   AND review_note <> ''
			 ORDER BY reviewed_at DESC
			 LIMIT %d",
			(int) $limit
		) );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'          => (int) $r->id,
				'post_id'     => (int) $r->post_id,
				'change_type' => $r->change_type,
				'summary'     => $r->change_summary,
				'note'        => $r->review_note,
				'reviewed_at' => $r->reviewed_at,
			);
		}
		return $out;
	}
}
