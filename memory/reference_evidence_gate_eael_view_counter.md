---
name: reference-evidence-gate-eael-view-counter
description: The published-page evidence-gate deadlock was Essential Addons' _eael_post_view_count, bumped by the gate's own loopback fetch; fixed in 0.89.9 after 4 failed guesses
metadata:
  type: reference
---

**Symptom.** On mammothmachinery.ca every `draft_*` proposal against a PUBLISHED
page returned 409 `page_evidence_required`, even with `verified_page_audit` run
seconds earlier and `body_sha1` unchanged. Templates and drafts queued fine.

**Root cause.** `verified_page_audit` proves a published page by loopback-fetching
its own front end. **Essential Addons increments `_eael_post_view_count` on every
render.** That write lands inside the audit request, so `after_read` stores a
receipt whose `post_hash` is already one view behind by the next request.
Drafts and templates use `get_post`, which renders nothing, so they never saw it.

**Why it took four attempts.** The 409 carried `evidence_diagnostics: null`, which
I read as "no evidence was captured". It means the opposite: a *successful* read
clears the diag slot, so a receipt that goes stale afterwards reported nothing at
all. Three fixes were guesses at Elementor cache keys. The fix that worked was to
stop guessing and make the gate name the key.

**Fix (0.89.9).** Two parts, both in the plugin:
1. `CC_Assistant_Integrity::volatile_meta_keys()` now includes `_eael_post_view_count`.
2. Receipts carry `meta_hashes` (per-key digests via `state_meta_hashes()`), and
   `validate_queue` diffs them with `meta_hash_diff()` on mismatch, emitting
   `reason: receipt_went_stale_after_read` plus the moved key. Regression test in
   `approval-wordpress-runtime-test.php`.

**How to apply.** When a gate refuses with no diagnostics, suspect a *render-time
counter*, not a builder cache: anything a plugin increments per page view will
defeat a fingerprint taken around a render. Read the 409's `moved_meta` first; if
it names a key, add it to the volatile list or the `cc_assistant_volatile_meta_keys`
filter. Never add a content-bearing key: that hides real edits from the reviewer.
Related: [[feedback_probe_discipline_positive_controls]],
[[feedback_read_settings_not_frontend_for_plugin_use]],
[[index_cc_assistant_plugin_dev]].
