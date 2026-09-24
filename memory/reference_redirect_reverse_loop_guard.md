---
name: reference_redirect_reverse_loop_guard
description: draft_create_redirect can create an infinite loop with a PRE-EXISTING reverse redirect; guard added v0.35.5; check before queueing on migrated sites
metadata: 
  node_type: memory
  type: reference
  originSessionId: cf6b72ff-7286-4f35-bac6-cc72e684abbc
---

These ER sites are migrations/clones and carry **pre-existing Rank Math redirects that point canonical→old** (the reverse of what a cleanup wants). Queueing an old→canonical 301 on top of an existing canonical→old 301 makes BOTH URLs loop (ERR_TOO_MANY_REDIRECTS). Happened on erofwhiterock 2026-06-02: my `541→3505` + pre-existing `3505→541` (Rank Math id 13) looped the Emergency Services pages; same on the ES pair (id 12). Fixed by the user trashing id 12/13 in Rank Math.

The old same-source dedup missed it because the conflicting rule's SOURCE is the DESTINATION. **Guarded in v0.35.5**: `draft_create_redirect` now looks up any redirect on the destination and refuses (422 `redirect_reverse_loop`) if it points back to the source, naming the conflicting id. (Self-loop A→A was already guarded since v0.10.)

**Operational rules:**
- Before any redirect cleanup, `draft_create_redirect(..., _diag=true)` on BOTH the source AND the destination to see existing rules pointing either way. (See [[feedback_verify_before_proposing_fix]].)
- After applying redirect + unpublish changes, **curl-verify the chain** with a browser UA: `curl -A "<browser UA>" -IL <old-url>` should show one `301`→canonical→`200`, no loop. WebFetch's "final URL" is unreliable for this; trust raw headers ([[reference_siteground_ua]] — browser UA required or SiteGround WAF 403s).
- The plugin can only CREATE redirects, not delete them — removing a bad/reverse redirect is still a manual Rank Math > Redirections step. A delete/deactivate tool is a TODO. Deploy v0.35.5 ([[reference_plugin_dev_vs_remote_deploy]]).
