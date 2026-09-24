<?php
/**
 * Operator Brain (v0.79) — the site as the store for memory, skills and the
 * bridge, with whoami announcing drift on every machine.
 *
 * What went wrong before this existed: a second laptop opened a chat with
 * "a lot of functions missing" (stale local bridge; version_drift only looked
 * the other way) and knew none of the rules learned on the first laptop
 * (234 memory files lived on one disk). Both halves are pinned here.
 *
 * The one invariant everything rests on: the laptop and the server compute
 * the SAME fingerprint for the same files. If those two implementations ever
 * drift, every status call would say "site_newer" forever and pushes would
 * report diverged_after_push. Test 1 feeds identical input to both.
 *
 * Run: php tests/operator-brain-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
define( 'CC_ASSISTANT_VERSION', '0.79.0-test' );

/* --- minimal WP surface for the REST class --- */
$GLOBALS['cc_test_options'] = array();
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $c = '', $m = '', $d = array() ) { $this->code = $c; $this->message = $m; $this->data = $d; }
		public function get_error_code() { return $this->code; }
	}
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['cc_test_options'][ $k ] ) ? $GLOBALS['cc_test_options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { if ( ! empty( $GLOBALS['brain_write_fail'] ) ) { return false; } $GLOBALS['cc_test_options'][ $k ] = $v; return true; }
class BrainLockDB {
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) { if ( str_contains( $sql, 'RELEASE_LOCK' ) ) { $GLOBALS['brain_lock_releases'] = 1 + ( $GLOBALS['brain_lock_releases'] ?? 0 ); return 1; } return empty( $GLOBALS['brain_lock_busy'] ) ? 1 : 0; }
}
$GLOBALS['wpdb'] = new BrainLockDB();
function rest_ensure_response( $x ) { return $x; }
function home_url() { return 'https://sids-ponds.com'; }
function current_user_can( $c ) { return true; }
function register_rest_route() {}

require_once CC_ASSISTANT_DIR . 'includes/class-rest-operator-kit.php';
require_once CC_ASSISTANT_DIR . 'bin/operator-brain.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( '' !== $extra ? " ($extra)" : '' ) . "\n"; }
}
function rrmdir( $d ) {
	if ( ! is_dir( $d ) ) { return; }
	foreach ( scandir( $d ) as $e ) {
		if ( '.' === $e || '..' === $e ) { continue; }
		$p = $d . '/' . $e;
		is_dir( $p ) ? rrmdir( $p ) : unlink( $p );
	}
	rmdir( $d );
}

/* Fake request object for the REST handlers. */
class CC_Brain_Test_Request {
	private $json; private $params;
	public function __construct( $json = array(), $params = array() ) { if ( $json && ! array_key_exists( 'expected_fingerprint', $json ) && empty( $GLOBALS['brain_omit_basis'] ) ) { $json['expected_fingerprint'] = CC_Assistant_REST_Operator_Kit::load_brain( false )['fingerprint'] ?? ''; } $this->json = $json; $this->params = $params; }
	public function get_json_params() { return $this->json; }
	public function get_param( $k ) { return isset( $this->params[ $k ] ) ? $this->params[ $k ] : null; }
}

/* ------------------------------------------------------------------ */
echo "\n-- 1. fingerprint formula is shared and order-independent\n";
$files = array(
	'memory/feedback_x.md'                     => "---\nname: feedback_x\ndescription: \"HARD: never guess\"\n---\nbody",
	'skills/design-system-global/SKILL.md'     => '# global',
	'project/CLAUDE.md'                        => '# claude',
);
$idx_bridge = cc_brain_index_from_files( $files );
$idx_server = CC_Assistant_REST_Operator_Kit::index_from_files( array_reverse( $files, true ) );
check( 'bridge and server indexes are identical', $idx_bridge === $idx_server );
check( 'fingerprints agree', cc_brain_fingerprint( $idx_bridge ) === CC_Assistant_REST_Operator_Kit::fingerprint_from_index( $idx_server ) );
check( 'fingerprint is 64 hex chars', (bool) preg_match( '/^[a-f0-9]{64}$/', cc_brain_fingerprint( $idx_bridge ) ) );
$files2 = $files; $files2['project/CLAUDE.md'] = '# claude v2';
check( 'one changed byte changes the fingerprint', cc_brain_fingerprint( cc_brain_index_from_files( $files2 ) ) !== cc_brain_fingerprint( $idx_bridge ) );
check( 'empty set has empty fingerprint', '' === cc_brain_fingerprint( array() ) && '' === CC_Assistant_REST_Operator_Kit::fingerprint_from_index( array() ) );

/* ------------------------------------------------------------------ */
echo "\n-- 2. path validation refuses anything that could escape on write-back\n";
foreach ( array( 'memory/a.md', 'skills/x/SKILL.md', 'skills/x/refs/deep/file.md', 'project/CLAUDE.md' ) as $ok ) {
	check( "accepts $ok", cc_brain_valid_path( $ok ) && CC_Assistant_REST_Operator_Kit::valid_brain_path( $ok ) );
}
foreach ( array( '../memory/a.md', 'memory/../x', '/memory/a.md', 'C:/x', 'memory\\a.md', 'other/a.md', 'memory/', '', "memory/a\0.md", str_repeat( 'a', 301 ) ) as $bad ) {
	check( 'refuses ' . json_encode( $bad ), ! cc_brain_valid_path( $bad ) && ! CC_Assistant_REST_Operator_Kit::valid_brain_path( $bad ) );
}

/* ------------------------------------------------------------------ */
echo "\n-- 3. .mcp.json template scrubs secrets and renders back for another machine\n";
$live = json_encode( array( 'mcpServers' => array(
	'cc-assistant-sids-ponds-com' => array(
		'command' => 'C:/Users/sumit/AppData/Local/Programs/Local/resources/php-8.2.29+0/bin/win64/php.exe',
		'args'    => array( '-d', 'extension_dir=C:/Users/sumit/.../ext', '-d', 'extension=curl', './wp-content/plugins/cc-assistant/bin/mcp-server.php' ),
		'env'     => array( 'CC_WP_URL' => 'https://sids-ponds.com/', 'CC_WP_USER' => 'admin', 'CC_WP_APP_PASSWORD' => 'abcd efgh ijkl' ),
	),
	'ubersuggest' => array( 'type' => 'http', 'url' => 'https://x/mcp', 'headers' => array( 'Authorization' => 'Bearer zzz' ) ),
) ) );
$tpl = cc_brain_mcp_template( $live );
check( 'template drops the app password', false === strpos( $tpl, 'abcd efgh' ) && false !== strpos( $tpl, '{FILL_ME}' ) );
check( 'template drops bearer token', false === strpos( $tpl, 'zzz' ) );
check( 'template abstracts the PHP binary and ext dir', false !== strpos( $tpl, '"{PHP}"' ) && false !== strpos( $tpl, 'extension_dir={PHP_EXT_DIR}' ) && false === strpos( $tpl, 'php-8.2.29' ) );
check( 'template keeps the site URL and user', false !== strpos( $tpl, 'https://sids-ponds.com/' ) && false !== strpos( $tpl, '"admin"' ) );
check( 'template of garbage is empty string', '' === cc_brain_mcp_template( 'not json' ) );

$tmp = sys_get_temp_dir() . '/cc-brain-test-' . getmypid();
rrmdir( $tmp );
mkdir( $tmp . '/php/ext', 0777, true );
$php_bin = $tmp . '/php/php.exe';
file_put_contents( $php_bin, 'x' );
$rendered = cc_brain_render_mcp_template( $tpl, $php_bin, array( 'https://sids-ponds.com' => 'new pass' ) );
$r = json_decode( $rendered, true );
$srv = $r['mcpServers']['cc-assistant-sids-ponds-com'];
check( 'rendered command is this machine\'s PHP', $srv['command'] === str_replace( '\\', '/', $php_bin ) );
check( 'rendered ext dir follows the PHP binary', in_array( 'extension_dir=' . str_replace( '\\', '/', $tmp ) . '/php/ext', $srv['args'], true ) );
check( 'known password filled (trailing-slash tolerant)', 'new pass' === $srv['env']['CC_WP_APP_PASSWORD'] );
check( 'unknown token stays {FILL_ME}', '{FILL_ME}' === $r['mcpServers']['ubersuggest']['headers']['Authorization'] );
$rendered2 = cc_brain_render_mcp_template( $tpl, $tmp . '/nope/php', array() );
$srv2 = json_decode( $rendered2, true )['mcpServers']['cc-assistant-sids-ponds-com'];
check( 'no ext dir on this machine: extension_dir flag dropped with its -d', ! in_array( 'extension_dir={PHP_EXT_DIR}', $srv2['args'], true ) && array_values( $srv2['args'] )[0] === '-d' && array_values( $srv2['args'] )[1] === 'extension=curl' );
check( 'unknown password stays {FILL_ME}', '{FILL_ME}' === $srv2['env']['CC_WP_APP_PASSWORD'] );

/* ------------------------------------------------------------------ */
echo "\n-- 4. project key matches what Claude Code writes on disk\n";
check( 'windows path key', 'c--Users-sumit-Local-Sites-plugintesting-app-public' === cc_brain_project_key( 'C:\\Users\\sumit\\Local Sites\\plugintesting\\app\\public' ) );
check( 'lower-case drive already', 'd--Best-video-generator' === cc_brain_project_key( 'd:\\Best video generator' ) );
check( 'posix path key', '-home-me-proj' === cc_brain_project_key( '/home/me/proj' ) );

/* ------------------------------------------------------------------ */
echo "\n-- 5. collect local brain from a fake home + project\n";
$home = $tmp . '/home';
$proj = $tmp . '/proj';
mkdir( $proj, 0777, true );
$memdir = $home . '/.claude/projects/' . cc_brain_project_key( $proj ) . '/memory';
mkdir( $memdir, 0777, true );
mkdir( $home . '/.claude/skills/design-system-global', 0777, true );
mkdir( $home . '/.claude/skills/sids-ponds-design/refs', 0777, true );
file_put_contents( $memdir . '/MEMORY.md', "- [x](feedback_x.md)\n" );
file_put_contents( $memdir . '/feedback_x.md', "---\nname: feedback_x\ndescription: \"HARD: never guess\"\nmetadata:\n  type: feedback\n---\nBody about anything.\n" );
file_put_contents( $memdir . '/project_sids.md', "---\nname: project_sids_ponds\ndescription: sids-ponds engagement notes\n---\nDivi + Woo.\n" );
file_put_contents( $memdir . '/reference_other.md', "---\nname: reference_other\ndescription: unrelated\n---\nmentions erofirving only.\n" );
file_put_contents( $memdir . '/binary.png', 'PNG' );
file_put_contents( $home . '/.claude/skills/design-system-global/SKILL.md', '# global' );
file_put_contents( $home . '/.claude/skills/sids-ponds-design/SKILL.md', '# sids' );
file_put_contents( $home . '/.claude/skills/sids-ponds-design/refs/palette.md', '# palette' );
file_put_contents( $home . '/.claude/skills/sids-ponds-design/refs/photo.jpg', 'JPG' );
file_put_contents( $proj . '/CLAUDE.md', '# project rules' );
file_put_contents( $proj . '/.mcp.json', $live );
mkdir( $proj . '/.claude/hooks', 0777, true );
file_put_contents( $proj . '/.claude/settings.json', '{"hooks":{}}' );
file_put_contents( $proj . '/.claude/settings.local.json', '{"personal":true}' );
file_put_contents( $proj . '/.claude/hooks/cc_gate.py', 'print(1)' );

$local = cc_brain_collect_local( $proj, $home );
$paths = array_keys( $local['files'] );
check( 'collects 3 memory files + index', 4 === $local['present']['memory'] && in_array( 'memory/MEMORY.md', $paths, true ) );
check( 'skips binary in memory', ! in_array( 'memory/binary.png', $paths, true ) );
check( 'collects skills recursively, text only', in_array( 'skills/sids-ponds-design/refs/palette.md', $paths, true ) && ! in_array( 'skills/sids-ponds-design/refs/photo.jpg', $paths, true ) && 3 === $local['present']['skills'] );
check( 'collects CLAUDE.md and scrubbed mcp template', in_array( 'project/CLAUDE.md', $paths, true ) && in_array( 'project/mcp-template.json', $paths, true ) && false === strpos( $local['files']['project/mcp-template.json'], 'abcd efgh' ) );
check( 'fingerprint set and newest mtime set', 64 === strlen( $local['fingerprint'] ) && $local['newest_mtime'] > 0 );
check( 'v0.81: project hooks + shared settings collected, settings.local.json NOT', in_array( 'project/.claude/settings.json', $paths, true ) && in_array( 'project/.claude/hooks/cc_gate.py', $paths, true ) && ! in_array( 'project/.claude/settings.local.json', $paths, true ) );
mkdir( $tmp . '/proj_am/.claude/skills/x-design', 0777, true );
file_put_contents( $tmp . '/proj_am/.claude/settings.local.json', json_encode( array( 'autoMemoryDirectory' => str_replace( '\\', '/', $tmp ) . '/proj_am/memory' ) ) );
file_put_contents( $tmp . '/proj_am/.claude/skills/x-design/SKILL.md', '# x' );
mkdir( $tmp . '/proj_am/memory', 0777, true );
file_put_contents( $tmp . '/proj_am/memory/MEMORY.md', '- idx' );
check( 'v0.81.1: autoMemoryDirectory in settings.local.json wins over the ~/.claude/projects default', str_replace( '\\', '/', $tmp ) . '/proj_am/memory' === cc_brain_memory_dir( $tmp . '/nohome', $tmp . '/proj_am' ) );
$am = cc_brain_collect_local( $tmp . '/proj_am', $tmp . '/nohome' );
check( 'v0.81.1: in-folder memory + project skills collected', isset( $am['files']['memory/MEMORY.md'] ) && isset( $am['files']['project/.claude/skills/x-design/SKILL.md'] ) );
check( 'v0.81.1: project skill maps back under the project', ( $tmp . '/x/.claude/skills/x-design/SKILL.md' ) === cc_brain_local_target( 'project/.claude/skills/x-design/SKILL.md', $tmp . '/x', $tmp . '/h' ) );
check( 'v0.81: hook path maps back under the project', ( $tmp . '/x/.claude/hooks/cc_gate.py' ) === cc_brain_local_target( 'project/.claude/hooks/cc_gate.py', $tmp . '/x', $tmp . '/h' ) && null === cc_brain_local_target( 'project/.claude/settings.local.json', $tmp . '/x', $tmp . '/h' ) );
check( 'empty machine collects nothing', 0 === cc_brain_collect_local( $tmp . '/nowhere', $tmp . '/nohome' )['file_count'] );

/* ------------------------------------------------------------------ */
echo "\n-- 6. server store round trip (save -> summary -> load -> merge -> limits)\n";
$saved = CC_Assistant_REST_Operator_Kit::save_brain( $local['files'], 'LAPTOP-A' );
check( 'save returns summary with matching fingerprint', is_array( $saved ) && $saved['fingerprint'] === $local['fingerprint'] && $saved['file_count'] === $local['file_count'] );
check( 'stored compressed smaller than raw', isset( $GLOBALS['cc_test_options']['cc_assistant_operator_brain']['stored_bytes'] ) && $GLOBALS['cc_test_options']['cc_assistant_operator_brain']['stored_bytes'] < $local['bytes'] );
$loaded = CC_Assistant_REST_Operator_Kit::load_brain( true );
check( 'load inflates identical files', $loaded['files'] === $local['files'] );
check( 'brain_summary groups counted', 4 === $saved['groups']['memory'] && 3 === $saved['groups']['skills'] && 4 === $saved['groups']['project'], json_encode( $saved['groups'] ) );
check( 'bridge_files reads real bin/*.php with version', isset( CC_Assistant_REST_Operator_Kit::bridge_files()['mcp-server.php']['version'] ) );

$merge = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array(
	'mode' => 'merge', 'files' => array( 'memory/new_rule.md' => 'new' ), 'delete' => array( 'memory/reference_other.md' ), 'pushed_from' => 'LAPTOP-B',
) ) );
$after = CC_Assistant_REST_Operator_Kit::load_brain( true );
check( 'merge adds and deletes', isset( $after['files']['memory/new_rule.md'] ) && ! isset( $after['files']['memory/reference_other.md'] ) && 'LAPTOP-B' === $after['pushed_from'] );
$expect_files = $local['files']; unset( $expect_files['memory/reference_other.md'] ); $expect_files['memory/new_rule.md'] = 'new';
check( 'merged fingerprint equals a fresh local fingerprint of the same set (push converges)', $after['fingerprint'] === cc_brain_fingerprint( cc_brain_index_from_files( $expect_files ) ) );

$bad = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'replace', 'files' => array( '../evil.md' => 'x' ) ) ) );
check( 'replace with escaping path refused', is_wp_error( $bad ) && 'bad_path' === $bad->code );
$bad2 = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'replace', 'files' => array() ) ) );
check( 'replace with empty set refused (never wipe the store by accident)', is_wp_error( $bad2 ) && 'empty_brain' === $bad2->code );
$bad3 = CC_Assistant_REST_Operator_Kit::save_brain( array( 'memory/huge.md' => str_repeat( 'a', 400001 ) ) );
check( 'oversized file refused', is_wp_error( $bad3 ) && 'file_too_large' === $bad3->code );
check( 'store untouched after refusals', CC_Assistant_REST_Operator_Kit::load_brain( false )['fingerprint'] === $after['fingerprint'] );

$status = CC_Assistant_REST_Operator_Kit::handle_brain_status( new CC_Brain_Test_Request() );
check( 'status carries index without bodies', isset( $status['index']['memory/new_rule.md']['sha1'] ) && ! isset( $status['files'] ) );
$get = CC_Assistant_REST_Operator_Kit::handle_brain_get( new CC_Brain_Test_Request( array(), array( 'prefix' => 'skills/' ) ) );
check( 'GET with prefix filters', 3 === $get['returned_files'] && ! isset( $get['files']['project/CLAUDE.md'] ) );

/* ------------------------------------------------------------------ */
echo "\n-- 7. diff + classification verdicts\n";
$site_summary = array( 'present' => true, 'fingerprint' => $after['fingerprint'], 'file_count' => $after['file_count'], 'updated_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) . ' UTC', 'pushed_from' => 'LAPTOP-B' );
$v = cc_brain_classify( $local, $site_summary, $after['index'] );
check( 'differing sets classified site_newer when site timestamp is later', 'site_newer' === $v['status'] && 1 === $v['diff']['only_local'] && 1 === $v['diff']['only_site'] );
$site_old = $site_summary; $site_old['updated_at'] = '2020-01-01 00:00:00 UTC';
check( 'local_newer when local files are newer than the push', 'local_newer' === cc_brain_classify( $local, $site_old, $after['index'] )['status'] );
$same = $site_summary; $same['fingerprint'] = $local['fingerprint'];
check( 'in_sync when fingerprints match', 'in_sync' === cc_brain_classify( $local, $same )['status'] );
check( 'local_missing when this machine has nothing', 'local_missing' === cc_brain_classify( cc_brain_collect_local( $tmp . '/nowhere', $tmp . '/nohome' ), $site_summary )['status'] );
check( 'site_empty when site has nothing', 'site_empty' === cc_brain_classify( $local, array( 'present' => false ) )['status'] );
check( 'both_empty', 'both_empty' === cc_brain_classify( cc_brain_collect_local( $tmp . '/nowhere', $tmp . '/nohome' ), array( 'present' => false ) )['status'] );
$d = cc_brain_diff( array( 'a' => array( 'sha1' => '1' ), 'b' => array( 'sha1' => '2' ) ), array( 'b' => array( 'sha1' => '9' ), 'c' => array( 'sha1' => '3' ) ) );
check( 'diff buckets', array( 'a' ) === $d['only_local'] && array( 'c' ) === $d['only_site'] && array( 'b' ) === $d['differ'] && 0 === $d['same'] );

/* bridge check against the real bin folder */
$real_hashes = CC_Assistant_REST_Operator_Kit::bridge_hashes();
check( 'bridge in_sync against its own hashes', 'in_sync' === cc_brain_bridge_check( $real_hashes )['status'] );
$tampered = $real_hashes; $tampered['mcp-server.php'] = 'deadbeef'; $tampered['future-tool.php'] = 'cafe';
$bc = cc_brain_bridge_check( $tampered );
check( 'bridge differs names the file and the missing one', 'differs' === $bc['status'] && array( 'mcp-server.php' ) === $bc['differ'] && array( 'future-tool.php' ) === $bc['only_site'] );
check( 'bridge unknown when site is pre-0.79', 'unknown' === cc_brain_bridge_check( array() )['status'] );

/* ------------------------------------------------------------------ */
echo "\n-- 8. relevant rules: HARD/STRICT + site token, names and descriptions only\n";
$rr = cc_brain_relevant_rules( $memdir, 'www.sids-ponds.com', "Sid&#039;s Ponds" );
check( 'site name entity-decoded before tokenising', in_array( "sid's ponds", $rr['tokens'], true ) && ! in_array( 'sid&#039;s ponds', $rr['tokens'], true ) );
$hard_names = array_map( function ( $r ) { return $r['name']; }, $rr['hard_rules'] );
$site_names = array_map( function ( $r ) { return $r['name']; }, $rr['site_rules'] );
check( 'HARD rule selected', array( 'feedback_x' ) === $hard_names );
check( 'site memory selected by host token', array( 'project_sids_ponds' ) === $site_names );
check( 'unrelated memory excluded', ! in_array( 'reference_other', $site_names, true ) );
check( 'MEMORY.md not counted as a memory', 3 === $rr['total_memories'] );
check( 'no bodies leak', ! isset( $rr['hard_rules'][0]['body'] ) );
$rr2 = cc_brain_relevant_rules( $tmp . '/nomem', 'x.com' );
check( 'missing memory folder reported, not fatal', false === $rr2['memory_present'] && isset( $rr2['note'] ) );

/* ------------------------------------------------------------------ */
echo "\n-- 9. write-back modes: missing_only never overwrites, replace does, never deletes, never .mcp.json\n";
$home2 = $tmp . '/home2';
$proj2 = $tmp . '/proj2';
mkdir( $proj2, 0777, true );
file_put_contents( $proj2 . '/CLAUDE.md', 'LOCAL VERSION' );
$w = cc_brain_write_files( $after['files'], $proj2, $home2, 'missing_only' );
check( 'writes everything missing', count( $w['written'] ) === count( $after['files'] ) - 1 && array( 'project/CLAUDE.md' ) === $w['skipped'] );
check( 'existing CLAUDE.md untouched in missing_only', 'LOCAL VERSION' === file_get_contents( $proj2 . '/CLAUDE.md' ) );
check( 'memory landed under the project key', is_file( $home2 . '/.claude/projects/' . cc_brain_project_key( $proj2 ) . '/memory/new_rule.md' ) );
check( 'skills landed in ~/.claude/skills', is_file( $home2 . '/.claude/skills/sids-ponds-design/refs/palette.md' ) );
check( 'template written as .mcp.template.json, live .mcp.json NOT created', is_file( $proj2 . '/.mcp.template.json' ) && ! is_file( $proj2 . '/.mcp.json' ) );
$w2 = cc_brain_write_files( array( 'project/CLAUDE.md' => 'SITE VERSION', 'memory/new_rule.md' => 'new' ), $proj2, $home2, 'replace' );
check( 'replace overwrites differing, skips identical', array( 'project/CLAUDE.md' ) === $w2['written'] && array( 'memory/new_rule.md' ) === $w2['skipped'] && 'SITE VERSION' === file_get_contents( $proj2 . '/CLAUDE.md' ) );
$w3 = cc_brain_write_files( array( '../escape.md' => 'x', 'other/x.md' => 'y' ), $proj2, $home2, 'replace' );
check( 'refuses bad paths on write', 2 === count( $w3['refused'] ) && empty( $w3['written'] ) && ! is_file( $tmp . '/escape.md' ) );

/* ------------------------------------------------------------------ */
echo "\n-- 10. push protocol against a fake site: diff, chunking, delete, verify\n";
$GLOBALS['cc_test_options'] = array();
$calls = array();
$fake_rest = function ( $endpoint, $method = 'GET', $body = null, $params = array() ) use ( &$calls ) {
	$calls[] = array( $endpoint, $method, $body ? count( $body['files'] ) : 0, $body && isset( $body['delete'] ) ? count( $body['delete'] ) : 0 );
	if ( '/operator-kit/brain/status' === $endpoint ) {
		return CC_Assistant_REST_Operator_Kit::handle_brain_status( new CC_Brain_Test_Request() );
	}
	if ( '/operator-kit/brain' === $endpoint && 'POST' === $method ) {
		$r = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( $body ) );
		return is_wp_error( $r ) ? array( 'error' => $r->code ) : $r;
	}
	if ( '/operator-kit/brain' === $endpoint ) {
		return CC_Assistant_REST_Operator_Kit::handle_brain_get( new CC_Brain_Test_Request( array(), $params ) );
	}
	return array( 'error' => 'unexpected ' . $endpoint );
};
$selected = cc_brain_tool_push( $fake_rest, $proj, $home, 40, array( 'memory/feedback_x.md' ) );
$snapshot = CC_Assistant_REST_Operator_Kit::load_brain( true );
check( 'scoped push sends only exact selected files', 'selected_files_verified' === $selected['status'] && array( 'memory/feedback_x.md' ) === array_keys( $snapshot['files'] ) );
CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'merge', 'files' => array( 'memory/remote-only.md' => 'Preserve this independent correction.' ) ) ) );
$selected = cc_brain_tool_push( $fake_rest, $proj, $home, 40, array( 'memory/feedback_x.md' ) );
check( 'scoped sync preserves unselected remote files and verifies selected hashes', 0 === $selected['deleted'] && 'selected_files_verified' === $selected['status'] && isset( CC_Assistant_REST_Operator_Kit::load_brain( true )['files']['memory/remote-only.md'] ) );
$calls = array();
check( 'empty scope never falls back to full upload', 'scope_paths_required' === cc_brain_tool_push( $fake_rest, $proj, $home, 40, array() )['error'] && empty( $calls ) );
check( 'unknown path cannot widen the selection', 'scope_path_unavailable' === cc_brain_tool_push( $fake_rest, $proj, $home, 40, array( '../secrets' ) )['error'] && empty( $calls ) );
$GLOBALS['cc_test_options'] = array();
$p1 = cc_brain_tool_push( $fake_rest, $proj, $home, 40 ); // tiny chunk size forces several requests
check( 'first push to empty site ends in_sync', 'in_sync' === $p1['status'] && $p1['pushed'] === $local['file_count'] && $p1['chunks'] > 1, json_encode( $p1 ) );
$snapshot_before = CC_Assistant_REST_Operator_Kit::load_brain( true );
$GLOBALS['brain_omit_basis'] = true;
$bad = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'merge', 'files' => array( 'memory/a.md' => 'unsafe stale write' ) ) ) );
check( 'missing expected fingerprint is rejected', is_wp_error( $bad ) && 'brain_basis_required' === $bad->code );
$GLOBALS['brain_omit_basis'] = false;
$bad = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'merge', 'expected_fingerprint' => '', 'files' => array( 'memory/a.md' => 'unsafe stale write' ) ) ) );
check( 'stale fingerprint cannot overwrite a newer shared brain', is_wp_error( $bad ) && 'brain_fingerprint_changed' === $bad->code && $snapshot_before === CC_Assistant_REST_Operator_Kit::load_brain( true ) );
$GLOBALS['brain_lock_busy'] = true;
$bad = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'merge', 'files' => array( 'memory/a.md' => 'busy write' ) ) ) );
check( 'unavailable writer lock stops the update', is_wp_error( $bad ) && 'brain_store_busy' === $bad->code );
$GLOBALS['brain_lock_busy'] = false; $GLOBALS['brain_write_fail'] = true;
$bad = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'merge', 'files' => array( 'memory/a.md' => 'failed write' ) ) ) );
check( 'failed storage cannot claim successful synchronization', is_wp_error( $bad ) && 'brain_storage_failed' === $bad->code && $snapshot_before === CC_Assistant_REST_Operator_Kit::load_brain( true ) );
$GLOBALS['brain_write_fail'] = false;
$good_store = $GLOBALS['cc_test_options'][CC_Assistant_REST_Operator_Kit::OPT_BRAIN];
$GLOBALS['cc_test_options'][CC_Assistant_REST_Operator_Kit::OPT_BRAIN]['fingerprint'] = str_repeat( 'a', 64 );
$releases = $GLOBALS['brain_lock_releases'];
$bad = CC_Assistant_REST_Operator_Kit::handle_brain_post( new CC_Brain_Test_Request( array( 'mode' => 'merge', 'files' => array( 'memory/a.md' => 'must not erase corrupt data' ) ) ) );
check( 'corrupt stored content cannot be replaced by an ordinary push', is_wp_error( $bad ) && 'brain_storage_corrupt' === $bad->code );
check( 'failed integrity check releases the writer lock', $releases + 1 === $GLOBALS['brain_lock_releases'] );
$GLOBALS['cc_test_options'][CC_Assistant_REST_Operator_Kit::OPT_BRAIN] = $good_store;
$calls = array();
$p2 = cc_brain_tool_push( $fake_rest, $proj, $home );
check( 'second push is a no-op', 'in_sync' === $p2['status'] && 0 === $p2['pushed'] && 1 === count( $calls ) );
unlink( $memdir . '/reference_other.md' );
file_put_contents( $memdir . '/feedback_x.md', "changed" );
$p3 = cc_brain_tool_push( $fake_rest, $proj, $home );
check( 'third push sends only the changed file and deletes the removed one', 'in_sync' === $p3['status'] && 1 === $p3['pushed'] && 1 === $p3['deleted'] );
$stored = CC_Assistant_REST_Operator_Kit::load_brain( true );
check( 'site store reflects deletion and change', ! isset( $stored['files']['memory/reference_other.md'] ) && 'changed' === $stored['files']['memory/feedback_x.md'] );

$pull = cc_brain_tool_pull( $fake_rest, 'memory', 'missing_only', $tmp . '/proj3', $tmp . '/home3' );
check( 'pull memory writes only memory/ files', empty( $pull['error'] ) && $pull['written_count'] === count( array_filter( array_keys( $stored['files'] ), function ( $k ) { return 0 === strpos( $k, 'memory/' ); } ) ) );

/* ------------------------------------------------------------------ */
echo "\n-- 11. whoami decoration: two-way drift + verdict + rules\n";
$resp = array( 'site_url' => 'https://sids-ponds.com', 'site_name' => "Sid's Ponds", 'plugin_version' => '0.80.0', 'operator_brain' => CC_Assistant_REST_Operator_Kit::brain_summary(), 'bridge_hashes' => $tampered );
cc_brain_decorate_whoami( $resp, '0.79.0', $proj, $home );
check( 'stale laptop gets an explicit version_drift', isset( $resp['version_drift'] ) && false !== strpos( $resp['version_drift'], 'LOCAL BRIDGE IS STALE' ) );
check( 'operator_brain becomes a verdict', isset( $resp['operator_brain']['status'] ) && in_array( $resp['operator_brain']['status'], array( 'in_sync', 'local_newer', 'site_newer' ), true ) );
check( 'bridge verdict names pull action', 'differs' === $resp['operator_brain']['bridge']['status'] && false !== strpos( $resp['operator_brain']['bridge']['action'], 'operator_brain_pull' ) );
check( 'bridge_hashes removed from the response (noise)', ! isset( $resp['bridge_hashes'] ) );
check( 'relevant_rules attached with hard rule', isset( $resp['relevant_rules']['hard_rules'] ) );
$resp2 = array( 'site_url' => 'https://sids-ponds.com', 'plugin_version' => '0.79.0', 'operator_brain' => CC_Assistant_REST_Operator_Kit::brain_summary(), 'bridge_hashes' => $real_hashes );
cc_brain_decorate_whoami( $resp2, '0.79.0', $proj, $home );
check( 'equal versions: no drift warning added', ! isset( $resp2['version_drift'] ) && 'in_sync' === $resp2['operator_brain']['bridge']['status'] );
check( 'in_sync brain after push', 'in_sync' === $resp2['operator_brain']['status'], $resp2['operator_brain']['status'] );

rrmdir( $tmp );
echo "\n" . ( $fails ? "FAILED: $fails" : 'ALL PASS' ) . "\n";
exit( $fails ? 1 : 0 );
