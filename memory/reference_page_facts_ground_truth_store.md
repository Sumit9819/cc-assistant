---
name: reference-page-facts-ground-truth-store
description: "page_facts (v0.75.0) - rendered-page ground truth store with explicit freshness + after-apply verification; READ IT before claiming anything is present/absent on a page, and read its verdict instead of the apply success flag"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-25T04:29:34.464Z
---

**Added v0.75.0 (2026-08-25), `includes/class-page-facts.php`, table `cc_page_facts`.** Built because the operator asked for "something that will let you know without you searching it ... 100% accurate", after the container-link false gap ([[feedback_dom_is_ground_truth_not_parsers]]).

**One reader, one store.** Every fact is derived from the rendered HTML via the render probe's fetch/DOM path: meta (title, description, robots, canonical, hreflang), headings outline + h1_count, links with `href/path/anchor/internal/location(content|nav|header|footer|breadcrumb)/wraps_children`, images + missing_alt, JSON-LD blocks + emitter, approximate word count, http_code, body_sha1, cache_state.

**The boundary (stated to the operator):** only deterministic rendered facts live here. Rankings, absorption, branded detection, quality judgements stay labelled measured/inferred elsewhere.

**Freshness is visible, never silent.** `get()` marks a record stale when post_modified_gmt > captured_at, when `_cc_assistant_last_internal_apply` > captured_at, or after 7 days; it refreshes before answering and returns `stale_before_read`. If a refresh fails it returns the old record with an explicit STALE warning.

**Writes are verified.** `after_apply()` (called from class-apply.php on every apply to a published post) recaptures and checks `expectations_for_pending()` against the DOM: robots noindex/index, meta description/title contains, widget link_present, widget text_present. Verdict `verified | FAILED | inconclusive_cache` is stored on the facts row AND merged into the pending row's `verification_result.page_facts`, and returned in the apply response. **This is the answer to "did the write land"; the apply success flag is not.** The noindex serialization bug would have read FAILED, not success.

**Capture triggers:** save_post (deferred 20s, published + allowed types only), after every apply, nightly `cc_assistant_page_facts_sweep` (stale-first, 40/run), on demand. Never on a front-end page view ([[feedback_plugin_performance]]).

**Surfaces:** MCP `page_facts {post_id, refresh}`; REST `/facts/{id}`, `/facts/coverage`; `whoami.session_recap.facts_coverage` {published, fresh, stale, missing}; `audit_post_links.rendered_cross_check` now reads from it (`source: page_facts@<time>`).

**Tests:** `tests/page-facts-test.php` (41 assertions) pins href classification, link location (footer menu link must read footer, not nav - a real bug caught on first run), staleness rules, and the verifier including the exact noindex-still-index case.

**Deploy:** WP-side (`includes/`) + `bin/` both changed -> upload zip AND restart MCP ([[reference_plugin_dev_vs_remote_deploy]]). First read on each site will be slow-ish (captures on demand); the nightly sweep fills coverage.
