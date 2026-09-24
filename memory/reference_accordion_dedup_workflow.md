---
name: reference-accordion-dedup-workflow
description: "How to dedupe nested-accordion items via cc-assistant — requires v0.11.2+ because settings.items[] AND elements[] both need synced edits"
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

To remove duplicate items from an Elementor nested-accordion (e.g. FAQ on a service page):

1. `get_post(post_id, widget_id="<accordion>")` to enumerate `settings.items[]` with their `_id` fields and `item_title`.
2. For each duplicate, call `draft_remove_accordion_item(post_id, accordion_widget_id, item_id)`. This queues one pending entry per item; reviewer approves them as a batch.

Why not `draft_update_elementor_widget` with the cleaned items[]: `walk_and_update` runs `array_merge` on `$el['settings']` only — settings.items (string-keyed in settings) gets replaced, but elements[] (which holds the answer containers; sibling of settings, not inside it) is unreachable. Result would be 12 titles paired with 22 answer containers → positional mismatch → empty bodies.

Plugin version gate: `draft_remove_accordion_item` ships in v0.12.0 ([[reference-cc-assistant-v0-12-0-guards]]). On older installs the dedup must be done by hand in the Elementor editor.

See also [[reference-elementor-builder-v0-11]] for the broader native-builder toolset.
