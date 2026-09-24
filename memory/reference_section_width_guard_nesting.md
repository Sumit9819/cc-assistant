---
name: section-width-guard-nesting
description: "cc-assistant v0.31 section-width guard fires on _element_custom_width below 700px regardless of nesting depth, but inner-column / stat-tile / grid-cell headings legitimately need narrower widths. Plugin v0.32 TODO — make the guard nesting-aware."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

The v0.31 section-width guard refuses any heading widget with `_element_custom_width` below ~700px, citing the global "section text caps at 800-900px" rule. But that rule was intended for ROOT-LEVEL headings — H1, H2, intro paragraphs that span a wide section.

When a heading is nested inside a narrow inner container (40%-wide stat panel, 200px-wide stat tile, mini-header inside a column), the parent already constrains it. Adding `_element_custom_width` below 700px is the correct authoring choice, and removing the setting (so it inherits parent width) is also correct. The guard's blanket refusal forced me to strip useful width controls from the chest pain Risk Factors section (post 2516).

**Plugin v0.32 TODO:**
- In `class-elementor-builder.php` width guard, walk up the parent chain. If the heading lives inside a container with explicit `width` < 700px (column or tile), allow narrower `_element_custom_width`.
- Or: skip the check when the widget's depth > 2 (only ROOT children get strict-checked).
- Or: cap the check at section depth and allow nested headings to inherit/override naturally.

**Operator workaround until v0.32:** Set EVERY heading widget (root or nested) to `_element_width: "initial"` + `_element_custom_width: 850px` + `_flex_align_self: "center"`. The guard requires the field present AND in range — omitting also fails with "missing `_element_custom_width`". On nested headings the parent container width constrains the rendered width naturally, so 850px acts as a harmless max-width hint.

Related: [[reference-cc-assistant-v0-31-audit-gaps]], [[feedback-section-max-widths]].
