<?php
/**
 * Elementor parser: container-level links.
 *
 * Regression for a real error. A homepage services grid had six cards, each a
 * CONTAINER with settings.link.url pointing at a service page. Elementor
 * renders that as <a class="e-con" href=...> wrapping the whole card. The
 * parser walked containers for their children but never read the container's
 * own link, so audit_post_links and get_elementor_widgets both reported the
 * grid as "links to nothing". That false gap was acted on: six pendings were
 * queued to add heading links that would have nested <a> inside <a>.
 *
 * This test feeds the parser exactly that structure and asserts the six links
 * come out. It also pins that a container WITHOUT a link adds nothing (no
 * phantom edges), and that the label resolves to the card's heading rather
 * than the "Popular" badge that sits above it.
 *
 * Run: php tests/elementor-parser-links-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );

/* ---- WP stubs ---------------------------------------------------------- */
$GLOBALS['cc_elementor_data'] = '';
class WP_Post { public $ID; public $post_title = 'Home'; public $post_status = 'publish'; public $post_type = 'page'; public function __construct( $id ) { $this->ID = $id; } }
function get_post( $id ) { return new WP_Post( (int) $id ); }
function get_post_meta( $id, $key, $single = false ) { return '_elementor_data' === $key ? $GLOBALS['cc_elementor_data'] : ''; }
function get_permalink( $id ) { return 'https://x.test/'; }
function get_edit_post_link( $id, $ctx = '' ) { return 'https://x.test/wp-admin/post.php?post=' . $id; }
function home_url( $p = '/' ) { return 'https://x.test' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url( $u ) { return $u; }
function wp_kses_post( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
if ( ! function_exists( 'mb_strpos' ) ) { function mb_strpos( $h, $n ) { return strpos( $h, $n ); } }

require dirname( __DIR__ ) . '/includes/class-elementor-parser.php';

$fails = 0;
function check( $label, $cond, $detail = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n"; }
}

/* ---- fixture: the real card-grid shape ---------------------------------- */
function card( $id, $label, $url ) {
	return array(
		'id' => $id, 'elType' => 'container',
		'settings' => array( 'link' => array( 'url' => $url, 'is_external' => '', 'nofollow' => '' ) ),
		'elements' => array(
			array( 'id' => $id . 'p', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Popular', 'header_size' => 'span' ), 'elements' => array() ),
			array( 'id' => $id . 'i', 'elType' => 'widget', 'widgetType' => 'image',   'settings' => array( 'image' => array( 'url' => 'https://x.test/a.webp', 'alt' => '' ) ), 'elements' => array() ),
			array( 'id' => $id . 'c', 'elType' => 'container', 'settings' => array(), 'elements' => array(
				array( 'id' => $id . 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $label, 'header_size' => 'h3' ), 'elements' => array() ),
			) ),
		),
	);
}
$grid = array(
	'id' => 'grid0001', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		card( 'bc25dce', 'Medical Weight Loss', 'https://x.test/medical-weight-loss-irving-tx/' ),
		card( '812322f', 'Aesthetic Treatment', 'https://x.test/aesthetic-treatments-irving-tx/' ),
		card( '6704eef', 'Hormone Therapy',     'https://x.test/hormone-therapy-irving-tx/' ),
		card( '9d1798e', 'IV Hydration Therapy','https://x.test/iv-therapy-irving-tx/' ),
		card( '1eb3e06', 'Botox',               'https://x.test/botox-irving-tx/' ),
		card( '232e713', 'Microneedling',       'https://x.test/microneedling-irving-tx/' ),
		// an UNLINKED container with a heading: must add no edge
		array( 'id' => 'plain001', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'plainh', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Not a link', 'header_size' => 'h3' ), 'elements' => array() ),
		) ),
	),
);
$GLOBALS['cc_elementor_data'] = json_encode( array( $grid ) );

$parsed = CC_Assistant_Elementor_Parser::parse( 8 );
check( 'parser returns a result', is_array( $parsed ) );
$links = isset( $parsed['all_links'] ) ? $parsed['all_links'] : array();
$urls  = array_column( $links, 'url' );

check( 'six container links are found (the bug)', 6 === count( $links ), 'found ' . count( $links ) . ': ' . json_encode( $urls ) );
check( 'weight loss card resolves', in_array( 'https://x.test/medical-weight-loss-irving-tx/', $urls, true ) );
check( 'microneedling card resolves', in_array( 'https://x.test/microneedling-irving-tx/', $urls, true ) );
check( 'all six are internal', 6 === count( array_filter( $links, function ( $l ) { return ! empty( $l['is_internal'] ); } ) ) );

$by = array();
foreach ( $links as $l ) { $by[ $l['url'] ] = $l; }
$wl = $by['https://x.test/medical-weight-loss-irving-tx/'] ?? array();
check( 'anchor is the card heading, not the Popular badge', 'Medical Weight Loss' === ( $wl['anchor'] ?? '' ), json_encode( $wl['anchor'] ?? null ) );
check( 'link is attributed to the container id', 'bc25dce' === ( $wl['widget_id'] ?? '' ), json_encode( $wl['widget_id'] ?? null ) );
check( 'link is marked as wrapping its children', ! empty( $wl['wraps_children'] ) );
check( 'widget_type says container(linked)', 'container(linked)' === ( $wl['widget_type'] ?? '' ) );
check( 'unlinked container adds no phantom link', ! in_array( '', $urls, true ) && 6 === count( $urls ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
