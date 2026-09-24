---
name: cc-assistant-local-warehouse
description: Agreed direction (2026-07-29) — move full-fidelity GSC data to a desktop SQLite warehouse in the MCP bridge; WP keeps thin rollups; outcome engine + unique features build on it
metadata: 
  node_type: memory
  type: project
  originSessionId: 598fc17d-01d9-403a-b093-67c7c45a8e9b
  modified: 2026-07-30T05:39:36.263Z
---

User's idea (2026-07-29), endorsed: stop storing full GSC query data in the WP database (250MB cap + impression-floor pruning were losing the local long-tail). Instead the desktop MCP bridge (bin/mcp-server.php, which already runs on the operator's machine) maintains per-site SQLite files (~/.cc-assistant/warehouse/) with full daily (page, query) granularity, forever. WP keeps only 28d/90d rollups + top-N for the advisor UI and nightly cron audits — host DB usage goes DOWN.

Sync: WP exposes a paginated raw GSC export endpoint (reusing its existing GSC OAuth); bridge drains incrementally (GSC finalizes ~2-3 days back) at session start or via scheduled task. If warehouse is lost, GSC only backfills 16 months — back up the folder. Single-machine constraint acknowledged (fine for solo operator; Drive sync later if team grows).

STATUS 2026-07-29: **Step 1 SHIPPED as v0.54.0.** Files: bin/warehouse.php (new, cc_wh_* functions), bin/mcp-server.php (3 tools: gsc_warehouse_sync/query/status + display_errors=stderr + curl json_parse_error fix), includes/class-rest-warehouse.php (GET /gsc-export + /gsc-export/status), class-gsc.php raw_export_page(). Zip built at wp-content/cc-assistant.zip — REMOTE SITES STILL RUN 0.53.x until user deploys it; warehouse sync on them errors with site_plugin_outdated until then. .mcp.json: all 8 servers got "-d extension=sqlite3" (backup .mcp.json.bak-v054); MCP servers need restart to pick it up. sync_log has a `complete` column (0 = date truncated by 24-page cap). Fresh-eyes review found 7 failure-path bugs (SQLite3 warnings-not-exceptions root cause) — all fixed via enableExceptions(true) + Throwable catches + streaming page inserts (memory), verified by a 27-check smoke test.

v0.57.0 (2026-07-30): **Step 2 SHIPPED — outcome engine v2.** Bridge-side `outcome_report` tool (bin/warehouse.php, cc_wh_tool_outcome_report): pulls /edits, computes DiD per applied edit — page clicks in SYMMETRIC pre/post windows minus site-wide change; verdicts win/flat/regressed/low_data/too_early/no_url (homepage skipped — '/' would match all URLs); regressions sort first; overlapping edits flagged; 12-check hand-verified fixture green. Zero WP-side changes. ALSO v0.57: operator_kit + operator_kit_push_skills (site stores design skills + CLAUDE.md template + .mcp.json entry + new-machine steps — disaster recovery; skills NOT yet pushed to any site, do after deploy) + bootstrap/CLAUDE-md-template.md ships in zip. One zip = v0.54-0.57, deploy pending everywhere. Remaining laptop-only state to back up: .mcp.json (app passwords), ~/.claude/projects/.../memory/, warehouse dir.

Build order agreed as candidate roadmap (v0.54+):
1. Warehouse + sync + `gsc_warehouse_query` MCP tool (local, fast, no WAF)
2. Outcome engine v2: difference-in-differences per applied change (28d pre/post per affected URL/query, site-total as control) — structurally fixes [[get-edit-outcome-window-skew]]; rollup into whoami recap
3. Empirical per-site CTR curve → striking-distance ledger ranked by realistic click gain (impressions × CTR uplift), not generic CTR tables
4. Decay radar: rolling 90d vs prior 90d per page, name the queries that left, auto-generate refresh brief
5. Cross-site playbook miner: tag pending changes with a change-type taxonomy; promote patterns with ≥3 wins across sites into playbook suggestions
6. AEO tracker: weekly snapshot joining llm_crawls + gsc_ai_overview + Ubersuggest brand_visibility tools

Lightweight principles: SQLite only (PHP built-in), no new services/queues, sync piggybacks on sessions, WP stays off the FE path. Related: [[ubersuggest-official-mcp]] (keyword store idea folds into the same warehouse as an external_keywords table).
