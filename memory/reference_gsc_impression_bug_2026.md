---
name: reference_gsc_impression_bug_2026
description: GSC over-reported impressions May 13 2025–Apr 27 2026; clicks unaffected; use clicks not impressions to judge drops
metadata: 
  node_type: memory
  type: reference
  originSessionId: d2d3c86d-6864-40bf-8493-8110b476d9bf
  modified: 2026-07-24T04:24:19.060Z
---

Google Search Console **inflated (over-reported) impressions** from **May 13, 2025 through April 27, 2026** due to a logging error. Google fixed logging going FORWARD on 2026-04-27 but did NOT backfill the tainted window. Result: nearly every site shows an impressions "drop" in mid-2026 that is partly/mostly the correction of fake inflation, not real visibility loss.

**Key diagnostic:** clicks, and metrics NOT derived from impressions, were UNAFFECTED. Only impressions / CTR / avg-position were distorted.
- Impressions ↓ but clicks flat = phantom drop (the bug). Do not panic-fix.
- Impressions ↓ AND clicks ↓ = real loss (core update / AI Overviews / decay).

Year-over-year impression comparisons are unreliable until ~May 2027 (both sides of the comparison clean). Judge drops on **clicks** and on the post-2026-04-27 clean window, not YoY impressions.

Also 2026 context: AI Overviews on ~48% of queries (Feb 2026), ~38–61% CTR loss on AIO queries, hits informational/simple-answer content hardest (20–40%). Cited brands get ~120% more clicks/impression → GEO (add stats, quotes, cite sources) is the lever. See [[reference_google_2026_seo_doctrine]] and [[reference_2026_local_healthcare_growth_playbook]].

First surfaced diagnosing erofirving.com blog impression drop (2026-07-24): site was found already well-optimized (fresh content, BlogPosting+medical schema, CDC/NIH citations), so the drop attributed to this bug + AIO, not site quality. Real gap there = no reviews/ratings surfaced anywhere.

## Confirmed boundary, measured on mammothmachinery.ca 2026-09-01

The correction lands between **April and May 2026**. Monthly impressions:
Jan 29,140 / Feb 24,985 / Mar 29,517 / Apr 28,302 / **May 11,094** / Jun 13,111 /
Jul 24,676 / Aug 39,552. Clicks barely moved across that step (Apr 738 -> May 492)
while CTR doubled, 2.61% -> 4.43%. Nothing shipped on the site that week.

**Consequence for reporting:** any window spanning the Apr/May boundary cannot compare
impressions or CTR. A Jun-Aug vs Mar-May quarterly comparison is contaminated on both
metrics. Report **clicks and position** across such a boundary, state plainly in the
document why, and where possible show the monthly trend from May onward, which is
internally consistent.

Related: [[feedback_client_report_format]], [[feedback_never_diagnose_from_average_position]].
