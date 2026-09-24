<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mutates Elementor `_elementor_data` trees: add / remove / move widgets and
 * containers. The legacy `walk_and_update` in CC_Assistant_Apply only merges
 * fields into an EXISTING widget's settings — it cannot insert a new node or
 * remove one. This class fills that gap so the plugin can build native
 * Elementor sections (icon-box rows, price-list tables, accordion items)
 * instead of resorting to a custom HTML widget.
 *
 * All mutations operate on a decoded `_elementor_data` PHP array; callers
 * re-encode and persist via `update_post_meta` after the mutation succeeds.
 * No method here writes to the database directly — apply handlers own the
 * write so the snapshot + cache-flush pipeline stays in one place.
 */
class CC_Assistant_Elementor_Builder {

	/**
	 * Generate an Elementor-style 7-char hex id (matches the format Elementor's
	 * editor produces, e.g. "1a7b455"). Returns a fresh id that is NOT already
	 * present in the supplied tree.
	 */
	public static function generate_id( $tree ) {
		$existing = self::collect_ids( $tree );
		for ( $i = 0; $i < 50; $i++ ) {
			$id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
			if ( ! isset( $existing[ $id ] ) ) {
				return $id;
			}
		}
		// Astronomically unlikely fallthrough — fall back to a longer id so we
		// still don't collide.
		return substr( bin2hex( random_bytes( 8 ) ), 0, 12 );
	}

	/**
	 * Recursively gather every `id` field in the tree into a lookup map. Used
	 * by generate_id() to avoid collisions and by find_node() for O(1) lookups.
	 */
	private static function collect_ids( $tree, &$acc = null ) {
		if ( null === $acc ) {
			$acc = array();
		}
		if ( ! is_array( $tree ) ) {
			return $acc;
		}
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) && is_string( $node['id'] ) ) {
				$acc[ $node['id'] ] = true;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::collect_ids( $node['elements'], $acc );
			}
		}
		return $acc;
	}

	/**
	 * Locate a node by id. Returns an array with the node, its parent's
	 * elements array (by reference), and the index inside that array, OR
	 * null if not found.
	 *
	 * @return array|null { node: array, parent_elements: array&, index: int } or null
	 */
	public static function &find_node( array &$tree, $target_id ) {
		$null = null;
		foreach ( $tree as $i => &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) && $node['id'] === $target_id ) {
				$ref = array(
					'node'            => &$node,
					'parent_elements' => &$tree,
					'index'           => $i,
				);
				return $ref;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$found = &self::find_node( $node['elements'], $target_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return $null;
	}

	/**
	 * Strip risky `__globals__` text-color refs from a settings array when the
	 * caller did not also pass an explicit hex for the same color setting.
	 *
	 * Why this exists: new widgets often inherit their `__globals__` payload
	 * from a "sample" widget elsewhere on the page (a pattern Claude uses to
	 * keep theming consistent). If that sample lives on a dark-background
	 * section, its `title_color` / `description_color` __globals__ point at
	 * custom kit globals like `globals/colors?id=500cf27` that resolve to
	 * white-ish swatches by design. Reusing that __globals__ payload on a new
	 * widget that lands on a default white section background = white-on-white
	 * text. The result is invisible content that passes Elementor's UI but
	 * fails WCAG-AA and embarrasses the reviewer.
	 *
	 * Rule for each foreground key in TEXT_FG_KEYS: if `__globals__[key]` is
	 * set AND `settings[key]` is NOT a non-empty string, drop
	 * `__globals__[key]`. The widget then inherits the Elementor kit's default
	 * text-color (intentionally dark / accessible). Callers can opt back into
	 * a specific global by passing BOTH the explicit hex AND the __globals__
	 * entry — explicit beats inherited.
	 *
	 * Background / surface globals (primary_color, secondary_color, etc.) are
	 * NOT stripped — they drive icon backgrounds and container surfaces, and
	 * inheriting those is what keeps brand consistency.
	 *
	 * @param array  &$settings    Widget settings, mutated in place.
	 * @param string $widget_type  Used to skip widgets that have no text fg.
	 * @return void
	 */
	public static function sanitize_unsafe_text_globals( array &$settings, $widget_type ) {
		static $text_widget_types = array(
			'heading'          => true,
			'text-editor'      => true,
			'icon-box'         => true,
			'icon-list'        => true,
			'price-list'       => true,
			'nested-accordion' => true,
			'accordion'        => true,
			'toggle'           => true,
		);
		$wt = (string) $widget_type;
		if ( '' !== $wt && ! isset( $text_widget_types[ $wt ] ) ) {
			return;
		}
		if ( empty( $settings['__globals__'] ) || ! is_array( $settings['__globals__'] ) ) {
			return;
		}
		static $text_fg_keys = array(
			'title_color',
			'description_color',
			'text_color',
			'heading_color',
			'_heading_color',
			'item_title_color',
			'item_text_color',
			'price_text_color',
			'icon_color',
			'hover_title_color',
			'hover_description_color',
		);
		foreach ( $text_fg_keys as $k ) {
			$has_global   = isset( $settings['__globals__'][ $k ] ) && '' !== (string) $settings['__globals__'][ $k ];
			$has_explicit = isset( $settings[ $k ] ) && is_string( $settings[ $k ] ) && '' !== trim( $settings[ $k ] );
			if ( $has_global && ! $has_explicit ) {
				unset( $settings['__globals__'][ $k ] );
			}
		}
		if ( empty( $settings['__globals__'] ) ) {
			unset( $settings['__globals__'] );
		}
	}

	/**
	 * Pre-process a children spec passed to add_container so the resulting
	 * tree carries the brand's design defaults. Two transforms:
	 *
	 *  - Adjacent heading + text-editor children at the TOP of the spec get
	 *    wrapped in a fresh inner container at `body_max_width` with
	 *    `flex_align_items: center` so the heading + intro paragraph render
	 *    centered with a comfortable reading-width line length. This is the
	 *    pattern Elementor's editor produces when an operator drags a
	 *    container into the outer box and constrains it to a custom width;
	 *    we reproduce it programmatically so the model doesn't have to
	 *    instrument each call.
	 *
	 *  - icon-box children without explicit icon_size / view / _padding /
	 *    border_radius pick up the brand profile values, so a fresh card
	 *    row matches the existing icon-box pattern on the page instead of
	 *    rendering with Elementor's small default icon.
	 *
	 * Pure transform: never raises, returns the (possibly modified) spec.
	 * Safe to call on a spec that already has the wrap or icon defaults —
	 * the wrap detector skips when children[0] is already a container, and
	 * icon defaults are applied only to keys that aren't already set.
	 */
	public static function apply_brand_defaults_to_children( array $children, array $outer_settings, $post_id = null ) {
		if ( empty( $children ) ) {
			return $children;
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-brand-profile.php';
		$brand = CC_Assistant_Brand_Profile::get();

		// Page-style sampling. When we have a post id, pull the heading and
		// text-editor samples off the live page so new widgets clone the
		// alignment / typography conventions the operator already uses on
		// this page (right page-centering property, right font weight, right
		// color globals). Falls back to brand profile when no sample exists.
		$style = null;
		if ( null !== $post_id && (int) $post_id > 0 ) {
			$style = self::get_style_context( (int) $post_id );
			if ( is_wp_error( $style ) ) {
				$style = null;
			}
		}
		$heading_sample = ( is_array( $style ) && ! empty( $style['heading_sample']['settings'] ) ) ? $style['heading_sample']['settings'] : null;
		$intro_sample   = ( is_array( $style ) && ! empty( $style['text_editor_sample']['settings'] ) ) ? $style['text_editor_sample']['settings'] : null;
		$children       = self::apply_heading_sample_to_children( $children, $heading_sample, $intro_sample );

		// (a) Wrap leading heading + text-editor in a centered max-width
		// container. We only do this when the OUTER container's content_width
		// is "boxed" (or absent — default boxed). For an inner column or a
		// custom-width container the caller has already decided the layout,
		// so we leave the spec alone.
		$outer_content_width = isset( $outer_settings['content_width'] ) ? (string) $outer_settings['content_width'] : 'boxed';
		if ( 'boxed' === $outer_content_width ) {
			$lead_heading = isset( $children[0] ) && self::child_is_widget_type( $children[0], 'heading' );
			$lead_text    = isset( $children[1] ) && self::child_is_widget_type( $children[1], 'text-editor' );
			// Also accept just a leading heading (no intro paragraph).
			if ( $lead_heading ) {
				$wrap_count = $lead_text ? 2 : 1;
				// Skip if the leading children are already inside an inner
				// container in the spec — operator pinned the layout already.
				$wrap_children = array_slice( $children, 0, $wrap_count );
				$tail          = array_slice( $children, $wrap_count );
				$wrapper = array(
					'type'     => 'container',
					'settings' => array(
						'content_width'    => 'full',
						'width'            => array( 'unit' => 'px', 'size' => $brand['body_max_width'], 'sizes' => array() ),
						'width_mobile'    => array( 'unit' => '%',  'size' => 100, 'sizes' => array() ),
						'flex_direction'   => 'column',
						'flex_align_items' => 'center',
						// Center the inner container itself within the parent.
						// Elementor flex containers honor `_flex_align_self` for
						// flex-item cross-axis alignment; `_element_self_align`
						// looks plausible but is silently ignored on flex
						// parents — that mismatch is what landed the v0.12.0
						// hormone-page section with the heading visually
						// stuck to the left edge of the section.
						'_flex_align_self' => 'center',
						'_margin'          => array( 'unit' => 'px', 'top' => '0', 'right' => 'auto', 'bottom' => '0', 'left' => 'auto', 'isLinked' => false ),
					),
					'children' => $wrap_children,
				);
				$children = array_merge( array( $wrapper ), $tail );
			}
		}

		// (b) Apply icon-box defaults recursively. Walk the whole spec; for
		// every widget child of type icon-box, fill missing brand defaults.
		$children = self::apply_iconbox_defaults_recursive( $children, $brand );

		return $children;
	}

	/**
	 * Clone alignment / typography properties from the page's existing
	 * heading and text-editor samples onto matching children in the new
	 * spec. Only fills slots the caller has not pinned — the explicit-wins
	 * contract we use everywhere else in the builder. The keys we
	 * propagate are the ones that determine where on the page the widget
	 * lands (`_flex_align_self`, `_element_width`, `_element_custom_width`)
	 * and how its typography reads (font_family, font_weight, font_size,
	 * color globals). Settings that vary per-widget (title text, body
	 * editor HTML) are deliberately ignored.
	 *
	 * @param array      $children       The spec tree being assembled.
	 * @param array|null $heading_sample Settings hash from an existing H2
	 *                                   on the page; null to skip.
	 * @param array|null $intro_sample   Settings hash from an existing
	 *                                   text-editor on the page; null to
	 *                                   skip.
	 * @return array Mutated children spec (operates by value).
	 */
	private static function apply_heading_sample_to_children( array $children, $heading_sample, $intro_sample ) {
		if ( empty( $heading_sample ) && empty( $intro_sample ) ) {
			return $children;
		}
		$heading_keys = array(
			'_flex_align_self',
			'_element_width',
			'_element_custom_width',
			'_element_self_align',
			'align',
			'typography_font_family',
			'typography_font_size',
			'typography_font_weight',
			'typography_letter_spacing',
			'title_typography_font_family',
			'title_typography_font_size',
			'title_typography_font_weight',
		);
		$intro_keys = array(
			'_flex_align_self',
			'_element_width',
			'_element_custom_width',
			'align',
			'typography_font_family',
			'typography_font_size',
			'typography_font_weight',
		);
		$globals_to_carry = array( 'title_color', 'text_color' );

		$apply = function ( &$nodes ) use ( &$apply, $heading_sample, $intro_sample, $heading_keys, $intro_keys, $globals_to_carry ) {
			foreach ( $nodes as &$c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$type = isset( $c['type'] ) ? (string) $c['type'] : '';
				$wt   = isset( $c['widgetType'] ) ? (string) $c['widgetType'] : '';
				if ( 'widget' === $type ) {
					if ( ! isset( $c['settings'] ) || ! is_array( $c['settings'] ) ) {
						$c['settings'] = array();
					}
					if ( 'heading' === $wt && ! empty( $heading_sample ) ) {
						foreach ( $heading_keys as $k ) {
							if ( ! array_key_exists( $k, $c['settings'] ) && isset( $heading_sample[ $k ] ) ) {
								$c['settings'][ $k ] = $heading_sample[ $k ];
							}
						}
						if ( ! empty( $heading_sample['__globals__'] ) && is_array( $heading_sample['__globals__'] ) ) {
							foreach ( $globals_to_carry as $gk ) {
								if ( isset( $heading_sample['__globals__'][ $gk ] ) && empty( $c['settings'][ $gk ] ) ) {
									if ( ! isset( $c['settings']['__globals__'] ) || ! is_array( $c['settings']['__globals__'] ) ) {
										$c['settings']['__globals__'] = array();
									}
									if ( ! isset( $c['settings']['__globals__'][ $gk ] ) ) {
										$c['settings']['__globals__'][ $gk ] = $heading_sample['__globals__'][ $gk ];
									}
								}
							}
						}
					}
					if ( 'text-editor' === $wt && ! empty( $intro_sample ) ) {
						foreach ( $intro_keys as $k ) {
							if ( ! array_key_exists( $k, $c['settings'] ) && isset( $intro_sample[ $k ] ) ) {
								$c['settings'][ $k ] = $intro_sample[ $k ];
							}
						}
					}
				}
				if ( ! empty( $c['children'] ) && is_array( $c['children'] ) ) {
					$apply( $c['children'] );
				}
			}
			unset( $c );
		};
		$apply( $children );
		return $children;
	}

	/**
	 * True when $spec is a widget-spec for the named widgetType.
	 */
	private static function child_is_widget_type( $spec, $widget_type ) {
		return is_array( $spec )
			&& isset( $spec['type'], $spec['widgetType'] )
			&& 'widget' === $spec['type']
			&& $spec['widgetType'] === $widget_type;
	}

	/**
	 * Walk a children spec tree and fill brand defaults on icon-box widgets.
	 * Defaults are applied with array_key_exists checks so a caller that
	 * pinned `icon_size: 20` keeps their value.
	 */
	private static function apply_iconbox_defaults_recursive( array $children, array $brand ) {
		foreach ( $children as &$child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( isset( $child['type'], $child['widgetType'] ) && 'widget' === $child['type'] && 'icon-box' === $child['widgetType'] ) {
				if ( ! isset( $child['settings'] ) || ! is_array( $child['settings'] ) ) {
					$child['settings'] = array();
				}
				$s = &$child['settings'];
				if ( ! array_key_exists( 'icon_size', $s ) ) {
					$s['icon_size'] = array( 'unit' => 'px', 'size' => $brand['icon_size'], 'sizes' => array() );
				}
				if ( ! array_key_exists( 'view', $s ) ) {
					$s['view'] = $brand['icon_view'];
				}
				if ( ! array_key_exists( '_padding', $s ) ) {
					$s['_padding'] = array(
						'unit' => 'px',
						'top' => (string) $brand['card_padding'],
						'right' => (string) $brand['card_padding'],
						'bottom' => (string) $brand['card_padding'],
						'left' => (string) $brand['card_padding'],
						'isLinked' => true,
					);
				}
				unset( $s );
			}
			// Also apply card border-radius to container children that look
			// like card wrappers (have width <= 50% and background_color).
			if ( isset( $child['type'] ) && 'container' === $child['type'] ) {
				$cs = isset( $child['settings'] ) && is_array( $child['settings'] ) ? $child['settings'] : array();
				$has_bg    = isset( $cs['background_color'] ) || isset( $cs['background_background'] );
				$has_width = isset( $cs['width'] );
				if ( $has_bg && $has_width && ! array_key_exists( 'border_radius', $cs ) ) {
					$child['settings']['border_radius'] = array(
						'unit' => 'px',
						'top' => (string) $brand['card_border_radius'],
						'right' => (string) $brand['card_border_radius'],
						'bottom' => (string) $brand['card_border_radius'],
						'left' => (string) $brand['card_border_radius'],
						'isLinked' => true,
					);
				}
			}
			if ( ! empty( $child['children'] ) && is_array( $child['children'] ) ) {
				$child['children'] = self::apply_iconbox_defaults_recursive( $child['children'], $brand );
			}
		}
		unset( $child );
		return $children;
	}

	/**
	 * Add a widget into a container's elements array.
	 *
	 * @param array  &$tree         The full _elementor_data tree (mutated).
	 * @param string $parent_id     ID of the container to insert into.
	 * @param string $widget_type   widgetType, e.g. "heading", "icon-box".
	 * @param array  $settings      Widget settings object.
	 * @param int|null $position    0-based insert index; null appends at end.
	 *
	 * @return string|WP_Error New widget id on success, WP_Error otherwise.
	 */
	public static function add_widget( array &$tree, $parent_id, $widget_type, array $settings, $position = null ) {
		if ( '' === (string) $widget_type ) {
			return new WP_Error( 'invalid_type', 'widget_type is required.' );
		}
		$located = &self::find_node( $tree, $parent_id );
		if ( null === $located ) {
			return new WP_Error( 'parent_not_found', sprintf( 'Parent container id %s not found in tree.', $parent_id ) );
		}
		$parent = &$located['node'];
		if ( ! self::is_container_node( $parent ) ) {
			return new WP_Error( 'parent_not_container', sprintf( 'Node id %s is a %s widget, not a container. Widgets must be added to a section, container, or column.', $parent_id, isset( $parent['widgetType'] ) ? $parent['widgetType'] : 'unknown' ) );
		}
		if ( ! isset( $parent['elements'] ) || ! is_array( $parent['elements'] ) ) {
			$parent['elements'] = array();
		}

		self::sanitize_unsafe_text_globals( $settings, (string) $widget_type );

		$new_id = self::generate_id( $tree );
		$new_node = array(
			'id'         => $new_id,
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => (string) $widget_type,
		);

		$count = count( $parent['elements'] );
		if ( null === $position || $position >= $count ) {
			$parent['elements'][] = $new_node;
		} elseif ( $position <= 0 ) {
			array_unshift( $parent['elements'], $new_node );
		} else {
			array_splice( $parent['elements'], (int) $position, 0, array( $new_node ) );
		}
		return $new_id;
	}

	/**
	 * Add a container (section / inner container / column) with optional
	 * pre-built children. Children should be a list of `{type: "widget"|"container",
	 * widgetType?: ..., settings: {...}, children?: [...]}` so we can nest one
	 * level deep without forcing the caller to make N sequential add_widget calls.
	 *
	 * @return string|WP_Error New container id on success, WP_Error otherwise.
	 */
	public static function add_container( array &$tree, $parent_id, array $settings = array(), $position = null, array $children = array(), $el_type = 'container', $post_id = null ) {
		if ( ! in_array( $el_type, array( 'section', 'container', 'column' ), true ) ) {
			return new WP_Error( 'invalid_el_type', 'el_type must be section, container, or column.' );
		}
		// Apply brand-aware transforms BEFORE finding the parent so failures
		// here surface before we touch the tree. Two things happen:
		//   (a) adjacent heading + text-editor children at the top of the
		//       container get wrapped in a centered max-width inner
		//       container — fixes the v0.12.0 "headings render full-width
		//       left-aligned" bug;
		//   (b) icon-box children inherit brand defaults (icon_size, view,
		//       card padding) when not specified.
		//   (c) when post_id is known, heading + text-editor children clone
		//       alignment / typography props off an existing widget on the
		//       same page so they match the page's actual style — not a
		//       hardcoded default.
		$children = self::apply_brand_defaults_to_children( $children, $settings, $post_id );
		self::maybe_default_grid_rows( $settings );

		$located = &self::find_node( $tree, $parent_id );
		if ( null === $located && '' !== $parent_id ) {
			return new WP_Error( 'parent_not_found', sprintf( 'Parent id %s not found.', $parent_id ) );
		}
		// Empty parent_id means add at root level.
		if ( '' === $parent_id ) {
			$parent_elements_ref = &$tree;
		} else {
			$parent = &$located['node'];
			if ( ! self::is_container_node( $parent ) ) {
				return new WP_Error( 'parent_not_container', sprintf( 'Cannot add a container inside a %s widget.', isset( $parent['widgetType'] ) ? $parent['widgetType'] : 'unknown' ) );
			}
			if ( ! isset( $parent['elements'] ) || ! is_array( $parent['elements'] ) ) {
				$parent['elements'] = array();
			}
			$parent_elements_ref = &$parent['elements'];
		}

		$new_id = self::generate_id( $tree );
		$new_node = array(
			'id'       => $new_id,
			'elType'   => $el_type,
			'settings' => $settings,
			'elements' => array(),
		);

		// Resolve children. We expand pre-built child specs into actual nodes
		// using fresh ids so the entire subtree comes in collision-free.
		foreach ( $children as $child ) {
			$built = self::build_child_node( $child, $tree );
			if ( is_wp_error( $built ) ) {
				return $built;
			}
			$new_node['elements'][] = $built;
		}

		$count = count( $parent_elements_ref );
		if ( null === $position || $position >= $count ) {
			$parent_elements_ref[] = $new_node;
		} elseif ( $position <= 0 ) {
			array_unshift( $parent_elements_ref, $new_node );
		} else {
			array_splice( $parent_elements_ref, (int) $position, 0, array( $new_node ) );
		}
		return $new_id;
	}

	/**
	 * Recursively build a child node from a spec: {type, widgetType?, settings, children?}.
	 * Used by add_container() to instantiate pre-built subtrees with fresh ids.
	 */
	private static function build_child_node( $spec, array &$tree ) {
		if ( ! is_array( $spec ) || empty( $spec['type'] ) ) {
			return new WP_Error( 'invalid_child_spec', 'Each child needs a type field (widget|container|column|section).' );
		}
		$type     = (string) $spec['type'];
		$settings = isset( $spec['settings'] ) && is_array( $spec['settings'] ) ? $spec['settings'] : array();
		$new_id   = self::generate_id( $tree );

		if ( 'widget' === $type ) {
			if ( empty( $spec['widgetType'] ) ) {
				return new WP_Error( 'missing_widget_type', 'Child widget spec needs widgetType.' );
			}
			self::sanitize_unsafe_text_globals( $settings, (string) $spec['widgetType'] );
			return array(
				'id'         => $new_id,
				'elType'     => 'widget',
				'settings'   => $settings,
				'elements'   => array(),
				'widgetType' => (string) $spec['widgetType'],
			);
		}
		// container, column, or section
		self::maybe_default_grid_rows( $settings );
		$node = array(
			'id'       => $new_id,
			'elType'   => $type,
			'settings' => $settings,
			'elements' => array(),
		);
		if ( ! empty( $spec['children'] ) && is_array( $spec['children'] ) ) {
			foreach ( $spec['children'] as $sub ) {
				$built = self::build_child_node( $sub, $tree );
				if ( is_wp_error( $built ) ) {
					return $built;
				}
				$node['elements'][] = $built;
			}
		}
		return $node;
	}

	/**
	 * Validate a children array against the child-spec shape that
	 * build_child_node() will enforce at apply time — WITHOUT building
	 * anything. Queue paths call this so a malformed spec is rejected at
	 * queue time (400 to the model, which can self-correct) instead of
	 * failing in the operator's inbox after approval, burning the review.
	 * Mirrors build_child_node exactly: same allowed types, same
	 * widgetType requirement, same recursion.
	 *
	 * @param array  $children Child specs as passed to add_container/rebuild_section.
	 * @param string $path     Internal: position breadcrumb for error messages.
	 * @return true|WP_Error
	 */
	public static function validate_child_specs( array $children, $path = 'children' ) {
		$allowed = array( 'widget', 'container', 'column', 'section' );
		foreach ( array_values( $children ) as $i => $spec ) {
			$where = $path . '[' . $i . ']';
			if ( ! is_array( $spec ) || empty( $spec['type'] ) ) {
				$hint = is_array( $spec ) && isset( $spec['elType'] )
					? ' This looks like raw Elementor JSON (elType/elements) — child specs use {type, widgetType?, settings, children?} instead.'
					: '';
				return new WP_Error( 'invalid_child_spec', sprintf( '%s needs a type field (widget|container|column|section).%s', $where, $hint ), array( 'status' => 400 ) );
			}
			$type = (string) $spec['type'];
			if ( ! in_array( $type, $allowed, true ) ) {
				return new WP_Error( 'invalid_child_spec', sprintf( '%s has unknown type "%s" (expected widget|container|column|section).', $where, $type ), array( 'status' => 400 ) );
			}
			if ( 'widget' === $type && empty( $spec['widgetType'] ) ) {
				return new WP_Error( 'invalid_child_spec', sprintf( '%s is a widget but has no widgetType.', $where ), array( 'status' => 400 ) );
			}
			if ( 'widget' !== $type && ! empty( $spec['children'] ) ) {
				if ( ! is_array( $spec['children'] ) ) {
					return new WP_Error( 'invalid_child_spec', sprintf( '%s children must be an array.', $where ), array( 'status' => 400 ) );
				}
				$sub = self::validate_child_specs( $spec['children'], $where . '.children' );
				if ( is_wp_error( $sub ) ) {
					return $sub;
				}
			}
		}
		return true;
	}

	/**
	 * v0.44: an Elementor grid container with columns set but NO rows defaults
	 * to 2 rows in the editor — so a single-row grid (children <= columns)
	 * reserves an empty second row = visible dead space (the exact bug the
	 * homepage hit). When the spec didn't pin rows, set them to auto so the
	 * grid sizes to its content. Mirrors what build_service_page already does.
	 */
	private static function maybe_default_grid_rows( array &$settings ) {
		if ( isset( $settings['container_type'] ) && 'grid' === $settings['container_type']
			&& isset( $settings['grid_columns_grid'] )
			&& ! isset( $settings['grid_rows_grid'] ) ) {
			$settings['grid_rows_grid'] = array( 'unit' => 'custom', 'size' => 'auto', 'sizes' => array() );
		}
	}

	/**
	 * Remove a node (widget or container) from the tree by id. Returns a copy
	 * of the removed node so the apply handler can stash it in pending.current_value
	 * for rollback.
	 *
	 * @return array|WP_Error The removed node, or WP_Error if not found.
	 */
	public static function remove_node( array &$tree, $target_id ) {
		$located = &self::find_node( $tree, $target_id );
		if ( null === $located ) {
			return new WP_Error( 'not_found', sprintf( 'Node id %s not found.', $target_id ) );
		}
		$removed = $located['node']; // copy
		$parent_elements = &$located['parent_elements'];
		array_splice( $parent_elements, $located['index'], 1 );
		// Reindex to keep PHP array contiguous (Elementor's JSON expects a list).
		$parent_elements = array_values( $parent_elements );
		return $removed;
	}

	/**
	 * Move a node to a different parent or position. Returns the new index
	 * inside the destination parent.
	 *
	 * @return int|WP_Error
	 */
	public static function move_node( array &$tree, $target_id, $new_parent_id, $new_position = null ) {
		// First, find the destination parent to confirm it exists + is a
		// container BEFORE we splice anything out — otherwise we leak nodes.
		$dest_located = &self::find_node( $tree, $new_parent_id );
		if ( null === $dest_located ) {
			return new WP_Error( 'dest_not_found', sprintf( 'Destination parent id %s not found.', $new_parent_id ) );
		}
		if ( ! self::is_container_node( $dest_located['node'] ) ) {
			return new WP_Error( 'dest_not_container', 'Destination must be a container/section/column.' );
		}
		// Reject obvious cycle: moving a node inside its own descendant.
		if ( $target_id === $new_parent_id ) {
			return new WP_Error( 'cycle', 'Cannot move a node into itself.' );
		}
		if ( self::is_descendant( $dest_located['node'], $target_id ) ) {
			return new WP_Error( 'cycle', 'Cannot move a node into one of its descendants.' );
		}

		$removed = self::remove_node( $tree, $target_id );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}
		// Re-resolve destination AFTER the remove, because array_values reindexed
		// upstream and our reference may now be stale.
		$dest_located = &self::find_node( $tree, $new_parent_id );
		if ( null === $dest_located ) {
			return new WP_Error( 'dest_lost', 'Destination disappeared during move (likely it was a descendant of the source).' );
		}
		$dest_parent = &$dest_located['node'];
		if ( ! isset( $dest_parent['elements'] ) || ! is_array( $dest_parent['elements'] ) ) {
			$dest_parent['elements'] = array();
		}
		$count = count( $dest_parent['elements'] );
		if ( null === $new_position || $new_position >= $count ) {
			$dest_parent['elements'][] = $removed;
			return $count;
		}
		if ( $new_position <= 0 ) {
			array_unshift( $dest_parent['elements'], $removed );
			return 0;
		}
		array_splice( $dest_parent['elements'], (int) $new_position, 0, array( $removed ) );
		return (int) $new_position;
	}

	/**
	 * Add an item to a nested-accordion widget's items[] array. This is the
	 * surgical fix for the prior plugin limitation that prevented adding FAQ
	 * items to existing accordions — Elementor stores them inside the widget's
	 * `settings.items` list, which is not reachable via array_merge on settings
	 * because each item is a distinct object with its own _id.
	 *
	 * @return string|WP_Error The new item _id on success.
	 */
	public static function add_accordion_item( array &$tree, $accordion_widget_id, $title, $content_html, $position = null ) {
		$located = &self::find_node( $tree, $accordion_widget_id );
		if ( null === $located ) {
			return new WP_Error( 'not_found', 'Accordion widget id not found.' );
		}
		$widget = &$located['node'];
		$type = isset( $widget['widgetType'] ) ? $widget['widgetType'] : '';
		if ( 'nested-accordion' !== $type ) {
			return new WP_Error( 'not_accordion', sprintf( 'Widget %s is %s, not nested-accordion.', $accordion_widget_id, $type ?: 'unknown' ) );
		}

		if ( ! isset( $widget['settings'] ) || ! is_array( $widget['settings'] ) ) {
			$widget['settings'] = array();
		}
		if ( ! isset( $widget['settings']['items'] ) || ! is_array( $widget['settings']['items'] ) ) {
			$widget['settings']['items'] = array();
		}

		$new_item_id = self::generate_id( $tree );
		$new_item = array(
			'item_title' => (string) $title,
			'_id'        => $new_item_id,
		);

		// Now create the inner container + text-editor widget that holds the answer.
		// Nested-accordion stores the answer body as the widget's own
		// elements[][index] container with a text-editor inside.
		$inner_container_id = self::generate_id( $tree );
		$inner_widget_id    = self::generate_id( $tree );
		$inner_container = array(
			'id'       => $inner_container_id,
			'elType'   => 'container',
			'settings' => array(
				'_title'       => 'item #' . ( count( $widget['settings']['items'] ) + 1 ),
				'content_width' => 'full',
			),
			'elements' => array(
				array(
					'id'         => $inner_widget_id,
					'elType'     => 'widget',
					'settings'   => array( 'editor' => (string) $content_html ),
					'elements'   => array(),
					'widgetType' => 'text-editor',
				),
			),
			'isInner'  => true,
			'isLocked' => true,
		);

		if ( ! isset( $widget['elements'] ) || ! is_array( $widget['elements'] ) ) {
			$widget['elements'] = array();
		}

		$item_count = count( $widget['settings']['items'] );
		if ( null === $position || $position >= $item_count ) {
			$widget['settings']['items'][] = $new_item;
			$widget['elements'][]          = $inner_container;
		} elseif ( $position <= 0 ) {
			array_unshift( $widget['settings']['items'], $new_item );
			array_unshift( $widget['elements'], $inner_container );
		} else {
			array_splice( $widget['settings']['items'], (int) $position, 0, array( $new_item ) );
			array_splice( $widget['elements'], (int) $position, 0, array( $inner_container ) );
		}
		return $new_item_id;
	}

	/**
	 * Remove an item from a nested-accordion by item _id. Symmetrically removes
	 * the inner container that holds the answer body.
	 */
	public static function remove_accordion_item( array &$tree, $accordion_widget_id, $item_id ) {
		$located = &self::find_node( $tree, $accordion_widget_id );
		if ( null === $located ) {
			return new WP_Error( 'not_found', 'Accordion widget not found.' );
		}
		$widget = &$located['node'];
		if ( empty( $widget['settings']['items'] ) || ! is_array( $widget['settings']['items'] ) ) {
			return new WP_Error( 'no_items', 'Accordion has no items.' );
		}
		$target_index = -1;
		foreach ( $widget['settings']['items'] as $i => $it ) {
			if ( isset( $it['_id'] ) && $it['_id'] === $item_id ) {
				$target_index = $i;
				break;
			}
		}
		if ( $target_index < 0 ) {
			return new WP_Error( 'item_not_found', sprintf( 'Item id %s not found in accordion.', $item_id ) );
		}
		$removed_item = $widget['settings']['items'][ $target_index ];
		array_splice( $widget['settings']['items'], $target_index, 1 );
		$widget['settings']['items'] = array_values( $widget['settings']['items'] );

		// Also splice the matching inner container if it exists at the same index.
		if ( isset( $widget['elements'][ $target_index ] ) ) {
			$removed_container = $widget['elements'][ $target_index ];
			array_splice( $widget['elements'], $target_index, 1 );
			$widget['elements'] = array_values( $widget['elements'] );
			$removed_item['_inner_container'] = $removed_container;
		}
		return $removed_item;
	}

	/**
	 * Container test: section / container / column nodes accept children. Widgets
	 * mostly don't (the few that nest, like nested-accordion, are handled by
	 * their own helpers above).
	 */
	public static function is_container_node( $node ) {
		if ( ! is_array( $node ) || ! isset( $node['elType'] ) ) {
			return false;
		}
		return in_array( $node['elType'], array( 'section', 'container', 'column' ), true );
	}

	/**
	 * Returns true if $target_id appears anywhere in the subtree rooted at $node.
	 * Used by move_node() to reject cycles.
	 */
	private static function is_descendant( $node, $target_id ) {
		if ( ! is_array( $node ) || empty( $node['elements'] ) ) {
			return false;
		}
		foreach ( $node['elements'] as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( isset( $child['id'] ) && $child['id'] === $target_id ) {
				return true;
			}
			if ( self::is_descendant( $child, $target_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Load + decode `_elementor_data` for a post into a PHP array. Returns a
	 * WP_Error on missing or unparseable data so callers don't have to repeat
	 * the same guards everywhere.
	 *
	 * Pass $allow_empty=true when the caller is appending to the tree (container
	 * add, widget add into a root, etc.) — a brand-new page created via
	 * draft_create_post has no _elementor_data postmeta yet, and refusing to
	 * load would block the first widget from ever being added. UPDATE/REMOVE
	 * paths keep the default $allow_empty=false because they need an existing
	 * tree to mutate.
	 */
	public static function load_tree( $post_id, $allow_empty = false ) {
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			if ( $allow_empty ) {
				return array();
			}
			return new WP_Error( 'no_elementor_data', 'Post has no Elementor data.' );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_elementor_data', 'Could not parse _elementor_data JSON.' );
		}
		return $data;
	}

	/**
	 * Persist a mutated tree back to `_elementor_data`. Mirrors the encode-or-
	 * abort guard from CC_Assistant_Apply::apply_elementor_widget so a non-UTF-8
	 * byte doesn't silently blank a page's content.
	 */
	public static function save_tree( $post_id, array $tree, $restoring = false ) {
		if ( ! $restoring ) {
			require_once __DIR__ . '/class-elementor-validation.php';
			$old = self::load_tree( $post_id, true );
			if ( is_wp_error( $old ) ) { return $old; }
			$valid = CC_Assistant_Elementor_Validation::validate_tree( $old, $tree );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}
		// Validate before persisting. validate_tree() catches the three failure
		// modes that have actually corrupted posts in the past: duplicate ids
		// (Elementor silently drops one and the page renders wrong), missing
		// elType (the renderer chokes and erases content), and malformed nodes.
		// Refuse-and-roll-back is safer than write-then-pray, so every apply
		// handler that ends in save_tree() picks up this guard for free.
		$validation = self::validate_tree( $tree );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$json = wp_json_encode( $tree );
		if ( false === $json ) {
			return new WP_Error( 'encode_failed', 'Could not re-encode Elementor data — aborting to avoid corrupting the post.' );
		}
		update_post_meta( (int) $post_id, '_elementor_data', wp_slash( $json ) );
		if ( get_post_meta( (int) $post_id, '_elementor_data', true ) !== $json ) { return new WP_Error( 'elementor_write_failed', 'Elementor data failed the saved-value readback check.' ); }
		// First-write graduation: a page can have _elementor_data without
		// _elementor_edit_mode=builder if it was created via draft_create_post
		// and never opened in the Elementor editor. In that state Elementor
		// renders the classic post_content body instead of the saved tree, so
		// the new widgets stay invisible on the front-end even though they're
		// persisted. Setting edit_mode here on every save ensures the page
		// graduates to Elementor-rendered the moment it gets its first widget.
		$existing_mode = get_post_meta( (int) $post_id, '_elementor_edit_mode', true );
		if ( 'builder' !== $existing_mode ) {
			update_post_meta( (int) $post_id, '_elementor_edit_mode', 'builder' );
		}
		if ( 'builder' !== get_post_meta( (int) $post_id, '_elementor_edit_mode', true ) ) { return new WP_Error( 'elementor_mode_failed', 'Elementor builder mode could not be saved.' ); }
		return true;
	}

	/**
	 * Lightweight summary of a node used by the pending UI render so reviewers
	 * see what's being added/removed without parsing the full settings JSON.
	 */
	public static function summarize_node( $node ) {
		if ( ! is_array( $node ) ) {
			return array( 'type' => 'unknown' );
		}
		$out = array(
			'id'      => isset( $node['id'] ) ? $node['id'] : '',
			'elType'  => isset( $node['elType'] ) ? $node['elType'] : '',
			'type'    => isset( $node['widgetType'] ) ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : 'unknown' ),
			'preview' => '',
		);
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$preview_keys = array( 'title', 'title_text', 'editor', 'text', 'description', 'description_text', 'html', 'content' );
		foreach ( $preview_keys as $k ) {
			if ( ! empty( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
				$snippet = wp_strip_all_tags( $settings[ $k ] );
				$out['preview'] = mb_substr( $snippet, 0, 160 );
				break;
			}
		}
		if ( '' === $out['preview'] && ! empty( $node['elements'] ) ) {
			$out['preview'] = sprintf( '(container with %d child element%s)', count( $node['elements'] ), 1 === count( $node['elements'] ) ? '' : 's' );
		}
		return $out;
	}

	/**
	 * v0.19 deep-clone an Elementor tree with FRESH ids on every node.
	 * Powers the build_service_page macro tool: take a working pillar
	 * (post 137 IV pillar etc.), clone its entire _elementor_data tree
	 * byte-for-byte with new ids so the cloned subtree never collides
	 * with the source, and write the clone into a new draft post.
	 *
	 * Skip ids: any node whose id is in $skip_ids is dropped (whole
	 * subtree). Used to omit sections like a PREMIUM MENU that the new
	 * page should not inherit.
	 *
	 * Returns a NEW array — does not mutate the input.
	 */
	public static function clone_tree_with_fresh_ids( array $tree, array $skip_ids = array() ) {
		$skip_lookup = array_flip( $skip_ids );
		$result      = array();
		$id_acc      = array();
		foreach ( $tree as $node ) {
			$cloned = self::clone_node_with_fresh_id( $node, $skip_lookup, $id_acc );
			if ( null !== $cloned ) {
				$result[] = $cloned;
			}
		}
		return $result;
	}

	private static function clone_node_with_fresh_id( $node, array $skip_lookup, array &$id_acc ) {
		if ( ! is_array( $node ) ) {
			return null;
		}
		$src_id = isset( $node['id'] ) ? (string) $node['id'] : '';
		if ( '' !== $src_id && isset( $skip_lookup[ $src_id ] ) ) {
			return null; // skipped — drop whole subtree.
		}
		// Generate a fresh id that doesn't collide with already-cloned ids.
		do {
			$new_id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
		} while ( isset( $id_acc[ $new_id ] ) );
		$id_acc[ $new_id ] = true;

		$clone = $node;
		$clone['id'] = $new_id;

		// Recurse into children.
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			$kids = array();
			foreach ( $node['elements'] as $child ) {
				$built = self::clone_node_with_fresh_id( $child, $skip_lookup, $id_acc );
				if ( null !== $built ) {
					$kids[] = $built;
				}
			}
			$clone['elements'] = $kids;
		} else {
			$clone['elements'] = array();
		}
		return $clone;
	}

	/**
	 * v0.19 walk a tree applying string replacements to text-bearing
	 * settings (editor, text, title, title_text, description_text, html,
	 * content). Used after clone_tree_with_fresh_ids to swap "IV Therapy"
	 * → "Laser Genesis" etc. Replacement uses str_ireplace so case
	 * variants are caught.
	 *
	 * $replacements: ordered associative array of search => replace.
	 * Order matters — longer phrases should come first so the replacement
	 * for "IV Therapy in Irving, TX" lands before "IV Therapy" alone.
	 *
	 * Returns the modified tree (modifies in place AND returns).
	 */
	public static function apply_text_replacements_to_tree( array &$tree, array $replacements ) {
		if ( empty( $replacements ) ) {
			return $tree;
		}
		$search  = array_keys( $replacements );
		$replace = array_values( $replacements );
		self::walk_and_replace( $tree, $search, $replace );
		return $tree;
	}

	private static function walk_and_replace( array &$nodes, array $search, array $replace ) {
		$text_keys = array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' );
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				foreach ( $text_keys as $k ) {
					if ( isset( $node['settings'][ $k ] ) && is_string( $node['settings'][ $k ] ) ) {
						$node['settings'][ $k ] = str_ireplace( $search, $replace, $node['settings'][ $k ] );
					}
				}
				// Walk widget-specific array fields:
				//   icon_list[].text     — icon-list widget
				//   slides[].content/name/title — reviews / testimonials widget
				//   items[].item_title/item_content/tab_title/tab_content — nested-accordion + toggle
				if ( isset( $node['settings']['icon_list'] ) && is_array( $node['settings']['icon_list'] ) ) {
					foreach ( $node['settings']['icon_list'] as &$item ) {
						if ( isset( $item['text'] ) && is_string( $item['text'] ) ) {
							$item['text'] = str_ireplace( $search, $replace, $item['text'] );
						}
					}
					unset( $item );
				}
				if ( isset( $node['settings']['slides'] ) && is_array( $node['settings']['slides'] ) ) {
					foreach ( $node['settings']['slides'] as &$slide ) {
						foreach ( array( 'content', 'name', 'title' ) as $sk ) {
							if ( isset( $slide[ $sk ] ) && is_string( $slide[ $sk ] ) ) {
								$slide[ $sk ] = str_ireplace( $search, $replace, $slide[ $sk ] );
							}
						}
					}
					unset( $slide );
				}
				if ( isset( $node['settings']['items'] ) && is_array( $node['settings']['items'] ) ) {
					foreach ( $node['settings']['items'] as &$it ) {
						foreach ( array( 'item_title', 'item_content', 'tab_title', 'tab_content' ) as $ik ) {
							if ( isset( $it[ $ik ] ) && is_string( $it[ $ik ] ) ) {
								$it[ $ik ] = str_ireplace( $search, $replace, $it[ $ik ] );
							}
						}
					}
					unset( $it );
				}
				// Replace link URLs too — e.g., spoke-link icon-box settings.link.url
				// AND google_maps address field.
				if ( isset( $node['settings']['link']['url'] ) && is_string( $node['settings']['link']['url'] ) ) {
					$node['settings']['link']['url'] = str_ireplace( $search, $replace, $node['settings']['link']['url'] );
				}
				if ( isset( $node['settings']['address'] ) && is_string( $node['settings']['address'] ) ) {
					$node['settings']['address'] = str_ireplace( $search, $replace, $node['settings']['address'] );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_and_replace( $node['elements'], $search, $replace );
			}
		}
		unset( $node );
	}

	/**
	 * Validate a decoded Elementor tree before we write it back. Catches the
	 * three failure modes that have actually corrupted posts in the past:
	 *
	 *   - non-array top level (json_decode returned a scalar/null)
	 *   - duplicate node ids (Elementor's editor silently drops the second one)
	 *   - missing elType (the renderer chokes and erases the page)
	 *
	 * Returns true on pass, WP_Error otherwise. Apply handlers MUST call this
	 * after every mutation to refuse-and-roll-back rather than write a
	 * malformed tree.
	 */
	public static function validate_tree( $tree ) {
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'tree_not_array', 'Elementor tree is not an array.' );
		}
		$seen        = array();
		$valid_types = array( 'section', 'container', 'column', 'widget' );
		$check = function ( $nodes ) use ( &$check, &$seen, $valid_types ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					return new WP_Error( 'tree_node_not_array', 'A node is not an array.' );
				}
				if ( empty( $n['id'] ) || ! is_string( $n['id'] ) ) {
					return new WP_Error( 'tree_node_no_id', 'A node is missing its id.' );
				}
				if ( isset( $seen[ $n['id'] ] ) ) {
					return new WP_Error( 'tree_duplicate_id', sprintf( 'Duplicate node id %s in tree.', $n['id'] ) );
				}
				$seen[ $n['id'] ] = true;
				$el_type = isset( $n['elType'] ) ? $n['elType'] : '';
				if ( '' === $el_type || ! in_array( $el_type, $valid_types, true ) ) {
					return new WP_Error( 'tree_bad_eltype', sprintf( 'Node %s has bad elType: %s', $n['id'], $el_type ) );
				}
				if ( 'widget' === $el_type && empty( $n['widgetType'] ) ) {
					return new WP_Error( 'tree_widget_no_type', sprintf( 'Widget node %s has no widgetType.', $n['id'] ) );
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$inner = $check( $n['elements'] );
					if ( is_wp_error( $inner ) ) {
						return $inner;
					}
				}
			}
			return true;
		};
		return $check( $tree );
	}

	/**
	 * Page style scanner. Returns the signals a fresh widget needs to match in
	 * order to look like it belongs on the page:
	 *
	 *   - global_colors      : Elementor Kit system + custom color variables
	 *   - global_typography  : Kit system + custom typography sets
	 *   - widget_patterns    : frequency of every widgetType actually used (so
	 *                          a new section reuses widgets the page already has)
	 *   - heading_levels     : distribution across h1..h6/span (where to slot a new H2)
	 *   - font_families      : every font family actually in use, by frequency
	 *   - color_palette      : every inline hex color actually used, by frequency
	 *   - container_styles   : 3 most common container shapes (flex direction,
	 *                          wrap, gap unit, content_width) — copy one of these
	 *                          when adding a new section to match the page rhythm
	 *   - icon_box_sample    : one full icon-box settings hash from the page,
	 *                          ready to clone for a new icon-box (preserves all
	 *                          the typography / spacing / color globals the page uses)
	 *   - root_ids           : top-level container ids on this page (good insert targets)
	 *
	 * This is the foundation for "scan page properly so new widgets match the
	 * existing styling" — pass the result to the model so it can pick a
	 * container shape and font/color set that already lives on the page.
	 */
	public static function get_style_context( $post_id ) {
		$tree = self::load_tree( (int) $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		// Kit-level globals: Elementor stores colors/typography on the active Kit.
		$kit_id            = (int) get_option( 'elementor_active_kit', 0 );
		$global_colors     = array();
		$global_typography = array();
		if ( $kit_id > 0 ) {
			$kit = get_post_meta( $kit_id, '_elementor_page_settings', true );
			if ( is_array( $kit ) ) {
				foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
					if ( ! empty( $kit[ $bucket ] ) && is_array( $kit[ $bucket ] ) ) {
						foreach ( $kit[ $bucket ] as $c ) {
							if ( is_array( $c ) && isset( $c['_id'], $c['title'], $c['color'] ) ) {
								$global_colors[] = array(
									'id'     => $c['_id'],
									'title'  => $c['title'],
									'color'  => $c['color'],
									'source' => $bucket,
								);
							}
						}
					}
				}
				foreach ( array( 'system_typography', 'custom_typography' ) as $bucket ) {
					if ( ! empty( $kit[ $bucket ] ) && is_array( $kit[ $bucket ] ) ) {
						foreach ( $kit[ $bucket ] as $t ) {
							if ( is_array( $t ) && isset( $t['_id'], $t['title'] ) ) {
								$global_typography[] = array(
									'id'           => $t['_id'],
									'title'        => $t['title'],
									'font_family'  => isset( $t['typography_font_family'] ) ? $t['typography_font_family'] : null,
									'font_weight'  => isset( $t['typography_font_weight'] ) ? $t['typography_font_weight'] : null,
									'source'       => $bucket,
								);
							}
						}
					}
				}
			}
		}

		// Walk the page to collect in-use signals.
		$stats = array(
			'widget_patterns'  => array(),
			'heading_levels'   => array(),
			'font_families'    => array(),
			'color_palette'    => array(),
			'container_shapes' => array(),
			'icon_box_sample'  => null,
			'heading_sample'   => null,
			'text_editor_sample' => null,
			'root_ids'         => array(),
		);

		foreach ( $tree as $root ) {
			if ( is_array( $root ) && ! empty( $root['id'] ) ) {
				$stats['root_ids'][] = array(
					'id'      => $root['id'],
					'el_type' => isset( $root['elType'] ) ? $root['elType'] : '',
					'children' => isset( $root['elements'] ) && is_array( $root['elements'] ) ? count( $root['elements'] ) : 0,
				);
			}
		}

		$bump = function ( &$bag, $key ) {
			if ( '' === (string) $key ) {
				return;
			}
			$bag[ $key ] = isset( $bag[ $key ] ) ? $bag[ $key ] + 1 : 1;
		};

		$visit = function ( $nodes ) use ( &$visit, &$stats, $bump ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$el_type = isset( $n['elType'] ) ? $n['elType'] : '';
				$s       = isset( $n['settings'] ) && is_array( $n['settings'] ) ? $n['settings'] : array();

				if ( 'widget' === $el_type ) {
					$wt = isset( $n['widgetType'] ) ? $n['widgetType'] : '';
					$bump( $stats['widget_patterns'], $wt );

					if ( 'heading' === $wt && ! empty( $s['header_size'] ) ) {
						$bump( $stats['heading_levels'], (string) $s['header_size'] );
					}
					foreach ( array( 'title_typography_font_family', 'typography_font_family', 'description_typography_font_family' ) as $k ) {
						if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
							$bump( $stats['font_families'], $s[ $k ] );
						}
					}
					foreach ( array( 'title_color', 'description_color', 'primary_color', 'secondary_color', 'color', '_border_color' ) as $k ) {
						if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) && '#' === substr( $s[ $k ], 0, 1 ) ) {
							$bump( $stats['color_palette'], strtolower( $s[ $k ] ) );
						}
					}
					if ( 'icon-box' === $wt && null === $stats['icon_box_sample'] ) {
						$stats['icon_box_sample'] = array(
							'widget_id' => isset( $n['id'] ) ? $n['id'] : '',
							'settings'  => $s,
						);
					}
					// Prefer the first H2 with an _flex_align_self setting as
					// the heading sample — that's the one that actually carries
					// the page's centering convention. If none exist, fall back
					// to the first H2 encountered. Skip eyebrow spans and H3+
					// since those have different role and styling.
					if ( 'heading' === $wt && ! empty( $s['header_size'] ) && 'h2' === (string) $s['header_size'] ) {
						$preferred = isset( $s['_flex_align_self'] ) && '' !== (string) $s['_flex_align_self'];
						if ( null === $stats['heading_sample'] || ( $preferred && empty( $stats['heading_sample']['_preferred'] ) ) ) {
							$stats['heading_sample'] = array(
								'widget_id'  => isset( $n['id'] ) ? $n['id'] : '',
								'settings'   => $s,
								'_preferred' => $preferred,
							);
						}
					}
					// First text-editor we encounter is good enough — the
					// alignment / typography choices are usually consistent
					// across the page and we just need ONE valid sample to
					// clone alignment props onto a new intro paragraph.
					if ( 'text-editor' === $wt && null === $stats['text_editor_sample'] ) {
						$stats['text_editor_sample'] = array(
							'widget_id' => isset( $n['id'] ) ? $n['id'] : '',
							'settings'  => $s,
						);
					}
				} elseif ( 'container' === $el_type || 'section' === $el_type ) {
					$shape = array(
						'flex_direction' => isset( $s['flex_direction'] ) ? $s['flex_direction'] : null,
						'flex_wrap'      => isset( $s['flex_wrap'] ) ? $s['flex_wrap'] : null,
						'content_width'  => isset( $s['content_width'] ) ? $s['content_width'] : null,
					);
					$key = md5( wp_json_encode( $shape ) );
					if ( ! isset( $stats['container_shapes'][ $key ] ) ) {
						$stats['container_shapes'][ $key ] = array(
							'count'  => 0,
							'sample' => $shape,
						);
					}
					$stats['container_shapes'][ $key ]['count']++;
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$visit( $n['elements'] );
				}
			}
		};
		$visit( $tree );

		arsort( $stats['widget_patterns'] );
		arsort( $stats['heading_levels'] );
		arsort( $stats['font_families'] );
		arsort( $stats['color_palette'] );
		uasort( $stats['container_shapes'], function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		// Strip the internal _preferred marker from the heading sample before
		// returning so the response shape stays clean.
		$heading_sample = $stats['heading_sample'];
		if ( is_array( $heading_sample ) ) {
			unset( $heading_sample['_preferred'] );
		}
		return array(
			'post_id'             => (int) $post_id,
			'kit_id'              => $kit_id,
			'global_colors'       => $global_colors,
			'global_typography'   => $global_typography,
			'widget_patterns'     => $stats['widget_patterns'],
			'heading_levels'      => $stats['heading_levels'],
			'font_families'       => $stats['font_families'],
			'color_palette'       => $stats['color_palette'],
			'container_shapes'    => array_values( array_slice( $stats['container_shapes'], 0, 3, true ) ),
			'icon_box_sample'     => $stats['icon_box_sample'],
			'heading_sample'      => $heading_sample,
			'text_editor_sample'  => $stats['text_editor_sample'],
			'root_ids'            => $stats['root_ids'],
		);
	}
}
