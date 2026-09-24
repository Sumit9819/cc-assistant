# CC Assistant 0.89.0 — implementation and site review

The tested source, desktop bridge and uploadable ZIP are ready. Install **cc-assistant-0.89.0.zip** on erofwhiterock.com and restart its Claude MCP connection. The final live check still reported backend **0.87.0**. The active desktop bridge is **0.89.0**. The separate C: testing site's WordPress backend was not upgraded by this work.

Package: `D:/cc-assistant/wp-content/plugins/cc-assistant-0.89.0.zip`

SHA-256: `686378162355a543e5a6f8470f729a073475433f08c0982ab33acba45d44c9b3`

## What changed

1. **Public authors.** Added `get_content_authors` and validated `author_id` selection for new posts/pages. Claude can save the author in its managed strategy using the exact observed display name. Missing, unavailable or renamed saved authors require a fresh selection; creation no longer silently uses the authenticated automation account. Author listing returns public IDs/names, without emails or login names. Your preference is the existing **ER of White Rock** organization author, observed ID **4**. This does not invent a physician or imply medical review.

2. **Ubersuggest research records.** Added `record_external_research`, with tool name, target, location/ID, language, capture time and optional provider update dates. `content_research.external_research_ids` attaches up to five actor-scoped saved records. Missing metrics stay absent; zero remains zero; dated estimates receive freshness labels. Invalid dates, unsupported provider tool names and extra payload fields are rejected. The provenance explicitly says Claude transcribed the observation: the WordPress server did not independently authenticate a Ubersuggest response. This prevents a submitted estimate from being represented as server-verified GSC data.

3. **Native SiteGround controls.** `inspect_plugin_capability(slug="sg-cachepress")` now exposes a reviewed native frontend adapter. `draft_update_plugin_setting(adapter="siteground_frontend_v1", ...)` can queue nine supported frontend toggles. It validates the installed implementation against reviewed official 7.8.1 source files and the current runtime registry, calls SiteGround's native setting method/cache purge, reads back the value and detects conflicts on apply/rollback. Unsupported operations remain refused. Gzip/server rules, dynamic cache, memcached, hosting CAPTCHA and multisite are outside this adapter. Calling a purge does not certify remote cache invalidation or improved page performance.

4. **An inspection bug.** SiteGround's toggle registry was read through class defaults; it now reads the current runtime value. A regression test detected this distinction.

5. **Shared-memory concurrency.** Shared-brain writes require an expected fingerprint under a database lock. Stale writers, missing fingerprints, corrupt stored data and storage failures return explicit errors. Chunked clients pass the returned fingerprint into the next request. Older bridges must be updated before pushing to a 0.89 backend. Selected-file sync still preserves unselected files.

6. **Review-only automation.** Added `bin/automation-runner.py`, private configuration and saved research/draft/verify phases. It reserves a per-run budget against a job budget, limits available tools, stores actual tool traces and resumes unfinished work. The bridge independently checks the phase allowlist and permits at most one workflow-bound draft-creation attempt per process. Lost write responses trigger read-only reconciliation rather than automatic redrafting. Only actual matching draft/verifier results can establish `review_ready`. Publication, approval, deletion, plugin-setting changes and messages are excluded. No scheduled task or paid background run was enabled. See [AUTOMATION.md](../../wp-content/plugins/cc-assistant/AUTOMATION.md).

7. **Complete browser bridge.** The active C: bridge was missing `browser-transport.mjs` and `browser/package.json`; its browser worker therefore stopped before connecting. Both are now installed. Direct HTTP received a SiteGround challenge, while the complete Chrome transport successfully read the site and queued the review proposals. White Rock's existing entries in both local MCP configuration files now select browser transport. A final read succeeded using the installed files and saved configuration, with no temporary override. Other site entries and credentials were preserved. Future CAPTCHA clearance can expire; this did not disable SiteGround protection.

## Changes already saved on the site

- Saved and read back your preference for practical SEO-focused content that fits the current site, with no magazine/editorial-style layouts or opinion essays. The same preference and author/research rules were recorded in the active Claude project memory and the White Rock design skill. Existing layouts were preserved.
- Expanded Claude-managed scope from two to **seven inspected service anchors**: service hub 3505, laboratory 971, dehydration 1072, chest pain 2516, pediatric care 2453, fractures 1374 and gastrointestinal evaluation 799. There are **nine reader questions**. Existing audience/exclusions were preserved. Final read-back showed matching source hashes. These anchors support topic exploration; their operational claims are not independently certified facts.
- Synced only `memory/feedback_erofwhiterock_current_evidence_policy.md`; the server verified its selected hash and reported zero deletions.
- Queued **1724** to change draft **5525** from `adminsumit` to the existing **ER of White Rock** author. The correction is pending, not applied. The existing publication proposal is **1723**; no post was published.
- Queued **1725–1728** for four specific lab-page widget changes on page **971**: replace unsupported flawless-results/fixed-turnaround copy with a sourced explanation; rename both faces of the “Fast & Flawless Results” card; remove guaranteed immediate treatment/instant-review language; and remove an instant-diagnosis promise. Widget structure and styling are preserved. The new explanatory wording follows [MedlinePlus's discussion of interpreting lab results](https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/).

[Review pending changes](https://erofwhiterock.com/wp-admin/admin.php?page=cc-assistant-pending). [Preview draft 5525](https://erofwhiterock.com/?p=5525&preview=true).

An upgrade or intervening edit can make an older proposal's evidence stale. Review the author correction before publication; if a guard refuses an old proposal, Claude should inspect the existing draft and refresh the affected proposal, preserving post 5525 rather than creating a duplicate. Refresh the strategy/author selection after installing 0.89, because the new backend contract is not present on 0.87.

## What Ubersuggest actually showed

The existing Ubersuggest connection worked through the native Claude client and its normal OAuth flow. No new account, project or article was created. Actual tool calls and responses are saved in the private preflight report, separately from the model's interpretation. The provider advertises keyword, competitor, content and traffic capabilities in its [MCP documentation](https://app.neilpatel.com/en/mcp).

| Query | US estimate | Dallas estimate | Interpretation |
|---|---:|---:|---|
| what to bring to emergency room | 110 | 10 | Potential preparation topic; inspect existing coverage and distinguish a broader visit guide from the medication/symptom-note draft. |
| emergency room lab tests | Not supplied | 0 | Missing national data and a local zero estimate do not prove no useful reader need. |
| when to go to er for dehydration | 320 | 10 | Relevant to a current service anchor; inspect the existing service page and support clinical distinctions before drafting. |

These are provider estimates, not observed Search Console counts. Dallas rows carried older update timestamps, approximately March 2026; the national rows also had their own update dates. Keyword variants must not be added as independent demand. Two additional read tools were denied by the CLI allowlist, so their missing results were not inferred.

The overlapping-domain results mixed plausible local comparisons, large healthcare publishers and unrelated businesses. They are discovery candidates. Claude must confirm service/geographic relevance and inspect actual comparable pages before calling them competitors or claiming an information gap. A useful addition can be a supported decision aid, practical preparation checklist, clearer explanation or documented facility process; extra statistics or different wording do not by themselves establish unique value. This approach follows [Google's helpful-content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content); ordinary SEO practices also remain relevant to [Google's AI search features](https://developers.google.com/search/docs/appearance/ai-features).

## Corrected finding and factual review still needed

**Service-hub placeholder correction:** page 3505 contains 22 “Lorem ipsum” occurrences in its legacy WordPress body, but zero in the current Elementor tree. The earlier observation was too broad if interpreted as a visible-page defect. Current builder content must be distinguished from legacy storage; this check does not independently certify every rendered state.

The four lab proposals address specific unsupported wording, not every factual issue on the website. Facility accreditation, exact turnaround/wait-time promises, available tests, treatment capabilities and pricing need current business evidence. Timeline article 5437 contains precise stage and overall visit timings; these should be supported by an identified measurement period and appropriate qualifications before being reused as facility facts. Pediatric and fracture pages also contain immediate-care and broad-capability claims requiring confirmation.

Chest-pain page 2516 makes broad legal-protection claims about freestanding ERs. Applicability should be reviewed against the facility's actual status and insurance situation. The [CMS EMTALA overview](https://www.cms.gov/medicare/regulations-guidance/legislation/emergency-medical-treatment-labor-act) and [CMS medical-bill rights guidance](https://www.cms.gov/medical-bill-rights) are starting sources, not proof that every claim on this specific page applies. No legal conclusion or replacement promise was invented.

## Validation and limits

- **190 PHP syntax checks** and **46 PHP regression files** passed. New coverage includes author selection, provider schema/date validation using the installed WordPress schema validator, actor isolation, native setting apply/rollback, missing or altered native code/registry, shared-brain conflicts/corruption and Python bridge distribution.
- Seven Python runner-state tests passed, including lost write responses, dry runs, mismatched verification and actual review-ready records. A separate real-stdio test confirmed the bridge refuses unlisted tools, non-post creation and repeat creation attempts without making network calls. Seven Python files compiled successfully.
- MCP initialization and tool listing passed with **176 unique tools** and valid object schemas.
- Two actual Claude CLI fixture trials passed: requested public author/practical draft, and stale/zero/missing provider evidence. Trial costs reported by the client were $0.408728 and $0.163877. These use simulated WordPress responses; they are behavior checks, not guarantees about all future model actions or a rendered-page visual test.
- Live browser reads, memory update/read-back, scope update/read-back, selected brain sync and five queued corrections succeeded against backend 0.87. New 0.89 backend author/provider/native/CAS behavior is tested locally and still needs post-install live verification. No native SiteGround toggle was changed during testing.
- ZIP has **155 files**, valid CRCs, forward-slash paths and verified required runtime file bytes; tests, dependency folders and private connection/job files are excluded. Source and previous active bridge backups, change hashes and test evidence accompany this report.

## Installation and operating state

1. Install the supplied 0.89 ZIP on erofwhiterock.com and restart Claude's MCP connection. The local source and complete bridge are already updated.
2. Let Claude refresh the existing strategy and save author ID 4 only after confirming the current display name/capability through `get_content_authors`. It should reuse your saved preferences, inspect the existing draft and preserve current exclusions.
3. Review the actual author and lab-copy proposals, then check saved and served output after application. Clinical/business/legal facts needing independent confirmation are not certified by a passing technical checklist.
4. Choose cadence and spending limits before unattended execution. Prepared private configuration: `C:/Users/sumit/.cc-assistant/automation/erofwhiterock/config.json`. It is **disabled**, with example limits of $1/run and $3/job. No scheduler exists and no unattended job has run. Each invocation advances one phase; the computer, Claude authentication and network must be available. CLI cost limits are estimates and external provider credits are separate. [Claude's programmatic-use documentation](https://code.claude.com/docs/en/headless) explains the native execution model.

Private live observations, full before/after notes and research traces are under `D:/cc-assistant/reports/preflight-0.89.0-2026-09-09`. The release manifest identifies every source change and the package hash. No ranking increase, permanent CAPTCHA clearance, global originality, full-site factual accuracy or autonomous clinical review is claimed.
