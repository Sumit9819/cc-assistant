---
name: reference_sibling_change_drift_guards
description: "Drift guards must ignore the plugin's own sibling writes; a merge-safe edit needs no whole-value hash"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-03T14:32:20.337Z
---

cc-assistant has now hit the same bug twice, in two different code paths.

**The pattern:** several pending changes target the same row. The operator approves them
together. The first one applies and changes the row. Every sibling then trips the drift guard
— "value changed after this was queued" — because the guard cannot tell the plugin's own
write from an outside edit.

- v0.16 hit it with `post_modified`. Fixed by stamping `_cc_assistant_last_internal_apply`
  and using `max(queued_at, last_internal_apply)` as the baseline. See
  [[reference_v0_16_race_safety_false_positive]].
- v0.67 hit it again with a per-row SHA-1 in `asset_reference_replace`. Six banner swaps
  queued against page #72, approved together: 2 applied, 4 refused. One of the two that
  "succeeded" had silently written only 2 of its 5 locations.
- v0.68 review found a THIRD variant: the cosine verifier wrote `verification_result`
  wholesale while two sibling crons (schema audit, render-health audit) MERGE into the same
  column — both fire ~30s after the same apply in nondeterministic order, so the blind
  writer could erase whichever merger ran first. Rule: any column shared by multiple
  writers must be read-merge-write, never overwritten.

**The deeper lesson, which is not just "remember last_internal_apply":** ask whether the
operation is *merge-safe* before reaching for a drift guard at all. A URL swap re-reads the
row, replaces only the URL, and writes back — a concurrent human edit survives untouched. A
whole-value hash therefore protects nothing and only creates false conflicts. Compare a
full-body content replace, which genuinely would clobber and genuinely needs the guard.

For a merge-safe edit the right drift signal is **semantic, not byte-level**: is the thing I
came to change still here? If the old URL is gone, someone already handled it and there is
nothing to do. Record the hash for the audit trail; never block on it.

**Also:** a run that writes some rows and skips others is a partial success, and returning
plain `true` hides it. Write the shortfall to `review_note` naming every skipped location, or
the operator finds out by looking at the page. See
[[reference_cc_assistant_v067_asset_references]].
