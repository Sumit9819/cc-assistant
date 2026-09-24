<?php
/**
 * Asset-reference rewriting: pure-path test for the two ways this class can
 * silently destroy a client database — serialized length prefixes and
 * JSON-escaped slashes. Run: php tests/asset-references-test.php
 *
 * No WordPress bootstrap. Only the handful of WP helpers the rewrite path
 * touches are stubbed; the DB paths (find/apply) are not exercised here.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 || ':' !== $data[1] ) {
			return false;
		}
		return (bool) preg_match( '/^[aOsbdi]:/', $data );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) {
		return strip_tags( (string) $s );
	}
}

require dirname( __DIR__ ) . '/includes/class-asset-references.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) {
		echo "PASS  $label\n";
	} else {
		$fails++;
		echo "FAIL  $label  $extra\n";
	}
}

$OLD = 'https://sids-ponds.com/wp-content/uploads/2025/04/Untitled-design-45.png';
$NEW = 'https://sids-ponds.com/wp-content/uploads/2026/08/popup-wildflower-meadow-border.webp';

/* ---------------------------------------------------------------- variants */

$variants = CC_Assistant_Asset_References::needle_variants( $OLD );
check( 'variants include the raw URL', in_array( $OLD, $variants, true ) );
check(
	'variants include the JSON-escaped URL',
	in_array( str_replace( '/', '\\/', $OLD ), $variants, true ),
	'this is the one that makes page-builder data invisible when missing'
);
check( 'variants include the root-relative path', in_array( '/wp-content/uploads/2025/04/Untitled-design-45.png', $variants, true ) );
check( 'variants include the http twin', in_array( str_replace( 'https://', 'http://', $OLD ), $variants, true ) );
check(
	'variants are longest-first',
	strlen( $variants[0] ) >= strlen( $variants[ count( $variants ) - 1 ] ),
	'short variants replaced first would corrupt the long ones that contain them'
);

/* ------------------------------------------------------------ plain string */

list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value(
	'<img src="' . $OLD . '" alt="x" />',
	$OLD,
	$NEW
);
check( 'plain html rewritten', false !== strpos( $out, $NEW ) && false === strpos( $out, $OLD ), $out );
check( 'plain html counted one replacement', 1 === $n, "n=$n" );
check( 'plain html no error', '' === $err, $err );

/* -------------------------------------------------------- JSON, escaped / */

$json = wp_json_encode_stub(
	array(
		'steps' => array(
			array( 'overlay' => $OLD ),
			array( 'overlay' => $OLD ),
		),
	)
);
function wp_json_encode_stub( $d ) {
	return json_encode( $d ); // default escapes slashes, which is the point
}
check( 'fixture really contains escaped slashes', false !== strpos( $json, '\\/' ), $json );

list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( $json, $OLD, $NEW );
check( 'json: both occurrences replaced', 2 === $n, "n=$n" );
check( 'json: still decodes', null !== json_decode( $out, true ), $out );
$decoded = json_decode( $out, true );
check( 'json: value is the new url, unescaped when decoded', isset( $decoded['steps'][0]['overlay'] ) && $NEW === $decoded['steps'][0]['overlay'], $out );
check( 'json: old url fully gone', false === strpos( $out, 'Untitled-design-45' ), $out );

/* ------------------------------------------------- serialized array (core) */

$payload    = array(
	'image'  => $OLD,
	'nested' => array( 'bg' => $OLD, 'keep' => 'untouched' ),
);
$serialized = serialize( $payload );

list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( $serialized, $OLD, $NEW );
check( 'serialized: two replacements', 2 === $n, "n=$n" );
check( 'serialized: no error', '' === $err, $err );

$round = @unserialize( $out );
check(
	'serialized: STILL UNSERIALIZES after a length-changing swap',
	false !== $round,
	'this is the failure mode that silently wipes plugin settings'
);
check( 'serialized: top-level value swapped', is_array( $round ) && $NEW === $round['image'] );
check( 'serialized: nested value swapped', is_array( $round ) && $NEW === $round['nested']['bg'] );
check( 'serialized: untouched sibling preserved', is_array( $round ) && 'untouched' === $round['nested']['keep'] );
check(
	'serialized: length prefix matches new string',
	false !== strpos( $out, 's:' . strlen( $NEW ) . ':"' . $NEW . '"' ),
	'prefix must be recomputed, not carried over'
);

/* --------------------------------------------------- serialized + stdClass */

$obj          = new stdClass();
$obj->overlay = $OLD;
$obj->label   = 'Overlay Image';
$ser_obj      = serialize( array( 'settings' => $obj ) );

list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( $ser_obj, $OLD, $NEW );
check( 'stdClass: replaced', 1 === $n, "n=$n err=$err" );
$round = @unserialize( $out );
check( 'stdClass: round-trips as an object', isset( $round['settings'] ) && $round['settings'] instanceof stdClass );
check( 'stdClass: property swapped', isset( $round['settings']->overlay ) && $NEW === $round['settings']->overlay );
check( 'stdClass: sibling property intact', isset( $round['settings']->label ) && 'Overlay Image' === $round['settings']->label );

/* ------------------------------------------- refusal on a non-stdClass obj */

$dangerous = 'a:1:{s:2:"wc";O:8:"WC_Order":1:{s:3:"img";s:' . strlen( $OLD ) . ':"' . $OLD . '";}}';
list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( $dangerous, $OLD, $NEW );
check( 'foreign class: refused', 0 === $n && '' !== $err, "n=$n err=$err" );
check( 'foreign class: value returned untouched', $out === $dangerous );
check( 'foreign class: reason names the class', false !== strpos( $err, 'WC_Order' ), $err );

/* ----------------------------------------------------------- shape keeping */

list( $out, $n ) = CC_Assistant_Asset_References::rewrite_value(
	'background-image:url(/wp-content/uploads/2025/04/Untitled-design-45.png)',
	$OLD,
	$NEW
);
check( 'root-relative stays root-relative', false !== strpos( $out, '/wp-content/uploads/2026/08/popup-wildflower-meadow-border.webp' ), $out );
check( 'root-relative did not become absolute', false === strpos( $out, 'https://sids-ponds.com/wp-content/uploads/2026' ), $out );

/* ------------------------------------------------------------------ no-ops */

list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( 'nothing to see here', $OLD, $NEW );
check( 'absent url: zero replacements, no error', 0 === $n && '' === $err );
check( 'absent url: value unchanged', 'nothing to see here' === $out );

$empty_ser = serialize( array( 'a' => 'b' ) );
list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( $empty_ser, $OLD, $NEW );
check( 'serialized without the url: untouched and valid', 0 === $n && $out === $empty_ser && '' === $err );

/* ------------------------------ regression: new URL CONTAINS the old one */

/* Looping str_replace over the variant list re-scans text it already wrote,
   so a replacement containing the needle grows every pass. This is not exotic:
   it is what a WebP conversion looks like. Shipped broken once; keep it tested. */

list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value(
	'<img src="https://s.com/u/hero.jpg">',
	'https://s.com/u/hero.jpg',
	'https://s.com/u/hero.jpg.webp'
);
check( 'suffix-extending swap: exactly one .webp', 1 === substr_count( $out, '.webp' ), $out );
check( 'suffix-extending swap: counted once', 1 === $n, "n=$n" );
check( 'suffix-extending swap: exact expected output', '<img src="https://s.com/u/hero.jpg.webp">' === $out, $out );

list( $out, $n ) = CC_Assistant_Asset_References::rewrite_value(
	'<img src="https://s.com/uploads/a.png">',
	'https://s.com/uploads/a.png',
	'https://s.com/uploads/2026/a.png'
);
check( 'file moved into a subfolder: no doubled path segment', '<img src="https://s.com/uploads/2026/a.png">' === $out, $out );

// Same trap inside a serialized blob, where a wrong length prefix compounds it.
$ser = serialize( array( 'bg' => 'https://s.com/u/hero.jpg' ) );
list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( $ser, 'https://s.com/u/hero.jpg', 'https://s.com/u/hero.jpg.webp' );
$round = @unserialize( $out );
check( 'suffix-extending swap inside serialized: unserializes', false !== $round, $out );
check( 'suffix-extending swap inside serialized: value correct', is_array( $round ) && 'https://s.com/u/hero.jpg.webp' === $round['bg'], $out );

/* ------------------------------------- regression: occurrence over-count */

/* An absolute URL literally contains its own protocol-relative and
   root-relative variants, so summing substr_count per variant reported 3
   references where there was 1. */
$hits_method = new ReflectionMethod( 'CC_Assistant_Asset_References', 'count_hits' );
$hits_method->setAccessible( true );
$one_ref = '<img src="https://s.com/u/a.png">';
$counted = $hits_method->invoke( null, $one_ref, CC_Assistant_Asset_References::needle_variants( 'https://s.com/u/a.png' ) );
check( 'one reference counts as one, not once per variant', 1 === $counted, "counted=$counted" );

$two_refs = '<img src="https://s.com/u/a.png"><img src="/u/a.png">';
$counted2 = $hits_method->invoke( null, $two_refs, CC_Assistant_Asset_References::needle_variants( 'https://s.com/u/a.png' ) );
check( 'absolute + root-relative in one value counts as two', 2 === $counted2, "counted=$counted2" );

/* ------------------------------------------------- compact_plan (storage) */

$fake_plan = array(
	'old_url' => $OLD,
	'new_url' => $NEW,
	'targets' => array(
		array( 'kind' => 'option', 'id' => 7, 'label' => 'option x', 'before' => 'AAA' . $OLD, 'after' => 'AAA' . $NEW ),
	),
);
$compact = CC_Assistant_Asset_References::compact_plan( $fake_plan );
check( 'compact_plan: drops before', ! isset( $compact['targets'][0]['before'] ) );
check( 'compact_plan: drops after', ! isset( $compact['targets'][0]['after'] ) );
check( 'compact_plan: keeps urls for recompute at apply time', $OLD === $compact['old_url'] && $NEW === $compact['new_url'] );
check( 'compact_plan: hash identifies the pre-edit value', sha1( 'AAA' . $OLD ) === $compact['targets'][0]['before_sha1'] );
check(
	'compact_plan: hash CHANGES if the row is edited after queueing',
	sha1( 'BBB' . $OLD ) !== $compact['targets'][0]['before_sha1'],
	'this comparison is the only thing stopping an apply from clobbering a newer edit'
);

/* ------------- regression: sibling swaps against the SAME row must compose */

/* Production incident: six banner swaps were queued against page #72, then
   approved together. The first apply rewrote the page, which changed its
   hash, and the other five refused with "value changed after this was
   queued". They were colliding with each other, not with an external edit.

   A URL swap re-reads the row and replaces only the URL, so it merges with
   whatever else changed. The row's hash is therefore NOT a reason to refuse;
   the only real drift signal is the old URL having already gone. */

$page = '[et_pb_section background_image="https://s.com/u/a.png"]'
	. '[et_pb_section background_image="https://s.com/u/b.png"]'
	. '[et_pb_text]Some copy a human might edit.[/et_pb_text]';

// Swap A, as change #1 would.
list( $after_a, $n_a ) = CC_Assistant_Asset_References::rewrite_value( $page, 'https://s.com/u/a.png', 'https://s.com/u/a.webp' );
check( 'sibling: first swap applies', 1 === $n_a && false !== strpos( $after_a, 'a.webp' ), $after_a );

// The row's bytes have now changed. Change #2 targets a DIFFERENT url in the
// same row and must still succeed against the updated content.
list( $after_b, $n_b ) = CC_Assistant_Asset_References::rewrite_value( $after_a, 'https://s.com/u/b.png', 'https://s.com/u/b.webp' );
check( 'sibling: second swap still applies to the rewritten row', 1 === $n_b, "n=$n_b" );
check( 'sibling: first swap survives the second', false !== strpos( $after_b, 'a.webp' ), $after_b );
check( 'sibling: both URLs now webp', false === strpos( $after_b, '.png' ), $after_b );

// A genuine concurrent human edit is preserved, not clobbered.
$human_edited = str_replace( 'Some copy a human might edit.', 'Copy the client rewrote while this sat in the inbox.', $after_a );
list( $after_c, $n_c ) = CC_Assistant_Asset_References::rewrite_value( $human_edited, 'https://s.com/u/b.png', 'https://s.com/u/b.webp' );
check( 'concurrent edit: swap still applies', 1 === $n_c, "n=$n_c" );
check(
	'concurrent edit: the human edit is PRESERVED',
	false !== strpos( $after_c, 'Copy the client rewrote while this sat in the inbox.' ),
	'this is why a whole-value hash guard is the wrong tool here'
);

// Old URL already gone = the one real drift case, and it is detectable
// without a hash: nothing to replace.
list( $after_d, $n_d ) = CC_Assistant_Asset_References::rewrite_value( $after_b, 'https://s.com/u/a.png', 'https://s.com/u/a.webp' );
check( 'already-swapped row reports zero replacements', 0 === $n_d, "n=$n_d" );

/* --------------------------------------- REST guard: new_url must be real */

define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
function wp_get_upload_dir() {
	return array(
		'baseurl' => 'https://sids-ponds.com/wp-content/uploads',
		'basedir' => sys_get_temp_dir() . '/cc-uploads-test',
	);
}
require dirname( __DIR__ ) . '/includes/class-rest-assets.php';

$updir = sys_get_temp_dir() . '/cc-uploads-test/2026/08';
if ( ! is_dir( $updir ) ) {
	mkdir( $updir, 0777, true );
}
file_put_contents( $updir . '/real.webp', 'x' );

$missing = new ReflectionMethod( 'CC_Assistant_REST_Assets', 'local_file_missing' );
$missing->setAccessible( true );
$probe = function ( $url ) use ( $missing ) {
	return $missing->invoke( null, $url );
};

check( 'guard: existing local file passes', null === $probe( 'https://sids-ponds.com/wp-content/uploads/2026/08/real.webp' ) );
check( 'guard: missing local file is caught', null !== $probe( 'https://sids-ponds.com/wp-content/uploads/2026/08/ghost.webp' ) );
check( 'guard: root-relative resolves too', null === $probe( '/wp-content/uploads/2026/08/real.webp' ) );
check( 'guard: query string ignored', null === $probe( 'https://sids-ponds.com/wp-content/uploads/2026/08/real.webp?ver=2' ) );
check( 'guard: percent-encoded name resolves', null === $probe( 'https://sids-ponds.com/wp-content/uploads/2026/08/re%61l.webp' ) );
check( 'guard: external host is not our problem', null === $probe( 'https://cdn.example.com/img/hero.webp' ) );
check(
	'guard: a traversal path is never resolved to a filesystem check',
	null === $probe( 'https://sids-ponds.com/wp-content/uploads/../../wp-config.php' ),
	'null here means the disk check was SKIPPED, not that the URL was approved — file_exists() must never be handed a ../ path'
);

@unlink( $updir . '/real.webp' );

echo "\n";
if ( $fails ) {
	echo "$fails FAILURE(S)\n";
	exit( 1 );
}
echo "All asset-reference tests passed.\n";
exit( 0 );
