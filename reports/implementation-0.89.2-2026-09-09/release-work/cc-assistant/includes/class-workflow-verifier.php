<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-content-workflow.php';

/** Observe actual records; never treats a plan or a client assertion as execution. */
class CC_Assistant_Workflow_Verifier {
    const META_KEY = '_cc_assistant_content_workflow';

    public static function basis( $record ) {
        $scope = CC_Assistant_Content_Strategy::scope();
        $changed = array();
        foreach ( (array) ( $record['source_basis'] ?? array() ) as $source ) {
            $now = CC_Assistant_Content_Evidence::snapshot( (int) $source['post_id'] );
            if ( is_wp_error( $now ) || $now['evidence_id'] !== $source['evidence_id'] ) { $changed[] = (int) $source['post_id']; }
        }
        $matches = ! empty( $record['source_basis'] ) && empty( $changed )
            && ( $record['scope_revision'] ?? '' ) === $scope['scope_revision']
            && ( $record['context']['context_hash'] ?? '' ) === $scope['context']['context_hash']
            && 'source_hashes_match' === $scope['freshness']['status'];
        return array( 'current' => $matches, 'changed_source_ids' => $changed, 'scope_freshness' => $scope['freshness'],
            'instruction' => $matches ? 'Stored source basis still matches; factual truth and editorial adequacy require review.' : 'Read changed sources and strategy, then prepare a fresh workflow before claiming current results.' );
    }

    public static function load( $id, $for_creation = false ) {
        if ( ! preg_match( '/^workflow-[a-f0-9]{32}$/', (string) $id ) ) { return new WP_Error( 'workflow_id_invalid', 'Use the workflow record ID returned by content_workflow.', array( 'status' => 400 ) ); }
        $record = CC_Assistant_Content_Decisions::history( $id );
        if ( is_wp_error( $record ) ) { return $record; }
        if ( $for_creation && ( $record['objective'] ?? '' ) !== 'new_blog' ) { return new WP_Error( 'workflow_objective_mismatch', 'Only new_blog workflows can bind a new blog draft.', array( 'status' => 422 ) ); }
        if ( $for_creation && ! self::basis( $record )['current'] ) { return new WP_Error( 'workflow_sources_changed', 'Workflow evidence is stale or unbound. Refresh the strategy and prepare a current workflow before creating its draft.', array( 'status' => 409 ) ); }
        return $record;
    }

    public static function bind( $id, $post_id, $pending_id ) {
        $binding = array( 'workflow_id' => $id, 'actor_id' => get_current_user_id(), 'pending_id' => (int) $pending_id );
        update_post_meta( $post_id, self::META_KEY, $binding );
        return get_post_meta( $post_id, self::META_KEY, true ) === $binding;
    }

    public static function verify( $args ) {
        $id = (string) ( $args['workflow_id'] ?? '' );
        $record = self::load( $id ); if ( is_wp_error( $record ) ) { return $record; }
        $basis = self::basis( $record );
        $post_id = (int) ( $args['post_id'] ?? 0 );
        $out = array( 'workflow_id' => $id, 'checked_at_utc' => gmdate( 'c' ), 'source_basis' => $basis,
            'execution_state' => 'no_result_verified', 'record_integrity_pass' => false, 'publication_verified' => false,
            'editorial_quality' => 'requires_review', 'rendered_output' => 'not_checked', 'issues' => array() );
        if ( ! $post_id ) { $out['issues'][] = 'No result post ID supplied. A prepared workflow is not a completed draft.'; return $out; }
        $post = get_post( $post_id );
        $binding = get_post_meta( $post_id, self::META_KEY, true );
        if ( ! $post || ! is_array( $binding ) || ( $binding['workflow_id'] ?? '' ) !== $id || (int) ( $binding['actor_id'] ?? 0 ) !== get_current_user_id() ) {
            return new WP_Error( 'workflow_result_unbound', 'This post is not a server-bound result of this workflow for the current actor.', array( 'status' => 422 ) );
        }
        require_once __DIR__ . '/class-pending-changes.php';
        $pending = CC_Assistant_Pending_Changes::get( (int) $binding['pending_id'] );
        $pending = is_object( $pending ) && ! is_wp_error( $pending ) ? (array) $pending : $pending;
        $pending_matches = is_array( $pending ) && (int) ( $pending['post_id'] ?? 0 ) === $post_id && ( $pending['change_type'] ?? '' ) === 'publish_draft' && empty( $pending['superseded_by'] );
        $source = CC_Assistant_Content_Evidence::snapshot( $post_id );
        $content_present = ! is_wp_error( $source ) && '' !== trim( $source['text'] ) && '' !== trim( $post->post_title );
        $out['result'] = array( 'post_id' => $post_id, 'post_status' => $post->post_status, 'post_type' => $post->post_type,
            'pending_id' => (int) $binding['pending_id'], 'pending_status' => $pending_matches ? ( $pending['status'] ?? 'unknown' ) : 'missing_or_mismatched',
            'evidence_id' => is_wp_error( $source ) ? null : $source['evidence_id'], 'preview_url' => get_preview_post_link( $post_id ), 'content_present' => $content_present );
        if ( ! $basis['current'] ) { $out['issues'][] = 'Source basis changed or is unbound.'; }
        if ( ! $content_present ) { $out['issues'][] = 'Saved title or readable content is missing.'; }
        if ( ! $pending_matches ) { $out['issues'][] = 'Matching publish proposal is unavailable.'; }
        if ( 'post' !== $post->post_type || 'draft' !== $post->post_status ) { $out['issues'][] = 'Expected a blog draft. Saved status is reported; publication and served output are not certified.'; }
        if ( $pending_matches && 'pending' !== ( $pending['status'] ?? '' ) ) { $out['issues'][] = 'The publish proposal is no longer awaiting review.'; }
        if ( $pending_matches ) {
            $proof = CC_Assistant_Evidence_Gate::validate_apply( (object) $pending );
            if ( is_wp_error( $proof ) ) { $out['issues'][] = 'Publication proposal evidence is stale: ' . $proof->get_error_message(); }
        }
        $out['record_integrity_pass'] = empty( $out['issues'] );
        $out['execution_state'] = $out['record_integrity_pass'] ? 'draft_and_pending_record_verified' : 'result_observed_needs_attention';
        return $out;
    }
}
