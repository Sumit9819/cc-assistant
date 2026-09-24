---
name: feedback-match-page-style-not-defaults
description: "When adding widgets, sample the page's actual existing pattern instead of imposing centered/safe defaults — user wants new widgets to look like they belong on the page"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

When inserting new Elementor widgets, ALWAYS pull alignment, typography, and color from an existing sample on the same page before deciding defaults. Do not center, do not impose "safe" sizes, do not pick brand colors from a static profile when the page itself has a working example to clone.

**Why:** 2026-05-14 — Hormone-page Delivery Methods + Timeline sections shipped with `_element_self_align: center` on the H2 widgets. That property is silently ignored by Elementor flex containers, so the widgets sat on the left edge despite text-align center inside them. The page's actual working pattern (visible on `c77c17b` "Your Journey to Restored Vitality") uses `_flex_align_self: center` + `_element_custom_width: 850px` + font_weight 300. I picked defaults instead of sampling the page, shipped a broken section, then doubled down on the wrong centering property in v0.13's auto-wrap. User pushed back twice on the same root cause.

**How to apply:**
1. Before any new heading / text-editor / icon-box widget, call `get_page_style_context(post_id)` and read `heading_sample`, `text_editor_sample`, and `icon_box_sample`.
2. Clone the alignment props (`_flex_align_self`, `_element_width`, `_element_custom_width`), typography (font_family, weight, size), and color globals (`__globals__.title_color`) from the sample into the new widget's settings. Caller-provided values still win — explicit > inherited.
3. v0.13.1's `Elementor_Builder::apply_heading_sample_to_children` does this automatically when `post_id` is threaded through. Confirm post_id is being passed.
4. Elementor flex-item alignment is `_flex_align_self`, NOT `_element_self_align`. The latter looks plausible in the JSON but does nothing on flex parents.

See also [[reference-cc-assistant-v0-12-0-guards]] for the contrast/position/dup guards.
