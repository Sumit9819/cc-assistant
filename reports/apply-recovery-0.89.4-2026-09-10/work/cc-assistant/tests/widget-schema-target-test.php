<?php
require __DIR__ . '/capability-validation-test.php';
require CC_ASSISTANT_DIR . 'includes/class-rest-widget-schema.php';
function get_post_meta( $id, $key, $single = false ) { return 19 === $id ? '[{"id":"abc","elType":"widget","widgetType":"fixture","settings":{"mode":"special"}}]' : ''; }
function rest_ensure_response( $r ) { return $r; }
class SchemaRequest extends ArrayObject { public function get_param( $name ) { return $this[$name] ?? null; } }
$request = new SchemaRequest( array( 'post_id' => 19, 'widget_id' => 'abc' ) );
$r = CC_Assistant_REST_Widget_Schema::handle_list( $request );
check( 'Saved widget type is resolved without a guessed widget_type', ! is_wp_error( $r ) && 'fixture' === $r['widget_type'] && 'abc' === $r['element']['element_id'] );
check( 'Resolved schema includes saved effective settings', 'special' === $r['element']['effective']['mode']['value'] );
$r = CC_Assistant_REST_Widget_Schema::handle_list( new SchemaRequest( array( 'post_id' => 19 ) ) );
check( 'Partial target does not silently return the generic registry', is_wp_error( $r ) && 'element_target_incomplete' === $r->code );
$r = CC_Assistant_REST_Widget_Schema::handle_list( new SchemaRequest( array( 'post_id' => 20, 'widget_id' => 'missing' ) ) );
check( 'Missing saved element is an explicit error', is_wp_error( $r ) && 'element_not_found' === $r->code );
