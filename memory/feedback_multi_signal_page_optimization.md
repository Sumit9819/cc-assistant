---
name: use-all-available-site-signals-not-just-gsc-when-optimizing-a-page
description: "Before queueing meta/body/schema changes on a page, pull cluster role, duplicate content, link health, image audit, accessibility, style guide, and niche-specific external research — GSC is one input, not the lens."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

When optimizing a page (homepage, service page, or blog), GSC tells you what users searched and what you ranked for. It does NOT tell you whether the page is structurally healthy, near-duplicate of a sibling, missing schema, orphaned in the link graph, inaccessible, or citing outdated sources. Pull these signals FIRST, then decide what to change:

1. **`post_dossier`** — cluster role, all sibling pendings, inbound/outbound internal links with anchors, GSC queries. One call replaces three.
2. **`find_duplicate_content`** — cosine similarity to sibling pages. A 0.7+ cosine match means content-level cannibalization that title changes won't fix.
3. **`analyze_post_structure`** — heading depth, word count vs cluster norm, image count, link count. Identifies structural deficits before meta-level changes.
4. **`audit_post_images`** — alt text coverage, oversized files (LCP / Core Web Vitals impact).
5. **`audit_post_links` / `links_audit_post`** — broken links, weak anchors, missing pillar link.
6. **`accessibility_audit`** — WCAG checks. Affects rankings via Core Web Vitals + legal compliance.
7. **`analyze_topic_cluster`** (if pillar/supporting) — gaps in supporting coverage.
8. **`get_style_guide`** — site-specific rules (e.g. no em dashes, no AI-tells).
9. **`gsc_cannibalization`** — sibling pages competing for the same query. Often the bigger lift is de-tuning a competing page, not optimizing the target page.
10. **External research** — web search for new authoritative sources in the site's niche. CDC, NIH, MedlinePlus, AHA, JAMA, FDA, peer-reviewed journals. Refresh outdated citations; surface 2025-2026 developments.

**Why:** 2026-05-12 — User asked for broader optimization scope than the GSC-only approach used early in the homepage meta session. GSC surfaces CTR gaps and rank positions, but a page may also have: (a) near-duplicate content with a sibling that drains both pages' rank, (b) missing schema preventing rich snippets, (c) orphan status from poor internal linking, (d) outdated citations damaging EEAT, (e) accessibility failures hurting Core Web Vitals. A GSC-only diagnosis misses all five.

**How to apply:**
1. Before queueing the first `draft_update_*` on a page, run at least: post_dossier + find_duplicate_content + analyze_post_structure + audit_post_links (parallel calls).
2. If duplicate cosine > 0.65, propose differentiation or merge BEFORE meta changes.
3. If structure is the bottleneck (e.g. thin word count, missing headings), body work outranks meta work.
4. If page is orphan (0 inbound), fix link graph FIRST — meta optimization on an orphan rarely moves the needle.
5. Cross-reference with [[feedback_homepage_title_data_check]] (the inverse rule for title changes) and [[feedback_pre_post_duplication_check]] (duplicates).
