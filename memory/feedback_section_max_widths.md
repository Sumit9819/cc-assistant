---
name: section-max-widths
description: "GLOBAL rule for every Elementor site. New root sections must constrain to max-width 1200-1300px; the heading + intro text block inside to 800-900px. Default content_width=\"full\" without an explicit boxed_width spreads edge-to-edge on most themes and reads as broken on desktop."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 0148947e-9e56-42d0-abd7-8d217c330a37
---

**This rule applies to every site, not just one. It is a general Elementor + visual-design principle.**

When adding a NEW root-level container or section via `draft_add_elementor_container` (or wrapping one via `draft_add_elementor_widget`):

- **Outer section / root container** = max-width **1200-1300px**, centered. Use Elementor 3.x `boxed_width: { unit: "px", size: 1280 }` (or the kit's boxed default).
- **Heading + intro text block** (the H2 + short text-editor above a card grid) = max-width **800-900px**, centered. Use a sub-container with `width: { unit: "px", size: 850 }` and `_margin: { auto, auto }`, OR set on the heading widget itself: `_element_width: "initial"` + `_element_custom_width: { unit: "px", size: 850 }` + `_flex_align_self: "center"`.
- **Card grid container** (icon-box rows, etc.) = the full 1200-1300 width.

**Why:** Default Elementor `content_width: "full"` doesn't apply a kit-level max-width on most themes — the section bleeds to viewport edge and looks broken on wide desktop monitors. The user has established this width rhythm across every site they own and a new section without it looks immediately wrong, even before the score impact.

**Mandatory pre-add discipline:**
1. **Before any** `draft_add_elementor_container` at root, sample an existing well-built root container on the page with `get_post widget_id=<container_id>` to confirm the exact `boxed_width` the site uses (1200, 1280, 1300, etc.).
2. Mirror that value on every new section.
3. Constrain heading widgets inside to ≤900px.

**Origin (2026-05-20):** Got bitten on irvingwellnessclinic.com queueing pendings #716/#717/#718 (FDA + Myths + Timeline sections on Microneedling 9991). Used `content_width: "full"` with no `boxed_width` — sections bled edge-to-edge. User flagged: "everything should be under 1200px or 1300px not more than that. Also heading text box should be of either 800 px or 900 px max." User has mentioned this rule multiple times across prior sessions; the rule was never persisted to memory until today.

**Reliable enforcement:** Memory alone is unreliable for this — the model can read the rule and still forget at queue time. A plugin-side hard lint (mirror of `wall_of_text` in v0.27.6) that refuses `draft_add_elementor_container` without proper `boxed_width` on root + width constraint on contained headings is the real fix. Ship a `section_width` lint in the next plugin version (v0.28.1+).

Pairs with [[match-page-style-not-defaults]] (always sample first) and [[layout-change-approval]] (layout-altering adds need user approval).
