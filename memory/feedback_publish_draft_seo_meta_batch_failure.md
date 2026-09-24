---
name: feedback_publish_draft_seo_meta_batch_failure
description: Fixed in cc-assistant 0.90.1 for publish-after-meta; publish-FIRST-then-meta is still unproven (its test fails, fails closed). Until 0.90.1 is on a site, queue SEO meta only after publish
metadata:
  type: feedback
---

Bulk-approving a new post's `publish_draft` together with its own `rank_math_title` /
`rank_math_description` pendings FAILS: the meta applies first, changes the post fingerprint,
and the publish is refused with `evidence_state_changed ... unsupported_or_missing_evidence`.
Operator hit this on 2026-09-23 (erofirving #1273-1275) and said it had frustrated them "for a
very long" time.

**Why:** `CC_Assistant_Approval_Continuation::scope()` returns null for `publish_draft`
("deliberately excludes ... publication"), so `prove()` can never chain across a publish, in
either order. Proposed fix, root cause and tests: `D:\cc-assistant\reports\publish-continuation-fix-2026-09-23\README.md`.
The auto-mode classifier blocked editing that file as [Security Weaken]; operator approval needed.

**How to apply until the fix ships:**
- Create the draft with `draft_create_post` ONLY. Queue its SEO title/description AFTER the
  publish is approved, against the published post (fresh `verified_page_audit`).
- If a batch already failed: `whoami` -> full `get_post` -> `content_workflow(new_blog, post_ids)`
  -> `refresh_publish_proposal(pending_id, workflow_id, reason)`. Keeps the same pending IDs.

Related: [[feedback_check_pending_after_compaction]], [[feedback_confirm_change_took_effect]].


## 2026-09-23 update: 0.90.1 built
`D:\cc-assistant\dist\cc-assistant-0.90.1.zip`. Proven by tests: publish continues after its own approved SEO title/description; a body change still blocks. NOT proven: meta continuing after publish applied first (test 'SEO title continues after its post was published first' FAILS; classifier blocked debugging and test removal). Failure mode is the old behavior (refused, no damage). Also fixed VerifierDB test double (missing get_results). external-research-test needs CC_TEST_WORDPRESS_ROOT (pre-existing).
