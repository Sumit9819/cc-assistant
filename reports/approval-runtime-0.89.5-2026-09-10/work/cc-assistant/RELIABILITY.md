# Reliability release 0.87.0

Claude now receives one versioned source of capability summaries, tool descriptions and SEO policy. MCP input schemas have their own canonical catalog. The release also fixes an invalid empty `properties` array in `get_content_scope` that caused the tested Claude client to reject the tool list.

## Check actual capabilities before proposing settings

Use `get_plugin_settings(slug)` followed by `inspect_plugin_capability(slug, option_name, path, proposed_value)` where relevant. The inspector performs no setting writes. It reports the installed version, active state, observation time, storage evidence, registered constraints and explicit unknowns.

- WordPress registered settings: declared types, enums and bounds. Stored values never become invented enums.
- Elementor: the current widget registry and existing conditional/default-value evaluator.
- Rank Math: registered modules and their runtime activation state.
- SiteGround Optimizer: native toggle names from its runtime REST registry. Generic writes to `siteground_optimizer_*` are rejected because native actions may also update server rules and caches. A native execution adapter is still required to automate those actions correctly. These optimizer controls do not establish hosting CAPTCHA configuration.
- Polylang: languages and translation relationships when its runtime APIs are available.

Coverage is partial. A missing schema does not prove a feature is absent. Registry validation does not establish licensing, factual correctness or the rendered effect. Some settings are registered only on admin screens and remain unknown during REST calls.

## Verify an actual blog draft

1. Claude prepares `content_workflow(objective="new_blog")` and reads current strategy/source evidence.
2. It creates the article with `draft_create_post(post_type="post", workflow_id=...)`.
3. The server attaches the workflow/account/proposal binding to the created draft. A client cannot edit that binding with the generic metadata tool.
4. Claude calls `verify_content_workflow(workflow_id, post_id)` using the returned post ID.
5. The tool checks source freshness, current strategy and site notes, the bound draft, nonempty saved title/content, and the corresponding pending publication proposal. It returns actual IDs/statuses and an explicit result.

`record_integrity_pass` certifies only those record checks. It does not certify article quality, source truth, category suitability, clinical accuracy, rendered output, indexing, rankings or publication. Missing IDs, unrelated drafts, rejected proposals, changed sources and empty content do not pass. A prepared plan alone never establishes a completed draft.

Version 0.86 workflows have no stored source basis. Prepare a new workflow before binding a new draft. Existing unbound drafts remain usable through the normal tools but cannot be retroactively presented as verified workflow results. Errors that include existing draft/proposal IDs require inspection before retrying creation.

Workflow bookkeeping is excluded from content-baseline hashes so attaching the result binding does not invalidate its pending publication proposal. Normal content changes still invalidate that baseline.

## Consistent audit and measurement language

`page_robustness_audit` now uses the same fresh evidence engine as `verified_page_audit`. The former score and pass fields are `null`; the legacy CTA rules, citation quotas and word-count rewrite prescriptions are retired. Read individual observations and unknowns. The `strict` argument remains accepted for compatibility.

`aeo_snapshot` now returns `low_ctr_candidates_28d` and `search_appearance_observations`, with measurement version `observations-2` and AI attribution explicitly unknown. Low CTR and crawler visits cannot prove AI consumption or citations. Comparisons skip incompatible legacy snapshots. Failed candidate measurements cannot silently become zero counts.

Business-mode detection no longer instructs Claude to assume 24/7 hours or a specific medical schema. Actual business facts and eligibility must be established separately.

## Tests and limits

The PHP regression suite checks production classes with controlled WordPress/SQLite fixtures. `tests/evals/run.py` runs the authenticated Claude CLI against an isolated MCP simulator using the real tool schemas and shared guidance. Six scenarios cover no-GSC drafting, complementary articles, CAPTCHA, unsupported settings, plan-only status and stale evidence, with repeated trials. Traces and saved fixture state are graded alongside final responses; this is distinct from PHP unit testing.

The harness disables inherited user/project settings, hooks and built-in tools, and loads only its simulator. It waits for the MCP catalog at startup using the documented `alwaysLoad` option: [Claude MCP documentation](https://code.claude.com/docs/en/mcp#exempt-a-server-from-deferral). A working authenticated Claude CLI is required; use its native executable on Windows if shell wrappers alter arguments.

These checks do not certify live WordPress/MySQL integration, every installed plugin version, or arbitrary future model behavior. No scheduled AI runner, automatic publication, full competitor search service or causal SEO experiment system is introduced in this release.
