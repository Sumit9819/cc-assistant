---
name: elementor-pro-posts-widget-load-more-uses-url-pagination
description: "Elementor Pro \"Posts\" widget Load More is URL-based AJAX (fetches /blog/N/), not admin-ajax POST — so any 301 on /blog/N/ silently breaks Load More just like it breaks numbered pagination"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ab36cec8-65c1-4a26-ba9e-b90b0467a7fe
---

The Elementor Pro **Posts widget** (`widgetType: "posts"`, `data-widget_type="posts.classic"` / `posts.cards" / posts.full_content`) implements ALL its pagination modes — including `pagination_type: "load_more_on_click"` and `pagination_type: "load_more_infinite_scroll"` — by fetching a real URL of the form `/{archive_slug}/N/`.

In the rendered HTML you'll see:
```html
<div class="e-load-more-anchor" data-page="1" data-max-page="3"
     data-next-page="https://example.com/blog/2/"></div>
```

When the button is clicked (or the anchor enters the viewport for infinite scroll), Elementor's JS does an XHR GET on `data-next-page`. It parses the response HTML, extracts the next batch of post cards via DOM querySelector, and appends them to the current list.

**Implication:** if `/blog/N/` doesn't resolve to "page N of the post listing" — for example because `/blog` is a static page rather than the WP Posts Page (Settings → Reading), and WP's `redirect_canonical` 301s `/blog/N/` back to `/blog` — then **Load More fetches page 1 content again**. The button "works" (no error), but every click loads the same first batch.

**Diagnosing the trap:**
1. User reports broken pagination on a blog/category index
2. Curl `/blog/2/` and `/blog/page/2/` — see HTTP 301 → /blog
3. Swap pagination_type from "numbers" → "load_more_on_click" thinking it'll AJAX past the redirect
4. Click Load More → same posts re-rendered → user still pissed
5. Inspect rendered HTML: find `data-next-page="https://example.com/blog/2/"` — confirms URL-based fetch
6. Realize the pagination_type change just swapped button UI; the AJAX target is the same broken URL

**Real fixes (ranked by simplicity):**
1. **Bump `posts_per_page`** so all expected posts fit on one page (works when post count is bounded). Sets `data-max-page=1`; Load More auto-hides. Cleanest for blogs <50 posts.
2. **Switch to Elementor Pro "Loop Grid" widget** (`widgetType: "loop-grid"`) — Loop Grid uses true POST to `/wp-admin/admin-ajax.php?action=elementor_loop_pagination` with no URL routing. Requires Elementor Pro template-loop work.
3. **Make `/blog/N/` actually resolve to page N** — set /blog as WP Posts Page (Settings → Reading) + Elementor Pro Theme Builder Archive template + Rank Math `noindex` on pages 2+. Complex but proper URL pagination.

Related: [[feedback_paginated_archives_not_worth_indexing]] (why URL pagination isn't actually worth fighting for in most cases).

**Why:** Diagnosed on erofwhiterock.com /blog 2026-05-22 during chest-pain page optimization session. First fix attempt (pagination_type → load_more_on_click) failed because the user still saw same 6 posts on Load More click. Root cause was data-next-page URL still 301'd by WP canonical_redirect.

**How to apply:** Whenever Elementor Pro Posts widget Load More misbehaves, check rendered HTML for `data-next-page` URL. If it's a non-resolving URL like `/blog/N/`, the fix is NOT a pagination_type change — pick from the 3 real fixes above.
