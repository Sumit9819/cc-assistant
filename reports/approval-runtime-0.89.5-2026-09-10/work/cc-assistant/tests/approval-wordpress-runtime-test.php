<?php
/** Execute the installed WordPress core ping hooks with isolated storage and no network. */
require __DIR__ . '/approval-continuation-test.php';
$wp_root = getenv('CC_TEST_WORDPRESS_ROOT');
if (!$wp_root || !is_file($wp_root.'/wp-includes/post.php')) { throw new RuntimeException('CC_TEST_WORDPRESS_ROOT must identify the installed WordPress source.'); }
function load_core_runtime_function($file, $name) {
    $source = file_get_contents($file);
    if (!preg_match('/^function '.preg_quote($name,'/').'\(.*?^\}/ms', $source, $match)) { throw new RuntimeException('Core function missing: '.$name); }
    eval($match[0]); // Trusted installed core source, not a rewritten imitation of the hook.
}
load_core_runtime_function($wp_root.'/wp-includes/post.php', '_publish_post_hook');
foreach (array('do_all_pingbacks','do_all_enclosures','do_all_trackbacks') as $name) { load_core_runtime_function($wp_root.'/wp-includes/comment.php', $name); }
function get_to_ping($id) { return array('https://example.invalid/trackback'); }
function get_posts($args) { return isset($GLOBALS['meta'][$args['meta_key']]) ? array(1) : array(); }
function get_post_types() { return array('post'); }
function pingback($content,$id) {}
function do_enclose($content,$id) {}
function do_trackbacks($id) {}
function runtime_fixture() {
    batch_fixture();
    $GLOBALS['post']->post_status='publish';
    $GLOBALS['test_options']=array('default_pingback_flag'=>true);
    $GLOBALS['test_tables_present']=true;
    $GLOBALS['test_post_save_hook']=static function($post) { if($post->post_status==='publish' && $post->post_type==='post') { _publish_post_hook($post->ID); } };
}
function runtime_cleanup() { do_all_pingbacks(); do_all_enclosures(); do_all_trackbacks(); }
runtime_fixture();
$title=continuation_plan('meta_update',array('field'=>'post_title','value'=>'World Cup 2026 and Heart Health: What Research Shows'));
$excerpt=continuation_plan('meta_update',array('field'=>'post_excerpt','value'=>'What the research shows about heart health.'));
$seo_title=continuation_plan('postmeta_update',array('key'=>'rank_math_title','value'=>'World Cup 2026 and Heart Health: What Research Shows'));
$seo_description=continuation_plan('postmeta_update',array('key'=>'rank_math_description','value'=>'What the research shows about heart health.'));
check(!is_wp_error(continue_apply($title)), 'Published title save succeeds');
check(isset($GLOBALS['meta']['_pingme'],$GLOBALS['meta']['_encloseme'],$GLOBALS['meta']['_trackbackme']), 'Actual WordPress publish hook created all three runtime flags');
$result=continue_apply($excerpt);
check(!is_wp_error($result), 'Excerpt survives the WordPress flags added by the preceding title save: '.(is_wp_error($result)?$result->get_error_message():''));
runtime_cleanup();
check(!isset($GLOBALS['meta']['_pingme']) && !isset($GLOBALS['meta']['_encloseme']) && !isset($GLOBALS['meta']['_trackbackme']), 'Actual WordPress cron handlers removed runtime flags');
check(!is_wp_error(continue_apply($seo_title)) && !is_wp_error(continue_apply($seo_description)), 'Remaining SEO proposals survive cron cleanup between approvals');

// Exact incident compatibility: a 0.89.4 title approval stored flags in both
// its after-hash and the internal-save marker; its siblings predate that save.
foreach (array(false,true) as $flags_at_observation) {
    runtime_fixture();
    if ($flags_at_observation) { _publish_post_hook(1); }
    $title=continuation_plan('meta_update',array('field'=>'post_title','value'=>'Mundial 2026 y Salud Cardíaca: Qué Dice la Evidencia'));
    $excerpt=continuation_plan('meta_update',array('field'=>'post_excerpt','value'=>'Qué dice la investigación sobre la salud cardíaca.'));
    $legacy_before=CC_Assistant_Integrity::state_hash(CC_Assistant_Integrity::post_state(1),true);
    foreach (array($title,$excerpt) as $id) {
        $row=CC_Assistant_Pending_Changes::get($id); $b=CC_Assistant_Integrity::baseline($row);
        $b['post_hash']=$legacy_before; $b['evidence']['posts'][1]['post_hash']=$legacy_before;
        CC_Assistant_Recovery::save_baseline($id,$b);
    }
    runtime_cleanup();
    check(!is_wp_error(continue_apply($title)), 'Old receipt survives core cron before the first apply');
    $legacy_after=CC_Assistant_Integrity::state_hash(CC_Assistant_Integrity::post_state(1),true);
    $row=CC_Assistant_Pending_Changes::get($title); $b=CC_Assistant_Integrity::baseline($row); $b['applied_post_hash']=$legacy_after;
    CC_Assistant_Recovery::save_baseline($title,$b);
    update_post_meta(1,'_cc_assistant_last_internal_hash',$legacy_after);
    runtime_cleanup();
    $proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($excerpt),CC_Assistant_Integrity::post_hash(1));
    check($proof['safe'] && $proof['approved_ids']===array($title) && $proof['legacy_runtime_hash_matches']===array($title), 'Existing old snapshot/after-hash proves the title change after cron and names the runtime-only hash mismatch');
    check(!is_wp_error(continue_apply($excerpt)), 'Original old pending excerpt applies, including the second internal-hash guard');
}

runtime_fixture();
$state=CC_Assistant_Integrity::post_state(1); $stable=CC_Assistant_Integrity::state_hash($state);
foreach (CC_Assistant_Integrity::RUNTIME_META as $key) {
    update_post_meta(1,$key,'1');
    check(CC_Assistant_Integrity::post_hash(1)===$stable, 'Core runtime flag does not change the content fingerprint: '.$key);
}
foreach (array('rank_math_title','rank_math_description','rank_math_robots','custom_permalink','enclosure','_pingme_other','unrelated_plugin_setting') as $key) {
    $changed=$state; $changed['meta'][$key]=array('External change');
    check(!CC_Assistant_Integrity::hash_matches_state($stable,$changed), 'Real metadata stays protected: '.$key);
}
foreach (array('post_title','post_content','post_excerpt','post_author','post_name') as $field) {
    $changed=$state; $changed['fields'][$field]='External change';
    check(!CC_Assistant_Integrity::hash_matches_state($stable,$changed), 'Real post field stays protected: '.$field);
}
$changed=$state; $changed['terms']['category']=array(999);
check(!CC_Assistant_Integrity::hash_matches_state($stable,$changed), 'Category changes remain protected');

runtime_fixture();
$title=continuation_plan('meta_update',array('field'=>'post_title','value'=>'Approved title'));
$excerpt=continuation_plan('meta_update',array('field'=>'post_excerpt','value'=>'Approved excerpt'));
continue_apply($title); runtime_cleanup(); update_post_meta(1,'rank_math_robots','noindex');
$proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($excerpt),CC_Assistant_Integrity::post_hash(1));
check(!$proof['safe'] && $proof['reason']==='approved_history_does_not_reach_current_state' && in_array('rank_math_robots',$proof['comparison_to_current']['meta_keys'],true), 'Diagnostics name the actual unexpected SEO key without exposing its value');
check(err(continue_apply($excerpt),'evidence_state_changed'), 'Ignoring cron flags never hides a concurrent SEO edit');

runtime_fixture();
$title=continuation_plan('meta_update',array('field'=>'post_title','value'=>'Approved title'));
$excerpt=continuation_plan('meta_update',array('field'=>'post_excerpt','value'=>'Approved excerpt'));
$core_hook=$GLOBALS['test_post_save_hook'];
$GLOBALS['test_post_save_hook']=static function($post) use($core_hook) { $core_hook($post); update_post_meta(1,'plugin_hook_side_effect','unexpected'); };
continue_apply($title);
$proof=CC_Assistant_Approval_Continuation::prove(CC_Assistant_Pending_Changes::get($excerpt),CC_Assistant_Integrity::post_hash(1));
check(!$proof['safe'] && $proof['reason']==='approved_write_has_unexplained_side_effects' && in_array('plugin_hook_side_effect',$proof['comparison_to_current']['meta_keys'],true), 'Unrelated plugin save-hook changes remain blocked and diagnosable');
echo "WordPress runtime regression checks passed: $tests\n";
