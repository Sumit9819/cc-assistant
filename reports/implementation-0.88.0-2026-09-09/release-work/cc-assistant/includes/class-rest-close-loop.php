<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Close-the-loop REST routes (v0.58): revert-from-outcome + lead events.
 * Lives in its own class so the file can be added without touching
 * class-rest-api.php (where parallel work happens).
 */
class CC_Assistant_REST_Close_Loop {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/revert/propose',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_propose_revert' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_leads' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array( 'days' => array( 'default' => 90 ) ),
			)
		);
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to use CC Assistant.', array( 'status' => 403 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	/**
	 * POST /revert/propose {edit_id, force?, reasoning?}
	 *
	 * Queues the restore of the snapshot captured just before that edit was
	 * applied, as a NORMAL pending change (draft-only doctrine: the human
	 * approves the revert like any other change). Refuses when newer applied
	 * edits exist on the same post (a restore would clobber them) unless
	 * force=true — and then says exactly what will be overwritten.
	 */
	public static function handle_propose_revert( $request ) {
		global $wpdb;

		$params  = $request->get_json_params();
		$params  = is_array( $params ) ? $params : array();
		$edit_id = isset( $params['edit_id'] ) ? (int) $params['edit_id'] : 0;
		$force   = ! empty( $params['force'] );
		if ( $edit_id <= 0 ) {
			return new WP_Error( 'invalid_payload', 'edit_id (integer) is required.', array( 'status' => 400 ) );
		}

		$edit = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cc_edits WHERE id = %d", $edit_id ),
			ARRAY_A
		);
		if ( ! $edit ) {
			return new WP_Error( 'edit_not_found', 'No applied edit with id ' . $edit_id . '.', array( 'status' => 404 ) );
		}
		$post_id = (int) $edit['post_id'];
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error( 'post_not_found', 'The edited post no longer exists.', array( 'status' => 404 ) );
		}

		// The snapshot taken at (or moments before) apply time is the revert
		// target. (v0.60.1: column is snapshot_type, not type — the original
		// SELECT errored and every propose_revert returned no_snapshot.)
		$snaps = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at, snapshot_type FROM {$wpdb->prefix}cc_snapshots
				 WHERE post_id = %d AND created_at <= %s ORDER BY created_at DESC, id ASC LIMIT 10",
				$post_id,
				$edit['applied_at']
			),
			ARRAY_A
		);
		if ( empty( $snaps ) ) {
			return new WP_Error( 'no_snapshot', 'No snapshot exists at or before this edit\'s apply time — cannot build a safe revert.', array( 'status' => 422 ) );
		}
		// Same-second disambiguation (bulk approves): several snapshots can
		// share the boundary second. Rank this edit among same-post edits
		// applied in ITS second, and pick the matching snapshot by id order —
		// otherwise reverting edit #1 of a bulk pair restores edit #2's
		// pre-apply state, which already CONTAINS edit #1.
		$boundary = (string) $snaps[0]['created_at'];
		$same_sec = array_values( array_filter( $snaps, function ( $s ) use ( $boundary ) {
			return (string) $s['created_at'] === $boundary;
		} ) );
		$snap = $same_sec[0];
		if ( count( $same_sec ) > 1 ) {
			$rank = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}cc_edits WHERE post_id = %d AND applied_at = %s AND id <= %d",
					$post_id,
					$edit['applied_at'],
					$edit_id
				)
			);
			$snap = isset( $same_sec[ $rank - 1 ] ) ? $same_sec[ $rank - 1 ] : end( $same_sec );
		}

		// Newer applied edits on the same post would be clobbered by the restore.
		$newer = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, change_type, change_summary, applied_at FROM {$wpdb->prefix}cc_edits
				 WHERE post_id = %d AND applied_at > %s ORDER BY applied_at ASC LIMIT 10",
				$post_id,
				$edit['applied_at']
			),
			ARRAY_A
		);
		if ( ! empty( $newer ) && ! $force ) {
			return new WP_Error(
				'newer_edits_exist',
				sprintf(
					'%d edit(s) were applied to this post AFTER edit #%d — restoring snapshot #%d would undo those too. Review them, then re-call with force=true if that is intended.',
					count( $newer ),
					$edit_id,
					(int) $snap['id']
				),
				array( 'status' => 409, 'newer_edits' => $newer )
			);
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		$summary = sprintf(
			'REVERT edit #%d (%s) by restoring snapshot #%d from %s%s',
			$edit_id,
			mb_substr( (string) $edit['change_summary'], 0, 120 ),
			(int) $snap['id'],
			(string) $snap['created_at'],
			! empty( $newer ) ? ' — WARNING: also undoes ' . count( $newer ) . ' later edit(s)' : ''
		);
		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => $post_id,
				'change_type'    => 'snapshot_restore',
				'change_summary' => $summary,
				// current_value is filled at APPLY time with the pre-restore
				// snapshot id — that's what the inbox Rollback button restores.
				'current_value'  => wp_json_encode( array( 'pre_restore_snapshot_id' => null ) ),
				'proposed_value' => wp_json_encode( array( 'snapshot_id' => (int) $snap['id'], 'edit_id' => $edit_id ) ),
				'reasoning'      => isset( $params['reasoning'] ) ? sanitize_textarea_field( (string) $params['reasoning'] ) : 'Outcome report verdict: regressed.',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return rest_ensure_response(
			array(
				'pending_id'          => $pending_id,
				'post_id'             => $post_id,
				'snapshot_id'         => (int) $snap['id'],
				'snapshot_created_at' => (string) $snap['created_at'],
				'undoes_newer_edits'  => count( (array) $newer ),
				'note'                => 'Queued for human approval like any other change; the restore itself takes a pre-restore snapshot, so approving it is also reversible.',
			)
		);
	}

	/** GET /leads?days=90 — compact columns/rows daily lead counts. */
	public static function handle_leads( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-lead-events.php';
		$rows = CC_Assistant_Lead_Events::query_rows( (int) $request->get_param( 'days' ) );
		return rest_ensure_response(
			array(
				'columns' => array( 'date', 'post_id', 'form_name', 'count' ),
				'rows'    => $rows,
				'total'   => array_sum( array_map( function ( $r ) { return (int) $r[3]; }, $rows ) ),
			)
		);
	}
}
