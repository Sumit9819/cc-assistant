---
name: project-cc-hero-preload-mobile-bg-bug
description: cc-assistant class-hero-preload.php preloads the desktop hero for ALL screens when the first container has a separate mobile background image; phones download both heroes (found on IWC homepage 2026-09-17)
metadata:
  type: project
---

`includes/class-hero-preload.php` (`CC_Assistant_Hero_Preload::parse_hero`) adds `media="(min-width: 768px)"` only when `background_image_mobile` is present but EMPTY (the erofirving "solid colour on phones" convention). When a page sets a DIFFERENT mobile image, `desktop_only` is false, so the desktop hero is preloaded on every viewport and the mobile image is never preloaded.

Seen live on irvingwellnessclinic homepage (post 8): desktop `2026/03/Untitled-design-18-1024x480-9.webp`, mobile `2026/04/Untitled-design-6.webp`. Mobile page downloaded both; US Lighthouse mobile LCP 3.0s.

**Why:** the plugin's own optimisation makes the mobile LCP worse on any site whose heroes have a real mobile image.

**How to apply:** fix in the plugin (emit two preloads with `media` when the mobile URL differs; keep desktop-only when it is blank; honour `background_image_tablet` too). Until then IWC works around it in its Code Snippets "Performance" snippet (`tools/iwc-performance-snippet/`), which removes the plugin's emit on the front page and prints its own responsive preloads. After the plugin fix ships, delete that workaround block from the snippet. Also note the per-post cache in `_cc_hero_preload` postmeta must be invalidated on release. Related: [[feedback-never-claim-server-slow-without-control]].
