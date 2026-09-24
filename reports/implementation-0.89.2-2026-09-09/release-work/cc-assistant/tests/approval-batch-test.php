<?php
/** Real apply, evidence, persistence and recovery classes with an isolated WP/Elementor fixture. */
require __DIR__ . '/fixtures/safety-harness.php';
require_once CC_ASSISTANT_DIR . 'includes/class-approval-batch.php';
function get_plugins() { return array(); }
function get_site_option($key, $default = false) { return $default; }
function did_action($hook) { return 1; }
function apply_filters($hook, $value, ...$args) { return $value; }
class BatchElementorFixture {
    public $widgets_manager;
    public function __construct() { $this->widgets_manager = $this; }
    public static function instance() { static $instance; return $instance ?? ($instance = new self()); }
    public function get_widget_types($type) { return $this; }
    public function get_controls() { return array('title' => array('type' => 'text'), 'align' => array('type' => 'select', 'options' => array('left'=>'Left','right'=>'Right'))); }
    public function get_title() { return 'Fixture heading'; }
}
class_alias('BatchElementorFixture', 'Elementor\Plugin');
function batch_fixture() {
    reset_review(); $GLOBALS['wp_version']='fixture-1';
    $nodes=array();
    foreach (array('first','second','third','fourth') as $id) { $nodes[]=array('id'=>$id,'elType'=>'widget','widgetType'=>'heading','settings'=>array('title'=>$id,'align'=>'left'),'elements'=>array()); }
    $GLOBALS['meta']['_elementor_data']=array(json_encode(array(array('id'=>'outer','elType'=>'container','settings'=>array(),'elements'=>$nodes))));
    $GLOBALS['meta']['_elementor_edit_mode']=array('builder');
}
function batch_pending($widget, $title = null) {
    $id=pending('elementor_widget_update');
    $hash=CC_Assistant_Integrity::post_hash(1);
    $proof=array('environment_hash'=>CC_Assistant_Evidence_Gate::environment_hash(),'posts'=>array(1=>array('post_hash'=>$hash)));
    $GLOBALS['wpdb']->update('wp_cc_pending_changes',array('proposed_value'=>json_encode(array('widget_id'=>$widget,'settings'=>array('title'=>$title ?? 'Updated '.$widget))), 'current_value'=>'{}', 'pre_check_baseline'=>json_encode(array('post_hash'=>$hash,'evidence'=>$proof))),array('id'=>$id));
    return $id;
}
function batch_title($index) { return json_decode(get_post_meta(1,'_elementor_data',true),true)[0]['elements'][$index]['settings']['title']; }

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
$one=CC_Assistant_Apply::apply_pending($a,17,true);
$two=CC_Assistant_Apply::apply_pending($b,17,true);
check(!is_wp_error($one) && err($two,'evidence_state_changed'), 'Reproduce separate approvals becoming stale after the first widget edit');

batch_fixture(); $ids=array_map('batch_pending',array('first','second','third','fourth'));
$GLOBALS['meta']['_cc_hero_preload']=array('derived-image-cache');
$results=CC_Assistant_Apply::apply_pending_batch(array_merge($ids,array($ids[0])),17);
if (array_filter($results,'is_wp_error')) { echo json_encode($results), "\n"; }
check(count($results)===4 && !array_filter($results,'is_wp_error'), 'Four independent widgets apply in one selected batch, with duplicate IDs applied once');
check(batch_title(0)==='Updated first' && batch_title(3)==='Updated fourth', 'Actual saved Elementor tree contains every approved update');
foreach ($ids as $id) { $row=CC_Assistant_Pending_Changes::get($id); check($row->status==='approved' && !empty(CC_Assistant_Integrity::baseline($row)['snapshot_id']), 'Applied widget retains its own recovery snapshot #'.$id); }

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('first','Conflicting title');
$results=CC_Assistant_Apply::apply_pending_batch(array($a,$b),17);
check(!is_wp_error($results[$a]) && err($results[$b],'evidence_state_changed') && batch_title(0)==='Updated first', 'Overlapping proposals cannot overwrite an earlier approved widget');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
$GLOBALS['meta']['external']=array('edited before approval');
$results=CC_Assistant_Apply::apply_pending_batch(array($a,$b),17);
check(err($results[$a],'evidence_state_changed') && err($results[$b],'evidence_state_changed'), 'Already stale proposals receive no batch exception');
check(CC_Assistant_Pending_Changes::get($a)->status==='pending', 'Evidence rejection leaves the proposal unclaimed');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
$batch=new CC_Assistant_Approval_Batch(array($a,$b));
$one=CC_Assistant_Apply::apply_pending($a,17,true,$batch);
$GLOBALS['meta']['external']=array('same-second edit between applies');
$two=CC_Assistant_Apply::apply_pending($b,17,true,$batch);
check(!is_wp_error($one) && err($two,'evidence_state_changed') && batch_title(1)==='second', 'Same-second external edit between siblings still blocks the remaining write');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
$batch=new CC_Assistant_Approval_Batch(array($a,$b));
CC_Assistant_Apply::apply_pending($a,17,true,$batch); $GLOBALS['wp_version']='fixture-2';
$two=CC_Assistant_Apply::apply_pending($b,17,true,$batch);
check(err($two,'environment_changed'), 'Environment changes remain blocked even after a trusted sibling');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
$batch=new CC_Assistant_Approval_Batch(array($a,$b));
$prediction=$batch->predict(CC_Assistant_Pending_Changes::get($a));
$tree=json_decode(get_post_meta(1,'_elementor_data',true),true);
$tree[0]['elements'][0]['settings']['title']='Updated first';
$GLOBALS['meta']['_elementor_data']=array(json_encode($tree)); $GLOBALS['post']->post_title='Unexpected hook edit';
$batch->record_success($prediction);
$two=CC_Assistant_Apply::apply_pending($b,17,true,$batch);
check(err($two,'evidence_state_changed'), 'Unexpected side effects cannot become trusted evidence');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
$batch=new CC_Assistant_Approval_Batch(array($a,$b));
CC_Assistant_Apply::apply_pending($a,17,true,$batch);
$GLOBALS['wpdb']->update('wp_cc_pending_changes',array('proposed_value'=>'{"widget_id":"second","settings":{"title":"Changed after selection"}}'),array('id'=>$b));
$two=CC_Assistant_Apply::apply_pending($b,17,true,$batch);
check(err($two,'batch_proposal_changed'), 'A proposal altered after selection cannot borrow the batch authorization');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
$batch=new CC_Assistant_Approval_Batch(array($a));
CC_Assistant_Apply::apply_pending($a,17,true,$batch);
$two=CC_Assistant_Apply::apply_pending($b,17,true,$batch);
check(err($two,'batch_proposal_changed'), 'Unselected proposals cannot join a batch');
$two=CC_Assistant_Apply::apply_pending_batch(array($b),17);
check(err($two[$b],'evidence_state_changed'), 'Later requests cannot inherit a previous batch exception');

batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second'); $GLOBALS['wpdb']->fail_snapshot=true;
$results=CC_Assistant_Apply::apply_pending_batch(array($a,$b),17);
check(is_wp_error($results[$a]) && is_wp_error($results[$b]) && batch_title(0)==='first' && batch_title(1)==='second', 'Recovery failure prevents writes and cannot advance sibling evidence');
echo "Batch regression checks passed: $tests\n";
