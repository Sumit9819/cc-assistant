---
name: apply-empty-meta-noop-false
description: "cc-assistant apply reports \"Apply returned false\" when a postmeta clear targets an already-empty value; it is a no-op, not a failure"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 57f4d3d5-c54a-40de-8c70-22efb3ef1392
  modified: 2026-08-11T06:33:31.515Z
---

`class-apply.php apply_post_meta()`: WordPress `update_post_meta()` returns false when the stored value already equals the proposed value. The plugin's idempotence guard (added for v0.48 rebuild-in-place) treats stored==proposed as success ONLY when the proposed value is non-empty, so **clearing a meta that is already empty/cleared reports `apply_failed` ("Apply returned false") even though nothing is wrong**.

**Why:** the guard deliberately excludes empty values to avoid masking a genuine empty-write failure, but that makes repeat clears look broken.

**How to apply:** when a postmeta CLEAR fails with "Apply returned false", first check the post's approved sibling history (`verify_change` on the failed pending shows siblings) — an earlier approved clear means this was a stale re-queue; reject the pending, nothing to fix. First hit: erofirving pendings 981/982 (2026-08-11), whose permalink rows were already cleared by approved 254/563/255 in June-July. Fix queued in dev backlog: delete-row-on-empty + return true (same semantics schema keys already get). Related: [[sibling-change-drift-guards]], [[no-guessing-epistemic-discipline]].
