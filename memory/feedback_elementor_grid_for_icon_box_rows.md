---
name: feedback-elementor-grid-for-icon-box-rows
description: Icon-box rows on irvingwellnessclinic service pillars must use container_type=grid with grid_columns_grid Nfr — flex-direction=row + flex-wrap=wrap makes them stack vertically because Elementor flex children default to content-width sizing
metadata: 
  node_type: memory
  type: feedback
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

**Rule:** When building a row of icon-boxes (or any same-size cards) on this site's service pillars, the parent inner container must be a **CSS Grid** container, not a flex container.

**Why:** 2026-05-18 — built the Laser Genesis pillar (post 9983) with row containers using `flex_direction: row` + `flex_wrap: wrap`. Result: all icon-box rows rendered as a single vertical column, full-width, every box stacked. User flagged "everything left-aligned, not properly structured." Root cause: Elementor flex containers don't auto-distribute children to equal widths. Without explicit `_element_custom_width` or `flex-grow` set on each child, icon-boxes take their content-width — usually wider than 1/3 or 1/4 of the row — so flex-wrap kicks in and they stack.

IV pillar (post 137) gets it right by using `container_type: "grid"` with `grid_columns_grid: {unit: "fr", size: N}`. Each icon-box auto-sizes to 1fr (equal share). Works regardless of content length, no width math.

**How to apply:**

Inner row container settings for icon-box rows:
```json
{
  "container_type": "grid",
  "presetTitle": "Grid",
  "presetIcon": "eicon-container-grid",
  "content_width": "full",
  "grid_columns_grid": {"unit": "fr", "size": N, "sizes": []},
  "grid_rows_grid": {"unit": "fr", "size": 1, "sizes": []},
  "grid_gap": {"column": "20", "row": "20", "isLinked": true, "unit": "px", "size": 20}
}
```

Where N is the number of cards:
- 4 icon-boxes (indications) → `size: 4`
- 3 icon-boxes (Why Choose / What to Expect / Safety) → `size: 3`
- 5 icon-boxes (IV pattern with allergies/jet-lag/etc.) → `size: 5`

The 2-column grid for the local-section area buttons uses the same pattern with `size: 2`.

**Do NOT use:** `flex_direction: row` + `flex_wrap: wrap` for equal-width card rows. It works for variable-width content (button row, inline-list), not for sized cards.

**2026-06-11 reinforcement (erofwhiterock hub 3505, user-flagged):** TWO more rules, violated despite this file:
1. `grid_rows_grid: {"unit":"fr","size":1}` is **MANDATORY**, not optional — omitting it makes Elementor fall back to its "2 rows" editor default (user sees "2 selected by default") and 1fr default rows stretch cards unevenly. The template above always had it; use the WHOLE template, never just the columns half. Also set `grid_auto_flow: "row"`.
2. **Match the page's existing column count — never invent one to fit a group on one row.** Hub groups got 5fr/6fr so each category fit one line → ~190px crushed cards, visibly off-pattern vs the site's 4-col rhythm. Standard: 4 desktop / 3 tablet / 2 mobile; let groups wrap (4+1, 4+2 is fine). Plugin gate gap noted: audit_page_design does not catch missing grid_rows_grid or column-count deviation — candidate checks for a future release.

Linked: [[reference-cc-assistant-v0-19-template-clone]] (build_service_page deep-clones IV's container settings byte-for-byte, including grid_columns_grid — solves this category of bug for whole-page builds).
