---
name: reference-gsc-retention-storage
description: wp_cc_gsc_queries is the top DB-size offender; v0.27.3 introduced tiered impression-floor pruning + 250 MB hard cap + Database Health admin view
metadata: 
  node_type: memory
  type: reference
  originSessionId: 27670b52-e373-45d1-89f0-41bffe52a0e6
---

`wp_cc_gsc_queries` is the cc-assistant table most likely to push a site past a 1 GB DB cap (real incident on ER of Irving, 2026-05-19). Cardinality = unique pages × unique queries × days × ~4 search_appearance variants, so a medical site with thousands of long-tail queries fills the table fast — ~70% of rows by count are single-impression long-tail noise no tool ever reads.

**v0.27.4 self-heal** — `ensure_table()` recreates `wp_cc_gsc_queries` via dbDelta if dropped (called from `cron_sync()` + `queue_backfill()`). DROP TABLE is now safe; previously only TRUNCATE was, because the activator only runs on plugin (re)activation. Pattern matches `cc_topic_clusters::ensure_tables()` and `cc_cannibalization_trends::ensure_table()`.

**v0.27.3 maintenance subsystem** ([class-gsc.php](wp-content/plugins/cc-assistant/includes/class-gsc.php)) — `maintain()` runs at the end of every daily `cc_assistant_gsc_sync` cron, in this order:

1. `DELETE WHERE impressions = 1 AND date < NOW - 14d` (IMP1_RETENTION_DAYS)
2. `DELETE WHERE impressions <= 2 AND date < NOW - 30d` (IMP2_RETENTION_DAYS)
3. `DELETE WHERE date < NOW - 60d` (RETENTION_DAYS)
4. Hard ceiling: if `data_length+index_length > 250 MB` (MAX_TABLE_MB), walk cutoff inward 7d at a time (max 8 iter) until under cap

Outcomes logged to `cc_activity_log` (type=`db_maintenance`) and `cc_assistant_gsc_last_maintain` option. Admin → CC Assistant → **Database health** shows live sizes of all `wp_cc_*` tables + last maintenance stats + "Run maintenance now" button.

**Retention floor rationale:** [class-gsc.php:1237](wp-content/plugins/cc-assistant/includes/class-gsc.php#L1237) `resolve_windows()` hard-clamps to 30 days. Every trend / anomaly / CTR tool compares current-30 vs previous-30, so **60 days is the absolute storage floor** for the full feature set. v0.27.3 dropped `RETENTION_DAYS` from 120 → 60 alongside the tiered noise filters.

**One-time disk reclaim** (DELETE doesn't shrink the .ibd; OPTIMIZE TABLE is mandatory to release pages to host):
```sql
OPTIMIZE TABLE wp_cc_gsc_queries;
```
Or just `TRUNCATE TABLE wp_cc_gsc_queries;` — daily cron repopulates 16d, Settings → "Run backfill" pulls 90d from GSC API.

**Other unbounded tables worth watching** (next-priority retention work):
- `cc_snapshots` — no pruning, full Elementor LONGTEXT per snapshot (100–500 KB each on service pages)
- `cc_pending_changes` — applied/rejected rows never deleted, hold LONGTEXT current+proposed diffs

Both surface in the new Database Health view with risk indicators. See [[feedback_plugin_performance]].
