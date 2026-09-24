---
name: WP "Delete" on a duplicate plugin folder triggers uninstall.php for the SHARED plugin slug — wipes ALL data
description: WordPress runs uninstall.php whenever the user clicks "Delete" in the Plugins list, REGARDLESS of whether another folder of the same plugin is still active. uninstall.php drops tables by table name (wp_cc_pending_changes, etc.) which is shared across all copies — so deleting one duplicate wipes data for the active copy too.
type: reference
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
**Symptom:** After cleaning up duplicate plugin folders via WP admin → Plugins → Delete, the surviving (active) copy of cc-assistant has lost all custom data — pending changes empty, snapshots empty, link graph empty (`edges: 0, graph_built: false`), site memory notes empty, GSC OAuth tokens missing.

**Cause:** WordPress runs `uninstall.php` from the deleted plugin folder on every WP-Delete action. uninstall.php drops tables by name:
```php
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cc_pending_changes" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cc_snapshots" );
// etc.
```
Table names are GLOBAL per WP install — not scoped to a folder. So deleting `cc-assistant-0.10.9-1/` (a duplicate) drops the same tables that `cc-assistant/` (the active copy) is using. All data goes.

**The active copy keeps running** (its options like `cc_assistant_db_version` are also wiped, so on next session the activator re-creates empty tables). User sees the plugin "working" but everything is empty.

**What's lost:**
- `wp_cc_pending_changes` (inbox)
- `wp_cc_snapshots` (rollback points)
- `wp_cc_edits` (history)
- `wp_cc_link_graph` (rebuildable via cron)
- `wp_cc_outcomes`, `wp_cc_clusters`, `wp_cc_link_proposals`, etc.
- `wp_options` rows for `cc_assistant_*` (GSC OAuth tokens, site memory notes, settings)

**What's preserved:**
- Post content, postmeta (Elementor data, SEO meta, etc.) — the plugin doesn't touch these on uninstall
- WordPress core, theme, other plugins

**Rebuilds automatically:**
- `wp_cc_link_graph` — cron rebuilds nightly, or call `links_rebuild` to queue immediately
- `wp_cc_clusters` — recomputed on next analysis
- `wp_cc_edits` — populates as new edits happen

**Needs manual re-do:**
- GSC OAuth: Settings → Search Console → Reconnect (full re-auth flow against the current property)
- Site memory notes (`update_site_memory_notes`): only what the human + AI re-establish in conversation

**Detection:** call `whoami` — if `last_snapshot: null`, `notes_tail: ""`, `pending_count: 0` AND `gsc_status.connected: false`, you're in this state. Combined empty signals are the fingerprint.

**Avoidance for future cleanup:** when WP Plugins shows duplicate folders for the same plugin slug, do NOT use WP-Delete on any of them. Instead:
1. Deactivate the unwanted folder via WP admin
2. Delete the FOLDER directly via SiteGround/cPanel File Manager (or SSH `rm -rf`)
3. Filesystem deletion bypasses WP's plugin lifecycle, so uninstall.php never runs

**Fix idea for cc-assistant:** uninstall.php should check if any other folder containing this same Plugin Name header is still installed (scan `WP_PLUGIN_DIR` for plugin headers matching `Plugin Name: CC Assistant`). If found, skip table drops. This would make duplicate-cleanup safe even via WP-Delete.

**Caught via:** erofwhiterock.com cleanup of three CC Assistant duplicate folders on 2026-05-06 after the broken-zip incident. Active 0.10.7 → upgraded to 0.10.9, two duplicate inactive folders deleted via WP-Delete; deletion of duplicates triggered uninstall.php which dropped all tables.
