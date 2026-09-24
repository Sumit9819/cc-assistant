---
name: project-portfolio-instrumentation-gap
description: "2026-08-27 portfolio audit: site_quality_score utility-exclusion DEFECT (false weak verdicts on all 4 sites), GBP unconnected on 3 of 4, 6 playbook errors, 11-item plugin roadmap"
metadata: 
  node_type: memory
  type: project
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-27T05:42:54.035Z
---

Artifact: https://claude.ai/code/artifact/7c921e82-0906-4422-a785-34a4f258c977 (companion to [[reference-search-evidence-2026-08]])

**PLUGIN DEFECT (P0, unfixed as of 2026-08-27):** `site_quality_score` utility-page exclusion at `includes/class-seo-tools.php:2892` is a 10-slug regex (cart|checkout|my-account|wishlist|refund-returns|privacy-policy|cookie-policy|terms|thank-you|order-received). It MISSES accessibility statements, medical disclaimers, HIPAA notices, billing disclosures, editorial policy, letter of protection, contact, careers, blog index, book-appointment, shop, AND every Polylang translation of an excluded page. Consequence measured live: WR's entire bottom-10 and IWC's entire bottom-5 are utility pages; 5 of Lufkin's bottom-10 are, which pushed Lufkin's site verdict to **warn** falsely. Bilingual sites are double-penalised. **Do not action site_quality_score bottom_n until this is fixed.** Fix = page-role model + translation inheritance + separate utility bucket with its own checklist.

**Live site state (read 2026-08-27):**
| Site | Posts | Scored | Mean | Weak | Verdict | GBP |
|---|---|---|---|---|---|---|
| irvingwellnessclinic | 80 | 78 | 87.0 | 5 | pass | NOT connected |
| erofirving | 95 | 91 | 72.9 | 6 | pass | NOT connected |
| eroflufkin | 150 | 126 | 65.0 | 28 | warn (false) | NOT connected |
| erofwhiterock | 125 | 73 | 62.6 | 16 | pass (on 58% of site) | connected, rate-limited |

Also: `llm_crawls` enabled=false on Irving. `aeo_snapshot` Irving absorbed_28d = 226 queries / **241,592 impressions**. GSC connected both ERs checked (Irving 185,905 rows, Lufkin 162,136). Irving runs v0.76.1 vs local v0.76.2 (drift).

**PLAYBOOK ERRORS to fix in `includes/class-seo-playbook.php`** (v2026.08.25.3, updated 2026-06-17):
1. CRITICAL "~92% of AIO citations come from top-10 rankers" -> REFUTED. Ahrefs Mar 2026: 37.9% top-10, 31% beyond pos 100. This is the load-bearing fact of the GEO/AEO section.
2. CRITICAL "AI referral converts higher (ChatGPT 15.9% vs organic 1.76%)" -> unsupported; paired 54-site test null, RCT no engagement lift; homepage-landing skew explains it.
3. "FAQ retired for non-govt/health" -> no exemption exists.
4. AIO CTR "47-65%" -> use the RCT's **-39.8% organic clicks**.
5. "most volatile core update, ~80% of top-3 shifted" -> 79.5% top-3 URL *churn*, no "record" claim.
6. Local weights "proximity 55/GBP 32/reviews 16-20" -> Whitespark 2026: reviews ~25, behavioural ~20, GBP ~18, on-page ~15.
MISSING: May 2026 core update (21 May-2 Jun); AI-manipulation-is-spam (15 May); GBP review solicitation bans (staff quotas/naming staff/on-premises/selective); FTC enforcement ($4M TruHeight, $53,088/violation) + 24 Jul markup rule; GSC impressions bug; GBP services #81->#22 and hours #85->#21 as top movers; back-button hijack (15 Jun); GPTBot is training-only vs OAI-SearchBot; Cloudflare 15 Sep defaults.

**ROADMAP (evidence-ranked):** P0: (1) fix utility exclusion, (2) GSC impressions-bug guard on every gsc_* read, (3) playbook patch. P1: (4) connect GBP on 3 sites, (5) `review_health` + review-request compliance lint, (6) AI citation tracker (playbook prescribes it, no tool does it), (7) enable llm_crawls. P2: (8) location-page originality guard (Bedford/Grapevine/Addison 62, Burke/Huntington 52 = the REAL content risk, not the legal pages), (9) core-update calendar, (10) tracking-pixel/PHI audit via render_probe, (11) rescore backlog + close version drift.

**Per-site conclusions:** ERs are structurally safe (first-party = 2026 winners); real exposure is city-swap location pages lacking first-party local facts (drive times, highways, transfer hospital, actual wait). Two games: "near me" = local pack (93% presence, AIO ~15%) = GBP discipline; clinical = AIO ~51% of health queries = judge on brand/citation not clicks. IWC is the healthiest site (mean 87) and should be managed for CONVERSION not ranking; has duplicate "Book Appointment" pages (128 and 10253).

**Verdict on more research: NO.** Industry doctrine is verified. The gap is first-party measurement. Only real research task left = `competitor_brief` against named local rivals per market.

## BLOCKER (2026-08-27): Business Profile API quota is 0, approval unanswered 3+ weeks

Operator enabled the GBP APIs and completed OAuth successfully, but quota is 0 so every call 429s (observed on erofwhiterock: "Quota exceeded for quota metric Requests ... mybusinessaccountmanagement.googleapis.com"). The Business Profile API access/quota request form was submitted 3+ weeks ago with NO response from Google.

**This does NOT block review monitoring.** Two different APIs, and the plugin already uses the unblocked one:
- `class-reviews.php` calls **Places API (New)** `places.googleapis.com/v1/places/{place_id}` with fieldMask `id,displayName,rating,userRatingCount,googleMapsUri,reviews`. API key only, pay-as-you-go, NO approval gate. Options: `cc_assistant_reviews_api_key`, `cc_assistant_reviews_place_id` (Admin -> CC Assistant -> Reviews). Built in v0.46, still needs credentials per site.
- `class-gbp.php` calls **Business Profile API** (OAuth, business.manage) — this is the blocked one. It is what would give full review history, owner replies, services/categories/hours writes.

**So `review_health` should be built on Places, not GBP.** Places supports: total count vs the 20-review floor, average rating vs 4.5 stars, newest-review age vs the 3-month recency threshold, and rough velocity from the 5 newest publishTimes. It does NOT give response rate, full history, or the ability to reply. The review-request COMPLIANCE lint needs no API at all.

Do not re-plan review work around GBP until the quota lands. See [[project_gbp_reviews_widget]] for the Phase1/Phase2 split that already anticipated this.

### Why the GBP application went silent (researched 2026-08-27, primary sources)

**LIKELY ROOT CAUSE: a quota-increase request is the WRONG form.** Google's limits page (developers.google.com/my-business/content/limits, updated 2026-05-07) states verbatim: *"If your quota limit for the Google Business Profile API is 0, you have not yet been granted access. **Don't request a quota increase.** Instead, submit the Application For Basic API Access."* A quota-increase request on an unapproved project goes nowhere and generates no reply. Operator described filling in "the form to increase the quota" - confirm which form was actually submitted before waiting any longer.

Correct path (prereqs page, updated 2025-08-28): GBP verified and active **60+ days** + a website on the GBP -> Cloud project -> Organization account -> **GBP API contact form** -> choose **"Application for Basic API Access"** -> supply the **Project NUMBER** (not Project ID, from the Project info card) -> submit from an email that is an **owner/manager** on the profile.

- **No SLA exists.** Google only says "A follow-up email will be sent to you after your request has been reviewed." The "7-10 business days" figure is not in the docs (it appears in auto-generated case emails only).
- **Status oracle = Cloud Console quota, not email.** 0 QPM = not granted. 300 QPM = approved. There is no application-status portal and no dedicated GBP API support channel.
- **3 weeks of silence is normal in 2026**, not diagnostic: multiple developers on discuss.google.dev (applications 2026-07-01, 2026-07-28, reports 2026-08-13/14) report no confirmation email at all and no staff reply. Resubmission is the documented remedy and is not penalised.
- Verified rejection causes, ranked: website on the GBP not matching the applicant/project (a confirmed real rejection); submitting account not owner/manager; Project ID used instead of Project Number; GBP under 60 days or unverified. "Personal Gmail vs Workspace" is UNVERIFIED - do not chase it.

**STING IN THE TAIL - reviews may be unreachable even after approval.** Reviews live only on the legacy v4 host `mybusiness.googleapis.com`, which returns `SERVICE_DISABLED` / "not available to this consumer" and does not appear in the API Library **even for approved developers** (reports through 2026-08-14). v4 is not deprecated (Latest updates shows additions through 2026-07-24) but there is currently no documented path for an approved developer to reach the reviews endpoints. So GBP approval may deliver locations/performance but NOT reviews or replies. Plan the reply workflow around the GBP web UI.

**Therefore Places is not a stopgap - treat it as the primary review-data source.** Places API (New) Place Details (docs updated 2026-08-25) returns `rating`, `userRatingCount` (Enterprise SKU) and `reviews` with real `publishTime` (Enterprise + Atmosphere SKU). Hard limits: **max 5 reviews, no pagination, NO owner-response field, no write access.** Pricing: Enterprise $20/1,000 with 1,000 free/month; Enterprise+Atmosphere $25/1,000 with 1,000 free/month. Four locations polled daily = ~120 calls/month = **free**.

**Never scrape Google Maps for reviews** - Maps Platform Terms No-Scraping clause. Third-party aggregators (Podium, Birdeye, GatherUp, ReviewTrackers) hold their own allowlist and are the legitimate paid alternative.
