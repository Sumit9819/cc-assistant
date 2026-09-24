---
name: feedback_queue_pending_in_batches_of_20
description: Queue pending changes in batches of about 20 cards, not 70+, so the operator can approve them in reviewable chunks
metadata:
  type: feedback
---

Queue draft changes in batches of **~20 cards**, then let the operator approve that
batch before queueing the next. Asked for on mammothmachinery 2026-08-28 after a
72-card sitewide claim sweep (#1078-#1149) landed in the inbox at once.

**Why:** the operator reviews and approves through the Pending Changes screen. A
70+ card batch is one undifferentiated wall of cards, and the bulk-approve result
line is then impossible to read: a second click on approve-all returned
"0 applied, 72 failed - Pending change has already been reviewed" for every card,
which LOOKS like a total failure but actually meant the first click had already
applied all 72. Smaller batches keep both the review and the result legible.

**How to apply:** when a sweep will exceed ~20 cards, queue the first ~20, report
the id range, and wait. Script the sweep so it can resume from an offset rather
than re-scanning. If a bulk approve ever reports "already been reviewed", do NOT
requeue: verify the live/saved state first ([[feedback_dom_is_ground_truth_not_parsers]]),
because that message means the cards left `pending`, usually because they applied.

Related: [[feedback_check_pending_after_compaction]], [[reference_pending_supersede_is_silent]],
[[feedback_automation_boundary]].
