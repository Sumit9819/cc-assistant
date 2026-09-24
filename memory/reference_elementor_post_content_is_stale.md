---
name: reference-elementor-post-content-is-stale
description: On Elementor pages post_content is a stale render snapshot that does NOT render — WP search over-counts ~3x; only _elementor_data hits are real
metadata: 
  node_type: memory
  type: reference
  originSessionId: fdf022d6-a851-4bf3-b1c5-59296fa18558
  modified: 2026-08-19T04:25:54.687Z
---

`post_content` on an Elementor-built page is a **stale rendered snapshot**, not the live source. Elementor renders `_elementor_data` and discards `post_content`. The snapshot is only refreshed when the page is saved in the Elementor editor, so it can hold copy that was replaced months ago.

**Consequence:** `list_posts(search=...)` and any audit that reads `post_content` will report matches that render nowhere. On the 2026-08-19 board-certified sweep the WP search returned Irving 41 / Lufkin 46 / White Rock 55 matches; the real live counts were roughly a third of that.

**Verified with a positive control, not assumed:**
- Irving post 228 — phrase present in `_elementor_data` → `render_probe(228, extract=headings)` shows the live `h3` "Board-Certified Team". Proves the probe detects it.
- Irving post 1072 — phrase present ONLY in `post_content` → `render_probe(1072)` shows no such heading and no such text. Proves the snapshot does not render.

**How to work:**
- Fetch with `get_post(id, slim=false)` and search **`elementor_data` only**. Ignore `content` unless `has_elementor` is false.
- Text also hides in `image.alt`, flip-box `description_text_b`, slider `slides[]`, and accordion/nested-tab `tabs[]` — walk arrays of dicts, not just top-level string settings.
- Classic (non-Elementor) posts are the opposite case: `post_content` IS live, so patch it with `draft_patch_post_content`.
- Post IDs are duplicated across the sister sites, so key any local scan cache by host, not by id. See [[project_wp_plugin]] and [[feedback_no_guessing_epistemic_discipline]].
