<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-access.php';
require_once __DIR__ . '/class-content-decisions.php';
require_once __DIR__ . '/class-content-workflow.php';

class CC_Assistant_REST_Content_Strategy {
	public static function register_routes() {
		require_once __DIR__ . '/class-external-research.php';
		$ids = array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'maxItems' => 40 );
		$texts = array( 'type' => 'array', 'items' => array( 'type' => 'string', 'maxLength' => 220 ), 'maxItems' => 40 );
		$routes = array(
			'/content/publication/refresh' => array( 'POST', 'refresh_publication', array(
				'pending_id' => array( 'type' => 'integer', 'minimum' => 1, 'required' => true ),
				'workflow_id' => array( 'type' => 'string', 'pattern' => '^workflow-[a-f0-9]{32}$', 'required' => true ),
				'reason' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 1000, 'required' => true ),
				'dry_run' => array( 'type' => 'boolean', 'default' => false ),
			) ),
			'/content/external-research' => array( 'POST', 'external_research', CC_Assistant_External_Research::schema() ),
			'/content/authors' => array( 'GET', 'authors', array() ),
			'/content/scope' => array( 'GET', 'scope', array() ),
			'/content/scope/discover' => array( 'GET', 'discover', array(
				'scan_limit' => array( 'type' => 'integer', 'minimum' => 10, 'maximum' => 500, 'default' => 200 ),
				'offset' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
			) ),
			'/content/scope/manage' => array( 'POST', 'manage', array(
				'author_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_author_name' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 250 ),
				'expected_revision' => array( 'type' => 'string', 'pattern' => '^scope-[a-f0-9]{32}$', 'required' => true ),
				'context_hash' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$', 'required' => true ),
				'reason' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 1000, 'required' => true ),
				'source_evidence' => array( 'type' => 'array', 'minItems' => 1, 'maxItems' => 40, 'required' => true,
					'items' => array( 'type' => 'object', 'required' => array( 'post_id', 'evidence_id', 'reason' ), 'additionalProperties' => false,
						'properties' => array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'evidence_id' => array( 'type' => 'string', 'pattern' => '^post-[0-9]+-[a-f0-9]{24}$' ), 'reason' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 400 ) ) ) ),
				'audience' => array( 'type' => 'string', 'maxLength' => 500 ),
				'excluded_topics' => array( 'type' => 'array', 'maxItems' => 50, 'items' => array( 'type' => 'string', 'maxLength' => 220 ) ),
				'exclusion_change_reason' => array( 'type' => 'string', 'maxLength' => 700 ),
				'reader_questions' => array( 'type' => 'array', 'maxItems' => 50, 'items' => array( 'type' => 'object', 'additionalProperties' => false,
					'required' => array( 'post_id', 'question' ), 'properties' => array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'question' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 220 ) ) ) ),
			) ),
            '/content/workflow/verify' => array( 'POST', 'verify_workflow', array(
                'workflow_id' => array( 'type' => 'string', 'pattern' => '^workflow-[a-f0-9]{32}$', 'required' => true ),
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ) ),
			'/content/workflow' => array( 'POST', 'workflow', array(
				'objective' => array( 'type' => 'string', 'enum' => array( 'new_blog', 'refresh', 'site_review' ), 'default' => 'new_blog' ),
				'post_ids' => array( 'type' => 'array', 'maxItems' => 8, 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ),
				'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5 ),
			) ),
			'/content/plan' => array( 'POST', 'plan', array(
				'service_post_ids' => $ids, 'topics' => $texts,
				'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 12 ),
				'scan_limit' => array( 'type' => 'integer', 'minimum' => 10, 'maximum' => 500, 'default' => 200 ),
				'offset' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
				'candidate_offset' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 200, 'default' => 0 ),
				'days' => array( 'type' => 'integer', 'minimum' => 7, 'maximum' => 90, 'default' => 28 ),
			) ),
			'/content/research' => array( 'POST', 'research', array(
				'external_research_ids' => array( 'type' => 'array', 'maxItems' => 5, 'items' => array( 'type' => 'string', 'pattern' => '^research-[a-f0-9]{32}$' ) ),
				'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'topic' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 220 ),
				'anchor_post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'reader_goal' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 700 ),
				'proposed_contribution' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 1000 ),
				'primary_source_urls' => array( 'type' => 'array', 'maxItems' => 3, 'items' => array( 'type' => 'string', 'maxLength' => 2048 ) ),
				'scan_limit' => array( 'type' => 'integer', 'minimum' => 10, 'maximum' => 500, 'default' => 200 ),
				'offset' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
				'competitor_urls' => array( 'type' => 'array', 'maxItems' => 3, 'items' => array( 'type' => 'string', 'maxLength' => 2048 ) ),
				'keyword' => array( 'type' => 'string', 'maxLength' => 200 ),
				'location' => array( 'type' => 'string', 'maxLength' => 100 ),
				'language' => array( 'type' => 'string', 'maxLength' => 50 ),
				'device' => array( 'type' => 'string', 'maxLength' => 30 ),
			) ),
			'/content/decision' => array( 'POST', 'decision', array(
				'post_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'minItems' => 1, 'maxItems' => 8, 'required' => true ),
				'action' => array( 'type' => 'string', 'enum' => array( 'assess','refresh','link','differentiate','merge','retire' ), 'default' => 'assess' ),
				'reader_goal' => array( 'type' => 'string', 'maxLength' => 700 ),
				'reason' => array( 'type' => 'string', 'maxLength' => 1000 ),
			) ),
			'/content/history' => array( 'GET', 'history', array( 'record_id' => array( 'type' => 'string', 'pattern' => '^(?:decision|research|workflow)-[a-f0-9]{32}$' ) ) ),
		);
		foreach ( $routes as $path => $route ) {
			register_rest_route( 'cc-assistant/v1', $path, array( 'methods' => $route[0],
				'callback' => array( __CLASS__, $route[1] ), 'permission_callback' => array( __CLASS__, 'permission' ), 'args' => $route[2] ) );
		}
	}

	public static function permission() {
		return CC_Assistant_Access::can_use() ? true : new WP_Error( 'content_forbidden', 'CC Assistant access is required.', array( 'status' => 403 ) );
	}
	private static function wrap( $value ) {
		return is_wp_error( $value ) ? $value : rest_ensure_response( $value );
	}
	public static function scope() {
		$scope = CC_Assistant_Content_Strategy::scope();
		foreach ( $scope['sources'] as &$source ) { $source['text_excerpt'] = mb_substr( $source['text'], 0, 700 ); unset( $source['text'], $source['links'] ); } unset( $source );
		$scope['policy'] = CC_Assistant_Content_Strategy::policy();
		return self::wrap( $scope );
	}
	public static function discover( $request ) { return self::wrap( CC_Assistant_Content_Scope::discover( $request->get_params() ) ); }
	public static function authors() { require_once __DIR__ . '/class-content-authors.php'; return self::wrap( CC_Assistant_Content_Authors::listing() ); }
	public static function manage( $request ) { return self::wrap( CC_Assistant_Content_Scope::save( $request->get_params() ) ); }
	public static function verify_workflow( $request ) { require_once __DIR__ . '/class-workflow-verifier.php'; return self::wrap( CC_Assistant_Workflow_Verifier::verify( $request->get_params() ) ); }
	public static function workflow( $request ) { return self::wrap( CC_Assistant_Content_Workflow::prepare( $request->get_params() ) ); }
	public static function refresh_publication( $request ) {
		require_once __DIR__ . '/class-workflow-verifier.php';
		require_once __DIR__ . '/class-content-authors.php';
		require_once __DIR__ . '/class-pre-publish.php';
		require_once __DIR__ . '/class-elementor-validation.php';
		require_once __DIR__ . '/class-write-lock.php';
		require_once __DIR__ . '/class-pending-changes.php';
		require_once __DIR__ . '/class-publication-recovery.php';
		return self::wrap( CC_Assistant_Publication_Recovery::refresh( $request->get_params() ) );
	}
	public static function plan( $request ) { return self::wrap( CC_Assistant_Content_Strategy::plan( $request->get_params() ) ); }
	public static function research( $request ) { return self::wrap( CC_Assistant_Content_Decisions::research( $request->get_params() ) ); }
	public static function external_research( $request ) { require_once __DIR__ . '/class-external-research.php'; return self::wrap( CC_Assistant_External_Research::capture( $request->get_params() ) ); }
	public static function decision( $request ) { return self::wrap( CC_Assistant_Content_Decisions::assess( $request->get_params() ) ); }
	public static function history( $request ) { return self::wrap( CC_Assistant_Content_Decisions::history( (string) $request->get_param( 'record_id' ) ) ); }

	public static function admin_menu() {
		add_submenu_page( 'cc-assistant', 'Content Strategy', 'Content Strategy', 'manage_options', 'cc-assistant-content-strategy', array( __CLASS__, 'admin_page' ) );
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$scope = CC_Assistant_Content_Strategy::scope();
		$profile = (array) get_option( CC_Assistant_Content_Strategy::PROFILE_OPTION, array() );
		echo '<div class="wrap"><h1>Content Strategy</h1><p>Claude handles strategy setup and maintenance using existing pages and site knowledge. This screen is for reviewing the result. Tell Claude any corrections in your conversation.</p>';
		echo '<p><strong>Managed by:</strong> ' . esc_html( $scope['managed_by'] ) . ' &nbsp; <strong>Evidence:</strong> ' . esc_html( $scope['freshness']['status'] ) . '</p>';
		if ( ! $scope['scope_saved'] ) { echo '<div class="notice notice-info"><p>No saved strategy yet. Ask Claude to handle a blog or site review; it can discover and save the strategy without any form setup.</p></div>'; }
		if ( 'needs_agent_refresh' === $scope['freshness']['status'] ) { echo '<div class="notice notice-warning"><p>Some source content or site notes changed. Claude should inspect the changed evidence and update its strategy before relying on older interpretations.</p></div>'; }
		echo '<h2>Scope sources</h2><ul>';
		foreach ( $scope['sources'] as $source ) { echo '<li><a href="' . esc_url( $source['url'] ) . '">' . esc_html( $source['title'] ) . '</a> — ' . esc_html( $source['coverage'] ) . '</li>'; }
		echo '</ul><h2>Intended readers</h2><p>' . esc_html( $scope['audience'] ?: 'Claude has not recorded an audience interpretation yet.' ) . '</p><h2>Excluded topics</h2><ul>';
		foreach ( $scope['excluded_topics'] as $topic ) { echo '<li>' . esc_html( $topic ) . '</li>'; }
		echo '</ul><h2>Reader questions</h2><ul>';
		foreach ( $scope['reader_questions'] as $question ) { echo '<li>' . esc_html( $question['question'] ) . '</li>'; }
		echo '</ul><h2>Last change</h2><p>' . esc_html( $profile['reason'] ?? 'No change has been recorded.' ) . '</p>';
		if ( ! empty( $profile['history'] ) ) {
			echo '<details><summary>Previous strategy changes</summary><ul>';
			foreach ( array_reverse( $profile['history'] ) as $item ) { echo '<li>' . esc_html( ( $item['profile']['updated_at_utc'] ?? 'Earlier profile' ) . ': ' . ( $item['profile']['reason'] ?? 'Existing strategy' ) ) . '</li>'; }
			echo '</ul></details>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=cc-assistant-pending' ) ) . '">Review pending changes</a></p><p>Claude prepares drafts and proposed changes through the existing review workflow. This strategy screen does not publish content.</p></div>';
	}
}
