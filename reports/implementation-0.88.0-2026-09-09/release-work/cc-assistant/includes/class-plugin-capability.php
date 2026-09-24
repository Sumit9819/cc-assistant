<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Read only runtime evidence. No callback evaluation, license guesses or invented enums. */
class CC_Assistant_Plugin_Capability {
    public static function registered_schema( $option, $path = '' ) {
        $all = function_exists( 'get_registered_settings' ) ? get_registered_settings() : array();
        $args = $all[$option] ?? array();
        $rest = $args['show_in_rest'] ?? false;
        $schema = is_array( $rest ) ? ( $rest['schema'] ?? array() ) : array();
        if ( '' === $path && empty( $schema ) && ! empty( $args['type'] ) ) { $schema = array( 'type' => $args['type'] ); }
        if ( '' !== $path ) { foreach ( explode( '.', $path ) as $segment ) { $schema = $schema['properties'][$segment] ?? array(); } }
        return $schema;
    }

    public static function inspect( $args ) {
        require_once __DIR__ . '/class-stack-introspect.php';
        require_once __DIR__ . '/class-setting-writer.php';
        $slug = (string) ( $args['slug'] ?? '' );
        if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $slug ) ) { return new WP_Error( 'capability_slug', 'Supply the exact installed plugin slug.', array( 'status' => 400 ) ); }
        $settings = CC_Assistant_Stack_Introspect::plugin_settings( $slug );
        if ( is_wp_error( $settings ) ) { return $settings; }
        $out = array( 'slug' => $slug, 'installed' => $settings['installed'] ?? false, 'active' => $settings['active'] ?? false,
            'version' => $settings['version'] ?? null, 'captured_at_utc' => gmdate( 'c' ), 'coverage' => 'partial_runtime_evidence',
            'feature_effect' => 'unverified', 'license_entitlements' => 'unknown', 'option_ownership' => 'heuristic_from_stack_scan', 'controls' => array(),
            'limits' => array( 'Storage values do not enumerate supported features. Unavailable schemas remain unknown.', 'Conditional applicability and saved values do not certify the rendered effect.' ) );
        if ( empty( $out['installed'] ) || empty( $out['active'] ) ) { $out['coverage'] = 'plugin_not_active'; return $out; }
        $option = (string) ( $args['option_name'] ?? '' ); $path = (string) ( $args['path'] ?? '' );
        if ( '' !== $option ) {
            $observed = null;
            foreach ( (array) ( $settings['options'] ?? array() ) as $row ) { if ( ( $row['option'] ?? '' ) === $option ) { $observed = $row; break; } }
            if ( null === $observed || CC_Assistant_Stack_Introspect::is_secret_key( $option . '.' . $path ) ) {
                return new WP_Error( 'capability_option_unobserved', 'Read get_plugin_settings and choose a visible option belonging to this installed plugin. Secret values are unavailable.', array( 'status' => 422 ) );
            }
            $value = CC_Assistant_Setting_Writer::read_path( $observed['value'], $path );
            $schema = self::public_schema( self::registered_schema( $option, $path ) );
            $control = array( 'option_name' => $option, 'path' => $path, 'stored_value' => $value, 'schema' => $schema,
                'accepted_values_status' => empty( $schema ) ? 'unknown' : 'registered_constraints_only',
                'schema_source' => empty( $schema ) ? null : 'wordpress_settings_registry_this_request', 'applicability' => 'unknown' );
            if ( array_key_exists( 'proposed_value', $args ) ) {
                $valid = CC_Assistant_Setting_Writer::validate_registered_value( $option, $path, $args['proposed_value'], ! $value['found'] );
                $control['validation'] = is_wp_error( $valid ) ? array( 'status' => 'rejected', 'code' => $valid->get_error_code(), 'message' => $valid->get_error_message() ) : $valid;
            }
            $out['controls'][] = $control;
        }
        if ( 'elementor' === $slug ) {
            require_once __DIR__ . '/class-widget-schema.php';
            $out['widget_registry'] = empty( $args['widget_type'] ) ? CC_Assistant_Widget_Schema::list_types() : CC_Assistant_Widget_Schema::schema( (string) $args['widget_type'] );
            if ( ! empty( $args['post_id'] ) && ! empty( $args['element_id'] ) ) { $out['element_values'] = CC_Assistant_Widget_Schema::effective_for_post_element( (int) $args['post_id'], (string) $args['element_id'] ); }
        }
        if ( 'seo-by-rank-math' === $slug && function_exists( 'rank_math' ) ) {
            $rm = rank_math();
            $modules = isset( $rm->manager->modules ) && is_array( $rm->manager->modules ) ? $rm->manager->modules : array();
            $out['registered_modules'] = array();
            foreach ( array_keys( $modules ) as $id ) {
                $out['registered_modules'][] = array( 'id' => $id, 'active' => is_callable( array( 'RankMath\\Helper', 'is_module_active' ) ) ? \RankMath\Helper::is_module_active( $id ) : null );
            }
            $out['module_source'] = 'current_rank_math_manager_registry';
        }
        if ( 'sg-cachepress' === $slug ) {
            $class = 'SiteGround_Optimizer\\Rest\\Rest';
            $props = class_exists( $class ) ? get_class_vars( $class ) : array();
            $out['native_toggle_routes'] = array_values( (array) ( $props['toggle_options'] ?? array() ) );
            $out['toggle_source'] = 'runtime_rest_class_public_registry';
            $out['limits'][] = 'Native SiteGround actions can change server rules and purge caches. Generic option writes cannot reproduce those actions and are rejected for SiteGround options. CAPTCHA/hosting controls are not inferred from this optimizer registry.';
        }
        if ( in_array( $slug, array( 'polylang', 'polylang-pro' ), true ) ) {
            $out['languages'] = function_exists( 'pll_languages_list' ) ? pll_languages_list( array( 'fields' => 'slug' ) ) : null;
            if ( ! empty( $args['post_id'] ) ) {
                $out['translations'] = function_exists( 'pll_get_post_translations' ) ? pll_get_post_translations( (int) $args['post_id'] ) : null;
            }
        }
        foreach ( $out as $key => $value ) { if ( is_wp_error( $value ) ) { $out[$key] = array( 'status' => 'unavailable', 'code' => $value->get_error_code(), 'message' => $value->get_error_message() ); } }
        return $out;
    }

    /** No secret defaults or plugin-provided callbacks in an exposed schema. */
    private static function public_schema( $schema ) {
        $safe = array();
        foreach ( (array) $schema as $key => $value ) {
            if ( in_array( $key, array( 'type', 'enum', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'format', 'required', 'additionalProperties' ), true ) ) { $safe[$key] = $value; }
            if ( 'properties' === $key ) {
                foreach ( (array) $value as $name => $child ) {
                    if ( ! CC_Assistant_Stack_Introspect::is_secret_key( $name ) ) { $safe['properties'][$name] = self::public_schema( $child ); }
                }
            }
            if ( 'items' === $key && is_array( $value ) ) { $safe[$key] = self::public_schema( $value ); }
        }
        return $safe;
    }
}
