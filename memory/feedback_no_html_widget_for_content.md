---
name: feedback-no-html-widget-for-content
description: "Use native Elementor widgets (heading/text-editor/icon-box/icon-list/price-list) for content sections, not custom HTML widgets — even for tables and comparison blocks"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

Build content sections on service pages with native Elementor widgets only. No `html` widget for tables, comparisons, timelines, or feature grids.

**Why:** Custom HTML widget content (1) doesn't get minified by SiteGround Speed Optimizer, (2) breaks the Elementor editing UX for the client, (3) doesn't inherit Elementor global colors/typography, (4) is harder to maintain. The Botox 9925 HTML-widget approach was a one-off because that page is unusually image-heavy and timeline-styled; user explicitly does NOT want it as the default pattern.

**How to apply:**
- For comparison tables: use a row of containers, each holding `heading` + `icon-list` + optional `price-list`. Same pattern as the existing SKU cards (Bioidentical HRT / Thyroid Optimization / etc.).
- For step timelines: use `icon-box` widgets in a flex row (same pattern as Lab Draw / Clinical Analysis / Your Custom Protocol / Ongoing Optimization already on hormone 623).
- For reference tables (lab markers, dosing schedules): use `price-list` widget with marker name as item title, range/range-text as item description, optional badge.
- For FAQ items: `nested-accordion` (already used). Plugin can't add accordion children via MCP — hand the user the items to paste in Elementor admin.

**Plugin limit reminder:** [[reference_plugin_walk_and_update_limit]] — `draft_update_elementor_widget` only merges into `widget.settings`. It cannot add new widgets to a container or add children to accordions. New widgets must be built manually in Elementor admin; provide step-by-step widget specs the user can drop in.
