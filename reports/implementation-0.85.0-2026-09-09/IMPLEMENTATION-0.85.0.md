# CC Assistant 0.85.0 implementation report

This release implements the priority reliability corrections and the first content-strategy features from the two research reports. It lets Claude plan useful new blogs from the business's services and reader needs even when Search Console has no relevant observations. It does not implement the entire research roadmap.

## Added

- **Niche-led blog planning:** selected service/pillar pages, audience, excluded topics, recorded reader questions, practical reader tasks, custom ideas and optional GSC evidence. No GSC gap or impression threshold is required. Proposals include source IDs, reader benefit, overlap candidates, demand uncertainty and evidence needed for a useful contribution.
- **Content Strategy settings:** administrator-managed niche profile under CC Assistant > Content Strategy. With no selected scope, published-page suggestions are explicitly inferred. More proposals and content inventory have separate pagination controls.
- **Five MCP tools:** `get_content_scope`, `plan_blog_content`, `content_research`, `content_decision`, `content_decision_history`.
- **Competitor research:** bounded collection of up to three explicit public URLs, with timestamps, source hashes, extraction states, short evidence excerpts and a claim ledger. Failed requests, redirects and CAPTCHA remain uninspected; the tool does not invent rankings, factual verification or originality.
- **Stable decision dossiers:** compare up to eight pages for refresh/link/differentiate/merge/retire research, with alternatives, missing evidence and preservation requirements. Identical inputs and source hashes reuse the saved decision; changed evidence creates a separate record. Records are actor-scoped and bounded, with database locking and explicit persistence errors.

## Corrected

- Query/page GSC counts are no longer proportionally allocated across page-level search appearances. These dimensions now use separate datasets with transactional date replacement. Relevant position aggregates are impression-weighted.
- Low CTR and non-Web search appearances no longer prove AI Overview presence or AI-caused lost clicks in the corrected diagnostic paths. Dashboard and keyword recommendations now distinguish observations from causal hypotheses.
- A cluster of three pages, lexical similarity, multiple observed URLs or low impressions no longer automatically justify merging, pruning or deindexing content in the corrected recommendation paths.
- Prices, tables, quotations, credential-like text and authority-looking links no longer certify first-party expertise or earn a fabricated numerical information-gain result. Authority host checks use domain boundaries.
- Originality inspection uses Elementor-aware stored extraction. Broken extraction is unknown, and dynamic/shortcode output is marked partial instead of silently using stale content.
- Short and full SEO guidance share one versioned rule source. Removed unsupported ranking-lift claims and rigid editorial quotas from that shared guidance.
- Audit comparisons explicitly detect removed rules, including a previously failing rule disappearing from the new audit.
- Drafting instructions and relevant tool descriptions direct Claude to inspect niche, coverage and useful reader contribution before drafting.

## Validation

All 35 PHP regression files passed. PHP syntax checks passed for 166 files. The real MCP stdio smoke check returned version 0.85.0 and 169 distinct tools, including the five additions. The existing Python execution-gate regression passed. The installable archive passed CRC, expected-content and byte-for-byte staged-source checks.

New regression cases cover missing/empty GSC, service boundaries, candidate pagination, related-page growth, Elementor failures, false originality signals, removed audit rules, decision stability, language differences, failed/concurrent storage, blocked competitor fetches, permissions, count inflation, idempotent date replacement and rollback.

These are local checks. Measurement fixtures run SQL against SQLite; a live MySQL migration and authenticated calls on erofwhiterock.com were not performed. The live site's rankings and third-party plugin behavior remain outside this verification.

## Install and configure

1. Upload `cc-assistant-0.85.0.zip` in WordPress and replace the existing plugin.
2. Refresh a separately stored desktop bridge, including all PHP files in `bin` and the new `content-tools.php`. Restart Claude's MCP connection or start a fresh session. Call `whoami` and confirm version 0.85.0 and the new tools.
3. Open **CC Assistant > Content Strategy**. Select published service or niche pillar post IDs, intended readers, exclusions and useful general reader questions.
4. Ask Claude: **Use plan_blog_content to propose five useful blogs within our services, including topics with no GSC history. Explain the reader need, source page, existing overlap and useful contribution before drafting.**
5. Re-sync relevant GSC dates before relying on corrected historical comparisons. Existing inferred rows cannot be reconstructed reliably without a re-sync. New-topic planning can start immediately without it.

The GSC appearance table is created on a subsequent sync. Date replacement requires verified InnoDB tables. The sync reports a storage error rather than replacing data without transactional protection. Existing API, retention and low-impression pruning limits still apply.

## Remaining roadmap

The new tools prepare evidence and decisions; they do not automatically execute consolidation, redirects, noindex or deletion. Advanced coordinated change sets, contextual link insertion, scheduled claim-drift monitoring, conversion-aware evaluation, external SERP/backlink integrations and broader plugin capability adapters remain future work. Existing heuristic reports outside the corrected paths also warrant continued review.

No code can guarantee that a language model will never guess. This release gives Claude clearer tool contracts, reproducible evidence and explicit unknown states; it does not certify every answer or guarantee ranking improvements.

See `CONTENT-STRATEGY.md` for tool boundaries and configuration. `changed-files.json`, `test-results.json`, `mcp-smoke-results.json` and `release-validation.json` provide the file manifest and validation evidence. The source backup preserves the original reviewed 0.84.0 files without including runtime credentials, databases or dependencies.
