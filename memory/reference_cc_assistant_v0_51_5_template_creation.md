---
name: cc-assistant-v0-51-5-template-creation
description: v0.51.5 adds Theme Builder template CREATION via draft_create_post (template_type + display_conditions); publish approve regenerates Elementor Pro conditions cache
metadata: 
  node_type: memory
  type: reference
  originSessionId: 1e79afee-4083-48c4-95aa-a68819103e12
  modified: 2026-07-23T09:11:22.439Z
---

cc-assistant v0.51.5 (built 2026-07-23, zip at plugintesting/app/public/cc-assistant-0.51.5.zip) closes the Theme Builder creation gap in [[wp-plugin-cc-assistant]]:

- The v0.49 opt-in (`cc_assistant_allow_template_editing`) only allowed READ/EDIT of existing templates; `create_draft_post` still enforced the raw page/post allowlist, so a 404/header/footer template could never be composed via MCP (error: `post_type_not_allowed`).
- v0.51.5: with the opt-in ON, `draft_create_post` accepts `post_type=elementor_library` + required `template_type` (error-404, header, footer, single, single-page, single-post, archive, search-results, section, popup) + optional `display_conditions` (Elementor Pro strings; 404 = `include/singular/not_found404`, site-wide = `include/general`).
- Create stamps `_elementor_template_type`, `_elementor_edit_mode=builder`, and the `elementor_library_type` taxonomy term; conditions sit dormant on the draft.
- On approve of the template's publish_draft, `apply_publish_draft` regenerates the Elementor Pro Theme Builder conditions cache (`Module::instance()->get_conditions_manager()->get_cache()->regenerate()`) + heavy CSS flush, so the template takes its slot immediately. Without that regen a queue-published template stays inert (the cache only indexes published templates).
- Templates remain un-trashable via the tool; creation refused while the opt-in is off.

**Workflow for a new template:** enable setting -> `draft_create_post` (shell with template_type + conditions) -> `import_elementor_data(post_id, raw_data)` for the tree -> operator approves both. Remember [[plugin-dev-vs-remote-deploy]]: local edits need the zip deployed per site.

**v0.51.5 TRAP fixed in v0.51.6/0.51.7 (verified end-to-end on eroflufkin 2026-07-23):** the publish approval's `wp_update_post` re-fires Elementor's save hooks, which RESET `_elementor_template_type` to "page" and DELETE `_elementor_conditions` — the template arrives published but typeless/inert and invisible under Theme Builder. v0.51.6 added diagnostics: `list_theme_templates` (all template rows + the Pro conditions cache; a published template renders ONLY if it appears in that cache) and `refresh_theme_builder_conditions`. v0.51.7 added the durable fix: create persists `_cc_assistant_template_intent` and `apply_publish_draft` re-stamps identity AFTER publish via `stamp_template_identity()`; plus repair mode on the refresh tool (`repair_post_id` + `template_type` + `display_conditions`). 404 condition string: `include/singular/not_found404` (Elementor Pro class Not_Found404, type singular).
