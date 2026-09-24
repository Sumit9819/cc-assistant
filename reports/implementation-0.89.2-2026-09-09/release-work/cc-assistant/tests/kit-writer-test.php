<?php
/**
 * Kit writer: path grammar over Elementor's list-of-rows registries, colour
 * validation, and the plan/apply/revert round trip against an in-memory kit.
 *
 * Pinned because the failure mode is silent: a mistyped path stores a key
 * Elementor never reads and the colour "changes" in the inbox but not on
 * the site (the same trap as draft_update_plugin_setting).
 *
 * Run: php tests/kit-writer-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );

class WP_Error { public $code; private $m; public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->m = $m; } public function get_error_message() { return $this->m; } public function get_error_code() { return $this->code; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
if ( ! function_exists( 'mb_substr' ) ) { function mb_substr( $s, $a, $l = null ) { return null === $l ? substr( $s, $a ) : substr( $s, $a, $l ); } }

$STORE = array( 'kit' => 77, 'meta' => array() );
function get_option( $k, $d = null ) { global $STORE; return 'elementor_active_kit' === $k ? $STORE['kit'] : $d; }
function get_post_meta( $id, $key, $single ) { global $STORE; return $id === $STORE['kit'] ? $STORE['meta'] : ''; }
function update_post_meta( $id, $key, $val ) { global $STORE; if ( $id === $STORE['kit'] ) { $STORE['meta'] = $val; return true; } return false; }

require CC_ASSISTANT_DIR . 'includes/class-kit-writer.php';
$K = 'CC_Assistant_Kit_Writer';

$fails = 0;
function check( $label, $cond, $detail = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n"; }
}

$meta = array(
	'system_colors' => array(
		array( '_id' => 'primary', 'title' => 'Primary', 'color' => '#DA1212' ),
		array( '_id' => 'secondary', 'title' => 'Secondary', 'color' => '#11468F' ),
		array( '_id' => 'text', 'title' => 'Text', 'color' => '#041562' ),
	),
	'custom_colors' => array(
		array( '_id' => '5a38648', 'title' => 'Brand White', 'color' => '#FFFFFF' ),
	),
	'system_typography' => array(
		array( '_id' => 'primary', 'title' => 'Primary', 'typography_typography' => 'custom', 'typography_font_weight' => '600' ),
	),
	'body_color'  => '#333333',
	'container_width' => array( 'unit' => 'px', 'size' => 1200 ),
);
$STORE['meta'] = $meta;

echo "--- path resolution ---\n";
$r = $K::resolve_path( $meta, 'system_colors.primary.color' );
check( 'list row by _id', $r['found'] && '#DA1212' === $r['value'] && array( 'system_colors', 0, 'color' ) === $r['keys'], json_encode( $r ) );
$r = $K::resolve_path( $meta, 'custom_colors.brand white.color' );
check( 'list row by title, case-insensitive', $r['found'] && '#FFFFFF' === $r['value'] );
$r = $K::resolve_path( $meta, 'system_colors.1.color' );
check( 'list row by index', $r['found'] && '#11468F' === $r['value'] );
$r = $K::resolve_path( $meta, 'system_colors.accent.color' );
check( 'unknown row is not found', ! $r['found'] );
$r = $K::resolve_path( $meta, 'container_width.size' );
check( 'assoc path resolves', $r['found'] && 1200 === $r['value'] );
$r = $K::resolve_path( $meta, 'link_normal_color' );
check( 'absent scalar not found', ! $r['found'] );

echo "--- colour validation ---\n";
check( 'hex6 valid', $K::is_valid_color( '#D01010' ) );
check( 'hex3 valid', $K::is_valid_color( '#fff' ) );
check( 'rgba valid', $K::is_valid_color( 'rgba(0, 46, 22, 0.5)' ) );
check( 'blank valid (reset)', $K::is_valid_color( '' ) );
check( 'word rejected', ! $K::is_valid_color( 'red' ) );
check( 'hex5 rejected', ! $K::is_valid_color( '#12345' ) );
check( 'color path detection', $K::is_color_path( 'system_colors.primary.color' ) && $K::is_color_path( 'link_normal_color' ) && ! $K::is_color_path( 'container_width.size' ) );

echo "--- planning ---\n";
$p = $K::plan_from_meta( $meta, 'system_colors.primary.color', '#D01010' );
check( 'plan on existing colour', is_array( $p ) && '#DA1212' === $p['prior_value'] && ! $p['created_path'] && array( 'system_colors', 0, 'color' ) === $p['resolved_keys'], is_wp_error( $p ) ? $p->get_error_message() : json_encode( $p['resolved_keys'] ) );
check( 'snapshot carries the whole kit', is_array( $p ) && $p['meta_snapshot'] === $meta );
$p = $K::plan_from_meta( $meta, 'system_colors.primary.color', 'crimson' );
check( 'invalid colour refused', is_wp_error( $p ) && 'invalid_color' === $p->get_error_code() );
$p = $K::plan_from_meta( $meta, 'link_normal_color', '#11468F' );
check( 'missing key refused without allow_create', is_wp_error( $p ) && 'path_not_found' === $p->get_error_code() );
$p = $K::plan_from_meta( $meta, 'link_normal_color', '#11468F', true );
check( 'missing key created with allow_create', is_array( $p ) && $p['created_path'] && null === $p['prior_value'] );
$p = $K::plan_from_meta( $meta, 'system_colors.primary', '#000000' );
check( 'row (not leaf) refused', is_wp_error( $p ) && 'path_not_leaf' === $p->get_error_code() );
$p = $K::plan_from_meta( $meta, 'system_colors.primary.color', '#DA1212' );
check( 'no-change refused', is_wp_error( $p ) && 'no_change' === $p->get_error_code() );
$p = $K::plan_from_meta( $meta, 'system_colors.nope.color', '#000000', true );
check( 'allow_create never invents a list row', is_wp_error( $p ) && 'path_not_found' === $p->get_error_code(), is_wp_error( $p ) ? $p->get_error_code() : 'planned' );
$p = $K::plan_from_meta( $meta, '', '#000000' );
check( 'empty path refused', is_wp_error( $p ) && 'path_required' === $p->get_error_code() );

echo "--- apply / revert round trip ---\n";
$plan = $K::build_plan( array( 'path' => 'system_colors.primary.color', 'value' => '#D01010' ) );
check( 'build_plan attaches kit id', is_array( $plan ) && 77 === $plan['kit_id'] );
$ok = $K::apply_plan( $plan );
check( 'apply writes the row', true === $ok && '#D01010' === $STORE['meta']['system_colors'][0]['color'], is_wp_error( $ok ) ? $ok->get_error_message() : '' );
check( 'apply leaves siblings alone', '#11468F' === $STORE['meta']['system_colors'][1]['color'] && '#333333' === $STORE['meta']['body_color'] );
$plan2 = $K::build_plan( array( 'path' => 'link_normal_color', 'value' => '#11468F', 'allow_create' => true ) );
$ok2   = $K::apply_plan( $plan2 );
check( 'apply creates an allowed new leaf', true === $ok2 && '#11468F' === $STORE['meta']['link_normal_color'] );
$rv = $K::revert_plan( $plan2 );
check( 'revert restores the exact prior blob', true === $rv && ! array_key_exists( 'link_normal_color', $STORE['meta'] ) && '#D01010' === $STORE['meta']['system_colors'][0]['color'] );
$rv = $K::revert_plan( $plan );
check( 'revert of first plan restores original colour', true === $rv && '#DA1212' === $STORE['meta']['system_colors'][0]['color'] );
$STORE['kit'] = 78;
$bad = $K::apply_plan( $plan );
check( 'apply refuses when the active kit changed', is_wp_error( $bad ) && 'kit_changed' === $bad->get_error_code() );
$STORE['kit'] = 77;

echo "--- describe ---\n";
$d = $K::describe( 77, $meta );
check( 'describe lists colour paths', 'system_colors.primary.color' === $d['colors']['system_colors'][0]['path'] && 'custom_colors.5a38648.color' === $d['colors']['custom_colors'][0]['path'] );
check( 'describe exposes scalars and array keys', '#333333' === $d['scalars']['body_color'] && isset( $d['array_keys']['container_width'] ) );
check( 'describe levers null when unset', array_key_exists( 'link_normal_color', $d['levers'] ) && null === $d['levers']['link_normal_color'] );

echo "\n" . ( $fails ? "$fails FAILURES\n" : "ALL PASS\n" );
exit( $fails ? 1 : 0 );
