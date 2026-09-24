<?php
// Isolated review harness: real plugin classes, in-memory SQLite, minimal WP doubles.
// No WordPress bootstrap, credentials, network requests, or live database access.
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('CC_ASSISTANT_DIR', dirname(__DIR__,2) . '/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
class WP_Error {
    public function __construct(public $code, public $message = '', public $data = []) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
class WriteReached extends Exception {}
function is_wp_error($v) { return $v instanceof WP_Error; }
function current_time($type, $gmt = false) { $t = strtotime('2026-09-08 12:00:00 UTC') + ($gmt ? 0 : $GLOBALS['offset']); return $type === 'timestamp' ? $t : gmdate('Y-m-d H:i:s', $t); }
function wp_parse_args($a, $b) { return array_merge($b, $a); }
function wp_json_encode($v) { return json_encode($v); }
function maybe_serialize($v) { return is_array($v) || is_object($v) ? serialize($v) : $v; }
function maybe_unserialize($v) { if (is_string($v) && preg_match('/^(a|s|O|b|i|d):/', $v)) { $u = @unserialize($v); if ($u !== false || $v === 'b:0;') return $u; } return $v; }
function get_current_user_id() { return 17; }
function get_post($id) { return $id ? $GLOBALS['post'] : null; }
function get_post_field($key, $id) { return $GLOBALS['post']->$key ?? ''; }
function get_post_meta($id, $key = '', $single = false) { if ($key === '') return $GLOBALS['meta']; return $single ? ($GLOBALS['meta'][$key][0] ?? '') : ($GLOBALS['meta'][$key] ?? []); }
function metadata_exists($type, $id, $key) { return isset($GLOBALS['meta'][$key]); }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : (is_string($v) ? stripslashes($v) : $v); }
function wp_slash($v) { return is_array($v) ? array_map('wp_slash', $v) : (is_string($v) ? addslashes($v) : $v); }
// WordPress's documented update_metadata/add_metadata unslash behavior.
function update_post_meta($id, $key, $v) { $GLOBALS['meta'][$key] = [wp_unslash($v)]; return 1; }
function add_post_meta($id, $key, $v) { $GLOBALS['meta'][$key][] = wp_unslash($v); return 1; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$key]); return true; }
function wp_update_post($args, $error = false) {
    $GLOBALS['write_count']++; if ($GLOBALS['stop_on_update']) throw new WriteReached('Reached live-content write');
    foreach (wp_unslash($args) as $k => $v) $GLOBALS['post']->$k = $v;
    return 1;
}
function delete_transient($key) { unset($GLOBALS['test_transients'][$key]); }
function get_transient($key) { return $GLOBALS['test_transients'][$key] ?? false; }
function set_transient($key, $v, $ttl) { $GLOBALS['test_transients'][$key]=$v; }
function wp_cache_delete($key, $group = '') {}
function wp_next_scheduled($hook) { return false; }
function wp_schedule_single_event(...$a) {}
function clean_post_cache($id) {}
function get_option($key, $default = false) { return $default; }
function human_time_diff($a, $b) { return (string) abs($a-$b) . ' seconds'; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function home_url() { return 'https://owned-site.example'; }
class ReviewDB {
    public $prefix = 'wp_'; public $options = 'wp_options'; public $posts='wp_posts'; public $postmeta='wp_postmeta'; public $termmeta='wp_termmeta'; public $insert_id = 0; public $fail_snapshot = false; public $db;
    public function __construct() {
        $this->db = new SQLite3(':memory:');
        $this->db->exec('CREATE TABLE wp_options (option_name TEXT, option_value TEXT)');
        $this->db->exec('CREATE TABLE wp_cc_pending_changes (id INTEGER PRIMARY KEY, post_id INTEGER, change_type TEXT, change_summary TEXT, current_value TEXT, proposed_value TEXT, reasoning TEXT, status TEXT, created_at TEXT, created_by TEXT, reviewed_at TEXT, reviewed_by INTEGER, review_note TEXT, superseded_by INTEGER, pre_check_baseline TEXT, success_metrics TEXT)');
        $this->db->exec('CREATE TABLE wp_cc_snapshots (id INTEGER PRIMARY KEY, post_id INTEGER, snapshot_type TEXT, post_content TEXT, post_title TEXT, post_meta TEXT, elementor_data TEXT, created_at TEXT, created_by INTEGER, note TEXT)');
    }
    public function prepare($sql, ...$args) {
        if (isset($args[0]) && is_array($args[0])) $args = $args[0]; $i=0;
        return preg_replace_callback('/%[ds]/', function($m) use (&$i, $args) { $v = $args[$i++]; return $m[0] === '%d' ? (string)(int)$v : "'" . SQLite3::escapeString((string)$v) . "'"; }, $sql);
    }
    public function query($sql) { $this->db->exec($sql); return $this->db->changes(); }
    public function get_row($sql) { $r = $this->db->querySingle($sql, true); return $r ? (object)$r : null; }
    public function get_results($sql) { $out=[]; $r=$this->db->query($sql); while($row=$r->fetchArray(SQLITE3_ASSOC)) $out[]=(object)$row; return $out; }
    public function get_var($sql) { if (str_starts_with($sql, 'SELECT GET_LOCK(')) return empty($GLOBALS['write_lock_busy']) ? 1 : 0; if (str_starts_with($sql, 'SELECT RELEASE_LOCK(')) return 1; return $this->db->querySingle($sql); }
    public function get_col($sql) { return []; }
    public function insert($table, $data) {
        if (!in_array($table, ['wp_cc_pending_changes','wp_cc_snapshots'])) { $this->insert_id=1; return 1; }
        if ($table === 'wp_cc_snapshots' && $this->fail_snapshot) { $this->insert_id=0; return false; }
        $cols=implode(',', array_keys($data)); $values=implode(',', array_fill(0,count($data),'?'));
        $s=$this->db->prepare("INSERT INTO $table ($cols) VALUES ($values)"); $i=1;
        foreach($data as $v) $s->bindValue($i++, $v, $v === null ? SQLITE3_NULL : (is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT));
        $s->execute(); $this->insert_id=$this->db->lastInsertRowID(); return 1;
    }
    public function update($table, $data, $where) {
        if ($table === $this->posts) { foreach ($data as $k=>$v) $GLOBALS['post']->$k=$v; return 1; }
        $fields=[]; foreach($data as $k=>$v) $fields[]=$this->prepare("$k = %s", $v);
        $conds=[]; foreach($where as $k=>$v) $conds[]=$this->prepare("$k = %s", $v);
        return $this->query("UPDATE $table SET ".implode(',',$fields).' WHERE '.implode(' AND ',$conds));
    }
}
require CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
require CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
require CC_ASSISTANT_DIR . 'includes/class-apply.php';
require CC_ASSISTANT_DIR . 'includes/class-win-audit.php';
function reset_review($offset = 0) {
    $GLOBALS['test_transients']=[];
    $GLOBALS['wpdb'] = new ReviewDB(); $GLOBALS['offset']=$offset; $GLOBALS['meta']=[]; $GLOBALS['stop_on_update']=false; $GLOBALS['write_count']=0; $GLOBALS['terms']=['category'=>[2], 'post_tag'=>[4]];
    $GLOBALS['post']=(object)['ID'=>1,'post_type'=>'post','post_parent'=>0,'menu_order'=>0,'post_title'=>'Original','post_content'=>'Original body','post_modified'=>'2026-09-08 11:59:00','post_modified_gmt'=>'2026-09-08 11:59:00','post_name'=>'original','post_status'=>'draft','post_excerpt'=>'Original excerpt','post_author'=>17];
}
function pending($type='post_content_update', $created='2026-09-08 12:00:00', $status='pending', $post_id=1) {
    $GLOBALS['wpdb']->insert('wp_cc_pending_changes', ['post_id'=>$post_id,'change_type'=>$type,'status'=>$status,'proposed_value'=>'{"content":"New body"}','current_value'=>'{"content":"Original body"}','change_summary'=>'Regression fixture','created_at'=>$created,'superseded_by'=>null]);
    return $GLOBALS['wpdb']->insert_id;
}
function result($label, $confirmed, $details) { echo json_encode(['case'=>$label,'issue_reproduced'=>(bool)$confirmed,'details'=>$details],JSON_UNESCAPED_SLASHES)."\n"; }


function get_gmt_from_date($date) { return gmdate('Y-m-d H:i:s', strtotime($date.' UTC')-$GLOBALS['offset']); }
function get_object_taxonomies($type) { return ['category','post_tag']; }
function wp_get_object_terms($id,$tax,$args) { return $GLOBALS['terms'][$tax]??[]; }
function wp_set_object_terms($id,$ids,$tax,$append=false) { $GLOBALS['terms'][$tax]=$ids; return $ids; }
function taxonomy_exists($tax) { return in_array($tax,['category','post_tag']); }
function wp_cache_flush() {}
function do_action(...$args) {}
function get_post_type($id) { return 'post'; }
function get_permalink($id) { return 'https://owned-site.example/example'; }
function add_action(...$args) {}
function add_filter(...$args) {}
function wp_http_validate_url($url) { return false; } // No DNS/network in regression tests.
if (!function_exists('mb_substr')) { function mb_substr($s,$start,$len=null) { return substr($s,$start,$len); } }
if (!function_exists('mb_strlen')) { function mb_strlen($s) { return strlen($s); } }
$tests=0;
function check($condition,$name) {
    global $tests; $tests++;
    if (!$condition) { fwrite(STDERR,"FAIL: $name\n"); exit(1); }
    echo "PASS: $name\n";
}
function err($r,$code) { return is_wp_error($r) && $r->get_error_code()===$code; }

function is_serialized($v) { return is_string($v) && preg_match('/^(a|s|O|b|i|d):/', $v); }
