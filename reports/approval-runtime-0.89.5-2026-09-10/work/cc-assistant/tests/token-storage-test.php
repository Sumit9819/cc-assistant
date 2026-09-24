<?php
define('ABSPATH',__DIR__.'/');
class WP_Error {
	public function __construct(public $code,public $message='') {}
	public function get_error_message() { return $this->message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_salt($scheme) { return 'isolated-test-key'; }
function wp_json_encode($v) { return json_encode($v); }
function get_option($key,$default=false) { return $GLOBALS['options'][$key]??$default; }
function update_option($key,$value,$autoload=null) { $GLOBALS['options'][$key]=$value; return true; }
require dirname(__DIR__).'/includes/class-gsc.php';
require dirname(__DIR__).'/includes/class-gbp.php';
foreach(array('CC_Assistant_GSC','CC_Assistant_GBP') as $class) {
	$GLOBALS['options']=array();
	$save=new ReflectionMethod($class,'set_tokens');
	$encrypt=new ReflectionMethod($class,'encrypt');
	$decrypt=new ReflectionMethod($class,'decrypt');
	$tokens=array('access_token'=>'fixture-secret','refresh_token'=>'fixture-refresh');
	$blob=$encrypt->invoke(null,json_encode($tokens));
	if(function_exists('openssl_encrypt')) {
		if(!is_string($blob) || strpos($blob,'gcm1:')!==0 || $decrypt->invoke(null,$blob)!==json_encode($tokens) || $save->invoke(null,$tokens)!==true || $class::get_tokens()!==$tokens) { exit(1); }
		echo "PASS: $class stores authenticated encryption and reads tokens back.\n";
	} else {
		$r=$save->invoke(null,$tokens);
		if(!is_wp_error($r) || isset($GLOBALS['options'][$class::OPT_TOKENS])) { exit(1); }
		echo "PASS: $class refuses unencrypted token storage when OpenSSL is unavailable.\n";
	}
}
