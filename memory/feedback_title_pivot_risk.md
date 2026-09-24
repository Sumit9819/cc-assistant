---
name: feedback-title-pivot-risk
description: "When a title pivots away from its high-impression query cluster, the page often loses rank in the old cluster without gaining rank in the new — verify the WHOLE query distribution before re-aligning intent"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 8054b32b-03d7-46d2-a9bf-8d5474be45b2
---

When a page has 0% CTR on its dominant ranking query, the instinct is to pivot the title toward a different intent. This is dangerous: pivoting away from the high-impression query family can cause Google to de-rank the page for the WHOLE original cluster without rebuilding ranking authority for the new intent.

**Why:** ER of Lufkin post 5718 (cut-infection) got a title pivot on Apr 30 2026 from infection-signs framing to stitches intent (rationale: dominant query had 0% CTR at pos 1). Two weeks later, the page dropped from position 12.4 to 23.4 — but the dominant queries it was actually ranking for (118 imp/"how to know if a cut is infected", 61 imp/"signs a cut is infected") were still cut-infection variants, not stitches. The pivot broke topic coherence without delivering on the new intent. Had to queue a re-revert (#268/#269 2026-05-18) to restore the infection lead.

**How to apply:** Before pivoting a title away from its dominant query family, audit the FULL GSC query distribution — not just the top-1 query. Specifically: (1) what % of impressions belong to the proposed-new intent vs. the existing cluster, (2) does the page rank well (<15) for queries in the new intent already, (3) is the 0% CTR a topic mismatch or just a generic title that needs sharpening within the same intent. The safer move is usually to sharpen the title within the existing cluster, not switch clusters. Relates to [[feedback_homepage_title_data_check]] but applies to ALL pages, not just homepage.
