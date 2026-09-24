---
name: project_mammoth_guides_outrank_money_pages
description: On mammothmachinery.ca the informational guides outrank the commercial category pages for buying queries, and convert ~9x worse
metadata:
  type: project
---

Diagnosed 2026-09-07 from a 28-day window (2026-08-09 to 2026-09-05, 26,703
impressions / 380 clicks). The site's problem is not demand, it is that the wrong
page ranks and it ranks at the bottom of page one.

**"mini skid steer", the biggest non-brand term:** the comparison guide
`/mini-skid-steer-vs-full-size/` holds position 10.0 with ~5,000 impressions
(1,304 on the page plus four anchor-fragment rows at 10.1), while the category page
`/mini-skidsteers/` that actually sells them sits at position 12.0 with 191
impressions. Eleven pages on the site rank for that one query. Per-impression the
guide converts 11/8,048 = 0.14%, the category 36/3,029 = 1.19%, roughly 9x better.
Same shape for "concrete buggy" (243 imp, 0 clicks, pos 8.0) which is answered by
`/what-is-a-concrete-buggy/` rather than any product or category page.

**Category pages are the demand centre and sit on page two:** 6 pages, 20,078
impressions, 262 clicks, CTR 1.30%, positions 11-12. Product pages by contrast:
22 pages, 5,183 impressions, 129 clicks, CTR 2.49%. Categories carry 4x the demand
at half the conversion.

**Zero-click commercial terms at good positions** (titles/descriptions, not
rankings): "wheel loaders for sale" 7.8 / 222 imp / 0 clicks, "wheel loader for
sale" 11.5 / 200 / 0, "dumper" 7.1 / 182 / 0, "mini skid loader" 10.3 / 393 / 0.
Striking distance overall: 50 queries, 8,391 impressions, 28 clicks (0.33%).

**Demand with no page:** "skid steer attachments" ranks the HOME page at position
1.2 with 95 impressions and zero clicks, an intent mismatch with no attachments
page in existence. "walk behind skid steer" 122 imp at 8.5.

**Do NOT fix this with a title pivot** ([[feedback_title_pivot_risk]]). Strengthen
the category page's commercial signals, re-angle the guide to purely informational,
and link guide to category inline ([[feedback_inline_links_only]]). The two dead
guides ([[feedback_never_noindex_pages]]) should be re-angled onto the unserved
intents above, which fixes both problems at once.

CORRECTED 2026-09-07: `h1_count` is now 1 on product pages 1568/1616 and category
pages 39/41. The July defect list claiming `h1_count=0` sitewide is STALE, do not
re-recommend it. Category pages do still lack collection/product schema, carrying
only BreadcrumbList + LocalBusiness.
