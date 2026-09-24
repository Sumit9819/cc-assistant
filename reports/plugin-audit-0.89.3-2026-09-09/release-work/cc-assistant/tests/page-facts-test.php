<?php
/**
 * Page Facts: the pure logic that decides what is true about a page.
 *
 * Three things are pinned because each one, wrong, produces a confident lie:
 *   1. link location - a footer-menu link must never read as an in-body link
 *      (it inflates "this page supports X" and hides real gaps).
 *   2. staleness - a record must be stale after a post edit, after a plugin
 *      apply, or after MAX_AGE, and fresh otherwise.
 *   3. expectations - the after-apply verifier must FAIL when the DOM does not
 *      show the intended change (this is the noindex bug, made impossible to
 *      miss) and pass when it does.
 *
 * Run: php tests/page-facts-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
if ( ! defined( 'XML_ELEMENT_NODE' ) ) { define( 'XML_ELEMENT_NODE', 1 ); }

/* ---- WP stubs (only what the pure helpers touch) ---- */
function home_url( $p = '/' ) { return 'https://x.test' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function add_action() {}
function get_option( $k, $d = null ) { return $d; }
function is_admin() { return false; }
function wp_next_scheduled() { return false; }
function wp_schedule_event() {}
function wp_schedule_single_event() {}
function wp_is_post_autosave() { return false; }
function wp_is_post_revision() { return false; }
class WP_Error { private $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } public function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
// The Local PHP CLI is built without mbstring; WordPress core polyfills these.
if ( ! function_exists( 'mb_substr' ) ) { function mb_substr( $s, $a, $l = null ) { return null === $l ? substr( $s, $a ) : substr( $s, $a, $l ); } }
if ( ! function_exists( 'mb_strtolower' ) ) { function mb_strtolower( $s ) { return strtolower( $s ); } }
if ( ! function_exists( 'mb_strpos' ) ) { function mb_strpos( $h, $n, $o = 0 ) { return strpos( $h, $n, $o ); } }

require dirname( __DIR__ ) . '/includes/class-page-facts.php';

$fails = 0;
function check( $label, $cond, $detail = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n"; }
}
$F = 'CC_Assistant_Page_Facts';

/* ---------------------------------------------------------- href kinds --- */
echo "--- href classification ---\n";
check( 'same host is internal',          'internal' === $F::href_kind( 'https://x.test/a/', 'x.test' ) );
check( 'www variant is internal',        'internal' === $F::href_kind( 'https://www.x.test/a/', 'x.test' ) );
check( 'relative path is internal',      'internal' === $F::href_kind( '/a/', 'x.test' ) );
check( 'other host is external',         'external' === $F::href_kind( 'https://cdc.gov/x', 'x.test' ) );
check( 'fragment is skipped',            'skip'     === $F::href_kind( '#pricing', 'x.test' ) );
check( 'bare # is skipped',              'skip'     === $F::href_kind( '#', 'x.test' ) );
check( 'tel is skipped',                 'skip'     === $F::href_kind( 'tel:+19725550100', 'x.test' ) );
check( 'mailto is skipped',              'skip'     === $F::href_kind( 'mailto:a@b.c', 'x.test' ) );
check( 'path normalises trailing slash', '/a/b/' === $F::norm_path( 'https://x.test/a/b' ) && '/a/b/' === $F::norm_path( '/a/b/?utm=1' ) );
check( 'root normalises to /',           '/' === $F::norm_path( 'https://x.test/' ) );

/* ------------------------------------------------------- link location --- */
echo "\n--- link location (footer must never look like content) ---\n";
check( 'plain content', 'content' === $F::classify_link_location( array( array( 'tag' => 'p', 'class' => '', 'id' => '' ), array( 'tag' => 'div', 'class' => 'elementor-widget-text-editor', 'id' => '' ) ) ) );
check( 'elementor footer template', 'footer' === $F::classify_link_location( array( array( 'tag' => 'li', 'class' => 'menu-item', 'id' => '' ), array( 'tag' => 'div', 'class' => 'elementor elementor-location-footer', 'id' => '' ) ) ),
	'footer wins over menu-item because it is checked first in the chain walk' );
check( 'html5 <footer>', 'footer' === $F::classify_link_location( array( array( 'tag' => 'footer', 'class' => '', 'id' => '' ) ) ) );
check( 'elementor header template', 'header' === $F::classify_link_location( array( array( 'tag' => 'div', 'class' => 'elementor-location-header', 'id' => '' ) ) ) );
check( 'nav menu', 'nav' === $F::classify_link_location( array( array( 'tag' => 'li', 'class' => 'menu-item menu-item-type-post_type', 'id' => '' ), array( 'tag' => 'ul', 'class' => 'elementor-nav-menu', 'id' => '' ) ) ) );
check( 'breadcrumb', 'breadcrumb' === $F::classify_link_location( array( array( 'tag' => 'span', 'class' => '', 'id' => '' ), array( 'tag' => 'nav', 'class' => 'rank-math-breadcrumb', 'id' => '' ) ) ),
	'breadcrumb is checked before the generic nav rule' );
check( 'a linked container card is content', 'content' === $F::classify_link_location( array( array( 'tag' => 'div', 'class' => 'e-con-inner', 'id' => '' ), array( 'tag' => 'div', 'class' => 'elementor-element e-con e-parent', 'id' => '' ) ) ) );

/* ----------------------------------------------------------- staleness --- */
echo "\n--- staleness ---\n";
$now = strtotime( '2026-08-25 12:00:00 UTC' );
check( 'missing record is stale',                  $F::is_stale( '', '2026-08-01 00:00:00', '', $now ) );
check( 'fresh record is fresh',                  ! $F::is_stale( '2026-08-25 10:00:00', '2026-08-20 00:00:00', '2026-08-19 00:00:00', $now ) );
check( 'post edited after capture is stale',       $F::is_stale( '2026-08-25 10:00:00', '2026-08-25 11:00:00', '', $now ) );
check( 'plugin apply after capture is stale',      $F::is_stale( '2026-08-25 10:00:00', '2026-08-20 00:00:00', '2026-08-25 11:30:00', $now ) );
check( 'older than MAX_AGE is stale',              $F::is_stale( '2026-08-10 10:00:00', '2026-08-01 00:00:00', '', $now ) );
check( 'apply BEFORE capture does not stale it', ! $F::is_stale( '2026-08-25 10:00:00', '2026-08-20 00:00:00', '2026-08-25 09:59:00', $now ) );

/* -------------------------------------------------------- expectations --- */
echo "\n--- after-apply verification ---\n";
$facts_noindex = array(
	'meta'     => array( 'title' => 'Melasma Treatment | Irving', 'description' => 'What works and what makes it worse.', 'robots' => 'noindex, follow, max-snippet:-1', 'canonical' => '' ),
	'headings' => array( 'outline' => array( array( 'level' => 'h1', 'text' => 'Melasma Treatment Options' ), array( 'level' => 'h2', 'text' => 'What Makes Melasma Worse?' ) ) ),
	'links'    => array( 'items' => array( array( 'path' => '/dermal-fillers-irving-tx/', 'anchor' => 'Dermal Fillers', 'internal' => true, 'location' => 'content' ) ) ),
	'schema'   => array( 'blocks' => array( array( 'types' => array( 'Article', 'FAQPage' ) ) ) ),
);
$facts_index = $facts_noindex; $facts_index['meta']['robots'] = 'follow, index, max-snippet:-1';

$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'robots_contains', 'value' => 'noindex' ) ) );
check( 'noindex verified when the DOM shows it', true === $r['pass'], json_encode( $r['checks'] ) );
$r = $F::check_expectations( $facts_index, array( array( 'check' => 'robots_contains', 'value' => 'noindex' ) ) );
check( 'noindex FAILS when the DOM still says index (the silent-write bug)', false === $r['pass'], json_encode( $r['checks'] ) );
check( 'failure evidence shows the actual robots value', false !== strpos( $r['checks'][0]['evidence'], 'follow, index' ) );
$r = $F::check_expectations( $facts_index, array( array( 'check' => 'robots_not_contains', 'value' => 'noindex' ) ) );
check( 'clearing noindex verifies against an index page', true === $r['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'link_present', 'href' => 'https://x.test/dermal-fillers-irving-tx/' ) ) );
check( 'link_present resolves a relative link against the same origin', true === $r['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'link_present', 'href' => '/botox-irving-tx/' ) ) );
check( 'link_present fails for a link the DOM lacks', false === $r['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'heading_present', 'text' => 'what makes melasma worse?' ) ) );
check( 'heading_present is case-insensitive', true === $r['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'text_present', 'text' => 'Dermal Fillers' ) ) );
check( 'text_present finds anchor text', true === $r['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'meta_contains', 'key' => 'description', 'value' => 'what makes it worse' ) ) );
check( 'meta_contains on description', true === $r['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'schema_type_present', 'value' => 'faqpage' ) ) );
check( 'schema_type_present is case-insensitive', true === $r['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'robots_contains', 'value' => 'noindex' ), array( 'check' => 'link_present', 'href' => '/nope/' ) ) );
check( 'one failing check fails the whole verification', false === $r['pass'] && 2 === count( $r['checks'] ) );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'made_up' ) ) );
check( 'unknown check fails closed, never passes', false === $r['pass'] );


foreach ( array( 'https://different.example/dermal-fillers-irving-tx/', 'http://x.test/dermal-fillers-irving-tx/', '/dermal-fillers-irving-tx/?wrong=1', '/dermal-fillers-irving-tx/#missing', '/dermal-fillers-irving-tx' ) as $href ) {
 $r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'link_present', 'href' => $href ) ) );
 check( 'Different origin, scheme, query, fragment or slash cannot falsely verify: ' . $href, false === $r['pass'] );
}
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'meta_contains', 'key' => 'title', 'value' => '   ' ) ) );
check( 'Empty expected metadata is inconclusive', null === $r['checks'][0]['pass'] );
$r = $F::check_expectations( $facts_noindex, array( array( 'check' => 'link_present', 'href' => 'javascript:alert(1)' ) ) );
check( 'Unresolvable link is inconclusive', null === $r['checks'][0]['pass'] );
check( 'Relative dot segments resolve without losing origin', 'https://x.test/a/b/contact/?x=1#form' === $F::normalise_link( '../contact/?x=1#form', 'https://x.test/a/b/page/' ) );


$with_base = $facts_noindex; $with_base['link_base_url'] = 'https://external.example/base/'; $with_base['links']['items'] = array( array( 'href' => '/contact/', 'anchor' => 'Contact' ) );
$r = $F::check_expectations( $with_base, array( array( 'check' => 'link_present', 'href' => 'https://x.test/contact/' ) ) );
check( 'HTML base URL cannot turn an external destination into internal proof', false === $r['pass'] );
check( 'Already decoded URL preserves literal entity-like query text', 'https://x.test/?q=&amp;x=1' === $F::normalise_link( '/?q=&amp;x=1', 'https://x.test/' ) );
$legacy_link = $facts_noindex; $legacy_link['links']['items'] = array( array( 'path' => '/contact/', 'internal' => false, 'anchor' => 'Contact' ) );
$r = $F::check_expectations( $legacy_link, array( array( 'check' => 'link_absent', 'href' => 'https://different.example/contact/' ) ) );
check( 'Legacy external path without its origin cannot prove link absence', null === $r['checks'][0]['pass'] );

/* ------------------------------------------- expectations from a pending --- */
echo "\n--- deriving expectations from a pending change ---\n";
$pend = (object) array( 'change_type' => 'postmeta_update', 'id' => 1 );
$e = $F::expectations_for_pending( $pend, array( 'key' => 'rank_math_robots', 'value' => array( 'noindex', 'follow' ) ) );
check( 'rank math noindex array -> robots_contains noindex', 1 === count( $e ) && 'robots_contains' === $e[0]['check'] );
$e = $F::expectations_for_pending( $pend, array( 'key' => 'rank_math_robots', 'value' => '' ) );
check( 'rank math clear -> robots_not_contains noindex', 'robots_not_contains' === $e[0]['check'] );
$e = $F::expectations_for_pending( $pend, array( 'key' => '_yoast_wpseo_meta-robots-noindex', 'value' => '1' ) );
check( 'yoast 1 -> noindex expected', 'robots_contains' === $e[0]['check'] );
$e = $F::expectations_for_pending( $pend, array( 'key' => '_yoast_wpseo_meta-robots-noindex', 'value' => '2' ) );
check( 'yoast 2 -> index expected', 'robots_not_contains' === $e[0]['check'] );
$e = $F::expectations_for_pending( $pend, array( 'key' => 'rank_math_description', 'value' => 'Medical weight loss near you in Irving, TX. APRN-led.' ) );
check( 'description write -> meta_contains description', 'meta_contains' === $e[0]['check'] && 'description' === $e[0]['key'] );
$e = $F::expectations_for_pending( $pend, array( 'key' => 'rank_math_title', 'value' => '%title% %sep% %sitename%' ) );
$r = $F::check_expectations( $facts_noindex, $e );
check( 'Unresolved SEO templates never verify the old title', 'unresolved_meta_template' === $e[0]['check'] && null === $r['checks'][0]['pass'] );
$pend2 = (object) array( 'change_type' => 'elementor_widget_update', 'id' => 2 );
$e = $F::expectations_for_pending( $pend2, array( 'settings' => array( 'link' => array( 'url' => 'https://x.test/botox-irving-tx/' ), 'text' => 'Botox in Irving' ) ) );
check( 'widget link + text -> link_present + text_present', 2 === count( $e ) );
$e = $F::expectations_for_pending( $pend2, array( 'settings' => array( 'link' => array( 'url' => '#pricing' ) ) ) );
check( 'fragment link yields no expectation', 0 === count( $e ) );
$pend3 = (object) array( 'change_type' => 'post_content_update', 'id' => 3 );
check( 'body rewrite yields no automatic expectation (too variable to assert)', 0 === count( $F::expectations_for_pending( $pend3, array( 'content' => 'x' ) ) ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
