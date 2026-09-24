<?php
namespace Elementor { class Plugin { public static function instance() { return $GLOBALS['elementor_fixture']; } } }
namespace {
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
class WP_Error {
	public $code; public $message;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function did_action() { return $GLOBALS['elementor_available'] ?? true; }
function get_option( $name, $default = null ) { return $GLOBALS['options'][$name] ?? $default; }
function update_option( $name, $value, $autoload = null ) {
	if ( ! empty( $GLOBALS['write_failure'] ) ) { return false; }
	$GLOBALS['options'][$name] = ! empty( $GLOBALS['filter_changes_value'] ) ? 'filtered' : $value; return true;
}
function get_registered_settings() { return $GLOBALS['registered'] ?? array(); }
function rest_validate_value_from_schema( $value, $schema, $param ) {
	return isset( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ? new WP_Error( 'invalid_enum', 'Invalid enum' ) : true;
}
class SettingDB { public $options = 'wp_options'; public function prepare( $sql, ...$args ) { return $sql; } public function get_var() { return 'no'; } }
$GLOBALS['wpdb'] = new SettingDB();
class FixtureWidget {
	public $controls; public function get_controls() { return $this->controls; }
	public function get_title() { return 'Fixture'; } public function get_categories() { return array( 'fixture' ); }
}
class FixtureManager {
	public $widget;
	public function get_widget_types( $type = null ) { return null === $type ? array( 'fixture' => $this->widget ) : ( 'fixture' === $type ? $this->widget : null ); }
	public function get_element_types( $type = null ) { return null === $type ? array() : null; }
}
$widget = new FixtureWidget();
$widget->controls = array( 'mode' => array( 'type' => 'select', 'options' => array( 'normal' => 'Normal', 'special' => 'Special' ), 'default' => 'normal' ), 'color' => array( 'type' => 'color', 'condition' => array( 'mode' => 'special' ) ) );
$manager = new FixtureManager(); $manager->widget = $widget;
$GLOBALS['elementor_fixture'] = (object) array( 'widgets_manager' => $manager, 'elements_manager' => $manager );
require CC_ASSISTANT_DIR . 'includes/class-widget-schema.php'; require CC_ASSISTANT_DIR . 'includes/class-setting-writer.php';
function check( $name, $pass ) { if ( ! $pass ) { throw new \RuntimeException( $name ); } echo "PASS $name\n"; }
foreach ( array( array( 'typo' => 'yes' ), array( 'mode_mobile' => 'special' ), array( 'mode' => array( 'special' ) ), array( 'mode' => 'imagined' ), array( 'color' => '#000000' ) ) as $settings ) {
	check( 'invalid or ineffective settings block queue', is_wp_error( CC_Assistant_Widget_Schema::blocking_error( CC_Assistant_Widget_Schema::validation_warnings_for_new_element( 'fixture', $settings ) ) ) );
}
check( 'setting and gate together allowed', null === CC_Assistant_Widget_Schema::blocking_error( CC_Assistant_Widget_Schema::validation_warnings_for_new_element( 'fixture', array( 'mode' => 'special', 'color' => '#000000' ) ) ) );
check( 'default-only widget remains supported', null === CC_Assistant_Widget_Schema::blocking_error( CC_Assistant_Widget_Schema::validation_warnings_for_new_element( 'fixture', array() ) ) );
$first = CC_Assistant_Widget_Schema::schema( 'fixture' ); $widget->controls['addon_control'] = array( 'type' => 'text' ); $second = CC_Assistant_Widget_Schema::schema( 'fixture' );
check( 'addon change visible without core version bump', ! isset( $first['controls']['addon_control'] ) && isset( $second['controls']['addon_control'] ) );
$GLOBALS['elementor_available'] = false;
check( 'unavailable schema blocks queue', is_wp_error( CC_Assistant_Widget_Schema::blocking_error( CC_Assistant_Widget_Schema::validation_warnings_for_new_element( 'fixture', array( 'mode' => 'normal' ) ) ) ) );
$GLOBALS['options'] = array( 'acme_settings' => array( 'mode' => 'normal' ), 'acme_scalar' => 'before' );
$secret = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'acme_settings', 'path' => 'api_key', 'value' => 'anything' ) );
check( 'credential leaf cannot be exposed in a preview', is_wp_error( $secret ) && 'option_protected' === $secret->code );
$unknown = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'acme_settings', 'path' => 'imagined', 'value' => 'on', 'allow_create' => true ) );
check( 'allow_create cannot invent unregistered keys', is_wp_error( $unknown ) && 'setting_schema_required' === $unknown->code );
$plan = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'acme_settings', 'path' => 'mode', 'value' => 'special' ) );
check( 'stored path does not prove feature support', 'storage_path_observed' === $plan['validation']['status'] && 'unknown' === $plan['validation']['feature_effect'] );
$GLOBALS['registered']['acme_settings'] = array( 'show_in_rest' => array( 'schema' => array( 'type' => 'object', 'properties' => array( 'mode' => array( 'type' => 'string', 'enum' => array( 'normal', 'special' ) ), 'new_field' => array( 'type' => 'string' ) ) ) ) );
$bad = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'acme_settings', 'path' => 'mode', 'value' => 'invented' ) );
check( 'registered enum rejects guessed value', is_wp_error( $bad ) && 'setting_schema_invalid' === $bad->code );
$created = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'acme_settings', 'path' => 'new_field', 'value' => 'valid', 'allow_create' => true ) );
check( 'explicitly declared new key can be planned', is_array( $created ) && 'registered_schema_valid' === $created['validation']['status'] );
$GLOBALS['options']['acme_settings']['sibling'] = 'newer'; $conflict = CC_Assistant_Setting_Writer::apply_plan( $plan );
check( 'apply refuses stale snapshot', is_wp_error( $conflict ) && 'setting_conflict' === $conflict->code );
$scalar = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'acme_scalar', 'value' => 'after' ) ); $GLOBALS['filter_changes_value'] = true;
check( 'accepted but filtered write fails readback', is_wp_error( CC_Assistant_Setting_Writer::apply_plan( $scalar ) ) );
$GLOBALS['filter_changes_value'] = false; $GLOBALS['options']['acme_scalar'] = 'after'; $GLOBALS['write_failure'] = true;
$failed = CC_Assistant_Setting_Writer::revert_plan( $scalar );
check( 'failed restoration cannot report success', is_wp_error( $failed ) && 'setting_revert_failed' === $failed->code );

$GLOBALS['elementor_available'] = true;
require CC_ASSISTANT_DIR . 'includes/class-elementor-validation.php';
$widget->controls['rows'] = array( 'type' => 'repeater', 'fields' => array( array( 'name' => 'label', 'type' => 'text' ), array( 'name' => 'choice', 'type' => 'select', 'options' => array( 'yes' => 'Yes', 'no' => 'No' ) ) ) );
$node = array( 'id' => 'abc', 'elType' => 'widget', 'widgetType' => 'fixture', 'settings' => array( 'rows' => array( array( '_id' => 'row', 'choice' => 'made-up' ) ) ), 'elements' => array() );
check( 'Nested repeater invalid enum rejected', is_wp_error( CC_Assistant_Elementor_Validation::validate_tree( array(), array( $node ) ) ) );
$node['settings']['rows'][0] = array( '_id' => 'row', 'label' => 'Valid', 'choice' => 'yes' );
check( 'Known repeater row accepted', true === CC_Assistant_Elementor_Validation::validate_tree( array(), array( $node ) ) );
$old = $node; $old['settings']['legacy_unknown'] = 'preserved'; $new = $old; $new['settings']['mode'] = 'special';
check( 'Unchanged legacy settings do not block surgical edits', true === CC_Assistant_Elementor_Validation::validate_tree( array( $old ), array( $new ) ) );
$new['settings']['legacy_unknown'] = 'guessed change';
check( 'Changing an unknown legacy key is blocked', is_wp_error( CC_Assistant_Elementor_Validation::validate_tree( array( $old ), array( $new ) ) ) );
$outer = $node; $bad = $node; $bad['settings']['invented_key'] = 'x'; $outer['elements'] = array( $bad );
check( 'Unknown setting in nested subtree rejected', is_wp_error( CC_Assistant_Elementor_Validation::validate_tree( array(), array( $outer ) ) ) );
$node['settings']['rows'][0]['typo'] = 'x';
check( 'Unknown repeater field rejected', is_wp_error( CC_Assistant_Elementor_Validation::validate_tree( array(), array( $node ) ) ) );
unset( $node['settings']['rows'][0]['typo'] ); unset( $widget->controls['rows'] );
check( 'Registry removal visible at revalidation', is_wp_error( CC_Assistant_Elementor_Validation::validate_tree( array(), array( $node ) ) ) );

echo "All capability checks passed.\n";
}
