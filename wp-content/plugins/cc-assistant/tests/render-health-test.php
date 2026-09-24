<?php
/**
 * Render Health Guard: pure-path tests for scan/compare/count_elements plus
 * the lint failure classifier. Run: php tests/render-health-test.php
 *
 * The fixtures model the real 2026-08-03 incident: a Divi Plus product
 * carousel whose query broke — page kept its CSS (selectors still present),
 * lost its rendered product elements, and printed the builder's empty-state
 * prose. The guard must catch that page, and must NOT be fooled by CSS
 * selectors that contain the same tokens as rendered elements.
 *
 * capture()/fetch() are WordPress-coupled (transients, wp_remote_get) and are
 * not exercised here.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-render-health.php';

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

/* ------------------------------------------------------------- fixtures */

$css_block = '<style>.dipl_single_woo_product{border:1px solid} '
	. '.dipl_woo_products_carousel .dipl_single_woo_product_title{min-height:54px} '
	. '.swiper-slide{width:100%}</style>';

$product_card = '<div class="dipl_woo_products_carousel_slide swiper-slide">'
	. '<div class="dipl_single_woo_product"><h4 class="dipl_single_woo_product_title">'
	. '<a href="/product/x">Bartell Roller</a></h4><span class="price">CAD $4,470.00</span></div></div>';

$healthy = '<html><head>' . $css_block . '</head><body>'
	. '<div class="et_pb_module dipl_woo_products_carousel">'
	. str_repeat( $product_card, 5 )
	. '</div>'
	. '<p>' . str_repeat( 'Normal storefront copy here. ', 300 ) . '</p>'
	. '</body></html>';

// Same page, dead query: CSS untouched, zero product markup, empty-state prose.
$broken = '<html><head>' . $css_block . '</head><body>'
	. '<div class="et_pb_module dipl_woo_products_carousel"><div class="et_pb_module_inner">'
	. '<div class="entry">The products you requested could not be found. '
	. 'Try changing your module settings or create some new products.</div>'
	. '</div></div>'
	. '<p>' . str_repeat( 'Normal storefront copy here. ', 300 ) . '</p>'
	. '</body></html>';

/* ----------------------------------------- count_elements: the CSS trap */

check(
	'count_elements: 5 rendered product cards counted as 5',
	5 === CC_Assistant_Render_Health::count_elements( $healthy, 'dipl_single_woo_product' )
);
check(
	'count_elements: CSS selectors alone count as ZERO',
	0 === CC_Assistant_Render_Health::count_elements( $broken, 'dipl_single_woo_product' ),
	'substring counting reported 24 products on a page rendering none — the exact bug this exists to prevent'
);
check(
	'count_elements: token does not match its own longer compounds',
	0 === CC_Assistant_Render_Health::count_elements(
		'<h4 class="dipl_single_woo_product_title">t</h4>',
		'dipl_single_woo_product'
	),
	'a product TITLE element must not count as a product CARD'
);
check(
	'count_elements: single-quoted class attributes count too',
	1 === CC_Assistant_Render_Health::count_elements( "<div class='a dipl_single_woo_product b'>x</div>", 'dipl_single_woo_product' )
);

/* -------------------------------------------------------------- scan() */

$hf = CC_Assistant_Render_Health::scan( $healthy, 200 );
$bf = CC_Assistant_Render_Health::scan( $broken, 200 );

check( 'scan: healthy page has no empty-state hits', empty( $hf['empty_states'] ) );
check( 'scan: broken page registers the Divi Plus empty-state', ! empty( $bf['empty_states'] ) );
check( 'scan: healthy counts 5 products', 5 === $hf['elements']['dipl_single_woo_product'] );
check( 'scan: broken counts 0 products', 0 === $bf['elements']['dipl_single_woo_product'] );
check( 'scan: no false php errors in prose', 0 === $hf['php_errors'] );
check( 'scan: no shortcode leak on rendered pages', 0 === $hf['shortcode_leaks'] );

/* ---------------------------------------------- compare(): the incident */

$findings = CC_Assistant_Render_Health::compare( $hf, $bf, true );
$codes    = array_column( $findings, 'code' );

check(
	'THE INCIDENT: broken carousel is caught',
	in_array( 'empty_state_appeared', $codes, true ),
	'this comparison is the entire reason the guard exists'
);
check( 'THE INCIDENT: vanished product population is caught', in_array( 'elements_vanished', $codes, true ) );
check( 'THE INCIDENT: findings are critical', CC_Assistant_Render_Health::has_critical( $findings ) );

check( 'healthy vs healthy: no findings', array() === CC_Assistant_Render_Health::compare( $hf, $hf, false ) );

/* ------------------------------------------------- compare(): stale fetch */

$stale = CC_Assistant_Render_Health::compare( $hf, $hf, true );
check(
	'byte-identical body after a mutation reports possibly_stale_fetch, not a clean pass',
	1 === count( $stale ) && 'possibly_stale_fetch' === $stale[0]['code'],
	'a cached pre-change page must never be reported as "verified healthy"'
);

/* ------------------------------------------------ compare(): delta rules */

// An empty-state string that ALREADY existed (e.g. localized text inside an
// inline script bundle) must not fire when unchanged — only an increase does.
$base_with_static = $hf;
$base_with_static['empty_states'] = array( 'No products were found' => 2 );
$base_with_static['body_sha1']    = 'aaa';
$now_same  = $base_with_static;
$now_same['body_sha1'] = 'bbb';
check(
	'pre-existing empty-state at the same count does NOT fire',
	array() === CC_Assistant_Render_Health::compare( $base_with_static, $now_same, true )
);
$now_more = $now_same;
$now_more['empty_states'] = array( 'No products were found' => 3 );
$codes2 = array_column( CC_Assistant_Render_Health::compare( $base_with_static, $now_more, true ), 'code' );
check( 'an INCREASED empty-state count fires', in_array( 'empty_state_appeared', $codes2, true ) );

// Small element populations may legitimately disappear; only >=3 vanishing fires.
$small_b = $hf; $small_b['elements'] = array( 'type-product' => 2 ); $small_b['body_sha1'] = 'a1';
$small_n = $hf; $small_n['elements'] = array( 'type-product' => 0 ); $small_n['body_sha1'] = 'a2';
check(
	'a 2-element population vanishing does not fire (below threshold)',
	! in_array( 'elements_vanished', array_column( CC_Assistant_Render_Health::compare( $small_b, $small_n, true ), 'code' ), true )
);

/* -------------------------------------- compare(): other critical paths */

$b2 = $hf; $b2['body_sha1'] = 's1';
$n2 = $hf; $n2['body_sha1'] = 's2'; $n2['http_code'] = 500;
check( 'HTTP 200 -> 500 fires critical', in_array( 'http_status_changed', array_column( CC_Assistant_Render_Health::compare( $b2, $n2, true ), 'code' ), true ) );

$n3 = $hf; $n3['body_sha1'] = 's3'; $n3['shortcode_leaks'] = 4;
check( 'shortcode leakage fires', in_array( 'shortcode_leakage', array_column( CC_Assistant_Render_Health::compare( $b2, $n3, true ), 'code' ), true ) );

$n4 = $hf; $n4['body_sha1'] = 's4'; $n4['php_errors'] = 1;
check( 'php error appearing fires', in_array( 'php_error_appeared', array_column( CC_Assistant_Render_Health::compare( $b2, $n4, true ), 'code' ), true ) );

$n5 = $hf; $n5['body_sha1'] = 's5'; $n5['text_len'] = (int) floor( $hf['text_len'] * 0.2 );
$shrunk = CC_Assistant_Render_Health::compare( $b2, $n5, true );
check(
	'large text shrink warns (not critical)',
	in_array( 'content_shrunk', array_column( $shrunk, 'code' ), true ) && ! CC_Assistant_Render_Health::has_critical( $shrunk )
);
$n6 = $hf; $n6['body_sha1'] = 's6'; $n6['text_len'] = $hf['text_len'] - 100;
check( 'small text shrink stays silent', array() === CC_Assistant_Render_Health::compare( $b2, $n6, true ) );

/* --------------------------------------- scan(): text_len is VISIBLE text */

// Inline CSS/JS must not count as page text: a stylesheet change would
// otherwise mask or fabricate a content shrink.
$page_small_css = '<html><head><style>.a{color:red}</style></head><body><p>Hello world prose.</p>'
	. '<script>var x = "' . str_repeat( 'jsjunk', 200 ) . '";</script></body></html>';
$page_huge_css  = '<html><head><style>' . str_repeat( '.b{margin:0} ', 2000 ) . '</style></head><body><p>Hello world prose.</p>'
	. '<script>var x = "' . str_repeat( 'jsjunk', 900 ) . '";</script></body></html>';

$f_small = CC_Assistant_Render_Health::scan( $page_small_css, 200 );
$f_huge  = CC_Assistant_Render_Health::scan( $page_huge_css, 200 );
check(
	'text_len ignores script/style content entirely',
	$f_small['text_len'] === $f_huge['text_len'],
	"small={$f_small['text_len']} huge={$f_huge['text_len']} — should be identical, only CSS/JS differ"
);
check( 'text_len reflects the actual prose', $f_small['text_len'] < 40, "got {$f_small['text_len']} for 'Hello world prose.'" );

/* -------------------------------- classify_lint_failures (P1, pure path) */

require dirname( __DIR__ ) . '/includes/class-pre-publish.php';

$proposed_checks = array(
	'sentence_length' => array( 'pass' => false ),
	'html_cruft'      => array( 'pass' => false ),
	'em_dashes'       => array( 'pass' => false ),
	'wall_of_text'    => array( 'pass' => false ),
	'reading_level'   => array( 'pass' => true ),
);
$current_checks = array(
	'sentence_length' => array( 'pass' => false ),
	'html_cruft'      => array( 'pass' => false ),
	'em_dashes'       => array( 'pass' => false ),
	'wall_of_text'    => array( 'pass' => true ),
);

$cl = CC_Assistant_Pre_Publish::classify_lint_failures(
	$proposed_checks,
	$current_checks,
	array( 'em_dashes' ) // additive-scoped: fired on ADDED text
);

check(
	'structural check failing in both bodies = pre_existing',
	in_array( 'sentence_length', $cl['pre_existing'], true ) && in_array( 'html_cruft', $cl['pre_existing'], true ),
	'these were the two false positives that made lint carry zero signal on Divi sites'
);
check(
	'structural check failing only in the proposal = introduced',
	in_array( 'wall_of_text', $cl['introduced'], true )
);
check(
	'additive-scoped check = introduced even though current body also fails it',
	in_array( 'em_dashes', $cl['introduced'], true ) && ! in_array( 'em_dashes', $cl['pre_existing'], true ),
	'it fired on the ADDED text specifically; current-body state is irrelevant'
);
check( 'passing checks are never classified', ! in_array( 'reading_level', array_merge( $cl['introduced'], $cl['pre_existing'] ), true ) );

$cl2 = CC_Assistant_Pre_Publish::classify_lint_failures( $proposed_checks, array(), array() );
check(
	'no current body (new post): every failure is introduced',
	4 === count( $cl2['introduced'] ) && 0 === count( $cl2['pre_existing'] )
);

/* ------------------------- classify: worsening upgrades to introduced */

// A check failing in BOTH bodies but with MORE violations in the proposal
// was made worse by this change — that worsening must not hide behind a
// "pre-existing" label. Ties and metric-free checks stay pre-existing.
$worse_proposed = array(
	'wall_of_text'    => array( 'pass' => false, 'violations' => array( 'w1', 'w2', 'w3' ) ),
	'sentence_length' => array( 'pass' => false, 'samples' => array( 's1', 's2' ) ),
	'html_cruft'      => array( 'pass' => false ), // no countable metric
);
$worse_current = array(
	'wall_of_text'    => array( 'pass' => false, 'violations' => array( 'w1' ) ),
	'sentence_length' => array( 'pass' => false, 'samples' => array( 's1', 's2' ) ),
	'html_cruft'      => array( 'pass' => false ),
);
$cl3 = CC_Assistant_Pre_Publish::classify_lint_failures( $worse_proposed, $worse_current, array() );
check(
	'worsened check (3 violations vs 1) classifies as introduced',
	in_array( 'wall_of_text', $cl3['introduced'], true ),
	'a NEW wall on a post that already had one must not soft-pass as pre-existing'
);
check( 'magnitude tie stays pre-existing', in_array( 'sentence_length', $cl3['pre_existing'], true ) );
check( 'metric-free check failing in both stays pre-existing', in_array( 'html_cruft', $cl3['pre_existing'], true ) );

echo "\n";
if ( $fails ) {
	echo "$fails FAILURE(S)\n";
	exit( 1 );
}
echo "All render-health tests passed.\n";
exit( 0 );
