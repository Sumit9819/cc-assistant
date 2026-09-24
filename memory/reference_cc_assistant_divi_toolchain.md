---
name: cc-assistant-divi-toolchain
description: "v0.51.0 Divi module tools — surgical edits, never hand-transcribe Divi bodies; sids-ponds bold-text root cause"
metadata: 
  node_type: memory
  type: reference
  originSessionId: e4e4eed3-3590-41bf-aad2-18859ca12214
  modified: 2026-07-21T03:44:42.597Z
---

cc-assistant v0.51.0 (built 2026-07-21) adds a Divi toolchain in `includes/class-rest-divi.php`: `list_divi_modules(post_id)` (indexed shortcode-tree inventory with previews/headings/typography attrs), `get_divi_module(post_id, index)` (one module, full attrs + inner HTML), `draft_update_divi_modules(post_id, edits[])` (splice inner_content and/or set_attrs per module server-side, all-or-nothing, re-verifies tree parse, routes through normal lint + Pending Changes queue). On Divi sites ([[sids-ponds-engagement]]) ALWAYS use these instead of draft_update_post_content — full-body transcription was the cause of slow post edits and dropped characters. Heading modules (dipl_fancy_text) hold text in the `fancy_text` attr. Divi font shorthand: `family|weight|style|…`. sids-ponds bold-paragraph bug = `text_font="|600|||||||"` on every et_pb_text (pre-existing site styling, preserved by 1:1 rewrites); fix is set_attrs {"text_font": "|400|||||||"} per text module while headings stay 700. Deploy reminder: [[plugin-dev-vs-remote-deploy]] — the remote site only gets the new tools after the operator uploads the zip.

## Two specificity traps when styling Divi / DiviFlash (found 2026-08-29)

**1. Module attrs beat custom CSS.** A DiviFlash module emits its own colour rules
at module-class + element specificity WITH `!important`, e.g.
`.dipl_woo_products_carousel_0 .dipl_single_woo_product_price ins span{color:X!important}`.
A single-class rule in `custom_css_free_form` loses and silently does nothing.
**Set colour through the native attr** (`sale_price_text_color`, `sale_text_color`,
`title_text_color`); use free-form CSS only for structure. If you must override an
attr rule, you need >= 2 classes plus `!important`.

**2. An explicitly-set padding becomes an INLINE style.** `custom_padding="11px|||||"`
renders `style="padding-top:11px"` on the row, so injected CSS cannot override it even
with `!important` - only `el.style.setProperty(...,'important')` or an attr edit wins.
Unset sides fall back to a class rule and ARE overridable, which makes this confusing:
padding-bottom responded to injected CSS while padding-top ignored it. When previewing a
padding change with Playwright, set it on the element, not via `addStyleTag`.
Format is `top|right|bottom|left|false|false`.

Woo sale prices carry a nested `<ins>` INSIDE the `<del>` when CURCY multi-currency is
active, so `ins span` selectors hit BOTH the old and new price. Scope the old one with
`del ins span` at higher specificity.
