---
name: wall-of-text-lint-guard
description: "cc-assistant v0.27.6 added a hard lint (wall_of_text) that refuses any text-editor widget >=200 words with no heading/list/table/image inside. Returns shape-aware widget recommendations (icon-list for enumerations, accordion for Q&A, icon-box row for steps, table for comparisons)."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 0148947e-9e56-42d0-abd7-8d217c330a37
---

`CC_Assistant_Pre_Publish::wall_of_text_check( $html, $plain )` runs as part of `lint_html_block()`. It is a hard violation — `draft_update_elementor_widget`, `draft_add_elementor_widget`, `draft_update_post_content`, and the retroactive inbox scanner all refuse with `lint_hard_violation` unless `override_lint=true`.

**Threshold:** ≥200 words in one block AND zero of `<h2>`-`<h6>`, `<ul>`, `<ol>`, `<table>`, `<img>` → fail.

**Shape detection in the failure message:**
- 2+ question marks → "Use draft_add_accordion_item for each Q+A pair."
- Step language (first/second/third/step 1/etc.) → "Build a row of icon-box widgets, or use icon-list with numbered icons."
- Enumeration cues ("include", "such as", "like", "these are") with ≥20 chars trailing → "Use an icon-list widget."
- Comparison cues (vs / versus / unlike / compared to) → "Use a heading + two-column container with icon-box widgets, or a native Elementor table widget."
- No specific shape → generic "Split the block: heading widget + multiple text-editors ≤150 words each."

**Wired into hard_violations at:**
- `class-pre-publish.php` lint_with_summary (line ~1257)
- `class-pre-publish.php` lint_post_content_change hard_check_names (line ~1923)
- `class-rest-api.php` widget update endpoint (line ~1379/1386)
- `class-rest-api.php` probe_layers (line ~3216)
- `class-pending-changes.php` retroactive scan (line ~541)

**NOT wired into:**
- `class-rest-api.php` line 4295 (SEO meta titles — too short to ever fail).
- `class-build-service-page.php` (template clones are presumed well-structured).

**Origin (2026-05-20):** Got bitten on homepage post 8 (irvingwellnessclinic.com). To push depth bucket 17→25 on helpful_content_score, dumped 300 words into a single text-editor widget. User pushed back twice ("wall of text" → "now feels messy"). Real fix was structural: the formula was depth-biased AND the model had no guard against wall-of-text instinct. Plugin patch 0.27.5 fixed the formula (homepage word-count threshold lowered to 800). Plugin patch 0.27.6 added this lint to prevent the wall-of-text instinct from succeeding at queue time even when depth is needed.

**How to apply:**
- When a page needs more depth/content, do NOT expand existing text-editor widgets. Add widgets of the appropriate type instead.
- Sample existing widget styles via `get_page_style_context` before adding new ones (per `[[match-page-style-not-defaults]]`).
- The lint suggestions in the refusal message ARE the design guidance — read them.
- If the long block is intentional (rare), pass `override_lint=true` ONLY after showing the user the lint output and getting consent.

Pairs with [[feedback-layout-change-approval]] (layout-altering adds need approval) and [[reference-elementor-builder-v0-11]] (the toolchain for building native structure).
