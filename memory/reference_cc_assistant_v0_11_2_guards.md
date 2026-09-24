---
name: reference-cc-assistant-v0-12-0-guards
description: cc-assistant v0.12.0 hard guards added after the hormone-page invisible-text + duplicate-FAQ regression — what they block and how to override per call
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

v0.12.0 turns four previously soft warnings into hard 422 errors at queue time, and adds one new tool. Each guard has a named override flag so a deliberate variant can still ship.

**Builder-side preventative**
- `Elementor_Builder::sanitize_unsafe_text_globals( &$settings, $widget_type )` runs inside `add_widget` and `build_child_node`. Strips `__globals__.title_color` / `description_color` / `text_color` / `heading_color` / etc. when no explicit hex is set on the same field in `settings`. Prevents the white-on-white inheritance from a sample widget's globals.

**Endpoint hard blocks**
- `draft_add_elementor_widget` and `draft_add_elementor_container` return 422 `accessibility_contrast` when `check_widget_accessibility` finds contrast < 3.0. Override: `override_a11y=true`.
- `draft_add_elementor_container` (root-level only) returns 422 `position_required` when `position` is unset AND the page has ≥ 6 root containers. Override: `override_position=true`.
- `draft_add_accordion_item` returns 422 `duplicate_accordion_item` when `similar_text` ≥ 80% OR normalized-title is identical to an existing item. Override: `override_dup=true`.

**New tool**
- `draft_remove_accordion_item( post_id, accordion_widget_id, item_id )` queues a single-item removal; full rollback supported. See [[reference-accordion-dedup-workflow]].

**Bypass discipline:** only set an override when the reviewer has explicitly accepted the warning in the same turn. Default to NOT bypassing — the warning usually points at a real bug.
