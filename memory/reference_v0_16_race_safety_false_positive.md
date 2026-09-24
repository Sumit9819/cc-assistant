---
name: cc-assistant-v0-18-1-fix-for-race-safety-false-positive-on-sibling-pendings
description: "previous false positive where pre-apply guard refused sibling pendings within a single bulk batch; fixed in v0.18.1 by stamping _cc_assistant_last_internal_apply postmeta and comparing against max(queued_at, that)"
metadata: 
  node_type: memory
  type: reference
  originSessionId: a48649d4-4501-45c1-b8a4-addd9908cee8
---

**Resolved in v0.18.1 on 2026-05-15.** Prior to the fix, the v0.16 pre-apply guard refused sibling pendings in the same bulk batch because publish_draft (or any earlier-applied sibling) bumped `post_modified` above their `queued_at`. The plugin treated its own apply as an external edit.

**Fix in class-apply.php (v0.18.1):**

1. The guard now computes `$baseline_mtime = max($queued_mtime, $last_internal_mtime)` where `$last_internal_mtime = strtotime( get_post_meta( $post_id, '_cc_assistant_last_internal_apply', true ) )`. The conflict refusal fires only when `$current_mtime > $baseline_mtime + 5`.
2. After a successful apply, the plugin writes `_cc_assistant_last_internal_apply = current_time('mysql', 1)` on the post (skipped for cluster changes with no post_id).
3. External edits (Elementor save in another tab, wp-admin save) still bump `post_modified` without writing the marker, so the guard still catches them. Internal sibling applies bump both together, so the baseline keeps pace.

**Reproduction that broke before the fix (now passes):**

1. Queue publish_draft on post X at T-10m and a sibling SEO meta change on post X at T-9m59s.
2. User bulk-approves at T. publish_draft runs first, post_modified becomes T, `_cc_assistant_last_internal_apply` becomes T.
3. The sibling SEO meta apply at T+1s: guard reads `post_modified=T`, `queued_at=T-9m59s`, `_last_internal=T`. Baseline = max(T-9m59s, T) = T. T > T+5? No. Applies cleanly.

**Pending IDs that died from the bug on 2026-05-15 (before the fix):** #513-#528 and #529-#531/#533-#535/#537-#539/#541-#543 — 28 sibling pendings refused across two batches. Re-queued as #545-#556 with fresh timestamps and applied successfully under the same broken guard because their queued_at then exceeded post_modified. The fix eliminates the need to re-queue.

**How to apply:** The fix is shipped in the cc-assistant.zip at v0.18.1. Upload to each live site (erofwhiterock, eroflufkin, irvingwellnessclinic). No DB migration needed since `_cc_assistant_last_internal_apply` is just a postmeta that defaults to empty (max with 0 becomes max(queued_at, 0) = queued_at, identical to old behavior on the first apply per post).

Cross-references [[reference_cc_assistant_v0_16_race_safety]] (the guard introduced in 0.16).
