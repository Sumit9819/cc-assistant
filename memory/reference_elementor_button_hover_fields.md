---
name: elementor-button-hover-fields
description: "Elementor button widget hover-bg field is `button_background_hover_color`, NOT `background_hover_color`. The latter is silently ignored by Elementor's render, so the bg doesn't change on hover but the text DOES change — producing invisible text when hover_color matches default bg."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

The Elementor button widget uses these field names — ALWAYS check both before queueing any button update:

| What | Default state | Hover state |
|---|---|---|
| Text color | `button_text_color` | `hover_color` |
| Background color | `background_color` | **`button_background_hover_color`** (NOT `background_hover_color`) |
| Border color | `border_color` | `button_hover_border_color` |

**The trap:** `background_hover_color` looks like the right name (mirrors `background_color`) but Elementor's button widget silently ignores it. If you set only `background_hover_color` + `hover_color`, the bg DOESN'T change on hover but the text DOES — so a button with `background_color: #DA1212` + `hover_color: #DA1212` renders as red text on red bg = invisible text on hover.

**Why this matters:** Bit by it on the chest pain page hero (pending #725). User reported "when hovered on the button, the text disappear, meaning, hover text matches with the bg color." Looked exactly like a mistake in color choice; was actually a field-name silent failure.

**How to apply:**
- Every button add/update must set `button_background_hover_color` if hover state matters.
- Confirm via `get_post widget_id=` after apply: the saved settings should include `button_background_hover_color`, not `background_hover_color`.
- Plugin v0.32 TODO: add a pre-queue lint that flags buttons setting `background_hover_color` (or setting `hover_color` without `button_background_hover_color`) and refuses with code `button_hover_field_typo`.

Related: [[reference-cc-assistant-v0-31-audit-gaps]].
