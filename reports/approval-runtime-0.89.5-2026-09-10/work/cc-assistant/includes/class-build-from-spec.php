<?php
/**
 * Build a complete Elementor page from a CONTENT SPEC in one pass (v0.40).
 *
 * Part 2 of the page-quality engine. Where build_service_page CLONES an
 * existing pillar, this composes a NEW page from typed section templates
 * (hero / text_image / card_grid / steps / faq / cta_band / form / map) so the
 * assistant can ship a winning page in one call instead of 10+ freehand
 * container_adds. With target_post_id it instead REBUILDS an existing post in
 * place: same composition + lints, one elementor_full_import pending.
 *
 * The 2026 winning bar is built in, not advisory:
 *   - at least one real image is REQUIRED (hero background or section image)
 *   - card grids past 10 cards REQUIRE grouping (one sub-grid per group) —
 *     no more flat 22-card walls
 *   - FAQ sections render as real question-led accordions (AEO-extractable)
 *   - heading hierarchy is enforced structurally (one H1, H2 per section,
 *     H3 cards/groups)
 *   - section widths follow the global rules (root boxed ~1300px, heading +
 *     intro capped ~850px centered)
 *   - styles are SAMPLED from a well-built page on the same site
 *     (style_mirror_post_id) via the builder's brand-default pass — never
 *     hardcoded for one tenant
 *   - the full per-widget lint + placeholder + duplicate-card checks run
 *     BEFORE anything is created; the v0.39 publish gate re-checks at publish
 *
 * Output: a draft post + ONE publish_draft pending for one-click human review.
 * Draft-only, like every other write path.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Build_From_Spec {

	const TEMPLATES = array( 'hero', 'text_image', 'card_grid', 'steps', 'faq', 'cta_band', 'form', 'map' );

	/**
	 * Max homogeneous cards in one ungrouped grid before grouping is required.
	 * Matches the publish-gate flat-list threshold.
	 */
	const MAX_UNGROUPED_CARDS = 10;

	/**
	 * Entry point. See REST handler / MCP schema for the spec shape.
	 *
	 * @return array|WP_Error
	 */
	/**
	 * Button + FAQ-title styling SAMPLED from the style mirror so each site's
	 * own recipe (corner radius, padding, border weight, font) is matched
	 * instead of hardcoded. Defaults follow the global design skill; sample_recipe()
	 * overrides them from the mirror before composing.
	 */
	private static $btn_recipe = array(
		'radius' => '8', 'pad_v' => '16', 'pad_h' => '32', 'border' => '1',
		'size' => 16, 'weight' => '700', 'family' => '',
	);
	private static $faq_title = array( 'size' => 20, 'weight' => '600', 'family' => '' );

	/** Resolved kit brand roles (primary / secondary / neutral), lazy-cached. */
	private static $kit_palette = null;

	/**
	 * Resolve a brand color ROLE from the target site's Elementor Kit so built
	 * sections self-brand instead of shipping the ER sister-site palette
	 * (#DA1212 / #11468F / #F4F4F4). primary/secondary come from the kit's
	 * system tokens; neutral is a kit light/background/grey CUSTOM color when
	 * one exists. Falls back to the historical hardcoded values ONLY when the
	 * kit colors cannot be resolved.
	 */
	private static function kit_color( $role ) {
		if ( null === self::$kit_palette ) {
			$palette = array(
				'primary'   => '#DA1212',
				'secondary' => '#11468F',
				'neutral'   => '#F4F4F4',
			);
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';
			$kit    = CC_Assistant_Elementor_IO::read_kit_globals();
			$colors = isset( $kit['colors'] ) && is_array( $kit['colors'] ) ? $kit['colors'] : array();
			foreach ( array( 'primary', 'secondary' ) as $id ) {
				if ( ! empty( $colors[ $id ]['color'] ) ) {
					$palette[ $id ] = (string) $colors[ $id ]['color'];
				}
			}
			foreach ( $colors as $c ) {
				$is_custom = isset( $c['source'] ) && 'custom_colors' === $c['source'];
				$title     = isset( $c['title'] ) ? (string) $c['title'] : '';
				if ( $is_custom && ! empty( $c['color'] ) && preg_match( '/\b(light|background|bg|grey|gray|neutral)\b/i', $title ) ) {
					$palette['neutral'] = (string) $c['color'];
					break;
				}
			}
			self::$kit_palette = $palette;
		}
		return isset( self::$kit_palette[ $role ] ) ? self::$kit_palette[ $role ] : '';
	}

	/** Read the mirror's first button + first accordion and copy their structural recipe. */
	private static function sample_recipe( $mirror_id ) {
		$raw = get_post_meta( (int) $mirror_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return;
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return;
		}
		$btn = null;
		$acc = null;
		$walk = function ( $nodes ) use ( &$walk, &$btn, &$acc ) {
			foreach ( (array) $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$wt = $n['widgetType'] ?? '';
				$s  = isset( $n['settings'] ) && is_array( $n['settings'] ) ? $n['settings'] : array();
				if ( 'button' === $wt && null === $btn && ! empty( $s ) ) {
					$btn = $s;
				}
				if ( in_array( $wt, array( 'accordion', 'toggle', 'nested-accordion' ), true ) && null === $acc && ! empty( $s ) ) {
					$acc = $s;
				}
				if ( ! empty( $n['elements'] ) ) {
					$walk( $n['elements'] );
				}
			}
		};
		$walk( $tree );
		if ( is_array( $btn ) ) {
			if ( isset( $btn['border_radius']['top'] ) ) { self::$btn_recipe['radius'] = (string) $btn['border_radius']['top']; }
			if ( isset( $btn['text_padding']['top'] ) ) { self::$btn_recipe['pad_v'] = (string) $btn['text_padding']['top']; }
			if ( isset( $btn['text_padding']['right'] ) ) { self::$btn_recipe['pad_h'] = (string) $btn['text_padding']['right']; }
			if ( isset( $btn['border_width']['top'] ) ) { self::$btn_recipe['border'] = (string) $btn['border_width']['top']; }
			if ( isset( $btn['typography_font_size']['size'] ) ) { self::$btn_recipe['size'] = $btn['typography_font_size']['size']; }
			if ( ! empty( $btn['typography_font_weight'] ) ) { self::$btn_recipe['weight'] = (string) $btn['typography_font_weight']; }
			if ( ! empty( $btn['typography_font_family'] ) ) { self::$btn_recipe['family'] = (string) $btn['typography_font_family']; }
		}
		if ( is_array( $acc ) ) {
			// Only adopt the mirror's title size when it is a sane PX value.
			// Mirrors with em-scale sizes (e.g. 1.1em) used to be copied verbatim
			// and re-emitted with a px unit — 1.1 PIXELS, invisible questions.
			if ( isset( $acc['title_typography_font_size']['size'] )
				&& 'px' === ( $acc['title_typography_font_size']['unit'] ?? 'px' )
				&& (float) $acc['title_typography_font_size']['size'] >= 10 ) {
				self::$faq_title['size'] = $acc['title_typography_font_size']['size'];
			}
			if ( ! empty( $acc['title_typography_font_weight'] ) ) { self::$faq_title['weight'] = (string) $acc['title_typography_font_weight']; }
			if ( ! empty( $acc['title_typography_font_family'] ) ) { self::$faq_title['family'] = (string) $acc['title_typography_font_family']; }
		}
	}

	public static function build( array $args ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-build-service-page.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-popups.php';

		$title           = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
		$slug            = isset( $args['slug'] ) ? trim( (string) $args['slug'] ) : '';
		$post_type       = isset( $args['post_type'] ) ? (string) $args['post_type'] : 'page';
		$style_mirror    = isset( $args['style_mirror_post_id'] ) ? (int) $args['style_mirror_post_id'] : 0;
		$sections        = isset( $args['sections'] ) && is_array( $args['sections'] ) ? $args['sections'] : array();
		$seo             = isset( $args['seo'] ) && is_array( $args['seo'] ) ? $args['seo'] : array();
		$dry_run         = ! empty( $args['dry_run'] );
		$override_lint   = ! empty( $args['override_lint'] );
		$override_images = ! empty( $args['override_images'] );
		$target_post_id  = isset( $args['target_post_id'] ) ? (int) $args['target_post_id'] : 0;

		if ( '' === $title ) {
			return new WP_Error( 'invalid_payload', 'title is required.', array( 'status' => 400 ) );
		}
		if ( empty( $sections ) ) {
			return new WP_Error( 'invalid_payload', 'sections[] is required — at least a hero plus one content section.', array( 'status' => 400 ) );
		}
		if ( $style_mirror <= 0 ) {
			return new WP_Error(
				'style_mirror_required',
				'style_mirror_post_id is required: pass a well-built page on THIS site so headings, fonts, and card styles are sampled from it (never hardcoded). Use suggest_best_mirror to pick one.',
				array( 'status' => 400 )
			);
		}
		if ( ! get_post( $style_mirror ) ) {
			return new WP_Error( 'style_mirror_not_found', sprintf( 'style_mirror_post_id %d not found.', $style_mirror ), array( 'status' => 404 ) );
		}
		$allowed_types = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		if ( ! in_array( $post_type, (array) $allowed_types, true ) ) {
			return new WP_Error( 'post_type_not_allowed', 'post_type is not in the allowlist.', array( 'status' => 403 ) );
		}

		// target_post_id = rebuild-in-place. Same composition + lints, but
		// instead of creating a new draft, ONE elementor_full_import pending is
		// queued against the target post (validated up front so a bad id fails
		// before anything is composed).
		if ( $target_post_id > 0 ) {
			$target = get_post( $target_post_id );
			if ( ! $target ) {
				return new WP_Error( 'target_post_not_found', sprintf( 'target_post_id %d not found.', $target_post_id ), array( 'status' => 404 ) );
			}
			if ( ! in_array( $target->post_type, (array) $allowed_types, true ) ) {
				return new WP_Error( 'target_post_not_allowed', sprintf( 'target_post_id %d is a "%s", which is not in the editable post-type allowlist.', $target_post_id, $target->post_type ), array( 'status' => 403 ) );
			}
			if ( in_array( $target->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				return new WP_Error( 'target_post_not_editable', sprintf( 'target_post_id %d has status "%s" and cannot be rebuilt.', $target_post_id, $target->post_status ), array( 'status' => 400 ) );
			}
		}

		// ------------------------------------------------------------------
		// 1. Validate every section spec BEFORE composing anything, so errors
		//    are precise ("sections[3] (card_grid): ...") and nothing half-builds.
		// ------------------------------------------------------------------
		$image_count = 0;
		$h1_count    = 0;
		foreach ( $sections as $i => $s ) {
			$tpl = isset( $s['template'] ) ? (string) $s['template'] : '';
			if ( ! in_array( $tpl, self::TEMPLATES, true ) ) {
				return new WP_Error(
					'unknown_template',
					sprintf( 'sections[%d]: unknown template "%s". Supported: %s.', $i, $tpl, implode( ', ', self::TEMPLATES ) ),
					array( 'status' => 400 )
				);
			}
			$err = self::validate_section( $tpl, $s, $i, $image_count, $h1_count );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		}
		// Utility/conversion pages (any form or map section) only need ONE image
		// — a hero background is enough for a contact-style page. Content pages
		// keep the two-image visual-rhythm bar.
		$is_utility_page = false;
		foreach ( (array) $sections as $s ) {
			if ( isset( $s['template'] ) && in_array( $s['template'], array( 'form', 'map' ), true ) ) {
				$is_utility_page = true;
				break;
			}
		}
		$min_images = $is_utility_page ? 1 : 2;
		if ( $image_count < $min_images && ! $override_images ) {
			return new WP_Error(
				'build_needs_images',
				sprintf(
					'Only %d image section(s); this spec needs at least %d. A real page is not a stack of identical card rows — content pages need TWO image-bearing sections (text_image) woven through for visual rhythm; utility pages (form/map) need one (a hero background counts). Add a text_image section, or pass override_images=true for a deliberate text-only page.',
					$image_count,
					$min_images
				),
				array( 'status' => 422 )
			);
		}

		// Section-variety guard: a wall of identical card grids reads as a template,
		// not a designed page. Refuse a monotonous layout (4+ card_grids with no
		// steps/text_image variety between them) unless explicitly overridden.
		if ( ! $override_lint ) {
			$tpl_seq   = array();
			foreach ( $sections as $s ) {
				$tpl_seq[] = isset( $s['template'] ) ? (string) $s['template'] : '';
			}
			$card_grids = count( array_keys( $tpl_seq, 'card_grid', true ) );
			$variety    = count( array_keys( $tpl_seq, 'steps', true ) ) + count( array_keys( $tpl_seq, 'text_image', true ) );
			$max_run    = 0;
			$run        = 0;
			foreach ( $tpl_seq as $t ) {
				$run     = ( 'card_grid' === $t ) ? $run + 1 : 0;
				$max_run = max( $max_run, $run );
			}
			if ( $card_grids >= 4 && ( $variety < 2 || $max_run >= 4 ) ) {
				return new WP_Error(
					'monotonous_layout',
					sprintf(
						'Layout too repetitive: %d card_grid sections, %d image/steps sections, longest card_grid run %d. Vary it — convert sequential content to a "steps" section, turn explainer sections into "text_image" splits, and never run 4 card_grids back to back. Then resubmit (or override_lint=true).',
						$card_grids,
						$variety,
						$max_run
					),
					array( 'status' => 422 )
				);
			}
		}
		if ( 0 === $h1_count ) {
			return new WP_Error( 'build_needs_hero', 'The spec needs exactly one hero section (it carries the page H1).', array( 'status' => 422 ) );
		}
		if ( $h1_count > 1 ) {
			return new WP_Error( 'multiple_heroes', 'Only one hero section allowed — one H1 per page.', array( 'status' => 422 ) );
		}

		// ------------------------------------------------------------------
		// 2. Compose the tree, one root container per section. Styles are
		//    sampled from the style mirror by the builder's brand-default pass.
		// ------------------------------------------------------------------
		// Match this site's button + FAQ recipe (radius, padding, font, sizes).
		self::sample_recipe( $style_mirror );

		$tree = array();
		$alt  = false; // alternate white / light-grey section backgrounds
		foreach ( $sections as $i => $s ) {
			$tpl     = (string) $s['template'];
			$builder = 'compose_' . $tpl;
			$section = self::$builder( $s, $alt );
			if ( is_wp_error( $section ) ) {
				return $section;
			}
			$added = CC_Assistant_Elementor_Builder::add_container(
				$tree,
				'', // root level
				$section['settings'],
				null,
				$section['children'],
				'container',
				$style_mirror // style sampling source
			);
			if ( is_wp_error( $added ) ) {
				return new WP_Error(
					$added->get_error_code(),
					sprintf( 'sections[%d] (%s): %s', $i, $tpl, $added->get_error_message() ),
					array( 'status' => 422 )
				);
			}
			// form (fixed light-grey card section) and map (full-bleed embed) sit
			// outside the white/grey alternation rhythm, like hero and cta_band.
			if ( ! in_array( $tpl, array( 'hero', 'cta_band', 'form', 'map' ), true ) ) {
				$alt = ! $alt;
			}
		}

		$validation = CC_Assistant_Elementor_Builder::validate_tree( $tree );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// ------------------------------------------------------------------
		// 3. Quality gates BEFORE creating anything: per-widget lint (em
		//    dashes / AI-tells / style guide / placeholders / wall-of-text /
		//    address / hospital) + duplicate-card + placeholder sweep.
		// ------------------------------------------------------------------
		$payloads = self::collect_per_widget_lint_payloads( $tree );
		$lint     = array();
		$hard     = array();
		if ( ! empty( $payloads ) && method_exists( 'CC_Assistant_Pre_Publish', 'lint_html_block_per_widget' ) ) {
			$lint = CC_Assistant_Pre_Publish::lint_html_block_per_widget( $payloads );
			if ( method_exists( 'CC_Assistant_Pre_Publish', 'detect_duplicate_blocks' ) ) {
				$dup = CC_Assistant_Pre_Publish::detect_duplicate_blocks( wp_list_pluck( $payloads, 'html' ) );
				if ( $dup > 0 ) {
					$lint['duplicate_card_text'] = array(
						'pass'    => false,
						'message' => sprintf( '%d block(s) of identical text across 3+ widgets — every card needs unique copy.', $dup ),
					);
				}
			}
			foreach ( array( 'em_dashes', 'ai_tells', 'style_guide', 'placeholders', 'wall_of_text', 'address_consistency', 'hospital_comparison', 'duplicate_card_text' ) as $name ) {
				if ( isset( $lint[ $name ]['pass'] ) && empty( $lint[ $name ]['pass'] ) ) {
					$hard[] = $name;
				}
			}
		}
		if ( ! empty( $hard ) && ! $override_lint ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf( 'Spec content fails: %s. Fix the section copy and resubmit (or pass override_lint=true).', implode( ', ', $hard ) ),
				array( 'status' => 422, 'lint' => $lint )
			);
		}

		$summary = self::summarize_tree( $tree );

		require_once __DIR__ . '/class-evidence-gate.php';
		require_once __DIR__ . '/class-elementor-validation.php';
		$identity = CC_Assistant_Evidence_Gate::identity_check();
		if ( is_wp_error( $identity ) ) { return $identity; }
		$valid = CC_Assistant_Elementor_Validation::validate_tree( array(), $tree );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( $dry_run ) {
			$out = array(
				'dry_run'   => true,
				'structure' => $summary,
				'lint'      => $lint,
			);
			// v0.50 popup condition guard (warn-only): evaluated against the
			// rebuild target when given, else against "a new page" (only
			// site-wide popups can already cover a page that does not exist).
			$popup_warnings = CC_Assistant_Popups::coverage_warnings_for_payload( (string) wp_json_encode( $tree ), $target_post_id );
			if ( ! empty( $popup_warnings ) ) {
				$out['warnings'] = $popup_warnings;
			}
			return $out;
		}

		// ------------------------------------------------------------------
		// 4a. Rebuild-in-place (target_post_id): queue ONE pending change that
		//     replaces the target's Elementor tree, through the proven
		//     elementor_full_import path (import_to_pending) so ID regeneration,
		//     snapshot-first rollback, CSS flush, and apply-time behavior are
		//     identical to a manual import. Nothing is written directly — the
		//     target may be LIVE, so everything goes through the inbox.
		// ------------------------------------------------------------------
		if ( $target_post_id > 0 ) {
			return self::queue_rebuild_in_place( $target_post_id, $tree, $summary, $sections, $seo, $title, $image_count, $lint );
		}

		// ------------------------------------------------------------------
		// 4. Create the draft + save the tree (same persistence steps as
		//    build_service_page, which are proven in production).
		// ------------------------------------------------------------------
		$insert = array(
			'post_title'   => $title,
			'post_status'  => 'draft',
			'post_type'    => $post_type,
			'post_content' => '',
		);
		if ( '' !== $slug ) {
			$insert['post_name'] = sanitize_title( $slug );
		}
		$new_id = wp_insert_post( $insert, true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		update_post_meta( $new_id, '_cc_assistant_last_internal_apply', current_time( 'mysql', true ) );

		$json = wp_json_encode( $tree );
		if ( false === $json ) {
			wp_delete_post( $new_id, true );
			return new WP_Error( 'encode_failed', 'Could not encode composed _elementor_data.', array( 'status' => 500 ) );
		}
		require_once __DIR__ . '/class-elementor-builder.php';
		$saved = CC_Assistant_Elementor_Builder::save_tree( $new_id, $tree );
		if ( is_wp_error( $saved ) ) { wp_delete_post( $new_id, true ); return $saved; }
		update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
		$mirror_tpl = get_post_meta( $style_mirror, '_elementor_template_type', true );
		if ( ! empty( $mirror_tpl ) ) {
			update_post_meta( $new_id, '_elementor_template_type', $mirror_tpl );
		}
		$page_template = get_post_meta( $style_mirror, '_wp_page_template', true );
		if ( ! empty( $page_template ) && 'default' !== $page_template ) {
			update_post_meta( $new_id, '_wp_page_template', $page_template );
		}

		foreach ( array( 'title', 'description', 'focus_keyword' ) as $k ) {
			if ( ! empty( $seo[ $k ] ) ) {
				CC_Assistant_Build_Service_Page::set_seo_field( $new_id, $k, (string) $seo[ $k ] );
			}
		}

		// Base schema so the page has structured data on day 1. PAGES are
		// skipped entirely: Article is wrong for a page, and the active SEO
		// plugin (Rank Math/Yoast) already emits a WebPage node — a second
		// page-jsonld blob would only duplicate it.
		$schema_generated = null;
		if ( 'page' !== $post_type && file_exists( CC_ASSISTANT_DIR . 'includes/class-schema-generator.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-schema-generator.php';
			$gen = CC_Assistant_Schema_Generator::generate( (int) $new_id );
			if ( ! is_wp_error( $gen ) && ! empty( $gen['jsonld'] ) ) {
				update_post_meta( (int) $new_id, CC_Assistant_Schema_Generator::META_KEY, wp_slash( $gen['jsonld'] ) );
				$schema_generated = array( 'types' => $gen['types'] ?? array() );
			}
		}

		update_post_meta( $new_id, '_cc_assistant_last_internal_apply', current_time( 'mysql', true ) );

		// ------------------------------------------------------------------
		// 5. One publish_draft pending for one-click human review. The v0.39
		//    publish gate re-checks the page at apply time (belt and braces).
		// ------------------------------------------------------------------
		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => $new_id,
				'change_type'    => 'publish_draft',
				'change_summary' => sprintf(
					'Build new %s "%s" from spec: %d sections, %d widgets, %d image(s).',
					$post_type,
					$title,
					count( $sections ),
					$summary['total_widgets'],
					$image_count
				),
				'proposed_value' => wp_json_encode(
					array(
						'built_from'  => 'spec',
						'title'       => $title,
						'slug'        => $slug,
						'sections'    => wp_list_pluck( $sections, 'template' ),
						'structure'   => $summary,
					)
				),
				'current_value'  => '',
			)
		);
		if ( is_wp_error( $pending_id ) ) {
			return $pending_id;
		}

		$out = array(
			'new_post_id'      => (int) $new_id,
			'pending_id'       => (int) $pending_id,
			'edit_url'         => get_edit_post_link( $new_id, '' ),
			'preview_url'      => add_query_arg( array( 'page_id' => $new_id, 'preview' => 'true' ), home_url() ),
			'review_url'       => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'structure'        => $summary,
			'image_count'      => $image_count,
			'lint'             => $lint,
			'schema_generated' => $schema_generated,
		);
		// v0.50 popup condition guard (warn-only): now that the draft exists,
		// check each popup-open link against the REAL new post id. A popup
		// that is not site-wide cannot cover a just-created page — the
		// operator must add it to the popup's conditions before publish.
		$popup_warnings = CC_Assistant_Popups::coverage_warnings_for_payload( (string) wp_json_encode( $tree ), (int) $new_id );
		if ( ! empty( $popup_warnings ) ) {
			$out['warnings'] = $popup_warnings;
		}
		return $out;
	}

	/**
	 * Queue the composed tree as ONE elementor_full_import pending against an
	 * existing post (rebuild-in-place), plus one postmeta_update pending per
	 * SEO field. Reuses CC_Assistant_Elementor_IO::import_to_pending so apply
	 * behavior (snapshot, fresh element ids, CSS cache flush, race guard)
	 * matches the production import path exactly.
	 *
	 * @return array|WP_Error
	 */
	private static function queue_rebuild_in_place( $target_post_id, array $tree, array $summary, array $sections, array $seo, $title, $image_count, $lint ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';

		$json = wp_json_encode( $tree );
		if ( false === $json ) {
			return new WP_Error( 'encode_failed', 'Could not encode composed _elementor_data.', array( 'status' => 500 ) );
		}

		$import = CC_Assistant_Elementor_IO::import_to_pending(
			$target_post_id,
			$json,
			array(
				// Same-site rebuild: keep images, keep this site's kit tokens,
				// and keep the composer's deliberate per-heading weights (the
				// composed tree already bakes the global type scale, so the
				// import-path blanket 700 pass would flatten 600-weight H3s).
				'regenerate_ids'        => true,
				'strip_images'          => false,
				'map_kit_globals'       => false,
				'enforce_bold_headings' => false,
				'snapshot_first'        => true,
				'change_summary'        => sprintf( 'Rebuild page in place from spec ("%s")', $title ),
				'reasoning'             => sprintf(
					'Rebuild-in-place from spec for "%s": replaces the Elementor tree of post %d with %d composed sections (%d widgets, %d image section(s)). Pre-import snapshot taken at apply for one-click rollback.',
					$title,
					$target_post_id,
					count( $sections ),
					$summary['total_widgets'],
					$image_count
				),
			)
		);
		if ( is_wp_error( $import ) ) {
			return $import;
		}

		// SEO meta queues against the target too — via the same postmeta_update
		// pending shape draft_update_seo_meta produces (key resolved through the
		// active SEO plugin). Never written directly: the target may be live.
		$seo_pending_ids = array();
		$seo_note        = null;
		$claim_warnings  = array();
		if ( ! empty( $seo ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
			foreach ( array( 'title', 'description', 'focus_keyword' ) as $k ) {
				if ( empty( $seo[ $k ] ) ) {
					continue;
				}
				$resolved = class_exists( 'CC_Assistant_Site_Memory' ) ? CC_Assistant_Site_Memory::resolve_seo_meta_key( $k ) : null;
				if ( null === $resolved ) {
					$seo_note = sprintf( 'seo skipped for "%s": no SEO-plugin meta key resolved on this site.', $k );
					continue;
				}
				// Don't queue a no-op: a rebuild often re-sends the SEO values
				// the page already carries, and identical pendings are pure
				// reviewer noise (and used to misreport as apply failures).
				$live_value = get_post_meta( $target_post_id, $resolved, true );
				if ( (string) $live_value === (string) $seo[ $k ] ) {
					continue;
				}
				$pid = CC_Assistant_Pending_Changes::queue(
					array(
						'post_id'        => $target_post_id,
						'change_type'    => 'postmeta_update',
						'change_summary' => sprintf( 'Update SEO %s (rebuild-in-place from spec)', $k ),
						'current_value'  => wp_json_encode( array( 'key' => $resolved, 'value' => get_post_meta( $target_post_id, $resolved, true ) ) ),
						'proposed_value' => wp_json_encode( array( 'key' => $resolved, 'value' => (string) $seo[ $k ] ) ),
						'reasoning'      => 'SEO meta accompanying the build_page_from_spec rebuild-in-place.',
					)
				);
				if ( is_wp_error( $pid ) ) { return $pid; }
				if ( ! is_wp_error( $pid ) ) {
					$seo_pending_ids[] = (int) $pid;
					$w = CC_Assistant_Pending_Changes::claim_removal_warnings( (int) $pid );
					if ( ! empty( $w ) ) {
						$claim_warnings = array_merge( $claim_warnings, $w );
					}
				}
			}
		}

		$out = array(
			'target_post_id' => (int) $target_post_id,
			'pending_id'     => (int) $import['pending_id'],
			'edit_url'       => get_edit_post_link( $target_post_id, '' ),
			'review_url'     => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'structure'      => $summary,
			'image_count'    => (int) $image_count,
			'lint'           => $lint,
			'import_summary' => isset( $import['summary'] ) ? $import['summary'] : null,
		);
		if ( ! empty( $seo_pending_ids ) ) {
			$out['seo_pending_ids'] = $seo_pending_ids;
		}
		if ( null !== $seo_note ) {
			$out['seo_note'] = $seo_note;
		}
		// v0.50: the import path already ran the popup condition guard against
		// the target post — surface its warn-only notices here.
		if ( ! empty( $import['warnings'] ) ) {
			$out['warnings'] = $import['warnings'];
		}
		// A rebuild-in-place replaces the whole tree — the claim-removal guard
		// matters MOST here; surface warnings from the import and SEO pendings.
		if ( ! empty( $import['claim_removal_warnings'] ) ) {
			$claim_warnings = array_merge( (array) $import['claim_removal_warnings'], $claim_warnings );
		}
		if ( ! empty( $claim_warnings ) ) {
			$out['claim_removal_warnings'] = $claim_warnings;
		}
		// Same reasoning as the claim guard: a superseded pending is hidden from
		// the inbox while still reading status='pending', so it is indistinguishable
		// from one that was never queued. Collect across the body pending and any
		// SEO pendings this build produced.
		$superseded = CC_Assistant_Pending_Changes::superseded_notices( (int) $import['pending_id'] );
		foreach ( $seo_pending_ids as $spid ) {
			$superseded = array_merge( $superseded, CC_Assistant_Pending_Changes::superseded_notices( (int) $spid ) );
		}
		if ( ! empty( $superseded ) ) {
			$out['superseded'] = $superseded;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Section validation
	 * ------------------------------------------------------------------- */

	private static function validate_section( $tpl, $s, $i, &$image_count, &$h1_count ) {
		$prefix = sprintf( 'sections[%d] (%s)', $i, $tpl );

		if ( 'hero' === $tpl ) {
			$h1_count++;
			if ( empty( $s['heading'] ) ) {
				return new WP_Error( 'invalid_section', $prefix . ': heading (the page H1) is required.', array( 'status' => 400 ) );
			}
			if ( ! empty( $s['image']['url'] ) ) {
				$image_count++;
			}
			return null;
		}
		if ( 'text_image' === $tpl ) {
			if ( empty( $s['heading'] ) || ( empty( $s['html'] ) && empty( $s['text'] ) ) ) {
				return new WP_Error( 'invalid_section', $prefix . ': heading and html (or text) are required.', array( 'status' => 400 ) );
			}
			if ( empty( $s['image']['url'] ) ) {
				return new WP_Error( 'invalid_section', $prefix . ': image.url is required (use the card_grid or a plain heading+text via cta_band if no image fits).', array( 'status' => 400 ) );
			}
			if ( empty( $s['image']['alt'] ) ) {
				return new WP_Error( 'invalid_section', $prefix . ': image.alt is required — descriptive alt text is part of the bar.', array( 'status' => 400 ) );
			}
			$image_count++;
			return null;
		}
		if ( 'card_grid' === $tpl ) {
			$cards = isset( $s['cards'] ) && is_array( $s['cards'] ) ? $s['cards'] : array();
			if ( empty( $s['heading'] ) || count( $cards ) < 2 ) {
				return new WP_Error( 'invalid_section', $prefix . ': heading and at least 2 cards are required.', array( 'status' => 400 ) );
			}
			foreach ( $cards as $ci => $c ) {
				if ( empty( $c['title'] ) || empty( $c['text'] ) ) {
					return new WP_Error( 'invalid_section', sprintf( '%s: cards[%d] needs title and text (unique copy per card — no shared boilerplate).', $prefix, $ci ), array( 'status' => 400 ) );
				}
			}
			if ( count( $cards ) > self::MAX_UNGROUPED_CARDS ) {
				$missing = array();
				foreach ( $cards as $ci => $c ) {
					if ( empty( $c['group'] ) ) {
						$missing[] = $ci;
					}
				}
				if ( ! empty( $missing ) ) {
					return new WP_Error(
						'flat_list_needs_grouping',
						sprintf(
							'%s: %d cards is too many for one flat grid (max %d). Add a "group" label to every card (e.g. "Chest & Heart", "Injuries", "Children") and the builder renders one titled sub-grid per group — scannable, not a wall. Cards missing group: %s.',
							$prefix,
							count( $cards ),
							self::MAX_UNGROUPED_CARDS,
							implode( ', ', array_slice( $missing, 0, 10 ) )
						),
						array( 'status' => 422 )
					);
				}
			}
			return null;
		}
		if ( 'steps' === $tpl ) {
			$steps = isset( $s['steps'] ) && is_array( $s['steps'] ) ? $s['steps'] : array();
			if ( empty( $s['heading'] ) || count( $steps ) < 2 ) {
				return new WP_Error( 'invalid_section', $prefix . ': heading and at least 2 steps are required (each {title, text}).', array( 'status' => 400 ) );
			}
			foreach ( $steps as $si => $st ) {
				if ( empty( $st['title'] ) || empty( $st['text'] ) ) {
					return new WP_Error( 'invalid_section', sprintf( '%s: steps[%d] needs title and text.', $prefix, $si ), array( 'status' => 400 ) );
				}
			}
			return null;
		}
		if ( 'faq' === $tpl ) {
			$items = isset( $s['items'] ) && is_array( $s['items'] ) ? $s['items'] : array();
			if ( count( $items ) < 2 ) {
				return new WP_Error( 'invalid_section', $prefix . ': at least 2 items required, each {q, a}.', array( 'status' => 400 ) );
			}
			foreach ( $items as $qi => $item ) {
				if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
					return new WP_Error( 'invalid_section', sprintf( '%s: items[%d] needs q and a.', $prefix, $qi ), array( 'status' => 400 ) );
				}
			}
			return null;
		}
		if ( 'cta_band' === $tpl ) {
			if ( empty( $s['heading'] ) ) {
				return new WP_Error( 'invalid_section', $prefix . ': heading is required.', array( 'status' => 400 ) );
			}
			return null;
		}
		if ( 'form' === $tpl ) {
			// page_label is REQUIRED — it names the page in the notification
			// email subject ("New website form submission - {page_label} page").
			$label = isset( $s['page_label'] ) && is_string( $s['page_label'] ) ? trim( $s['page_label'] ) : '';
			if ( '' === $label ) {
				return new WP_Error( 'invalid_section', $prefix . ': page_label (string) is required — it is used in the notification email subject.', array( 'status' => 400 ) );
			}
			// Deliberately does NOT count toward the image requirement and has
			// no cards/headings for the variety or grouping lints to judge.
			return null;
		}
		if ( 'map' === $tpl ) {
			$addr = isset( $s['address'] ) && is_string( $s['address'] ) ? trim( $s['address'] ) : '';
			if ( '' === $addr ) {
				$addr = trim( (string) get_option( 'cc_assistant_facility_address', '' ) );
			}
			if ( '' === $addr ) {
				return new WP_Error(
					'map_address_missing',
					$prefix . ': no address available. Pass address in the section spec, or set the cc_assistant_facility_address site option.',
					array( 'status' => 400 )
				);
			}
			return null;
		}
		return null;
	}

	/* ---------------------------------------------------------------------
	 * Section composers — each returns { settings, children } for one root
	 * container. Width discipline (root boxed 1300 / inner 850) follows the
	 * global section-width rules; everything visual beyond structure is left
	 * to the builder's style sampling so each tenant keeps its own look.
	 * ------------------------------------------------------------------- */

	private static function root_settings( $background = '', $extra = array() ) {
		$settings = array_merge(
			array(
				'content_width' => 'boxed',
				'boxed_width'   => array( 'unit' => 'px', 'size' => 1300 ),
				'flex_direction' => 'column',
				'flex_align_items' => 'center',
				'padding'       => array( 'unit' => 'px', 'top' => '80', 'bottom' => '80', 'left' => '20', 'right' => '20', 'isLinked' => false ),
			),
			$extra
		);
		if ( '' !== $background ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = $background;
		}
		return $settings;
	}

	private static function heading_spec( $text, $size, $align = 'center', $width = 850, $on_dark = false ) {
		// Bake the global type scale (design-system-global B) so headings never
		// fall back to the kit's 400-weight token (the "skeleton skin" where every
		// H2 rendered thin). Weight + size are fixed/global; COLOR comes from the
		// kit token (site self-brands) on light bg, or literal white on dark bands.
		$scale = array(
			'h1' => array( 'w' => '700', 'd' => 48, 'm' => 34, 'lh' => 1.15 ),
			'h2' => array( 'w' => '700', 'd' => 36, 'm' => 28, 'lh' => 1.2 ),
			'h3' => array( 'w' => '600', 'd' => 28, 'm' => 24, 'lh' => 1.25 ),
			'h4' => array( 'w' => '600', 'd' => 22, 'm' => 20, 'lh' => 1.3 ),
			'h5' => array( 'w' => '600', 'd' => 18, 'm' => 17, 'lh' => 1.4 ),
			'h6' => array( 'w' => '600', 'd' => 14, 'm' => 14, 'lh' => 1.4 ),
			'p'  => array( 'w' => '600', 'd' => 14, 'm' => 14, 'lh' => 1.4 ),
		);
		$sz         = isset( $scale[ $size ] ) ? $scale[ $size ] : $scale['h2'];
		$is_eyebrow = in_array( $size, array( 'h6', 'p' ), true );
		$settings   = array(
			'title'                       => (string) $text,
			'header_size'                 => $size,
			'align'                       => $align,
			'_element_width'              => 'initial',
			'_element_custom_width'       => array( 'unit' => 'px', 'size' => $width ),
			'_flex_align_self'            => 'center',
			'typography_typography'       => 'custom',
			'typography_font_weight'      => $sz['w'],
			'typography_font_size'        => array( 'unit' => 'px', 'size' => $sz['d'], 'sizes' => array() ),
			'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => $sz['m'], 'sizes' => array() ),
			'typography_line_height'      => array( 'unit' => 'em', 'size' => $sz['lh'], 'sizes' => array() ),
		);
		if ( $is_eyebrow ) {
			$settings['typography_text_transform'] = 'uppercase';
			$settings['typography_letter_spacing'] = array( 'unit' => 'px', 'size' => 2, 'sizes' => array() );
		}
		if ( $on_dark ) {
			$settings['title_color'] = '#FFFFFF';
		} elseif ( $is_eyebrow ) {
			$settings['__globals__'] = array( 'title_color' => 'globals/colors?id=secondary' );
		} else {
			$settings['__globals__'] = array( 'title_color' => 'globals/colors?id=text' );
		}
		return array(
			'type'       => 'widget',
			'widgetType' => 'heading',
			'settings'   => $settings,
		);
	}

	private static function text_spec( $html, $align = 'left', $width = 850, $on_dark = false ) {
		$settings = array(
			'editor'                  => (string) $html,
			'align'                   => $align,
			'_element_width'          => 'initial',
			'_element_custom_width'   => array( 'unit' => 'px', 'size' => $width ),
			'_flex_align_self'        => 'center',
			'typography_typography'   => 'custom',
			'typography_font_size'    => array( 'unit' => 'px', 'size' => 18, 'sizes' => array() ),
			'typography_line_height'  => array( 'unit' => 'em', 'size' => 1.6, 'sizes' => array() ),
		);
		if ( $on_dark ) {
			// White, not a near-white grey: #E0E0E0 is off the kit palette (fails
			// brand_compliance) and white reads cleaner + higher-contrast on navy.
			$settings['text_color'] = '#FFFFFF';
		} else {
			$settings['__globals__'] = array( 'text_color' => 'globals/colors?id=text' );
		}
		return array(
			'type'       => 'widget',
			'widgetType' => 'text-editor',
			'settings'   => $settings,
		);
	}

	private static function buttons_row_spec( $buttons, $on_dark = false ) {
		$children = array();
		$idx      = 0;
		foreach ( (array) $buttons as $b ) {
			// v0.50: popup_id (int) is first-class — it WINS over url and emits
			// the exact Elementor popup-open action link server-side.
			$url = isset( $b['url'] ) && is_string( $b['url'] ) ? trim( $b['url'] ) : '';
			if ( ! empty( $b['popup_id'] ) && (int) $b['popup_id'] > 0 ) {
				$url = CC_Assistant_Popups::popup_action_url( (int) $b['popup_id'] );
			}
			if ( empty( $b['text'] ) || '' === $url ) {
				continue;
			}
			// The FIRST button is the page's primary action (solid); the rest are
			// secondary (outline). Two solid identical buttons side by side (the
			// "Call and Get Directions are the same color" bug) breaks the visual
			// hierarchy AND, on a colored band, made a red button vanish on red.
			$children[] = array(
				'type'       => 'widget',
				'widgetType' => 'button',
				'settings'   => self::button_settings( (string) $b['text'], $url, 0 === $idx, $on_dark ),
			);
			$idx++;
		}
		if ( empty( $children ) ) {
			return null;
		}
		return array(
			'type'     => 'container',
			'settings' => array(
				'content_width'        => 'full',
				'flex_direction'       => 'row',
				'flex_justify_content' => 'center',
				'flex_gap'             => array( 'unit' => 'px', 'size' => 16 ),
			),
			'children' => $children,
		);
	}

	/**
	 * One button's settings. Four variants from (primary|secondary) x (light|dark)
	 * so buttons always read as a hierarchy AND always have contrast against their
	 * section. Brand colors come from kit tokens (self-brand per site); only
	 * universal neutrals (white, light-grey, transparent) are literal.
	 *   - light primary   : solid brand fill, white label
	 *   - light secondary : transparent, brand outline + label, fills on hover
	 *   - dark  primary   : WHITE fill + brand label (reads on navy AND red bands)
	 *   - dark  secondary : transparent, white outline + label
	 */
	private static function button_settings( $text, $url, $is_primary, $on_dark ) {
		$r      = self::$btn_recipe;
		$common = array(
			'text'                   => $text,
			'link'                   => array( 'url' => $url, 'is_external' => '', 'nofollow' => '' ),
			'typography_typography'  => 'custom',
			'typography_font_weight' => $r['weight'],
			'typography_font_size'   => array( 'unit' => 'px', 'size' => $r['size'], 'sizes' => array() ),
			'border_radius'          => array( 'unit' => 'px', 'top' => $r['radius'], 'right' => $r['radius'], 'bottom' => $r['radius'], 'left' => $r['radius'], 'isLinked' => true ),
			'text_padding'           => array( 'unit' => 'px', 'top' => $r['pad_v'], 'right' => $r['pad_h'], 'bottom' => $r['pad_v'], 'left' => $r['pad_h'], 'isLinked' => false ),
			'border_border'          => 'solid',
			'border_width'           => array( 'unit' => 'px', 'top' => $r['border'], 'right' => $r['border'], 'bottom' => $r['border'], 'left' => $r['border'], 'isLinked' => true ),
		);
		if ( '' !== $r['family'] ) {
			$common['typography_font_family'] = $r['family'];
		}

		// Every variant defines a full hover (bg + border + text) so there are no
		// undefined hover states, and the brand colors come from kit tokens so each
		// site self-brands. Neutrals (white / light-grey / transparent) are literal.
		if ( $on_dark ) {
			if ( $is_primary ) {
				// White solid + brand label; hover dims to the kit neutral, label stays brand.
				return array_merge( $common, array(
					'background_color'              => '#FFFFFF',
					'border_color'                  => '#FFFFFF',
					'button_background_hover_color' => self::kit_color( 'neutral' ),
					'button_hover_border_color'     => self::kit_color( 'neutral' ),
					'__globals__'                   => array(
						'button_text_color' => 'globals/colors?id=primary',
						'hover_color'       => 'globals/colors?id=primary',
					),
				) );
			}
			// White outline; hover inverts to white fill + secondary (navy) label.
			return array_merge( $common, array(
				'background_color'              => 'rgba(0,0,0,0)',
				'button_text_color'             => '#FFFFFF',
				'border_color'                  => '#FFFFFF',
				'button_background_hover_color' => '#FFFFFF',
				'button_hover_border_color'     => '#FFFFFF',
				'__globals__'                   => array( 'hover_color' => 'globals/colors?id=secondary' ),
			) );
		}

		if ( $is_primary ) {
			// Brand solid + white label; hover inverts to white fill + brand label.
			return array_merge( $common, array(
				'button_text_color'             => '#FFFFFF',
				'button_background_hover_color' => '#FFFFFF',
				'__globals__'                   => array(
					'background_color' => 'globals/colors?id=primary',
					'border_color'     => 'globals/colors?id=primary',
					'hover_color'      => 'globals/colors?id=primary',
				),
			) );
		}
		// Brand outline; hover fills brand + white label.
		return array_merge( $common, array(
			'background_color' => 'rgba(0,0,0,0)',
			'hover_color'      => '#FFFFFF',
			'__globals__'      => array(
				'button_text_color'             => 'globals/colors?id=primary',
				'border_color'                  => 'globals/colors?id=primary',
				'button_background_hover_color' => 'globals/colors?id=primary',
			),
		) );
	}

	private static function compose_hero( $s, $alt ) {
		$settings = array(
			'content_width'    => 'boxed',
			'boxed_width'      => array( 'unit' => 'px', 'size' => 1300 ),
			'flex_direction'   => 'column',
			'flex_align_items' => 'center',
			'min_height'       => array( 'unit' => 'vh', 'size' => 60 ),
			'padding'          => array( 'unit' => 'px', 'top' => '96', 'bottom' => '96', 'left' => '20', 'right' => '20', 'isLinked' => false ),
		);
		if ( ! empty( $s['image']['url'] ) ) {
			$settings['background_background']         = 'classic';
			$settings['background_image']              = array_filter(
				array(
					'url' => (string) $s['image']['url'],
					'id'  => isset( $s['image']['id'] ) ? (int) $s['image']['id'] : null,
				)
			);
			$settings['background_position']           = 'center center';
			$settings['background_size']               = 'cover';
			$settings['background_overlay_background'] = 'classic';
			$settings['background_overlay_color']      = isset( $s['overlay_color'] ) ? (string) $s['overlay_color'] : '#000000';
			$settings['background_overlay_opacity']    = array( 'unit' => 'px', 'size' => isset( $s['overlay_opacity'] ) ? (float) $s['overlay_opacity'] : 0.6 );
		} else {
			// Imageless hero: solid brand band (kit secondary) so the H1 reads on
			// a designed surface, not a bare white skeleton. White text set via
			// on_dark below. Explicit hex + __globals__ token, per the codebase
			// pattern, so the kit token wins where supported.
			$settings['background_background'] = 'classic';
			// Hero text/buttons are emitted for a DARK band (white text), so the
			// imageless fallback must be the darker brand token. kit 'secondary'
			// is a light accent on some sites (e.g. yellow) and made white hero
			// text unreadable.
			$settings['background_color']      = self::kit_color( 'primary' );
			$settings['__globals__']           = array( 'background_color' => 'globals/colors?id=primary' );
		}

		$children = array();
		if ( ! empty( $s['eyebrow'] ) ) {
			// Eyebrow renders as a styled <p>, NOT an <h6>. An h6 above the h1
			// breaks heading order (skip + reverse), which hurts a11y and AI
			// heading parsing. header_size 'p' keeps the kicker styling
			// (uppercase, letter-spacing, secondary/white color) with no heading
			// tag in the DOM.
			$children[] = self::heading_spec( $s['eyebrow'], 'p', 'center', 850, true );
		}
		$children[] = self::heading_spec( $s['heading'], 'h1', 'center', 850, true );
		if ( ! empty( $s['text'] ) ) {
			$children[] = self::text_spec( '<p>' . esc_html( (string) $s['text'] ) . '</p>', 'center', 850, true );
		}
		// Hero always sits on a dark surface (navy band or image+overlay), so its
		// buttons use the on-dark variants (white primary, white-outline secondary).
		$btns = self::buttons_row_spec( isset( $s['buttons'] ) ? $s['buttons'] : array(), true );
		if ( $btns ) {
			$children[] = $btns;
		}
		return array( 'settings' => $settings, 'children' => $children );
	}

	private static function compose_text_image( $s, $alt ) {
		$image_first = isset( $s['image_position'] ) && 'left' === $s['image_position'];

		$image_col = array(
			'type'     => 'container',
			'settings' => array( 'content_width' => 'full', 'width' => array( 'unit' => '%', 'size' => 45 ) ),
			'children' => array(
				array(
					'type'       => 'widget',
					'widgetType' => 'image',
					'settings'   => array(
						'image'      => array_filter(
							array(
								'url' => (string) $s['image']['url'],
								'id'  => isset( $s['image']['id'] ) ? (int) $s['image']['id'] : null,
								'alt' => (string) $s['image']['alt'],
							)
						),
						'image_size' => 'large',
					),
				),
			),
		);
		$html = ! empty( $s['html'] ) ? (string) $s['html'] : '<p>' . esc_html( (string) $s['text'] ) . '</p>';
		$text_col = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'    => 'full',
				'width'            => array( 'unit' => '%', 'size' => 55 ),
				'flex_direction'   => 'column',
				'flex_align_items' => 'flex-start',
			),
			'children' => array(
				self::heading_spec( (string) $s['heading'], 'h2', 'left', 640 ),
				self::text_spec( $html, 'left', 640 ),
			),
		);

		return array(
			'settings' => self::root_settings(
				$alt ? self::kit_color( 'neutral' ) : '',
				array(
					'flex_direction'   => 'row',
					'flex_align_items' => 'center',
					'flex_gap'         => array( 'unit' => 'px', 'size' => 48 ),
				)
			),
			'children' => $image_first ? array( $image_col, $text_col ) : array( $text_col, $image_col ),
		);
	}

	/**
	 * Curated, visually-distinct fallback icons (FA6 solid). Used in order when
	 * a card has no spec icon and no semantic match, or when its match is already
	 * taken in the grid. All exist in the free Font Awesome set the kit loads.
	 */
	private static function fallback_icons() {
		// FA5-NAMED on purpose: Elementor bundles Font Awesome 5 on these sites,
		// and FA6 keeps these as aliases — so FA5 names render on BOTH versions.
		// FA6-only names (e.g. circle-check, kit-medical, user-doctor) render
		// blank on FA5. Verified against the live FA set.
		return array(
			'fas fa-stethoscope',
			'fas fa-notes-medical',
			'fas fa-briefcase-medical',
			'fas fa-clinic-medical',
			'fas fa-clipboard-list',
			'fas fa-user-md',
			'fas fa-hospital',
			'fas fa-hand-holding-heart',
			'fas fa-tasks',
			'fas fa-heartbeat',
			'fas fa-clock',
			'fas fa-check-circle',
		);
	}

	/**
	 * Infer a meaningful FA6-solid icon from a card's words. Returns '' if no
	 * confident match (caller then uses the fallback rotation). Order matters:
	 * more specific themes are listed before generic ones.
	 */
	private static function semantic_icon( $haystack ) {
		// All FA5-named (render on FA5 and FA6-via-alias). Do NOT use FA6-only
		// names (circle-check, car-burst, hand-fist, helmet-safety, heart-pulse,
		// shield-halved, person-falling, etc.) — they render blank on FA5 sites.
		$map = array(
			array( 'fas fa-brain', 'brain', 'tbi', 'concussion', 'neuro', 'cognitive' ),
			array( 'fas fa-walking', 'fall', 'slip', 'tripping' ),
			array( 'fas fa-car-crash', 'car', 'vehicle', 'crash', 'collision', 'motor', 'traffic', 'auto' ),
			array( 'fas fa-football-ball', 'sport', 'athlet', 'football', 'recreation', 'helmet on' ),
			array( 'fas fa-fist-raised', 'assault', 'violence', 'fight', 'punch', 'a blow' ),
			array( 'fas fa-hard-hat', 'struck', 'workplace', 'construction', 'an object' ),
			array( 'fas fa-child', 'child', 'kids', 'pediatric', 'infant', 'toddler', 'baby' ),
			array( 'fas fa-tint', 'blood thinner', 'warfarin', 'apixaban', 'clopidogrel', 'anticoagul', 'bleed' ),
			array( 'fas fa-x-ray', ' ct ', 'ct scan', 'imaging', 'x-ray', 'xray', 'radiolog' ),
			array( 'fas fa-clipboard-check', 'triage', 'vitals', 'check-in', 'intake' ),
			array( 'fas fa-heartbeat', 'monitor', 'observation', 'vital sign' ),
			array( 'fas fa-route', 'next step', 'discharge', 'transfer', 'follow-up', 'follow up', 'referral', 'consult' ),
			array( 'fas fa-bolt', 'right away', 'immediate', 'within minutes', 'sudden onset' ),
			array( 'fas fa-arrow-down', 'worse', 'declin', 'deteriorat' ),
			array( 'fas fa-hourglass-half', 'over hours', 'over time', 'hours to days', 'delayed', 'building over' ),
			array( 'fas fa-shield-alt', 'insur', 'in-network', 'protected', 'coverage', 'no surprises' ),
			array( 'fas fa-hand-holding-medical', 'everyone', 'emtala', 'regardless', 'all patients', 'anyone' ),
			array( 'fas fa-file-invoice-dollar', 'self-pay', 'self pay', 'cost', 'pricing', 'a bill', 'payment' ),
			array( 'fas fa-check-circle', 'do this', 'recommended', 'you should', 'safe to', 'best to' ),
			array( 'fas fa-times-circle', 'avoid', 'do not', "don't", 'never ', 'refrain' ),
			array( 'fas fa-exclamation-triangle', 'severe', '911', 'critical', 'life-threat' ),
			array( 'fas fa-exclamation-circle', 'moderate', 'warning', 'caution', 'urgent' ),
			array( 'fas fa-info-circle', 'mild', 'minor', 'low risk' ),
			array( 'fas fa-dizzy', 'dizz', 'vomit', 'nausea', 'confus', 'disorient' ),
			array( 'fas fa-ambulance', 'ambulance', 'paramedic', ' ems ' ),
			array( 'fas fa-lungs', 'breath', 'respiratory', 'lung', 'asthma' ),
			array( 'fas fa-bone', 'fracture', 'broken bone', 'orthopedic' ),
			array( 'fas fa-virus', 'infection', 'sepsis', 'fever', 'the flu' ),
			array( 'fas fa-stethoscope', 'exam', 'evaluat', 'diagnos', 'assess' ),
		);
		foreach ( $map as $entry ) {
			$icon = array_shift( $entry );
			foreach ( $entry as $kw ) {
				if ( false !== strpos( $haystack, $kw ) ) {
					return $icon;
				}
			}
		}
		return '';
	}

	/**
	 * Resolve the icon for one card, guaranteeing distinctness within the grid.
	 * Priority: explicit spec icon -> semantic inference -> fallback rotation.
	 * Mutates $used (icons already placed in this grid) and $fallback_i (rotation
	 * cursor, persists across groups for more variety).
	 */
	/**
	 * Map FA6-only icon names to their FA5 equivalents. Elementor bundles Font
	 * Awesome 5 on these sites, so an FA6 name (fa-circle-info, fa-car-burst,
	 * fa-hand-fist...) renders as a BLANK shape. This guarantees every icon
	 * resolves no matter which name the spec passes. FA6 names already valid in
	 * FA5 pass through untouched.
	 */
	private static function normalize_icon( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return '';
		}
		$map = array(
			'fa-circle-info'          => 'fa-info-circle',
			'fa-circle-check'         => 'fa-check-circle',
			'fa-circle-xmark'         => 'fa-times-circle',
			'fa-circle-exclamation'   => 'fa-exclamation-circle',
			'fa-triangle-exclamation' => 'fa-exclamation-triangle',
			'fa-car-burst'            => 'fa-car-crash',
			'fa-hand-fist'            => 'fa-fist-raised',
			'fa-helmet-safety'        => 'fa-hard-hat',
			'fa-heart-pulse'          => 'fa-heartbeat',
			'fa-shield-halved'        => 'fa-shield-alt',
			'fa-person-falling'       => 'fa-walking',
			'fa-arrow-trend-down'     => 'fa-arrow-down',
			'fa-arrow-trend-up'       => 'fa-chart-line',
			'fa-truck-medical'        => 'fa-ambulance',
			'fa-house-medical'        => 'fa-clinic-medical',
			'fa-kit-medical'          => 'fa-briefcase-medical',
			'fa-user-doctor'          => 'fa-user-md',
			'fa-face-dizzy'           => 'fa-dizzy',
			'fa-face-frown'           => 'fa-frown',
			'fa-magnifying-glass'     => 'fa-search',
			'fa-list-check'           => 'fa-tasks',
			'fa-droplet'              => 'fa-tint',
			'fa-rotate'               => 'fa-redo',
			'fa-rotate-right'         => 'fa-redo',
			'fa-gauge-high'           => 'fa-tachometer-alt',
		);
		foreach ( $map as $fa6 => $fa5 ) {
			if ( false !== strpos( $name, $fa6 ) ) {
				return 'fas ' . $fa5;
			}
		}
		return $name;
	}

	private static function card_icon( $c, &$used, &$fallback_i ) {
		$icon = ! empty( $c['icon'] ) ? self::normalize_icon( (string) $c['icon'] ) : '';
		if ( '' === $icon ) {
			// Title first (the card's actual subject), then title+text. Matching
			// body text alone misfires — e.g. a "Right away" card whose text
			// mentions "concussion" would grab the brain icon.
			$title = strtolower( (string) ( isset( $c['title'] ) ? $c['title'] : '' ) );
			$icon  = self::semantic_icon( ' ' . $title . ' ' );
			if ( '' === $icon ) {
				$body = strtolower( (string) ( isset( $c['text'] ) ? $c['text'] : '' ) );
				$icon = self::semantic_icon( ' ' . $title . ' ' . $body . ' ' );
			}
		}
		if ( '' === $icon || in_array( $icon, $used, true ) ) {
			$pool = self::fallback_icons();
			$n    = count( $pool );
			$pick = '';
			for ( $k = 0; $k < $n; $k++ ) {
				$cand = $pool[ ( $fallback_i + $k ) % $n ];
				if ( ! in_array( $cand, $used, true ) ) {
					$pick       = $cand;
					$fallback_i = ( $fallback_i + $k + 1 ) % $n;
					break;
				}
			}
			// Grid larger than the whole pool: allow a repeat rather than ship a blank.
			$icon = ( '' !== $pick ) ? $pick : $pool[ $fallback_i++ % $n ];
		}
		$used[] = $icon;
		return $icon;
	}

	private static function compose_card_grid( $s, $alt ) {
		$cards    = (array) $s['cards'];
		$columns  = isset( $s['columns'] ) ? max( 2, min( 4, (int) $s['columns'] ) ) : 3;
		$children = array();

		$children[] = self::heading_spec( $s['heading'], 'h2' );
		if ( ! empty( $s['intro'] ) ) {
			$children[] = self::text_spec( '<p>' . esc_html( (string) $s['intro'] ) . '</p>', 'center' );
		}

		// Group cards (preserving spec order of first appearance). Ungrouped
		// small sets render as a single anonymous group.
		$groups = array();
		foreach ( $cards as $c ) {
			$g = isset( $c['group'] ) ? trim( (string) $c['group'] ) : '';
			if ( ! isset( $groups[ $g ] ) ) {
				$groups[ $g ] = array();
			}
			$groups[ $g ][] = $c;
		}

		$fallback_i = 0;
		foreach ( $groups as $group_label => $group_cards ) {
			if ( '' !== $group_label ) {
				$children[] = self::heading_spec( $group_label, 'h3', 'center', 850 );
			}
			$grid_children = array();
			$used_icons    = array(); // distinct icon per card within this grid
			foreach ( $group_cards as $c ) {
				// DETERMINISTIC color tokens: every card gets the secondary (navy)
				// token + navy top-border. The ONLY exception is an explicit
				// spec opt-in — "tier": "primary" — which flips that card to the
				// primary (red) token + red border. The old card_tone() keyword
				// inference assigned primary/secondary based on title wording,
				// which read as random to operators and made sibling cards
				// mismatch for no visible reason.
				$is_primary_tier = isset( $c['tier'] ) && is_string( $c['tier'] ) && 'primary' === strtolower( trim( $c['tier'] ) );
				$icon_token      = $is_primary_tier ? 'globals/colors?id=primary' : 'globals/colors?id=secondary';
				$card_border     = $is_primary_tier ? self::kit_color( 'primary' ) : self::kit_color( 'secondary' );
				$card_settings = array(
					'title_text'       => (string) $c['title'],
					'description_text' => (string) $c['text'],
					'title_size'       => 'h4',
					'position'         => 'top',
					'text_align'       => 'left',
					'icon_size'        => array( 'unit' => 'px', 'size' => 28, 'sizes' => array() ),
					'title_typography_typography'  => 'custom',
					'title_typography_font_weight' => '700',
					'title_typography_font_size'   => array( 'unit' => 'px', 'size' => 18, 'sizes' => array() ),
					// Card surface (design-system C/D): white panel, brand top-accent
					// bar, soft shadow, rounded, padded — so cards read as cards on
					// any section bg instead of flat text. Colors via kit tokens.
					'_background_background' => 'classic',
					'_background_color'      => '#FFFFFF',
					'_border_border'         => 'solid',
					'_border_width'          => array( 'unit' => 'px', 'top' => '3', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ),
					'_border_color'          => $card_border,
					'_border_radius'         => array( 'unit' => 'px', 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'isLinked' => true ),
					'_padding'               => array( 'unit' => 'px', 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'isLinked' => true ),
					'_box_shadow_box_shadow_type' => 'yes',
					'_box_shadow_box_shadow'      => array( 'horizontal' => 0, 'vertical' => 1, 'blur' => 3, 'spread' => 0, 'color' => 'rgba(0,0,0,0.1)' ),
					'__globals__'            => array(
						'primary_color'     => $icon_token,
						'title_color'       => 'globals/colors?id=text',
						'description_color' => 'globals/colors?id=text',
						'_border_color'     => $icon_token,
					),
				);
				// ALWAYS give every card a real, distinct icon. Without this,
				// Elementor renders its default star on every icon-box — a wall of
				// identical stars (operator-flagged). The spec icon wins; otherwise
				// we infer one from the card's words, then guarantee no repeats
				// within the grid via a curated fallback rotation.
				$card_icon = self::card_icon( $c, $used_icons, $fallback_i );
				$card_settings['selected_icon'] = array( 'value' => $card_icon, 'library' => 'fa-solid' );
				// Cards with a url become REAL links on the icon-box widget.
				// Without this, operators had to re-add links to 100+ built
				// cards by hand. Only a non-empty STRING url qualifies.
				// v0.50: popup_id (int) WINS over url — the card opens an
				// Elementor popup via the exact action link, built server-side.
				if ( ! empty( $c['popup_id'] ) && (int) $c['popup_id'] > 0 ) {
					$card_settings['link'] = array( 'url' => CC_Assistant_Popups::popup_action_url( (int) $c['popup_id'] ), 'is_external' => '', 'nofollow' => '' );
				} elseif ( isset( $c['url'] ) && is_string( $c['url'] ) && '' !== trim( $c['url'] ) ) {
					$card_settings['link'] = array( 'url' => trim( $c['url'] ), 'is_external' => '', 'nofollow' => '' );
				}
				$grid_children[] = array(
					'type'       => 'widget',
					'widgetType' => 'icon-box',
					'settings'   => $card_settings,
				);
			}
			$children[] = array(
				'type'     => 'container',
				'settings' => array(
					'content_width'            => 'full',
					'width'                    => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
					'container_type'           => 'grid',
					'grid_columns_grid'        => array( 'unit' => 'fr', 'size' => $columns, 'sizes' => array() ),
					'grid_columns_grid_tablet' => array( 'unit' => 'fr', 'size' => 2, 'sizes' => array() ),
					'grid_columns_grid_mobile' => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
					// MANDATORY: omitting rows triggers Elementor's 2-row editor default
					// (uneven card stretching, operator-flagged twice). Gap key is
					// grid_gaps (plural); 'grid_gap' is silently ignored.
					'grid_rows_grid'           => array( 'unit' => 'custom', 'size' => 'auto', 'sizes' => array() ),
					'grid_auto_flow'           => 'row',
					'grid_gaps'                => array( 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '16', 'isLinked' => true ),
				),
				'children' => $grid_children,
			);
		}

		return array(
			'settings' => self::root_settings( $alt ? self::kit_color( 'neutral' ) : '' ),
			'children' => $children,
		);
	}

	/**
	 * A numbered process row — visually DISTINCT from card_grid (no card border or
	 * shadow; a large brand numeral is the anchor instead of an icon). Use for
	 * sequential content ("what happens when you arrive", "how we treat") so the
	 * page does not become a stack of identical card grids.
	 */
	private static function compose_steps( $s, $alt ) {
		$steps   = (array) $s['steps'];
		$columns = isset( $s['columns'] ) ? max( 2, min( 4, (int) $s['columns'] ) ) : min( 4, max( 2, count( $steps ) ) );
		$children = array();
		$children[] = self::heading_spec( $s['heading'], 'h2' );
		if ( ! empty( $s['intro'] ) ) {
			$children[] = self::text_spec( '<p>' . esc_html( (string) $s['intro'] ) . '</p>', 'center' );
		}

		$grid_children = array();
		$num           = 0;
		foreach ( $steps as $st ) {
			$num++;
			$item_children = array(
				// Big brand numeral = the visual anchor (not an icon-box card).
				array(
					'type'       => 'widget',
					'widgetType' => 'heading',
					'settings'   => array(
						'title'                        => (string) $num,
						// Decorative numeral: header_size 'div' keeps it OUT of the
						// heading outline (an h2 here created a skip to the h3 title
						// and littered the page with numeric h2s).
						'header_size'                  => 'div',
						'align'                        => 'left',
						'typography_typography'        => 'custom',
						'typography_font_weight'       => '700',
						'typography_font_size'         => array( 'unit' => 'px', 'size' => 44, 'sizes' => array() ),
						'typography_line_height'       => array( 'unit' => 'em', 'size' => 1, 'sizes' => array() ),
						'__globals__'                  => array( 'title_color' => 'globals/colors?id=primary' ),
					),
				),
				array(
					'type'       => 'widget',
					'widgetType' => 'heading',
					'settings'   => array(
						'title'                        => (string) $st['title'],
						// h3: section is h2, so the step title is the next level down
						// (no skip). Sized 22 so it stays >= h4 card titles (no
						// heading-size inversion) while reading as a step sub-heading.
						'header_size'                  => 'h3',
						'align'                        => 'left',
						'typography_typography'        => 'custom',
						'typography_font_weight'       => '700',
						'typography_font_size'         => array( 'unit' => 'px', 'size' => 22, 'sizes' => array() ),
						'_margin'                      => array( 'unit' => 'px', 'top' => '6', 'bottom' => '4', 'left' => '0', 'right' => '0', 'isLinked' => false ),
						'__globals__'                  => array( 'title_color' => 'globals/colors?id=text' ),
					),
				),
				array(
					'type'       => 'widget',
					'widgetType' => 'text-editor',
					'settings'   => array(
						'editor'                 => '<p>' . esc_html( (string) $st['text'] ) . '</p>',
						'align'                  => 'left',
						'typography_typography'  => 'custom',
						'typography_font_size'   => array( 'unit' => 'px', 'size' => 16, 'sizes' => array() ),
						'typography_line_height' => array( 'unit' => 'em', 'size' => 1.6, 'sizes' => array() ),
						'__globals__'            => array( 'text_color' => 'globals/colors?id=text' ),
					),
				),
			);
			$grid_children[] = array(
				'type'     => 'container',
				'settings' => array(
					'content_width'        => 'full',
					'flex_direction'       => 'column',
					'flex_align_items'     => 'flex-start',
					// Contrasting FLAT panel — bg is the opposite of the section bg so a
					// step never equals its background (operator hard rule), while
					// staying distinct from card_grid: flat + big brand numeral, no
					// border/shadow/icon (cards have a navy top-border + shadow + icon).
					'background_background' => 'classic',
					'background_color'      => $alt ? '#FFFFFF' : self::kit_color( 'neutral' ),
					'border_radius'         => array( 'unit' => 'px', 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'isLinked' => true ),
					'padding'               => array( 'unit' => 'px', 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'isLinked' => true ),
				),
				'children' => $item_children,
			);
		}
		$children[] = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'            => 'full',
				'width'                    => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
				'container_type'           => 'grid',
				'grid_columns_grid'        => array( 'unit' => 'fr', 'size' => $columns, 'sizes' => array() ),
				'grid_columns_grid_tablet' => array( 'unit' => 'fr', 'size' => 2, 'sizes' => array() ),
				'grid_columns_grid_mobile' => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
				'grid_rows_grid'           => array( 'unit' => 'custom', 'size' => 'auto', 'sizes' => array() ),
				'grid_auto_flow'           => 'row',
				'grid_gaps'                => array( 'unit' => 'px', 'size' => 32, 'column' => '32', 'row' => '24', 'isLinked' => false ),
			),
			'children' => $grid_children,
		);

		return array(
			'settings' => self::root_settings( $alt ? self::kit_color( 'neutral' ) : '' ),
			'children' => $children,
		);
	}

	private static function compose_faq( $s, $alt ) {
		$tabs = array();
		foreach ( (array) $s['items'] as $item ) {
			$tabs[] = array(
				'_id'         => substr( md5( wp_json_encode( $item ) . count( $tabs ) ), 0, 7 ),
				'tab_title'   => (string) $item['q'],
				'tab_content' => '<p>' . wp_kses_post( (string) $item['a'] ) . '</p>',
			);
		}
		$children   = array();
		$children[] = self::heading_spec( $s['heading'], 'h2' );
		if ( ! empty( $s['intro'] ) ) {
			$children[] = self::text_spec( '<p>' . esc_html( (string) $s['intro'] ) . '</p>', 'center' );
		}
		// Belt-and-braces: never emit a sub-readable title size (the 1.1px bug —
		// an em-scale sampled value re-emitted with a px unit made questions
		// invisible). Weight falls back to 600 when the sample left it empty.
		$faq_size   = ( is_numeric( self::$faq_title['size'] ) && (float) self::$faq_title['size'] >= 10 ) ? self::$faq_title['size'] : 20;
		$faq_weight = '' !== (string) self::$faq_title['weight'] ? self::$faq_title['weight'] : '600';
		$acc_settings = array(
			'tabs'                          => $tabs,
			'title_html_tag'                => 'h3',
			// Size the h3 down to the site's accordion-title scale (sampled) so the
			// FAQ titles don't render at full section-h3 size. Explicit kit-primary
			// title color so titles are never theme-default white on a light bg.
			'title_typography_typography'   => 'custom',
			'title_typography_font_size'    => array( 'unit' => 'px', 'size' => $faq_size, 'sizes' => array() ),
			'title_typography_font_weight'  => $faq_weight,
			'title_color'                   => self::kit_color( 'primary' ),
			'_element_width'                => 'initial',
			'_element_custom_width'         => array( 'unit' => 'px', 'size' => 900 ),
			'_flex_align_self'              => 'center',
			'__globals__'                   => array(
				'title_color'       => 'globals/colors?id=primary',
				'tab_active_color'  => 'globals/colors?id=secondary',
				'icon_color'        => 'globals/colors?id=secondary',
				'icon_active_color' => 'globals/colors?id=secondary',
			),
		);
		if ( '' !== self::$faq_title['family'] ) {
			$acc_settings['title_typography_font_family'] = self::$faq_title['family'];
		}
		$children[] = array(
			'type'       => 'widget',
			'widgetType' => 'accordion',
			'settings'   => $acc_settings,
		);
		return array(
			'settings' => self::root_settings( $alt ? self::kit_color( 'neutral' ) : '' ),
			'children' => $children,
		);
	}

	private static function compose_cta_band( $s, $alt ) {
		$bg      = isset( $s['background'] ) ? (string) $s['background'] : '';
		$on_dark = self::is_dark_hex( $bg );
		$children   = array();
		$children[] = self::heading_spec( $s['heading'], 'h2', 'center', 850, $on_dark );
		if ( ! empty( $s['text'] ) ) {
			$children[] = self::text_spec( '<p>' . esc_html( (string) $s['text'] ) . '</p>', 'center', 850, $on_dark );
		}
		$btns = self::buttons_row_spec( isset( $s['buttons'] ) ? $s['buttons'] : array(), $on_dark );
		if ( $btns ) {
			$children[] = $btns;
		}
		return array(
			'settings' => self::root_settings( $bg ),
			'children' => $children,
		);
	}

	/**
	 * True when a hex color is dark enough that white text belongs on it.
	 * Replaces the old hardcoded ER-palette whitelist (#DA1212/#11468F/#041562/
	 * #000000 — all still classified dark by this math) so self-branded sites
	 * passing their own dark kit color as a cta_band background get light text.
	 */
	private static function is_dark_hex( $hex ) {
		$hex = ltrim( trim( (string) $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return false;
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return ( ( 0.299 * $r ) + ( 0.587 * $g ) + ( 0.114 * $b ) ) / 255 < 0.5;
	}

	/**
	 * Contact/message form section — this site family's established standard
	 * structure: light-grey full section (#F4F4F4, _element_id "message")
	 * holding a 700px white card with heading, intro, and an Elementor Pro
	 * form (name / phone / email / message) wired to the site's standard
	 * notification recipients (option cc_assistant_form_email_standard, with
	 * admin_email fallbacks). Not an image-bearing section on purpose.
	 */
	private static function compose_form( $s, $alt ) {
		$heading = isset( $s['heading'] ) && is_string( $s['heading'] ) && '' !== trim( $s['heading'] )
			? trim( $s['heading'] )
			: 'Have a Question? Send Us a Message';
		$intro_html = isset( $s['intro_html'] ) && is_string( $s['intro_html'] ) && '' !== trim( $s['intro_html'] )
			? (string) $s['intro_html']
			: '<p>Fill out the form below, and our team will respond within 24 hours <strong>(for non-urgent matters only)</strong></p>';
		$page_label = isset( $s['page_label'] ) ? trim( (string) $s['page_label'] ) : '';
		$subject    = sprintf( 'New website form submission - %s page', $page_label );

		// Standard recipients from the site option; sane fallbacks when unset.
		// The _2 (second recipient) block is emitted ONLY when the option
		// actually defines email_to_2 — never invented.
		$std = get_option( 'cc_assistant_form_email_standard', array() );
		$std = is_array( $std ) ? $std : array();
		$email_to        = ! empty( $std['email_to'] ) ? (string) $std['email_to'] : (string) get_option( 'admin_email' );
		$email_from      = ! empty( $std['email_from'] ) ? (string) $std['email_from'] : (string) get_option( 'admin_email' );
		$email_from_name = ! empty( $std['email_from_name'] ) ? (string) $std['email_from_name'] : get_bloginfo( 'name' ) . ' Website';

		$form_settings = array(
			'form_name'   => $page_label . ' page form',
			'form_fields' => array(
				array( '_id' => 'name', 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'Name', 'placeholder' => 'Name' ),
				array( '_id' => 'phone', 'custom_id' => 'phone', 'field_type' => 'number', 'field_label' => 'Phone', 'placeholder' => 'Phone' ),
				array( '_id' => 'email', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Email', 'placeholder' => 'Email', 'required' => 'true' ),
				array( '_id' => 'message', 'custom_id' => 'message', 'field_type' => 'textarea', 'field_label' => 'Message', 'placeholder' => 'Message' ),
			),
			'submit_actions' => array( 'email' ),
			'email_to'        => $email_to,
			'email_from'      => $email_from,
			'email_from_name' => $email_from_name,
			'email_subject'   => $subject,
			'email_content'   => '[all-fields]',
			// Standard human-readable submit feedback.
			'custom_messages'        => 'yes',
			'success_message'        => 'Your submission was successful.',
			'error_message'          => 'Your submission failed because of an error.',
			'server_message'         => 'Your submission failed because of a server error.',
			'invalid_message'        => 'Your submission failed because it is invalid.',
			'required_field_message' => 'This field is required.',
			// Standard styling: black labels, brand-primary send button.
			'label_color'                   => '#000000',
			'html_color'                    => '#000000',
			'button_background_color'       => self::kit_color( 'primary' ),
			'button_background_hover_color' => self::kit_color( 'secondary' ),
			'__globals__'                   => array(
				'button_background_color'       => 'globals/colors?id=primary',
				'button_background_hover_color' => 'globals/colors?id=secondary',
			),
			'button_text'                   => 'Send',
			'button_width'                  => '30',
		);
		if ( ! empty( $std['email_to_2'] ) ) {
			$form_settings['submit_actions'][] = 'email2';
			$form_settings['email_to_2']       = (string) $std['email_to_2'];
			$form_settings['email_subject_2']  = $subject;
			$form_settings['email_content_2']  = '[all-fields]';
			if ( ! empty( $std['email_from_2'] ) ) {
				$form_settings['email_from_2'] = (string) $std['email_from_2'];
			}
			if ( ! empty( $std['email_from_name_2'] ) ) {
				$form_settings['email_from_name_2'] = (string) $std['email_from_name_2'];
			}
			if ( ! empty( $std['email_reply_to_2'] ) ) {
				$form_settings['email_reply_to_2'] = (string) $std['email_reply_to_2'];
			}
		}

		$inner = array(
			'type'     => 'container',
			'settings' => array(
				'flex_direction'        => 'column',
				'content_width'         => 'full',
				'width'                 => array( 'unit' => 'px', 'size' => 700, 'sizes' => array() ),
				'width_mobile'          => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
				'background_background' => 'classic',
				// Classic background with no color renders transparent; the card
				// must read as a white panel on the grey section.
				'background_color'      => '#FFFFFF',
				'padding'               => array( 'unit' => 'px', 'top' => '50', 'right' => '70', 'bottom' => '50', 'left' => '70', 'isLinked' => false ),
				'padding_mobile'        => array( 'unit' => 'px', 'top' => '30', 'right' => '20', 'bottom' => '30', 'left' => '20', 'isLinked' => false ),
			),
			'children' => array(
				array(
					'type'       => 'widget',
					'widgetType' => 'heading',
					'settings'   => array(
						'title'                       => $heading,
						'title_color'                 => self::kit_color( 'primary' ),
						'__globals__'                 => array( 'title_color' => 'globals/colors?id=primary' ),
						'typography_typography'       => 'custom',
						'typography_font_family'      => 'Montserrat',
						'typography_font_size'        => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ),
						'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 28, 'sizes' => array() ),
						'typography_font_weight'      => '600',
					),
				),
				array(
					'type'       => 'widget',
					'widgetType' => 'text-editor',
					'settings'   => array(
						'editor'                 => $intro_html,
						'text_color'             => '#000000',
						'typography_typography'  => 'custom',
						'typography_font_family' => 'Montserrat',
						'typography_font_weight' => '400',
					),
				),
				array(
					'type'       => 'widget',
					'widgetType' => 'form',
					'settings'   => $form_settings,
				),
			),
		);

		return array(
			'settings' => array(
				'content_width'         => 'boxed',
				'boxed_width'           => array( 'unit' => 'px', 'size' => 1300 ),
				'flex_direction'        => 'column',
				'flex_align_items'      => 'center',
				'padding'               => array( 'unit' => 'px', 'top' => '80', 'bottom' => '80', 'left' => '20', 'right' => '20', 'isLinked' => false ),
				'background_background' => 'classic',
				'background_color'      => self::kit_color( 'neutral' ),
				'_element_id'           => 'message',
			),
			'children' => array( $inner ),
		);
	}

	/**
	 * Full-bleed Google Maps embed — the site family's standard footer map
	 * (zero-margin/padding container, 400px map, _element_id "map"). Address
	 * resolution: spec address -> option cc_assistant_facility_address
	 * (validate_section refuses earlier when neither exists).
	 */
	private static function compose_map( $s, $alt ) {
		$address = isset( $s['address'] ) && is_string( $s['address'] ) ? trim( $s['address'] ) : '';
		if ( '' === $address ) {
			$address = trim( (string) get_option( 'cc_assistant_facility_address', '' ) );
		}
		return array(
			'settings' => array(
				'flex_direction' => 'column',
				'content_width'  => 'full',
				'margin'         => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ),
				'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ),
				'margin_mobile'  => array( 'unit' => 'px', 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ),
				'_element_id'    => 'map',
			),
			'children' => array(
				array(
					'type'       => 'widget',
					'widgetType' => 'google_maps',
					'settings'   => array(
						'address' => $address,
						'zoom'    => array( 'unit' => 'px', 'size' => 14, 'sizes' => array() ),
						'height'  => array( 'unit' => 'px', 'size' => 400, 'sizes' => array() ),
					),
				),
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers (mirrors of the proven build_service_page internals)
	 * ------------------------------------------------------------------- */

	private static function collect_per_widget_lint_payloads( array $tree ) {
		$out  = array();
		$walk = function ( $nodes ) use ( &$walk, &$out ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$st = isset( $n['settings'] ) && is_array( $n['settings'] ) ? $n['settings'] : array();
				$wt = isset( $n['widgetType'] ) ? (string) $n['widgetType'] : '';
				if ( 'widget' === ( $n['elType'] ?? '' ) && ! empty( $st ) ) {
					$html = '';
					foreach ( array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' ) as $f ) {
						if ( isset( $st[ $f ] ) && is_string( $st[ $f ] ) ) {
							$html .= "\n" . $st[ $f ];
						}
					}
					if ( '' !== trim( $html ) ) {
						$out[] = array(
							'html'     => $html,
							'settings' => $st,
							'label'    => '' !== $wt ? $wt : 'widget',
						);
					}
					// Accordion items are linted INDIVIDUALLY (one payload per Q&A),
					// not as one concatenated blob — a normal 6-item FAQ summed to
					// 200+ words and false-tripped the wall_of_text gate.
					if ( ! empty( $st['tabs'] ) && is_array( $st['tabs'] ) ) {
						foreach ( $st['tabs'] as $tab ) {
							$thtml = '';
							foreach ( array( 'tab_title', 'tab_content' ) as $f ) {
								if ( isset( $tab[ $f ] ) && is_string( $tab[ $f ] ) ) {
									$thtml .= "\n" . $tab[ $f ];
								}
							}
							if ( '' !== trim( $thtml ) ) {
								$out[] = array(
									'html'     => $thtml,
									'settings' => array(),
									'label'    => 'accordion-item',
								);
							}
						}
					}
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walk( $n['elements'] );
				}
			}
		};
		$walk( $tree );
		return $out;
	}

	private static function summarize_tree( array $tree ) {
		$widgets = 0;
		$types   = array();
		$walk    = function ( $nodes ) use ( &$walk, &$widgets, &$types ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if ( 'widget' === ( $n['elType'] ?? '' ) ) {
					$widgets++;
					$wt = isset( $n['widgetType'] ) ? (string) $n['widgetType'] : 'unknown';
					$types[ $wt ] = ( $types[ $wt ] ?? 0 ) + 1;
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walk( $n['elements'] );
				}
			}
		};
		$walk( $tree );
		return array(
			'root_containers' => count( $tree ),
			'total_widgets'   => $widgets,
			'widget_types'    => $types,
		);
	}
}
