---
name: reference-cc-assistant-v0-18-empty-tree-bootstrap
description: "cc-assistant v0.18.0 fixes the silent failure where 8 Elementor sections queued on a fresh draft_create_post page all fail at apply with \"Post has no Elementor data\" + adds _elementor_edit_mode='builder' graduation on first save"
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

**The failure that prompted this:** Building the Laser Genesis service page (post 9983) via draft_create_post + 8 root-level draft_add_elementor_container calls. All 8 sections queued successfully (pendings 573-580). When the operator clicked Approve, 4 of 12 applied (publish_draft + 3 SEO meta) and 8 failed with `Post has no Elementor data.`

**Root cause:** `draft_create_post` creates a page with status='draft' but does NOT initialize the `_elementor_data` postmeta. When `apply_elementor_container_add` runs, it calls `CC_Assistant_Elementor_Builder::load_tree($post_id)` which returns `WP_Error('no_elementor_data')` on empty meta — refusing to bootstrap a brand-new tree even when parent_id="" (which is a perfectly valid request: "add this as the page's first root container").

**v0.18 fixes three issues:**

1. **`load_tree($post_id, $allow_empty = false)`** ([class-elementor-builder.php](wp-content/plugins/cc-assistant/includes/class-elementor-builder.php)). New optional second parameter — when true and `_elementor_data` is empty, returns `[]` instead of WP_Error. Default false keeps UPDATE/REMOVE paths strict (they DO need existing data to mutate).

2. **`apply_elementor_widget_add` and `apply_elementor_container_add`** ([class-apply.php](wp-content/plugins/cc-assistant/includes/class-apply.php) lines 627 + 788) now call `load_tree($post_id, true)`. Other apply paths (update, remove, accordion ops) intentionally keep strict mode — they require existing data.

3. **`save_tree()` graduates the page to `_elementor_edit_mode='builder'`** on every save. Without this flag set, Elementor renders the classic post_content body instead of the saved Elementor tree, so the new widgets stay invisible on the front-end even though they're persisted. Setting `edit_mode=builder` on first write ensures the page becomes Elementor-rendered the moment it gets its first widget.

**Queue-time validation was NOT affected:** the REST endpoints `handle_draft_elementor_container_add` only calls `load_tree()` when `parent_id !== ''` (it needs to verify the parent exists). For root containers (`parent_id=""`), validation skips and the pending queues successfully — which is why we got the surprise at apply time, not queue time.

**The architectural lesson:** ADD operations should bootstrap missing state. UPDATE/REMOVE should refuse missing state. Conflating these two regimes in one strict loader meant the first widget on every new page silently failed for 4 weeks.

**Operator impact:** any pending queued before v0.18 against a fresh draft_create_post page will be in `apply_failed` status. They have to be re-queued under v0.18 to land cleanly. Once the page has its first container, subsequent adds work as normal.
