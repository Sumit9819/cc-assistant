---
name: reference-search-evidence-2026-08
description: "Verified Aug 2026 search/AI research - supersedes stale items in google-2026-seo-doctrine; FAQ carve-out was WRONG, GSC impressions bug May 2025-Apr 2026, best CTR number is the -39.8% RCT"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-27T04:40:32.379Z
---

Research 2026-08-27: 4 parallel passes + 2 adversarial refutation passes, 27 claims re-tested against primary sources (18 held, 8 adjusted, 1 refuted). Artifact: https://claude.ai/code/artifact/704e8778-90fd-4e7c-b507-4d9803413640

**CORRECTIONS to [[google-2026-seo-doctrine]] - that file is now partly wrong:**
1. **FAQ rich results have NO govt/health carve-out.** That was the Sept 2023 state. The 2026-05-07 retirement is total; docs deleted 2026-06-15, URL 301s to changelog. Markup still valid, produces nothing. (The doctrine memory says "except govt/health" - wrong.)
2. **GSC impressions were inflated 2025-05-13 -> 2026-04-27** (Google logging error, fixed, history NOT restated). Any YoY impression/CTR/avg-position read inside that window is invalid. Re-base warehouse baselines before using them.
3. **Best CTR number is now -39.8% organic clicks** (Agarwal ISB / Sen CMU randomised experiment, SSRN 6513059, >1,000 US users, early 2026; AIO citation links get only ~8% of clicks). Retire the Ahrefs 58% (unverifiable on primary) and treat Seer's range as superseded: Seer's own data shows rebound 1.31% (Dec 25) -> 2.36% (Feb 26) vs 3.82% no-AIO. Cited pages: +120% vs uncited but still -38% vs no-AIO.
4. "March 2026 = most volatile on record / 80% of top-3 shifted" - the real figure is 79.5% top-3 URL *churn* (SE Ranking via SEL); "most volatile on record" has no primary. Soften it.

**New official facts (all primary-fetched 2026-08-27):**
- Dashboard URL is `status.search.google.com/products/rGHU1u87FJnkP6W2GwMi/history` (the other product ID 404s).
- 2026 updates: Mar spam (24 Mar), Mar core (27 Mar-8 Apr), **May core (21 May-2 Jun)**, Jun spam (24-26 Jun), Aug spam (18-21 Aug). **NO core update in Jun/Jul/Aug** - "August core update" chatter is tracker volatility.
- Spam policies 2026-05-15: spam now includes "attempting to manipulate generative AI responses in Google Search".
- Back-button hijacking: announced 2026-04-13, **enforced from 2026-06-15**.
- AI optimization guide (updated 2026-07-10) explicitly says ignore llms.txt + chunking (llms.txt line added 2026-06-15).
- GSC Generative AI report (2026-06-03): impressions/pages/countries/devices only. **No clicks, no queries, no API. Still a subset of properties** - the "global 11 Aug" claim is unsupported. Opt-out control forfeits traffic AND impressions.
- Review snippet doc 2026-07-24: "Don't include fake or undisclosed incentivized reviews on your page or in your structured data markup." Self-controlled aggregateRating still ineligible.
- GBP contribution policy bans staff review quotas / naming staff / on-premises pressure / selective positive solicitation - but the "16-17 Apr 2026" date is wrong (present by 5 Apr; added between Sep 2025 and Apr 2026).
- I/O 2026-05-19: Gemini 3.5 Flash = default **model in AI Mode**, NOT AI Mode as default surface. AIO 2.5B MAU, AI Mode 1B+.
- **QRG last updated 2025-09-11. No 2026 update exists** - "June 12 2026 QRG update" is fabricated.
- Cloudflare 2026-09-15: for NEW domains, Training+Agent blocked by default on ad-bearing pages; "multi-purpose crawlers such as Googlebot, Applebot, and BingBot will be blocked by customers who have selected to block Training."

**Confirmed harder than before:** schema does NOT lift AI citations (Ahrefs DiD, 1,885 vs 4,000 controls: AIO -4.6%, AI Mode/ChatGPT ~0). llms.txt useless (8.7% adoption + Google says so).

**Evidence-ranked what works:** first-party not derivative > intent/source-type fit > review recency+replies > GBP predefined services/categories/hours (moves in 24-72h) > brand mentions (r~0.66-0.74 vs backlinks 0.19) > long-form chaptered YouTube (5.6% of AIO citations) > owned service pages (~60% of Gemini local citations).
**Avoid:** fake/incentivised/gated reviews (FTC $4M TruHeight Apr 2026, $53,088/violation), gaming AIO, back-button hijack, scaled AI/translated content, being a derivative layer, self-serving aggregateRating, GBP name stuffing, JS-only content (AI crawlers don't render JS), facet bloat.
**Still fine:** leftover FAQ/HowTo markup, AI drafts with human review, blocking Google-Extended, location pages with real substance, CWV "needs improvement".

**Portfolio read (3 ERs + IWC):** structurally on the winning side. Clinical/symptom pages = AIO on ~51% of health queries (74% at 7+ words) -> impressions without clicks, judge on brand/assisted. "Near me" = local pack 93% / AIO ~15% -> GBP discipline. Reviews ~25% of local weight AND the top legal risk. GBP posts/Q&A are near-bottom ranking factors (441-kw/9-wk test: no movement) - don't sell as ranking work. Unchecked: tracking pixels on symptom/appointment pages (73% of 59 clinic sites leak trackers; no GA4 BAA).

Related: [[seo-practitioner-reality]], [[reference_2026_local_healthcare_growth_playbook]], [[feedback_precise_figures_only]].
