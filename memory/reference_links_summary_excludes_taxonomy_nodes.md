---
name: reference-links-summary-excludes-taxonomy-nodes
description: cc-assistant links_summary / links_orphans / click_depth_audit only graph post+page nodes, so WooCommerce category archives look unlinked when they are not
metadata:
  type: reference
---

`links_summary`, `links_orphans` and `click_depth_audit` read `wp_cc_link_graph`,
which is built over the site's **allowed post types only** (`post`, `page`).
WooCommerce `product_cat` archives and `product` pages are **not nodes**, so links
pointing at them are invisible to the rollup.

On sids-ponds this produced a flat-wrong conclusion: `links_summary` showed 38 pages,
112 edges, top hubs all blog posts, and "zero product categories", which I read as
categories being link-starved. The live DOM said the opposite - the nav links **33
categories on every page**, and the blog carries **31 in-body category links** across
23 posts.

**How to apply:** never conclude a category or product is unlinked from
`links_summary`. Confirm against the rendered DOM. Two reliable reads:

- `audit_post_links(id)` returns body links and carries a `rendered_cross_check`
  block that lists DOM-only paths.
- slice `class="entry-content"` .. `</article>` out of the fetched page and regex the
  anchors, which excludes nav and footer chrome.

Do NOT diff a page's links against a nav baseline with `comm` - that silently drops
body links whose URL also appears in the nav, which is exactly how I missed the
living-wall post's own category link. See
[[feedback_dom_is_ground_truth_not_parsers]] and
[[feedback_test_the_theory_before_planning_on_it]].
