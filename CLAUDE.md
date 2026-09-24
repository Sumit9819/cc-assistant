# Working with this WordPress site

This folder is the cc-assistant operator workspace: plugin source + bridge (`wp-content/plugins/cc-assistant/`), standalone PHP (`php/`), the operator brain (`memory/`, `.claude/skills/`, `.claude/hooks/`) and `.mcp.json` for the live sites. No WordPress runs here; every MCP server talks to a live site over REST. All content work goes through the plugin — never edit `post_content` directly via SQL or the WP REST API outside it. See README.md for layout, release and new-machine steps.

## Session protocol

**At session start:** call `whoami` on the MCP server for the site the user selected first; check its returned site identity. Its `session_recap` field is your memory across restarts — it contains:
- `working_state`: historical job context. Resume only when it matches the current user request; preserve explicit decisions and constraints and record actual progress.
- `pending_count` and `recent_pending` (last 5 queued changes with their status)
- `last_snapshot` (most recent rollback point)
- `notes_tail` (last ~500 chars of the site memory log)
- `preflight`: whoami and memory_consistency, relevant site rules, current page evidence and plugin capabilities, then pending-work checks. Use content_workflow for content jobs and verify actual results.

Read the recap before proposing anything. If a recent_pending entry overlaps with what the user is now asking, acknowledge it instead of duplicating.

**Operator Brain.** Read version drift and relevant rules in whoami. Restart MCP after bridge updates. Review file scope before syncing: this site receives only relevant curated knowledge; do not indiscriminately push unrelated client memories or credentials to all sites. A shared brain snapshot is not proof that every local file should be uploaded.

**Evidence protocol (v0.84).** For published-page assessments and before editing, call `verified_page_audit(post_id)` on the selected site. Preserve its rule IDs, statuses, exact evidence, UTC capture time and hashes. Use its comparison when a result differs from a prior run. Unknown is not pass or fail. Review means context is needed. No overall “everything is correct” claim from partial checks. Server HTML does not prove JavaScript behaviour, visual appearance, Google indexing, content accuracy or field performance. Use browser evidence, Search Console and subject-matter review for those questions. After approval, recapture and verify the actual intended output; a saved setting alone does not prove the feature worked.

**Read capabilities before a plan.** Use `inspect_plugin_capability`, `list_installed_plugins`, `get_plugin_settings` and `widget_schema` for the actual installed site/version. Stored paths, discovered options and cached admin menus are partial observations, not an exhaustive list of supported features, accepted values or license entitlements. Report unavailable facts as unknown. For an unsupported plugin feature, consult its installed schema/UI or exact-version vendor documentation before proposing an implementation. Local SEO scores and suggested actions are heuristics, not Google scores or proven defects.

**Enforcement (v0.84).** WordPress now enforces fresh identity and page/option observations at the shared REST proposal queue for every client. Receipts belong to the authenticated WordPress user and Application Password/browser session, expire after ten minutes, and cannot be supplied by the model. Published targets require `verified_page_audit`; drafts/templates require full `get_post(slim=false)`; kit edits require `get_kit_settings`; option edits require `get_plugin_settings`. Failed newer reads invalidate earlier page proof. Approval checks the page state and plugin/theme/kit/SEO environment. Since 0.89.4, independent supported changes may continue across sibling approvals only when complete recovery snapshots and exact hashes prove every intervening approved write. Overlaps and unexplained changes still require a fresh plan. Check verify_change.evidence_check before rebuilding remaining proposals; compatible proposals keep their IDs. Only the tested 0.89.3-to-0.89.4 patch is environment-compatible when all other fingerprinted settings and versions remain identical. Elementor control checks cover nested children, imports, rebuilds, repeaters and publication, then repeat at approval and persistence. Human approval remains required. CC Assistant approvals and rollbacks share a database lock. These guards cannot control claims in model prose or third-party writers.

**Before stopping:** call `update_site_memory_notes` with one short line about what changed in this session (default mode is append, timestamped). Examples:
- `"Proposed 8 em-dash fixes on post 3680 (FAQ + body widgets). Yoast meta trim queued."`
- `"User confirmed style guide bans em dashes; en dashes in number ranges are fine."`
- `"Discovered Rank Math is active here, not Yoast — site_memory auto-detect was right."`

Keep notes terse. Only the last ~500 chars surface in `session_recap`, so a wall of text crowds out older context.

## When the site is unreachable (SiteGround challenge)

A `siteground_ip_challenge` response establishes that the particular request was challenged. It does not establish the cause, an IP-reputation diagnosis, or that every SiteGround site is blocked.

Use the selected site's configured MCP transport. Authenticated HTTP to erofwhiterock.com worked during the 2026-09-09 verification; this dated observation is not a permanent clearance guarantee. The plugin also supports integrated browser transport for the same authenticated REST routes. See `wp-content/plugins/cc-assistant/CONNECTION.md` for the actual setup and limitations. Do not assume an old ad-hoc browser script or wp-admin nonce is needed.

If the provider challenges the configured browser, complete its normal challenge or obtain a supported API-access arrangement from SiteGround. Restart MCP after bridge/configuration changes. Do not disable protection site-wide or work around the approval queue. A failed content fetch remains unverified, even if another API call succeeds.

## Hard rules (also enforced server-side)

- **Draft-only writes.** Never modify live content. All edits go through the Pending Changes inbox and require human approval.
- **Plugin-scoped only.** Don't touch theme files, other plugins, core, or uploads.
- **SEO meta routing.** Use `draft_update_seo_meta` with a logical key (`description`, `title`, etc.), not `draft_update_postmeta` with a hardcoded key — the plugin auto-routes to Yoast/Rank Math/AIOSEO.

## When in doubt

`get_site_memory` returns the full notes blob plus full auto-detected stack. Use it when `notes_tail` is truncated and you need older context.

## Weekly harvest ritual (per site, when doing SEO work)

Use warehouse observations when available to investigate query opportunities, CTR changes and page trends. Missing GSC gaps do not block new niche topics. Diagnose actual deficiencies before proposing changes; before/after associations do not prove causation. Do not automatically queue three changes, copy apparent wins or revert apparent regressions. Use current evidence and content_decision, then prepare the smallest justified draft for review.

## The Four Principles in Detail

### 1. Think Before Coding

*Don't assume. Don't hide confusion. Surface tradeoffs.*

LLMs often pick an interpretation silently and run with it. This principle forces explicit reasoning:

- **State assumptions explicitly** — If uncertain, ask rather than guess
- **Present multiple interpretations** — Don't pick silently when ambiguity exists
- **Push back when warranted** — If a simpler approach exists, say so
- **Stop when confused** — Name what's unclear and ask for clarification

### 2. Simplicity First

*Minimum code that solves the problem. Nothing speculative.*

Combat the tendency toward overengineering:

- No features beyond what was asked
- No abstractions for single-use code
- No "flexibility" or "configurability" that wasn't requested
- No error handling for impossible scenarios
- If 200 lines could be 50, rewrite it

**The test:** Would a senior engineer say this is overcomplicated? If yes, simplify.

### 3. Surgical Changes

*Touch only what you must. Clean up only your own mess.*

When editing existing code:

- Don't "improve" adjacent code, comments, or formatting
- Don't refactor things that aren't broken
- Match existing style, even if you'd do it differently
- If you notice unrelated dead code, mention it — don't delete it

When your changes create orphans:

- Remove imports/variables/functions that YOUR changes made unused
- Don't remove pre-existing dead code unless asked

**The test:** Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution

*Define success criteria. Loop until verified.*

Transform imperative tasks into verifiable goals:

| Instead of... | Transform to... |
| --- | --- |
| "Add validation" | "Write tests for invalid inputs, then make them pass" |
| "Fix the bug" | "Write a test that reproduces it, then make it pass" |
| "Refactor X" | "Ensure tests pass before and after" |

For multi-step tasks, state a brief plan:

```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let the LLM loop independently. Weak criteria ("make it work") require constant clarification.

## Current content guidance (0.88)

Read `memory/feedback_erofwhiterock_current_evidence_policy.md` when available and the current site Rules. Claude maintains strategy from source evidence; the operator reviews results. For a proposed article, content_research accepts topic, anchor_post_id, reader_goal and proposed_contribution, with competitor_urls and primary_source_urls. A prepared workflow is not a created article.
