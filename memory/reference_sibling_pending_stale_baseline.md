---
name: reference_sibling_pending_stale_baseline
description: Two pendings queued on the same post in one batch self-invalidate - applying the first bumps post_modified past the second's baseline and the race guard silently refuses it
metadata:
  type: reference
---

Queueing two pending changes against the SAME post in one batch can make the second
one unappliable. The race-safety guard refuses any pending whose `post_modified` is
newer than its `queued_at`. Approving the first pending updates `post_modified`, which
retroactively staleness-kills every sibling queued against the older baseline.

**The failure is silent.** The refused pending keeps `status: "pending"` with
`reviewed_at: null`, so the inbox looks normal and clicking Approve just does nothing.
It does not surface as an error to the operator, and it does not show as rejected.

Verified 2026-09-11 on irvingwellnessclinic post 128: pending 1327 (anchor div) and
1328 (hero CTA buttons) queued together at 04:54:03. 1327 applied at 04:56:10. 1328
then had queued_at 04:54:03 < post_modified 04:56:10 and would not apply. Operator
reported "CTA was not applied".

**Diagnose:** `list_pending_changes` -> compare the row's `created_at` against
`list_posts` `modified` for that post. `modified` newer = stale, will never apply.

**Fix:** reject the stale pending and re-queue identical content. The re-queue needs
fresh gates in this order: `whoami` (evidence_identity_required, 10 min TTL) ->
`verified_page_audit` (page_evidence_required) -> `get_page_map` (map_not_consulted,
600s TTL) -> the draft_* call.

**Avoid:** when several changes touch one post, either queue them one at a time and
wait for approval between, or accept that everything after the first approval needs
re-queueing. Batching across DIFFERENT posts is unaffected.

Related: [[feedback_queue_pending_in_batches_of_20]], [[feedback_confirm_change_took_effect]],
[[feedback_check_pending_after_compaction]]
