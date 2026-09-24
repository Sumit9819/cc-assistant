---
name: feedback_gbp_api_quota_unavailable
description: Never tell the operator to "connect Google Business Profile" - Google has not granted the GBP API quota; operator has said so repeatedly and is frustrated by the repeat
metadata:
  type: feedback
---

Google has **not granted Business Profile API quota** for this operator's project, so
`gbp_locations` / `gbp_performance` will stay `gbp_not_connected` on every site. The
operator has told me this several times (IWC audit 2026-09-22, ER of Irving audit
2026-09-23) and was annoyed when I recommended "connect GBP in Settings" again.

**Why:** it is not a settings problem the operator can click through; the quota request
itself was never approved.

**How to apply:**
- Do not list "connect Business Profile" as an operator action in any audit or plan.
- Report GBP as "unmeasurable via API (quota not granted)" and move on.
- Alternatives the operator has floated: read GBP insights through the headless browser
  transport (`D:\cc-assistant\tools\cc-via-browser.mjs` style, signed-in Chrome) - ask before doing it.
- For location splits: GSC only has a `country` dimension (no city/state). The cc-assistant
  plugin does NOT expose country yet; the operator can read it in GSC -> Performance ->
  Countries. DFW-vs-rest-of-US can only be inferred from local-intent queries.

Related: [[feedback_gmb_utm_is_intentional]].
