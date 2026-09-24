---
name: custom-permalinks-pagination-4site-diagnostic
description: "When pagination breaks on one bilingual ER site but works on its siblings, the Custom Permalinks postmeta trap is almost always the cause — diff the get_permalink() output across sites to expose it in 10 seconds"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ab36cec8-65c1-4a26-ba9e-b90b0467a7fe
---

When a user reports broken pagination on one site in a multi-site portfolio (erofwhiterock / eroflufkin / erofirving / irvingwellnessclinic), the fastest path to root cause is a **4-site comparison curl**, not deep-diving into the broken site's WP config.

## The diagnostic pattern (works in <60 seconds)

```bash
UA="Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)"
for site in eroflufkin.com erofirving.com irvingwellnessclinic.com erofwhiterock.com; do
  echo "=== $site/blog/page/2/ ==="
  curl -sI "https://$site/blog/page/2/" -A "$UA" | grep -E "^(HTTP|Location:|X-Redirect-By:)"
done
```

If three sites return 200 and one returns 301 with `X-Redirect-By: WordPress` + `Location: https://broken-site/blog` — **it's the Custom Permalinks trap.** Confirm in 5 more seconds:

```bash
curl -s "https://broken-site.com/wp-json/wp/v2/pages?slug=blog" -A "$UA" | python -c "import sys,json; d=json.load(sys.stdin); print(d[0]['link'])"
```

WP native `get_permalink()` ALWAYS returns trailing slash for pages. If you see `https://broken-site.com/blog` (no slash) vs the working sites' `https://other-site.com/blog/` (with slash), that no-slash output is direct evidence that Custom Permalinks plugin's `get_permalink` filter is overriding the URL. Only one mechanism on WordPress produces that exact discrepancy.

## Why the trap exists

Custom Permalinks plugin stores per-post `custom_permalink` postmeta (e.g. `"blog"`). Its filter on `get_permalink()` replaces the WP-native URL with this custom value. Its runtime URL handler intercepts every request matching `/{custom_permalink}/*` and 301-redirects to `/{custom_permalink}` (no path suffix). This kills pagination, query strings, and child paths.

## Why diffing across sites beats single-site investigation

Single-site investigation of "why is /blog/page/2/ broken" leads down WP canonical_redirect / Polylang / theme rabbit holes that take 30+ minutes and produce wrong fixes (mu-plugin filters, Loop Grid widget swaps, show-all-on-one-page hacks). Comparing to known-working siblings instantly isolates that the breakage is site-specific, which immediately narrows the suspect list to per-site postmeta / per-site plugin config / per-site Rank Math rules — and Custom Permalinks postmeta is the #1 candidate every time on this portfolio (per memory `reference_custom_permalinks_trap`).

## The fix

Clear the `custom_permalink` postmeta:

```
mcp__cc-assistant-{site}__draft_update_postmeta
  post_id: {blog_page_id}
  meta_key: "custom_permalink"
  value: ""
```

Setting to empty string causes the plugin's filter `if ($custom_permalink)` to evaluate false (empty is falsy in PHP) → native WP permalink restored → native pagination handling restored. No mu-plugin filters, no theme edits, no widget swaps needed.

## Why this rule exists

Diagnosed on erofwhiterock.com 2026-05-22 after spending ~1 hour proposing wrong fixes:
- pagination_type flip on Posts widget (cosmetic only, didn't fix the URL)
- show-all posts_per_page=50 (workaround, broke at scale)
- mu-plugin filter to suppress canonical_redirect (caused user to break their theme by pasting into functions.php)
- Loop Grid widget swap (requires Elementor Pro template work)

ALL of those were wrong. The actual fix was clearing one postmeta row. The 4-site comparison curl took 10 seconds and would have nailed the diagnosis on the first probe instead of the fourth.

## How to apply

ANY time a user reports broken pagination, broken URL handling, or unexpected redirects on a multi-site portfolio:
1. **First**, run the 4-site curl comparison
2. **Second**, check `link` field from REST API for trailing slash discrepancy
3. **Third**, if trap confirmed, queue the postmeta clear via draft_update_postmeta
4. **Never** propose mu-plugin filters / theme edits / widget swaps without ruling this trap out first

Related: [[reference_custom_permalinks_trap]] (the general trap doc), [[reference_elementor_pro_posts_widget_load_more_uses_url_pagination]] (related Elementor gotcha but downstream from the Custom Permalinks issue), [[feedback_paginated_archives_not_worth_indexing]] (separate SEO posture question — relevant only AFTER the trap is cleared).
