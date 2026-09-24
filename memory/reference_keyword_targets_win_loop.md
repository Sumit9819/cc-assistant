---
name: reference-keyword-targets-win-loop
description: keyword_targets (v0.74.0) - the per-keyword WIN LOOP; persistent query->owner registry in the warehouse with verdicts + next_action; this is how the plugin beats competitors instead of just auditing
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-25T03:35:22.634Z
---

**Added v0.74.0 (2026-08-24), `bin/keyword-targets.php`.** Built after the operator asked how to actually WIN keywords, not just gather information. Root gap found: the plugin had audits (win_audit), measurement (outcome engine) and synthesis (site_status) but **no persistent per-keyword campaign** - `success_metrics` die with their edit, nothing said "query Q belongs to page P, track until won", every session re-derived the battlefield.

**Loop:** `set {query, owner_page}` once -> `report` each session -> act on `next_action` via pending queue -> next report shows movement.

**Verdicts (worst first):** `hijacked` (owner has no impressions, another own page ranks) -> `outranked_internally` (owner ranks but an own page ranks BETTER = parent-beats-child, the most fixable: reroute anchors, teaser the rival) -> `losing` -> `stalled` (with >=2 rivals: consolidate; without: competitive gap -> win_audit vs REAL SERP URLs from Ubersuggest MCP) -> `not_ranking` -> `absorbed` (AI answer eats clicks: reformat for citation, judge on impressions) -> `low_data` (<20 imp: refuse to trend) -> `gaining` -> `won`.

**Two verdict bugs caught by running on real IWC data before shipping:** (1) "botox irving tx GAINING +35 positions" on 2 impressions - added the 20-impression floor; (2) "facial fillers stalled, no self-competition" while its one rival outranked it 7.3 vs 19.5 - the >=2-rivals threshold hid the parent-beats-child case; added `outranked_internally`.

**Storage:** `kw_targets` table in the per-site warehouse SQLite. **bin/ tool -> MCP RESTART, not zip** ([[reference_plugin_dev_vs_remote_deploy]]). Tests: `tests/keyword-targets-test.php` (32 assertions incl. utm-variant folding so an owner is never its own rival).

**Playbook bumped to 2026.08.24.1** with six measured rules served in whoami top_rules: one-owner-per-query, absorbed-vs-cited, anchor routing, branded-share honesty, local volume is not where demand is ("medical weight loss irving tx" = ~10 typed searches/mo; demand lives in GSC impressions + map pack), and THE WIN LOOP itself. Ubersuggest stays a sibling MCP joined at session level ([[reference_ubersuggest_mcp]]), never proxied.

**IWC targets seeded 2026-08-24** (6): medical weight loss irving, weight loss clinic irving tx, botox irving tx, facial fillers irving tx, iv therapy for fatigue, how to reverse insulin resistance. Re-run `keyword_targets` at session start on that site. See also [[reference_site_status_tool]].
