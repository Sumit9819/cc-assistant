<?php
define('ABSPATH',__DIR__.'/');
define('CC_ASSISTANT_VERSION','0.89.5');
define('CC_ASSISTANT_BASENAME','cc-assistant/cc-assistant.php');
function wp_json_encode($v) { return json_encode($v); }
function get_plugins() { return $GLOBALS['plugins']; }
function get_option($k,$d=false) { return $GLOBALS['options'][$k]??$d; }
function get_site_option($k,$d=false) { return $d; }
function get_post_meta($id,$key,$single) { return $GLOBALS['kit']; }
function wp_get_theme($name) { return new class { public function get($key) { return $GLOBALS['theme_version']; } }; }
require __DIR__.'/../includes/class-evidence-gate.php';
$tests=0;
function check($ok,$message) { global $tests; ++$tests; if(!$ok) { fwrite(STDERR,"FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
function env_fixture() {
    $GLOBALS['wp_version']='7.1'; $GLOBALS['theme_version']='1.0'; $GLOBALS['kit']=array('color'=>'blue');
    $GLOBALS['plugins']=array(CC_ASSISTANT_BASENAME=>array('Version'=>'0.89.3'),'elementor/elementor.php'=>array('Version'=>'4.2.3'),'ctx-feed/ctx-feed.php'=>array('Version'=>'8.0.21'));
    $GLOBALS['options']=array('active_plugins'=>array_keys($GLOBALS['plugins']),'elementor_active_kit'=>10,'stylesheet'=>'theme','template'=>'theme','rank-math-options-titles'=>array('robots'=>array('index')));
    $hash=CC_Assistant_Evidence_Gate::environment_material_hash();
    $GLOBALS['plugins'][CC_ASSISTANT_BASENAME]['Version']='0.89.5';
    return $hash;
}
$old=env_fixture();
check(CC_Assistant_Evidence_Gate::environment_matches(CC_Assistant_Evidence_Gate::environment_material_hash('0.89.4')),'0.89.4 proposals survive this specific guard-only upgrade');
check(CC_Assistant_Evidence_Gate::environment_material_hash()!==$old && CC_Assistant_Evidence_Gate::environment_matches($old),'Only the tested CC Assistant patch upgrade preserves prior environment evidence');
check(CC_Assistant_Evidence_Gate::environment_matches(CC_Assistant_Evidence_Gate::environment_material_hash()),'New proposals still accept the exact current environment');
foreach(array('plugin','activation','wp','theme','kit','seo','url') as $change) {
    $old=env_fixture();
    if($change==='plugin') $GLOBALS['plugins']['elementor/elementor.php']['Version']='4.2.4';
    if($change==='activation') $GLOBALS['options']['active_plugins']=array(CC_ASSISTANT_BASENAME);
    if($change==='wp') $GLOBALS['wp_version']='7.2';
    if($change==='theme') $GLOBALS['theme_version']='2.0';
    if($change==='kit') $GLOBALS['kit']['color']='red';
    if($change==='seo') $GLOBALS['options']['rank-math-options-titles']['robots']=array('noindex');
    if($change==='url') $GLOBALS['options']['home']='https://different-site.example';
    check(!CC_Assistant_Evidence_Gate::environment_matches($old),'CC patch compatibility does not conceal a changed '.$change);
}
env_fixture(); $older=CC_Assistant_Evidence_Gate::environment_material_hash('0.89.2');
check(!CC_Assistant_Evidence_Gate::environment_matches($older),'Unreviewed older plugin upgrades remain blocked');
check(!CC_Assistant_Evidence_Gate::environment_matches('') && !CC_Assistant_Evidence_Gate::environment_matches(null),'Missing environment evidence cannot borrow upgrade compatibility');

// 0.90.0. The fingerprint used to hash the version of EVERY active plugin, so
// one unrelated plugin updating itself overnight invalidated every queued
// change: 20 product descriptions on sids-ponds died to CTX Feed 8.0.21->8.0.22.
$old=env_fixture(); $components=CC_Assistant_Evidence_Gate::environment_components();
$GLOBALS['plugins']['ctx-feed/ctx-feed.php']['Version']='8.0.22';
check(CC_Assistant_Evidence_Gate::environment_matches($old),'An unrelated plugin auto-update no longer invalidates a queued plan');
check(CC_Assistant_Evidence_Gate::environment_components()['active_plugins']===$components['active_plugins'],'The active plugin set is version-independent');
$GLOBALS['options']['active_plugins']=array(CC_ASSISTANT_BASENAME,'elementor/elementor.php');
check(!CC_Assistant_Evidence_Gate::environment_matches($old),'Deactivating that same plugin still blocks');

// Both sides of the material boundary, and the diagnostics a refusal carries.
$old=env_fixture();
$GLOBALS['plugins']['elementor/elementor.php']['Version']='4.2.4';
check(!CC_Assistant_Evidence_Gate::environment_matches($old),'A page builder version change still blocks');
$evidence=array('environment_components'=>$components,'environment_material_plugins'=>array(CC_ASSISTANT_BASENAME=>'0.89.3','elementor/elementor.php'=>'4.2.3'));
check(CC_Assistant_Evidence_Gate::environment_drift($evidence)===array('material_plugins'),'The refusal names the component that moved');
$drift=CC_Assistant_Evidence_Gate::material_plugin_drift($evidence);
check(($drift['elementor/elementor.php']??'')==='4.2.3 to 4.2.4','The refusal names the plugin and both versions');
check(!isset($drift['ctx-feed/ctx-feed.php']),'Unrelated plugins stay out of the material drift report');

// Plans queued before 0.90.0 stored the whole-site hash. They must still validate.
env_fixture(); $legacy=CC_Assistant_Evidence_Gate::environment_hash('0.89.4');
check(CC_Assistant_Evidence_Gate::environment_matches($legacy),'Pre-0.90.0 plans still validate against the legacy fingerprint');
$GLOBALS['options']['home']='https://different-site.example';
check(!CC_Assistant_Evidence_Gate::environment_matches($legacy),'A pre-0.90.0 plan is still blocked by a real environment change');
echo "Environment regression checks passed: $tests\n";
