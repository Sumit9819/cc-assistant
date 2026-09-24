# ER of White Rock — live site audit, September 9, 2026

The site is available and CC Assistant 0.89.3 is installed. The main priorities are inaccurate or unsupported patient-facing statements, broken internal links, an incorrect blog canonical, and incomplete language/author cleanup. Basic HTML checks alone substantially understate these problems.

**Google indexing across all 129 published URLs:** 117 indexed; 7 discovered but not indexed; 2 crawled but not indexed; 3 unknown to Google. The unknown group includes today's new article. Nine URLs have explicit non-indexed statuses; an unknown status remains a separate observation.

This audit changed no live content, settings, redirects, authors or approval proposals. It refreshed 30 dates in the local Search Console warehouse and saved audit observations. The current content strategy is fresh and selects the established ER of White Rock author, ID 4.

## Evidence and coverage

- Inventoried **129 published URLs: 39 posts and 90 pages**, including English and Spanish content.
- Fetched fresh HTML for all 129, between **14:58 and 15:02 UTC**. Every published URL returned HTTP 200. Saved HTML and response headers; these captures reported cache misses.
- Checked robots.txt, both XML sitemaps, and **47 additional requests** covering internal destinations, archives, redirects, a linked PDF, and clean blog URLs.
- Ran Chrome at **390px and 1440px on eight representative pages**, with scrolling, screenshots and axe accessibility checks. Forms were not submitted.
- Refreshed GSC query/page observations through **September 7** and compared two complete 28-day date windows. These rows omit anonymized queries and can omit other API data; they are not complete property traffic totals.
- Inspected Google indexing separately across the published inventory. See `INDEXING-RESULTS.md` and `indexing-results.csv` for each URL's actual result, last crawl and canonical selection. Google index records are not real-time browser tests; endpoint responses may use the plugin's one-hour cache.

Raw evidence: `initial.json`, `broad.json`, `inventory.json`, `crawl.json`, `extra-crawl.json`, `html/`, `browser-checks.json`, `gsc-refresh.json`, `gsc-analysis.json`, `verification.json`. Human-readable inventories: `page-inventory.csv` and `broken-links.csv`.

## Fix first: patient information and trust

### WR-01 — Urgent: incorrect stroke-treatment wording and conflicting emergency instructions

**Confirmed rendered content, page 2482:** [stroke service](https://erofwhiterock.com/services/expert-stroke-care/).

The page describes coordinating thrombolytic treatment for both ischemic and hemorrhagic strokes. These conditions need different treatment; the wording incorrectly extends clot-busting treatment to a brain bleed. It also presents driving to this facility as an alternative in its suspected-stroke instructions.

Correct the treatment distinction and make calling 911 the clear immediate action for suspected stroke. Describe this facility's evaluation, stabilization and transfer role only to the extent it is verified. Have the medical team check the final wording. [NIH stroke treatment guidance](https://www.nhlbi.nih.gov/health/stroke/treatment) distinguishes treatment of ischemic stroke from treatment of hemorrhage; [CDC emergency guidance](https://www.cdc.gov/stroke/signs-symptoms/index.html) recommends calling 911 for an ambulance.

The Spanish stroke page, **4739**, also requires a clinical review alongside the English correction; do not assume translation wording matches or automatically repeat the English error. The exact erroneous English sentence was directly observed on 2482.

### WR-02 — High: surgery claims contradict the described referral model

**Confirmed contradiction:** English appendicitis page **1091** and Spanish page **4717**.

The [English page](https://erofwhiterock.com/services/appendicitis-treatment-in-white-rock/) says the facility is equipped for emergency surgery while another section describes coordinating care with specialist surgeons. Its introduction also calls the business “Emergency Room of Dallas.”

Verify the actual facility capability and replace conflicting statements with a consistent explanation of evaluation, stabilization and transfer/referral. Do not claim an operating capability based on generic emergency-room copy. Remove the incorrect business name and unnatural “near me” wording in sentences.

### WR-03 — High: imaging metadata advertises services not substantiated by the page

**Confirmed metadata/body mismatch; actual availability requires verification:** imaging pages **852 / 4745**.

The [English imaging page](https://erofwhiterock.com/services/diagnostic-imaging-services/) advertises **MRI and 3D mammography** in its description; the title includes MRI. The actual service sections describe CT and ultrasound. The Spanish metadata also advertises MRI. The page claims pediatric radiologists and mixes walk-in diagnostic care with appointment language.

Verify the equipment, staffing and scope of services against facility records. Remove unsupported offerings from metadata, schema and body together. A single mention elsewhere on this same website is not independent confirmation. This is a patient expectation problem, not merely a keyword choice.

### WR-04 — High: automatic medical-review attribution needs evidence for each article

**Confirmed display; actual reviews not verified:** all **20 English blog posts** display the nursing-team review badge, including today's new article **5525**.

The badge is rendered by Elementor text widget **b22b0e6**, with adjacent updated-date widget **c7b6d31**. The [review policy](https://erofwhiterock.com/editorial-policy/) says medical content is reviewed before publication. The About page lists a nursing team, but that does not prove each article was reviewed.

Display a medical-review claim only when a review record exists for that particular content version. Keep an ordinary update date separate from a clinical review date. Do not invent reviewers or infer review from an approval click or a template. The existing evidence does **not** establish that the badge is truthful for every article, nor does this audit establish that no actual reviews occurred.

### WR-05 — High review priority: absolute operating and billing claims

Ten newer neighborhood pages state a door-to-provider time below ten minutes for every visit: **5496, 5495, 5470, 5471, 5469, 5468, 5466, 5463, 5462, 5460**. The homepage metadata promises no wait, and other pages make different lab turnaround promises.

Verify these against documented operations; do not present an average as a guarantee for every patient. Also reconcile the homepage's broad insurance language with [billing page 2198](https://erofwhiterock.com/insurance-billing-info/), which says the facility is out of network. Acceptance, network participation, patient cost sharing and program participation need distinct wording. This is a request for factual/billing review, not a legal finding of noncompliance. [CMS explains the relevant distinction between emergency-service protections and network status](https://www.cms.gov/initiatives/your-patient-rights/medical-bill-rights/know-your-medical-bill-rights/know-your-rights-insurance).

## Confirmed links, SEO and language issues

### WR-06 — High: four broken destinations used by 11 links on four pages

All four returned **404** when fetched. The full source URL and anchor inventory is in `broken-links.csv`.

| Broken destination | Source post IDs | Instances | Repair target or decision |
|---|---|---:|---|
| `/es/contactanos/` | 5063, 5058 | 2 | Current Spanish contact page: `/es/contactenos/` |
| `/es/sala-de-emergencias-independiente-vs-hospitalaria-cerca-de-white-rock-lake/` | 5067, 5063, 5058 | 5 | Inspect and use the current Spanish comparison article, ID 5009 |
| `/es/sala-de-emergencias-pediatrica-white-rock-guia-padres/` | 5067, 5063, 5058 | 3 | Match the anchor's task: Spanish pediatric service 4737 or parent guide 5067; avoid an accidental self-link |
| `/services/abdominal-pain-er-treatment/` | 4986 | 1 | Current abdominal-pain service: `/services/abdominal-pain-treatment-in-white-rock/` |

Update the source links. Add redirects only after confirming whether each obsolete URL has an appropriate equivalent and relevant historical use. Do not redirect all missing URLs to the homepage.

### WR-07 — High: the first blog page declares page two as canonical

Both a fresh capture and a separate clean request to **`/blog/`** returned:

`https://erofwhiterock.com/blog/2/`

as the canonical URL. Page two also declares itself canonical. The landing page displays a different article list, so this warrants correcting the canonical-generation behavior, not consolidating the content. Check the interaction between the blog query/pagination and SEO output before applying a workaround. Google's canonical signals should consistently identify the intended page. [Google canonical guidance](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls).

The sitemaps contain 128 URLs: the Spanish homepage is listed as the redirecting `/es/` alias, and `/blog/` is absent. Blog/archive omission can be intentional; verify the configuration rather than treating absence alone as an indexing defect. Prefer the final canonical Spanish homepage URL in sitemap output.

URL Inspection confirms Google has observed the incorrect `/blog/2/` declaration but currently selects `/blog/` and indexes the landing page. Therefore this is a conflicting signal to repair, **not evidence that Google has already removed the blog landing page**.

### WR-08 — High: Spanish Richardson page is configured as English

Page **5496** contains Spanish text but has Polylang language **en**, rendered `lang="en-US"`, no alternate-language tags, and no pairing with English Richardson **5495**. The Spanish navigation's `/es/sala-de-emergencias-cerca-de-richardson/` redirects to the unprefixed page.

Repair the language assignment and pairing, then verify the final URL, redirect, language switcher and reciprocal hreflang on both pages. Preserve existing inbound URLs through an appropriate redirect if the permalink changes. Do not simply edit the HTML lang attribute. [Google localized-page guidance](https://developers.google.com/search/docs/specialty/international/localized-versions).

### WR-09 — Medium: translations and copied content have diverged

- Spanish lab page **4747** retains absolute accuracy and instant-diagnosis language, while the English page **971** contains the recent qualified explanation of test interpretation. Apply the substantive correction to the translation after reviewing its actual wording.
- The Spanish imaging page **4745** contains an English insurance paragraph. Spanish categories still display English category names in places.
- The Spanish services hub **4751** links “Coppell” to a URL that redirects to **Lake Highlands**, and “Grand Prairie” to a URL that redirects to **Lakewood**. These are wrong destination expectations even though the responses are 200. Its catchment and travel-time statements need a site-specific review.
- Six lab, stroke and sore-throat pages (**971, 4747, 2482, 4739, 868, 4705**) use a traumatic-brain-injury warning in otherwise unrelated contact-form sections. Replace with appropriate general emergency instructions.
- Hospital comparison language remains on the homepage, stroke and eye-injury pages despite the standing preference against such marketing comparisons.
- World Cup articles **5092 / 5122** retain forward-looking June/July 2026 wording in September. Review their time-sensitive framing and keep historical research distinct from claims about actual local outcomes.

### WR-10 — Medium: garbled characters on eight policy pages

**Confirmed in served text:** **4759, 4758, 4757, 4655, 4652, 4650, 4647, 4643**.

These include English/Spanish terms, privacy and HIPAA material plus English billing/disclaimer pages. Examples include `┬á`, `ÔÇÖ` and heavily corrupted Spanish accents. Spanish policies also contain malformed `/es/es//` URLs. Correct the encoding damage and links while preserving approved legal meaning; do not retranslate legal policies casually.

### WR-11 — Medium: author cleanup is incomplete

**22 published pages** are still assigned to author ID 7. On **nine** pages, the rendered JSON-LD explicitly exposes a Person author named `adminsumit`: **5407, 5394, 5390, 5378, 5375, 5370, 5366, 5360, 5342**.

Backfill the established organization author where appropriate, then verify rendered schema and any visible attribution. The new article **5525** already uses ER of White Rock, ID 4. The complete remaining page list is in `evidence-summary.json`.

Separately, **all 19 Spanish blog articles display “Admin”** in their author/date area even though their stored author is ID 4. Fix the Spanish article template as well; changing post authors alone will not address this observed template output.

### WR-12 — Medium: redirect and navigation cleanup

There are 24 active Rank Math redirects. One configured chain runs from `/es/emergency-services-er-of-white-rock-2/` through another old services URL to the current Spanish services hub. Link directly to the final destination and flatten the chain after checking it.

The Spanish menu also retains multiple retired service slugs. They currently redirect successfully; they are **not 404s**. Update them to final destinations to reduce unnecessary hops. Spanish dated-archive links redirect to the English homepage, which is confusing for readers; remove pointless date links or use a relevant language-appropriate destination.

## Browser and accessibility findings

### WR-13 — High: low-contrast links on the lab page

On **971**, axe reproduced two failures at both tested widths: the pediatric-fever link in widget **2a6e509** and the billing link in widget **6fe9fbe**. Foreground `#d01010` on background `#11468f` measured **1.63:1**, below the 4.5:1 requirement for this normal-weight 18px text. Use an accessible link treatment on the dark section and verify hover/focus too. [W3C contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).

### WR-14 — Medium: invalid accordion ARIA

On Spanish Richardson **5496**, Elementor accordion heading `#elementor-tab-title-2081` has `role="button"` with unsupported `aria-selected="true"`. Reproduced at mobile and desktop widths. Check a supported Elementor update or widget fix; this is generated widget markup, so changing article prose will not fix it.

### WR-15 — Improvement: mobile article spacing and performance measurement

The new article's mobile screenshot shows a very large heading and little side padding, pushing the useful introduction far below the top. Adjust the existing article template's typography/spacing; a magazine-style redesign is unnecessary. Small excess scroll widths were recorded on some pages, but the cause and practical impact need a targeted layout check before calling them failures.

No broken rendered images, page JavaScript exceptions or HTTP resource failures were observed in the 16 sampled browser visits. Some axe checks remained inconclusive; this is not a full accessibility certification. Navigation responses ranged roughly **0.68–3.8 seconds to first byte** from this operator's machine. These are single-run lab observations, not US user experience or field Core Web Vitals. Collect field data and US mobile measurements before diagnosing performance.

## Connection, automation and maintenance

### WR-16 — Confirmed: the running connector and fresh configuration disagree

The already-running MCP tools received SiteGround browser challenges and reported that SQLite was unavailable. A newly launched bridge using the saved configuration successfully connected with browser transport, SQLite and version **0.89.3**. The saved configuration already enables the relevant options.

Restart/reconnect the affected MCP session so it loads the current configuration. Do not disable SiteGround protection. This audit does not show Googlebot being blocked: the homepage was indexed and Google's last crawl succeeded on September 9.

### WR-17 — Confirmed: search-data freshness and provenance need stricter handling

At the start, the local warehouse stopped at **August 22**. It now contains fresh observations through **September 7**, after re-fetching 30 dates. The server's separate GSC cache reported `legacy_unverified`; refreshing the local warehouse does not prove that the server cache's historical rows were rebuilt.

Every decision should identify which store, dates and measurement type it uses. Do not mix stale server trends with refreshed local figures. URL fragments in GSC are not separate competing articles; do not count eight heading-anchor URLs as eight competing pages or assume their impressions are additive property reach.

### WR-18 — Blocked evidence: Business Profile API quota

The Business Profile read returned **HTTP 429**, reporting quota exhaustion in Google's account-management API. This prevents confirming current Maps listing details, calls and directions through this connection. Investigate project quota/approval and recent usage. The response alone does not establish the exact quota configuration or a problem with the public listing.

The same quota response persisted on a later read-only retry. One separate Google URL Inspection call initially returned an upstream 500 and succeeded on retry; that was not a 500 response from the public page.

### WR-19 — Maintenance: five updates are advertised

The live WordPress update inventory reports:

| Plugin | Installed | Available in site update data |
|---|---|---|
| Elementor | 4.2.3 | 4.2.4 |
| Polylang | 3.8.7 | 3.8.9 |
| Rank Math | 1.0.276 | 1.0.278 |
| Simple History | 5.30.0 | 5.32.0 |
| SiteGround Speed Optimizer | 7.8.1 | 7.8.2 |

Review release notes and test these together on staging, especially Elementor/Pro, Polylang, custom permalinks and the SEO plugin. An available update is not proof that the installed version is vulnerable. WP File Manager is active; review whether it is still needed. No vulnerability scan, backup restore or server-configuration audit was performed.

## Search performance and content priorities

### WR-20 — High: indexing gaps require investigation separate from HTML checks

Google reports laboratory service page **971** as **“Discovered - currently not indexed,”** although the live page returns 200, has a self-canonical, is in the sitemap and has internal links. The separate lab-timing article **4635** is indexed. Do not confuse these two URLs or infer a duplicate-content cause from their related subjects.

The new medication-note article **5525** is **“URL is unknown to Google”** on its publication day. That is a monitoring item, not evidence of a technical defect. The full inventory's results and additional indexing gaps are listed in `INDEXING-RESULTS.md`. Inspect crawl history, canonical alternatives, internal discovery and substantive page purpose before choosing a remedy; no automatic deletion or merge is justified by a non-indexed status.

The complete exceptions are:

| Google status | Pages |
|---|---|
| Discovered, not indexed | English TBI **5390**, abdominal pain **5342**, fractures **1374**, high fever **1317**, dehydration **1072**, food poisoning **993**, lab service **971** |
| Crawled, not indexed | Spanish TBI **5394**; English sprain/fracture article **5055** |
| Unknown to Google | New medication-note article **5525**; older pediatric service **2453** and sore-throat service **868** |

Prioritize the established service pages. They are reachable in the live link graph and return 200; this audit has not established why Google has not indexed them. Their related topics do not prove cannibalization, and their status does not justify removing useful services.

Comparison: **July 14–August 10** versus **August 11–September 7**. All 56 dates have completed local sync records. Excluding fragment URLs, retrieved rows show **77 → 75 clicks** and **9,309 → 13,654 impressions**. This is a limited query/page dataset, not a complete traffic or conversion report. It does not demonstrate a major traffic loss or identify a cause.

| Current page | Retrieved impressions | Clicks | CTR | Weighted position |
|---|---:|---:|---:|---:|
| ER blood-test timing article, 4635 | 3,683 | 9 | 0.24% | 7.35 |
| Homepage, 228 | 2,999 | 60 | 2.00% | 8.30 |
| ER visit duration article, 5437 | 1,456 | 4 | 0.27% | 5.56 |
| Head-injury decision article, 5419 | 790 | 1 | 0.13% | 12.24 |
| Sore-throat article, 5012 | 655 | 0 | 0% | 9.57 |

The lab-timing article increased from 3 to 9 retrieved clicks; it should not be labelled a failed page. Investigate query intent, geography/device mix, actual search snippets and competing results before rewriting titles because of a low CTR. Google's [traffic-diagnosis guidance](https://developers.google.com/search/docs/monitor-debug/debugging-search-traffic-drops) supports investigating multiple explanations.

Recommended content work after the urgent corrections:

1. Improve the existing lab-timing and visit-duration articles with accurate explanations of what can affect timing, without unsupported turnaround guarantees. Keep their different reader tasks.
2. Review overlapping infection/vector-borne articles **5134 / 5094**, with Spanish counterparts, for distinct reader purposes. Similar terminology alone is insufficient grounds to merge or redirect them.
3. Keep developing practical content within confirmed services even when GSC has no query data. Research reader questions, inspect existing coverage and comparable pages, and substantiate the useful contribution. No invented local statistics, clinical facts, provider quotes or competitor rankings.
4. Add relevant contextual links where they help readers. No automatic orphan-removal project is justified: the rendered graph with observed redirects reached all 129 published pages. A handful of pages can still benefit from clearer routes.
5. Measure meaningful outcomes such as calls and directions with validated event definitions. A visible analytics script does not establish correct event tracking; Maps metrics remain unavailable in this audit.

Google recommends original, useful, reliable content and does not prescribe a preferred word count or fixed expert-quote quota. [People-first content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content).

## Plugin improvements supported by this audit

- **Build the link graph from current rendered navigation, archives and observed redirects.** The existing click-depth tool called five recent articles unreachable, including articles directly linked from the homepage. That is a confirmed diagnostic false positive.
- **Audit templates and metadata together with the article.** Publication checks must see review badges, schema authors, SEO service claims and the actual rendered wrapper. Passing nine HTML rules does not establish medical accuracy.
- **Require review evidence for review claims.** Store reviewer authorization, reviewed version and review time; never add a clinical-review badge solely because content was published.
- **Track translation drift after important corrections.** An English medical correction should create a review task for its paired Spanish page, with source and target hashes.
- **Audit language intent and link destinations.** A Spanish page misassigned to English can pass basic HTML checks; a 200 redirect to a different neighborhood is still wrong for the reader.
- **Give every finding its evidence, scope and capture time.** Preserve unknown states for Google indexing, clinical accuracy, form delivery, field performance and blocked APIs. Use these live examples as regression cases.

## Repair order

1. Correct stroke instructions and reconcile surgery/imaging capabilities; verify medical-review and operating claims.
2. Repair the 11 broken links, blog canonical and Richardson language pairing; verify rendered output and Google-selected canonicals separately.
3. Fix policy encoding, remaining author/schema attribution, translation drift and copied template text.
4. Repair lab contrast and the accordion markup; make restrained mobile typography adjustments.
5. Restart the stale connector, reconcile GSC caches, resolve the Business Profile quota issue, and stage plugin updates.
6. Continue source-backed content development and conversion measurement after the higher-priority corrections.

## What this audit does not establish

It does not certify every medical statement, legal disclosure, security configuration, analytics event, third-party citation or form-delivery path. Browser checks sampled eight pages; the full 129-page sweep was server HTML. Google indexing records were retrieved separately, and no ranking or indexing guarantee is implied. These limits are part of the findings, not a reason to label unknown checks as passed.
