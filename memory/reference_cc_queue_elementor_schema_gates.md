---
name: reference-cc-queue-elementor-schema-gates
description: What the cc-assistant queue refuses when importing an Elementor tree (Elementor 4.2.3 / Pro 4.x) - device-suffix and hide_* keys, dead button hover key, left/right vs start/end - plus the evidence gates (whoami within 10 min, get_post?slim=false for templates)
metadata:
  type: reference
---

Learned 2026-09-18 building Lufkin's native header (template 7151) through
`POST cc-assistant/v1/posts/<id>/elementor-import`. Each of these cost a round trip;
validate locally first with `D:\cc-assistant\tools\ekit-removal\validate-tree.py`,
which mirrors `CC_Assistant_Widget_Schema::validate_settings`.

**Refusals (422 `unverified_elementor_settings`; the message often carries no key names):**
- **Any device-suffix key**: `padding_mobile`, `width_mobile`, `flex_justify_content_mobile`, `icon_size_mobile`, `space_between_tablet`... This control stack registers device variants for only ~26 controls per widget (sticky/transform/animation, `_element_width`, social-icons `align`). `responsive_base()` strips the suffix, the base has no `responsive` flag, and it blocks. **Put responsive rules in one `custom_css` block instead** (the control exists on containers and widgets with Pro) and give elements `_element_id` to target.
- **`hide_desktop` / `hide_tablet` / `hide_mobile`**: real Elementor controls, but the validator reads them as device variants of a non-existent control "hide", so they come back as `unknown_setting_key`. Same fix: CSS.
- **`background_hover_color` on the button**: does not exist in this version. Use `button_background_hover_color`, and set its gate `button_background_hover_background: "classic"` or it is refused as `inert_setting`. (The eroflufkin-design skill §21/§23 still says to set both; the live schema wins.)
- **`left` / `right` alignment values**: Elementor 4.x options are `start`, `center`, `end` for `icon_align`, image `align` and friends. Legacy trees keep rendering "left", new writes are rejected as `invalid_option_value`.
- Every gated control must have its gate satisfied **in the same settings object**: `background_color` needs `background_background`, `boxed_width` needs `content_width: "boxed"`, `width` needs `content_width: "full"`, flex keys need `container_type: "flex"` (default), typography children need `*_typography_typography: "custom"`, `dropdown_box_shadow_box_shadow` needs `..._type: "yes"`.

**Evidence gates, in order, or the POST 400s:**
1. `evidence_identity_required` - `whoami` on that site **within 10 minutes**, same user and connection. Put it first in the same batch.
2. `page_evidence_required` - a qualifying read of the target: `verified_page_audit` for a published page, `get_post` for a draft or `elementor_library` template. **The read must not be auto-slimmed**: a big template returns `auto_slimmed: true` and does not qualify, so call `posts/<id>?slim=false`. `slim` and `widget_id` must be unset/false, and the post fingerprint must not move between the read and the write.
3. Templates additionally need the plugin option **"Allow editing Theme Builder templates"** (`cc_assistant_allow_template_editing`); it is ON for Lufkin.

Import options worth knowing: `dry_run: true` validates and reports a widget inventory without queueing; `regenerate_ids` defaults true; `strip_images` defaults false. The response warns that approving a template import changes every page using that template.

Related: [[project-er-elementskit-removal]], [[reference-cc-rest-via-browser-nonce]].
