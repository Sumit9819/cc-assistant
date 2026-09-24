<?php
// Isolated read-only strategy probes. No WordPress bootstrap, network or database.
define('ABSPATH', __DIR__.'/');
define('CC_ASSISTANT_DIR', 'D:/cc-assistant/wp-content/plugins/cc-assistant/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
function wp_strip_all_tags($s) { return strip_tags($s); }
function wp_parse_url($url,$part=-1) { return parse_url($url,$part); }
function get_option($name,$fallback=false) { return $fallback; }
function apply_filters($name,$value,...$rest) { return $value; }
function home_url() { return 'https://example.test'; }
class CC_Assistant_Pre_Publish {}
require CC_ASSISTANT_DIR.'includes/class-rest-api.php';
require CC_ASSISTANT_DIR.'includes/class-win-audit.php';
require CC_ASSISTANT_DIR.'includes/class-topical-authority.php';
require CC_ASSISTANT_DIR.'bin/warehouse.php';

$synthetic='<p>The competitor lists $99. DO means the word do in capitals.</p><table><tr><td>Ordinary text</td></tr></table><blockquote>Generic unattributed text.</blockquote><a href="https://agency.gov/">Unverified reference</a>';
$signals=CC_Assistant_REST_API::info_gain_signals($synthetic);

$cluster=new ReflectionMethod('CC_Assistant_Topical_Authority','classify_cluster');
$cluster->setAccessible(true);
$recommend=new ReflectionMethod('CC_Assistant_Topical_Authority','recommendation');
$recommend->setAccessible(true);
$gsc=array('impressions'=>30000,'clicks'=>2000,'avg_position'=>3);
$linking=array('avg_inbound'=>10);
$status=$cluster->invoke(null,$gsc,$linking,3);

$fingerprint=CC_Assistant_Win_Audit::fingerprint('<p>10% 20% 30% 40% 50%</p>'.str_repeat('<blockquote>Unattributed ordinary text.</blockquote>',3).str_repeat('<a href="https://research.gov.example/path">Unverified reference</a>',4),'https://example.test/');
$actions=array();
$dim=new ReflectionMethod('CC_Assistant_Win_Audit','dim_info_gain');
$dim->setAccessible(true);
$info=$dim->invokeArgs(null,array($fingerprint,array(),&$actions));

$before=array('impressions'=>999,'clicks'=>0,'position'=>3,'intent_family'=>'research');
$after=$before;$after['impressions']=1000;
$out=array(
 'scope'=>'Synthetic inputs to installed PHP; no database or network; not a live-site diagnosis.',
 'surface_signals'=>array('input'=>$synthetic,'result'=>$signals),
 'healthy_three_page_cluster'=>array('gsc'=>$gsc,'linking'=>$linking,'status'=>$status,'recommendation'=>$recommend->invoke(null,$status,$gsc,$linking,3)),
 'unsupported_info_gain'=>array('authority_links'=>$fingerprint['authority_links'],'stats_count'=>$fingerprint['stats_count'],'blockquote_count'=>$fingerprint['blockquotes'],'dimension_score'=>$info['score'],'competitors_examined'=>0),
 'threshold_flip'=>array('at_999'=>cc_commodity_classify($before),'at_1000'=>cc_commodity_classify($after)),
 'position_aggregation_counterexample'=>array('rows'=>array(array('impressions'=>99,'position'=>2),array('impressions'=>1,'position'=>80)),'sql_AVG_used_by_cannibalization'=>41,'impression_weighted_mean'=>2.78,'note'=>'Arithmetic illustration of the inspected SQL aggregation; SQL itself was not executed.')
);
file_put_contents(__DIR__.'/strategy-probe-results.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

