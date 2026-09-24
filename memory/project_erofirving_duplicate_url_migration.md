---
name: project_erofirving_duplicate_url_migration
description: erofirving.com articles are indexed at BOTH /blog/<slug>/ and /<slug>/ — the cause of the 2026 click collapse
metadata: 
  node_type: memory
  type: project
  originSessionId: 130cc6ff-5f2e-4578-922a-b3979c9bd445
  modified: 2026-08-06T04:12:57.097Z
---

Diagnosed 2026-08-04 from the full GSC warehouse (Jan 1 – Jul 31 2026). **erofirving.com is running a half-finished URL migration: the same articles are indexed and ranking at two addresses simultaneously**, `/blog/<slug>/` and root-level `/<slug>/`.

Evidence: `/blog/best-foods-to-eat-when-sick/` 2,726 clicks AND `/best-foods-to-eat-when-sick/` 704 clicks (the root copy earned *all* of its clicks after mid-April, so it went live mid-window). Same doubling on `red-cricle-on-skin-not-ringworm` (1,827 + 415) and `pulled-chest-muscle-guide` (3,056 + 363). Clicks by prefix: `/blog/` 5,000 (Mar) → 657 (Jul); root 128 (Mar) → 1,041 (Jul). Distinct pages ranking 119 (Jan) → 365 (Jun) with no matching publishing volume.

Consequence: **top-3 impressions fell 738,043 (Mar) → 124,498 (Jul), -83%**, and site clicks fell 5,128 → 1,698 (-67%). Impressions did *not* collapse — this is rank loss from self-competition, not demand loss. Note the impressions side is partly confounded by [[reference_gsc_impression_bug_2026]]; clicks are not.

**Fix (P0, not yet queued):** pick one canonical URL per article, 301 the duplicate, align canonicals. Expect the [[reference_custom_permalinks_trap]] stale-postmeta problem to block redirects here — clear that postmeta as part of the fix. Until it is done, every other content investment on this domain is diluted by half.

## UPDATE 2026-08-06 — RESOLVED. The retirement was executed CORRECTLY. No action needed.

I initially claimed these URLs were "hard 404s with no redirect" and called it a broken migration. **That was wrong.** Correcting it here so the error is not inherited.

**What actually happened:** the pages were deliberately retired (June-30 pruning, already investigated 2026-07-06, standing decision "Do NOT un-retire") and every retired URL carries an **active `410 Gone` in Rank Math** — ids 31, 33, 34, 35, 46, 47. 410 is the textbook-correct disposition for intentionally removed content, and it is exactly what `redirect_audit` itself recommends.

**Why I got it wrong — reusable lesson:** the GSC URL Inspection API reports a 410 as `coverage_state: "Not found (404)"` / `page_fetch_state: NOT_FOUND`. **It does not distinguish 410 from 404.** Never infer "no redirect exists" from a NOT_FOUND verdict. The authoritative check is `draft_create_redirect(source, destination, _diag=true)`, which dumps the live Rank Math redirect rows for that source without queueing anything.

Also falsely alarming: GSC `referring_urls` listed live service pages as linking to the dead URLs. `audit_post_links` on 1267 / 1072 shows **zero** links to them — `referring_urls` is historical crawl data and goes stale.

**What is actually true and still matters:**
- The retirement was bigger than recorded: ~**1,050 clicks/month** removed (best-foods 445, red-cricle 320, pulled-stomach 108, bladder 93, hpv-bumps 49, costal 29, hpv-vs-herpes 15), not the "600-700 clicks/mo" in working_state.
- **The pruning has not yet produced a local lift.** Local-query ("irving") clicks were flat all year: 2.52/day (Mar) → 2.97 → 2.90 → 2.77 → 2.45 (Jul). Money-page clicks flat ~3.0–4.0/day. The whole 5,128 → 1,698 decline is the retired informational blog; local business neither gained nor lost.
- Avg position worsened 6.4 → 12.2 (Mar→Jul) with page count flat at 108→113, so that is **not** the usual expansion artifact.
- Residual cosmetic-only: six 301→410 chains (`redirect_audit` #5, #6, #8–#11). End state is already correct; not worth churn.

Related: [[project_mammoth_url_migration_404s]] is a genuinely different case — those really are unredirected 404s. Do not pattern-match the two.

Secondary finding: heavy AI-Overview absorption on commodity explainers — `costochondritis` position 1.3 / 2,656 impressions / **zero clicks**, `distended` position 2.5 / zero clicks, `iv hydration` position 3.2 / zero clicks. No title rewrite recovers those; see [[reference_google_2026_seo_doctrine]].
