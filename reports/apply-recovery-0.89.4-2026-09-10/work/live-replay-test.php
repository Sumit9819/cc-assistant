<?php
// Real reviewed payloads in an isolated fixture. This does not read production snapshots or apply remotely.
require __DIR__.'/cc-assistant/tests/approval-continuation-test.php';
$fixtures=json_decode(file_get_contents(__DIR__.'/live-replay-fixtures.json'),true);
$count=0;
foreach($fixtures as $fixture) {
    batch_fixture(); $before=$fixture['before'];
    foreach(array('post_title'=>'title','post_content'=>'content','post_excerpt'=>'excerpt','post_name'=>'slug','post_status'=>'status','post_author'=>'author_id') as $wp=>$key) $GLOBALS['post']->$wp=$before[$key]??'';
    $GLOBALS['meta']=array();
    if(!empty($before['elementor_data'])) { $GLOBALS['meta']['_elementor_data']=array(wp_json_encode($before['elementor_data'])); $GLOBALS['meta']['_elementor_edit_mode']=array('builder'); }
    foreach($fixture['pending'] as $row) {
        if($row['change_type']!=='postmeta_update')continue;
        $v=json_decode($row['current_value'],true); $GLOBALS['meta'][$v['key']]=array((string)$v['value']);
    }
    $hash=CC_Assistant_Integrity::post_hash(1);
    $proof=array('environment_hash'=>CC_Assistant_Evidence_Gate::environment_hash(),'posts'=>array(1=>array('post_hash'=>$hash)));
    foreach(array_merge($fixture['approved'],$fixture['pending']) as $r) {
        $row=array_intersect_key($r,array_flip(array('id','change_type','change_summary','proposed_value','current_value')));
        $row['post_id']=1; $row['status']='pending'; $row['created_at']='2026-09-08 12:00:00'; $row['superseded_by']=null;
        $row['pre_check_baseline']=wp_json_encode(array('post_hash'=>$hash,'evidence'=>$proof));
        $GLOBALS['wpdb']->insert('wp_cc_pending_changes',$row);
    }
    foreach($fixture['approved'] as $r) {
        $row=CC_Assistant_Pending_Changes::get($r['id']);
        $sid=CC_Assistant_Snapshots::snapshot_post(1,'pre_apply','Local replay fixture');
        $state=CC_Assistant_Snapshots::state(CC_Assistant_Snapshots::get_snapshot($sid));
        $after=CC_Assistant_Approval_Continuation::replay($row,$state);
        check(is_array($after),'Real approved payload can be replayed #'.$r['id']);
        foreach($after['fields'] as $k=>$v) $GLOBALS['post']->$k=$v;
        $GLOBALS['meta']=$after['meta'];
        $b=CC_Assistant_Integrity::baseline($row); $b['snapshot_id']=$sid; $b['applied_post_hash']=CC_Assistant_Integrity::post_hash(1);
        CC_Assistant_Recovery::save_baseline($row->id,$b);
        $GLOBALS['wpdb']->update('wp_cc_pending_changes',array('status'=>'approved','reviewed_at'=>'2026-09-08 12:00:00'),array('id'=>$row->id));
    }
    foreach($fixture['pending'] as $r) {
        $row=CC_Assistant_Pending_Changes::get($r['id']);
        $chain=CC_Assistant_Approval_Continuation::prove($row,CC_Assistant_Integrity::post_hash(1));
        check($chain['safe'],'Existing pending #'.$r['id'].' can continue through its actual approved payload');
        ++$count;
    }
}
check($count===28,'All 28 incident proposals exercised against their related real approved payloads');
echo "Live-payload replay cases: $count across ".count($fixtures)." targets. Production full-metadata snapshot proof remains an install-time check.\n";
