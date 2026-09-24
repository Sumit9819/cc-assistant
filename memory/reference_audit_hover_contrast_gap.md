---
name: audit-hover-contrast-gap
description: cc-assistant v0.31 WCAG contrast check only scans default-state button colors; hover-state contrast (button_background_hover_color × hover_color) is NOT checked. Plugin TODO for v0.32 — extend the check.
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

The v0.31 `audit_page_design` WCAG check walks `button_text_color` × `background_color` (default state) but does NOT evaluate `hover_color` × `button_background_hover_color` (hover state).

**Why this matters:** Caught on chest-pain hero (post 2516). A button with `background_color: #DA1212` + `hover_color: #DA1212` and NO explicit `button_background_hover_color` renders default state correctly (white text on red bg, contrast 4.95) but on hover the bg stays red while the text turns red → contrast 1.00, invisible text. The pre-queue guard didn't refuse and the audit didn't flag it after apply.

**Plugin TODO for v0.32:**

1. **Pre-queue lint** — In `apply_elementor_widget_add` / `apply_elementor_widget` / `apply_section_content_replace`, when a button widget has `hover_color` set and either `button_background_hover_color` is missing OR `button_background_hover_color` resolves to the same hex as `hover_color`, refuse with code `button_hover_contrast` and message naming the colliding fields.
2. **Audit check** — In `class-page-audit.php` WCAG walker, add a per-button hover-state pair: `(hover_color OR button_text_color) × (button_background_hover_color OR background_color)`. Flag any pair below 4.5.
3. **Also flag** — if widget has `background_hover_color` (the wrong field name, see [[reference-elementor-button-hover-fields]]) — that's a 100% silent failure and should be a hard error.

**Operator workaround until v0.32 ships:** After every button add/update, manually inspect hover state in browser. The apply pipeline cannot be trusted to catch this yet.

Related: [[reference-elementor-button-hover-fields]], [[reference-cc-assistant-v0-31-audit-gaps]].
