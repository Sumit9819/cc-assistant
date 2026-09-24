<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds an annotated, section-aware map of an Elementor page.
 *
 * v0.34 — added because numeric `position` params on container_add /
 * widget_add had no awareness of logical section continuity, leading to
 * inserts that split an H2 from its body container. The map shape this
 * class returns lets callers reason about ROOT containers + the
 * "section pairs" the site visually presents (H2-alone container +
 * its body container), and refuse inserts that would split a pair.
 *
 * Three public entry points:
 *   build_page_map( $post_id )       — full annotated map
 *   render_ascii_tree( $map )        — human-readable preview
 *   validate_insert_position( $map, $position ) — split detection
 *
 * Plus a transient-backed cache so apply handlers can verify that the
 * caller actually consulted the map within the last 10 minutes
 * (mark_map_consulted / was_map_consulted_recently).
 */
class CC_Assistant_Elementor_Map {

	const CACHE_KEY_PREFIX = 'cc_assistant_map_consulted_';
	const CACHE_TTL        = 600; // 10 minutes — long enough for an AI
	                              // session to think + queue, short enough
	                              // that a stale map can't blanket-bypass
	                              // the gate for hours.

	/**
	 * Build the annotated page map.
	 *
	 * Returns:
	 *   post_id, permalink, root_count,
	 *   roots: [
	 *     {
	 *       position, container_id, el_type, depth_in_root, widget_count,
	 *       headings: [{level, text, widget_id}],
	 *       widget_types: ["heading", "text-editor", ...],
	 *       semantic_label,
	 *       has_h2_only, has_h2_plus_body, is_heading_alone,
	 *       pair_with_position (int|null), pair_role ("head"|"body"|null),
	 *     }, ...
	 *   ],
	 *   section_pairs: [{head_position, body_position, label}],
	 *   safe_insert_positions: [int],   // gaps between complete sections
	 *   risky_insert_positions: [int],  // inside a pair
	 *   warnings: [string]
	 *
	 * Returns null if the post has no Elementor data.
	 */
	public static function build_page_map( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return null;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		// Defensive: re-key by sequential 0..N. Elementor always stores
		// _elementor_data as a JSON array, but if a corrupted import
		// produced an associative array we'd hand out string positions to
		// callers, which the apply path can't use.
		$data = array_values( $data );

		$roots = array();
		foreach ( $data as $i => $node ) {
			if ( ! is_array( $node ) ) {
				// Skip malformed entries. We could fabricate a placeholder
				// to preserve the position-N index in the underlying array
				// but the apply path's index-into-raw-array semantics make
				// that fragile. Instead the resulting map gets fewer roots
				// than the raw array claims — the operator will notice the
				// discrepancy on the next read and can repair.
				continue;
			}
			$roots[] = self::analyze_root_container( $node, $i );
		}

		// Section pair detection — heading-alone container followed by a body container.
		$pairs                  = self::detect_section_pairs( $roots );
		$safe_positions         = array();
		$risky_positions        = array();
		$risky_position_reasons = array();

		// Attach pair metadata back onto the roots and build the safe-insert list.
		// The "safe" insert positions are: 0 (before the first root) and any
		// index N where roots[N-1] is NOT the head of a pair (i.e. inserting
		// at N would not split). Reverse logic for "risky".
		$pair_lookup_head = array();
		$pair_lookup_body = array();
		foreach ( $pairs as $p ) {
			$pair_lookup_head[ $p['head_position'] ] = $p;
			$pair_lookup_body[ $p['body_position'] ] = $p;
		}
		foreach ( $roots as &$r ) {
			$pos = $r['position'];
			if ( isset( $pair_lookup_head[ $pos ] ) ) {
				$r['pair_with_position'] = $pair_lookup_head[ $pos ]['body_position'];
				$r['pair_role']          = 'head';
			} elseif ( isset( $pair_lookup_body[ $pos ] ) ) {
				$r['pair_with_position'] = $pair_lookup_body[ $pos ]['head_position'];
				$r['pair_role']          = 'body';
			} else {
				$r['pair_with_position'] = null;
				$r['pair_role']          = null;
			}
		}
		unset( $r );

		$total = count( $roots );
		// Insertion index N means "insert as the new roots[N]". Valid range is 0..total.
		for ( $n = 0; $n <= $total; $n++ ) {
			$before = ( $n > 0 ) ? $roots[ $n - 1 ] : null;
			// Risky iff the slot is right after a "head" container — i.e. between head and body.
			if ( $before && 'head' === $before['pair_role'] ) {
				$risky_positions[]                    = $n;
				$risky_position_reasons[ (string) $n ] = sprintf(
					'splits section "%s" between its H2 (position %d) and its body (position %d)',
					$before['semantic_label'],
					$before['position'],
					$before['pair_with_position']
				);
			} else {
				$safe_positions[] = $n;
			}
		}

		return array(
			'post_id'                 => $post_id,
			'post_title'              => $post->post_title,
			'permalink'               => get_permalink( $post_id ),
			'edit_url'                => get_edit_post_link( $post_id, 'raw' ),
			'root_count'              => $total,
			'roots'                   => $roots,
			'section_pairs'           => $pairs,
			'safe_insert_positions'   => $safe_positions,
			'risky_insert_positions'  => $risky_positions,
			'risky_position_reasons'  => $risky_position_reasons,
			'rendered_at'             => current_time( 'mysql' ),
		);
	}

	/**
	 * Inspect one root container node and return its summary row.
	 */
	private static function analyze_root_container( array $node, $position ) {
		$container_id = isset( $node['id'] ) ? (string) $node['id'] : '';
		$el_type      = isset( $node['elType'] ) ? (string) $node['elType'] : 'container';

		$state = array(
			'headings'     => array(),
			'widget_types' => array(),
			'widget_count' => 0,
			'max_depth'    => 0,
		);
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			self::walk_for_summary( $node['elements'], $state, 1 );
		}

		// Determine the semantic label — first H1/H2/H3 we hit drives the
		// section's identity. Falls back to "(no heading)" so the section
		// is still uniquely addressable when ordered.
		$semantic_label = '(no heading)';
		$first_h2_text  = '';
		foreach ( $state['headings'] as $h ) {
			if ( '' === $semantic_label || '(no heading)' === $semantic_label ) {
				$semantic_label = $h['text'];
			}
			if ( 'h2' === $h['level'] && '' === $first_h2_text ) {
				$first_h2_text = $h['text'];
			}
		}
		if ( '' !== $first_h2_text ) {
			$semantic_label = $first_h2_text;
		}

		// "Heading-alone" classification: container has exactly one heading
		// and zero text-bearing siblings (text-editor / icon-list / icon-box /
		// image-box / button / accordion / tabs). This is the signature of
		// the H2-alone container in the site's H2-then-body pattern.
		$text_bearing_types = array(
			'text-editor', 'icon-list', 'icon-box', 'image-box', 'button',
			'accordion', 'toggle', 'nested-accordion', 'tabs', 'nested-tabs',
			'price-list', 'testimonial', 'call-to-action', 'html', 'shortcode',
			'form', 'image',
		);
		$heading_count       = 0;
		$text_bearing_count  = 0;
		$h2_count            = 0;
		foreach ( $state['widget_types'] as $t ) {
			if ( 'heading' === $t ) {
				$heading_count++;
			} elseif ( in_array( $t, $text_bearing_types, true ) ) {
				$text_bearing_count++;
			}
		}
		foreach ( $state['headings'] as $h ) {
			if ( 'h2' === $h['level'] ) {
				$h2_count++;
			}
		}

		$is_heading_alone = ( 1 === $h2_count && 0 === $text_bearing_count );

		// Mobile-flex flag — does this container override flex-direction on mobile?
		$mobile_flex_override = false;
		if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
			$s = $node['settings'];
			if ( ! empty( $s['flex_direction_mobile'] ) || ! empty( $s['flex_direction_tablet'] ) ) {
				$mobile_flex_override = true;
			}
		}

		return array(
			'position'             => (int) $position,
			'container_id'         => $container_id,
			'el_type'              => $el_type,
			'semantic_label'       => $semantic_label,
			'widget_count'         => (int) $state['widget_count'],
			'widget_types'         => array_values( array_unique( $state['widget_types'] ) ),
			'heading_count'        => $heading_count,
			'h2_count'             => $h2_count,
			'text_bearing_count'   => $text_bearing_count,
			'headings'             => $state['headings'],
			'max_depth'            => (int) $state['max_depth'],
			'is_heading_alone'     => $is_heading_alone,
			'mobile_flex_override' => $mobile_flex_override,
		);
	}

	/**
	 * Mini-walker that just gathers heading + widget-type signal — much
	 * cheaper than the full Elementor_Parser walk because it skips
	 * link / text extraction.
	 */
	private static function walk_for_summary( array $elements, array &$state, $depth ) {
		if ( $depth > $state['max_depth'] ) {
			$state['max_depth'] = $depth;
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type = isset( $el['elType'] ) ? $el['elType'] : '';
			if ( 'container' === $el_type || 'section' === $el_type || 'column' === $el_type ) {
				if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
					self::walk_for_summary( $el['elements'], $state, $depth + 1 );
				}
				continue;
			}
			if ( 'widget' !== $el_type ) {
				continue;
			}
			$state['widget_count']++;
			$type = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';
			$state['widget_types'][] = $type;

			$wid      = isset( $el['id'] ) ? (string) $el['id'] : '';
			$settings = isset( $el['settings'] ) ? $el['settings'] : array();

			if ( 'heading' === $type ) {
				$title = isset( $settings['title'] ) ? wp_strip_all_tags( (string) $settings['title'] ) : '';
				$level = isset( $settings['header_size'] ) ? strtolower( (string) $settings['header_size'] ) : 'h2';
				if ( '' !== $title && preg_match( '/^h[1-6]$/', $level ) ) {
					$state['headings'][] = array(
						'level'     => $level,
						'text'      => $title,
						'widget_id' => $wid,
					);
				}
			}

			// Widgets that render their own heading (image-box, icon-box,
			// nested-accordion) — surface those too so the semantic label
			// can pick up titles from a section built entirely from
			// image-box cards.
			if ( in_array( $type, array( 'image-box', 'icon-box' ), true ) ) {
				$title = isset( $settings['title_text'] ) ? wp_strip_all_tags( (string) $settings['title_text'] ) : '';
				$tag   = isset( $settings['title_size'] ) ? strtolower( (string) $settings['title_size'] ) : 'h3';
				if ( '' !== $title && preg_match( '/^h[1-6]$/', $tag ) ) {
					$state['headings'][] = array(
						'level'     => $tag,
						'text'      => $title,
						'widget_id' => $wid,
					);
				}
			}

			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_for_summary( $el['elements'], $state, $depth + 1 );
			}
		}
	}

	/**
	 * Identify "section pairs" — adjacent root containers where the first
	 * is heading-alone and the second contains body content WITHOUT its own
	 * H2. Returns an array of pairs with the head + body positions and the
	 * inherited semantic label.
	 */
	/**
	 * v0.44: full nested-node tree. build_page_map exposes roots only and
	 * get_elementor_widgets exposes leaf widgets only — the MIDDLE containers
	 * (grid rows, column wrappers) had no id surface, so the assistant kept
	 * reverse-engineering them out of a 78 KB export with a Python script.
	 * This returns EVERY node: id, el_type, widget_type, depth, parent_id,
	 * position (index within parent), path, child_count, a grid/flex layout
	 * hint, and a short label. One call replaces the export+parse dance and
	 * makes "which grid reserves an empty row" trivially visible
	 * (compare layout.grid_rows * grid_columns against child_count).
	 */
	public static function build_full_tree( $post_id ) {
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$nodes = array();
		self::walk_full_tree( array_values( $data ), '', 0, '', $nodes );
		return array(
			'post_id'    => (int) $post_id,
			'node_count' => count( $nodes ),
			'nodes'      => $nodes,
		);
	}

	private static function walk_full_tree( array $elements, $parent_id, $depth, $parent_path, &$nodes ) {
		foreach ( array_values( $elements ) as $i => $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type  = isset( $el['elType'] ) ? (string) $el['elType'] : 'container';
			$wtype    = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : null;
			$id       = isset( $el['id'] ) ? (string) $el['id'] : '';
			$seg      = ( 'widget' === $el_type ? 'widget' : 'container' ) . '[' . $i . ']';
			$path     = '' === $parent_path ? $seg : $parent_path . '/' . $seg;
			$children = ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) ? $el['elements'] : array();

			$node = array(
				'id'          => $id,
				'el_type'     => $el_type,
				'widget_type' => $wtype,
				'depth'       => $depth,
				'position'    => $i,
				'parent_id'   => $parent_id,
				'path'        => $path,
				'child_count' => count( $children ),
			);

			$s = ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) ? $el['settings'] : array();
			if ( 'container' === $el_type && $s ) {
				$layout = array();
				if ( isset( $s['container_type'] ) ) {
					$layout['type'] = $s['container_type'];
				}
				if ( isset( $s['grid_columns_grid']['size'] ) ) {
					$layout['grid_columns'] = $s['grid_columns_grid']['size'];
				}
				if ( isset( $s['grid_rows_grid']['size'] ) ) {
					$layout['grid_rows'] = $s['grid_rows_grid']['size'];
				}
				if ( isset( $s['flex_direction'] ) ) {
					$layout['flex_direction'] = $s['flex_direction'];
				}
				if ( $layout ) {
					$node['layout'] = $layout;
				}
			} elseif ( 'widget' === $el_type && $s ) {
				foreach ( array( 'title', 'title_text', 'text', 'editor' ) as $k ) {
					if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
						$node['label'] = mb_substr( trim( wp_strip_all_tags( $s[ $k ] ) ), 0, 50 );
						break;
					}
				}
			}

			$nodes[] = $node;
			if ( $children ) {
				self::walk_full_tree( $children, $id, $depth + 1, $path, $nodes );
			}
		}
	}

	public static function detect_section_pairs( array $roots ) {
		$pairs = array();
		$n     = count( $roots );
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$head = $roots[ $i ];
			$body = $roots[ $i + 1 ];
			if ( ! $head['is_heading_alone'] ) {
				continue;
			}
			// Body must have text content AND not introduce its own H2.
			if ( $body['h2_count'] > 0 ) {
				continue;
			}
			if ( $body['text_bearing_count'] < 1 ) {
				continue;
			}
			$pairs[] = array(
				'head_position' => $head['position'],
				'body_position' => $body['position'],
				'label'         => $head['semantic_label'],
			);
		}
		return $pairs;
	}

	/**
	 * Validate a proposed insert position against the map.
	 *
	 * Returns array { is_safe: bool, reason: string|null, recommended_positions: int[] }.
	 */
	public static function validate_insert_position( array $map, $position ) {
		$position = (int) $position;
		$total    = (int) $map['root_count'];
		if ( $position < 0 || $position > $total ) {
			return array(
				'is_safe'               => false,
				'reason'                => sprintf( 'position %d is out of range (valid 0..%d)', $position, $total ),
				'recommended_positions' => $map['safe_insert_positions'],
			);
		}
		if ( in_array( $position, $map['risky_insert_positions'], true ) ) {
			$why = isset( $map['risky_position_reasons'][ (string) $position ] )
				? $map['risky_position_reasons'][ (string) $position ]
				: 'splits a logical section';
			return array(
				'is_safe'               => false,
				'reason'                => $why,
				'recommended_positions' => $map['safe_insert_positions'],
			);
		}
		return array(
			'is_safe'               => true,
			'reason'                => null,
			'recommended_positions' => $map['safe_insert_positions'],
		);
	}

	/**
	 * Plain-text ASCII tree of the map. Goes back in API responses so the
	 * reviewer (and the AI) can sanity-check before committing to a position.
	 */
	public static function render_ascii_tree( array $map ) {
		$lines = array();
		$lines[] = sprintf( '# Page Map — %s', $map['post_title'] );
		$lines[] = sprintf( '# %d root containers; %d section pair(s).', $map['root_count'], count( $map['section_pairs'] ) );
		$lines[] = '';

		foreach ( $map['roots'] as $r ) {
			$tag = '';
			if ( 'head' === $r['pair_role'] ) {
				$tag = sprintf( ' [pair-head with pos %d]', (int) $r['pair_with_position'] );
			} elseif ( 'body' === $r['pair_role'] ) {
				$tag = sprintf( ' [pair-body with pos %d]', (int) $r['pair_with_position'] );
			}
			$types = $r['widget_types'] ? ' (' . implode( ', ', $r['widget_types'] ) . ')' : '';
			$lines[] = sprintf(
				'pos %2d | %s | %s | %d widget(s)%s%s',
				$r['position'],
				$r['container_id'] ?: '(no-id)',
				$r['semantic_label'],
				$r['widget_count'],
				$types,
				$tag
			);
		}

		$lines[] = '';
		$lines[] = 'Safe insert positions: ' . ( $map['safe_insert_positions'] ? implode( ', ', $map['safe_insert_positions'] ) : '(none)' );
		if ( ! empty( $map['risky_insert_positions'] ) ) {
			$lines[] = 'Risky insert positions:';
			foreach ( $map['risky_insert_positions'] as $rp ) {
				$reason = isset( $map['risky_position_reasons'][ (string) $rp ] ) ? $map['risky_position_reasons'][ (string) $rp ] : 'splits a section';
				$lines[] = sprintf( '  %d -> %s', $rp, $reason );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Mark that get_page_map was called for this post. Apply handlers
	 * consult this transient to gate layout-altering operations.
	 *
	 * v0.34.0 also stamps the post's `post_modified_gmt` at consult time so
	 * `was_map_consulted_recently` can detect a stale map (post edited by
	 * another actor between consult and apply). The 10-min TTL alone is not
	 * enough — a layout change in minute 9 against an 8-min-stale map is
	 * still a correctness bug.
	 */
	public static function mark_map_consulted( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		$modified = get_post_field( 'post_modified_gmt', $post_id );
		set_transient(
			self::CACHE_KEY_PREFIX . $post_id,
			array(
				'consulted_at'                 => time(),
				'post_id'                      => $post_id,
				'post_modified_when_consulted' => is_string( $modified ) ? $modified : '',
			),
			self::CACHE_TTL
		);
	}

	/**
	 * True iff get_page_map has been called for this post within CACHE_TTL
	 * AND the post has not been modified since.
	 *
	 * Returns a tuple-style status: ["fresh"|"stale"|"absent", since_seconds].
	 * Use was_map_consulted_recently() for the boolean form.
	 */
	public static function map_consult_status( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array( 'absent', 0 );
		}
		$cached = get_transient( self::CACHE_KEY_PREFIX . $post_id );
		if ( ! is_array( $cached ) || empty( $cached['consulted_at'] ) ) {
			return array( 'absent', 0 );
		}
		$age = time() - (int) $cached['consulted_at'];
		// Stale-state guard: if the post was modified AFTER the map was
		// consulted, the cached map is no longer trustworthy. Refuse the
		// gate and force a fresh map call. Compares post_modified_gmt as
		// a string (mysql datetime) so trailing-zeros do not matter.
		$current_modified = get_post_field( 'post_modified_gmt', $post_id );
		$consulted_at_mod = isset( $cached['post_modified_when_consulted'] ) ? (string) $cached['post_modified_when_consulted'] : '';
		if ( '' !== $current_modified && '' !== $consulted_at_mod && $current_modified !== $consulted_at_mod ) {
			return array( 'stale', $age );
		}
		return array( 'fresh', $age );
	}

	/**
	 * True iff get_page_map has been called for this post within CACHE_TTL
	 * AND the post is still in the same state it was at consult time.
	 */
	public static function was_map_consulted_recently( $post_id ) {
		list( $status, $_age ) = self::map_consult_status( $post_id );
		unset( $_age );
		return 'fresh' === $status;
	}

	/**
	 * Return the timestamp (UNIX epoch) of the last get_page_map call, or 0.
	 * Used in error messages so the caller knows how stale they are.
	 */
	public static function last_consulted_at( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return 0;
		}
		$cached = get_transient( self::CACHE_KEY_PREFIX . $post_id );
		if ( is_array( $cached ) && isset( $cached['consulted_at'] ) ) {
			return (int) $cached['consulted_at'];
		}
		return 0;
	}

	/**
	 * Return the current root container count for a post. Used by the
	 * empty-page bypass: a post with zero root containers has nothing to
	 * split, so the map-first gate is moot. Returns 0 for non-Elementor
	 * posts too.
	 */
	public static function root_container_count( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return 0;
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return 0;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return 0;
		}
		return count( $data );
	}

	/**
	 * v0.52: Full normalized layout spec — EVERY node (container + widget)
	 * with its structural position, type, and ALL settings. Volatile keys
	 * (element `id`, repeater `_id`) are stripped so a build and its clone
	 * (which have regenerated ids) diff cleanly. __globals__ token refs are
	 * kept verbatim, so a kit-token swap is still caught. Pair with
	 * compare_specs() to prove a build matches a reference page/template.
	 *
	 * Returns null if the post has no Elementor data.
	 */
	public static function build_layout_spec( $post_id ) {
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$nodes = array();
		self::walk_spec( array_values( $data ), '', 0, $nodes );
		return array(
			'post_id'    => (int) $post_id,
			'node_count' => count( $nodes ),
			'nodes'      => $nodes,
		);
	}

	private static function walk_spec( array $elements, $parent_path, $depth, &$nodes ) {
		foreach ( array_values( $elements ) as $i => $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type  = isset( $el['elType'] ) ? (string) $el['elType'] : 'container';
			$wtype    = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : null;
			$seg      = ( 'widget' === $el_type ? 'w' : 'c' ) . '[' . $i . ']';
			$path     = '' === $parent_path ? $seg : $parent_path . '/' . $seg;
			$settings = ( isset( $el['settings'] ) && is_array( $el['settings'] ) )
				? self::strip_volatile_ids( $el['settings'] )
				: array();
			$children = ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) ? $el['elements'] : array();

			$nodes[] = array(
				'path'        => $path,
				'depth'       => (int) $depth,
				'position'    => (int) $i,
				'el_type'     => $el_type,
				'widget_type' => $wtype,
				'child_count' => count( $children ),
				'settings'    => $settings,
			);
			if ( $children ) {
				self::walk_spec( $children, $path, $depth + 1, $nodes );
			}
		}
	}

	/** Recursively drop repeater `_id` keys (they differ between clones, carry no visual meaning). */
	private static function strip_volatile_ids( $arr ) {
		if ( ! is_array( $arr ) ) {
			return $arr;
		}
		unset( $arr['_id'] );
		foreach ( $arr as $k => $v ) {
			if ( is_array( $v ) ) {
				$arr[ $k ] = self::strip_volatile_ids( $v );
			}
		}
		return $arr;
	}

	/**
	 * v0.52: Deep diff of two Elementor posts. Walks both trees in lockstep
	 * by structural position (element ids ignored) and returns EVERY delta:
	 * structural (missing/extra node, widget-type mismatch) and per-setting
	 * value differences. The definitive "does my build match the reference"
	 * check. Both target + reference may be a page OR a Theme Builder template.
	 */
	public static function compare_specs( $target_id, $reference_id, $max = 500 ) {
		$t_raw = get_post_meta( (int) $target_id, '_elementor_data', true );
		$r_raw = get_post_meta( (int) $reference_id, '_elementor_data', true );
		if ( empty( $t_raw ) || empty( $r_raw ) ) {
			return array(
				'error'                    => 'One or both posts have no Elementor data.',
				'target_has_elementor'     => ! empty( $t_raw ),
				'reference_has_elementor'  => ! empty( $r_raw ),
			);
		}
		$t = json_decode( $t_raw, true );
		$r = json_decode( $r_raw, true );
		if ( ! is_array( $t ) || ! is_array( $r ) ) {
			return array( 'error' => 'Malformed Elementor data on one side.' );
		}
		$deltas = array();
		self::diff_nodes( array_values( $t ), array_values( $r ), '', $deltas, (int) $max );

		$structural = 0;
		$setting    = 0;
		foreach ( $deltas as $d ) {
			if ( isset( $d['kind'] ) && 'setting' === $d['kind'] ) {
				$setting++;
			} elseif ( isset( $d['kind'] ) && 'truncated' !== $d['kind'] ) {
				$structural++;
			}
		}
		return array(
			'target_id'         => (int) $target_id,
			'reference_id'      => (int) $reference_id,
			'match'             => empty( $deltas ),
			'delta_count'       => count( $deltas ),
			'structural_deltas' => $structural,
			'setting_deltas'    => $setting,
			'deltas'            => $deltas,
		);
	}

	private static function diff_nodes( array $a, array $b, $path, &$deltas, $max ) {
		$a = array_values( $a );
		$b = array_values( $b );
		$n = max( count( $a ), count( $b ) );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( count( $deltas ) >= $max ) {
				$deltas[] = array( 'kind' => 'truncated', 'path' => $path, 'message' => 'delta cap reached — first ' . $max . ' shown' );
				return;
			}
			$p  = $path . '/' . $i;
			$an = ( isset( $a[ $i ] ) && is_array( $a[ $i ] ) ) ? $a[ $i ] : null;
			$bn = ( isset( $b[ $i ] ) && is_array( $b[ $i ] ) ) ? $b[ $i ] : null;
			if ( null === $an && null === $bn ) {
				continue;
			}
			if ( null === $an ) {
				$deltas[] = array( 'kind' => 'missing_in_target', 'path' => $p, 'reference' => self::node_label( $bn ) );
				continue;
			}
			if ( null === $bn ) {
				$deltas[] = array( 'kind' => 'extra_in_target', 'path' => $p, 'target' => self::node_label( $an ) );
				continue;
			}
			$at = self::node_type( $an );
			$bt = self::node_type( $bn );
			if ( $at !== $bt ) {
				$deltas[] = array( 'kind' => 'type_mismatch', 'path' => $p, 'target' => $at, 'reference' => $bt );
				self::diff_nodes( self::node_kids( $an ), self::node_kids( $bn ), $p, $deltas, $max );
				continue;
			}
			$as   = self::strip_volatile_ids( ( isset( $an['settings'] ) && is_array( $an['settings'] ) ) ? $an['settings'] : array() );
			$bs   = self::strip_volatile_ids( ( isset( $bn['settings'] ) && is_array( $bn['settings'] ) ) ? $bn['settings'] : array() );
			$keys = array_unique( array_merge( array_keys( $as ), array_keys( $bs ) ) );
			foreach ( $keys as $k ) {
				if ( 'id' === $k ) {
					continue;
				}
				$av = array_key_exists( $k, $as ) ? $as[ $k ] : null;
				$bv = array_key_exists( $k, $bs ) ? $bs[ $k ] : null;
				if ( wp_json_encode( $av ) !== wp_json_encode( $bv ) ) {
					if ( count( $deltas ) >= $max ) {
						$deltas[] = array( 'kind' => 'truncated', 'path' => $p, 'message' => 'delta cap reached — first ' . $max . ' shown' );
						return;
					}
					$deltas[] = array(
						'kind'      => 'setting',
						'path'      => $p . ' [' . $bt . ']',
						'setting'   => $k,
						'target'    => self::short_val( $av ),
						'reference' => self::short_val( $bv ),
					);
				}
			}
			self::diff_nodes( self::node_kids( $an ), self::node_kids( $bn ), $p, $deltas, $max );
		}
	}

	private static function node_type( $n ) {
		$et = isset( $n['elType'] ) ? (string) $n['elType'] : 'container';
		if ( 'widget' === $et ) {
			return isset( $n['widgetType'] ) ? 'widget:' . $n['widgetType'] : 'widget';
		}
		return $et;
	}

	private static function node_kids( $n ) {
		return ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) ? $n['elements'] : array();
	}

	private static function node_label( $n ) {
		$t = self::node_type( $n );
		$s = ( isset( $n['settings'] ) && is_array( $n['settings'] ) ) ? $n['settings'] : array();
		foreach ( array( 'title', 'title_text', 'text', 'editor' ) as $k ) {
			if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
				return $t . ' "' . mb_substr( trim( wp_strip_all_tags( $s[ $k ] ) ), 0, 40 ) . '"';
			}
		}
		return $t;
	}

	private static function short_val( $v ) {
		if ( null === $v ) {
			return null;
		}
		$j = wp_json_encode( $v );
		if ( ! is_string( $j ) ) {
			return null;
		}
		return mb_strlen( $j ) > 140 ? mb_substr( $j, 0, 140 ) . '…' : $j;
	}
}
