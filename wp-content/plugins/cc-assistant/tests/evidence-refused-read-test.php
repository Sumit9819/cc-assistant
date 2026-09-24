<?php
/**
 * A refused read must not take the site down.
 *
 * get_post() on a post type outside the allowlist returns a WP_Error, not an
 * array. The evidence gate's diagnostic branch reads $data with array syntax to
 * report why the read did not qualify, and on PHP 8 array access against an
 * object is a fatal error, not a null read. That turned one refused MCP call
 * into a 500 on the live site (mammothmachinery.ca, 0.89.9).
 */
require __DIR__ . '/fixtures/safety-harness.php';

define( 'REST_REQUEST', true );

define( 'CC_ASSISTANT_VERSION', '0.89.10' );
define( 'CC_ASSISTANT_BASENAME', 'cc-assistant/cc-assistant.php' );

$GLOBALS['post'] = (object) array(
	'ID' => 501, 'post_title' => 'contact-form', 'post_content' => '', 'post_excerpt' => '',
	'post_name' => 'contact-form', 'post_status' => 'publish', 'post_type' => 'forminator_forms',
	'post_author' => 1, 'post_parent' => 0, 'menu_order' => 0,
);
$GLOBALS['meta'] = array();
$GLOBALS['terms'] = array();
$GLOBALS['test_transients'] = array();
$GLOBALS['test_options'] = array( 'active_plugins' => array(), 'stylesheet' => 'astra', 'template' => 'astra' );

// Declared conditionally so PHP does not hoist them ahead of the shared
// harness, which defines some of the same shims.
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { return true; } }
if ( ! function_exists( 'add_action' ) ) { function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return true; } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $hook, $value ) { return $value; } }
if ( ! function_exists( 'get_site_option' ) ) { function get_site_option( $key, $default = false ) { return $default; } }
if ( ! function_exists( 'get_plugins' ) ) { function get_plugins() { return array(); } }
if ( ! function_exists( 'get_mu_plugins' ) ) { function get_mu_plugins() { return array(); } }
if ( ! function_exists( 'rest_get_authenticated_app_password' ) ) { function rest_get_authenticated_app_password() { return 'app-pass-uuid'; } }
if ( ! function_exists( 'wp_get_session_token' ) ) { function wp_get_session_token() { return ''; } }

class WP_REST_Request {
	private $route; private $params;
	public function __construct( $route, array $params = array() ) { $this->route = $route; $this->params = $params; }
	public function get_route() { return $this->route; }
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
}

require_once dirname( __DIR__ ) . '/includes/class-integrity.php';
require_once dirname( __DIR__ ) . '/includes/class-evidence-gate.php';

$handler = array( 'callback' => array( 'CC_Assistant_REST_API', 'handle_get_post' ) );
$request = new WP_REST_Request( '/cc-assistant/v1/posts/501', array( 'id' => 501 ) );
$refusal = new WP_Error( 'post_type_not_allowed', 'That post type is not in the allowlist.' );

$returned = null; $fatal = null;
try {
	$returned = CC_Assistant_Evidence_Gate::after_read( $refusal, $handler, $request );
} catch ( Throwable $e ) {
	$fatal = get_class( $e ) . ': ' . $e->getMessage();
}

check( null === $fatal, 'A WP_Error response does not fatal the gate' . ( $fatal ? " ($fatal)" : '' ) );
check( $returned === $refusal, 'The refusal is passed through untouched, not swallowed' );

// The same path with a verified_page_audit refusal, which reads $data['usable'].
$audit_handler = array( 'callback' => array( 'CC_Assistant_REST_API', 'handle_verified_page_audit' ) );
$audit_request = new WP_REST_Request( '/cc-assistant/v1/verified-audit/501', array( 'id' => 501 ) );
$fatal = null;
try {
	CC_Assistant_Evidence_Gate::after_read( new WP_Error( 'blocked', 'nope' ), $audit_handler, $audit_request );
} catch ( Throwable $e ) {
	$fatal = get_class( $e ) . ': ' . $e->getMessage();
}
check( null === $fatal, 'A refused audit does not fatal either' . ( $fatal ? " ($fatal)" : '' ) );

// A successful array response must still be read normally, so the guard cannot
// be "normalise everything to empty" in disguise.
$ok_request = new WP_REST_Request( '/cc-assistant/v1/posts/501', array( 'id' => 501 ) );
$fatal = null;
try {
	CC_Assistant_Evidence_Gate::after_read(
		array( 'id' => 501, 'status' => 'publish' ), $handler, $ok_request );
} catch ( Throwable $e ) {
	$fatal = get_class( $e ) . ': ' . $e->getMessage();
}
check( null === $fatal, 'An ordinary array response still passes through' . ( $fatal ? " ($fatal)" : '' ) );

echo "\nRefused-read checks passed: $tests\n";
