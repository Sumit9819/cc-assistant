---
name: reference_seo_scanner_tool_benchmark
description: Open-source SEO crawlers/validators benchmarked 2026-09-23 against hand-verified facts; advertools accurate with one fix, SEOmator and the Adobe schema validator give false passes; design at D:\seo-system
metadata:
  type: reference
---

For sites with no cc-assistant MCP (Shopify etc.) the design lives in `D:\seo-system\DESIGN.md`,
results in `D:\seo-system\BENCHMARK.md`, test runs in `D:\seo-system\bench\`.

**advertools 0.18.0** (`pip install --user advertools`; needs
`C:\Users\sumit\AppData\Roaming\Python\Python313\Scripts` on PATH for `scrapy.exe`). Matched every
hand-verified fact on Solo Cru (223 URLs) and Lost Aviator (70), with two traps:
- its `title` column joins EVERY `<title>` element with `@@`, including SVG icon titles. On Solo Cru
  177/178 titles read as ~160 chars. Use the segment before `@@` / `head > title`.
- custom settings go over the command line, so lists must be comma strings
  (`"RETRY_HTTP_CODES": "429,500,503"`), and long URL lists run as several spiders. Wait on the
  process, not the first "Spider closed".
- polite settings that avoided every 429 on solocru.com: 1 request per 1.6 s, 1 concurrent.

**SEOmator 5.1.0** (`@seomator/seo-audit`): 373 rules, honest "not measured", but ~35 s/page and
writes output only at the end. False PASS "no LocalBusiness schema" on pages carrying
`CafeOrCoffeeShop` (no subtype support); entity-conflict compares only logo/phone; flagged a café
at "404 York Road" as a soft 404. Second opinion only. It did surface a real miss of ours (extra
LocalBusiness nodes on Lost Aviator contact-us and coffee-v2).

**@adobe/structured-data-validator**: default export (README's named import is wrong). No handler
for LocalBusiness, FAQPage, Article, so "0 issues" on those types means NOT CHECKED.

Keyless PageSpeed Insights API is 429 on the shared daily quota; field data needs a free API key.
GSC token scope is `webmasters.readonly` only.

Third-party Claude SEO skills (AgriciDaniel/claude-seo 17k stars, coreyhaines31/marketingskills
51k) are good checklists but score with weighted opinion; read before installing, they run
scripts. See [[feedback_dom_is_ground_truth_not_parsers]], [[project_solocru_lostaviator_engagement]].

## The scanner itself (built 2026-09-23)

`python D:\seo-system\scanner\scan.py D:\seo-system\config\<site>.json` runs inventory, raw fetch, link targets,
rendered pass and rules, with a SQLite run history and diffs; `--rules` re-runs rules on saved data. README at
`D:\seo-system\README.md`. Own bugs found and fixed on the first runs: a closed off-canvas cart drawer counted as
a full-screen overlay (the rule now needs viewport overlap plus the centre element; positive control is the Solo
Cru promo popup), self-links hid orphans, `ProductGroup` was not treated as product markup, and Shopify's
`/customer_authentication/redirect` returns 406 to non-browsers and is not a broken link. A one-off rendered
"Something went wrong" title surfaced as CONFLICT and cleared on re-render, as designed.
