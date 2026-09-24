---
name: google-2026-seo-doctrine
description: "Google's documented SEO rules as of May 2026 — AI Optimization Guide, March 2026 core update site-wide signal, FAQ deprecation, back-button hijacking spam policy, deprecated schema types, named ranking systems"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 0148947e-9e56-42d0-abd7-8d217c330a37
---

**STALENESS NOTE (2026-08-27):** parts of this file were re-verified and CORRECTED. Read [[reference-search-evidence-2026-08]] first: FAQ carve-out was wrong, GSC impressions were inflated 2025-05-13 to 2026-04-27, and the March-2026 volatility framing is unsupported.

Google's current (2026) documented SEO doctrine, distilled from the full /search/docs tree. Use this as the canonical reference when proposing site changes or plugin features; verify against live docs if more than 3 months stale.

**AI Optimization Guide (published 2026-05-15, /search/docs/fundamentals/ai-optimization-guide)** is the AEO/GEO mythbuster:
- AI Overviews + AI Mode use the core ranking systems (RAG + query fan-out). NO new ranking factors.
- DO NOT need: llms.txt, special AI markup, AI-specific schema, content "chunking", separate per-query-variant pages, "inauthentic mentions" (linkbuilding).
- DO: foundational SEO, unique non-commodity content with a POV ("Why We Waived the Inspection" beats "7 Tips for First-Time Homebuyers"), crawlability, page experience, supporting images/videos.
- Eligibility: page must be indexed + eligible to show in Search with a snippet.

**March 2026 Core Update (Mar 27 → Apr 8, 2026)**: Most volatile core update on record (~80% of top-3 positions shifted at peak per third-party telemetry). Helpful Content signal went SITE-WIDE — one bad section can drag the whole domain. E-E-A-T scope expanded beyond YMYL into trades, local services, professional practices.

**Spam-policy additions**:
- 2024-03-05: expired-domain abuse, scaled-content abuse (covers AI-generated bulk content), site-reputation abuse
- 2024-05-05: site reputation abuse enforcement (third-party content exploiting host signals)
- 2026-04-13: back-button hijacking (history.pushState/popstate abuse)

**Schema deprecations**:
- 2026-05-07: FAQ rich results retired for ALL sites (the govt/health carve-out was the SEPT 2023 state and does NOT apply to the 2026 retirement; verified 2026-08-27, see [[reference-search-evidence-2026-08]]) — others lose the SERP feature, schema stays valid but no longer surfaces
- 2025: book actions, course info, estimated salary, learning video, special announcement, vehicle listing all deprecated
- data-vocabulary.org: no longer eligible (use schema.org)

**Named active ranking systems** (/search/docs/appearance/ranking-systems-guide): BERT, Deduplication, Exact Match Domain demotion, Freshness, Link Analysis/PageRank, MUM, Neural Matching, Original Content, Passage Ranking, RankBrain, Reliable Information, Reviews, Site Diversity, SpamBrain. **Helpful Content System (2022) was folded INTO core ranking March 2024** — no longer a separate system.

**Core Web Vitals thresholds** (75th percentile real-user data): LCP ≤2.5s, INP ≤200ms (replaced FID 2024), CLS ≤0.1.

**hreflang strictness**: reciprocity required, self-reference required, absolute URLs only, `<head>` placement only, canonical must point inside the hreflang cluster, ISO 639-1 language + optional ISO 3166-1 alpha-2 region (use `GB` not `UK`).

**Why this matters for our work**: Every clinic site optimization (erofwhiterock, erofirving, eroflufkin, irvingwellnessclinic) should anchor to these rules, not blog-post tactics. See [[cc-assistant-seo-alignment]] for the plugin gap analysis derived from this doctrine.
