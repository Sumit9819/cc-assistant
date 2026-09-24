<?php
/**
 * Sentence and paragraph boundary detection in the content lint.
 *
 * Two false positives fixed 2026-08-23, both found while writing an ordinary
 * e-commerce category description:
 *
 *   1. count_sentences() counted EVERY period, so "$1,259.99" contributed a
 *      phantom sentence. A 4-sentence paragraph quoting three prices counted
 *      as 7 and failed paragraph_length. That is a guaranteed false positive
 *      on any page that says what something costs.
 *
 *   2. wp_strip_all_tags() removes tags without leaving whitespace, so a
 *      heading ran straight into the paragraph after it ("...hardscaping.What
 *      is the difference..."). The splitter needs terminator + WHITESPACE, so
 *      it never split, and a 5-word heading plus a 21-word sentence was
 *      measured as one 26-word run-on. On the description that exposed this,
 *      22.2% of "sentences" were over 25 words; after the fix, 0.0% — every
 *      one had been a glued heading rather than a real run-on.
 *
 * Both had been quietly inflating sentence_length across the whole network.
 *
 * Run: php tests/lint-boundaries-test.php
 */
error_reporting( E_ALL & ~E_DEPRECATED );
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
if ( ! function_exists( 'current_time' ) )      { function current_time( $t = 'mysql' ) { return '2026-08-23 00:00:00'; } }
// WordPress polyfills mbstring in wp-includes/compat.php; this CLI has none.
if ( ! function_exists( 'mb_strtolower' ) ) { function mb_strtolower( $s, $e = null ) { return strtolower( $s ); } }
if ( ! function_exists( 'mb_strtoupper' ) ) { function mb_strtoupper( $s, $e = null ) { return strtoupper( $s ); } }
if ( ! function_exists( 'mb_strlen' ) )     { function mb_strlen( $s, $e = null ) { return strlen( $s ); } }
if ( ! function_exists( 'mb_substr' ) )     { function mb_substr( $s, $a, $l = null, $e = null ) { return null === $l ? substr( $s, $a ) : substr( $s, $a, $l ); } }

require_once dirname( __DIR__ ) . '/includes/class-pre-publish.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( $extra ? " ($extra)" : '' ) . "\n"; }
}

/* ---------------------------------------------------------------- BUG 1 */
// Four real sentences, three prices. Previously counted 7 and failed.
$priced = '<p>Match lit kits start at $1,259.99. Electronic ignition kits start at '
	. '$4,619.99, with the largest rectangular kit at $5,774.99. All eight insert kits '
	. 'here are Crossfire brass burners running on natural gas. Brass holds up outdoors '
	. 'far better than steel.</p>';
$r = CC_Assistant_Pre_Publish::lint_html_block( $priced );
check( 'a 4-sentence paragraph quoting 3 prices passes paragraph_length',
	$r['paragraph_length']['pass'], json_encode( $r['paragraph_length']['violations'] ?? array() ) );

// The guard must not go the other way: 6+ real sentences still fails.
$long_para = '<p>One sentence here. Two sentences here. Three sentences here. Four '
	. 'sentences here. Five sentences here. Six sentences here. Seven sentences here.</p>';
$r = CC_Assistant_Pre_Publish::lint_html_block( $long_para );
check( 'a genuinely long paragraph (7 sentences) still FAILS paragraph_length',
	! $r['paragraph_length']['pass'] );

// Abbreviation masking must survive the change.
$abbr = '<p>Open Mon to Fri. Ask for Dr. Smith at the desk. We ship to the U.S. too. '
	. 'Call ahead. That is all.</p>';
$r = CC_Assistant_Pre_Publish::lint_html_block( $abbr );
check( 'abbreviations (Dr. / U.S.) still do not inflate the count',
	$r['paragraph_length']['pass'], json_encode( $r['paragraph_length']['violations'] ?? array() ) );

/* ---------------------------------------------------------------- BUG 2 */
// Heading ENDING IN PUNCTUATION followed by a paragraph. This is the case the
// original normalization missed, because it only acted when punctuation was absent.
$glued = '<h3>What does a recessed lighting run need?</h3>'
	. '<p>A transformer, cable, and connectors that carry power from the supply out to '
	. 'each fixture along the run without any voltage drop worth worrying about.</p>';
$r = CC_Assistant_Pre_Publish::lint_html_block( $glued );
check( 'heading ending in "?" does not glue to the next sentence',
	$r['sentence_length']['pass'],
	json_encode( $r['sentence_length']['samples'] ?? array() ) );

// Heading with NO terminal punctuation (the case the original rule did handle).
// Calibrated so the fixture actually detects gluing: the sentence is 24 words
// on its own (passes), and 27 with the 3-word heading attached (fails). An
// earlier version of this test used a sentence that was already 26 words, so it
// failed for its own length and proved nothing about boundaries.
$glued2 = '<h3>What we stock</h3>'
	. '<p>The recessed range covers fifteen fixtures in black and stainless finishes, '
	. 'and every one of them runs at twelve volts on one shared system.</p>';
$r = CC_Assistant_Pre_Publish::lint_html_block( $glued2 );
check( 'heading without punctuation still does not glue',
	$r['sentence_length']['pass'],
	json_encode( $r['sentence_length']['samples'] ?? array() ) );

// And a REAL run-on must still be caught, or the fix is just a mute button.
$runon = '<p>' . str_repeat( 'word ', 40 ) . 'end.</p>';
$r = CC_Assistant_Pre_Publish::lint_html_block( $runon );
check( 'a real 40-word run-on still FAILS sentence_length',
	! $r['sentence_length']['pass'], (string) ( $r['sentence_length']['long_percent'] ?? -1 ) );

/* ------------------------------------------------- the exact regression */
$real = '<h2>Fire Pit and Fire Table Inserts</h2><p>A fire pit insert is the burner '
	. 'assembly that turns a masonry or custom-built surround into a working fire feature. '
	. 'Drop one into a table frame and you have a fire table. Set it into a stone surround '
	. 'and you have a built-in fire pit. It is the most flexible way to get a finished '
	. 'feature that matches your hardscaping.</p>'
	. '<h3>What do fire table inserts cost?</h3><p>Match lit kits start at $1,259.99. '
	. 'Electronic ignition kits start at $4,619.99, with the largest rectangular kit at '
	. '$5,774.99. All eight insert kits here are Crossfire brass burners running on '
	. 'natural gas.</p>';
$r = CC_Assistant_Pre_Publish::lint_html_block( $real );
check( 'the real category description passes paragraph_length', $r['paragraph_length']['pass'] );
check( 'the real category description passes sentence_length', $r['sentence_length']['pass'],
	(string) ( $r['sentence_length']['long_percent'] ?? -1 ) );
check( 'and reports 0% long sentences, not merely under the threshold',
	0.0 === (float) $r['sentence_length']['long_percent'],
	(string) $r['sentence_length']['long_percent'] );

echo "\n" . ( $fails ? "FAILED: $fails" : 'ALL PASS' ) . "\n";
exit( $fails ? 1 : 0 );
