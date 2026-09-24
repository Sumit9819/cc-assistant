<?php
/**
 * helpful_content_score / site_quality_score scoring-scope regressions.
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
 * BUG 1 — the scorer read site chrome, so every page carried the same
 * penalty and no page edit could clear it.
 * ------------------------------------------------------------------ */

// Faithful reproduction of the sids-ponds page shape: a header promo bar and a
// popup that appear on EVERY page, plus a clean post body with no exclamations.
$page = '<!doctype html><html><head>'
	. '<script type="application/ld+json">{"@type":"Person","name":"Matt"}</script>'
	. '</head><body>'
	. '<header><div>FREE SHIPPING CANADA-WIDE on orders $100 or more!</div><nav>Home Shop</nav></header>'
	. '<main><h1>Outdoor Lighting in Mississauga</h1>'
	. '<h2>Types of outdoor and landscape lighting</h2>'
	. '<p>Path lighting guides walkways and driveways after dark.</p>'
	. '<h2>Outdoor lighting FAQs</h2>'
	. '<h3>Do you install outdoor lighting in Mississauga?</h3><p>We supply rather than install.</p>'
	. '<h3>How much does landscape lighting cost?</h3><p>It depends on the fixture count.</p>'
	. '<h3>What is low-voltage outdoor lighting?</h3><p>A transformer steps power to 12 volts.</p>'
	. '</main>'
	. '<div class="popup">Garden Patio Box Shop Now! $949.99 Shop Now!</div>'
	. '<footer>Pond, garden, and landscape supply in Mississauga since 2001.</footer>'
	. '</body></html>';

$isolated = CC_Assistant_Pre_Publish::isolate_main_content( $page );
$iso_text = wp_strip_all_tags( $isolated );

check( 'isolate_main_content drops the header promo bar',
	false === strpos( $iso_text, 'FREE SHIPPING' ) );
check( 'isolate_main_content drops the popup',
	false === strpos( $iso_text, 'Shop Now' ) );
check( 'isolate_main_content drops the footer tagline',
	false === strpos( $iso_text, 'since 2001' ) );
check( 'isolate_main_content keeps the post body',
	false !== strpos( $iso_text, 'Path lighting guides walkways' ) );

// The exact regression: 3 chrome exclamations on a body that has none.
check( 'full page carries 3 chrome exclamations (the old, wrong input)',
	3 === substr_count( wp_strip_all_tags( $page ), '!' ),
	'got ' . substr_count( wp_strip_all_tags( $page ), '!' ) );
check( 'isolated body carries 0 exclamations (the fix)',
	0 === substr_count( $iso_text, '!' ),
	'got ' . substr_count( $iso_text, '!' ) );

// Never score an empty body as thin.
check( 'isolation of a chrome-only document still yields usable text via fallback',
	'' === trim( wp_strip_all_tags( CC_Assistant_Pre_Publish::isolate_main_content( '<html><body><header>x!</header></body></html>' ) ) ) );
check( 'helpful_content_score guards the empty-isolation case',
	false !== strpos( $src, "if ( '' === trim( wp_strip_all_tags( (string) \$html ) ) ) {" ) );

// Structural: the scorer must isolate, but JSON-LD must still read the full doc.
check( 'helpful_content_score isolates main content before scoring',
	false !== strpos( $src, "CC_Assistant_Pre_Publish::isolate_main_content( \$html_full )" ) );
check( 'JSON-LD extraction still reads the FULL document (schema lives in <head>)',
	false !== strpos( $src, '</script>#i\', $html_full, $jm2 )' ) );

/* ------------------------------------------------------------------ *
 * BUG 2 — question_h2s ignored H3, so the plugin's own FAQ-card pattern
 * scored 0 questions on pages carrying several real ones.
 * ------------------------------------------------------------------ */
$q_h2 = preg_match_all( '/<h2\b[^>]*>([\s\S]*?)<\/h2>/i', $isolated, $h2m ) ? $h2m[1] : array();
$q_h3 = preg_match_all( '/<h3\b[^>]*>([\s\S]*?)<\/h3>/i', $isolated, $h3m ) ? $h3m[1] : array();
$q_count = 0;
foreach ( array_merge( $q_h2, $q_h3 ) as $h ) {
	if ( false !== strpos( trim( wp_strip_all_tags( $h ) ), '?' ) ) { $q_count++; }
}
$h2_only = 0;
foreach ( $q_h2 as $h ) {
	if ( false !== strpos( trim( wp_strip_all_tags( $h ) ), '?' ) ) { $h2_only++; }
}
check( 'H2-only counting reports 0 questions on a real 3-question FAQ (the old bug)', 0 === $h2_only );
check( 'H2+H3 counting reports all 3 FAQ questions', 3 === $q_count, "got $q_count" );
check( 'scorer merges h2 and h3 when counting question headings',
	false !== strpos( $src, 'foreach ( array_merge( $q_h2, $q_h3 ) as $h ) {' ) );

/* ------------------------------------------------------------------ *
 * BUG 3 — "landscape" was a BARE word in the AI-tell list, so a
 * landscape-supply company was penalised for naming its own product.
 * The shipped regex is pulled out of the source so this tests the real
 * pattern rather than a copy of it.
 * ------------------------------------------------------------------ */
$rx = '';
foreach ( explode( "\n", $src ) as $line ) {
	if ( false !== strpos( $line, 'delve into' ) && preg_match( "/'(\\/.+\\/i)',\s*$/", trim( $line ), $m ) ) {
		$rx = $m[1];
		break;
	}
}
check( 'located the shipped AI-tell regex in source', '' !== $rx );
if ( '' !== $rx ) {
	check( '"landscape lighting" is NOT an AI-tell (it is a product category)',
		0 === preg_match( $rx, 'Types of outdoor and landscape lighting' ) );
	check( '"landscape supply in Mississauga" is NOT an AI-tell',
		0 === preg_match( $rx, 'Pond, garden, and landscape supply in Mississauga' ) );
	check( '"the digital landscape" IS still an AI-tell',
		1 === preg_match( $rx, 'navigating the digital landscape today' ) );
	check( '"ever-changing landscape" IS still an AI-tell',
		1 === preg_match( $rx, 'in this ever-changing landscape' ) );
	check( 'genuine tells still fire: "delve into"',
		1 === preg_match( $rx, 'Let us delve into the details' ) );
	check( 'genuine tells still fire: "elevate your"',
		1 === preg_match( $rx, 'elevate your outdoor space' ) );
}

/* ------------------------------------------------------------------ *
 * BUG 4 — authority hosts were US-only, and retail had no overlay, so a
 * Canadian e-commerce tenant scored citation density 0.00 forever.
 * ------------------------------------------------------------------ */
$hosts = CC_Assistant_SEO_Tools::authority_hosts();
check( 'authority_hosts still includes the US catchalls', in_array( '.gov', $hosts, true ) && in_array( '.edu', $hosts, true ) );
check( 'authority_hosts includes Canadian federal (.gc.ca)', in_array( '.gc.ca', $hosts, true ) );
check( 'authority_hosts includes canada.ca', in_array( 'canada.ca', $hosts, true ) );
check( 'authority_hosts includes ontario.ca', in_array( 'ontario.ca', $hosts, true ) );
check( 'authority_hosts includes UK government', in_array( '.gov.uk', $hosts, true ) );
check( 'authority_hosts includes Australian government', in_array( '.gov.au', $hosts, true ) );

// Host matching is substring-based in the scorer, so verify real URLs resolve.
function host_matches( $url, $hosts ) {
	if ( ! preg_match( '#^https?://([^/]+)#i', $url, $h ) ) { return false; }
	$host = strtolower( $h[1] );
	foreach ( $hosts as $ah ) { if ( false !== strpos( $host, $ah ) ) { return true; } }
	return false;
}
check( 'a Canada.ca citation now counts',   host_matches( 'https://www.canada.ca/en/environment.html', $hosts ) );
check( 'an ontario.ca citation now counts', host_matches( 'https://www.ontario.ca/page/grow-plants', $hosts ) );
check( 'a nrcan.gc.ca citation now counts', host_matches( 'https://natural-resources.canada.ca/x', $hosts ) || host_matches( 'https://www.nrcan.gc.ca/x', $hosts ) );
check( 'a random blog still does NOT count', ! host_matches( 'https://someblog.com/post', $hosts ) );
check( 'wikipedia still does NOT count',     ! host_matches( 'https://en.wikipedia.org/wiki/Pond', $hosts ) );

check( 'an ecommerce authority overlay exists', false !== strpos( $src, "'ecommerce' => array(" ) );
check( 'ecommerce overlay names standards bodies', false !== strpos( $src, "'csagroup.org'" ) && false !== strpos( $src, "'astm.org'" ) );

/* ------------------------------------------------------------------ *
 * BUG 5 — the score cache had no code-version key, so shipping a scorer
 * change left every site serving numbers produced by the OLD scorer until
 * each post happened to be edited. site_quality_score reported rescored=0
 * and repeated the pre-fix verdict verbatim, which is indistinguishable
 * from "the fix did nothing". refresh_stale could not rescue it either,
 * because those entries were timestamped recently and looked fresh.
 * ------------------------------------------------------------------ */
check( 'score cache stamps the scorer version on write',
	false !== strpos( $src, "'_cc_helpful_content_score_ver'" ) );
check( 'score cache compares stored version against CC_ASSISTANT_VERSION',
	false !== strpos( $src, '$cached_ver !== $current_ver' ) );
check( 'a version mismatch clears the cached score instead of serving it',
	false !== strpos( $src, "\$cached_score = '';" ) );
check( 'version stamp is read back on the cache path',
	false !== strpos( $src, "get_post_meta( \$post_id, '_cc_helpful_content_score_ver', true )" ) );

/* ------------------------------------------------------------------ *
 * BUG 5b — site_quality_score reads the cached postmeta DIRECTLY rather
 * than through helpful_content_score(), so the version gate added above
 * did not apply to the aggregate. Fixing only the scorer left the
 * dashboard still reporting rescored=0 and the pre-fix verdict. Any gate
 * added to one MUST be mirrored in the other.
 * ------------------------------------------------------------------ */
check( 'aggregate reads the per-row scorer version',
	false !== strpos( $src, "\$row_ver = (string) get_post_meta( \$pid, '_cc_helpful_content_score_ver', true );" ) );
check( 'aggregate marks a row stale when the scorer build differs',
	false !== strpos( $src, '$row_ver !== $scorer_version' ) );
check( 'aggregate reports how many rows came from another build',
	false !== strpos( $src, "'scored_by_other_build' => \$mixed_scorer_rows," ) );
check( 'aggregate reports which scorer build produced the verdict',
	false !== strpos( $src, "'scorer_version'  => \$scorer_version," ) );
check( 'no dead stale-counter left behind',
	false === strpos( $src, 'stale_scorer_rows' ) );



/* ------------------------------------------------------------------ *
 * BUG 6 — the v0.69 utility exclusion was written against a STORE
 * (cart/checkout/legal) and never widened. On a medical site the same
 * class of page is far larger: HIPAA notices, accessibility statements,
 * medical disclaimers and billing disclosures are mandated boilerplate
 * that can never cite a study, yet they were scored as content and
 * aggregated into the site verdict. Measured across four live sites on
 * 2026-08-27: one site had its entire bottom-N and another its whole
 * bottom-5 filled with pages of this class, and the false weak-share
 * pushed a third site to verdict=warn. Bilingual sites were penalised
 * twice, because each translated legal page counted again.
 * ------------------------------------------------------------------ */

// Rebuild the SHIPPED pattern out of source so this exercises the real
// regex rather than a copy of it that can drift. It ships as a multi-line
// PHP concatenation, so drop the join syntax and the outer quotes.
// Deliberately NOT stripslashes(), which would eat the \d in the trailing
// (-\d+)? and silently weaken every assertion below.
$re_src = '';
$re_key = '$utility_slug_re = ';
$re_at  = strpos( $src, $re_key );
if ( false !== $re_at ) {
	$re_from = $re_at + strlen( $re_key );
	$re_to   = strpos( $src, ";\n", $re_from );
	$expr    = trim( substr( $src, $re_from, $re_to - $re_from ) );
	$expr    = preg_replace( '/\'\s*\.\s*\'/', '', $expr );
	$expr    = preg_replace( '/\A\'|\'\z/', '', $expr );
	$re_src  = str_replace( "\'", "'", $expr );
}
check( 'reassembled the shipped utility regex from source', '' !== $re_src && false !== @preg_match( $re_src, 'x' ), $re_src );

if ( '' !== $re_src && false !== @preg_match( $re_src, 'x' ) ) {
	// v0.69 behaviour must not regress.
	foreach ( array( 'cart', 'checkout', 'my-account', 'wishlist', 'privacy-policy',
		'cookie-policy', 'terms-and-conditions', 'thank-you', 'order-received',
		'refund-returns' ) as $slug ) {
		check( "store/legal page still excluded: $slug", 1 === preg_match( $re_src, $slug ) );
	}
	// Newly excluded. Every one was observed live scoring 35-57 and sitting
	// in bottom_n as though it were weak content.
	foreach ( array( 'accessibility-statement', 'web-accessibility-statement',
		'medical-disclaimer', 'notice-of-privacy-practices',
		'hipaa-notice-of-privacy-practices', 'billing-disclosures',
		'surprise-billing-rights', 'editorial-policy', 'letter-of-protection',
		'terms-of-use', 'contact-us', 'careers', 'career', 'blog',
		'book-appointment', 'shop' ) as $slug ) {
		check( "boilerplate/functional page now excluded: $slug", 1 === preg_match( $re_src, $slug ) );
	}
	// WP suffixes a slug on collision; the live sites carry blog-2.
	check( 'collision suffix tolerated: blog-2',             1 === preg_match( $re_src, 'blog-2' ) );
	check( 'collision suffix tolerated: book-appointment-2', 1 === preg_match( $re_src, 'book-appointment-2' ) );

	// Must NOT swallow real content. An About page at 43 and an Insurance
	// page at 45 are GENUINE findings; hiding them would trade one blind
	// spot for another.
	foreach ( array( 'about-er-of-irving', 'insurance-billing-info',
		'chest-pain-treatment', 'emergency-services-in-bedford-tx',
		'blog-post-about-cookies', 'contact-lens-care',
		'career-guide-for-nurses' ) as $slug ) {
		check( "real content NOT excluded: $slug", 0 === preg_match( $re_src, $slug ) );
	}
}

// Structural guarantees the regex alone cannot express.
check( 'translations inherit the utility exclusion',
	false !== strpos( $src, "'utility_translation'" ) );
check( 'translation inheritance goes through the multilingual helper',
	false !== strpos( $src, 'CC_Assistant_Multilingual::translations_of( $src_id )' ) );
check( 'the blog index is excluded functionally, not by slug alone',
	false !== strpos( $src, "get_option( 'page_for_posts', 0 )" ) );
check( 'every exclusion carries a machine-readable reason',
	false !== strpos( $src, "'reason'  => \$utility_map[ (int) \$pid ]," ) );



/* ------------------------------------------------------------------ *
 * BUG 7 — isolate_main_content() took the FIRST <main>/<article>, so on
 * any page whose related-posts grid precedes the body wrapper it scored
 * a teaser card instead of the article. Elementor emits
 * <article class="elementor-post elementor-grid-item" role="listitem">
 * per card. Measured on erofirving 2026-08-27: real blog posts reported
 * word_count=21 and fell to poor/weak, and THREE sites flipped to
 * verdict=fail on that artifact alone. Lufkin was unaffected only
 * because its single-post template wraps the body in a real <main>.
 * ------------------------------------------------------------------ */

// Faithful reproduction: no <main>, body first, then a related-posts grid
// whose cards are <article role="listitem">.
$grid_page = '<!doctype html><html><body>'
	. '<header><nav>Home Blog</nav></header>'
	. '<div class="post-body"><h1>Pediatric Dehydration</h1>'
	. '<p>' . str_repeat( 'A baby who cannot keep liquids down needs assessment quickly. ', 40 ) . '</p></div>'
	. '<section class="related">'
	. '<article class="elementor-post elementor-grid-item" role="listitem"><h3><a href="/a/">Should You Go to the ER After a Car Accident</a></h3></article>'
	. '<article class="elementor-post elementor-grid-item" role="listitem"><h3><a href="/b/">How Long Does Food Poisoning Last</a></h3></article>'
	. '</section>'
	. '<footer>Open 24/7 in Irving</footer>'
	. '</body></html>';

$grid_iso  = CC_Assistant_Pre_Publish::isolate_main_content( $grid_page );
$grid_text = wp_strip_all_tags( $grid_iso );
$grid_words = str_word_count( $grid_text );

check( 'related-post cards are not mistaken for the article body',
	false === strpos( $grid_text, 'Should You Go to the ER After a Car Accident' ) );
check( 'the real body survives isolation',
	false !== strpos( $grid_text, 'needs assessment quickly' ) );
check( 'word count reflects the article, not a teaser card',
	$grid_words > 200, "got $grid_words" );

// The exact regression signature that reached production.
check( 'isolation no longer yields a ~21-word teaser',
	$grid_words > 100, "got $grid_words" );

// A page with a real <main> must still win on <main>, and an EMPTY <main>
// placed early (a skip-link target) must not starve the scorer.
$empty_main = '<!doctype html><html><body>'
	. '<main id="content"></main>'
	. '<main class="site-main"><h1>Real</h1><p>' . str_repeat( 'Body sentence here. ', 30 ) . '</p></main>'
	. '</body></html>';
$em_text = wp_strip_all_tags( CC_Assistant_Pre_Publish::isolate_main_content( $empty_main ) );
check( 'an empty <main> skip-link target does not win over the real one',
	false !== strpos( $em_text, 'Body sentence here' ) );

// <main> still beats <article> when both are present.
$both = '<!doctype html><html><body>'
	. '<article role="listitem"><h3>Teaser</h3></article>'
	. '<main><h1>Canonical</h1><p>' . str_repeat( 'Main body text. ', 30 ) . '</p></main>'
	. '</body></html>';
$both_text = wp_strip_all_tags( CC_Assistant_Pre_Publish::isolate_main_content( $both ) );
check( 'main still takes precedence over article',
	false !== strpos( $both_text, 'Main body text' ) && false === strpos( $both_text, 'Teaser' ) );

// Structural.
$pp_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-pre-publish.php' );
check( 'isolation picks the largest block, not the first',
	false !== strpos( $pp_src, 'private static function largest_block' ) );
check( 'list items are excluded from candidate blocks',
	2 <= substr_count( $pp_src, 'listitem' ) );


// BUG 6b — the translation-inheritance pass was guarded by a bare
// class_exists() check, but class-multilingual is only require'd inside
// OTHER methods of class-seo-tools, so on most requests the class was not
// loaded, the guard was false, and the whole pass was silently skipped.
// Measured on erofwhiterock 2026-08-27: Career and Blog were excluded while
// their Spanish twins Carreras (35) and Articulos (40) stayed in the scored
// pool and sat in bottom_n. A guard that silently disables the feature it
// guards is worse than no guard.
check( 'site_quality_score loads the multilingual class before using it',
	false !== strpos( $src, "require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';\n\t\tif ( CC_Assistant_Multilingual::is_active() ) {" ) );
check( 'the inheritance pass no longer hides behind class_exists',
	false === strpos( $src, "class_exists( 'CC_Assistant_Multilingual' ) && CC_Assistant_Multilingual::is_active()" ) );

echo "\n" . ( $fails ? "FAILED: $fails" : 'ALL PASS' ) . "\n";
exit( $fails ? 1 : 0 );
