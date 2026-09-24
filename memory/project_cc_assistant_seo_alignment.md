---
name: cc-assistant-seo-alignment
description: "Gap analysis between cc-assistant plugin v0.21 and Google's 2026 SEO doctrine — prioritized list of plugin changes (P0 guards, P1 advisor cards, P2 nice-to-have, P3 playbook patches)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 0148947e-9e56-42d0-abd7-8d217c330a37
---

Gap audit conducted 2026-05-19. cc-assistant v0.21 covers foundational SEO well (E-E-A-T rules, schema templates, cluster analysis, GSC integration, link graph) but has measurable gaps vs Google's full doc set. See [[google-2026-seo-doctrine]] for the source rules.

**Why:** User wants to ship plugin changes that anchor to documented Google rules. The audit identifies which rules the plugin currently does NOT check or enforce, ranked by leverage.

**How to apply:** When user asks about plugin improvements or new tools, propose from this list (with priority + rationale). Re-verify the doctrine memory hasn't been updated before quoting specific rules.

**P0 — Hard guards (block bad publishes)**:
1. canonical_audit (head-placement, single, absolute, self-ref, no JS injection)
2. hreflang_audit (reciprocity, head-placement, valid ISO, canonical agreement) — fills gap behind unreliable polylang_link_translations
3. schema_parity_check (JSON-LD tokens must appear in rendered DOM)
4. self_review_detection (aggregateRating/review on own Organization → manual action risk; high relevance for clinic sites)
5. faqpage_eligibility_gate (warn non-govt/health sites still shipping FAQPage post 2026-05-07)
6. Extend sanitize_unsafe_text_globals beyond white-on-white: opacity:0, font-size:0, off-screen positioning, display:none on keyword content
7. back_button_hijack_lint (April 2026 spam-policy addition)

**P1 — High-leverage advisor cards**:
8. helpful_content_score per post (Google's 28-question self-assessment → 0-100 score; THE March 2026 reweighted signal)
9. site_quality_score (domain aggregate of #8; March 2026 site-wide signal)
10. external_originality_check (cosine vs top-3 SERP competitors, not just intra-site find_duplicate_content)
11. eeat_coverage_audit (byline + schema Person + 1 .gov/.edu per 1000 words density check)
12. traffic_drop_diagnostic (5 official categories: algorithm, technical, security, spam, seasonality)
13. core_update_calendar (Search Status Dashboard scrape; pause major rewrites during active rollout)
14. title_rewrite_risk_audit (predict when Google rewrites the SERP title: half-empty, obsolete date, micro-boilerplate, lang mismatch, etc.)
15. page_experience_audit (HTTPS + CWV via PageSpeed Insights API + interstitial detection)
16. schema_deprecation_audit (flag data-vocabulary, deprecated 2025 types, FAQ on non-qualifying sites)

**P2 — Lower leverage**: duplicate_title_detection, breadcrumb_schema_audit, localbusiness_completeness_audit, redirect_chain_audit, image_seo_audit_v2 (filenames + background-image-as-content + missing fallback src), mobile_parity_audit, page_size_audit (15MB crawl-budget), lazy_load_lint, keyword_stuffing_lint (phone/city blocks).

**P3 — Playbook (seo_playbook) updates, no code needed**:
- Drop "llms.txt or equivalent" line (Google explicitly says unnecessary)
- Add AI-agent readiness section (DOM + a11y tree — agents parse like screen readers)
- Add "FAQ rich results retired 2026-05-07 for non-govt/health" note + schema-still-valid clarification
- Add schema-must-be-visible-in-DOM rule (structured-data policy explicit)
- Add March 2026 site-wide helpful-content signal section + "remove or rewrite, don't just bury thin content"
- Add originality-vs-the-web emphasis (not just intra-site dedup)
- Add back-button hijacking to spam-policy section
- Add scaled-content-abuse note: covers translated content via automated transformations (run find_duplicate_content at 0.8 before publishing translated pillars)

**Recommended build order**: P3 playbook patches first (immediate value across all 5 sites, no code), then #1+#2+#4+#7 as a P0 cluster ("spam-policy + structural guards" patch), then #8 helpful_content_score (longest lever, most design work).
