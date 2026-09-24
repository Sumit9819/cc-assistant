---
name: reference-elementor-responsive-keys-rejected-by-queue
description: cc-assistant 0.89.12 queue validator rejects responsive (tablet/mobile) container keys and hide_tablet/hide_mobile even though widget_schema lists them; desktop keys queue fine
metadata:
  type: reference
---

Observed on mammothmachinery.ca 2026-09-23 (plugin 0.89.12, local bridge 0.89.5):

- `hide_desktop` / `hide_tablet` / `hide_mobile` on a container: widget_schema LISTS all three,
  but draft_update_elementor_widget refuses `hide_tablet` and `hide_mobile` as unknown_setting_key.
- `width_tablet` on a container: refused with "Control width has no registered responsive
  variant width_tablet". `_element_custom_width` is also refused on containers (it is a widget
  control; containers use `width`).
- Plain desktop `width` queues fine.

Same family as the form-widget STYLE-controls gap recorded in the mammoth site Decisions.

**How to apply:** don't plan a change that depends on tablet or mobile values going through the queue.
Either do the desktop value by queue and hand the operator the one-click tablet or mobile step
in the Elementor editor, or, when hiding something temporarily, remove it with
draft_remove_elementor_widget after exporting a backup to D:\cc-assistant\backups\<site>\.
Also check: stale local bridge (0.89.5 vs site 0.89.12) may be the cause; re-test after
operator_brain_pull(what=bridge) + MCP restart. Related: [[feedback_probe_discipline_positive_controls]].
