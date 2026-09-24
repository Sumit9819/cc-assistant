# SEO Audit: Irving Health and Wellness Clinic
**Date:** 2026-09-22 · **Method:** read-only. No SQL, no writes during the audit phase.

## What was accessible

| Source | Status | Notes |
|---|---|---|
| cc-assistant MCP | Full | inventory, link graph, schema scan, quality scores, GSC warehouse |
| Google Search Console | Full | 11,535 rows, last sync 2026-09-21 |
| Live HTTP (curl) | Full | robots.txt, sitemaps, head meta for all 82 indexable URLs |
| Lighthouse (US) | Full | desktop + mobile |
| Ubersuggest | Full | authority, keywords, competitors, backlinks |
| Direct database / SQL | NOT AVAILABLE | revisions, transients, orphaned postmeta are UNMEASURED |
| Competitor URLs | NOT PROVIDED | the original request left the `[COMPETITOR URLS]` placeholder unfilled; section L uses auto-detected rivals |

Measurement caveat: the auditing machine reaches this server slowly and erratically (TTFB 1.6s to 14s). A static-file control was equally slow, so that is the route, not the server. All speed conclusions come from US-based Lighthouse.

---

# A. Overall readiness: 72 / 100

| Dimension | Score | Basis |
|---|---|---|
| Technical SEO | 94 | canonicals, robots, sitemaps, redirects, schema all verified clean |
| Core Web Vitals | 88 | desktop excellent, mobile good |
| On-page meta hygiene | 96 | zero missing, zero duplicate across 82 URLs |
| Content quality / E-E-A-T | 85 | named APRN, credential schema, .gov citations, editorial policy |
| Topical coverage | 78 | IV, hormones, weight loss deep; aesthetics and local thin |
| Click conversion | 28 | 101,469 impressions produced 100 clicks |
| Domain authority | 25 | DA 10, and the profile is mostly spam |
| AI search readiness | 80 | crawlers welcomed and active; llms.txt live |

# B. Biggest problems

### 1. Visibility grew 260%. Clicks grew 22%.

| Metric | Last 28d | Prior 28d | Change |
|---|---|---|---|
| Impressions | 101,469 | 28,218 | +259.6% |
| Clicks | 100 | 82 | +22% |
| Avg position | 10.2 | 28.6 | +18.4 |
| Non-branded clicks | 59 | 32 | +84% |

Site-wide CTR is 0.099%.

### 2. Four queries account for 61,676 impressions and zero clicks

| Query | Impressions | Clicks | Position | URL credited |
|---|---|---|---|---|
| immunity iv near me | 20,331 | 0 | 1 | `/?utm_source=gmb&utm_medium=gmb` |
| recovery iv near me | 18,195 | 0 | 1 | same |
| energy iv near me | 13,440 | 0 | 1 | same |
| wellness iv near me | 9,710 | 0 | 1 | same |

INFERENCE, not verified: position 1 with zero clicks on 20,000 impressions is not how an organic web result behaves. These are almost certainly Google Business Profile / local pack appearances, where the user taps the listing, calls, or gets directions instead of visiting the site. If correct, this is good news reported badly, and the action is to instrument GBP calls and direction requests. Confirm in GSC Search Appearance against GBP insights. Do NOT remove the GBP UTM URL.

### 3. The backlink profile is essentially fake

Reported: 204 backlinks, 101 referring domains, DA 10. Actual: 24 of the top 25 referring domains by authority are ONE spam network. Identical page title ("Boost your Google rankings with Premium PBN and Link Building"), identical URL pattern (`/all/1475/32.html`), identical keyword-stuffed anchor, all dofollow, spam scores to 60, first seen July to September 2026 and still accumulating. Host domains are unrelated junk: laundry, psilocybin, casino mirrors, kitchen design.

Anchor-text data: 63 root domains carrying the spam string for irvingwellnessclinic.com, 51 more for irvingmedspa.com.

Genuinely real links: trendhunter.com (DA 81, nofollow, real editorial feature), irvingmedspa.com (owned), plus LinkedIn, Yelp, PRNewswire, Yahoo Finance and getfocushealth.com found via web search.

The anchor text targets the domain name rather than money keywords and reads like the spam page's own product listing, which points to unsolicited collateral spam rather than a purchased campaign. UNRESOLVED: confirm with the client whether anyone bought links around June or July 2026.

# C. Technical SEO

Verified clean, which is unusual:

| Check | Result |
|---|---|
| Canonical tags | 82/82 self-referencing, absolute, in head, single |
| Meta robots | 72 indexable; 2 deliberate noindex (campaign page, duplicate booking page) |
| robots.txt | blocks crawl traps, feeds, author archives, search; explicitly welcomes GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot |
| XML sitemap | valid, 57 posts + 25 pages |
| Category archives | noindex, follow. No archive bloat |
| Legacy URL redirects | all 4 tested legacy URLs 301 correctly |
| Schema JSON-LD | 14 pages scanned, 0 issues |
| llms.txt | live, 27.5KB, well populated |
| Duplicate slugs | none |

### Real issues found

**1. Two-hop redirect chain.** `http://www` to `https://www` to `https://apex`. CANNOT be fixed in .htaccess: hop 1 is SiteGround nginx HTTPS-Enforce, which runs before Apache. Fix is HSTS (no Strict-Transport-Security header currently exists). LOW PRIORITY: real traffic arrives from Google and GBP at zero hops.

**2. Schema entity problems.** Rank Math emits a `Person` literally named "Irving H&W Clinic" with `@id` at `/author/irving/`, a URL robots.txt disallows. The real Lori Secerovic Person node sits unconnected in a Code Snippets block at `#practitioner-lori`. `https://irvingwellnessclinic.com/#website` is defined twice with different content by two emitters. 22 of 38 managed-schema posts emit a duplicate BreadcrumbList (warn, not critical).

**3. NAP conflict.** Site says Suite 100 consistently (schema and footer). PRNewswire and Yahoo Finance say Suite 110, which is ER of Irving's suite in the same building. UNRESOLVED.

**4. Post 6492 absent from sitemap.** Intentional compliance hold. Not a defect.

**5. `/book-appointment-2/` (10253)** is an intentional noindex per operator instruction. Audits will keep flagging it as an orphan and duplicate title. Expected output, not a finding.

# D. On-page SEO

| Check | Count | Verdict |
|---|---|---|
| Missing meta title | 0 | Pass |
| Missing meta description | 0 | Pass |
| Duplicate titles | 0 | Pass |
| Duplicate descriptions | 0 | Pass |
| Titles over 60 chars | 34 | Review |
| Descriptions over 160 chars | 1 | Fixed this session |

IMPORTANT: only 2 of the 34 long titles come from Rank Math's site-name suffix. The other 32 are hand-written, so there is NO global template fix available. A template change would strip branding from 8 correctly-sized titles for almost no gain.

# E. Content quality

73 scored posts: mean 91.5, median 97, 68 strong, 3 solid, 2 weak, 0 poor.

The pattern is inverted. The blog outscores the service pages:

| Page | Type | Score |
|---|---|---|
| 610 Medical Weight Loss | Money page | 50 |
| 9925 Botox | Money page | 55 |
| 10145 New Patient Consultations | Money page | 63 |
| Most blog posts | Informational | 87 to 97 |

CORRECTION to that reading: a direct E-E-A-T audit of 610, the worst scorer, PASSES cleanly. Visible byline, Person schema with credentials and LinkedIn sameAs, 5 citations to NIDDK, NEJM, FDA and CDC at 1.71 per 1,000 words. The low composite is structural, not a trust failure, and site history already records these parsers disagreeing with the live DOM. Treat the 50 as "inspect", not "broken".

### Compliance

Two meta descriptions asserted more than their own articles supported (glutathione, and the 7-benefits post). Both fixed this session. Body copy on both was already compliant and well hedged.

# F. Topic coverage

| Cluster | Pillar | Supporting | Depth |
|---|---|---|---|
| IV therapy | 137 | 13 | Strong |
| Weight loss / GLP-1 | 610 | 11 plus 2 drug pages | Strong |
| Hormones | 623 | 11 | Strong |
| Aesthetics | 618 | 13 plus 3 service pages | Moderate |
| Wellness coaching | 631 | 4 | Thin |
| Functional / diagnostics | NONE | 4 | No pillar |

Missing content types: pricing pages (high value), local landing pages beyond Irving (high), original data or statistics (high, best link-earning asset), glossary (medium), alternatives pages (medium), FAQ hub (medium), case studies (limited by medical consent).

# G. Keyword and intent gaps

Self-competition. These are overlap candidates, NOT proof of harm:

| Query | Own URLs | Impressions | Clicks |
|---|---|---|---|
| wellness clinic | 6 | 4,513 | 0 |
| irving health and wellness clinic | 15 | 1,046 | 28 |
| iv drip for fatigue | 8 | 803 | 0 |

Reachable head terms:

| Keyword | Volume | Position |
|---|---|---|
| wellness clinic near me | 4,400 | 13 |
| wellness coach near me | 1,600 | not ranking (SD 9, winnable) |
| health and wellness clinic | 1,300 | 12 |
| iv wellness near me | 1,000 | 34 |

# H. Internal linking

Healthy and deliberately built. 432 edges, 82 of 85 pages have inbound links, orphan rate 3.5%. Top hub is the homepage at 33 inbound. Only one real orphan existed and it was fixed this session.

# I. Schema opportunities

Current state clean. Highest-value additions: fix the author entity at the emitter, `Offer` and `priceRange` on service pages, `MedicalWebPage` plus `lastReviewed` on YMYL pages, `Service` with `areaServed`, NPI Registry in Lori's `sameAs`.

WARNING: do NOT run `propose_schema` on this site. A dry run on post 6220 showed it scrapes H2 headings into FAQ entries, including a call-to-action containing a phone number. That is how sites lose rich results.

# J. Core Web Vitals

| Metric | Desktop | Mobile | Threshold |
|---|---|---|---|
| LCP | 624 ms | 2.5 s | 2.5s or less |
| CLS | 0 | 0 | 0.1 or less |
| TBT | 0 ms | 0 ms | 200ms or less |
| FCP | 584 ms | 2.0 s | 1.8s or less |
| Speed Index | 2.0 s | 4.5 s | 3.4s or less |

Mobile passes but sits exactly on the LCP boundary. Risks: the redirect chain (630ms mobile), 46 to 48 KB unused CSS, and three heavy PNGs (2.45 MB, 1.10 MB, 1.01 MB) that should be WebP. Media library is otherwise fine: only 3 images over 200 KB out of 500 scanned.

# K. E-E-A-T

Strong, and better than every local competitor checked. Named clinician with a bio page, Person schema with credentials and LinkedIn sameAs, editorial policy page, .gov and peer-reviewed citations, full policy page set.

Gaps: the author entity problem above; 57 of 58 posts authored by a generic account; no review schema; and the About page FAQ says "Walk-ins are welcome", which contradicts the scheduled-care positioning and needs a factual answer from the clinic before anyone edits it.

# L. Competitors

Caveat: competitor URLs were never supplied. These are auto-detected.

| Competitor | DA | Traffic | Shared kw | Kw you lack |
|---|---|---|---|---|
| hydrationroom.com | 31 | 29,700 | 107 | 4,025 |
| vitafusiondoctors.com | 8 | 281 | 89 | 1,045 |
| happyhealthwellness.com | 12 | 3,116 | 51 | 1,016 |
| webmd / cvs / bswhealth | 65 to 94 | massive | 47 to 142 | not a realistic target |

Exploitable: vitafusiondoctors.com is DA 8, below this site, yet shares 89 keywords. Three Irving competitors audited earlier had zero bylines and zero citations. hydrationroom.com wins on scale and links, not on quality.

# M to P. Fix lists

See `02-changes-applied.md` for what was done and `04-open-items.md` for what remains.

Zero-click pages by lifetime impressions: `/top-3-anti-aging-treatments-fine-lines-wrinkles/` 2,408; `/how-many-laser-hair-removal-sessions/` 1,303; `/wellness-coaching-irving-tx/` 1,133; `/choose-weight-loss-clinic/` 1,092; `/semaglutide-irving-tx/` 811.

Per the standing operator rule, NONE of these get noindexed. A page at position 8 to 15 has simply not been clicked yet; deindexing turns a maybe into a certain zero.

Genuinely thin pages: only 3 under 400 words, all utility (booking, blog archive). No blog consolidation is recommended; the decision gate already refused the IV cluster merge on evidence and that refusal stands.

# Q to S. Priority plan

**30 days.** Fix the schema author at the emitter. Resolve the NAP suite. Answer the PBN question. Enable HSTS. Convert 3 PNGs to WebP. Instrument GBP calls and direction requests. On or after 2026-10-12, run `gsc_inspect_url(post_id=6296)` and read coverage_state: that single result decides whether page-level treatment works or whether the constraint is authority.

**60 days.** Tier 1 citations per the link brief. Chamber membership. Review velocity program. IV pricing page. Las Colinas and Valley Ranch pages, genuinely distinct, not city-swaps. `MedicalWebPage` plus `lastReviewed` across YMYL pages.

**90 days.** Original data piece with outreach. Functional medicine pillar. Glossary. Deepen wellness coaching. Re-audit targeting DA 14 or better, 150 or more referring domains, and CTR above 0.5%.
