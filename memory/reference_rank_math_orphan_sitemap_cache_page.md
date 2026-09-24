---
name: reference_rank_math_orphan_sitemap_cache_page
description: Rank Math sitemap can keep a stale cached page forever (duplicates + missing URLs at page boundaries); fix = re-save Sitemap Settings; detect by counting raw entries
metadata:
  type: reference
---

sids-ponds 2026-09-17: product-sitemap2.xml was a stale cached page. Products edited on 2026-09-15 (via cc-assistant meta_update, which uses wp_update_post and DOES fire save_post) moved to page 1; page 2 kept their old lastmod, so 3 URLs were duplicated and the 3 products pushed across the 200-item boundary (in-lite Puck 22, Fusion 22, Fusion 22 RVS) were in no sitemap.

**Detect:** fetch every product-sitemapN.xml and count RAW entries, not a deduped Set. Duplicates with different lastmods = stale page. Same lastmod on page 1 and an older one on page 2 is the tell.

**Fix (verified):** operator clicks Save Changes on Rank Math > Sitemap Settings with no changes. That runs the full `Cache::invalidate_storage()` (deletes the whole uploads/rank-math dir). After: 408 unique, 0 dups.

**Likely mechanism (read from Rank Math 1.0.268 source locally, site runs 1.0.278, not proven for the event):** per-type invalidation deletes only files listed in the `sitemap_cache_files` option map, and `store_sitemap()` updates that map read-modify-write. Concurrent generation of several pages can drop an entry, leaving an untracked file that type invalidation never deletes. A full clear does delete it.

**Not our bug:** cc-assistant apply paths fire save_post. Possible defensive fix: full Rank Math sitemap invalidation after a bulk apply.

Related: [[reference_setting_revert_restores_whole_option]] (why not to queue a no-op sitemap option write).
