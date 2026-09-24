---
name: project_solocru_lostaviator_engagement
description: Two Shopify clients added 2026-09-22 — Solo Cru (Italian wine, Ontario) and Lost Aviator Coffee (Guelph cafés); access facts, the core finding for each, and the crawl traps
metadata:
  node_type: memory
  type: project
---

Two **Shopify** sites, so there is no cc-assistant MCP server for either and no write access.
Work is read-only crawling, the GSC API and Ubersuggest. Workspaces `D:\SoloCru\seo` and
`D:\Lost-Aviator\seo`, each with a `STATE.md` holding the full detail. Reports issued
2026-09-22 to `D:\Client-Reports\Solo-Cru\` and `D:\Client-Reports\Lost-Aviator\`
(doc ids SC-2026-09-P1 and LA-2026-09-P1), each a 6-page audit plus a five-week plan
running 28 Sep to 1 Nov 2026.

## Access, which differs between them

- **solocru.com: Search Console IS connected.** It is the third property on the working
  token at `C:\Users\sumit\.gsc\token-sids.json`, alongside mammothmachinery.ca,
  sids-ponds.com, conscapecanada.ca and growthboss.co. Data starts 2025-05-10.
- **lostaviatorcoffee.com: Search Console is NOT available**, and neither is the Google
  Business Profile for either café. The old `~/.gsc/token.json` is dead (`deleted_client`);
  do not retry it. Every Lost Aviator search figure is a third-party estimate and the report
  says so on its own first page. Getting both is week one, item one.
- **lostaviator.com is a different, unrelated domain.** The client is
  **www.lostaviatorcoffee.com** (apex 301s to www, correctly — not a defect).

## Solo Cru: the finding is intent, not ranking

Canada, 90 days: 1,085 non-brand queries, 6,665 impressions, **87 clicks**. 1,027 queries
(95%) took zero clicks while carrying 85% of impressions, and **666 of those rank in the top
ten**. The 58 queries that do click convert at **8.75%**.

The split is producer/brand names (convert) against grape varieties, regions and styles (do
not). "falanghina wine" holds **373 mobile impressions in Canada at average position 1.5
across 88 separate days with zero clicks** — it survives the disaggregation test, so it is
real and not an average-position artifact.

And the pages built for the converting half are broken: **all 45 producer pages under
`/pages/producers/` return 404** while sitting in the sitemap, doubled by the `/fr/` locale.
Also: **no H1 on the home page or any of the 35 collections** (verified in raw HTML and in a
rendered mobile DOM), 10 live `-copy` duplicate products, 23 products whose slug year differs
from their title year, 0 of 125 products with `aggregateRating`, and no BreadcrumbList
anywhere.

## Lost Aviator: the finding is a confused local entity

The home page carries ~2,165 of ~2,350 estimated monthly visits and only 8 pages earn
anything. "coffee shop guelph" is ~4,400/month at difficulty 17 and the site sits at
**position 21**, with four pages competing for it. Ranking keywords fell 1,812 → 144 over
two years and nothing has been published since March 2025.

**Two cafés are described by six schema entities** (the 09-22 report said four; REV B of 09-23 corrects it — `/pages/contact-us` and `/pages/coffee-v2` add two more York Road nodes). The home page emits
`#cafe-york` and `#cafe-laird` whose `url` points at the locations hub; the two café pages
emit their own `#cafe` nodes pointing at themselves; and the hub itself emits **no JSON-LD at
all**. None of the six carries opening hours, price range, a `sameAs` to the Business Profile
or a rating. Separately, the collection sort control writes links to
`/collections/price-ascending` and six siblings that do not exist, giving 14 dead pages linked
from every collection page.

## Traps worth carrying forward

- **solocru.com rate-limits aggressively.** Two concurrent workers with no delay got 182 of
  224 URLs refused with 429. What works: single thread, 1.6s between requests,
  retry-after backoff, incremental saves, and ordering the queue by GSC impressions so a
  throttled run still covers the pages that matter. `D:\SoloCru\seo\scripts\crawl2.py`.
- **Do not pipe fetched HTML through a shell into Python** — it dies on unpaired surrogates.
  Fetch inside Python.
- **Shopify metaobject pages can 404 while staying in the sitemap.** Nothing in the sitemap
  or the robots file hints at it; only requesting them reveals it. Worth checking on any
  Shopify site.
- A shallow JSON-LD parse that does not walk `@graph` reports a false "no schema". It did
  here on the Lost Aviator home page before the re-check. See
  [[feedback_dom_is_ground_truth_not_parsers]].

Related: [[reference_gsc_impression_bug_2026]] (Solo Cru's 2025 impressions are inflated, so
clicks were used for every comparison), [[feedback_never_diagnose_from_average_position]],
[[feedback_client_report_format]], [[feedback_never_noindex_pages]] (the index-bloat
recommendations are merge, redirect or drop from the sitemap, never noindex).

## Current versions and data policy (2026-09-23)

Current PDFs: Solo Cru `...-2026-09-23.pdf` (SC-2026-09-P1 REV B), Lost Aviator `...-2026-09-23-revC.pdf`
(LA-2026-09-P1 REV C); older versions in each `_superseded\`. Both were re-verified against the evidence-first
scanner in `D:\seo-system` (see [[reference_seo_scanner_tool_benchmark]]), which also produced the corrections:
Solo Cru out-of-stock-with-clicks is 12 of 15 not 3, and the Dissegna and Gamondi collections are linked from
nowhere; Lost Aviator page counts are 14 / 17 / 6 not Ubersuggest's 46 / 36 / 12.

**Data policy, operator decision 2026-09-23:** Lost Aviator uses Ubersuggest only; Solo Cru uses Search Console
plus Ubersuggest. Ubersuggest data is saved as ESTIMATE in `D:\seo-system\sites\<site>\ubersuggest.json`.
A second GSC login `~/.gsc/token-growthboss.json` exists (select with `GSC_TOKEN=token-growthboss.json`); it reads
the same five properties as `token-sids.json` and does NOT include lostaviatorcoffee.com.

## Deliverables as of 2026-09-23 (evening)

Page-by-page work orders: `D:\Client-Reports\Solo-Cru\Solo-Cru-SEO-Work-Order-Page-by-Page-2026-09-23.pdf`
(SC-2026-09-W1, 78pp: 9 site-wide, 122 cards incl. 28 producer-page restores, 84 no-change rows) and
`D:\Client-Reports\Lost-Aviator\Lost-Aviator-SEO-Work-Order-Page-by-Page-2026-09-23.pdf` (LA-2026-09-W1, 36pp:
6 site-wide, 69 cards, 183 changes). Plans corrected to Solo Cru REV C and Lost Aviator REV D.

Verified facts that corrected the plans: Solo Cru "-copy" products are ten DIFFERENT wines (rename handles, never
redirect); no collection page shows its name at all (add the Collection banner section); titles already carry the
current vintage; 7 products have Judge.me reviews that never reach the markup; 39 products publish an empty Product
description. Lost Aviator: both Google listings exist (York 4.9/277, Laird 4.9/60 with owner posts), York's listing
phone is 519-400-8000 vs the site's 519-780-4315, Laird's page shows no hours, and the site contradicts itself on
which café is the roastery.
