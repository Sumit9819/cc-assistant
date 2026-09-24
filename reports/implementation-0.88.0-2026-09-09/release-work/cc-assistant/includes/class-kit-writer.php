<?php
/**
 * v0.76 — Elementor Kit (Site Settings) writer.
 *
 * The Kit is where the site-wide levers live: global colours, global
 * typography, the link colour, button defaults. Until now every one of
 * those was an operator trip into Elementor > Site Settings, because the
 * kit is an `elementor_library` post and its settings sit in one
 * serialized postmeta blob (`_elementor_page_settings`) that
 * draft_update_postmeta must never touch as a string.
 *
 * Same contract as the plugin-setting writer (v0.69): ONE leaf by dot
 * path, refuse paths that do not exist unless allow_create, snapshot the
 * whole blob so revert restores the exact prior structure, and treat a
 * write as unproven until the rendered page is re-read.
 *
 * Path grammar adds one thing the option writer does not need: Elementor
 * keeps colours and fonts as LISTS of {_id, title, ...} rows, so a list
 * segment may be addressed by its `_id` ("system_colors.primary.color"),
 * by its title case-insensitively ("custom_colors.Brand Red.color"), or by
 * numeric index ("system_colors.0.color").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Kit_Writer {

	const META          = '_elementor_page_settings';
	const MAX_VALUE_LEN = 2000;

	/** Active kit post id, 0 when Elementor has none. */
	public static function kit_id() {
		return (int) get_option( 'elementor_active_kit', 0 );
	}

	/* ------------------------------------------------------------- read --- */

	/**
	 * Compact, decision-oriented view of the kit: the colour and typography
	 * registries (id + title + value), every scalar top-level setting, and the
	 * names of array settings so the caller can drill in with a path.
	 */
	public static function read() {
		$kit = self::kit_id();
		if ( $kit <= 0 ) {
			return new WP_Error( 'no_kit', 'Elementor has no active kit on this site (option elementor_active_kit is empty).' );
		}
		$meta = get_post_meta( $kit, self::META, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		return self::describe( $kit, $meta );
	}

	/** Pure: shape the kit meta for the reader. */
	public static function describe( $kit_id, array $meta ) {
		$colors = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
			$colors[ $bucket ] = array();
			if ( ! empty( $meta[ $bucket ] ) && is_array( $meta[ $bucket ] ) ) {
				foreach ( $meta[ $bucket ] as $i => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$colors[ $bucket ][] = array(
						'index' => $i,
						'_id'   => isset( $row['_id'] ) ? (string) $row['_id'] : '',
						'title' => isset( $row['title'] ) ? (string) $row['title'] : '',
						'color' => isset( $row['color'] ) ? (string) $row['color'] : '',
						'path'  => $bucket . '.' . ( isset( $row['_id'] ) ? (string) $row['_id'] : (string) $i ) . '.color',
					);
				}
			}
		}
		$typo = array();
		foreach ( array( 'system_typography', 'custom_typography' ) as $bucket ) {
			$typo[ $bucket ] = array();
			if ( ! empty( $meta[ $bucket ] ) && is_array( $meta[ $bucket ] ) ) {
				foreach ( $meta[ $bucket ] as $i => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$leaves = array();
					foreach ( $row as $k => $v ) {
						if ( is_scalar( $v ) && '_id' !== $k && 'title' !== $k ) {
							$leaves[ $k ] = $v;
						}
					}
					$typo[ $bucket ][] = array(
						'index'  => $i,
						'_id'    => isset( $row['_id'] ) ? (string) $row['_id'] : '',
						'title'  => isset( $row['title'] ) ? (string) $row['title'] : '',
						'leaves' => $leaves,
					);
				}
			}
		}
		$scalars    = array();
		$array_keys = array();
		foreach ( $meta as $k => $v ) {
			if ( is_scalar( $v ) || null === $v ) {
				$sv = is_bool( $v ) ? ( $v ? 'true' : 'false' ) : (string) $v;
				$scalars[ (string) $k ] = mb_substr( $sv, 0, 200 );
			} elseif ( is_array( $v ) && ! in_array( $k, array( 'system_colors', 'custom_colors', 'system_typography', 'custom_typography' ), true ) ) {
				$array_keys[ (string) $k ] = count( $v );
			}
		}
		ksort( $scalars );
		ksort( $array_keys );

		// The keys people actually come here for, with their current values
		// (absent = Elementor default / theme default applies).
		$watch = array(
			'link_normal_color', 'link_hover_color',
			'link_normal_typography_typography', 'link_normal_typography_text_decoration',
			'body_color', 'body_typography_typography',
			'h1_color', 'h2_color', 'h3_color', 'h4_color',
			'button_text_color', 'button_background_color',
			'form_field_text_color', 'form_field_background_color',
		);
		$levers = array();
		foreach ( $watch as $w ) {
			$levers[ $w ] = array_key_exists( $w, $meta ) && is_scalar( $meta[ $w ] ) ? (string) $meta[ $w ] : null;
		}

		return array(
			'kit_id'     => (int) $kit_id,
			'colors'     => $colors,
			'typography' => $typo,
			'levers'     => $levers,
			'scalars'    => $scalars,
			'array_keys' => $array_keys,
			'path_examples' => array(
				'system_colors.primary.color'              => 'the Primary global colour (by _id)',
				'custom_colors.Brand Red.color'            => 'a custom colour by its title',
				'link_normal_color'                        => 'site-wide link colour (unset = theme default, Hello theme uses #cc3366)',
				'link_normal_typography_text_decoration'   => 'underline | none (set link_normal_typography_typography=custom first)',
			),
			'note' => 'Values shown are what Elementor stores; a null lever means the theme/Elementor default applies. Writes go through draft_update_kit_setting and are verified on the front page after apply.',
		);
	}

	/* ------------------------------------------------------------- path --- */

	/** True when an array is a plain list (0..n-1 keys). */
	private static function is_list( $a ) {
		if ( ! is_array( $a ) ) {
			return false;
		}
		return array_keys( $a ) === range( 0, count( $a ) - 1 );
	}

	/** Find the key of a list row addressed by index, _id or title. */
	private static function list_key( array $list, $segment ) {
		if ( is_numeric( $segment ) && array_key_exists( (int) $segment, $list ) ) {
			return (int) $segment;
		}
		foreach ( $list as $k => $row ) {
			if ( is_array( $row ) && isset( $row['_id'] ) && (string) $row['_id'] === (string) $segment ) {
				return $k;
			}
		}
		foreach ( $list as $k => $row ) {
			if ( is_array( $row ) && isset( $row['title'] ) && strtolower( (string) $row['title'] ) === strtolower( (string) $segment ) ) {
				return $k;
			}
		}
		return null;
	}

	/**
	 * Walk a dot path. Returns found flag, value, and the concrete key chain
	 * (indexes resolved), so a write lands on the same row a read described.
	 *
	 * @return array{found:bool, value:mixed, keys:array}
	 */
	public static function resolve_path( array $data, $path ) {
		$keys = array();
		$cur  = $data;
		$segs = '' === (string) $path ? array() : explode( '.', (string) $path );
		foreach ( $segs as $seg ) {
			if ( ! is_array( $cur ) ) {
				return array( 'found' => false, 'value' => null, 'keys' => $keys );
			}
			if ( self::is_list( $cur ) && ! empty( $cur ) ) {
				$k = self::list_key( $cur, $seg );
				if ( null === $k ) {
					return array( 'found' => false, 'value' => null, 'keys' => $keys );
				}
			} elseif ( array_key_exists( $seg, $cur ) ) {
				$k = $seg;
			} else {
				return array( 'found' => false, 'value' => null, 'keys' => $keys );
			}
			$keys[] = $k;
			$cur    = $cur[ $k ];
		}
		return array( 'found' => true, 'value' => $cur, 'keys' => $keys );
	}

	/**
	 * Write a leaf. Existing rows are addressed exactly as resolve_path did;
	 * a missing tail is created only when $create is true (never a new LIST
	 * row — that would invent a colour with no _id Elementor can reference).
	 */
	public static function write_path( array $data, $path, $value, $create = false ) {
		$segs = explode( '.', (string) $path );
		$ref  = &$data;
		$n    = count( $segs );
		foreach ( $segs as $i => $seg ) {
			$last = ( $i === $n - 1 );
			if ( self::is_list( $ref ) && ! empty( $ref ) ) {
				$k = self::list_key( $ref, $seg );
				if ( null === $k ) {
					return new WP_Error( 'path_not_found', sprintf( 'No row "%s" in this list (address rows by _id, title, or index).', $seg ) );
				}
			} else {
				$k = $seg;
				if ( ! array_key_exists( $k, $ref ) ) {
					if ( ! $create ) {
						return new WP_Error( 'path_not_found', sprintf( 'Key "%s" does not exist.', $seg ) );
					}
					$ref[ $k ] = $last ? null : array();
				}
			}
			if ( $last ) {
				$ref[ $k ] = $value;
			} else {
				if ( ! is_array( $ref[ $k ] ) ) {
					return new WP_Error( 'path_not_leaf', sprintf( 'Segment "%s" is a scalar; cannot descend into it.', $seg ) );
				}
				$ref = &$ref[ $k ];
			}
		}
		return $data;
	}

	/* ------------------------------------------------------- validation --- */

	/** CSS colour Elementor will store and render: hex, rgb(a), hsl(a), or blank (= default). */
	public static function is_valid_color( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return true;
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v ) ) {
			return true;
		}
		return (bool) preg_match( '/^(rgb|rgba|hsl|hsla)\(\s*[\d.%\s,\/]+\)$/i', $v );
	}

	/** Does the last path segment look like a colour control? */
	public static function is_color_path( $path ) {
		$last = strtolower( (string) substr( strrchr( '.' . (string) $path, '.' ), 1 ) );
		return ( 'color' === $last || false !== strpos( $last, '_color' ) || false !== strpos( $last, 'color_' ) );
	}

	/* ------------------------------------------------------------- plan --- */

	/**
	 * Pure planner: everything except the kit lookup, so it can be pinned
	 * by tests against a fixture meta array.
	 *
	 * @return array|WP_Error
	 */
	public static function plan_from_meta( array $meta, $path, $value, $allow_create = false ) {
		$path = trim( (string) $path );
		if ( '' === $path ) {
			return new WP_Error( 'path_required', 'path is required, e.g. "link_normal_color" or "system_colors.primary.color". Call get_kit_settings to see what exists.', array( 'status' => 400 ) );
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return new WP_Error( 'value_not_scalar', 'value must be a string, number, or boolean. This tool flips one leaf of the kit; it does not restructure it.', array( 'status' => 400 ) );
		}
		if ( is_string( $value ) && strlen( $value ) > self::MAX_VALUE_LEN ) {
			return new WP_Error( 'value_too_long', sprintf( 'value exceeds %d characters.', self::MAX_VALUE_LEN ), array( 'status' => 400 ) );
		}
		if ( self::is_color_path( $path ) && ! self::is_valid_color( $value ) ) {
			return new WP_Error( 'invalid_color', sprintf( '"%s" is not a CSS colour Elementor can store (use #RRGGBB, rgba(), hsla(), or an empty string to reset).', (string) $value ), array( 'status' => 400 ) );
		}
		// Never let a caller replace a whole registry or a row.
		$read = self::resolve_path( $meta, $path );
		if ( $read['found'] && ( is_array( $read['value'] ) || is_object( $read['value'] ) ) ) {
			return new WP_Error( 'path_not_leaf', sprintf( 'Path "%s" points at a nested structure, not a single setting. Target a leaf such as "%s.color".', $path, $path ), array( 'status' => 400 ) );
		}
		if ( ! $read['found'] && ! $allow_create ) {
			return new WP_Error(
				'path_not_found',
				sprintf( 'Path "%s" does not exist in the kit. Check get_kit_settings; a mistyped key would be stored and never rendered, so the setting would LOOK changed while doing nothing. Pass allow_create=true only for a key Elementor actually reads (e.g. link_normal_color when no link colour was ever set).', $path ),
				array( 'status' => 404 )
			);
		}
		$prior     = $read['found'] ? $read['value'] : null;
		$prior_str = is_bool( $prior ) ? ( $prior ? 'true' : 'false' ) : (string) $prior;
		$new_str   = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
		if ( $read['found'] && $prior_str === $new_str ) {
			return new WP_Error( 'no_change', sprintf( 'Already set to "%s". Nothing to queue.', $new_str ), array( 'status' => 409 ) );
		}
		$written = self::write_path( $meta, $path, $value, $allow_create );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		$check = self::resolve_path( $written, $path );
		if ( ! $check['found'] ) {
			return new WP_Error( 'write_unverifiable', 'The planned write cannot be read back on the same path; refusing.' );
		}
		return array(
			'path'          => $path,
			'resolved_keys' => $check['keys'],
			'value'         => $value,
			'prior_value'   => $prior,
			'created_path'  => ! $read['found'],
			'meta_snapshot' => $meta,
		);
	}

	public static function build_plan( $args ) {
		$kit = self::kit_id();
		if ( $kit <= 0 ) {
			return new WP_Error( 'no_kit', 'Elementor has no active kit on this site.', array( 'status' => 404 ) );
		}
		$meta = get_post_meta( $kit, self::META, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$plan = self::plan_from_meta(
			$meta,
			isset( $args['path'] ) ? $args['path'] : '',
			array_key_exists( 'value', (array) $args ) ? $args['value'] : null,
			! empty( $args['allow_create'] )
		);
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$plan['kit_id'] = $kit;
		return $plan;
	}

	/* ------------------------------------------------------------ apply --- */

	public static function apply_plan( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['kit_id'] ) || empty( $payload['path'] ) ) {
			return new WP_Error( 'kit_plan_invalid', 'Stored plan has no kit_id/path.' );
		}
		$kit = (int) $payload['kit_id'];
		if ( self::kit_id() !== $kit ) {
			return new WP_Error( 'kit_changed', sprintf( 'The active kit is no longer #%d; refusing to write a stale plan.', $kit ) );
		}
		$meta = get_post_meta( $kit, self::META, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$updated = self::write_path( $meta, (string) $payload['path'], $payload['value'], ! empty( $payload['created_path'] ) );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		update_post_meta( $kit, self::META, $updated );
		// Read back from the database, not the object cache: with a persistent
		// cache (SiteGround Memcached) a bulk approve that already ran a cache
		// flush earlier in the same request can hand back the pre-write meta and
		// mark a landed write as failed (Lufkin 780/781, 2026-08-26).
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $kit, 'post_meta' );
		}
		$back = self::resolve_path( (array) get_post_meta( $kit, self::META, true ), (string) $payload['path'] );
		$want = is_bool( $payload['value'] ) ? ( $payload['value'] ? 'true' : 'false' ) : (string) $payload['value'];
		$got  = $back['found'] ? ( is_bool( $back['value'] ) ? ( $back['value'] ? 'true' : 'false' ) : (string) $back['value'] ) : null;
		if ( $got !== $want ) {
			return new WP_Error( 'kit_write_failed', sprintf( 'update_post_meta on the kit did not take effect (wanted %d chars, read back %s).', strlen( $want ), null === $got ? 'nothing' : strlen( $got ) . ' chars' ) );
		}
		self::flush();
		return true;
	}

	public static function revert_plan( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['kit_id'] ) ) {
			return new WP_Error( 'kit_plan_invalid', 'Stored plan has no kit_id to revert.' );
		}
		if ( ! array_key_exists( 'meta_snapshot', $payload ) || ! is_array( $payload['meta_snapshot'] ) ) {
			return new WP_Error( 'no_snapshot', 'Plan carries no prior kit snapshot; refusing to guess.' );
		}
		update_post_meta( (int) $payload['kit_id'], self::META, $payload['meta_snapshot'] );
		self::flush();
		return true;
	}

	/**
	 * Kit changes live in Elementor's generated global CSS; without a flush
	 * the front end keeps the old colour until the file cache expires.
	 */
	private static function flush() {
		if ( class_exists( 'CC_Assistant_Apply' ) && method_exists( 'CC_Assistant_Apply', 'flush_elementor_css_cache' ) ) {
			CC_Assistant_Apply::flush_elementor_css_cache();
		}
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				$plugin = \Elementor\Plugin::$instance;
				if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
					$plugin->files_manager->clear_cache();
				}
			} catch ( \Throwable $e ) { // phpcs:ignore
				// Best effort; the verify step will say if the colour did not land.
			}
		}
		if ( class_exists( 'CC_Assistant_Cache' ) && method_exists( 'CC_Assistant_Cache', 'flush_all' ) ) {
			try {
				CC_Assistant_Cache::flush_all();
			} catch ( \Throwable $e ) { // phpcs:ignore
			}
		}
	}
}
