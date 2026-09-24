---
name: feedback-read-live-meta-before-rewriting
description: Always fetch the live rendered meta title + description before proposing rewrites; never trade high-impression tokens for one new word
metadata: 
  node_type: memory
  type: feedback
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

Before queueing ANY `draft_update_seo_meta` rewrite, read the live values first (`curl` the page and parse the `<title>` + `<meta name="description">` tag). Build a token-coverage matrix: which tokens does the current value own, which tokens does the GSC impression distribution demand, and what does the rewrite remove vs add. Only queue if the net trade preserves all high-impression tokens.

**Why:** 2026-05-14 — Without reading the live meta, I proposed `Bioidentical Hormone Therapy in Irving, TX | Pellets + TRT` to replace `Hormone Therapy Clinic Irving, TX | Bioidentical HRT & TRT`. Looked like an improvement on the surface; actually dropped `clinic` (page ranks for hormone clinic irving pos 4.5, hormone health clinic near me, etc.) and `HRT` (page ranks for hrt near me 6 imp, bhrt near me, hrt therapy near me) — both high-impression tokens — in exchange for one new word "Pellets" that has 2 impressions in 28 days. Same pattern on the weight loss page: traded `clinic` + `GLP-1` for `tirzepatide`, losing 44 impressions of "weight loss clinic" coverage. User caught it: "Why are you removing clinic? You're not being systematic."

**How to apply:**
1. `curl -s -A "Mozilla/5.0 ..." <page-url>` and grep `<title>` + `<meta name="description">` + `<meta property="og:title">` BEFORE drafting any rewrite. Or use a direct read if exposed by the plugin.
2. Run `gsc_page_queries` and rank queries by impression count. List the tokens in those queries.
3. Tokens in the CURRENT meta title that appear in ranking queries are LOAD-BEARING — never drop them without a 2x stronger replacement.
4. The original well-crafted title is almost always closer to optimal than my rewrite. Start from "what's missing that the page is ranking for" instead of "let me make this better."
5. When in doubt, propose the diff to the user with explicit remove/add columns BEFORE queueing. See also [[feedback-multi-signal-page-optimization]].
