---
name: column-container-explicit-align
description: "Every nested column-direction flex container that holds left-aligned text content MUST explicitly set `flex_align_items: flex-start`. Defaults silently center widgets inside."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

When building Elementor flex containers with `flex_direction: column`, the `flex_align_items` field controls how children align on the CROSS axis (horizontal, since the main axis is now vertical). If you don't set it, Elementor defaults to "stretch" or "center" depending on parent context — and children that DON'T have their own align property (like text-editor widgets) silently render center-aligned.

**Why this matters:** Caught on chest pain page Section 6 (post 2516). My 5-step workup timeline had each step as a 2-col row, with the right-column inner container being a `flex_direction: column` holding chip + heading + body. I set `align: "left"` on the heading widget (worked) but the chip text-editor and body text-editor inherited the column container's default cross-axis alignment, which rendered center. User had to fix it manually. They said "be cautious with your design."

**How to apply:** On EVERY `flex_direction: column` container that holds text content, add:
```json
"flex_align_items": "flex-start"
```
Even if the children "look like" they should be left-aligned, set the alignment explicitly. Don't trust inheritance.

Heading widgets — they have their own `align: "left"|"center"|"right"` property, which CAN override container alignment. text-editor widgets do NOT — they rely on `<p style="text-align:...">` HTML OR the parent container's cross-axis alignment.

Plugin v0.32 TODO: pre-queue lint that warns when a `flex_direction: column` container is missing `flex_align_items`, especially if it holds text-editor children without inline `text-align`.

Related: [[feedback-match-page-style-not-defaults]], [[reference-cc-assistant-v0-31-audit-gaps]].
