<?php
/**
 * Claim-removal guard: does this change undo a change someone already approved?
 *
 * The guard is advisory, so its whole value is signal-to-noise. It used to fire
 * when ANY ONE removed token of 4+ chars appeared anywhere in an approved
 * sibling's summary or payload, which meant one ordinary word in common counted
 * as evidence. Observed 2026-08: a homepage title swapping "Garden Supply" for
 * "Garden Store" matched an unrelated product-carousel revert on the token
 * SUPPLY, and on mammothmachinery BEST and MODELS fired the same way. Operators
 * hand-dismissed them, which is how a guard trains people to ignore it.
 *
 * Evidence is now a removed PHRASE (2+ tokens that were adjacent), or a
 * distinctive single token (ALL-CAPS acronym / contains a digit / 8+ chars).
 * Both halves matter: these tests pin the false positives DOWN and the real
 * detections UP, because loosening a guard until it is quiet is not a fix.
 *
 * Run: php tests/claim-guard-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	}
}
if ( ! function_exists( 'is_wp_error' ) )      { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ){ function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); } }
if ( ! function_exists( 'apply_filters' ) )    { function apply_filters( $h, $v ) { return $v; } }
if ( ! function_exists( 'get_option' ) )       { function get_option( $k, $d = false ) { return $d; } }
if ( ! function_exists( 'esc_html' ) )         { function esc_html( $s ) { return $s; } }
if ( ! function_exists( '__' ) )               { function __( $s, $d = null ) { return $s; } }
if ( ! function_exists( 'current_time' ) )     { function current_time( $t = 'mysql' ) { return '2026-08-23 00:00:00'; } }

/** Minimal $wpdb serving one fixed set of approved sibling rows. */
class CC_Claim_Test_WPDB {
	public $prefix = 'wp_';
	public $siblings = array();
	public function prepare( $q ) { return $q; }
	public function get_results( $q ) { return $this->siblings; }
	public function get_var( $q ) { return null; }
	public function get_row( $q ) { return null; }
}
$GLOBALS['wpdb'] = new CC_Claim_Test_WPDB();

require_once dirname( __DIR__ ) . '/includes/class-pending-changes.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( $extra ? " ($extra)" : '' ) . "\n"; }
}

$ref = new ReflectionMethod( 'CC_Assistant_Pending_Changes', 'detect_claim_removal' );
$ref->setAccessible( true );

/**
 * @param string $old       value being replaced
 * @param string $new       proposed value
 * @param string $sib_sum   approved sibling's summary (must look deliberate)
 * @param string $sib_val   approved sibling's payload
 */
function warn_for( $old, $new, $sib_sum, $sib_val = '' ) {
	global $ref;
	$GLOBALS['wpdb']->siblings = array(
		(object) array( 'id' => 285, 'change_summary' => $sib_sum, 'proposed_value' => $sib_val ),
	);
	return $ref->invoke( null, 999, array(
		'change_type'    => 'postmeta_update',
		'post_id'        => 72,
		'current_value'  => $old,
		'proposed_value' => $new,
	) );
}

// ---------------------------------------------------------------- FALSE POSITIVES
// The exact homepage case: only the lone token SUPPLY leaves the title.
$w = warn_for(
	"Pond Supplies & Garden Supply Mississauga | Sid's Ponds",
	"Pond Supplies & Garden Store in Mississauga | Sid's Ponds",
	'REVERT #283: restore the product carousel module to its exact pre-edit settings',
	'carousel supply of products restored'
);
check( 'lone common token (SUPPLY) no longer fires', empty( $w ), json_encode( $w ) );

$w = warn_for(
	'Best Tracked Mini Dumpers | Models and Prices',
	'Tracked Mini Dumpers from $12,499 CAD | Mammoth Machinery',
	'Restore the brand tail per operator decision #733',
	'best models available'
);
check( 'mammoth BEST / MODELS singles no longer fire', empty( $w ), json_encode( $w ) );

// ---------------------------------------------------------------- TRUE POSITIVES
// The case this guard exists for: an ALL-CAPS capability acronym disappears.
$w = warn_for(
	'On-site MRI and CT imaging, open 24 hours',
	'On-site CT imaging, open 24 hours',
	'Operator confirmed: MRI is available on site, add it back',
	'MRI on site'
);
check( 'acronym capability claim (MRI) still fires', ! empty( $w ), json_encode( $w ) );

// A real multi-word phrase removed, and the sibling deliberately added it.
// Vocabulary chosen so NO word qualifies as a distinctive single (all under 8
// chars, no digits, no acronyms) — otherwise this would pass through the
// single-token path and prove nothing about phrase detection.
$w = warn_for(
	'We stock winter pond care kits and weekend pickup.',
	'We stock weekend pickup.',
	'Operator confirmed: restore the winter pond care wording',
	'copy now reads: winter pond care kits in stock'
);
check( 'removed PHRASE fires (no word is distinctive on its own)', ! empty( $w ), json_encode( $w ) );

// Distinctive by digits: a verified price disappearing.
$w = warn_for(
	'Tracked Mini Dumpers from $12,499 CAD',
	'Tracked Mini Dumpers | Mammoth Machinery',
	'Operator confirmed the verified from-price, add back',
	'price 12,499 CAD verified from the machine page'
);
check( 'price token (contains digits) still fires', ! empty( $w ), json_encode( $w ) );

// ---------------------------------------------------------------- SCOPE GUARDS
// Adjacency is required: the same words scattered apart are not evidence.
// Same non-distinctive vocabulary as the phrase test above, so the ONLY thing
// that differs between firing and not firing is whether the words sit together.
$w = warn_for(
	'We stock winter pond care kits and weekend pickup.',
	'We stock weekend pickup.',
	'Operator confirmed: restore something else entirely',
	'pond liners are stocked; winter hours vary; we take care of delivery'
);
check( 'scattered words do NOT count as a phrase match', empty( $w ), json_encode( $w ) );

// A sibling that was never a deliberate add/confirm is still ignored.
$w = warn_for(
	'On-site MRI and CT imaging',
	'On-site CT imaging',
	'Routine typo fix in the hero heading',
	'MRI mentioned in passing'
);
check( 'sibling without a deliberate-add marker is ignored', empty( $w ), json_encode( $w ) );

// Nothing removed at all -> nothing to warn about.
$w = warn_for( 'On-site CT imaging', 'On-site CT imaging plus parking', 'Operator confirmed, add back', 'CT imaging' );
check( 'pure addition produces no warning', empty( $w ), json_encode( $w ) );

echo "\n" . ( $fails ? "FAILED: $fails" : 'ALL PASS' ) . "\n";
exit( $fails ? 1 : 0 );
