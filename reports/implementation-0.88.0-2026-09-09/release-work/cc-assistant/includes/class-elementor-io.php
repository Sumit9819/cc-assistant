<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cross-site Elementor data I/O (v0.29.0).
 *
 * The existing builder tools (draft_add_elementor_container,
 * draft_update_elementor_widget, build_service_page) all operate WITHIN a
 * single site. There was no way to take a working page from site A and
 * port it to site B verbatim, which forced the AI to manually reconstruct
 * layouts from style samples — lossy, brittle, and the reason the
 * irvingwellnessclinic IV-Therapy template never made it to erofwhiterock
 * with its visual integrity intact.
 *
 * This class fixes that gap.
 *
 * Workflow:
 *   1. AI calls `export()` on the SOURCE site's MCP server → returns the
 *      full raw `_elementor_data` JSON string + structural counts.
 *   2. AI calls `import_to_pending()` on the TARGET site's MCP server,
 *      passing the raw JSON + transform options (text replacements, Kit
 *      globals mapping, widget-id regeneration, image stripping, etc.).
 *      Returns a single pending_id.
 *   3. Human approves the one pending change. The apply handler writes
 *      the new `_elementor_data`, snapshots the pre-state for rollback,
 *      and flushes Elementor's CSS cache.
 *
 * Cross-site clone of a 50-section page becomes 2 MCP calls + 1 approval
 * instead of 50+ individual container_add reconstructions that lose flex
 * layouts, hover states, responsive properties, and motion settings.
 */
class CC_Assistant_Elementor_IO {

	const CHANGE_TYPE = 'elementor_full_import';

	/**
	 * v0.30: Transparent 1×1 SVG used as the swap target when
	 * strip_images=true. Preserves layout dimensions (browser still
	 * reserves the image slot at the widget's height) instead of
	 * leaving an empty src that triggers the broken-image icon.
	 * Data URI so it works offline + doesn't depend on any third-party.
	 */
	const PLACEHOLDER_IMAGE_DATA_URI = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MDAiIGhlaWdodD0iNTAwIiB2aWV3Qm94PSIwIDAgODAwIDUwMCI+PHJlY3Qgd2lkdGg9IjgwMCIgaGVpZ2h0PSI1MDAiIGZpbGw9IiNGNEY0RjQiLz48dGV4dCB4PSI0MDAiIHk9IjI1MCIgZm9udC1mYW1pbHk9Ik1vbnRzZXJyYXQsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMjAiIGZvbnQtd2VpZ2h0PSI2MDAiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiPlVwbG9hZCBpbWFnZSBoZXJlPC90ZXh0Pjwvc3ZnPg==';

	const PLACEHOLDER_META_KEY = '_cc_image_placeholders';

	/**
	 * Read the raw _elementor_data for a post.
	 *
	 * Returns:
	 *   raw_data: string (the JSON blob as Elementor stores it)
	 *   section_count: int (root-level containers)
	 *   widget_count: int (every node with widgetType != container)
	 *   max_depth: int
	 *   has_edit_mode_builder: bool (whether the post is in Elementor mode)
	 *   kit_globals: array { color_tokens, font_tokens } from the source Kit
	 *     (so the importer can map by token-id at apply time)
	 */
	public static function export( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'post_id is required.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post %d not found.', $post_id ) );
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return new WP_Error( 'no_elementor_data', 'Post has no Elementor data to export.' );
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'invalid_json', 'Could not parse _elementor_data.' );
		}
		$edit_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );

		return array(
			'post_id'              => $post_id,
			'post_title'           => $post->post_title,
			'raw_data'             => $raw,
			'section_count'        => count( $tree ),
			'widget_count'         => self::count_widgets( $tree ),
			'max_depth'            => self::max_depth( $tree ),
			'has_edit_mode_builder' => ( 'builder' === $edit_mode ),
			'kit_globals'          => self::read_kit_globals(),
		);
	}

	/**
	 * Import a raw _elementor_data blob (typically from another site's
	 * export()) into a target post as a pending change.
	 *
	 * Options:
	 *   replacements (array of search=>replace, applied with str_ireplace
	 *     on every text-bearing widget field — title/editor/text/title_text/
	 *     description_text/html/content/link.url. Order matters: longer
	 *     phrases first.)
	 *   regenerate_ids (bool, default TRUE): assign fresh 8-char hex ids
	 *     to every element so cloned widgets don't collide with existing
	 *     widget ids on the target post.
	 *   strip_images (bool, default FALSE): clear image src URLs so the
	 *     operator can upload real photos instead of inheriting the
	 *     source site's CDN URLs.
	 *   map_kit_globals (bool, default TRUE): rewrite source Kit
	 *     `__globals__` references. System-color tokens (primary/secondary/
	 *     text/accent) keep their references (the target Kit will resolve
	 *     them). Custom-color/font tokens that don't exist on the target
	 *     are stripped so the widget's inline-hex fallback takes over.
	 *   source_kit_globals (array): the source site's kit_globals payload
	 *     from export() — used for custom-color resolution.
	 *   snapshot_first (bool, default TRUE): create a pre_import snapshot
	 *     of the target post's current _elementor_data so the human can
	 *     roll back in one click if the result looks wrong.
	 *   reasoning (string): human-visible reason shown in the pending
	 *     inbox. Pass a meaningful message — this is what the reviewer
	 *     sees when deciding whether to approve.
	 *   dry_run (bool, default FALSE): if true, return the transformed
	 *     summary without queueing a pending. Useful for previewing.
	 */
	public static function import_to_pending( $post_id, $raw_data, $options = array() ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'post_id is required.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Target post %d not found.', $post_id ) );
		}
		if ( ! is_string( $raw_data ) || '' === trim( $raw_data ) ) {
			return new WP_Error( 'empty_raw_data', 'raw_data must be a non-empty JSON string.' );
		}

		$options = wp_parse_args(
			$options,
			array(
				'replacements'         => array(),
				'regenerate_ids'       => true,
				// v0.47.1: default FALSE, matching the documented contract (docblock
				// above + MCP tool schema both said "default false" while this
				// defaulted true). Same-site rebuilds kept losing their images and
				// needing a manual restore batch after every import. Cross-site
				// clones should pass strip_images=true explicitly; even then,
				// same-domain URLs are kept (see strip_image_sources).
				'strip_images'         => false,
				'map_kit_globals'      => true,
				'source_kit_globals'   => array(),
				'snapshot_first'       => true,
				'reasoning'            => '',
				'dry_run'              => false,
				// v0.30 additions
				'strip_widget_ids'     => array(),
				'strip_widget_types'   => array(),
				'enforce_bold_headings' => true,
				'heading_weight'       => '700',
				// v0.31 additions
				'replace_in_inline_styles'     => true,  // also scrub <p style="color:#xxx"> in editor HTML
				'strip_cross_domain_schema_html' => true, // drop HTML widgets whose JSON-LD @id points to a foreign domain
				'color_remap'                  => array(), // {source_hex: target_hex} explicit color rewrites
				'sort_replacements_by_length'  => true,    // longest keys first to avoid substring overlap bugs
			)
		);

		// v0.31: auto-sort replacements by key-length descending so "Irving" is
		// never replaced before "irvingwellnessclinic" — which created the
		// "White Rockwellnessclinic" bug on the chest pain page clone.
		if ( ! empty( $options['replacements'] ) && is_array( $options['replacements'] )
			&& ! empty( $options['sort_replacements_by_length'] ) ) {
			$keys = array_keys( $options['replacements'] );
			usort( $keys, function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			} );
			$sorted = array();
			foreach ( $keys as $k ) {
				$sorted[ $k ] = $options['replacements'][ $k ];
			}
			$options['replacements'] = $sorted;
		}

		$tree = json_decode( $raw_data, true );
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'invalid_raw_data', 'raw_data did not parse to a JSON array.' );
		}

		$placeholders = array();
		$replacement_examples = array();
		$substring_overlap_warnings = array();

		// v0.31: detect replacement keys that are substrings of OTHER keys.
		// Even with sort_by_length, the operator should know about overlaps.
		if ( ! empty( $options['replacements'] ) && is_array( $options['replacements'] ) ) {
			$keys = array_keys( $options['replacements'] );
			foreach ( $keys as $i => $a ) {
				foreach ( $keys as $j => $b ) {
					if ( $i === $j ) {
						continue;
					}
					if ( strlen( $a ) < strlen( $b ) && false !== stripos( $b, $a ) ) {
						$substring_overlap_warnings[] = array(
							'shorter_key' => $a,
							'longer_key' => $b,
							'note' => 'shorter key is a substring of longer — auto-ordered longest-first so longer matches first',
						);
					}
				}
			}
		}

		$summary = array(
			'sections_before'      => count( $tree ),
			'widgets_before'       => self::count_widgets( $tree ),
			'replacements_applied' => 0,
			'ids_regenerated'      => 0,
			'images_stripped'      => 0,
			'images_kept_local'    => 0,
			'globals_kept'         => 0,
			'globals_stripped'     => 0,
			'widgets_subtree_dropped' => 0,
			'headings_bolded'      => 0,
			'inline_style_colors_remapped' => 0,
			'cross_domain_schema_widgets_stripped' => 0,
			'color_remap_applied' => 0,
			'replacement_overlap_warnings' => count( $substring_overlap_warnings ),
		);

		// v0.30: drop specific subtrees BEFORE other transforms run, so we
		// don't waste cycles processing widgets that are about to be removed.
		// strip_widget_ids: array of node ids to drop wholesale (e.g. the
		//   Irving pricing menu container id).
		// strip_widget_types: array of widgetType strings to drop everywhere
		//   (e.g. ['price-list', 'reviews']).
		if ( ! empty( $options['strip_widget_ids'] ) && is_array( $options['strip_widget_ids'] ) ) {
			$tree = self::strip_by_ids( $tree, $options['strip_widget_ids'], $summary );
		}
		if ( ! empty( $options['strip_widget_types'] ) && is_array( $options['strip_widget_types'] ) ) {
			$tree = self::strip_by_types( $tree, $options['strip_widget_types'], $summary );
		}

		// 1. Apply text replacements first so subsequent transforms see
		// the localized text (in case Kit token names get replaced too).
		if ( ! empty( $options['replacements'] ) && is_array( $options['replacements'] ) ) {
			self::apply_replacements( $tree, $options['replacements'], $summary, $replacement_examples );
		}

		// 1b. v0.31: scrub inline `style="color:#xxx"` in text-editor/html
		// content so source-Kit-resolved colors (e.g. Irving's green inside
		// editor HTML) don't survive the cross-site clone.
		if ( ! empty( $options['replace_in_inline_styles'] ) && ! empty( $options['color_remap'] ) ) {
			self::scrub_inline_html_colors( $tree, $options['color_remap'], $summary );
		}
		// And the bare widget-setting color fields (background_color, title_color, etc.)
		if ( ! empty( $options['color_remap'] ) ) {
			self::apply_widget_color_remap( $tree, $options['color_remap'], $summary );
		}

		// 1c. v0.31: strip HTML widgets whose JSON-LD @id points to a foreign domain.
		if ( ! empty( $options['strip_cross_domain_schema_html'] ) ) {
			$current_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$tree = self::strip_cross_domain_schema( $tree, $current_host, $summary );
		}

		// 1b. v0.30: enforce bold headings (default true). The Kit's primary
		// typography token may be 400 weight; explicit per-widget weight
		// override ensures every heading renders bold regardless of Kit.
		if ( ! empty( $options['enforce_bold_headings'] ) ) {
			self::enforce_bold_headings( $tree, (string) $options['heading_weight'], $summary );
		}

		// 2. Map Kit globals (rewrite/strip __globals__ refs).
		if ( ! empty( $options['map_kit_globals'] ) ) {
			$source_globals = is_array( $options['source_kit_globals'] ) ? $options['source_kit_globals'] : array();
			$target_globals = self::read_kit_globals();
			self::map_kit_globals( $tree, $source_globals, $target_globals, $summary );
		}

		// 3. Strip image sources if requested (so operator uploads fresh
		// images instead of inheriting source-site CDN URLs). v0.30: now
		// REPLACES URLs with a transparent placeholder SVG (layout
		// dimensions preserved) AND tracks each stripped image in
		// $placeholders so list_image_placeholders() can return a
		// register of {widget_id, widget_type, alt_text, section_heading,
		// original_url} for the operator's image-replacement workflow.
		if ( ! empty( $options['strip_images'] ) ) {
			self::strip_image_sources( $tree, $summary, $placeholders );
		}

		// 4. Regenerate widget IDs so the clone doesn't collide with any
		// existing widget IDs on the target post. This is the default
		// because not doing it can corrupt the existing tree if the
		// target post has nodes with the same IDs (which they often do —
		// Elementor recycles short hex IDs across templates).
		if ( ! empty( $options['regenerate_ids'] ) ) {
			self::regenerate_ids( $tree, $summary );
		}

		$summary['sections_after'] = count( $tree );
		$summary['widgets_after']  = self::count_widgets( $tree );

		// v0.50 popup condition guard (warn-only): scan the incoming raw tree
		// for Elementor popup-open action links and flag any popup whose
		// display conditions do not cover the TARGET post — the imported
		// button would silently no-op on the live page. Never blocks.
		require_once CC_ASSISTANT_DIR . 'includes/class-popups.php';
		$popup_warnings = CC_Assistant_Popups::coverage_warnings_for_payload( $raw_data, $post_id );

		if ( ! empty( $options['dry_run'] ) ) {
			$dry = array(
				'dry_run'              => true,
				'summary'              => $summary,
				'preview_first_section_title' => self::find_first_heading_text( $tree ),
				'replacement_examples' => array_slice( $replacement_examples, 0, 10 ),
				'image_placeholders'   => $placeholders,
				'section_outline'      => self::section_outline( $tree ),
				'widget_type_inventory' => self::widget_type_inventory( $tree ),
			);
			if ( ! empty( $popup_warnings ) ) {
				$dry['warnings'] = $popup_warnings;
			}
			return $dry;
		}

		// Encode the transformed tree.
		$proposed_json = wp_json_encode( $tree );
		if ( false === $proposed_json ) {
			return new WP_Error( 'encode_failed', 'Transformed elementor data could not be JSON-encoded.' );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';

		$stats_line = sprintf(
			'%d sections, %d widgets. Replacements: %d. IDs regenerated: %d. Images stripped: %d.',
			$summary['sections_after'],
			$summary['widgets_after'],
			$summary['replacements_applied'],
			$summary['ids_regenerated'],
			$summary['images_stripped']
		);
		// Callers like the rebuild-in-place path pass their own label so the
		// reviewer's inbox card reflects what they are actually approving.
		if ( ! empty( $options['change_summary'] ) && is_string( $options['change_summary'] ) ) {
			$change_summary = rtrim( $options['change_summary'], ' .' ) . ': ' . $stats_line;
		} else {
			$change_summary = 'Cross-site Elementor import: ' . $stats_line;
		}

		// We stash the snapshot intent + the transformed payload in the
		// proposed_value JSON so the apply handler can take a snapshot
		// before writing.
		$proposed_payload = array(
			'elementor_data'     => $tree,
			'snapshot_first'     => ! empty( $options['snapshot_first'] ),
			'summary'            => $summary,
			'image_placeholders' => $placeholders, // v0.30: written to postmeta on apply
		);

		$current_raw = (string) get_post_meta( $post_id, '_elementor_data', true );
		$current_payload = array(
			'elementor_data_existing_length' => strlen( $current_raw ),
			'existing_section_count'         => $current_raw ? count( (array) json_decode( $current_raw, true ) ) : 0,
		);

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => $post_id,
				'change_type'    => self::CHANGE_TYPE,
				'change_summary' => $change_summary,
				'current_value'  => wp_json_encode( $current_payload ),
				'proposed_value' => wp_json_encode( $proposed_payload ),
				'reasoning'      => (string) $options['reasoning'],
			)
		);

		if ( is_wp_error( $pending_id ) ) {
			return $pending_id;
		}

		$out = array(
			'pending_id' => (int) $pending_id,
			'summary'    => $summary,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
		);
		if ( ! empty( $popup_warnings ) ) {
			$out['warnings'] = $popup_warnings;
		}
		$claim_warnings = CC_Assistant_Pending_Changes::claim_removal_warnings( (int) $pending_id );
		if ( ! empty( $claim_warnings ) ) {
			$out['claim_removal_warnings'] = $claim_warnings;
		}
		// Superseding is silent by design and the hidden row keeps status='pending',
		// so a buried change looks identical to one that was never queued. Say it here.
		$superseded = CC_Assistant_Pending_Changes::superseded_notices( (int) $pending_id );
		if ( ! empty( $superseded ) ) {
			$out['superseded'] = $superseded;
		}
		return $out;
	}

	/**
	 * Called from class-apply.php when an `elementor_full_import` pending
	 * is approved. Takes a snapshot if requested, writes the transformed
	 * Elementor data, flushes Elementor's CSS cache, and graduates the
	 * post into builder mode if it wasn't already.
	 */
	public static function apply_import( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		if ( ! is_array( $proposed ) || empty( $proposed['elementor_data'] ) ) {
			return new WP_Error( 'invalid_payload', 'proposed_value must include elementor_data array.' );
		}

		// Pre-import snapshot for rollback.
		if ( ! empty( $proposed['snapshot_first'] ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
			if ( method_exists( 'CC_Assistant_Snapshots', 'snapshot_post' ) ) {
				$snapshot = CC_Assistant_Snapshots::snapshot_post( $post_id, 'pre_elementor_full_import', (int) $pending_id );
			if ( is_wp_error( $snapshot ) ) { return $snapshot; }
			}
		}

		$tree = $proposed['elementor_data'];
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'invalid_tree', 'elementor_data payload must be an array.' );
		}

		$json = wp_json_encode( $tree );
		if ( false === $json ) {
			return new WP_Error( 'encode_failed', 'Could not re-encode elementor_data — aborting to avoid corrupting the post.' );
		}

		require_once __DIR__ . '/class-elementor-builder.php';
		$saved = CC_Assistant_Elementor_Builder::save_tree( $post_id, $tree );
		if ( is_wp_error( $saved ) ) { return $saved; }
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		// v0.30: write the image placeholder registry to postmeta so the
		// operator can call list_image_placeholders(post_id) and see what
		// images need uploading + where they go.
		if ( ! empty( $proposed['image_placeholders'] ) && is_array( $proposed['image_placeholders'] ) ) {
			update_post_meta( $post_id, self::PLACEHOLDER_META_KEY, $proposed['image_placeholders'] );
		}

		// Flush Elementor's CSS cache so the result renders correctly.
		// In bulk mode the caller flushes once at the end.
		if ( ! $bulk_mode ) {
			if ( method_exists( 'CC_Assistant_Apply', 'flush_elementor_css_cache' ) ) {
				CC_Assistant_Apply::flush_elementor_css_cache();
			} elseif ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		}

		return true;
	}

	// ---- helpers --------------------------------------------------------

	private static function count_widgets( $tree ) {
		$count = 0;
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['elType'] ) && 'widget' === $node['elType'] ) {
				$count++;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$count += self::count_widgets( $node['elements'] );
			}
		}
		return $count;
	}

	private static function max_depth( $tree, $current = 1 ) {
		$max = $current;
		foreach ( (array) $tree as $node ) {
			if ( is_array( $node ) && ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$depth = self::max_depth( $node['elements'], $current + 1 );
				if ( $depth > $max ) {
					$max = $depth;
				}
			}
		}
		return $max;
	}

	private static function find_first_heading_text( $tree ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] && ! empty( $node['settings']['title'] ) ) {
				return (string) $node['settings']['title'];
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$found = self::find_first_heading_text( $node['elements'] );
				if ( '' !== $found ) {
					return $found;
				}
			}
		}
		return '';
	}

	/**
	 * Read the current site's Elementor Kit global colors + typography
	 * tokens. Used at export time so the import side knows what tokens to
	 * map FROM, and at import time to know what tokens the target site has.
	 */
	public static function read_kit_globals() {
		$kit_id = (int) get_option( 'elementor_active_kit', 0 );
		if ( $kit_id <= 0 ) {
			return array( 'colors' => array(), 'typography' => array() );
		}
		$kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $kit_settings ) ) {
			return array( 'colors' => array(), 'typography' => array() );
		}
		$colors = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
			if ( ! empty( $kit_settings[ $bucket ] ) && is_array( $kit_settings[ $bucket ] ) ) {
				foreach ( $kit_settings[ $bucket ] as $c ) {
					if ( ! empty( $c['_id'] ) && ! empty( $c['color'] ) ) {
						$colors[ (string) $c['_id'] ] = array(
							'title'  => isset( $c['title'] ) ? (string) $c['title'] : '',
							'color'  => (string) $c['color'],
							'source' => $bucket,
						);
					}
				}
			}
		}
		$typography = array();
		if ( ! empty( $kit_settings['system_typography'] ) && is_array( $kit_settings['system_typography'] ) ) {
			foreach ( $kit_settings['system_typography'] as $t ) {
				if ( ! empty( $t['_id'] ) ) {
					$typography[ (string) $t['_id'] ] = array(
						'title'       => isset( $t['title'] ) ? (string) $t['title'] : '',
						'font_family' => isset( $t['typography_font_family'] ) ? (string) $t['typography_font_family'] : '',
						'font_weight' => isset( $t['typography_font_weight'] ) ? (string) $t['typography_font_weight'] : '',
						'source'      => 'system_typography',
					);
				}
			}
		}
		return array( 'colors' => $colors, 'typography' => $typography );
	}

	/**
	 * Walk the tree and apply text replacements to every text-bearing field.
	 * Modifies $tree in place; updates $summary['replacements_applied'].
	 * v0.30: also collects up to N before/after examples into $examples
	 * so dry_run can preview which strings will actually change.
	 */
	private static function apply_replacements( &$tree, $replacements, &$summary, &$examples = array() ) {
		$text_fields = array( 'title', 'editor', 'text', 'title_text', 'description_text', 'html', 'content', 'item_title' );
		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				foreach ( $text_fields as $field ) {
					if ( isset( $node['settings'][ $field ] ) && is_string( $node['settings'][ $field ] ) ) {
						$before = $node['settings'][ $field ];
						foreach ( $replacements as $search => $replace ) {
							$node['settings'][ $field ] = str_ireplace( (string) $search, (string) $replace, $node['settings'][ $field ] );
						}
						if ( $before !== $node['settings'][ $field ] ) {
							$summary['replacements_applied']++;
							if ( count( $examples ) < 20 ) {
								$examples[] = array(
									'widget_type' => isset( $node['widgetType'] ) ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : '' ),
									'field'       => $field,
									'before'      => mb_substr( (string) $before, 0, 160 ),
									'after'       => mb_substr( (string) $node['settings'][ $field ], 0, 160 ),
								);
							}
						}
					}
				}
				// link.url
				if ( isset( $node['settings']['link']['url'] ) && is_string( $node['settings']['link']['url'] ) ) {
					$before = $node['settings']['link']['url'];
					foreach ( $replacements as $search => $replace ) {
						$node['settings']['link']['url'] = str_ireplace( (string) $search, (string) $replace, $node['settings']['link']['url'] );
					}
					if ( $before !== $node['settings']['link']['url'] ) {
						$summary['replacements_applied']++;
					}
				}
				// nested-accordion items[].item_title
				if ( isset( $node['settings']['items'] ) && is_array( $node['settings']['items'] ) ) {
					foreach ( $node['settings']['items'] as &$item ) {
						if ( isset( $item['item_title'] ) && is_string( $item['item_title'] ) ) {
							$before = $item['item_title'];
							foreach ( $replacements as $search => $replace ) {
								$item['item_title'] = str_ireplace( (string) $search, (string) $replace, $item['item_title'] );
							}
							if ( $before !== $item['item_title'] ) {
								$summary['replacements_applied']++;
							}
						}
					}
					unset( $item );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::apply_replacements( $node['elements'], $replacements, $summary, $examples );
			}
		}
		unset( $node );
	}

	/**
	 * Walk the tree and rewrite Kit `__globals__` references. System-color
	 * token ids (primary, secondary, text, accent, primary [typography],
	 * etc.) are kept verbatim because the target Kit has the same names.
	 * Custom token ids (8-char hex IDs that only exist on the source Kit)
	 * are stripped so the widget falls back to its inline-hex value.
	 */
	private static function map_kit_globals( &$tree, $source_globals, $target_globals, &$summary ) {
		// "system" tokens have stable string names that exist on every Kit.
		// Anything else is a custom token whose hex-id only exists on the
		// source site.
		$system_token_names = array( 'primary', 'secondary', 'text', 'accent' );
		$target_color_ids = isset( $target_globals['colors'] ) ? array_keys( $target_globals['colors'] ) : array();
		$target_typo_ids  = isset( $target_globals['typography'] ) ? array_keys( $target_globals['typography'] ) : array();

		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['settings']['__globals__'] ) && is_array( $node['settings']['__globals__'] ) ) {
				foreach ( $node['settings']['__globals__'] as $key => $ref ) {
					if ( ! is_string( $ref ) || '' === $ref ) {
						continue;
					}
					// Parse "globals/colors?id=XXX" or "globals/typography?id=XXX"
					if ( preg_match( '#^globals/([^?]+)\?id=(.+)$#', $ref, $m ) ) {
						$bucket = $m[1]; // colors | typography
						$id     = $m[2];
						$is_system = in_array( $id, $system_token_names, true );
						$exists_on_target = ( 'colors' === $bucket && in_array( $id, $target_color_ids, true ) )
							|| ( 'typography' === $bucket && in_array( $id, $target_typo_ids, true ) );
						if ( $is_system || $exists_on_target ) {
							// Keep the reference — the target Kit can resolve it.
							$summary['globals_kept']++;
							continue;
						}
						// Strip the unresolvable custom token reference. The
						// widget's inline-hex value (carried over from source)
						// will render as the fallback.
						unset( $node['settings']['__globals__'][ $key ] );
						$summary['globals_stripped']++;
					}
				}
				// Clean up if __globals__ is now empty.
				if ( empty( $node['settings']['__globals__'] ) ) {
					unset( $node['settings']['__globals__'] );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::map_kit_globals( $node['elements'], $source_globals, $target_globals, $summary );
			}
		}
		unset( $node );
	}

	/**
	 * Walk the tree and replace image source URLs with a transparent
	 * placeholder SVG so layout dimensions stay correct, then track each
	 * stripped image in $placeholders so list_image_placeholders() can
	 * tell the operator exactly which widget needs which photo.
	 *
	 * Affects:
	 * - image widget: settings.image.url + .id
	 * - container settings with background_image.url
	 * - icon-box widget: image-typed selected_icon
	 *
	 * v0.47.1: same-domain URLs are KEPT. The point of stripping is to avoid
	 * inheriting a foreign site's CDN URLs on a cross-site clone; an image
	 * already hosted on THIS site is a valid local attachment and stripping
	 * it only creates restore work. Such images are counted in
	 * summary.images_kept_local instead.
	 */
	private static function is_local_image_url( $url ) {
		$img_host  = wp_parse_url( (string) $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( empty( $img_host ) || empty( $home_host ) ) {
			return false;
		}
		return strtolower( $img_host ) === strtolower( $home_host );
	}

	private static function strip_image_sources( &$tree, &$summary, &$placeholders, $section_heading = '' ) {
		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			// Track the closest enclosing section heading so the placeholder
			// registry has actionable "this image goes near X heading" hints.
			$child_section_heading = $section_heading;
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType']
				&& ! empty( $node['settings']['title'] ) ) {
				$child_section_heading = (string) $node['settings']['title'];
			}

			if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				// Image widget
				if ( isset( $node['settings']['image']['url'] ) && '' !== $node['settings']['image']['url'] ) {
					if ( self::is_local_image_url( $node['settings']['image']['url'] ) ) {
						$summary['images_kept_local']++;
					} else {
					$placeholders[] = array(
						'widget_id'       => isset( $node['id'] ) ? (string) $node['id'] : '',
						'widget_type'     => 'image',
						'field'           => 'image.url',
						'alt_text'        => isset( $node['settings']['image']['alt'] ) ? (string) $node['settings']['image']['alt'] : '',
						'section_heading' => $section_heading,
						'original_url'    => (string) $node['settings']['image']['url'],
					);
					$node['settings']['image']['url'] = self::PLACEHOLDER_IMAGE_DATA_URI;
					$node['settings']['image']['id']  = 0;
					$summary['images_stripped']++;
					}
				}
				// Container background image
				if ( isset( $node['settings']['background_image']['url'] ) && '' !== $node['settings']['background_image']['url'] ) {
					if ( self::is_local_image_url( $node['settings']['background_image']['url'] ) ) {
						$summary['images_kept_local']++;
					} else {
					$placeholders[] = array(
						'widget_id'       => isset( $node['id'] ) ? (string) $node['id'] : '',
						'widget_type'     => isset( $node['elType'] ) ? (string) $node['elType'] : 'container',
						'field'           => 'background_image.url',
						'alt_text'        => '',
						'section_heading' => $section_heading,
						'original_url'    => (string) $node['settings']['background_image']['url'],
					);
					$node['settings']['background_image']['url'] = self::PLACEHOLDER_IMAGE_DATA_URI;
					$node['settings']['background_image']['id']  = 0;
					$summary['images_stripped']++;
					}
				}
				// Icon-box / image-box source image (when widget uses an
				// uploaded image instead of a Font Awesome icon).
				if ( isset( $node['settings']['selected_icon']['library'] )
					&& 'svg' === $node['settings']['selected_icon']['library']
					&& isset( $node['settings']['selected_icon']['value']['url'] )
					&& '' !== $node['settings']['selected_icon']['value']['url'] ) {
					if ( self::is_local_image_url( $node['settings']['selected_icon']['value']['url'] ) ) {
						$summary['images_kept_local']++;
					} else {
					$placeholders[] = array(
						'widget_id'       => isset( $node['id'] ) ? (string) $node['id'] : '',
						'widget_type'     => isset( $node['widgetType'] ) ? (string) $node['widgetType'] : 'svg-icon',
						'field'           => 'selected_icon.value.url',
						'alt_text'        => '',
						'section_heading' => $section_heading,
						'original_url'    => (string) $node['settings']['selected_icon']['value']['url'],
					);
					// v0.31 fix: use the placeholder data URI instead of empty string,
					// so layout doesn't collapse and operator can see where to upload.
					$node['settings']['selected_icon']['value']['url'] = self::PLACEHOLDER_IMAGE_DATA_URI;
					$summary['images_stripped']++;
					}
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::strip_image_sources( $node['elements'], $summary, $placeholders, $child_section_heading );
			}
		}
		unset( $node );
	}

	/**
	 * v0.30: drop entire subtrees by node id. Used to surgically remove
	 * Irving-specific sections (pricing menu, provider bio, IM/SQ injections)
	 * that should not carry to a non-wellness target site.
	 */
	private static function strip_by_ids( $tree, $ids_to_drop, &$summary ) {
		$drop_set = array_flip( array_map( 'strval', $ids_to_drop ) );
		$filtered = array();
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				$filtered[] = $node;
				continue;
			}
			if ( isset( $node['id'] ) && isset( $drop_set[ (string) $node['id'] ] ) ) {
				$summary['widgets_subtree_dropped'] += 1 + self::count_widgets( isset( $node['elements'] ) ? $node['elements'] : array() );
				continue;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$node['elements'] = self::strip_by_ids( $node['elements'], $ids_to_drop, $summary );
			}
			$filtered[] = $node;
		}
		return $filtered;
	}

	/**
	 * v0.30: drop every widget of a given widgetType (e.g. all "price-list"
	 * or all "reviews" widgets, anywhere in the tree).
	 */
	private static function strip_by_types( $tree, $types_to_drop, &$summary ) {
		$drop_set = array_flip( array_map( 'strval', $types_to_drop ) );
		$filtered = array();
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				$filtered[] = $node;
				continue;
			}
			if ( isset( $node['widgetType'] ) && isset( $drop_set[ (string) $node['widgetType'] ] ) ) {
				$summary['widgets_subtree_dropped']++;
				continue;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$node['elements'] = self::strip_by_types( $node['elements'], $types_to_drop, $summary );
			}
			$filtered[] = $node;
		}
		return $filtered;
	}

	/**
	 * v0.30: enforce bold headings. Every heading widget gets an explicit
	 * typography_font_weight regardless of what the source Kit defines.
	 * Headings inheriting weight from a 400-weight Kit primary token were
	 * reading as anemic on imported pages; this guarantees visual hierarchy.
	 */
	private static function enforce_bold_headings( &$tree, $weight, &$summary ) {
		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] ) {
				if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) {
					$node['settings'] = array();
				}
				$node['settings']['typography_typography']   = 'custom';
				$node['settings']['typography_font_weight']  = $weight;
				$summary['headings_bolded']++;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::enforce_bold_headings( $node['elements'], $weight, $summary );
			}
		}
		unset( $node );
	}

	/**
	 * v0.30: extract the H1/H2/H3 outline so dry_run reviewers can see
	 * the section structure at a glance.
	 */
	private static function section_outline( $tree, &$out = array() ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] ) {
				$level = isset( $node['settings']['header_size'] ) ? (string) $node['settings']['header_size'] : 'h2';
				$title = isset( $node['settings']['title'] ) ? (string) $node['settings']['title'] : '';
				if ( '' !== $title ) {
					$out[] = array( 'level' => $level, 'title' => mb_substr( $title, 0, 160 ) );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::section_outline( $node['elements'], $out );
			}
		}
		return $out;
	}

	/**
	 * v0.30: count widgets by widgetType so dry_run reviewers can see what
	 * shapes the imported tree will have (e.g. 14 icon-box, 6 button,
	 * 2 google_maps).
	 */
	private static function widget_type_inventory( $tree, &$out = array() ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) ) {
				$t = (string) $node['widgetType'];
				$out[ $t ] = isset( $out[ $t ] ) ? $out[ $t ] + 1 : 1;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::widget_type_inventory( $node['elements'], $out );
			}
		}
		arsort( $out );
		return $out;
	}

	/**
	 * v0.30: read the image placeholder registry written at apply time.
	 * Returns array of {widget_id, widget_type, field, alt_text,
	 * section_heading, original_url}.
	 */
	public static function list_image_placeholders( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'post_id required' );
		}
		$rows = get_post_meta( $post_id, self::PLACEHOLDER_META_KEY, true );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		return array(
			'post_id'           => $post_id,
			'placeholder_count' => count( $rows ),
			'placeholders'      => $rows,
		);
	}

	/**
	 * v0.31: Scrub inline `style="color:#xxx"` declarations inside
	 * text-editor/html widget content. The source Kit's resolved colors
	 * (e.g. Irving's green `#003017`) often end up baked into editor HTML
	 * via Elementor's TinyMCE color picker — invisible to widget-settings
	 * color fields but visible on render. Rewrites them per $color_remap
	 * (source-hex => target-hex). Handles hex and rgb()/rgba() notations.
	 */
	private static function scrub_inline_html_colors( &$tree, $color_remap, &$summary ) {
		if ( empty( $color_remap ) || ! is_array( $color_remap ) ) {
			return;
		}
		$norm_map = array();
		foreach ( $color_remap as $src => $dst ) {
			$src_hex = self::normalize_hex( $src );
			$dst_hex = self::normalize_hex( $dst );
			if ( '' !== $src_hex && '' !== $dst_hex ) {
				$norm_map[ $src_hex ] = $dst_hex;
			}
		}
		if ( empty( $norm_map ) ) {
			return;
		}
		$html_fields = array( 'editor', 'html', 'text', 'content', 'description_text', 'title_text' );
		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				foreach ( $html_fields as $f ) {
					if ( isset( $node['settings'][ $f ] ) && is_string( $node['settings'][ $f ] ) ) {
						$node['settings'][ $f ] = self::rewrite_inline_colors( $node['settings'][ $f ], $norm_map, $summary );
					}
				}
				if ( isset( $node['settings']['items'] ) && is_array( $node['settings']['items'] ) ) {
					foreach ( $node['settings']['items'] as &$item ) {
						if ( isset( $item['item_title'] ) && is_string( $item['item_title'] ) ) {
							$item['item_title'] = self::rewrite_inline_colors( $item['item_title'], $norm_map, $summary );
						}
					}
					unset( $item );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::scrub_inline_html_colors( $node['elements'], $color_remap, $summary );
			}
		}
		unset( $node );
	}

	private static function rewrite_inline_colors( $str, $norm_map, &$summary ) {
		if ( '' === $str || false === stripos( $str, 'color' ) ) {
			return $str;
		}
		$out = preg_replace_callback(
			'#((?:background-)?color\s*:\s*)(#[0-9a-fA-F]{3,8})#i',
			function ( $m ) use ( $norm_map, &$summary ) {
				$hex = self::normalize_hex( $m[2] );
				if ( '' !== $hex && isset( $norm_map[ $hex ] ) ) {
					$summary['inline_style_colors_remapped']++;
					return $m[1] . $norm_map[ $hex ];
				}
				return $m[0];
			},
			$str
		);
		$out = preg_replace_callback(
			'#((?:background-)?color\s*:\s*)(rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+(?:\s*,\s*[\d.]+)?\s*\))#i',
			function ( $m ) use ( $norm_map, &$summary ) {
				$hex = self::normalize_hex( $m[2] );
				if ( '' !== $hex && isset( $norm_map[ $hex ] ) ) {
					$summary['inline_style_colors_remapped']++;
					return $m[1] . $norm_map[ $hex ];
				}
				return $m[0];
			},
			$out
		);
		return null === $out ? $str : $out;
	}

	/**
	 * v0.31: Normalize hex/rgb/rgba to lowercase 6-char hex. Returns empty
	 * string if unparseable.
	 */
	private static function normalize_hex( $val ) {
		$val = trim( (string) $val );
		if ( '' === $val ) {
			return '';
		}
		if ( preg_match( '/^#([0-9a-fA-F]{3,8})$/', $val, $m ) ) {
			$h = strtolower( $m[1] );
			if ( 3 === strlen( $h ) ) {
				$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
			}
			if ( 8 === strlen( $h ) ) {
				$h = substr( $h, 0, 6 );
			}
			return '#' . $h;
		}
		if ( preg_match( '/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d.]+)?\s*\)$/i', $val, $m ) ) {
			$r = max( 0, min( 255, (int) $m[1] ) );
			$g = max( 0, min( 255, (int) $m[2] ) );
			$b = max( 0, min( 255, (int) $m[3] ) );
			return sprintf( '#%02x%02x%02x', $r, $g, $b );
		}
		return '';
	}

	/**
	 * v0.31: Apply $color_remap (source-hex => target-hex) to every common
	 * widget color setting. Catches background_color, title_color,
	 * description_color, button_text_color, border_color, primary_color,
	 * icon_color, text_color, header_color, caption_color, etc.
	 */
	private static function apply_widget_color_remap( &$tree, $color_remap, &$summary ) {
		if ( empty( $color_remap ) || ! is_array( $color_remap ) ) {
			return;
		}
		$norm_map = array();
		foreach ( $color_remap as $src => $dst ) {
			$src_hex = self::normalize_hex( $src );
			$dst_hex = self::normalize_hex( $dst );
			if ( '' !== $src_hex && '' !== $dst_hex ) {
				$norm_map[ $src_hex ] = $dst_hex;
			}
		}
		if ( empty( $norm_map ) ) {
			return;
		}
		$color_fields = array(
			'background_color', 'background_overlay_color',
			'title_color', 'description_color', 'header_color', 'caption_color',
			'text_color', 'primary_color', 'secondary_color',
			'icon_color', 'icon_primary_color', 'icon_secondary_color', 'icon_view_color',
			'button_text_color', 'button_background_color', 'background_hover_color',
			'hover_button_text_color', 'hover_color',
			'border_color', 'divider_color',
			'item_title_color', 'item_active_color',
			'_background_color', '_background_overlay_color',
		);
		self::walk_apply_color_remap( $tree, $color_fields, $norm_map, $summary );
	}

	private static function walk_apply_color_remap( &$tree, $color_fields, $norm_map, &$summary ) {
		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				foreach ( $color_fields as $f ) {
					if ( ! isset( $node['settings'][ $f ] ) ) {
						continue;
					}
					$v = $node['settings'][ $f ];
					if ( ! is_string( $v ) || '' === $v ) {
						continue;
					}
					$hex = self::normalize_hex( $v );
					if ( '' === $hex || ! isset( $norm_map[ $hex ] ) ) {
						continue;
					}
					$node['settings'][ $f ] = $norm_map[ $hex ];
					$summary['color_remap_applied']++;
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_apply_color_remap( $node['elements'], $color_fields, $norm_map, $summary );
			}
		}
		unset( $node );
	}

	/**
	 * v0.31: Drop html / text-editor widgets whose body contains a JSON-LD
	 * script with an @id (or url) pointing to a foreign domain. Source-site
	 * Person / FAQPage / MedicalClinic schemas with the source domain
	 * baked in will fail Google's structured-data tests on the target
	 * domain and (worse) link out to the source site if those URLs render.
	 */
	private static function strip_cross_domain_schema( $tree, $current_host, &$summary ) {
		$filtered = array();
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				$filtered[] = $node;
				continue;
			}
			$wt = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
			if ( in_array( $wt, array( 'html', 'text-editor', 'shortcode' ), true ) ) {
				$body = '';
				if ( isset( $node['settings']['html'] ) && is_string( $node['settings']['html'] ) ) {
					$body = $node['settings']['html'];
				} elseif ( isset( $node['settings']['editor'] ) && is_string( $node['settings']['editor'] ) ) {
					$body = $node['settings']['editor'];
				} elseif ( isset( $node['settings']['shortcode'] ) && is_string( $node['settings']['shortcode'] ) ) {
					$body = $node['settings']['shortcode'];
				}
				if ( '' !== $body && self::body_contains_foreign_schema( $body, $current_host ) ) {
					$summary['cross_domain_schema_widgets_stripped']++;
					continue;
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$node['elements'] = self::strip_cross_domain_schema( $node['elements'], $current_host, $summary );
			}
			$filtered[] = $node;
		}
		return $filtered;
	}

	private static function body_contains_foreign_schema( $body, $current_host ) {
		if ( false === stripos( $body, 'ld+json' ) ) {
			return false;
		}
		$current_host = strtolower( preg_replace( '#^www\.#i', '', (string) $current_host ) );
		if ( '' === $current_host ) {
			return false;
		}
		if ( ! preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $body, $matches ) ) {
			return false;
		}
		foreach ( $matches[1] as $json_blob ) {
			$data = json_decode( trim( $json_blob ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$hosts = array();
			self::collect_schema_hosts( $data, $hosts );
			foreach ( $hosts as $h ) {
				$h = strtolower( preg_replace( '#^www\.#i', '', (string) $h ) );
				if ( '' !== $h && $h !== $current_host ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function collect_schema_hosts( $node, &$out ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $k => $v ) {
			if ( in_array( $k, array( '@id', 'url', 'contentUrl', 'image', 'sameAs', 'mainEntityOfPage' ), true ) ) {
				if ( is_string( $v ) ) {
					$h = wp_parse_url( $v, PHP_URL_HOST );
					if ( $h ) {
						$out[] = $h;
					}
				} elseif ( is_array( $v ) ) {
					foreach ( $v as $item ) {
						if ( is_string( $item ) ) {
							$h = wp_parse_url( $item, PHP_URL_HOST );
							if ( $h ) {
								$out[] = $h;
							}
						} else {
							self::collect_schema_hosts( $item, $out );
						}
					}
				}
			} elseif ( is_array( $v ) ) {
				self::collect_schema_hosts( $v, $out );
			}
		}
	}

	/**
	 * Walk the tree and assign fresh 8-char hex ids to every element. The
	 * default behavior on import because Elementor recycles short hex ids
	 * across templates, and collision with existing target-post ids can
	 * silently corrupt the tree.
	 */
	private static function regenerate_ids( &$tree, &$summary ) {
		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['id'] ) ) {
				$node['id'] = substr( md5( uniqid( '', true ) . wp_rand() ), 0, 8 );
				$summary['ids_regenerated']++;
			}
			// nested-accordion items also have item_id values
			if ( isset( $node['settings']['items'] ) && is_array( $node['settings']['items'] ) ) {
				foreach ( $node['settings']['items'] as &$item ) {
					if ( isset( $item['item_id'] ) ) {
						$item['item_id'] = substr( md5( uniqid( '', true ) . wp_rand() ), 0, 8 );
					}
				}
				unset( $item );
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::regenerate_ids( $node['elements'], $summary );
			}
		}
		unset( $node );
	}
}
