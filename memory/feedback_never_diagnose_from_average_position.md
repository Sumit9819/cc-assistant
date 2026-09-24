---
name: feedback_never_diagnose_from_average_position
description: "Average position across pages is an artifact, not a rank — always GROUP BY page and check clicks before calling anything a problem"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: acae98d0-ca94-4d6e-907a-aa5917f30cc0
  modified: 2026-08-06T05:27:22.648Z
---

**Never diagnose a ranking problem from an aggregated average position.** When several pages of the same site rank for one query, GSC's average is a mean across all of them. A handful of deep rows (position 60–80) drags the mean into alarm territory while the money page sits at position 2.

**Why:** burned on erofirving 2026-08-06. I reported "your own brand 'er of irving' is at position 11.9 — a brand emergency, fix before writing anything." Broken out by page, the homepage was at **2.1 with 99 clicks**, `/emergency-services-er-of-irving/` at 1.8, `/contact-us/` at 2.3. Seven-plus pages rank for the brand at once; the deep ones made the average. There was no problem at all. Same session I also over-read a `NOT_FOUND` verdict as a missing redirect — see [[reference_gsc_inspect_410_vs_404]]. Both were aggregate/summary readings asserted as fact.

**How to apply:**
1. Before calling any query a ranking problem, re-run it `GROUP BY query, page`. Look at the best-performing page, not the mean.
2. **Clicks are the tell.** Meaningful clicks on a query prove *something* ranks well, whichever way the average reads. Zero clicks across thousands of impressions is the genuine signal — that survives the disaggregation test (it did for "emergency treatment irving", 3,000 imp / 0 clicks / pos 61).
3. Site-wide average position moving is almost never a ranking story. Check page count first — see [[feedback_avg_position_expansion_artifact]].
4. Same discipline as [[feedback_no_guessing_epistemic_discipline]]: an aggregate is *inferred*, a per-page breakdown is *measured*. Do not act on the first and call it the second.
