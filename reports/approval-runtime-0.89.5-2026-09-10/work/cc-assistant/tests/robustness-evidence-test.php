<?php
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
class WP_Error { public function get_error_code() { return 'fetch_blocked'; } public function get_error_message() { return 'CAPTCHA'; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_post( $id ) { return null; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $v, $autoload = null ) { $GLOBALS['options'][$key] = $v; return true; }
require CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
require CC_ASSISTANT_DIR . 'includes/class-verified-page-audit.php';
require CC_ASSISTANT_DIR . 'includes/class-page-robustness.php';
function check( $label, $v ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS $label\n"; }
// The compatibility method must route to the real evidence engine; verify its
// blocked-result contract without requiring a network endpoint.
$r = CC_Assistant_Verified_Page_Audit::evaluate( array( 'post_id' => 1, 'error' => 'challenge_page', 'http_code' => 200, 'refreshed' => true ) );
check( 'CAPTCHA cannot pass or fail page findings', 'unverified' === $r['assessment'] && empty( array_filter( $r['findings'], static function ( $f ) { return 'unknown' !== $f['status']; } ) ) );
$r = CC_Assistant_Page_Robustness::audit( 987, true );
check( 'compatibility audit preserves unavailable evidence without a score', null === $r['score'] && null === $r['pass'] && 'unverified' === $r['assessment'] && empty( $r['blocking'] ) );
echo "All robustness evidence checks passed.\n";
