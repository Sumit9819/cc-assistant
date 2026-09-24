---
name: quote-lint-v0-51-2
description: "cc-assistant v0.51.2 enforces quote quality at queue time on ALL sites - source link (hard), attribution (warn), section relevance (warn); scoped to NEW quotes only"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 501752a8-8c88-48d5-88ba-e672ff5142f9
  modified: 2026-07-21T04:52:28.524Z
---

Built 2026-07-21 after the operator asked to "tighten the quote system" so weak quotes can't slip through review. `CC_Assistant_Pre_Publish::quote_checks()` runs on every content path (post create/update/patch via lint_post_content_change; widget/container/import payloads via lint_html_block) on every connected site.

Three checks per NEW `<blockquote>` (quotes already in the current body are skipped, so legacy posts never flag on surgical edits):
1. **quote_source_link (HARD)** - no `<a href>` inside the blockquote = queue refused. Enforces the verified-verbatim-with-source rule mechanically.
2. **quote_attribution (warn)** - no text after the quote paragraph and no `|` separator = missing "Name, Title | Source" card line.
3. **quote_relevance (warn)** - the editorial test "does removing the quote remove a fact?", approximated: 5-char-prefix token overlap between quote text and (document's FIRST heading + nearest heading above the quote), stopword-filtered, must be >= 2. Verified by 6-case harness: the real Faubion off-topic card FAILS, the Wender pellet-fact and Aronne non-responder quotes PASS.

Test harness: scratchpad test_quote_checks.php (needs `-d extension=mbstring` with Local's CLI PHP).

Editorial rule this encodes: [[headings-are-queries-not-prose]] sibling - **quote cards state section-specific FACTS, not generic authority**. Related: [[expert-quotes-in-blogs]] [[cc-assistant-page-builder-roadmap]]
