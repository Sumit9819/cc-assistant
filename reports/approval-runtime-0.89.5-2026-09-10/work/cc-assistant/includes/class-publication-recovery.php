<?php
/** Refresh an existing, unreviewed publication intent after a fresh draft review. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CC_Assistant_Publication_Recovery {
	public static function refresh( array $args ) {
		$lock = CC_Assistant_Write_Lock::acquire();
		if ( is_wp_error( $lock ) ) { return $lock; }
		try { return self::refresh_locked( $args ); }
		finally { CC_Assistant_Write_Lock::release(); }
	}

	private static function refresh_locked( array $args ) {
		global $wpdb;
		$id = (int) ( $args['pending_id'] ?? 0 );
		$workflow_id = (string) ( $args['workflow_id'] ?? '' );
		$reason = trim( (string) ( $args['reason'] ?? '' ) );
		if ( $id < 1 || '' === $reason || strlen( $reason ) > 4000 ) { return new WP_Error( 'publication_refresh_arguments', 'An existing pending ID and a specific review reason are required.', array( 'status' => 400 ) ); }
		$row = CC_Assistant_Pending_Changes::get( $id );
		if ( ! $row || 'pending' !== $row->status || ! empty( $row->superseded_by ) || 'publish_draft' !== $row->change_type ) {
			return new WP_Error( 'publication_refresh_not_pending', 'Only an unreviewed, unsuperseded publish_draft proposal can be refreshed.', array( 'status' => 409 ) );
		}
		$payload = json_decode( $row->proposed_value, true );
		if ( array( 'post_status' => 'publish' ) !== $payload ) { return new WP_Error( 'publication_refresh_intent', 'The stored proposal is not a supported publication intent.', array( 'status' => 422 ) ); }
		$pid = (int) $row->post_id;
		$post = get_post( $pid );
		if ( ! $post || 'post' !== $post->post_type || 'draft' !== $post->post_status ) { return new WP_Error( 'publication_refresh_not_draft', 'The existing result must still be a blog draft.', array( 'status' => 409 ) ); }
		$old_binding = get_post_meta( $pid, CC_Assistant_Workflow_Verifier::META_KEY, true );
		if ( ! is_array( $old_binding ) || (int) ( $old_binding['actor_id'] ?? 0 ) !== get_current_user_id() || (int) ( $old_binding['pending_id'] ?? 0 ) !== $id ) {
			return new WP_Error( 'publication_refresh_actor', 'Refresh through the actor that owns the server-bound draft and its publication proposal.', array( 'status' => 403 ) );
		}
		$workflow = CC_Assistant_Workflow_Verifier::load( $workflow_id, true );
		if ( is_wp_error( $workflow ) ) { return $workflow; }
		$source = CC_Assistant_Content_Evidence::snapshot( $pid );
		if ( is_wp_error( $source ) ) { return $source; }
		if ( '' === trim( (string) $post->post_title ) || '' === trim( (string) ( $source['text'] ?? '' ) ) ) { return new WP_Error( 'publication_refresh_empty', 'The draft needs a saved title and readable content before publication review.', array( 'status' => 422 ) ); }
		$target_match = false;
		foreach ( $workflow['targets'] ?? array() as $target ) {
			if ( (int) ( $target['post_id'] ?? 0 ) === $pid && ( $target['evidence_id'] ?? '' ) === $source['evidence_id'] ) { $target_match = true; }
		}
		if ( ! $target_match ) { return new WP_Error( 'publication_refresh_target', 'Prepare a current new_blog workflow with post_ids containing this existing draft. Its observed content must still match.', array( 'status' => 409 ) ); }
		$author = CC_Assistant_Content_Authors::validate( (int) $post->post_author );
		if ( is_wp_error( $author ) ) { return $author; }
		$queue_args = array( 'post_id' => $pid, 'change_type' => 'publish_draft', 'proposed_value' => $row->proposed_value );
		$evidence = CC_Assistant_Evidence_Gate::validate_queue( $queue_args );
		if ( is_wp_error( $evidence ) ) { return $evidence; }
		// This feature always needs REST observations, including when called internally.
		if ( empty( $evidence['posts'][$pid]['post_hash'] ) ) { return new WP_Error( 'publication_refresh_evidence', 'Run whoami and get_post(slim=false) through this connection before refreshing publication.', array( 'status' => 409 ) ); }
		$valid = CC_Assistant_Elementor_Validation::validate_payload( $queue_args );
		if ( is_wp_error( $valid ) ) { return $valid; }
		// No override parameter: recovery must not turn a failed quality gate into approval.
		if ( get_post_meta( $pid, '_cc_publish_gate_override', true ) ) { return new WP_Error( 'publication_refresh_override', 'Remove the publication override and inspect the actual gate before refreshing this proposal.', array( 'status' => 409 ) ); }
		$gate = CC_Assistant_Pre_Publish::evaluate_publish_gate( $pid );
		if ( empty( $gate['pass'] ) ) { return new WP_Error( 'publish_gate_blocked', 'The existing draft has blocking publication findings.', array( 'status' => 422, 'gate' => $gate ) ); }
		$before = CC_Assistant_Integrity::baseline( $row );
		$history = (array) ( $before['publication_refresh_history'] ?? array() );
		$history[] = array( 'refreshed_at_gmt' => current_time( 'mysql', true ), 'actor_id' => get_current_user_id(), 'previous_workflow_id' => $old_binding['workflow_id'] ?? '',
			'previous_baseline_sha256' => hash( 'sha256', (string) $row->pre_check_baseline ), 'previous_reasoning' => (string) $row->reasoning );
		$baseline = array( 'evidence' => $evidence, 'queued_at_gmt' => current_time( 'mysql', true ), 'post_hash' => $evidence['posts'][$pid]['post_hash'],
			'publication_refresh_history' => array_slice( $history, -10 ), 'publication_workflow_id' => $workflow_id,
			'publication_workflow_basis' => array_intersect_key( $workflow, array_flip( array( 'source_basis', 'scope_revision', 'context' ) ) ) );
		$json = wp_json_encode( $baseline );
		if ( false === $json ) { return new WP_Error( 'publication_refresh_encoding', 'Could not encode the refreshed evidence.' ); }
		// Recheck after the potentially expensive source and quality reads.
		$proof_row = (object) array( 'pre_check_baseline' => $json );
		$proof = CC_Assistant_Evidence_Gate::validate_apply( $proof_row );
		if ( is_wp_error( $proof ) ) { return $proof; }
		if ( ! CC_Assistant_Workflow_Verifier::basis( $workflow )['current'] ) { return new WP_Error( 'workflow_sources_changed', 'Workflow sources changed during review. Read current sources and prepare the workflow again.', array( 'status' => 409 ) ); }
		$result = array( 'pending_id' => $id, 'post_id' => $pid, 'workflow_id' => $workflow_id, 'post_status' => 'draft', 'author' => $author, 'publication_gate' => $gate,
			'publication_verified' => false, 'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ), 'preview_url' => get_preview_post_link( $pid ) );
		if ( ! empty( $args['dry_run'] ) ) { return array_merge( $result, array( 'state' => 'validated_not_refreshed' ) ); }

		// Bind before the conditional row write, restoring the original binding on failure.
		if ( ! CC_Assistant_Workflow_Verifier::bind( $workflow_id, $pid, $id ) ) {
			update_post_meta( $pid, CC_Assistant_Workflow_Verifier::META_KEY, $old_binding );
			return new WP_Error( 'publication_refresh_binding_failed', 'Could not confirm the refreshed workflow binding. Inspect the existing workflow; the proposal evidence was not changed.', array( 'status' => 500 ) );
		}
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}cc_pending_changes SET pre_check_baseline = %s, reasoning = %s
			 WHERE id = %d AND status = 'pending' AND superseded_by IS NULL AND change_type = 'publish_draft'
			 AND proposed_value = %s AND pre_check_baseline = %s",
			$json, $reason, $id, $row->proposed_value, (string) $row->pre_check_baseline
		) );
		$saved = CC_Assistant_Pending_Changes::get( $id );
		if ( false === $updated || ! $saved || 'pending' !== $saved->status || ! empty( $saved->superseded_by ) || $saved->pre_check_baseline !== $json || $saved->reasoning !== $reason ) {
			// A failed read is not proof that the write failed. Restore the old binding
			// only when the database confirms the original row survived unchanged.
			// Otherwise retain the new binding for reconciliation with a possibly committed row.
			if ( $saved && $saved->pre_check_baseline === $row->pre_check_baseline && $saved->reasoning === $row->reasoning ) {
				update_post_meta( $pid, CC_Assistant_Workflow_Verifier::META_KEY, $old_binding );
			}
			return new WP_Error( 'publication_refresh_storage_failed', 'The publication refresh was not confirmed. Inspect the existing proposal and workflow before retrying; no draft was created or published.', array( 'status' => 500, 'pending_id' => $id ) );
		}
		$verification = CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $workflow_id, 'post_id' => $pid ) );
		$state = ! is_wp_error( $verification ) && ! empty( $verification['record_integrity_pass'] ) ? 'refreshed_awaiting_human_review' : 'refreshed_needs_attention';
		return array_merge( $result, array( 'state' => $state, 'verification' => $verification ) );
	}
}
