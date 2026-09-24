# Working with this WordPress site

This directory is a WordPress install connected to Claude Code via the **cc-assistant** MCP server (`wp-content/plugins/cc-assistant/bin/mcp-server.php`). All content work goes through it — never edit `post_content` directly via SQL or the WP REST API outside the plugin.

## Session protocol

**At session start:** call `mcp__{SERVER_NAME}__whoami` first. Its `session_recap` field is your memory across restarts: `working_state` (historical context; resume only when it matches the current user request, and preserve explicit constraints), `pending_count` + `recent_pending`, `last_snapshot`, `notes_tail`, and the ordered `preflight`.

**Read `operator_brain` and `relevant_rules` in whoami.** Check site identity and bridge version; restart MCP after an update. Review knowledge scope before syncing. Prefer operator_brain_push(paths=[exact site-relevant paths]) to merge selected files while preserving remote files. Never indiscriminately upload unrelated clients or credentials. Read selected content before replacing local knowledge; full-brain equality is not required for a deliberately curated site snapshot.

**Verify with `render_probe`, not curl.** Before AND after any edit that affects rendered output, call `render_probe(id)` — it loopback-fetches the page's own front end (reliable even behind an edge WAF).

**Before stopping:** call `update_site_memory_notes` with one short, terse line about what changed this session.

## Hard rules (also enforced server-side)

- **Draft-only writes.** Never modify live content. All edits go through the Pending Changes inbox and require human approval.
- **Plugin-scoped only.** Don't touch theme files, other plugins, core, or uploads.
- **SEO meta routing.** Use `draft_update_seo_meta` with a logical key (`description`, `title`), not `draft_update_postmeta` with a hardcoded key.
- **Attention flow.** Call `attention_spec(post_id)` BEFORE composing a page, `attention_audit(id)` AFTER — fix major flags before queueing.
- **Never guess Elementor setting keys.** Call `widget_schema(widget_type)` — a typo'd key is a silent no-op.

## New blogs and content decisions

For a complete new-blog, refresh or site-review request, use `content_workflow` and execute its steps; do not stop at a plan. Claude owns strategy setup: inspect `discover_content_scope`, `get_content_scope` and existing `get_site_memory`, then save supported selections, audience and reader questions with `manage_content_scope`. Do not ask the operator to fill forms or supply post IDs the tools can discover. `plan_blog_content` initializes an inferred scope when none exists and proposes niche topics even without GSC gaps. Preserve operator constraints, inspect overlap and support the useful contribution. Return actual draft/pending IDs and preview links for review. A connected Claude session performs these actions; the plugin does not launch an unattended model runner.

Read `memory_consistency` for outdated guidance to reconcile. Routine Sessions logs do not invalidate strategy; promote new lasting constraints to Rules or Decisions. Heuristic scores, citation quotas and low CTR do not prove a defect or ranking effect.

Use `content_research(topic, anchor_post_id, reader_goal, proposed_contribution, competitor_urls, primary_source_urls)` before a proposed article exists, or `content_research(post_id, competitor_urls)` for actual comparable page observations and `content_decision` before refresh/consolidation proposals. Keep observations, hypotheses and actions separate. Similarity or low clicks alone never justify merging or removal. Read `content_strategy_policy` from whoami.

## When in doubt

`get_site_memory` returns the full notes blob plus the auto-detected stack.

## The Four Principles

1. **Think Before Coding** — state assumptions explicitly; present interpretations instead of picking silently; push back when a simpler approach exists; stop and ask when confused.
2. **Simplicity First** — minimum code that solves the problem; no speculative features, abstractions, or configurability; if 200 lines could be 50, rewrite.
3. **Surgical Changes** — touch only what you must; match existing style; clean up only orphans YOUR change created; mention unrelated dead code, don't delete it.
4. **Goal-Driven Execution** — define success criteria before working; state a step → verify plan; loop until verified.
