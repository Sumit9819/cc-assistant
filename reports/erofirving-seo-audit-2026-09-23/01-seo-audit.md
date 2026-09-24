# SEO Audit: ER of Irving (erofirving.com)
**Date:** 2026-09-23 · **Type:** read-only · **Plugin:** cc-assistant 0.90.1

## What was accessible

| Source | Coverage | Notes |
|---|---|---|
| Google Search Console | Dec 5 2025 to Sep 21 2026, 291 days | Local warehouse was a month stale; re-synced during this audit |
| Live site | Every published post and page (98 at scan, 101 now) | Loopback and external fetches |
| Rank Math, schema | 30-page sample | All emitters |
| Internal link graph | 101 pages, 496 links | Rebuilt nightly |
| Ubersuggest | Domain, backlinks, keywords, PageSpeed | Estimates, not GSC |
| Google reviews | Rating and count | Places API returns no review text, so recency is unknown |
| **Not accessible** | Google Business Profile metrics, GA4, call tracking | Google has not granted Business Profile API quota |

Every figure below comes from one of these sources. Anything I could not measure is labelled unknown.

---

# A. Verdict

The website is technically clean and its blog is strong. **Its commercial side is not measurable, and its homepage is slow enough to lose patients.**

Almost all search traffic goes to national health articles. The pages that bring in patients (homepage, service pages, city pages) earn about 130 to 150 clicks a month, and that number has not moved all year. The listing that probably drives most calls, your Google Business Profile, ranks at the top of the map pack for "ER near me", but none of its calls or direction requests are visible to us.

---

# B. Biggest problems

### 1. The homepage takes 9 to 11 seconds to start loading

Three back-to-back requests on 2026-09-23 measured time to first byte of **8.99 s, 9.98 s and 11.12 s**, getting worse each time. SiteGround's page cache reported `MISS` on all three, so the homepage is being rebuilt from scratch on every visit.

**Control:** on the same server, in the same minute, other pages answered in 0.9 to 1.9 s, and the http-to-https redirect answered in 0.5 s. The network and the server are fine. The problem is specific to the homepage.

Google's own lab test agrees: mobile Largest Contentful Paint 3.5 s, Time to Interactive 9.0 s, and real-user First Contentful Paint rated **SLOW**.

**Why it matters most for an ER:** the homepage is where people land from the Business Profile. A person with chest pain on a phone will not wait 10 seconds.

**Cause: not yet known.** Two separate questions, both needing server access:
- Why the cache never stores the homepage (every page shows `MISS`, not just this one)
- What the homepage does that takes 9 seconds to build when other Elementor pages take 1 second

This matches the unresolved "Irving is slow" report from earlier sessions.

### 2. The business side is invisible

| Where people find you | What we can measure |
|---|---|
| Google Business Profile (map pack) | Nothing. Google has not granted API quota. |
| Calls from the website | Nothing. No call tracking. |
| Website searches | Yes (GSC) |

The GBP listing URL (`?utm_source=gmb`) ranks **#1.6 for "er near me"** and **#2.5 for "emergency room near me"**. That listing is almost certainly your biggest source of patients, and it is completely unmeasured.

### 3. 97% of search clicks are national, not local

Last 90 days, by page type:

| Page type | Clicks | Impressions | Pages |
|---|---|---|---|
| Blog | 3,248 | 970,985 | 84 |
| Homepage and ER hub | 109 | 41,467 | 2 |
| Service pages | 38 | 33,401 | 29 |
| City pages | 7 | 6,051 | 10 |

The blog does its job of building authority, but its readers are mostly outside Dallas-Fort Worth. The question from earlier stands: **a GA4 or call-tracking location split is the only way to know how much of this traffic can ever become a patient.**

---

# C. Technical SEO

| Check | Result |
|---|---|
| Canonical tags | Pass. Every tested page points to itself. |
| Old duplicate addresses (`/blog/x/` vs `/x/`) | Resolved. One live URL each, the other 301-redirects. |
| Duplicate meta description (theme) | **Fixed today** via Hello → Settings. Verified: one tag per page. |
| robots.txt | Pass. AI crawlers allowed. Sitemap declared. |
| XML sitemap | Pass. Rank Math index with post and page sitemaps. |
| http → https | 1 hop, fast |
| www → apex | 1 to 2 hops, fast |
| HSTS header | **Missing.** Same as Irving Wellness Clinic. Enable in SiteGround Site Tools. |
| Redirects | 42 active. 8 chains and 2 that send content URLs to the homepage (see M) |
| Page cache | **Every page tested returned `X-Proxy-Cache: MISS`** |
| Plugin | 0.90.1 live |

### Real issues
1. Homepage speed (B1)
2. Page cache never hitting (B1)
3. HSTS missing (low)
4. 8 redirect chains. The known 301-to-410 chains from the June retirement are harmless; the rest point to URLs that themselves redirect.

---

# D. On-page SEO

- Titles and descriptions on the blog are strong and were reworked in recent sessions.
- The three new posts (flu, blood pressure, vomiting) went live today with query-matched titles.
- **Service pages are structurally sound.** All seven checked (head injury, chest pain, high fever, fractures, abdominal pain, appendicitis, concussion) already carry a "911, ER, or ..." decision section and an ER-vs-urgent-care FAQ. **Correction made during follow-up work:** the low weighted positions below come mostly from searches the pages cannot win: other cities (Fort Worth, Pearland, Bulverde, Nacogdoches, Orange TX, West Palm Beach) and national terms like "chest pain doctor". No rewrite is recommended.

| Service page | Clicks (90d) | Impressions | Weighted position |
|---|---|---|---|
| Diagnostic imaging | 13 | 10,503 | 23.0 |
| Pediatric care | 20 | 9,283 | 21.7 |
| Blood clots | 2 | 4,711 | 11.2 |
| Back pain | 1 | 4,246 | 13.4 |
| Lab testing | 5 | 2,910 | 24.3 |
| Fractures | 0 | 2,281 | 37.0 |
| High fever | 0 | 1,927 | 36.6 |
| Chest pain | 0 | 1,786 | 46.0 |
| Abdominal pain | 0 | 1,352 | 49.9 |
| Kidney stones | 0 | 1,135 | 39.3 |
| DKA | 2 | 926 | 7.0 |

Weighted position averages across every query a page appears for, so treat it as a direction, not a rank.

---

# E. Content quality

The blog is the strongest part of the site. It uses cited sources (MedlinePlus, CDC, NIH, AAOS), short sentences, a team byline linked to the editorial policy, and in-body cards. Today's work added:
- Rebuilt ER vs urgent care guide (2780) with a 20-condition table
- Six in-post gap fills (hairline fracture, chest muscle, 103 fever, quinsy, shin bruise, poison ivy on the face)
- Three new decision posts

**Limit:** commodity explainers ("what is a hairline fracture", "costochondritis") increasingly get answered by Google's AI Overviews. Rankings hold but clicks do not. No title rewrite recovers that. Decision-intent posts ("when to go to the ER for X") hold up better because the reader has to act.

---

# F. Topic coverage

Well covered: fractures and bruises, dehydration, chest pain, throat, poison ivy, fever, burns, bites, sepsis, stroke, heart attack, heat.

Covered today: flu (adults), high blood pressure, vomiting and diarrhea.

Still thin:
- **Kidney stones:** corrected on follow-up. The service page (3990) ranks **2.0** for "kidney stone treatment irving" and **2.7** for "...irving tx"; its weighted 39 comes from Fort Worth, Dallas, McKinney and Plano searches. The real gaps were on the blog post (3858): it was an orphan and never answered the warm-bath, position or ER-vs-urgent-care questions. Both fixed in pendings #1286 and #1288.
- Asthma flare, allergic reaction decision guide, testicular pain, severe headache decision guide (3833 covers only the worst-headache case).

---

# G. Keyword and intent gaps

| Search | Your best page | Position | Note |
|---|---|---|---|
| er of irving | Homepage | 2.0 | Healthy |
| emergency room irving tx | ER hub (541) | 3.0 | Low web volume; demand is in the map pack |
| emergency room near me | GBP listing | 2.5 | Map pack |
| er near me | GBP listing | 1.6 | Map pack |
| urgent care irving tx | Homepage | 8.2 | You are an ER; the ER vs urgent care guide is the right page for this intent |
| irving er | Homepage | 1.8 | Healthy |

**Correction logged during the audit:** an early query average put "emergency room irving tx" at position 37. That figure blended 20 pages. Page by page, the hub ranks #3 and the homepage #5.9.

---

# H. Internal linking

| Measure | Value |
|---|---|
| Published pages | 101 |
| Links between them | 496 |
| Orphans (no inbound content link) | **16** (15.8%) |
| Not reachable from the homepage through content links | **26** |
| Strongest hubs | Homepage (49 in), editorial policy (41), contact (38), imaging (26) |

Orphans include the three new posts, the kidney stone post (3858), the car accident post (4410), food poisoning (4345), RSV vs flu (3808), CO poisoning (3867) and vision loss (3816).

These posts do appear in the theme's "You Might Also Like" box, but that is template output, not an editorial link, so it carries little weight.

---

# I. Schema

- Valid JSON on every page sampled (30 of 30).
- Emitters: Rank Math, a hand-built snippet (MedicalOrganization + EmergencyService), and cc-assistant on two policy pages.
- Only finding: Rank Math writes BreadcrumbList positions as strings. Informational only; Google accepts it.
- No self-serving review markup found.
- Post 3201 (dehydration guide) outputs two BreadcrumbList nodes (Rank Math and cc-assistant page-jsonld). Warn level; Google accepts multiple trails.

---

# J. Core Web Vitals

| Metric | Desktop | Mobile |
|---|---|---|
| First Contentful Paint (lab) | 0.63 s | 2.7 s |
| Largest Contentful Paint (lab) | 0.93 s | 3.5 s |
| Total Blocking Time | 411 ms | 303 ms |
| Time to Interactive | 2.5 s | 9.0 s |
| Layout shift | 0.005 | 0.008 |
| Real-user FCP | SLOW | SLOW |
| Real-user LCP | AVERAGE | AVERAGE |

Layout stability is excellent. Speed is the weak point, and the homepage TTFB (B1) is the biggest single contributor.

---

# K. E-E-A-T

- Team byline "Medically reviewed by the ER of Irving Medical Team" links to the editorial policy, which names the reviewing RN. Consistent across posts.
- Expert quotes are verified word for word against their sources and styled as cards.
- **Reviews: 4.8 stars from 542 Google reviews.** Excellent. Recency cannot be measured because the Places API is not billed for review text.

---

# L. Competitors and authority

| Measure | ER of Irving |
|---|---|
| Domain authority (Ubersuggest) | 11 |
| Referring domains | 162 |
| .gov / .edu links | 0 |
| Organic keywords (est.) | 3,957 |

The closest real competitor type is another freestanding ER (scer247.com, DA 7), which chases off-topic national traffic. Most other "competitors" in the tool are Mayo Clinic, WebMD and Cleveland Clinic, which you cannot and need not outrank.

Local ranking for an ER depends mostly on the Business Profile, reviews and proximity, not on backlinks. Your review count already beats most local ERs.

---

# M. Redirect list

| # | From | Issue |
|---|---|---|
| 1 | /blog/24-7-emergency-room-irving-tx/ | Sends a content URL to the homepage |
| 3 | /blog/best-emergency-room-irving-tx-urgent-care/ | Sends a content URL to the homepage |
| 2, 4 | Two old blog URLs | Point at URLs that themselves redirect |
| 5, 6, 8 to 11 | Old duplicates | 301 then 410. Known, harmless, left alone by decision on 2026-08-06 |

Low priority. Fixing #2 and #4 (point at the final URL) is a 2-minute job in Rank Math.

---

# N to P. What does not need fixing

- **The Mar-to-Aug traffic drop.** Already explained: the June 30 retirement of about 1,050 clicks a month of off-topic posts (correct 410s), plus the GSC impression logging bug that over-counted until Apr 27 2026. Traffic has stabilized since July and September is on pace for about 2,350 clicks.
- **City pages.** No near-duplicate pairs at a 0.6 similarity threshold. They are not doorway pages. Caveat: the check reads stored content, which can lag on Elementor pages.
- **Duplicate URLs.** Resolved.
- **Schema.** Clean.
