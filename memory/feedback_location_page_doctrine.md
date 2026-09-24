---
name: feedback_location_page_doctrine
description: "2026 doctrine for local \"location\" / service-area landing pages across the ER/local sites — when to build, when NOT to, how to differentiate, where to place"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: cf6b72ff-7286-4f35-bac6-cc72e684abbc
---

How to build local location / service-area landing pages that rank in 2026 (researched via deep-research, two passes, verified against Google primary docs). Applies to all four local sites (erofwhiterock, erofirving, eroflufkin, irvingwellnessclinic).

**Why:** The old "one template, swap the city name, publish N near-identical pages" approach is now a NAMED spam pattern, not just weak. Google spam policy lists "multiple pages targeted at specific regions/cities that funnel users to one page" as doorway abuse; March 2024 scaled-content-abuse policy bans mass low-value pages "no matter how created"; rewording ("red"→"maroon") does NOT count as unique (Sterling Sky documented a manual "thin content" action on a 3,000-page reworded site). Two failure modes: organic *filter* (Google silently picks one, buries the rest) and *manual penalty*.

**How to apply:**
- **Core distinction:** physical-location page = the place you SIT (homepage/location page owns it on the verified address; GBP service-area settings have ZERO ranking effect). Service-area page = a place you SERVE but aren't physically in. NEVER build a service-area page for the city you're located in — that cannibalizes the homepage. (See [[feedback_verify_service_exists_before_service_page]] for the parallel service-existence gate.)
- **When NOT to build:** the geo term the homepage already ranks for (check GSC first — see [[feedback_homepage_title_data_check]]); any service×city matrix (cannibalizes on implicit searches — consolidate instead); volume for its own sake (raises penalty exposure).
- **Each kept page must be genuinely local + unique:** text driving directions from THAT neighborhood, area landmarks/drive-time, local reviews, area-specific FAQs, neighborhood expertise. Litmus: if two pages are ~90% identical side-by-side, red flag. Honor [[feedback_no_html_widget_for_content]] / native Elementor widgets and [[feedback_inline_links_only]].
- **YMYL layer (ER/clinic = highest PQ bar, Trust paramount):** prominent NAP, hours, accreditation; bylines "where expected" — but erofwhiterock has NO provider bylines until physician consent ([[feedback_erofwhiterock_no_provider_bylines_yet]]).
- **Architecture:** hub-and-spoke — a /service-areas/ hub links to each neighborhood spoke, spokes link back, plus contextual inline links. Do NOT cross-link every spoke to every other spoke. Keyword-in-URL is only "very lightweight" — content uniqueness is what avoids the doorway penalty, not URL structure.
- **Schema:** most-specific subtype (MedicalClinic/EmergencyService, not generic LocalBusiness); full facility/Organization schema on ONE page only (not every neighborhood page); neighborhood pages reference the canonical facility node via @graph @id + areaServed, not competing per-page business nodes.

**Applied to erofwhiterock (2026-06-02):** homepage owns "er of white rock" (pos 1.12), "white rock er" (1.85), "emergency room" (1.48), "er near me" (1.76) — so NO White Rock location page (would cannibalize). Lake Highlands + Lakewood service-area pages already exist (correct — places served, not occupied). East Dallas is the genuine gap. Also flagged: 9-page cannibalization pileup on "er of white rock" (homepage wins, low urgency).
