<?php
require __DIR__.'/fixtures/safety-harness.php';
foreach ([-18000,20700] as $offset) {
    reset_review($offset);
    $created=current_time('mysql');
    $id=pending('post_content_update',$created);
    $r=CC_Assistant_Apply::apply_pending($id,17,true);
    check(!is_wp_error($r) && $GLOBALS['post']->post_content==='New body', 'Legacy queue time applies correctly at offset '.$offset);
    reset_review($offset); $id=pending('post_content_update',current_time('mysql'));
    $GLOBALS['post']->post_modified_gmt='2026-09-08 12:30:00';
    $r=CC_Assistant_Apply::apply_pending($id,17,true);
    check(err($r,'post_modified_conflict') && !$GLOBALS['write_count'],'Later edit blocks apply at offset '.$offset);
    reset_review($offset);
    $id=CC_Assistant_Pending_Changes::queue(['post_id'=>null,'change_type'=>'rewrite_outline','proposed_value'=>'{}']);
    $row=CC_Assistant_Pending_Changes::get($id);
    check(CC_Assistant_Integrity::queued_timestamp($row)===strtotime('2026-09-08 12:00:00 UTC'),'New queue stores explicit UTC at offset '.$offset);
}
reset_review(); $id=pending(); $GLOBALS['wpdb']->fail_snapshot=true;
$r=CC_Assistant_Apply::apply_pending($id,17,true);
check(err($r,'snapshot_failed') && !$GLOBALS['write_count'] && CC_Assistant_Pending_Changes::get($id)->status==='apply_failed','Failed snapshot prevents content write and false approval');
reset_review(); $id=pending(); $GLOBALS['stop_on_update']=true;
$r=CC_Assistant_Apply::apply_pending($id,17,true);
check(err($r,'apply_interrupted') && CC_Assistant_Pending_Changes::get($id)->status==='apply_failed','Interrupted content write records failure');
check(!empty(CC_Assistant_Integrity::baseline(CC_Assistant_Pending_Changes::get($id))['snapshot_id']),'Recovery pointer is durable before write begins');
reset_review(); $id=pending();
check(CC_Assistant_Pending_Changes::claim_for_apply($id,17),'First approval claims pending');
check(!CC_Assistant_Pending_Changes::claim_for_apply($id,18),'Second approval cannot claim pending');
CC_Assistant_Pending_Changes::reject($id,18,'Race');
check(CC_Assistant_Pending_Changes::get($id)->status==='applying','Concurrent reject cannot overwrite active approval');
$r=CC_Assistant_Apply::apply_pending($id,18,true);
check(err($r,'not_pending') && CC_Assistant_Pending_Changes::get($id)->status==='applying','Losing apply request cannot fail another reviewer operation');
check((int)CC_Assistant_Pending_Changes::get($id)->reviewed_by===17,'Reviewer numeric ID retained');
reset_review(); $id=pending();
$b=['queued_at_gmt'=>current_time('mysql',true),'post_hash'=>CC_Assistant_Integrity::post_hash(1)];
$GLOBALS['wpdb']->update('wp_cc_pending_changes',['pre_check_baseline'=>json_encode($b)],['id'=>$id]);
$GLOBALS['meta']['_elementor_data']=['[{"id":"external-same-second"}]'];
$r=CC_Assistant_Apply::apply_pending($id,17,true);
check(err($r,'post_modified_conflict') && !$GLOBALS['write_count'],'Same-second metadata-only external edit detected by state hash');
reset_review(); $before=CC_Assistant_Integrity::post_hash(1); $GLOBALS['terms']['category']=[9];
check($before!==CC_Assistant_Integrity::post_hash(1),'Taxonomy changes participate in conflict fingerprint');
reset_review();
$method=new ReflectionMethod('CC_Assistant_Apply','apply_post_meta');
$value=json_encode(['@type'=>'Article','headline'=>'A "quoted" title','path'=>'C:\file']);
$r=$method->invoke(null,1,['key'=>'_cc_assistant_schema_jsonld','value'=>$value]);
check($r && get_post_meta(1,'_cc_assistant_schema_jsonld',true)===$value,'JSON and backslashes survive metadata writes');
$GLOBALS['meta']['_elementor_data']=[$value];
$snap=CC_Assistant_Snapshots::snapshot_post(1);
$GLOBALS['post']->post_title='Changed'; $GLOBALS['post']->post_name='changed';
$GLOBALS['post']->post_status='publish'; $GLOBALS['post']->post_author=22; $GLOBALS['post']->post_excerpt='New';
$GLOBALS['meta']['_cc_new_schema']=['introduced']; $GLOBALS['meta']['other_plugin_new_key']=['keep'];
$GLOBALS['meta']['_elementor_data']=['changed']; $GLOBALS['terms']['category']=[9];
$r=CC_Assistant_Snapshots::restore_snapshot($snap,true);
check(!is_wp_error($r) && $GLOBALS['post']->post_name==='original' && $GLOBALS['post']->post_status==='draft' && $GLOBALS['post']->post_author==17 && $GLOBALS['post']->post_excerpt==='Original excerpt','Snapshot restores slug, status, author and excerpt');
check(!isset($GLOBALS['meta']['_cc_new_schema']) && isset($GLOBALS['meta']['other_plugin_new_key']),'Snapshot removes introduced plugin keys and preserves unrelated new metadata');
check(get_post_meta(1,'_elementor_data',true)===$value && $GLOBALS['terms']['category']===[2],'Snapshot restores escaped Elementor data and taxonomy');
reset_review(); $snap=CC_Assistant_Snapshots::snapshot_post(1); $GLOBALS['wpdb']->fail_snapshot=true;
$r=CC_Assistant_Snapshots::restore_snapshot($snap,true);
check(err($r,'snapshot_failed') && !$GLOBALS['write_count'],'Restore stops if its own recovery snapshot fails');
foreach (['category_update','elementor_full_import','elementor_section_content_replace','emergency_service_schema','trash_post'] as $type) {
    reset_review(); $id=pending($type,current_time('mysql'),'approved');
    $snap=CC_Assistant_Snapshots::snapshot_post(1);
    $GLOBALS['post']->post_content='Applied'; $GLOBALS['post']->post_status='trash'; $GLOBALS['terms']['category']=[9];
    CC_Assistant_Recovery::save_baseline($id,['snapshot_id'=>$snap,'applied_post_hash'=>CC_Assistant_Integrity::post_hash(1)]);
    $r=CC_Assistant_Apply::rollback_pending($id,17);
    check(!is_wp_error($r) && $GLOBALS['post']->post_content==='Original body' && $GLOBALS['post']->post_status==='draft' && $GLOBALS['terms']['category']===[2] && CC_Assistant_Pending_Changes::get($id)->status==='rolled_back','Recovery rollback implemented: '.$type);
}
reset_review(); $id=pending('trash_post',current_time('mysql'),'approved');
$snap=CC_Assistant_Snapshots::snapshot_post(1);
CC_Assistant_Recovery::save_baseline($id,['snapshot_id'=>$snap,'applied_post_hash'=>CC_Assistant_Integrity::post_hash(1)]);
$GLOBALS['post']->post_content='Later human edit';
$r=CC_Assistant_Apply::rollback_pending($id,17);
check(err($r,'rollback_conflict') && !$GLOBALS['write_count'],'Rollback refuses to overwrite later edits');
$method=new ReflectionMethod('CC_Assistant_Win_Audit','guard_external_url');
$r=$method->invoke(null,'http://[::1]:8080/');
check(is_wp_error($r),'SSRF guard rejects IPv6 loopback without a network request');
require_once CC_ASSISTANT_DIR.'includes/class-access.php';
function current_user_can($cap) { return in_array($cap,$GLOBALS['caps']); }
function rest_get_authenticated_app_password() { return $GLOBALS['app_password']; }
function wp_verify_nonce($nonce,$action) { return $nonce==='valid'; }
class TestRequest {
 public function __construct(public $route='/cc-assistant/v1/pending/1/decide',public $nonce='valid') {}
 public function get_header($name) { return $this->nonce; }
 public function get_route() { return $this->route; }
}
$GLOBALS['caps']=['manage_options']; $GLOBALS['app_password']='application-password-uuid';
check(err(CC_Assistant_Access::can_review(new TestRequest()),'human_review_required'),'Admin Application Password cannot approve even with a nonce');
$GLOBALS['app_password']=null;
check(CC_Assistant_Access::can_review(new TestRequest())===true,'Interactive administrator with valid nonce can review');
check(err(CC_Assistant_Access::can_review(new TestRequest('','invalid')),'human_review_required'),'Administrator without review nonce cannot approve');
$GLOBALS['caps']=['cc_assistant_use'];
check(CC_Assistant_Access::can_use() && is_wp_error(CC_Assistant_Access::can_review(new TestRequest())),'Operator can use plugin but cannot review');
check(err(CC_Assistant_Access::scope_operator(null,null,new TestRequest('/wp/v2/posts')),'operator_scope'),'Operator cannot use core REST write routes');
check(CC_Assistant_Access::scope_operator(null,null,new TestRequest('/cc-assistant/v1/whoami'))===null,'Operator can call plugin routes');
function register_rest_route($namespace,$route,$args) { $GLOBALS['registered_routes'][$route]=$args; }
require_once CC_ASSISTANT_DIR.'includes/class-rest-api.php';
require_once CC_ASSISTANT_DIR.'includes/class-rest-pending.php';
CC_Assistant_REST_API::register_routes();
CC_Assistant_REST_Pending::register_routes();
$GLOBALS['caps']=['manage_options']; $GLOBALS['app_password']='application-password-uuid';
foreach(array('/pending/(?P<id>\d+)/apply','/pending/(?P<id>\d+)/decide') as $route) {
	$callback=$GLOBALS['registered_routes'][$route]['permission_callback'];
	check(err(call_user_func($callback,new TestRequest()),'human_review_required'),'Registered review endpoint rejects automation: '.$route);
}
require_once CC_ASSISTANT_DIR.'includes/class-deactivator.php';
function _get_cron_array() {
	return array(123=>array('cc_assistant_gsc_sync'=>array(),'other_plugin_sync'=>array()),
		456=>array('cc_assistant_verify_post'=>array('args'=>array(5)),'cc_assistant_gsc_sync'=>array()));
}
function wp_unschedule_hook($hook) { $GLOBALS['unscheduled'][]=$hook; }
CC_Assistant_Deactivator::deactivate();
check($GLOBALS['unscheduled']===array('cc_assistant_gsc_sync','cc_assistant_verify_post'),'Deactivation removes all owned hooks including argument-bearing jobs and leaves other plugins alone');
echo "$tests regression checks passed.\n";
