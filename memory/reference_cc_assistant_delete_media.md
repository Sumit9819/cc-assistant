---
name: reference-cc-assistant-delete-media
description: v0.78.0 delete_media tool — the only irreversible tool; three guards including a pending-changes check
metadata: 
  node_type: memory
  type: reference
  originSessionId: 9c6f1bb7-e1aa-47b7-a180-e9cd066c7b98
  modified: 2026-09-04T07:56:00.916Z
---

cc-assistant v0.78.0 adds `delete_media` (POST /assets/delete). It is the only
irreversible tool in the plugin, so the guards sit in FRONT of the call:

1. **Not ours** — refuses attachments without `_cc_assistant_uploaded`. Override
   is `allow_foreign: true`, only after a human confirms the specific file.
2. **Still referenced** — checks full-size URL, every registered intermediate
   size, `_thumbnail_id`, numeric ids in `_elementor_data`, AND unapproved rows
   in `wp_cc_pending_changes`. That last one matters: draft-only writing means a
   graphic can be uploaded and queued but not live, so a live-tables-only check
   would call it unreferenced and deleting it would break the next approval.
3. **Not confirmed** — `confirm: false` (default) is a dry run.

Max 50 ids per call. `wp_delete_attachment($id, true)` — files leave disk.

`bin/mcp-server.php` changed, so this needs an **MCP restart**, not just the zip.

Related: [[reference_plugin_dev_vs_remote_deploy]], [[feedback_zip_plugin_yourself]]
