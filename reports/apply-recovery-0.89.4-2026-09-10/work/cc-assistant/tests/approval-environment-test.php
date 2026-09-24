<?php
define('ABSPATH',__DIR__.'/');
define('CC_ASSISTANT_VERSION','0.89.4');
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
    $GLOBALS['plugins']=array(CC_ASSISTANT_BASENAME=>array('Version'=>'0.89.3'),'elementor/elementor.php'=>array('Version'=>'4.2.3'));
    $GLOBALS['options']=array('active_plugins'=>array_keys($GLOBALS['plugins']),'elementor_active_kit'=>10,'stylesheet'=>'theme','template'=>'theme','rank-math-options-titles'=>array('robots'=>array('index')));
    $hash=CC_Assistant_Evidence_Gate::environment_hash();
    $GLOBALS['plugins'][CC_ASSISTANT_BASENAME]['Version']='0.89.4';
    return $hash;
}
$old=env_fixture();
check(CC_Assistant_Evidence_Gate::environment_hash()!==$old && CC_Assistant_Evidence_Gate::environment_matches($old),'Only the tested CC Assistant patch upgrade preserves prior environment evidence');
check(CC_Assistant_Evidence_Gate::environment_matches(CC_Assistant_Evidence_Gate::environment_hash()),'New proposals still accept the exact current environment');
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
env_fixture(); $older=CC_Assistant_Evidence_Gate::environment_hash('0.89.2');
check(!CC_Assistant_Evidence_Gate::environment_matches($older),'Unreviewed older plugin upgrades remain blocked');
check(!CC_Assistant_Evidence_Gate::environment_matches('') && !CC_Assistant_Evidence_Gate::environment_matches(null),'Missing environment evidence cannot borrow upgrade compatibility');
echo "Environment regression checks passed: $tests\n";
