# CC Assistant 0.87.0 implementation report

This is the reliability release: stronger evidence, consistent instructions, current plugin capability inspection and verification of actual saved drafts. Source and desktop bridge are prepared for matching version 0.87.0. The website ZIP must still be installed on erofwhiterock.com.

## Fixed

1. **MCP discovery failure.** `get_content_scope` advertised `inputSchema.properties` as `[]`. The actual Claude CLI rejected tools/list. It now emits `{}`, with catalog-wide schema checks.
2. **Conflicting instructions.** Capability summaries, tool descriptions, short/full SEO policy and content policy now read one versioned contract. Schema definitions live in one catalog, preserving all existing tool names plus two additions.
3. **False certainty in page audits.** `page_robustness_audit` now runs the existing fresh verified-page evidence engine. Old CTA prescriptions, word-count rules, citation quotas and uncalibrated score/pass verdicts are retired. Blocked/unavailable evidence remains unknown.
4. **Misleading AI measurement labels.** Low-CTR candidates are no longer called AI-absorbed queries. Snapshot versioning prevents comparisons with incompatible older measurements. Failed SQL measurements are explicit failures rather than zero observations.
5. **Business assumptions.** whoami no longer equates inferred emergency mode with verified 24/7 hours, service claims or medical schema eligibility.
6. **Incomplete setting changes.** Direct writes to SiteGround optimizer options are rejected because they cannot reproduce native server-rule and cache actions. A native execution adapter remains future work; unrelated generic registered settings retain validation and readback.
7. **Draft/result integrity.** New workflow bindings are protected from generic metadata edits and excluded from content hashes so their bookkeeping does not invalidate the draft's own pending proposal. Queue/binding failures report existing IDs for inspection before retries.

## Added

**inspect_plugin_capability:** exact installed/active/version evidence, optional proposed-value validation, WordPress registered constraints, Elementor controls/defaults/conditions, Rank Math registered module states, SiteGround native toggle registry, and Polylang language relationships. Runtime coverage is partial; unavailable controls, licenses and rendered effects remain unknown. Stored secrets and schema defaults are filtered.

**verify_content_workflow:** verifies a server-bound blog draft and matching pending publication proposal for the current account. Checks current source hashes, strategy revision and site notes; nonempty saved title/content; actual post/proposal status; and actual IDs. Rejects unrelated/cross-account records and does not confuse a plan with execution. Use `draft_create_post(post_type="post", workflow_id=...)` before verification. Editorial quality, factual truth, clinical accuracy, category suitability and rendered/publication results remain separate checks.

## Validation

- 178 PHP files passed syntax checks; **41 PHP regression files passed**.
- Real MCP stdio initialize/tools-list passed: **0.87.0, 174 distinct tools**, valid object-shaped properties, workflow binding parameter, no stderr.
- **12 actual Claude trials passed** after trace-based grading: two each for drafting without GSC, complementary articles, CAPTCHA, unsupported settings, prepared-only status, and stale source recovery.
- Claude trials used the authenticated local CLI against an isolated MCP simulator with production tool schemas and policy. They did not call the live website. The initial diagnostic failures discovered the actual schema defect. Two first-pass grading failures were a simulator error that counted `dry_run=true` validation as setting writes; the saved traces prove no write attempt, and the grader was corrected. Full correction details are in agent-eval-results.json.
- Final trials reported about **$2.14** in CLI-estimated usage (diagnostic attempts are additional). This is a CLI estimate, not a billing claim.
- ZIP checked for CRC, duplicate entries, required dependencies, safe WordPress paths, excluded development files and byte-for-byte agreement with reviewed source. 148 package files; SHA-256 `4e3da13fa241d03041973f25151668a3e9c26bbb2900becec3595b32926e033d`.

## Compatibility and remaining work

- Install the ZIP and restart the Claude MCP connection. Existing credentials and site configuration do not need re-entry. The desktop bridge files are updated locally, but its running process needs restart.
- Legacy robustness `score`/`pass` are now null. AEO consumers must use `low_ctr_candidates_28d`, `search_appearance_observations` and measurement version `observations-2`.
- Version 0.86 workflow records lack source bindings. Prepare a current workflow for a new blog; existing unbound drafts cannot be retroactively certified by this verifier.
- This is not an unattended runner or automatic publishing system. Advanced competitor discovery, richer opportunity scoring, retry-safe job orchestration and outcome experiments remain subsequent releases.
- No live site content/settings were changed in this implementation. Live WordPress/MySQL integration and every installed plugin/version were not certified. Two repetitions per scenario are regression evidence, not a guarantee that a language model will never make a mistake.

Detailed workflow and compatibility notes: RELIABILITY.md. Evidence files: changed-files.json, release-validation.json, test-results.json, mcp-smoke-results.json, agent-eval-results.json and per-trial traces.
