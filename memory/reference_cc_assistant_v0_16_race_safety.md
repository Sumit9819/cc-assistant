---
name: reference-cc-assistant-v0-16-race-safety
description: cc-assistant v0.16.0 fixes the race between plugin apply and Elementor editor save — pre-apply post_modified conflict guard + post-apply verification re-read + aggressive cache flush so applies actually take effect
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

**The failure that prompted this:** Pending 502 on post 623 reported `applied_at: 2026-05-14 09:30:11` for `elementor_widget_remove` targeting widget 7a08042 — the duplicate MedicalProcedure HTML widget. The plugin's apply ran successfully and saved `_elementor_data` without 7a08042. BUT widget 7a08042 was still on the page after the apply because the operator had an Elementor editor session open in another tab. When the operator manually deleted a widget in the editor and saved, the editor wrote BACK its in-memory `_elementor_data` (which still had 7a08042) — clobbering the plugin's remove. Net result: the wrong widget got removed (the one deleted manually) and the duplicate stayed.

**v0.16 closes the race on three levels:**

1. **Pre-apply `post_modified` conflict guard** ([class-apply.php](wp-content/plugins/cc-assistant/includes/class-apply.php) — runs before the change_type switch). Compares the post's `post_modified` against the pending row's `created_at`. If `post_modified > created_at + 5s`, refuse the apply with `post_modified_conflict` (HTTP 409) and mark the pending row `apply_failed` with a clear review_note. The reviewer must re-query the page state and queue a fresh change.

2. **Post-apply VERIFICATION re-read** (`apply_elementor_widget_remove` and analogues). After `save_tree()` succeeds, immediately re-load the tree from the DB (not from in-memory) and confirm the target widget is actually gone. If it's still there, return `remove_clobbered` (HTTP 409) so the apply is marked as failed instead of silently reporting success.

3. **Aggressive multi-plugin cache flush** in `flush_elementor_css_cache()`. Now busts: `wp_cache_flush`, Rank Math schema transient, WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, SiteGround SuperCacher, Cloudflare-PageCache, WP Engine, Kinsta, plus a `cc_assistant_after_cache_flush` action hook other plugins can listen to. Each guarded by class/function existence so it's safe on installs that don't have the plugin.

**The architectural lesson:** report success ONLY when the final state is verified from the source of truth (the DB), not from the in-memory copy. Plugin writes can be raced by other writers (theme, editor, REST API endpoints) — verifying after the fact is the only way to guarantee what the operator sees matches what the apply log says.

**Operator discipline:** when about to approve a pending change on a post, make sure no Elementor editor session is open for that post. v0.16's conflict guard catches it automatically and refuses with a clear error, but skipping that error is faster than recovering from a clobbered apply.
