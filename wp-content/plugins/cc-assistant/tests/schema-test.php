<?php
/**
 * Widget-schema pure-path test: control compaction (responsive collapse,
 * options, units, globals, conditions) + warn-only settings validation with
 * did-you-mean suggestions. Run: php tests/schema-test.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-widget-schema.php';

$fails = 0;
function check( $label, $cond, $extra = '' ) {
	global $fails;
	if ( $cond ) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label  $extra\n"; }
}

$controls = array(
	'section_button'   => array( 'type' => 'section' ),
	'text'             => array( 'type' => 'text', 'default' => 'Click here', 'section' => 'section_button' ),
	'link'             => array( 'type' => 'url', 'default' => array( 'url' => '' ), 'section' => 'section_button' ),
	'size'             => array( 'type' => 'select', 'default' => 'sm', 'options' => array( 'xs' => 'XS', 'sm' => 'SM', 'md' => 'MD', 'lg' => 'LG', 'xl' => 'XL' ), 'section' => 'section_button' ),
	'align'            => array( 'type' => 'choose', 'options' => array( 'left' => 'L', 'center' => 'C', 'right' => 'R', 'justify' => 'J' ), 'section' => 'section_button' ),
	'align_tablet'     => array( 'type' => 'choose', 'options' => array( 'left' => 'L', 'center' => 'C', 'right' => 'R', 'justify' => 'J' ) ),
	'align_mobile'     => array( 'type' => 'choose', 'options' => array( 'left' => 'L', 'center' => 'C', 'right' => 'R', 'justify' => 'J' ) ),
	'button_background_hover_color' => array( 'type' => 'color', 'global' => array( 'default' => '' ), 'section' => 'section_hover' ),
	'typography_font_size' => array( 'type' => 'slider', 'size_units' => array( 'px', 'em', 'rem', 'vw' ), 'section' => 'section_style' ),
	'typography_font_size_tablet' => array( 'type' => 'slider', 'size_units' => array( 'px', 'em', 'rem', 'vw' ) ),
	'hover_animation'  => array( 'type' => 'hover_animation', 'section' => 'section_hover' ),
	'ui_note'          => array( 'type' => 'raw_html' ),
	'style_heading'    => array( 'type' => 'heading' ),
	'selected_icon'    => array( 'type' => 'icons', 'condition' => array( 'text!' => '' ), 'section' => 'section_button' ),
);

$compact = CC_Assistant_Widget_Schema::compact_controls( $controls );

check( 'ui-only controls dropped', ! isset( $compact['ui_note'] ) && ! isset( $compact['style_heading'] ) );
check( 'responsive variants collapsed', ! isset( $compact['align_tablet'] ) && ! isset( $compact['align_mobile'] ) && ! isset( $compact['typography_font_size_tablet'] ) );
check( 'base marked responsive', true === $compact['align']['responsive'] && true === $compact['typography_font_size']['responsive'], json_encode( $compact['align'] ) );
check( 'options as key list', array( 'xs', 'sm', 'md', 'lg', 'xl' ) === $compact['size']['options'], json_encode( $compact['size'] ) );
check( 'units surfaced', array( 'px', 'em', 'rem', 'vw' ) === $compact['typography_font_size']['units'] );
check( 'global token flagged', true === $compact['button_background_hover_color']['global_token'] );
check( 'default surfaced', 'Click here' === $compact['text']['default'] );
check( 'condition surfaced', array( 'text!' => '' ) === $compact['selected_icon']['condition'] );
check( 'section surfaced', 'section_button' === $compact['text']['section'] );

$w = CC_Assistant_Widget_Schema::validate_settings( $compact, array(
	'text'        => 'Call Now',
	'size'        => 'sm',
	'__globals__' => array( 'button_background_color' => 'globals/colors?id=accent' ),
), 'button' );
check( 'valid settings: no warnings', array() === $w, json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $compact, array( 'buton_background_hover_color' => '#fff' ), 'button' );
check( 'typo key warned with suggestion', 1 === count( $w ) && 'unknown_setting_key' === $w[0]['code'] && false !== strpos( $w[0]['message'], 'button_background_hover_color' ), json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $compact, array( 'size' => 'giant' ), 'button' );
check( 'invalid enum warned', 1 === count( $w ) && 'invalid_option_value' === $w[0]['code'], json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $compact, array( 'align_mobile' => 'center' ), 'button' );
check( 'responsive suffix key accepted via base', array() === $w, json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $compact, array( 'align_mobile' => 'middle' ), 'button' );
check( 'responsive suffix enum still checked', 1 === count( $w ) && 'invalid_option_value' === $w[0]['code'], json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $compact, array( 'totally_made_up_key_zzz' => 1 ), 'button' );
check( 'far-off key: warning without bogus suggestion', 1 === count( $w ) && false === strpos( $w[0]['message'], 'Did you mean' ), json_encode( $w ) );

check( 'nearest: close match', 'heading' === CC_Assistant_Widget_Schema::nearest( 'headng', array( 'heading', 'button', 'image' ) ) );
check( 'nearest: nothing close', '' === CC_Assistant_Widget_Schema::nearest( 'zzzzzz', array( 'heading', 'button' ) ) );

/* ------------------------------------------------------------------------
 * v0.80 effective-value guard. Controls modelled on Elementor's container
 * (content_width / boxed_width / width) and background-overlay group
 * (background_overlay_background gates colour + opacity; opacity default .5)
 * — the two real incidents on the mammothmachinery dealer hero, 2026-09-03.
 * -------------------------------------------------------------------- */
echo "\n-- v0.80 effective values + gates\n";
$container = array(
	'section_layout'              => array( 'type' => 'section' ),
	'content_width'               => array( 'type' => 'select', 'default' => 'boxed', 'options' => array( 'boxed' => 'Boxed', 'full' => 'Full' ), 'section' => 'section_layout' ),
	'boxed_width'                 => array( 'type' => 'slider', 'default' => array( 'unit' => 'px' ), 'condition' => array( 'content_width' => 'boxed' ), 'section' => 'section_layout' ),
	'boxed_width_tablet'          => array( 'type' => 'slider' ),
	'width'                       => array( 'type' => 'slider', 'condition' => array( 'content_width' => 'full' ), 'section' => 'section_layout' ),
	'min_height'                  => array( 'type' => 'slider', 'section' => 'section_layout' ),
	'section_background_overlay'  => array( 'type' => 'section' ),
	'background_overlay_background' => array( 'type' => 'choose', 'options' => array( 'classic' => 'C', 'gradient' => 'G' ), 'section' => 'section_background_overlay' ),
	'background_overlay_color'    => array( 'type' => 'color', 'condition' => array( 'background_overlay_background' => array( 'classic', 'gradient' ) ), 'section' => 'section_background_overlay' ),
	'background_overlay_opacity'  => array( 'type' => 'slider', 'default' => array( 'size' => 0.5 ), 'condition' => array( 'background_overlay_background' => array( 'classic', 'gradient' ) ), 'section' => 'section_background_overlay' ),
	'overlay_blend_mode'          => array( 'type' => 'select', 'options' => array( '' => 'Normal', 'multiply' => 'M' ), 'section' => 'section_background_overlay' ),
	'section_style'               => array( 'type' => 'section' ),
	'button_background_color'     => array( 'type' => 'color', 'global' => array( 'default' => '' ), 'section' => 'section_style' ),
	'fancy'                       => array( 'type' => 'switcher', 'default' => 'yes', 'section' => 'section_style', 'conditions' => array( 'relation' => 'or', 'terms' => array(
		array( 'name' => 'content_width', 'operator' => '==', 'value' => 'full' ),
		array( 'name' => 'min_height[size]', 'operator' => '>', 'value' => 100 ),
	) ) ),
);
$cc = CC_Assistant_Widget_Schema::compact_controls( $container );
check( 'complex conditions surfaced', isset( $cc['fancy']['conditions']['terms'] ) );

function codes( $w ) { return array_values( array_unique( array_map( function ( $x ) { return $x['code']; }, $w ) ) ); }

/* incident 1: boxed_width under content_width=full */
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'boxed_width' => array( 'size' => 900, 'unit' => 'px' ) ), 'container', array( 'content_width' => 'full' ) );
check( 'boxed_width under content_width=full -> inert_setting', in_array( 'inert_setting', codes( $w ), true ), json_encode( $w ) );
check( 'inert message names the gate, its value and the needed value', (bool) preg_grep( '/content_width is "full" \(needs boxed\)/', array_column( $w, 'message' ) ), json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'boxed_width' => array( 'size' => 900, 'unit' => 'px' ), 'content_width' => 'boxed' ), 'container', array( 'content_width' => 'full' ) );
check( 'gate fixed in the same write -> no inert', ! in_array( 'inert_setting', codes( $w ), true ), json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'boxed_width_tablet' => array( 'size' => 700, 'unit' => 'px' ) ), 'container', array() );
check( 'default content_width=boxed satisfies the gate for a responsive variant (absent key = default)', ! in_array( 'inert_setting', codes( $w ), true ), json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'width' => array( 'size' => 50, 'unit' => '%' ) ), 'container', array() );
check( 'width under DEFAULT content_width=boxed -> inert (the default is the gate)', in_array( 'inert_setting', codes( $w ), true ), json_encode( $w ) );

/* incident 2: overlay colour without its gate / with the opacity default */
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'background_overlay_color' => 'rgba(15,18,15,0.78)' ), 'container', array() );
check( 'overlay colour with no overlay type -> inert (gate unset)', in_array( 'inert_setting', codes( $w ), true ) && (bool) preg_grep( '/background_overlay_background is unset/', array_column( $w, 'message' ) ), json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'background_overlay_background' => 'classic', 'background_overlay_color' => 'rgba(15,18,15,0.78)' ), 'container', array() );
check( 'overlay type + colour -> no inert', ! in_array( 'inert_setting', codes( $w ), true ), json_encode( $w ) );
check( 'unset opacity default 0.5 reported as defaults_in_effect', (bool) preg_grep( '/background_overlay_opacity=\{"size":0\.5\}/', array_column( $w, 'message' ) ), json_encode( $w ) );
check( 'blend mode (no default) and colour (not render-governing type) NOT listed', ! (bool) preg_grep( '/overlay_blend_mode|background_overlay_color=/', array_column( $w, 'message' ) ), json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'background_overlay_background' => 'classic', 'background_overlay_color' => '#000', 'background_overlay_opacity' => array( 'size' => 1 ) ), 'container', array() );
check( 'opacity set explicitly -> not in defaults_in_effect', ! (bool) preg_grep( '/background_overlay_opacity=/', array_column( $w, 'message' ) ), json_encode( $w ) );

$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'background_overlay_color' => '#000' ), 'container', array( 'background_overlay_background' => 'classic', 'background_overlay_opacity' => array( 'size' => 0.9 ) ) );
check( 'stored gate + stored opacity -> clean write', array() === $w, json_encode( $w ) );

/* the mammoth shape exactly: gate stored, colour written, opacity never set */
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'background_overlay_color' => 'rgba(15,18,15,0.78)' ), 'container', array( 'background_overlay_background' => 'classic' ) );
check( 'co-gated sibling (opacity) reported when only the colour is written', (bool) preg_grep( '/background_overlay_opacity=\{"size":0\.5\}/', array_column( $w, 'message' ) ) && ! in_array( 'inert_setting', codes( $w ), true ), json_encode( $w ) );

/* content_width write: boxed_width is gated by it but its default {"unit":"px"} is meaningless */
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'content_width' => 'boxed' ), 'container', array() );
check( 'meaningless {"unit":"px"} default not reported', array() === $w, json_encode( $w ) );

/* global token wins over literal */
$cur = array( '__globals__' => array( 'button_background_color' => 'globals/colors?id=accent' ) );
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'button_background_color' => '#ff0000' ), 'container', $cur );
check( 'literal over live token -> global_token_overrides_literal', in_array( 'global_token_overrides_literal', codes( $w ), true ), json_encode( $w ) );
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'button_background_color' => '#ff0000', '__globals__' => array( 'button_background_color' => '' ) ), 'container', $cur );
check( 'literal + token detached in same write -> clean', ! in_array( 'global_token_overrides_literal', codes( $w ), true ), json_encode( $w ) );
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( '__globals__' => array( 'button_background_color' => 'globals/colors?id=primary' ) ), 'container', $cur );
check( 'retargeting the token -> clean', array() === $w, json_encode( $w ) );
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'button_background_color' => '#ff0000' ), 'container', array() );
check( 'literal with no token bound -> clean', array() === $w, json_encode( $w ) );

/* complex conditions (relation or) */
check( 'conditions: or-relation true via second term', CC_Assistant_Widget_Schema::control_visible( $cc['fancy'], array( 'content_width' => 'boxed', 'min_height' => array( 'size' => 300 ) ) ) );
check( 'conditions: or-relation false when both fail', ! CC_Assistant_Widget_Schema::control_visible( $cc['fancy'], array( 'content_width' => 'boxed', 'min_height' => array( 'size' => 10 ) ) ) );
check( 'condition: negative form', CC_Assistant_Widget_Schema::control_visible( array( 'condition' => array( 'text!' => '' ) ), array( 'text' => 'Go' ) ) && ! CC_Assistant_Widget_Schema::control_visible( array( 'condition' => array( 'text!' => '' ) ), array( 'text' => '' ) ) );
check( 'condition: sub-key form', CC_Assistant_Widget_Schema::control_visible( array( 'condition' => array( 'image[url]!' => '' ) ), array( 'image' => array( 'url' => 'x.jpg' ) ) ) );

/* effective values */
$eff = CC_Assistant_Widget_Schema::effective_values( $cc, array( 'content_width' => 'full', '__globals__' => array( 'a' => 'b' ) ), array( 'min_height' => array( 'size' => 400 ) ) );
check( 'effective: new > stored > default', 'new' === $eff['min_height']['source'] && 'full' === $eff['content_width']['value'] && 'stored' === $eff['content_width']['source'] && 'default' === $eff['background_overlay_opacity']['source'] );
check( 'effective: __globals__ passes through', isset( $eff['__globals__'] ) && 'stored' === $eff['__globals__']['source'] );
check( 'effective: controls with no default and no value are absent', ! isset( $eff['background_overlay_color'] ) );

/* v0.56 behaviour unchanged without current settings */
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'boxed_width' => array( 'size' => 900 ) ), 'container' );
check( 'no current settings -> only v0.56 checks run (no inert)', array() === $w, json_encode( $w ) );
$w = CC_Assistant_Widget_Schema::validate_settings( $cc, array( 'min_height' => array( 'size' => 1 ) ), 'container', array() );
check( 'negative control: ungated write in a section without governing defaults is clean', array() === $w, json_encode( $w ) );

/* multi-select gates: a control like submit_actions stores an ARRAY, and Elementor
   asks whether the required value is IN it. Comparing the whole array to a scalar
   read every such gate as off, which marked correct Elementor Pro email settings
   inert and refused the queue (mammothmachinery financing form, 0.89.10). */
$email_gate = array( 'condition' => array( 'submit_actions' => 'email' ) );
check( 'multi-select gate: value present in the stored array opens it', CC_Assistant_Widget_Schema::control_visible( $email_gate, array( 'submit_actions' => array( 'email', 'save-to-database' ) ) ) );
check( 'multi-select gate: value absent keeps it shut', ! CC_Assistant_Widget_Schema::control_visible( $email_gate, array( 'submit_actions' => array( 'save-to-database', 'webhook' ) ) ) );
check( 'multi-select gate: single-entry array opens it', CC_Assistant_Widget_Schema::control_visible( $email_gate, array( 'submit_actions' => array( 'email' ) ) ) );
check( 'multi-select gate: empty array keeps it shut', ! CC_Assistant_Widget_Schema::control_visible( $email_gate, array( 'submit_actions' => array() ) ) );
$any_of = array( 'condition' => array( 'submit_actions' => array( 'email', 'email2' ) ) );
check( 'multi-select gate: array vs array opens on any overlap', CC_Assistant_Widget_Schema::control_visible( $any_of, array( 'submit_actions' => array( 'save-to-database', 'email2' ) ) ) );
check( 'multi-select gate: array vs array shut with no overlap', ! CC_Assistant_Widget_Schema::control_visible( $any_of, array( 'submit_actions' => array( 'save-to-database', 'webhook' ) ) ) );
check( 'scalar gate unchanged: match opens, mismatch shuts', CC_Assistant_Widget_Schema::control_visible( array( 'condition' => array( 'skin' => 'cards' ) ), array( 'skin' => 'cards' ) ) && ! CC_Assistant_Widget_Schema::control_visible( array( 'condition' => array( 'skin' => 'cards' ) ), array( 'skin' => 'classic' ) ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );
