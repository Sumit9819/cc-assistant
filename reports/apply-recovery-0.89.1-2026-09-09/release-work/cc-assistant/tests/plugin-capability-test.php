<?php
namespace SiteGround_Optimizer\Rest { class Rest { public static $toggle_options = array( 'lazyload_images', 'optimize_css' ); } }
namespace RankMath { class Helper { public static function is_module_active( $id ) { return 'schema' === $id; } } }
namespace {
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' ); define( 'ARRAY_A', 'ARRAY_A' ); define( 'WP_PLUGIN_DIR', __DIR__ . '/nonexistent-fixture-plugins' );
class WP_Error { public $code; public $message; public function __construct( $c, $m = '', $d = null ) { $this->code = $c; $this->message = $m; } public function get_error_code() { return $this->code; } public function get_error_message() { return $this->message; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $s ) ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function maybe_unserialize( $s ) { return unserialize( $s ); }
function get_plugins() { $out = array(); foreach ( array( 'repair-plugin','sg-cachepress','seo-by-rank-math','polylang','elementor' ) as $name ) { $out[$name . '/plugin.php'] = array( 'Name' => $name, 'Version' => '1.2.3', 'TextDomain' => $name ); } return $out; }
function is_plugin_active( $s ) { return empty( $GLOBALS['inactive'] ); }
function get_registered_settings() { return $GLOBALS['registered']; }
function get_option( $s, $default = false ) { return $GLOBALS['options'][$s] ?? $default; }
function rest_validate_value_from_schema( $v, $s, $param ) { return isset( $s['enum'] ) && ! in_array( $v, $s['enum'], true ) ? new WP_Error( 'invalid_enum', 'Unsupported enum value.' ) : true; }
function rank_math() { return (object) array( 'manager' => (object) array( 'modules' => array( 'schema' => array(), 'analytics' => array() ) ) ); }
function pll_languages_list( $args ) { return array( 'en','es' ); }
function pll_get_post_translations( $id ) { return array( 'en' => $id, 'es' => 22 ); }
function did_action( $name ) { return 0; }
class CapabilityDB { public $options = 'wp_options'; public function esc_like( $s ) { return $s; } public function prepare( $sql, ...$args ) { return $sql; } public function get_results( $sql, $mode ) { return array( array( 'option_name' => 'repair_options', 'option_value' => serialize( $GLOBALS['options']['repair_options'] ), 'autoload' => 'no' ) ); } }
$GLOBALS['wpdb'] = new CapabilityDB(); $GLOBALS['options'] = array( 'repair_options' => array( 'layout' => 'grid', 'api_key' => 'do-not-expose' ) );
$GLOBALS['registered'] = array( 'repair_options' => array( 'show_in_rest' => array( 'schema' => array( 'type' => 'object', 'properties' => array( 'layout' => array( 'type' => 'string', 'enum' => array( 'grid','list' ) ), 'api_key' => array( 'type' => 'string', 'default' => 'do-not-expose' ) ) ) ) ) );
require CC_ASSISTANT_DIR . 'includes/class-plugin-capability.php';
function check( $label, $v ) { if ( ! $v ) { throw new \RuntimeException( $label ); } echo "PASS $label\n"; }
$r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'repair-plugin', 'option_name' => 'repair_options', 'path' => 'layout', 'proposed_value' => 'orbit' ) );
check( 'exact registry enum rejects a guessed value without mutation', 'rejected' === $r['controls'][0]['validation']['status'] && 'grid' === $GLOBALS['options']['repair_options']['layout'] );
check( 'active plugin version and observation time exposed', '1.2.3' === $r['version'] && $r['active'] && ! empty( $r['captured_at_utc'] ) );
$r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'repair-plugin', 'option_name' => 'repair_options' ) );
check( 'stored secrets and schema defaults stay private', false === strpos( json_encode( $r ), 'do-not-expose' ) );
check( 'credential paths are denied', is_wp_error( CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'repair-plugin', 'option_name' => 'repair_options', 'path' => 'api_key' ) ) ) );
check( 'unobserved option is rejected', is_wp_error( CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'repair-plugin', 'option_name' => 'invented' ) ) ) );
$GLOBALS['registered'] = array(); $r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'repair-plugin', 'option_name' => 'repair_options', 'path' => 'layout' ) );
check( 'stored value does not manufacture accepted values', 'unknown' === $r['controls'][0]['accepted_values_status'] );
$GLOBALS['inactive'] = true; $r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'repair-plugin' ) );
check( 'inactive plugins cannot advertise live controls', 'plugin_not_active' === $r['coverage'] && empty( $r['controls'] ) ); $GLOBALS['inactive'] = false;
$r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'sg-cachepress' ) );
check( 'SiteGround reports actual runtime toggle registry only', array( 'lazyload_images','optimize_css' ) === $r['native_toggle_routes'] && 'unverified' === $r['feature_effect'] );
$r = CC_Assistant_Setting_Writer::validate_registered_value( 'siteground_optimizer_optimize_css', '', 1 );
check( 'native side effects cannot be faked by direct storage writes', 'setting_native_apply_required' === $r->get_error_code() );
$r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'seo-by-rank-math' ) );
check( 'Rank Math registration and activation are distinct', $r['registered_modules'][0]['active'] && ! $r['registered_modules'][1]['active'] );
$r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'polylang', 'post_id' => 11 ) );
check( 'Polylang reads actual language graph', 22 === $r['translations']['es'] );
$r = CC_Assistant_Plugin_Capability::inspect( array( 'slug' => 'elementor', 'widget_type' => 'invented' ) );
check( 'unavailable widget runtime returns explicit error evidence', 'unavailable' === $r['widget_registry']['status'] );
echo "All plugin capability checks passed.\n";
}
