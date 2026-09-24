<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_REST_API {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
        register_rest_route( self::REST_NAMESPACE, '/stack/capability', array(
            'methods' => 'POST', 'callback' => array( __CLASS__, 'handle_plugin_capability' ),
            'permission_callback' => array( __CLASS__, 'check_permission' ),
            'args' => array( 'slug' => array( 'type' => 'string', 'required' => true, 'pattern' => '^[a-z0-9][a-z0-9_-]*$' ),
                'option_name' => array( 'type' => 'string', 'maxLength' => 190 ), 'path' => array( 'type' => 'string', 'maxLength' => 500 ),
                'widget_type' => array( 'type' => 'string', 'maxLength' => 100 ), 'element_id' => array( 'type' => 'string', 'maxLength' => 100 ),
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
        ) );
		register_rest_route( self::REST_NAMESPACE, '/verified-audit/(?P<id>\d+)', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'handle_verified_page_audit' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );
		register_rest_route(
			self::REST_NAMESPACE,
			'/whoami',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_whoami' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.65: stack introspection — what is installed, where its UI lives,
		// and what it is set to. Read-only; replaces guessing at wp-admin paths.
		register_rest_route(
			self::REST_NAMESPACE,
			'/stack/plugins',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_stack_plugins' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'include_inactive' => array( 'default' => true ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/stack/admin-menu',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_stack_admin_menu' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/stack/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_stack_settings' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'slug' => array( 'required' => true ),
				),
			)
		);

		// 0.44: render-introspection probe — loopback-fetch the post's own front
		// end and read the rendered DOM (schema/emitter, resolved alt, headings).
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/render-probe',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_render_probe' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'extract' => array( 'default' => '' ),
				),
			)
		);

		// 0.44: full nested-node Elementor tree (every container + widget id).
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/elementor-tree',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_elementor_tree' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// 0.44: site-wide schema-source scanner (aggregates render_probe schema).
		register_rest_route(
			self::REST_NAMESPACE,
			'/schema-scan',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_schema_scan' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'post_ids' => array( 'default' => '' ),
					'limit'    => array( 'default' => 12 ),
				),
			)
		);

		// 0.44: redirect-hygiene audit (Rank Math) — funnel-to-homepage + chains.
		register_rest_route(
			self::REST_NAMESPACE,
			'/redirect-audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_redirect_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// 0.45.1: visibility for the schema the plugin injects itself (per-post
		// _cc_emergency_service_schema / _cc_assistant_schema_jsonld postmeta).
		register_rest_route(
			self::REST_NAMESPACE,
			'/managed-schema',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_managed_schema' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'post_id' => array( 'default' => 0 ),
				),
			)
		);

		// 0.50: Elementor popup inventory + condition coverage (read-only).
		register_rest_route(
			self::REST_NAMESPACE,
			'/popups',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list_popups' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'covers_post_id' => array( 'default' => 0 ),
				),
			)
		);

		// 0.51.6: Theme Builder template inventory + conditions-cache view
		// (read-only). Exists because diagnosing "why doesn't my 404 template
		// render" blind cost a whole debugging session: template meta,
		// taxonomy, conditions, and the Pro conditions cache were all
		// invisible through the tool surface.
		register_rest_route(
			self::REST_NAMESPACE,
			'/theme-templates',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list_theme_templates' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// 0.51.6: force-regenerate the Elementor Pro Theme Builder conditions
		// cache. Maintenance action, content-neutral, applied immediately (no
		// pending): it only rebuilds Pro's own index of already-approved
		// templates. Fixes the stale-cache class of failures (template deleted
		// or edited outside the plugin).
		register_rest_route(
			self::REST_NAMESPACE,
			'/theme-templates/refresh-conditions',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_refresh_theme_conditions' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// 0.44: atomic section rebuild (add new + remove old as ONE pending).
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/elementor-section-rebuild',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_rebuild_section' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// 0.45: semantic section recipe -> brand-correct tree (add or rebuild).
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/add-section',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_add_section' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// 0.14: long-form SEO playbook. whoami ships the top_rules summary;
		// this endpoint returns the full sections for deeper lookups.
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo-playbook',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_seo_playbook' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list_posts' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					// Empty default = search posts AND pages. The old 'page'
					// default made list_posts(search=...) silently miss every
					// blog post. Pass 'post' or 'page' to filter to one type;
					// 'any' behaves like the default.
					'post_type' => array( 'default' => '' ),
					'status'    => array( 'default' => 'any' ),
					'per_page'  => array( 'default' => 20 ),
					'page'      => array( 'default' => 1 ),
					'search'    => array( 'default' => '' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_post' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'slim'      => array( 'default' => false ),
					'widget_id' => array( 'default' => '' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/elementor-widgets',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_elementor_widgets' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'format' => array( 'default' => 'summary' ),
				),
			)
		);

		// v0.34: annotated, section-aware page map. Mandatory pre-flight before
		// any root-level container_add — the layout gate refuses with 422 if
		// this endpoint hasn't been hit for the post within the last 10 minutes.
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/page-map',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_page_map' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'include_ascii' => array( 'default' => true ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/quality-gate',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_quality_gate' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.52: layout-spec + layout-compare — full per-widget spec and a
		// deep byte-level diff against a reference, so a build match is
		// provable rather than eyeballed. Read-only; accept any Elementor
		// post (page OR Theme Builder template) so a product template can be
		// diffed against a machine page.
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/layout-spec',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_layout_spec' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/layout-compare',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_layout_compare' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'reference_id' => array( 'required' => true ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/entity-lookup',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_entity_lookup' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'q' => array( 'required' => true ),
				),
			)
		);

		// v0.41: win_audit — score a page vs 2026 criteria + live competitors.
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/win-audit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_win_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.43: page_robustness_audit — three-layer page standard
		// (intent -> conversion -> trust), industry + business-mode aware.
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/robustness-audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_page_robustness_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'strict' => array( 'default' => '1' ),
				),
			)
		);

		// v0.29: cross-site Elementor data I/O. Export returns the raw
		// _elementor_data + Kit globals so the importer on the target
		// site can map color/typography tokens. Import accepts the raw
		// data + transform options (text replacements, ID regeneration,
		// kit globals mapping, image stripping) and queues a single
		// pending change — one approval clones an entire page.
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/elementor-export',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_elementor_export' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/elementor-import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_elementor_import' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		// v0.30: post-build/import design audit + image placeholder registry
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/audit-design',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_audit_design' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/image-placeholders',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_image_placeholders' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		// v0.31: section-level edit tools.
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/sections',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list_sections' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/sections/replace-content',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_replace_section_content' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/clusters',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_find_clusters' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'post_type' => array( 'default' => 'page' ),
					'threshold' => array( 'default' => 0.7 ),
					'limit'     => array( 'default' => 200 ),
					'format'    => array( 'default' => 'summary' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/clusters/analyze',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_analyze_cluster' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'post_ids'      => array( 'required' => true ),
					'excerpt_chars' => array( 'default' => 500 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/pending',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list_pending' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/post-meta',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_post_meta' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/postmeta',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_postmeta' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/trash-post',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_trash_post' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/elementor-widget',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_elementor_widget' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/elementor-widget-add',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_elementor_widget_add' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/elementor-widget-remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_elementor_widget_remove' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/elementor-container-add',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_elementor_container_add' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.19: build_service_page macro tool — clone a working pillar's
		// Elementor tree into a NEW draft with text replacements, in ONE call.
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/build-service-page',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_build_service_page' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.40: build_page_from_spec — compose a NEW page from typed section
		// templates (hero/text_image/card_grid/faq/cta_band) in one call.
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/build-from-spec',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_build_from_spec' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.22: suggest_best_mirror — rank existing service pages by clone-
		// readiness so build_service_page gets a pre-vetted mirror_post_id.
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/suggest-best-mirror',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'handle_suggest_best_mirror' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.19: page_completeness_score — structural compare to a mirror.
		register_rest_route(
			self::REST_NAMESPACE,
			'/page-completeness-score',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'handle_page_completeness_score' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// v0.19: service_inventory — list real service tiers from existing pillars.
		register_rest_route(
			self::REST_NAMESPACE,
			'/service-inventory',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'handle_service_inventory' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/elementor-accordion-item-add',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_elementor_accordion_item_add' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/elementor-accordion-item-remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_elementor_accordion_item_remove' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Read-only page style scanner. Returns the global colors, typography,
		// font families, and most common container shapes on a page so a fresh
		// widget add can match the existing page styling instead of bringing
		// its own. No queueing, no mutation; just inspection.
		register_rest_route(
			self::REST_NAMESPACE,
			'/elementor/style-context',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_elementor_style_context' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/post-content',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_post_content' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/post-content-patch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_post_content_patch' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/outline',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_outline' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/create-post',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_create_post' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/categories',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_categories' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/category/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_category_create' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/category/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_category_list' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/category/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_category_delete' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/terms/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_terms_list' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'taxonomy' => array( 'default' => 'product_cat' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/term',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_term' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		// v0.69 — write one plugin setting, draft-queued.
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/plugin-setting',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_plugin_setting' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		// v0.76 — Elementor kit (Site Settings): read, and write one leaf draft-queued.
		register_rest_route(
			self::REST_NAMESPACE,
			'/kit-settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_kit_settings' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/kit-setting',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_kit_setting' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		// v0.69 — bulk taxonomy-term assignment (brands, categories, tags).
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/bulk-terms',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_bulk_terms' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/schema/rank-math',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_rank_math_schema' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array( 'post_id' => array( 'required' => true ) ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/rank-math-schema',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_rank_math_schema' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/pending/(?P<id>\d+)/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_apply_pending' ),
				'permission_callback' => array( 'CC_Assistant_Access', 'can_review' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/pending/(?P<id>\d+)/reject',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_reject_pending' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/link-audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_link_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'check_external' => array( 'default' => false ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/pre-publish-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_pre_publish' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/style-guide',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_style_guide' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/site-memory',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_site_memory' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/site-memory/notes',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_update_site_notes' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/seo-meta',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_seo_meta' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/redirect',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_redirect' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/redirect/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_redirect_delete' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/redirect/untrash',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_redirect_untrash' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/working-state',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'handle_get_working_state' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'handle_update_working_state' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gbp/locations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gbp_locations' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gbp/performance',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gbp_performance' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/draft/emergency-service-schema',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_draft_emergency_service_schema' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/inspect-url',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_inspect_url' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/similarity/find-duplicates',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_find_duplicate_content' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_health' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_status' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/opportunities',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_opportunities' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'min_position'    => array( 'default' => 5 ),
					'max_position'    => array( 'default' => 15 ),
					'min_impressions' => array( 'default' => 50 ),
					'limit'           => array( 'default' => 100 ),
					'days'            => array( 'default' => 28 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/low-ctr',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_low_ctr' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'min_impressions' => array( 'default' => 1000 ),
					'max_ctr'         => array( 'default' => 0.02 ),
					'limit'           => array( 'default' => 100 ),
					'days'            => array( 'default' => 28 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/missing-mentions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_missing_mentions' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'min_impressions' => array( 'default' => 100 ),
					'limit'           => array( 'default' => 50 ),
					'days'            => array( 'default' => 28 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/page-queries',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_page_queries' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'post_id' => array( 'default' => 0 ),
					'page'    => array( 'default' => '' ),
					'limit'   => array( 'default' => 100 ),
					'days'    => array( 'default' => 28 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/trends',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_trends' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'window_days' => array( 'default' => 7 ),
					'lens'        => array( 'default' => 'all' ),
					'limit'       => array( 'default' => 25 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/edits',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list_edits' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array( 'limit' => array( 'default' => 25 ) ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/edits/(?P<id>\d+)/outcome',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_edit_outcome' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/anomalies',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_anomalies' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'window_days'   => array( 'default' => 7 ),
					'baseline_days' => array( 'default' => 28 ),
					'direction'     => array( 'default' => 'both' ),
					'limit'         => array( 'default' => 25 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/ai-overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_ai_overview' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'days'  => array( 'default' => 28 ),
					'limit' => array( 'default' => 50 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/aio-ctr-drop',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_aio_ctr_drop' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'min_impressions' => array( 'default' => 500 ),
					'max_position'    => array( 'default' => 5.0 ),
					'max_ratio'       => array( 'default' => 0.5 ),
					'limit'           => array( 'default' => 50 ),
					'days'            => array( 'default' => 28 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gsc/intent-breakdown',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gsc_intent_breakdown' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'days'    => array( 'default' => 28 ),
					'post_id' => array( 'default' => 0 ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/llm-crawls',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_llm_crawls' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array( 'days' => array( 'default' => 7 ) ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/links/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_links_summary' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/links/orphans',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_links_orphans' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'limit'           => array( 'default' => 50 ),
					'include_pending' => array( 'default' => false ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/audits',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_content_audits' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array( 'limit' => array( 'default' => 200 ) ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connection/test',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_connection_test' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/facts/coverage',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_page_facts_coverage' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/facts/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_page_facts' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/links/audit/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_links_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/links/rebuild',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_links_rebuild' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/polylang/link-translations',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_polylang_link_translations' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/polylang/inspect/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_polylang_inspect' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/topical-authority',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_topical_authority' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'post_type' => array( 'default' => 'post' ),
					'threshold' => array( 'default' => 0.55 ),
					'limit'     => array( 'default' => 200 ),
					'days'      => array( 'default' => 28 ),
				),
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
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	/* ------------------------------------------------------------------
	 * v0.65 stack introspection handlers (read-only)
	 * ---------------------------------------------------------------- */

	public static function handle_stack_plugins( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-stack-introspect.php';
		$inactive = $request->get_param( 'include_inactive' );
		$inactive = ! ( 'false' === $inactive || '0' === $inactive || false === $inactive );
		return rest_ensure_response( CC_Assistant_Stack_Introspect::plugins( $inactive ) );
	}

	public static function handle_stack_admin_menu() {
		require_once CC_ASSISTANT_DIR . 'includes/class-stack-introspect.php';
		return rest_ensure_response( CC_Assistant_Stack_Introspect::admin_menu_snapshot() );
	}

	public static function handle_plugin_capability( $request ) { require_once __DIR__ . '/class-plugin-capability.php'; return self::wrap( CC_Assistant_Plugin_Capability::inspect( $request->get_params() ) ); }
	public static function handle_stack_settings( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-stack-introspect.php';
		$res = CC_Assistant_Stack_Introspect::plugin_settings( $request->get_param( 'slug' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( $res );
	}

	public static function handle_whoami() {
		require_once CC_ASSISTANT_DIR . 'includes/class-content-strategy.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';

		$identity = CC_Assistant_Site_Identity::whoami();

		// Stamp this session bootstrapped so mutating handlers can tell whether
		// the chat went through pre-flight before it starts editing.
		require_once CC_ASSISTANT_DIR . 'includes/class-session-gate.php';
		CC_Assistant_Session_Gate::mark_bootstrapped();

		$recent      = CC_Assistant_Pending_Changes::list_pending( 'pending', 5 );
		$recent_brief = array();
		foreach ( $recent as $r ) {
			$recent_brief[] = array(
				'id'         => (int) $r->id,
				'post_id'    => (int) $r->post_id,
				'type'       => $r->change_type,
				'summary'    => $r->change_summary,
				'status'     => $r->status,
				'created_at' => $r->created_at,
			);
		}

		// v0.81: the post-apply audits (render health, rendered schema,
		// duplication verifier) already write verdicts onto the applied row.
		// Nobody read them at bootstrap, so a change that broke the page was
		// visible only on the inbox card. Surface the last five applied rows'
		// verdicts, compacted to one line per audit.
		$recent_applied = array();
		foreach ( CC_Assistant_Pending_Changes::list_pending( 'approved', 5 ) as $r ) {
			$recent_applied[] = array(
				'id'           => (int) $r->id,
				'post_id'      => (int) $r->post_id,
				'type'         => $r->change_type,
				'summary'      => $r->change_summary,
				'reviewed_at'  => isset( $r->reviewed_at ) ? $r->reviewed_at : null,
				'verification' => self::compact_verification( isset( $r->verification_result ) ? $r->verification_result : null ),
			);
		}

		$snapshots = CC_Assistant_Snapshots::list_snapshots( null, 1 );
		$last_snap = ! empty( $snapshots ) ? array(
			'id'         => (int) $snapshots[0]->id,
			'post_id'    => (int) $snapshots[0]->post_id,
			'type'       => $snapshots[0]->snapshot_type,
			'created_at' => $snapshots[0]->created_at,
		) : null;

		$notes        = CC_Assistant_Site_Memory::get_notes();
		$notes_tail   = '';
		$rules_section = '';
		$decisions_tail = '';
		if ( '' !== $notes ) {
			// Structured-section parse: Rules and Decisions are permanent and
			// surface in full (capped) so the model rereads them every session.
			// Sessions is append-only and only the tail is shown.
			$parsed = CC_Assistant_Site_Memory::parse_sections( $notes );
			$rules  = trim( (string) ( $parsed[ CC_Assistant_Site_Memory::SECTION_RULES ] ?? '' ) );
			$dec    = trim( (string) ( $parsed[ CC_Assistant_Site_Memory::SECTION_DECISIONS ] ?? '' ) );
			$sess   = trim( (string) ( $parsed[ CC_Assistant_Site_Memory::SECTION_SESSIONS ] ?? '' ) );
			$rules_section  = strlen( $rules ) > 1500 ? substr( $rules, 0, 1500 ) . '…' : $rules;
			$decisions_tail = strlen( $dec ) > 800 ? '…' . substr( $dec, -800 ) : $dec;
			$notes_tail     = strlen( $sess ) > 500 ? '…' . substr( $sess, -500 ) : $sess;
		}

		// Reject-feedback loop: surface the last 3 reviewer notes from rejected
		// pending changes so the next session sees patterns ("paragraphs too
		// long", "wrong meta key") instead of repeating the same mistakes.
		$recent_rejected = CC_Assistant_Pending_Changes::recent_rejected_with_notes( 3 );

		// Post-drift summary: how many posts have been modified outside the
		// plugin since their last applied edit. The model should re-read
		// before proposing further changes on these.
		$drifted_count = CC_Assistant_Edit_Outcomes::drifted_count();

		// Notes-oversized advisory: set by site-memory when the blob crossed
		// the 30 KB soft cap. Surfaced here so the next session is prompted
		// to consolidate before appending more.
		$notes_oversized = (int) get_transient( 'cc_assistant_notes_oversized' );

		// 0.14: rolling activity log + bundled SEO playbook. Together these make
		// whoami the explicit "what should the AI know on a fresh chat" pack.
		// The activity_log is hard-capped to the last 48 h (pruned daily by
		// cron) so a long-running site doesn't bloat the response over time.
		// The seo_playbook top-rules ship every session so the model can't
		// regress to stale 2018-era keyword-density advice; the FULL playbook
		// is available via the seo_playbook MCP tool for deeper lookups.
		require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-playbook.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-playbook-fit.php';
		$activity_log = CC_Assistant_Activity_Log::recent_window( 48, 30 );
		$activity_summary = CC_Assistant_Activity_Log::summary_window( 48 );
		// Resolve once and reuse — top_rules and the industry pack both need it,
		// and the option read is cheap but the auto-detect path that runs on
		// first-ever read does HTTP, so we don't want to fire it twice.
		$industry_profile = CC_Assistant_Industry_Profile::get();
		$industry_slug    = isset( $industry_profile['industry'] ) ? $industry_profile['industry'] : 'general';
		// Playbook-fit audit: compares overlay vocabulary against site vocabulary
		// so a wrong-overlay or stale-overlay match gets flagged BEFORE the AI
		// acts on the industry-specific rules. Cached 24h, no HTTP after the
		// first run of the day. Cheap to read on every session.
		$playbook_fit = CC_Assistant_Playbook_Fit::audit();

		require_once CC_ASSISTANT_DIR . 'includes/class-working-state.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-page-intent.php';

		$business_mode = CC_Assistant_Page_Intent::business_mode();

		// v0.59: the plugin describes itself — a versioned feature + workflow
		// index so a brand-new chat can never miss a capability we built.
		require_once CC_ASSISTANT_DIR . 'includes/class-capabilities.php';

		// v0.69: connection-health warnings, so a dead GSC link is announced at
		// bootstrap instead of discovered mid-task by a failing data call.
		// Field gap: a production site's OAuth expired silently and every
		// audit that session was flying blind until a random call 400'd.
		$health_warnings = array();
		if ( class_exists( 'CC_Assistant_GSC' ) ) {
			$gsc_status = CC_Assistant_GSC::status();
			if ( empty( $gsc_status['connected'] ) ) {
				$was_connected = ! empty( $gsc_status['property'] ) || ! empty( $gsc_status['last_sync_at'] ) || $gsc_status['rows_cached'] > 0;
				$health_warnings[] = array(
					'code'    => $was_connected ? 'gsc_connection_lost' : 'gsc_never_connected',
					'message' => $was_connected
						? 'Search Console WAS connected (property/sync history exists) but the connection is now DEAD — token likely expired or revoked. All gsc_* tools will fail and outcome measurement is blind. Tell the operator to reconnect in Settings > Search Console before doing data-driven work.'
						: 'Search Console has never been connected on this site. gsc_* tools are unavailable; prioritize by on-site signals only.',
					'last_error' => isset( $gsc_status['last_error'] ) ? $gsc_status['last_error'] : null,
				);
			}
		}

		// v0.79 Operator Brain: what this site stores + the bin/ hashes it ships.
		// The bridge compares both against the laptop and rewrites this block
		// into a verdict (in_sync / local_missing / site_newer / stale bridge).
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-operator-kit.php';
		$identity['operator_brain'] = CC_Assistant_REST_Operator_Kit::brain_summary();
		$identity['bridge_hashes']  = CC_Assistant_REST_Operator_Kit::bridge_hashes();

		$identity['session_recap'] = array(
			// Returned IN FULL (never truncated): the structured current-job
			// record. If status=active, you are MID-TASK — resume from it,
			// honor its decisions/constraints, do not re-litigate them.
			'working_state' => CC_Assistant_Working_State::get(),
			// v0.69: non-empty ONLY when something needs operator attention.
			'health_warnings'    => $health_warnings,
			// The ordered pre-flight every NEW chat must run before it edits.
			'preflight'          => CC_Assistant_Session_Gate::preflight(),
			'capabilities'       => CC_Assistant_Capabilities::manifest(),
			'pending_count'      => CC_Assistant_Pending_Changes::count_pending(),
			'recent_pending'     => $recent_brief,
			// v0.81: post-apply verdicts. Any entry with verification.attention=true
			// is an applied change that the audits flagged — look before new work.
			'recent_applied'     => $recent_applied,
			'last_snapshot'      => $last_snap,
			'rules'              => $rules_section,        // permanent site conventions — read these every session
			'decisions_tail'     => $decisions_tail,        // tail of Decisions section
			'notes_tail'         => $notes_tail,            // tail of Sessions section (timestamped log)
			'notes_updated_at'   => get_option( 'cc_assistant_site_notes_updated', '' ),
			'recent_rejected'    => $recent_rejected,
			'drifted_post_count' => $drifted_count,
			// v0.75.0: how much of the site has a fresh rendered-facts record.
			// page_facts(post_id) is the read path; it is the ground truth for
			// what a page contains, so read it before claiming anything is
			// present or absent on a page.
			'facts_coverage'     => ( function () {
				try {
					require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
					return CC_Assistant_Page_Facts::coverage();
				} catch ( \Throwable $e ) {
					return array( 'error' => $e->getMessage() );
				}
			} )(),
			'notes_oversized'    => $notes_oversized > 0 ? $notes_oversized : null,
			'activity_log_48h'   => $activity_log,                                // structured event ticker
			'activity_summary'   => $activity_summary,                            // {type: count} for the 48h window
			'industry'           => array(
				'slug'       => $industry_slug,
				'label'      => CC_Assistant_Industry_Profile::label( $industry_slug ),
				'source'     => isset( $industry_profile['source'] ) ? $industry_profile['source'] : 'auto',
				'confidence' => isset( $industry_profile['confidence'] ) ? (int) $industry_profile['confidence'] : 0,
			),
			'content_strategy_policy' => CC_Assistant_Content_Strategy::policy(),
			'memory_consistency' => CC_Assistant_Memory_Policy::audit( $notes ),
			'seo_playbook'       => array(
				'version'   => CC_Assistant_SEO_Playbook::VERSION,
				'industry'  => $industry_slug,
				'top_rules' => CC_Assistant_SEO_Playbook::top_rules( $industry_slug ),
				'fit'       => $playbook_fit,                                    // self-audit: does overlay vocab match site vocab?
				'full_via'  => 'seo_playbook',                                    // MCP tool name for the long form
			),
			// v0.43: the page standard every build/optimize must clear, and the
			// business mode that flips how it is judged (ER vs wellness clinic).
			'business_mode'      => array(
				'mode'   => $business_mode,
				'source' => CC_Assistant_Page_Intent::mode_source(),
				'means' => 'Business-mode classification is context, not verified services, hours, licensing or schema eligibility. Confirm actual business facts and the page reader task before changing calls to action or structured data.',
			),
			'page_standard' => array(
				'doctrine' => 'Make the primary reader task clear. Service pages can include useful explanations that help the decision; distinct supporting articles should earn their own purpose.',
				'layers' => array( '1_intent' => 'Inspect the actual audience, task and supported business offering.',
					'2_conversion' => 'Use clear headings, accessible navigation and a relevant next step supported by current service facts.',
					'3_trust' => 'Support factual claims with appropriate reliable sources and authorized attribution. No fixed citation quota, invented expertise or assumed service hours.' ),
				'assessment' => 'Use verified_page_audit for current evidence. Local checklist scores do not prove ranking effects or prescribe a rewrite.',
				'workflow' => 'content_workflow',
			),
			'evidence_policy' => array( 'enforced_by' => 'WordPress shared proposal queue', 'ttl_seconds' => 600, 'scope' => 'WordPress user and Application Password/browser session', 'required_reads' => array( 'whoami', 'verified_page_audit for each published target', 'get_post(slim=false) for each draft/template', 'get_kit_settings for kit edits', 'get_plugin_settings for option edits' ), 'approval' => 'The observed post and plugin/theme/kit/SEO environment must still match at approval. A sibling approval changes the page too: re-read and re-plan remaining changes.', 'unknown' => 'Missing evidence, CAPTCHA and unsupported controls block proposals. Stored values do not prove visible effects or Google indexing.' ),
			'bootstrap_hint' => 'Start with whoami, get_site_memory, existing operator knowledge and list_pending_changes. For content tasks use content_workflow and execute its steps: Claude discovers and maintains the strategy from site evidence, researches, drafts and verifies; the operator reviews concrete results. Do not ask for manual strategy setup or post IDs that tools can discover. Check version_drift and refresh a stale bridge through operator_brain_pull(what=bridge). Read actual plugin/editor controls before proposing changes. Use verified_page_audit for published targets and get_post(slim=false) for drafts/templates. Business-mode detection and stored content are observations to verify, not authority to invent services, hours, credentials or claims. seo_playbook.top_rules is the shared current guidance; preserve explicit operator constraints. Report facts, interpretations and unknowns separately.',
		);

		return self::wrap( $identity );
	}

	/**
	 * GET /posts/{id}/render-probe — loopback-fetch the post's OWN front end and
	 * introspect the rendered DOM: every JSON-LD block + its emitter (with the
	 * self-serving-rating / duplicate-breadcrumb / missing-name lints), resolved
	 * link accessible names (so alt'd image links stop reading as "empty"), alt
	 * coverage, and the heading outline. The reliable verify path when an edge
	 * WAF 403s external curl.
	 *
	 * `extract` is an optional comma list: schema,links,images,headings.
	 */
	public static function handle_render_probe( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-render-probe.php';
		$post_id = (int) $request['id'];
		$extract = (string) $request->get_param( 'extract' );
		$facets  = '' !== trim( $extract ) ? array_map( 'trim', explode( ',', $extract ) ) : null;
		$result  = CC_Assistant_Render_Probe::probe( $post_id, $facets );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				404
			);
		}

		// v0.59 diff mode: the verify workflow probes BEFORE and AFTER an
		// edit — two full DOM extractions just to spot the delta. With
		// diff=true we return ONLY what changed vs the previous probe of this
		// post (kept 24h), plus untouched-section counts. Token cost drops
		// from two full payloads to one full + one small delta.
		$diff_mode = filter_var( $request->get_param( 'diff' ), FILTER_VALIDATE_BOOLEAN );
		$snap_key  = 'cc_probe_snap_' . $post_id;
		if ( $diff_mode ) {
			$baseline = get_transient( $snap_key );
			set_transient( $snap_key, $result, DAY_IN_SECONDS );
			if ( ! is_array( $baseline ) ) {
				return self::wrap( array_merge( $result, array( 'diff_note' => 'No baseline probe stored for this post — full result returned; the next diff=true call will diff against THIS one.' ) ) );
			}
			return self::wrap( self::probe_diff( $baseline, $result ) );
		}
		set_transient( $snap_key, $result, DAY_IN_SECONDS );
		return self::wrap( $result );
	}

	/**
	 * Compact facet-by-facet delta between two probe results (v0.60.1
	 * rewrite: probe facets are KEYED reports — links={total, empty_links...},
	 * headings={counts, outline...} — not flat lists; the original list-diff
	 * produced wrong or bloated deltas). Scalars diff to from/to, lists diff
	 * count-aware (a duplicated breadcrumb block is visible), nested reports
	 * recurse. Unchanged facets collapse to {unchanged:true}.
	 */
	private static function probe_diff( $baseline, $current ) {
		$out = array( 'diff' => true );
		foreach ( array( 'schema', 'links', 'images', 'headings' ) as $facet ) {
			$old = isset( $baseline[ $facet ] ) ? $baseline[ $facet ] : null;
			$new = isset( $current[ $facet ] ) ? $current[ $facet ] : null;
			if ( wp_json_encode( $old ) === wp_json_encode( $new ) ) {
				$out[ $facet ] = array( 'unchanged' => true );
				continue;
			}
			$out[ $facet ] = self::report_delta(
				is_array( $old ) ? $old : array(),
				is_array( $new ) ? $new : array()
			);
		}
		return $out;
	}

	private static function report_delta( $old, $new ) {
		$delta = array();
		foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $k ) {
			$o = isset( $old[ $k ] ) ? $old[ $k ] : null;
			$n = isset( $new[ $k ] ) ? $new[ $k ] : null;
			if ( wp_json_encode( $o ) === wp_json_encode( $n ) ) {
				continue;
			}
			$o_list = is_array( $o ) && array_values( $o ) === $o;
			$n_list = is_array( $n ) && array_values( $n ) === $n;
			if ( $o_list || $n_list ) {
				$delta[ $k ] = self::list_delta( is_array( $o ) ? $o : array(), is_array( $n ) ? $n : array() );
			} elseif ( is_array( $o ) && is_array( $n ) ) {
				$delta[ $k ] = self::report_delta( $o, $n );
			} else {
				$delta[ $k ] = array( 'from' => $o, 'to' => $n );
			}
		}
		return $delta;
	}

	/** Count-aware (multiset) list diff — duplicates are counted, not collapsed. */
	private static function list_delta( $old, $new ) {
		$tally = function ( $items ) {
			$c = array();
			foreach ( $items as $i ) {
				$k       = (string) wp_json_encode( $i );
				$c[ $k ] = isset( $c[ $k ] ) ? $c[ $k ] + 1 : 1;
			}
			return $c;
		};
		$oc      = $tally( $old );
		$nc      = $tally( $new );
		$added   = array();
		$removed = array();
		foreach ( $nc as $k => $n ) {
			for ( $i = isset( $oc[ $k ] ) ? $oc[ $k ] : 0; $i < $n; $i++ ) {
				$added[] = json_decode( $k, true );
			}
		}
		foreach ( $oc as $k => $n ) {
			for ( $i = isset( $nc[ $k ] ) ? $nc[ $k ] : 0; $i < $n; $i++ ) {
				$removed[] = json_decode( $k, true );
			}
		}
		return array( 'added' => $added, 'removed' => $removed, 'unchanged_count' => count( $new ) - count( $added ) );
	}

	/**
	 * GET /posts/{id}/elementor-tree — every nested node (id / el_type /
	 * widget_type / depth / parent_id / position / path / child_count + a
	 * grid/flex layout hint + label). Replaces the export+Python dance for
	 * reaching middle containers (grid rows, column wrappers).
	 */
	public static function handle_elementor_tree( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-map.php';
		$post_id = (int) $request['id'];
		$g = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $g ) ) { return $g; }
		$tree    = CC_Assistant_Elementor_Map::build_full_tree( $post_id );
		if ( null === $tree ) {
			return new WP_REST_Response(
				array( 'code' => 'no_elementor_data', 'message' => 'No Elementor data for this post (or not an Elementor page).' ),
				404
			);
		}
		return self::wrap( $tree );
	}

	/**
	 * GET /schema-scan — aggregate the render_probe schema lint across a sample
	 * of pages (or explicit post_ids). Surfaces self-serving aggregateRating,
	 * duplicate/broken breadcrumbs, and which emitter owns each problem.
	 */
	public static function handle_schema_scan( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-schema-scan.php';
		$raw_ids  = trim( (string) $request->get_param( 'post_ids' ) );
		$post_ids = '' !== $raw_ids ? array_values( array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $raw_ids ) ) ) ) : null;
		$limit    = (int) $request->get_param( 'limit' );
		$result   = CC_Assistant_Schema_Scan::scan( $post_ids, $limit ? $limit : 12 );
		return self::wrap( $result );
	}

	/**
	 * GET /redirect-audit — read-only Rank Math redirect-hygiene check: flags
	 * content->homepage funnels and redirect chains.
	 */
	public static function handle_redirect_audit() {
		require_once CC_ASSISTANT_DIR . 'includes/class-redirect-audit.php';
		return self::wrap( CC_Assistant_Redirect_Audit::audit() );
	}

	/**
	 * GET /managed-schema — read-only report of the JSON-LD the plugin injects
	 * itself (per-post postmeta) and the live enabled state of each emitter.
	 * Pass post_id for the full raw blob of one post.
	 */
	public static function handle_managed_schema( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-managed-schema.php';
		return self::wrap( CC_Assistant_Managed_Schema::report( (int) $request['post_id'] ) );
	}

	public static function handle_seo_playbook() {
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-playbook.php';
		return self::wrap( CC_Assistant_SEO_Playbook::full() );
	}

	/**
	 * v0.50: GET /popups — Elementor popup templates with display conditions
	 * + derived coverage. Optional covers_post_id adds per-popup
	 * covers_this_post (true/false/null) for that target page. Read-only.
	 */
	public static function handle_list_popups( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-popups.php';
		return self::wrap( CC_Assistant_Popups::list_popups( (int) $req->get_param( 'covers_post_id' ) ) );
	}

	/**
	 * v0.51.6: GET /theme-templates — every Theme Builder template CPT row
	 * (any status, including trash) with the meta that decides whether it
	 * actually renders: _elementor_template_type, the elementor_library_type
	 * taxonomy term, _elementor_conditions, and whether it holds a tree.
	 * Also returns the raw Elementor Pro conditions cache so a template
	 * present in meta but absent from the cache is immediately visible.
	 */
	public static function handle_list_theme_templates( WP_REST_Request $req ) {
		$rows = array();
		foreach ( self::template_post_types() as $cpt ) {
			if ( ! post_type_exists( $cpt ) ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $cpt,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
					'posts_per_page' => 200,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			foreach ( $posts as $p ) {
				$terms = wp_get_object_terms( $p->ID, 'elementor_library_type', array( 'fields' => 'slugs' ) );
				$row   = array(
					'id'                => $p->ID,
					'title'             => $p->post_title,
					'post_type'         => $p->post_type,
					'status'            => $p->post_status,
					'template_type'     => (string) get_post_meta( $p->ID, '_elementor_template_type', true ),
					'taxonomy_types'    => is_wp_error( $terms ) ? array() : $terms,
					'conditions'        => get_post_meta( $p->ID, '_elementor_conditions', true ),
					'has_elementor_data' => (bool) get_post_meta( $p->ID, '_elementor_data', true ),
					'edit_mode'         => (string) get_post_meta( $p->ID, '_elementor_edit_mode', true ),
					'modified'          => $p->post_modified,
					'edit_url'          => get_edit_post_link( $p->ID, 'raw' ),
				);
				// v0.53.1 — Divi Theme Builder enrichment. et_template rows
				// carry the assignment conditions + links to their layout-area
				// posts; the layout posts carry the actual [et_pb_*] shortcode
				// content (editable with the Divi module tools once template
				// editing is enabled).
				if ( 0 === strpos( $p->post_type, 'et_' ) ) {
					$row['divi'] = array(
						'use_on'           => get_post_meta( $p->ID, '_et_use_on', true ),
						'exclude_from'     => get_post_meta( $p->ID, '_et_exclude_from', true ),
						'is_default'       => (bool) get_post_meta( $p->ID, '_et_default', true ),
						'enabled'          => get_post_meta( $p->ID, '_et_enabled', true ),
						'header_layout_id' => (int) get_post_meta( $p->ID, '_et_header_layout_id', true ),
						'body_layout_id'   => (int) get_post_meta( $p->ID, '_et_body_layout_id', true ),
						'footer_layout_id' => (int) get_post_meta( $p->ID, '_et_footer_layout_id', true ),
						'has_divi_data'    => ( false !== strpos( (string) $p->post_content, '[et_pb_section' ) ),
					);
				}
				$rows[] = $row;
			}
		}
		return self::wrap(
			array(
				'templates'        => $rows,
				'count'            => count( $rows ),
				'conditions_cache' => get_option( 'elementor_pro_theme_builder_conditions', null ),
				'note'             => 'A published template renders only when it appears in conditions_cache. Missing there but present in conditions meta => run refresh_theme_builder_conditions.',
			)
		);
	}

	/**
	 * v0.51.6: POST /theme-templates/refresh-conditions — regenerate the
	 * Elementor Pro Theme Builder conditions cache. Content-neutral
	 * maintenance (rebuilds Pro's index of already-published templates), so
	 * it applies immediately without a pending row.
	 */
	public static function handle_refresh_theme_conditions( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-apply.php';

		// v0.51.7 optional repair: re-stamp a template whose identity was
		// reset by Elementor's own save hooks (type back to "page", conditions
		// deleted). Requires the template-editing opt-in since it changes
		// which template serves a site-wide location.
		$repair_id = (int) $req->get_param( 'repair_post_id' );
		$repaired  = null;
		if ( $repair_id > 0 ) {
			if ( ! get_option( 'cc_assistant_allow_template_editing', false ) ) {
				return new WP_Error( 'template_editing_disabled', 'Repair requires the "Allow editing Theme Builder templates" setting.', array( 'status' => 403 ) );
			}
			$post = get_post( $repair_id );
			if ( ! $post || ! self::is_template_post_type( $post->post_type ) ) {
				return new WP_Error( 'not_a_template', 'repair_post_id is not a Theme Builder template post.', array( 'status' => 400 ) );
			}
			$template_type = sanitize_key( (string) $req->get_param( 'template_type' ) );
			$known         = array( 'error-404', 'header', 'footer', 'single', 'single-page', 'single-post', 'archive', 'search-results', 'section', 'popup' );
			if ( ! in_array( $template_type, $known, true ) ) {
				return new WP_Error( 'template_type_unknown', 'Repair needs template_type, one of: ' . implode( ', ', $known ), array( 'status' => 400 ) );
			}
			$conditions_raw = $req->get_param( 'display_conditions' );
			CC_Assistant_Apply::stamp_template_identity( $repair_id, $template_type, is_array( $conditions_raw ) ? $conditions_raw : array() );
			$repaired = array(
				'id'            => $repair_id,
				'template_type' => (string) get_post_meta( $repair_id, '_elementor_template_type', true ),
				'conditions'    => get_post_meta( $repair_id, '_elementor_conditions', true ),
			);
		}

		$ok = CC_Assistant_Apply::regenerate_theme_builder_conditions();
		if ( ! $ok ) {
			return new WP_Error( 'regenerate_unavailable', 'Elementor Pro Theme Builder conditions cache could not be regenerated (Pro inactive, or its cache API changed).', array( 'status' => 500 ) );
		}
		return self::wrap(
			array(
				'regenerated'      => true,
				'repaired'         => $repaired,
				'conditions_cache' => get_option( 'elementor_pro_theme_builder_conditions', null ),
			)
		);
	}

	/**
	 * v0.29: GET /posts/{id}/elementor-export
	 * Returns the raw _elementor_data + Kit globals so a sister site can
	 * import this page verbatim.
	 */
	public static function handle_elementor_export( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';
		$post_id = (int) $request['id'];
		$g = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $g ) ) { return $g; }
		$result  = CC_Assistant_Elementor_IO::export( $post_id );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				404
			);
		}
		return self::wrap( $result );
	}

	/**
	 * v0.29: POST /posts/{id}/elementor-import
	 * Body: { raw_data, replacements, regenerate_ids, strip_images,
	 *         map_kit_globals, source_kit_globals, snapshot_first,
	 *         reasoning, dry_run }
	 * Queues a single pending change of type elementor_full_import.
	 * Approving it writes the cloned Elementor data to the target post
	 * (with a pre_elementor_full_import snapshot for rollback).
	 */
	public static function handle_elementor_import( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';
		$post_id    = (int) $request['id'];
		// A full-tree replace is the most destructive Elementor write — gate it
		// on the same allowlist + template opt-in as the surgical edits, so it
		// can never bypass the "template editing off" switch.
		$gate = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $gate ) ) { return $gate; }
		$params     = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = (array) $request->get_params();
		}
		$raw_data   = isset( $params['raw_data'] ) ? (string) $params['raw_data'] : '';
		$options    = $params;
		unset( $options['raw_data'] );

		$result = CC_Assistant_Elementor_IO::import_to_pending( $post_id, $raw_data, $options );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				400
			);
		}
		$result = is_array( $result ) ? $result : array( 'result' => $result );
		if ( self::is_template_post_type( get_post_type( $post_id ) ) ) {
			$result['site_wide_impact'] = 'This replaces the tree of an Elementor Theme Builder template. On approval it changes EVERY page that uses this template, not just one page.';
		}
		return self::wrap( $result );
	}

	/**
	 * v0.30: GET /posts/{id}/audit-design
	 */
	public static function handle_audit_design( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-page-audit.php';
		$post_id = (int) $request['id'];
		$g = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $g ) ) { return $g; }
		$result  = CC_Assistant_Page_Audit::audit( $post_id );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
				404
			);
		}
		return self::wrap( $result );
	}

	/**
	 * v0.30: GET /posts/{id}/image-placeholders
	 */
	public static function handle_image_placeholders( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';
		$post_id = (int) $request['id'];
		$g = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $g ) ) { return $g; }
		$result  = CC_Assistant_Elementor_IO::list_image_placeholders( $post_id );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
				404
			);
		}
		return self::wrap( $result );
	}

	/**
	 * v0.31: GET /posts/{id}/sections
	 * Returns one summary row per root-level Elementor container.
	 */
	public static function handle_list_sections( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-sections.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';
		$post_id = (int) $request['id'];
		$g = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $g ) ) { return $g; }
		$result  = CC_Assistant_Sections::list_sections( $post_id );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
				404
			);
		}
		return self::wrap( $result );
	}

	/**
	 * v0.31: POST /posts/{id}/sections/replace-content
	 * Body: { section_id, widget_updates: [{widget_id, settings}], reasoning }
	 */
	public static function handle_replace_section_content( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-sections.php';
		$post_id = (int) $request['id'];
		// Section-replace is a write path — honor the allowlist + template opt-in
		// like the other Elementor edits, not just the surgical widget update.
		$gate = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $gate ) ) { return $gate; }
		$params  = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = (array) $request->get_params();
		}
		$section_id     = isset( $params['section_id'] ) ? (string) $params['section_id'] : '';
		$widget_updates = isset( $params['widget_updates'] ) && is_array( $params['widget_updates'] ) ? $params['widget_updates'] : array();
		$reasoning      = isset( $params['reasoning'] ) ? (string) $params['reasoning'] : '';
		$override_lint  = isset( $params['override_lint'] ) ? filter_var( $params['override_lint'], FILTER_VALIDATE_BOOLEAN ) : false;
		$result = CC_Assistant_Sections::queue_replace_section_content( $post_id, $section_id, $widget_updates, $reasoning, $override_lint );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ),
				400
			);
		}
		$result = is_array( $result ) ? $result : array( 'result' => $result );
		if ( self::is_template_post_type( get_post_type( $post_id ) ) ) {
			$result['site_wide_impact'] = 'This edits an Elementor Theme Builder template. On approval it changes EVERY page that uses this template, not just one page.';
		}
		return self::wrap( $result );
	}

	/**
	 * GET /posts/{id}/quality-gate — preview the v0.39 page-quality gate for a
	 * post (blocking defects + warnings + stats) WITHOUT publishing. Lets the
	 * assistant self-check before queueing publish_draft, and powers page
	 * refresh / optimization against the 2026 winning bar.
	 */
	public static function handle_quality_gate( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$post_id = (int) $request['id'];
		if ( ! get_post( $post_id ) ) {
			return new WP_REST_Response( array( 'code' => 'post_not_found', 'message' => 'Post not found.' ), 404 );
		}
		return self::wrap( CC_Assistant_Pre_Publish::evaluate_publish_gate( $post_id ) );
	}

	/**
	 * v0.41: POST /posts/{id}/win-audit — score the page against the verified
	 * 2026 winning criteria AND (optionally) 2-5 fetched competitor pages.
	 * Body: { query?, competitor_urls?[], days? }. Read-only.
	 */
	public static function handle_win_audit( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-win-audit.php';
		$params            = $req->get_json_params();
		$params            = is_array( $params ) ? $params : array();
		$params['post_id'] = (int) $req['id'];
		$result            = CC_Assistant_Win_Audit::audit( $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	/**
	 * v0.43: GET /posts/{id}/robustness-audit — three-layer page standard.
	 * Query: { strict? }. Read-only.
	 */
	public static function handle_page_robustness_audit( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-page-robustness.php';
		$strict = $req->get_param( 'strict' );
		$strict = ( null === $strict ) ? true : filter_var( $strict, FILTER_VALIDATE_BOOLEAN );
		$result = CC_Assistant_Page_Robustness::audit( (int) $req['id'], $strict );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_health() {
		return self::wrap(
			array(
				'status'           => 'ok',
				'pending_count'    => CC_Assistant_Pending_Changes::count_pending(),
				'snapshots_count'  => CC_Assistant_Snapshots::count_snapshots(),
				'elementor_active' => CC_Assistant_Site_Identity::is_elementor_active(),
			)
		);
	}

	public static function handle_list_posts( WP_REST_Request $req ) {
		$allowed   = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$requested = (string) $req->get_param( 'post_type' );

		// Default ('' or 'any') covers BOTH posts and pages. The previous
		// 'page'-only default made list_posts(search="skeletal traction")
		// return nothing even though the blog post existed — searches only
		// ever matched pages. A specific type still filters as before.
		if ( '' === $requested || 'any' === $requested ) {
			$post_type = array_values( array_intersect( array( 'post', 'page' ), $allowed ) );
			if ( empty( $post_type ) ) {
				$post_type = $allowed;
			}
		} else {
			if ( ! in_array( $requested, $allowed, true ) ) {
				return new WP_Error(
					'post_type_not_allowed',
					sprintf( 'Post type "%s" is not in the allowlist. Allowed: %s (or "any" for posts + pages)', $requested, implode( ', ', $allowed ) ),
					array( 'status' => 400 )
				);
			}
			$post_type = $requested;
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $req->get_param( 'status' ),
			'posts_per_page' => (int) $req->get_param( 'per_page' ),
			'paged'          => (int) $req->get_param( 'page' ),
		);

		$search = $req->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );

		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
		$posts = array();
		foreach ( $query->posts as $post ) {
			$row = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'slug'         => $post->post_name,
				'status'       => $post->post_status,
				'type'         => $post->post_type,
				'modified'     => $post->post_modified,
				'author'       => get_the_author_meta( 'display_name', $post->post_author ),
				'has_elementor' => (bool) get_post_meta( $post->ID, '_elementor_edit_mode', true ),
				'edit_url'     => get_edit_post_link( $post->ID, 'raw' ),
				'url'          => get_permalink( $post->ID ),
				'word_count'   => str_word_count( wp_strip_all_tags( $post->post_content ) ),
			);
			$lang = CC_Assistant_Multilingual::language_of( $post->ID );
			if ( null !== $lang ) {
				$row['lang']  = $lang;
				$translations = CC_Assistant_Multilingual::translations_of( $post->ID );
				if ( ! empty( $translations ) ) {
					$row['translations'] = (object) $translations;
				}
			}
			$posts[] = $row;
		}

		return self::wrap(
			array(
				'posts' => $posts,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
			)
		);
	}

	public static function handle_get_post( WP_REST_Request $req ) {
		$id      = (int) $req->get_param( 'id' );
		$post    = get_post( $id );
		$allowed = self::editable_post_types();

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			return new WP_Error(
				'post_type_not_allowed',
				'Post type is not in the allowlist.',
				array( 'status' => 403 )
			);
		}

		$slim      = filter_var( $req->get_param( 'slim' ), FILTER_VALIDATE_BOOLEAN );
		$widget_id = trim( (string) $req->get_param( 'widget_id' ) );

		// Widget-only mode: return just the requested widget's settings (and its
		// own _elementor wrapper attributes) so callers do not have to download
		// 70KB of JSON to edit a single text-editor.
		if ( '' !== $widget_id ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';
			$elementor_raw = get_post_meta( $id, '_elementor_data', true );
			if ( empty( $elementor_raw ) ) {
				return new WP_Error( 'no_elementor_data', 'Post has no Elementor data.', array( 'status' => 404 ) );
			}
			$data = json_decode( $elementor_raw, true );
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'invalid_elementor_data', 'Could not parse _elementor_data JSON.', array( 'status' => 500 ) );
			}
			$widget = self::find_widget_by_id( $data, $widget_id );
			if ( null === $widget ) {
				return new WP_Error( 'widget_not_found', 'Widget id not found in this post.', array( 'status' => 404 ) );
			}
			return self::wrap(
				array(
					'id'        => $post->ID,
					'title'     => $post->post_title,
					'widget_id' => $widget_id,
					'widget'    => $widget,
				)
			);
		}

		$elementor_raw = get_post_meta( $id, '_elementor_data', true );

		// Auto-slim guard (v0.37). A full get_post on a large Elementor page
		// returns 80-120KB+ and overflows the caller's token budget (it gets
		// truncated to a file, defeating the read). When the caller did NOT
		// explicitly ask for slim but the heavy fields (body + tree) exceed the
		// threshold, fall back to slim automatically and tell the caller how to
		// fetch what they actually need. Small posts are unaffected; an explicit
		// slim=false is still honored (the caller knowingly takes the big payload).
		// Detect whether the caller EXPLICITLY passed slim, independent of the
		// route's registered default. get_param('slim') returns that default
		// (false), never null, when slim is omitted — that collision made the
		// auto-slim guard below unreachable (P0). Inspect the raw query/body.
		$slim_explicit = array_key_exists( 'slim', (array) $req->get_query_params() )
			|| array_key_exists( 'slim', (array) $req->get_json_params() );
		$heavy_bytes   = strlen( (string) $post->post_content ) + strlen( (string) $elementor_raw );
		$auto_slim_max = (int) apply_filters( 'cc_assistant_get_post_auto_slim_bytes', 60000 );
		$auto_slimmed  = false;
		if ( ! $slim && ! $slim_explicit && $heavy_bytes > $auto_slim_max ) {
			$slim         = true;
			$auto_slimmed = true;
		}

		$elementor = null;
		if ( ! empty( $elementor_raw ) && ! $slim ) {
			$decoded   = json_decode( $elementor_raw, true );
			$elementor = $decoded ?: null;
		}

		$response = array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'status'         => $post->post_status,
			'type'           => $post->post_type,
			'content'        => $slim ? null : $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'modified'       => $post->post_modified,
			'created'        => $post->post_date,
			'author'         => get_the_author_meta( 'display_name', $post->post_author ),
			'url'            => get_permalink( $post->ID ),
			'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
			'has_elementor'  => (bool) get_post_meta( $id, '_elementor_edit_mode', true ),
			'elementor_data' => $elementor,
			'word_count'     => str_word_count( wp_strip_all_tags( $post->post_content ) ),
		);

		// Divi-built post: steer the caller to the module toolchain instead of
		// hand-editing the raw shortcode body (v0.51.0).
		if ( false !== strpos( (string) $post->post_content, '[et_pb_section' ) ) {
			$response['divi_hint'] = 'This post is Divi-built. Do NOT hand-transcribe or regenerate the shortcode body. Use list_divi_modules(post_id) for structure, get_divi_module for one module, and draft_update_divi_modules for surgical edits.';
		}

		if ( $slim ) {
			// Slim drops the heaviest fields. Caller can re-fetch with slim=false
			// when they actually need the body or the parsed Elementor tree.
			unset( $response['content'], $response['elementor_data'] );
			$response['content_length'] = strlen( (string) $post->post_content );
			$response['elementor_size'] = strlen( (string) $elementor_raw );
			if ( $auto_slimmed ) {
				$response['auto_slimmed'] = true;
				$response['notice']       = sprintf(
					'Body + Elementor tree are ~%d KB, too large to return whole, so this response was auto-slimmed. To get the parts you need: pass widget_id= for one widget, use list_sections / get_elementor_widgets for structure, or pass slim=false to force the full payload. (Threshold filterable via cc_assistant_get_post_auto_slim_bytes.)',
					(int) round( $heavy_bytes / 1024 )
				);
			}
		}

		return self::wrap( $response );
	}

	/**
	 * Recursively locate a widget by id in an Elementor tree. Returns the
	 * full widget node (id, settings, widgetType, etc.) or null.
	 */
	private static function find_widget_by_id( $elements, $widget_id ) {
		foreach ( (array) $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( isset( $el['id'] ) && $el['id'] === $widget_id ) {
				return $el;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = self::find_widget_by_id( $el['elements'], $widget_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	public static function handle_elementor_widgets( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';

		$id      = (int) $req->get_param( 'id' );
		$format  = $req->get_param( 'format' );
		$post    = get_post( $id );

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		if ( ! in_array( $post->post_type, self::editable_post_types(), true ) ) {
			return new WP_Error( 'post_type_not_allowed', 'Post type is not in the allowlist. (Elementor templates require the "Allow editing Theme Builder templates" setting.)', array( 'status' => 403 ) );
		}

		$parsed = CC_Assistant_Elementor_Parser::parse( $id );
		if ( null === $parsed ) {
			return self::wrap(
				array(
					'post_id'       => $id,
					'post_title'    => $post->post_title,
					'has_elementor' => false,
				)
			);
		}

		if ( 'full' !== $format ) {
			// Summary: keep what is useful for analysis, drop the verbose widget tree and the full text dump.
			$internal = 0;
			$external = 0;
			foreach ( $parsed['all_links'] as $link ) {
				if ( ! empty( $link['is_internal'] ) ) {
					$internal++;
				} else {
					$external++;
				}
			}
			$parsed = array(
				'post_id'       => $parsed['post_id'],
				'post_title'    => $parsed['post_title'],
				'post_status'   => $parsed['post_status'],
				'permalink'     => $parsed['permalink'],
				'has_elementor' => true,
				'word_count'    => $parsed['word_count'],
				'structure'     => $parsed['structure'],
				'headings'      => $parsed['headings'],
				'links'         => array(
					'total'    => count( $parsed['all_links'] ),
					'internal' => $internal,
					'external' => $external,
					'list'     => $parsed['all_links'],
				),
			);
		}

		return self::wrap( $parsed );
	}

	/**
	 * v0.34: GET /posts/{id}/page-map
	 * Returns the annotated, section-aware map and stamps the post's "map
	 * consulted" transient so subsequent layout-altering tools accept it.
	 */
	/**
	 * v0.52: entity_lookup — find a name/slug across ALL post types at once
	 * (product, page, post, Theme Builder templates, any public CPT). Built
	 * so a duplicate never gets missed: before building a page for something,
	 * one call reveals it already exists as e.g. a WooCommerce product. Read-only.
	 */
	public static function handle_entity_lookup( WP_REST_Request $req ) {
		global $wpdb;

		$q = trim( (string) $req->get_param( 'q' ) );
		if ( '' === $q ) {
			return new WP_Error( 'missing_query', 'q (name or slug to search) is required.', array( 'status' => 400 ) );
		}

		// Search every registered public post type, plus product + Theme
		// Builder templates explicitly (elementor_library is not "public").
		$types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( array( 'product', 'page', 'post', 'elementor_library' ) as $t ) {
			if ( post_type_exists( $t ) ) {
				$types[ $t ] = $t;
			}
		}
		unset( $types['attachment'] );
		$types = array_values( array_unique( $types ) );
		if ( empty( $types ) ) {
			return self::wrap( array( 'query' => $q, 'count' => 0, 'results' => array() ) );
		}

		$slug       = sanitize_title( $q );
		$like       = '%' . $wpdb->esc_like( $q ) . '%';
		$slug_like  = '%' . $wpdb->esc_like( $slug ) . '%';
		$ph_types   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql        = $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_type, post_status
			 FROM {$wpdb->posts}
			 WHERE post_type IN ($ph_types)
			   AND post_status NOT IN ('trash','auto-draft','inherit')
			   AND ( post_title LIKE %s OR post_name LIKE %s )
			 ORDER BY ( post_name = %s ) DESC, post_type ASC, post_title ASC
			 LIMIT 60",
			array_merge( $types, array( $like, $slug_like, $slug ) )
		);
		$rows = $wpdb->get_results( $sql );

		$results = array();
		foreach ( (array) $rows as $row ) {
			$id             = (int) $row->ID;
			$has_elementor  = '' !== (string) get_post_meta( $id, '_elementor_data', true );
			$item = array(
				'id'                => $id,
				'title'             => $row->post_title,
				'slug'              => $row->post_name,
				'post_type'         => $row->post_type,
				'status'            => $row->post_status,
				'url'               => get_permalink( $id ),
				'edit_url'          => get_edit_post_link( $id, 'raw' ),
				'has_elementor'     => $has_elementor,
				'exact_slug_match'  => ( $row->post_name === $slug ),
			);
			if ( 'elementor_library' === $row->post_type ) {
				$item['template_type'] = get_post_meta( $id, '_elementor_template_type', true );
			}
			$results[] = $item;
		}

		return self::wrap(
			array(
				'query'          => $q,
				'derived_slug'   => $slug,
				'searched_types' => $types,
				'count'          => count( $results ),
				'results'        => $results,
				'note'           => 'Includes products and Theme Builder templates. exact_slug_match=true means an entity already owns this URL slug — do not build a duplicate; edit that one.',
			)
		);
	}

	/**
	 * v0.52: full normalized layout spec — every node + every setting.
	 * Read-only; accepts any post that has Elementor data (page or template).
	 */
	public static function handle_layout_spec( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-map.php';

		$id   = (int) $req->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		$spec = CC_Assistant_Elementor_Map::build_layout_spec( $id );
		if ( null === $spec ) {
			return self::wrap(
				array(
					'post_id'       => $id,
					'post_title'    => $post->post_title,
					'has_elementor' => false,
					'message'       => 'Post has no Elementor data.',
				)
			);
		}
		$spec['post_title'] = $post->post_title;
		$spec['post_type']  = $post->post_type;
		return self::wrap( $spec );
	}

	/**
	 * v0.52: deep diff of a target post against a reference. Returns every
	 * structural + per-setting delta. The provable "does my build match"
	 * check. Read-only; either side may be a page or Theme Builder template.
	 */
	public static function handle_layout_compare( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-map.php';

		$id  = (int) $req->get_param( 'id' );
		$ref = (int) $req->get_param( 'reference_id' );
		if ( $ref <= 0 ) {
			return new WP_Error( 'missing_reference', 'reference_id is required.', array( 'status' => 400 ) );
		}
		$post  = get_post( $id );
		$rpost = get_post( $ref );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Target post %d not found.', $id ), array( 'status' => 404 ) );
		}
		if ( ! $rpost ) {
			return new WP_Error( 'reference_not_found', sprintf( 'Reference post %d not found.', $ref ), array( 'status' => 404 ) );
		}
		$result                     = CC_Assistant_Elementor_Map::compare_specs( $id, $ref );
		$result['target_title']     = $post->post_title;
		$result['reference_title']  = $rpost->post_title;
		return self::wrap( $result );
	}

	public static function handle_page_map( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-map.php';

		$id      = (int) $req->get_param( 'id' );
		$post    = get_post( $id );
		$allowed = self::editable_post_types();

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			return new WP_Error( 'post_type_not_allowed', 'Post type is not in the allowlist.', array( 'status' => 403 ) );
		}

		$map = CC_Assistant_Elementor_Map::build_page_map( $id );
		if ( null === $map ) {
			return self::wrap(
				array(
					'post_id'       => $id,
					'post_title'    => $post->post_title,
					'has_elementor' => false,
					'message'       => 'Post has no Elementor data — page-map gate does not apply.',
				)
			);
		}

		// Stamp the cache so layout-altering tools know the map was consulted.
		CC_Assistant_Elementor_Map::mark_map_consulted( $id );

		$include_ascii = filter_var( $req->get_param( 'include_ascii' ), FILTER_VALIDATE_BOOLEAN );
		if ( $include_ascii ) {
			$map['ascii_tree'] = CC_Assistant_Elementor_Map::render_ascii_tree( $map );
		}

		$map['has_elementor']    = true;
		$map['map_ttl_seconds']  = CC_Assistant_Elementor_Map::CACHE_TTL;
		$map['gate_hint']        = 'Layout-altering tools (draft_add_elementor_container, root-level draft_add_elementor_widget) refuse with 422 if this map is not consulted within ' . CC_Assistant_Elementor_Map::CACHE_TTL . 's. Use safe_insert_positions; avoid risky_insert_positions.';

		return self::wrap( $map );
	}

	public static function handle_find_clusters( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';

		$post_type = $req->get_param( 'post_type' );
		$threshold = max( 0.0, min( 1.0, (float) $req->get_param( 'threshold' ) ) );
		$limit     = max( 2, min( 1000, (int) $req->get_param( 'limit' ) ) );
		$allowed   = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );

		if ( ! in_array( $post_type, $allowed, true ) ) {
			return new WP_Error( 'post_type_not_allowed', 'Post type is not in the allowlist.', array( 'status' => 400 ) );
		}

		// Increase limits for vector compute on large sites.
		@set_time_limit( 300 );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$result = CC_Assistant_Similarity::find_clusters( $query->posts, $threshold );
		$result['post_type'] = $post_type;
		$result['scanned']   = count( $query->posts );

		// Summary mode by default: drop the per-cluster pairs array (quadratic noise).
		$format = $req->get_param( 'format' );
		if ( 'full' !== $format && ! empty( $result['clusters'] ) ) {
			foreach ( $result['clusters'] as &$cluster ) {
				if ( isset( $cluster['pairs'] ) ) {
					$max = 0;
					foreach ( $cluster['pairs'] as $p ) {
						if ( $p['similarity'] > $max ) {
							$max = $p['similarity'];
						}
					}
					$cluster['top_similarity'] = round( $max, 4 );
					unset( $cluster['pairs'] );
				}
			}
			unset( $cluster );
		}

		return self::wrap( $result );
	}

	public static function handle_analyze_cluster( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';

		$post_ids_raw = $req->get_param( 'post_ids' );
		$post_ids     = array();
		if ( is_array( $post_ids_raw ) ) {
			$post_ids = array_map( 'intval', $post_ids_raw );
		} else {
			foreach ( explode( ',', (string) $post_ids_raw ) as $id ) {
				$id = (int) trim( $id );
				if ( $id > 0 ) {
					$post_ids[] = $id;
				}
			}
		}

		if ( count( $post_ids ) < 2 ) {
			return new WP_Error( 'invalid_post_ids', 'Provide at least two post_ids.', array( 'status' => 400 ) );
		}

		$excerpt_chars = max( 100, min( 5000, (int) $req->get_param( 'excerpt_chars' ) ) );
		$payload       = CC_Assistant_Similarity::get_cluster_payload( $post_ids, $excerpt_chars );
		return self::wrap(
			array(
				'post_ids' => $post_ids,
				'count'    => count( $payload ),
				'pages'    => $payload,
			)
		);
	}

	public static function handle_list_pending() {
		$items = CC_Assistant_Pending_Changes::list_pending( 'pending', 100 );
		return self::wrap(
			array(
				'pending' => $items,
				'count'   => count( $items ),
			)
		);
	}

	/**
	 * Post types the read/edit tools may touch: the operator allowlist, plus
	 * Elementor Theme Builder templates (headers, footers, single-post, archive
	 * — all the elementor_library post type) ONLY when the operator has opted in
	 * via cc_assistant_allow_template_editing. Templates are a deliberate switch
	 * because one edit changes every page that uses them. Trashing is NOT routed
	 * through here — templates stay un-deletable via the tool.
	 */
	/**
	 * CPTs that hold Theme Builder templates (headers / footers / single-post
	 * layouts) across the builders these sites use: Elementor Pro
	 * (elementor_library) AND ElementsKit (elementskit_template /
	 * elementskit_content). Both store their tree in _elementor_data, so the
	 * same read/edit/import tools work on all of them. Filterable so a site on
	 * yet another builder can extend the list without a code change.
	 */
	public static function template_post_types() {
		$types = array(
			'elementor_library',
			'elementskit_template',
			'elementskit_content',
			// v0.53.1 — Divi Theme Builder. The template row plus its three
			// layout-area CPTs (the shortcode content lives on the layout
			// posts). Same opt-in gate as Elementor templates: the
			// "Allow editing Theme Builder templates" setting.
			'et_template',
			'et_header_layout',
			'et_body_layout',
			'et_footer_layout',
		);
		return (array) apply_filters( 'cc_assistant_template_post_types', $types );
	}

	public static function editable_post_types() {
		$types = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		if ( get_option( 'cc_assistant_allow_template_editing', false ) ) {
			$types = array_merge( $types, self::template_post_types() );
		}
		return array_values( array_unique( $types ) );
	}

	/** True when a post type is a Theme Builder template (Elementor or ElementsKit). */
	public static function is_template_post_type( $post_type ) {
		return in_array( $post_type, self::template_post_types(), true );
	}

	private static function check_post_for_draft( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		if ( ! in_array( $post->post_type, self::editable_post_types(), true ) ) {
			return new WP_Error( 'post_type_not_allowed', 'Post type not in allowlist. (Elementor templates require the "Allow editing Theme Builder templates" setting.)', array( 'status' => 403 ) );
		}
		return $post;
	}

	public static function handle_draft_post_meta( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$field = $req->get_param( 'field' );
		$value = $req->get_param( 'value' );
		if ( ! in_array( $field, CC_Assistant_Apply::ALLOWED_POST_FIELDS, true ) ) {
			return new WP_Error( 'field_not_allowed', 'Field must be one of: ' . implode( ', ', CC_Assistant_Apply::ALLOWED_POST_FIELDS ), array( 'status' => 400 ) );
		}

		// post_author accepts a user ID, login, email, or display name and is
		// resolved to a numeric ID HERE so the reviewer approves a concrete
		// user, not a lookup that could drift between queue and apply.
		if ( 'post_author' === $field ) {
			$user = self::resolve_user_reference( $value );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
			$value = (string) $user->ID;
		}

		$current     = $post->{$field};
		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? self::sanitize_success_metrics( $success_raw )
			: null;

		$proposed_payload = wp_json_encode( array( 'field' => $field, 'value' => $value ) );
		$gate             = self::pre_queue_gate( $req, $post_id, 'meta_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'meta_update',
				'field'       => $field,
				'warnings'    => $gate['warnings'],
			) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => $post_id,
				'change_type'     => 'meta_update',
				'change_summary'  => $req->get_param( 'summary' ) ?: sprintf( 'Update %s', $field ),
				'current_value'   => wp_json_encode( array( 'field' => $field, 'value' => $current ) ),
				'proposed_value'  => $proposed_payload,
				'reasoning'       => (string) $req->get_param( 'reasoning' ),
				'success_metrics' => $success,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * Resolve a user reference (numeric ID, login, email, nicename, or exact
	 * display name) to a WP_User. Errors on no match or an ambiguous
	 * display-name match rather than guessing.
	 */
	private static function resolve_user_reference( $ref ) {
		$ref = trim( (string) $ref );
		if ( '' === $ref ) {
			return new WP_Error( 'user_required', 'post_author requires a user ID, login, email, or display name.', array( 'status' => 400 ) );
		}
		if ( ctype_digit( $ref ) ) {
			$user = get_user_by( 'id', (int) $ref );
			return $user ? $user : new WP_Error( 'user_not_found', sprintf( 'No user with ID %s.', $ref ), array( 'status' => 404 ) );
		}
		foreach ( array( 'login', 'email', 'slug' ) as $by ) {
			$user = get_user_by( $by, $ref );
			if ( $user ) {
				return $user;
			}
		}
		$matches = get_users(
			array(
				'search'         => $ref,
				'search_columns' => array( 'display_name' ),
				'number'         => 2,
			)
		);
		if ( 1 === count( $matches ) ) {
			return $matches[0];
		}
		if ( count( $matches ) > 1 ) {
			return new WP_Error( 'user_ambiguous', sprintf( '"%s" matches more than one user — pass the numeric user ID instead.', $ref ), array( 'status' => 400 ) );
		}
		return new WP_Error( 'user_not_found', sprintf( 'No user found for "%s" (tried ID, login, email, nicename, display name).', $ref ), array( 'status' => 404 ) );
	}

	public static function handle_draft_postmeta( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$key     = sanitize_key( $req->get_param( 'meta_key' ) );
        if ( '_cc_assistant_content_workflow' === $key ) { return new WP_Error( 'workflow_binding_protected', 'Workflow bindings are server-owned and cannot be edited through post metadata tools.', array( 'status' => 403 ) ); }
		$value   = $req->get_param( 'value' );
		if ( empty( $key ) ) {
			return new WP_Error( 'meta_key_required', 'meta_key is required.', array( 'status' => 400 ) );
		}

		// Attachment carve-out: featured-image alt text lives on the attachment
		// post (not the parent post that uses it), and `_wp_attachment_image_alt`
		// is the only canonical meta key for it. Allow attachment posts through
		// the postmeta endpoint when the key is exactly this — narrow exception
		// so we don't open the door to arbitrary attachment writes. Apply also
		// honours this carve-out on the apply path.
		$post_obj    = get_post( $post_id );
		$attach_alt  = ( $post_obj && 'attachment' === $post_obj->post_type && '_wp_attachment_image_alt' === $key );

		if ( ! $attach_alt ) {
			$post = self::check_post_for_draft( $post_id );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
		} else {
			// Attachment exists; just verify the post object is real.
			if ( ! $post_obj ) {
				return new WP_Error( 'post_not_found', 'Attachment not found.', array( 'status' => 404 ) );
			}
		}

		// SEO meta keys belong to draft_update_seo_meta which auto-routes
		// to the active SEO plugin. Block raw postmeta writes against those
		// keys so the model can't accidentally write a Yoast key on a Rank
		// Math site (or vice versa). Always-allow override for power users
		// who really mean to write the raw key.
		$is_seo_key = preg_match( '/^_yoast_wpseo_|^rank_math_|^_aioseo_|^_seopress_/', $key );
		if ( $is_seo_key && ! filter_var( $req->get_param( 'force_raw' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return new WP_Error(
				'use_seo_meta_route',
				sprintf( 'Meta key "%s" is an SEO-plugin key. Use draft_update_seo_meta with a logical_key (e.g. "description", "title") so the plugin routes to the correct SEO plugin. Pass force_raw=true if you really need the raw key.', $key ),
				array( 'status' => 400 )
			);
		}

		// Plugin-managed schema keys must hold valid JSON (or an empty value,
		// which means "remove the emitter"). Older versions queued whatever
		// arrived, and six posts on a production install ended up storing
		// ~10KB of unparseable JSON-LD each — the wp_head emitter silently
		// skipped them, so the garbage was invisible to every rendered-output
		// audit and only managed_schema could see it. Refuse it at the door.
		if ( in_array( $key, array( '_cc_assistant_schema_jsonld', '_cc_emergency_service_schema' ), true )
			&& is_string( $value ) && '' !== trim( (string) $value ) ) {
			$schema_decoded = json_decode( trim( (string) $value ), true );
			if ( ! is_array( $schema_decoded ) ) {
				return new WP_Error(
					'invalid_schema_json',
					sprintf( 'Meta key "%s" stores JSON-LD and the proposed value is not valid JSON (%s). Fix the payload, or pass an empty value to remove the schema.', $key, json_last_error_msg() ),
					array( 'status' => 422 )
				);
			}
		}

		$current     = get_post_meta( $post_id, $key, true );
		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? self::sanitize_success_metrics( $success_raw )
			: null;

		$proposed_payload = wp_json_encode( array( 'key' => $key, 'value' => $value ) );
		$gate             = self::pre_queue_gate( $req, $post_id, 'postmeta_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'postmeta_update',
				'meta_key'    => $key,
				'warnings'    => $gate['warnings'],
			) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => $post_id,
				'change_type'     => 'postmeta_update',
				'change_summary'  => $req->get_param( 'summary' ) ?: sprintf( 'Update meta %s', $key ),
				'current_value'   => wp_json_encode( array( 'key' => $key, 'value' => $current ) ),
				'proposed_value'  => $proposed_payload,
				'reasoning'       => (string) $req->get_param( 'reasoning' ),
				'success_metrics' => $success,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		$out = array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $gate['warnings'],
		);
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
		return self::wrap( $out );
	}

	/**
	 * Queue a trash_post pending change: retire a page/post through the approval
	 * inbox instead of the operator hand-deleting it in wp-admin. Reversible
	 * (WP Trash). Plugin-scoped to allowlisted post types.
	 */
	public static function handle_draft_trash_post( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'post_id not found.', array( 'status' => 404 ) );
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'already_trashed', 'Post is already in the Trash.', array( 'status' => 400 ) );
		}
		$allowed = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		if ( ! in_array( $post->post_type, (array) $allowed, true ) ) {
			return new WP_Error( 'post_type_not_allowed', sprintf( 'Post type "%s" is not in the allowlist; refusing to trash.', $post->post_type ), array( 'status' => 403 ) );
		}

		$title   = get_the_title( $post_id );
		$summary = $req->get_param( 'summary' );
		if ( empty( $summary ) ) {
			$summary = sprintf( 'Trash %s "%s" (ID %d)', $post->post_type, $title, $post_id );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => $post_id,
				'change_type'    => 'trash_post',
				'change_summary' => $summary,
				'current_value'  => wp_json_encode( array( 'post_status' => $post->post_status, 'title' => $title, 'url' => get_permalink( $post_id ) ) ),
				'proposed_value' => wp_json_encode( array( 'action' => 'trash', 'post_id' => $post_id ) ),
				'reasoning'      => (string) $req->get_param( 'reasoning' ),
				'status'         => 'pending',
				'created_by'     => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) {
			return $pending_id;
		}

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'post_id'    => $post_id,
			'title'      => $title,
		) );
	}

	public static function handle_draft_elementor_widget( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$widget_id = (string) $req->get_param( 'widget_id' );
		$settings  = $req->get_param( 'settings' );
		if ( empty( $widget_id ) || ! is_array( $settings ) ) {
			return new WP_Error( 'invalid_payload', 'widget_id (string) and settings (object) are required.', array( 'status' => 400 ) );
		}

		// v0.50: link.popup_id is first-class input — replace it server-side
		// with the exact Elementor popup-open action URL BEFORE lint/queueing,
		// so the AI never hand-builds the base64 blob.
		require_once CC_ASSISTANT_DIR . 'includes/class-popups.php';
		$settings = CC_Assistant_Popups::resolve_popup_links( $settings );

		// Capture current widget settings for diff display.
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';
		$current_settings = self::find_widget_settings( $post_id, $widget_id );
		if ( null === $current_settings ) {
			return new WP_Error( 'widget_not_found', 'Widget id not found in this post.', array( 'status' => 404 ) );
		}

		// Style-guide / banned-phrase lint on the widget's editor or text fields.
		// Hard-fail on em dashes and other configured violations unless the caller
		// explicitly passes override_lint=true. Same pattern as post_content edits.
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$lint_text = '';
		foreach ( array( 'editor', 'text', 'title', 'description_text_a', 'description_text_b' ) as $field ) {
			if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) ) {
				$lint_text .= "\n" . $settings[ $field ];
			}
		}
		$lint = null;
		if ( '' !== trim( $lint_text ) && method_exists( 'CC_Assistant_Pre_Publish', 'lint_html_block' ) ) {
			// v0.35: pass settings hash so address_consistency + palette_compliance
			// run on this widget's non-text fields (link.url, hex color fields).
			// v0.62: pass the widget's CURRENT text so pre-existing long
			// paragraphs downgrade to debt instead of blocking the edit
			// (the documented paragraph_length trap).
			$current_lint_text = '';
			if ( is_array( $current_settings ) ) {
				foreach ( array( 'editor', 'title', 'text', 'description_text' ) as $cf ) {
					if ( isset( $current_settings[ $cf ] ) && is_string( $current_settings[ $cf ] ) ) {
						$current_lint_text .= "\n" . $current_settings[ $cf ];
					}
				}
			}
			$block_lint  = CC_Assistant_Pre_Publish::lint_html_block( $lint_text, is_array( $settings ) ? $settings : null, false, $current_lint_text );
			// Compute hard_violations the same way lint_post_content_change does:
			// em_dashes, ai_tells, style_guide, wall_of_text, address_consistency,
			// hospital_comparison failures are blockers; the rest are soft warnings
			// the reviewer sees on the inbox card.
			$hard = array();
			$hard_names = array( 'em_dashes', 'ai_tells', 'style_guide', 'wall_of_text', 'address_consistency', 'hospital_comparison', 'quote_source_link' );
			if ( ! empty( $block_lint['checks'] ) && is_array( $block_lint['checks'] ) ) {
				foreach ( $hard_names as $name ) {
					if ( isset( $block_lint['checks'][ $name ] ) && empty( $block_lint['checks'][ $name ]['pass'] ) ) {
						$hard[] = $name;
					}
				}
			} elseif ( isset( $block_lint['em_dashes']['pass'] ) ) {
				// lint_html_block returns checks at top level — handle either shape.
				foreach ( $hard_names as $name ) {
					if ( isset( $block_lint[ $name ] ) && empty( $block_lint[ $name ]['pass'] ) ) {
						$hard[] = $name;
					}
				}
			}
			$lint = array_merge( (array) $block_lint, array( 'hard_violations' => $hard ) );
			$override = filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN );
			if ( ! empty( $hard ) && ! $override ) {
				return new WP_Error(
					'lint_hard_violation',
					sprintf(
						'Proposed widget content fails: %s. Fix and resubmit, or pass override_lint=true to queue anyway.',
						implode( ', ', $hard )
					),
					array(
						'status' => 422,
						'lint'   => $lint,
					)
				);
			}
		}

		// Conflict detection: if another pending PC already targets this same
		// post_id + widget_id, surface it so the caller can decide to merge or
		// supersede instead of silently stacking edits whose apply order matters.
		$conflicts = self::find_widget_conflicts( $post_id, $widget_id );

		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? self::sanitize_success_metrics( $success_raw )
			: null;

		$proposed_payload = wp_json_encode( array( 'widget_id' => $widget_id, 'settings' => $settings ) );
		$gate             = self::pre_queue_gate( $req, $post_id, 'elementor_widget_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }

		// v0.50 popup condition guard (warn-only): if the settings carry a
		// popup-open action URL whose display conditions do NOT cover this
		// post, the button will silently no-op on the live page. Never blocks.
		$gate['warnings'] = array_merge(
			$gate['warnings'],
			CC_Assistant_Popups::coverage_warnings_for_payload( $proposed_payload, $post_id )
		);

		// v0.83 widget-schema guard (blocking): an unknown setting key or an
		// invalid enum value is a SILENT no-op in Elementor — surface it with
		// a did-you-mean before the human reviews the change.
		require_once CC_ASSISTANT_DIR . 'includes/class-widget-schema.php';
		$gate['warnings'] = array_merge(
			$gate['warnings'],
			CC_Assistant_Widget_Schema::validation_warnings_for_post_widget( $post_id, $widget_id, $settings )
		);
		$schema_error = CC_Assistant_Widget_Schema::blocking_error( $gate['warnings'] );
		if ( is_wp_error( $schema_error ) ) { return $schema_error; }

		$conflict_payload = null;
		if ( ! empty( $conflicts ) ) {
			$conflict_payload = array(
				'count'   => count( $conflicts ),
				'message' => sprintf(
					'%d earlier pending change(s) already target widget %s on post %d. Approve in queued order or reject the older ones first.',
					count( $conflicts ),
					$widget_id,
					$post_id
				),
				'pending' => $conflicts,
			);
		}

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'elementor_widget_update',
				'widget_id'   => $widget_id,
				'lint'        => $lint,
				'conflicts'   => $conflict_payload,
				'warnings'    => $gate['warnings'],
			) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => $post_id,
				'change_type'     => 'elementor_widget_update',
				'change_summary'  => $req->get_param( 'summary' ) ?: sprintf( 'Update Elementor widget %s', $widget_id ),
				'current_value'   => wp_json_encode( array( 'widget_id' => $widget_id, 'settings' => $current_settings ) ),
				'proposed_value'  => $proposed_payload,
				'reasoning'       => (string) $req->get_param( 'reasoning' ),
				'lint_report'     => $lint,
				'success_metrics' => $success,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		$out = array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $gate['warnings'],
		);
		if ( $conflict_payload ) {
			$out['conflicts'] = $conflict_payload;
		}
		if ( self::is_template_post_type( $post->post_type ) ) {
			$out['site_wide_impact'] = 'This edits an Elementor Theme Builder template. On approval it changes EVERY page that uses this template, not just one page.';
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
		return self::wrap( $out );
	}

	/**
	 * Queue a new Elementor widget insertion into a container.
	 * POST /draft/elementor-widget-add
	 * Body: { post_id, parent_id, widget_type, settings, position?, summary?, reasoning?, dry_run? }
	 */
	public static function handle_draft_elementor_widget_add( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$parent_id   = (string) $req->get_param( 'parent_id' );
		$widget_type = (string) $req->get_param( 'widget_type' );
		$settings    = $req->get_param( 'settings' );
		$position    = $req->get_param( 'position' );

		if ( '' === $parent_id || '' === $widget_type || ! is_array( $settings ) ) {
			return new WP_Error( 'invalid_payload', 'parent_id (string), widget_type (string), and settings (object) are required.', array( 'status' => 400 ) );
		}

		// v0.50: link.popup_id is first-class input — replace it server-side
		// with the exact Elementor popup-open action URL BEFORE lint/queueing.
		require_once CC_ASSISTANT_DIR . 'includes/class-popups.php';
		$settings = CC_Assistant_Popups::resolve_popup_links( $settings );

		// Verify the parent exists and is a container BEFORE queueing so the
		// reviewer doesn't approve a change that's going to fail on apply.
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$located = CC_Assistant_Elementor_Builder::find_node( $tree, $parent_id );
		if ( null === $located ) {
			return new WP_Error( 'parent_not_found', sprintf( 'Parent container id %s not found on this post.', $parent_id ), array( 'status' => 404 ) );
		}
		if ( ! CC_Assistant_Elementor_Builder::is_container_node( $located['node'] ) ) {
			return new WP_Error( 'parent_not_container', sprintf( 'Node %s is not a container — widgets can only be added inside section/container/column nodes.', $parent_id ), array( 'status' => 422 ) );
		}

		// Lint the text-bearing settings the same way widget_update does, so we
		// catch em dashes / AI-tells before they land in the inbox.
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$lint = self::lint_widget_settings_payload( $settings, $req );
		if ( is_wp_error( $lint ) ) {
			return $lint;
		}

		// 0.15: schema-entity dedup for HTML widgets added via this endpoint.
		// Edit 344 in the v0.14 era went through draft_add_elementor_widget
		// (not container_add), so the schema guard has to live on BOTH paths.
		// Wraps the single proposed widget in a one-element children list and
		// reuses the same scan+collide helpers as container_add.
		$override_schema_dup_w = filter_var( $req->get_param( 'override_schema_dup' ), FILTER_VALIDATE_BOOLEAN );
		if ( 'html' === $widget_type && ! $override_schema_dup_w ) {
			$proposed_children = array( array( 'type' => 'widget', 'widgetType' => 'html', 'settings' => $settings ) );
			$proposed_schemas  = self::extract_schema_entities_from_children( $proposed_children );
			if ( ! empty( $proposed_schemas ) ) {
				$live_schemas      = self::collect_live_schema_entities( $post_id );
				$schema_conflicts  = self::find_schema_collisions( $proposed_schemas, $live_schemas );
				if ( ! empty( $schema_conflicts ) ) {
					$first = $schema_conflicts[0];
					return new WP_Error(
						'schema_already_on_page',
						sprintf(
							'Proposed HTML widget would emit a JSON-LD entity (%s, @id=%s) that the page ALREADY renders. Duplicate @id on the same URL is a Google Rich Results spec violation. Edit the existing entity via draft_update_elementor_widget, OR resubmit with override_schema_dup=true if a deliberate duplicate is intended. Found %d collision%s.',
							$first['type'],
							$first['id'] !== '' ? $first['id'] : '(empty)',
							count( $schema_conflicts ),
							1 === count( $schema_conflicts ) ? '' : 's'
						),
						array( 'status' => 422, 'schema_conflicts' => $schema_conflicts )
					);
				}
			}
			// HTML widget content-similarity check on the widget_add path.
			if ( ! empty( $settings['html'] ) && mb_strlen( (string) $settings['html'] ) >= 300 ) {
				$existing_html_bodies = self::collect_live_html_widget_bodies( $post_id );
				$candidate = (string) $settings['html'];
				foreach ( $existing_html_bodies as $existing ) {
					similar_text( $candidate, $existing['html'], $pct );
					if ( $pct >= 80 ) {
						return new WP_Error(
							'html_widget_duplicate',
							sprintf(
								'Proposed HTML widget content is %d%% similar to an existing HTML widget (#%s) on this page. Edit the existing widget instead, OR resubmit with override_dup=true if a deliberate duplicate is intended.',
								(int) round( $pct ),
								$existing['id']
							),
							array( 'status' => 422, 'existing_widget_id' => $existing['id'] )
						);
					}
				}
			}
		}

		$proposed = array(
			'parent_id'   => $parent_id,
			'widget_type' => $widget_type,
			'settings'    => $settings,
		);
		if ( null !== $position && '' !== $position ) {
			$proposed['position'] = (int) $position;
		}

		// Accessibility check: catches the white-on-white / light-on-light
		// pattern at queue time. Inherits the parent container's background if
		// it has one (so an icon-box on a dark section won't get false-flagged).
		$parent_bg = null;
		if ( ! empty( $located['node']['settings']['background_color'] ) && is_string( $located['node']['settings']['background_color'] ) && '#' === substr( $located['node']['settings']['background_color'], 0, 1 ) ) {
			$parent_bg = strtolower( $located['node']['settings']['background_color'] );
		}
		$a11y_warnings = self::check_widget_accessibility( $widget_type, $settings, $parent_bg );

		// Hard block on a11y failure unless the operator has explicitly seen the
		// warning and accepted it. The v0.11.0 invisible-text regression got
		// shipped because the warning was advisory only — flipping it to a
		// 422 forces a corrective queue or an explicit override per call.
		$override_a11y = filter_var( $req->get_param( 'override_a11y' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $a11y_warnings ) && ! $override_a11y ) {
			return new WP_Error(
				'accessibility_contrast',
				sprintf(
					'Proposed %s widget would render with low text contrast: %s. Fix the colors (set explicit hex like "#003017" / "#202020") OR resubmit with override_a11y=true if the section background is intentionally handled elsewhere.',
					$widget_type,
					implode( ' | ', $a11y_warnings )
				),
				array( 'status' => 422, 'warnings' => $a11y_warnings )
			);
		}

		$gate = self::pre_queue_gate( $req, $post_id, 'elementor_widget_add', wp_json_encode( $proposed ) );
		if ( is_wp_error( $gate ) ) { return $gate; }

		$all_warnings = $gate['warnings'];
		// v0.50 popup condition guard (warn-only): a popup-open link whose
		// display conditions do not cover this post silently no-ops live.
		$all_warnings = array_merge(
			$all_warnings,
			CC_Assistant_Popups::coverage_warnings_for_payload( wp_json_encode( $proposed ), $post_id )
		);
		// v0.80 effective-value guard: unknown keys, invalid enums, gated-off
		// (inert) settings and governing defaults for a NEW widget.
		require_once CC_ASSISTANT_DIR . 'includes/class-widget-schema.php';
		$all_warnings = array_merge(
			$all_warnings,
			CC_Assistant_Widget_Schema::validation_warnings_for_new_element( $widget_type, $settings )
		);
		$schema_error = CC_Assistant_Widget_Schema::blocking_error( $all_warnings );
		if ( is_wp_error( $schema_error ) ) { return $schema_error; }
		foreach ( $a11y_warnings as $msg ) {
			$all_warnings[] = array(
				'code'    => 'accessibility_contrast',
				'message' => $msg,
			);
		}

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'elementor_widget_add',
				'parent_id'   => $parent_id,
				'widget_type' => $widget_type,
				'lint'        => $lint,
				'warnings'    => $all_warnings,
			) );
		}

		$summary = (string) $req->get_param( 'summary' );
		if ( '' === $summary ) {
			$summary = sprintf( 'Add %s widget into container %s', $widget_type, $parent_id );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => 'elementor_widget_add',
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( array( 'parent_id' => $parent_id, 'parent_widget_count' => isset( $located['node']['elements'] ) ? count( $located['node']['elements'] ) : 0 ) ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'lint_report'    => $lint,
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $all_warnings,
		) );
	}

	/**
	 * Queue widget removal.
	 * POST /draft/elementor-widget-remove
	 * Body: { post_id, widget_id, summary?, reasoning?, dry_run? }
	 */
	public static function handle_draft_elementor_widget_remove( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$widget_id = (string) $req->get_param( 'widget_id' );
		if ( '' === $widget_id ) {
			return new WP_Error( 'invalid_payload', 'widget_id is required.', array( 'status' => 400 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$located = CC_Assistant_Elementor_Builder::find_node( $tree, $widget_id );
		if ( null === $located ) {
			return new WP_Error( 'widget_not_found', sprintf( 'Widget id %s not found on this post.', $widget_id ), array( 'status' => 404 ) );
		}
		$preview = CC_Assistant_Elementor_Builder::summarize_node( $located['node'] );

		$proposed = array( 'widget_id' => $widget_id );
		$gate     = self::pre_queue_gate( $req, $post_id, 'elementor_widget_remove', wp_json_encode( $proposed ) );
		if ( is_wp_error( $gate ) ) { return $gate; }
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type'    => 'elementor_widget_remove',
				'widget_id'      => $widget_id,
				'preview'        => $preview,
				'warnings'       => $gate['warnings'],
			) );
		}

		$summary = (string) $req->get_param( 'summary' );
		if ( '' === $summary ) {
			$summary = sprintf( 'Remove %s widget %s', $preview['type'], $widget_id );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => 'elementor_widget_remove',
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( $preview ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * Queue addition of a container with optional pre-built children.
	 * POST /draft/elementor-container-add
	 * Body: { post_id, parent_id (string, '' for root), el_type?, settings, position?, children?, summary?, reasoning? }
	 */
	public static function handle_draft_elementor_container_add( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$parent_id = $req->get_param( 'parent_id' );
		if ( null === $parent_id ) {
			return new WP_Error( 'invalid_payload', 'parent_id is required (pass empty string "" to insert at root).', array( 'status' => 400 ) );
		}
		$parent_id = (string) $parent_id;
		$settings  = $req->get_param( 'settings' );
		if ( null === $settings ) {
			$settings = array();
		}
		if ( ! is_array( $settings ) ) {
			return new WP_Error( 'invalid_payload', 'settings must be an object (may be empty).', array( 'status' => 400 ) );
		}
		$children = $req->get_param( 'children' );
		if ( null === $children ) {
			$children = array();
		}
		if ( ! is_array( $children ) ) {
			return new WP_Error( 'invalid_payload', 'children must be an array.', array( 'status' => 400 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$spec_check = CC_Assistant_Elementor_Builder::validate_child_specs( $children );
		if ( is_wp_error( $spec_check ) ) {
			return $spec_check;
		}
		$el_type = (string) ( $req->get_param( 'el_type' ) ?: 'container' );
		if ( ! in_array( $el_type, array( 'section', 'container', 'column' ), true ) ) {
			return new WP_Error( 'invalid_payload', 'el_type must be section, container, or column.', array( 'status' => 400 ) );
		}

		// Validate the parent if non-empty before queueing.
		if ( '' !== $parent_id ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
			$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
			if ( is_wp_error( $tree ) ) {
				return $tree;
			}
			$located = CC_Assistant_Elementor_Builder::find_node( $tree, $parent_id );
			if ( null === $located ) {
				return new WP_Error( 'parent_not_found', sprintf( 'Parent id %s not found.', $parent_id ), array( 'status' => 404 ) );
			}
		}

		// Recursively lint text in the children spec — adding a container with
		// 4 text-editor cards full of em-dashes would otherwise bypass the
		// style-guide gate that draft_update_elementor_widget enforces.
		//
		// v0.35: switched from concatenated-blob lint to per-widget evaluation
		// so wall_of_text doesn't false-positive on a 4-card grid (200+ words
		// total but only 50 words per card). em_dashes / ai_tells / style_guide
		// / placeholders / address / hospital still aggregate.
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$per_widget = self::collect_per_widget_lint_payloads( $children );
		$child_lint = null;
		if ( ! empty( $per_widget ) ) {
			$child_lint = self::lint_per_widget_payloads( $per_widget, $req );
			if ( is_wp_error( $child_lint ) ) {
				return $child_lint;
			}
		}

		// Accessibility lint: walk the proposed subtree and check for low-contrast
		// foreground/background combinations. Added in v0.11.1 after the v0.11.0
		// Delivery Methods + Results Timeline regression where icon-boxes copied
		// from a dark-background sample rendered white-on-white on a default
		// section background. We surface warnings rather than block — the
		// operator may have a dark global section background we can't see in
		// the spec — but the message tells them exactly what to fix.
		$bg_override = null;
		if ( ! empty( $settings['background_color'] ) && is_string( $settings['background_color'] ) && '#' === substr( $settings['background_color'], 0, 1 ) ) {
			$bg_override = strtolower( $settings['background_color'] );
		}
		$a11y_warnings = self::check_accessibility_in_children( $children, $bg_override );

		// Hard block on a11y failure (same rationale as widget_add) — flip
		// override_a11y=true if the dark section bg is handled outside the
		// proposed subtree.
		$override_a11y = filter_var( $req->get_param( 'override_a11y' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $a11y_warnings ) && ! $override_a11y ) {
			return new WP_Error(
				'accessibility_contrast',
				sprintf(
					'Proposed container would render with low-contrast text in %d widget(s): %s. Pin explicit hex colors (e.g. title_color "#003017", description_color "#202020") on the icon-box / heading / text-editor children, OR resubmit with override_a11y=true.',
					count( $a11y_warnings ),
					implode( ' | ', $a11y_warnings )
				),
				array( 'status' => 422, 'warnings' => $a11y_warnings )
			);
		}

		// Section-width hard lint (v0.28.1). Refuses root-level container_add
		// unless the outer container has boxed_width 1100-1300px AND every
		// heading widget in the children subtree has _element_custom_width
		// 700-900px. Mirrors the wall_of_text pattern: a memory rule the
		// model repeatedly violated (sections bleeding edge-to-edge on
		// desktop, headings spanning the full section width) is now enforced
		// server-side so even a fresh AI session with no memory cannot ship
		// it. Only applies at root level — inner containers can be any
		// width. Override via override_section_width=true (rare; usually
		// means the section is intentionally edge-to-edge like a hero).
		$override_sw = filter_var( $req->get_param( 'override_section_width' ), FILTER_VALIDATE_BOOLEAN );
		$sw_issues   = self::check_section_width_constraints( $parent_id, $settings, $children );
		if ( ! empty( $sw_issues ) && ! $override_sw ) {
			return new WP_Error(
				'section_width_violation',
				sprintf(
					'Proposed section fails the global width rule. %s Sample an existing well-built root container on the page first (get_post widget_id=<id>) to copy the exact boxed_width the site uses, then resubmit. Override via override_section_width=true ONLY if the section is intentionally edge-to-edge (e.g. a hero).',
					implode( ' ', $sw_issues )
				),
				array( 'status' => 422, 'violations' => $sw_issues )
			);
		}

		// Heading-title consistency check. If the proposed children spec
		// contains a heading widget whose title already appears as an H2 / H3
		// somewhere on this page, the operator is probably re-queueing a
		// section that already exists (the v0.12.0 hormone-page regression
		// shipped 3 copies of "Compare Hormone Delivery Methods" because
		// successive supersede attempts all got approved). Refuse unless
		// override_dup=true. Title comparison is case-insensitive and
		// whitespace-normalized so trivial rewording doesn't slip through.
		$override_dup_heading = filter_var( $req->get_param( 'override_dup' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! $override_dup_heading ) {
			$proposed_titles = self::collect_heading_titles_from_children( $children );
			if ( ! empty( $proposed_titles ) ) {
				$existing_titles = self::existing_heading_titles( $post_id );
				$conflicts       = array();
				foreach ( $proposed_titles as $candidate ) {
					$norm = self::normalize_heading_title( $candidate );
					if ( '' === $norm ) {
						continue;
					}
					if ( isset( $existing_titles[ $norm ] ) ) {
						$conflicts[] = array(
							'proposed' => $candidate,
							'existing' => $existing_titles[ $norm ],
						);
					}
				}
				if ( ! empty( $conflicts ) ) {
					$first = $conflicts[0];
					return new WP_Error(
						'heading_already_on_page',
						sprintf(
							'Proposed container has a heading "%s" that already exists on this page as "%s" (%d conflict%s). Edit the existing section via draft_update_elementor_widget instead of adding another copy, OR resubmit with override_dup=true if a deliberate duplicate is intended.',
							$first['proposed'],
							$first['existing'],
							count( $conflicts ),
							1 === count( $conflicts ) ? '' : 's'
						),
						array( 'status' => 422, 'conflicts' => $conflicts )
					);
				}
			}
		}

		// 0.15: schema-entity dedup. Walks JSON-LD scripts inside any HTML
		// widget descendant of the proposed children, then cross-references
		// against the live page's existing JSON-LD. If the proposed payload
		// would render a second `@type` + `@id` already present on the page,
		// refuse. Override: `override_schema_dup=true`.
		$override_schema_dup = filter_var( $req->get_param( 'override_schema_dup' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! $override_schema_dup ) {
			$proposed_schemas = self::extract_schema_entities_from_children( $children );
			if ( ! empty( $proposed_schemas ) ) {
				$live_schemas = self::collect_live_schema_entities( $post_id );
				$schema_conflicts = self::find_schema_collisions( $proposed_schemas, $live_schemas );
				if ( ! empty( $schema_conflicts ) ) {
					$first = $schema_conflicts[0];
					return new WP_Error(
						'schema_already_on_page',
						sprintf(
							'Proposed container would emit a JSON-LD entity (%s, @id=%s) that the page ALREADY renders. Duplicate @id on the same URL is a Google Rich Results spec violation. Edit the existing entity via draft_update_elementor_widget, OR resubmit with override_schema_dup=true if a deliberate duplicate is intended. Found %d collision%s.',
							$first['type'],
							$first['id'] !== '' ? $first['id'] : '(empty)',
							count( $schema_conflicts ),
							1 === count( $schema_conflicts ) ? '' : 's'
						),
						array( 'status' => 422, 'schema_conflicts' => $schema_conflicts )
					);
				}
			}
		}

		// 0.15: HTML-widget content-similarity check. Catches the case where
		// the proposed children include an HTML widget whose body is >80%
		// similar_text to an existing HTML widget on the same page — the
		// "same comparison table re-added in a new container" pattern.
		// Override: `override_dup=true` (same flag as heading dedup since
		// the operator intent is the same).
		if ( ! $override_dup_heading ) {
			$proposed_html_bodies = self::extract_html_bodies_from_children( $children );
			if ( ! empty( $proposed_html_bodies ) ) {
				$existing_html_bodies = self::collect_live_html_widget_bodies( $post_id );
				$html_similarity_conflicts = array();
				foreach ( $proposed_html_bodies as $candidate ) {
					if ( mb_strlen( $candidate ) < 300 ) {
						continue;
					}
					foreach ( $existing_html_bodies as $existing ) {
						similar_text( $candidate, $existing['html'], $pct );
						if ( $pct >= 80 ) {
							$html_similarity_conflicts[] = array(
								'existing_widget_id' => $existing['id'],
								'similarity'         => (int) round( $pct ),
								'proposed_preview'   => mb_substr( wp_strip_all_tags( $candidate ), 0, 120 ),
							);
							break;
						}
					}
				}
				if ( ! empty( $html_similarity_conflicts ) ) {
					$first = $html_similarity_conflicts[0];
					return new WP_Error(
						'html_widget_duplicate',
						sprintf(
							'Proposed HTML widget content is %d%% similar to an existing HTML widget (#%s) on this page. Edit the existing widget instead, OR resubmit with override_dup=true if a deliberate duplicate is intended.',
							$first['similarity'],
							$first['existing_widget_id']
						),
						array( 'status' => 422, 'html_conflicts' => $html_similarity_conflicts )
					);
				}
			}
		}

		// v0.34: Map-first gate + position validator. For ROOT-level
		// container_add ONLY (parent_id === ''). Skipped entirely when the
		// post has 0 root containers (empty draft — nothing to split).
		$override_map_check  = filter_var( $req->get_param( 'override_map_check' ), FILTER_VALIDATE_BOOLEAN );
		$override_continuity = filter_var( $req->get_param( 'override_section_continuity' ), FILTER_VALIDATE_BOOLEAN );

		if ( '' === $parent_id ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-map.php';
			$existing_root_count = CC_Assistant_Elementor_Map::root_container_count( $post_id );

			// Empty-page bypass: a post with no existing root containers has
			// nothing to split, so neither the map-first gate nor the
			// position validator can fire usefully.
			if ( $existing_root_count > 0 ) {

				// --- Map-first gate ---
				if ( ! $override_map_check ) {
					list( $consult_status, $consult_age ) = CC_Assistant_Elementor_Map::map_consult_status( $post_id );
					if ( 'fresh' !== $consult_status ) {
						$reason = ( 'stale' === $consult_status )
							? sprintf( 'the cached map for post %d is STALE — the post was modified after get_page_map was called %ds ago. Re-call get_page_map(id=%d) to refresh and resubmit.', $post_id, $consult_age, $post_id )
							: sprintf( 'get_page_map(id=%d) must be called before any root-level container_add on this post. The map exposes section pairs (H2-alone container + its body container) so this insert does not split one. Call get_page_map, review safe_insert_positions, then resubmit.', $post_id );
						return new WP_Error(
							'map_not_consulted',
							$reason . ' Override: override_map_check=true ONLY if the page has no logical sections to split (rare).',
							array(
								'status'             => 422,
								'gate'               => 'map_first',
								'consult_status'     => $consult_status,
								'cache_ttl_seconds'  => CC_Assistant_Elementor_Map::CACHE_TTL,
								'existing_root_count' => $existing_root_count,
							)
						);
					}
				}

				// --- Position validator ---
				if ( ! $override_continuity ) {
					$req_position_check = $req->get_param( 'position' );
					if ( null !== $req_position_check && '' !== $req_position_check ) {
						$map_for_check = CC_Assistant_Elementor_Map::build_page_map( $post_id );
						if ( is_array( $map_for_check ) ) {
							$check = CC_Assistant_Elementor_Map::validate_insert_position( $map_for_check, (int) $req_position_check );
							if ( ! $check['is_safe'] ) {
								return new WP_Error(
									'section_continuity_violation',
									sprintf(
										'Insert at position %d %s. Pick a safe position from: %s. Override via override_section_continuity=true if you really mean to split the section.',
										(int) $req_position_check,
										$check['reason'],
										implode( ', ', $check['recommended_positions'] )
									),
									array(
										'status'                => 422,
										'gate'                  => 'section_continuity',
										'rejected_position'     => (int) $req_position_check,
										'recommended_positions' => $check['recommended_positions'],
									)
								);
							}
						}
					}
				}

				// Audit trail: if either override flag fires past this point,
				// stamp the activity log so a reviewer can later see who
				// bypassed which guard. Only stamps on root-level insert.
				if ( $override_map_check || $override_continuity ) {
					$flags = array();
					if ( $override_map_check ) {
						$flags[] = 'override_map_check';
					}
					if ( $override_continuity ) {
						$flags[] = 'override_section_continuity';
					}
					if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-activity-log.php' ) ) {
						require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
						if ( class_exists( 'CC_Assistant_Activity_Log' ) && method_exists( 'CC_Assistant_Activity_Log', 'record' ) ) {
							CC_Assistant_Activity_Log::record(
								'layout_gate_override',
								sprintf( 'root container_add bypassed guards: %s', implode( ', ', $flags ) ),
								$post_id,
								null,
								'ai'
							);
						}
					}
				}
			}
		}

		// Position guidance for root-level inserts. When parent_id is "" and
		// position is unset, the section lands at the bottom of the page —
		// almost never the intended reading-flow position. Suggest mid-body
		// indices based on the page's current root container count.
		$position_warnings = array();
		if ( '' === $parent_id ) {
			$req_position = $req->get_param( 'position' );
			$position_warnings = self::position_warnings_for_root_insert( $post_id, ( null !== $req_position && '' !== $req_position ) ? (int) $req_position : null );
		}

		// Hard block on missing position for a deep page (>=6 root containers).
		// On shallow pages, appending is fine — the FAQ-tail trap only exists
		// when there's enough page above for it to be a real reading-flow miss.
		$override_position = filter_var( $req->get_param( 'override_position' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $position_warnings ) && ! $override_position && '' === $parent_id ) {
			$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
			$root_count = 0;
			if ( ! empty( $raw ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$root_count = count( $decoded );
				}
			}
			if ( $root_count >= 6 ) {
				return new WP_Error(
					'position_required',
					sprintf(
						'Root-level container_add on a %d-section page needs an explicit position. Without one, the section lands at the end of the page (typically after the FAQ + reviews tail — almost never the intended reading flow). Pass an integer position OR override_position=true if appending is intentional.',
						$root_count
					),
					array( 'status' => 422, 'warnings' => $position_warnings, 'root_count' => $root_count )
				);
			}
		}

		$proposed = array(
			'parent_id' => $parent_id,
			'el_type'   => $el_type,
			'settings'  => $settings,
			'children'  => $children,
		);
		$position = $req->get_param( 'position' );
		if ( null !== $position && '' !== $position ) {
			$proposed['position'] = (int) $position;
		}

		$gate = self::pre_queue_gate( $req, $post_id, 'elementor_container_add', wp_json_encode( $proposed ) );
		if ( is_wp_error( $gate ) ) { return $gate; }

		// Merge accessibility + position guidance with the rate-limit/duplicate
		// warnings from pre_queue_gate. Reviewers see them all in one list on
		// the inbox card AND in the queue response so the model can self-correct.
		$all_warnings = $gate['warnings'];
		foreach ( $a11y_warnings as $msg ) {
			$all_warnings[] = array(
				'code'    => 'accessibility_contrast',
				'message' => $msg,
			);
		}
		foreach ( $position_warnings as $w ) {
			$all_warnings[] = $w;
		}
		// v0.80 effective-value guard for the NEW container itself (boxed_width
		// under content_width=full, overlay colour without its gate, etc.).
		require_once CC_ASSISTANT_DIR . 'includes/class-widget-schema.php';
		$all_warnings = array_merge(
			$all_warnings,
			CC_Assistant_Widget_Schema::validation_warnings_for_new_element( (string) $el_type, $settings )
		);
		$schema_error = CC_Assistant_Widget_Schema::blocking_error( $all_warnings );
		if ( is_wp_error( $schema_error ) ) { return $schema_error; }

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'elementor_container_add',
				'parent_id'   => $parent_id,
				'el_type'     => $el_type,
				'child_count' => count( $children ),
				'warnings'    => $all_warnings,
			) );
		}

		$summary = (string) $req->get_param( 'summary' );
		if ( '' === $summary ) {
			$summary = sprintf( 'Add %s%s with %d child element(s)', $el_type, '' === $parent_id ? ' at page root' : ' in ' . $parent_id, count( $children ) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => 'elementor_container_add',
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( array( 'parent_id' => $parent_id ) ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $all_warnings,
		) );
	}

	/**
	 * POST /draft/elementor-section-rebuild — atomic "build new section + remove
	 * old" as ONE reviewable, all-or-nothing pending. Replaces the add+remove
	 * pair (and the "approve both together / mind the position drift" dance)
	 * with a single operation. No heading-duplicate guard: replacing a section
	 * inherently re-uses its headings, which is the whole point.
	 * Body: { post_id, remove_id, settings, children?, position?, parent_id?,
	 *         el_type?, summary?, reasoning? }
	 */
	public static function handle_draft_rebuild_section( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$remove_id = (string) $req->get_param( 'remove_id' );
		$settings  = $req->get_param( 'settings' );
		if ( '' === $remove_id || ! is_array( $settings ) || empty( $settings ) ) {
			return new WP_Error( 'invalid_payload', 'remove_id and a non-empty settings object are required.', array( 'status' => 400 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		if ( null === CC_Assistant_Elementor_Builder::find_node( $tree, $remove_id ) ) {
			return new WP_Error( 'remove_id_not_found', sprintf( 'Section %s not found on this post — nothing to rebuild.', $remove_id ), array( 'status' => 404 ) );
		}

		$children   = $req->get_param( 'children' );
		$children   = is_array( $children ) ? $children : array();
		$spec_check = CC_Assistant_Elementor_Builder::validate_child_specs( $children );
		if ( is_wp_error( $spec_check ) ) {
			return $spec_check;
		}
		$parent_id  = $req->get_param( 'parent_id' );
		$el_type    = (string) $req->get_param( 'el_type' );
		$proposed   = array(
			'parent_id' => null === $parent_id ? '' : (string) $parent_id,
			'el_type'   => '' !== $el_type ? $el_type : 'container',
			'settings'  => $settings,
			'children'  => $children,
			'remove_id' => $remove_id,
		);
		$position = $req->get_param( 'position' );
		if ( null !== $position && '' !== $position ) {
			$proposed['position'] = (int) $position;
		}

		$gate = self::pre_queue_gate( $req, $post_id, 'elementor_section_rebuild', wp_json_encode( $proposed ) );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'elementor_section_rebuild',
				'remove_id'   => $remove_id,
				'child_count' => count( $children ),
				'warnings'    => $gate['warnings'],
			) );
		}

		$summary = (string) $req->get_param( 'summary' );
		if ( '' === $summary ) {
			$summary = sprintf( 'Rebuild section atomically: +new (%d children) / -%s', count( $children ), $remove_id );
		}
		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => 'elementor_section_rebuild',
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( array( 'remove_id' => $remove_id ) ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'note'       => 'Atomic: on approval the new section is added AND the old one removed in a single write — no transient duplicate, one approval, position handled internally.',
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * POST /draft/add-section — build a section from a semantic RECIPE. Expands
	 * {recipe, data} into the full brand-correct Elementor tree (eyebrow + 38px
	 * H2 + accent bar + content, brand tokens, grid rows auto, FA5 icons), then
	 * queues it through the proven apply path: container_add, or — when remove_id
	 * is supplied — the atomic section_rebuild.
	 * Body: { post_id, recipe, data, position?, remove_id?, summary?, reasoning? }
	 */
	public static function handle_draft_add_section( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$recipe = (string) $req->get_param( 'recipe' );
		$data   = $req->get_param( 'data' );
		$data   = is_array( $data ) ? $data : array();
		if ( '' === $recipe ) {
			return new WP_Error( 'invalid_payload', 'recipe is required.', array( 'status' => 400 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-section-recipes.php';
		$expanded = CC_Assistant_Section_Recipes::expand( $recipe, $data );
		if ( is_wp_error( $expanded ) ) {
			return $expanded;
		}

		$remove_id = (string) $req->get_param( 'remove_id' );
		$position  = $req->get_param( 'position' );
		$proposed  = array(
			'parent_id' => '',
			'el_type'   => 'container',
			'settings'  => $expanded['settings'],
			'children'  => $expanded['children'],
		);
		if ( null !== $position && '' !== $position ) {
			$proposed['position'] = (int) $position;
		}

		// Recipe-based REBUILD when remove_id is supplied -> atomic engine.
		if ( '' !== $remove_id ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
			$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
			if ( is_wp_error( $tree ) ) {
				return $tree;
			}
			if ( null === CC_Assistant_Elementor_Builder::find_node( $tree, $remove_id ) ) {
				return new WP_Error( 'remove_id_not_found', sprintf( 'Section %s not found on this post.', $remove_id ), array( 'status' => 404 ) );
			}
			$proposed['remove_id'] = $remove_id;
			$change_type           = 'elementor_section_rebuild';
		} else {
			$change_type = 'elementor_container_add';
		}

		$gate = self::pre_queue_gate( $req, $post_id, $change_type, wp_json_encode( $proposed ) );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => $change_type,
				'recipe'      => $recipe,
				'child_count' => count( $expanded['children'] ),
				'warnings'    => $gate['warnings'],
			) );
		}

		$summary = (string) $req->get_param( 'summary' );
		if ( '' === $summary ) {
			$summary = '' !== $remove_id
				? sprintf( 'Rebuild section from "%s" recipe (atomic)', $recipe )
				: sprintf( 'Add "%s" recipe section', $recipe );
		}
		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => $change_type,
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( '' !== $remove_id ? array( 'remove_id' => $remove_id ) : array( 'parent_id' => '' ) ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'recipe'     => $recipe,
			'mode'       => '' !== $remove_id ? 'rebuild (atomic)' : 'add',
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * Queue addition of an item inside a nested-accordion widget.
	 * POST /draft/elementor-accordion-item-add
	 * Body: { post_id, accordion_widget_id, title, content_html?, position?, summary?, reasoning? }
	 */
	public static function handle_draft_elementor_accordion_item_add( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$accordion_id = (string) $req->get_param( 'accordion_widget_id' );
		$title        = (string) $req->get_param( 'title' );
		$content_html = (string) $req->get_param( 'content_html' );
		if ( '' === $accordion_id || '' === $title ) {
			return new WP_Error( 'invalid_payload', 'accordion_widget_id and title are required.', array( 'status' => 400 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$located = CC_Assistant_Elementor_Builder::find_node( $tree, $accordion_id );
		if ( null === $located ) {
			return new WP_Error( 'accordion_not_found', sprintf( 'Accordion widget %s not found on this post.', $accordion_id ), array( 'status' => 404 ) );
		}
		$widget_type = isset( $located['node']['widgetType'] ) ? $located['node']['widgetType'] : '';
		if ( 'nested-accordion' !== $widget_type ) {
			return new WP_Error( 'not_accordion', sprintf( 'Widget %s is %s, not nested-accordion. Use draft_update_elementor_widget for legacy accordion widgets — its items live in settings.tabs.', $accordion_id, $widget_type ?: 'unknown' ), array( 'status' => 422 ) );
		}

		// Fuzzy duplicate guard. Cycle through existing item_titles and refuse
		// when the proposed title is the same question phrased slightly
		// differently (similar_text >= 80% or normalized-equal). v0.11.1 shipped
		// 7 duplicate FAQ entries on the hormone page because successive
		// add_accordion_item calls didn't compare against existing items —
		// titles like "Are bioidentical hormones safer than synthetic?" and
		// "Are bioidentical hormones safer than synthetic hormones?" each got
		// queued as if they were fresh questions.
		$existing_items = isset( $located['node']['settings']['items'] ) && is_array( $located['node']['settings']['items'] )
			? $located['node']['settings']['items']
			: array();
		$dup_match = self::find_fuzzy_duplicate_title( $title, $existing_items );
		$override_dup = filter_var( $req->get_param( 'override_dup' ), FILTER_VALIDATE_BOOLEAN );
		if ( $dup_match && ! $override_dup ) {
			return new WP_Error(
				'duplicate_accordion_item',
				sprintf(
					'Accordion %s already has a similar item: "%s" (similarity %d%%). Update the existing item via draft_update_elementor_widget if the answer needs improvement, OR pass override_dup=true to add this as a genuine variant.',
					$accordion_id,
					$dup_match['title'],
					$dup_match['similarity']
				),
				array( 'status' => 422, 'existing_item' => $dup_match )
			);
		}

		// Lint content body the same as widget_update.
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$lint = self::lint_widget_settings_payload( array( 'editor' => $content_html ), $req );
		if ( is_wp_error( $lint ) ) {
			return $lint;
		}

		$proposed = array(
			'accordion_widget_id' => $accordion_id,
			'title'               => $title,
			'content_html'        => $content_html,
		);
		$position = $req->get_param( 'position' );
		if ( null !== $position && '' !== $position ) {
			$proposed['position'] = (int) $position;
		}

		$gate = self::pre_queue_gate( $req, $post_id, 'elementor_accordion_item_add', wp_json_encode( $proposed ) );
		if ( is_wp_error( $gate ) ) { return $gate; }
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'elementor_accordion_item_add',
				'accordion_widget_id' => $accordion_id,
				'title'       => $title,
				'lint'        => $lint,
				'warnings'    => $gate['warnings'],
			) );
		}

		$summary = (string) $req->get_param( 'summary' );
		if ( '' === $summary ) {
			$summary = sprintf( 'Add FAQ item to accordion %s: %s', $accordion_id, mb_substr( $title, 0, 70 ) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => 'elementor_accordion_item_add',
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( array(
				'accordion_widget_id' => $accordion_id,
				'item_count_before'   => isset( $located['node']['settings']['items'] ) && is_array( $located['node']['settings']['items'] ) ? count( $located['node']['settings']['items'] ) : 0,
			) ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'lint_report'    => $lint,
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * POST /draft/elementor-accordion-item-remove
	 * Body: { post_id, accordion_widget_id, item_id, summary?, reasoning? }
	 *
	 * Mirrors handle_draft_elementor_accordion_item_add for the removal side.
	 * Needed because draft_update_elementor_widget would do an array_merge on
	 * settings.items[] (numeric keys append, string keys overwrite — the
	 * settings.items slot is a numeric-indexed list, so its semantics are
	 * brittle), and the elements[] sibling array isn't reachable at all
	 * through that path. So we expose a dedicated remove for accordion items.
	 */
	public static function handle_draft_elementor_accordion_item_remove( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$accordion_id = (string) $req->get_param( 'accordion_widget_id' );
		$item_id      = (string) $req->get_param( 'item_id' );
		if ( '' === $accordion_id || '' === $item_id ) {
			return new WP_Error( 'invalid_payload', 'accordion_widget_id and item_id are required.', array( 'status' => 400 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$tree = CC_Assistant_Elementor_Builder::load_tree( $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$located = CC_Assistant_Elementor_Builder::find_node( $tree, $accordion_id );
		if ( null === $located ) {
			return new WP_Error( 'accordion_not_found', sprintf( 'Accordion widget %s not found on this post.', $accordion_id ), array( 'status' => 404 ) );
		}
		$widget_type = isset( $located['node']['widgetType'] ) ? $located['node']['widgetType'] : '';
		if ( 'nested-accordion' !== $widget_type ) {
			return new WP_Error( 'not_accordion', sprintf( 'Widget %s is %s, not nested-accordion.', $accordion_id, $widget_type ?: 'unknown' ), array( 'status' => 422 ) );
		}

		// Validate the item exists and capture a preview so the reviewer sees
		// what they're deleting on the inbox card.
		$existing_items = isset( $located['node']['settings']['items'] ) && is_array( $located['node']['settings']['items'] ) ? $located['node']['settings']['items'] : array();
		$item_title  = null;
		$item_index  = -1;
		foreach ( $existing_items as $i => $it ) {
			if ( isset( $it['_id'] ) && $it['_id'] === $item_id ) {
				$item_title = isset( $it['item_title'] ) ? (string) $it['item_title'] : '';
				$item_index = $i;
				break;
			}
		}
		if ( $item_index < 0 ) {
			return new WP_Error( 'item_not_found', sprintf( 'No item with _id %s in accordion %s.', $item_id, $accordion_id ), array( 'status' => 404 ) );
		}

		$proposed = array(
			'accordion_widget_id' => $accordion_id,
			'item_id'             => $item_id,
		);
		$gate = self::pre_queue_gate( $req, $post_id, 'elementor_accordion_item_remove', wp_json_encode( $proposed ) );
		if ( is_wp_error( $gate ) ) { return $gate; }
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type'         => 'elementor_accordion_item_remove',
				'accordion_widget_id' => $accordion_id,
				'item_id'             => $item_id,
				'item_title'          => $item_title,
				'warnings'            => $gate['warnings'],
			) );
		}

		$summary = (string) $req->get_param( 'summary' );
		if ( '' === $summary ) {
			$summary = sprintf( 'Remove FAQ item from accordion %s: %s', $accordion_id, mb_substr( $item_title, 0, 70 ) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => 'elementor_accordion_item_remove',
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( array(
				'accordion_widget_id' => $accordion_id,
				'item_id'             => $item_id,
				'item_title'          => $item_title,
				'item_index'          => $item_index,
				'item_count_before'   => count( $existing_items ),
			) ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * Fuzzy duplicate detection on accordion item_title against existing items.
	 * Returns null when no near-match found, OR an array {title, similarity}
	 * when a near-match is detected. The threshold is intentionally permissive
	 * (>= 80% similarity OR normalized-equal after stripping punctuation and
	 * common filler words) so question rewrites like "How long until I feel
	 * results from bioidentical HRT?" vs "How long until I feel results from
	 * bioidentical hormone therapy?" get caught.
	 *
	 * @param string $candidate     Proposed item title.
	 * @param array  $existing_items settings.items list from an accordion.
	 * @return array|null
	 */
	private static function find_fuzzy_duplicate_title( $candidate, $existing_items ) {
		$cand_norm = self::normalize_accordion_title( $candidate );
		if ( '' === $cand_norm ) {
			return null;
		}
		$best = null;
		foreach ( $existing_items as $it ) {
			if ( ! isset( $it['item_title'] ) ) {
				continue;
			}
			$existing_title = (string) $it['item_title'];
			$ex_norm        = self::normalize_accordion_title( $existing_title );
			if ( '' === $ex_norm ) {
				continue;
			}
			if ( $cand_norm === $ex_norm ) {
				return array( 'title' => $existing_title, 'similarity' => 100 );
			}
			similar_text( $cand_norm, $ex_norm, $pct );
			if ( null === $best || $pct > $best['similarity'] ) {
				$best = array( 'title' => $existing_title, 'similarity' => (int) round( $pct ) );
			}
		}
		if ( $best && $best['similarity'] >= 80 ) {
			return $best;
		}
		return null;
	}

	/**
	 * Lowercase, strip punctuation, collapse whitespace, drop a small set of
	 * filler words. Designed to make rewritten-but-equivalent FAQ titles
	 * collapse to the same normalized form.
	 */
	private static function normalize_accordion_title( $title ) {
		$s = (string) $title;
		$s = mb_strtolower( $s );
		// Drop punctuation and any non-letter/digit/space characters.
		$s = preg_replace( '/[^a-z0-9\s]/u', ' ', $s );
		if ( null === $s ) {
			return '';
		}
		// Collapse whitespace.
		$s = trim( preg_replace( '/\s+/u', ' ', $s ) );
		// Drop low-signal filler tokens that don't change question meaning.
		// Generic grammatical fillers only. Do NOT add domain vocabulary here
		// (pre-v0.37 this list hardcoded 'tx','texas','hormones','therapy', which
		// over-collapsed distinct FAQ titles on therapy/HRT sites and meant
		// nothing on every other vertical). Keep this list industry-neutral.
		$fillers = array( 'the', 'a', 'an', 'is', 'are', 'do', 'does', 'i', 'my', 'your',
			'or', 'and', 'this', 'that', 'in', 'on', 'with', 'while', 'for',
		);
		$tokens = array_filter( explode( ' ', $s ), function ( $t ) use ( $fillers ) {
			return '' !== $t && ! in_array( $t, $fillers, true );
		} );
		return implode( ' ', $tokens );
	}

	/**
	 * v0.19: build_service_page macro tool. Clone a working pillar's full
	 * Elementor tree into a new draft page in ONE call, with text
	 * replacements applied. Replaces the 13-pending manual build that
	 * produced the misshapen Laser Genesis pillar — the source tree's
	 * container-level layout settings (flex_direction, flex_wrap, widths)
	 * are preserved byte-for-byte instead of approximated.
	 *
	 * POST /draft/build-service-page
	 *   {mirror_post_id, title, slug?, post_type?, replacements?,
	 *    skip_widget_ids?, seo_title?, seo_description?, focus_keyword?,
	 *    dry_run?, override_lint?}
	 */
	public static function handle_draft_build_service_page( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-build-service-page.php';
		$args = array(
			'mirror_post_id'         => (int) $req->get_param( 'mirror_post_id' ),
			'title'                  => (string) $req->get_param( 'title' ),
			'slug'                   => (string) $req->get_param( 'slug' ),
			'post_type'              => (string) ( $req->get_param( 'post_type' ) ?: 'page' ),
			'replacements'           => $req->get_param( 'replacements' ),
			'skip_widget_ids'        => $req->get_param( 'skip_widget_ids' ),
			'seo_title'              => (string) $req->get_param( 'seo_title' ),
			'seo_description'        => (string) $req->get_param( 'seo_description' ),
			'focus_keyword'          => (string) $req->get_param( 'focus_keyword' ),
			'target_language'        => (string) $req->get_param( 'target_language' ),
			'dry_run'                => filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN ),
			'override_lint'          => filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN ),
			'override_mirror_quality'=> filter_var( $req->get_param( 'override_mirror_quality' ), FILTER_VALIDATE_BOOLEAN ),
		);
		$result = CC_Assistant_Build_Service_Page::build( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	/**
	 * v0.40: build_page_from_spec. Compose a NEW page from typed section
	 * templates in one pass, with the 2026 winning bar built in (image
	 * required, grouping past 10 cards, AEO FAQ, heading hierarchy, full
	 * lint). Creates a draft + ONE publish_draft pending.
	 *
	 * POST /draft/build-from-spec
	 *   {title, slug?, post_type?, style_mirror_post_id, seo?, sections[],
	 *    dry_run?, override_lint?, override_images?}
	 */
	public static function handle_draft_build_from_spec( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-build-from-spec.php';
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = (array) $req->get_params();
		}
		$result = CC_Assistant_Build_From_Spec::build( $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	/**
	 * v0.22 suggest_best_mirror — rank existing service pages by clone-
	 * readiness so build_service_page gets a pre-vetted mirror_post_id
	 * instead of asking the operator to guess. Scored by pre-publish pass
	 * rate then word count, filtered by optional keyword + language.
	 *
	 * GET/POST /draft/suggest-best-mirror
	 *   params: service_keyword?, target_language?, exclude_id?, limit?
	 */
	public static function handle_suggest_best_mirror( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-build-service-page.php';
		$opts = array(
			'service_keyword' => (string) $req->get_param( 'service_keyword' ),
			'target_language' => (string) $req->get_param( 'target_language' ),
			'exclude_id'      => (int) $req->get_param( 'exclude_id' ),
			'limit'           => (int) $req->get_param( 'limit' ),
		);
		return self::wrap( CC_Assistant_Build_Service_Page::suggest_best_mirror( $opts ) );
	}

	/**
	 * v0.19: page_completeness_score. Compares target post's structure to
	 * a mirror's structure and returns a 0-100 score plus the list of
	 * sections / widget types the target is missing. Lets the caller refuse
	 * to publish a page that scores below an acceptable threshold.
	 *
	 * GET /page-completeness-score?post_id=NNN&mirror_post_id=MMM
	 */
	public static function handle_page_completeness_score( WP_REST_Request $req ) {
		$post_id   = (int) $req->get_param( 'post_id' );
		$mirror_id = (int) $req->get_param( 'mirror_post_id' );
		if ( $post_id <= 0 || $mirror_id <= 0 ) {
			return new WP_Error( 'invalid_payload', 'post_id and mirror_post_id are required.', array( 'status' => 400 ) );
		}
		$target_raw = get_post_meta( $post_id, '_elementor_data', true );
		$mirror_raw = get_post_meta( $mirror_id, '_elementor_data', true );
		if ( empty( $target_raw ) ) {
			return new WP_Error( 'no_elementor_data', sprintf( 'Post %d has no _elementor_data.', $post_id ), array( 'status' => 404 ) );
		}
		if ( empty( $mirror_raw ) ) {
			return new WP_Error( 'no_elementor_data', sprintf( 'Mirror %d has no _elementor_data.', $mirror_id ), array( 'status' => 404 ) );
		}
		$target = json_decode( $target_raw, true );
		$mirror = json_decode( $mirror_raw, true );
		if ( ! is_array( $target ) || ! is_array( $mirror ) ) {
			return new WP_Error( 'invalid_elementor_data', 'Could not parse Elementor data.', array( 'status' => 500 ) );
		}
		$summary_target = self::completeness_tally( $target );
		$summary_mirror = self::completeness_tally( $mirror );

		// Scoring: each missing widget type that the mirror has counts. We weigh
		// by mirror's count of that widget type so missing reviews / google_maps
		// hurts as much as missing 1 heading. Score = 1 - (weighted_missing /
		// weighted_total), bounded [0, 1].
		$weighted_total   = 0;
		$weighted_missing = 0;
		$missing_types    = array();
		foreach ( $summary_mirror['widget_types'] as $wt => $count ) {
			$weighted_total += $count;
			$have = isset( $summary_target['widget_types'][ $wt ] ) ? (int) $summary_target['widget_types'][ $wt ] : 0;
			$missing = max( 0, $count - $have );
			if ( $missing > 0 ) {
				$weighted_missing += $missing;
				$missing_types[]  = array(
					'widget_type'    => $wt,
					'mirror_count'   => $count,
					'target_count'   => $have,
					'shortfall'      => $missing,
				);
			}
		}
		$score = $weighted_total > 0
			? (int) round( 100 * ( 1 - ( $weighted_missing / $weighted_total ) ) )
			: 100;
		$score = max( 0, min( 100, $score ) );

		// Critical-section flags: an absent reviews / google_maps / image /
		// nested-accordion on a page that should be a service pillar is high-
		// signal. Surface them separately so the caller can branch.
		$critical_missing = array();
		foreach ( array( 'reviews', 'google_maps', 'image', 'nested-accordion' ) as $crit ) {
			if ( ( $summary_mirror['widget_types'][ $crit ] ?? 0 ) > 0
				&& 0 === ( $summary_target['widget_types'][ $crit ] ?? 0 ) ) {
				$critical_missing[] = $crit;
			}
		}

		return self::wrap( array(
			'post_id'           => $post_id,
			'mirror_post_id'    => $mirror_id,
			'score'             => $score,
			'pass'              => $score >= 80,
			'target_summary'    => $summary_target,
			'mirror_summary'    => $summary_mirror,
			'missing_types'     => $missing_types,
			'critical_missing'  => $critical_missing,
		) );
	}

	private static function completeness_tally( $tree ) {
		$root_containers = 0;
		$total_widgets   = 0;
		$widget_types    = array();
		$walker          = function ( $nodes, $depth ) use ( &$walker, &$total_widgets, &$widget_types, &$root_containers ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$el = isset( $n['elType'] ) ? $n['elType'] : '';
				if ( 'widget' === $el ) {
					$total_widgets++;
					$wt = isset( $n['widgetType'] ) ? $n['widgetType'] : 'unknown';
					$widget_types[ $wt ] = ( $widget_types[ $wt ] ?? 0 ) + 1;
				} elseif ( 0 === $depth && in_array( $el, array( 'container', 'section' ), true ) ) {
					$root_containers++;
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walker( $n['elements'], $depth + 1 );
				}
			}
		};
		$walker( $tree, 0 );
		return array(
			'root_containers' => $root_containers,
			'total_widgets'   => $total_widgets,
			'widget_types'    => $widget_types,
		);
	}

	/**
	 * v0.19: service_inventory. Walks every post tagged as a service pillar
	 * (post_type=page with MedicalProcedure schema OR explicit cc_service_pillar
	 * postmeta flag) and extracts the actual service tiers (menu card titles +
	 * prices + durations) from each pillar's Elementor tree. Result is the
	 * source of truth for "what services do we actually sell?" — Claude
	 * cross-references this before queueing a new menu so it cannot invent
	 * service tiers that do not exist (the $TBD failure mode).
	 *
	 * GET /service-inventory
	 */
	public static function handle_service_inventory( WP_REST_Request $req ) {
		// Find candidate service-pillar pages: pages with a MedicalProcedure
		// JSON-LD html widget OR a heading H1 ending in "in Irving, TX" / similar
		// service-page suffix. Cheap proxy heuristic; admin can override.
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'meta_query'     => array(
				array(
					'key'     => '_elementor_data',
					'compare' => 'EXISTS',
				),
			),
			'fields'         => 'ids',
		) );
		$pillars = array();
		foreach ( $pages as $pid ) {
			$raw = get_post_meta( $pid, '_elementor_data', true );
			if ( empty( $raw ) || false === strpos( $raw, 'MedicalProcedure' ) ) {
				continue;
			}
			$tree = json_decode( $raw, true );
			if ( ! is_array( $tree ) ) {
				continue;
			}
			$tiers = self::extract_service_tiers( $tree );
			if ( empty( $tiers ) ) {
				continue;
			}
			$pillars[] = array(
				'post_id'    => $pid,
				'post_title' => get_the_title( $pid ),
				'permalink'  => get_permalink( $pid ),
				'tier_count' => count( $tiers ),
				'tiers'      => $tiers,
			);
		}
		return self::wrap( array(
			'pillar_count' => count( $pillars ),
			'pillars'      => $pillars,
		) );
	}

	/**
	 * Walk an Elementor tree and surface anything that looks like a menu /
	 * pricing card: an inner column container holding a sequence of heading
	 * widgets (name, price, duration) plus a description text-editor. We do
	 * not require a strict schema — we just collect the title heading + any
	 * price-formatted heading we find in the same container.
	 */
	private static function extract_service_tiers( $tree ) {
		$tiers   = array();
		$walker  = function ( $nodes ) use ( &$walker, &$tiers ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if ( ! empty( $n['elements'] ) ) {
					// Heuristic: this node is a "card" if its direct children include
					// at least one h3 heading AND at least one heading whose text
					// matches a price/duration pattern (\$N or N MIN).
					$direct_widgets = array();
					foreach ( $n['elements'] as $c ) {
						if ( is_array( $c ) && isset( $c['elType'] ) && 'widget' === $c['elType'] ) {
							$direct_widgets[] = $c;
						}
					}
					$title = '';
					$price = '';
					$dur   = '';
					$desc  = '';
					foreach ( $direct_widgets as $w ) {
						$wt = $w['widgetType'] ?? '';
						$s  = $w['settings'] ?? array();
						if ( 'heading' === $wt ) {
							$h = (string) ( $s['header_size'] ?? 'h3' );
							$t = wp_strip_all_tags( (string) ( $s['title'] ?? '' ) );
							if ( 'h3' === $h && '' === $title ) {
								$title = $t;
							} elseif ( preg_match( '/^\s*\$\s*\d+/', $t ) ) {
								$price = $t;
							} elseif ( preg_match( '/\d+\s*MIN\b/i', $t ) || preg_match( '/\b(?:SINGLE|MOST POPULAR|BEST VALUE|MAX)\b/i', $t ) ) {
								$dur = $t;
							}
						} elseif ( 'text-editor' === $wt && '' === $desc ) {
							$desc = wp_strip_all_tags( (string) ( $s['editor'] ?? '' ) );
						}
					}
					if ( '' !== $title && ( '' !== $price || '' !== $dur ) ) {
						$tiers[] = array(
							'title'       => $title,
							'price'       => $price,
							'duration'    => $dur,
							'description' => mb_substr( $desc, 0, 200 ),
						);
					}
					$walker( $n['elements'] );
				}
			}
		};
		$walker( $tree );
		if ( empty( $tiers ) ) {
			// Fallback: pricing built from plain heading widgets (no h3 card
			// title) — e.g. an h4 "Fillers" followed by span-level headings
			// whose titles ARE the price strings ("$800 / 1 syringe").
			$tiers = self::extract_service_tiers_fallback( $tree );
		}
		return $tiers;
	}

	/**
	 * Fallback tier extraction for pricing "cards" built purely from plain
	 * heading widgets: an h3/h4 tier title followed by span-level heading
	 * widgets whose titles are price strings like "$800 / 1 syringe". The
	 * card heuristic above requires an h3 inside the same container, so it
	 * finds nothing on such pages. Walk every heading widget in document
	 * order; a price-patterned title becomes a tier whose title is the
	 * nearest preceding non-price h3/h4 heading (the full price string is
	 * kept as the description) or, failing that, the text after the slash.
	 */
	private static function extract_service_tiers_fallback( $tree ) {
		$headings = array();
		$walker   = function ( $nodes ) use ( &$walker, &$headings ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if ( isset( $n['elType'] ) && 'widget' === $n['elType'] && 'heading' === ( $n['widgetType'] ?? '' ) ) {
					$s          = $n['settings'] ?? array();
					$headings[] = array(
						'size'  => (string) ( $s['header_size'] ?? 'h2' ),
						'title' => trim( wp_strip_all_tags( (string) ( $s['title'] ?? '' ) ) ),
					);
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walker( $n['elements'] );
				}
			}
		};
		$walker( $tree );

		$tiers   = array();
		$current = '';
		foreach ( $headings as $h ) {
			$t = $h['title'];
			if ( '' === $t ) {
				continue;
			}
			if ( preg_match( '/^\s*(\$\s?[\d,]+(?:\.\d{2})?)\s*\/\s*(.+)$/', $t, $m ) ) {
				$after   = trim( $m[2] );
				$tiers[] = array(
					'title'       => '' !== $current ? $current : $after,
					'price'       => preg_replace( '/\s+/', '', $m[1] ),
					'duration'    => '',
					'description' => mb_substr( $t, 0, 200 ),
				);
			} elseif ( in_array( $h['size'], array( 'h3', 'h4' ), true ) && ! preg_match( '/\$\s?[\d,]+/', $t ) ) {
				$current = $t;
			}
		}
		return $tiers;
	}

	/**
	 * Surface a page's style signals: Elementor Kit globals, in-use fonts and
	 * colors, container shapes, and a sample icon-box from the page that can be
	 * cloned to seed a new section. Used by Claude before queueing an
	 * elementor_widget_add or elementor_container_add so the new widget reuses
	 * the same color tokens, font families, and container rhythm the rest of
	 * the page is already running on.
	 *
	 * GET /elementor/style-context?post_id=NNN
	 */
	public static function handle_elementor_style_context( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'post_id is required.', array( 'status' => 400 ) );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		$ctx = CC_Assistant_Elementor_Builder::get_style_context( $post_id );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		return self::wrap( $ctx );
	}

	/**
	 * Extract `{type, id}` for every schema entity declared inside a JSON-LD
	 * `<script>` tag in any HTML widget descendant of the spec tree. Used by
	 * the schema-dedup guard so the model can't queue a MedicalProcedure /
	 * FAQPage / WebPage entity whose `@id` already exists on the page.
	 *
	 * Singleton types (FAQPage, MedicalProcedure, MedicalWebPage, WebPage,
	 * Article, BreadcrumbList) collide on `@type` ALONE when neither side
	 * declares an `@id` — that's the "page already has a FAQPage" pattern
	 * Rank Math auto-generates from nested-accordion widgets.
	 *
	 * Returns a flat list of {type: string, id: string, source: 'proposed'}.
	 */
	private static function extract_schema_entities_from_children( $children ) {
		$out = array();
		if ( ! is_array( $children ) ) {
			return $out;
		}
		$walk = function ( $nodes ) use ( &$walk, &$out ) {
			foreach ( $nodes as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$type = isset( $c['type'] ) ? (string) $c['type'] : '';
				$wt   = isset( $c['widgetType'] ) ? (string) $c['widgetType'] : '';
				if ( 'widget' === $type && 'html' === $wt ) {
					$html = isset( $c['settings']['html'] ) ? (string) $c['settings']['html'] : '';
					self::scan_html_for_jsonld_entities( $html, $out );
				}
				if ( ! empty( $c['children'] ) ) {
					$walk( $c['children'] );
				}
			}
		};
		$walk( $children );
		return $out;
	}

	/**
	 * Scan a raw HTML string for JSON-LD <script> blocks and append every
	 * detected schema entity ({type, id}) into $acc. Handles three shapes
	 * the spec supports: a single object, an `@graph` with embedded
	 * entities, and a JSON array of entities.
	 */
	private static function scan_html_for_jsonld_entities( $html, &$acc ) {
		if ( '' === (string) $html ) {
			return;
		}
		if ( ! preg_match_all( '#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $html, $m ) ) {
			return;
		}
		foreach ( $m[1] as $blob ) {
			$decoded = json_decode( trim( $blob ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$entities = array();
			if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
				$entities = $decoded['@graph'];
			} elseif ( isset( $decoded['@type'] ) ) {
				$entities = array( $decoded );
			} elseif ( array_keys( $decoded ) === range( 0, count( $decoded ) - 1 ) ) {
				$entities = $decoded;
			}
			foreach ( $entities as $e ) {
				if ( ! is_array( $e ) ) {
					continue;
				}
				$t = $e['@type'] ?? '';
				if ( is_array( $t ) ) {
					$t = implode( '|', $t );
				}
				$acc[] = array(
					'type' => (string) $t,
					'id'   => isset( $e['@id'] ) ? (string) $e['@id'] : '',
				);
			}
		}
	}

	/**
	 * Walk the live `_elementor_data` for the post and collect every JSON-LD
	 * entity emitted by HTML widgets. Also checks for nested-accordion
	 * widgets with `faq_schema: yes` — those imply Rank Math will auto-emit
	 * a FAQPage on render, so we add a synthetic FAQPage entity to the live
	 * set to prevent a proposed FAQPage from stacking on top.
	 *
	 * Returns the same {type, id} shape as extract_schema_entities_from_children.
	 */
	private static function collect_live_schema_entities( $post_id ) {
		$out = array();
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return $out;
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return $out;
		}
		$walk = function ( $nodes ) use ( &$walk, &$out ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$is_widget = isset( $n['elType'] ) && 'widget' === $n['elType'];
				$wt        = isset( $n['widgetType'] ) ? (string) $n['widgetType'] : '';
				$s         = isset( $n['settings'] ) && is_array( $n['settings'] ) ? $n['settings'] : array();
				if ( $is_widget && 'html' === $wt && ! empty( $s['html'] ) ) {
					self::scan_html_for_jsonld_entities( (string) $s['html'], $out );
				}
				// Rank Math auto-FAQPage signal: nested-accordion with faq_schema=yes.
				if ( $is_widget && 'nested-accordion' === $wt && isset( $s['faq_schema'] ) && 'yes' === (string) $s['faq_schema'] ) {
					$out[] = array( 'type' => 'FAQPage', 'id' => 'rankmath:auto:nested-accordion' );
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walk( $n['elements'] );
				}
			}
		};
		$walk( $tree );
		return $out;
	}

	/**
	 * Compare proposed schema entities against live entities and return any
	 * collisions. Two kinds:
	 *   1. Same `@type` AND same non-empty `@id` (hard duplicate)
	 *   2. Same `@type` for a singleton type when EITHER side has no id
	 *      (e.g. proposed FAQPage with no @id + Rank Math auto-FAQPage
	 *      already present)
	 */
	private static function find_schema_collisions( $proposed_entities, $live_entities ) {
		$singletons = array(
			'FAQPage' => true,
			'MedicalProcedure' => true,
			'MedicalWebPage' => true,
			'WebPage' => true,
			'Article' => true,
			'BreadcrumbList' => true,
			'WebSite' => true,
		);
		$out = array();
		foreach ( $proposed_entities as $p ) {
			$ptype = $p['type'];
			$pid   = $p['id'];
			foreach ( $live_entities as $l ) {
				$ltype = $l['type'];
				$lid   = $l['id'];
				// Match type — handle pipe-joined union types from @type arrays.
				$ptypes = explode( '|', $ptype );
				$ltypes = explode( '|', $ltype );
				$type_overlap = false;
				foreach ( $ptypes as $pt ) {
					if ( '' === $pt ) {
						continue;
					}
					if ( in_array( $pt, $ltypes, true ) ) {
						$type_overlap = true;
						break;
					}
				}
				if ( ! $type_overlap ) {
					continue;
				}
				// Rule 1: same @id (non-empty)
				if ( '' !== $pid && $pid === $lid ) {
					$out[] = array( 'type' => $ptype, 'id' => $pid, 'reason' => 'same_id' );
					continue 2;
				}
				// Rule 2: singleton type with at least one missing id
				foreach ( $ptypes as $pt ) {
					if ( isset( $singletons[ $pt ] ) && in_array( $pt, $ltypes, true ) ) {
						if ( '' === $pid || '' === $lid ) {
							$out[] = array( 'type' => $pt, 'id' => $pid, 'reason' => 'singleton_already_on_page' );
							continue 3;
						}
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Collect proposed `html` widget bodies in a children spec. Used by the
	 * content-similarity guard to compare against existing widgets on the
	 * same page.
	 */
	private static function extract_html_bodies_from_children( $children ) {
		$out = array();
		if ( ! is_array( $children ) ) {
			return $out;
		}
		$walk = function ( $nodes ) use ( &$walk, &$out ) {
			foreach ( $nodes as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$type = isset( $c['type'] ) ? (string) $c['type'] : '';
				$wt   = isset( $c['widgetType'] ) ? (string) $c['widgetType'] : '';
				if ( 'widget' === $type && 'html' === $wt && ! empty( $c['settings']['html'] ) ) {
					$out[] = (string) $c['settings']['html'];
				}
				if ( ! empty( $c['children'] ) ) {
					$walk( $c['children'] );
				}
			}
		};
		$walk( $children );
		return $out;
	}

	/**
	 * Return `{id, html}` for every html-type widget currently on the post.
	 * Used by the content-similarity guard.
	 */
	private static function collect_live_html_widget_bodies( $post_id ) {
		$out = array();
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return $out;
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return $out;
		}
		$walk = function ( $nodes ) use ( &$walk, &$out ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if ( isset( $n['elType'], $n['widgetType'] ) && 'widget' === $n['elType'] && 'html' === $n['widgetType'] && ! empty( $n['settings']['html'] ) ) {
					$out[] = array( 'id' => (string) ( $n['id'] ?? '' ), 'html' => (string) $n['settings']['html'] );
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walk( $n['elements'] );
				}
			}
		};
		$walk( $tree );
		return $out;
	}

	/**
	 * Section-width lint (v0.28.1). Inspects the proposed outer container
	 * settings + recursive children for the global Elementor section-width
	 * rule: outer root containers cap at 1100-1300px (boxed_width); heading
	 * widgets inside cap at 700-900px (_element_custom_width). Only applies
	 * when adding at the page root (parent_id ''). Inner containers may use
	 * any width since they inherit the root's boxed constraint.
	 *
	 * Returns an array of human-readable violation messages. Empty array
	 * means the section passes.
	 */
	private static function check_section_width_constraints( $parent_id, $settings, $children ) {
		// Only enforce at root level. Inner containers (parent_id set) can
		// be any width — they inherit their root's boxed_width.
		if ( '' !== (string) $parent_id ) {
			return array();
		}
		$issues = array();

		// Outer container boxed_width sanity. Accept both {size:1280} integer
		// shape and {size:"1280"} string shape since Elementor saves either
		// depending on how the value was entered in the editor.
		$bw = 0;
		if ( isset( $settings['boxed_width']['size'] ) ) {
			$bw = (int) $settings['boxed_width']['size'];
		}
		if ( $bw <= 0 ) {
			$issues[] = 'Outer root container missing `boxed_width`. Add boxed_width: { unit: "px", size: 1280 } so the section caps at 1280px instead of bleeding to viewport edge.';
		} elseif ( $bw > 1300 ) {
			$issues[] = sprintf( 'Outer root container `boxed_width` is %dpx — wider than the 1300px max. Reduce to 1200-1300px.', $bw );
		} elseif ( $bw < 1100 ) {
			$issues[] = sprintf( 'Outer root container `boxed_width` is %dpx — narrower than the 1100px min. Use 1200-1300px.', $bw );
		}

		// Walk children for heading widgets and verify their width.
		// Depth starts at 0 (these children are the direct children of the
		// outer root container). The 700-900px floor only applies at depth
		// 0 (root container) and depth 1 (its direct children). Deeper
		// nesting — e.g. a heading inside a column inside a card grid —
		// legitimately needs a narrower width, so we skip the check there.
		$heading_issues = self::check_heading_widths_recursive( $children, 'children', 0 );
		$issues         = array_merge( $issues, $heading_issues );

		return $issues;
	}

	/**
	 * Recursively walk a proposed children subtree and flag any heading
	 * widget whose `_element_custom_width` is missing or outside the
	 * 700-900px allowed band. Skips non-heading widgets and non-array
	 * structures.
	 *
	 * Depth-aware (v0.35): the 700-900px floor / 900px ceiling only applies
	 * when $depth <= 1 (root container or its direct children). At depth
	 * >= 2 — headings inside nested columns, card grids, tiles — we skip
	 * the width check entirely. Pre-v0.35 the recursion ignored depth and
	 * refused legitimate narrow headings inside three-column card layouts.
	 */
	private static function check_heading_widths_recursive( $children, $path, $depth = 0 ) {
		$issues = array();
		if ( ! is_array( $children ) ) {
			return $issues;
		}
		foreach ( $children as $i => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$type = isset( $child['type'] ) ? (string) $child['type'] : '';
			$wt   = isset( $child['widgetType'] ) ? (string) $child['widgetType'] : '';
			if ( 'widget' === $type && 'heading' === $wt && $depth <= 1 ) {
				$settings = isset( $child['settings'] ) && is_array( $child['settings'] ) ? $child['settings'] : array();
				$width    = 0;
				if ( isset( $settings['_element_custom_width']['size'] ) ) {
					$width = (int) $settings['_element_custom_width']['size'];
				}
				if ( $width <= 0 ) {
					$issues[] = sprintf(
						'Heading widget at %s[%d] missing `_element_custom_width`. Add `_element_width: "initial"`, `_element_custom_width: { unit: "px", size: 850 }`, `_flex_align_self: "center"` so the heading text block caps at 850px (per global section-width rule).',
						$path,
						(int) $i
					);
				} elseif ( $width > 900 ) {
					$issues[] = sprintf(
						'Heading widget at %s[%d] `_element_custom_width` is %dpx — wider than the 900px max for heading text blocks. Reduce to 800-900px.',
						$path,
						(int) $i,
						$width
					);
				} elseif ( $width < 700 ) {
					$issues[] = sprintf(
						'Heading widget at %s[%d] `_element_custom_width` is %dpx — narrower than the 700px min. Use 800-900px.',
						$path,
						(int) $i,
						$width
					);
				}
			}
			// Recurse into nested children for sub-containers.
			if ( isset( $child['children'] ) && is_array( $child['children'] ) ) {
				$sub_issues = self::check_heading_widths_recursive( $child['children'], $path . '[' . (int) $i . '].children', $depth + 1 );
				$issues     = array_merge( $issues, $sub_issues );
			}
		}
		return $issues;
	}

	/**
	 * Walk a proposed children spec tree and collect every heading widget's
	 * `title` so the container_add pre-queue gate can compare against
	 * existing page headings. Used by the heading-already-on-page guard.
	 */
	private static function collect_heading_titles_from_children( $children ) {
		$out = array();
		if ( ! is_array( $children ) ) {
			return $out;
		}
		foreach ( $children as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$ct = isset( $c['type'] ) ? (string) $c['type'] : '';
			$wt = isset( $c['widgetType'] ) ? (string) $c['widgetType'] : '';
			if ( 'widget' === $ct && 'heading' === $wt ) {
				$title = isset( $c['settings']['title'] ) ? (string) $c['settings']['title'] : '';
				if ( '' !== trim( $title ) ) {
					$out[] = $title;
				}
			}
			if ( ! empty( $c['children'] ) ) {
				foreach ( self::collect_heading_titles_from_children( $c['children'] ) as $t ) {
					$out[] = $t;
				}
			}
		}
		return $out;
	}

	/**
	 * Build a normalized-title → original-title map of every heading widget
	 * (H1–H6, not span) currently on a post. Used by the heading consistency
	 * guard. Returns an empty array when the post has no Elementor data.
	 *
	 * Includes only widgets whose header_size is h1..h6. Span/eyebrow labels
	 * are intentionally excluded — those are decorative kickers like "PREMIUM
	 * MENU" that we don't want to collide with real heading dedup.
	 */
	private static function existing_heading_titles( $post_id ) {
		$out = array();
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return $out;
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return $out;
		}
		$walk = function ( $nodes ) use ( &$walk, &$out ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if ( isset( $n['elType'], $n['widgetType'] ) && 'widget' === $n['elType'] && 'heading' === $n['widgetType'] ) {
					$s     = isset( $n['settings'] ) && is_array( $n['settings'] ) ? $n['settings'] : array();
					$title = isset( $s['title'] ) ? (string) $s['title'] : '';
					$tag   = isset( $s['header_size'] ) ? (string) $s['header_size'] : 'h2';
					if ( '' !== trim( $title ) && preg_match( '/^h[1-6]$/i', $tag ) ) {
						$norm = self::normalize_heading_title( $title );
						if ( '' !== $norm && ! isset( $out[ $norm ] ) ) {
							$out[ $norm ] = $title;
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

	/**
	 * Lowercase + strip punctuation + collapse whitespace so two heading
	 * titles that differ only by trailing colon, ampersand vs "and", or
	 * stray double-space collide cleanly in the dup map.
	 */
	private static function normalize_heading_title( $title ) {
		$s = mb_strtolower( (string) $title );
		$s = preg_replace( '/[^a-z0-9\s]/u', ' ', $s );
		if ( null === $s ) {
			return '';
		}
		$s = trim( preg_replace( '/\s+/u', ' ', $s ) );
		return $s;
	}

	/**
	 * Recursively gather all text-bearing values from a children spec tree so
	 * container_add can lint the body text of pre-built children. Without this,
	 * a draft_add_elementor_container call that pre-builds 4 text-editor cards
	 * with em-dashes inside would bypass the style lint that draft_update would
	 * have caught on the same content.
	 */
	private static function collect_lint_text_from_children( $children ) {
		$out = '';
		if ( ! is_array( $children ) ) {
			return $out;
		}
		foreach ( $children as $ch ) {
			if ( ! is_array( $ch ) ) {
				continue;
			}
			if ( ! empty( $ch['settings'] ) && is_array( $ch['settings'] ) ) {
				foreach ( array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' ) as $field ) {
					if ( isset( $ch['settings'][ $field ] ) && is_string( $ch['settings'][ $field ] ) ) {
						$out .= "\n" . $ch['settings'][ $field ];
					}
				}
			}
			if ( ! empty( $ch['children'] ) ) {
				$out .= self::collect_lint_text_from_children( $ch['children'] );
			}
		}
		return $out;
	}

	/**
	 * v0.35: walk the children subtree and emit ONE payload entry per
	 * text-bearing widget so wall_of_text can run in isolation. Returns
	 * an array of ['html' => ..., 'settings' => ..., 'label' => ...] entries.
	 * The other lint checks (em_dashes / ai_tells / style_guide / placeholders /
	 * address / hospital / palette) still aggregate inside
	 * CC_Assistant_Pre_Publish::lint_html_block_per_widget.
	 */
	private static function collect_per_widget_lint_payloads( $children ) {
		$out = array();
		if ( ! is_array( $children ) ) {
			return $out;
		}
		$walker = function ( $nodes, $path ) use ( &$walker, &$out ) {
			foreach ( $nodes as $i => $ch ) {
				if ( ! is_array( $ch ) ) {
					continue;
				}
				$type     = isset( $ch['type'] ) ? (string) $ch['type'] : '';
				$wt       = isset( $ch['widgetType'] ) ? (string) $ch['widgetType'] : '';
				$settings = isset( $ch['settings'] ) && is_array( $ch['settings'] ) ? $ch['settings'] : array();
				if ( 'widget' === $type && ! empty( $settings ) ) {
					$widget_html = '';
					foreach ( array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' ) as $field ) {
						if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) ) {
							$widget_html .= "\n" . $settings[ $field ];
						}
					}
					if ( '' !== trim( $widget_html ) ) {
						$out[] = array(
							'html'     => $widget_html,
							'settings' => $settings,
							'label'    => ( '' !== $wt ? $wt : 'widget' ) . ' at ' . $path . '[' . (int) $i . ']',
						);
					} else {
						// Still pass settings through (no text) so address/palette
						// can inspect link.url, button URLs, hex colors, etc.
						$out[] = array(
							'html'     => '',
							'settings' => $settings,
							'label'    => ( '' !== $wt ? $wt : 'widget' ) . ' at ' . $path . '[' . (int) $i . ']',
						);
					}
				}
				if ( ! empty( $ch['children'] ) && is_array( $ch['children'] ) ) {
					$walker( $ch['children'], $path . '[' . (int) $i . '].children' );
				}
			}
		};
		$walker( $children, 'children' );
		return $out;
	}

	/**
	 * v0.35 per-widget linter for container_add. Runs page-level checks on
	 * the aggregated text and wall_of_text per widget via
	 * CC_Assistant_Pre_Publish::lint_html_block_per_widget. Returns the same
	 * shape (or WP_Error on hard violation) as lint_widget_settings_payload.
	 */
	private static function lint_per_widget_payloads( array $widget_payloads, WP_REST_Request $req ) {
		if ( empty( $widget_payloads ) || ! method_exists( 'CC_Assistant_Pre_Publish', 'lint_html_block_per_widget' ) ) {
			return null;
		}
		$block_lint = CC_Assistant_Pre_Publish::lint_html_block_per_widget( $widget_payloads );
		// v0.39: duplicate-card detection (3+ siblings with identical copy = an
		// unfilled template). Merged into the lint so the hard-violation
		// extraction below picks it up like any other check.
		if ( method_exists( 'CC_Assistant_Pre_Publish', 'detect_duplicate_blocks' ) ) {
			$dup = CC_Assistant_Pre_Publish::detect_duplicate_blocks( wp_list_pluck( $widget_payloads, 'html' ) );
			if ( $dup > 0 ) {
				$block_lint['duplicate_card_text'] = array(
					'pass'    => false,
					'message' => sprintf( '%d block(s) of identical text repeated across 3+ widgets — looks like an unfilled template. Give each card unique copy.', $dup ),
				);
			}
		}
		$hard       = array();
		$probe_layers = array();
		if ( ! empty( $block_lint['checks'] ) && is_array( $block_lint['checks'] ) ) {
			$probe_layers[] = $block_lint['checks'];
		}
		$probe_layers[] = $block_lint;
		foreach ( array( 'em_dashes', 'ai_tells', 'style_guide', 'placeholders', 'wall_of_text', 'address_consistency', 'hospital_comparison', 'duplicate_card_text', 'quote_source_link' ) as $name ) {
			foreach ( $probe_layers as $layer ) {
				if ( isset( $layer[ $name ]['pass'] ) && empty( $layer[ $name ]['pass'] ) ) {
					$hard[] = $name;
					break;
				}
			}
		}
		$lint     = array_merge( (array) $block_lint, array( 'hard_violations' => $hard ) );
		$override = filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $hard ) && ! $override ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf(
					'Proposed children fail: %s. Fix and resubmit, or pass override_lint=true to queue anyway.',
					implode( ', ', $hard )
				),
				array(
					'status' => 422,
					'lint'   => $lint,
				)
			);
		}
		return $lint;
	}

	/**
	 * Hex-color reference for known Elementor global color tokens on this site.
	 * Used by check_accessibility_issues() to detect "white-on-white" patterns
	 * before a queued widget gets written. Falls back to letting the operator
	 * decide if the token is unknown.
	 *
	 * Returns hex (lowercase, with leading "#") or null when unknown.
	 */
	private static function resolve_global_color_to_hex( $global_token ) {
		// Static system color mapping. The custom-color ids vary per site, so
		// we also pull from the active Kit for those. Cached per request.
		static $resolved = null;
		if ( null === $resolved ) {
			$resolved = array();
			$kit_id   = (int) get_option( 'elementor_active_kit', 0 );
			if ( $kit_id > 0 ) {
				$kit = get_post_meta( $kit_id, '_elementor_page_settings', true );
				if ( is_array( $kit ) ) {
					foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
						if ( ! empty( $kit[ $bucket ] ) && is_array( $kit[ $bucket ] ) ) {
							foreach ( $kit[ $bucket ] as $c ) {
								if ( isset( $c['_id'], $c['color'] ) ) {
									$resolved[ (string) $c['_id'] ] = strtolower( (string) $c['color'] );
								}
							}
						}
					}
				}
			}
		}
		if ( ! is_string( $global_token ) ) {
			return null;
		}
		// Token format: "globals/colors?id=primary" or "globals/colors?id=164e783"
		if ( ! preg_match( '#id=([A-Za-z0-9_-]+)#', $global_token, $m ) ) {
			return null;
		}
		$id = $m[1];
		return isset( $resolved[ $id ] ) ? $resolved[ $id ] : null;
	}

	/**
	 * WCAG-style luminance for a hex color. Returns 0..1 where higher is lighter.
	 * Source: WCAG 2.x relative-luminance formula.
	 */
	private static function color_luminance( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return null;
		}
		$rgb = array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);
		foreach ( $rgb as &$c ) {
			$c = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		unset( $c );
		return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
	}

	/**
	 * Effective foreground color for a widget setting: prefers explicit hex on
	 * the field (e.g. settings.title_color = "#003017"), falls back to the
	 * __globals__ token resolution. Returns null if unresolved.
	 */
	private static function effective_color( $settings, $field_name ) {
		if ( ! is_array( $settings ) ) {
			return null;
		}
		if ( ! empty( $settings[ $field_name ] ) && is_string( $settings[ $field_name ] ) && '#' === substr( $settings[ $field_name ], 0, 1 ) ) {
			return strtolower( $settings[ $field_name ] );
		}
		if ( ! empty( $settings['__globals__'][ $field_name ] ) ) {
			return self::resolve_global_color_to_hex( $settings['__globals__'][ $field_name ] );
		}
		return null;
	}

	/**
	 * Accessibility check on a single widget's settings. Catches the high-confidence
	 * "title or description is light AND no dark background is set" failure mode
	 * that bricked the v0.11.0 Delivery Methods + Results Timeline sections
	 * (title rendered white-on-white, description light-on-white). Returns a list
	 * of warning strings; empty array means no issues detected.
	 *
	 * Background resolution: we look at the widget's own _background_color, then
	 * at the inherited container background passed in via $container_bg (recursive
	 * caller propagates the parent's effective bg). When neither sets a dark bg,
	 * a light foreground color is flagged.
	 *
	 * "Light" means luminance > 0.6 (WCAG-style). "Dark" bg means luminance < 0.4.
	 */
	private static function check_widget_accessibility( $widget_type, $settings, $container_bg = null ) {
		$warnings = array();
		if ( ! is_array( $settings ) ) {
			return $warnings;
		}
		// Which fields are visible foreground text? Only check the widgets where
		// these settings are actually rendered.
		$check_fields_by_type = array(
			'icon-box'    => array( 'title_color' => 'title_text', 'description_color' => 'description_text' ),
			'image-box'   => array( 'title_color' => 'title_text', 'description_color' => 'description_text' ),
			'heading'     => array( 'title_color' => 'title' ),
			'text-editor' => array( 'text_color' => 'editor' ),
			'button'      => array( 'button_text_color' => 'text' ),
		);
		if ( ! isset( $check_fields_by_type[ $widget_type ] ) ) {
			return $warnings;
		}
		// Resolve effective background: widget's own background takes precedence,
		// then inherited container bg.
		$bg_hex = null;
		if ( ! empty( $settings['_background_color'] ) && is_string( $settings['_background_color'] ) && '#' === substr( $settings['_background_color'], 0, 1 ) ) {
			$bg_hex = strtolower( $settings['_background_color'] );
		} elseif ( ! empty( $settings['background_color'] ) && is_string( $settings['background_color'] ) && '#' === substr( $settings['background_color'], 0, 1 ) ) {
			$bg_hex = strtolower( $settings['background_color'] );
		} elseif ( $container_bg ) {
			$bg_hex = $container_bg;
		}
		$bg_lum = null === $bg_hex ? 1.0 : self::color_luminance( $bg_hex ); // assume white page bg when unset
		if ( null === $bg_lum ) {
			$bg_lum = 1.0;
		}
		foreach ( $check_fields_by_type[ $widget_type ] as $color_field => $text_field ) {
			// Only complain if the text field actually has content (otherwise
			// it does not matter what color the invisible empty value is).
			if ( empty( $settings[ $text_field ] ) ) {
				continue;
			}
			$fg_hex = self::effective_color( $settings, $color_field );
			if ( null === $fg_hex ) {
				continue;
			}
			$fg_lum = self::color_luminance( $fg_hex );
			if ( null === $fg_lum ) {
				continue;
			}
			// Contrast ratio (WCAG): (max + 0.05) / (min + 0.05). 4.5 is AA for body text.
			$ratio = ( max( $fg_lum, $bg_lum ) + 0.05 ) / ( min( $fg_lum, $bg_lum ) + 0.05 );
			if ( $ratio < 3.0 ) {
				$warnings[] = sprintf(
					'%s widget: %s (%s) on bg (%s) has contrast ratio %.2f, below WCAG AA 4.5. Text will be hard to read. Set an explicit dark text color (e.g. "#003017" or "#202020") OR add a dark background to the container.',
					$widget_type,
					$color_field,
					$fg_hex,
					$bg_hex ?: 'inherited/white',
					$ratio
				);
			}
		}
		return $warnings;
	}

	/**
	 * Recursively walk a children spec tree and accumulate accessibility warnings
	 * across every widget. Each container can declare a background color that
	 * children inherit, so we propagate $effective_bg as we descend.
	 */
	private static function check_accessibility_in_children( $children, $effective_bg = null ) {
		$warnings = array();
		if ( ! is_array( $children ) ) {
			return $warnings;
		}
		foreach ( $children as $ch ) {
			if ( ! is_array( $ch ) ) {
				continue;
			}
			$child_bg = $effective_bg;
			$settings = isset( $ch['settings'] ) && is_array( $ch['settings'] ) ? $ch['settings'] : array();
			// Containers can declare their own bg; propagate to descendants.
			if ( ! empty( $ch['type'] ) && 'container' === $ch['type'] ) {
				if ( ! empty( $settings['background_color'] ) && is_string( $settings['background_color'] ) && '#' === substr( $settings['background_color'], 0, 1 ) ) {
					$child_bg = strtolower( $settings['background_color'] );
				}
			}
			if ( ! empty( $ch['type'] ) && 'widget' === $ch['type'] ) {
				$wt = isset( $ch['widgetType'] ) ? (string) $ch['widgetType'] : '';
				$w_warn = self::check_widget_accessibility( $wt, $settings, $child_bg );
				foreach ( $w_warn as $msg ) {
					$warnings[] = $msg;
				}
			}
			if ( ! empty( $ch['children'] ) ) {
				$inner = self::check_accessibility_in_children( $ch['children'], $child_bg );
				foreach ( $inner as $msg ) {
					$warnings[] = $msg;
				}
			}
		}
		return $warnings;
	}

	/**
	 * Position guidance for a fresh root-level container_add. When the caller
	 * inserts at root without specifying position, the new section lands at
	 * the BOTTOM of the page — which is usually the wrong reading-flow spot
	 * and was the second symptom of the v0.11.0 Delivery Methods bug. This
	 * surfaces a warning + suggested positions so the operator can make an
	 * informed call.
	 */
	private static function position_warnings_for_root_insert( $post_id, $position ) {
		if ( null !== $position ) {
			return array();
		}
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return array();
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$root_count = count( $data );
		// Pages with an FAQ + Promise + Reviews block at the end (very common
		// service-page pattern) almost never want a new section appended past
		// those. Suggest mid-body positions instead.
		$suggest_low  = max( 3, (int) floor( $root_count * 0.4 ) );
		$suggest_high = max( $suggest_low + 1, (int) floor( $root_count * 0.7 ) );
		return array( array(
			'code'    => 'no_position',
			'message' => sprintf(
				'parent_id is "" and position is unset — new section will append at index %d (page bottom). To insert in body flow, pass an explicit position. Common mid-body slots on this page: %d, %d.',
				$root_count,
				$suggest_low,
				$suggest_high
			),
			'root_count' => $root_count,
		) );
	}

	/**
	 * Shared lint helper for the new widget-add and accordion-item-add endpoints.
	 * Returns the lint report (array) on success, or a WP_Error if hard
	 * violations were detected and override_lint is not set. Mirrors the gate
	 * in handle_draft_elementor_widget.
	 */
	private static function lint_widget_settings_payload( $settings, WP_REST_Request $req ) {
		$lint_text = '';
		foreach ( array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' ) as $field ) {
			if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) ) {
				$lint_text .= "\n" . $settings[ $field ];
			}
		}
		if ( '' === trim( $lint_text ) || ! method_exists( 'CC_Assistant_Pre_Publish', 'lint_html_block' ) ) {
			return null;
		}
		// v0.35: pass $settings as second arg so address_consistency_check +
		// palette_compliance_check run on this widget's non-text fields too.
		$block_lint = CC_Assistant_Pre_Publish::lint_html_block( $lint_text, is_array( $settings ) ? $settings : null );
		$hard       = array();
		$probe_layers = array();
		if ( ! empty( $block_lint['checks'] ) && is_array( $block_lint['checks'] ) ) {
			$probe_layers[] = $block_lint['checks'];
		}
		$probe_layers[] = $block_lint;
		foreach ( array( 'em_dashes', 'ai_tells', 'style_guide', 'placeholders', 'wall_of_text', 'address_consistency', 'hospital_comparison', 'quote_source_link' ) as $name ) {
			foreach ( $probe_layers as $layer ) {
				if ( isset( $layer[ $name ]['pass'] ) && empty( $layer[ $name ]['pass'] ) ) {
					$hard[] = $name;
					break;
				}
			}
		}
		$lint = array_merge( (array) $block_lint, array( 'hard_violations' => $hard ) );
		$override = filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $hard ) && ! $override ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf(
					'Proposed widget content fails: %s. Fix and resubmit, or pass override_lint=true to queue anyway.',
					implode( ', ', $hard )
				),
				array(
					'status' => 422,
					'lint'   => $lint,
				)
			);
		}
		return $lint;
	}

	/**
	 * Return earlier pending changes (still in pending status) that target the
	 * same post_id + widget_id. Used for conflict warnings on widget queue —
	 * the caller can then merge or reject the older row instead of stacking
	 * silent overwrites.
	 */
	private static function find_widget_conflicts( $post_id, $widget_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		// LIKE on a synthesized JSON fragment we know we wrote ourselves at
		// queue time: '"widget_id":"<id>"'. Cheap and exact for our own format.
		$needle = '"widget_id":"' . str_replace( '"', '\\"', $widget_id ) . '"';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, change_summary, created_at FROM {$table}
				WHERE status = 'pending'
				  AND superseded_by IS NULL
				  AND change_type = 'elementor_widget_update'
				  AND post_id = %d
				  AND proposed_value LIKE %s
				ORDER BY id ASC",
				$post_id,
				'%' . $wpdb->esc_like( $needle ) . '%'
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	private static function find_widget_settings( $post_id, $widget_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$node = self::find_widget_by_id( $data, $widget_id );
		if ( null === $node ) {
			return null;
		}
		return isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
	}

	/**
	 * Queue an outline-only proposal for human approval before a body rewrite.
	 * The model proposes a structured outline (H2 by H2, with action markers:
	 * keep / cut / merge / add) plus a one-line rationale. Once approved,
	 * the row sits in cc_pending_changes with status='approved' and
	 * change_type='rewrite_outline' — draft_update_post_content reads it to
	 * decide whether a large body rewrite on the same post is gated.
	 *
	 * The structured outline shape:
	 *   {
	 *     "outline": [
	 *       { "action": "keep|cut|merge|add", "h2": "H2 heading", "notes": "" },
	 *       …
	 *     ],
	 *     "rationale": "One-line summary of the editorial plan"
	 *   }
	 */
	public static function handle_draft_outline( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$outline   = $req->get_param( 'outline' );
		$rationale = (string) $req->get_param( 'rationale' );
		if ( ! is_array( $outline ) || empty( $outline ) ) {
			return new WP_Error( 'outline_required', 'outline (array of {action, h2, notes}) is required and must be non-empty.', array( 'status' => 400 ) );
		}

		// Sanitize each outline row. Accept only known action values; clip
		// h2 + notes to reasonable lengths so a runaway model can't bloat
		// the proposed_value blob.
		$allowed_actions = array( 'keep', 'cut', 'merge', 'add', 'rewrite' );
		$clean_outline   = array();
		foreach ( $outline as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$action = isset( $row['action'] ) ? sanitize_key( $row['action'] ) : '';
			if ( ! in_array( $action, $allowed_actions, true ) ) {
				$action = 'keep';
			}
			$clean_outline[] = array(
				'action' => $action,
				'h2'     => mb_substr( sanitize_text_field( (string) ( $row['h2'] ?? '' ) ), 0, 200 ),
				'notes'  => mb_substr( sanitize_text_field( (string) ( $row['notes'] ?? '' ) ), 0, 400 ),
			);
		}

		$payload = array(
			'outline'   => $clean_outline,
			'rationale' => mb_substr( sanitize_textarea_field( $rationale ), 0, 1000 ),
		);

		// Generate a clean summary describing the structural plan: counts of
		// each action type so the inbox card surfaces the gist without
		// the reviewer expanding the row.
		$counts = array( 'keep' => 0, 'cut' => 0, 'merge' => 0, 'add' => 0, 'rewrite' => 0 );
		foreach ( $clean_outline as $row ) {
			if ( isset( $counts[ $row['action'] ] ) ) {
				$counts[ $row['action'] ]++;
			}
		}
		$summary = $req->get_param( 'summary' );
		if ( empty( $summary ) ) {
			$parts = array();
			foreach ( $counts as $action => $n ) {
				if ( $n > 0 ) {
					$parts[] = sprintf( '%d %s', $n, $action );
				}
			}
			$summary = sprintf( 'Outline plan: %s', empty( $parts ) ? '(empty)' : implode( ', ', $parts ) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => $post_id,
				'change_type'    => 'rewrite_outline',
				'change_summary' => (string) $summary,
				'current_value'  => wp_json_encode( array( 'outline' => null ) ),
				'proposed_value' => wp_json_encode( $payload ),
				'reasoning'      => (string) $req->get_param( 'reasoning' ),
				'status'         => 'pending',
				'created_by'     => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'next_step'  => 'Wait for the reviewer to approve this outline. Once approved, draft_update_post_content on the same post (within 7 days) will accept the body without an outline-required refusal.',
		) );
	}

	/**
	 * Look up an approved rewrite_outline row for a given post within the
	 * last $days_window days. Returns the row id + reviewed_at if found,
	 * null otherwise. Used by the body-rewrite gate to decide whether a
	 * large change has the editorial sign-off it needs.
	 *
	 * Cutoff is site-local because cc_pending_changes.reviewed_at is written
	 * via current_time('mysql') (site-local). gmdate(time() - delta) would
	 * silently break on non-UTC sites.
	 */
	/**
	 * Heading skeleton comparison for the outline-required gate (v0.10.31).
	 *
	 * Returns true when the H2/H3 count between current and proposed body
	 * differs, signalling a structural restructure that should go through
	 * outline review. Returns false when the heading skeleton is intact —
	 * prose-only rephrasings should be allowed to pass without an outline
	 * even if the char delta is large, because they aren't editorial
	 * restructures.
	 *
	 * Compares counts rather than exact text so a heading-text rephrase
	 * (e.g. "What to Watch" -> "Warning Signs to Watch") is treated as a
	 * non-structural change. If you want to gate on heading text changes
	 * too, this is where to extend.
	 */
	private static function heading_skeleton_changed( $current_html, $proposed_html ) {
		$count = function ( $html ) {
			$h2 = preg_match_all( '/<h2\b/i', (string) $html );
			$h3 = preg_match_all( '/<h3\b/i', (string) $html );
			return array( 'h2' => (int) $h2, 'h3' => (int) $h3 );
		};
		$a = $count( $current_html );
		$b = $count( $proposed_html );
		return $a['h2'] !== $b['h2'] || $a['h3'] !== $b['h3'];
	}

	private static function recent_approved_outline( $post_id, $days_window = 7 ) {
		global $wpdb;
		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( (int) $days_window * DAY_IN_SECONDS ) );
		$row    = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, reviewed_at FROM {$wpdb->prefix}cc_pending_changes
			 WHERE post_id = %d
			   AND change_type = 'rewrite_outline'
			   AND status = 'approved'
			   AND reviewed_at >= %s
			 ORDER BY reviewed_at DESC LIMIT 1",
			(int) $post_id,
			$cutoff
		), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Surgical patch alternative to handle_draft_post_content. Takes a list of
	 * {search, replace} pairs, applies them in order to the current post_content,
	 * and delegates to handle_draft_post_content for lint, outline gating, and
	 * queueing.
	 *
	 * Solves the "regenerate full body to insert one inline link" problem: the
	 * caller no longer re-emits the whole body, so CRLF normalization, curly-
	 * apostrophe substitution, and other invisible char drifts can't sneak in.
	 *
	 * Each `search` must match EXACTLY ONCE in the current body. Zero matches
	 * returns 404; 2+ matches returns 422 (caller must add surrounding context
	 * to disambiguate). Strict-by-default is intentional: a silently-skipped or
	 * silently-double-applied patch is far worse than a refusal.
	 *
	 * Patches are applied sequentially against the running content, so a later
	 * patch can target text inserted by an earlier patch if needed.
	 */
	public static function handle_draft_post_content_patch( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$patches = $req->get_param( 'patches' );
		if ( ! is_array( $patches ) || empty( $patches ) ) {
			return new WP_Error( 'patches_required', 'patches (array of {search, replace}) is required.', array( 'status' => 400 ) );
		}
		if ( count( $patches ) > 50 ) {
			return new WP_Error( 'patches_too_many', 'Maximum 50 patches per call. Split into multiple calls.', array( 'status' => 413 ) );
		}

		$content        = (string) $post->post_content;
		$patches_summary = array();

		foreach ( $patches as $i => $patch ) {
			if ( ! is_array( $patch ) || ! isset( $patch['search'] ) || ! isset( $patch['replace'] ) ) {
				return new WP_Error(
					'patch_malformed',
					sprintf( 'Patch #%d must be an object with `search` and `replace` keys.', $i ),
					array( 'status' => 400, 'patch_index' => $i )
				);
			}
			$search  = (string) $patch['search'];
			$replace = (string) $patch['replace'];
			if ( '' === $search ) {
				return new WP_Error(
					'patch_empty_search',
					sprintf( 'Patch #%d has an empty `search`.', $i ),
					array( 'status' => 400, 'patch_index' => $i )
				);
			}
			$count = substr_count( $content, $search );
			if ( 0 === $count ) {
				return new WP_Error(
					'patch_no_match',
					sprintf( 'Patch #%d: `search` not found in current post_content. Refetch with get_post and copy the substring byte-for-byte (whitespace, curly quotes, &amp; entities, CRLF all matter).', $i ),
					array(
						'status'         => 404,
						'patch_index'    => $i,
						'search_preview' => substr( $search, 0, 120 ),
					)
				);
			}
			if ( $count > 1 ) {
				return new WP_Error(
					'patch_ambiguous',
					sprintf( 'Patch #%d: `search` matches %d times. Add surrounding context to make the match unique.', $i, $count ),
					array(
						'status'         => 422,
						'patch_index'    => $i,
						'match_count'    => $count,
						'search_preview' => substr( $search, 0, 120 ),
					)
				);
			}
			if ( $search === $replace ) {
				return new WP_Error(
					'patch_noop',
					sprintf( 'Patch #%d: `search` equals `replace` (no change).', $i ),
					array( 'status' => 400, 'patch_index' => $i )
				);
			}

			$content = str_replace( $search, $replace, $content );
			$patches_summary[] = array(
				'index'       => $i,
				'chars_added' => strlen( $replace ) - strlen( $search ),
				'preview'     => substr( $search, 0, 60 ) . ( strlen( $search ) > 60 ? '…' : '' ),
			);
		}

		// Hand off to the full-body handler — it runs all the lint, outline
		// gating, success_metrics handling, and queueing logic. We only had to
		// build the new content; everything else is shared.
		$req->set_param( 'content', $content );
		$response = self::handle_draft_post_content( $req );

		// Enrich the response with patches_applied so the caller (the model)
		// gets feedback on exactly what was applied, in order. Helpful for
		// chaining patches or debugging unexpected lint output. handle_draft_
		// post_content returns either WP_Error (failure) or wrap()'s shape
		// {site, data} (success / dry-run). We only enrich the success shape.
		if ( is_array( $response ) && isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$response['data']['patches_applied'] = $patches_summary;
		}

		return $response;
	}

	public static function handle_draft_post_content( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$content = $req->get_param( 'content' );
		if ( null === $content || '' === $content ) {
			return new WP_Error( 'content_required', 'content is required.', array( 'status' => 400 ) );
		}
		$content = (string) $content;

		// Cap absurd payloads. 1MB of HTML is enough for any single post.
		if ( strlen( $content ) > 1024 * 1024 ) {
			return new WP_Error( 'content_too_large', 'Proposed content exceeds 1MB. Split into multiple smaller updates.', array( 'status' => 413 ) );
		}

		// If the proposed content is identical to current, no-op.
		if ( $content === (string) $post->post_content ) {
			return new WP_Error( 'no_change', 'Proposed content is identical to current post_content.', array( 'status' => 400 ) );
		}

		// Outline-first gate (v0.10.31: structural-delta aware).
		//
		// Two conditions now trigger the gate, BOTH must be true:
		//   1) char_delta exceeds the size threshold (was 2000, raised to 4000)
		//   2) heading skeleton changed (h2/h3 count differs from current)
		//
		// Rationale: pure prose rephrasings that preserve every H2/H3 are not
		// editorial-restructuring changes; they're style/clarity passes and
		// should not require a fresh outline. The structural delta is what
		// signals a real restructure (sections added/removed/renamed at H2
		// level), and that is what the outline review should govern.
		//
		// Surgical edits (inline-link inserts, ~30 char delta) still pass
		// immediately because they fail condition 1. Major restructures still
		// need an outline because they fail both conditions. Prose-only
		// revisions that keep the heading skeleton pass through condition 2.
		// force_no_outline=true is preserved for migrations / batch fixes,
		// dry_run skips the gate so the model can preview lint independently.
		$char_delta_abs = abs( strlen( $content ) - strlen( (string) $post->post_content ) );
		$skip_outline   = filter_var( $req->get_param( 'force_no_outline' ), FILTER_VALIDATE_BOOLEAN );
		$is_dry_run     = filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN );
		$heading_changed = self::heading_skeleton_changed( (string) $post->post_content, $content );
		if ( $char_delta_abs > 4000 && $heading_changed && ! $skip_outline && ! $is_dry_run ) {
			$approved_outline = self::recent_approved_outline( $post_id, 7 );
			if ( null === $approved_outline ) {
				return new WP_Error(
					'outline_required',
					sprintf(
						'Body rewrite of %d chars on post %d changes the heading skeleton (H2/H3 count differs). Restructures need an approved outline first. Call propose_rewrite_outline(post_id=%d, outline=[...]) and wait for the reviewer to approve it. Pass force_no_outline=true to bypass (rare; only for migrations or batch fixes), or dry_run=true to preview the lint without queueing.',
						$char_delta_abs,
						$post_id,
						$post_id
					),
					array(
						'status'              => 422,
						'char_delta'          => $char_delta_abs,
						'heading_changed'     => true,
						'outline_endpoint'    => '/wp-json/cc-assistant/v1/draft/outline',
						'outline_window_days' => 7,
					)
				);
			}
		}

		// Pre-flight content lint: paragraph length, sentence length, reading
		// level, HTML cruft, jargon, em dashes, AI tells, banned phrases, plus
		// proposed-vs-current diff checks (bulk_add_ratio, deletion_ratio,
		// redundancy). Hard violations are refused by default so the model is
		// forced to fix obvious mistakes before queueing. The reviewer can
		// override by passing override_lint=true (treated as "I know what I'm
		// doing, queue it anyway and surface the lint on the inbox card").
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$lint     = CC_Assistant_Pre_Publish::lint_post_content_change( (string) $post->post_content, $content );
		$override = filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $lint['hard_violations'] ) && ! $override ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf(
					'Proposed content fails: %s. Fix and resubmit, or pass override_lint=true to queue anyway and let the reviewer decide.',
					implode( ', ', $lint['hard_violations'] )
				),
				array(
					'status' => 422,
					'lint'   => $lint,
				)
			);
		}

		// Optional success_metrics: target_query, target_position, target_ctr,
		// eval_window_days. Stored as JSON on the pending row so edit_outcomes
		// can later score the change against the operator's stated intent.
		$success_raw = $req->get_param( 'success_metrics' );
		$success     = null;
		if ( is_array( $success_raw ) && ! empty( $success_raw ) ) {
			$success = self::sanitize_success_metrics( $success_raw );
		}

		$proposed_payload = wp_json_encode( array( 'content' => $content ) );
		$gate             = self::pre_queue_gate( $req, $post_id, 'post_content_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }

		$lint_summary = array(
			'pass'       => $lint['pass'],
			'pass_count' => $lint['pass_count'],
			'fail_count' => $lint['fail_count'],
			'failed'     => self::lint_failed_names( $lint ),
			// v0.68: failures split by attribution. "introduced" is the only
			// list that reflects THIS change; "pre_existing" already fails on
			// the stored body and must not be read as caused by this edit.
			'introduced'   => isset( $lint['introduced'] ) ? $lint['introduced'] : array(),
			'pre_existing' => isset( $lint['pre_existing'] ) ? $lint['pre_existing'] : array(),
		);

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type'     => 'post_content_update',
				'current_length'  => strlen( (string) $post->post_content ),
				'proposed_length' => strlen( $content ),
				'character_delta' => strlen( $content ) - strlen( (string) $post->post_content ),
				'lint'            => $lint_summary,
				'warnings'        => $gate['warnings'],
			) );
		}

		// Capture pre-check duplication baseline. Auto-detects cluster siblings
		// and runs find_clusters() at threshold 0.5. Stored as JSON on the
		// pending row; the post-apply verifier reads it back and re-runs the
		// same comparison post-apply to compute the cosine delta. No-op for
		// posts without cluster membership.
		require_once CC_ASSISTANT_DIR . 'includes/class-post-apply-verifier.php';
		$pre_check_baseline = CC_Assistant_Post_Apply_Verifier::capture_baseline( $post_id );

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'            => $post_id,
				'change_type'        => 'post_content_update',
				'change_summary'     => $req->get_param( 'summary' ) ?: 'Replace post body',
				'current_value'      => wp_json_encode( array( 'content' => (string) $post->post_content ) ),
				'proposed_value'     => $proposed_payload,
				'reasoning'          => (string) $req->get_param( 'reasoning' ),
				'lint_report'        => $lint,
				'success_metrics'    => $success,
				'pre_check_baseline' => $pre_check_baseline,
				'status'             => 'pending',
				'created_by'         => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		$out = array(
			'pending_id'      => $pending_id,
			'review_url'      => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'current_length'  => strlen( (string) $post->post_content ),
			'proposed_length' => strlen( $content ),
			'character_delta' => strlen( $content ) - strlen( (string) $post->post_content ),
			'lint'            => $lint_summary,
			'warnings'        => $gate['warnings'],
		);
		// v0.68.2: say at QUEUE time whether the render-health baseline was
		// captured. A failed capture used to surface only as a missing pill
		// after apply, three silent steps downstream of the actual failure.
		if ( class_exists( 'CC_Assistant_Render_Health' ) ) {
			$out['render_baseline'] = CC_Assistant_Render_Health::last_capture_status();
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
		return self::wrap( $out );
	}

	/**
	 * Sanitize the success_metrics payload. Whitelisted keys only — anything
	 * else gets dropped silently so the model can't smuggle arbitrary fields
	 * onto the pending row.
	 */
	/**
	 * Public alias so other REST classes (class-rest-seo, class-rest-clusters)
	 * can sanitize their own success_metrics payload through the same whitelist.
	 */
	public static function sanitize_success_metrics_public( $raw ) {
		return self::sanitize_success_metrics( $raw );
	}

	private static function sanitize_success_metrics( $raw ) {
		$out = array();
		if ( isset( $raw['target_query'] ) ) {
			$out['target_query'] = sanitize_text_field( (string) $raw['target_query'] );
		}
		if ( isset( $raw['target_position'] ) ) {
			$out['target_position'] = (float) $raw['target_position'];
		}
		if ( isset( $raw['target_ctr'] ) ) {
			$out['target_ctr'] = (float) $raw['target_ctr'];
		}
		if ( isset( $raw['eval_window_days'] ) ) {
			$out['eval_window_days'] = max( 7, min( 90, (int) $raw['eval_window_days'] ) );
		}
		if ( isset( $raw['hypothesis'] ) ) {
			$out['hypothesis'] = sanitize_textarea_field( (string) $raw['hypothesis'] );
		}
		return empty( $out ) ? null : $out;
	}

	/**
	 * Centralised pre-queue gate used by every draft_* / propose_* handler.
	 * One call decides:
	 *   - Should this be queued, or is dry_run=true ?
	 *   - Are there rate-limit / duplicate / sibling-collision warnings to
	 *     surface to the caller (informational, not blocking)?
	 * Returns an array with three keys:
	 *   - dry_run     : bool, mirrors the request param
	 *   - warnings    : array of {code, message} pairs. Caller appends to response.
	 *   - skip_queue  : bool, true when dry_run mode and the caller should NOT
	 *                   call ::queue() but instead build a synthetic response.
	 *
	 * Filtering for created_by='claude' so manual admin proposals don't trip
	 * the rate-limit warning meant for runaway model loops.
	 */
	/**
	 * v0.44: a representative blocklist of Font Awesome 6-only solid icons —
	 * renamed from FA5 or FA6-new — that render BLANK on the bundled FA5.
	 * Returns the FA6 names found in a proposed-value JSON string.
	 */
	private static function fa6_only_icons_in( $json ) {
		if ( '' === (string) $json ) {
			return array();
		}
		static $fa6_only = array(
			'fa-person-walking', 'fa-person-running', 'fa-shield-heart', 'fa-house-medical',
			'fa-house-chimney-medical', 'fa-head-side-cough', 'fa-truck-medical', 'fa-kit-medical',
			'fa-user-doctor', 'fa-heart-pulse', 'fa-magnifying-glass', 'fa-xmark', 'fa-circle-xmark',
			'fa-circle-info', 'fa-trash-can', 'fa-pen-to-square', 'fa-arrow-up-right-from-square',
			'fa-bars-staggered', 'fa-arrow-right-long', 'fa-square-check', 'fa-phone-flip',
		);
		$found = array();
		foreach ( $fa6_only as $name ) {
			if ( false !== strpos( $json, $name ) ) {
				$found[] = $name;
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * v0.81: one line per post-apply audit from a row's verification_result
	 * JSON. Keys written by class-post-apply-audit (render_health,
	 * rendered_schema_audit) and class-post-apply-verifier (summary). Pure.
	 */
	public static function compact_verification( $json ) {
		if ( ! is_string( $json ) || '' === $json ) {
			return array( 'audited' => false, 'attention' => false );
		}
		$v = json_decode( $json, true );
		if ( ! is_array( $v ) ) {
			return array( 'audited' => false, 'attention' => false );
		}
		$out = array( 'audited' => true, 'attention' => false );
		if ( isset( $v['render_health'] ) && is_array( $v['render_health'] ) ) {
			$rh = $v['render_health'];
			$out['render_health'] = ! empty( $rh['checked'] )
				? ( ! empty( $rh['critical'] ) ? 'CRITICAL: ' . count( (array) ( isset( $rh['findings'] ) ? $rh['findings'] : array() ) ) . ' finding(s)' : ( empty( $rh['findings'] ) ? 'ok' : count( (array) $rh['findings'] ) . ' warning(s)' ) )
				: 'not checked' . ( ! empty( $rh['reason'] ) ? ': ' . $rh['reason'] : '' );
			if ( ! empty( $rh['critical'] ) ) {
				$out['attention'] = true;
			}
		}
		if ( isset( $v['rendered_schema_audit'] ) && is_array( $v['rendered_schema_audit'] ) ) {
			$sa = $v['rendered_schema_audit'];
			$issues = isset( $sa['issues'] ) ? count( (array) $sa['issues'] ) : ( isset( $sa['findings'] ) ? count( (array) $sa['findings'] ) : 0 );
			$out['rendered_schema'] = $issues ? $issues . ' issue(s)' : 'ok';
			if ( $issues ) {
				$out['attention'] = true;
			}
		}
		if ( isset( $v['summary'] ) && is_array( $v['summary'] ) ) {
			$s = $v['summary'];
			$reg = isset( $s['regressed'] ) ? (int) $s['regressed'] : 0;
			$out['duplication'] = $reg ? $reg . ' pair(s) regressed' : ( isset( $s['status'] ) ? (string) $s['status'] : 'ok' );
			if ( $reg ) {
				$out['attention'] = true;
			}
		}
		return $out;
	}

	private static function pre_queue_gate( $req, $post_id, $change_type, $proposed_value ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-evidence-gate.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-validation.php';
		$args = compact( 'post_id', 'change_type', 'proposed_value' );
		$proof = CC_Assistant_Evidence_Gate::validate_queue( $args );
		if ( is_wp_error( $proof ) ) { return $proof; }
		$valid = CC_Assistant_Elementor_Validation::validate_payload( $args );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$dry_run  = filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN );
		$warnings = array();

		// Session bootstrap gate: every chat must go through pre-flight (whoami
		// -> recap -> design skill -> render_probe -> pending check) before it
		// mutates content. Soft reminder by default; hard 409 only if the
		// operator turned on cc_assistant_bootstrap_hard_gate.
		require_once CC_ASSISTANT_DIR . 'includes/class-session-gate.php';
		$hard_gate = CC_Assistant_Session_Gate::hard_block_if_enabled();
		if ( is_wp_error( $hard_gate ) ) {
			return $hard_gate;
		}
		$boot_reminder = CC_Assistant_Session_Gate::reminder_if_stale();
		if ( $boot_reminder ) {
			$warnings[] = $boot_reminder;
		}

		// v0.44: Font Awesome 6 icons render BLANK on the bundled FA5. Catch
		// FA6-only icon names in the proposed value at queue time so a section
		// of invisible icons is flagged before approval (cost us #288 once).
		$fa6 = self::fa6_only_icons_in( (string) $proposed_value );
		if ( ! empty( $fa6 ) ) {
			$warnings[] = array(
				'code'    => 'fa6_icon',
				'message' => sprintf(
					'FA6-only icon(s) detected (%s) — these render BLANK on Font Awesome 5. Swap for an FA5 glyph (e.g. fa-walking, fa-shield-alt, fa-heartbeat, fa-user-md, fa-search, fa-times).',
					implode( ', ', array_slice( $fa6, 0, 6 ) )
				),
			);
		}

		// Rate-limit: more than 10 claude-queued pending rows on the same
		// post in the last hour suggests a loop or an un-batched session.
		// Informational, not blocking — the human can still approve in bulk
		// from the inbox if the work is legitimate.
		if ( $post_id ) {
			$recent_count = CC_Assistant_Pending_Changes::recent_for_post( (int) $post_id, 60 );
			if ( $recent_count >= 10 ) {
				$warnings[] = array(
					'code'    => 'rate_limit',
					'message' => sprintf( '%d pending changes already queued by Claude on post %d in the last hour. Consider batching or approving the queue before adding more.', $recent_count, (int) $post_id ),
				);
			}
		}

		// Duplicate detection: an exact-hash match within the last hour. As of
		// v0.13.0 this is a HARD block (422) for change types that mutate the
		// Elementor tree, so successive supersede attempts can't stack into 3
		// copies of the same section landing on the page. Override via
		// `override_dup=true` for the rare case where the operator legitimately
		// wants the duplicate queued (e.g. A/B variants pending side-by-side).
		$dups = CC_Assistant_Pending_Changes::find_similar_recent( $post_id, $change_type, $proposed_value, 60 );
		if ( ! empty( $dups ) ) {
			$structural_types = array(
				'elementor_widget_add',
				'elementor_widget_remove',
				'elementor_container_add',
				'elementor_accordion_item_add',
				'elementor_accordion_item_remove',
			);
			$override_dup = filter_var( $req->get_param( 'override_dup' ), FILTER_VALIDATE_BOOLEAN );
			if ( in_array( $change_type, $structural_types, true ) && ! $override_dup ) {
				return new WP_Error(
					'duplicate_pending',
					sprintf(
						'%d existing pending row(s) with identical proposed_value already queued in the last hour (e.g. #%d "%s"). Approve or reject the existing row instead of stacking. Pass override_dup=true if a duplicate is genuinely needed.',
						count( $dups ),
						(int) $dups[0]['id'],
						mb_substr( (string) ( $dups[0]['change_summary'] ?? '' ), 0, 80 )
					),
					array( 'status' => 422, 'matches' => array_slice( $dups, 0, 3 ) )
				);
			}
			// For non-structural change types or when override is set, keep
			// the prior soft-warning behavior so existing callers don't break.
			$warnings[] = array(
				'code'    => 'duplicate',
				'message' => sprintf( '%d existing pending row(s) with identical proposed_value found in the last hour. This is probably a retry — review or reject the older row instead of stacking.', count( $dups ) ),
				'matches' => array_slice( $dups, 0, 3 ),
			);
		}

		return array(
			'dry_run'    => $dry_run,
			'warnings'   => $warnings,
			'skip_queue' => $dry_run,
		);
	}

	/**
	 * Build the synthetic response shape returned when dry_run=true. Mirrors
	 * the structure of the real queue response so the model's downstream
	 * logic can ignore the dry_run boundary, except pending_id is null and
	 * a top-level dry_run=true flag is present.
	 */
	private static function dry_run_response( $extra = array() ) {
		return self::wrap( array_merge(
			array(
				'dry_run'    => true,
				'pending_id' => null,
				'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			),
			$extra
		) );
	}

	/**
	 * Pull just the failed check names off a lint report for the response
	 * payload. Keeps the queue endpoint's response small while still telling
	 * the model exactly which checks to fix on retry.
	 */
	private static function lint_failed_names( $lint ) {
		$names = array();
		if ( ! empty( $lint['checks'] ) && is_array( $lint['checks'] ) ) {
			foreach ( $lint['checks'] as $name => $c ) {
				if ( empty( $c['pass'] ) ) {
					$names[] = $name;
				}
			}
		}
		return $names;
	}

	public static function handle_draft_create_post( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-apply.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';

		$content = (string) $req->get_param( 'content' );
		$title   = (string) $req->get_param( 'title' );
		if ( '' === $title ) {
			return new WP_Error( 'title_required', 'title is required.', array( 'status' => 400 ) );
		}
		if ( '' === $content ) {
			return new WP_Error( 'content_required', 'content is required.', array( 'status' => 400 ) );
		}

		// Same content-quality lint as body rewrites: em dashes, AI-tells,
		// banned phrases (hard violations), plus paragraph/sentence length,
		// reading level, HTML cruft, jargon density (warnings). The diff-based
		// checks (bulk_add_ratio, deletion_ratio, redundancy) don't apply on
		// new posts since there is no current body to compare against.
		//
		// Title is the POST H1, not the SERP title — uses logical_key
		// 'post_title' which runs the meaningful checks (em dashes, AI-tells,
		// banned phrases, suspicious chars) but skips the SERP-length window
		// (50-60 chars). H1 titles routinely exceed 60 chars; the Rank Math
		// SEO title is set separately via draft_update_seo_meta and gets the
		// length check there.
		$body_lint  = CC_Assistant_Pre_Publish::lint_html_block( $content );
		$title_lint = CC_Assistant_Pre_Publish::lint_seo_meta( 'post_title', $title );
		$lint       = self::merge_lint_reports( $body_lint, $title_lint );
		$override   = filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $lint['hard_violations'] ) && ! $override ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf(
					'Proposed draft fails: %s. Fix and resubmit, or pass override_lint=true.',
					implode( ', ', $lint['hard_violations'] )
				),
				array( 'status' => 422, 'lint' => $lint )
			);
		}

		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? self::sanitize_success_metrics( $success_raw )
			: null;

		$lint_summary = array(
			'pass'       => $lint['pass'],
			'pass_count' => $lint['pass_count'],
			'fail_count' => $lint['fail_count'],
			'failed'     => self::lint_failed_names( $lint ),
		);

		// Dry-run skips both the WP draft insert AND the pending queue. We
		// still surface the lint + a synthesized payload so the caller can
		// preview without leaving an orphan draft post on the site.
		$dry_run = filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN );
		if ( $dry_run ) {
			return self::dry_run_response( array(
				'change_type'    => 'publish_draft',
				'proposed_title' => $title,
				'content_length' => strlen( $content ),
				'lint'           => $lint_summary,
			) );
		}

		// Categories: optional array of term IDs to attach on insert. Falls
		// through to create_draft_post → wp_set_post_terms after wp_insert_post.
		$category_ids_raw = $req->get_param( 'category_ids' );
		$category_ids     = array();
		if ( is_array( $category_ids_raw ) ) {
			foreach ( $category_ids_raw as $cid ) {
				$cid = (int) $cid;
				if ( $cid > 0 ) {
					$category_ids[] = $cid;
				}
			}
		}

		// `categories` is the more ergonomic param: accepts either term IDs
		// (integers / numeric strings) or category names (strings). Names are
		// resolved against existing terms in apply layer; missing names are
		// auto-created so a fresh draft never silently lands in "Uncategorized".
		$categories_raw = $req->get_param( 'categories' );
		$categories     = is_array( $categories_raw ) ? $categories_raw : array();

		// Tags: array of strings; missing tags auto-created downstream.
		$tags_raw = $req->get_param( 'tags' );
		$tags     = array();
		if ( is_array( $tags_raw ) ) {
			foreach ( $tags_raw as $t ) {
				$name = trim( (string) $t );
				if ( '' !== $name ) {
					$tags[] = $name;
				}
			}
		}

		$featured_image_id = (int) $req->get_param( 'featured_image_id' );

		// Multilingual params (optional). `lang` sets the new post's language
		// taxonomy slug immediately (e.g. "es"). `polylang_translation_of`
		// points at an existing post the new draft is a translation of — the
		// apply layer links them via pll_save_post_translations after insert.
		$lang_param            = trim( (string) $req->get_param( 'lang' ) );
		$translation_of_param  = (int) $req->get_param( 'polylang_translation_of' );

		// Optional explicit slug. When omitted, WordPress derives it from the
		// title — which on long H1s lands a 100+ char post_name that hurts
		// shareability and SEO. Accepting a slug at create time lets the
		// caller commit to a canonical pillar slug pattern in one step
		// instead of needing a follow-up draft_update_post_meta(post_name).
		$slug_param = sanitize_title( (string) $req->get_param( 'slug' ) );

		// v0.51.5 — Theme Builder template creation. A template CPT create must
		// declare which Theme Builder slot it fills; a typeless elementor_library
		// post shows up as an unusable orphan in the Theme Builder screen.
		$post_type_param     = $req->get_param( 'post_type' ) ?: 'page';
		$template_type_param = sanitize_key( (string) $req->get_param( 'template_type' ) );
		$conditions_raw      = $req->get_param( 'display_conditions' );
		$display_conditions  = is_array( $conditions_raw ) ? array_map( 'strval', $conditions_raw ) : array();
		if ( self::is_template_post_type( (string) $post_type_param ) ) {
			$known_template_types = array( 'error-404', 'header', 'footer', 'single', 'single-page', 'single-post', 'archive', 'search-results', 'section', 'popup' );
			if ( '' === $template_type_param ) {
				return new WP_Error( 'template_type_required', 'template_type is required when creating a Theme Builder template. One of: ' . implode( ', ', $known_template_types ), array( 'status' => 400 ) );
			}
			if ( ! in_array( $template_type_param, $known_template_types, true ) ) {
				return new WP_Error( 'template_type_unknown', sprintf( 'Unknown template_type "%s". One of: %s', $template_type_param, implode( ', ', $known_template_types ) ), array( 'status' => 400 ) );
			}
		}

		$args = array(
			'title'                   => $title,
			'content'                 => $content,
			'excerpt'                 => (string) $req->get_param( 'excerpt' ),
			'post_type'               => $post_type_param,
			'template_type'           => $template_type_param,
			'display_conditions'      => $display_conditions,
			'summary'                 => (string) $req->get_param( 'summary' ),
			'reasoning'               => (string) $req->get_param( 'reasoning' ),
			'lint_report'             => $lint,
			'success_metrics'         => $success,
			'category_ids'            => $category_ids,
			'categories'              => $categories,
			'tags'                    => $tags,
			'featured_image_id'       => $featured_image_id,
			'lang'                    => $lang_param,
			'polylang_translation_of' => $translation_of_param,
			'slug'                    => $slug_param,
			'workflow_id'             => (string) $req->get_param( 'workflow_id' ),
		);

		$result = CC_Assistant_Apply::create_draft_post( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = array_merge( $result, array(
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'lint'       => $lint_summary,
		) );

		if ( ! self::is_template_post_type( (string) $post_type_param ) ) {
            $response['content_evidence'] = self::info_gain_signals( $content );
            $response['reader_value_review'] = 'Identify the reader task, supported contribution and source. No GSC gap or minimum impression count is required to propose a useful in-niche blog.';
        }

		return self::wrap( $response );
	}

	/**
	 * Detect non-commodity ("information gain") elements in draft HTML:
	 * first-party prices, data tables, authority citations, and a named
	 * provider (from the brand profile / facility settings when available).
	 */
	public static function info_gain_signals( $content ) {
        require_once CC_ASSISTANT_DIR . 'includes/class-content-evidence.php';
        return CC_Assistant_Content_Evidence::surface_signals( $content );
    }

    /** Categories remain subject to the existing drafting permissions. */

	public static function handle_draft_categories( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$ids_raw = $req->get_param( 'category_ids' );
		if ( ! is_array( $ids_raw ) ) {
			return new WP_Error( 'category_ids_required', 'category_ids must be an array of integer term IDs.', array( 'status' => 400 ) );
		}
		$category_ids = array();
		foreach ( $ids_raw as $cid ) {
			$cid = (int) $cid;
			if ( $cid > 0 ) {
				$category_ids[] = $cid;
			}
		}
		if ( empty( $category_ids ) ) {
			return new WP_Error( 'category_ids_empty', 'At least one valid category id is required.', array( 'status' => 400 ) );
		}

		// Validate each id resolves to an existing category term so we don't
		// queue a change that will silently no-op on apply.
		$missing = array();
		foreach ( $category_ids as $cid ) {
			if ( ! get_term( $cid, 'category' ) ) {
				$missing[] = $cid;
			}
		}
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'category_not_found',
				sprintf( 'Category term(s) not found: %s', implode( ', ', $missing ) ),
				array( 'status' => 404 )
			);
		}

		$append = filter_var( $req->get_param( 'append' ), FILTER_VALIDATE_BOOLEAN );

		// Capture current categories for diff display + rollback.
		$current_ids = wp_get_post_categories( $post_id );

		$proposed_payload = wp_json_encode( array(
			'category_ids' => $category_ids,
			'append'       => (bool) $append,
		) );

		$gate = self::pre_queue_gate( $req, $post_id, 'category_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type'  => 'category_update',
				'category_ids' => $category_ids,
				'append'       => (bool) $append,
				'warnings'     => $gate['warnings'],
			) );
		}

		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? self::sanitize_success_metrics( $success_raw )
			: null;

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => $post_id,
				'change_type'     => 'category_update',
				'change_summary'  => $req->get_param( 'summary' ) ?: sprintf( 'Set %d categor%s', count( $category_ids ), count( $category_ids ) === 1 ? 'y' : 'ies' ),
				'current_value'   => wp_json_encode( array( 'category_ids' => array_map( 'intval', $current_ids ) ) ),
				'proposed_value'  => $proposed_payload,
				'reasoning'       => (string) $req->get_param( 'reasoning' ),
				'success_metrics' => $success,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * Create a category term immediately (no pending review). Categories are
	 * organisational metadata, not content — gating them behind the inbox
	 * adds friction without safety. The capability check (check_permission =
	 * manage_options) is the gate. Returns the created term info, or the
	 * existing term info if name+slug already exists (idempotent).
	 */
	public static function handle_category_create( WP_REST_Request $req ) {
		$name = trim( (string) $req->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error( 'name_required', 'name is required.', array( 'status' => 400 ) );
		}
		$slug        = sanitize_title( (string) $req->get_param( 'slug' ) );
		$description = (string) $req->get_param( 'description' );
		$parent      = (int) $req->get_param( 'parent' );

		// Idempotent: if a term with this slug (or name) already exists, return
		// it instead of erroring. Avoids duplicate terms when a re-run hits the
		// endpoint after partial success.
		if ( '' !== $slug ) {
			$existing = get_term_by( 'slug', $slug, 'category' );
			if ( $existing ) {
				return self::wrap( array(
					'created'     => false,
					'already_exists' => true,
					'term_id'     => (int) $existing->term_id,
					'name'        => $existing->name,
					'slug'        => $existing->slug,
					'description' => $existing->description,
					'parent'      => (int) $existing->parent,
				) );
			}
		} else {
			$existing = get_term_by( 'name', $name, 'category' );
			if ( $existing ) {
				return self::wrap( array(
					'created'        => false,
					'already_exists' => true,
					'term_id'        => (int) $existing->term_id,
					'name'           => $existing->name,
					'slug'           => $existing->slug,
					'description'    => $existing->description,
					'parent'         => (int) $existing->parent,
				) );
			}
		}

		$args = array();
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}
		if ( '' !== $description ) {
			$args['description'] = $description;
		}
		if ( $parent > 0 && get_term( $parent, 'category' ) ) {
			$args['parent'] = $parent;
		}

		$result = wp_insert_term( $name, 'category', $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = (int) ( $result['term_id'] ?? 0 );
		$term    = $term_id ? get_term( $term_id, 'category' ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'term_lookup_failed', 'Term created but could not be re-fetched.', array( 'status' => 500 ) );
		}

		return self::wrap( array(
			'created'        => true,
			'already_exists' => false,
			'term_id'        => (int) $term->term_id,
			'name'           => $term->name,
			'slug'           => $term->slug,
			'description'    => $term->description,
			'parent'         => (int) $term->parent,
		) );
	}

	/**
	 * Return all category terms with their IDs, names, slugs, and post counts.
	 * Useful for picking the right category_id before queueing an assignment.
	 */
	public static function handle_category_list( WP_REST_Request $req ) {
		$terms = get_terms( array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		) );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$out = array();
		foreach ( (array) $terms as $t ) {
			$out[] = array(
				'term_id'     => (int) $t->term_id,
				'name'        => $t->name,
				'slug'        => $t->slug,
				'description' => $t->description,
				'parent'      => (int) $t->parent,
				'count'       => (int) $t->count,
			);
		}
		return self::wrap( array( 'categories' => $out, 'total' => count( $out ) ) );
	}

	/**
	 * Taxonomies draft_update_term may touch. WooCommerce product categories
	 * are the headline use case (thin "money pages"); blog categories ride
	 * along for term-description SEO there too.
	 */
	private static function term_edit_taxonomies() {
		return array( 'product_cat', 'category' );
	}

	/**
	 * v0.53 — list terms of an allowed taxonomy with SEO state. Read-only
	 * recon for the category-page optimization loop: which terms have no
	 * description (thin archive), what Rank Math term meta exists, and the
	 * front-end permalink for curl/render verification.
	 */
	public static function handle_terms_list( WP_REST_Request $req ) {
		$taxonomy = sanitize_key( $req->get_param( 'taxonomy' ) ?: 'product_cat' );
		if ( ! in_array( $taxonomy, self::term_edit_taxonomies(), true ) ) {
			return new WP_Error( 'taxonomy_not_allowed', sprintf( 'Taxonomy "%s" is not editable. Allowed: %s.', $taxonomy, implode( ', ', self::term_edit_taxonomies() ) ), array( 'status' => 403 ) );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'taxonomy_missing', sprintf( 'Taxonomy "%s" is not registered on this site.', $taxonomy ), array( 'status' => 404 ) );
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		// v0.69: full descriptions across ~70 terms produced a 125KB response
		// that overflowed the caller's context. Default to a plain-text excerpt;
		// full=true restores complete HTML descriptions for content work.
		$full = rest_sanitize_boolean( $req->get_param( 'full' ) );
		$out  = array();
		foreach ( (array) $terms as $t ) {
			$link      = get_term_link( $t );
			$desc_text = wp_strip_all_tags( (string) $t->description );
			$row       = array(
				'term_id'           => (int) $t->term_id,
				'name'              => $t->name,
				'slug'              => $t->slug,
				'parent'            => (int) $t->parent,
				'count'             => (int) $t->count,
				'permalink'         => is_wp_error( $link ) ? null : $link,
				'description_words' => str_word_count( $desc_text ),
				'description'       => $full ? (string) $t->description : mb_substr( trim( preg_replace( '/\s+/', ' ', $desc_text ) ), 0, 240 ),
				'seo_title'         => (string) get_term_meta( $t->term_id, 'rank_math_title', true ),
				'seo_description'   => (string) get_term_meta( $t->term_id, 'rank_math_description', true ),
			);
			if ( ! $full && strlen( $desc_text ) > 240 ) {
				$row['description_truncated'] = true;
			}
			$out[] = $row;
		}
		return self::wrap( array( 'taxonomy' => $taxonomy, 'terms' => $out, 'total' => count( $out ), 'descriptions' => $full ? 'full' : 'excerpt (pass full=true for complete HTML)' ) );
	}

	/**
	 * v0.53 — queue a term_update pending change: category description and/or
	 * Rank Math term SEO title/description. Draft-only like every other write:
	 * lands in the Pending Changes inbox, applies via wp_update_term +
	 * update_term_meta on approval, and the queued current_value snapshot is
	 * the revert payload. Description HTML goes through the standard content
	 * lint (em dashes / AI-tells / banned phrases are hard); SEO fields go
	 * through the meta lint with their length windows.
	 */
	public static function handle_draft_term( WP_REST_Request $req ) {
		$term_id  = (int) $req->get_param( 'term_id' );
		$taxonomy = sanitize_key( $req->get_param( 'taxonomy' ) ?: 'product_cat' );
		if ( ! in_array( $taxonomy, self::term_edit_taxonomies(), true ) ) {
			return new WP_Error( 'taxonomy_not_allowed', sprintf( 'Taxonomy "%s" is not editable. Allowed: %s.', $taxonomy, implode( ', ', self::term_edit_taxonomies() ) ), array( 'status' => 403 ) );
		}
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'term_not_found', sprintf( 'Term %d not found in taxonomy "%s".', $term_id, $taxonomy ), array( 'status' => 404 ) );
		}

		$description     = $req->get_param( 'description' );
		$seo_title       = $req->get_param( 'seo_title' );
		$seo_description = $req->get_param( 'seo_description' );
		if ( null === $description && null === $seo_title && null === $seo_description ) {
			return new WP_Error( 'nothing_to_change', 'Provide at least one of: description, seo_title, seo_description.', array( 'status' => 400 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$success_raw  = $req->get_param( 'success_metrics' );
		$success      = is_array( $success_raw ) && ! empty( $success_raw ) ? self::sanitize_success_metrics( $success_raw ) : null;
		$target_query = is_array( $success ) && ! empty( $success['target_query'] ) ? (string) $success['target_query'] : '';

		$hard = array();
		$lints = array();
		if ( null !== $description ) {
			// lint_html_block() returns a RAW checks array (name => check) with
			// no hard_violations envelope — compute blockers the same way the
			// widget-update path does (v0.51.3 dual-shape handling).
			$block_lint = CC_Assistant_Pre_Publish::lint_html_block( (string) $description );
			$desc_hard  = array();
			$hard_names = array( 'em_dashes', 'ai_tells', 'style_guide', 'wall_of_text', 'address_consistency', 'hospital_comparison', 'quote_source_link' );
			$check_set  = ( ! empty( $block_lint['checks'] ) && is_array( $block_lint['checks'] ) ) ? $block_lint['checks'] : $block_lint;
			foreach ( $hard_names as $name ) {
				if ( isset( $check_set[ $name ] ) && empty( $check_set[ $name ]['pass'] ) ) {
					$desc_hard[] = $name;
				}
			}
			$desc_failed = array();
			foreach ( $check_set as $name => $c ) {
				if ( is_array( $c ) && array_key_exists( 'pass', $c ) && empty( $c['pass'] ) ) {
					$desc_failed[] = $name;
				}
			}
			$lints['description'] = array( 'pass' => empty( $desc_failed ), 'checks' => array(), 'failed_names' => $desc_failed );
			$hard = array_merge( $hard, $desc_hard );
		}
		if ( null !== $seo_title ) {
			$l = CC_Assistant_Pre_Publish::lint_seo_meta( 'title', (string) $seo_title, $target_query );
			$lints['seo_title'] = $l;
			if ( ! empty( $l['hard_violations'] ) ) {
				$hard = array_merge( $hard, (array) $l['hard_violations'] );
			}
		}
		if ( null !== $seo_description ) {
			$l = CC_Assistant_Pre_Publish::lint_seo_meta( 'description', (string) $seo_description, $target_query );
			$lints['seo_description'] = $l;
			if ( ! empty( $l['hard_violations'] ) ) {
				$hard = array_merge( $hard, (array) $l['hard_violations'] );
			}
		}
		$override = filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $hard ) && ! $override ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf( 'Proposed term update fails: %s. Fix and resubmit, or pass override_lint=true to queue anyway.', implode( ', ', array_unique( $hard ) ) ),
				array( 'status' => 422, 'lint' => $lints )
			);
		}

		$current  = array( 'term_id' => $term_id, 'taxonomy' => $taxonomy, 'term_name' => $term->name );
		$proposed = array( 'term_id' => $term_id, 'taxonomy' => $taxonomy, 'term_name' => $term->name );
		if ( null !== $description ) {
			$current['description']  = (string) $term->description;
			$proposed['description'] = (string) $description;
		}
		if ( null !== $seo_title ) {
			$current['seo_title']  = (string) get_term_meta( $term_id, 'rank_math_title', true );
			$proposed['seo_title'] = (string) $seo_title;
		}
		if ( null !== $seo_description ) {
			$current['seo_description']  = (string) get_term_meta( $term_id, 'rank_math_description', true );
			$proposed['seo_description'] = (string) $seo_description;
		}

		$proposed_payload = wp_json_encode( $proposed );
		$gate             = self::pre_queue_gate( $req, 0, 'term_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }

		$lint_summary = array();
		foreach ( $lints as $field => $l ) {
			$failed = isset( $l['failed_names'] ) ? $l['failed_names'] : self::lint_failed_names( $l );
			$lint_summary[ $field ] = array(
				'pass'   => ! empty( $l['pass'] ) && empty( $failed ),
				'failed' => $failed,
			);
		}

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'term_update',
				'term_id'     => $term_id,
				'term_name'   => $term->name,
				'lint'        => $lint_summary,
				'warnings'    => $gate['warnings'],
			) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => 0,
				'change_type'     => 'term_update',
				'change_summary'  => $req->get_param( 'summary' ) ?: sprintf( 'Update %s category "%s"', $taxonomy, $term->name ),
				'current_value'   => wp_json_encode( $current ),
				'proposed_value'  => $proposed_payload,
				'reasoning'       => (string) $req->get_param( 'reasoning' ),
				'success_metrics' => $success,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		$term_link = get_term_link( $term );
		return self::wrap( array(
			'pending_id' => $pending_id,
			'term_id'    => $term_id,
			'term_name'  => $term->name,
			'permalink'  => is_wp_error( $term_link ) ? null : $term_link,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'lint'       => $lint_summary,
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * v0.76 — read the active Elementor kit (Site Settings): colour and
	 * typography registries with the exact dot paths a write needs.
	 */
	public static function handle_get_kit_settings( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-kit-writer.php';
		$r = CC_Assistant_Kit_Writer::read();
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return self::wrap( $r );
	}

	/**
	 * v0.76 — queue a kit_setting_update pending change: one leaf of the
	 * active kit's _elementor_page_settings. Same discipline as the plugin
	 * setting writer (exists-or-refuse, whole-blob snapshot for revert), plus
	 * colour validation, because the kit is what turns a #cc3366 theme-default
	 * link into a site-wide contrast failure or a one-approval fix.
	 */
	public static function handle_draft_kit_setting( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-kit-writer.php';
		$plan = CC_Assistant_Kit_Writer::build_plan(
			array(
				'path'         => $req->get_param( 'path' ),
				'value'        => $req->get_param( 'value' ),
				'allow_create' => $req->get_param( 'allow_create' ),
			)
		);
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$disp = function ( $v ) {
			if ( is_bool( $v ) ) {
				return $v ? 'true' : 'false';
			}
			$v = (string) $v;
			return '' === $v ? '(empty)' : $v;
		};
		$summary = sprintf( 'Elementor Site Settings [%s]: %s -> %s', $plan['path'], $disp( $plan['prior_value'] ), $disp( $plan['value'] ) );
		$payload = wp_json_encode( $plan );
		$gate    = self::pre_queue_gate( $req, 0, 'kit_setting_update', $payload );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$fields = array(
			'change_type'  => 'kit_setting_update',
			'summary'      => $summary,
			'kit_id'       => $plan['kit_id'],
			'path'         => $plan['path'],
			'prior_value'  => $disp( $plan['prior_value'] ),
			'new_value'    => $disp( $plan['value'] ),
			'created_path' => $plan['created_path'],
			'warnings'     => $gate['warnings'],
		);
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( $fields );
		}
		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => 0,
				'change_type'    => 'kit_setting_update',
				'change_summary' => $req->get_param( 'summary' ) ?: $summary,
				'current_value'  => $payload,
				'proposed_value' => $payload,
				'reasoning'      => (string) $req->get_param( 'reasoning' ),
				'status'         => 'pending',
				'created_by'     => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }
		$fields['pending_id']  = $pending_id;
		$fields['review_url']  = admin_url( 'admin.php?page=cc-assistant-pending' );
		$fields['verify_hint'] = 'On approval the kit CSS cache is flushed and the front page is re-read by page_facts. A global colour is only proven when a page that uses it renders it: confirm with wcag_sweep or render_probe.';
		return self::wrap( $fields );
	}

	/**
	 * v0.69 — queue a plugin_setting_update pending change.
	 *
	 * The write counterpart to get_plugin_settings. Deliberately generic: one
	 * leaf of one option by dot path, for any plugin, rather than a per-plugin
	 * adapter that would need a sibling for every new plugin.
	 */
	public static function handle_draft_plugin_setting( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-setting-writer.php';

		$plan = CC_Assistant_Setting_Writer::build_plan(
			array(
				'option_name'  => $req->get_param( 'option_name' ),
				'path'         => $req->get_param( 'path' ),
				'value'        => $req->get_param( 'value' ),
				'allow_create' => $req->get_param( 'allow_create' ),
			)
		);
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$prior_disp = is_bool( $plan['prior_value'] ) ? ( $plan['prior_value'] ? 'true' : 'false' ) : (string) $plan['prior_value'];
		$new_disp   = is_bool( $plan['value'] ) ? ( $plan['value'] ? 'true' : 'false' ) : (string) $plan['value'];
		$summary    = sprintf(
			'%s%s: "%s" -> "%s"',
			$plan['option_name'],
			$plan['path'] ? ' [' . $plan['path'] . ']' : '',
			'' === $prior_disp ? '(empty)' : $prior_disp,
			'' === $new_disp ? '(empty)' : $new_disp
		);

		$payload = wp_json_encode( $plan );
		$gate    = self::pre_queue_gate( $req, 0, 'plugin_setting_update', $payload );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		if ( $gate['skip_queue'] ) {
			return self::dry_run_response(
				array(
					'change_type' => 'plugin_setting_update',
					'summary'     => $summary,
					'option_name' => $plan['option_name'],
					'path'        => $plan['path'],
					'prior_value' => $prior_disp,
					'new_value'   => $new_disp,
					'created_path'=> $plan['created_path'],
				'validation'  => $plan['validation'],
					'warnings'    => $gate['warnings'],
				)
			);
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => 0,
				'change_type'    => 'plugin_setting_update',
				'change_summary' => $req->get_param( 'summary' ) ?: $summary,
				'current_value'  => $payload,
				'proposed_value' => $payload,
				'reasoning'      => (string) $req->get_param( 'reasoning' ),
				'status'         => 'pending',
				'created_by'     => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap(
			array(
				'pending_id'  => $pending_id,
				'summary'     => $summary,
				'option_name' => $plan['option_name'],
				'path'        => $plan['path'],
				'prior_value' => $prior_disp,
				'new_value'   => $new_disp,
				'created_path'=> $plan['created_path'],
				'validation'  => $plan['validation'],
				'review_url'  => admin_url( 'admin.php?page=cc-assistant-pending' ),
				'warnings'    => $gate['warnings'],
			)
		);
	}

	/**
	 * v0.69 — queue a bulk_term_assign pending change.
	 *
	 * Read-only until approved, like every other write. The plan carries each
	 * target's PRIOR terms so rollback restores the exact previous state, and
	 * title matching is SQL LIKE on post_title only — deliberately narrower
	 * than wp-admin's product search, which also matches descriptions.
	 */
	public static function handle_draft_bulk_terms( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-bulk-terms.php';

		$plan = CC_Assistant_Bulk_Terms::build_plan(
			array(
				'taxonomy'    => $req->get_param( 'taxonomy' ),
				'term_id'     => $req->get_param( 'term_id' ),
				'post_type'   => $req->get_param( 'post_type' ),
				'match_type'  => $req->get_param( 'match_type' ),
				'match_value' => $req->get_param( 'match_value' ),
				'post_ids'    => $req->get_param( 'post_ids' ),
				'source_term' => $req->get_param( 'source_term' ),
				'mode'        => $req->get_param( 'mode' ),
			)
		);
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$summary_line = sprintf(
			'Assign %s "%s" to %d %s (%s)',
			$plan['taxonomy'],
			$plan['term_name'],
			$plan['to_change'],
			$plan['post_type'],
			$plan['match']['label']
		);

		// Nothing to do is a successful, informative no-op — never an empty
		// pending row the human has to triage.
		if ( empty( $plan['targets'] ) ) {
			return self::wrap(
				array(
					'pending_id'    => null,
					'queued'        => false,
					'message'       => 0 === $plan['total_matched']
						? 'No posts matched. Nothing queued.'
						: sprintf( 'All %d matched %s already carry "%s". Nothing to change.', $plan['already_had'], $plan['post_type'], $plan['term_name'] ),
					'total_matched' => $plan['total_matched'],
					'already_had'   => $plan['already_had'],
					'to_change'     => 0,
				)
			);
		}

		$proposed_payload = wp_json_encode( $plan );
		$gate             = self::pre_queue_gate( $req, 0, 'bulk_term_assign', $proposed_payload );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$preview = array();
		foreach ( array_slice( $plan['targets'], 0, 8 ) as $t ) {
			$preview[] = $t['title'];
		}

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response(
				array(
					'change_type'   => 'bulk_term_assign',
					'summary'       => $summary_line,
					'total_matched' => $plan['total_matched'],
					'already_had'   => $plan['already_had'],
					'to_change'     => $plan['to_change'],
					'sample'        => $preview,
					'truncated'     => isset( $plan['truncated'] ) ? $plan['truncated'] : null,
					'warnings'      => $gate['warnings'],
				)
			);
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => 0,
				'change_type'    => 'bulk_term_assign',
				'change_summary' => $req->get_param( 'summary' ) ?: $summary_line,
				// current_value mirrors the plan: apply and revert both read
				// targets[].prior, so one payload is its own inverse.
				'current_value'  => $proposed_payload,
				'proposed_value' => $proposed_payload,
				'reasoning'      => (string) $req->get_param( 'reasoning' ),
				'status'         => 'pending',
				'created_by'     => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap(
			array(
				'pending_id'    => $pending_id,
				'queued'        => true,
				'summary'       => $summary_line,
				'total_matched' => $plan['total_matched'],
				'already_had'   => $plan['already_had'],
				'to_change'     => $plan['to_change'],
				'sample'        => $preview,
				'truncated'     => isset( $plan['truncated'] ) ? $plan['truncated'] : null,
				'review_url'    => admin_url( 'admin.php?page=cc-assistant-pending' ),
				'warnings'      => $gate['warnings'],
			)
		);
	}

	/**
	 * Rank Math's modern Schema Builder stores each schema node in its own
	 * postmeta row keyed `rank_math_schema_<Type>` — a CAPITALISED suffix that
	 * sanitize_key() would destroy, holding a nested PHP-serialized array whose
	 * leaves may be %variable% placeholders resolved at render time. Neither
	 * draft_update_postmeta (lowercases the key, stores a flat string) nor
	 * propose_schema (writes our own JSON-LD block, a second emitter) can edit
	 * it. These two handlers are the case-preserving, array-aware path.
	 *
	 * @return string Regex a Rank Math schema meta key must match.
	 */
	private static function rank_math_schema_key_pattern() {
		return '/^rank_math_schema_[A-Za-z][A-Za-z0-9_]*$/';
	}

	/**
	 * Read every Rank Math schema row on a post. Also reports the legacy
	 * scalar keys (rank_math_rich_snippet + rank_math_snippet_*), because a
	 * site may be on either generation and the fix differs: modern rows go
	 * through draft_update_rank_math_schema, legacy scalars are plain
	 * lowercase keys that draft_update_postmeta already handles.
	 */
	public static function handle_get_rank_math_schema( WP_REST_Request $req ) {
		global $wpdb;
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post %d not found.', $post_id ), array( 'status' => 404 ) );
		}

		// Direct query: get_post_meta() can't do a LIKE on the key, and the
		// suffix is case-sensitive so we filter in PHP rather than trust the
		// collation.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_key ASC",
				$post_id,
				$wpdb->esc_like( 'rank_math_schema_' ) . '%'
			),
			ARRAY_A
		);

		$schemas = array();
		foreach ( (array) $rows as $row ) {
			$key = (string) $row['meta_key'];
			if ( ! preg_match( self::rank_math_schema_key_pattern(), $key ) ) {
				continue;
			}
			$value = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $value ) ) {
				$schemas[] = array(
					'meta_key' => $key,
					'type'     => null,
					'error'    => 'Row is not an array — Rank Math Schema Builder rows always are. Left alone.',
				);
				continue;
			}
			$paths = array();
			self::flatten_schema_paths( $value, '', $paths );
			$schemas[] = array(
				'meta_key'    => $key,
				'type'        => isset( $value['@type'] ) && is_scalar( $value['@type'] ) ? (string) $value['@type'] : null,
				'paths'       => $paths,
				'placeholders' => array_keys( array_filter( $paths, static function ( $v ) {
					return is_string( $v ) && preg_match( '/%[a-z0-9_():\-]+%/i', $v );
				} ) ),
			);
		}

		// Legacy generation: one scalar master switch plus flat snippet keys.
		$legacy      = array();
		$legacy_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_key ASC",
				$post_id,
				$wpdb->esc_like( 'rank_math_snippet_' ) . '%'
			),
			ARRAY_A
		);
		foreach ( (array) $legacy_rows as $row ) {
			$legacy[ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}

		$flag = get_post_meta( $post_id, 'rank_math_rich_snippet', true );

		return self::wrap( array(
			'post_id'            => $post_id,
			'post_title'         => get_the_title( $post_id ),
			'permalink'          => get_permalink( $post_id ),
			'rich_snippet_flag'  => '' === $flag ? null : $flag,
			'generation'         => ! empty( $schemas ) ? 'schema_builder' : ( ! empty( $legacy ) ? 'legacy_snippet' : 'none' ),
			'schemas'            => $schemas,
			'legacy_snippet_meta' => $legacy,
			'note'               => 'Edit schema_builder rows with draft_update_rank_math_schema (dot paths). Edit legacy_snippet_meta keys with draft_update_postmeta — they are already lowercase. rich_snippet_flag set to "off" suppresses Rank Math\'s ENTIRE @graph, not just the Product node.',
		) );
	}

	/**
	 * Flatten a nested schema array into dot paths so the caller can see
	 * exactly which path to target. List indexes become numeric segments.
	 */
	private static function flatten_schema_paths( $node, $prefix, array &$out ) {
		foreach ( (array) $node as $k => $v ) {
			$path = '' === $prefix ? (string) $k : $prefix . '.' . $k;
			if ( is_array( $v ) ) {
				if ( empty( $v ) ) {
					$out[ $path ] = array();
					continue;
				}
				self::flatten_schema_paths( $v, $path, $out );
			} else {
				$out[ $path ] = $v;
			}
		}
	}

	/**
	 * Queue a merge into one Rank Math Schema Builder row. Only the dot paths
	 * supplied are touched; every other leaf — including %variable%
	 * placeholders Rank Math resolves at render time — is carried through
	 * byte-identical. Rows are never created here: a missing node needs a
	 * whole Schema Builder template (shortcode id, isPrimary, metadata), which
	 * is a different job.
	 */
	public static function handle_draft_rank_math_schema( WP_REST_Request $req ) {
		global $wpdb;
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post %d not found.', $post_id ), array( 'status' => 404 ) );
		}

		$set = $req->get_param( 'set' );
		if ( is_string( $set ) ) {
			$set = json_decode( $set, true );
		}
		if ( ! is_array( $set ) || empty( $set ) ) {
			return new WP_Error( 'nothing_to_change', 'Provide "set" as an object of dot-path => value, e.g. {"offers.price":"38999"}.', array( 'status' => 400 ) );
		}

		// Resolve which row to edit. Case matters, so read keys straight from
		// the DB rather than round-tripping through sanitize_key().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_key ASC",
				$post_id,
				$wpdb->esc_like( 'rank_math_schema_' ) . '%'
			),
			ARRAY_A
		);
		$available = array();
		foreach ( (array) $rows as $row ) {
			if ( preg_match( self::rank_math_schema_key_pattern(), (string) $row['meta_key'] ) ) {
				$available[] = (string) $row['meta_key'];
			}
		}
		if ( empty( $available ) ) {
			return new WP_Error(
				'no_schema_row',
				sprintf( 'Post %d has no rank_math_schema_* row. Run get_rank_math_schema first — this site may be on the legacy rank_math_snippet_* generation, which draft_update_postmeta already handles.', $post_id ),
				array( 'status' => 404 )
			);
		}

		$meta_key = (string) $req->get_param( 'meta_key' );
		if ( '' === $meta_key ) {
			if ( count( $available ) > 1 ) {
				return new WP_Error( 'ambiguous_schema_row', sprintf( 'Post %d has %d schema rows (%s). Pass meta_key to pick one.', $post_id, count( $available ), implode( ', ', $available ) ), array( 'status' => 400 ) );
			}
			$meta_key = $available[0];
		} elseif ( ! in_array( $meta_key, $available, true ) ) {
			return new WP_Error( 'schema_row_not_found', sprintf( 'Post %d has no meta key "%s". Available: %s.', $post_id, $meta_key, implode( ', ', $available ) ), array( 'status' => 404 ) );
		}

		$original = maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) );
		if ( ! is_array( $original ) ) {
			return new WP_Error( 'schema_not_array', sprintf( '"%s" on post %d is not an array. Refusing to rewrite it.', $meta_key, $post_id ), array( 'status' => 422 ) );
		}

		$merged      = $original;
		$before      = array();
		$after       = array();
		$errors      = array();
		$soft_notes  = array();
		foreach ( $set as $path => $value ) {
			$path = (string) $path;
			$err  = self::validate_schema_path( $path, $merged );
			if ( $err ) {
				$errors[] = $err;
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				$errors[] = sprintf( '"%s": value must be a scalar. Set nested leaves one dot path at a time.', $path );
				continue;
			}
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}
			$value = (string) $value;
			$verr  = self::validate_schema_value( $path, $value );
			if ( $verr ) {
				$errors[] = $verr;
				continue;
			}
			$old = self::get_schema_path( $merged, $path );
			if ( is_string( $old ) && preg_match( '/%[a-z0-9_():\-]+%/i', $old ) ) {
				$soft_notes[] = sprintf( 'Overwriting Rank Math variable "%s" at %s with a literal — it will stop resolving per-post.', $old, $path );
			}
			if ( null !== $old && (string) $old === $value ) {
				continue;
			}
			$before[ $path ] = null === $old ? '' : $old;
			$after[ $path ]  = $value;
			self::set_schema_path( $merged, $path, $value );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_schema_path', implode( ' ', $errors ), array( 'status' => 422 ) );
		}
		if ( empty( $after ) ) {
			return new WP_Error( 'no_change', 'Every supplied path already holds that value. Nothing queued.', array( 'status' => 400 ) );
		}

		// The legacy master switch overrides everything below it. With it set
		// to "off" Rank Math emits NO graph at all, so a perfectly correct
		// merge would render nothing and read as "the fix didn't work". Warn
		// rather than block: clearing the flag is a separate, deliberate call.
		if ( 'off' === (string) get_post_meta( $post_id, 'rank_math_rich_snippet', true ) ) {
			$soft_notes[] = 'rank_math_rich_snippet is "off" on this post, which suppresses Rank Math\'s ENTIRE JSON-LD graph. This merge will be correct in the database but render nothing until that flag is set back to its type (e.g. "product").';
		}

		// Rank Math's own UI writes this row without necessarily bumping
		// post_modified, so the plugin's usual race guard can't see that kind
		// of drift. Stamp the base we merged onto; apply refuses if the live
		// row has moved since, rather than clobbering a human's schema edit.
		$base_hash = md5( (string) maybe_serialize( $original ) );

		$current = array(
			'meta_key'  => $meta_key,
			'type'      => isset( $original['@type'] ) && is_scalar( $original['@type'] ) ? (string) $original['@type'] : '',
			'paths'     => $before,
			'full'      => $original,
			'base_hash' => $base_hash,
		);
		$proposed = array(
			'meta_key'  => $meta_key,
			'type'      => $current['type'],
			'paths'     => $after,
			'full'      => $merged,
			'base_hash' => $base_hash,
		);

		$proposed_payload = wp_json_encode( $proposed );
		$gate             = self::pre_queue_gate( $req, $post_id, 'rank_math_schema_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }

		$summary = $req->get_param( 'summary' );
		if ( ! $summary ) {
			$summary = sprintf( 'Set %s on %s schema', implode( ', ', array_keys( $after ) ), $current['type'] ?: $meta_key );
		}

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'rank_math_schema_update',
				'post_id'     => $post_id,
				'meta_key'    => $meta_key,
				'paths'       => $after,
				'notes'       => $soft_notes,
				'warnings'    => $gate['warnings'],
			) );
		}

		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw ) ? self::sanitize_success_metrics( $success_raw ) : null;

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => $post_id,
				'change_type'     => 'rank_math_schema_update',
				'change_summary'  => $summary,
				'current_value'   => wp_json_encode( $current ),
				'proposed_value'  => $proposed_payload,
				'reasoning'       => (string) $req->get_param( 'reasoning' ),
				'success_metrics' => $success,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'post_id'    => $post_id,
			'meta_key'   => $meta_key,
			'paths'      => $after,
			'permalink'  => get_permalink( $post_id ),
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'notes'      => $soft_notes,
			'warnings'   => $gate['warnings'],
		) );
	}

	/**
	 * A path may add a leaf to a branch that already exists, but may not
	 * invent a branch — that would mean authoring schema structure Rank Math
	 * never rendered, which belongs in the Schema Builder, not a field merge.
	 *
	 * @return string Error message, or '' when the path is usable.
	 */
	private static function validate_schema_path( $path, array $tree ) {
		if ( '' === $path ) {
			return 'Empty path.';
		}
		$segments = explode( '.', $path );
		foreach ( $segments as $seg ) {
			if ( ! preg_match( '/^@?[A-Za-z0-9_\-]+$/', $seg ) ) {
				return sprintf( '"%s": segment "%s" is not a valid schema key.', $path, $seg );
			}
		}
		$leaf   = (string) array_pop( $segments );
		$node   = $tree;
		$walked = array();
		foreach ( $segments as $seg ) {
			$walked[] = $seg;
			if ( ! is_array( $node ) || ! array_key_exists( $seg, $node ) ) {
				return sprintf( '"%s": parent path "%s" does not exist on this schema. Add leaves to existing branches only.', $path, implode( '.', $walked ) );
			}
			$node = $node[ $seg ];
			if ( ! is_array( $node ) ) {
				return sprintf( '"%s": parent path "%s" holds a scalar, not a branch.', $path, implode( '.', $walked ) );
			}
		}
		// The TARGET must be a leaf, not a branch. Writing a scalar over a
		// branch would delete the whole node — and because get_schema_path()
		// reports a branch as "not set", the reviewer's diff would render that
		// destruction as an innocent blank-to-value change. "offers" instead of
		// "offers.price" is one keystroke away, so this is a hard refusal.
		if ( is_array( $node ) && array_key_exists( $leaf, $node ) && is_array( $node[ $leaf ] ) ) {
			$children = array_keys( $node[ $leaf ] );
			return sprintf(
				'"%s": that path is a branch, not a leaf — writing to it would delete the whole node. Target one of its leaves instead (%s).',
				$path,
				$children ? $path . '.' . implode( ', ' . $path . '.', array_slice( $children, 0, 5 ) ) : 'it is currently empty'
			);
		}
		return '';
	}

	/**
	 * Field-shape guards for the leaves that actually break rich results when
	 * malformed. Google rejects a price carrying a currency symbol or comma.
	 *
	 * @return string Error message, or '' when the value is usable.
	 */
	private static function validate_schema_value( $path, $value ) {
		$parts = explode( '.', $path );
		$leaf  = strtolower( (string) end( $parts ) );
		if ( preg_match( '/%[a-z0-9_():\-]+%/i', $value ) ) {
			return sprintf( '"%s": refusing to write a %%variable%% placeholder. Only Rank Math\'s own UI should author those.', $path );
		}
		if ( preg_match( '/<[a-z\/][^>]*>/i', $value ) ) {
			return sprintf( '"%s": value contains HTML. Schema values are plain text.', $path );
		}
		if ( 'price' === $leaf || 'lowprice' === $leaf || 'highprice' === $leaf ) {
			if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $value ) ) {
				return sprintf( '"%s": price must be digits with an optional 2-decimal part — no currency symbol, no thousands comma. Got "%s".', $path, $value );
			}
		}
		if ( 'pricecurrency' === $leaf && ! preg_match( '/^[A-Z]{3}$/', $value ) ) {
			return sprintf( '"%s": priceCurrency must be a 3-letter ISO code such as CAD. Got "%s".', $path, $value );
		}
		if ( 'availability' === $leaf ) {
			$allowed = array( 'InStock', 'OutOfStock', 'PreOrder', 'BackOrder', 'Discontinued', 'InStoreOnly', 'LimitedAvailability', 'OnlineOnly', 'SoldOut', 'PreSale' );
			$bare    = str_replace( array( 'https://schema.org/', 'http://schema.org/' ), '', $value );
			if ( ! in_array( $bare, $allowed, true ) ) {
				return sprintf( '"%s": availability must be one of %s (optionally prefixed https://schema.org/). Got "%s".', $path, implode( ', ', $allowed ), $value );
			}
		}
		if ( ( 'pricevaliduntil' === $leaf || 'validthrough' === $leaf ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return sprintf( '"%s": date must be ISO 8601 YYYY-MM-DD. Got "%s".', $path, $value );
		}
		return '';
	}

	private static function get_schema_path( array $tree, $path ) {
		$node = $tree;
		foreach ( explode( '.', $path ) as $seg ) {
			if ( ! is_array( $node ) || ! array_key_exists( $seg, $node ) ) {
				return null;
			}
			$node = $node[ $seg ];
		}
		return is_array( $node ) ? null : $node;
	}

	private static function set_schema_path( array &$tree, $path, $value ) {
		$segments = explode( '.', $path );
		$node     = &$tree;
		foreach ( $segments as $i => $seg ) {
			if ( $i === count( $segments ) - 1 ) {
				$node[ $seg ] = $value;
				break;
			}
			$node = &$node[ $seg ];
		}
		unset( $node );
	}

	/**
	 * Bulk-delete one or more category terms. Refuses when a target term has
	 * posts attached (count > 0) unless force=true, and always refuses to
	 * delete the WP default category. Idempotent: non-existent term IDs
	 * return ok:false with error:not_found without aborting the batch.
	 */
	public static function handle_category_delete( WP_REST_Request $req ) {
		$raw = $req->get_param( 'term_ids' );
		if ( ! is_array( $raw ) ) {
			$single = (int) $req->get_param( 'term_id' );
			$raw    = $single > 0 ? array( $single ) : array();
		}
		$term_ids = array();
		foreach ( $raw as $v ) {
			$tid = (int) $v;
			if ( $tid > 0 && ! in_array( $tid, $term_ids, true ) ) {
				$term_ids[] = $tid;
			}
		}
		if ( empty( $term_ids ) ) {
			return new WP_Error( 'term_ids_required', 'term_ids is required (array of positive integers).', array( 'status' => 400 ) );
		}
		$force       = (bool) $req->get_param( 'force' );
		$default_cat = (int) get_option( 'default_category' );

		$results    = array();
		$ok_count   = 0;
		$fail_count = 0;
		foreach ( $term_ids as $tid ) {
			$term = get_term( $tid, 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				$results[]  = array( 'term_id' => $tid, 'ok' => false, 'error' => 'not_found' );
				$fail_count++;
				continue;
			}
			if ( $default_cat > 0 && $tid === $default_cat ) {
				$results[]  = array(
					'term_id' => $tid,
					'ok'      => false,
					'error'   => 'is_default_category',
					'name'    => $term->name,
					'slug'    => $term->slug,
				);
				$fail_count++;
				continue;
			}
			$count = (int) $term->count;
			if ( $count > 0 && ! $force ) {
				$results[]  = array(
					'term_id' => $tid,
					'ok'      => false,
					'error'   => 'has_posts',
					'count'   => $count,
					'name'    => $term->name,
					'slug'    => $term->slug,
				);
				$fail_count++;
				continue;
			}
			$del = wp_delete_term( $tid, 'category' );
			if ( is_wp_error( $del ) || false === $del || 0 === $del ) {
				$results[]  = array(
					'term_id' => $tid,
					'ok'      => false,
					'error'   => is_wp_error( $del ) ? $del->get_error_code() : 'delete_failed',
					'name'    => $term->name,
					'slug'    => $term->slug,
				);
				$fail_count++;
				continue;
			}
			$results[] = array(
				'term_id' => $tid,
				'ok'      => true,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'count'   => $count,
				'forced'  => $count > 0 && $force,
			);
			$ok_count++;
		}
		return self::wrap( array(
			'count'   => count( $results ),
			'ok'      => $ok_count,
			'failed'  => $fail_count,
			'results' => $results,
		) );
	}

	/**
	 * Merge two lint reports into one envelope. Title-side checks get a
	 * "title_" prefix so they don't collide with body-side checks (both
	 * have em_dashes, ai_tells, style_guide entries).
	 */
	private static function merge_lint_reports( $a, $b ) {
		// v0.51.3: lint_html_block() returns a RAW checks array with no
		// 'checks' wrapper, while lint_seo_meta() returns a wrapped report.
		// The old `$a['checks'] ?? array()` silently dropped every body-side
		// check on the create-post path — body hard violations (em dashes,
		// AI-tells, wall_of_text, unverifiable quotes) never blocked a new
		// draft, and the reported pass_count was title checks only. Caught
		// by the v0.51.2 quote-lint smoke test. Accept both shapes.
		$unwrap = static function ( $report ) {
			if ( isset( $report['checks'] ) && is_array( $report['checks'] ) ) {
				return $report['checks'];
			}
			return is_array( $report ) ? $report : array();
		};
		$checks = array();
		foreach ( $unwrap( $a ) as $name => $c ) {
			if ( is_array( $c ) && array_key_exists( 'pass', $c ) ) {
				$checks[ $name ] = $c;
			}
		}
		foreach ( $unwrap( $b ) as $name => $c ) {
			if ( is_array( $c ) && array_key_exists( 'pass', $c ) ) {
				$checks[ 'title_' . $name ] = $c;
			}
		}
		$pass = 0;
		$fail = 0;
		$hard = array();
		foreach ( $checks as $name => $c ) {
			if ( ! empty( $c['pass'] ) ) {
				$pass++;
			} else {
				$fail++;
				$base = 0 === strpos( $name, 'title_' ) ? substr( $name, 6 ) : $name;
				// v0.51.3: hard set now mirrors the body-rewrite path instead
				// of the old 3-name subset, so a new post is held to the same
				// bar as an edit to an existing one.
				if ( in_array( $base, array( 'em_dashes', 'ai_tells', 'style_guide', 'placeholders', 'wall_of_text', 'address_consistency', 'hospital_comparison', 'quote_source_link' ), true ) ) {
					$hard[] = $name;
				}
			}
		}
		return array(
			'pass'            => 0 === $fail,
			'pass_count'      => $pass,
			'fail_count'      => $fail,
			'total'           => count( $checks ),
			'hard_violations' => $hard,
			'checks'          => $checks,
			'generated_at'    => current_time( 'mysql' ),
		);
	}

	public static function handle_apply_pending( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-apply.php';
		$id     = (int) $req->get_param( 'id' );
		$result = CC_Assistant_Apply::apply_pending( $id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_reject_pending( WP_REST_Request $req ) {
		$id   = (int) $req->get_param( 'id' );
		$note = (string) $req->get_param( 'note' );

		// Capture the pending row before flipping its status so we can detect
		// publish_draft rejections — those leave an orphan WP draft post that
		// nothing else will ever clean up. Trash it on reject so the rejection
		// is actually idempotent.
		$pending = CC_Assistant_Pending_Changes::get( $id );

		CC_Assistant_Pending_Changes::reject( $id, get_current_user_id(), $note );

		$trashed_post_id   = null;
		$snapshots_deleted = 0;
		if ( $pending && 'publish_draft' === $pending->change_type && $pending->post_id ) {
			$post = get_post( (int) $pending->post_id );
			// Only trash if the draft was created by this rejection's pending row
			// and is still unpublished. Don't trash a manually-published post or
			// a draft someone else has been editing.
			if ( $post && 'draft' === $post->post_status ) {
				$trashed = wp_trash_post( (int) $pending->post_id );
				if ( $trashed ) {
					$trashed_post_id = (int) $pending->post_id;

					// Clean up cc_snapshots rows captured against this now-trashed
					// draft. Without this they sit in the table forever pointing
					// at a non-existent post — wasted storage + orphan UI rows.
					global $wpdb;
					$snapshots_deleted = (int) $wpdb->delete(
						$wpdb->prefix . 'cc_snapshots',
						array( 'post_id' => (int) $pending->post_id )
					);
				}
			}
		}

		return self::wrap( array(
			'rejected'          => $id,
			'trashed_post_id'   => $trashed_post_id,
			'snapshots_deleted' => $snapshots_deleted,
		) );
	}

	public static function handle_link_audit( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-link-audit.php';
		$id      = (int) $req->get_param( 'id' );
		$post    = self::check_post_for_draft( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$check_external = filter_var( $req->get_param( 'check_external' ), FILTER_VALIDATE_BOOLEAN );
		$result         = CC_Assistant_Link_Audit::audit_post( $id, $check_external );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_pre_publish( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$id   = (int) $req->get_param( 'id' );
		$post = self::check_post_for_draft( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$result = CC_Assistant_Pre_Publish::check_post( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_get_site_memory() {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		return self::wrap( CC_Assistant_Site_Memory::full_memory() );
	}

	public static function handle_update_site_notes( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		$mode    = $req->get_param( 'mode' );
		$notes   = (string) $req->get_param( 'notes' );
		$section = $req->get_param( 'section' );
		if ( '' === $notes ) {
			return new WP_Error( 'notes_required', 'notes parameter is required.', array( 'status' => 400 ) );
		}

		// v0.81 open-loop guard. A Sessions note is the "I am stopping" signal.
		// If working_state is still active with open loops, stopping silently
		// abandons them — the next chat inherits a half-finished job with no
		// record of why. Refuse once; the caller either closes the loops
		// (update_working_state remove_open_loops / status) or acknowledges
		// them by name and the note goes through with the loops appended.
		$section_norm = is_string( $section ) ? strtolower( trim( $section ) ) : '';
		if ( 'replace' !== $mode && ( '' === $section_norm || 'sessions' === $section_norm ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-working-state.php';
			$ws    = CC_Assistant_Working_State::get();
			$loops = isset( $ws['open_loops'] ) && is_array( $ws['open_loops'] ) ? array_values( array_filter( array_map( 'strval', $ws['open_loops'] ) ) ) : array();
			$active = isset( $ws['status'] ) && 'active' === strtolower( (string) $ws['status'] );
			if ( $active && ! empty( $loops ) ) {
				$ack = filter_var( $req->get_param( 'acknowledge_open_loops' ), FILTER_VALIDATE_BOOLEAN );
				if ( ! $ack ) {
					return new WP_Error(
						'open_loops_unacknowledged',
						sprintf(
							'REFUSED: working_state is active with %d open loop(s). Either close them (update_working_state with remove_open_loops, or status="done"/"paused") or pass acknowledge_open_loops=true to log the note anyway; the loops will be appended to the note so the next chat sees them.',
							count( $loops )
						),
						array( 'status' => 409, 'open_loops' => $loops, 'active_task' => isset( $ws['active_task'] ) ? $ws['active_task'] : '' )
					);
				}
				$notes .= ' | OPEN LOOPS left: ' . implode( '; ', array_slice( $loops, 0, 8 ) );
			}
		}
		// Append by default. Replace must be explicit so a missing mode never wipes the log.
		if ( 'replace' === $mode ) {
			CC_Assistant_Site_Memory::set_notes( $notes );
			$applied_mode    = 'replace';
			$applied_section = null;
		} else {
			$applied_section = CC_Assistant_Site_Memory::normalize_section( $section );
			CC_Assistant_Site_Memory::append_note( $notes, $applied_section );
			$applied_mode = 'append';
		}
		// Don't return the full notes blob in the response — on big logs this
		// blew the MCP transport past 65k chars. Return only what the caller
		// actually needs: a confirmation, a byte count, and the last ~500 chars
		// so the operator can verify the append landed. Mirrors notes_tail in
		// whoami's session_recap.
		$full         = (string) CC_Assistant_Site_Memory::get_notes();
		$bytes_after  = strlen( $full );
		$tail_length  = 500;
		$notes_tail   = $bytes_after > $tail_length ? mb_substr( $full, -1 * $tail_length ) : $full;
		return self::wrap( array(
			'updated'      => true,
			'mode'         => $applied_mode,
			'section'      => $applied_section,
			'bytes_after'  => $bytes_after,
			'notes_tail'   => $notes_tail,
			'tail_length'  => $tail_length,
		) );
	}

	/**
	 * Translate a plain robots directive list into the shape the active SEO
	 * plugin actually stores.
	 *
	 * Added v0.71.4 after a silent failure on a live site: seven posts were
	 * "noindexed" by writing a pre-serialized array string to rank_math_robots
	 * via draft_update_postmeta(force_raw). Every apply reported success and
	 * every page kept emitting "follow, index". Cause: WordPress
	 * maybe_serialize() serializes a SECOND time when handed a string that is
	 * already serialized, so Rank Math read back a string where it expects an
	 * array and ignored the override. draft_update_postmeta types `value` as a
	 * string, so it can never write array meta -- which is exactly why raw
	 * writes to SEO keys are blocked by default.
	 *
	 * @param string $directives Comma list, e.g. "noindex" or "noindex,nofollow".
	 * @param string $plugin     Detected SEO plugin slug.
	 * @return array|string|WP_Error Storage-ready value, or WP_Error.
	 */
	private static function robots_value_for_plugin( $directives, $plugin ) {
		$parts = array_filter( array_map( 'trim', explode( ',', strtolower( $directives ) ) ) );
		$known = array( 'index', 'noindex', 'follow', 'nofollow' );
		foreach ( $parts as $p ) {
			if ( ! in_array( $p, $known, true ) ) {
				return new WP_Error(
					'robots_directive_unknown',
					sprintf( 'Unknown robots directive "%s". Supported: index, noindex, follow, nofollow.', $p ),
					array( 'status' => 400 )
				);
			}
		}
		if ( empty( $parts ) ) {
			return new WP_Error(
				'robots_directive_required',
				'Pass a directive list, e.g. "noindex", "noindex,follow", or "index" to restore the site default.',
				array( 'status' => 400 )
			);
		}

		$noindex  = in_array( 'noindex', $parts, true );
		$nofollow = in_array( 'nofollow', $parts, true );

		switch ( $plugin ) {
			case 'rank-math':
				// Array of directive strings. Passing "index" with nothing else
				// restrictive means "stop overriding", which is an empty value:
				// apply_post_meta deletes the row and the site default returns.
				if ( ! $noindex && ! $nofollow ) {
					return '';
				}
				$out = array();
				$out[] = $noindex ? 'noindex' : 'index';
				$out[] = $nofollow ? 'nofollow' : 'follow';
				return $out;

			case 'yoast':
				// '1' = noindex, '2' = index, '' = default. The resolved key is
				// the noindex flag only; nofollow is a SEPARATE Yoast key, so
				// refuse rather than silently dropping half the instruction.
				if ( $nofollow ) {
					return new WP_Error(
						'robots_nofollow_unsupported_yoast',
						'Yoast stores nofollow under a separate key (_yoast_wpseo_meta-robots-nofollow). Set noindex here, and queue nofollow as its own change.',
						array( 'status' => 400 )
					);
				}
				return $noindex ? '1' : '2';

			case 'seopress':
				if ( $nofollow ) {
					return new WP_Error(
						'robots_nofollow_unsupported_seopress',
						'SEOPress stores nofollow under a separate key (_seopress_robots_follow). Set noindex here, and queue nofollow as its own change.',
						array( 'status' => 400 )
					);
				}
				// SEOPress uses 'yes' to MEAN noindex; empty restores default.
				return $noindex ? 'yes' : '';

			default:
				return new WP_Error(
					'robots_unsupported_plugin',
					sprintf( 'Robots meta is not writable through postmeta on this stack (SEO plugin: %s). AIOSEO keeps it in custom tables, and with no SEO plugin there is nothing to write.', $plugin ?: 'none detected' ),
					array( 'status' => 400 )
				);
		}
	}

	public static function handle_draft_seo_meta( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';

		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$logical_key = sanitize_key( $req->get_param( 'logical_key' ) );
		$value       = $req->get_param( 'value' );
		if ( empty( $logical_key ) ) {
			return new WP_Error( 'logical_key_required', 'logical_key is required (e.g. "description", "title", "focus_keyword").', array( 'status' => 400 ) );
		}

		$resolved = CC_Assistant_Site_Memory::resolve_seo_meta_key( $logical_key );
		if ( null === $resolved ) {
			$seo = CC_Assistant_Site_Memory::detect_seo();
			return new WP_Error(
				'seo_key_unsupported',
				sprintf(
					'No SEO meta key for "%s" on this site (SEO plugin: %s). Supported logical keys: %s.',
					$logical_key,
					$seo['plugin_name'] ?: 'none detected',
					implode( ', ', array_keys( $seo['meta_keys'] ) )
				),
				array( 'status' => 400 )
			);
		}

		$current     = get_post_meta( $post_id, $resolved, true );
		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? self::sanitize_success_metrics( $success_raw )
			: null;

		// v0.71.4: robots is a DIRECTIVE, not prose, and every SEO plugin stores
		// it in a different shape (Rank Math: array; Yoast: '1'/'2' string flag;
		// SEOPress: 'yes' meaning noindex). Translate the caller's plain
		// directive list into whatever the active plugin actually reads, and
		// skip the prose lint, which is meaningless for "noindex,follow".
		if ( 'robots' === $logical_key ) {
			$seo_now   = CC_Assistant_Site_Memory::detect_seo();
			$converted = self::robots_value_for_plugin( (string) $value, (string) $seo_now['plugin'] );
			if ( is_wp_error( $converted ) ) {
				return $converted;
			}
			$value = $converted;
			$lint  = array( 'pass' => true, 'pass_count' => 0, 'fail_count' => 0, 'checks' => array(), 'hard_violations' => array() );
		} else {
			// Lint the proposed meta value at queue time. Hard violations (em dashes,
			// AI-tells, banned phrases) refuse unless override_lint=true. Length
			// windows + suspicious-char + keyword-coverage are warnings only.
			require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
			$target_query = is_array( $success ) && ! empty( $success['target_query'] ) ? (string) $success['target_query'] : '';
			$lint         = CC_Assistant_Pre_Publish::lint_seo_meta( $logical_key, (string) $value, $target_query );
		}
		$override = filter_var( $req->get_param( 'override_lint' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! empty( $lint['hard_violations'] ) && ! $override ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf(
					'Proposed %s fails: %s. Fix and resubmit, or pass override_lint=true to queue anyway and let the reviewer decide.',
					$logical_key,
					implode( ', ', $lint['hard_violations'] )
				),
				array( 'status' => 422, 'lint' => $lint )
			);
		}

		$proposed_payload = wp_json_encode( array( 'key' => $resolved, 'value' => $value ) );
		$gate             = self::pre_queue_gate( $req, $post_id, 'postmeta_update', $proposed_payload );
		if ( is_wp_error( $gate ) ) { return $gate; }

		$lint_summary = array(
			'pass'       => $lint['pass'],
			'pass_count' => $lint['pass_count'],
			'fail_count' => $lint['fail_count'],
			'failed'     => self::lint_failed_names( $lint ),
		);

		if ( $gate['skip_queue'] ) {
			return self::dry_run_response( array(
				'change_type' => 'postmeta_update',
				'resolved_to' => $resolved,
				'lint'        => $lint_summary,
				'warnings'    => $gate['warnings'],
			) );
		}

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'         => $post_id,
				'change_type'     => 'postmeta_update',
				'change_summary'  => $req->get_param( 'summary' ) ?: sprintf( 'Update SEO %s', $logical_key ),
				'current_value'   => wp_json_encode( array( 'key' => $resolved, 'value' => $current ) ),
				'proposed_value'  => $proposed_payload,
				'reasoning'       => (string) $req->get_param( 'reasoning' ),
				'lint_report'     => $lint,
				'success_metrics' => $success,
				'status'          => 'pending',
				'created_by'      => 'claude',
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		$out = array(
			'pending_id'  => $pending_id,
			'resolved_to' => $resolved,
			'review_url'  => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'lint'        => $lint_summary,
			'warnings'    => $gate['warnings'],
		);
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
		return self::wrap( $out );
	}

	public static function handle_get_style_guide() {
		$content = get_option( 'cc_assistant_style_guide', '' );
		if ( empty( $content ) ) {
			$content = self::default_style_guide();
		}
		return self::wrap(
			array(
				'content'   => $content,
				'has_custom' => '' !== get_option( 'cc_assistant_style_guide', '' ),
			)
		);
	}

	private static function default_style_guide() {
		return "# Default style guide\n\n" .
			"## Voice\n" .
			"- Natural, human, conversational. Not robotic.\n" .
			"- Short paragraphs (2 to 3 sentences max).\n" .
			"- Active voice over passive.\n\n" .
			"## Banned\n" .
			"- Em dashes (use periods, commas, parentheses)\n" .
			"- Phrases: \"in today's fast-paced world\", \"it is important to note\", \"in conclusion\", \"unleash\", \"leverage\", \"delve\", \"tapestry\", \"dive into\", \"navigate\", \"unlock\", \"game-changer\".\n\n" .
			"## EEAT\n" .
			"- Quote real, named professionals with sources.\n" .
			"- First-hand experience markers when realistic.\n" .
			"- Cite only .gov, .edu, or domains in the trusted authority list. Never cite competitors.\n" .
			"- Visible last-updated date and author byline.\n\n" .
			"## AI/LLM friendly\n" .
			"- TL;DR or key takeaways block at the top of long posts.\n" .
			"- Definition sentence near the top (\"X is Y that does Z\").\n" .
			"- Question-style H2s where natural.\n";
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

	public static function handle_gsc_status() {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		return self::wrap( CC_Assistant_GSC::status() );
	}

	public static function handle_gsc_opportunities( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected. Visit Settings > Search Console.', array( 'status' => 400 ) );
		}
		$args = array(
			'min_position'    => (float) $req->get_param( 'min_position' ),
			'max_position'    => (float) $req->get_param( 'max_position' ),
			'min_impressions' => (int) $req->get_param( 'min_impressions' ),
			'limit'           => (int) $req->get_param( 'limit' ),
			'days'            => (int) $req->get_param( 'days' ),
		);
		$rows = CC_Assistant_GSC::opportunities( $args );
		return self::wrap(
			array(
				'count'           => count( $rows ),
				'filters'         => $args,
				'last_sync_at'    => CC_Assistant_GSC::status()['last_sync_at'],
				'recent_filtered' => (int) CC_Assistant_GSC::$last_recent_filtered,
				'live_check_note' => CC_Assistant_GSC::live_check_note(),
				'rows'            => $rows,
			)
		);
	}

	public static function handle_gsc_low_ctr( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$args = array(
			'min_impressions' => (int) $req->get_param( 'min_impressions' ),
			'max_ctr'         => (float) $req->get_param( 'max_ctr' ),
			'limit'           => (int) $req->get_param( 'limit' ),
			'days'            => (int) $req->get_param( 'days' ),
		);
		$rows = CC_Assistant_GSC::low_ctr( $args );
		return self::wrap(
			array(
				'count'           => count( $rows ),
				'filters'         => $args,
				'recent_filtered' => (int) CC_Assistant_GSC::$last_recent_filtered,
				'live_check_note' => CC_Assistant_GSC::live_check_note(),
				'rows'            => $rows,
			)
		);
	}

	public static function handle_gsc_missing_mentions( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$args = array(
			'min_impressions' => (int) $req->get_param( 'min_impressions' ),
			'limit'           => (int) $req->get_param( 'limit' ),
			'days'            => (int) $req->get_param( 'days' ),
		);
		$rows = CC_Assistant_GSC::missing_mentions( $args );
		return self::wrap(
			array(
				'count'   => count( $rows ),
				'filters' => $args,
				'rows'    => $rows,
			)
		);
	}

	public static function handle_gsc_page_queries( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$args = array(
			'post_id' => (int) $req->get_param( 'post_id' ),
			'page'    => (string) $req->get_param( 'page' ),
			'limit'   => (int) $req->get_param( 'limit' ),
			'days'    => (int) $req->get_param( 'days' ),
		);
		$result = CC_Assistant_GSC::page_queries( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_gsc_trends( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$window = (int) $req->get_param( 'window_days' );
		$lens   = sanitize_key( $req->get_param( 'lens' ) );
		$limit  = (int) $req->get_param( 'limit' );

		if ( 'all' === $lens || '' === $lens ) {
			return self::wrap( CC_Assistant_GSC::trends_summary( $window ) );
		}

		$args = array( 'window_days' => $window, 'limit' => $limit );
		switch ( $lens ) {
			case 'decay':
				$rows = CC_Assistant_GSC::decayed_pages( $args );
				break;
			case 'rise':
			case 'rising':
				$rows = CC_Assistant_GSC::rising_pages( $args );
				break;
			case 'new_striking':
			case 'striking':
				$rows = CC_Assistant_GSC::new_striking_distance( $args );
				break;
			case 'lost':
				$rows = CC_Assistant_GSC::lost_queries( $args );
				break;
			default:
				return new WP_Error( 'invalid_lens', 'lens must be one of: all, decay, rise, new_striking, lost.', array( 'status' => 400 ) );
		}
		return self::wrap(
			array(
				'lens'        => $lens,
				'window_days' => $window,
				'count'       => count( $rows ),
				'rows'        => $rows,
			)
		);
	}

	public static function handle_list_edits( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		$limit = (int) $req->get_param( 'limit' );
		$rows  = CC_Assistant_Edit_Outcomes::recent_with_status( $limit );
		return self::wrap(
			array(
				'count' => count( $rows ),
				'edits' => $rows,
			)
		);
	}

	public static function handle_edit_outcome( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		$id     = (int) $req->get_param( 'id' );
		$result = CC_Assistant_Edit_Outcomes::compute_outcome( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_gsc_anomalies( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$args = array(
			'window_days'   => (int) $req->get_param( 'window_days' ),
			'baseline_days' => (int) $req->get_param( 'baseline_days' ),
			'direction'     => sanitize_key( $req->get_param( 'direction' ) ),
			'limit'         => (int) $req->get_param( 'limit' ),
		);
		$rows = CC_Assistant_GSC::anomalous_pages( $args );
		return self::wrap( array( 'count' => count( $rows ), 'filters' => $args, 'rows' => $rows ) );
	}

	public static function handle_gsc_ai_overview( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$args = array(
			'days'  => (int) $req->get_param( 'days' ),
			'limit' => (int) $req->get_param( 'limit' ),
		);
		$rows = CC_Assistant_GSC::ai_overview_pages( $args );
		return self::wrap( array( 'count' => count( $rows ), 'rows' => $rows ) );
	}

	public static function handle_gsc_aio_ctr_drop( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$args = array(
			'min_impressions' => (int) $req->get_param( 'min_impressions' ),
			'max_position'    => (float) $req->get_param( 'max_position' ),
			'max_ratio'       => (float) $req->get_param( 'max_ratio' ),
			'limit'           => (int) $req->get_param( 'limit' ),
			'days'            => (int) $req->get_param( 'days' ),
		);
		$rows = CC_Assistant_GSC::aio_ctr_drop_alert( $args );
		return self::wrap(
			array(
				'count'           => count( $rows ),
				'filters'         => $args,
				'recent_filtered' => (int) CC_Assistant_GSC::$last_recent_filtered,
				'rows'            => $rows,
			)
		);
	}

	public static function handle_gsc_intent_breakdown( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		if ( ! CC_Assistant_GSC::is_connected() ) {
			return new WP_Error( 'gsc_not_connected', 'Search Console is not connected.', array( 'status' => 400 ) );
		}
		$args = array(
			'days'    => (int) $req->get_param( 'days' ),
			'post_id' => (int) $req->get_param( 'post_id' ),
		);
		return self::wrap( CC_Assistant_GSC::intent_breakdown( $args ) );
	}

	public static function handle_llm_crawls( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';
		$days = (int) $req->get_param( 'days' );
		return self::wrap( CC_Assistant_LLM_Tracker::summary( $days ) );
	}

	public static function handle_links_summary() {
		require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
		return self::wrap( CC_Assistant_Internal_Links::summary() );
	}

	public static function handle_links_orphans( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
		$rows = CC_Assistant_Internal_Links::find_orphans( array(
			'limit'           => (int) $req->get_param( 'limit' ),
			'include_pending' => filter_var( $req->get_param( 'include_pending' ), FILTER_VALIDATE_BOOLEAN ),
		) );
		return self::wrap( array( 'count' => count( $rows ), 'orphans' => $rows ) );
	}

	public static function handle_content_audits( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-content-audits.php';
		$result = CC_Assistant_Content_Audits::run_all( array(
			'limit' => (int) $req->get_param( 'limit' ),
		) );
		return self::wrap( $result );
	}

	/**
	 * One-shot connectivity probe. Confirms the REST layer is reachable with
	 * the caller's app-password and that the site_identity service is up. Used
	 * by the Settings → Connection "Test connection" button so operators can
	 * verify their .mcp.json before opening Claude Code.
	 */
	public static function handle_connection_test() {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		return self::wrap( array(
			'ok'                => true,
			'fingerprint'       => CC_Assistant_Site_Identity::fingerprint(),
			'site_url'          => home_url(),
			'wp_user'           => wp_get_current_user()->user_login,
			'plugin_version'    => defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : 'unknown',
			'php_extensions'    => array(
				'curl'    => function_exists( 'curl_init' ),
				'openssl' => extension_loaded( 'openssl' ),
				'json'    => function_exists( 'json_encode' ),
			),
			'tested_at'         => current_time( 'mysql' ),
		) );
	}

	/** v0.75.0: rendered-page facts, refreshed on read when stale. */
	public static function handle_verified_page_audit( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-verified-page-audit.php';
		return self::wrap( CC_Assistant_Verified_Page_Audit::run( (int) $req->get_param( 'id' ) ) );
	}

	public static function handle_page_facts( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
		$id      = (int) $req->get_param( 'id' );
		$refresh = null === $req->get_param( 'refresh' ) || filter_var( $req->get_param( 'refresh' ), FILTER_VALIDATE_BOOLEAN );
		$result  = CC_Assistant_Page_Facts::get( $id, $refresh );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_page_facts_coverage() {
		require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
		return self::wrap( CC_Assistant_Page_Facts::coverage() );
	}

	public static function handle_links_audit( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
		$id     = (int) $req->get_param( 'id' );
		$result = CC_Assistant_Internal_Links::audit_post( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_links_rebuild() {
		// Defer to cron so a giant site does not block the request.
		wp_schedule_single_event( time() + 5, 'cc_assistant_link_graph_rebuild' );
		return self::wrap( array( 'queued' => true, 'note' => 'Link graph rebuild scheduled via cron.' ) );
	}

	/**
	 * Bulk-link Polylang translation pairs. Closes the gap where a site has
	 * Polylang installed AND per-post language assigned, but the translation
	 * relationships (Spanish post X is the translation of English post Y)
	 * were never set up — typically because pages were created via a Duplicate
	 * Post plugin instead of Polylang's "Add translation" workflow. Without
	 * this linking, the language switcher fails and hreflang tags are wrong.
	 *
	 * Body: { "pairs": [ {"en": 228, "es": 4687}, {"en": 383, "es": 4689}, ... ] }
	 *
	 * Each pair is a map of language_slug => post_id covering ALL languages
	 * for one piece of content. Posts must already have their language assigned
	 * (the endpoint will set it from the pair if missing). Existing
	 * translation relationships are overwritten — Polylang's
	 * pll_save_post_translations() handles the term assignment.
	 *
	 * Returns per-pair success/error so the caller can act on partial failure.
	 */
	public static function handle_polylang_link_translations( WP_REST_Request $req ) {
		if ( ! function_exists( 'pll_save_post_translations' ) || ! function_exists( 'pll_get_post_language' ) ) {
			return new WP_Error(
				'polylang_not_active',
				'Polylang must be active and provide pll_save_post_translations() / pll_get_post_language() to use this endpoint.',
				array( 'status' => 400 )
			);
		}

		$pairs          = $req->get_param( 'pairs' );
		$force_language = (bool) $req->get_param( 'force_language' );
		if ( ! is_array( $pairs ) || empty( $pairs ) ) {
			return new WP_Error(
				'invalid_pairs',
				'pairs must be a non-empty array of {lang_code: post_id, ...} objects.',
				array( 'status' => 400 )
			);
		}

		$results = array();
		$ok      = 0;
		$failed  = 0;

		foreach ( $pairs as $idx => $pair ) {
			if ( ! is_array( $pair ) || empty( $pair ) ) {
				$results[] = array( 'index' => $idx, 'ok' => false, 'errors' => array( 'pair must be a non-empty object/array' ) );
				$failed++;
				continue;
			}

			$cleaned = array();
			$errors  = array();

			foreach ( $pair as $lang => $post_id ) {
				$post_id = (int) $post_id;
				$lang    = (string) $lang;
				if ( $post_id <= 0 || '' === $lang ) {
					$errors[] = sprintf( 'invalid lang/post_id: %s => %s', $lang, $post_id );
					continue;
				}
				$post = get_post( $post_id );
				if ( ! $post ) {
					$errors[] = sprintf( 'post %d (%s) does not exist', $post_id, $lang );
					continue;
				}
				$detected = pll_get_post_language( $post_id, 'slug' );
				if ( $detected && $detected !== $lang ) {
					if ( ! $force_language ) {
						$errors[] = sprintf( 'post %d is in language "%s" but mapping says "%s" — refusing to overwrite. Pass force_language=true to overwrite.', $post_id, $detected, $lang );
						continue;
					}
					// Force-overwrite: caller has explicitly opted in.
					if ( function_exists( 'pll_set_post_language' ) ) {
						pll_set_post_language( $post_id, $lang );
					}
				} elseif ( ! $detected && function_exists( 'pll_set_post_language' ) ) {
					pll_set_post_language( $post_id, $lang );
				}
				$cleaned[ $lang ] = $post_id;
			}

			if ( ! empty( $errors ) ) {
				$results[] = array( 'index' => $idx, 'ok' => false, 'pair' => $pair, 'errors' => $errors );
				$failed++;
				continue;
			}

			pll_save_post_translations( $cleaned );

			$results[] = array( 'index' => $idx, 'ok' => true, 'linked' => $cleaned );
			$ok++;
		}

		return self::wrap(
			array(
				'count'   => count( $pairs ),
				'ok'      => $ok,
				'failed'  => $failed,
				'results' => $results,
			)
		);
	}

	/**
	 * Debug: dump the raw Polylang state for a post — language code via the
	 * helper API, translations via the helper API, translations via the
	 * underlying PLL() model object (bypasses any REST-context shortcut), and
	 * the raw post_translations taxonomy term assignment. Used to diagnose
	 * why pll_save_post_translations() says success but pll_get_post_translations()
	 * returns empty.
	 */
	public static function handle_polylang_inspect( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'id' );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		$out = array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'engine'     => null,
			'lang'       => null,
			'translations_via_helper' => null,
			'translations_via_model'  => null,
			'term_assignments'        => array(),
		);

		if ( function_exists( 'pll_current_language' ) ) {
			$out['engine'] = 'polylang';
			if ( function_exists( 'pll_get_post_language' ) ) {
				$out['lang'] = pll_get_post_language( $post_id, 'slug' );
			}
			if ( function_exists( 'pll_get_post_translations' ) ) {
				$out['translations_via_helper'] = pll_get_post_translations( $post_id );
			}
			if ( function_exists( 'PLL' ) ) {
				$pll = PLL();
				if ( $pll && isset( $pll->model ) && isset( $pll->model->post ) && method_exists( $pll->model->post, 'get_translations' ) ) {
					$out['translations_via_model'] = $pll->model->post->get_translations( $post_id );
				}
			}
		} else {
			$out['engine'] = 'none';
		}

		// Raw term assignments — the "language" taxonomy and "post_translations" taxonomy.
		$lang_terms = wp_get_post_terms( $post_id, 'language', array( 'fields' => 'all' ) );
		$tx_terms   = wp_get_post_terms( $post_id, 'post_translations', array( 'fields' => 'all' ) );
		$out['term_assignments']['language']          = is_wp_error( $lang_terms ) ? array() : array_map(
			function ( $t ) { return array( 'term_id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name ); },
			$lang_terms
		);
		$out['term_assignments']['post_translations'] = is_wp_error( $tx_terms ) ? array() : array_map(
			function ( $t ) { return array( 'term_id' => $t->term_id, 'slug' => $t->slug, 'description' => $t->description ); },
			$tx_terms
		);

		// Diagnostic: call our own helper to see what it returns vs the
		// direct pll_get_post_translations result above.
		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
		CC_Assistant_Multilingual::reset_caches();
		$out['translations_via_helper_class'] = CC_Assistant_Multilingual::translations_of( $post_id );
		$out['lang_via_helper_class']         = CC_Assistant_Multilingual::language_of( $post_id );
		$out['engine_detected']               = CC_Assistant_Multilingual::engine();

		return self::wrap( $out );
	}

	/**
	 * Run TF-IDF cosine similarity across a set of posts and return clusters of
	 * near-duplicates above the threshold. Use BEFORE rewriting to find
	 * competing pages on the same topic. Caller passes a candidate post_id list
	 * (typically from list_posts(search="topic")) and a threshold (default 0.7).
	 */
	public static function handle_find_duplicate_content( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';

		$raw_ids   = $req->get_param( 'post_ids' );
		$threshold = $req->get_param( 'threshold' );
		$threshold = ( null === $threshold || '' === $threshold ) ? 0.7 : (float) $threshold;
		if ( $threshold < 0.3 || $threshold > 0.99 ) {
			return new WP_Error( 'threshold_out_of_range', 'threshold must be between 0.3 and 0.99 (cosine similarity).', array( 'status' => 400 ) );
		}

		if ( ! is_array( $raw_ids ) || empty( $raw_ids ) ) {
			return new WP_Error( 'post_ids_required', 'post_ids must be a non-empty array of integers.', array( 'status' => 400 ) );
		}

		$post_ids = array();
		foreach ( $raw_ids as $id ) {
			$pid = (int) $id;
			if ( $pid > 0 ) {
				$post_ids[] = $pid;
			}
		}
		$post_ids = array_values( array_unique( $post_ids ) );
		if ( count( $post_ids ) < 2 ) {
			return new WP_Error( 'need_two_posts', 'At least 2 valid post_ids are required to compare similarity.', array( 'status' => 400 ) );
		}

		$result = CC_Assistant_Similarity::find_clusters( $post_ids, $threshold );

		// Annotate each cluster member with title + permalink so the caller has
		// human-readable context without a follow-up list_posts.
		if ( ! empty( $result['clusters'] ) && is_array( $result['clusters'] ) ) {
			foreach ( $result['clusters'] as &$cluster ) {
				if ( ! empty( $cluster['members'] ) && is_array( $cluster['members'] ) ) {
					$enriched = array();
					foreach ( $cluster['members'] as $m ) {
						$pid = is_array( $m ) && isset( $m['post_id'] ) ? (int) $m['post_id'] : (int) $m;
						$post = get_post( $pid );
						if ( ! $post ) {
							continue;
						}
						$enriched[] = array(
							'post_id'    => $pid,
							'title'      => $post->post_title,
							'permalink'  => get_permalink( $pid ),
							'word_count' => str_word_count( wp_strip_all_tags( $post->post_content ) ),
							'modified'   => $post->post_modified_gmt,
							'status'     => $post->post_status,
						);
					}
					$cluster['members'] = $enriched;
				}
			}
			unset( $cluster );
		}

		return self::wrap( $result );
	}

	/**
	 * Inspect the indexing status of a single URL via the Google Search Console
	 * URL Inspection API. Pass either url=full-URL or post_id=N (which gets
	 * resolved to the post permalink).
	 */
	public static function handle_gsc_inspect_url( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';

		$url     = trim( (string) $req->get_param( 'url' ) );
		$post_id = (int) $req->get_param( 'post_id' );
		$force   = filter_var( $req->get_param( 'force_refresh' ), FILTER_VALIDATE_BOOLEAN );

		if ( '' === $url && $post_id > 0 ) {
			$permalink = get_permalink( $post_id );
			if ( ! $permalink ) {
				return new WP_Error( 'post_not_found', 'Post not found or has no permalink.', array( 'status' => 404 ) );
			}
			$url = $permalink;
		}
		if ( '' === $url ) {
			return new WP_Error( 'url_required', 'Pass either url= (full URL) or post_id= (resolves to permalink).', array( 'status' => 400 ) );
		}

		$result = CC_Assistant_GSC::inspect_url( $url, $force );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::wrap( $result );
	}

	public static function handle_topical_authority( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topical-authority.php';
		$args = array(
			'post_type' => sanitize_key( $req->get_param( 'post_type' ) ),
			'threshold' => (float) $req->get_param( 'threshold' ),
			'limit'     => (int) $req->get_param( 'limit' ),
			'days'      => (int) $req->get_param( 'days' ),
		);
		return self::wrap( CC_Assistant_Topical_Authority::analyze( $args ) );
	}

	/**
	 * Queue a 301 (or other code) redirect to be created via the active SEO
	 * plugin's redirect manager on approval. Currently supports Rank Math.
	 * Source must be a path or full URL on this site; destination can be on
	 * any host. Used for duplicate-content resolution and post-deletion redirects.
	 */
	public static function handle_draft_redirect( WP_REST_Request $req ) {
		$source      = trim( (string) $req->get_param( 'source' ) );
		$destination = trim( (string) $req->get_param( 'destination' ) );
		$http_code   = (int) ( $req->get_param( 'http_code' ) ?: 301 );
		$reasoning   = (string) $req->get_param( 'reasoning' );
		$summary     = (string) $req->get_param( 'summary' );

		if ( ! in_array( $http_code, array( 301, 302, 307, 410, 451 ), true ) ) {
			return new WP_Error( 'redirect_invalid_code', 'http_code must be one of: 301, 302, 307, 410, 451.', array( 'status' => 400 ) );
		}
		// 410/451 are "gone" statuses — Rank Math stores them without a
		// destination, so requiring one here contradicted the tool contract
		// and forced callers to pass a dummy URL.
		$needs_destination = ! in_array( $http_code, array( 410, 451 ), true );
		if ( '' === $source || ( $needs_destination && '' === $destination ) ) {
			return new WP_Error( 'redirect_payload_missing', $needs_destination ? 'source and destination are required.' : 'source is required.', array( 'status' => 400 ) );
		}
		if ( ! class_exists( '\RankMath\Redirections\Redirection' ) ) {
			return new WP_Error( 'rank_math_unavailable', 'Rank Math Redirections module is not active. Enable it in Rank Math > Dashboard before queueing redirect-create pendings.', array( 'status' => 400 ) );
		}

		// Diagnostic mode: when _diag=true is passed, run the dedup investigation
		// and return its raw output WITHOUT queueing. Used by the operator to
		// inspect Rank Math's storage format on a specific install when the
		// dedup is unexpectedly missing a known-existing redirect (the v0.10.0/0.10.1
		// case where strict marker checks didn't recognize a JSON-stored sources
		// blob). Remove or gate this behind a constant before shipping if it
		// turns out to leak something sensitive in the diag payload.
		$diag = filter_var( $req->get_param( '_diag' ), FILTER_VALIDATE_BOOLEAN );
		if ( $diag ) {
			return self::wrap( self::diagnose_redirect_dedup( $source ) );
		}

		// Hard guard against source==destination self-loops. Pre-empts the
		// ERR_TOO_MANY_REDIRECTS class of breakage where a redirect points a
		// path back to itself. Caught here at queue time so the bad pending
		// never reaches the inbox.
		if ( self::is_self_redirect( $source, $destination ) ) {
			return new WP_Error(
				'redirect_self_loop',
				sprintf( 'Refusing self-redirect: source path "%s" resolves to the same path as destination "%s". A redirect from a URL to itself produces an infinite loop.', self::normalize_redirect_source( $source ), $destination ),
				array( 'status' => 422 )
			);
		}

		// Reverse-loop guard: refuse A -> B when an existing redirect (Rank Math
		// or a pending) already sends B -> A. Approving both produces an
		// infinite loop on BOTH URLs (ERR_TOO_MANY_REDIRECTS). This is the
		// 2026-06-02 incident: a pre-existing canonical -> old redirect plus a
		// newly-queued old -> canonical redirect looped both pages. The
		// same-source dedup below never saw it because the conflicting rule has
		// a DIFFERENT source (the destination). We look up any redirect whose
		// source is our destination and check whether it points back to us.
		$reverse = self::find_existing_redirect_for_source( $destination );
		if ( ! is_wp_error( $reverse ) && $reverse
			&& self::normalize_redirect_source( (string) $reverse['destination'] ) === self::normalize_redirect_source( $source ) ) {
			return new WP_Error(
				'redirect_reverse_loop',
				sprintf(
					'Refusing redirect loop: an existing %s redirect (id %d) already sends "%s" -> "%s". Adding "%s" -> "%s" would make both URLs loop forever. Remove the existing reverse redirect first (Rank Math > Redirections), then re-queue.',
					'rank_math' === $reverse['source_in_use_by'] ? 'Rank Math' : 'pending',
					(int) $reverse['id'],
					self::normalize_redirect_source( $destination ),
					self::normalize_redirect_source( (string) $reverse['destination'] ),
					self::normalize_redirect_source( $source ),
					self::normalize_redirect_source( $destination )
				),
				array(
					'status'           => 422,
					'reverse_redirect' => $reverse,
				)
			);
		}

		// Dedup against existing Rank Math redirects + any same-source pending
		// redirect already in the inbox. Either case means the operator has
		// already done this work (or queued it); silently re-queueing wastes
		// reviewer time and confuses the agent. Both checks return 409 with
		// the existing entry's details so the caller can verify and move on.
		$dupe_check = self::find_existing_redirect_for_source( $source );
		if ( ! is_wp_error( $dupe_check ) && $dupe_check ) {
			return new WP_Error(
				'redirect_already_exists',
				$dupe_check['message'],
				array(
					'status'            => 409,
					'existing_redirect' => $dupe_check,
				)
			);
		}

		$proposed = array(
			'source'      => $source,
			'destination' => $destination,
			'http_code'   => $http_code,
		);

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => null,
			'change_type'    => 'create_redirect',
			'change_summary' => $summary ?: sprintf( '%d redirect: %s -> %s', $http_code, $source, $destination ),
			'current_value'  => '',
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => $reasoning,
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id' => $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'http_code'  => $http_code,
			'source'     => $source,
			'destination' => $destination,
		) );
	}

	/**
	 * Shared queue path for redirect lifecycle ops (delete / untrash). Fetches
	 * the Rank Math redirect by id so the reviewer sees exactly what they are
	 * approving (source pattern, destination, current status), then queues a
	 * pending. Returns a wrap() payload or WP_Error.
	 */
	private static function queue_redirect_lifecycle( $id, $action, $reasoning ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return new WP_Error( 'redirect_id_required', 'A positive redirect id is required.', array( 'status' => 400 ) );
		}
		if ( ! class_exists( '\RankMath\Redirections\DB' ) ) {
			return new WP_Error( 'rank_math_unavailable', 'Rank Math Redirections module is not active.', array( 'status' => 400 ) );
		}

		$row = \RankMath\Redirections\DB::get_redirection_by_id( $id, 'all' );
		if ( is_object( $row ) ) {
			$row = (array) $row;
		}
		if ( empty( $row ) || ! is_array( $row ) ) {
			return new WP_Error( 'redirect_not_found', sprintf( 'No Rank Math redirect found with id %d.', $id ), array( 'status' => 404 ) );
		}

		// sources is a (maybe-serialized) array of {pattern, comparison, ignore}.
		$sources = isset( $row['sources'] ) ? maybe_unserialize( $row['sources'] ) : array();
		$pattern = '';
		if ( is_array( $sources ) && isset( $sources[0]['pattern'] ) ) {
			$pattern = (string) $sources[0]['pattern'];
		}
		$destination = isset( $row['url_to'] ) ? (string) $row['url_to'] : '';
		$status      = isset( $row['status'] ) ? (string) $row['status'] : '';

		if ( 'untrash' === $action && 'active' === strtolower( $status ) ) {
			return new WP_Error(
				'redirect_already_active',
				sprintf( 'Redirect id %d is already active; nothing to untrash.', $id ),
				array( 'status' => 409 )
			);
		}

		$proposed = array(
			'id'          => $id,
			'action'      => $action,
			'source'      => $pattern,
			'destination' => $destination,
			'prev_status' => $status,
		);

		$verb    = 'delete' === $action ? 'Delete' : 'Restore (untrash)';
		$summary = sprintf(
			'%s Rank Math redirect id %d: %s -> %s (status: %s)',
			$verb,
			$id,
			'' !== $pattern ? $pattern : '(pattern n/a)',
			'' !== $destination ? $destination : '(no destination)',
			'' !== $status ? $status : 'unknown'
		);

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => null,
			'change_type'    => 'delete' === $action ? 'delete_redirect' : 'untrash_redirect',
			'change_summary' => $summary,
			'current_value'  => wp_json_encode( array( 'id' => $id, 'source' => $pattern, 'destination' => $destination, 'status' => $status ) ),
			'proposed_value' => wp_json_encode( $proposed ),
			'reasoning'      => (string) $reasoning,
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );

		if ( is_wp_error( $pending_id ) ) {
			return $pending_id;
		}

		return self::wrap( array(
			'pending_id'  => $pending_id,
			'review_url'  => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'action'      => $action,
			'redirect_id' => $id,
			'source'      => $pattern,
			'destination' => $destination,
		) );
	}

	/**
	 * POST /draft/redirect/delete — queue PERMANENT deletion of a Rank Math
	 * redirect by id. For removing a stale, wrong, or looping redirect the
	 * create-side guards can't fix (the assistant previously had no delete path,
	 * forcing a manual Rank Math step). On approval: Rank Math DB::delete (which
	 * also purges its redirect cache).
	 */
	public static function handle_draft_redirect_delete( WP_REST_Request $req ) {
		return self::queue_redirect_lifecycle(
			(int) $req->get_param( 'id' ),
			'delete',
			(string) $req->get_param( 'reasoning' )
		);
	}

	/**
	 * POST /draft/redirect/untrash — queue restoring a trashed/inactive Rank
	 * Math redirect back to active by id. On approval: DB::change_status active
	 * + cache purge so it fires again.
	 */
	public static function handle_draft_redirect_untrash( WP_REST_Request $req ) {
		return self::queue_redirect_lifecycle(
			(int) $req->get_param( 'id' ),
			'untrash',
			(string) $req->get_param( 'reasoning' )
		);
	}

	/**
	 * GET /gbp/locations — cached Business Profile locations (title, primary +
	 * additional categories, address, phone, website, place id). Read-only; no
	 * pending inbox (it queries Google, doesn't mutate the site). Pass
	 * refresh=true to re-pull from Google instead of the cached option.
	 */
	public static function handle_get_working_state( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-working-state.php';
		return self::wrap( array( 'working_state' => CC_Assistant_Working_State::get() ) );
	}

	public static function handle_update_working_state( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-working-state.php';
		$params = $req->get_json_params();
		$state  = CC_Assistant_Working_State::update( is_array( $params ) ? $params : array() );
		return self::wrap( array( 'working_state' => $state ) );
	}

	public static function handle_gbp_locations( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gbp.php';
		if ( ! CC_Assistant_GBP::is_connected() ) {
			return new WP_Error( 'gbp_not_connected', 'Google Business Profile is not connected. Connect it in Settings → Business Profile.', array( 'status' => 400 ) );
		}
		$refresh = filter_var( $req->get_param( 'refresh' ), FILTER_VALIDATE_BOOLEAN );
		$locs    = $refresh ? CC_Assistant_GBP::fetch_locations() : CC_Assistant_GBP::get_locations();
		if ( is_wp_error( $locs ) ) {
			return $locs;
		}
		if ( empty( $locs ) && ! $refresh ) {
			$locs = CC_Assistant_GBP::fetch_locations();
			if ( is_wp_error( $locs ) ) {
				return $locs;
			}
		}
		return self::wrap( array( 'count' => count( $locs ), 'locations' => $locs ) );
	}

	/**
	 * GET /gbp/performance — daily local-pack KPI totals for one location over
	 * the trailing N days (calls, website clicks, direction requests, map +
	 * search impressions). location = "locations/123" (from gbp_locations).
	 */
	public static function handle_gbp_performance( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gbp.php';
		if ( ! CC_Assistant_GBP::is_connected() ) {
			return new WP_Error( 'gbp_not_connected', 'Google Business Profile is not connected. Connect it in Settings → Business Profile.', array( 'status' => 400 ) );
		}
		$location = trim( (string) $req->get_param( 'location' ) );
		if ( '' === $location ) {
			return new WP_Error( 'gbp_location_required', 'location is required (e.g. "locations/123"). Get it from gbp_locations.', array( 'status' => 400 ) );
		}
		$days = (int) ( $req->get_param( 'days' ) ?: 30 );
		if ( $days < 1 || $days > 537 ) {
			// 537 = ~18-month Performance API retention minus the 3-day lag pad.
			$days = 30;
		}
		$res = CC_Assistant_GBP::fetch_performance( $location, $days );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::wrap( $res );
	}

	/**
	 * Look for an existing redirect that already covers this source URL.
	 *
	 * Two dedup paths:
	 *   1. Rank Math's wp_rank_math_redirections table — if the user (or a prior
	 *      session) already added a redirect, queueing another one is dead weight.
	 *      The sources column is a serialized PHP array of {pattern, comparison,
	 *      ignore} entries; LIKE-matching against the normalized path-with-query
	 *      finds exact matches and leaves regex / start-with comparisons alone
	 *      (those aren't dedup candidates anyway because they don't cover one
	 *      specific source).
	 *   2. Same-source pending row already in the cc inbox — catches the case
	 *      where the model proposes the same redirect twice in a session.
	 *
	 * Returns false if nothing found, or an array with dupe details + a
	 * one-line operator-friendly message.
	 */
	private static function find_existing_redirect_for_source( $source ) {
		global $wpdb;
		$path  = self::normalize_redirect_source( $source );
		$rm_table = $wpdb->prefix . 'rank_math_redirections';

		// Confirm RM redirections table exists before LIKE-matching it.
		// Module class existed at the caller, but the table may not on a
		// fresh install where the user hasn't created a redirect yet.
		$rm_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rm_table ) ) === $rm_table;
		if ( $rm_exists ) {
			// Rank Math stores pattern WITHOUT the leading slash (verified via
			// _diag against eroflufkin.com on 2026-05-05: stored values like
			// s:37:"services/chest-pain-treatment-lufkin/", not s:38:"/services/...").
			// We test both leading-slash and no-leading-slash variants for the
			// LIKE filter and the per-row marker check, so this works whether
			// a future Rank Math version standardizes either form.
			$rm_path  = ltrim( $path, '/' );
			$variants = array_unique( array_filter( array( $rm_path, $path ) ) );
			if ( empty( $variants ) ) {
				$variants = array( $path );
			}

			// Drop the status filter and pull all candidate rows for either
			// path variant. Rank Math has used both string ('active' / 'inactive')
			// and integer (1 / 2 / 0) status values across versions; LIKE-matching
			// the path alone and then filtering in PHP is safer than guessing the
			// column type. Limit to first 10 matches because path collisions
			// across distinct redirects are rare.
			$where_parts = array_fill( 0, count( $variants ), 'sources LIKE %s' );
			$like_args   = array_map( function ( $v ) use ( $wpdb ) {
				return '%' . $wpdb->esc_like( $v ) . '%';
			}, $variants );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, sources, url_to, header_code, status
				 FROM {$rm_table}
				 WHERE " . implode( ' OR ', $where_parts ) . "
				 LIMIT 10",
				$like_args
			) );

			foreach ( (array) $rows as $row ) {
				// Confirm the path appears as an exact-match pattern (not just a
				// substring of a longer URL like /cat matching /catalog). Rank
				// Math has stored sources as both PHP-serialized arrays and JSON
				// across versions, so we accept either format's exact-pattern
				// marker. Bare-path fallback handles edge formats we have not
				// catalogued; a false positive there only over-warns the operator.
				$haystack = (string) $row->sources;
				$is_exact = false;
				foreach ( $variants as $candidate ) {
					$marker_serialized = sprintf( 's:%d:"%s"', strlen( $candidate ), $candidate );
					$marker_json       = sprintf( '"pattern":"%s"', $candidate );
					$marker_bare       = '"' . $candidate . '"';
					if ( false !== strpos( $haystack, $marker_serialized )
						|| false !== strpos( $haystack, $marker_json )
						|| false !== strpos( $haystack, $marker_bare ) ) {
						$is_exact = true;
						break;
					}
				}
				if ( $is_exact ) {
					// Rank Math soft-deletes redirects: a "deleted" or disabled
					// rule lingers in this table with status inactive/trashed
					// (string or int, depending on RM version) but never fires.
					// Such a ghost row must NOT block creating a fresh redirect —
					// that was the 409 "redirect already exists" bug where a
					// trashed rule blocked the replacement forever and no cache
					// flush could clear it. Skip inactive/trashed rows and keep
					// scanning for a genuinely-active match.
					$status_norm = strtolower( trim( (string) $row->status ) );
					if ( in_array( $status_norm, array( 'inactive', 'trashed', '2', '0' ), true ) ) {
						continue;
					}
					return array(
						'source_in_use_by' => 'rank_math',
						'id'               => (int) $row->id,
						'destination'      => (string) $row->url_to,
						'http_code'        => (int) $row->header_code,
						'status'           => (string) $row->status,
						'message'          => sprintf(
							'Rank Math already has a redirect for "%s" (id %d, %d to %s, status %s). No new pending queued. Verify in Rank Math > Redirections.',
							$path,
							(int) $row->id,
							(int) $row->header_code,
							(string) $row->url_to,
							(string) $row->status
						),
					);
				}
			}
		}

		// Same-source pending row already in the cc inbox. Skip superseded
		// rows so they don't block a new pending — once a row is superseded,
		// it's effectively obsolete and shouldn't gate new proposals.
		$pending_table = $wpdb->prefix . 'cc_pending_changes';
		$rows = $wpdb->get_results(
			"SELECT id, proposed_value, created_at
			 FROM {$pending_table}
			 WHERE change_type = 'create_redirect'
			   AND status = 'pending'
			   AND superseded_by IS NULL
			 ORDER BY id DESC
			 LIMIT 200"
		);
		foreach ( (array) $rows as $r ) {
			$decoded = json_decode( (string) $r->proposed_value, true );
			if ( ! is_array( $decoded ) || empty( $decoded['source'] ) ) {
				continue;
			}
			if ( self::normalize_redirect_source( (string) $decoded['source'] ) === $path ) {
				return array(
					'source_in_use_by' => 'cc_pending',
					'id'               => (int) $r->id,
					'destination'      => (string) $decoded['destination'],
					'http_code'        => (int) $decoded['http_code'],
					'status'           => 'pending',
					'message'          => sprintf(
						'A pending redirect for "%s" is already in the inbox (PC #%d, %d to %s, queued %s). Approve or reject that one before queueing a new pending.',
						$path,
						(int) $r->id,
						(int) $decoded['http_code'],
						(string) $decoded['destination'],
						(string) $r->created_at
					),
				);
			}
		}

		return false;
	}

	/**
	 * Diagnostic counterpart to find_existing_redirect_for_source.
	 *
	 * Returns the same investigation it does PLUS the raw query state — table
	 * existence, total row count in the table, sample of the FIRST 3 sources
	 * blobs in the table verbatim (truncated to 1KB each), the LIKE-match
	 * count, and per-row marker-test results. Used to debug why dedup is
	 * unexpectedly missing a known-existing redirect on a particular install.
	 * The output is meant for operator inspection, not for downstream apply
	 * logic; nothing in the apply path consumes this shape.
	 */
	private static function diagnose_redirect_dedup( $source ) {
		global $wpdb;
		$path     = self::normalize_redirect_source( $source );
		$rm_table = $wpdb->prefix . 'rank_math_redirections';

		$diag = array(
			'source_input'      => $source,
			'normalized_path'   => $path,
			'rm_table_attempted'=> $rm_table,
			'wpdb_prefix'       => $wpdb->prefix,
		);

		$rm_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rm_table ) ) === $rm_table;
		$diag['rm_table_exists'] = $rm_table_exists;
		if ( ! $rm_table_exists ) {
			// List candidate tables so we can see if the prefix or table name diverged.
			$diag['candidate_tables'] = $wpdb->get_col( "SHOW TABLES LIKE '%rank_math%'" );
			$diag['cc_pending_match'] = self::find_existing_redirect_for_source( $source );
			return $diag;
		}

		$diag['total_rows'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rm_table}" );

		// Sample any 3 rows verbatim so we can see the actual storage format
		// (PHP serialized vs JSON vs something else).
		$sample = $wpdb->get_results( "SELECT id, sources, url_to, header_code, status FROM {$rm_table} ORDER BY id ASC LIMIT 3" );
		$diag['sample_rows'] = array();
		foreach ( (array) $sample as $r ) {
			$diag['sample_rows'][] = array(
				'id'             => (int) $r->id,
				'status'         => (string) $r->status,
				'header_code'    => (int) $r->header_code,
				'url_to'         => (string) $r->url_to,
				'sources_length' => strlen( (string) $r->sources ),
				'sources_preview'=> substr( (string) $r->sources, 0, 1024 ),
			);
		}

		// Test both leading-slash and no-leading-slash variants because Rank
		// Math stores patterns WITHOUT leading slash. v0.10.24 and earlier
		// only tested the leading-slash form here, so this diag silently
		// reported zero matches even when the dedup helper was finding the
		// row correctly. The dedup helper at find_existing_redirect_for_source
		// already does both variants — we mirror it here so operator-facing
		// diagnostic output cannot diverge from the actual queue-time check.
		$rm_path  = ltrim( $path, '/' );
		$variants = array_values( array_unique( array_filter( array( $rm_path, $path ) ) ) );
		if ( empty( $variants ) ) {
			$variants = array( $path );
		}

		$where_parts = array_fill( 0, count( $variants ), 'sources LIKE %s' );
		$like_args   = array_map( function ( $v ) use ( $wpdb ) {
			return '%' . $wpdb->esc_like( $v ) . '%';
		}, $variants );
		$matches = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, sources, url_to, header_code, status FROM {$rm_table} WHERE " . implode( ' OR ', $where_parts ) . ' LIMIT 5',
			$like_args
		) );
		$diag['like_match_count'] = is_array( $matches ) ? count( $matches ) : 0;
		$diag['variants_tested']  = $variants;

		$markers_per_variant = array();
		foreach ( $variants as $candidate ) {
			$markers_per_variant[ $candidate ] = array(
				'serialized' => sprintf( 's:%d:"%s"', strlen( $candidate ), $candidate ),
				'json'       => sprintf( '"pattern":"%s"', $candidate ),
				'bare'       => '"' . $candidate . '"',
			);
		}
		$diag['markers_tested'] = $markers_per_variant;
		$diag['like_matches']   = array();
		foreach ( (array) $matches as $row ) {
			$haystack = (string) $row->sources;
			$marker_hits = array();
			foreach ( $markers_per_variant as $candidate => $markers ) {
				$marker_hits[ $candidate ] = array(
					'serialized' => false !== strpos( $haystack, $markers['serialized'] ),
					'json'       => false !== strpos( $haystack, $markers['json'] ),
					'bare'       => false !== strpos( $haystack, $markers['bare'] ),
				);
			}
			$diag['like_matches'][] = array(
				'id'              => (int) $row->id,
				'status'          => (string) $row->status,
				// Mirror the live dedup filter: this row is skipped (does NOT
				// block a new redirect) when inactive/trashed, so the diag must
				// say so or it misleads anyone debugging a "dedup didn't block".
				'status_skipped'  => in_array( strtolower( trim( (string) $row->status ) ), array( 'inactive', 'trashed', '2', '0' ), true ),
				'header_code'     => (int) $row->header_code,
				'url_to'          => (string) $row->url_to,
				'sources_length'  => strlen( $haystack ),
				'sources_preview' => substr( $haystack, 0, 1024 ),
				'marker_hits'     => $marker_hits,
			);
		}

		$diag['cc_pending_match'] = self::find_existing_redirect_for_source( $source );

		// Audit ALL self-redirects (rows where the source pattern resolves to
		// the same path as the destination url_to). Independent of the source
		// argument — this is a table-wide health scan included in every diag
		// call so operators get a single-shot view of the whole loop class
		// when investigating any one broken URL. Cheap: a single SELECT over
		// at most a few hundred rows on any normal site.
		$diag['self_redirects'] = self::find_self_redirects_in_rank_math();
		return $diag;
	}

	/**
	 * Path-level equality check between a redirect source and destination.
	 * Both inputs are normalized to /-prefixed paths (origin stripped if any),
	 * so a full URL on the destination side and a relative path on the source
	 * side compare correctly. Used to refuse self-loops at queue and apply time.
	 */
	private static function is_self_redirect( $source, $destination ) {
		$src_path = self::normalize_redirect_source( $source );
		$dst_path = self::normalize_redirect_source( $destination );
		if ( '' === $src_path || '' === $dst_path ) {
			return false;
		}
		return $src_path === $dst_path;
	}

	/**
	 * Scan the Rank Math redirects table for self-loops — any row where one of
	 * the source patterns resolves to the same path as the destination. Returns
	 * a list of {id, pattern, comparison, url_to, header_code, status} entries
	 * for cleanup. The dedup helper already prevents NEW self-loops from being
	 * queued, but pre-existing rows (manual or imported) need this audit to
	 * surface them so the operator can delete them in Rank Math admin.
	 */
	public static function find_self_redirects_in_rank_math() {
		global $wpdb;
		$rm_table = $wpdb->prefix . 'rank_math_redirections';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rm_table ) ) === $rm_table;
		if ( ! $exists ) {
			return array();
		}
		$rows = $wpdb->get_results( "SELECT id, sources, url_to, header_code, status FROM {$rm_table}" );
		$loops = array();
		foreach ( (array) $rows as $row ) {
			$dst_path = self::normalize_redirect_source( (string) $row->url_to );
			if ( '' === $dst_path || '/' === $dst_path ) {
				continue;
			}
			$entries = self::deserialize_redirect_sources( (string) $row->sources );
			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['pattern'] ) ) {
					continue;
				}
				$pattern    = (string) $entry['pattern'];
				$comparison = isset( $entry['comparison'] ) ? (string) $entry['comparison'] : '';
				// Only flag exact + contains comparisons. Regex patterns are
				// templates, not literal paths, and would false-positive here.
				if ( '' !== $comparison && 'exact' !== $comparison && 'contains' !== $comparison ) {
					continue;
				}
				$src_path = self::normalize_redirect_source( $pattern );
				if ( $src_path === $dst_path ) {
					$loops[] = array(
						'id'          => (int) $row->id,
						'pattern'     => $pattern,
						'comparison'  => $comparison,
						'url_to'      => (string) $row->url_to,
						'header_code' => (int) $row->header_code,
						'status'      => (string) $row->status,
					);
					break;
				}
			}
		}
		return $loops;
	}

	/**
	 * Tolerantly deserialize Rank Math's sources blob. Older versions store
	 * a PHP-serialized array; some versions / imports use JSON. Returns an
	 * empty array on either failure path so callers can iterate safely.
	 */
	private static function deserialize_redirect_sources( $blob ) {
		if ( '' === $blob ) {
			return array();
		}
		$unserialized = @unserialize( $blob, array( 'allowed_classes' => false ) );
		if ( is_array( $unserialized ) ) {
			return $unserialized;
		}
		$json = json_decode( $blob, true );
		if ( is_array( $json ) ) {
			return $json;
		}
		return array();
	}

	/**
	 * Normalize a redirect source for matching: strip protocol + host, ensure
	 * leading slash, preserve query string. Trailing slash kept as authored —
	 * /foo and /foo/ are distinct entries in Rank Math.
	 */
	private static function normalize_redirect_source( $source ) {
		$source = trim( (string) $source );
		if ( '' === $source ) {
			return '';
		}
		// Already a path? Just normalize leading slash.
		if ( '/' === substr( $source, 0, 1 ) ) {
			return $source;
		}
		$parts = wp_parse_url( $source );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}
		if ( '' === $path ) {
			return '/' . ltrim( $source, '/' );
		}
		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Queue an EmergencyService JSON-LD schema for a post. The plugin stores
	 * the JSON in postmeta on approval, then injects it via a wp_head hook on
	 * the front-end. Inputs accept loose objects so callers can pass partial
	 * schemas (e.g. just geo + areaServed); the endpoint backfills name + url
	 * from the post itself when not provided. The 24/7 opening_hours flag
	 * expands to the canonical OpeningHoursSpecification array.
	 */
	public static function handle_draft_emergency_service_schema( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = self::check_post_for_draft( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$name        = trim( (string) ( $req->get_param( 'name' ) ?: get_the_title( $post_id ) ) );
		$url         = trim( (string) ( $req->get_param( 'url' ) ?: get_permalink( $post_id ) ) );
		$telephone   = trim( (string) $req->get_param( 'telephone' ) );
		$address     = $req->get_param( 'address' );
		$geo         = $req->get_param( 'geo' );
		$area_served = $req->get_param( 'area_served' );
		$hours       = $req->get_param( 'opening_hours' );
		$price_range = trim( (string) $req->get_param( 'price_range' ) );
		$image       = trim( (string) $req->get_param( 'image' ) );
		$same_as     = $req->get_param( 'same_as' );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'EmergencyService',
			'name'     => $name,
			'url'      => $url,
		);
		if ( '' !== $telephone ) {
			$schema['telephone'] = $telephone;
		}
		if ( ! empty( $address ) && is_array( $address ) ) {
			$schema['address'] = array_merge( array( '@type' => 'PostalAddress' ), $address );
		}
		if ( ! empty( $geo ) && is_array( $geo ) ) {
			$geo_block = array( '@type' => 'GeoCoordinates' );
			if ( isset( $geo['latitude'] ) ) {
				$geo_block['latitude'] = (float) $geo['latitude'];
			}
			if ( isset( $geo['longitude'] ) ) {
				$geo_block['longitude'] = (float) $geo['longitude'];
			}
			$schema['geo'] = $geo_block;
		}
		if ( ! empty( $area_served ) ) {
			$schema['areaServed'] = is_array( $area_served ) ? $area_served : (string) $area_served;
		}
		if ( ! empty( $hours ) ) {
			if ( '24/7' === $hours || 'always' === $hours ) {
				$schema['openingHoursSpecification'] = array(
					array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
						'opens'     => '00:00',
						'closes'    => '23:59',
					),
				);
			} elseif ( is_array( $hours ) ) {
				$schema['openingHoursSpecification'] = $hours;
			}
		}
		if ( '' !== $price_range ) {
			$schema['priceRange'] = $price_range;
		}
		if ( '' !== $image ) {
			$schema['image'] = $image;
		}
		if ( ! empty( $same_as ) && is_array( $same_as ) ) {
			$schema['sameAs'] = array_values( array_filter( $same_as, 'is_string' ) );
		}

		$current         = get_post_meta( $post_id, '_cc_emergency_service_schema', true );
		$current_decoded = is_string( $current ) && '' !== $current ? json_decode( $current, true ) : null;

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'        => $post_id,
			'change_type'    => 'emergency_service_schema',
			'change_summary' => $req->get_param( 'summary' ) ?: sprintf( 'EmergencyService schema for %s', get_the_title( $post_id ) ),
			'current_value'  => is_array( $current_decoded ) ? wp_json_encode( $current_decoded ) : '',
			'proposed_value' => wp_json_encode( $schema ),
			'reasoning'      => (string) $req->get_param( 'reasoning' ),
			'status'         => 'pending',
			'created_by'     => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap( array(
			'pending_id'     => $pending_id,
			'review_url'     => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'schema_preview' => $schema,
		) );
	}
}
