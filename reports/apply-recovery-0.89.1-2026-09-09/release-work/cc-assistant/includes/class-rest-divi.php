<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Divi module I/O — surgical, module-level editing for Divi-built posts.
 *
 * Divi stores the whole page as one nested shortcode string. Before this
 * class, the only way to edit a Divi post was draft_update_post_content
 * with the FULL body: the model had to hand-transcribe 10-20KB of
 * shortcodes to change one paragraph, which was slow, dropped characters,
 * and tripped the deletion_ratio lint. These endpoints parse the shortcode
 * tree server-side so the caller addresses ONE module by index and sends
 * only the new inner HTML (or attribute changes) — the server rebuilds the
 * body and hands it to the normal lint + outline + pending-changes queue.
 *
 * Lives in its own file so it can be added without touching
 * class-rest-api.php (where parallel work happens).
 */
class CC_Assistant_REST_Divi {

	const REST_NAMESPACE = 'cc-assistant/v1';

	/**
	 * Container types whose inner content is other modules, not HTML.
	 * inner_content edits are refused on these; set_attrs is still allowed.
	 */
	const STRUCTURAL_TYPES = array(
		'et_pb_section',
		'et_pb_row',
		'et_pb_row_inner',
		'et_pb_column',
		'et_pb_column_inner',
	);

	const MAX_EDITS = 30;

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/divi/modules',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list_modules' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/divi/module',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_module' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/divi/update-modules',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_update_modules' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/divi/remove-module',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_remove_module' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error(
				'rest_forbidden',
				'You do not have permission to use CC Assistant.',
				array( 'status' => 403 )
			);
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Parser
	 * ------------------------------------------------------------------ */

	/**
	 * Shortcode-name prefixes treated as Divi modules. Covers core Divi
	 * (et_pb_) and the common third-party module ecosystems seen on client
	 * sites (Divi Plus dipl_, Divi Supreme dsm_, Divi Flash difl_, Divi
	 * Machine dvmd_). Filterable for exotic module packs.
	 */
	private static function module_prefixes() {
		return apply_filters(
			'cc_assistant_divi_module_prefixes',
			array( 'et_pb_', 'dipl_', 'dsm_', 'difl_', 'dvmd_' )
		);
	}

	private static function is_module_tag( $tag ) {
		foreach ( self::module_prefixes() as $prefix ) {
			if ( 0 === strpos( $tag, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Tokenize a Divi shortcode body into a flat, document-ordered module
	 * list. Each entry:
	 *   index       int    position in document order (stable per parse)
	 *   type        string shortcode tag, e.g. et_pb_text
	 *   depth       int    nesting depth (section=0)
	 *   structural  bool   container (section/row/column)
	 *   open_start  int    byte offset of '[' of the open tag
	 *   open_end    int    byte offset just past ']' of the open tag
	 *   inner_start int    byte offset where inner content begins
	 *   inner_end   int    byte offset where inner content ends
	 *   close_end   int    byte offset just past ']' of the close tag
	 *   attrs_str   string raw attribute string from the open tag
	 *
	 * Unclosed modules are dropped (defensive: malformed bodies should not
	 * fatal, they just parse shallower).
	 */
	public static function parse_modules( $content ) {
		$modules = array();
		$stack   = array();

		if ( ! preg_match_all(
			'/\[(\/?)([a-zA-Z0-9_]+)((?:\s+[a-zA-Z0-9_\-]+="[^"]*")*)\s*\]/s',
			$content,
			$matches,
			PREG_OFFSET_CAPTURE | PREG_SET_ORDER
		) ) {
			return $modules;
		}

		foreach ( $matches as $m ) {
			$full_tag = $m[0][0];
			$offset   = $m[0][1];
			$closing  = '' !== $m[1][0];
			$tag      = $m[2][0];
			$attrs    = isset( $m[3] ) ? $m[3][0] : '';

			if ( ! self::is_module_tag( $tag ) ) {
				continue;
			}

			if ( ! $closing ) {
				$stack[] = array(
					'type'       => $tag,
					'open_start' => $offset,
					'open_end'   => $offset + strlen( $full_tag ),
					'attrs_str'  => $attrs,
					'depth'      => count( $stack ),
				);
				continue;
			}

			// Close tag: pop until we find the matching open (skips any
			// unclosed intermediates instead of corrupting the pairing).
			while ( ! empty( $stack ) ) {
				$open = array_pop( $stack );
				if ( $open['type'] === $tag ) {
					$modules[] = array(
						'type'        => $open['type'],
						'depth'       => $open['depth'],
						'structural'  => in_array( $open['type'], self::STRUCTURAL_TYPES, true ),
						'open_start'  => $open['open_start'],
						'open_end'    => $open['open_end'],
						'inner_start' => $open['open_end'],
						'inner_end'   => $offset,
						'close_end'   => $offset + strlen( $full_tag ),
						'attrs_str'   => $open['attrs_str'],
					);
					break;
				}
			}
		}

		// Document order = open-tag offset order, then assign stable indexes.
		usort(
			$modules,
			function ( $a, $b ) {
				return $a['open_start'] - $b['open_start'];
			}
		);
		foreach ( $modules as $i => &$mod ) {
			$mod['index'] = $i;
		}
		unset( $mod );

		return $modules;
	}

	/**
	 * Count OPEN module tags in raw content. parse_modules() silently drops
	 * unclosed opens (defensive), so opens_count - parsed_count = orphan
	 * count. The update handler refuses edits that grow the orphan count —
	 * that is how a stray unclosed shortcode in inner_content gets caught.
	 */
	private static function count_module_opens( $content ) {
		if ( ! preg_match_all( '/\[([a-zA-Z0-9_]+)(?=[\s\]])/', $content, $m ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $m[1] as $tag ) {
			if ( self::is_module_tag( $tag ) ) {
				$n++;
			}
		}
		return $n;
	}

	private static function parse_attrs( $attrs_str ) {
		$attrs = array();
		if ( preg_match_all( '/([a-zA-Z0-9_\-]+)="([^"]*)"/s', (string) $attrs_str, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $pair ) {
				$attrs[ $pair[1] ] = $pair[2];
			}
		}
		return $attrs;
	}

	/**
	 * Human-oriented preview of a module for the inventory listing: strip
	 * tags, collapse whitespace, truncate. For dipl_fancy_text the visible
	 * text lives in the fancy_text ATTR (heading modules), so surface that.
	 */
	private static function module_preview( $content, $mod ) {
		$attrs = self::parse_attrs( $mod['attrs_str'] );

		$text = '';
		if ( isset( $attrs['fancy_text'] ) ) {
			$text = $attrs['fancy_text'];
		} elseif ( ! $mod['structural'] ) {
			$text = substr( $content, $mod['inner_start'], $mod['inner_end'] - $mod['inner_start'] );
		}
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
		if ( function_exists( 'mb_substr' ) ) {
			$preview = mb_substr( $text, 0, 140 );
		} else {
			$preview = substr( $text, 0, 140 );
		}

		// Headings inside inner HTML (et_pb_text bodies often embed h2/h3).
		$headings = array();
		$inner    = $mod['structural'] ? '' : substr( $content, $mod['inner_start'], $mod['inner_end'] - $mod['inner_start'] );
		$scan     = $inner . ' ' . ( isset( $attrs['fancy_text'] ) ? $attrs['fancy_text'] : '' );
		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $scan, $hm, PREG_SET_ORDER ) ) {
			foreach ( $hm as $h ) {
				$headings[] = 'h' . $h[1] . ': ' . trim( wp_strip_all_tags( $h[2] ) );
			}
		}

		return array( $preview, $headings );
	}

	/**
	 * Absolute, protocol-relative or root-relative URL. Deliberately strict:
	 * a bare "contact" in a link attr is not resolvable from here, and
	 * guessing would put made-up URLs in the inventory.
	 */
	private static function looks_like_url( $value ) {
		return (bool) preg_match( '#^(https?:)?//#i', $value ) || 0 === strpos( $value, '/wp-content/' );
	}

	private static function get_target_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		if ( false === strpos( (string) $post->post_content, '[et_pb_section' ) ) {
			return new WP_Error(
				'not_divi',
				'Post ' . $post_id . ' has no Divi shortcode content ([et_pb_section] not found). Use draft_update_post_content / draft_patch_post_content for classic posts, or the Elementor tools for Elementor pages.',
				array( 'status' => 422 )
			);
		}
		return $post;
	}

	/* ---------------------------------------------------------------------
	 * GET /divi/modules — module inventory (the "table of contents")
	 * ------------------------------------------------------------------ */

	public static function handle_list_modules( WP_REST_Request $req ) {
		$post = self::get_target_post( (int) $req->get_param( 'post_id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$content = (string) $post->post_content;
		$mods    = self::parse_modules( $content );

		$out = array();
		foreach ( $mods as $mod ) {
			list( $preview, $headings ) = self::module_preview( $content, $mod );
			$attrs = self::parse_attrs( $mod['attrs_str'] );

			$row = array(
				'index'      => $mod['index'],
				'type'       => $mod['type'],
				'depth'      => $mod['depth'],
				'structural' => $mod['structural'],
				'attr_keys'  => array_keys( $attrs ),
			);

			// Attribute VALUES that carry content, surfaced for every module
			// including structural ones. A section's background_image and a
			// button's link are content, but they live on the open tag, so a
			// key-only listing hid them and the only way to learn which
			// section used which banner was to read the rendered CSS.
			$media = array();
			$links = array();
			foreach ( $attrs as $name => $value ) {
				if ( ! is_string( $value ) || '' === $value || ! self::looks_like_url( $value ) ) {
					continue;
				}
				if ( preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|mp4|webm|ogg)(\?|$)/i', $value ) ) {
					$media[ $name ] = $value;
				} else {
					$links[ $name ] = $value;
				}
			}
			if ( $media ) {
				$row['media'] = $media;
			}
			if ( $links ) {
				$row['links'] = $links;
			}

			// Visible text stored in attributes rather than inner content.
			// No pattern can find these, so they are named explicitly.
			foreach ( array( 'title', 'heading', 'header', 'button_text', 'button_one_text', 'button_two_text', 'quote_author', 'author', 'job_title' ) as $text_attr ) {
				if ( isset( $attrs[ $text_attr ] ) && '' !== $attrs[ $text_attr ] ) {
					$row['text_attrs'][ $text_attr ] = $attrs[ $text_attr ];
				}
			}

			if ( ! $mod['structural'] ) {
				$row['inner_length'] = $mod['inner_end'] - $mod['inner_start'];
				$row['preview']      = $preview;
				if ( $headings ) {
					$row['headings'] = $headings;
				}
				// Typography attrs surfaced inline because they are the ones
				// operators ask about ("why is everything bold?"): Divi font
				// shorthand is family|weight|style|... so |600| in slot 2 =
				// semi-bold body text.
				foreach ( array( 'text_font', 'body_font', 'header_font' ) as $font_attr ) {
					if ( isset( $attrs[ $font_attr ] ) && '' !== $attrs[ $font_attr ] ) {
						$row[ $font_attr ] = $attrs[ $font_attr ];
					}
				}
				if ( isset( $attrs['src'] ) ) {
					$row['src'] = $attrs['src'];
				}
				if ( isset( $attrs['alt'] ) ) {
					$row['alt'] = $attrs['alt'];
				}
			}
			$out[] = $row;
		}

		return self::wrap(
			array(
				'post_id'        => $post->ID,
				'module_count'   => count( $out ),
				'content_length' => strlen( $content ),
				'modules'        => $out,
				'hint'           => 'Edit with draft_update_divi_modules({post_id, edits:[{index, type, inner_content?|set_attrs?}]}). Indexes are positions in THIS parse — re-list after an approved edit is applied. Heading modules (dipl_fancy_text) hold their text in the fancy_text attr, not inner content.',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * GET /divi/module — one module, full fidelity
	 * ------------------------------------------------------------------ */

	public static function handle_get_module( WP_REST_Request $req ) {
		$post = self::get_target_post( (int) $req->get_param( 'post_id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$index = (int) $req->get_param( 'index' );

		$content = (string) $post->post_content;
		$mods    = self::parse_modules( $content );
		if ( ! isset( $mods[ $index ] ) ) {
			return new WP_Error(
				'module_not_found',
				sprintf( 'Module index %d out of range (post has %d modules). Call list_divi_modules for the current inventory.', $index, count( $mods ) ),
				array( 'status' => 404 )
			);
		}
		$mod = $mods[ $index ];

		return self::wrap(
			array(
				'post_id'       => $post->ID,
				'index'         => $mod['index'],
				'type'          => $mod['type'],
				'depth'         => $mod['depth'],
				'structural'    => $mod['structural'],
				'attrs'         => self::parse_attrs( $mod['attrs_str'] ),
				'inner_content' => $mod['structural'] ? null : substr( $content, $mod['inner_start'], $mod['inner_end'] - $mod['inner_start'] ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * POST /divi/update-modules — surgical edit, queued as pending change
	 * ------------------------------------------------------------------ */

	public static function handle_update_modules( WP_REST_Request $req ) {
		$post = self::get_target_post( (int) $req->get_param( 'post_id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$edits = $req->get_param( 'edits' );
		if ( ! is_array( $edits ) || empty( $edits ) ) {
			return new WP_Error( 'edits_required', 'edits (array of {index, type, inner_content?|set_attrs?}) is required.', array( 'status' => 400 ) );
		}
		if ( count( $edits ) > self::MAX_EDITS ) {
			return new WP_Error( 'edits_too_many', 'Maximum ' . self::MAX_EDITS . ' edits per call. Split into multiple calls.', array( 'status' => 413 ) );
		}

		$content = (string) $post->post_content;
		$mods    = self::parse_modules( $content );

		// Validate every edit against the CURRENT parse before touching
		// anything — all-or-nothing, same philosophy as the patch endpoint.
		$normalized = array();
		$seen       = array();
		foreach ( $edits as $i => $edit ) {
			if ( ! is_array( $edit ) || ! isset( $edit['index'] ) || ! isset( $edit['type'] ) ) {
				return new WP_Error( 'edit_malformed', sprintf( 'Edit #%d must be an object with `index` and `type` keys.', $i ), array( 'status' => 400, 'edit_index' => $i ) );
			}
			$index = (int) $edit['index'];
			if ( ! isset( $mods[ $index ] ) ) {
				return new WP_Error( 'module_not_found', sprintf( 'Edit #%d: module index %d out of range (post has %d modules).', $i, $index, count( $mods ) ), array( 'status' => 404, 'edit_index' => $i ) );
			}
			if ( isset( $seen[ $index ] ) ) {
				return new WP_Error( 'edit_duplicate_index', sprintf( 'Edit #%d targets module %d which an earlier edit in this call already targets. Merge them into one edit.', $i, $index ), array( 'status' => 400, 'edit_index' => $i ) );
			}
			$seen[ $index ] = true;
			$mod            = $mods[ $index ];

			if ( (string) $edit['type'] !== $mod['type'] ) {
				return new WP_Error(
					'module_type_mismatch',
					sprintf( 'Edit #%d: module %d is %s, not %s. Your inventory is stale — call list_divi_modules again.', $i, $index, $mod['type'], $edit['type'] ),
					array( 'status' => 409, 'edit_index' => $i )
				);
			}

			$has_inner = array_key_exists( 'inner_content', $edit ) && null !== $edit['inner_content'];
			$has_attrs = isset( $edit['set_attrs'] ) && is_array( $edit['set_attrs'] ) && ! empty( $edit['set_attrs'] );
			if ( ! $has_inner && ! $has_attrs ) {
				return new WP_Error( 'edit_empty', sprintf( 'Edit #%d has neither inner_content nor set_attrs.', $i ), array( 'status' => 400, 'edit_index' => $i ) );
			}
			if ( $has_inner && $mod['structural'] ) {
				return new WP_Error(
					'structural_inner_edit',
					sprintf( 'Edit #%d: %s is a structural container; its inner content is other modules. Edit the child modules instead (set_attrs on the container is allowed).', $i, $mod['type'] ),
					array( 'status' => 422, 'edit_index' => $i )
				);
			}
			if ( $has_inner && preg_match( '/\[\/?et_pb_(section|row|column)/', (string) $edit['inner_content'] ) ) {
				return new WP_Error(
					'inner_contains_structure',
					sprintf( 'Edit #%d: inner_content contains section/row/column shortcodes. Module inner content is HTML only; layout changes go through the operator.', $i ),
					array( 'status' => 422, 'edit_index' => $i )
				);
			}
			if ( $has_attrs ) {
				foreach ( $edit['set_attrs'] as $name => $value ) {
					if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', (string) $name ) ) {
						return new WP_Error( 'attr_name_invalid', sprintf( 'Edit #%d: attribute name %s is invalid.', $i, $name ), array( 'status' => 400, 'edit_index' => $i ) );
					}
					if ( null !== $value && false !== strpos( (string) $value, '"' ) ) {
						return new WP_Error(
							'attr_value_quote',
							sprintf( 'Edit #%d: attribute %s contains a raw double quote. Divi encodes quotes inside attribute values as %%22.', $i, $name ),
							array( 'status' => 400, 'edit_index' => $i )
						);
					}
				}
			}

			$normalized[] = array(
				'edit_no'   => $i,
				'mod'       => $mod,
				'inner'     => $has_inner ? (string) $edit['inner_content'] : null,
				'set_attrs' => $has_attrs ? $edit['set_attrs'] : array(),
			);
		}

		// Apply in DESCENDING open_start order so earlier byte offsets stay
		// valid while we splice. Inner spans of distinct modules can nest
		// only when one is structural, and structural modules can only take
		// attr edits (open tag only) — so spans never overlap destructively.
		usort(
			$normalized,
			function ( $a, $b ) {
				return $b['mod']['open_start'] - $a['mod']['open_start'];
			}
		);

		$summary = array();
		foreach ( $normalized as $n ) {
			$mod = $n['mod'];

			if ( null !== $n['inner'] ) {
				$content = substr( $content, 0, $mod['inner_start'] ) . $n['inner'] . substr( $content, $mod['inner_end'] );
			}

			if ( ! empty( $n['set_attrs'] ) ) {
				$open_tag = substr( $content, $mod['open_start'], $mod['open_end'] - $mod['open_start'] );
				foreach ( $n['set_attrs'] as $name => $value ) {
					$pattern = '/\s+' . preg_quote( $name, '/' ) . '="[^"]*"/';
					// preg_replace treats $N and \N in the REPLACEMENT as
					// backreferences — an attr value like "<h2>Under $100</h2>"
					// would silently corrupt to "Under 0". Escape \ and $.
					$safe_attr = null === $value ? '' : addcslashes( ' ' . $name . '="' . $value . '"', '\\$' );
					if ( null === $value ) {
						$open_tag = preg_replace( $pattern, '', $open_tag, 1 );
					} elseif ( preg_match( $pattern, $open_tag ) ) {
						$open_tag = preg_replace( $pattern, $safe_attr, $open_tag, 1 );
					} else {
						$open_tag = preg_replace( '/\s*\]$/', $safe_attr . ']', $open_tag );
					}
				}
				$content = substr( $content, 0, $mod['open_start'] ) . $open_tag . substr( $content, $mod['open_end'] );
			}

			$summary[] = array(
				'edit_no'       => $n['edit_no'],
				'module_index'  => $mod['index'],
				'type'          => $mod['type'],
				'inner_changed' => null !== $n['inner'],
				'attrs_changed' => array_keys( $n['set_attrs'] ),
			);
		}

		// Sanity: the rebuilt body must still parse to the same module count
		// with the same type sequence, and must not introduce new unclosed
		// module tags. Catches malformed inner_content that would otherwise
		// silently eat later modules or leave stray shortcodes rendering.
		$orig_orphans = self::count_module_opens( (string) $post->post_content ) - count( $mods );
		$reparsed     = self::parse_modules( $content );
		$new_orphans  = self::count_module_opens( $content ) - count( $reparsed );
		if ( $new_orphans > $orig_orphans ) {
			return new WP_Error(
				'rebuild_parse_mismatch',
				sprintf( 'Rebuilt body contains %d unclosed Divi module tag(s) that the original did not have. An inner_content payload likely includes a shortcode open tag without its closer. Nothing was queued.', $new_orphans - $orig_orphans ),
				array( 'status' => 422 )
			);
		}
		if ( count( $reparsed ) !== count( $mods ) ) {
			return new WP_Error(
				'rebuild_parse_mismatch',
				sprintf( 'Rebuilt body parses to %d modules but the original had %d. An inner_content payload likely contains an unbalanced shortcode-like token. Nothing was queued.', count( $reparsed ), count( $mods ) ),
				array( 'status' => 422 )
			);
		}
		foreach ( $reparsed as $k => $rmod ) {
			if ( $rmod['type'] !== $mods[ $k ]['type'] ) {
				return new WP_Error(
					'rebuild_type_drift',
					sprintf( 'Rebuilt module #%d is %s but was %s. Nothing was queued.', $k, $rmod['type'], $mods[ $k ]['type'] ),
					array( 'status' => 422 )
				);
			}
		}

		// Attrs-only edits (font weights, alt text, spacing — no inner_content
		// in any edit) change ZERO visible prose, but the delegated handler
		// lints the whole rebuilt body, so PRE-EXISTING conditions in old
		// posts (plus raw shortcode syntax read as giant "sentences") hard-
		// block the queue. That is the wrong outcome for a no-text change:
		// auto-bypass the content lint and tell the reviewer why. Any edit
		// that touches inner_content still gets the full lint.
		$attrs_only = true;
		foreach ( $normalized as $n ) {
			if ( null !== $n['inner'] ) {
				$attrs_only = false;
				break;
			}
		}
		if ( $attrs_only && ! filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN ) ) {
			$req->set_param( 'override_lint', true );
		}

		// Delegate to the full-body handler: it owns lint, outline gating,
		// success_metrics, snapshots, and the pending-changes queue. Module
		// edits are surgical by construction, so the deletion_ratio and
		// outline gates naturally pass for normal edits.
		$req->set_param( 'content', $content );
		$response = CC_Assistant_REST_API::handle_draft_post_content( $req );

		usort(
			$summary,
			function ( $a, $b ) {
				return $a['edit_no'] - $b['edit_no'];
			}
		);
		if ( is_array( $response ) && isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$response['data']['divi_edits_applied'] = $summary;
		}
		return $response;
	}

	/**
	 * v0.70 — remove ONE module from a Divi body.
	 *
	 * Why this exists: update-modules can only rewrite a module's inner content
	 * or attributes, and it hard-refuses any edit that changes the module count
	 * (that guard is what catches malformed inner_content). So a leftover module
	 * — the classic case being an empty container someone dropped in and never
	 * configured — could only be removed by opening the layout in the builder.
	 * On a big Theme Builder layout that is exactly the operation that hangs.
	 *
	 * Deliberately narrow, for the same reason the inner_content path refuses
	 * structural edits: this removes ONE leaf module and its own subtree. It
	 * will not delete a section, row, or column, because that silently takes
	 * every child with it and is a layout decision, not a content edit.
	 *
	 * Removal is a real content deletion, so the normal lint (including
	 * deletion_ratio) still applies — no auto-bypass. Nothing is written; the
	 * rebuilt body goes through the same Pending Changes queue as every edit.
	 */
	public static function handle_remove_module( WP_REST_Request $req ) {
		$post = self::get_target_post( (int) $req->get_param( 'post_id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( null === $req->get_param( 'index' ) ) {
			return new WP_Error( 'index_required', 'index (from list_divi_modules) is required.', array( 'status' => 400 ) );
		}
		$index = (int) $req->get_param( 'index' );
		$type  = (string) $req->get_param( 'type' );
		if ( '' === $type ) {
			return new WP_Error( 'type_required', 'type is required and must match the module at that index — it is the guard against acting on a stale inventory.', array( 'status' => 400 ) );
		}

		$content = (string) $post->post_content;
		$mods    = self::parse_modules( $content );

		if ( ! isset( $mods[ $index ] ) ) {
			return new WP_Error( 'module_not_found', sprintf( 'Module index %d out of range (post has %d modules).', $index, count( $mods ) ), array( 'status' => 404 ) );
		}
		$mod = $mods[ $index ];

		if ( $type !== $mod['type'] ) {
			return new WP_Error(
				'module_type_mismatch',
				sprintf( 'Module %d is %s, not %s. Your inventory is stale — call list_divi_modules again.', $index, $mod['type'], $type ),
				array( 'status' => 409 )
			);
		}
		if ( $mod['structural'] ) {
			return new WP_Error(
				'structural_removal_refused',
				sprintf( 'Module %d is a %s — removing it would take every module inside it with it. Section/row/column removal is a layout change and goes through the operator in the builder.', $index, $mod['type'] ),
				array( 'status' => 422 )
			);
		}

		// Everything nested inside this module goes too. Count it up front so
		// the caller is told the true blast radius and the post-parse check
		// knows exactly how many modules should have disappeared.
		$descendants = array();
		foreach ( $mods as $other ) {
			if ( $other['index'] === $mod['index'] ) {
				continue;
			}
			if ( $other['open_start'] >= $mod['open_start'] && $other['close_end'] <= $mod['close_end'] ) {
				$descendants[] = $other['type'];
			}
		}
		if ( ! empty( $descendants ) && ! filter_var( $req->get_param( 'confirm_children' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return new WP_Error(
				'module_has_children',
				sprintf(
					'Module %d (%s) contains %d nested module(s): %s. Pass confirm_children=true to remove them as well.',
					$index,
					$mod['type'],
					count( $descendants ),
					implode( ', ', array_slice( $descendants, 0, 6 ) )
				),
				array( 'status' => 409, 'nested_modules' => $descendants )
			);
		}

		$removed_text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags(
			substr( $content, $mod['inner_start'], $mod['inner_end'] - $mod['inner_start'] )
		) ) );

		$rebuilt = substr( $content, 0, $mod['open_start'] ) . substr( $content, $mod['close_end'] );

		// Same all-or-nothing sanity as update-modules, with the expectation
		// inverted: exactly (1 + descendants) modules should be gone, the
		// orphan count must not grow, and every survivor must keep its type.
		$expected_removed = 1 + count( $descendants );
		$orig_orphans     = self::count_module_opens( $content ) - count( $mods );
		$reparsed         = self::parse_modules( $rebuilt );
		$new_orphans      = self::count_module_opens( $rebuilt ) - count( $reparsed );

		if ( $new_orphans > $orig_orphans ) {
			return new WP_Error(
				'rebuild_parse_mismatch',
				sprintf( 'Rebuilt body has %d more unclosed Divi tag(s) than the original. Nothing was queued.', $new_orphans - $orig_orphans ),
				array( 'status' => 422 )
			);
		}
		if ( count( $reparsed ) !== count( $mods ) - $expected_removed ) {
			return new WP_Error(
				'rebuild_parse_mismatch',
				sprintf(
					'Rebuilt body parses to %d modules; expected %d (%d original minus %d removed). Nothing was queued.',
					count( $reparsed ),
					count( $mods ) - $expected_removed,
					count( $mods ),
					$expected_removed
				),
				array( 'status' => 422 )
			);
		}
		$survivors = array();
		foreach ( $mods as $m ) {
			if ( $m['open_start'] >= $mod['open_start'] && $m['close_end'] <= $mod['close_end'] ) {
				continue;
			}
			$survivors[] = $m['type'];
		}
		foreach ( $reparsed as $k => $rmod ) {
			if ( ! isset( $survivors[ $k ] ) || $rmod['type'] !== $survivors[ $k ] ) {
				return new WP_Error(
					'rebuild_type_drift',
					sprintf( 'Rebuilt module #%d is %s but the surviving sequence expected %s. Nothing was queued.', $k, $rmod['type'], isset( $survivors[ $k ] ) ? $survivors[ $k ] : '(none)' ),
					array( 'status' => 422 )
				);
			}
		}

		$req->set_param( 'content', $rebuilt );
		$response = CC_Assistant_REST_API::handle_draft_post_content( $req );

		if ( is_array( $response ) && isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$response['data']['divi_module_removed'] = array(
				'index'           => $mod['index'],
				'type'            => $mod['type'],
				'nested_removed'  => $descendants,
				'had_inner_text'  => '' !== $removed_text,
				'inner_text'      => '' === $removed_text ? '' : mb_substr( $removed_text, 0, 160 ),
				'bytes_removed'   => $mod['close_end'] - $mod['open_start'],
				'modules_before'  => count( $mods ),
				'modules_after'   => count( $reparsed ),
			);
		}
		return $response;
	}

	private static function wrap( $data ) {
		return array(
			'site' => array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url(),
				'fingerprint' => CC_Assistant_Site_Identity::fingerprint(),
			),
			'data' => $data,
		);
	}
}
