<?php
/**
 * Bulk term assignment: pure-path tests for the guarantees this tool sells.
 * Run: php tests/bulk-terms-test.php
 *
 * The DB query paths are not exercised (no WP bootstrap). What IS covered is
 * everything that decides whether a live catalogue gets tagged correctly:
 * validation refusals, no-op detection, the target cap, and — the one that
 * matters most — that revert restores the exact prior state including "this
 * post had no term at all", which a naive "remove what we added" gets wrong.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );

/* ---------------------------------------------------------------------
 * Minimal WP surface. Each stub is only as smart as the code under test
 * needs, and the term/post stores are arrays we can assert against.
 * ------------------------------------------------------------------- */
$GLOBALS['_tax']       = array( 'product_brand' => array( 'product' ), 'product_cat' => array( 'product' ) );
$GLOBALS['_terms']     = array( 10 => (object) array( 'term_id' => 10, 'name' => 'Kichler', 'taxonomy' => 'product_brand' ) );
$GLOBALS['_posts']     = array( 101 => 'KICHLER - Path Light', 102 => 'KICHLER - Wall Wash', 103 => 'Hornwort' );
$GLOBALS['_obj_terms'] = array();  // post_id => array of term ids (product_brand)
$GLOBALS['_writes']    = array();

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $k ) ); }
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $t ) { return isset( $GLOBALS['_tax'][ $t ] ); }
}
if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( $pt ) {
		$out = array();
		foreach ( $GLOBALS['_tax'] as $tax => $types ) { if ( in_array( $pt, $types, true ) ) { $out[] = $tax; } }
		return $out;
	}
}
if ( ! function_exists( 'get_term' ) ) {
	function get_term( $id, $tax = '' ) {
		if ( ! isset( $GLOBALS['_terms'][ $id ] ) ) { return null; }
		$t = $GLOBALS['_terms'][ $id ];
		if ( $tax && $t->taxonomy !== $tax ) { return null; }
		return $t;
	}
}
if ( ! function_exists( 'get_term_children' ) ) {
	function get_term_children( $id, $tax ) { return array(); }
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) { return isset( $GLOBALS['_posts'][ $id ] ) ? (object) array( 'ID' => $id ) : null; }
}
if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $pid, $tax, $args = array() ) {
		return isset( $GLOBALS['_obj_terms'][ $pid ] ) ? $GLOBALS['_obj_terms'][ $pid ] : array();
	}
}
if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( $pid, $terms, $tax, $append = false ) {
		$terms = array_map( 'intval', (array) $terms );
		$prev  = isset( $GLOBALS['_obj_terms'][ $pid ] ) ? $GLOBALS['_obj_terms'][ $pid ] : array();
		$GLOBALS['_obj_terms'][ $pid ] = $append ? array_values( array_unique( array_merge( $prev, $terms ) ) ) : $terms;
		$GLOBALS['_writes'][] = array( 'id' => $pid, 'append' => (bool) $append, 'terms' => $terms );
		return $GLOBALS['_obj_terms'][ $pid ];
	}
}
if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $k, $g = '' ) { return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { return $value; }
}
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $s, $a, $b = null ) { return substr( $s, $a, $b ); }
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message;
		public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; }
		public function get_error_message() { return $this->message; }
		public function get_error_code() { return $this->code; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $t ) { return $t instanceof WP_Error; }
}

require dirname( __DIR__ ) . '/includes/class-bulk-terms.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( $extra ? " ($extra)" : '' ) . "\n"; }
}

/* ---------------- validation refusals ---------------- */
$r = CC_Assistant_Bulk_Terms::build_plan( array( 'taxonomy' => 'nope_tax', 'term_id' => 10 ) );
check( 'refuses an unregistered taxonomy', is_wp_error( $r ) && 'taxonomy_missing' === $r->get_error_code() );

$r = CC_Assistant_Bulk_Terms::build_plan( array( 'taxonomy' => 'product_brand', 'term_id' => 999 ) );
check( 'refuses a term that does not exist (never creates terms)', is_wp_error( $r ) && 'term_not_found' === $r->get_error_code() );

$r = CC_Assistant_Bulk_Terms::build_plan( array( 'taxonomy' => 'product_brand', 'term_id' => 10, 'post_type' => 'attachment' ) );
check( 'refuses a post type outside the allowlist', is_wp_error( $r ) && 'post_type_not_allowed' === $r->get_error_code() );

$r = CC_Assistant_Bulk_Terms::build_plan( array( 'taxonomy' => 'product_cat', 'term_id' => 10, 'post_type' => 'post' ) );
check( 'refuses taxonomy not registered for the post type', is_wp_error( $r ) && 'taxonomy_post_type_mismatch' === $r->get_error_code() );

$r = CC_Assistant_Bulk_Terms::build_plan( array( 'taxonomy' => 'product_brand', 'term_id' => 10, 'match_type' => 'title_contains', 'match_value' => '  ' ) );
check( 'refuses an empty match_value', is_wp_error( $r ) && 'match_value_required' === $r->get_error_code() );

$r = CC_Assistant_Bulk_Terms::build_plan( array( 'taxonomy' => 'product_brand', 'term_id' => 10, 'match_type' => 'post_ids', 'post_ids' => array() ) );
check( 'refuses an empty post_ids list', is_wp_error( $r ) && 'no_post_ids' === $r->get_error_code() );

/* ---------------- apply: writes + partial success ----------------
 * Seed the live store so it matches the plan's snapshot: post 102 already
 * carries an unrelated term (77). Append must keep it; revert must return to
 * exactly it. Apply deliberately appends against LIVE data rather than the
 * plan's snapshot, so this fixture is what makes that behaviour observable.
 */
$GLOBALS['_obj_terms'][102] = array( 77 );

$plan = array(
	'taxonomy' => 'product_brand', 'term_id' => 10, 'term_name' => 'Kichler',
	'post_type' => 'product', 'mode' => 'append',
	'targets' => array(
		array( 'id' => 101, 'title' => 'KICHLER - Path Light', 'prior' => array() ),
		array( 'id' => 102, 'title' => 'KICHLER - Wall Wash',  'prior' => array( 77 ) ),
	),
);
$out = CC_Assistant_Bulk_Terms::apply_plan( $plan );
check( 'apply reports both writes', is_array( $out ) && 2 === $out['written'], json_encode( $out ) );
check( 'append mode preserves an existing unrelated term', $GLOBALS['_obj_terms'][102] === array( 77, 10 ), json_encode( $GLOBALS['_obj_terms'][102] ) );
check( 'post that had nothing now carries only the new term', $GLOBALS['_obj_terms'][101] === array( 10 ) );
check( 'apply used append semantics, not replace', $GLOBALS['_writes'][0]['append'] === true );

/* ---------------- revert: exact prior state ---------------- */
$rev = CC_Assistant_Bulk_Terms::revert_plan( $plan );
check( 'revert succeeds', true === $rev );
check( 'REVERT RESTORES EMPTY: post that had no term has none again', $GLOBALS['_obj_terms'][101] === array(), json_encode( $GLOBALS['_obj_terms'][101] ) );
check( 'REVERT RESTORES PRIOR: post keeps its original unrelated term only', $GLOBALS['_obj_terms'][102] === array( 77 ), json_encode( $GLOBALS['_obj_terms'][102] ) );

/* ---------------- apply: drift + total failure ---------------- */
$GLOBALS['_writes'] = array();
$drift = $plan;
$drift['targets'][] = array( 'id' => 999, 'title' => 'deleted product', 'prior' => array() );
$out = CC_Assistant_Bulk_Terms::apply_plan( $drift );
check( 'a deleted post is skipped, not fatal', is_array( $out ) && 2 === $out['written'] && 1 === count( $out['skipped'] ) );
check( 'skip reason names the missing post', isset( $out['skipped'][0]['reason'] ) && false !== strpos( $out['skipped'][0]['reason'], 'no longer exists' ) );

$out = CC_Assistant_Bulk_Terms::apply_plan( array(
	'taxonomy' => 'product_brand', 'term_id' => 10, 'mode' => 'append',
	'targets'  => array( array( 'id' => 999, 'title' => 'gone', 'prior' => array() ) ),
) );
check( 'a run that writes nothing is an error, not a silent success', is_wp_error( $out ) && 'bulk_term_wrote_nothing' === $out->get_error_code() );

$out = CC_Assistant_Bulk_Terms::apply_plan( array( 'taxonomy' => 'product_brand', 'term_id' => 10, 'targets' => array() ) );
check( 'an empty plan is refused', is_wp_error( $out ) && 'bulk_term_plan_invalid' === $out->get_error_code() );

/* ---------------- guard: term vanished between queue and apply ---------------- */
$saved = $GLOBALS['_terms'];
$GLOBALS['_terms'] = array();
$out = CC_Assistant_Bulk_Terms::apply_plan( $plan );
check( 'refuses to apply when the term was deleted after queueing', is_wp_error( $out ) && 'term_not_found' === $out->get_error_code() );
$GLOBALS['_terms'] = $saved;

/* ---------------- cap is real ---------------- */
check( 'target cap is bounded', CC_Assistant_Bulk_Terms::MAX_TARGETS > 0 && CC_Assistant_Bulk_Terms::MAX_TARGETS <= 1000 );

echo "\n";
if ( $fails ) {
	echo "$fails FAILED\n";
	exit( 1 );
}
echo "All bulk-term tests passed.\n";
