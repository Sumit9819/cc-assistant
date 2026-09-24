---
name: feedback-check-pending-after-compaction
description: "After a session compaction, call list_pending_changes BEFORE queueing any new edit on a post; the summary often omits previously-queued changes and you will duplicate work"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

After a conversation summary/compaction, always call `list_pending_changes` (or `post_dossier` which includes the pending list) for the target post BEFORE queueing the next change. Do this even if the summary appears complete.

**Why:** The compaction summary captures intent and final state of the prior conversation, but the IDs and full text of every queued pending change rarely survive. On 2026-05-14 (irvingwellnessclinic.com post 623), I re-queued 7 icon-box expansions (#355, 357, 360, 362, 365, 368, 373) that already existed as #358, 361, 363, 366, 370, 372, 374 from a pre-compaction segment of the same session. The older versions had specific clinical anchors (350 ng/dL testosterone example, q3mo retest cadence with PSA/CBC/CMP/lipid/ferritin, ASC sterility standards) that my retries lost. The plugin's conflict detector caught it, but only after the noise hit the inbox.

**How to apply:**
- First MCP call after a compaction for any post: `post_dossier(id)` or `list_pending_changes()`
- If a conflict warning appears on a queued change, do NOT auto-supersede; show the user both versions and let them pick
- This rule pairs with [[feedback_verify_before_proposing_fix]] — both are about checking current state before acting
