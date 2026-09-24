<?php
/**
 * Plugin-setting writer: the guardrails are the product here. This tool can
 * reach into any plugin's configuration, so every refusal below is load-bearing
 * and each one has a concrete failure it prevents.
 * Run: php tests/setting-writer-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['_options'] = array(
	'wf_feed_productdata' => array(
		'feedrules' => array(
			'provider'           => 'google',
			'thousand_separator' => '',
			'decimals'           => '',
			'nested'             => array( 'deep' => 'value' ),
		),
	),
	'some_scalar_option'  => 'plain',
	'active_plugins'      => array( 'a/a.php' ),
	'acme_api_key'        => 'xyz',
);

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['_options'] ) ? $GLOBALS['_options'][ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$same = array_key_exists( $name, $GLOBALS['_options'] ) && $GLOBALS['_options'][ $name ] === $value;
		$GLOBALS['_options'][ $name ] = $value;
		return ! $same;
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message;
		public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $t ) { return $t instanceof WP_Error; }
}
// Deliberately NOT stubbed: the writer pulls its denylists from the real
// introspect class, so the refusal tests below exercise the actual core-option
// list and credential regex rather than a convenient copy that could drift
// away from what ships.
// $wpdb is only touched for the autoload lookup.
class _FakeWpdb {
	public $options = 'wp_options';
	public function prepare( $q, ...$a ) { return $q; }
	public function get_var( $q ) { return 'no'; }
}
$GLOBALS['wpdb'] = new _FakeWpdb();

require dirname( __DIR__ ) . '/includes/class-setting-writer.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( $extra ? " ($extra)" : '' ) . "\n"; }
}
function code( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : '(not an error)'; }

/* ---------------- refusals that protect the site ---------------- */
$r = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'active_plugins', 'value' => 'x' ) );
check( 'refuses a core WordPress option (active_plugins)', is_wp_error( $r ) && 'option_protected' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'acme_api_key', 'value' => 'x' ) );
check( 'refuses a credential-shaped option name', is_wp_error( $r ) && 'option_protected' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'cc_assistant_settings', 'value' => 'x' ) );
check( 'refuses to edit its own plugin options', is_wp_error( $r ) && 'option_protected' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'does_not_exist', 'value' => 'x' ) );
check( 'refuses an option that does not exist (never invents settings)', is_wp_error( $r ) && 'option_not_found' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array(
	'option_name' => 'wf_feed_productdata', 'path' => 'feedrules.typo_field', 'value' => 'x' ) );
check( 'refuses a path that does not exist (the silent no-op trap)', is_wp_error( $r ) && 'path_not_found' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array(
	'option_name' => 'wf_feed_productdata', 'path' => 'feedrules.nested', 'value' => 'x' ) );
check( 'refuses a path pointing at a nested structure, not a leaf', is_wp_error( $r ) && 'path_not_leaf' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array(
	'option_name' => 'wf_feed_productdata', 'path' => 'feedrules.decimals', 'value' => array( 1, 2 ) ) );
check( 'refuses a non-scalar value', is_wp_error( $r ) && 'value_not_scalar' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array(
	'option_name' => 'some_scalar_option', 'path' => 'a.b', 'value' => 'x' ) );
check( 'refuses a dot path on a scalar option', is_wp_error( $r ) && 'path_on_scalar' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array(
	'option_name' => 'wf_feed_productdata', 'path' => 'feedrules.provider', 'value' => 'google' ) );
check( 'refuses a no-op (value already set)', is_wp_error( $r ) && 'no_change' === code( $r ) );

$r = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'some_scalar_option' ) );
check( 'refuses a missing value argument', is_wp_error( $r ) && 'value_required' === code( $r ) );

/* ---------------- the happy path ---------------- */
$plan = CC_Assistant_Setting_Writer::build_plan( array(
	'option_name' => 'wf_feed_productdata', 'path' => 'feedrules.thousand_separator', 'value' => ' ' ) );
check( 'builds a plan for a real leaf', is_array( $plan ) && 'wf_feed_productdata' === $plan['option_name'], code( $plan ) );
check( 'plan captures the prior leaf value', is_array( $plan ) && '' === $plan['prior_value'] );
check( 'plan snapshots the WHOLE option for revert', is_array( $plan ) && isset( $plan['option_snapshot']['feedrules']['provider'] ) );

$ok = CC_Assistant_Setting_Writer::apply_plan( $plan );
check( 'apply succeeds', true === $ok, is_wp_error( $ok ) ? $ok->get_error_message() : '' );
check( 'apply wrote the leaf', ' ' === $GLOBALS['_options']['wf_feed_productdata']['feedrules']['thousand_separator'] );
check( 'apply left sibling keys intact', 'google' === $GLOBALS['_options']['wf_feed_productdata']['feedrules']['provider'] );
check( 'apply left nested structures intact', 'value' === $GLOBALS['_options']['wf_feed_productdata']['feedrules']['nested']['deep'] );

/* ---------------- revert restores everything ---------------- */
// Simulate the plugin having also changed something else in the meantime;
// revert is documented as restoring the WHOLE option, and that is what the
// snapshot guarantees.
$GLOBALS['_options']['wf_feed_productdata']['feedrules']['decimals'] = '2';
$rev = CC_Assistant_Setting_Writer::revert_plan( $plan );
check( 'revert refuses concurrent changes', 'setting_revert_conflict' === code( $rev ) );
check( 'newer setting survives refused rollback', '2' === $GLOBALS['_options']['wf_feed_productdata']['feedrules']['decimals'] );
$GLOBALS['_options']['wf_feed_productdata']['feedrules']['decimals'] = '';
$rev = CC_Assistant_Setting_Writer::revert_plan( $plan );
check( 'revert succeeds', true === $rev );
check( 'revert restores the edited leaf', '' === $GLOBALS['_options']['wf_feed_productdata']['feedrules']['thousand_separator'] );
check( 'revert restores the whole option from snapshot', '' === $GLOBALS['_options']['wf_feed_productdata']['feedrules']['decimals'] );

$rev = CC_Assistant_Setting_Writer::revert_plan( array( 'option_name' => 'wf_feed_productdata' ) );
check( 'revert without a snapshot refuses rather than guessing', is_wp_error( $rev ) && 'no_snapshot' === code( $rev ) );

/* ---------------- apply re-checks guardrails at approval time ---------------- */
$evil = array( 'option_name' => 'active_plugins', 'value' => array(), 'option_snapshot' => array() );
$r = CC_Assistant_Setting_Writer::apply_plan( $evil );
check( 'apply re-refuses a protected option even if a row was crafted', is_wp_error( $r ) && 'option_protected' === code( $r ) );

echo "\n";
if ( $fails ) { echo "$fails FAILED\n"; exit( 1 ); }
echo "All setting-writer tests passed.\n";
