---
name: feedback_plugin_update_invalidates_pendings
description: A pending change is refused at approval if a "material" plugin (Elementor, Elementor Pro, Rank Math, Custom Permalinks, cc-assistant) changed version after it was queued; re-verify the page and re-queue, never force
metadata:
  type: feedback
---

2026-09-24 erofirving #1301 (_thumbnail_id on 4802) failed on approval with "configuration
changed after this plan was drafted. Changed: material_plugins" because Elementor 4.2.4 -> 4.3.1
and Elementor Pro 4.2.2 -> 4.3.0 auto-updated between queueing (07:12 UTC) and approval.
The pending stays in the queue with status pending after the failed apply.

**Why:** each pending stores an environment hash (wp version, theme, active + material plugin
versions, Elementor kit, SEO/routing options). A mismatch at apply time is a safety refusal,
not a bug: the evidence the change was planned against is stale.

**How to apply:**
- Smoke-test first: render_probe the target (compare headings/bytes to the pending's
  pre_check_baseline) and spot-check 2-3 Elementor pages for fatal errors.
- reject_pending_change the stale id with a note, then whoami -> verified_page_audit ->
  re-queue the same change. Tell the operator the new id.
- Queue-to-approval gaps are when auto-updates bite; batch approvals soon after queueing.
- Check sister sites' queues too; same update can hit them (WR was on Elementor 4.3.1 already).
Related: [[feedback_publish_draft_seo_meta_batch_failure]], [[feedback_check_pending_after_compaction]].

**Same-template batches (2026-09-24, erofwhiterock header 4476):** queued #1846 + #1847
(widget setting updates) and #1848 (widget ADD) on one template; the operator approved all
three and #1848 was refused because the first two changed `_elementor_data` after it was
queued. Setting updates on different widgets merged fine; the add did not. When a batch mixes
edits and an add on the same post, queue the add alone after the edits apply (or expect to
reject and re-queue it, as #1849 did).

**HARD RULE (2026-09-25, after it happened TWICE and the operator lost patience):** ONE pending
change per post/template at a time. Never queue a second change to the same post until the
first is approved and applied. It is not only adds: on erofirving header 4760, #1323 (container)
and #1325 (icon list) applied and #1324 (social widget) was refused; on White Rock #1848 (add)
was refused. Which ones survive is unpredictable, so do not batch at all. If several edits are
needed on one template, queue the most important one, tell the operator the others follow one
by one, and queue the next only after the approval lands.

