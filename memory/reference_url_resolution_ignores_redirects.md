---
name: reference-url-resolution-ignores-redirects
description: "PLUGIN DEFECT: GSC/commodity/AEO tools resolve URL->post with url_to_postid() only, never checking the Rank Math redirect table — redirected URLs report as unresolved and their impressions vanish from analysis"
metadata:
  node_type: memory
  type: reference
---

**FIXED + VERIFIED IN PRODUCTION, v0.71.0 (2026-08-20).** Confirmed by source read against v0.70.0.

`includes/class-rest-aeo.php:58` `handle_signals()` resolves a GSC page URL with `url_to_postid( $url )` and nothing else. On 0 it returns `found:false`. `bin/warehouse.php:892` then prints a **speculative** note: *"URL did not resolve to a post (Custom Permalinks / host mismatch?)"* — a guess presented as an explanation. It never consults the redirect table.

**The data is already in the plugin.** `includes/class-redirect-audit.php:22` queries `{prefix}rank_math_redirections` and has `extract_sources()` + `norm_path()` helpers. And `includes/class-internal-links.php:619` already carries a fallback chain (get_page_by_path full path -> last segment -> strip lang prefix) with a comment listing the failure cases. **One module learned the lesson; it was never generalized.** 17 `url_to_postid()` call sites; the GSC/commodity/AEO path has no fallback.

**Why it matters — it corrupts analysis, not just labels.** Impressions on a redirected URL belong to the destination post but are dropped:
- irvingwellnessclinic 2026-08: 4 URLs / 1,388 imp orphaned. Post 6356 reported 6,501 imp, really ~7,290.
- Worse: `topical_authority` called the microneedling cluster **"redundant — merge and redirect"** while 237 imp were ALREADY arriving via a redirect from `does-microneedling-hurt`. The tool could not see its own prior consolidation and recommended redoing it.

**Fix:** one shared `resolve_url_to_post()` returning `[post_id, via]` where via = `direct|path|redirect:301`. Chain: url_to_postid -> get_page_by_path(full, then last segment, lang-prefix stripped) -> redirect-source lookup. Fold redirected warehouse rows into the destination with an explicit `via_redirect` marker (visible, never silent). Per [[feedback_no_per_plugin_adapters]] take a redirect-source *interface*, not a Rank Math hardcode.

**Operator lesson:** run `redirect_audit` + `draft_create_redirect(_diag=true)` BEFORE reporting any URL as broken/orphaned. Same trap as [[reference_gsc_inspect_410_vs_404]]. See [[feedback_probe_discipline_positive_controls]].

## Fixed in v0.71.0

New `includes/class-url-resolver.php` — `CC_Assistant_URL_Resolver::resolve()` returns `[post_id, via, hops, code, final_url]`, via = `direct|path|custom_permalink|redirect|gone|loop|unresolved`. Chain: url_to_postid -> get_page_by_path (full path, last segment, lang prefix stripped) -> **custom_permalink postmeta** -> **Rank Math redirect table** (exact + longest-start; regex/contains deliberately NOT guessed). Host guard: a foreign-host URL never falls through to slug matching (that bug was caught by its own test — it would have attributed another site's metrics to a local post). 410 returns via=`gone`, which finally distinguishes deliberate retirement from a broken URL.

Wired into: commodity signals, warehouse commodity_audit (+ `cc_commodity_fold_redirects()` merges a redirected URL's impressions into its destination, impression-weighted position, `folded_from` provenance), refresh_queue, brief_for_keyword, gsc missing_mentions + aio, llm-tracker, internal-links (both sites — its private fallback chain was deleted in favour of the shared one). Caches flushed on create/delete/untrash redirect and on slug-change redirect.

Tests: `tests/url-resolver-test.php` (29 assertions) + folding tests in `tests/commodity-test.php`.

## Verified live on irvingwellnessclinic 2026-08-20

`commodity_audit(days=90, limit=30)`, before -> after, predicted numbers hit exactly:
- REVIEW rows 2 -> 0; both former `post_id: 0` rows resolve (6356, 8139)
- post 6356: 6,501 -> **6,711** imp, pos 18.4 -> **18.8**, `folded_from` = /iv-therapy-for-fatigue-how-it-works/ (301, 210 imp)
- post 8139: 815 -> **1,014** imp, pos 9.4 -> **12.1**, `folded_from` = /laser-genesis-large-pores-skin-texture/ (301, 199 imp)
- verdicts did NOT flip (6356 stays CITE_PLAY, 8139 stays BRIDGE) — classification is stable, just computed on correct volume now
- `page_canonical_hash` migration applied cleanly; `get_edit_outcome` returns measured with no Unknown-column fatal; `confidence` block renders (`level: full, normalization_factor: 1`)

Still to deploy: erofirving, eroflufkin, erofwhiterock (all on 0.64.0). erofwhiterock benefits most — it is the Custom Permalinks site with the false-orphan problem on swapped service pages 5342/5360/5366; run `links_rebuild` there after deploy and re-check `links_orphans`.
