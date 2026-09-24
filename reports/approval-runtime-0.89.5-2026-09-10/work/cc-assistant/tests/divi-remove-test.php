<?php
/**
 * Divi module removal. The guards are the product here: this endpoint deletes
 * layout content, so every refusal below prevents a specific way of quietly
 * destroying a page. Pure-path — parse_modules() and the splice/verify logic
 * need no WordPress.
 * Run: php tests/divi-remove-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $t ) { return $t instanceof WP_Error; }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
// WordPress guarantees mb_substr even without the mbstring extension — it
// polyfills it in wp-includes/compat.php. This CLI has no mbstring, so the
// stub stands in for that polyfill rather than papering over a real gap.
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $str, $start, $length = null, $encoding = null ) {
		return null === $length ? substr( $str, $start ) : substr( $str, $start, $length );
	}
}

require dirname( __DIR__ ) . '/includes/class-rest-divi.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( $extra ? " ($extra)" : '' ) . "\n"; }
}

/* ---------------------------------------------------------------
 * A body shaped like the real one this was built for: an empty
 * difl_dual_button sitting between a code module and add-to-cart.
 * --------------------------------------------------------------- */
$body =
'[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"]' .
'[et_pb_code]Go to Wishlist[/et_pb_code]' .
'[difl_dual_button btn_left_icon_color="#fff"][/difl_dual_button]' .
'[et_pb_wc_add_to_cart][/et_pb_wc_add_to_cart]' .
'[et_pb_text]Keep me[/et_pb_text]' .
'[/et_pb_column][/et_pb_row][/et_pb_section]';

$mods = CC_Assistant_REST_Divi::parse_modules( $body );
check( 'parses the fixture', 7 === count( $mods ), count( $mods ) . ' modules' );

$byType = array();
foreach ( $mods as $m ) { $byType[ $m['type'] ] = $m; }
check( 'finds the empty dual button', isset( $byType['difl_dual_button'] ) );
$dual = $byType['difl_dual_button'];
check( 'dual button has no inner content', $dual['inner_end'] === $dual['inner_start'] );
check( 'dual button is not structural', false === $dual['structural'] );
check( 'section IS structural', true === $byType['et_pb_section']['structural'] );

/* ---------------- the splice itself ---------------- */
$rebuilt = substr( $body, 0, $dual['open_start'] ) . substr( $body, $dual['close_end'] );
$reparsed = CC_Assistant_REST_Divi::parse_modules( $rebuilt );

check( 'removal drops exactly one module', count( $reparsed ) === count( $mods ) - 1, count( $reparsed ) . ' left' );
check( 'dual button is gone', false === strpos( $rebuilt, 'difl_dual_button' ) );
check( 'neighbour BEFORE survives intact', false !== strpos( $rebuilt, '[et_pb_code]Go to Wishlist[/et_pb_code]' ) );
check( 'neighbour AFTER survives intact', false !== strpos( $rebuilt, '[et_pb_wc_add_to_cart][/et_pb_wc_add_to_cart]' ) );
check( 'unrelated text module survives', false !== strpos( $rebuilt, 'Keep me' ) );
check( 'section wrapper still closed', false !== strpos( $rebuilt, '[/et_pb_section]' ) );

$surviving = array();
foreach ( $mods as $m ) { if ( 'difl_dual_button' !== $m['type'] ) { $surviving[] = $m['type']; } }
$after = array();
foreach ( $reparsed as $m ) { $after[] = $m['type']; }
check( 'surviving type sequence is unchanged', $surviving === $after, implode( ',', $after ) );

/* ---------------- nested subtree accounting ---------------- */
$nested =
'[et_pb_section][et_pb_row][et_pb_column type="4_4"]' .
'[difl_advancedtab][difl_advancedtabitem title="Specs"][/difl_advancedtabitem]' .
'[difl_advancedtabitem title="Install"][/difl_advancedtabitem][/difl_advancedtab]' .
'[et_pb_text]after[/et_pb_text]' .
'[/et_pb_column][/et_pb_row][/et_pb_section]';

$nmods = CC_Assistant_REST_Divi::parse_modules( $nested );
$tab = null;
foreach ( $nmods as $m ) { if ( 'difl_advancedtab' === $m['type'] ) { $tab = $m; } }
check( 'finds the tab container', null !== $tab );

$kids = 0;
foreach ( $nmods as $m ) {
	if ( $m['index'] === $tab['index'] ) { continue; }
	if ( $m['open_start'] >= $tab['open_start'] && $m['close_end'] <= $tab['close_end'] ) { $kids++; }
}
check( 'counts the 2 nested tab items (the confirm_children trigger)', 2 === $kids, "$kids found" );

$nrebuilt  = substr( $nested, 0, $tab['open_start'] ) . substr( $nested, $tab['close_end'] );
$nreparsed = CC_Assistant_REST_Divi::parse_modules( $nrebuilt );
check( 'removing a parent removes 1 + children', count( $nreparsed ) === count( $nmods ) - 3, count( $nreparsed ) . ' left' );
check( 'no orphaned child tag left behind', false === strpos( $nrebuilt, 'difl_advancedtabitem' ) );
check( 'sibling after the subtree survives', false !== strpos( $nrebuilt, 'after' ) );

/* ---------------- the structural refusal matters ---------------- */
$col = null;
foreach ( $mods as $m ) { if ( 'et_pb_column' === $m['type'] ) { $col = $m; } }
$colKids = 0;
foreach ( $mods as $m ) {
	if ( $m['index'] === $col['index'] ) { continue; }
	if ( $m['open_start'] >= $col['open_start'] && $m['close_end'] <= $col['close_end'] ) { $colKids++; }
}
check( 'a column contains the whole content subtree (why removal is refused)', $colKids >= 4, "$colKids children" );

/* ===============================================================
 * HANDLER-LEVEL tests. The splice logic above is only half the
 * story; these drive handle_remove_module() itself so every guard
 * actually executes, with the WordPress surface stubbed.
 * =============================================================== */

class _FakeReq {
	private $p;
	public function __construct( $p ) { $this->p = $p; }
	public function get_param( $k ) { return array_key_exists( $k, $this->p ) ? $this->p[ $k ] : null; }
	public function set_param( $k, $v ) { $this->p[ $k ] = $v; }
	public function all() { return $this->p; }
}
class_alias( '_FakeReq', 'WP_REST_Request' );

$GLOBALS['_posts'] = array();
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		return isset( $GLOBALS['_posts'][ $id ] ) ? $GLOBALS['_posts'][ $id ] : null;
	}
}
// Captures the rebuilt body the handler hands downstream.
class CC_Assistant_REST_API {
	public static $last_content = null;
	public static function handle_draft_post_content( $req ) {
		self::$last_content = $req->get_param( 'content' );
		return array( 'data' => array( 'queued' => true ) );
	}
}

$GLOBALS['_posts'][777] = (object) array( 'ID' => 777, 'post_status' => 'publish', 'post_content' => $body );
$GLOBALS['_posts'][778] = (object) array( 'ID' => 778, 'post_status' => 'publish', 'post_content' => $nested );
$GLOBALS['_posts'][779] = (object) array( 'ID' => 779, 'post_status' => 'publish', 'post_content' => '<p>classic post, no Divi</p>' );

$dualIdx = null; $colIdx = null; $tabIdx = null;
foreach ( $mods as $m )  { if ( 'difl_dual_button' === $m['type'] ) { $dualIdx = $m['index']; } if ( 'et_pb_column' === $m['type'] ) { $colIdx = $m['index']; } }
foreach ( $nmods as $m ) { if ( 'difl_advancedtab' === $m['type'] ) { $tabIdx = $m['index']; } }

$call = function ( $params ) {
	CC_Assistant_REST_API::$last_content = null;
	return CC_Assistant_REST_Divi::handle_remove_module( new _FakeReq( $params ) );
};
function code( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : '(not an error)'; }

$r = $call( array( 'post_id' => 779, 'index' => 0, 'type' => 'et_pb_text' ) );
check( 'refuses a non-Divi post', is_wp_error( $r ) && 'not_divi' === code( $r ), code( $r ) );

$r = $call( array( 'post_id' => 777, 'type' => 'difl_dual_button' ) );
check( 'refuses a missing index', is_wp_error( $r ) && 'index_required' === code( $r ), code( $r ) );

$r = $call( array( 'post_id' => 777, 'index' => $dualIdx ) );
check( 'refuses a missing type (stale-inventory guard)', is_wp_error( $r ) && 'type_required' === code( $r ), code( $r ) );

$r = $call( array( 'post_id' => 777, 'index' => 999, 'type' => 'difl_dual_button' ) );
check( 'refuses an out-of-range index', is_wp_error( $r ) && 'module_not_found' === code( $r ), code( $r ) );

$r = $call( array( 'post_id' => 777, 'index' => $dualIdx, 'type' => 'et_pb_text' ) );
check( 'refuses a type mismatch', is_wp_error( $r ) && 'module_type_mismatch' === code( $r ), code( $r ) );

$r = $call( array( 'post_id' => 777, 'index' => $colIdx, 'type' => 'et_pb_column' ) );
check( 'refuses removing a COLUMN', is_wp_error( $r ) && 'structural_removal_refused' === code( $r ), code( $r ) );

$r = $call( array( 'post_id' => 778, 'index' => $tabIdx, 'type' => 'difl_advancedtab' ) );
check( 'refuses a parent with children unless confirmed', is_wp_error( $r ) && 'module_has_children' === code( $r ), code( $r ) );

$r = $call( array( 'post_id' => 778, 'index' => $tabIdx, 'type' => 'difl_advancedtab', 'confirm_children' => true ) );
check( 'confirm_children lets the subtree go', ! is_wp_error( $r ), code( $r ) );
check( '  and the children really left the body', is_string( CC_Assistant_REST_API::$last_content ) && false === strpos( CC_Assistant_REST_API::$last_content, 'difl_advancedtabitem' ) );

// index 0 must be usable — a falsy-but-valid value that `empty()` would eat.
$firstNonStructural = null;
foreach ( $mods as $m ) { if ( ! $m['structural'] ) { $firstNonStructural = $m; break; } }
$r = $call( array( 'post_id' => 777, 'index' => $firstNonStructural['index'], 'type' => $firstNonStructural['type'] ) );
check( 'index ' . $firstNonStructural['index'] . ' (first non-structural) is accepted', ! is_wp_error( $r ), code( $r ) );

/* ---------------- the happy path, end to end ---------------- */
$r = $call( array( 'post_id' => 777, 'index' => $dualIdx, 'type' => 'difl_dual_button' ) );
check( 'removes the empty module', ! is_wp_error( $r ), code( $r ) );
$out = is_array( $r ) && isset( $r['data']['divi_module_removed'] ) ? $r['data']['divi_module_removed'] : null;
check( 'reports what it removed', is_array( $out ) && 'difl_dual_button' === $out['type'] );
check( 'reports zero nested modules', is_array( $out ) && array() === $out['nested_removed'] );
check( 'reports the module held no text', is_array( $out ) && false === $out['had_inner_text'] );
check( 'reports the before/after counts', is_array( $out ) && $out['modules_before'] === $out['modules_after'] + 1 );
check( 'handed downstream a body without the module', is_string( CC_Assistant_REST_API::$last_content ) && false === strpos( CC_Assistant_REST_API::$last_content, 'difl_dual_button' ) );
check( 'downstream body still contains the neighbours', false !== strpos( CC_Assistant_REST_API::$last_content, 'Go to Wishlist' ) && false !== strpos( CC_Assistant_REST_API::$last_content, 'Keep me' ) );

echo "\n";
if ( $fails ) { echo "$fails FAILED\n"; exit( 1 ); }
echo "All divi-remove tests passed.\n";
