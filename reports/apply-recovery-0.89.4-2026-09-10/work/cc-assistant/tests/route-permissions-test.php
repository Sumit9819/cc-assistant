<?php
/** Register the actual REST modules; no WordPress/database bootstrap or requests. */
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
class WP_Error { public function __construct( public $code, public $message = '', public $data = array() ) {} }
class WP_REST_Request { public function get_header( $key ) { return 'fixture-nonce'; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['caps'][$cap] ); }
function rest_get_authenticated_app_password() { return $GLOBALS['app'] ?? null; }
function wp_verify_nonce( $nonce, $action ) { return true; }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['routes'][] = array( $namespace, $route, $args ); }
foreach ( glob( CC_ASSISTANT_DIR . 'includes/class-rest-*.php' ) as $file ) { require_once $file; }
require_once CC_ASSISTANT_DIR . 'includes/class-reviews.php';
CC_Assistant_Reviews::register_routes();
$modules = 0;
foreach ( get_declared_classes() as $class ) {
    if ( str_starts_with( $class, 'CC_Assistant_REST_' ) && method_exists( $class, 'register_routes' ) ) { $class::register_routes(); $modules++; }
}
$count = 0; $seen = array();
foreach ( $GLOBALS['routes'] as list( $namespace, $path, $args ) ) {
    $handlers = isset( $args['callback'] ) ? array( $args ) : $args;
    foreach ( $handlers as $handler ) {
        if ( ! is_array( $handler ) || ! isset( $handler['callback'] ) ) { continue; }
        if ( ! is_callable( $handler['callback'] ) || ! is_callable( $handler['permission_callback'] ?? null ) ) { throw new RuntimeException( 'Missing callback/permission: ' . $path ); }
        $GLOBALS['caps'] = array();
        $result = call_user_func( $handler['permission_callback'], new WP_REST_Request() );
        if ( true === $result || ( ! is_wp_error( $result ) && false !== $result ) ) { throw new RuntimeException( 'Anonymous access accepted: ' . $path ); }
        $methods = $handler['methods'] ?? 'GET';
        foreach ( is_array( $methods ) ? $methods : explode( ',', $methods ) as $method ) {
            $key = $namespace . $path . ' ' . trim( $method );
            if ( isset( $seen[$key] ) ) { throw new RuntimeException( 'Duplicate route: ' . $key ); }
            $seen[$key] = true;
        }
        $count++;
    }
}
foreach ( array( array(), array( 'cc_assistant_use' => true ), array( 'manage_options' => true ) ) as $caps ) {
    $GLOBALS['caps'] = $caps; $GLOBALS['app'] = 'fixture-application-password-id';
    if ( ! is_wp_error( CC_Assistant_Access::can_review( new WP_REST_Request() ) ) ) { throw new RuntimeException( 'Application password accepted for review' ); }
}
$GLOBALS['caps'] = array( 'manage_options' => true ); $GLOBALS['app'] = null;
if ( true !== CC_Assistant_Access::can_review( new WP_REST_Request() ) ) { throw new RuntimeException( 'Interactive administrator cannot review' ); }
$GLOBALS['caps'] = array( 'cc_assistant_use' => true );
if ( true !== CC_Assistant_Reviews::rest_permission() ) { throw new RuntimeException( 'Operator cannot read review health' ); }
echo "PASS: $count registered handlers in $modules REST modules have callable, anonymous-denying permissions and unique routes.\n";
echo "PASS: Application passwords cannot approve; administrator browser review remains available.\n";
