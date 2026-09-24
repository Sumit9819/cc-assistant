---
name: avg-position-expansion-artifact
description: "GSC site-wide avg position drops dramatically when a site publishes a batch of new pages, because the new URLs pull long-tail impressions at positions 50-90+ before they rank. Always frame avg position drops in expansion context before treating as damage."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

When the user reports a "site-wide avg position drop" (e.g. 8 → 28), DO NOT diagnose it as a ranking penalty until you check whether the site has recently published a batch of new pages.

**Why:** GSC site-wide avg position is volume-weighted across every (page, query) pair. New pages enter at positions 50–95 and pull thousands of long-tail impressions before Google has engagement data to rank them. A site that publishes 30+ new pages in a month can move site-wide avg from 8 to 28 with zero damage to its existing pages. Confirmed on erofwhiterock.com 2026-05-20: the site doubled its indexed surface (15 EN posts + 15 ES translations + 30 ES service-page translations) in 4 weeks, and gsc_anomalies/decay/lost all returned 0 rows while existing money queries held position 1–2.

**How to apply:** Before concluding ranking damage, run in parallel:
1. `list_posts` for both `page` and `post`, count posts published in the last 30 days
2. `gsc_anomalies direction=decay` — if 0 rows, no statistically anomalous decay
3. `gsc_trends lens=decay` and `lens=lost` over 30d — if 0 rows, no individual page is losing ranking
4. `gsc_page_queries` for the user's flagship URL — confirm money queries still hold their positions

If decay/anomalies/lost are all 0 AND there's a recent publication batch, the avg-position drop is an expansion artifact. Tell the user:
- It is volume-weighted math, not damage
- Watch total **clicks** (and click-weighted position), not impression-weighted avg position
- New pages take 4–8 weeks of engagement signal to climb from pos 80 → 5

Related: [[reference_seo_practitioner_reality]] — AIO CTR cannibalization is a separate, real problem that can co-occur and confuse the diagnosis.
