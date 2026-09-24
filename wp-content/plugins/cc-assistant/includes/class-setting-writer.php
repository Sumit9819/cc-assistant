<?php
/**
 * v0.69 — draft-queued writes to a plugin's stored settings.
 *
 * The read half (CC_Assistant_Stack_Introspect::plugin_settings) has existed
 * since v0.65 and is deliberately read-only. This is its counterpart, and it
 * exists because of a real dead end: a store's Google Shopping feed was
 * rejecting 146 products over a price-format setting that lives inside a
 * third-party feed plugin. Reading the setting told us exactly what was wrong
 * and then there was nothing the toolchain could do about it.
 *
 * DESIGN RULE THIS FOLLOWS: no per-plugin adapters. This writes one leaf of
 * one option by dot path, for ANY plugin, rather than growing a CTX-Feed
 * module that would need a sibling for the next plugin and the one after.
 *
 * WHY IT IS SAFE ENOUGH TO EXIST:
 *   - Draft-only. Nothing is written until a human approves the pending row.
 *   - The FULL prior option value is snapshotted, so revert restores the whole
 *     structure, not just the leaf — a partial restore on a serialized
 *     settings blob is how you corrupt a plugin's configuration.
 *   - The path must already exist unless allow_create is passed. A typo
 *     therefore fails loudly instead of silently planting a junk key that the
 *     plugin ignores while the operator believes the setting was changed.
 *   - Core WordPress options and anything that looks like a credential are
 *     refused outright, reusing the same detectors the reader redacts with.
 *   - Scalars only at the leaf. This tool flips a setting; it does not
 *     restructure a plugin's data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Setting_Writer {

	/** Refuse values larger than this at the leaf (chars). */
	const MAX_VALUE_LEN = 2000;

	/**
	 * Options that are never writable regardless of who asks: core site
	 * plumbing plus the plugin's own bookkeeping. Editing active_plugins or
	 * user_roles through a generic setter is how a site stops loading.
	 */
	private static function denied_option( $name ) {
		$name = (string) $name;
		require_once CC_ASSISTANT_DIR . 'includes/class-stack-introspect.php';
		if ( in_array( $name, CC_Assistant_Stack_Introspect::core_option_names(), true ) ) {
			return 'core WordPress option';
		}
		if ( CC_Assistant_Stack_Introspect::is_secret_key( $name ) ) {
			return 'looks like a credential';
		}
		if ( 0 === strpos( $name, 'cc_assistant' ) || 0 === strpos( $name, '_transient' ) || 0 === strpos( $name, '_site_transient' ) ) {
			return 'plugin-internal or transient';
		}
		return '';
	}

	/**
	 * Walk a dot path into a nested array.
	 *
	 * @return array{found:bool, value:mixed}
	 */
	public static function read_path( $data, $path ) {
		if ( '' === $path ) {
			return array( 'found' => true, 'value' => $data );
		}
		$node = $data;
		foreach ( explode( '.', $path ) as $seg ) {
			if ( is_array( $node ) && array_key_exists( $seg, $node ) ) {
				$node = $node[ $seg ];
				continue;
			}
			if ( is_object( $node ) && isset( $node->$seg ) ) {
				$node = $node->$seg;
				continue;
			}
			return array( 'found' => false, 'value' => null );
		}
		return array( 'found' => true, 'value' => $node );
	}

	/**
	 * Set a dot path on a nested array, returning the modified copy.
	 * Objects are refused by the caller, so this only handles arrays.
	 */
	private static function write_path( $data, $path, $value ) {
		if ( '' === $path ) {
			return $value;
		}
		$segs = explode( '.', $path );
		$copy = $data;
		$ref  = &$copy;
		foreach ( $segs as $i => $seg ) {
			if ( $i === count( $segs ) - 1 ) {
				$ref[ $seg ] = $value;
				break;
			}
			if ( ! isset( $ref[ $seg ] ) || ! is_array( $ref[ $seg ] ) ) {
				$ref[ $seg ] = array();
			}
			$ref = &$ref[ $seg ];
		}
		unset( $ref );
		return $copy;
	}

	/**
	 * Build a change plan without writing.
	 *
	 * @param array $args option_name, path, value, allow_create
	 * @return array|WP_Error
	 */
	public static function build_plan( $args ) {
		if ( ! empty( $args['adapter'] ) ) { require_once __DIR__ . '/class-siteground-adapter.php'; return CC_Assistant_SiteGround_Adapter::build_plan( $args ); }
		$option = isset( $args['option_name'] ) ? trim( (string) $args['option_name'] ) : '';
		$path   = isset( $args['path'] ) ? trim( (string) $args['path'] ) : '';
		require_once CC_ASSISTANT_DIR . 'includes/class-stack-introspect.php';
		foreach ( explode( '.', $path ) as $segment ) {
			if ( CC_Assistant_Stack_Introspect::is_secret_key( $segment ) ) { return new WP_Error( 'option_protected', 'Credential-shaped setting paths cannot be previewed or changed through the generic writer.', array( 'status' => 403 ) ); }
		}
		$create = ! empty( $args['allow_create'] );

		if ( '' === $option ) {
			return new WP_Error( 'option_required', 'option_name is required. Use get_plugin_settings to find it.', array( 'status' => 400 ) );
		}
		$denied = self::denied_option( $option );
		if ( $denied ) {
			return new WP_Error(
				'option_protected',
				sprintf( 'Refusing to write "%s" — %s. This tool changes plugin configuration, not site plumbing or secrets.', $option, $denied ),
				array( 'status' => 403 )
			);
		}
		if ( ! array_key_exists( 'value', $args ) ) {
			return new WP_Error( 'value_required', 'value is required (use an empty string to blank a setting).', array( 'status' => 400 ) );
		}
		$value = $args['value'];
		if ( is_array( $value ) || is_object( $value ) ) {
			return new WP_Error( 'value_not_scalar', 'value must be a string, number, or boolean. This tool flips one setting; it does not restructure a plugin\'s data.', array( 'status' => 400 ) );
		}
		if ( is_string( $value ) && strlen( $value ) > self::MAX_VALUE_LEN ) {
			return new WP_Error( 'value_too_long', sprintf( 'value exceeds %d characters.', self::MAX_VALUE_LEN ), array( 'status' => 400 ) );
		}

		$current = get_option( $option, null );
		if ( null === $current ) {
			return new WP_Error(
				'option_not_found',
				sprintf( 'Option "%s" does not exist. This tool edits settings that are already there; it will not invent one.', $option ),
				array( 'status' => 404 )
			);
		}
		if ( '' !== $path && ! is_array( $current ) ) {
			return new WP_Error(
				'path_on_scalar',
				sprintf( 'Option "%s" holds a scalar, so a dot path is meaningless. Omit path to replace the whole value.', $option ),
				array( 'status' => 400 )
			);
		}

		$read = self::read_path( $current, $path );
		if ( ! $read['found'] && ! $create ) {
			return new WP_Error(
				'path_not_found',
				sprintf(
					'Path "%s" does not exist in option "%s". Re-check it with get_plugin_settings — a mistyped path would otherwise store a key the plugin never reads, and the setting would appear changed while doing nothing. Creation requires allow_create=true AND an explicit registered REST schema for that path.',
					$path,
					$option
				),
				array( 'status' => 404 )
			);
		}
		$validation = self::validate_registered_value( $option, $path, $value, ! $read['found'] );
		if ( is_wp_error( $validation ) ) { return $validation; }
		$prior = $read['found'] ? $read['value'] : null;
		if ( is_array( $prior ) || is_object( $prior ) ) {
			return new WP_Error(
				'path_not_leaf',
				sprintf( 'Path "%s" points at a nested structure, not a single setting. Target a leaf.', $path ),
				array( 'status' => 400 )
			);
		}

		$prior_str = is_bool( $prior ) ? ( $prior ? 'true' : 'false' ) : (string) $prior;
		$new_str   = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
		if ( $read['found'] && $prior_str === $new_str ) {
			return new WP_Error(
				'no_change',
				sprintf( 'Already set to "%s". Nothing to queue.', $new_str ),
				array( 'status' => 409 )
			);
		}

		return array(
			'validation'    => $validation,
			'option_name'   => $option,
			'path'          => $path,
			'value'         => $value,
			'prior_value'   => $prior,
			'created_path'  => ! $read['found'],
			// The FULL option, so revert restores the whole structure rather
			// than re-walking a path that may have shifted underneath us.
			'option_snapshot' => $current,
			'autoload'      => self::current_autoload( $option ),
		);
	}

	public static function validate_registered_value( $option, $path, $value, $creating = false ) {
        // These controls have native side effects (server rules/cache). A direct option
        // write must not masquerade as applying the plugin feature.
        if ( 0 === strpos( $option, 'siteground_optimizer_' ) ) {
            return new WP_Error( 'setting_native_apply_required', 'SiteGround optimizer options require a native action adapter. Direct option writes cannot verify server rules or cache changes. Inspect the current native control; no setting was changed.', array( 'status' => 422 ) );
        }
        require_once __DIR__ . '/class-plugin-capability.php';
        $schema = CC_Assistant_Plugin_Capability::registered_schema( $option, $path );
		if ( empty( $schema['type'] ) || ! function_exists( 'rest_validate_value_from_schema' ) ) {
			if ( $creating ) { return new WP_Error( 'setting_schema_required', 'Cannot create an unverified setting key. No explicit registered REST schema for this path is available; use the plugin UI or an adapter for its installed version.', array( 'status' => 422 ) ); }
			return array( 'status' => 'storage_path_observed', 'feature_effect' => 'unknown', 'instruction' => 'Existing storage is verified, but accepted values and feature behaviour are not. Check the installed plugin documentation/UI and verify the actual outcome after approval.' );
		}
		$valid = rest_validate_value_from_schema( $value, $schema, $option . '.' . $path );
		if ( is_wp_error( $valid ) ) { return new WP_Error( 'setting_schema_invalid', $valid->get_error_message(), array( 'status' => 422 ) ); }
		return array( 'status' => 'registered_schema_valid', 'source' => 'wordpress_settings_registry_this_request', 'feature_effect' => 'unknown' );
	}

	private static function current_autoload( $option ) {
		global $wpdb;
		$a = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		return $a ? $a : 'yes';
	}

	/**
	 * Apply a stored plan.
	 */
	public static function apply_plan( $payload ) {
		if ( ! empty( $payload['adapter'] ) ) { require_once __DIR__ . '/class-siteground-adapter.php'; return CC_Assistant_SiteGround_Adapter::apply_plan( $payload ); }
		if ( ! is_array( $payload ) || empty( $payload['option_name'] ) ) {
			return new WP_Error( 'setting_plan_invalid', 'Stored plan has no option_name.' );
		}
		$option = (string) $payload['option_name'];
		$denied = self::denied_option( $option );
		if ( $denied ) {
			return new WP_Error( 'option_protected', sprintf( 'Refusing to write "%s" — %s.', $option, $denied ) );
		}
		$path  = isset( $payload['path'] ) ? (string) $payload['path'] : '';
		foreach ( explode( '.', $path ) as $segment ) {
			if ( CC_Assistant_Stack_Introspect::is_secret_key( $segment ) ) { return new WP_Error( 'option_protected', 'Credential-shaped setting paths cannot be changed through the generic writer.' ); }
		}
		$value = array_key_exists( 'value', $payload ) ? $payload['value'] : '';

		$current = get_option( $option, null );
		if ( null === $current ) {
			return new WP_Error( 'option_not_found', sprintf( 'Option "%s" disappeared before apply.', $option ) );
		}
		if ( '' !== $path && ! is_array( $current ) ) {
			return new WP_Error( 'path_on_scalar', sprintf( 'Option "%s" is no longer an array.', $option ) );
		}

		if ( ! array_key_exists( 'option_snapshot', $payload ) || $current !== $payload['option_snapshot'] ) {
			return new WP_Error( 'setting_conflict', 'Option changed after this plan was drafted. Read it again and draft a new plan.' );
		}
		$valid = self::validate_registered_value( $option, $path, $value, ! empty( $payload['created_path'] ) );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$updated = ( '' === $path ) ? $value : self::write_path( $current, $path, $value );
		$ok      = update_option( $option, $updated );
		// Always read back: update_option filters can change an accepted value.
		{
			// update_option returns false when the stored value is identical.
			$check = self::read_path( get_option( $option, null ), $path );
			$now   = $check['found'] ? $check['value'] : null;
			$want  = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
			$got   = is_bool( $now ) ? ( $now ? 'true' : 'false' ) : (string) $now;
			if ( $got !== $want ) {
				return new WP_Error( 'setting_write_failed', sprintf( 'update_option("%s") did not take effect.', $option ) );
			}
		}
		return true;
	}

	/**
	 * Restore the entire prior option value.
	 */
	public static function revert_plan( $payload ) {
		if ( ! empty( $payload['adapter'] ) ) { require_once __DIR__ . '/class-siteground-adapter.php'; return CC_Assistant_SiteGround_Adapter::apply_plan( $payload, true ); }
		if ( ! is_array( $payload ) || empty( $payload['option_name'] ) ) {
			return new WP_Error( 'setting_plan_invalid', 'Stored plan has no option_name to revert.' );
		}
		if ( ! array_key_exists( 'option_snapshot', $payload ) ) {
			return new WP_Error( 'no_snapshot', 'Plan carries no prior snapshot; refusing to guess the previous value.' );
		}
		$option = (string) $payload['option_name'];
		if ( self::denied_option( $option ) ) { return new WP_Error( 'option_protected', 'This option cannot be restored through the generic settings writer.' ); }
		$current = get_option( $option, null );
		if ( $current === $payload['option_snapshot'] ) { return true; }
		$expected = self::write_path( $payload['option_snapshot'], (string) ( $payload['path'] ?? '' ), $payload['value'] ?? null );
		if ( $current !== $expected ) { return new WP_Error( 'setting_revert_conflict', 'Option changed after apply. Refusing to overwrite newer settings during rollback.' ); }
		update_option( $option, $payload['option_snapshot'] );
		return get_option( $option, null ) === $payload['option_snapshot'] ? true : new WP_Error( 'setting_revert_failed', 'The previous option value was not restored.' );
	}
}
