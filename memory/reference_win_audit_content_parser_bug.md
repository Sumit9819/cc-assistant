---
name: reference-win-audit-content-parser-bug
description: "win_audit's content parser can return zeros for stats/links/lists/tables that provably exist, making its score useless for that page; verify against render_probe before trusting it"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ae37a612-eb3c-4f37-b85d-76f7f880700a
  modified: 2026-07-27T05:31:12.785Z
---

`win_audit` can report **zeros across every content dimension** for a page whose content demonstrably contains those elements. When that happens its overall score is meaningless for that URL and must not be used as a baseline or a success metric.

**Proven with a controlled before/after, 2026-07-27, irvingwellnessclinic post 6356:**

An edit added 4 statistics (3.6%, 12.5%, 200 pg/mL, 300 pg/mL), a second `.gov` authority link, and a 4-item `<ul>`. Confirmed live via `get_post` (body contains the text, word count 1253 → 1328) and `verify_change` (status approved).

`win_audit` before and after the edit:

| Dimension | Before | After | Reality |
|---|---|---|---|
| info_gain / stats_per_1k | 0 | **0** | 4 statistics present |
| authority_links | 0 | **0** | 2 `ods.od.nih.gov` links present |
| extractability / lists | 0 | **0** | 6 `<ul>`/`<ol>` present |
| extractability / tables | 0 | **0** | 1 `<table>` present |
| question_headings | 1 | **1** | 9 question-shaped H2s present |
| first_para_words | 0 | **0** | intro paragraph present |
| **freshness / days_old** | 20 | **0** | correct, it DID refetch |
| Overall score | 26 | **26** | unchanged |

`freshness` updating proves the tool re-fetched the page. So this is **not a cache issue** — the fetch succeeded and the content parser then extracted nothing. `first_para_words: 0` is the giveaway: any page with a visible intro should never report that.

**How to apply:**
1. Before trusting `win_audit`, sanity-check two or three of its structural claims against `render_probe` (live DOM) or `analyze_post_structure`. If they disagree, the parser has failed and the score is void.
2. Its **competitor** numbers appear sound even when own-page parsing fails (competitor stats_per_1k, images, bylines matched the actual pages). Use it for competitive intel, not self-assessment.
3. When the parser fails, fall back to GSC position and clicks at 28 days as the real measure. See [[feedback_gsc_window_28d_before_diagnosing]].
4. Do not set a `success_metrics` hypothesis phrased as "lift the win_audit score" on an affected page. Phrase it in GSC terms.

Sits alongside [[reference_paragraph_length_lint_preexisting]] (same plugin, same day) and [[reference_cc_assistant_schema_generator_bugs]]. Per [[feedback_flag_root_cause]], both are plugin defects worth fixing in source, not working around forever.
