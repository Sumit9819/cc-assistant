---
name: reference-cc-assistant-v0-14-bootstrap
description: cc-assistant v0.14.0 ships a session-bootstrap pack — whoami now returns a 2-day activity log + 2026 SEO playbook so a fresh chat has full context before any action
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

`whoami` is the mandatory first call on every new session — the description literally says "MANDATORY FIRST CALL". It now returns a richer `session_recap`:

- `rules` — permanent site conventions (Rules section of site memory, full)
- `decisions_tail` — Decisions section tail (also persistent)
- `notes_tail` — last ~500 chars of Sessions section
- `recent_pending` — last 5 queued items
- `recent_rejected` — last 3 rejected with reviewer notes
- `activity_log_48h` — **NEW in 0.14**. Structured ticker of every edit_applied / edit_rejected / note_added / rule_added / audit_run / drift_detected in the trailing 48 hours. Pruned daily by `cc_assistant_activity_log_prune` cron. Lives in `wp_cc_activity_log` table.
- `activity_summary` — **NEW**. `{type: count}` for the 48h window — quick "this site has been busy" signal.
- `seo_playbook.top_rules` — **NEW**. 12 condensed 2026 SEO rules bundled into the plugin so the model doesn't regress to stale advice. Full long-form via `seo_playbook` MCP tool.
- `bootstrap_hint` — narrative line telling the model to read the recap before proposing changes.

**Retention:** activity log auto-prunes at 2 days. Anything older is in `cc_edits` + snapshots.

**Performance:** single indexed table, write happens inside the apply/reject path (post-commit), read is one SELECT with WHERE on the `ts` index, LIMIT 30.

**Token economy:** activity rows are 500-char-capped summaries. SEO playbook in the recap is just 12 one-liners. Full playbook only loads if `seo_playbook` MCP tool is called explicitly.

**Versioning:** `CC_Assistant_SEO_Playbook::VERSION = '2026.05'`. Bump when search algorithm landscape moves.
