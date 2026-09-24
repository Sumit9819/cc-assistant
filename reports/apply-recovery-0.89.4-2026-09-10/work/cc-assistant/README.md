# CC Assistant

WordPress plugin that connects your site to Claude Code via MCP (Model Context Protocol) for content analysis, drafting and reviewed publication on Elementor sites.

**Version 0.87.0 adds verified workflow results, runtime plugin capability inspection, consistent audit guidance and an MCP schema fix. Claude prepares content; the administrator reviews publication. See [RELIABILITY.md](RELIABILITY.md), [CONTENT-STRATEGY.md](CONTENT-STRATEGY.md), [EVIDENCE.md](EVIDENCE.md) and [CONNECTION.md](CONNECTION.md).**

---

## What it does

### Core safety
- **Pending Changes inbox** — Claude proposes edits, you review and approve. Live site never touches uncommitted text.
- **Snapshots** — pre-write capture of post content + meta + Elementor data. New snapshots include post fields and taxonomy; historical snapshots have limited recovery data.
- **Atomic apply** — concurrent Approve clicks can't double-write or double-record outcomes.
- **Auto-cleanup on post delete** — orphaned pendings are auto-rejected, link-graph + cluster references purged.

### Editorial workflow gates
- **Outline-first for big rewrites** — any body change >2,000 chars requires an approved `rewrite_outline` (H2-by-H2 plan with keep/cut/merge/add markers) within 7 days. Surgical edits skip the gate.
- **Pre-flight bundler** — `prepare_rewrite_brief` returns dossier + structure + competitor brief + cannibalization + accessibility + style guide + CTR diagnostic + 11-point checklist in one call.
- **Self-audit** — `verify_change(pending_id)` returns lint report + structure diff + sibling pendings + verdict the model can quote.
- **Dry-run mode** — every draft tool accepts `dry_run=true` to preview lint without queueing.

### Content quality (lint at queue time)
22+ quality signals including em dashes, AI-tell phrases, banned phrases, paragraph length (≤3 sentences), sentence length (≤25 words), Flesch-Kincaid reading level, HTML cruft, jargon density, bulk-add ratio, deletion ratio, bigram redundancy with kept text, shortcode preservation, image preservation, authority-citation preservation, internal-link preservation, phone-number preservation, headings (one H1, no skipped levels, depth, empty), image alt, meta description length, authority sources, blocked competitor citations, freshness, author bio, schema presence, source density, featured-snippet shape, style-guide compliance. Hard violations (em dashes, AI-tells, banned phrases) refuse the queue unless explicitly overridden.

### Outcome scoring
- **`success_metrics` per change** — `target_query`, `target_position`, `target_ctr`, `eval_window_days`, `hypothesis`. Optional but strongly recommended.
- **Verdict against intent** — outcomes scored 14+ days post-apply: hit / partial / missed / no_data. Includes a **meta-tag bottleneck flag** that fires when position landed but CTR didn't (the canonical "body worked, snippet is the limiter" diagnosis with a concrete recommendation to propose a meta-tag rewrite).
- **"What's working" dashboard card** — 30-day rollup of every measured edit against its targets.
- **Daily admin notices** — once an edit finishes its eval window, the verdict notifier posts a sharper notice with the position + CTR delta and bottleneck callout.

### Insights & analysis
- **Search Console integration** — sync, anomalies, intent breakdown, independent search-appearance observations, low-CTR candidates, page-1 opportunities, query overlap and decay tracking. These observations do not establish AI causation.
- **Topic Clusters** — pillar + supporting page model with health score and cluster-aware GSC overlay.
- **Internal link graph** — orphan detection, click-depth BFS, anchor-text suggestions.
- **Weekly advisor** — synthesises 7 signal sources into a ranked top-N "what to do this week" with dismiss + decay learning.
- **Niche-led blog planner** — service/pillar sources, reader questions and useful tasks produce proposals even with no GSC gaps. Includes existing-content candidates, evidence requirements and explicit scope/demand limitations.
- **Schema generator** — Article/HowToArticle + FAQPage + BreadcrumbList JSON-LD with FAQ-content validation (refuses schema for items not actually visible on the page).
- **Editorial calendar** — month grid of published, scheduled, and refresh-suggested posts.
- **AI bot tracking** — opt-in logger for GPTBot, ClaudeBot, PerplexityBot, Google-Extended, etc.

### Operational hygiene
- **Structured site memory** — Rules / Decisions / Sessions sections; Rules and Decisions persist across sessions, Sessions is a timestamped append-only log. Permanent conventions stop getting evicted by daily logs.
- **Post-drift detection** — if a post is edited outside the plugin since the last applied edit, dossier and `whoami` flag the staleness so the model re-reads.
- **Rate-limit + duplicate-detection** — queue endpoint warns when ≥10 changes are queued on one post in the last hour, or when a payload is byte-identical to an existing pending row.
- **Reject-feedback loop** — `whoami.session_recap.recent_rejected` returns the last 3 rejected pendings WITH the reviewer's note, so future sessions don't repeat patterns the human pushed back on.

## Content planning and review

Use `content_workflow` for a complete blog, refresh or site-review task, then execute its tool steps. `plan_blog_content` initializes an inferred scope automatically; Claude uses `discover_content_scope` and `manage_content_scope` to maintain it from existing pages and site notes. **CC Assistant → Content Strategy** shows the result for review. No manual strategy form or post-ID entry is required. Research and decisions distinguish observations from interpretations.

Content publication and proposed edits use review workflows. Some existing tools perform immediate organizational or asset actions; their individual tool descriptions identify those side effects. Research tools save bounded metadata, not live content changes.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Elementor (recommended; the plugin works without it but widget-level edit tools are limited)
- A dedicated CC Assistant Operator account with Application Passwords enabled (recommended); a separate administrator reviews changes

## Install

1. Drop the `cc-assistant` folder into `wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Open **CC Assistant → Setup** and follow the 4-step wizard.

## Connecting Claude Code (MCP)

1. **CC Assistant → Settings → Connection** shows a pre-filled `.mcp.json`. Replace its username with your dedicated CC Assistant Operator username.
2. Generate an Application Password under **Users → Profile → Application Passwords**.
3. Paste the generated password into the JSON, save the file at the root of the folder you'll open in Claude Code.
4. Open Claude Code in that folder and ask: *"Use whoami to confirm the site."* The fingerprint in the response should match the one shown on the dashboard.

## Connecting Search Console

**Settings → Search Console** has the full step-by-step:

1. Create a Google Cloud project + OAuth client (Web application).
2. Add your site's redirect URI (shown on the page, copy-button).
3. Paste Client ID + Secret.
4. Click **Connect with Google**.
5. Pick your verified property + click **Sync now**.

Daily sync runs via WP-Cron after the first manual sync.

**Local sites:** Google rejects `.local` and `.test` redirect URIs. Use Local's "Live Link" tunnel or ngrok, then paste the tunnel URL into the **Redirect URI override** field.

## Pre-flight checklist before going live

- [ ] Backup the database before activating.
- [ ] Test in a staging clone for 24-48 hours first.
- [ ] Review `wp-content/debug.log` for errors after the first cron cycle.
- [ ] Visit **Settings → Health** to confirm cron jobs are scheduled and no errors recorded.
- [ ] Enable AI bot tracking only after 1 week of stable admin-only use.
- [ ] Ensure your host allows loopback HTTP requests (so the rendered-HTML cache can warm).
- [ ] If `DISABLE_WP_CRON` is set, configure system cron to hit `wp-cron.php` every 5 minutes.

## Performance design

- **Front-end:** the plugin loads NOTHING on public pages unless AI bot tracking is on. With it on, one autoloaded boolean read + one user-agent regex per request.
- **Admin:** 3-tier lazy load. A non-CC admin page (Plugins, Tools, Posts list) loads only 3 plugin classes. Heavy classes (GSC, snapshots, internal links) load via `current_screen` only on screens that need them.
- **Crons:** all heavy work (cannibalization aggregation, refresh queue, weekly advisor synthesis, rendered-HTML fetching) is cron-driven and cache-backed. Cache misses on user-facing routes return placeholder + schedule async work.
- **HTTP fetches:** background integrations, explicit page audits and bounded competitor research may make outbound requests. Content research fetches at most three explicit URLs with time/response limits and a six-hour cache.

## Uninstalling

Delete the plugin from **Plugins → Installed Plugins** and click "Delete" on the plugin row. WordPress will run `uninstall.php` which:

- Drops all 10 plugin-owned database tables
- Deletes every option with the `cc_assistant_` prefix
- Deletes every transient with the `cc_` or `cc_assistant_` prefix
- Clears all scheduled cron events
- Removes `_cc_assistant_schema_jsonld` post meta from all posts
- Flushes the object cache

**Nothing is left behind.**

## Architecture

- `bin/mcp-server.php` — stdio JSON-RPC 2.0 server invoked by Claude Code. Translates MCP tool calls into REST requests against this site.
- `includes/class-rest-*.php` — REST endpoints under `/wp-json/cc-assistant/v1/*`. Application-Password authenticated, `manage_options` capability gated.
- `includes/class-pending-changes.php` — queue + `cc_pending_changes` table.
- `includes/class-snapshots.php` — `cc_snapshots` table + restore helper.
- `includes/class-apply.php` — applies an approved pending change (snapshots first, then writes).
- `includes/class-pre-publish.php` — 17 quality checks; rendered-HTML truth source.
- `includes/class-gsc.php` — Search Console OAuth + sync + aggregations.
- `includes/class-topic-clusters.php` — pillar/supporting cluster data + cluster GSC overlay.
- `includes/class-weekly-advisor.php` — synthesised priority list.
- `includes/class-seo-tools.php` — cannibalization, image audit, refresh queue, click depth, brief generator, accessibility audit, keyword research.
- `includes/class-schema-generator.php` — JSON-LD generator.
- `includes/class-internal-links.php` — link graph builder.
- `includes/class-llm-tracker.php` — AI bot crawler logger.
- `includes/class-error-log.php` + `class-admin-notices.php` — defensive cron wrapper + Settings → Health.
- `admin/views/*.php` — Dashboard, Pending, Topic Clusters, Check Up, Snapshots, Calendar, Brief, AI bot activity, Settings, Onboarding.

## Hard rules (enforced server-side)

- Never modifies live content directly. All edits go through Pending Changes.
- Never touches files outside this plugin's folder. No theme, core, or other-plugin writes.
- Plugin-scoped DB writes only.
- Citation rules: `.gov` and `.edu` always allowed; configured authority list allowed; competitor domains blocked.
- SEO meta auto-routes by detected plugin (Yoast / Rank Math / AIOSEO) — no orphan meta keys.

## The body-rewrite workflow

The canonical path for rewriting an existing post. Every step below is a single MCP tool call from Claude Code.

1. **`prepare_rewrite_brief(post_id)`** — pulls everything you need to plan: dossier, structure, brief, competitor brief, cannibalization filtered to this post, accessibility audit, image audit, full style guide, CTR diagnostic, 11-point checklist. The CTR diagnostic in particular flags whether the page's CTR is below the expected curve for its rank — that's a meta-tag problem and tells you to propose a meta change *alongside* (not instead of) the body work.

2. **`propose_rewrite_outline(post_id, outline)`** — H2-by-H2 plan with keep/cut/merge/add/rewrite markers. Lands in the inbox as a single review unit. Required for any rewrite >2,000 char delta. The reviewer signs off on the plan before any HTML gets written.

3. **`draft_update_post_content(post_id, content, success_metrics)`** — body rewrite. Runs the full content lint at queue time. Hard violations refuse. Soft violations surface on the inbox card. Always include `success_metrics` so future-you can see whether the change worked.

4. **`draft_update_seo_meta(post_id, logical_key, value, success_metrics)`** — when the CTR diagnostic flagged a meta-tag bottleneck. Auto-routes to Yoast / Rank Math / AIOSEO. Length-windowed lint per field.

5. **`verify_change(pending_id)`** — self-audit. Surfaces lint, structure diff, siblings, verdict. Run this before asking the human to approve.

6. *Wait 14+ days, then* the verdict notifier cron posts an admin notice with the score against your stated `target_position` and `target_ctr`, including a **meta-tag bottleneck callout** if position moved but CTR didn't.

## Versioning

Plugin: 0.5.1 · DB schema: 0.4.0

The DB schema version is checked on every `admin_init` and only migrates when out of date. v0.4.0 added `lint_report` and `success_metrics` columns to `cc_pending_changes` (idempotent ALTER on existing installs; new installs get them in the initial schema).

## License

GPL-2.0-or-later. Same license as WordPress core.
