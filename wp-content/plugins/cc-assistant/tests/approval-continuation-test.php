<?php
require __DIR__ . '/approval-batch-test.php';
require_once CC_ASSISTANT_DIR . 'includes/class-approval-continuation.php';
function get_user_by($field,$value) { return $value===4 ? (object)array('ID'=>4) : false; }
function continuation_plan($type,$payload) {
    $id=pending($type); $hash=CC_Assistant_Integrity::post_hash(1);
    $e=array('environment_hash'=>CC_Assistant_Evidence_Gate::environment_hash(),'posts'=>array(1=>array('post_hash'=>$hash)));
    $GLOBALS['wpdb']->update('wp_cc_pending_changes',array('proposed_value'=>wp_json_encode($payload),'pre_check_baseline'=>wp_json_encode(array('post_hash'=>$hash,'evidence'=>$e))),array('id'=>$id));
    return $id;
}
function continue_apply($id) { return CC_Assistant_Apply::apply_pending($id,17,true); }

batch_fixture();
$section=continuation_plan('elementor_section_content_replace',array('section_id'=>'outer','widget_updates'=>array(array('widget_id'=>'first','settings'=>array('title'=>'Corrected medical wording')),array('widget_id'=>'second','settings'=>array('align'=>'right')))));
$title=continuation_plan('meta_update',array('field'=>'post_title','value'=>'Updated imaging title'));
$seo=continuation_plan('postmeta_update',array('key'=>'rank_math_title','value'=>'Accurate imaging SEO title'));
$author=continuation_plan('meta_update',array('field'=>'post_author','value'=>'4'));
foreach(array($section,$title,$seo,$author) as $id) { $r=continue_apply($id); check(!is_wp_error($r),'Section, title, SEO and author survive separate requests #'.$id.': '.(is_wp_error($r)?$r->get_error_message():'')); }
check(batch_title(0)==='Corrected medical wording' && get_post_field('post_title',1)==='Updated imaging title' && get_post_meta(1,'rank_math_title',true)==='Accurate imaging SEO title' && (int)get_post_field('post_author',1)===4,'Mixed edits all persist without replacing prior content');

batch_fixture(); $ids=array();
$ids[]=continuation_plan('post_content_update',array('content'=>'Revised article body'));
$ids[]=continuation_plan('meta_update',array('field'=>'post_title','value'=>'Revised article title'));
$ids[]=continuation_plan('meta_update',array('field'=>'post_excerpt','value'=>'Revised excerpt'));
$ids[]=continuation_plan('postmeta_update',array('key'=>'rank_math_title','value'=>'Revised SEO title'));
$ids[]=continuation_plan('postmeta_update',array('key'=>'rank_math_description','value'=>'Revised SEO description'));
$result=CC_Assistant_Apply::apply_pending_batch($ids,17);
check(!array_filter($result,'is_wp_error'),'Article body, title, excerpt and both SEO fields apply together');
check(get_post_field('post_content',1)==='Revised article body' && get_post_field('post_excerpt',1)==='Revised excerpt','Article revisions remain intact');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second'); $c=batch_pending('third');
continue_apply($b); continue_apply($a);
$proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($c),CC_Assistant_Integrity::post_hash(1));
check($proof['safe'] && $proof['approved_ids']===array($b,$a),'Chain follows actual snapshot/application order, not proposal creation order');
check(!is_wp_error(continue_apply($c)),'A multi-step chain works after separate approval requests');

batch_fixture(); $a=continuation_plan('elementor_section_content_replace',array('widget_updates'=>array(array('widget_id'=>'first','settings'=>array('title'=>'First change')))));
$b=batch_pending('first','Overwrites first change'); continue_apply($a);
check(err(continue_apply($b),'evidence_state_changed'),'Section and individual-widget overlap remains blocked');

batch_fixture(); $a=continuation_plan('postmeta_update',array('key'=>'rank_math_description','value'=>'First description'));
$b=continuation_plan('postmeta_update',array('key'=>'rank_math_description','value'=>'Competing description')); continue_apply($a);
check(err(continue_apply($b),'evidence_state_changed'),'Competing writes to the same SEO key remain blocked');

batch_fixture(); $a=batch_pending('first'); $b=continuation_plan('meta_update',array('field'=>'post_name','value'=>'new-slug')); continue_apply($a);
check(err(continue_apply($b),'evidence_state_changed'),'Structural permalink change receives no continuation exception');

foreach(array('missing_snapshot','missing_after','wrong_post','legacy_snapshot','not_approved') as $case) {
    batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second'); continue_apply($a);
    $row=CC_Assistant_Pending_Changes::get($a); $base=CC_Assistant_Integrity::baseline($row); $sid=$base['snapshot_id'];
    if($case==='missing_snapshot') { $GLOBALS['wpdb']->query('DELETE FROM wp_cc_snapshots WHERE id='.(int)$sid); }
    if($case==='missing_after') { unset($base['applied_post_hash']); CC_Assistant_Recovery::save_baseline($a,$base); }
    if($case==='wrong_post') { $GLOBALS['wpdb']->update('wp_cc_snapshots',array('post_id'=>2),array('id'=>$sid)); }
    if($case==='legacy_snapshot') { $GLOBALS['wpdb']->update('wp_cc_snapshots',array('post_meta'=>'a:0:{}'),array('id'=>$sid)); }
    if($case==='not_approved') { $GLOBALS['wpdb']->update('wp_cc_pending_changes',array('status'=>'apply_failed'),array('id'=>$a)); }
    check(err(continue_apply($b),'evidence_state_changed'),'Incomplete or unapproved recovery history fails closed: '.$case);
}

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second'); continue_apply($a);
$GLOBALS['meta']['external']=array('Unrelated external edit');
$c=continuation_plan('postmeta_update',array('key'=>'rank_math_title','value'=>'Later fresh edit')); continue_apply($c);
check(err(continue_apply($b),'evidence_state_changed'),'A later legitimate approval cannot conceal an earlier external edit');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second'); continue_apply($a);
$GLOBALS['wpdb']->update('wp_cc_pending_changes',array('proposed_value'=>'{"widget_id":"third","settings":{"title":"Tampered history"}}'),array('id'=>$a));
check(err(continue_apply($b),'evidence_state_changed'),'Altered approved payload cannot prove the recorded state transition');

batch_fixture(); $snapshot=CC_Assistant_Snapshots::snapshot_post(1,'pre_apply','Fingerprint fixture');
$state=CC_Assistant_Snapshots::state(CC_Assistant_Snapshots::get_snapshot($snapshot));
check(CC_Assistant_Integrity::state_hash($state)===CC_Assistant_Integrity::post_hash(1),'Recovery snapshot and live state use identical stable fingerprints');
// 0.90.1: a new post approved in one batch with its own SEO title and description.
function continuation_approved_publish($status_after, $slug_after = null) {
    $pub=continuation_plan('publish_draft',array('post_status'=>'publish'));
    $sid=CC_Assistant_Snapshots::snapshot_post(1,'pre_apply','Pending #'.$pub.' (publish_draft)');
    $GLOBALS['post']->post_status=$status_after;
    if($slug_after!==null) { $GLOBALS['post']->post_name=$slug_after; }
    $base=CC_Assistant_Integrity::baseline(CC_Assistant_Pending_Changes::get($pub));
    $base['snapshot_id']=$sid; $base['applied_post_hash']=CC_Assistant_Integrity::post_hash(1);
    CC_Assistant_Recovery::save_baseline($pub,$base);
    $GLOBALS['wpdb']->update('wp_cc_pending_changes',array('status'=>'approved','reviewed_at'=>'2026-09-08 12:00:00'),array('id'=>$pub));
    return $pub;
}

batch_fixture(); $GLOBALS['post']->post_status='draft';
$pub=continuation_plan('publish_draft',array('post_status'=>'publish'));
$t=continuation_plan('postmeta_update',array('key'=>'rank_math_title','value'=>'New post SEO title'));
$d=continuation_plan('postmeta_update',array('key'=>'rank_math_description','value'=>'New post SEO description'));
continue_apply($t); continue_apply($d);
$proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($pub),CC_Assistant_Integrity::post_hash(1));
check($proof['safe'] && $proof['approved_ids']===array($t,$d),'Publish continues after its own approved SEO title and description');
check(!is_wp_error(CC_Assistant_Evidence_Gate::validate_apply(CC_Assistant_Pending_Changes::get($pub))),'Publish passes the apply-time evidence gate after SEO siblings');

batch_fixture(); $GLOBALS['post']->post_status='draft';
$pub=continuation_plan('publish_draft',array('post_status'=>'publish'));
$body=continuation_plan('post_content_update',array('content'=>'Body rewritten after review'));
continue_apply($body);
$proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($pub),CC_Assistant_Integrity::post_hash(1));
check(!$proof['safe'] && $proof['reason']==='overlapping_approved_change','A body change between review and publish still blocks publication');

batch_fixture(); $GLOBALS['post']->post_status='draft';
$meta=continuation_plan('postmeta_update',array('key'=>'rank_math_title','value'=>'Title queued while draft'));
$pub=continuation_approved_publish('publish');
$proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($meta),CC_Assistant_Integrity::post_hash(1));
check($proof['safe'] && $proof['approved_ids']===array($pub),'SEO title continues after its post was published first');

batch_fixture(); $GLOBALS['post']->post_status='draft';
$meta=continuation_plan('postmeta_update',array('key'=>'rank_math_title','value'=>'Title queued while draft'));
continuation_approved_publish('publish','slug-generated-on-publish');
$proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($meta),CC_Assistant_Integrity::post_hash(1));
check(!$proof['safe'] && $proof['reason']==='approved_write_has_unexplained_side_effects','A publish with any side effect beyond post_status fails closed');

echo "Continuation regression checks passed: $tests\n";
