---
name: verify-rendered-visuals-after-build
description: "After any Elementor page build/import, verify what actually RENDERS (CSS values, image hosts, contrast) - structural verification is not enough"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 501752a8-8c88-48d5-88ba-e672ff5142f9
---

After building or importing any Elementor page, verifying structure (headings, links, schema, word counts) is NOT sufficient. The operator caught three visual defects on the irvingwellnessclinic filler page (2026-07-14) that structural checks missed: hero relying on a DEAD image host (via.placeholder.com returns 000 — despite the design skill recommending it), FAQ accordion titles rendering at 1.1px (builder emitted px instead of em), and a pricing card far thinner than the site's real card anatomy.

**Why:** render_probe and get_elementor_widgets confirm structure, not appearance. The generated CSS file and image host availability are the ground truth for what a human sees. The user said: "do proper research and check everything."

**How to apply:**
1. After every build/import: run `audit_page_design(post_id)` (13 checks incl. WCAG contrast + button/section blend) — not just page_robustness_audit.
2. Curl the generated stylesheet `wp-content/uploads/elementor/css/post-{id}.css` and eyeball font-size / background / color values for sanity (e.g. a 1.1px font).
3. Curl -I every external image URL (placeholders included) — via.placeholder.com is DEAD; use existing same-site media from the parent pillar instead (the [[irvingwellnessclinic-design]] skill §23 needs updating).
4. Before styling a new page, extract the SIBLING page's rendered CSS for its hero/cards (not just get_page_style_context, which misses container-level backgrounds).
5. Compare card anatomy against the operator's real cards (price pills, includes/results lists, per-SKU BOOK popup buttons), not against the builder's minimal template.
