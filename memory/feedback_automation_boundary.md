---
name: CC Assistant automation boundary
description: What automation is allowed in the cc-assistant plugin and what is explicitly off-limits
type: feedback
---
The user wants the plugin to do as much as possible **without** invoking Claude Code — pre-compute insights, surface dashboards, run nightly cron jobs, deterministic SQL — so Claude is reserved for prose generation and judgment.

**Explicitly off-limits (asked for and declined as of 2026-04-28):**
- "Less-Claude Operational Layer" idea: auto-queueing deterministic pending changes (e.g. auto-proposing alt text fixes, auto-rewriting weak titles via templates, auto-queuing meta descriptions). The user does not want the plugin generating pending changes on its own. They prefer Claude to remain the proposal author; the plugin's job is data + insights + audit + linking graph, not change generation.

**Why:** Pending changes feel like a deliberate human/Claude decision in this user's workflow. Templates that auto-queue undermine trust in the inbox. Bigger automation should still surface as data and let Claude or the user decide.

**How to apply:** Add new analytics, audits, dashboard cards, and read-only MCP tools freely. Do **not** add features that automatically write to `wp_cc_pending_changes` from cron or from rule engines. New write paths must be triggered by an explicit user action or by Claude calling a draft_* tool.
