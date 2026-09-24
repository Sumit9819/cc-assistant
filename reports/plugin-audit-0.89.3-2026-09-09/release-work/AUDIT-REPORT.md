# CC Assistant plugin audit — 0.89.3

Audit date: 9 September 2026. Source reviewed: `D:\cc-assistant\wp-content\plugins\cc-assistant`, version 0.89.2. Corrected release: **0.89.3**.

The audit found confirmed defects in publication, proposal concurrency, evidence handling, accessibility scanning, transport, distribution and cleanup. The existing 48 PHP regression files passed before changes; they did not cover several of these interactions. The corrected release passes 56 PHP regression files plus additional integration checks.

This was a repository-wide automated scan with targeted manual review and isolated behavioral tests, not a proof that every execution path is defect-free. Production checks were limited to identity/version and the pending inbox. No production article, setting, approval, rejection or publication was changed during this audit.

## Fixed findings

Severity reflects practical impact and priority, not an externally assigned vulnerability score.

| Priority | Finding and trigger | Correction |
|---|---|---|
| High | `meta_update` could write `post_status=publish` through `wp_update_post`, skipping the dedicated publication handler's quality and permalink safeguards. Future/trash/arbitrary status values also used this generic route. | Generic status edits accept only draft, pending and private, at both proposal and apply time. Publishing and trashing require dedicated proposals; scheduling is unsupported. Previously queued generic publication is also refused. |
| High | Two concurrent queue requests could each supersede the other: the candidate query selected any ID except itself. A candidate claimed by a reviewer during the read could also be hidden. | Superseding selects strictly older IDs and updates only still-pending rows. Notices list only rows actually superseded by this proposal. |
| Medium | Slow rendered-baseline capture occurred after evidence validation. A post could change during that fetch, leaving a newly stored proposal immediately stale and potentially hiding earlier work. | Revalidate server-owned evidence after preparation and before insertion or superseding. |
| High | Initial workflow-backed draft creation checked source freshness only at creation. Later service/source changes could escape the approval guard if the draft itself stayed unchanged. | Initial publication proposals now persist the workflow's source, strategy and context basis, which is checked again at approval. |
| Medium | Workflow verification could report record integrity passed for a matching pending row with no server evidence or a malformed publication payload. | Require the draft's server fingerprint, environment evidence, correct publication intent and consistent refreshed workflow ID. Missing evidence remains an actionable unknown. |
| Medium | A publication refresh could commit successfully, lose its read-back, then restore the old workflow binding against the new proposal baseline. | Restore the old binding only when the original row is confirmed unchanged. An uncertain write retains its prospective matching binding and returns an explicit reconciliation error. |
| Medium | `pre_publish_check` returned the advisory checklist but omitted the different quality gate actually used at publication. This could give Claude apparently conflicting results. | Return `publication_gate` alongside the checklist, explicitly label their scopes, and make no publication-verification claim. |
| Medium | `widget_schema(post_id, widget_id)` silently returned the generic registry when `widget_type` was omitted. Incomplete targets were also discarded. | Resolve the saved element's actual type automatically; return its schema/effective settings. Partial or missing targets produce explicit errors. The bridge forwards supplied target fields. |
| High | Accessibility scanning clicked submit buttons. A preventDefault submit listener could not stop custom click/AJAX handlers from sending a message or another request. | Inspect form markup passively, without clicks, submission or validation-event dispatch. Submission/error-announcement behavior is reported as untested. A real browser fixture verifies zero events and requests. |
| Medium | Four older server audit fetchers and the desktop accessibility sitemap fetch disabled HTTPS certificate verification. They could follow a redirect and inspect different content. | Enable certificate verification and disable automatic redirects in those paths. Bound server response sizes. The desktop cURL path can use the native CA store. |
| Medium | The rendered-content warm cache accepted HTTP 200 CAPTCHA HTML as post content and accepted redirect responses. | Require usable complete HTML for the requested page, reject recognized challenges, redirects and oversized responses, and leave failed observations unavailable. |
| Medium | Bridge synchronization distributed top-level PHP/browser files but omitted the accessibility JavaScript runtime. Fresh installs could lack it; existing installs could keep stale code. | Include and hash all accessibility runtime assets; validate JavaScript syntax before replacement. ZIP construction checks that required assets exist. |
| Low | Opt-in uninstall used unescaped underscores in its option-prefix match, omitted the page-facts table, and cleared only cron events with empty arguments. | Escape the prefix, include page facts and verification schedules, and remove all argument variants of listed hooks. The default still retains data. |
| Low | The dedicated operator account could access other plugin reads but received forbidden responses from review-health inspection. | Apply the shared operator/admin read permission to that endpoint. |
| Integration | The Local Sites workspace contained a v0.81 Claude hook that accepted inadequate observations. The D: workspace held the corrected v0.83 hook. | Synchronize the Local Sites file to the tested D: copy, with a backup. The D: project has hook registration; the Local Sites project currently has no hook registration. This audit does not enable new automatic stop/push behavior. |
| Documentation | The FAQ claimed every tool only queued changes and could not write uploads or other plugin settings. Existing tools have broader documented side effects. | Explain direct draft/research/memory/media/maintenance actions and distinguish them from reviewed publication. Update relevant shared MCP descriptions. |

The concurrency and generic-publication regressions were observed failing against the original code before the fixes. The hook regression failed against the old workspace copy and passed against the corrected copy. Other fixes have targeted behavioral coverage or were verified through source/contract inspection as described below.

## Validation

- **202 PHP files** passed syntax validation; **56 PHP regression files** passed.
- Registered **182 REST handlers**, across 14 REST modules plus the review-health route: callbacks exist, permissions deny anonymous users, and method/path pairs are unique.
- Approval permission tests reject Application Password authentication and permit a valid interactive administrator session. This does not mean every administrative tool is approval-only: the separately documented rejection and maintenance tools have their own side effects.
- **177 unique MCP tools** passed actual stdio initialization/catalog checks at bridge version 0.89.3.
- Seven Python automation-state tests, actual bridge tool restrictions/one-create-attempt checks, Claude hook evidence checks, Python compilation and JavaScript syntax checks passed.
- A real headless Chrome fixture checked native-required, aria-required and optional forms without click, submit or invalid events and without network requests.
- Targeted tests cover generic status rejection, concurrent superseding, queue-time state drift, initial publication source drift, missing/stale workflow proof, refresh read-back failure, widget resolution, rendered CAPTCHA rejection, bridge asset installation and opt-in cleanup.
- Final ZIP: **158 shipped files**. Archive integrity and file-by-file comparisons are recorded in `release-manifest.json`. Tests and generated SQLite fixtures are excluded from the ZIP.

Tests use isolated WordPress doubles/SQLite where needed. They do not replace full MySQL/WordPress/Elementor integration tests across supported versions. No paid Claude evaluation was run during this audit. Existing automation remains disabled.

The read-only production check succeeded through the configured connection: **erofwhiterock.com runs 0.89.2; pending count is 0**. This check establishes connection/version/inbox state, not page rendering, rankings or production execution of the new fixes.

## Remaining improvements

1. **Add a real WordPress integration test matrix.** Exercise MySQL, persistent object cache, PHP/WordPress versions, Elementor, Rank Math and Polylang. Prioritize create → inspect → queue → bulk approve → verify → rollback, including interrupted requests and edits from the native editor. Current plugin locks coordinate plugin writes; other WordPress writers do not share those locks.
2. **Introduce explicit request idempotency and repair for incomplete creation.** The runner refuses automatic duplicate creates after uncertainty, but a lost response or failed queue/binding still needs inspection. A server-side operation ID and a narrowly scoped repair workflow would improve recovery for all draft/page types. Existing publication refresh remains limited to supported workflow-bound blog drafts.
3. **Split the largest modules without changing behavior.** `class-rest-api.php`, `class-apply.php`, `class-pre-publish.php` and `class-seo-tools.php` contain many interacting responsibilities. Move validated handlers into smaller modules and generate transport/schema contracts from one definition. Keep existing regression cases during that work.
4. **Continue calibrating SEO judgments.** Checklist thresholds, lexical similarity and surface signals remain heuristics. They cannot establish factual accuracy, harmful cannibalization, originality, medical expertise or ranking potential. Consolidation should require inspected intent, unique contributions, links and search evidence; missing GSC data must remain distinct from no opportunity. Use repeatable contradiction cases to evaluate Claude's interpretation of these tools.
5. **Broaden evidence coverage carefully.** Dynamic widgets, third-party plugin settings and factual claims still need explicit unknowns and rendered/source checks. An observed storage value is not proof that a plugin feature executed correctly. SiteGround's native adapter intentionally refuses unreviewed implementations.
6. **Make client installation health visible.** Report the actual running bridge, runtime assets and local hook registration/version together. Updating the WordPress ZIP cannot update every workstation or register Claude hooks there. This audit repaired the local copies; it did not enable new scheduling or automatic memory publication.
7. **Add controlled form testing separately.** End-to-end form delivery and error announcements require an isolated destination or an explicitly authorized test submission. The general accessibility scan now leaves those behaviors unknown.

These are follow-up engineering and validation priorities, not claims that the current site's content is defective. A passed plugin audit is not a medical review, accessibility certification or SEO ranking guarantee.

## Deployment and recovery

Install `D:\cc-assistant\wp-content\plugins\cc-assistant-0.89.3.zip` through the normal WordPress plugin update flow, then restart the Claude MCP connection so the updated bridge loads. Canonical D: source and the Local Sites bridge are updated locally; the Local Sites WordPress backend itself is not upgraded by this audit.

After installation, run `whoami` and inspect current state before rebuilding any proposal. A deployment changes the environment fingerprint, so proposals created before the update may correctly need fresh evidence. The inspected live inbox is empty, so there was no pending publication to retry.

Private backups include the previous canonical source, active bridge and old workspace hook. `release-manifest.json` records hashes, changed files and verification results. Preserve these when investigating an update problem; do not reapply a write solely because its response was lost.

For the WordPress behaviors used in these fixes: [wp_clear_scheduled_hook matches the supplied argument set](https://developer.wordpress.org/reference/functions/wp_clear_scheduled_hook/), while [wp_unschedule_hook removes all events for a hook](https://developer.wordpress.org/reference/functions/wp_unschedule_hook/). [The WordPress HTTP API documents certificate verification and redirect controls](https://developer.wordpress.org/reference/functions/wp_remote_get/).
