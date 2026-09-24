<?php
/** Real recovery/evidence/SQLite state transitions; workflow and quality providers are deterministic doubles. */
require __DIR__.'/fixtures/safety-harness.php';
define('REST_REQUEST',true);
function get_plugins() { return []; }
function get_site_option($key,$default=false) { return $default; }
function admin_url($path) { return 'https://fixture.test/wp-admin/'.$path; }
function get_preview_post_link($id) { return 'https://fixture.test/?p='.$id.'&preview=true'; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function get_locale() { return 'en_US'; }
class CC_Assistant_Content_Authors {
    public static function validate($id) { return empty($GLOBALS['author_fail']) ? ['id'=>$id,'display_name'=>'Fixture Organization'] : new WP_Error('content_author_unavailable'); }
}
class CC_Assistant_Workflow_Verifier {
    const META_KEY='_cc_assistant_content_workflow';
    public static function load($id,$creation=false) { return empty($GLOBALS['source_stale']) && $id===str_repeat('a',32) ? $GLOBALS['workflow'] : new WP_Error('workflow_sources_changed'); }
    public static function basis($record) { return ['current'=>empty($GLOBALS['source_stale'])]; }
    public static function bind($id,$post,$pending) {
        if (!empty($GLOBALS['binding_fail'])) return false;
        update_post_meta($post,self::META_KEY,['workflow_id'=>$id,'actor_id'=>17,'pending_id'=>$pending]);
        if (!empty($GLOBALS['claim_race'])) $GLOBALS['wpdb']->update('wp_cc_pending_changes',['status'=>'approved'],['id'=>$pending]);
        return true;
    }
    public static function verify($args) { return ['record_integrity_pass'=>true,'publication_verified'=>false]; }
}
class CC_Assistant_Pre_Publish {
    public static function evaluate_publish_gate($id) {
        $GLOBALS['gate_reads']++;
        if (!empty($GLOBALS['environment_drift'])) $GLOBALS['wp_version']='changed-during-check';
        if (!empty($GLOBALS['draft_drift'])) $GLOBALS['post']->post_content='Edited during check';
        return ['pass'=>empty($GLOBALS['gate_fail']),'blocking'=>empty($GLOBALS['gate_fail'])?[]:['placeholder text'],'warnings'=>[]];
    }
}
class PublicationDB extends ReviewDB {
    private $hide_next_read = false;
    public function query($sql) {
        if (!empty($GLOBALS['storage_fail']) && str_contains($sql,'SET pre_check_baseline =')) return false;
        if (!empty($GLOBALS['readback_fail']) && str_contains($sql,'SET pre_check_baseline =')) $this->hide_next_read = true;
        return parent::query($sql);
    }
    public function get_row($sql) {
        if ($this->hide_next_read) { $this->hide_next_read = false; return null; }
        return parent::get_row($sql);
    }
}
require CC_ASSISTANT_DIR.'includes/class-publication-recovery.php';
function publication_fixture() {
    reset_review();$GLOBALS['wpdb']=new PublicationDB();$GLOBALS['wp_version']='current';
    foreach (['source_stale','author_fail','binding_fail','claim_race','gate_fail','environment_drift','draft_drift','storage_fail','readback_fail','write_lock_busy'] as $key) $GLOBALS[$key]=false;
    $GLOBALS['gate_reads']=0;
    $id=pending('publish_draft');
    $GLOBALS['wpdb']->update('wp_cc_pending_changes',['proposed_value'=>'{"post_status":"publish"}','pre_check_baseline'=>'{"evidence":{"environment_hash":"old-environment","posts":{"1":{"post_hash":"old-post"}}}}','reasoning'=>'Original publication proposal'],['id'=>$id]);
    update_post_meta(1,CC_Assistant_Workflow_Verifier::META_KEY,['workflow_id'=>'old-workflow','actor_id'=>17,'pending_id'=>$id]);
    $evidence_id=CC_Assistant_Content_Evidence::snapshot(1)['evidence_id'];
    $GLOBALS['workflow']=['targets'=>[['post_id'=>1,'evidence_id'=>$evidence_id]],'source_basis'=>[['post_id'=>1,'evidence_id'=>$evidence_id]],'scope_revision'=>'current-scope','context'=>['context_hash'=>'current-context']];
    $key=new ReflectionMethod('CC_Assistant_Evidence_Gate','key');
    set_transient($key->invoke(null,'identity',0),['observed_at'=>time()],600);
    set_transient($key->invoke(null,'post',1),['observed_at'=>time(),'post_hash'=>CC_Assistant_Integrity::post_hash(1),'environment_hash'=>CC_Assistant_Evidence_Gate::environment_hash(),'kind'=>'editor'],600);
    return ['pending_id'=>$id,'workflow_id'=>str_repeat('a',32),'reason'=>'Reviewed the existing draft against current sources.'];
}
function refresh_fixture($args) { return CC_Assistant_Publication_Recovery::refresh($args); }

$args=publication_fixture();$before=CC_Assistant_Integrity::post_hash(1);$id=$args['pending_id'];
check(err(CC_Assistant_Evidence_Gate::validate_apply(CC_Assistant_Pending_Changes::get($id)),'environment_changed'),'Original publication proposal is stale');
$r=refresh_fixture($args);if(is_wp_error($r)) echo json_encode($r),"\n";
check(!is_wp_error($r) && $r['state']==='refreshed_awaiting_human_review','Fresh draft review refreshes the existing publication proposal');
check($r['pending_id']===$id && $GLOBALS['wpdb']->get_var('SELECT COUNT(*) FROM wp_cc_pending_changes')===1,'Recovery keeps the existing pending ID without duplicates');
check(!$GLOBALS['write_count'] && $GLOBALS['post']->post_status==='draft' && CC_Assistant_Integrity::post_hash(1)===$before,'Recovery never publishes or changes the author, title or body');
check($GLOBALS['gate_reads']===1 && !$r['publication_verified'],'Publication quality gate runs and recovery makes no publication claim');
$row=CC_Assistant_Pending_Changes::get($id);$baseline=CC_Assistant_Integrity::baseline($row);
check(CC_Assistant_Evidence_Gate::validate_apply($row)===true && !empty($baseline['publication_refresh_history'][0]['previous_baseline_sha256']),'Fresh server evidence and prior-basis audit history are saved');
$GLOBALS['source_stale']=true;
check(err(CC_Assistant_Evidence_Gate::validate_apply($row),'publication_sources_changed'),'Later source changes still block approval');

$args=publication_fixture();$before=serialize(CC_Assistant_Pending_Changes::get($args['pending_id']));$binding=get_post_meta(1,CC_Assistant_Workflow_Verifier::META_KEY,true);
$r=refresh_fixture($args+['dry_run'=>true]);
check(!is_wp_error($r) && $r['state']==='validated_not_refreshed' && $before===serialize(CC_Assistant_Pending_Changes::get($args['pending_id'])) && $binding===get_post_meta(1,CC_Assistant_Workflow_Verifier::META_KEY,true),'Dry run performs checks without replacing evidence or workflow binding');

foreach (['approved','applying','rejected','apply_failed'] as $status) {
    $args=publication_fixture();$GLOBALS['wpdb']->update('wp_cc_pending_changes',['status'=>$status],['id'=>$args['pending_id']]);
    check(err(refresh_fixture($args),'publication_refresh_not_pending'),'Reviewed or uncertain operation cannot be reset: '.$status);
}
$args=publication_fixture();$GLOBALS['wpdb']->update('wp_cc_pending_changes',['superseded_by'=>99],['id'=>$args['pending_id']]);
check(err(refresh_fixture($args),'publication_refresh_not_pending'),'Superseded publication cannot be revived');
$args=publication_fixture();$GLOBALS['post']->post_status='publish';check(err(refresh_fixture($args),'publication_refresh_not_draft'),'An already published post cannot enter draft recovery');
$args=publication_fixture();$GLOBALS['post']->post_content='';check(err(refresh_fixture($args),'publication_refresh_empty'),'Empty saved content cannot become ready for publication');
$args=publication_fixture();$GLOBALS['meta'][CC_Assistant_Workflow_Verifier::META_KEY][0]['actor_id']=99;
check(err(refresh_fixture($args),'publication_refresh_actor'),'Another actor cannot borrow the original draft binding');
$args=publication_fixture();$GLOBALS['workflow']['targets'][0]['post_id']=99;
check(err(refresh_fixture($args),'publication_refresh_target'),'A workflow for another draft cannot authorize this publication');
$args=publication_fixture();$GLOBALS['source_stale']=true;check(err(refresh_fixture($args),'workflow_sources_changed'),'Stale workflow is not refreshed blindly');
$args=publication_fixture();$GLOBALS['test_transients']=[];check(err(refresh_fixture($args),'evidence_identity_required'),'Missing identity receipt prevents refreshing');
$args=publication_fixture();$GLOBALS['post']->post_content='Changed after observation';check(err(refresh_fixture($args),'publication_refresh_target'),'Old draft observation cannot authorize changed content');
$args=publication_fixture();$GLOBALS['gate_fail']=true;check(err(refresh_fixture($args),'publish_gate_blocked'),'Quality findings block recovery without overrides');
$args=publication_fixture();$GLOBALS['meta']['_cc_publish_gate_override']=[1];check(err(refresh_fixture($args),'page_evidence_required'),'An override introduced after reading invalidates draft evidence');
$args=publication_fixture();$GLOBALS['author_fail']=true;check(err(refresh_fixture($args),'content_author_unavailable'),'Invalid attribution blocks publication recovery');
$args=publication_fixture();$GLOBALS['environment_drift']=true;check(err(refresh_fixture($args),'environment_changed'),'Environment drift during validation prevents refresh');
$args=publication_fixture();$GLOBALS['draft_drift']=true;check(err(refresh_fixture($args),'evidence_state_changed'),'Draft drift during validation prevents refresh');
$args=publication_fixture();$before=serialize(CC_Assistant_Pending_Changes::get($args['pending_id']));$GLOBALS['storage_fail']=true;
check(err(refresh_fixture($args),'publication_refresh_storage_failed') && $before===serialize(CC_Assistant_Pending_Changes::get($args['pending_id'])) && get_post_meta(1,CC_Assistant_Workflow_Verifier::META_KEY,true)['workflow_id']==='old-workflow','Storage failure preserves original evidence and restores old binding');
$args=publication_fixture();$GLOBALS['binding_fail']=true;check(err(refresh_fixture($args),'publication_refresh_binding_failed'),'Binding failure cannot report refreshed evidence');
$args=publication_fixture();$GLOBALS['claim_race']=true;
check(err(refresh_fixture($args),'publication_refresh_storage_failed') && CC_Assistant_Pending_Changes::get($args['pending_id'])->status==='approved','Conditional evidence write cannot reset a competing approval');
$args=publication_fixture();$GLOBALS['write_lock_busy']=true;check(err(refresh_fixture($args),'write_lock_unavailable'),'Publication refresh shares the approval lock');
$args=publication_fixture();$GLOBALS['readback_fail']=true;
check(err(refresh_fixture($args),'publication_refresh_storage_failed'),'A lost read-back never reports a confirmed refresh');
$saved=CC_Assistant_Integrity::baseline(CC_Assistant_Pending_Changes::get($args['pending_id']));
check($saved['publication_workflow_id']===get_post_meta(1,CC_Assistant_Workflow_Verifier::META_KEY,true)['workflow_id'],'A committed refresh keeps its matching new binding when read-back is temporarily unavailable');
echo "Publication recovery assertions: $tests\n";
