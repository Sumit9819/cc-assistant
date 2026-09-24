<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-content-decisions.php';

/** A persisted handoff for the connected agent; never reports tool execution that did not happen. */
class CC_Assistant_Content_Workflow {
	public static function prepare( $args = array() ) {
		$objective = (string) ( $args['objective'] ?? 'new_blog' );
		if ( ! in_array( $objective, array( 'new_blog', 'refresh', 'site_review' ), true ) ) { return new WP_Error( 'workflow_objective', 'Unknown content workflow.', array( 'status' => 400 ) ); }
		$ids = array_values( array_unique( array_map( 'intval', (array) ( $args['post_ids'] ?? array() ) ) ) ); sort( $ids );
		if ( count( $ids ) > 8 || ( 'refresh' === $objective && empty( $ids ) ) ) { return new WP_Error( 'workflow_targets', 'Refresh needs one to eight target post IDs.', array( 'status' => 400 ) ); }
		$targets = array();
		foreach ( $ids as $id ) {
			$source = CC_Assistant_Content_Evidence::snapshot( $id );
			if ( is_wp_error( $source ) ) { return $source; }
			$targets[] = array( 'post_id' => $id, 'title' => $source['title'], 'url' => $source['url'], 'evidence_id' => $source['evidence_id'], 'coverage' => $source['coverage'] );
		}
		$plan = CC_Assistant_Content_Strategy::plan( array( 'limit' => max( 1, min( 10, (int) ( $args['limit'] ?? 5 ) ) ) ) );
		if ( is_wp_error( $plan ) ) { return $plan; }
		$scope = CC_Assistant_Content_Strategy::scope();
		$steps = array(
			array( 'task' => 'Read existing knowledge and available capabilities', 'tools' => array( 'whoami', 'get_site_memory', 'list_pending_changes' ), 'completion' => 'Use existing notes and actual plugin/editor schemas. Check pending work before creating another proposal.' ),
			array( 'task' => 'Maintain the strategy', 'tools' => array( 'discover_content_scope', 'get_content_scope', 'manage_content_scope' ), 'completion' => 'Claude inspects source pages, preserves operator constraints, saves audience/questions and explains exclusions. Resolve stale evidence without asking the operator to enter IDs or configure a form.' ),
		);
		if ( 'new_blog' === $objective ) {
			$steps[] = array( 'task' => 'Choose and research a distinct reader task', 'tools' => array( 'plan_blog_content', 'content_research', 'content_decision' ), 'completion' => 'For a new article call content_research with topic, current anchor_post_id, reader_goal and proposed_contribution; no existing article is required. Use the included niche proposals even without GSC gaps. Inspect overlap, research comparable public URLs and primary sources using available search tools, and identify a supported useful contribution. Unknowns remain explicit.' );
			$steps[] = array( 'task' => 'Prepare the complete draft', 'tools' => array( 'get_content_authors', 'list_categories', 'draft_create_post', 'get_post' ), 'completion' => 'Claude chooses an observed public author and prepares the article, appropriate category, supported sources, metadata, useful internal links and available media. Follow current site style and layout preferences. Use available Ubersuggest MCP to research demand and comparable pages, preserving date, location and uncertainty in record_external_research; do not require a GSC gap or positive volume. Map consequential claims to inspected primary sources and explicitly mark claims requiring business or clinical confirmation. Read each tool schema. Save a draft and return its actual ID; do not claim a proposed article is already created.' );
		} else {
			$steps[] = array( 'task' => 'Diagnose the actual deficiency', 'tools' => array( 'verified_page_audit', 'content_decision', 'get_plugin_settings' ), 'completion' => 'Inspect current target-bound rendered evidence and relevant performance. Inspect plugin controls before proposing settings. Similarity, low clicks and checklist scores do not prove a need to merge or rewrite.' );
			$steps[] = array( 'task' => 'Prepare the smallest supported changes', 'tools' => array( 'get_post', 'draft_patch_post_content', 'draft_update_post_content' ), 'completion' => 'Choose tools supported by the actual builder and target. Preserve useful material. Queue concrete changes via existing draft tools; no automatic retirement or guessed options.' );
		}
		$steps[] = array( 'task' => 'Give the operator a review package', 'tools' => array( 'verify_content_workflow', 'list_pending_changes', 'get_post' ), 'completion' => 'Bind new drafts using workflow_id. Verify actual saved records with verify_content_workflow. Show actual draft/pending IDs, preview links, what changes, why, supporting evidence and unresolved issues. Operator reviews the result; setup and research belong to Claude.' );
		$steps[] = array( 'task' => 'Verify applied work', 'tools' => array( 'verified_page_audit', 'content_decision_history' ), 'completion' => 'After changes are actually applied through the existing review workflow, verify saved and served output. Report what passed, failed or remains unverified. A prepared workflow is not an applied change.' );
		$payload = array( 'post_ids' => $ids, 'assessment' => 'workflow_prepared_for_connected_agent', 'objective' => $objective,
			'execution_state' => 'prepared_not_executed', 'scope_revision' => $scope['scope_revision'], 'scope_freshness' => $scope['freshness'],
			'source_basis' => array_values( array_merge( array_map( static function ( $s ) { return array( 'post_id' => $s['post_id'], 'evidence_id' => $s['evidence_id'] ); }, $scope['sources'] ), $targets ) ),
			'context' => $scope['context'], 'targets' => $targets, 'blog_plan' => $plan, 'agent_steps' => $steps,
			'operator_role' => 'Review concrete drafts, changes and exceptions; no manual strategy setup is required.',
			'background_execution' => array( 'claude_session_required' => true, 'scheduled_ai_runner_configured_by_this_feature' => false,
				'explanation' => 'The plugin supplies evidence, metadata and a saved workflow. The connected Claude agent performs research and drafting while its session runs. This feature does not launch or schedule Claude sessions.' ),
			'next_step' => 'Claude: execute the relevant steps with available tools, then report the actual reviewable result. Do not stop after returning this plan or ask the operator to perform discoverable setup.' );
		$basis_plan = $plan; unset( $basis_plan['generated_at_utc'], $basis_plan['scope_initialization'] );
		return CC_Assistant_Content_Decisions::save( 'workflow', array( $objective, $targets, $scope['scope_revision'], $scope['context']['context_hash'], $basis_plan ), $payload );
	}
}
