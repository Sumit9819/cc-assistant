# CC Assistant 0.88.0 implementation report — 2026-09-09

The source release and desktop bridge are updated to 0.88.0. Install `D:\cc-assistant\wp-content\plugins\cc-assistant-0.88.0.zip` on erofwhiterock.com, then restart its Claude MCP connection. The live WordPress backend was 0.87.0 during verification. The C: testing site's WordPress backend remains 0.81.3; only its active bridge is updated. This chat's already-running connector cannot reload new PHP code automatically.

## Fixed and added

1. **Strategy falsely becoming stale after session logging.** Context now hashes site identity and durable Rules/Decisions/preamble; routine structured Sessions entries are excluded. Unstructured legacy constraints remain included. Source changes and lasting corrections still invalidate old evidence. Existing profiles/workflows require a fresh review under the new context version.
2. **Utility pages being treated as services.** Careers, billing, HIPAA, accessibility, medical disclaimers, branded About pages and common translated utility pages are classified as context instead of automatic service anchors. A regression caught and corrected an overly broad rule that excluded contact dermatitis. Discovery remains heuristic and saved explicit selections remain reviewable.
3. **Research requiring an article to already exist.** `content_research` now supports a prospective topic, current niche anchor, reader goal and proposed contribution. It collects up to three competitor and three primary-source excerpts, reports source-role uncertainty and paginated existing-content candidates, and saves an actor-scoped record. GSC is not a prerequisite. It does not claim rankings, search volume, verified facts or global originality.
4. **Conflicting guidance across entry points.** whoami/get_site_memory now expose memory-consistency review prompts with rule IDs, excerpts and hashes. Session preflight, bootstrap, workflow and tool descriptions distinguish current intent from historical jobs and local heuristic preferences from verified defects. Legacy E-E-A-T and pre-publish lint descriptions no longer present citation/byline thresholds as Google requirements.
5. **Unsafe whole-dataset memory recommendations.** `operator_brain_push(paths=[...])` uploads only exact selected local files, preserves unselected remote files and verifies selected hashes. Empty, unknown and invalid selections cannot silently become full sync. Omitting paths retains legacy whole-brain behavior, so current guidance recommends deliberate selection. Timestamp differences do not establish which instructions are correct.
6. **Evaluation harness path handling.** Relative output directories now resolve before launching Claude, preventing settings-file lookup failures after changing the subprocess working directory. The memory grader now recognizes the valid phrase “do not establish”; original traces and the grading correction remain recorded.

## Changes already made to your environment and live metadata

- Reconciled twelve local instruction/memory files across D: and the active C: Claude project. Preserved no-cloning, physician-consent, language/category and actual-service constraints. Corrected both project guides' unsupported SiteGround-wide IP diagnosis and obsolete ad-hoc browser instructions.
- Backed up the full original live notes, applied targeted durable-guidance corrections and read back the exact result. The entire Sessions history is preserved. Older historical decisions remain available; current guidance explicitly governs conflicting recipes.
- Synced only three reviewed files to the site's shared brain: current site evidence policy, no-provider-bylines constraint and no-cloning constraint. The server verified their selected hashes. No remote files were deleted and no unrelated client knowledge was uploaded.
- Saved initial Claude-managed content scope using inspected service-hub page 3505 and laboratory page 971, an audience, exclusions and four reader questions. These are editorial anchors; stored service claims are not independently certified facts. Claude can expand this scope from additional inspected service sources.

## Live pilot

Created **“What to Tell the ER Team: A Medication and Symptom Note”**, English draft **5525**, pending publication proposal **1723**.

- Preview: https://erofwhiterock.com/?p=5525&preview=true
- Edit: https://erofwhiterock.com/wp-admin/post.php?post=5525&action=edit
- Review inbox: https://erofwhiterock.com/wp-admin/admin.php?page=cc-assistant-pending
- Workflow: `workflow-87ad22b181a57ee39ecea94e57aec221`.
- Inspected all 38 blog titles and the full related ER-timeline article 5437 before selecting the communication-note task. This is a distinct proposed reader aid, not a claim of market-wide novelty.
- The dry run passed 13 lint checks. `get_post` returned the actual saved article in draft status. `verify_content_workflow` returned `record_integrity_pass=true`, `draft_and_pending_record_verified`, current source basis and no record issues. The pending proposal was still pending. Nothing was approved or published.
- Category 123 and English were supplied on creation. The record verifier does not independently certify category suitability, medical accuracy, layout, indexing or rankings. Review the draft in WordPress before publication. No featured image or independent clinical review is claimed.
- This pilot verified the installed **0.87.0** draft workflow. New 0.88 research/context behavior was tested with production PHP classes and isolated Claude fixtures, and still needs live verification after installation. Refresh old strategy/workflow evidence after upgrading; preserve draft 5525 and avoid creating a duplicate merely because its old workflow becomes stale.

Sources used for the draft: [MedlinePlus emergency guidance](https://medlineplus.gov/ency/article/001927.htm), [medication safety](https://medlineplus.gov/ency/patientinstructions/000501.htm), and [AHRQ discharge guidance](https://psnet.ahrq.gov/primer/readmissions-and-adverse-events-after-discharge). Sources were paraphrased, with no invented provider attribution or operational promises.

## Validation

- 181 PHP syntax checks and 43 PHP regression files passed. New cases cover session-vs-rule freshness, legacy constraints, utility-page classification, prospective research, stale/invalid anchors, exclusions, fetch budgets, unsafe URLs, actor isolation and selected-file synchronization.
- Actual stdio initialization/tools-list succeeded: **174 tools**, unique names, valid object schemas. Null selected-memory scope was rejected without network activity. New features extend existing tools rather than adding more names.
- **10 completed Claude CLI trials across 8 scenarios** passed the evaluated behaviors: prospective research, conflicting memory, no-GSC drafting, complementary-page decisions, CAPTCHA uncertainty, unsupported settings, plan-only status and stale source recovery. One initial phrase-grader false negative was corrected and documented; two additional memory trials passed. An earlier harness launch failed before evaluation because relative settings paths were wrong; that defect is fixed.
- These were isolated simulator trials with real schemas/contracts and no live site credentials. They do not guarantee arbitrary future model behavior. The harness disables inherited local memory/hooks, so live instruction corrections were inspected and verified separately.
- ZIP structure, CRC, required-file hashes, PHP syntax and no-BOM gates passed. The package excludes tests, fixture databases and dependency folders. Exact package hash, change manifest and source/bridge backups accompany this report.

## Remaining work, in order

1. Install 0.88.0 and restart Claude's MCP connection. Let Claude refresh the saved strategy against the new context version and exercise prospective research against real external sources. The operator reviews the result; no strategy form is required.
2. Review the pilot and the site's factual claims. The stored service hub contains placeholder text; the lab and ER-timeline copy also contain precise turnaround promises and sweeping accuracy language that need supporting evidence. These are observations requiring content review, not a verified Google penalty. The service-hub HTML audit returned nine passes and six unknowns, including accuracy, indexing and browser appearance; it did not establish that everything is correct.
3. Broaden the curated scope and audit additional site-specific skills/memories. This release reconciles identified conflicting guidance, not every file or historical assertion in all client projects.
4. Add native execution adapters for plugin features that generic settings cannot reproduce, starting with SiteGround Optimizer. Add externally observed SERP/competitor discovery and richer primary-source claim review if those data sources are available.
5. Add a separately configured, budgeted scheduled Claude runner with resumable jobs and review boundaries if unattended operation is desired. Current workflows execute while a connected Claude session runs; the plugin does not launch a background model by itself.
6. Add server-side compare-and-swap/locking to shared-brain updates before relying on concurrent writers. Current selected sync limits what is sent and verifies hashes but does not guarantee serialization against another machine changing the same store.

Authenticated HTTP calls worked during this session; SiteGround clearance can change. The integrated browser transport remains available through the existing connection setup. A successful API call does not prove every page fetch or future connection will succeed.

The SEO guidance follows [Google's helpful-content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content) and [AI features guidance](https://developers.google.com/search/docs/appearance/ai-features): assess supported reader value and ordinary search fundamentals instead of treating fixed quotas as ranking requirements.

Private before/after site evidence and instruction backups are under `D:\cc-assistant\reports\preflight-0.88.0-2026-09-09`. Source and bridge rollback ZIPs are stored with this report. The live website was not upgraded automatically.
