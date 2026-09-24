---
name: feedback-gsc-window-28d-before-diagnosing
description: Always read GSC at 28 days before calling a page underperforming; 90-day averages hide recent fixes and cause duplicate work
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ae37a612-eb3c-4f37-b85d-76f7f880700a
  modified: 2026-07-27T04:32:30.450Z
---

Before diagnosing any page as underperforming, pull its GSC numbers at **28 days**, not 90. A 90-day average blends in the period *before* recent optimization work and can make an already-fixed page look broken.

**Why:** 2026-07-27 on irvingwellnessclinic. Post 6356 (IV therapy / chronic fatigue) read as "position 21-28, zero clicks" on a 90-day window, and I called it the site's top priority on that basis. The 28-day window showed position 11-15. Changes 840/841/842 had been approved on 2026-07-07 and had already moved it 8-13 spots. The planned "refresh" (rewrite title, add comparison table, add byline) was almost entirely work that had already shipped. Queueing it would have duplicated approved changes, which is exactly why pending 898 was rejected on this same site.

**How to apply:**
1. `post_dossier(id)` first. Its `history` shows approved changes with dates, and its `top_gsc_queries` are 28-day. If a page was edited recently, the 90-day average is stale by definition.
2. Compare 28d vs 90d explicitly when a page looks bad. A large gap means the trend is moving, and the direction matters more than the average.
3. `render_probe` the live DOM before proposing structural additions. Tables, FAQs and question headings a prior session added will not be visible in a stale mental model.
4. Cross-check `win_audit` structural sub-scores against `render_probe`. On 6356 win_audit reported 0 tables / 1 question heading while the live DOM had the table and 9 question headings. Trust the probe for structure; trust win_audit for quotes, stats and competitor comparison.

Inventory-based gap analysis (SKU coverage via `service_inventory` + `list_posts` across all posts) does NOT have this problem, since it does not depend on a GSC window. See [[feedback_verify_gaps_against_post_inventory]] and [[feedback_read_live_meta_before_rewriting]].
