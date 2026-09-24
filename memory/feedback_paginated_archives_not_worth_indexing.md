---
name: paginated-archives-not-worth-indexing
description: "Paginated archive URLs (/blog/page/N/, /category/N/) are net-negative for SEO — fix pagination via AJAX/Load More, not by exposing more indexable thin URLs"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ab36cec8-65c1-4a26-ba9e-b90b0467a7fe
---

When user complains about broken pagination on a blog or category index, the right fix is AJAX-style (Load More button / Infinite Scroll), NOT reconfiguring WP to expose `/blog/page/2/`, `/blog/page/3/`, etc. as indexable URLs.

**Why:** Paginated archive pages are net-negative for SEO:
- Thin content (just titles + excerpts of posts)
- Duplicate-content overlap with the actual posts they list
- Compete with the actual post URLs for category/topic queries (cannibalize their own children)
- Google's John Mueller has stated for years that paginated archive indexing is not a positive ranking signal
- Some SEO teams explicitly `noindex` pages 2+ to push crawl budget toward the actual post URLs

**What actually gets blog posts indexed:**
1. XML sitemap (every post listed → Google discovers directly)
2. Internal links from pillars / hub pages / category pages / inline mentions
3. Homepage feed or related-posts widgets
4. Author/category/tag archives if configured

**Concrete pattern when diagnosing a blog pagination problem (2026-05-22 incident):**
- `/blog/page/2/` 301-loops to `/blog` → diagnose as canonical-redirect from WP core (static page can't paginate)
- Initial reflex: "set Blog as WP Posts Page in Settings → Reading"
- That's WRONG. It just makes the thin paginated URLs indexable, which is the opposite of helpful.
- RIGHT fix: flip Elementor Posts widget `pagination_type` from `"numbers"` to `"load_more_on_click"` (or `"load_more_infinite_scroll"`). Users get navigation, archive URLs stay out of Google's index, individual posts remain indexable via sitemap.

**Why:** User correction on 2026-05-22. Original recommendation labeled "Posts Page + Elementor Pro Archive template" as the BEST SEO option. User pushed back: "getting paginated page rank is bad for the website, the specific blog should be indexed." User was correct; the framing was wrong.

**How to apply:** Any time a user reports broken pagination on `/blog`, `/category/X/`, or any archive — default recommendation is AJAX pagination (Load More / Infinite Scroll). Reserve URL-based pagination only when (a) the archive itself has substantial unique content beyond the post excerpts (a true category landing page, not just a list), AND (b) page-2+ would be explicitly `noindex`-ed.

Related: [[reference_custom_permalinks_trap]] (different trap, not this one), [[feedback_curl_test_url_before_editing]] (confirmed via curl that all three URL variants 301-loop).
