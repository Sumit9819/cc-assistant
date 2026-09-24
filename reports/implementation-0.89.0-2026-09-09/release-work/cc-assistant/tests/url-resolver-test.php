<?php
/**
 * CC_Assistant_URL_Resolver test.
 *
 * Covers the case that motivated the class: a Search Console URL that 301s to
 * its replacement must resolve to the DESTINATION post, not to 0. Also pins
 * the failure modes that would be worse than not resolving at all — a loop
 * that never terminates, and a 410 reported as a plain miss.
 *
 * Run: php tests/url-resolver-test.php
 */
error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );

/* ---------------------------------------------------------------------------
 * WordPress stubs. Deliberately literal: the fixture below is the shape of a
 * real site (a live post, a consolidated post, a chain, a loop, a 410).
 * ------------------------------------------------------------------------ */

$GLOBALS['cc_test_posts'] = array(
	// path => post ID (what url_to_postid would answer for a live permalink)
	'how-iv-therapy-fights-chronic-fatigue'                   => 6356,
	'what-is-microneedling-collagen-induction-therapy-guide'   => 6449,
	'aesthetic-treatments-irving-tx'                           => 618,
	'nueva-pagina'                                             => 7001,
);

// Pages reachable by slug but NOT by url_to_postid — the Custom Permalinks /
// virtual-prefix case that class-internal-links.php already worked around.
$GLOBALS['cc_test_slugs'] = array(
	'abdominal-pain-treatment' => 5342,
);

$GLOBALS['cc_test_transients'] = array();

class WP_Post {
	public $ID = 0;
	public function __construct( $id ) {
		$this->ID = $id;
	}
}

function home_url( $path = '/' ) {
	return 'https://example.test' . ( '/' === substr( $path, 0, 1 ) ? $path : '/' . $path );
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function url_to_postid( $url ) {
	$p = parse_url( $url );
	// A URL on another host is never a local post.
	if ( ! empty( $p['host'] ) && 'example.test' !== $p['host'] ) {
		return 0;
	}
	$path = isset( $p['path'] ) ? trim( strtolower( $p['path'] ), '/' ) : '';
	return isset( $GLOBALS['cc_test_posts'][ $path ] ) ? $GLOBALS['cc_test_posts'][ $path ] : 0;
}

function get_page_by_path( $path, $output = OBJECT, $types = array() ) {
	$path = trim( strtolower( (string) $path ), '/' );
	// Real WordPress finds a post by slug whether or not its permalink happens
	// to match that path, so both fixture maps are in scope here.
	if ( isset( $GLOBALS['cc_test_slugs'][ $path ] ) ) {
		return new WP_Post( $GLOBALS['cc_test_slugs'][ $path ] );
	}
	if ( isset( $GLOBALS['cc_test_posts'][ $path ] ) ) {
		return new WP_Post( $GLOBALS['cc_test_posts'][ $path ] );
	}
	return null;
}

function maybe_unserialize( $v ) {
	if ( ! is_string( $v ) ) {
		return $v;
	}
	$out = @unserialize( $v );
	return false === $out && 'b:0;' !== $v ? $v : $out;
}

function get_transient( $k ) {
	return isset( $GLOBALS['cc_test_transients'][ $k ] ) ? $GLOBALS['cc_test_transients'][ $k ] : false;
}
function set_transient( $k, $v, $t = 0 ) {
	$GLOBALS['cc_test_transients'][ $k ] = $v;
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['cc_test_transients'][ $k ] );
	return true;
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/** Minimal $wpdb exposing just what redirect_rows() touches. */
class CC_Test_WPDB {
	public $prefix   = 'wp_';
	public $postmeta = 'wp_postmeta';
	public $rows     = array();
	public $cp_rows  = array();
	public $has_table = true;

	public function prepare( $sql, ...$args ) {
		foreach ( $args as $a ) {
			$sql = preg_replace( '/%s|%d/', is_int( $a ) ? (string) $a : "'" . $a . "'", $sql, 1 );
		}
		return $sql;
	}
	public function get_var( $sql ) {
		return $this->has_table ? 'wp_rank_math_redirections' : null;
	}
	public function get_results( $sql, $mode = ARRAY_A ) {
		if ( false !== strpos( $sql, 'postmeta' ) ) {
			return $this->cp_rows;
		}
		return $this->rows;
	}
}

$wpdb = new CC_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;

/** Build a Rank Math style row. */
function rm_row( $sources, $to, $code = 301 ) {
	$src = array();
	foreach ( (array) $sources as $s ) {
		$pattern    = is_array( $s ) ? $s[0] : $s;
		$comparison = is_array( $s ) ? $s[1] : 'exact';
		$src[]      = array( 'pattern' => $pattern, 'comparison' => $comparison );
	}
	return array(
		'sources'     => serialize( $src ),
		'url_to'      => $to,
		'header_code' => $code,
	);
}

$wpdb->rows = array(
	// The real irvingwellnessclinic case.
	rm_row( 'iv-therapy-for-fatigue-how-it-works', 'https://example.test/how-iv-therapy-fights-chronic-fatigue/' ),
	rm_row( 'does-microneedling-hurt-what-to-expect', '/what-is-microneedling-collagen-induction-therapy-guide/' ),
	// Two sources onto one destination (Rank Math allows this).
	rm_row( array( 'laser-genesis-large-pores', 'laser-genesis-rosacea' ), '/aesthetic-treatments-irving-tx/' ),
	// A chain: old -> interim -> live.
	rm_row( 'very-old-url', '/interim-url/' ),
	rm_row( 'interim-url', '/aesthetic-treatments-irving-tx/' ),
	// A loop.
	rm_row( 'loop-a', '/loop-b/' ),
	rm_row( 'loop-b', '/loop-a/' ),
	// Deliberate retirement.
	rm_row( 'retired-junk-post', '', 410 ),
	// Prefix rule + a more specific exact rule that must win.
	rm_row( array( array( 'old-section', 'start' ) ), '/aesthetic-treatments-irving-tx/' ),
	rm_row( 'old-section/special', '/how-iv-therapy-fights-chronic-fatigue/' ),
	// Comparison types we refuse to guess at.
	rm_row( array( array( 'fuzzy-thing', 'regex' ) ), '/aesthetic-treatments-irving-tx/' ),
);

// Custom Permalinks: an arbitrary URL stored in postmeta, routed by that
// plugin. Core cannot resolve it, which is why swapped service pages read as
// orphaned across four sites.
$wpdb->cp_rows = array(
	array( 'post_id' => 5342, 'meta_value' => 'services/abdominal-pain-treatment-in-white-rock/' ),
	array( 'post_id' => 5360, 'meta_value' => '/services/emergency-head-injury/' ),
);

require dirname( __DIR__ ) . '/includes/class-url-resolver.php';

$fails = 0;
function check( $label, $cond, $detail = '' ) {
	global $fails;
	if ( $cond ) {
		echo "PASS  $label\n";
	} else {
		$fails++;
		echo "FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n";
	}
}

$R = 'CC_Assistant_URL_Resolver';

/* -------------------------------------------------------------- direct ---- */
$r = $R::resolve( 'https://example.test/how-iv-therapy-fights-chronic-fatigue/' );
check( 'live permalink resolves direct', 6356 === $r['post_id'] && 'direct' === $r['via'], json_encode( $r ) );
check( 'direct explains to empty string', '' === $R::explain( $r ) );

/* ---------------------------------------------------------------- path ---- */
$R::flush();
$r = $R::resolve( 'https://example.test/services/abdominal-pain-treatment/' );
check( 'virtual prefix falls back to slug', 5342 === $r['post_id'] && 'path' === $r['via'], json_encode( $r ) );

$R::flush();
$r = $R::resolve( 'https://example.test/es/nueva-pagina/' );
check( 'language prefix stripped', 7001 === $r['post_id'], json_encode( $r ) );

/* ------------------------------------------------------------ redirect ---- */
$R::flush();
$r = $R::resolve( 'https://example.test/iv-therapy-for-fatigue-how-it-works/' );
check( 'redirected URL resolves to destination post', 6356 === $r['post_id'], json_encode( $r ) );
check( 'redirect via + code reported', 'redirect' === $r['via'] && 301 === $r['code'] && 1 === $r['hops'], json_encode( $r ) );
check( 'redirect explanation names destination', false !== strpos( $R::explain( $r ), 'how-iv-therapy-fights-chronic-fatigue' ), $R::explain( $r ) );

$R::flush();
$r = $R::resolve( 'https://example.test/does-microneedling-hurt-what-to-expect/' );
check( 'relative destination resolves', 6449 === $r['post_id'], json_encode( $r ) );

$R::flush();
$a = $R::resolve( '/laser-genesis-large-pores/' );
$b = $R::resolve( '/laser-genesis-rosacea/' );
check( 'multiple sources -> one destination', 618 === $a['post_id'] && 618 === $b['post_id'], json_encode( array( $a, $b ) ) );

$R::flush();
$r = $R::resolve( '/very-old-url/' );
check( 'redirect chain followed to the end', 618 === $r['post_id'] && 2 === $r['hops'], json_encode( $r ) );

/* --------------------------------------------------------- edge cases ---- */
$R::flush();
$r = $R::resolve( '/loop-a/' );
check( 'loop detected, not infinite', 0 === $r['post_id'] && 'loop' === $r['via'], json_encode( $r ) );
check( 'loop explanation is actionable', false !== strpos( $R::explain( $r ), 'loop' ), $R::explain( $r ) );

$R::flush();
$r = $R::resolve( '/retired-junk-post/' );
check( '410 reported as gone, not unresolved', 'gone' === $r['via'] && 410 === $r['code'], json_encode( $r ) );
check( '410 explanation says do not add a 301', false !== strpos( $R::explain( $r ), '410' ), $R::explain( $r ) );

$R::flush();
$r = $R::resolve( '/old-section/special/' );
check( 'exact rule beats start prefix', 6356 === $r['post_id'], json_encode( $r ) );

$R::flush();
$r = $R::resolve( '/old-section/anything-else/' );
check( 'start prefix still matches non-exact paths', 618 === $r['post_id'], json_encode( $r ) );

$R::flush();
$r = $R::resolve( '/fuzzy-thing-xyz/' );
check( 'regex comparison left unresolved rather than guessed', 0 === $r['post_id'] && 'unresolved' === $r['via'], json_encode( $r ) );

$R::flush();
$r = $R::resolve( 'https://example.test/genuinely-missing/' );
check( 'true miss is unresolved', 0 === $r['post_id'] && 'unresolved' === $r['via'], json_encode( $r ) );
check( 'miss explanation does not invent a cause', false === strpos( $R::explain( $r ), 'Custom Permalinks' ), $R::explain( $r ) );

$R::flush();
$r = $R::resolve( 'https://other-site.example/how-iv-therapy-fights-chronic-fatigue/' );
check( 'foreign host does not resolve to a local post', 0 === $r['post_id'], json_encode( $r ) );

$R::flush();
$r = $R::resolve( 'https://example.test/iv-therapy-for-fatigue-how-it-works/?utm_source=x#frag' );
check( 'query string and fragment ignored', 6356 === $r['post_id'], json_encode( $r ) );

$R::flush();
check( 'to_post_id returns int', 6356 === $R::to_post_id( '/iv-therapy-for-fatigue-how-it-works/' ) );
check( 'empty url is safe', 0 === $R::to_post_id( '' ) );

/* --------------------------------------------------- custom permalinks ---- */
$R::flush();
$r = $R::resolve( 'https://example.test/services/abdominal-pain-treatment-in-white-rock/' );
check( 'custom_permalink URL resolves', 5342 === $r['post_id'] && 'custom_permalink' === $r['via'], json_encode( $r ) );

$R::flush();
$r = $R::resolve( '/services/emergency-head-injury/' );
check( 'custom_permalink stored with leading slash resolves', 5360 === $r['post_id'], json_encode( $r ) );

$R::flush();
$r = $R::resolve( 'https://other-site.example/services/emergency-head-injury/' );
check( 'custom_permalink not matched across hosts', 0 === $r['post_id'], json_encode( $r ) );

/* ------------------------------------------------------------- caching ---- */
$R::flush();
$R::resolve( '/very-old-url/' );
check( 'redirect map cached in transient', isset( $GLOBALS['cc_test_transients']['cc_assistant_redirect_map'] ) );
$R::flush();
check( 'flush clears the transient', ! isset( $GLOBALS['cc_test_transients']['cc_assistant_redirect_map'] ) );

// No redirect table at all (Rank Math absent) must degrade quietly.
$R::flush();
$wpdb->has_table = false;
$r = $R::resolve( '/iv-therapy-for-fatigue-how-it-works/' );
check( 'no redirect table -> unresolved, no fatal', 0 === $r['post_id'] && 'unresolved' === $r['via'], json_encode( $r ) );
$wpdb->has_table = true;

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
