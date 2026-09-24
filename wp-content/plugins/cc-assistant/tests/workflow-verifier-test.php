<?php
// Reuse the existing WordPress fixture and scope lifecycle checks.
require __DIR__ . '/content-scope-test.php';
require CC_ASSISTANT_DIR . 'includes/class-workflow-verifier.php';
require CC_ASSISTANT_DIR . 'includes/class-evidence-gate.php';
function update_post_meta( $id, $key, $value ) { if ( ! empty( $GLOBALS['meta_failure'] ) ) { return false; } $GLOBALS['meta'][$id][$key] = $value; return true; }
function get_preview_post_link( $id ) { return home_url( '/?preview=true&p=' . $id ); }
function get_object_taxonomies( $type ) { return array(); }
function get_plugins() { return array(); }
function get_site_option( $key, $default = false ) { return $default; }
class VerifierDB extends ScopeDB {
	public function get_row( $sql ) { return $GLOBALS['pending_fixture'] ?? null; }
	// 0.90.1: publish_draft now reaches the approved-history lookup; no history here.
	public function get_results( $sql ) { return array(); }
}
$GLOBALS['wpdb'] = new VerifierDB();
$workflow = CC_Assistant_Content_Workflow::prepare(); $id = $workflow['record_id'];
check( 'current workflow has verifiable source basis', ! is_wp_error( CC_Assistant_Workflow_Verifier::load( $id, true ) ) );
$r = CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id ) );
check( 'prepared plan is never a completed article', ! $r['record_integrity_pass'] && 'no_result_verified' === $r['execution_state'] );
post_fixture( 901, 'Useful draft', 'post', 'draft' );
check( 'unrelated draft cannot prove completion', 'workflow_result_unbound' === CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )->get_error_code() );
check( 'server binds an actual result', CC_Assistant_Workflow_Verifier::bind( $id, 901, 71 ) );
$GLOBALS['pending_fixture'] = (object) array( 'id' => 71, 'post_id' => 901, 'change_type' => 'publish_draft', 'status' => 'pending', 'proposed_value' => '{"post_status":"publish"}' );
$proof = json_encode( array( 'evidence' => array( 'environment_hash' => CC_Assistant_Evidence_Gate::environment_hash(), 'posts' => array( 901 => array( 'post_hash' => CC_Assistant_Integrity::post_hash( 901 ) ) ) ) ) );
check( 'missing server evidence is not review-ready', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['pending_fixture']->pre_check_baseline = $proof;
$GLOBALS['pending_fixture']->proposed_value = '{}';
check( 'malformed publication intent is not review-ready', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['pending_fixture']->proposed_value = '{"post_status":"publish"}';
$r = CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) );
check( 'bound draft and matching pending proposal verify', $r['record_integrity_pass'] && ! $r['publication_verified'] && 'requires_review' === $r['editorial_quality'] );
$GLOBALS['pending_fixture']->superseded_by = 99;
check( 'superseded publication is not review-ready', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
unset( $GLOBALS['pending_fixture']->superseded_by );
$GLOBALS['pending_fixture']->pre_check_baseline = json_encode( array( 'evidence' => array( 'environment_hash' => 'old-environment', 'posts' => array() ) ) );
check( 'stale approval environment cannot pass workflow verification', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['pending_fixture']->pre_check_baseline = json_encode( array( 'evidence' => array( 'environment_hash' => CC_Assistant_Evidence_Gate::environment_hash(), 'posts' => array( 901 => array( 'post_hash' => 'old-post' ) ) ) ) );
check( 'stale draft fingerprint cannot pass workflow verification', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['pending_fixture']->pre_check_baseline = $proof;
$bound_hash = CC_Assistant_Integrity::post_hash( 901 ); unset( $GLOBALS['meta'][901][CC_Assistant_Workflow_Verifier::META_KEY] );
check( 'workflow bookkeeping does not invalidate a queued content baseline', $bound_hash === CC_Assistant_Integrity::post_hash( 901 ) ); CC_Assistant_Workflow_Verifier::bind( $id, 901, 71 );
$GLOBALS['posts'][901]->post_content = '';
check( 'empty article cannot pass record integrity', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['posts'][901]->post_content = 'A saved article.'; $GLOBALS['pending_fixture']->post_id = 200;
check( 'mismatched pending proposal cannot verify', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['pending_fixture']->post_id = 901; $GLOBALS['pending_fixture']->status = 'rejected';
check( 'rejected proposal cannot be reported ready', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['pending_fixture']->status = 'pending'; $GLOBALS['posts'][901]->post_status = 'publish';
check( 'published status does not certify rendered success', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['publication_verified'] );
$GLOBALS['posts'][901]->post_status = 'draft'; $GLOBALS['posts'][100]->post_content .= ' Newly changed source fact.';
check( 'changed source blocks a stale workflow', 'workflow_sources_changed' === CC_Assistant_Workflow_Verifier::load( $id, true )->get_error_code() );
check( 'result does not hide changed source evidence', ! CC_Assistant_Workflow_Verifier::verify( array( 'workflow_id' => $id, 'post_id' => 901 ) )['record_integrity_pass'] );
$GLOBALS['actor'] = 99;
check( 'another actor cannot use workflow records', 'decision_not_found' === CC_Assistant_Workflow_Verifier::load( $id )->get_error_code() );
echo "All workflow result verification checks passed.\n";
