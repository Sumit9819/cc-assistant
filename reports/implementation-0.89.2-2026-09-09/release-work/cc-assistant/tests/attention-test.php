<?php
/**
 * Attention-audit fixture test (pure paths, no WordPress). A flat generic
 * page must score LOW with the right flags; a composed emergency page must
 * score HIGH with none. Includes regressions for the v0.55 review findings
 * (Post Title h1 default, tel-in-linked-heading, form-as-CTA, em units,
 * Spanish word count, Divi fullwidth_header attrs, proof false-positive).
 * Run: php tests/attention-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );

function wp_strip_all_tags( $s ) {
	return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) );
}
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $str, $start, $length = null ) {
		return null === $length ? substr( $str, $start ) : substr( $str, $start, $length );
	}
}
function apply_filters( $tag, $value ) {
	return $value;
}

require dirname( __DIR__ ) . '/includes/class-rest-divi.php';
require dirname( __DIR__ ) . '/includes/class-attention-audit.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label  $extra\n"; }
}

function widget( $type, $settings = array() ) {
	return array( 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
}
function section( $children, $settings = array() ) {
	return array( 'elType' => 'container', 'settings' => $settings, 'elements' => $children );
}
$lorem = trim( str_repeat( 'these are some plain filler words for the body text ', 10 ) );

// Flat generic page.
$flat_tree = array();
for ( $i = 0; $i < 5; $i++ ) {
	$flat_tree[] = section( array(
		widget( 'heading', array( 'title' => 'Generic Section ' . $i, 'header_size' => 'h2' ) ),
		widget( 'text-editor', array( 'editor' => '<p>' . $lorem . '</p>' ) ),
	) );
}
$bands  = CC_Assistant_Attention_Audit::extract_bands_elementor( $flat_tree );
$report = CC_Assistant_Attention_Audit::score_bands( $bands, 'service_local' );
$codes  = array_column( $report['flags'], 'code' );

check( 'flat: 5 bands extracted', 5 === count( $bands ), count( $bands ) );
check( 'flat: no_h1 flagged', in_array( 'no_h1', $codes, true ), json_encode( $codes ) );
check( 'flat: no_cta flagged', in_array( 'no_cta', $codes, true ) );
check( 'flat: flat_hierarchy flagged', in_array( 'flat_hierarchy', $codes, true ), json_encode( $codes ) );
check( 'flat: monotone_rhythm flagged', in_array( 'monotone_rhythm', $codes, true ) );
check( 'flat: score is low (<=55)', $report['attention_score'] <= 55, (string) $report['attention_score'] );
check( 'flat: attention decays down the page', $report['bands'][0]['est_attention_pct'] > $report['bands'][2]['est_attention_pct'] && $report['bands'][2]['est_attention_pct'] > $report['bands'][4]['est_attention_pct'] );
check( 'flat: top_fixes non-empty, max 3', count( $report['top_fixes'] ) >= 1 && count( $report['top_fixes'] ) <= 3 );

// Composed emergency page.
$good_tree = array(
	section( array(
		widget( 'heading', array( 'title' => 'Emergency Room in Irving, Open 24/7', 'header_size' => 'h1', 'typography_font_size' => array( 'size' => 44 ) ) ),
		widget( 'button', array( 'text' => 'Call Now', 'link' => array( 'url' => 'tel:+19725550100' ) ) ),
		widget( 'image', array() ),
	), array( 'background_background' => 'classic', 'background_color' => '#0a2540' ) ),
	section( array(
		widget( 'heading', array( 'title' => 'Board-Certified Physicians, No Wait', 'header_size' => 'h2' ) ),
		widget( 'icon-list', array() ),
		widget( 'testimonial', array() ),
	) ),
	section( array(
		widget( 'heading', array( 'title' => 'Conditions We Treat', 'header_size' => 'h2' ) ),
		widget( 'text-editor', array( 'editor' => '<p>' . $lorem . '</p>' ) ),
		widget( 'image', array() ),
	), array( 'background_background' => 'classic', 'background_color' => '#f5f8fb' ) ),
	section( array(
		widget( 'heading', array( 'title' => 'Get Seen in Minutes', 'header_size' => 'h2', 'typography_font_size' => array( 'size' => 36 ) ) ),
		widget( 'star-rating', array() ),
		widget( 'button', array( 'text' => 'Call (972) 555-0100', 'link' => array( 'url' => 'tel:+19725550100' ) ) ),
	) ),
);
$bands2  = CC_Assistant_Attention_Audit::extract_bands_elementor( $good_tree );
$report2 = CC_Assistant_Attention_Audit::score_bands( $bands2, 'emergency_transactional' );
$codes2  = array_column( $report2['flags'], 'code' );
$majors2 = array_filter( $report2['flags'], function ( $f ) { return 'major' === $f['severity']; } );

check( 'good: 4 bands', 4 === count( $bands2 ) );
check( 'good: hero label from H1', false !== strpos( $bands2[0]['label'], 'Emergency Room' ), $bands2[0]['label'] );
check( 'good: tel detected in hero', true === $bands2[0]['tel_link'] );
check( 'good: proof detected in bands 1+3', $bands2[1]['proof'] && $bands2[3]['proof'] );
check( 'good: zero MAJOR flags', 0 === count( $majors2 ), json_encode( $codes2 ) );
check( 'good: score high (>=90)', $report2['attention_score'] >= 90, (string) $report2['attention_score'] );
check( 'good: hero dominates attention', $report2['bands'][0]['est_attention_pct'] >= 35, (string) $report2['bands'][0]['est_attention_pct'] );

// Emergency page without tel above fold gets flagged.
$no_tel_tree = $good_tree;
$no_tel_tree[0]['elements'][1]['settings']['link']['url'] = 'https://example.com/contact';
$bands3  = CC_Assistant_Attention_Audit::extract_bands_elementor( $no_tel_tree );
$report3 = CC_Assistant_Attention_Audit::score_bands( $bands3, 'emergency_transactional' );
check( 'no-tel emergency: no_tel_above_fold flagged', in_array( 'no_tel_above_fold', array_column( $report3['flags'], 'code' ), true ) );

// Divi basic extraction.
$divi = '[et_pb_section background_color="#0a2540"][et_pb_row][et_pb_column type="4_4"]'
	. '[et_pb_text]<h1>Pond Design in Dallas</h1><p>' . $lorem . ' call <a href="tel:+12145550111">now</a></p>[/et_pb_text]'
	. '[et_pb_button button_url="tel:+12145550111" button_text="Call"][/et_pb_button]'
	. '[/et_pb_column][/et_pb_row][/et_pb_section]'
	. '[et_pb_section][et_pb_row][et_pb_column type="4_4"]'
	. '[et_pb_text]<h2>Our Work</h2><p>' . $lorem . '</p>[/et_pb_text]'
	. '[et_pb_image src="x.jpg"][/et_pb_image]'
	. '[/et_pb_column][/et_pb_row][/et_pb_section]'
	. '[et_pb_section][et_pb_row][et_pb_column type="4_4"]'
	. '[et_pb_testimonial author="Jill"]Great pond team[/et_pb_testimonial]'
	. '[et_pb_button button_url="/quote" button_text="Get a Quote"][/et_pb_button]'
	. '[/et_pb_column][/et_pb_row][/et_pb_section]';

$dbands = CC_Assistant_Attention_Audit::extract_bands_divi( $divi );
check( 'divi: 3 root sections', 3 === count( $dbands ), count( $dbands ) );
check( 'divi: h1 found in band 0', true === $dbands[0]['h1'] );
check( 'divi: tel found in band 0', true === $dbands[0]['tel_link'] );
check( 'divi: cta counted band 0', 1 === $dbands[0]['cta_count'], (string) $dbands[0]['cta_count'] );
check( 'divi: image counted band 1', 1 === $dbands[1]['images'] );
check( 'divi: proof in band 2', true === $dbands[2]['proof'] );
check( 'divi: tinted bg band 0', 'tinted' === $dbands[0]['bg_treatment'] );
$dreport = CC_Assistant_Attention_Audit::score_bands( $dbands, 'service_local' );
check( 'divi: scoreable', is_numeric( $dreport['attention_score'] ) );

// Regressions: v0.55 review findings.
$b = CC_Assistant_Attention_Audit::extract_bands_elementor( array( section( array(
	widget( 'theme-post-title', array( 'title' => 'Service Page Title' ) ),
	widget( 'button', array( 'link' => array( 'url' => '/contact' ) ) ),
) ) ) );
check( 'F1: theme-post-title defaults to h1', true === $b[0]['h1'], json_encode( $b[0]['headings'] ) );

$b = CC_Assistant_Attention_Audit::extract_bands_elementor( array( section( array(
	widget( 'heading', array( 'title' => 'CALL NOW (972) 555-0100', 'header_size' => 'h2', 'link' => array( 'url' => 'tel:+19725550100' ) ) ),
) ) ) );
check( 'F2: tel on linked heading detected', true === $b[0]['tel_link'] );

$b = CC_Assistant_Attention_Audit::extract_bands_elementor( array( section( array(
	widget( 'heading', array( 'title' => 'Book a Consultation', 'header_size' => 'h1' ) ),
	widget( 'form', array() ),
) ) ) );
check( 'F3: form counts as CTA', 1 === $b[0]['cta_count'] );

$b = CC_Assistant_Attention_Audit::extract_bands_elementor( array( section( array(
	widget( 'heading', array( 'title' => 'Big Em Heading', 'header_size' => 'h1', 'typography_font_size' => array( 'size' => 2.5, 'unit' => 'em' ) ) ),
) ) ) );
check( 'F5: 2.5em heading = 40px', 40.0 === (float) $b[0]['headings'][0]['px'], json_encode( $b[0]['headings'][0] ) );

$es = 'Atención médica de urgencia con médicos certificados en el corazón de Irving';
$b  = CC_Assistant_Attention_Audit::extract_bands_elementor( array( section( array(
	widget( 'text-editor', array( 'editor' => '<p>' . $es . '</p>' ) ),
) ) ) );
check( 'F6: Spanish 12-token sentence counts 12', 12 === $b[0]['text_words'], (string) $b[0]['text_words'] );

$divi_hero = '[et_pb_section fullwidth="on"][et_pb_fullwidth_header title="Emergency Room Lufkin" button_one_text="Call Now" button_one_url="tel:+19365550100"][/et_pb_fullwidth_header][/et_pb_section]';
$db = CC_Assistant_Attention_Audit::extract_bands_divi( $divi_hero );
check( 'F4: Divi fullwidth_header h1 from attrs', true === $db[0]['h1'], json_encode( $db[0]['headings'] ) );
check( 'F4: Divi fullwidth_header tel button', true === $db[0]['tel_link'] && 1 === $db[0]['cta_count'] );

$divi_prev = '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]<h2>Preview our facility tour</h2><p>reviewed annually</p>[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';
$db = CC_Assistant_Attention_Audit::extract_bands_divi( $divi_prev );
check( 'F7: "Preview"/"reviewed" body text is not proof', false === $db[0]['proof'] );
$divi_real = '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]<h2>Patient Reviews</h2>[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';
$db = CC_Assistant_Attention_Audit::extract_bands_divi( $divi_real );
check( 'F7: "Reviews" heading IS proof', true === $db[0]['proof'] );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
