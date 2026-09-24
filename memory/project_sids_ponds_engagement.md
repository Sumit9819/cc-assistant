---
name: project-sids-ponds-engagement
description: "New SEO engagement — sids-ponds.com (WooCommerce garden/pond store, Mississauga ON); stack + blockers + plan"
metadata: 
  node_type: memory
  type: project
  originSessionId: 0d6bfd82-7065-481f-87c7-77e5bdae018f
---

sids-ponds.com = Sid's Ponds & Gardenscapes, Mississauga ON (GTA). E-commerce + physical store. MCP server `cc-assistant-sids-ponds-com` added 2026-07-12 (fixed: entry must use Local's bundled PHP path, not bare `php` — see [[reference-local-php]]).

Stack: **Divi 4.27.7 (NOT Elementor — Elementor builder tools/design skills don't apply)**, Rank Math, WooCommerce 10.9.4, SiteGround (WAF, browser-UA needed), WP 7.0.1, ~800–1,100 products, 25 blog posts, en_CA.

Key constraints/blockers:
- **GSC not connected** (top blocker for data-driven work; user must OAuth in wp-admin → CC Assistant → Search Console)
- Plugin allowlist = page/post only → **WooCommerce products/categories not editable via cc-assistant**
- Cart/checkout/my-account/wishlist in Rank Math sitemap → manual Rank Math noindex fix
- Product URLs nested under /product/all-products/... — permalink restructure = future project needing ~1,000 301s
- Taxonomy mess: 24/25 posts in "Homeowners" category; redesign is future scope

2026-07-12 session queued pending #1–7: slug fixes + 301s for 3 posts whose slugs were cloned from a bicycle-storage post (13527, 13538, 8406 at /8406-2/), plus 12871 out of Uncategorized. Empty-category deletion denied by permissions — left for user.

Plan agreed: 1) GSC connect, 2) hygiene sweep, 3) WooCommerce category-page buildout (where e-comm SEO is won), 4) local layer (GBP + LocalBusiness schema), 5) buying-guide content linking into categories.

## CORRECTION 2026-09-09: product CATEGORIES are editable after all

The constraint line above ("WooCommerce products/categories not editable") is half
wrong and has been since plugin v0.53, which predates most of this engagement's
frustration. Verified in source (`class-rest-api.php`, `term_edit_taxonomies()`
returns `product_cat` and `category`) and against the live site (74 terms returned
with their Rank Math meta):

- **`product_cat` terms ARE writable** via `draft_update_term(term_id, taxonomy,
  description|seo_title|seo_description)` - description HTML goes through the normal
  content lint, SEO fields through the meta lint, and it queues a pending like any
  other write. `list_product_categories` is the read side. The source calls thin
  category money pages "the headline use case".
- **Individual `product` posts are still NOT writable.** `allowed_post_types` is
  `["post","page"]`, so hornwort, limestone-screening and silica-sand cannot be
  touched, and neither can price/stock/identifier fields. Fixing those means the
  operator either adds `product` to the allowlist or works in wp-admin/CTX Feed.

So category-page buildout (step 3 of the agreed plan) was never actually blocked.
The product-level and feed-level work ([[project-sids-ponds-ctx-feed-broken]]) still is.

## 2026-09-09 re-measurement confirms the July baseline, and GSC is STILL not connected

`gsc_warehouse_sync` returns `gsc_not_connected`. The local warehouse holds 76,068
rows but only **2026-05-01..08-22** (last sync 08-25), so every current number is
16 days stale and the 28-day living-wall verdict due ~2026-09-24 **cannot be
measured until the operator OAuths Search Console**. Same blocker as 2026-07-12.

Re-confirmed in the 07-26..08-22 window, non-branded, position 4-20:

- **Bike/outdoor-storage cluster ~2,899 imp / 18 clicks (0.6%)** at positions 4-13,
  all on term 393, which still holds **exactly 1 product**. The
  [[project-sids-ponds-gsc-baseline-2026]] finding persists unchanged; do not
  "fix" it with metadata, and keep honouring the decision not to retitle that page.
- **hornwort 532 imp / 0 clicks at pos 9.6** and "hornwort pond plant" pos 4.7 /
  0 clicks - consistent with the baseline's OutOfStock diagnosis.
- **Artificial-turf-Mississauga cluster ~476 imp / 0 clicks at pos 8.8-11.1** across
  5 query variants. Genuine local commercial intent with no editable landing page.
- **sod mississauga 608 imp / 0 clicks** but the query-level position is **48.6**,
  not the 9.3 `site_status` shows. 9.3 is one page's average out of 6 competing
  URLs. Textbook [[feedback_never_diagnose_from_avg_position]] - it is not a quick win.
- 5 `product_cat` terms have **count 0** and 13 have count <=2 with 0-word
  descriptions. Thin archives, but the no-noindex rule holds
  ([[feedback_never_noindex_pages]]); consolidate or merchandise instead.

**`site_status`'s "11,287 impressions rank well but earn almost nothing" is mostly an
artifact - do not act on it.** Grouping the brand query by page shows the homepage at
pos 1.4 taking 192 of 220 clicks while ~8 other URLs each log ~800 impressions at pos
2.2-3.0 with 1-9 clicks. That is the sitelink pattern, not eight independent rankings
with bad CTR. Likewise "google_prefers wall-deck-lighting at position 1 with 0 clicks"
rests on a **single impression**. Group by page before believing any CTR headline.

Seasonality caveat: non-branded clicks/day ran 21.4 (May) -> 13.4 -> 7.9 -> 8.2 (Aug),
branded 28.5 -> 12.6. The warehouse starts 2026-05-01, so there is **no YoY control**
and a peak-to-late-season landscaping decline cannot be separated from a real one.
Do not call this a collapse on this data.

## Living-wall day-20 interim (2026-09-09, GSC reconnected): still no detectable gain

GSC is back and the warehouse now covers **2026-04-17..2026-09-07** (94,947 rows).
`gsc_warehouse_status` also surfaces Google's `gsc_impressions_inflated` bug
(**2025-05-13..2026-04-27**, impressions inflated, **clicks correct**). Everything
from 2026-05-01 on is post-fix and safe; any further backfill must be read on
clicks only, and the 342 remaining backfill dates all land inside the bug window.

Re-ran the living-wall test on fresh data with equal 11-day windows, the 2026-08-27
changeover day excluded, shared queries only (81 of them):

| | pre 08-16..26 | post 08-28..09-07 |
|---|---|---|
| impressions | 659 | 647 |
| clicks | 6 | 6 |
| weighted position | 20.17 | **21.80** |
| queries improved / worse | | **39 / 39** |

Flat impressions, identical clicks, position slightly worse, and a dead-even query
split. This **reproduces the 2026-09-07 correction and kills the original "day 11
POSITIVE" read for good.** Caveat that matters: 6 clicks per window is far too small
to conclude anything from clicks, and 1.6 positions on 650 impressions is weak either
way. The honest statement is "no detectable improvement at day 20", not "it failed".
Keep the hold to the ~2026-09-24 verdict; do not scale the category-rewrite programme.

Context: `/product-category/all-products/green-living-wall-kits/` drew 2,407 imp / 28
clicks since 08-01, while the `/how-to-build-a-living-wall/` post drew 747 imp / **1 click**.

## Site-wide 28-day read (to 2026-09-07): clicks down, impressions up

| | now 08-11..09-07 | prior 07-14..08-10 |
|---|---|---|
| branded clicks | 275 | 412 |
| non-branded clicks | 187 | 204 |
| non-branded impressions | **33,520** | 27,118 |

Non-branded impressions **+24%** while non-branded clicks **-8%** and branded **-33%**.
Rising impressions against falling clicks looks like the July baseline's "CTR is the
binding constraint" finding, but **that claim is already retired** and I should not have
repeated it. The site's own working_state records the correction: the original CTR
reading came from a gap analysis run WITHOUT a brand filter, and on NON-BRAND demand the
largest CTR gap on the whole site is 9 clicks/90d. **The real constraint is INVENTORY**,
which is exactly where this session's evidence landed independently (term 393 with one
product, hornwort at position 1.3 unbuyable). Always apply the brand filter before
ranking a CTR gap. Still no YoY control (warehouse starts
2026-04-17), so a late-season landscaping decline cannot be separated out. Do not
call this a collapse.

**Fresh zero-click clusters for the pond terms** (28d to 09-07): Aquascape ~203 imp /
**0 clicks** (pumps 10.6, canada 9.6, kits 11.1, aerator 13.5); Laguna ~146 imp /
**0 clicks** (sand filter 25.2, max flo 27.5, powerjet 14.1); pond-pump ~179 imp /
**0 clicks**. And `hornwort pond plant` now sits at **position 1.3 with 336 impressions
and zero clicks** - position one, no clicks, unbuyable stock, and the product is not
writable through the plugin. That single row is the clearest statement of what limits
this site.

## CORRECTION 2026-09-09 (3): the "raw Rank Math template variable" defect DOES NOT EXIST

I claimed terms shipping `%term% %page% %sep% %sitename%` in `rank_math_title` were
leaking raw variables into the title tag, and framed pendings 405/406 as fixing that.
**That was an unverified inference from stored values and it is wrong.** Rank Math
renders those variables normally, and it decodes `&amp;` to `&` as well. Read off the
live DOM with Playwright:

| stored `rank_math_title` | rendered `<title>` |
|---|---|
| `%term% %page% %sep% %sitename%` (term 22) | Power Tools & Construction Equipment \| Sid's Ponds |
| `%term% Products %page% %sep% %sitename%` (term 475) | Blades, Saws, Filters & Maintenance Products \| Sid's Ponds |

So terms 21, 22, 475 and 332 are **not defective** - they simply carry Rank Math's
default template instead of a bespoke title. Rewriting them is a targeting judgement,
not hygiene, and should be justified on query evidence or skipped. 405 and 406 were
still net improvements (405 puts Laguna and Aquascape in a title that had neither, and
both replaced genuine AI-tell metas), but the reason recorded on those pending rows is
wrong.

**How to verify a term archive's rendered title**, since `render_probe` and
`page_facts` are post-ID only and no plugin route renders an arbitrary URL:

```
# script must live beside the Playwright install; ESM ignores NODE_PATH
cp read-titles.mjs ~/.cc-assistant/wcag/ && cd ~/.cc-assistant/wcag
node read-titles.mjs https://SITE/product-category/...   # goto networkidle, retry while sgcaptcha
```

Lesson, and it is the third time this session: **stored value is not rendered output.**
[[feedback_dom_is_ground_truth_not_parsers]] applies to template strings and meta
fields, not just to shortcode parsing. Three proposed changes died on contact with the
live DOM today: this one, the "Hardscaping page says Providers so it reads as a
contractor directory" hypothesis (live H1 is "Hardscaping & Interlock **Supplies** in
Mississauga" and the title tag is already "Interlock Pavers & Patio Stones Mississauga"),
and the `site_status` low-CTR headline.

## The honest state of this site: the copy is largely DONE

Audited every product_cat and the top pages on fresh 28d data. The pages carrying real
non-branded demand are already well written: terms 24, 28, 35, 36, 60, 150, 522, 365 and
page 1125 all have query-shaped headings, real brand and product names, Ninth Line
pickup and GTA delivery. **The remaining constraints are not editorial:**

- **Inventory** - term 393 (4,508 imp / 28 clicks at pos 11.4) holds ONE product;
  `hornwort pond plant` sits at **position 1.3** with zero clicks on unbuyable stock.
- **Map pack / GBP** - `/hardscaping-and-interlock/` takes 2,268 imp for 2 clicks at
  pos 17.7 with an already-excellent title and 979 words. ~800 of those impressions are
  contractor intent a supplier cannot serve, and the rest sit under a map pack of
  installers. GBP is still not connected.
- **Feed / merchant listings** - [[project-sids-ponds-ctx-feed-broken]], untouched.

What genuine editorial work remains is the legacy AI-tell sweep on the terms that were
never rewritten: **62 (done, pending 408)**, 63 mulch, 61 aggregates, 21 all-products,
22 power-tools, 33, 473, 474, 487, 489, 490, 563. Prioritise by measured demand, not by
word count: 63 carries 128 imp at pos 18.0, 61 carries 256 at pos 34.5, and 563 carries
**1 impression at position 94** despite 701 words, so 563 is not worth touching.
