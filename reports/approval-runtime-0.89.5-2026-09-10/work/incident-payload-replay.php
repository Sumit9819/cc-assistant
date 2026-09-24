<?php
require __DIR__.'/cc-assistant/tests/approval-wordpress-runtime-test.php';
$incident=json_decode(file_get_contents(__DIR__.'/initial-live-evidence.json'),true);
$earlier=json_decode(file_get_contents('D:/cc-assistant/reports/apply-recovery-0.89.4-2026-09-10/post-install/final-verification.json'),true);
$observations=json_decode(file_get_contents('D:/cc-assistant/reports/apply-recovery-0.89.4-2026-09-10/post-install/native-verification.json'),true);
$cases=0;
foreach(array(5092=>1813,5122=>1817) as $pid=>$title_id) {
    runtime_fixture(); unset($GLOBALS['meta']['_elementor_data'],$GLOBALS['meta']['_elementor_edit_mode']);
    $before=null; foreach($observations['posts'] as $p) { if($p['id']===$pid) { $before=$p; } }
    foreach(array('post_title'=>'title','post_content'=>'content','post_excerpt'=>'excerpt','post_author'=>'author_id','post_name'=>'slug','post_status'=>'status') as $field=>$source) { $GLOBALS['post']->$field=$before[$source]; }
    $rows=array(); foreach($earlier['pending']['pending'] as $r) { if((int)$r['post_id']===$pid) { $rows[(int)$r['id']]=$r; } } ksort($rows);
    foreach($rows as $r) { if($r['change_type']==='postmeta_update') { $p=json_decode($r['current_value'],true); $GLOBALS['meta'][$p['key']]=array($p['value']); } }
    $legacy_before=CC_Assistant_Integrity::state_hash(CC_Assistant_Integrity::post_state(1),true); $queued=array();
    foreach($rows as $original_id=>$r) {
        $id=continuation_plan($r['change_type'],json_decode($r['proposed_value'],true)); $queued[$original_id]=$id;
        $b=CC_Assistant_Integrity::baseline(CC_Assistant_Pending_Changes::get($id)); $b['post_hash']=$legacy_before; $b['evidence']['posts'][1]['post_hash']=$legacy_before; CC_Assistant_Recovery::save_baseline($id,$b);
    }
    check(!is_wp_error(continue_apply($queued[$title_id])), 'Incident title #'.$title_id.' saves with actual WordPress publish hook');
    $b=CC_Assistant_Integrity::baseline(CC_Assistant_Pending_Changes::get($queued[$title_id]));
    $b['applied_post_hash']=CC_Assistant_Integrity::state_hash(CC_Assistant_Integrity::post_state(1),true); CC_Assistant_Recovery::save_baseline($queued[$title_id],$b); update_post_meta(1,'_cc_assistant_last_internal_hash',$b['applied_post_hash']);
    foreach($rows as $original_id=>$r) {
        if($original_id===$title_id) { continue; }
        runtime_cleanup(); $result=continue_apply($queued[$original_id]);
        check(!is_wp_error($result), 'Existing incident payload #'.$original_id.' applies after title and cron without requeue');
        $p=json_decode($r['proposed_value'],true); $stored=$r['change_type']==='meta_update'?get_post_field($p['field'],1):get_post_meta(1,$p['key'],true);
        check($stored===$p['value'], 'Exact intended value retained for #'.$original_id); ++$cases;
    }
}
check($cases===6,'All six actual remaining proposal payloads covered');
echo "Incident payload replay passed: $cases remaining proposals\n";
