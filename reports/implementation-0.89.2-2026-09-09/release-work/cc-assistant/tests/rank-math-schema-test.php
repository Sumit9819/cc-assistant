<?php
/**
 * Rank Math Schema Builder merge: pure-path tests for the dot-path helpers
 * behind get_rank_math_schema / draft_update_rank_math_schema.
 *
 * The property that matters most: merging one leaf must leave every other
 * leaf — especially Rank Math's %variable% placeholders — byte-identical.
 * Run: php tests/rank-math-schema-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-rest-api.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label  $extra\n"; }
}

/** Reach the private static helpers the REST handler composes. */
function call_private( $method, array $args ) {
	$m = new ReflectionMethod( 'CC_Assistant_REST_API', $method );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}

/** set_schema_path takes its tree by reference. */
function merge_path( array $tree, $path, $value ) {
	$m = new ReflectionMethod( 'CC_Assistant_REST_API', 'set_schema_path' );
	$m->setAccessible( true );
	$m->invokeArgs( null, array( &$tree, $path, $value ) );
	return $tree;
}

// A realistic Schema Builder Product row: placeholders at several depths, an
// empty price (the exact defect this tool exists to fix), and a nested list.
$product = array(
	'@type'    => 'Product',
	'name'     => '%seo_title%',
	'description' => '%seo_description%',
	'sku'      => 'WL7500',
	'image'    => array( '@type' => 'ImageObject', 'url' => '%post_thumbnail%' ),
	'brand'    => array( '@type' => 'Brand', 'name' => 'Mammoth Machinery' ),
	'offers'   => array(
		'@type'         => 'Offer',
		'price'         => '',
		'priceCurrency' => '',
		'availability'  => '',
		'url'           => '%url%',
	),
	'metadata' => array( 'title' => 'Product', 'type' => 'template', 'isPrimary' => '1', 'shortcode' => 's-9f2c1b' ),
);

// ---------------------------------------------------------------- flatten --
$paths = array();
$f = new ReflectionMethod( 'CC_Assistant_REST_API', 'flatten_schema_paths' );
$f->setAccessible( true );
$f->invokeArgs( null, array( $product, '', &$paths ) );

check( 'flatten reaches nested leaves', isset( $paths['offers.price'] ) && isset( $paths['image.url'] ) && isset( $paths['brand.name'] ), json_encode( array_keys( $paths ) ) );
check( 'flatten keeps root leaves', '%seo_title%' === $paths['name'] && 'WL7500' === $paths['sku'] );
check( 'flatten emits no branch keys', ! isset( $paths['offers'] ) && ! isset( $paths['brand'] ) );

// ------------------------------------------------------- path validation --
check( 'existing branch accepted', '' === call_private( 'validate_schema_path', array( 'offers.price', $product ) ) );
check( 'root leaf accepted', '' === call_private( 'validate_schema_path', array( 'sku', $product ) ) );
check( '@-prefixed segment accepted', '' === call_private( 'validate_schema_path', array( 'offers.@type', $product ) ) );
check( 'new leaf on existing branch accepted', '' === call_private( 'validate_schema_path', array( 'offers.priceValidUntil', $product ) ) );

$e = call_private( 'validate_schema_path', array( 'aggregateRating.ratingValue', $product ) );
check( 'invented branch refused', '' !== $e && false !== strpos( $e, 'does not exist' ), $e );

$e = call_private( 'validate_schema_path', array( 'sku.deeper', $product ) );
check( 'descending into a scalar refused', '' !== $e && false !== strpos( $e, 'scalar' ), $e );

$e = call_private( 'validate_schema_path', array( 'offers.pri ce', $product ) );
check( 'malformed segment refused', '' !== $e && false !== strpos( $e, 'not a valid schema key' ), $e );

check( 'empty path refused', '' !== call_private( 'validate_schema_path', array( '', $product ) ) );

// Regression: "offers" instead of "offers.price" is one keystroke away, and
// writing a scalar there deletes the entire Offer node. get_schema_path()
// reports a branch as null, so the reviewer's diff rendered that destruction
// as a harmless "(not set)" -> "39999". Must be refused outright.
$e = call_private( 'validate_schema_path', array( 'offers', $product ) );
check( 'writing over a branch refused', '' !== $e && false !== strpos( $e, 'branch, not a leaf' ), $e );
check( 'branch refusal names the real leaves', false !== strpos( (string) $e, 'offers.price' ), $e );

$e = call_private( 'validate_schema_path', array( 'metadata', $product ) );
check( 'Rank Math metadata branch protected', '' !== $e && false !== strpos( $e, 'branch, not a leaf' ), $e );

$e = call_private( 'validate_schema_path', array( 'image', $product ) );
check( 'nested object branch protected', '' !== $e, $e );

check( 'leaf inside a protected branch still allowed', '' === call_private( 'validate_schema_path', array( 'image.url', $product ) ) );

// An empty branch is still a branch: replacing it with a scalar is the same
// structural edit, just with nothing visible to lose.
$with_empty = $product;
$with_empty['audience'] = array();
$e = call_private( 'validate_schema_path', array( 'audience', $with_empty ) );
check( 'empty branch also protected', '' !== $e && false !== strpos( $e, 'currently empty' ), $e );
check( 'leaf may be added to an empty branch', '' === call_private( 'validate_schema_path', array( 'audience.audienceType', $with_empty ) ) );

check( 'brand-new root leaf still allowed', '' === call_private( 'validate_schema_path', array( 'gtin13', $product ) ) );

// ------------------------------------------------------ value validation --
check( 'plain price accepted', '' === call_private( 'validate_schema_value', array( 'offers.price', '139999' ) ) );
check( 'decimal price accepted', '' === call_private( 'validate_schema_value', array( 'offers.price', '139999.00' ) ) );

$e = call_private( 'validate_schema_value', array( 'offers.price', '$139,999' ) );
check( 'price with symbol+comma refused', '' !== $e && false !== strpos( $e, 'no thousands comma' ), $e );

$e = call_private( 'validate_schema_value', array( 'offers.price', '139,999' ) );
check( 'price with comma refused', '' !== $e, $e );

check( 'ISO currency accepted', '' === call_private( 'validate_schema_value', array( 'offers.priceCurrency', 'CAD' ) ) );
check( 'lowercase currency refused', '' !== call_private( 'validate_schema_value', array( 'offers.priceCurrency', 'cad' ) ) );

check( 'bare availability token accepted', '' === call_private( 'validate_schema_value', array( 'offers.availability', 'InStock' ) ) );
check( 'prefixed availability accepted', '' === call_private( 'validate_schema_value', array( 'offers.availability', 'https://schema.org/InStock' ) ) );
check( 'bogus availability refused', '' !== call_private( 'validate_schema_value', array( 'offers.availability', 'Available' ) ) );

check( 'ISO date accepted', '' === call_private( 'validate_schema_value', array( 'offers.priceValidUntil', '2027-12-31' ) ) );
check( 'loose date refused', '' !== call_private( 'validate_schema_value', array( 'offers.priceValidUntil', '31/12/2027' ) ) );

$e = call_private( 'validate_schema_value', array( 'name', '%seo_title%' ) );
check( 'writing a %variable% refused', '' !== $e && false !== strpos( $e, 'placeholder' ), $e );

$e = call_private( 'validate_schema_value', array( 'description', 'A <strong>great</strong> loader' ) );
check( 'HTML in a schema value refused', '' !== $e && false !== strpos( $e, 'HTML' ), $e );

check( 'ordinary text value accepted', '' === call_private( 'validate_schema_value', array( 'description', 'Articulating wheel loader, 4.5 t.' ) ) );
check( 'price rule is leaf-scoped, not substring', '' === call_private( 'validate_schema_value', array( 'offers.priceCurrency', 'CAD' ) ) );

// -------------------------------------------------------------- the merge --
$merged = merge_path( $product, 'offers.price', '139999' );

check( 'target leaf set', '139999' === $merged['offers']['price'] );
check( 'sibling leaves untouched', '%url%' === $merged['offers']['url'] && '' === $merged['offers']['priceCurrency'] );
check( 'root placeholder survives', '%seo_title%' === $merged['name'] );
check( 'nested placeholder survives', '%post_thumbnail%' === $merged['image']['url'] );
check( 'Rank Math metadata survives', 's-9f2c1b' === $merged['metadata']['shortcode'] && '1' === $merged['metadata']['isPrimary'] );
check( 'original tree not mutated', '' === $product['offers']['price'], 'set_schema_path leaked by reference' );

$diff = array();
foreach ( $merged as $k => $v ) {
	if ( ! array_key_exists( $k, $product ) || $product[ $k ] !== $v ) { $diff[] = $k; }
}
check( 'exactly one top-level branch changed', array( 'offers' ) === $diff, json_encode( $diff ) );

// A new leaf lands on an existing branch without disturbing it.
$merged2 = merge_path( $merged, 'offers.priceValidUntil', '2027-12-31' );
check( 'new leaf added', '2027-12-31' === $merged2['offers']['priceValidUntil'] );
check( 'earlier merge preserved', '139999' === $merged2['offers']['price'] );
check( 'branch key count grew by one', count( $merged2['offers'] ) === count( $merged['offers'] ) + 1 );

// ------------------------------------------------------------ path reader --
check( 'reader returns nested leaf', '%url%' === call_private( 'get_schema_path', array( $product, 'offers.url' ) ) );
check( 'reader returns empty leaf as empty string', '' === call_private( 'get_schema_path', array( $product, 'offers.price' ) ) );
check( 'reader returns null for missing path', null === call_private( 'get_schema_path', array( $product, 'offers.nope' ) ) );
check( 'reader returns null for a branch', null === call_private( 'get_schema_path', array( $product, 'offers' ) ) );

// ------------------------------------------------------------- round trip --
// The queue JSON-encodes the merged array and apply writes what comes back;
// the shape must survive that trip unchanged or the revert payload lies.
$round = json_decode( json_encode( $merged2 ), true );
check( 'survives JSON round trip', $round === $merged2, json_encode( $round ) );

// -------------------------------------------------------------- key guard --
$pattern = call_private( 'rank_math_schema_key_pattern', array() );
check( 'accepts capitalised Type key', 1 === preg_match( $pattern, 'rank_math_schema_Product' ) );
check( 'accepts multiword Type key', 1 === preg_match( $pattern, 'rank_math_schema_LocalBusiness' ) );
check( 'rejects the legacy scalar key', 0 === preg_match( $pattern, 'rank_math_rich_snippet' ) );
check( 'rejects a bare prefix', 0 === preg_match( $pattern, 'rank_math_schema_' ) );
check( 'rejects an unrelated meta key', 0 === preg_match( $pattern, '_elementor_data' ) );
check( 'rejects a traversal attempt', 0 === preg_match( $pattern, 'rank_math_schema_A/../_elementor_data' ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );
