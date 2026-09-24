<?php
/**
 * site_status classifier test.
 *
 * The DB aggregation is exercised against the real warehouse; what needs
 * pinning here are the pure classifiers, because each one encodes a judgement
 * that is easy to silently break:
 *   - branded detection decides whether "organic reach" is reported honestly
 *   - fan-out vs absorbed decides whether a page gets pruned or protected
 *   - page_key decides whether a tracking variant looks like a separate page
 *
 * Run: php tests/site-status-test.php
 */
error_reporting( E_ALL );

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

require dirname( __DIR__ ) . '/bin/site-status.php';

/* ------------------------------------------------------------- page_key --- */
echo "--- page canonicalisation ---\n";
$home = 'https://x.test/';
check( 'tracking param folds onto the bare page',
	cc_ss_page_key( 'https://x.test/?utm_source=gmb&utm_medium=gmb' ) === cc_ss_page_key( $home ),
	cc_ss_page_key( 'https://x.test/?utm_source=gmb' ) );
check( 'fragment folds onto the bare page',
	cc_ss_page_key( 'https://x.test/guide/#section-3' ) === cc_ss_page_key( 'https://x.test/guide/' ) );
check( 'trailing slash is not a different page',
	cc_ss_page_key( 'https://x.test/guide' ) === cc_ss_page_key( 'https://x.test/guide/' ) );
check( 'genuinely different paths stay separate',
	cc_ss_page_key( 'https://x.test/a/' ) !== cc_ss_page_key( 'https://x.test/b/' ) );
check( 'root never collapses to empty string', '' !== cc_ss_page_key( 'https://x.test/' ) );

/* ------------------------------------------------------------- near me ---- */
echo "\n--- map-pack detection ---\n";
check( 'near me caught', cc_ss_is_near_me( 'iv therapy near me' ) );
check( 'nearby caught', cc_ss_is_near_me( 'wellness clinic nearby' ) );
check( 'plain local query is NOT map-pack', ! cc_ss_is_near_me( 'iv therapy irving tx' ) );
check( 'word boundary respected (nearest is not near me)', ! cc_ss_is_near_me( 'nearest hospital' ) );

/* -------------------------------------------------- fan-out vs absorbed --- */
echo "\n--- fan-out detection (the distinction that protects assets) ---\n";
check( 'authority-named synthetic query is fan-out',
	cc_ss_is_fanout( 'cleveland clinic laser hair removal how many sessions 6 to 8 weeks apart' ) );
check( 'question-shaped synthetic query is fan-out',
	cc_ss_is_fanout( 'how do i choose a clinic that provides prescription weight loss injections?' ) );
check( 'long natural sentence is fan-out',
	cc_ss_is_fanout( 'which iv therapy blends are best for athletes or those with chronic fatigue' ) );
check( 'short human head term is NOT fan-out',
	! cc_ss_is_fanout( 'botox irving tx' ) );
check( 'short human query with a brand word is NOT fan-out',
	! cc_ss_is_fanout( 'semaglutide grapevine' ) );
// Regression: numerics in the query. str_word_count() ignores them, so this
// real eroflufkin query counted as 7 words and was mislabelled "absorbed"
// (stop optimising) when it is actually "cited" (protect it).
check( 'numeric tokens count toward the fan-out length test',
	cc_ss_is_fanout( 'american heart association hypertensive crisis 180 120 symptoms emergency' ),
	'this was classified as absorbed, inverting the advice' );
check( 'named authority alone is enough, even when short',
	cc_ss_is_fanout( 'american heart association hypertensive crisis' ) );

/* ------------------------------------------------------------- branded ---- */
echo "\n--- branded detection ---\n";
$tokens = array( 'tokens' => array( 'irving', 'wellness', 'clinic' ), 'blob' => 'irvingwellnessclinic' );
check( 'full brand name is branded', cc_ss_is_branded( 'irving health and wellness clinic', $tokens ) );
check( 'partial brand name is branded', cc_ss_is_branded( 'irving wellness clinic', $tokens ) );
check( 'generic pair without the lead token is NOT branded',
	! cc_ss_is_branded( 'wellness clinic near me', $tokens ),
	'this is the false positive that would overstate branded share' );
check( 'lead token alone is NOT branded (service + city)',
	! cc_ss_is_branded( 'iv therapy irving', $tokens ) );
check( 'competitor in the same city is NOT branded',
	! cc_ss_is_branded( 'irving urgent care', $tokens ) );
check( 'too few tokens disables token matching',
	! cc_ss_is_branded( 'irving wellness', array( 'tokens' => array( 'irving' ), 'blob' => '' ) ),
	'better to report nothing than to guess' );

// Abbreviation hosts: "erofirving" is ER of Irving. Token matching cannot see
// this (er/of are under the length floor), so the de-spaced host route must.
$abbr = array( 'tokens' => array( 'irving' ), 'blob' => 'erofirving' );
check( 'abbreviation host: spaced brand name is branded',
	cc_ss_is_branded( 'er of irving', $abbr ) );
check( 'abbreviation host: brand plus modifier is branded',
	cc_ss_is_branded( 'er of irving tx reviews', $abbr ) );
check( 'abbreviation host: city alone is NOT branded',
	! cc_ss_is_branded( 'emergency room irving', $abbr ),
	'this is the query the site wants to RANK for, not a brand search' );

/* ------------------------------------------------------------- deltas ----- */
echo "\n--- delta maths ---\n";
check( 'normal growth', 100.0 === cc_ss_pct( 20, 10 ) );
check( 'decline is negative', -50.0 === cc_ss_pct( 5, 10 ) );
check( 'growth from zero is null, not infinity', null === cc_ss_pct( 5, 0 ),
	'reporting "+infinity%" or "+500%" from a zero base is misleading' );
check( 'zero to zero is flat, not null', 0.0 === cc_ss_pct( 0, 0 ) );

/* ------------------------------------------------------- dilution kinds --- */
echo "
--- indexable dilution ---
";
check( 'wp pagination caught', 'pagination' === cc_ss_dilution_kind( 'https://x.test/category/emergency-care/page/2/' ) );
check( 'numeric blog leaf caught', 'pagination' === cc_ss_dilution_kind( 'https://x.test/blog/2/' ) );
check( 'elementor pagination param caught', 'pagination' === cc_ss_dilution_kind( 'https://x.test/blog/?e-page-fcb762c=3' ) );
check( 'classic paged param caught', 'pagination' === cc_ss_dilution_kind( 'https://x.test/blog/?paged=4' ) );
check( 'term archive classed separately from pagination',
	'archive' === cc_ss_dilution_kind( 'https://x.test/category/emergency-care/' ),
	'archives are a judgement call, pagination is not' );
check( 'date archive caught', 'archive' === cc_ss_dilution_kind( 'https://x.test/2026/08/' ) );
check( 'real service page is KEPT', '' === cc_ss_dilution_kind( 'https://x.test/services/chest-pain-treatment/' ) );
check( 'post whose slug ends in a number is KEPT',
	'' === cc_ss_dilution_kind( 'https://x.test/covid-19/' ),
	'a numeric slug is not pagination' );
check( 'utm-tagged real page is KEPT (folded elsewhere, not dilution)',
	'' === cc_ss_dilution_kind( 'https://x.test/?utm_source=gmb' ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
