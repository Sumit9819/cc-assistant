<?php
/** Agent-owned setup: no form, current evidence, constrained metadata and reviewable work. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' ); define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 );
class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_parse_url( $v, $part = -1 ) { return parse_url( $v, $part ); }
function home_url( $path = '' ) { return 'https://scope.test' . $path; }
function admin_url( $path = '' ) { return home_url( '/wp-admin/' . $path ); }
function get_locale() { return 'en_US'; }
function get_current_blog_id() { return 1; }
function get_current_user_id() { return $GLOBALS['actor'] ?? 9; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, $autoload = null ) {
	if ( ! empty( $GLOBALS['storage_fail'] ) ) { return false; }
	if ( isset( $GLOBALS['options'][$key] ) && $GLOBALS['options'][$key] === $value ) { return false; }
	$GLOBALS['options'][$key] = $value; $GLOBALS['writes'][] = $key; $GLOBALS['autoload'][$key] = $autoload; return true;
}
function wp_cache_delete( $key, $group ) { return true; }
function get_post( $id ) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta( $id, $key = '', $single = true ) { return '' === $key ? ( $GLOBALS['meta'][$id] ?? array() ) : ( $GLOBALS['meta'][$id][$key] ?? '' ); }
function get_permalink( $id ) { return 'https://scope.test/' . ( $GLOBALS['posts'][$id]->post_name ?? $id ) . '/'; }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'], true ); }
function rest_ensure_response( $v ) { return $v; }
function register_rest_route( $ns, $path, $args ) { $GLOBALS['routes'][$path] = $args; }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return esc_html( $s ); }
class WP_Query {
	public $posts; public $found_posts;
	public function __construct( $args ) {
		$ids = array();
		foreach ( $GLOBALS['posts'] as $id => $post ) {
			if ( ! in_array( $post->post_type, (array) $args['post_type'], true ) || ! in_array( $post->post_status, (array) $args['post_status'], true ) || ! empty( $post->post_password ) ) { continue; }
			$ids[] = $id;
		}
		sort( $ids ); $this->found_posts = count( $ids ); $this->posts = array_slice( $ids, $args['offset'] ?? 0, $args['posts_per_page'] );
	}
}
class ScopeDB {
	public $prefix = 'wp_'; public $last_error = '';
	public function prepare( $sql, ...$args ) { return $sql; }
	public function esc_like( $s ) { return $s; }
	public function get_var( $sql ) { return str_contains( $sql, 'GET_LOCK' ) ? ( empty( $GLOBALS['busy'] ) ? 1 : 0 ) : null; }
}
$GLOBALS['wpdb'] = new ScopeDB(); $GLOBALS['caps'] = array( 'cc_assistant_use' );
$GLOBALS['options'] = array( 'blogname' => 'Actual business', 'cc_assistant_site_notes' => 'Focus on our imaging and laboratory services. No invented services or bylines.' );
$GLOBALS['posts'] = array(); $GLOBALS['writes'] = array();
function post_fixture( $id, $title, $type = 'page', $status = 'publish', $password = '' ) {
	$GLOBALS['posts'][$id] = (object) array( 'ID' => $id, 'post_title' => $title, 'post_name' => strtolower( str_replace( ' ', '-', $title ) ),
		'post_content' => str_repeat( $title . ' provides practical information, useful questions and explanations from this business. ', 5 ),
		'post_type' => $type, 'post_status' => $status, 'post_password' => $password, 'post_modified_gmt' => '2026-09-09 00:00:00' );
}
function check( $label, $condition ) { if ( ! $condition ) { throw new RuntimeException( $label ); } echo "PASS $label\n"; }
function save_args( $ids = array( 100,200 ) ) {
	$scope = CC_Assistant_Content_Strategy::scope(); $evidence = array();
	foreach ( $ids as $id ) { $s = CC_Assistant_Content_Evidence::snapshot( $id ); $evidence[] = array( 'post_id' => $id, 'evidence_id' => $s['evidence_id'], 'reason' => 'Inspected the current service explanation and existing site notes.' ); }
	return array( 'expected_revision' => $scope['scope_revision'], 'context_hash' => $scope['context']['context_hash'], 'source_evidence' => $evidence, 'reason' => 'Maintain scope from actual services and reader tasks.' );
}
post_fixture( 10, 'Home' ); post_fixture( 20, 'Privacy Policy' );
post_fixture( 100, 'Diagnostic imaging' ); post_fixture( 200, 'Laboratory testing' );
post_fixture( 300, 'Imaging preparation', 'post' ); post_fixture( 400, 'Secret draft', 'page', 'draft' );
post_fixture( 500, 'Private service', 'page', 'private' ); post_fixture( 600, 'Protected service', 'page', 'publish', 'password' );
require CC_ASSISTANT_DIR . 'includes/class-rest-content-strategy.php';
$discovery = CC_Assistant_Content_Scope::discover();
check( 'discovery is read-only and reuses existing site notes', empty( $GLOBALS['writes'] ) && str_contains( $discovery['context']['site_notes_excerpt'], 'No invented services' ) );
check( 'utility and private pages never become discovered service candidates', array( 100,200,300 ) === array_column( $discovery['candidates'], 'post_id' ) );
check( 'blog mentions remain editorial candidates rather than automatic offered services', ! $discovery['candidates'][2]['automatic_candidate'] );
$read = CC_Assistant_Content_Strategy::scope();
check( 'scope can be inferred without manual post IDs or a form', array( 100,200 ) === array_column( $read['sources'], 'post_id' ) && ! $read['scope_saved'] );
$plan = CC_Assistant_Content_Strategy::plan();
$option = CC_Assistant_Content_Strategy::PROFILE_OPTION;
check( 'planning initializes a saved scope for the restricted operator account without GSC', ! is_wp_error( $plan ) && $plan['candidate_count'] > 0 && 'saved' === $plan['scope_initialization']['status'] && 'automatic_discovery' === $GLOBALS['options'][$option]['managed_by'] );
check( 'automatic setup writes only bounded non-autoload strategy metadata', array( $option ) === $GLOBALS['writes'] && false === $GLOBALS['autoload'][$option] );
$args = save_args(); $args['audience'] = 'Readers seeking explanations of our existing services.';
$args['excluded_topics'] = array( 'celebrity gossip' ); $args['reader_questions'] = array( array( 'post_id' => 100, 'question' => 'What should I ask about diagnostic imaging?' ) );
$saved = CC_Assistant_Content_Scope::save( $args );
check( 'Claude can complete strategy metadata with current evidence', 'saved' === $saved['status'] && 'claude' === $saved['profile']['managed_by'] && 0 === $saved['content_changes'] );
check( 'previous strategy is retained for review', 1 === count( $saved['profile']['history'] ) );
$same = CC_Assistant_Content_Scope::save( save_args() );
check( 'identical strategy save is idempotent even when update_option rejects unchanged values', 'unchanged' === $same['status'] && $same['scope_revision'] === $saved['scope_revision'] );
$stale = $args; $stale['audience'] = 'Old concurrent edit';
check( 'an old revision cannot overwrite newer corrections', 'scope_revision_changed' === CC_Assistant_Content_Scope::save( $stale )->get_error_code() );
$args = save_args(); $GLOBALS['posts'][100]->post_content .= ' Source updated after discovery.';
check( 'changed content invalidates a pending scope update', 'scope_source_changed' === CC_Assistant_Content_Scope::save( $args )->get_error_code() );
$scope = CC_Assistant_Content_Strategy::scope();
check( 'read reports source drift instead of claiming prior interpretations are fresh', array( 100 ) === $scope['freshness']['changed_post_ids'] && 'needs_agent_refresh' === $scope['freshness']['status'] );
$args = save_args(); $GLOBALS['options']['cc_assistant_site_notes'] .= ' An important new operator correction.';
check( 'concurrent note changes cannot be silently stamped as already read', 'scope_context_changed' === CC_Assistant_Content_Scope::save( $args )->get_error_code() );
check( 'scope freshness includes existing site-note changes', CC_Assistant_Content_Strategy::scope()['freshness']['context_changed'] );
$saved = CC_Assistant_Content_Scope::save( save_args() );
check( 'omitted fields preserve exclusions, audience and reader questions', $saved['profile']['excluded_topics'] === array( 'celebrity gossip' ) && 1 === count( $saved['profile']['reader_questions'] ) && str_contains( $saved['profile']['audience'], 'existing services' ) );
$args = save_args(); $args['excluded_topics'] = array();
check( 'exclusions cannot disappear without an explicit changed basis', 'scope_exclusion_preservation' === CC_Assistant_Content_Scope::save( $args )->get_error_code() );
$args = save_args( array( 200 ) );
check( 'scope narrowing cannot orphan recorded questions', 'scope_question_source' === CC_Assistant_Content_Scope::save( $args )->get_error_code() );
$args = save_args(); $GLOBALS['posts'][100]->post_password = 'new protection';
check( 'newly protected pages cannot be used as public scope evidence', 'scope_source_changed' === CC_Assistant_Content_Scope::save( $args )->get_error_code() );
check( 'newly unavailable sources are explicit on read', array( 100 ) === CC_Assistant_Content_Strategy::scope()['freshness']['unavailable_post_ids'] );
$GLOBALS['posts'][100]->post_password = '';
$args = save_args(); $args['reason'] = 'Different maintenance note'; $before = $GLOBALS['options'][$option];
$GLOBALS['storage_fail'] = true; $failure = CC_Assistant_Content_Scope::save( $args ); $GLOBALS['storage_fail'] = false;
check( 'storage failure preserves the existing strategy and reports failure', 'scope_storage_failed' === $failure->get_error_code() && $before === $GLOBALS['options'][$option] );
$GLOBALS['busy'] = true; $failure = CC_Assistant_Content_Scope::save( $args ); $GLOBALS['busy'] = false;
check( 'concurrent maintenance fails explicitly instead of overwriting', 'scope_store_busy' === $failure->get_error_code() );
$GLOBALS['caps'] = array();
check( 'unauthorized accounts cannot maintain strategy metadata', 'scope_forbidden' === CC_Assistant_Content_Scope::save( $args )->get_error_code() );
$GLOBALS['caps'] = array( 'cc_assistant_use' );
$before = $GLOBALS['options'][$option]; CC_Assistant_Content_Strategy::plan();
check( 'planning preserves a strategy already maintained by Claude', $before === $GLOBALS['options'][$option] );
$before_posts = serialize( $GLOBALS['posts'] ); $workflow = CC_Assistant_Content_Workflow::prepare();
check( 'workflow persists a reviewable agent handoff without writing live content', ! is_wp_error( $workflow ) && 'prepared_not_executed' === $workflow['record']['execution_state'] && $before_posts === serialize( $GLOBALS['posts'] ) );
$repeat = CC_Assistant_Content_Workflow::prepare();
check( 'same workflow evidence reuses its record', $repeat['reused'] && $repeat['record_id'] === $workflow['record_id'] );
check( 'workflow never pretends an unattended AI runner was started', ! $workflow['record']['background_execution']['scheduled_ai_runner_configured_by_this_feature'] && $workflow['record']['background_execution']['claude_session_required'] );
check( 'refresh workflow cannot claim work on an unspecified target', 'workflow_targets' === CC_Assistant_Content_Workflow::prepare( array( 'objective' => 'refresh' ) )->get_error_code() );
$GLOBALS['actor'] = 10;
check( 'workflow history remains actor-scoped', 'decision_not_found' === CC_Assistant_Content_Decisions::history( $workflow['record_id'] )->get_error_code() );
$GLOBALS['actor'] = 9;
for ( $i = 0; $i < 12; $i++ ) { $args = save_args(); $args['reason'] = 'Maintenance observation ' . $i; CC_Assistant_Content_Scope::save( $args ); }
check( 'profile history remains bounded after repeated maintenance', 10 === count( $GLOBALS['options'][$option]['history'] ) );
$GLOBALS['caps'] = array( 'manage_options' ); ob_start(); CC_Assistant_REST_Content_Strategy::admin_page(); $html = ob_get_clean();
check( 'administrator screen reviews results without exposing a setup form', ! str_contains( $html, '<form' ) && str_contains( $html, 'Review pending changes' ) );
CC_Assistant_REST_Content_Strategy::register_routes();
foreach ( $GLOBALS['routes'] as $route ) { check( 'route is permission-gated: ' . $route['callback'][1], $route['permission_callback'] === array( 'CC_Assistant_REST_Content_Strategy', 'permission' ) ); }
echo "All managed content scope checks passed.\n";
