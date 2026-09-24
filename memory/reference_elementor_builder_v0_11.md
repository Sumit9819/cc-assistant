---
name: reference-elementor-builder-v0-11
description: cc-assistant v0.11.0 adds Elementor tree mutation tools — use draft_add_elementor_widget / draft_remove_elementor_widget / draft_add_elementor_container / draft_add_accordion_item instead of HTML widgets
metadata: 
  node_type: memory
  type: reference
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

cc-assistant v0.11.0 closed the prior plugin limit where the MCP could only update existing widget settings. Four new MCP tools are now available:

| Tool | Purpose |
|---|---|
| `draft_add_elementor_widget` | Insert a new heading/text-editor/icon-box/icon-list/price-list/button/image widget into an existing container. Args: post_id, parent_id, widget_type, settings, position?, summary?, reasoning? |
| `draft_remove_elementor_widget` | Splice a widget out by id. Stashes the removed node for rollback. Args: post_id, widget_id |
| `draft_add_elementor_container` | Insert a new section/container/column with optional pre-built children (recursive). Args: post_id, parent_id ("" for root), el_type? ("container"/"section"/"column"), settings, children? (array of {type, widgetType?, settings, children?}), position? |
| `draft_add_accordion_item` | Add a Q+A item to a nested-accordion widget (fixes the prior FAQ-items-unreachable limitation). Args: post_id, accordion_widget_id, title, content_html?, position? |

**When to use vs. draft_update_elementor_widget:**
- Use the new tools to BUILD net-new structure (new sections, new widgets, new FAQ items).
- Use `draft_update_elementor_widget` to TWEAK an existing widget's settings (edit text, change color, swap a link).

**Pending UI shows distinct previews per type:**
- ADD widget: green badge, parent container id, position, settings preview, full JSON in collapsible
- REMOVE widget: red badge, widget id, type, preview of content being deleted
- ADD container: purple badge, parent id, el_type, list of children with widgetType + 120-char preview
- ADD FAQ item: green badge, accordion id, question, rendered answer body

**Plugin limitation lifted:** [[reference_plugin_walk_and_update_limit]] no longer fully applies — accordion items + new widgets are now reachable. The legacy walk_and_update still only merges settings of existing widgets.

**No HTML widget rule preserved:** [[feedback_no_html_widget_for_content]] still holds — assemble sections with these new native tools, never the html widget.
