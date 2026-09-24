<?php
/**
 * v0.81 session gate: hard by default, 8h window, explicit "0" opts out.
 *
 * The soft reminder (v0.44) was attached to every mutating response of a
 * recorded session and ignored each time. This pins the behaviour that
 * replaces it: a stale gate is a 409, not a warning; the operator can still
 * turn it off deliberately; and a fresh stamp lets writes through.
 *
 * Run: php tests/session-gate-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opt'] = array();
$GLOBALS['tr']  = array();
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $c = '', $m = '', $d = array() ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	}
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $k ] : $d; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['tr'] ) ? $GLOBALS['tr'][ $k ] : false; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['tr'][ $k ] = $v; $GLOBALS['tr'][ $k . '__ttl' ] = $ttl; return true; }

require_once CC_ASSISTANT_DIR . 'includes/class-session-gate.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label" . ( '' !== $extra ? " ($extra)" : '' ) . "\n"; }
}

check( 'hard gate ON when option unset', CC_Assistant_Session_Gate::hard_gate_enabled() );
$GLOBALS['opt']['cc_assistant_bootstrap_hard_gate'] = '';
check( 'hard gate ON when option empty string', CC_Assistant_Session_Gate::hard_gate_enabled() );
$GLOBALS['opt']['cc_assistant_bootstrap_hard_gate'] = '1';
check( 'hard gate ON when option "1"', CC_Assistant_Session_Gate::hard_gate_enabled() );
foreach ( array( '0', 0, false, 'false', 'off', 'no' ) as $v ) {
	$GLOBALS['opt']['cc_assistant_bootstrap_hard_gate'] = $v;
	check( 'hard gate OFF when option ' . json_encode( $v ), ! CC_Assistant_Session_Gate::hard_gate_enabled() );
}
unset( $GLOBALS['opt']['cc_assistant_bootstrap_hard_gate'] );

$GLOBALS['tr'] = array();
$blocked = CC_Assistant_Session_Gate::hard_block_if_enabled();
check( 'stale + hard -> WP_Error 409 session_not_bootstrapped', is_wp_error( $blocked ) && 'session_not_bootstrapped' === $blocked->code && 409 === $blocked->data['status'] );
check( 'refusal names whoami and the override', false !== strpos( $blocked->message, 'whoami' ) && false !== strpos( $blocked->message, 'cc_assistant_bootstrap_hard_gate' ) );
check( 'refusal ships the preflight', isset( $blocked->data['preflight'] ) && 5 === count( $blocked->data['preflight'] ) );
check( 'reminder also present when stale', is_array( CC_Assistant_Session_Gate::reminder_if_stale() ) );

CC_Assistant_Session_Gate::mark_bootstrapped();
check( 'stamp TTL is 8 hours', 8 * 3600 === $GLOBALS['tr'][ CC_Assistant_Session_Gate::KEY . '__ttl' ] );
check( 'fresh -> no block', null === CC_Assistant_Session_Gate::hard_block_if_enabled() );
check( 'fresh -> no reminder', null === CC_Assistant_Session_Gate::reminder_if_stale() );

$GLOBALS['tr'][ CC_Assistant_Session_Gate::KEY ] = time() - 8 * 3600 - 5;
check( 'stamp older than 8h -> blocked again', is_wp_error( CC_Assistant_Session_Gate::hard_block_if_enabled() ) );
$GLOBALS['tr'][ CC_Assistant_Session_Gate::KEY ] = time() - 7 * 3600;
check( 'stamp 7h old -> still fresh (a long session is not cut off)', null === CC_Assistant_Session_Gate::hard_block_if_enabled() );

$GLOBALS['tr'] = array();
$GLOBALS['opt']['cc_assistant_bootstrap_hard_gate'] = '0';
check( 'operator opt-out: stale gate returns null (reminder only)', null === CC_Assistant_Session_Gate::hard_block_if_enabled() && is_array( CC_Assistant_Session_Gate::reminder_if_stale() ) );

echo $fails ? "\n$fails FAILURES\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );
