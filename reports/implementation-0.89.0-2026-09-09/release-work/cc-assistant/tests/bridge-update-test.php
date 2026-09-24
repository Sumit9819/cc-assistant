<?php
require dirname(__DIR__).'/bin/operator-brain.php';
$root=sys_get_temp_dir().'/cc-bridge-regression-'.bin2hex(random_bytes(8));
$bin=$root.'/wp-content/plugins/cc-assistant/bin';
mkdir($bin,0777,true);
$count=0;
function check_bridge($ok,$name) {
	global $count; $count++;
	if(!$ok) { fwrite(STDERR,"FAIL: $name\n"); exit(1); }
	echo "PASS: $name\n";
}
function pull_fixture($content,$mode='replace',$hash=null,$name='fixture.php') {
	global $root;
	return cc_brain_tool_pull(static function()use($content,$hash,$name) { return array('files'=>array($name=>array('content'=>$content,'sha1'=>$hash ?? sha1($content)))); },'bridge',$mode,$root,$root.'/home');
}
$original="<?php // preserved local code\n";
$replacement="<?php // valid replacement, never executed\n";
file_put_contents($bin.'/fixture.php',$original);
$r=pull_fixture($replacement,'replace',str_repeat('0',40));
check_bridge(count($r['errors'])===1 && file_get_contents($bin.'/fixture.php')===$original,'Hash mismatch cannot overwrite existing bridge');
$r=pull_fixture($replacement,'missing_only');
check_bridge(!$r['written'] && file_get_contents($bin.'/fixture.php')===$original,'Missing-only preserves existing local files');
$r=pull_fixture('<?php function {');
check_bridge(count($r['errors'])===1 && file_get_contents($bin.'/fixture.php')===$original,'Invalid PHP cannot replace working bridge');
$r=pull_fixture($replacement);
check_bridge(!$r['errors'] && file_get_contents($bin.'/fixture.php')===$replacement && file_get_contents($bin.'/fixture.php.previous')===$original,'Valid bridge update saves backup and replaces complete file');
$r=pull_fixture('{"private":true,"dependencies":{"playwright":"1.62.1"}}','replace',null,'browser/package.json');
check_bridge(!$r['errors'] && is_file($bin.'/browser/package.json'),'Browser manifest supported by bridge distribution');
$r=pull_fixture('export const sample = 1;','replace',null,'browser-transport.mjs');
check_bridge(!$r['errors'] && is_file($bin.'/browser-transport.mjs'),'Browser worker syntax checked and installed');
$r=pull_fixture('bad','replace',null,'../../outside.php');
check_bridge(!$r['written'] && !is_file($root.'/outside.php'),'Bridge paths cannot traverse out of bin');
$r=pull_fixture('def broken(', 'replace', null, 'automation-runner.py');
check_bridge(count($r['errors'])===1 && !is_file($bin.'/automation-runner.py'),'Invalid Python cannot be installed as a valid bridge file');
$r=pull_fixture('value = 1', 'replace', null, 'automation-runner.py');
check_bridge(!$r['errors'] && is_file($bin.'/automation-runner.py'),'Python runner is syntax checked and distributed');
// Clean only fixed test files in this freshly created random directory.
foreach(array('fixture.php','fixture.php.previous','browser-transport.mjs','browser/package.json','automation-runner.py') as $file) { unlink($bin.'/'.$file); }
rmdir($bin.'/browser'); rmdir($bin); rmdir(dirname($bin)); rmdir($root.'/wp-content/plugins'); rmdir($root.'/wp-content'); rmdir($root);
echo "$count bridge update regression checks passed.\n";
