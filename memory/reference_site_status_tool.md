---
name: reference-site-status-tool
description: "site_status (v0.72.0) - one MCP tool answering how is the site doing / what is happening / where is it lacking; LOCAL warehouse only, so it needs an MCP restart not a zip deploy"
metadata:
  node_type: memory
  type: reference
---

**Added v0.72.0; hardened through v0.73.0 (2026-08-24).** `bin/site-status.php`, registered in `bin/mcp-server.php`. Args: `days` (default 28, 7-90), `max_findings` (default 8).

**Why it exists.** The plugin already had 14 admin screens and a dozen audits, none of which surfaced what actually mattered on a real diagnostic pass. Everything in it had to be hand-written in SQL first.

**Returns:** `headline`, `happening`, `working`, `lacking[]`, `caveats`.
- `happening` carries the **branded vs non-branded click split** (awareness is not organic reach) and the **AI fan-out impression trend**.
- `lacking[]` runs six checks, each with headline + why + action + evidence:
  `self_competition` (3+ OWN urls on one query), `ai_absorbed` vs **cited**, `unreachable_by_content` (near-me = GBP lever), `dead_inventory` (0 lifetime clicks), `losing_ground`.

**The distinction that matters most:** absorbed and cited have IDENTICAL symptoms (high impressions, no clicks) and opposite meanings. Absorbed = an AI Overview ate the demand. Cited = machine fan-out queries are quoting you, which is a WIN and must never be pruned. See [[project_cc_assistant_attention_system]] and the IWC Decisions entry on judging blogs by fanout, not clicks.

**DEPLOYMENT: this is a `bin/` tool. It needs an MCP RESTART, not a zip install** - it reads the local SQLite warehouse and never touches the site. The zip only carries version parity. See [[reference_plugin_dev_vs_remote_deploy]].

**Known heuristic limits (stated in the tool's own `caveats`):** brand tokens are derived from the site host, not curated - check `happening.branded.tokens` if the split looks wrong; fan-out detection is pattern-based (long sentence / named authority), not a Google label; warehouse data only, so conversions and calls are invisible.

First live run on irvingwellnessclinic (28d): clicks 36 -> 92 but 60.9% branded; fanout impressions 330 -> 1,735; findings = 7,574 absorbed imp, 8,650 map-pack imp, 15 zero-click pages, self-competition on "wellness clinic".

## Hardening history (each fix came from running it on a site it had not seen)

- **v0.72.1** branded detection failed on ABBREVIATION hosts. `erofirving` = "ER of Irving"; the searcher types "er of irving", er/of fall under the 5-char floor, so only one token survived and detection disabled itself. Added a second route: collapse the query to alphanumerics and match the host blob. erofirving went 0 -> 70 branded clicks. Also stopped `losing_ground` reporting DELIBERATE retirements as decay (a page with zero impressions left was removed, not decaying) - counted as `excluded_removed` instead.
- **v0.72.2** `str_word_count()` IGNORES NUMERICS, so "american heart association hypertensive crisis 180 120 symptoms emergency" measured 7 words against a >=8 threshold and was classified **absorbed** (stop optimising) when it is **cited** (protect it). ~7,800 imp of eroflufkin's hypertension page was mislabelled. Now splits on whitespace; authority list gained american heart/college/diabetes, WHO, UpToDate, Merck.
- **v0.73.0** `self_competition` now names `google_prefers` (the page Google already ranks best = the natural consolidation owner) and `zero_click_rivals` (the count earning nothing = deindex those first). New `indexable_dilution` check for indexed pagination/archive URLs, split into `pagination` (uncontroversial deindex) vs `archive` (judgement call), with a 100-impression materiality floor.

**The materiality floor earned its keep immediately.** I had told the operator pagination was "the clearest win available" on erofirving, extrapolating from one query over a long window. The check refused to fire; investigating why showed those URLs draw **53 impressions across 4 URLs in 28 days, 0.007% of 750,003**. The claim was wrong and the threshold caught it. The real dilution there is 9 location pages + 12 service pages. LESSON: measure the pool before calling anything a win.
