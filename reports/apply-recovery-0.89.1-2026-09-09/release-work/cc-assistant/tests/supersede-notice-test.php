<?php
/**
 * Auto-supersede must never be silent.
 *
 * queue() hides an older pending on the same target by setting superseded_by.
 * That is correct for v2-of-an-intent, and it is how a failed-lint draft stops
 * cluttering the inbox. The problem was that it happened in TOTAL SILENCE, and
 * the hidden row keeps status='pending' while the inbox filters on
 * superseded_by IS NULL — so a buried change is indistinguishable from one that
 * was never queued at all.
 *
 * post_content_update makes this sharp: supersede_subkey() returns '' for it,
 * so post_id ALONE is the key and ANY two content pendings on one post collide,
 * even when they edit completely different modules.
 *
 * Real incident, sids-ponds post 75 on 2026-08-26. #382 added a title to the
 * About Us map iframe (WCAG A). #383 changed a testimonial colour. Two
 * independent Divi module edits. Queueing #383 silently buried #382; the
 * operator approved everything visible and the accessibility fix vanished with
 * no error, no cc_edits row, and an inbox that read as empty. It was diagnosed
 * only by hand-comparing the live DOM against the stored module.
 *
 * These tests pin BOTH halves: the notice must fire when something is hidden,
 * and must NOT fire when nothing is (or the notice becomes noise operators
 * learn to scroll past — the same way the claim guard did).
 *
 * Run: php tests/supersede-notice-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $c = '', $m = '', $d = array() ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	}
}
if ( ! function_exists( 'is_wp_error' ) )       { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); } }
if ( ! function_exists( 'apply_filters' ) )     { function apply_filters( $h, $v ) { return $v; } }
if ( ! function_exists( 'get_option' ) )        { function get_option( $k, $d = false ) { return $d; } }
if ( ! function_exists( 'esc_html' ) )          { function esc_html( $s ) { return $s; } }
if ( ! function_exists( '__' ) )                { function __( $s, $d = null ) { return $s; } }
if ( ! function_exists( 'current_time' ) )      { function current_time( $t = 'mysql' ) { return '2026-08-26 00:00:00'; } }

/**
 * $wpdb serving two DIFFERENT SELECTs: the candidate scan, then the summary
 * lookup the notice builder does. Routed on the column list so the test
 * exercises the real two-query path rather than a flattened stub.
 */
class CC_Supersede_Test_WPDB {
	public $prefix = 'wp_';
	public $candidates = array();   // rows for the candidate scan
	public $summaries  = array();   // rows for the summary lookup
	public $updated    = 0;         // what the UPDATE reports
	public $update_sql = '';
	public function prepare( $q ) { return $q; }
	public function get_results( $q ) {
		return ( false !== strpos( $q, 'change_summary' ) ) ? $this->summaries : $this->candidates;
	}
	public function query( $q ) { $this->update_sql = $q; return $this->updated; }
	public function get_var( $q ) { return null; }
	public function get_row( $q ) { return null; }
}
$GLOBALS['wpdb'] = new CC_Supersede_Test_WPDB();

require_once dirname( __DIR__ ) . '/includes/class-pending-changes.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( $extra ? " ($extra)" : '' ) . "\n"; }
}

/** Drive mark_older_as_superseded with a controlled candidate set. */
function run_supersede( $new_id, $args, $candidates, $summaries, $updated ) {
	$db = $GLOBALS['wpdb'];
	$db->candidates = $candidates;
	$db->summaries  = $summaries;
	$db->updated    = $updated;
	$n = CC_Assistant_Pending_Changes::mark_older_as_superseded( $new_id, $args );
	return array( $n, CC_Assistant_Pending_Changes::superseded_notices( $new_id ) );
}

/* ------------------------------------------------- THE REAL INCIDENT */
// #383 (colour) queued while #382 (iframe title) was still open on post 75.
list( $n, $notices ) = run_supersede(
	383,
	array( 'change_type' => 'post_content_update', 'post_id' => 75, 'proposed_value' => '{"content":"...colour..."}' ),
	array( (object) array( 'id' => 382, 'proposed_value' => '{"content":"...iframe title..."}' ) ),
	array( (object) array( 'id' => 382, 'change_summary' => 'About Us: give the Google Map an accessible name (WCAG A 4.1.2)' ) ),
	1
);
check( 'two content pendings on one post DO supersede (unchanged behaviour)', 1 === $n, "n=$n" );
check( 'and a notice is now recorded instead of silence', 1 === count( $notices ), json_encode( $notices ) );
check( 'the notice names the HIDDEN pending id, not the new one',
	isset( $notices[0]['pending_id'] ) && 382 === $notices[0]['pending_id'], json_encode( $notices ) );
check( 'the notice carries the summary of the row that was hidden',
	isset( $notices[0]['summary'] ) && false !== strpos( $notices[0]['summary'], 'accessible name' ), json_encode( $notices ) );
check( 'the message states it can never be approved',
	isset( $notices[0]['message'] ) && false !== strpos( $notices[0]['message'], 'never be approved' ) );
check( 'the message tells the operator how to recover it',
	isset( $notices[0]['message'] ) && false !== strpos( $notices[0]['message'], 're-read the post' ) );

/* --------------------------------------------- NO FALSE NOTICES */
// Nothing else open on the post: the common case must stay quiet.
list( $n, $notices ) = run_supersede(
	400,
	array( 'change_type' => 'post_content_update', 'post_id' => 99, 'proposed_value' => '{"content":"x"}' ),
	array(), array(), 0
);
check( 'no open sibling => no supersede and NO notice', 0 === $n && empty( $notices ), "n=$n " . json_encode( $notices ) );

// Two postmeta updates targeting DIFFERENT meta keys must not collide. This
// pins the documented sub-key fix: an empty sub-key here would collapse title
// and description pendings into a mutual supersede chain.
list( $n, $notices ) = run_supersede(
	401,
	array( 'change_type' => 'postmeta_update', 'post_id' => 75, 'proposed_value' => '{"key":"description","value":"b"}' ),
	array( (object) array( 'id' => 390, 'proposed_value' => '{"key":"title","value":"a"}' ) ),
	array( (object) array( 'id' => 390, 'change_summary' => 'SEO title' ) ),
	1
);
check( 'postmeta on DIFFERENT keys does not supersede, and stays quiet',
	0 === $n && empty( $notices ), "n=$n " . json_encode( $notices ) );

// Same meta key IS a real duplicate — dedup must still work, and now say so.
list( $n, $notices ) = run_supersede(
	402,
	array( 'change_type' => 'postmeta_update', 'post_id' => 75, 'proposed_value' => '{"key":"description","value":"v2"}' ),
	array( (object) array( 'id' => 391, 'proposed_value' => '{"key":"description","value":"v1"}' ) ),
	array( (object) array( 'id' => 391, 'change_summary' => 'SEO description v1' ) ),
	1
);
check( 'same meta key still supersedes (dedup preserved)', 1 === $n, "n=$n" );
check( 'and that supersede is now reported too', 1 === count( $notices ), json_encode( $notices ) );

// A type outside the whitelist must not supersede at all.
list( $n, $notices ) = run_supersede(
	403,
	array( 'change_type' => 'elementor_widget_add', 'post_id' => 75, 'proposed_value' => '{}' ),
	array( (object) array( 'id' => 392, 'proposed_value' => '{}' ) ),
	array( (object) array( 'id' => 392, 'change_summary' => 'add widget' ) ),
	1
);
check( 'non-supersede-able type never supersedes and never notices',
	0 === $n && empty( $notices ), "n=$n " . json_encode( $notices ) );

/* ------------------------------- the UPDATE must be guarded, not blind */
list( $n, $notices ) = run_supersede(
	404,
	array( 'change_type' => 'post_content_update', 'post_id' => 75, 'proposed_value' => '{"content":"y"}' ),
	array( (object) array( 'id' => 393, 'proposed_value' => '{"content":"z"}' ) ),
	array( (object) array( 'id' => 393, 'change_summary' => 'older body' ) ),
	1
);
check( 'the UPDATE still refuses to re-supersede an already-superseded row',
	false !== strpos( $GLOBALS['wpdb']->update_sql, 'superseded_by IS NULL' ), $GLOBALS['wpdb']->update_sql );

// If the UPDATE reports 0 rows changed, nothing was hidden — claiming otherwise
// would be a lie in the response.
list( $n, $notices ) = run_supersede(
	405,
	array( 'change_type' => 'post_content_update', 'post_id' => 75, 'proposed_value' => '{"content":"y"}' ),
	array( (object) array( 'id' => 394, 'proposed_value' => '{"content":"z"}' ) ),
	array( (object) array( 'id' => 394, 'change_summary' => 'older body' ) ),
	0
);
check( 'UPDATE affecting 0 rows records NO notice', empty( $notices ), json_encode( $notices ) );

echo "\n" . ( $fails ? "FAILED: $fails" : 'ALL PASS' ) . "\n";
exit( $fails ? 1 : 0 );
