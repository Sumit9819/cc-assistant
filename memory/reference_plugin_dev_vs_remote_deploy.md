---
name: reference_plugin_dev_vs_remote_deploy
description: Editing plugin code in the local plugintesting install does NOT change the running plugin on the remote production sites — must build + deploy the zip
metadata: 
  node_type: memory
  type: reference
  originSessionId: cf6b72ff-7286-4f35-bac6-cc72e684abbc
---

The plugin SOURCE lives in the standalone workspace at `D:\cc-assistant\wp-content\plugins\cc-assistant` (since 2026-09-06; the earlier Local-by-Flywheel copy under `Local Sites\plugintesting` is STALE and Local is retired). No WordPress runs in the workspace. The nine cc-assistant MCP servers in `D:\cc-assistant\.mcp.json` connect to the **remote production sites**, each running its own DEPLOYED copy of the plugin. Built zips go to `D:\cc-assistant\dist\` via the plugin's own `build-zip.py`.

**Consequence:** editing a PHP file here fixes the LOCAL plugintesting copy only. It has ZERO effect on a remote site's running code until the rebuilt zip is uploaded to that site. So after a code fix, a remote MCP lint/check keeps using the OLD behavior — do not assume the edit "took." Symptom I hit: fixed `address_consistency` ([[reference_address_consistency_false_positive]]), re-ran the remote dry-run, still failed because erofwhiterock.com was still on v0.35.3.

**Workflow:** edit → `php -l` ([[reference_local_php]]) → bump version + changelog → build zip with Python zipfile ([[reference_zip_packaging_gotcha]], build it yourself per [[feedback_zip_plugin_yourself]]) → hand the user the zip path to upload on each production site (via File Manager, never WP-Delete per [[reference_uninstall_on_duplicate_delete]]). I cannot deploy remotely myself.

## The OTHER half: `bin/` runs locally and needs an MCP RESTART, not a zip

The zip only ships the **WP-side** code (`includes/`, `admin/`, `cc-assistant.php`). `bin/mcp-server.php` and `bin/warehouse.php` run on THIS machine as the long-lived MCP server process, which `require`s them once at startup. Editing them changes nothing until the MCP server restarts — a plugin upgrade will not pick them up.

**Hit 2026-08-20 (v0.71.0).** Deployed to irvingwellnessclinic and verified the WP half live: URL resolution fixed (REVIEW rows 2 -> 0, both resolving to 6356 / 8139), `page_canonical_hash` migration applied, outcome `confidence` block rendering. But `cc_commodity_fold_redirects()` lives in `bin/warehouse.php`, so impressions did NOT fold — post 6356 still read 6,501 instead of the predicted 6,711. Not a code bug; the running process held the pre-edit warehouse.php.

**Rule:** when a change touches BOTH sides, say so up front and give two steps — (1) upload the zip, (2) restart the MCP server. Verifying only after step 1 makes a working change look broken. Diagnose this by checking whether the symptom is WP-side (works) vs `bin/`-side (stale).
