---
name: feedback_reject_does_not_undo_an_applied_pending
description: Rejecting a pending that was already approved returns ok:true status:rejected and does NOT undo the applied content; requeueing then duplicates it
metadata:
  type: feedback
---

`POST /pending/<id>/decide {"action":"reject"}` marks the row rejected. It does
NOT inspect whether the change already applied, and it does NOT revert content.
On an already-applied pending it returns `{"ok":true,"status":"rejected"}` -
indistinguishable from a real reject.

**Why this bites.** 2026-09-07: pendings 1120 and 1122 were queued, the operator
approved them in the same pass that approved 1121/1123, and minutes later a reject
was sent for 1120/1122 in order to rebuild them with corrected images. The reject
reported success. The rebuilt pendings then inserted a SECOND copy of every card,
leaving posts 3081 and 3091 with four figures each, stacked back to back. The
anti-stacking guard in mkpatch-erof.py could not catch it because it was reading a
body snapshot fetched before the first copy applied.

**How to apply.**

1. Before rejecting, READ the pending's status (`/pending`, or the recap's
   `recent_pending`). If it is not still awaiting review, a reject is a no-op on
   the content: you need a patch that reverses it instead.
2. After any reject, VERIFY against the live body, not the API response. `ok:true`
   here means "row marked", never "content unchanged".
3. Re-fetch post bodies immediately before generating patches. A snapshot even a
   few minutes old can miss an approval, and every guard that reads the body
   inherits that blindness.
4. An empty pending queue does NOT mean your rejects worked. It means every row
   was decided, approvals included.

Related: [[feedback_confirm_change_took_effect]],
[[feedback_queue_pending_in_batches_of_20]], [[project_erofirving_card_generator]].
