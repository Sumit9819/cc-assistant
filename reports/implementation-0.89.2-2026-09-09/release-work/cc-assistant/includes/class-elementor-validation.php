<?php
/** Validate proposed Elementor settings against the running site's controls. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-widget-schema.php';

class CC_Assistant_Elementor_Validation {

	private static function error( $message ) { return new WP_Error( 'unverified_elementor_settings', $message, array( 'status' => 422 ) ); }
	private static function tree( $id ) {
		require_once __DIR__ . '/class-elementor-builder.php';
		return CC_Assistant_Elementor_Builder::load_tree( (int) $id, true );
	}
	/** Changed settings only: untouched legacy values are not new proposals. */
	public static function validate_tree( $before, $after ) {
		if ( is_wp_error( $before ) ) { return $before; }
		if ( is_wp_error( $after ) ) { return $after; }
		if ( ! is_array( $after ) ) { return self::error( 'Elementor data must be an array.' ); }
		$index = array(); $schemas = array(); $count = 0;
		$collect = function ( $nodes, $depth = 0 ) use ( &$collect, &$index ) {
			if ( $depth > 64 || ! is_array( $nodes ) ) { return; }
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) { continue; }
				if ( ! empty( $node['id'] ) ) { $index[$node['id']] = $node; }
				$collect( $node['elements'] ?? array(), $depth + 1 );
			}
		};
		$collect( $before );
		$walk = function ( $nodes, $depth = 0 ) use ( &$walk, &$index, &$schemas, &$count ) {
			if ( $depth > 64 || ! is_array( $nodes ) ) { return self::error( 'Elementor children are malformed or exceed the depth limit.' ); }
			foreach ( $nodes as $node ) {
				if ( ++$count > 10000 || ! is_array( $node ) ) { return self::error( 'Elementor data is malformed or exceeds the node limit.' ); }
				$kind = $node['elType'] ?? $node['type'] ?? '';
				$type = 'widget' === $kind ? ( $node['widgetType'] ?? '' ) : $kind;
				if ( ! in_array( $kind, array( 'widget', 'container', 'section', 'column' ), true ) || ! is_string( $type ) || '' === $type ) { return self::error( 'An Elementor node has no supported element/widget type.' ); }
				$settings = $node['settings'] ?? array();
				if ( ! is_array( $settings ) ) { return self::error( 'Elementor settings must be an object.' ); }
				$old = $index[$node['id'] ?? ''] ?? null;
				$old_type = $old ? ( $old['widgetType'] ?? $old['elType'] ?? '' ) : '';
				$current = $old_type === $type && is_array( $old['settings'] ?? null ) ? $old['settings'] : array();
				$changed = array();
				foreach ( $settings as $key => $value ) { if ( ! array_key_exists( $key, $current ) || $value !== $current[$key] ) { $changed[$key] = $value; } }
				if ( ! $old || $old_type !== $type || $changed ) {
					if ( ! isset( $schemas[$type] ) ) { $schemas[$type] = CC_Assistant_Widget_Schema::schema( $type ); }
					$schema = $schemas[$type];
					if ( is_wp_error( $schema ) ) { return self::error( 'Cannot verify ' . $type . ': ' . $schema->get_error_message() ); }
					// The final settings supply gate values; omitted old keys reset to defaults.
					$warnings = CC_Assistant_Widget_Schema::validate_settings( $schema['controls'], $changed, $type, array_intersect_key( $current, $settings ) );
					$error = CC_Assistant_Widget_Schema::blocking_error( $warnings );
					if ( is_wp_error( $error ) ) { return $error; }
				}
				$result = $walk( $node['elements'] ?? $node['children'] ?? array(), $depth + 1 );
				if ( is_wp_error( $result ) ) { return $result; }
			}
			return true;
		};
		return $walk( $after );
	}
	public static function validate_payload( $args ) {
		$type = $args['change_type'] ?? ''; $id = (int) ( $args['post_id'] ?? 0 );
		$p = $args['proposed_value'] ?? array(); $p = is_string( $p ) ? json_decode( $p, true ) : $p;
		if ( ! is_array( $p ) ) { return self::error( 'Proposed changes must contain valid JSON.' ); }
		if ( 'elementor_widget_update' === $type ) {
			return CC_Assistant_Widget_Schema::blocking_error( CC_Assistant_Widget_Schema::validation_warnings_for_post_widget( $id, $p['widget_id'] ?? '', $p['settings'] ?? null ) ) ?: true;
		}
		if ( 'elementor_section_content_replace' === $type ) {
			foreach ( $p['widget_updates'] ?? array() as $row ) {
				$r = self::validate_payload( array( 'post_id' => $id, 'change_type' => 'elementor_widget_update', 'proposed_value' => $row ) );
				if ( is_wp_error( $r ) ) { return $r; }
			}
		}
		if ( 'elementor_widget_add' === $type ) {
			return self::validate_tree( array(), array( array( 'elType' => 'widget', 'widgetType' => $p['widget_type'] ?? '', 'settings' => $p['settings'] ?? array() ) ) );
		}
		if ( in_array( $type, array( 'elementor_container_add', 'elementor_section_rebuild' ), true ) ) {
			return self::validate_tree( array(), array( array( 'elType' => $p['el_type'] ?? 'container', 'settings' => $p['settings'] ?? array(), 'children' => $p['children'] ?? array() ) ) );
		}
		if ( 'elementor_accordion_item_add' === $type ) {
			require_once __DIR__ . '/class-elementor-builder.php';
			$before = self::tree( $id ); if ( is_wp_error( $before ) ) { return $before; } $after = $before;
			$added = CC_Assistant_Elementor_Builder::add_accordion_item( $after, $p['accordion_widget_id'] ?? '', $p['title'] ?? '', $p['content_html'] ?? '', $p['position'] ?? null );
			return is_wp_error( $added ) ? $added : self::validate_tree( $before, $after );
		}
		if ( 'elementor_full_import' === $type ) { return self::validate_tree( self::tree( $id ), $p['elementor_data'] ?? null ); }
		if ( 'publish_draft' === $type ) { return self::validate_tree( array(), self::tree( $id ) ); }
		if ( 'postmeta_update' === $type && '_elementor_data' === ( $p['key'] ?? $p['meta_key'] ?? '' ) ) { return self::error( 'Use the Elementor import or widget tools for Elementor data.' ); }
		return true;
	}
}
