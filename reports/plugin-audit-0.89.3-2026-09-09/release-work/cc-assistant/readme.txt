=== CC Assistant ===
Contributors: sumit
Tags: claude, ai, content, elementor, mcp
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.89.3
License: GPLv2 or later

Connects WordPress to Claude Code via MCP for safe content analysis, optimization, and drafting on Elementor sites.

== Description ==

CC Assistant lets Claude Code read your WordPress content, propose changes, and queue them for your approval. Nothing is published until you say so.

**What it does**

* Exposes a local MCP server that Claude Code connects to
* Lets Claude read posts, pages, and Elementor widget trees
* Queues every proposed change in a Pending Changes inbox for your review
* Captures required recovery snapshots before applying queued post changes
* Enforces source citation rules (.gov, .edu, your authority list only)

**Hard rules built in**

* Publishing and queued content changes require a human administrator review session.
* Optional operator-brain synchronization writes local memory, skills and bridge files; review trusted sources before pulling.
* Never cites disallowed domains.
* Sees only the post types you allow.

== Installation ==

1. Place this plugin folder in `wp-content/plugins/`.
2. Activate it from the Plugins screen in wp-admin.
3. Open the CC Assistant menu and follow the connection steps on the dashboard.

**Connecting Claude Code**

1. Create an Application Password at Users > Profile, named "Claude Code". Copy it.
2. Open the project root folder (the one containing wp-content) in Claude Code.
3. Copy `wp-content/plugins/cc-assistant/.mcp.json.template` into the project root, rename it to `.mcp.json`, and fill in your URL, username, and application password.
4. In Claude Code, ask "who am I connected to?" to verify.

**Note on PHP path**

The `command` field in `.mcp.json` is set to `php`, which assumes PHP is in your shell PATH. On Windows with Local by Flywheel, if you do not have PHP in PATH, replace `php` with the full path to the PHP binary, for example:
`C:/Program Files (x86)/Local/resources/extraResources/lightning-services/php-8.1.9+0/bin/win64/php.exe`

== Frequently Asked Questions ==

= Does this auto-publish anything? =
Publication and ordinary live-content proposals require human review in the Pending Changes inbox. Draft creation, research records, site memory, media uploads and explicitly documented maintenance tools have direct side effects. Check each tool contract; a stored draft is not a published post.

= Can it edit theme or core files? =
It does not offer arbitrary theme or WordPress core file editing. Authorized tools can write post content, metadata, supported plugin settings, and uploaded media. The desktop MCP bridge can also update its own runtime and scoped project memory files.

= Does it use my Anthropic API key? =
It connects through your authenticated Claude Code configuration. Billing depends on that configuration. Optional automation uses the same native client; CLI budget figures are estimates and external provider usage is separate.

== Changelog ==

= 0.89.3 =
* Block generic publication/scheduling/trashing status updates that bypass dedicated reviewed handlers.
* Prevent concurrent proposals from superseding newer or already-claimed work; recheck evidence after slow queue preparation.
* Require complete publication evidence and matching intent during workflow verification; preserve consistent bindings after uncertain refresh writes.
* Carry source/strategy checks into initial workflow-backed publication proposals and expose the actual publication gate in pre_publish_check.
* Resolve saved Elementor widget types automatically rather than returning an unrelated registry listing.
* Make accessibility form checks passive; distribute and syntax-check all accessibility runtime assets during bridge updates.
* Verify HTTPS certificates in older audit fetches; refuse redirects and CAPTCHA responses in the rendered-content warm cache.
* Fix opt-in uninstall option-prefix matching, page-facts table cleanup and scheduled tasks with arguments.
* Permit the dedicated operator role to read review health. Correct documentation of direct tool side effects.

= 0.89.2 =
* Workflow verification now rejects stale publication fingerprints and superseded proposals instead of reporting those records ready.
* Added refresh_publish_proposal for an existing workflow-bound blog draft: fresh actor-scoped observations, current source workflow, publication gate, preserved draft/author/pending ID and a recorded refresh history.
* Revalidate refreshed publication sources and strategy at approval; recovery never publishes content or creates a duplicate draft.
* Added conflict, storage-failure, identity, dry-run and no-publication regression coverage.

= 0.89.1 =
* Fixed bulk approval invalidating independent Elementor widget proposals on the same page after its first successful edit.
* Batch evidence is limited to selected, initially current proposals and exact predicted saved changes; overlapping widgets, altered proposals, external edits and environment changes remain blocked.
* Preserved per-change recovery snapshots and clarified that partial approval keeps successful edits saved.
* Excluded the generated hero-image preload cache from content fingerprints, like other generated Elementor caches.
* Added regression coverage for sibling approvals, conflicts, partial failure, selection boundaries and recovery failures.

= 0.89.0 =
* Added existing public-author discovery, saved defaults and author validation for new posts/pages; removed the silent automation-account fallback.
* Added scoped, dated Ubersuggest observation records and links to content research; missing metrics, stale estimates and unverified competitor candidates remain explicit.
* Added a reviewed SiteGround frontend adapter using native setting/cache-purge behavior, pending review, source checks and conflict-aware rollback.
* Fixed SiteGround inspection to read the current runtime toggle registry.
* Added shared-brain expected-fingerprint checks, database locking, corruption detection and storage-failure reporting.
* Added an optional budgeted local Claude runner with saved job phases, exact tool allowlists, one draft attempt and read-only recovery after uncertain writes. Scheduling is disabled until configured.
* Included the Python runner in verified bridge distribution, alongside the required browser worker and manifest.

= 0.88.0 =
* Added prospective topic research before an article exists, with a current niche anchor, proposed reader benefit, competitor/primary-source excerpts and explicit unknowns.
* Strategy freshness now uses durable Rules/Decisions instead of routine Sessions entries; legacy unstructured constraints remain included.
* Added memory-consistency review prompts to whoami and get_site_memory; reconciled stale task and score-driven bootstrap guidance.
* Improved discovery of utility, legal and translated context pages so they do not become automatic service anchors.
* Added selected-path operator-brain push: merges only reviewed files, preserves other remote files and verifies selected hashes.
* Added regression and Claude behavior cases for these workflows.

= 0.87.0 =
* Fixed an invalid MCP schema that could prevent Claude from loading the tool list.
* Added runtime plugin capability inspection and verification of actual workflow-bound drafts.
* Unified active capability summaries, tool descriptions and SEO guidance.
* Replaced legacy robustness scores and rewrite quotas with fresh evidence findings.
* Corrected low-CTR/AI measurement labels and legacy comparisons.
* Added PHP regressions and repeatable, isolated Claude behavior evaluations.


= 0.86.0 =
* Claude-managed content scope: discovery, current source citations, audience, exclusions and reader questions, without a setup form.
* Automatic inferred scope initialization on planning; existing strategies and omitted constraints are preserved.
* Current revision, source hashes and site-note context prevent stale metadata overwrites; ten previous profiles are retained.
* Added discover_content_scope, manage_content_scope and content_workflow MCP tools.
* Content Strategy is now a review screen. Connected Claude sessions execute the saved workflow using existing draft/review tools.
* Aligned session bootstrap guidance with the shared evidence policy; removed conflicting fixed citation and page-intent instructions there.

= 0.85.0 =
* Added niche-led blog planning independent of GSC gaps, a source-page scope profile and reader questions.
* Added bounded competitor evidence collection and actor-scoped persistent decision/history tools.
* Removed automatic cluster-size merge advice, unsupported originality scoring and causal AI/CTR claims from corrected decision paths.
* Preserved query/page GSC counts separately from search appearances, added migration provenance and impression-weighted aggregates.
* Unified short/full SEO policy and fixed removed-rule audit comparisons.
* See CONTENT-STRATEGY.md for tool scope, limits, update steps and remaining roadmap work.

= 0.84.0 =
* FIX: Link verification retains origin, scheme, query and fragment; empty metadata expectations and unresolved SEO templates remain inconclusive.
* NEW: Server-owned evidence receipts enforce fresh identity and target observations for REST proposals. Approval refuses changed page or plugin/theme/kit/SEO state.
* FIX: Shared Elementor validation covers nested children, imports, section replacements, repeaters, builds and publication; approval and final persistence validate again.
* FIX: Elementor saves check their stored value. Widget rollback restores the previous settings exactly, including removing newly introduced keys.
* FIX: Proposal database failures propagate as errors through all queue callers.
* NEW: MySQL/MariaDB named lock coordinates CC Assistant approvals and rollbacks on the same site.
* TEST: Standalone regressions and disposable WordPress/MySQL/Elementor integration checks, including approval, rollback and separate-connection locking.

= 0.83.0 =
* Add repeatable verified_page_audit with evidence, timestamps, rule IDs and comparison.
* Reject unusable page fetches and prevent stale/cached/incomplete verification claims.
* Block unsupported settings on the three supported Elementor draft routes.
* Add explicit capability provenance, schema validation and setting conflict/readback checks.
* Add freshness checks to the local Claude hook and label local SEO scores as heuristics.
* See EVIDENCE.md for coverage, limitations, client requirements and tests.


= 0.82.0 =
* Separate automation access from administrator review on both approval endpoints.
* Add an optional CC Assistant Operator role restricted to plugin REST routes.
* Persist UTC queue baselines, detect post/meta/taxonomy conflicts, and track applying/failure states.
* Stop writes when required recovery snapshots fail; capture full post fields and taxonomy.
* Implement missing post, redirect and asset-reference rollback paths with conflict checks.
* Preserve JSON escaping and numeric reviewer attribution.
* Harden external URL fetching, bridge file replacement, Google token encryption, and cron cleanup.
* Add opt-in browser transport for sites that challenge plain HTTP API clients.
* See CONNECTION.md for connection setup, migration details and limitations.


= 0.81.3 =
* The bridge now NAMES a SiteGround transport failure instead of reporting a bare
  "Could not parse response as JSON". An sgcaptcha body is reported as
  siteground_ip_challenge, with the offending IP, an explicit note that neither the
  User-Agent nor .mcp.json is the cause, and the browser-transport workaround. A
  SiteGround error page is reported separately as siteground_waf_block. Anything else
  still falls through, because over-claiming would misdirect just as badly.
* Covered by tests/bridge-transport-errors-test.php, which also asserts the classifier
  is wired into BOTH transports and runs BEFORE the json_parse_error return.
* bin/ only: no WordPress-side code changed, so the zip is only needed to carry the
  new bridge to the sites. MCP servers must be restarted for it to load locally.

= 0.81.2 =
* brain-sync / operator_brain_push over HTTPS from the standalone workspace PHP failed with "unable to get local issuer certificate": the workspace PHP ships no php.ini and therefore no CA bundle. The brain HTTP client now does what the bridge has done since v0.60.1: verify the peer and, when curl.cainfo is unset, use the Windows certificate store (CURLSSLOPT_NATIVE_CA). Local dev hosts keep verification off.

= 0.81.1 =
* Standalone operator workspace. The plugin source, bridge, brain and hooks now live in one folder (D:\cc-assistant) with no WordPress install around them; the bridge talks to the live sites only. The brain honours Claude Code's `autoMemoryDirectory` (project .claude/settings.local.json or user settings), so memory can live inside the workspace, and collects project-scoped skills under .claude/skills/. The harness Stop hook resolves the same memory folder.

= 0.81.0 =
* Session gate is HARD by default. Since v0.44 a mutating call without a recent whoami only attached a reminder, and a recorded session ignored that reminder on every write. Every write now returns 409 `session_not_bootstrapped` until whoami has run on the site within the last 8 hours (was a 30-minute soft window). The message says exactly what to call. Operator override: option `cc_assistant_bootstrap_hard_gate` = "0".
* Open-loop guard on session notes. A Sessions note is the "I am stopping" signal; while working_state is active with open_loops the note is refused (409 `open_loops_unacknowledged`, loops listed) until the loops are closed via update_working_state or the caller passes `acknowledge_open_loops=true`, in which case the loops are appended to the note so the next chat inherits them explicitly. Rules/Decisions writes and replace mode are unaffected.
* whoami `recent_pending` entries now carry `verification`: the post-apply verdicts already stored on the row (render-health critical/checked, rendered schema audit, duplication summary) compacted to one line per audit, so an applied change that broke the page is visible in the first call of the next session instead of only on the inbox card.
* Harness gate (Claude Code hooks, shipped in the project as .claude/hooks/cc_gate.py + .claude/settings.json and carried by the operator brain): PreToolUse denies any mutating cc-assistant tool until whoami ran on that site in THIS chat session, and denies a render-affecting edit until render_probe or page_facts ran on that post this session; Stop refuses to end the turn while a mutated site's Sessions note is more than 20 minutes behind, then pushes the operator brain to all sites when local memory or skills changed (at most every 10 minutes, never blocking on failure). The plugin gate is site-wide; the harness gate is per session. Both are needed and neither depends on the model remembering.
* Operator brain now includes project/.claude/settings.json and project/.claude/hooks/* so the harness enforcement restores with the rest of the brain on a new machine. settings.local.json stays personal and is never collected or written.

= 0.80.0 =
* NEW: Effective-value guard for Elementor writes. Elementor stores only what differs from a control's default and gates many controls behind another control's value, so a setting can be stored, visible in the editor, and render nothing. Two real cases on one hero: `boxed_width: 900` under `content_width: "full"` (never applied), and an overlay colour whose alpha was halved by the never-set `background_overlay_opacity` default of 0.5. Validation now runs on EFFECTIVE settings (new over stored over default) and replicates Elementor's own `condition` / `conditions` visibility rule.
* New queue warnings on `draft_update_elementor_widget`, `draft_add_elementor_widget` and `draft_add_elementor_container`: `inert_setting` (the written key is gated off; names the gate, its current value and the value it needs), `global_token_overrides_literal` (a literal written where the element binds a Kit global token is ignored by Elementor; says how to detach or retarget), `defaults_in_effect` (unset render-governing siblings in the sections being written, with the default that will render). Warn-only, like the v0.56 key/enum checks.
* Layout elements are covered. The v0.56 guard skipped anything without a `widgetType`, which is exactly where both incidents happened. Containers, sections and columns are now read from Elementor's elements manager, listed by `widget_schema`, and validated on every write.
* `widget_schema(widget_type, post_id, widget_id)` returns `element`: every control's stored vs effective value, whether it applies under its gate, and `inert_stored`, the values the editor shows that render nothing. "Is it set" and "does it apply" become one read instead of a guess.
* Tests: tests/schema-test.php extended with gate-off, gate-satisfied-in-same-write, overlay default, global-token override, complex `conditions` relation, and negative controls.

= 0.79.0 =
* NEW: Operator Brain. The site now stores the WHOLE operator knowledge base, not just two design skills: every Claude memory file for the project plus its index, every skill folder, the project CLAUDE.md, and a secrets-stripped .mcp.json template. Stored gzip-compressed in one autoload-off option with an uncompressed path -> sha1 index; the fingerprint is sha256 over the sorted index, computed identically on the laptop, so equal hashes mean identical files.
* NEW: the site also serves its own bridge (`GET /operator-kit/bridge`, the deployed bin/*.php with hashes). A laptop whose local MCP build is older than the site rebuilds it from the site with `operator_brain_pull(what=bridge)`; a brand-new laptop fetches it with one PowerShell line and needs no repository.
* NEW: whoami embeds `operator_brain` (in_sync / local_missing / local_newer / site_newer with the exact action and a file diff), `operator_brain.bridge` (does this chat's bin/ match what the site ships) and `relevant_rules` (memories flagged HARD/STRICT plus memories that mention this site, names + descriptions). The first call of every session now says whether this machine is behind and which rules apply, instead of relying on the model to remember.
* version_drift is two-way. It warned only when the SITE was older than the laptop; the opposite case, a stale laptop whose chat opens with tools missing, said nothing. It now names the fix. The Unknown-tool error says the same.
* Tools: `operator_brain_status`, `operator_brain_push` (index-diff, chunked, fingerprint-verified), `operator_brain_pull` (what=all|memory|skills|project|bridge, mode=missing_only|replace, never deletes). CLI `bin/brain-sync.php status|push|pull|bootstrap` fans out across every cc-assistant server in .mcp.json; `bootstrap` restores a fresh machine and writes .mcp.json from the template with this machine's PHP path.
* Brain paths are validated on both sides (relative, forward-slash, rooted in skills/ memory/ project/); anything else is refused because these files are written back to disk on other machines. The live .mcp.json is never written by a pull.
* Tests: tests/operator-brain-test.php pins the shared fingerprint formula, path validation, template secret scrubbing and rendering, diff/classification, relevant-rule selection, write modes, and the server store round trip.

= 0.65.0 =
* NEW: Rank Math schema is editable at last. Rank Math's modern Schema Builder keeps each node in a postmeta row named `rank_math_schema_Product` — a capitalised key holding a nested serialized array. Every existing write path in this plugin ran meta keys through WordPress's `sanitize_key()`, which lowercases them, and stored a flat string, so the array would have been destroyed. Two new tools do it properly: `get_rank_math_schema` reads every schema row on a post and flattens it into dot paths, and `draft_update_rank_math_schema` merges values into those paths.
* The merge is surgical. Only the paths you name change; every other leaf is carried through byte-identical, including Rank Math's own `%seo_title%`-style placeholders, which keep resolving per post instead of being frozen into literal text.
* Field guards that match what Google actually rejects: a price must be bare digits (no currency symbol, no thousands comma), `priceCurrency` a 3-letter ISO code, `availability` a real schema.org token, dates ISO 8601. Leaves can only be added to branches that already exist — the tool merges into schema Rank Math already renders, it does not invent structure.
* Writing to a branch is refused outright. Targeting `offers` when you meant `offers.price` is one keystroke away and would replace the entire Offer node with a string; worse, the reviewer's diff would have shown that as a harmless blank-to-value change. The error names the real leaves instead.
* Approving a schema change now purges the page cache. JSON-LD is rendered into the HTML, so the change is a front-end change — without the purge a SiteGround-cached page keeps serving the old markup and `render_probe`, which fetches through the same cache, reports the fix as failed. Reverts purge too.
* The queue warns when `rank_math_rich_snippet` is "off" on the target post: the merge will be correct in the database but render nothing until that master switch is restored.
* Drift protection: saving schema in Rank Math's own interface does not reliably bump `post_modified`, so the usual race guard is blind to it. Each queued change records a hash of the row it merged onto and refuses to apply if that row has since changed, rather than silently discarding a human's edit.
* Draft-only and fully revertible like every other change: the pre-merge array is snapshotted whole, the reviewer sees a per-path before/after diff, and revert is an exact restore. Queueing a second change to the same schema row supersedes the first, because each payload carries the complete array.
* Deliberately does not touch `rank_math_rich_snippet`. That legacy scalar is a master switch: setting it "off" suppresses Rank Math's entire JSON-LD graph — Organization, WebSite, BreadcrumbList and all — not just the node being edited.

= 0.64.0 =
* NEW: Reports. The plugin can finally answer "how are we doing" in one place — site totals for a chosen period (clicks, impressions, CTR, average position) each compared against the previous period of the same length, a per-page table, and a full single-page report combining that page's search queries with the list of changes we applied to it. Reports takes the visible menu door; the old Page Performance screen is now the "Rankings" tab beside it, so the menu count is unchanged.
* NEW: CSV export — the first export of any kind in this plugin. Four exports (site summary, all pages, changes made, and a single page's full report), each streamed with a UTF-8 BOM so Excel opens accented titles correctly. Every cell is neutralised against spreadsheet formula injection: a page title beginning with = + - or @ is written as text, not executed as a formula.
* NEW: form leads are visible at last. Elementor Pro submissions have been recorded per page since v0.58 but were never rendered anywhere in wp-admin; they now appear as a site total and per page.
* FIX: Page Performance rendered 200 rows of "0 clicks / 0 impressions / — position" whenever Search Console was unconnected, mid-sync, or outside the selected range — with no connection check anywhere on the screen, so a configuration gap was indistinguishable from a dead site. Both Reports and Page Performance now say which of those it is, and link to the fix.
* Reports never prints a bare zero: when a figure is missing it explains whether the cause is a missing connection, an unfinished first sync, an empty window, or normal Search Console reporting lag (which runs 2-3 days behind and makes very recent changes unmeasurable by definition).
* Changes are grouped per page rather than listed per change, so a large batch on one page can no longer push every other page out of the visible window.

= 0.63.0 =
* Review Deck: approve/reject WITHOUT page reloads. New JSON POST /pending/{id}/decide endpoint (same internals as the form handler, but the client KNOWS whether the apply succeeded); on success the card fades out in place and the tab count decrements — a 37-item review is no longer 37 full reloads. Apply failures show the error and leave the card; network trouble falls back to the classic POST.
* Information architecture: 14 menu doors become 6. Check up, AI bot activity, Calendar, Brief generator, Reindex Tracker, Database health, and Google Reviews are hidden from the sidebar and reachable from their hubs instead: Page Performance carries Rankings | Quality check | AI bots tabs; Topic Clusters carries Clusters | Calendar | Brief generator tabs; the Changes header links to the Reindex tracker; Settings > Health links to the database console and the Reviews widget config. No page was removed — every URL still works.

= 0.62.0 =
* Lint engine: HTML entities are decoded BEFORE every text check — &#8212;/&mdash; can no longer bypass the em-dash lint (a documented months-old loophole, closed for every check at once).
* Lint engine: pre-existing long paragraphs are DEBT, not a blocker. The widget-update lint now receives the widget's CURRENT text; paragraph_length only fails on NEW violations, reporting pre-existing ones separately — a one-word edit to a post with an inherited 7-sentence paragraph no longer requires override_lint (the documented paragraph_length trap).
* Pending inbox polling relaxed from every 5 seconds to every 30 (battery/phone kindness).
* Note: the suspicious_chars Spanish-accent false positive in our records was already fixed in v0.20.2 — records corrected.

= 0.61.0 =
* UX quick wins (from the full admin UX audit). Pending inbox: change-type badges are human labels (slug in tooltip); Reject now confirms with an OPTIONAL REASON textarea wired to the note field the handler always supported but no input ever fed — the AI operator finally learns why things get rejected; Approve turns amber with a warning title on items carrying HARD lint violations, and bulk-approve confirms name how many hard-violation items are included; the doubled cc-lint- class is fixed at the source.
* Calendar honesty: refresh suggestions are no longer scattered onto fabricated calendar dates (crc32 placement) — they render as a ranked "Refresh next" list below the grid, priority order, no invented deadlines.
* CSS repairs: the three class collisions (.cc-rollup-stat big-stat vs pill, .cc-failed-line chip vs row, .cc-tag badge vs trend tag) are resolved by renaming the later components (.cc-rollup-pill, .cc-trend-tag) and deleting the dead chip block — un-squashing the Check up and LLM Activity stat tiles.
* Menu: Settings now registers late (priority 30) so it renders LAST — Page Performance and Reviews no longer sit below it.
* Mobile: shared .cc-table-scroll wrapper applied to the 10-column Page Performance table and the Check up table — wide tables scroll in their own box instead of dragging the whole page sideways.
* Editor sidebar: em dash removed from "ship it" line (house style applies to our own UI too).

= 0.60.1 =
* SECURITY: MCP bridge TLS verification is now ON by default for non-local hosts using the Windows native certificate store (CURLSSLOPT_NATIVE_CA, empirically verified) — the previous accept-any-certificate default carried admin Application Passwords to production sites unverified. CC_MCP_VERIFY_TLS=0/1 overrides; .local/.test/localhost stay unverified (self-signed dev).
* SECURITY: uninstall.php now requires the cc_assistant_delete_data_on_uninstall opt-in AND the canonical folder name before destroying anything — deleting a duplicate plugin folder can no longer wipe the live database (a loss this fleet already suffered once). Sweep completed: cc_activity_log + cc_lead_events tables, 3 orphaned cron hooks, all _cc_ postmeta.
* CRITICAL fix: propose_revert selected a nonexistent snapshot column (type vs snapshot_type) — every revert returned no_snapshot since v0.58. Also: same-second bulk-approve disambiguation (rank-matched snapshot selection), rollback of an applied revert now works (pre-restore snapshot id recorded into current_value at apply), and reverts trigger the heavy Elementor/SG cache path.
* Fix: render_probe diff mode rewritten shape-aware (probe facets are keyed reports, not lists — deltas were wrong or bloated); count-aware list diffs make duplicated blocks visible.
* Fix: commodity_audit returns REVIEW (never a blind STOP) when a URL cannot be resolved to a post; info-gain signals now detected inside Elementor-JSON bodies (escaped href); transport-404 messages name the actual missing route + current version.
* Fix: outcome_report page matching uses exact URL permutations instead of suffix LIKE (Polylang same-slug translations no longer pool into the EN page); unattributed lead counts surfaced; lead referer resolution retries against home_url (scheme/www mismatches).
* Perf: six front-end options seeded at activation (were a notoptions DB miss per anonymous page view each); admin-only text blobs flipped out of alloptions; daily expired cc_* transient sweep (win-audit competitor caches accumulated forever); lead-events DDL off the submission hot path (activator dbDelta + self-heal on failure).
* Fix: win_audit internal_support dimension queried a table that never existed (cc_internal_links -> cc_link_graph) — it silently skipped on 100% of installs since v0.41.
* Bridge: JSON-RPC main loop catches Throwable (a TypeError in a tool no longer kills the server); GET retry stops on definitive HTTP-status errors.

= 0.60.0 =
* New: commodity_audit tool — the four-strategy router. Classifies the site's top pages (warehouse impressions) into INVEST (conversion pages), BRIDGE (research content still earning clicks), CITE_PLAY (AI-absorbed but high-volume: reformat answer-first + cited to be the source the AI names), STOP (absorbed + low volume: no new investment). Absorption = actual CTR under 25% of the positional expectation. Joins warehouse metrics with new POST /commodity/signals (intent family + info-gain elements per URL); CITE_PLAY pages with zero info-gain elements get an explicit fix line.
* New: aeo_snapshot tool — dated AI-visibility snapshot stored in the local warehouse: absorbed-query census (how much demand AI answers consume), AI-crawler hits (llm_crawls 7d), AI Overview presence; deltas vs the previous snapshot. Weekly-harvest companion.
* Capabilities manifest gains commodity_router + aeo_tracking entries so new sessions discover both.

= 0.59.0 =
* New: capabilities manifest — whoami now embeds a versioned feature + workflow index generated by the plugin itself (includes/class-capabilities.php), so a brand-new chat on any machine learns every capability and its workflow in call one. The continuity guarantee: it ships with the code, so it can never go stale.
* Token pass: render_probe diff=true returns ONLY the delta vs the previous probe of the post (kept 24h) — the verify loop's second probe shrinks from a full DOM dump to a small diff; widget_schema gains a section filter (~60KB full schema -> a few hundred bytes) and lists section names on unfiltered calls; the bridge strips the legacy {site,data} response envelope before payloads reach the model (identical boilerplate on every legacy tool call, and the shape mismatch behind two shipped bugs).
* Fix: whoami version-drift check read plugin_version above the data envelope and would have false-warned on every site.
* New: transport retry (bridge) — GETs that die at the connection level (SiteGround dropping sustained sync sequences) retry twice with backoff; POSTs never retry (double-queue risk).
* New: info-gain gate (warn-only) on draft_create_post — drafts with ZERO non-commodity elements (no first-party price, data table, .gov/.edu citation, credentialed provider, or expert quote) get a COMMODITY RISK warning per Google's May 2026 non-commodity guidance; detected signals are reported otherwise.
* Tests now live in the repo (tests/, excluded from the zip): warehouse, outcome-DiD, attention, widget-schema suites + tests/run.sh.

= 0.58.0 =
* New: propose_revert tool — closes the outcome loop. Queues the restore of the snapshot captured just before an applied edit, as a NORMAL pending change (human approves the revert like any other change). Refuses with 409 when newer edits on the same post would also be undone (force=true to override, with the list shown); the restore takes its own pre-restore snapshot so approved reverts stay reversible. New change_type snapshot_restore in the apply dispatch.
* New: lead-event logging — Elementor Pro form submissions increment one aggregate (date, post, form) counter row; counts only, nothing personal, zero render-path cost. lead_events tool returns compact daily rows; outcome_report now folds leads {pre, post} into every judged edit when data exists — outcomes in the money metric, not just clicks.
* New: whoami version-drift warning (bridge-side) — when a site reports an older plugin version than the local build, whoami says so and which tools will 404 until the zip is deployed.

= 0.57.0 =
* New: outcome_report tool (outcome engine v2) — every applied edit judged with difference-in-differences from the local GSC warehouse: target-page clicks in SYMMETRIC pre/post windows around the apply date minus the site-wide change (algorithm updates and seasonality can no longer masquerade as edit results). Verdicts win/flat/regressed/low_data/too_early/no_url; regressions sort first; overlapping edits flagged. Computed entirely on the operator's machine — zero new load on the site.
* New: operator_kit + operator_kit_push_skills — the SITE now stores the operator knowledge (global design system + per-site brand skill, CLAUDE.md template with the server name filled, a .mcp.json entry template, ordered new-machine steps). A fresh laptop bootstraps the full working environment from the site alone; a lost laptop loses nothing but the app password. Skill pushes are direct saves (operator config, no pending queue), 200KB cap each.
* Ships bootstrap/CLAUDE-md-template.md in the plugin so the session protocol travels with the code.

= 0.56.0 =
* New: widget_schema tool — live Elementor widget-control registry read from Elementor's own controls stack on this site (core + Pro + addons, version-accurate). Without args: every registered widget type. With widget_type: the compacted control schema — type, default, valid options, size units, responsive flag (device variants collapsed), global-token capability, section, visibility condition. Cached 12h per Elementor version.
* New: warn-only schema validation on draft_update_elementor_widget — unknown setting keys and invalid enum values (both SILENT no-ops in Elementor) now attach unknown_setting_key / invalid_option_value warnings with did-you-mean suggestions to the queue response. Never blocks; silent when Elementor is unavailable.

= 0.55.0 =
* New: attention-flow system — pages are composed as a controlled path for the eyes, not a stack of sections. attention_spec tool returns four archetypes (emergency_transactional, consideration_conversion, service_local, informational_guide), each declaring the 5-second job, first-viewport requirements, scroll story, CTA rules, and scan pattern (Z/F/layer-cake); archetype auto-resolves from page intent + business mode.
* New: attention_audit tool ("theoretical heatmap") — walks top-level bands (Elementor full fidelity, Divi basic) and predicts each band's share of visitor attention from position decay x visual weight (heading size, CTAs, imagery, background treatment, text density). Flags: buried_h1, no_cta, cta_below_fold, competing_ctas, no_tel_above_fold (emergency), cta_without_proof, flat_hierarchy (the generic-page smell), monotone_rhythm, text_wall_band. Returns attention_score 0-100 + top_fixes; run before AND after builds.
* New: optional Microsoft Clarity integration — paste a Clarity project ID in settings to load the free heatmap/scroll-map tracker on the public site and validate predicted attention against real behavior. Empty by default = nothing loads; logged-in admins are never tracked.

= 0.54.0 =
* New: desktop GSC warehouse. The MCP bridge now keeps a full-fidelity (date, page, query) Search Console archive in SQLite on the operator's machine (~/.cc-assistant/warehouse/<site>.sqlite) — no impression-floor pruning, no 250MB cap, and query-level history beyond what the WP table retains. The WordPress site is only a pass-through proxy: the new GET /gsc-export route makes one searchAnalytics API call per request (paginated via start_row) and writes NOTHING to the site database.
* New MCP tools: gsc_warehouse_sync (incremental pull — refreshes the trailing week for late-arriving data, gap-fills, then backfills toward the ~16-month GSC retention floor across repeated calls), gsc_warehouse_query (read-only SELECT/WITH against the local archive with positional params and a row cap), gsc_warehouse_status (coverage + staleness snapshot).
* GET /gsc-export/status preflight route reports connection, property, plugin version, and the freshest finalized date, so the bridge fails with an actionable message on sites that are not GSC-connected or still run a pre-0.54 plugin.
* Requires the sqlite3 PHP extension in the BRIDGE process only (add "-d", "extension=sqlite3" to the server args in .mcp.json); sites without it keep every existing tool — the warehouse tools alone refuse with setup instructions.

= 0.51.7 =
* Fix (root cause of the invisible 404 template): publishing a template through the queue fires wp_update_post, which re-triggers Elementor's own save hooks — those RESET _elementor_template_type to "page" and DELETE _elementor_conditions, silently undoing the identity stamped at create. Template creates now persist the intended identity to _cc_assistant_template_intent, and apply_publish_draft replays it AFTER publish (type meta, builder mode, elementor_library_type term, conditions) before regenerating the conditions cache. Verified failure: an error-404 template arrived published as a typeless "page" with no conditions, so it neither rendered 404s nor appeared under Theme Builder > 404.
* New: repair mode on refresh_theme_builder_conditions — pass repair_post_id + template_type (+ display_conditions) to re-stamp an already-published template that was reset by the pre-0.51.7 behavior, then regenerate the cache in the same call. Requires the template-editing opt-in.
* Shared stamping consolidated in CC_Assistant_Apply::stamp_template_identity().

= 0.51.6 =
* New: list_theme_templates tool (GET /theme-templates, read-only) — inventories every Theme Builder template CPT row in ANY status (including trash) with the meta that decides rendering (_elementor_template_type, elementor_library_type taxonomy, _elementor_conditions, has_elementor_data, edit_mode) AND the raw Elementor Pro conditions cache. Exists because a template that "does not show / does not render" was previously undiagnosable through the tool surface.
* New: refresh_theme_builder_conditions tool (POST /theme-templates/refresh-conditions) — force-regenerates the Elementor Pro conditions cache and returns it. Content-neutral maintenance, applied immediately (no pending): it only rebuilds Pro's index of already-approved templates. Fixes the stale-cache failure class (template trashed/edited outside the plugin leaves the cache pointing at a dead template, or missing a live one).
* Fix: ANY approved apply touching a template CPT (widget edits, section ops, full imports — not just publish_draft) now regenerates the Pro conditions cache alongside the heavy CSS flush.

= 0.51.5 =
* New: Theme Builder template CREATION via draft_create_post. The v0.49 template-editing opt-in only covered existing templates; the create path still enforced the raw page/post allowlist, so composing a new 404/header/footer template through the tool was impossible. With "Allow editing Theme Builder templates" on, post_type=elementor_library is now accepted with a required template_type (error-404, header, footer, single, single-page, single-post, archive, search-results, section, popup) and optional display_conditions (Elementor Pro condition strings, e.g. include/singular/not_found404). Create stamps _elementor_template_type + the elementor_library_type taxonomy term and stores conditions dormant on the draft.
* New: approving a template publish_draft now regenerates the Elementor Pro Theme Builder conditions cache (plus the heavy Elementor CSS flush), so the approved template immediately takes over its slot — previously a template published through the queue stayed inert until the operator re-saved its conditions by hand. Guarded so a cache-regen failure can never roll back a successful publish.
* Templates remain un-trashable through the tool, and template creation stays refused while the opt-in is off.

= 0.51.4 =
* Fix: plugin-managed schema meta (_cc_assistant_schema_jsonld / _cc_emergency_service_schema) is now validated as JSON at BOTH queue time (draft_update_postmeta refuses invalid payloads with 422 invalid_schema_json) and apply time (last line of defense for rows queued by older versions). Previously six production posts stored ~10KB of unparseable JSON-LD each — the wp_head emitter silently skipped them, so the garbage was invisible to every rendered-output audit and only managed_schema could see it.
* Fix: applying an EMPTY value to a schema meta key now deletes the row instead of storing a 0-byte meta (five location pages on one install carried exactly those no-op emergency-service rows).
* New: 0.9.0 DB migration garbage-collects defective schema meta already stored by older versions — invalid-JSON blobs and empty rows are removed, valid rows untouched; result counts saved to option cc_assistant_schema_gc_result.

= 0.51.1 =
* Fix: draft_update_divi_modules attrs-only edits (font weights, image alt, spacing — no inner_content) no longer hard-block on body lint. The delegated lint scans the whole rebuilt body, so pre-existing conditions in old posts (plus raw shortcode syntax counted as giant "sentences" by wall_of_text/sentence_length) refused no-text changes. Attrs-only calls now auto-bypass the content lint; any edit touching inner_content still gets the full lint.

= 0.51.0 =
* New: Divi module toolchain. Divi-built posts (one giant nested shortcode string) previously forced full-body rewrites through draft_update_post_content — slow, character-drop-prone, deletion_ratio false positives. Three new tools fix that: list_divi_modules (indexed inventory of the shortcode tree with type, preview, headings, typography attrs), get_divi_module (one module in full fidelity), and draft_update_divi_modules (surgical inner-HTML and/or attribute edits on specific modules; the server splices, re-verifies the module tree parses identically, and routes through the normal lint + outline + Pending Changes queue).
* New: attribute editing on Divi modules covers the common non-content fixes without body resends: heading text on dipl_fancy_text (fancy_text attr), image alt on et_pb_image, and typography weights (e.g. sitewide semi-bold body text from text_font="|600|...").

= 0.50.1 =
* Fix: imageless hero background now resolves to the kit PRIMARY (dark) token - kit secondary is a light accent on some sites and made white hero text unreadable.
* Fix: hero/cta white-button hover now uses the kit neutral color instead of hardcoded #F4F4F4.

= 0.50.0 =
* NEW: first-class Elementor popup support (includes/class-popups.php). list_popups tool + GET /popups endpoint inventory every popup template (elementor_library with _elementor_template_type=popup): id, title, post_status, modified, edit_url, raw _elementor_conditions, derived site_wide / included_pages, and the ready-made popup_open_url. Optional covers_post_id adds per-popup covers_this_post (true/false/null — null when a condition string is not understood, never guessed).
* NEW: popup-open links as first-class button/link input. Pass link:{popup_id:N} to draft_add/update_elementor_widget, or popup_id on build_page_from_spec hero/cta_band buttons and card_grid cards (popup_id wins over url) — the plugin emits the exact Elementor action URL (#elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3D{base64 {"id":"N","toggle":false}}) server-side instead of the AI hand-building the base64 blob from scraped HTML.
* NEW: popup condition guard (warn-only, never blocks). Every queue path that carries a popup-open link — the two draft widget endpoints, build_page_from_spec, and import_elementor_data (raw_data scan) — decodes the popup id(s), evaluates the popup's display conditions against the target post, and attaches popup_not_displayed_on_target when the popup will not fire there (Elementor only loads a popup document on pages matching its conditions, so the button silently no-ops — happened in production). Softer popup_conditions_unverified when conditions can't be parsed; popup_not_found / popup_not_published cover the other silent-failure cases. The message names the popup and the exact operator fix (Elementor > Templates > Popups > edit > Publish Settings > Conditions > add the page or Entire Site).

= 0.49.2 =
* FIX: Theme Builder template editing now recognizes ElementsKit templates (elementskit_template / elementskit_content), not just Elementor Pro's elementor_library. Sites whose header/footer is built with ElementsKit could never edit those templates even with the opt-in on. The editable list is filterable via cc_assistant_template_post_types. Heavy cache flush + site_wide_impact warning + trash-block all extend to the ElementsKit CPTs.
* FIX: page_robustness_audit medical_schema check now JSON-decodes JSON-LD blocks, walks @graph arrays, and accepts array @type values (mirrors render_probe's parser) — pages typed ["MedicalClinic","HealthAndBeautyBusiness","LocalBusiness"] no longer fail with "No medical schema found".
* FIX: cross_vertical_vocab (page_robustness_audit) and industry_vocabulary (audit_page_design) consult the playbook-fit coverage; when the overlay fit is drift_detected/thin the checks downgrade to a non-blocking warn/advisory with an overlay-drift note instead of blocking on the site's own services.
* FIX: service_inventory adds a fallback tier-extraction pass for pricing built from plain heading widgets (h4 card title + span-level "$800 / 1 syringe" price headings) — previously returned zero tiers on such pages.
* FIX: build_page_from_spec section templates resolve primary/secondary/neutral colors from the target site's Elementor Kit (with __globals__ token refs) instead of hardcoding the ER sister-site palette (#DA1212 / #11468F / #F4F4F4); hardcoded values remain only as fallbacks when the kit can't be resolved.
* FIX: no more Article schema on pages — build_page_from_spec skips auto-writing page-jsonld for post_type=page (the SEO plugin already emits WebPage) and propose_schema generates a WebPage node for pages (Article kept for posts).
* FIX: FAQ accordion titles no longer inherit an em-scale sampled size re-emitted as px (1.1px, invisible questions) — sampling only adopts sane px values, emit is clamped to >=10px (default 20px, weight 600), and titles get an explicit kit-primary color.

= 0.49.1 =
* FIX: draft_create_redirect was unreachable for 410/451 through the MCP tool — the dispatcher still demanded a destination argument even though the schema and both server gates had dropped it. Now requires source only.
* FIX: template opt-in was bypassable. import_elementor_data and replace_section_content (both write paths) and the structure-read tools (elementor export, list_sections, elementor-tree, audit-design, image-placeholders) did not honor cc_assistant_allow_template_editing — they now route through the same allowlist + template gate as every other edit, so "template editing off" actually protects templates on every path. Trash stays strict; site_wide_impact warning now also emitted by the import and section-replace paths.
* FIX: apply_post_meta idempotence guard now requires scalar operands and a non-empty proposed value (avoids an Array-to-string notice on serialized meta and a false-success on a genuine empty-value write failure).
* FIX: hero preload now accepts protocol-relative (//host) background image URLs instead of silently skipping them.

= 0.49.0 =
* NEW: Elementor Theme Builder template editing (opt-in). Settings > General > "Allow editing Theme Builder templates" lets the assistant read and propose edits to headers, footers, and single-post/archive layouts (the elementor_library post type) through the existing Elementor tools. Off by default. Every change still routes through the Pending Changes inbox; template edits carry a site_wide_impact warning; applying a template edit triggers a global Elementor + SiteGround cache flush (a template affects many pages); templates can never be trashed by the tool.
* Centralized the read/edit post-type gate into CC_Assistant_REST_API::editable_post_types() so the allowlist + template opt-in are enforced consistently across get_post, get_elementor_widgets, page_map, and all draft edit endpoints.

= 0.48.2 =
* NEW: settings UI for the form email standard (Settings > General): recipients/senders the composer's `form` template bakes into every built contact form. Fields optional; admin-email fallback; second recipient emitted only when set.
* FIX: draft_create_redirect no longer requires a destination for 410/451 "gone" statuses (validation contradicted the tool contract and forced a dummy URL).

= 0.48.1 =
* FIX: idempotent postmeta applies. update_post_meta() returns false when the stored value already equals the proposed one; the apply handler treated that no-op as a failure ("Apply returned false") — first seen when rebuild-in-place SEO pendings re-sent values the page already carried. Apply now verifies the stored value and reports success.
* FIX: rebuild-in-place no longer queues SEO pendings whose value is already live (reviewer noise).

= 0.48.0 =
* NEW: hero background preload. Emits <link rel="preload" as="image" fetchpriority="high"> for the first root container's background image (the LCP element on built pages); desktop-only media attr when the site convention blanks the mobile background. URL cached in postmeta, invalidated on save/apply. Toggle: cc_assistant_hero_preload_enabled.
* NEW: claim-removal guard. Queueing a change that deletes text a prior APPROVED pending deliberately added/confirmed (summary matches confirm/restore/revert) attaches claim_removal_warnings naming the sibling change. Warn-only; covers 3-letter ALL-CAPS acronyms (MRI, EKG) as well as full words.
* NEW: build_page_from_spec target_post_id — rebuild an existing page in place through the elementor_full_import queue path (one pending, same ID-regeneration and rollback), instead of the vehicle-draft/export/import round-trip. SEO fields queue against the target.
* NEW: composer section templates `form` (standard contact form; recipients from option cc_assistant_form_email_standard; subject "New website form submission - {page_label} page") and `map` (google_maps; address from spec or cc_assistant_facility_address).
* NEW: card `tier: "primary"` field for red-tier cards; all other cards get deterministic secondary tokens (removed the keyword-based tone inference that assigned primary at random).
* NEW: gsc_low_ctr / gsc_opportunities annotate the first 20 rows with http_status / live / redirects_to (6h cache) so pruned 404/410 pages are not proposed as optimization candidates.
* FIX: card url now reliably produces an icon-box link (strict validation; verified end-to-end through brand-default passes).
* FIX: applying any Elementor change now regenerates the post's Elementor CSS, clears the element cache, and purges SiteGround (per-URL; site-wide on full imports). No more manual "Regenerate CSS & Data" after approvals.
* FIX: propose_schema honors dry_run (the MCP dispatcher was dropping the flag), omits BreadcrumbList when Rank Math/Yoast already emits one (opt back in via include_breadcrumb), emits valid dates or none, permalink URLs, Organization author, and a real description fallback.
* FIX: list_posts searches posts AND pages by default (was pages only); optional post_type filter.
* FIX: keyword_coverage lint ignores stopwords (near, me, a, for...) and matches word prefixes ("doctors" ~ "doctor"), ending false failures that demanded spammy tokens in titles.

= 0.47.0 =
* NEW: legacy Elementor accordion/toggle ARIA repair. Elementor frontend JS stamps aria-selected onto .elementor-tab-title[role="button"], invalid on the button role (axe aria-allowed-attr critical; fails Lighthouse agent-accessibility-tree). Tiny MutationObserver printed in wp_footer ONLY on pages that rendered a legacy accordion/toggle strips it at runtime; aria-expanded still carries state. Toggle: cc_assistant_aria_fix_enabled.
* NEW: X-Frame-Options: SAMEORIGIN emitted on front-end responses when no upstream frame-control policy (XFO or CSP frame-ancestors) is set. Same-origin iframes (Elementor editor, customizer) unaffected. Toggle: cc_assistant_xfo_enabled.

= 0.46.2 =
* FIX: content_audits cross_site_reference threw a false positive on the active site's OWN brand. The default sister-brand list ships generic ER names (incl. "ER of Irving"); on erofirving.com that is the site's own name, so the check flagged ALL 93 pages as "template paste from a sister site" — noise that could bury a real Lufkin/White Rock hit. The existing guard only neutralized single-word city tokens ("irving"), not the multi-word brand. Now the check excludes the active site's own brand, matched two ways: by site title (substring) and by alnum-folded host ("er of irving" -> "erofirving" found inside erofirving.com). Real sister brands on other tenants still flag correctly (verified: Plano/Frisco/Grapevine flag on erofirving; "ER of Irving" flags on an erofplano site).

= 0.46.1 =
* FIX: render_probe was the "reliable verify path" but could silently return STALE markup. Its only cache-bust was a query-string param (cc_probe=time()), which does not defeat SiteGround's full-page Dynamic Cache (keyed on path, ignores query strings) — so a cache HIT served pre-edit HTML that the probe reported as live. Now it (a) sends no-cache request headers (Cache-Control/Pragma) as a best-effort bypass for caches that honor them, and (b) reads the response cache markers (X-Proxy-Cache / X-SG-Cache / SG-F-Cache / X-SG-CDN / X-Cache / cf-cache-status / Age) and returns a `cache: {state, markers}` block. On a HIT the result carries a loud STALE-RISK warning telling the operator to purge the host/CDN cache and re-probe before trusting schema/links/headings/alt. No more silent stale reads.

= 0.46.0 =
* FEAT: auto Table of Contents on posts (includes/class-toc.php). Reads each post's H2/H3, adds stable anchor ids (reusing any id already present), and injects a numbered, collapsible TOC immediately above the first paragraph via the_content (priority 20). Entries auto-number 1 / 1.1 (CSS counters); the box collapses via a native <details>/<summary> (no JS); clicks scroll smoothly (respecting prefers-reduced-motion, with scroll-margin so targets are not hidden under a sticky header); a tiny IntersectionObserver highlights the section currently in view. Posts only (not pages); H2/H3 only. OFF BY DEFAULT — toggle at cc-assistant > Settings > General; when off the TOC class is never even loaded (cheap gate before require), so zero front-end overhead. Controls: option cc_assistant_toc_enabled (default false), per-post opt-out postmeta _cc_assistant_toc_disabled, filter cc_assistant_toc_min_headings (default 3), filter cc_assistant_toc_post_types (default ['post']). Built to replace flaky third-party TOC plugins (e.g. on ER of Irving) with a reliable, self-contained one — disable the old TOC plugin before enabling this.
* NOTE: 0.46.0 also carries the Google Business Profile reviews Elementor widget (header bumped in parallel) — document that feature here too.

= 0.45.1 =
* FEAT: managed_schema tool (GET /managed-schema) — read-only visibility into the JSON-LD the plugin injects ITSELF at wp_head (per-post _cc_emergency_service_schema / _cc_assistant_schema_jsonld postmeta). Lists every post carrying managed schema with parsed @type + byte size + live enabled flags; pass post_id for the full raw blob. Closes the gap where a bulk-applied emergency-service node was invisible and became a silent site-wide org duplicate that took a code-grep to find.
* FEAT: site-wide kill-switches for the two managed-schema emitters — options cc_assistant_emergency_schema_enabled + cc_assistant_page_schema_enabled (default true) gate the wp_head injection, so it can be disabled without clearing postmeta or deactivating the plugin.
* FIX: render_probe emitter attribution — guess_emitter only checked the JSON body for "_cc_assistant", but the plugin's own marker is the data-cc-assistant attribute on the <script> TAG, so render_probe mislabeled its OWN output as "unknown" (which sent a schema-source hunt down the wrong path through themes / code plugins). Now returns cc_assistant:<variant> (emergency-service / page-jsonld).

= 0.45.0 =
* FEAT: draft_add_section — semantic section RECIPES. Describe a section by intent + content ({recipe, data}) and the plugin expands the full brand-correct Elementor tree (eyebrow + 38px H2 + red accent bar, brand tokens, grid rows auto, FA5 icons, responsive type, capped widths) — ~10 lines of data instead of ~300 lines of hand-authored JSON. Queues via the proven apply path: container_add, or the atomic section_rebuild when remove_id is supplied (recipe-based rebuild). First batch: hero, card_grid, checklist_2col, cta_banner, section_header (class-section-recipes.php). FAQ / gallery / decision_compare follow in the next batch.

= 0.44.1 =
* FIX (steps template — card != background): step items are now a contrasting FLAT panel (bg is the opposite of the section bg, light-grey on white / white on grey) so a step never blends into its own background, while staying visually distinct from card_grid (flat + big brand numeral, no border/shadow/icon). Closes the recurring operator complaint that the "How we treat" steps read as same-color-on-same-color.
* FIX (steps template hierarchy): step titles render at 22px h3 so they sit above the 18px card_grid h4 (no h3<h4 inversion). Bundles the fix that had been applied per-page by hand.

= 0.44.0 =
* FEAT: render_probe — loopback-fetch a post's own front end and introspect the LIVE rendered DOM. Returns every JSON-LD block attributed to its emitter (Rank Math / hand-built snippet / cc-assistant) and linted for self-serving aggregateRating on org/business nodes, duplicate @id, duplicate BreadcrumbList, and breadcrumb items missing name; resolved link accessible names (alt'd image links stop reading as empty); alt coverage; heading outline. The reliable verify path when an edge WAF 403s external curl. MCP tool render_probe(id, extract).
* FEAT: session bootstrap gate (class-session-gate.php) — whoami stamps the session and ships an ordered pre-flight (whoami -> design skill -> render_probe before/after edits -> pending check -> robustness/win audit), surfaced in session_recap.preflight and the bootstrap_hint. Mutating handlers attach a session_not_bootstrapped reminder when a chat skipped whoami. Opt-in hard gate via option cc_assistant_bootstrap_hard_gate.
* FEAT: get_elementor_tree — full nested-node tree (every container + widget id, depth, parent_id, position, path, child_count, grid/flex layout hint, label). Replaces the export+parse workaround for reaching middle containers (grid rows, column wrappers) and makes grid dead-rows visible (grid_rows x grid_columns vs child_count).
* FEAT: schema_scan — site-wide schema-source scanner; aggregates the render_probe schema lint across a sample of pages and reports self-serving aggregateRating / broken + duplicate breadcrumbs by emitter (Rank Math / hand-built snippet / cc-assistant), including emitters cc-assistant cannot itself edit.
* FEAT: redirect_audit — read-only Rank Math redirect-hygiene check; flags content->homepage funnels (the stale-301 pattern that diluted the homepage) and redirect chains.
* FIX (audit grounding + severity tiers): brand_compliance now allows the documented semantic greys (#555555/#777777/#dddddd/#e0e0e0); cross_domain_images whitelists placeholder hosts (via.placeholder.com et al.) per the §24 protocol; heading_size_hierarchy demoted to an "advisory" tier that never drops the verdict on its own. Each check now carries a severity field. Ends the chronic false-positive noise.
* FIX (builder): grid containers built with columns but no rows now default rows to auto (no reserved empty row / dead space) in both add_container and nested build_child_node. FA6-only icon names in a proposed change are flagged at queue time (they render blank on the bundled FA5).
* FEAT: draft_rebuild_section — atomic "add new section + remove old" as ONE all-or-nothing pending (new change_type elementor_section_rebuild). A single read-modify-write: the new section is built and the old removed on the same in-memory tree and written ONCE only after both succeed, so the page never shows a transient duplicate and never half-applies. Pre-apply snapshot + a dedicated rollback (remove the new, restore the old at its original root index) are the recovery path; no heading-duplicate guard (replacing a section re-uses its headings by design). Collapses the add+remove pair (and the position-drift / "approve both together" dance) into one approval. CAUTION: this is the only change in 0.44.0 that touches the apply/mutation path — validate on a staging copy before relying on it on a live site.
* PLANNED (next): generalised build-session transactions (group arbitrary edits) + semantic section recipes (draft_add_section(recipe, data) — describe a section by intent+content, plugin expands the brand-correct tree). draft_rebuild_section is the shared atomic-apply foundation for both.

= 0.43.10 =
* Fixed (composer): the new `steps` template, first-render bugs. The step number is now a decorative `div` (was an `h2` that created an h2->h4 heading skip and littered the outline with numeric headings); the step title is now `h3` (correct level under the section `h2`); the off-palette `#dddddd` top border is gone (steps separate by the brand numeral + grid gap, which also keeps them visually distinct from card_grid).
* Fixed (audit): `alignment_consistency` now treats a CSS-grid container (card grid OR steps row) as block content, so a centered section heading over a left-aligned grid is no longer false-flagged.

= 0.43.9 =
* New (composer): `steps` section template — a numbered process row with no card chrome (a large brand numeral is the anchor), visually DISTINCT from card_grid. Use for sequential content so pages stop looking like a stack of identical card grids.
* New (composer): section-variety + image enforcement. build_page_from_spec now REQUIRES at least 2 image-bearing sections, and REFUSES a monotonous layout (4+ card_grids with fewer than 2 steps/text_image breaking them up, or 4 card_grids back-to-back) unless override_lint=true. Forces a designed page, not a template.
* Fixed (composer): icon-name normalizer. Any FA6-only icon name passed in a spec (fa-circle-info, fa-car-burst, fa-hand-fist, etc.) is auto-mapped to its FA5 equivalent so icons never render as a blank shape on these FA5 sites — regardless of which name the caller passes.
* Design skill updated (design-system-global §I/J/K): section-variety mandate, FA5 icon naming, and the operating principle "assume expertise, then execute" — so a fresh chat builds to standard by default.

= 0.43.8 =
* Fixed (audit): three false-positive sources removed so the design audit stops crying wolf. (1) hover_coverage now recognizes the real Elementor hover keys (button_background_hover_color / hover_color / button_hover_border_color + kit-token hover refs) instead of only the non-existent background_hover_color. (2) button_contrast now exempts buttons with a contrasting solid border (outlined chips / white-on-grey pills are visible by their border). (3) alignment_consistency now skips sections that contain a table, list, accordion, or card grid — a centered heading over left-aligned block content is the intended pattern, not a defect.
* Changed (composer): card_tone() recognizes Spanish danger words (grave, evite, no maneje, peligro, emergencia, etc.) so danger-card icons auto-color brand-red on Spanish pages, matching English. No more per-page manual red-icon patch on ES builds.
* Fixed (composer): build_page_from_spec lints each FAQ accordion item INDIVIDUALLY instead of as one concatenated blob, so a normal multi-item FAQ no longer false-trips the wall_of_text gate (removes the routine override_lint on FAQ pages).

= 0.43.7 =
* Changed: admin notices are no longer dumped as N stacked banners on every CC Assistant screen (the "notification spam"). They now collapse into ONE notification center showing a total count + a per-emergency-level breakdown (Critical / Needs attention / Info). It stays collapsed by default; click "View" to expand a panel that groups notices by level, each individually dismissible (dismiss decrements the count live). Severity is derived from the existing notice type (error -> Critical, warning -> Needs attention, info/success -> Info). Snooze/TTL behavior unchanged.

= 0.43.6 =
* New: `draft_trash_post` MCP tool + `POST /draft/trash-post` + `trash_post` apply type. The assistant can now RETIRE an old page (e.g. one replaced by a rebuild, or an obsolete translation) through the Pending Changes inbox instead of the operator hand-deleting it in wp-admin. Reversible (WP Trash, restorable), pre-apply snapshot taken, plugin-scoped to allowlisted post types. The modified-conflict guard is skipped for trash_post (retiring a page doesn't care about content drift).
* New: "Approve all" now applies in dependency-safe order instead of selection order — trash_post first (frees a URL the new page will claim), then content + meta edits, then custom-permalink claims, then publish_draft LAST (so the publish gate sees the finished page and a new page never collides with the old one's permalink). This removes the most common cause of bulk-approve failures (publish applying before its fix; permalink collisions; race-guard trips).

= 0.43.5 =
* Changed (composer): button + FAQ styling is now SAMPLED from the style mirror (`sample_recipe`), so each page matches that site's own recipe — corner radius, padding, border weight, font family/size for buttons; title size/weight for FAQ accordions — instead of hardcoded defaults. On erofwhiterock this yields radius 2, 18/32 padding, 1px border, Montserrat 16/700 automatically.
* Fixed (composer): every button variant now defines a COMPLETE hover state (background + border + text) using the site's invert pattern (solid↔white), so buttons no longer ship without a hover. Brand colors via kit tokens; neutrals literal.
* Fixed (composer): FAQ accordion titles keep the `h3` tag for SEO but are sized down to the site's accordion-title scale (was rendering at full section-h3 size) and colored via kit tokens.

= 0.43.4 =
* Fixed (audit): `button_contrast` compared each button's fill to its OWN background (after the bg cascade absorbed it) and so false-flagged every solid button. It now compares against the SECTION behind the button. Red-on-red is still caught; white-on-navy correctly passes.
* Fixed (audit): the color resolver now treats a fully transparent fill (`rgba(r,g,b,0)`) as no-fill instead of opaque white/black, so transparent outline buttons no longer read as white-on-white in `wcag_contrast`.
* Fixed (composer): on-dark body text is now white (`#FFFFFF`) instead of off-palette `#E0E0E0` (cleared brand_compliance + higher contrast on navy bands).

= 0.43.3 =
* Fixed (composer): `build_page_from_spec` now gives every card a real, DISTINCT icon. Previously cards only got an icon when the spec set one, so Elementor rendered its default star on every icon-box (a wall of ~22 identical stars). New semantic engine in class-build-from-spec.php infers a meaningful Font Awesome icon from each card's title (falls, car crash, brain, blood thinner, do/avoid check-x, insurance shield, etc.), then guarantees no repeats within a grid via a curated fallback rotation. Spec-supplied icons still win. All icon names are FA5-compatible (render on Font Awesome 5 AND 6 via alias) — FA6-only names rendered blank on the FA5 sites.
* Fixed (composer): button hierarchy + contrast. The first button in a row is the solid primary action; the rest are outline secondaries — no more two identical solid buttons side by side. On dark/colored bands buttons invert (white primary + brand label, white-outline secondary) so a button can never vanish into a same-color band (the red-button-on-red-band bug). Brand colors stay kit-token driven; only neutrals are literal.
* Fixed (composer): icon color is now semantic — brand-red ONLY for danger cards (severe / call 911 / avoid / do-not), brand-navy for everything else. Coloring every icon red made "Do this" read as a warning and turned pages into a wall of red.
* Fixed (composer): hero eyebrow/kicker now renders as a styled `<p>`, not an `<h6>`. An h6 above the h1 inverted the heading outline (a11y + AI heading-parse defect).
* New (audit): `audit_page_design` adds `icon_variety` (weight 8 — fails default-star walls or one icon dominating 60%+ of 4+ boxes) and `button_contrast` (weight 10 — fails any solid button whose fill matches its section background). Closes the gaps where a page with 22 identical icons or an invisible button still scored "pass".
* Changed (audit): `check_heading_order` now flags any h2-h6 heading that appears BEFORE the page H1 (the eyebrow-as-heading defect), not just downward level skips.

= 0.43.0 =
* New: `page_robustness_audit` MCP tool + `GET /posts/{id}/robustness-audit`. Audits any page against a three-layer standard — intent (one page = one intent; no informational sprawl on conversion pages), conversion (message-matched H1, answer-first, single primary CTA, social proof at the decision point), and trust/extraction (YMYL E-E-A-T authority density, mode-correct medical schema, NAP, inverted-pyramid extractability, no cross-vertical vocabulary). New class includes/class-page-robustness.php.
* New: `CC_Assistant_Page_Intent` (includes/class-page-intent.php) — global query-intent + page-type classifier and a healthcare BUSINESS MODE (emergency vs scheduled) auto-detected from the title corpus, overridable via option `cc_assistant_business_mode`. Freestanding ERs and wellness clinics are now judged oppositely above the fold.
* Fixed: `win_audit` intent_format no longer penalizes a conversion page for facing informational-article competitors on a research-intent query. When query intent and page intent are different families it now warns (neutral 65) and points to the spoke strategy instead of tanking the score to 35. win_audit output gains `query_intent` + `page_intent`.
* Changed: `whoami` session_recap now ships `business_mode` + `page_standard`, and the bootstrap_hint instructs a fresh chat to run page_robustness_audit before/after every build. SEO playbook (healthcare overlay) gains the intent doctrine as its first top rule; playbook version bumped.

= 0.12.0 =
* New: `draft_remove_accordion_item` MCP tool + `/draft/elementor-accordion-item-remove` REST endpoint. Removes both the title (settings.items[]) and matching answer container (widget.elements[]) of a nested-accordion item together, with rollback that re-inserts at the original index. Fixes the gap where draft_update_elementor_widget could not dedupe FAQ items because it only merges into widget.settings.
* New: fuzzy duplicate-title detection on `draft_add_accordion_item`. Refuses (422) when an existing item has >= 80% similar normalized title; pass override_dup=true to add anyway. Prevents the duplicate-FAQ regression introduced in 0.11.1.
* New: builder helper `sanitize_unsafe_text_globals` now strips risky `__globals__` text-color refs (title_color, description_color, text_color, heading_color, hover_*) when the caller did not pin an explicit hex. Prevents widgets cloned off a dark-bg sample from inheriting white-on-white text when they land on a light section background.

= 0.1.0 =
* Initial scaffold: site identity, snapshots, pending changes inbox, MCP server with whoami / list_posts / get_post / list_pending_changes / health tools, REST API fallback, friendly admin UI.
