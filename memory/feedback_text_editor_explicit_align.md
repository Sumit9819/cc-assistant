---
name: text-editor-explicit-align
description: "Every text-editor widget must explicitly set `align: \"left\"` (or appropriate value). Without it, Elementor renders the content centered even when the parent container is left-aligned."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

The text-editor widget has TWO separate alignment behaviors that combine:

1. **Where the widget BLOCK sits** in its parent — controlled by the parent container's `flex_align_items` (cross-axis). See [[feedback-column-container-explicit-align]].
2. **How the CONTENT inside the widget aligns** — controlled by the widget's OWN `align` setting (left / center / right / justify).

These are independent. A text-editor inside a `flex_align_items: flex-start` container sits at the left edge (the block is left), but the `<p>` content inside defaults to centered (the text is center-aligned within that left-edge block) unless the widget's own `align` is set.

**Why this matters:** Section 7 (ER vs Urgent Care comparison) on chest pain page (post 2516). The Emergency Room card had heading "Emergency Room (ER)" left-aligned correctly (the icon-box `text_align: "left"` worked), but every body text-editor below it rendered center-aligned. User had to fix it manually. They said "think correctly next time." First time the same alignment issue surfaced in a different widget — the column-container rule alone wasn't sufficient.

**How to apply:** Every `text-editor` widget gets explicit `align: "left"` (or center/right as intentional). Don't trust default. The full belt-and-braces pattern for left-aligned card content is:

```json
{
  "type": "container",
  "settings": {
    "flex_direction": "column",
    "flex_align_items": "flex-start"   // block-level
  },
  "children": [
    {
      "type": "widget",
      "widgetType": "text-editor",
      "settings": {
        "editor": "<p>...</p>",
        "align": "left"                  // content-level — DO NOT OMIT
      }
    }
  ]
}
```

Heading widgets have their own `align: "left"` property. Icon-box has `text_align: "left"`. Each widget type has its own — none of them inherit from container.

**Plugin v0.32 TODO:** Pre-queue lint that flags text-editor widgets with no `align` property AND container parent that's not centered. Also include heading widgets with no `align`. Should refuse with `widget_align_missing` code.

Related: [[feedback-column-container-explicit-align]], [[feedback-match-page-style-not-defaults]].
