<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live Elementor widget-schema registry (v0.56) + effective-value guard (v0.80).
 *
 * Ground truth for "what settings does this widget actually accept" — read
 * straight from Elementor's own controls stack on THIS site (so Pro and
 * addon widgets, and version differences, are always reflected). Kills the
 * guess-the-setting-key failure class: a typo'd key in queued settings used
 * to be a silent no-op on the live page; now list/inspect via the
 * widget_schema tool and get blocking validation on the three supported draft routes.
 *
 * v0.80 closes the second silent class: a setting that is STORED but does
 * nothing. Elementor stores only what differs from the control default and
 * gates many controls behind another control's value (`condition`). Two
 * real incidents on one hero: `boxed_width: 900` stored under
 * `content_width: "full"` (never rendered), and an overlay colour whose alpha
 * was halved by the never-set `background_overlay_opacity` default of 0.5.
 * The editor showed both values; neither did what it looked like.
 *
 * So validation now works on EFFECTIVE settings (new over stored over
 * default), replicates Elementor's own visibility rule, and warns when a
 * written key is gated off (`inert_setting`), when a literal is written over
 * a live global token (`global_token_overrides_literal`), and which unset
 * defaults will govern the render next to what was written
 * (`defaults_in_effect`). Containers/sections/columns are covered too — the
 * incidents were on containers, which the v0.56 guard skipped entirely.
 *
 * Compaction, visibility, effective values and validation are pure static
 * methods (arrays in, arrays out) so they are testable without Elementor.
 */
class CC_Assistant_Widget_Schema {

	/** Settings keys that are always legitimate but never in get_controls(). */
	const ALWAYS_ALLOWED = array( '__globals__', '__dynamic__' );

	/** Responsive device suffixes Elementor appends to control names. */
	const DEVICE_SUFFIXES = array( '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' );

	/** UI-only control types that carry no persistable setting. */
	const SKIP_CONTROL_TYPES = array( 'heading', 'raw_html', 'divider', 'notice', 'deprecated_notice', 'alert', 'button' );

	/** Control types whose unset default visibly governs the render. */
	const RENDER_GOVERNING_TYPES = array( 'slider', 'number', 'choose', 'select', 'switcher', 'dimensions' );

	public static function available() {
		return did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' );
	}

	/* ---------------------------------------------------------------------
	 * Live registry access
	 * ------------------------------------------------------------------- */

	/** All widget types registered on THIS site (core + Pro + addons). */
	public static function list_types() {
		if ( ! self::available() ) {
			return new WP_Error( 'elementor_missing', 'Elementor is not active on this site.', array( 'status' => 422 ) );
		}
		$ver       = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '0';
		// Read the active runtime registry; old addon/Pro controls must not survive an update.

		$out = array();
		try {
			$types = \Elementor\Plugin::instance()->widgets_manager->get_widget_types();
			foreach ( $types as $name => $widget ) {
				$out[] = array(
					'name'       => (string) $name,
					'title'      => (string) $widget->get_title(),
					'categories' => array_values( (array) $widget->get_categories() ),
				);
			}
			// v0.80: layout elements too — the inert-setting incidents were on containers.
			$elements = \Elementor\Plugin::instance()->elements_manager->get_element_types();
			foreach ( (array) $elements as $name => $el ) {
				$out[] = array(
					'name'       => (string) $name,
					'title'      => (string) $el->get_title(),
					'categories' => array( 'layout_element' ),
				);
			}
		} catch ( Throwable $e ) {
			return new WP_Error( 'widget_registry_error', 'Could not read the Elementor widget registry: ' . $e->getMessage(), array( 'status' => 500 ) );
		}

		usort(
			$out,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);
		$result = array(
			'elementor_version' => $ver,
			'count'             => count( $out ),
			'widgets'           => $out,
		);
		return $result;
	}

	/**
	 * Compacted control schema for one widget type. Optional $section filters
	 * to one control section (v0.59) — the full button schema is ~60KB; a
	 * targeted section is a few hundred bytes. Section names are listed in
	 * the unfiltered response's `sections` field.
	 */
	public static function schema( $type, $section = '' ) {
		$full = self::schema_full( $type );
		if ( is_wp_error( $full ) || '' === (string) $section ) {
			if ( ! is_wp_error( $full ) ) {
				$full['sections'] = array_values( array_unique( array_filter( array_map(
					function ( $c ) {
						return isset( $c['section'] ) ? $c['section'] : '';
					},
					$full['controls']
				) ) ) );
			}
			return $full;
		}
		$filtered = array();
		foreach ( $full['controls'] as $name => $c ) {
			if ( isset( $c['section'] ) && $c['section'] === $section ) {
				$filtered[ $name ] = $c;
			}
		}
		$full['controls']         = $filtered;
		$full['control_count']    = count( $filtered );
		$full['section_filtered'] = $section;
		return $full;
	}

	private static function schema_full( $type ) {
		if ( ! self::available() ) {
			return new WP_Error( 'elementor_missing', 'Elementor is not active on this site.', array( 'status' => 422 ) );
		}
		$type      = (string) $type;
		$ver       = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '0';
		// Read the active runtime registry; old addon/Pro controls must not survive an update.

		try {
			$plugin = \Elementor\Plugin::instance();
			$widget = $plugin->widgets_manager->get_widget_types( $type );
			$kind   = 'widget';
			if ( ! $widget ) {
				// v0.80: container / section / column live in the elements manager.
				$widget = $plugin->elements_manager->get_element_types( $type );
				$kind   = 'element';
			}
			if ( ! $widget ) {
				$known = self::known_type_names();
				$near  = self::nearest( $type, $known );
				return new WP_Error(
					'unknown_widget_type',
					'No widget or element type "' . $type . '" is registered on this site.' . ( '' !== $near ? ' Did you mean "' . $near . '"?' : '' ),
					array( 'status' => 404 )
				);
			}
			$controls = $widget->get_controls();
			if ( ! is_array( $controls ) ) {
				$controls = array();
			}
			$title = (string) $widget->get_title();
		} catch ( Throwable $e ) {
			return new WP_Error( 'widget_schema_error', 'Could not read controls for "' . $type . '": ' . $e->getMessage(), array( 'status' => 500 ) );
		}

		$result = array(
			'widget_type'       => $type,
			'kind'              => $kind,
			'title'             => $title,
			'elementor_version' => $ver,
			'always_allowed'    => self::ALWAYS_ALLOWED,
			'controls'          => self::compact_controls( $controls ),
			'read_me'           => 'A key absent from a saved element means DEFAULT, not unset. A control with `condition` renders nothing unless the gate holds. Pass post_id + widget_id to see stored vs effective values for one element.',
		);
		$result['control_count'] = count( $result['controls'] );
		return $result;
	}

	private static function known_type_names() {
		try {
			$plugin = \Elementor\Plugin::instance();
			$names  = array_map( 'strval', array_keys( $plugin->widgets_manager->get_widget_types() ) );
			foreach ( (array) $plugin->elements_manager->get_element_types() as $n => $e ) {
				$names[] = (string) $n;
			}
			return $names;
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/* ---------------------------------------------------------------------
	 * Pure: compaction
	 * ------------------------------------------------------------------- */

	/**
	 * Compact Elementor's verbose control definitions into what a composer
	 * needs: type, default, options, units, responsive, global-capable,
	 * section, condition(s). Responsive device variants (_tablet, _mobile, …)
	 * collapse onto their base control as responsive:true.
	 */
	public static function compact_controls( $controls ) {
		$names = array_keys( $controls );
		$out   = array();

		foreach ( $controls as $name => $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$name = (string) $name;
			$type = isset( $c['type'] ) ? (string) $c['type'] : '';
			if ( in_array( $type, self::SKIP_CONTROL_TYPES, true ) ) {
				continue;
			}
			$base = self::responsive_base( $name );
			if ( null !== $base && in_array( $base, $names, true ) ) {
				// Collapse device variants onto their base control.
				if ( isset( $out[ $base ] ) ) {
					$out[ $base ]['responsive'] = true;
				}
				continue;
			}

			$entry = array( 'type' => $type );
			if ( 'repeater' === $type && isset( $c['fields'] ) && is_array( $c['fields'] ) ) {
				$fields = array();
				foreach ( $c['fields'] as $field_name => $field ) { if ( is_array( $field ) ) { $fields[$field['name'] ?? $field_name] = $field; } }
				$entry['fields'] = self::compact_controls( $fields );
			}
			if ( ! empty( $c['multiple'] ) ) { $entry['multiple'] = true; }
			if ( isset( $c['default'] ) && ! self::is_blank_default( $c['default'] ) ) {
				$entry['default'] = $c['default'];
			}
			if ( isset( $c['options'] ) && is_array( $c['options'] ) && ! empty( $c['options'] ) ) {
				$keys             = array_map( 'strval', array_keys( $c['options'] ) );
				$entry['options'] = $keys; // Validation must retain every accepted value, even for long lists.
			}
			if ( isset( $c['size_units'] ) && is_array( $c['size_units'] ) ) {
				$entry['units'] = array_values( array_map( 'strval', $c['size_units'] ) );
			}
			if ( isset( $c['global'] ) ) {
				$entry['global_token'] = true;
			}
			if ( isset( $c['section'] ) && '' !== $c['section'] ) {
				$entry['section'] = (string) $c['section'];
			}
			if ( isset( $c['condition'] ) && is_array( $c['condition'] ) && ! empty( $c['condition'] ) ) {
				$entry['condition'] = $c['condition'];
			}
			if ( isset( $c['conditions'] ) && is_array( $c['conditions'] ) && ! empty( $c['conditions']['terms'] ) ) {
				$entry['conditions'] = $c['conditions'];
			}
			$out[ $name ] = $entry;
		}
		return $out;
	}

	private static function responsive_base( $name ) {
		foreach ( self::DEVICE_SUFFIXES as $suffix ) {
			$len = strlen( $suffix );
			if ( strlen( $name ) > $len && substr( $name, -$len ) === $suffix ) {
				return substr( $name, 0, -$len );
			}
		}
		return null;
	}

	private static function is_blank_default( $default ) {
		return '' === $default || array() === $default || null === $default;
	}

	/* ---------------------------------------------------------------------
	 * Pure: effective values + visibility (mirrors Elementor's own rules)
	 * ------------------------------------------------------------------- */

	/**
	 * What each control will actually be after this write: new over stored
	 * over control default. Keys present in settings but absent from the
	 * schema (responsive variants, __globals__) pass through as-is.
	 * Returns key => array( 'value' => mixed, 'source' => new|stored|default ).
	 */
	public static function effective_values( $compact_controls, $current_settings, $new_settings ) {
		$current = is_array( $current_settings ) ? $current_settings : array();
		$new     = is_array( $new_settings ) ? $new_settings : array();
		$out     = array();
		foreach ( $compact_controls as $name => $c ) {
			if ( isset( $new[ $name ] ) && null !== $new[ $name ] ) {
				$out[ $name ] = array( 'value' => $new[ $name ], 'source' => 'new' );
			} elseif ( isset( $current[ $name ] ) && null !== $current[ $name ] ) {
				$out[ $name ] = array( 'value' => $current[ $name ], 'source' => 'stored' );
			} elseif ( isset( $c['default'] ) ) {
				$out[ $name ] = array( 'value' => $c['default'], 'source' => 'default' );
			}
		}
		foreach ( array( 'stored' => $current, 'new' => $new ) as $src => $set ) {
			foreach ( $set as $k => $v ) {
				if ( ! isset( $out[ $k ] ) && null !== $v && ! isset( $compact_controls[ $k ] ) ) {
					$out[ $k ] = array( 'value' => $v, 'source' => $src );
				}
			}
		}
		return $out;
	}

	/** Flat key => value map from effective_values() output. */
	public static function flatten_effective( $effective ) {
		$flat = array();
		foreach ( $effective as $k => $e ) {
			$flat[ $k ] = $e['value'];
		}
		return $flat;
	}

	/**
	 * Elementor's Controls_Stack::is_control_visible(), reduced to what a
	 * saved element can express: `condition` (key[sub]! => value|array) and
	 * `conditions` (relation + terms). $values must already contain defaults
	 * (use flatten_effective), exactly as Elementor's get_settings() does.
	 */
	public static function control_visible( $control, $values ) {
		if ( ! empty( $control['conditions'] ) && ! self::conditions_check( $control['conditions'], $values ) ) {
			return false;
		}
		if ( empty( $control['condition'] ) || ! is_array( $control['condition'] ) ) {
			return true;
		}
		foreach ( $control['condition'] as $ckey => $cvalue ) {
			if ( ! preg_match( '/([a-z_\-0-9]+)(?:\[([a-z_]+)])?(!?)$/i', (string) $ckey, $m ) ) {
				continue;
			}
			$pure = $m[1];
			$sub  = isset( $m[2] ) ? $m[2] : '';
			$neg  = ! empty( $m[3] );
			if ( ! isset( $values[ $pure ] ) || null === $values[ $pure ] ) {
				return false;
			}
			$instance = $values[ $pure ];
			if ( '' !== $sub && is_array( $instance ) ) {
				if ( ! isset( $instance[ $sub ] ) ) {
					return false;
				}
				$instance = $instance[ $sub ];
			}
			$contains = is_array( $cvalue ) ? in_array( $instance, $cvalue, true ) : ( $instance === $cvalue );
			if ( ( $neg && $contains ) || ( ! $neg && ! $contains ) ) {
				return false;
			}
		}
		return true;
	}

	/** Elementor\Conditions::check() — relation and/or over terms, recursive. */
	public static function conditions_check( $conditions, $values ) {
		if ( ! is_array( $conditions ) || empty( $conditions['terms'] ) || ! is_array( $conditions['terms'] ) ) {
			return true;
		}
		$is_or  = isset( $conditions['relation'] ) && 'or' === $conditions['relation'];
		$result = ! $is_or;
		foreach ( $conditions['terms'] as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			if ( ! empty( $term['terms'] ) ) {
				$ok = self::conditions_check( $term, $values );
			} else {
				if ( ! isset( $term['name'] ) || ! preg_match( '/(\w+)(?:\[(\w+)])?/', (string) $term['name'], $m ) ) {
					continue;
				}
				$value = isset( $values[ $m[1] ] ) ? $values[ $m[1] ] : null;
				if ( ! empty( $m[2] ) ) {
					$value = is_array( $value ) && isset( $value[ $m[2] ] ) ? $value[ $m[2] ] : null;
				}
				$ok = self::compare( $value, isset( $term['value'] ) ? $term['value'] : null, isset( $term['operator'] ) ? $term['operator'] : null );
			}
			if ( $is_or ) {
				if ( $ok ) {
					return true;
				}
			} elseif ( ! $ok ) {
				return false;
			}
		}
		return $result;
	}

	private static function compare( $left, $right, $operator ) {
		switch ( $operator ) {
			case '==':
				return $left == $right; // phpcs:ignore Universal.Operators.StrictComparisons -- mirrors Elementor
			case '!=':
				return $left != $right; // phpcs:ignore Universal.Operators.StrictComparisons -- mirrors Elementor
			case '!==':
				return $left !== $right;
			case 'in':
				return is_array( $right ) && in_array( $left, $right, true );
			case '!in':
				return ! ( is_array( $right ) && in_array( $left, $right, true ) );
			case 'contains':
				return is_array( $left ) && in_array( $right, $left, true );
			case '!contains':
				return ! ( is_array( $left ) && in_array( $right, $left, true ) );
			case '<':
				return $left < $right;
			case '<=':
				return $left <= $right;
			case '>':
				return $left > $right;
			case '>=':
				return $left >= $right;
			default:
				return $left === $right;
		}
	}

	/** Human-readable gate description for a control's `condition`. */
	private static function describe_condition( $control, $values ) {
		$parts = array();
		if ( ! empty( $control['condition'] ) && is_array( $control['condition'] ) ) {
			foreach ( $control['condition'] as $ckey => $cvalue ) {
				if ( ! preg_match( '/([a-z_\-0-9]+)(?:\[([a-z_]+)])?(!?)$/i', (string) $ckey, $m ) ) {
					continue;
				}
				$pure   = $m[1];
				$neg    = ! empty( $m[3] );
				$actual = isset( $values[ $pure ] ) ? $values[ $pure ] : null;
				if ( is_array( $actual ) && isset( $m[2] ) && '' !== $m[2] && isset( $actual[ $m[2] ] ) ) {
					$actual = $actual[ $m[2] ];
				}
				$want    = is_array( $cvalue ) ? implode( '|', array_map( 'strval', $cvalue ) ) : (string) $cvalue;
				$parts[] = sprintf(
					'%s is %s (needs %s%s)',
					$pure,
					null === $actual ? 'unset' : json_encode( $actual, JSON_UNESCAPED_SLASHES ),
					$neg ? 'NOT ' : '',
					'' === $want ? '""' : $want
				);
			}
		}
		if ( ! empty( $control['conditions'] ) ) {
			$parts[] = 'complex conditions ' . json_encode( $control['conditions'], JSON_UNESCAPED_SLASHES );
		}
		return implode( '; ', $parts );
	}

	/* ---------------------------------------------------------------------
	 * Pure: validation
	 * ------------------------------------------------------------------- */

	/**
	 * Warn-only validation of proposed settings against a compacted schema.
	 * Returns array of {code, message} — same shape as the popup coverage
	 * warnings the queue path already merges.
	 *
	 * v0.80: pass $current_settings (the element's saved settings) to enable
	 * the effective-value checks: inert_setting, global_token_overrides_literal,
	 * defaults_in_effect. Without it only the v0.56 key/enum checks run.
	 */
	public static function validate_settings( $compact_controls, $settings, $widget_type, $current_settings = null ) {
		$warnings = array();
		if ( ! is_array( $settings ) ) {
			return $warnings;
		}
		$known = array_keys( $compact_controls );

		$has_current = is_array( $current_settings );
		$effective   = $has_current ? self::effective_values( $compact_controls, $current_settings, $settings ) : array();
		$flat        = $has_current ? self::flatten_effective( $effective ) : array();
		$written_roots = array();

		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, self::ALWAYS_ALLOWED, true ) ) {
				continue;
			}
			$base = self::responsive_base( $key );
			$root = null !== $base ? $base : $key;

			if ( ! in_array( $root, $known, true ) ) {
				$near       = self::nearest( $root, $known );
				$warnings[] = array(
					'code'    => 'unknown_setting_key',
					'message' => sprintf(
						'Setting "%s" is not a control of widget type "%s" — Elementor will silently ignore it.%s',
						$key,
						$widget_type,
						'' !== $near ? ' Did you mean "' . $near . '"?' : ''
					),
				);
				continue;
			}
			$written_roots[ $root ] = $key;

			$control = $compact_controls[ $root ];
			if ( 'repeater' === ( $control['type'] ?? '' ) ) {
				if ( ! is_array( $value ) || array_values( $value ) !== $value || ! isset( $control['fields'] ) ) {
					$warnings[] = array( 'code' => 'invalid_option_value', 'message' => 'Repeater ' . $key . ' requires a list of rows and a live field schema.' );
				} else {
					foreach ( $value as $i => $row ) {
						if ( ! is_array( $row ) ) { $warnings[] = array( 'code' => 'invalid_option_value', 'message' => 'Malformed repeater row: ' . $key ); continue; }
						unset( $row['_id'] ); // Elementor's internal row identifier.
						$prior = array();
						foreach ( (array) ( $current_settings[$key] ?? array() ) as $old_row ) {
							if ( is_array( $old_row ) && ! empty( $value[$i]['_id'] ) && ( $old_row['_id'] ?? '' ) === $value[$i]['_id'] ) { $prior = $old_row; break; }
						}
						$delta = array();
						foreach ( $row as $field => $v ) { if ( ! array_key_exists( $field, $prior ) || $prior[$field] !== $v ) { $delta[$field] = $v; } }
						$warnings = array_merge( $warnings, self::validate_settings( $control['fields'], $delta, $widget_type . '.' . $key . '[' . $i . ']', $row ) );
					}
				}
			}

			if ( null !== $base && empty( $control['responsive'] ) ) {
				$warnings[] = array( 'code' => 'unknown_setting_key', 'message' => 'Control "' . $root . '" has no registered responsive variant "' . $key . '".' );
			}
			if ( isset( $control['options'] ) && is_array( $control['options'] ) && ! is_scalar( $value ) ) {
				$valid_list = is_array( $value ) && ! empty( $control['multiple'] );
				foreach ( (array) $value as $choice ) {
					$valid_list = $valid_list && is_scalar( $choice ) && in_array( (string) $choice, $control['options'], true );
				}
				if ( ! $valid_list ) { $warnings[] = array( 'code' => 'invalid_option_value', 'message' => 'Control "' . $key . '" received an invalid choice or value shape.' ); }
			}

			if ( isset( $control['options'] ) && is_array( $control['options'] ) && is_scalar( $value )
				&& '' !== (string) $value && ! in_array( (string) $value, $control['options'], true ) ) {
				$warnings[] = array(
					'code'    => 'invalid_option_value',
					'message' => sprintf(
						'Setting "%s" = "%s" is not one of the valid choices for widget type "%s": %s.',
						$key,
						(string) $value,
						$widget_type,
						implode( ', ', $control['options'] )
					),
				);
			}

			if ( ! $has_current ) {
				continue;
			}

			// v0.80 inert_setting: stored, shown in the editor, renders nothing.
			if ( ( ! empty( $control['condition'] ) || ! empty( $control['conditions'] ) ) && ! self::control_visible( $control, $flat ) ) {
				$warnings[] = array(
					'code'    => 'inert_setting',
					'message' => sprintf(
						'Setting "%s" will be STORED but has NO EFFECT on widget type "%s": its gate is off — %s. Set the gate in the same change or drop this key. (Elementor shows the value in the editor either way.)',
						$key,
						$widget_type,
						self::describe_condition( $control, $flat )
					),
				);
			}

			// v0.80 global token wins over a literal.
			$globals     = isset( $current_settings['__globals__'] ) && is_array( $current_settings['__globals__'] ) ? $current_settings['__globals__'] : array();
			$new_globals = isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ? $settings['__globals__'] : null;
			$token_live  = isset( $globals[ $root ] ) && '' !== (string) $globals[ $root ];
			$token_kept  = $token_live && ( null === $new_globals || ! array_key_exists( $root, $new_globals ) || '' !== (string) $new_globals[ $root ] );
			if ( $token_kept && is_scalar( $value ) && '' !== (string) $value ) {
				$warnings[] = array(
					'code'    => 'global_token_overrides_literal',
					'message' => sprintf(
						'Setting "%s" = "%s" will be IGNORED: this element binds %s to the Kit global token "%s", and Elementor renders the token, not the literal. Either write __globals__: {"%s": ""} in the same change to detach it, or set __globals__.%s to a different token instead of a literal.',
						$key,
						(string) $value,
						$root,
						(string) $globals[ $root ],
						$root,
						$root
					),
				);
			}
		}

		if ( ! $has_current || empty( $written_roots ) ) {
			return $warnings;
		}

		// v0.80 defaults_in_effect: render-governing controls that nobody set,
		// whose default therefore renders, and that are RELATED to what was
		// written: they gate a written key, are gated by one, or share a gate
		// with one (overlay colour and overlay opacity are both gated by the
		// overlay type — that co-gating is the mammoth incident). Unrelated
		// section-mates are not listed; a min_height write must not recite
		// the whole layout section. One notice per section, capped.
		$related = array();
		foreach ( $written_roots as $root => $key ) {
			$gates = self::gate_keys( $compact_controls[ $root ] );
			foreach ( $compact_controls as $name => $c ) {
				if ( isset( $written_roots[ $name ] ) ) {
					continue;
				}
				$their = self::gate_keys( $c );
				if ( in_array( $name, $gates, true ) || in_array( $root, $their, true ) || ( ! empty( $gates ) && array_intersect( $gates, $their ) ) ) {
					$related[ $name ] = true;
				}
			}
		}
		$by_section = array();
		foreach ( array_keys( $related ) as $name ) {
			$c = $compact_controls[ $name ];
			if ( ! isset( $c['default'] ) || ! self::meaningful_default( $c['default'] ) || ! in_array( $c['type'], self::RENDER_GOVERNING_TYPES, true ) ) {
				continue;
			}
			if ( ! isset( $effective[ $name ] ) || 'default' !== $effective[ $name ]['source'] ) {
				continue;
			}
			if ( ! self::control_visible( $c, $flat ) ) {
				continue;
			}
			$sec = isset( $c['section'] ) ? $c['section'] : '';
			if ( ! isset( $by_section[ $sec ] ) ) {
				$by_section[ $sec ] = array();
			}
			if ( count( $by_section[ $sec ] ) < 6 ) {
				$by_section[ $sec ][] = $name . '=' . json_encode( $c['default'], JSON_UNESCAPED_SLASHES );
			}
		}
		foreach ( $by_section as $sec => $list ) {
			$warnings[] = array(
				'code'    => 'defaults_in_effect',
				'message' => sprintf(
					'FYI: controls tied to this write in section "%s" of widget type "%s" are unset and render at their Elementor defaults — %s. Set them explicitly if the default is not what you want (e.g. background_overlay_opacity=0.5 halves an overlay colour\'s alpha).',
					$sec,
					$widget_type,
					implode( ', ', $list )
				),
			);
		}
		return $warnings;
	}

	/** Control names referenced by a control's condition / conditions. */
	private static function gate_keys( $control ) {
		$keys = array();
		if ( ! empty( $control['condition'] ) && is_array( $control['condition'] ) ) {
			foreach ( array_keys( $control['condition'] ) as $ckey ) {
				if ( preg_match( '/([a-z_\-0-9]+)(?:\[([a-z_]+)])?(!?)$/i', (string) $ckey, $m ) ) {
					$keys[] = $m[1];
				}
			}
		}
		if ( ! empty( $control['conditions'] ) ) {
			$keys = array_merge( $keys, self::conditions_keys( $control['conditions'] ) );
		}
		return array_values( array_unique( $keys ) );
	}

	private static function conditions_keys( $conditions ) {
		$keys = array();
		if ( ! is_array( $conditions ) || empty( $conditions['terms'] ) ) {
			return $keys;
		}
		foreach ( $conditions['terms'] as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			if ( ! empty( $term['terms'] ) ) {
				$keys = array_merge( $keys, self::conditions_keys( $term ) );
			} elseif ( isset( $term['name'] ) && preg_match( '/(\w+)/', (string) $term['name'], $m ) ) {
				$keys[] = $m[1];
			}
		}
		return $keys;
	}

	/**
	 * A default worth telling anyone about. Scalars yes; arrays only when
	 * they carry a value beyond a unit (boxed_width's default is just
	 * {"unit":"px"} — no size — and says nothing about what renders).
	 */
	private static function meaningful_default( $default ) {
		if ( is_array( $default ) ) {
			foreach ( $default as $k => $v ) {
				if ( 'unit' === $k || 'isLinked' === $k ) {
					continue;
				}
				if ( is_array( $v ) ? ! empty( $v ) : ( '' !== (string) $v && null !== $v ) ) {
					return true;
				}
			}
			return false;
		}
		return '' !== (string) $default && null !== $default;
	}

	/** Nearest known name by edit distance (<=3), '' when nothing close. */
	public static function nearest( $needle, $haystack ) {
		$best      = '';
		$best_dist = 4;
		foreach ( $haystack as $candidate ) {
			$dist = levenshtein( strtolower( (string) $needle ), strtolower( (string) $candidate ) );
			if ( $dist < $best_dist ) {
				$best_dist = $dist;
				$best      = (string) $candidate;
			}
		}
		return $best;
	}

	/* ---------------------------------------------------------------------
	 * Queue-path hooks (warn-only, never block, silent when unavailable)
	 * ------------------------------------------------------------------- */

	/** Existing element (widget OR container/section/column) on a post. */
	public static function blocking_error( $warnings ) {
		$blocked = array_values( array_filter( $warnings, static function ( $w ) {
			return in_array( $w['code'] ?? '', array( 'unknown_setting_key', 'invalid_option_value', 'inert_setting', 'global_token_overrides_literal', 'schema_unavailable' ), true );
		} ) );
		return empty( $blocked ) ? null : new WP_Error( 'unverified_elementor_settings', 'Settings were not queued: the live control schema rejects or cannot verify this change. Read widget_schema, fix the plan, then retry.', array( 'status' => 422, 'findings' => $blocked ) );
	}

	public static function validation_warnings_for_post_widget( $post_id, $widget_id, $settings ) {
		if ( ! self::available() || ! is_array( $settings )  ) {
			return array( array( 'code' => 'schema_unavailable', 'message' => 'The active Elementor control schema could not be established. Read widget_schema and correct the target before proposing settings.' ) );
		}
		$node = self::find_element_node( $post_id, (string) $widget_id );
		if ( null === $node ) {
			return array( array( 'code' => 'schema_unavailable', 'message' => 'The active Elementor control schema could not be established. Read widget_schema and correct the target before proposing settings.' ) );
		}
		$schema = self::schema( $node['type'] );
		if ( is_wp_error( $schema ) || empty( $schema['controls'] ) ) {
			return array( array( 'code' => 'schema_unavailable', 'message' => 'The active Elementor control schema could not be established. Read widget_schema and correct the target before proposing settings.' ) );
		}
		return self::validate_settings( $schema['controls'], $settings, $node['type'], $node['settings'] );
	}

	/** New element being added: no stored settings, defaults are the baseline. */
	public static function validation_warnings_for_new_element( $type, $settings ) {
		if ( ! self::available() || ! is_array( $settings )  || '' === (string) $type ) {
			return array( array( 'code' => 'schema_unavailable', 'message' => 'The active Elementor control schema could not be established. Read widget_schema and correct the target before proposing settings.' ) );
		}
		$schema = self::schema( (string) $type );
		if ( is_wp_error( $schema ) || empty( $schema['controls'] ) ) {
			return array( array( 'code' => 'schema_unavailable', 'message' => 'The active Elementor control schema could not be established. Read widget_schema and correct the target before proposing settings.' ) );
		}
		return self::validate_settings( $schema['controls'], $settings, (string) $type, array() );
	}

	/**
	 * v0.80 widget_schema(post_id, widget_id): stored vs effective for one
	 * saved element, with visibility, so "is it set / does it apply" is one
	 * read instead of a guess.
	 */
	public static function effective_for_post_element( $post_id, $widget_id ) {
		if ( ! self::available() ) {
			return new WP_Error( 'elementor_missing', 'Elementor is not active on this site.', array( 'status' => 422 ) );
		}
		$node = self::find_element_node( (int) $post_id, (string) $widget_id );
		if ( null === $node ) {
			return new WP_Error( 'element_not_found', 'No element with id "' . $widget_id . '" in post ' . (int) $post_id . '.', array( 'status' => 404 ) );
		}
		$schema = self::schema( $node['type'] );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}
		$effective = self::effective_values( $schema['controls'], $node['settings'], array() );
		$flat      = self::flatten_effective( $effective );
		$rows      = array();
		$inert     = array();
		foreach ( $effective as $k => $e ) {
			$control = isset( $schema['controls'][ $k ] ) ? $schema['controls'][ $k ] : null;
			$visible = null === $control ? true : self::control_visible( $control, $flat );
			$rows[ $k ] = array(
				'value'   => $e['value'],
				'source'  => $e['source'],
				'applies' => $visible,
			);
			if ( 'stored' === $e['source'] && ! $visible && null !== $control ) {
				$inert[ $k ] = self::describe_condition( $control, $flat );
			}
		}
		return array(
			'post_id'        => (int) $post_id,
			'element_id'     => (string) $widget_id,
			'element_type'   => $node['type'],
			'stored_keys'    => count( array_filter( $rows, function ( $r ) { return 'stored' === $r['source']; } ) ),
			'default_keys'   => count( array_filter( $rows, function ( $r ) { return 'default' === $r['source']; } ) ),
			'inert_stored'   => $inert,
			'effective'      => $rows,
			'read_me'        => 'Values and applicability are derived from the current widget registry. Defaults, global styles, responsive settings, dynamic data and rendering filters can affect the final output; verify the rendered page before claiming the feature works.',
		);
	}

	/** Locate a node by id; returns array(type, settings) or null. */
	private static function find_element_node( $post_id, $widget_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return null;
		}
		return self::walk_for_node( $tree, $widget_id );
	}

	private static function walk_for_node( $nodes, $widget_id ) {
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) && (string) $node['id'] === $widget_id ) {
				$type = isset( $node['widgetType'] ) && '' !== $node['widgetType']
					? (string) $node['widgetType']
					: ( isset( $node['elType'] ) ? (string) $node['elType'] : '' );
				if ( '' === $type ) {
					return null;
				}
				return array(
					'type'     => $type,
					'settings' => isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array(),
				);
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$found = self::walk_for_node( $node['elements'], $widget_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}
}
