---
name: reference-cc-assistant-v0-19-template-clone
description: cc-assistant v0.19 ships the template-clone toolchain (build_service_page + page_completeness_score + service_inventory + placeholder lint) — replaces the 13-pending manual service-page build that produced misshapen pages
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

**Shipped 2026-05-15** to fix the failure mode that produced the misshapen Laser Genesis page on irvingwellnessclinic.com (post 9983). Manual mirroring couldn't copy the source pillar's container-level layout settings (flex_direction, flex_wrap, widths) because the cc-assistant parser only exposed widgets, not containers. Result: icon-box rows stacked vertically instead of 3-across; sparse sections with no images/maps/reviews; invented service tiers with $TBD placeholders.

**v0.19 ships 5 new capabilities:**

1. **Placeholder lint** ([class-pre-publish.php::lint_html_block](wp-content/plugins/cc-assistant/includes/class-pre-publish.php)). New `placeholders` check joins `em_dashes`, `ai_tells`, `style_guide` as a hard violation. Catches `$TBD`, `[TBD]`, `[TODO]`, `[PLACEHOLDER]`, `[FIXME]`, `Lorem ipsum`, `{{handlebars}}`, and standalone `TBD`/`TODO`/`FIXME`. Wired into both `lint_widget_settings_payload` (widget add/update) and `lint_post_content_change` (post content rewrites). Override via `override_lint=true` only when the operator has accepted the warning.

2. **Deep-clone helpers** ([class-elementor-builder.php](wp-content/plugins/cc-assistant/includes/class-elementor-builder.php)). `clone_tree_with_fresh_ids(tree, skip_ids)` walks the source Elementor tree, generates fresh 7-char hex ids on every node, drops any subtree whose root id is in `skip_ids`, returns a new array. Pairs with `apply_text_replacements_to_tree(tree, replacements)` which walks the cloned tree applying `str_ireplace` to text-bearing settings PLUS widget-specific arrays: `icon_list[].text` (icon-list), `slides[].content/name/title` (reviews), `items[].item_title/item_content/tab_title/tab_content` (nested-accordion + toggle), plus `link.url` and `address` (google_maps).

3. **`build_service_page` macro tool** ([class-build-service-page.php](wp-content/plugins/cc-assistant/includes/class-build-service-page.php) + new REST endpoint `POST /draft/build-service-page`). The orchestrator: validate mirror, clone tree with fresh ids, drop skip subtrees, apply text replacements, lint the assembled body (with placeholders check active), create new WP draft post, save `_elementor_data` + `_elementor_edit_mode=builder` + copy `_elementor_template_type` + `_wp_page_template` from mirror, set SEO meta via auto-detected SEO plugin keys (Rank Math / Yoast / AIOSEO), queue ONE `publish_draft` pending. ONE click to approve the whole page. The mirror's container-level layout is preserved byte-for-byte because we're cloning JSON, not reconstructing structure.

4. **`page_completeness_score`** (REST `GET /page-completeness-score?post_id=NNN&mirror_post_id=MMM`). Compares target page's widget-type histogram to mirror's. Scores 0-100 weighted by mirror counts. Pass at score >= 80. Returns `missing_types[]` and `critical_missing[]` (reviews, google_maps, image, nested-accordion). Run this AFTER `build_service_page` and BEFORE approving the publish_draft pending — sub-80 means the build is incomplete.

5. **`service_inventory`** (REST `GET /service-inventory`). Walks every page with `MedicalProcedure` schema, extracts pricing-menu cards (title heading + price-formatted heading + duration-formatted heading + description text-editor) from each. Returns the actual list of services + tiers the site sells. Call this BEFORE building a new service page so you do NOT invent service tiers that don't exist (the $TBD failure mode). When the target service has no inventory match, pass `skip_widget_ids` to `build_service_page` to drop the pricing menu subtree from the clone.

**MCP tool wrappers** added in [bin/mcp-server.php](wp-content/plugins/cc-assistant/bin/mcp-server.php): `build_service_page`, `page_completeness_score`, `service_inventory`. Tool descriptions explain WHEN to use each.

**Workflow for any new service page going forward:**

```
1. service_inventory()                     → find real tiers (or confirm none)
2. get_page_style_context(mirror=137)      → identify root_ids and any subtrees to skip
3. build_service_page(
     mirror_post_id = 137,
     title = "...",
     slug = "...",
     replacements = {"IV Therapy": "Laser Genesis", "IV drip": "laser facial", ...},
     skip_widget_ids = ["<container_id_of_PREMIUM_MENU>"],   // if no real tiers
     seo_title = "...",
     seo_description = "...",
     focus_keyword = "..."
   )
4. page_completeness_score(post_id=NEW, mirror_post_id=137)  → verify score >= 80
5. approve the publish_draft pending if score passes
6. propose_cluster_assignment(post_id=NEW, cluster_id=...)   → demote old blog pillar
```

**Pre-v0.19 lessons baked in:**
- Place container-level settings under operator's control via clone-not-rebuild (was the LG layout bug)
- Block $TBD at queue time (was the LG pricing-tier bug)
- Refuse low-completeness pages (was the LG missing-FAQ/missing-image bug)
- Cross-reference proposed tiers against real inventory (was the invented service tiers bug)
