<?php
/**
 * E-E-A-T byline + organisational-reviewer regressions (v0.76.12).
 *
 * All four bugs here were found together on sids-ponds (2026-08-20), where the
 * scorer returned verdict=fail on a site whose pages were fine. They share one
 * root cause pattern: the scorer judged text it should never have been looking
 * at, or refused to count text it should have.
 *
 * Pure-path — isolate_main_content() and authority_hosts() need no WordPress.
 * Run: php tests/scorer-scope-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) )     { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); } }
if ( ! function_exists( 'apply_filters' ) )   { function apply_filters( $h, $v ) { return $v; } }
if ( ! function_exists( 'esc_html' ) )        { function esc_html( $s ) { return $s; } }
if ( ! function_exists( '__' ) )              { function __( $s, $d = null ) { return $s; } }

// Pin the tenant to the retail vertical so the ecommerce overlay is exercised.
// Without this, authority_hosts() falls through to industry detection, which
// queries the database.
// The profile is stored as an ARRAY ( industry, source, confidence ), not a
// bare slug — returning a string here silently falls through to live detection.
$GLOBALS['cc_test_industry'] = 'ecommerce';
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $d = false ) {
		if ( false !== strpos( (string) $k, 'industry' ) ) {
			return array( 'industry' => $GLOBALS['cc_test_industry'], 'source' => 'manual', 'confidence' => 100 );
		}
		return $d;
	}
}
if ( ! function_exists( 'update_option' ) ) { function update_option( $k, $v, $a = null ) { return true; } }
if ( ! function_exists( 'home_url' ) )      { function home_url( $p = '' ) { return 'https://example.test' . $p; } }
if ( ! function_exists( 'get_bloginfo' ) )  { function get_bloginfo( $f = '' ) { return 'Test Site'; } }
// class-industry-profile falls back to a $wpdb scan when no option is set; the
// scorer must never depend on that in tests.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	class CC_Test_WPDB {
		public $prefix = 'wp_'; public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta';
		public function get_col( $q = '', $x = 0 ) { return array(); }
		public function get_var( $q = '' ) { return null; }
		public function get_results( $q = '', $o = null ) { return array(); }
		public function prepare( $q ) { return $q; }
		public function esc_like( $s ) { return $s; }
	}
	$GLOBALS['wpdb'] = new CC_Test_WPDB();
}

require_once dirname( __DIR__ ) . '/includes/class-pre-publish.php';
require_once dirname( __DIR__ ) . '/includes/class-seo-tools.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; }
	else { $fails++; echo "FAIL  $label" . ( $extra ? " ($extra)" : '' ) . "\n"; }
}

$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-seo-tools.php' );

/* ------------------------------------------------------------------ *
 * BUG A - a colon after "Reviewed by" made a legitimate team byline
 * score as no byline at all. eroflufkin's own editorial-policy page
 * uses the colon form, so copying the site's sanctioned wording onto a
 * service page would have earned zero E-E-A-T.
 * ------------------------------------------------------------------ */
check( 'A1 shipped regex tolerates a colon after the by-phrase',
	false !== strpos( $src, "escrito\s+por)[\s:]+(?:the\s+|el\s+|la\s+)?" ) );

$team_re = '/\b(?:medically\s+reviewed\s+by|reviewed\s+by|written\s+by|revisado\s+(?:m.?dicamente\s+)?por|escrito\s+por)[\s:]+(?:the\s+|el\s+|la\s+)?[^.<>{}\r\n]{0,80}?\b(?:team|equipo|staff)\b/iu';
check( 'A2 colon form scores',        1 === preg_match( $team_re, 'Reviewed by: the ER of Lufkin clinical team' ) );
check( 'A3 no-colon form still scores', 1 === preg_match( $team_re, 'Medically reviewed by the ER of Lufkin clinical team | Updated: August 2026' ) );
check( 'A4 Spanish equipo still scores', 1 === preg_match( $team_re, 'Revisado por el equipo clinico de ER of White Rock' ) );
check( 'A5 unrelated prose does not score', 0 === preg_match( $team_re, 'Our team treats patients around the clock.' ) );

/* ------------------------------------------------------------------ *
 * BUG B - reviewedBy pointing at an Organization earned nothing, so a
 * site that cannot name a clinician (consent withheld) measured as
 * having no accountability signal at all.
 * ------------------------------------------------------------------ */
$m = new ReflectionMethod( 'CC_Assistant_SEO_Tools', 'eeat_scan_jsonld_node' );
$m->setAccessible( true );
function scan( $node ) {
	$f = array( 'has_person' => false, 'has_sameas' => false, 'has_linkedin_sameas' => false, 'has_credential' => false, 'person_names' => array(), 'has_org_reviewer' => false, 'org_reviewer_names' => array() );
	$m = new ReflectionMethod( 'CC_Assistant_SEO_Tools', 'eeat_scan_jsonld_node' );
	$m->setAccessible( true );
	$m->invokeArgs( null, array( $node, &$f ) );
	return $f;
}

$org = scan( array( '@type' => 'MedicalWebPage', 'reviewedBy' => array( '@type' => 'Organization', 'name' => 'ER of Irving Medical Team' ) ) );
check( 'B1 reviewedBy Organization is credited', true === $org['has_org_reviewer'], json_encode( $org ) );
check( 'B2 reviewer name captured', in_array( 'ER of Irving Medical Team', $org['org_reviewer_names'], true ) );
check( 'B3 org reviewer is NOT counted as a Person', false === $org['has_person'] );

$med = scan( array( '@type' => 'MedicalWebPage', 'reviewedBy' => array( '@type' => 'MedicalOrganization', 'name' => 'X' ) ) );
check( 'B4 MedicalOrganization reviewer credited', true === $med['has_org_reviewer'] );

$hosp = scan( array( '@type' => 'WebPage', 'reviewer' => array( '@type' => 'Hospital', 'name' => 'Y' ) ) );
check( 'B5 reviewer key + Hospital credited', true === $hosp['has_org_reviewer'] );

// The critical false-positive guard: publisher/Organization is on essentially
// every page Rank Math emits. It must NEVER count as a deliberate reviewer,
// or every page on every site silently gains 5 points it did not earn.
$pub = scan( array( '@type' => 'WebPage', 'publisher' => array( '@type' => 'Organization', 'name' => 'ER of Irving' ) ) );
check( 'B6 publisher Organization does NOT count', false === $pub['has_org_reviewer'], json_encode( $pub ) );

$bare = scan( array( '@type' => 'Organization', 'name' => 'ER of Irving' ) );
check( 'B7 a bare Organization node does NOT count', false === $bare['has_org_reviewer'] );

$person = scan( array( '@type' => 'Person', 'name' => 'Jane Roe', 'hasCredential' => 'MD', 'sameAs' => array( 'https://www.linkedin.com/in/x' ) ) );
check( 'B8 Person detection unchanged',   true === $person['has_person'] );
check( 'B9 credential detection unchanged', true === $person['has_credential'] );
check( 'B10 linkedin detection unchanged',  true === $person['has_linkedin_sameas'] );

// Nested inside @graph, which is how Rank Math actually ships it.
$graph = scan( array( '@graph' => array( array( '@type' => 'WebPage', 'reviewedBy' => array( '@type' => 'Organization', 'name' => 'Z' ) ) ) ) );
check( 'B11 reviewedBy found inside @graph', true === $graph['has_org_reviewer'] );

/* Scoring wiring: org reviewer earns the base 5 but never the bonuses. */
check( 'C1 helpful_content_score credits the org reviewer',
	false !== strpos( $src, "} elseif ( ! empty( \$schema['has_org_reviewer'] ) ) {" ) );
check( 'C2 org-only pages get the explanatory issue, not the hard fail',
	false !== strpos( $src, "'code' => 'eeat_org_reviewer_only'" ) );
check( 'C3 sameAs / credential bonuses remain Person-only',
	false !== strpos( $src, "if ( \$schema['has_sameas'] ) { \$eeat += 3; }" ) );

echo "\n" . ( $fails ? "FAILED: $fails" : 'ALL PASS' ) . "\n";
exit( $fails ? 1 : 0 );
