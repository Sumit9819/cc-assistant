---
name: cc-assistant Elementor parser must walk widget-internal headings
description: icon-box, image-box, accordion, nested-accordion, and posts widgets render H3 tags but the parser only counted heading widgets — caused false 0-H3 flags
type: reference
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
The cc-assistant Elementor parser (`includes/class-elementor-parser.php`) historically only extracted headings from `heading` widget instances. That missed real headings rendered by other widget types:

| Widget | Renders | Default level |
|---|---|---|
| `icon-box` | `<h3 class="elementor-icon-box-title">` | h3 (`title_size`) |
| `image-box` | `<h3 class="elementor-image-box-title">` | h3 (`title_size`) |
| `accordion` / `toggle` | item title | h3 (`title_html_tag` if set) |
| `nested-accordion` | `<h3 class="e-n-accordion-item-title-text">` | h3 (`title_tag`) |
| `posts` / `post-feed` / `theme-posts` / `archive-posts` | `<h3 class="elementor-post__title">` per card | h3 (`title_size`) |

**Why:** When the rendered-HTML fetcher fails (WAF, timeout, cache miss), pre_publish falls back to the parser. With the gap, pages built primarily from icon-box/accordion widgets falsely reported "0 H3s" and failed `heading_depth` even though the actual rendered page had a clean h2/h3 hierarchy. The erofwhiterock.com homepage was a textbook case: 11 H2s + ~22 H3s in rendered HTML, parser saw 11 H2s + 0 H3s.

**How to apply:**
- Fixed in v0.10.22 — the switch in `class-elementor-parser.php::walk()` now appends a heading entry from these widget types using their configured tag setting (default h3).
- If a future audit shows `heading_depth` failing on a page heavy with these widgets, check the plugin version. Below 0.10.22, the count is wrong; trust the rendered count (curl the page) over the parser fallback.
- The accordion case used to emit `level: 'accordion'` which the heading_depth check ignored — now emits `level: 'h3'` (or honored `title_html_tag`). This was a correctness fix, not just additive.
