---
name: project-board-certified-sweep-incomplete
description: "2026-08-28: the fleet-wide board-certified removal is NOT done. Site-wide source on all 3 ER sites is Elementor Custom Code snippet post 3236, which the plugin cannot edit."
metadata: 
  node_type: memory
  type: project
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-28T07:12:35.961Z
---

The 2026-08-19 user decision (replace "board-certified physicians" with "experienced emergency physicians" across all three ER sites) was recorded as "Irving DONE / Lufkin PARTIAL / White Rock NOT STARTED". **Measured 2026-08-28 by curl+grep over every sitemap URL, Irving is NOT done either.**

**THE SITE-WIDE SOURCE (all three sites, same post ID because they are clones):**
`postmeta _elementor_code` on **post 3236**, post_type `elementor_snippet`, titled **"Local Business Schema"**. It injects a hand-built `Hospital` / `MedicalBusiness` JSON-LD block into every page, whose `description` contains the claim.

- Irving: on all 97 URLs. Lufkin: 152/153. White Rock: nearly all 127 (EN + /es/).
- **The plugin CANNOT edit it.** `draft_update_postmeta` on 3236 returns 403 `post_type_not_allowed`. The "Allow editing Theme Builder templates" setting covers `elementor_library`, NOT `elementor_snippet`. This is OPERATOR-ONLY: WP Admin > Elementor > Custom Code > "Local Business Schema" > edit the `description` field.
- I deliberately did NOT widen the plugin allowlist to include `elementor_snippet`: that would let the assistant write site-wide code/scripts, a bigger permission grant than the task justified. Propose it explicitly before doing it.

**THE SECOND SOURCE, easy to miss: SEO meta descriptions.** A single `rank_math_description` renders THREE times per page (meta description + og:description + twitter:description), so one field looks like three hits. Fixed via `draft_update_seo_meta(logical_key="description")`. Queued 2026-08-28: Irving #1083-1092 (10 pages), Lufkin #810-815 (6 pages).

**Third source: genuine body copy** in Elementor text-editor widgets. Still open at time of writing: Lufkin ~10 pages (chest-pain 2516, imaging 852, blood-clot 1295, Burke 3680, fracture 1374, bronchitis 909, abdominal 1041, our-team 6678, 2 blog posts); Irving 3 (services-hub FAQ 541, Coppell 3635, fractures 1374); White Rock ~10 incl. homepage, Garland, Lake Highlands, Lakewood, services hub and several /es/ twins.

**Lufkin's 7 newest service pages (6956-6962) REINTRODUCED the claim** after the sweep, because they were built from a spec that still carried it. Fixed in #803-809. Lesson: a fleet-wide claim removal must also patch whatever template/spec generates new pages, or the next build re-adds it.

**METHOD THAT WORKS (do not use list_posts search):** `post_content` is a stale snapshot on Elementor pages. Sweep the sitemap with curl + a browser UA, then classify each hit as head-meta vs JSON-LD vs visible body, because the three need three different fixes. Counting raw occurrences without classifying produces badly wrong scope estimates (I initially mis-read Irving's ubiquitous schema hit as body copy on 90 pages).

Related: [[feedback_no_medication_sourcing_claims]], [[feedback_verify_service_exists_before_service_page]], [[reference_elementor_post_content_is_stale]]
