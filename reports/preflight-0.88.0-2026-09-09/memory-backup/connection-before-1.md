# Working with this WordPress site

This directory is a WordPress install connected to Claude Code via the **cc-assistant** MCP server (`wp-content/plugins/cc-assistant/bin/mcp-server.php`). All content work goes through it — never edit `post_content` directly via SQL or the WP REST API outside the plugin.

## Session protocol

**At session start:** call `mcp__cc-assistant-plugintesting__whoami` first. Its `session_recap` field is your memory across restarts — it contains:
- `working_state`: historical job context. Resume only when it matches the current user request; preserve explicit decisions and constraints and record actual progress.
- `pending_count` and `recent_pending` (last 5 queued changes with their status)
- `last_snapshot` (most recent rollback point)
- `notes_tail` (last ~500 chars of the site memory log)
- `preflight`: whoami and memory_consistency, relevant site rules, current page evidence and plugin capabilities, then pending-work checks. Use content_workflow for content jobs and verify actual results.

Read the recap before proposing anything. If a recent_pending entry overlaps with what the user is now asking, acknowledge it instead of duplicating.

**Operator Brain.** Read version drift and relevant rules in whoami. Restart MCP after bridge updates. Review file scope before syncing: this site receives only relevant curated knowledge; do not indiscriminately push unrelated client memories or credentials to all sites. A shared brain snapshot is not proof that every local file should be uploaded.

**Enforcement is mechanical, not advisory (v0.81).** The plugin refuses any write until whoami ran on the site within 8 hours (409). The project hooks in `.claude/hooks/cc_gate.py` deny a mutating tool until whoami ran in THIS chat, deny a render-affecting edit until `render_probe` or `page_facts` ran on that post in this chat, block the end of a turn while a mutated site's notes are more than 20 minutes behind, and push the operator brain when memory or skills changed. A Sessions note is refused while `working_state` is active with open loops unless you close them or pass `acknowledge_open_loops`. When a refusal arrives, do what it names; do not look for a way around it.

**Verify with `render_probe`, not curl (v0.44).** Before AND after any edit that affects rendered output (schema, alt text, breadcrumb, headings), call `mcp__cc-assistant-plugintesting__render_probe(id)`. It loopback-fetches the page's own front end and reads the LIVE DOM — the reliable verify path when an edge WAF (e.g. SiteGround) 403s external curl/WebFetch. It attributes every JSON-LD block to its emitter (Rank Math / hand-built snippet / cc-assistant) and flags self-serving `aggregateRating`, duplicate/broken breadcrumbs, and truly-empty links (crediting `img[alt]`). Mutating tools attach a `session_not_bootstrapped` reminder if you skipped `whoami` this session.

**Before stopping:** call `mcp__cc-assistant-plugintesting__update_site_memory_notes` with one short line about what changed in this session (default mode is append, timestamped). Examples:
- `"Proposed 8 em-dash fixes on post 3680 (FAQ + body widgets). Yoast meta trim queued."`
- `"User confirmed style guide bans em dashes; en dashes in number ranges are fine."`
- `"Discovered Rank Math is active here, not Yoast — site_memory auto-detect was right."`

Keep notes terse. Only the last ~500 chars surface in `session_recap`, so a wall of text crowds out older context.

## When the site is unreachable (SiteGround IP challenge)

If a tool fails with `siteground_ip_challenge`, or with `json_parse_error` whose
body contains `sgcaptcha`, SiteGround has flagged this machine's IP reputation.
It hits **every site on their platform at once**, including sites this machine
never contacted, so it is not your request volume and not a local config problem.

**Do not** change the User-Agent (already correct since v0.35.3) and do not go
looking through `.mcp.json` or switch folders. Confirm in one command:

```
curl -s -o /dev/null -w "%{http_code}" https://ANY-SG-SITE/wp-json/    # 202 = challenged
```

Real fixes: change the egress IP (hotspot or VPN) and restart the MCP server, or
ask SiteGround support to clear the reputation flag.

**Meanwhile, work continues over the browser transport.** A real Chrome passes the
challenge and a signed-in wp-admin page carries `wpApiSettings.nonce`, which
reaches the SAME plugin REST routes the bridge uses, so lint, the session gate,
the race-safety guard and the Pending Changes approval queue all still apply:

```
node D:/cc-assistant/tools/cc-via-browser.mjs https://SITE --batch calls.json
```

It runs HEADLESS: no window, about 9 seconds a batch. If you are launching a
visible browser to read or write site data, you are using the wrong tool. The
wp-admin login is PER SITE though, and `no-nonce` means "not signed in to that
site", not a permissions problem. Fix that once, then stop it (it holds the Chrome
profile lock) before running cc-via-browser:

```
node D:/cc-assistant/tools/open-chrome.mjs https://SITE/wp-admin/ 12
```

Both namespaces work: `cc-assistant/v1/...` for plugin routes, `wp/v2/...` for
reading post bodies. While the challenge is up `brain-sync.php push` fails on all
nine sites too, so the brain stored ON the sites is stale and
`operator_brain_status` will mislead: trust `D:\cc-assistant\memory`, and do NOT
pull the brain from a site, which would overwrite newer local memory.

Route names are not tool names: `upload_media` is `/assets/upload`,
`draft_patch_post_content` is `/draft/post-content-patch`. Use batch mode, since
each launch spends ~20s clearing the challenge. Replaying the browser's clearance
cookie from curl does NOT work; it is bound to the browser. Never drive wp-admin
directly to get around the queue.

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
