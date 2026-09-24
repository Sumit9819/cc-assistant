---
name: gmb-utm-is-intentional
description: "/?utm_source=google&utm_medium=gmb on every site is an INTENTIONAL GMB tracking parameter — never flag as cannibalization, never recommend removing"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ab36cec8-65c1-4a26-ba9e-b90b0467a7fe
---

The URL `https://{site}.com/?utm_source=google&utm_medium=gmb` is intentional. User configures it as the website URL in the Google Business Profile so they can track GMB-sourced traffic in Google Analytics. The UTM parameters do NOT redirect or break — visitors land on the homepage, the params are parsed by GA for attribution, and the page renders normally.

**Stop doing the following:**
- Don't flag this URL as "cannibalization" in gsc_cannibalization output
- Don't recommend "remove UTMs from GBP profile"
- Don't describe the GBP UTM URL as a "fix-it" item
- Don't list it as an AIO CTR-loss target requiring action

**What to actually do:**
- When AIO CTR alerts surface this URL, acknowledge it's the GMB tracking URL and EXPECTED to have lower CTR than the bare homepage (it picks up GMB-impressions where bare-name searches happen; AIO siphons brand clicks)
- If the user wants brand-SERP optimization, focus on the BARE homepage (post 228), not the UTM version
- Treat metrics from /?utm_source=google&utm_medium=gmb as informational only — they reflect GMB attribution, not a separate page

**Why:** User has corrected this multiple times across multiple sessions. Memory record on 2026-05-22 — corrected for at least the 4th time. Applies to all 4 sites in portfolio (erofwhiterock, eroflufkin, erofirving, irvingwellnessclinic, plus any future addition). The GBP UTM URL is the SAME working homepage with attribution params, not a competing page.

**How to apply:** Any time a tool surfaces `/?utm_source=google&utm_medium=gmb` as a metric source, treat that line of output as a duplicate of the homepage and skip the "you should fix this" framing. If the prompt asks for homepage SEO recommendations, anchor the analysis on the bare homepage URL only.
