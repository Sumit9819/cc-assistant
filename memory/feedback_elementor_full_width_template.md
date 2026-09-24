---
name: elementor-full-width-template-on-conversions
description: Classic-to-Elementor page conversions must also set _wp_page_template to elementor_header_footer (Elementor Full Width)
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 813fe1b6-ece5-4c84-92c1-740614b56b3f
---

When converting a classic-editor page to Elementor via import_elementor_data (or building any new Elementor page), the import only writes `_elementor_data` — it does NOT change the page template. Classic pages keep the theme's default template (`template: ""`), which wraps Elementor output in the theme's constrained content column.

**Why:** User flagged 2026-07-08 after the first two erofirving classic conversions (3989, 3990): "you were not using elementor page setting as full elementor width."

**How to apply:**
- Queue `draft_update_postmeta(post_id, "_wp_page_template", "elementor_header_footer")` as a standard step of every classic→Elementor conversion, alongside the import.
- `elementor_header_footer` = "Elementor Full Width" (keeps theme header/footer). `elementor_canvas` would strip header/footer — do not use for service pages.
- Verify cheaply across many pages with `wp-json/wp/v2/pages?include=...&_fields=id,template` (browser UA for the SiteGround WAF).
- Existing Elementor service pages inherit the correct template through imports, so only classic conversions and brand-new pages need this.
