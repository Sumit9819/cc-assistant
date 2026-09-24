---
name: web-search-for-niche-only-research-refuse-off-topic-content
description: "When researching for blog posts or page refreshes, web search for new authoritative sources but stay strictly inside the site's declared niche — Google penalizes topical drift."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

When researching for content, web search is mandatory (sources go stale within a year; new clinical guidance, FDA approvals, and authoritative studies appear constantly). But every search query and every cited source must connect to one of the site's declared service lines or adjacent topics from the site's profile. Off-topic publishing — even high-quality content — triggers topical-authority penalties and dilutes the site's niche signal.

**The discipline:**
1. Before searching, name the service-line bridge. "I'm researching X for service-page Y / topic-cluster Z." If you can't name the bridge, don't search.
2. Prefer authoritative sources in-niche: CDC, NIH, MedlinePlus, AHA, JAMA, FDA, NEJM, peer-reviewed journals, specialty-society guidelines (ASAPS for aesthetic, AACE for endocrinology, AAAM for med-spa, etc.).
3. Refuse general-interest sources for medical/wellness content (lifestyle magazines, Healthline-style aggregators that re-cite without primary sourcing).
4. When a search surfaces interesting-but-off-topic content (e.g. business advice, tech, unrelated medicine), do NOT propose it. Note the topic and move on.

**Why:** 2026-05-12 — User explicitly stated: "always search web search for new thing related to our website. This way we can catch new thing fast and provide information on that, but only things that comes inside our niche, as google in penalizing website that goes beyond their niche." Topical authority is a measurable ranking factor; sites that publish broad off-topic content lose niche relevance scores.

**How to apply:**
1. Each site's plugin memory (Rules section, via `get_site_memory`) should declare its niche scope. Read it before researching.
2. For Irving Health & Wellness Clinic specifically: in-niche = med spa / aesthetic / IV therapy / hormone / medical weight loss / wellness coaching + adjacent (metabolic health, longevity, sleep, stress, recovery). Out-of-niche = general business, tech, unrelated medical specialties.
3. Pair this rule with [[feedback_content_quality_and_research]] (the broader content-quality rule that already mandates external authoritative sourcing).
4. When in doubt: read the site's homepage and three top service pages. If the topic doesn't bridge to those, it's out of scope.
