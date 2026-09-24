---
name: project_gbp_reviews_widget
description: "cc-assistant Google Reviews Elementor widget — Phase 1 built (Places API, cron-cached); Phase 2 = GBP v4 for all reviews"
metadata: 
  node_type: memory
  type: project
  originSessionId: ce230c56-9945-46a4-ad73-0056289dd069
---

Building a native Google Reviews feature into cc-assistant instead of paying for Smash Balloon Pro. Decided 2026-06-24; user greenlit a low-risk experiment.

**Phase 1 — BUILT (v0.46.0, lint-clean, in local plugintesting copy, NOT yet deployed):**
- `includes/class-reviews.php` — data layer (Places API v1 Place Details fetch, up to 5 reviews), caches in option `cc_assistant_reviews_cache`, daily cron `cc_assistant_reviews_refresh`, isolated admin page (CC Assistant → Reviews) with API-key + Place-ID fields + "Fetch now". Options: `cc_assistant_reviews_api_key`, `cc_assistant_reviews_place_id`.
- `includes/class-reviews-widget.php` — Elementor widget `cc-google-reviews` ("Google Reviews (CC)"), renders ONLY cached data (no FE external call). NO self-serving Review/aggregateRating schema by design.
- Wired in `cc-assistant.php` via `elementor/widgets/register` + admin/cron bootstrap.

**To TEST:** needs a Google **Places API key** + the business **Place ID** (user must supply — can't generate an API key). Enter on CC Assistant → Reviews, Fetch now, then drop the widget on a page.

**Key constraint (why this isn't trivial):** public Places API returns max 5 reviews. ALL reviews (e.g. all 40) require **Business Profile API v4** + Google's separate legacy-API access approval. GBP not even connected on irvingwellnessclinic yet (gbp_locations → gbp_not_connected). `class-gbp.php` OAuth scope `business.manage` already covers reviews; docstring says "structured to add once granted."

**Phase 2 (later):** swap `CC_Assistant_Reviews::fetch_from_google()` to the v4 reviews endpoint; storage + widget unchanged.

Deploy to client sites via zip per [[reference_plugin_dev_vs_remote_deploy]] + [[reference_zip_packaging_gotcha]] (Python zipfile, forward slashes). PHP lint via [[reference_local_php]]. Perf rule honored per [[feedback_plugin_performance]] (cron-cache, no FE queries).


## v0.76.7 (2026-08-27) — Places path promoted to the primary review source

GBP API is blocked (quota 0, application unanswered; and reviews may be unreachable even after approval since they live only on legacy v4 `mybusiness.googleapis.com`, which returns SERVICE_DISABLED even for approved developers). So Phase 2 is parked and Places is now the primary source, not a stopgap. See [[project-portfolio-instrumentation-gap]].

Shipped:
- **Real menu entry.** `register_menu()` had `add_submenu_page( null, ... )` — parent null, hidden. Its only door was a secondary button labelled "Google Reviews widget" on the Settings **Health** tab (database/error-log territory), so the operator could not find where to enter the API key and reached it only via a pasted URL. Now parented to `cc-assistant` and appears as **CC Assistant -> Reviews**.
- **`published_at` is now stored.** `normalize_reviews()` kept only `relativePublishTimeDescription` ("2 months ago") and DISCARDED `publishTime`. A human string cannot be compared to the 90-day threshold, so recency — the binding local-pack dimension — was unmeasurable. Re-fetch after upgrading to populate it; existing caches have no timestamps and report `newest_age_days: null`.
- **`CC_Assistant_Reviews::health()`** — one brain for the admin page and the tool so they cannot disagree. Thresholds: 20+ reviews, 4.5+ rating, newest under 90 days (BrightLocal 2026, n=1,002, stated intent). Returns per-check pass/fail, plain-language issues, estimated pace, verdict pass/warn/fail/error/not_configured.
- **GET `/reviews` REST route** (manage_options, never returns the API key; `?refresh=1` fetches first) + **`review_health` MCP tool**. Before this the review cache was readable ONLY inside wp-admin — there was no way to verify a Places setup or track rating/count/recency remotely.
- Rebuilt admin page: status card with the three checks, setup steps (enable Places API (New), restrict key, Place ID finder), and error decoding (REQUEST_DENIED = API not enabled or key restricted elsewhere; INVALID_ARGUMENT = bad Place ID; billing message = no billing account).

Caveat to keep repeating: Places returns **max 5 reviews and no owner replies**, so total/rating are exact, pace is an estimate from the newest few, and response rate is unobtainable. Do not promise response-rate tracking.

## RESEARCH 2026-08-28 — the Places design has a ToS problem, and the omission is documented

**COMPLIANCE (act on this):** Maps Platform Terms §3.2.3 (PRIMARY, cloud.google.com/maps-platform/terms): *"Customer will not... (iii) copy and save business names, addresses, or user reviews."* Maps Service Terms §14.3 permits caching lat/long only (30 days) and place IDs indefinitely. **So caching Places review TEXT in a WP option — which is exactly what Phase 1 does — is not permitted.** Displaying reviews also requires author attribution (avatar, name, profile link) plus a path to the review on Google Maps.
Implication: keep storing aggregate **rating + userRatingCount + newest publishTime** (metrics, not content) for review_health, but do NOT persist review bodies. The Elementor widget's cache-and-render design needs revisiting or retiring.

**Why `reviews` is silently absent from a 200 (erofirving):** documented behaviour, not a fault. Places "Choose fields to return" (PRIMARY): *"When a response message is parsed, and a field in the response message contains its default value, the field may be omitted from the response even if you specified it in the response field mask."* An empty repeated field is simply dropped.

RULED OUT with evidence: billing not enabled (would be 403 PERMISSION_DENIED, and `rating`/`userRatingCount` are **Enterprise** SKU fields that DID return, so billing works at that level); invalid field name (would be INVALID_ARGUMENT; a 200 proves `reviews` was accepted); wrong API / key API-restrictions (would be 403 SERVICE_DISABLED); free-tier exhaustion (results in being charged, not blocked).
SKU boundary is a real coincidence — id=Essentials, displayName/googleMapsUri=Pro, rating/userRatingCount=**Enterprise**, reviews=**Enterprise+Atmosphere** — the cutoff lands exactly there. BUT no Google doc says Atmosphere needs enablement/approval, and **there is no Atmosphere toggle in Cloud Console** (verified absent). Treat the entitlement-gate theory as UNVERIFIED.
Ranked causes: (1) the outbound request did not actually carry `reviews` in the mask (WP HTTP layer / security plugin altering headers); (2) Google genuinely has no review content for that place ID — sub-cause: service-area/no-storefront listings return empty review data (SECONDARY: Smash Balloon 2026-08-14, Outscraper); (3) place ID resolves to a listing Google will not serve reviews for; (4) undocumented Atmosphere gate.

**No 2025-2026 restriction on review data.** Full Places API (New) release notes read: zero entries gating/deprecating reviews. Still documented, max 5, relevance-sorted.

**Legacy Places API is NOT a workaround for new projects.** Went Legacy 2025-03-01 and is "not available in new Cloud projects" — existing projects only, feature-frozen, no announced sunset (Google promises 12-month notice).

**The durable answer is Business Profile API v4:** FREE, first-party, ALL reviews paginated 50/page with text, star rating, `createTime` and reply status, and it is the only option that permits storing the data. Allowlist via support.google.com/business/contact/api_default (Org account + verified GBP active 60+ days). 0 QPM = not approved; 300 QPM = approved. Confirms [[project-portfolio-instrumentation-gap]]: submit the ACCESS application, not a quota increase.
Zero-code recency stopgap: enable the GBP "Customer activity" email alert.
Scraper APIs (DataForSEO $0.075/1k, Apify $0.25/1k, Outscraper 500 free then $3/1k, SerpApi $25/mo) exist but are scrapers — do not use for client sites.
Google Takeout `reviews.json`: sources conflict on whether it contains inbound or authored reviews — UNVERIFIED, test before relying on it.

## CORRECTION 2026-08-28 — "Business Profile API v4" is not a findable product; reviews may be unreachable

Operator could not find it in the API Library. That is correct behaviour, not user error.

There is no product named "Business Profile API v4". The Business Profile APIs are a FAMILY, and the ones that appear in the Cloud Console API Library are: My Business Account Management, My Business Business Information, My Business Notifications, My Business Verifications, My Business Place Actions, My Business Lodging, My Business Q&A, and Business Profile Performance. **None of them return reviews.**

Reviews (`Reviews.ListReviews` + reply endpoints) exist ONLY on the legacy v4 host `mybusiness.googleapis.com`, which **does not appear in the API Library at all** and returns `403 SERVICE_DISABLED` / "Service is not available to this consumer" — reportedly even for developers who HAVE been granted Basic API Access (reports through 2026-08-14). There is currently no documented path for an approved developer to reach the reviews endpoints.

**So: do NOT promise review text, review history, response-rate tracking, or reply-from-the-plugin on any timeline.** Combined with the Places finding (200 with `reviews` absent even when requested alone, verified by per-field probe on erofirving 2026-08-28), review CONTENT is not obtainable through any Google API available to us today.

**The Basic API Access application is still worth submitting — but for the OTHER APIs, not reviews.** Approved access unlocks categories, services, hours, NAP (Business Information) and local-pack KPIs: impressions, calls, direction requests, website clicks (Performance). Per Whitespark 2026 those are the cheapest tested movers (predefined services #81->#22, correct hours #85->#21, moving within 24-72h). Reframe the ask accordingly.

**What actually works for reviews today:** rating + review count via Places API (New) — verified working, compliant as metrics. Recency: GBP "Customer activity" email alerts (free, zero code). Automated recency/response-rate would require a third-party aggregator (Podium, Birdeye, GatherUp, ReviewTrackers) that holds its own allowlist — paid, adds a processor, operator decision.

## DECISION 2026-08-28 — review integration is CLOSED. Do not reopen.

Operator ended this workstream. Reasoning, and it is sound: the point was auto-displaying review CONTENT on pages, which is now proven impossible (Places returns no review bodies even when requested alone; the GBP reviews host is not in the API Library and 403s even for approved developers). Without that, the remaining monitoring value is nil **because the review operation is already healthy**: erofirving is 4.8 across 518, they reply to every review, and they receive 2-3 reviews every other day (~35-40/month).

Scored against the thresholds that gate local-pack choice, Irving passes all three comfortably — count 518 vs 20, rating 4.8 vs 4.5, recency ~2 days vs 90. **Reviews are a solved problem for this portfolio; there is no work to propose here.** A future session should NOT pitch review tooling, aggregators, or the GBP application as a reviews play.

What stays shipped and costs nothing: rating + review count via Places (compliant metrics, daily cron) and the Elementor rating badge, which still renders "4.8 from 518 Google reviews" as page social proof. Individual review cards render nothing by design. `review_health` remains available but is not a priority surface.

The GBP Basic API Access application retains value ONLY for Business Information (categories, services, hours, NAP) and Performance (impressions, calls, directions, website clicks) — never pitch it for reviews.

**Redirect effort to the genuinely open items in [[project-portfolio-instrumentation-gap]]:** location pages carrying no first-party local facts (erofirving Bedford/Grapevine/Addison 62-67, eroflufkin Burke/Huntington 52); eroflufkin service pages at word_count 0 (animal bite, migraine, burn, allergic reaction, UTI, flu, laceration); the portfolio-wide E-E-A-T ceiling (schema Person resolves to the ORG name, no sameAs/credential, so every post caps at 15/25 — real named-provider bylines are the biggest single scoring lever left); erofwhiterock rescore; AI citation tracking; llm_crawls still disabled.
