<?php
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
function is_serialized( $d, $s = true ) {
	if ( ! is_string( $d ) ) { return false; }
	$d = trim( $d );
	if ( 'N;' === $d ) { return true; }
	if ( strlen( $d ) < 4 || ':' !== $d[1] ) { return false; }
	return (bool) preg_match( '/^[aOsbdi]:/', $d );
}
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }

require 'c:/Users/sumit/Local Sites/plugintesting/app/public/wp-content/plugins/cc-assistant/includes/class-asset-references.php';

$cases = array(
	// label, old, new, stored value
	array(
		'new URL contains old filename (moved into a subfolder)',
		'https://s.com/uploads/a.png',
		'https://s.com/uploads/2026/a.png',
		'<img src="https://s.com/uploads/a.png">',
	),
	array(
		'new path is an extension of the old path',
		'https://s.com/u/hero.jpg',
		'https://s.com/u/hero.jpg.webp',
		'<img src="https://s.com/u/hero.jpg">',
	),
	array(
		'ordinary same-folder rename (control)',
		'https://s.com/u/a.png',
		'https://s.com/u/b.png',
		'<img src="https://s.com/u/a.png">',
	),
	array(
		'root-relative stored, new in subfolder',
		'https://s.com/u/a.png',
		'https://s.com/u/2026/a.png',
		'background:url(/u/a.png)',
	),
);

foreach ( $cases as $c ) {
	list( $label, $old, $new, $value ) = $c;
	list( $out, $n, $err ) = CC_Assistant_Asset_References::rewrite_value( $value, $old, $new );
	$occurrences = substr_count( $out, 'a.png' ) + substr_count( $out, 'hero.jpg' );
	echo "--- $label\n";
	echo "    in  : $value\n";
	echo "    out : $out\n";
	echo "    n=$n err=" . ( $err ?: '(none)' ) . "\n";
	// The output should contain the new URL exactly once and nothing malformed.
	$ok = ( false !== strpos( $out, $new ) );
	echo '    contains the intended new URL: ' . ( $ok ? 'YES' : 'NO  <<<< BROKEN' ) . "\n\n";
}

// hit counting accuracy
$hits = new ReflectionMethod( 'CC_Assistant_Asset_References', 'count_hits' );
$hits->setAccessible( true );
$url  = 'https://s.com/u/a.png';
$vars = CC_Assistant_Asset_References::needle_variants( $url );
$one  = '<img src="https://s.com/u/a.png">';
echo 'count_hits on a value with exactly ONE occurrence: ' . $hits->invoke( null, $one, $vars ) . " (should be 1)\n";
