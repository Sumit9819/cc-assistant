<?php
namespace SiteGround_Optimizer\Options {
    class Options { public static function change_option( $key, $value ) {
        $GLOBALS['native_calls']++;
        if ( empty( $GLOBALS['write_failure'] ) ) { $GLOBALS['options'][$key] = $value; }
        return empty( $GLOBALS['write_failure'] );
    } }
}
namespace SiteGround_Optimizer\Rest {
    class Rest { public static $toggle_options = array( 'optimize_css', 'optimize_javascript', 'combine_javascript', 'optimize_javascript_async', 'optimize_html', 'optimize_web_fonts', 'remove_query_strings', 'disable_emojis', 'lazyload_images' ); }
}
namespace {
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' ); define( 'WP_PLUGIN_DIR', __DIR__ . '/fixtures' );
class WP_Error { public function __construct( public $code, $message = '', $data = null ) {} public function get_error_code() { return $this->code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function is_plugin_active( $path ) { return empty( $GLOBALS['inactive'] ); }
function is_multisite() { return ! empty( $GLOBALS['multisite'] ); }
function check( $s, $v ) { if ( ! $v ) { throw new \RuntimeException( $s ); } echo "PASS $s\n"; }
require CC_ASSISTANT_DIR . 'includes/class-setting-writer.php';
require CC_ASSISTANT_DIR . 'includes/class-siteground-adapter.php';
$GLOBALS['native_calls'] = 0;
$key = 'siteground_optimizer_optimize_css'; $GLOBALS['options'] = array( $key => '0' );
$args = array( 'adapter' => CC_Assistant_SiteGround_Adapter::ID, 'option_name' => $key, 'value' => 1 );
check( 'official reviewed native source and registry accepted', CC_Assistant_SiteGround_Adapter::capability()['available'] );
$source = WP_PLUGIN_DIR . '/sg-cachepress/core/Options/Options.php'; $original = file_get_contents( $source );
try { file_put_contents( $source, $original . "\n// changed implementation\n" ); check( 'unreviewed native file changes disable adapter', ! CC_Assistant_SiteGround_Adapter::capability()['available'] ); }
finally { file_put_contents( $source, $original ); }
$plan = CC_Assistant_Setting_Writer::build_plan( $args );
check( 'native planning writes nothing', ! is_wp_error( $plan ) && 0 === $GLOBALS['native_calls'] );
$GLOBALS['options'][$key] = 1;
check( 'concurrent setting change blocks native apply', 'setting_conflict' === CC_Assistant_Setting_Writer::apply_plan( $plan )->get_error_code() && 0 === $GLOBALS['native_calls'] );
$GLOBALS['options'][$key] = '0';
check( 'apply invokes native method and reads back option', true === CC_Assistant_Setting_Writer::apply_plan( $plan ) && 1 === $GLOBALS['native_calls'] );
check( 'rollback also invokes native method', true === CC_Assistant_Setting_Writer::revert_plan( $plan ) && 2 === $GLOBALS['native_calls'] && 0 === $GLOBALS['options'][$key] );
$GLOBALS['write_failure'] = true;
check( 'native write failure remains failure', 'native_write_failed' === CC_Assistant_Setting_Writer::apply_plan( $plan )->get_error_code() );
$GLOBALS['write_failure'] = false;
foreach ( array( 'enable_gzip_compression', 'enable_browser_caching', 'memcached', 'captcha' ) as $unsupported ) {
    $bad = $args; $bad['option_name'] = 'siteground_optimizer_' . $unsupported;
    check( 'unsupported native operation refused: ' . $unsupported, is_wp_error( CC_Assistant_Setting_Writer::build_plan( $bad ) ) );
}
$bad = $args; $bad['value'] = '1';
check( 'native value requires exact integer enum', is_wp_error( CC_Assistant_Setting_Writer::build_plan( $bad ) ) );
$bad = $args; $bad['adapter'] = 'invented';
check( 'forged adapter cannot bypass generic guards', is_wp_error( CC_Assistant_Setting_Writer::build_plan( $bad ) ) );
$GLOBALS['inactive'] = true;
check( 'deactivation after queue blocks apply', 'native_adapter_unavailable' === CC_Assistant_Setting_Writer::apply_plan( $plan )->get_error_code() );
$GLOBALS['inactive'] = false; $GLOBALS['multisite'] = true;
check( 'multisite remains explicitly unsupported', ! CC_Assistant_SiteGround_Adapter::capability()['available'] );
$GLOBALS['multisite'] = false; \SiteGround_Optimizer\Rest\Rest::$toggle_options = array();
check( 'changed runtime registry disables adapter', ! CC_Assistant_SiteGround_Adapter::capability()['available'] );
}
