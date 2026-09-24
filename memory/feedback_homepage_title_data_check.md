---
name: pull-architecture-ranking-data-before-recommending-homepage-title-changes
description: "Before re-ordering or rewriting a homepage/service-page SEO title, gather three data points — service-page existence, current GSC rank on the term being demoted, and full list of page-1-zero-CTR opportunities — to avoid optimizing the wrong pond."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

Before recommending a new SEO title for a homepage or service page, pull these three data points and weigh them against the change:

1. **Service-page architecture.** Does a dedicated page exist for the keyword you're considering leading with? Use `list_posts` with a `search` for the term. If a dedicated page exists, the homepage should de-tune from it (avoid cannibalization). If not, the homepage IS the de-facto landing page for that term — keeping it prominent is correct.
2. **Current GSC rank for the term you'd be demoting.** Pull `gsc_page_queries` on the target post (28d or 90d). If we already rank page 1 (pos ≤10) for the term being moved out of title-position-1, the move risks a relevance-signal downgrade that costs the ranking. The pre-existing ranking is information; honor it.
3. **All page-1 zero-CTR queries on the page, not just brand-CTR gap.** Rank opportunities by `impressions * (target_ctr − current_ctr)`. A 350-imp query at pos 6 with 0 CTR usually beats a brand-CTR rescue from 8% → 18% on the same impression base.

**Why:** 2026-05-12 — For irvingwellnessclinic.com homepage (post 8) I proposed `Irving Health & Wellness Clinic | Med Spa & Health Center` (brand-first) based purely on brand-CTR-gap diagnostics (brand at 8.3% CTR vs 18.7% expected curve). User pushed back: "Med Spa has more search volume." Pulling data:
- No `/med-spa/` page exists — homepage is the de-facto med-spa landing.
- Live title already led with "Med Spa" and ranked pos 9.4 for "med spa irving" (34 imp/0 clicks) and pos 11.3 for "med spa irving tx" (30 imp/0 clicks).
- Brand-first demotes "Med Spa" to title-position-4 — likely costs the pos-9 page-1 ranking.
- Bigger CTR rescue on the same page was actually `irving health center` (352 imp, pos 6.5, 0 CTR), which "Health Center" in the title captures regardless of brand placement.

The brand-CTR rescue was real but smaller; pursuing it cost a winnable page-1 ranking. I optimized the smaller pond by skipping the architecture and current-rank checks.

**How to apply:**
1. Any time you're about to call `draft_update_seo_meta` on a homepage or service page with a structurally different title order than the live one, first call `list_posts` (architecture) + `gsc_page_queries` 90d (rankings).
2. If the live title leads with a term that already ranks page 1, do NOT demote it without an explicit reason that outweighs the rank loss.
3. Brand presence in title ≠ brand-first ordering. The brand string just needs to appear in the title for SERP skim-match; pos 2 brand rankings are anchored by inbound links + EEAT, not title word order.
4. Cross-reference with [[feedback_service_page_cannibalization]] — the inverse rule (don't compete with service pages from blog posts) shares the same architecture-check discipline.
