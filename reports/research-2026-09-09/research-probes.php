<?php
// Read-only probes of the installed implementation using an in-memory database double.
define('ABSPATH', __DIR__ . '/');
define('CC_ASSISTANT_DIR', 'D:/cc-assistant/wp-content/plugins/cc-assistant/');
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
class ResearchDatabaseDouble {
    public $prefix = 'research_';
    public $inserts = array();
    public function prepare($sql, ...$args) { return array($sql, $args); }
    public function query($prepared) {
        if (strpos($prepared[0], 'INSERT INTO') === 0) {
            $this->inserts = array_merge($this->inserts, array_chunk($prepared[1][0], 11));
        }
        return 1;
    }
}
require CC_ASSISTANT_DIR . 'includes/class-gsc.php';
require CC_ASSISTANT_DIR . 'includes/class-verified-page-audit.php';
$wpdb = new ResearchDatabaseDouble();
$url = 'https://example.test/page/';
$method = new ReflectionMethod('CC_Assistant_GSC', 'store_rows_for_date');
$method->setAccessible(true);
$method->invoke(null, '2026-09-01',
    array(array('keys'=>array('2026-09-01',$url,'example query'),'clicks'=>1,'impressions'=>1,'ctr'=>1,'position'=>1)),
    array(
        array('keys'=>array('2026-09-01',$url,'AMP_BLUE_LINK'),'impressions'=>1),
        array('keys'=>array('2026-09-01',$url,'REVIEW_SNIPPET'),'impressions'=>1)
    )
);
$rows = array_map(function($r) { return array('query'=>$r[2],'appearance'=>$r[3],'clicks'=>$r[4],'impressions'=>$r[5]); }, $wpdb->inserts);
$old = array('rules_version'=>'1','source'=>array(), 'findings'=>array(
    array('rule_id'=>'kept', 'status'=>'pass'), array('rule_id'=>'removed', 'status'=>'fail')
));
$new = array('rules_version'=>'2','source'=>array('body_sha1'=>null,'cache_state'=>null,'error'=>null), 'findings'=>array(array('rule_id'=>'kept','status'=>'pass')));
echo json_encode(array(
    'scope'=>'Synthetic inputs; installed source; in-memory database double; no WordPress bootstrap, database access or network calls.',
    'gsc_rounding'=>array('input_clicks'=>1,'input_impressions'=>1,'stored_rows'=>$rows,
        'stored_clicks'=>array_sum(array_column($rows,'clicks')), 'stored_impressions'=>array_sum(array_column($rows,'impressions'))),
    'removed_audit_rule'=>CC_Assistant_Verified_Page_Audit::compare($old,$new)
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
