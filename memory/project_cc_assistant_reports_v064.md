---
name: cc-assistant-reports-v064
description: "v0.64.0 Reports feature — why the measure section showed no data, what shipped, and the 4 measurement bugs still unfixed"
metadata: 
  node_type: memory
  type: project
  originSessionId: 21d22be7-f59b-4f03-94b7-0e1642640d1e
  modified: 2026-07-31T07:50:34.244Z
---

Operator (2026-07-31): "the measuring section is very bad, I dont get data from there, if I ever have to see report, individually or in sum, I wont get to see it properly. Also if I every need to export a report, there is no any option for that too."

**Diagnosis — it was a UI problem, not a pipeline problem.** Data existed all along: GSC connected, 6,881 WP rows, warehouse synced. Three real causes:
1. `page-performance.php` had **zero** GSC connection check (grep `is_connected` = no hits) — rendered 200 rows of "0 clicks / 0 impr / — position" so unconfigured looked identical to dead site. Dashboard gates correctly on `$gsc_ready`; Page Performance never did.
2. No aggregate view anywhere, and per-page drilldown showed only a query list (no history, no link to what we changed). Backend already computed `top_movers`/`top_decayed`/`query_count` in `compute_outcome` (class-edit-outcomes.php:551) then **discarded all three** in `recent_with_status` (:836).
3. Zero export in the entire plugin — confirmed no `fputcsv`/`Content-Disposition`/`text/csv` anywhere. Only download was the `.mcp.json` config Blob (settings.php:672).

Also: `wp_cc_lead_events` (Elementor Pro form submits, captured since v0.58) had **zero admin readers**.

**Shipped v0.64.0:** `includes/class-reports.php` + `admin/views/reports.php` + CSS block. Site totals vs prior period, per-page table, single-page report (its queries + our applied changes + leads), 4 CSV exports, honest empty states naming the actual cause (not connected / first sync / empty window / normal 2-3d GSC lag). Changes grouped **per page not per change**, because a 39-edit batch on one page used to flood the whole window. Reports gets a visible menu door above Page Performance (7 items, deliberate — see [[cc-assistant-ux-audit]] 6-item target).

**Adversarial review caught 3, all fixed:** CSV formula injection (titles starting `= + - @` execute in Excel — every cell now routes through `putrow`→`csv_cell`); `edit_posts` (Contributor tier!) gated site-wide analytics + full change-ledger export → raised to `edit_others_posts` via `self::CAP`; `?cc_post=N` went straight to `get_post()` with no read check → added `current_user_can('read_post')`. Page Performance deliberately LEFT at `edit_posts` and visible so Authors keep their only performance screen.

**Still unfixed (v0.65 candidates), all evidenced:**
- llm-activity "pages crawled" tile counts an array requested with `limit 30` — any site crawled on >30 URLs reports exactly 30 forever.
- checkup "Showing X of Y" prints `count($query->posts)` (page size) as if a site total; `found_posts` available and unused.
- checkup fails-only filter runs AFTER the 25-row page slice → "Nothing to flag" while later pages are full of failures.
- reindex-tracker compares site-local `_cc_gsc_reindexed_at` against GMT `applied_at` → queue never drains in any US timezone. Needs `current_time('mysql', true)` at class-admin.php:292 **plus a migration** for existing values.
- Outcome verdicts doubled at first read: `class-edit-outcomes.php:458` normalizes a 7-day after-window by `BASELINE_DAYS/measurable_days` = 2.0, so early verdicts show 2× real clicks and shrink as the window fills. Nothing marks them provisional; dashboard tooltip wrongly claims a flat 14-vs-14 comparison.

Related: [[cc-assistant-ux-audit]], [[reference-local-php]], [[reference-zip-packaging-gotcha]], [[feedback-zip-plugin-yourself]]
