# CC Assistant improvement roadmap

### Recommended direction

CC Assistant should become a more dependable WordPress operator by improving the evidence behind its answers, the accuracy of its plugin knowledge, and the integrity of its SEO data. The highest-value next release is a reliability release. Adding more advisory tools before correcting conflicting interpretations would increase the number of ways an assistant can reach an unsupported conclusion.

The immediate priorities are to correct Search Console data handling, remove unjustified AI-search diagnoses, make audit comparisons complete, and document which operations require human approval. The next major feature should be a unified evidence report that distinguishes stored settings, actual page output, browser behavior and Google's observations. Plugin-specific capability adapters and coordinated change sets should follow.

### Current baseline

The installed source is version 0.84.0. It already contains 164 MCP tool definitions, an approval inbox, snapshots and rollback, Elementor inspection and editing, plugin-option discovery, Search Console integration including URL Inspection, a local search-data warehouse, multilingual tools, browser accessibility checks, and several SEO reports. These capabilities should be connected and strengthened rather than rebuilt.[^37]

The 0.84.0 evidence gate requires recent authenticated identity and target observations before covered REST proposals. It checks post and selected environment hashes again at approval. The verified page audit uses explicit rule IDs and separates pass, fail, review and unknown. This is a useful foundation, but its authority is limited to the evidence actually collected.[^1]

The recorded September 8 verification on erofwhiterock.com found two matching homepage audits: 9 passes, no failures, no review findings and 6 unknowns. Both fetched usable HTTP 200 HTML with cache MISS. This supports repeatability for those homepage checks at that time; it does not certify the entire site, browser appearance, Google indexing or every installed plugin.[^2]

| Area | Already available | Most useful improvement |
|---|---|---|
| Reliable audits | Deterministic rules and recent-read gate | Durable evidence history and claim-level scope |
| Plugin knowledge | Installed inventory, option reads, Elementor schemas | Version-aware, tested capability adapters |
| Safe editing | Proposals, approval, rollback and concurrency checks | Coordinated change sets and retry-safe operations |
| SEO | GSC, URL Inspection, schema and link tools | Correct data lineage and a unified evidence report |
| Accessibility | Real-browser WCAG-oriented sweep | Evidence integration and broader interaction coverage |
| Connection | HTTP and browser transport | Clear authentication/CAPTCHA diagnostics |

### Scope and confidence

This roadmap prioritizes operation of existing sites; public distribution is a later track. Code-confirmed findings and isolated reproductions are identified separately from recommendations. The latest live baseline is September 8, 2026, and the external documentation cutoff is September 9. No new full-site crawl or production mutation is part of this assessment.




## Defects and misleading contracts to address first

### Search Console data integrity

**Confirmed interpretation defect:** `ai_overview_pages()` treats every non-empty, non-“Web” search-appearance bucket as AI-related activity. `aio_ctr_drop_alert()` uses that same condition to assign “confirmed” to suspected AI cannibalization. An ordinary rich-result appearance is therefore sufficient to trigger an AI-specific label. A low CTR does not establish its cause, even when an AI impression has independently been observed.[^3]

**Confirmed storage defect:** `store_rows_for_date()` distributes page/query clicks and impressions across appearance buckets using the page-level impression proportions. This manufactures a query-by-appearance breakdown instead of preserving what Google returned. It also rounds every allocated bucket independently. An isolated invocation of the installed method, with a database double, converted one input click and impression into two stored clicks and impressions when two buckets had equal weight.[^4]

The repair should preserve unmodified query facts and appearance facts at their original granularity in separate datasets. Never join them into alleged observations by proportional allocation. If estimated allocations remain useful, label them as estimates, keep them outside measured totals and preserve their model version. Historical inferred rows need a migration strategy: re-fetch available dates, recover suitable raw warehouse data where present, and label unrecoverable history. Accurate-looking reports must not silently mix the two.

### Contradictory SEO instructions

The short SEO playbook still promotes a percentage claim that its longer section says was not verified. Other descriptions assert that particular CTR patterns reveal AI absorption or citation, or that a title/description rewrite is almost always the solution. These conflicting instructions can prime Claude to give incompatible answers even when its input data is unchanged.[^3]

Replace the scattered playbook rules with one versioned policy registry. Each entry should distinguish documented platform behavior, a measured site observation, a heuristic and an editorial preference. Recommendations need supporting evidence, alternatives and an expiry/review date. This also applies to arbitrary title-length windows, “quality” scores, citation-domain preferences and claims about ranking mechanisms.

### Audit comparisons and approvals

**Reproduced comparison defect:** removing a failing rule from a newer audit can yield `unchanged_findings` with an empty change list. The current comparator visits only the new findings. It separately flags a rules-version change, but fails to explain which coverage disappeared. Compare the union of old and new rule IDs, distinguishing added, removed, changed and no-longer-comparable checks.[^5][^4]

**Confirmed documentation mismatch:** the README says every proposed change is queued and that uploads cannot be modified. Media upload/deletion and some taxonomy handlers mutate directly. Media deletion includes useful reference checks and explicit parameters, but an assistant-supplied `confirm=true` is different from a human approval in the inbox.[^6]

Publish an operation registry showing permissions, side effects, approval requirements and recovery limits. Consider a human-approved deletion manifest or recoverable quarantine. Existing references can be rechecked immediately before deletion; a permanent deletion should never be presented as snapshot-reversible.

The README also advertises “Tested up to: 7.0.” Available release records establish the narrower fixture described earlier. Add a reproducible compatibility matrix or narrow the claim; absence of a test record is not proof that WordPress 7.0 fails.[^6][^38]


## Reliable answers through a shared evidence model

### A claim should identify what was actually checked

Introduce a persistent evidence ledger that extends the current recent-read gate and latest-report storage. Every observation should record the site fingerprint, target and resolved URL, capture time, collector and rules versions, environment fingerprint, raw-evidence reference, cache state and collection errors. Keep several historical snapshots with configurable retention; a “latest result” remains a convenient view.[^1]

Separate four kinds of evidence. A stored Rank Math field answers what WordPress contains. A fetched HTML response answers what one request received. A browser capture answers what a particular viewport and interaction displayed. URL Inspection answers what Google's indexed-copy report says. These can legitimately disagree without any source being fabricated. Google expressly limits its Inspection API to the indexed version; it is not a live URL test.[^19]

| Claim | Minimum evidence | Appropriate outcome when missing |
|---|---|---|
| “The meta description is present” | Captured HTML, tag count and text | Unknown: capture unavailable |
| “The mobile button is usable” | Browser viewport and interaction result | Unknown: browser check absent |
| “Google selected this canonical” | Dated URL Inspection result | Unknown: Google evidence absent |
| “This plugin supports the option” | Installed runtime/schema or version-matched adapter | Unsupported or unverified |
| “The change worked” | Stored readback plus the required effect check | Saved; effect not yet verified |

### Consistency should be enforced below the chat layer

Use one deterministic evaluator for reusable factual checks. Every SEO summary, page dossier and status tool should consume the same findings instead of recalculating its own verdict. Expose concise structured results such as `claim_id`, `status`, `evidence_ids`, `scope`, `observed_at`, `limitations` and `next_check`. MCP output schemas and structured results provide a standard transport for such contracts.[^8]

Avoid a single confidence percentage unless it is calibrated against labeled cases. Specific states are more useful: verified, unknown, unsupported, stale, conflicting evidence and needs human review. A cache header that does not prove freshness should remain distinct from a verified MISS. Freshness policy should depend on the claim: current HTML and a 28-day performance dataset have different meanings.

When two reports differ, show the reason: changed content, changed environment, changed rules, changed collection conditions or genuine conflicting observations. “No new issue found in the checks completed” is a defensible result; “everything is correct” is usually beyond the available coverage.

### Plans and conversations need their own boundaries

The current gate scopes observations to the WordPress user and Application Password/browser session. Two Claude conversations using the same credential can share that scope. Add an explicit server-issued workflow handle, bound to the authenticated principal and site, with evidence IDs referenced by each plan. This improves isolation; it should not replace authentication.[^1][^34]

The plugin can prevent unsupported writes, return constrained findings and validate references in a submitted plan. It cannot guarantee that an external Claude client will never add unsupported prose. For the strongest reporting guarantee, render the final factual report from validated findings in WordPress and let Claude explain it. A report validator can flag claims that lack evidence before a plan reaches approval.


## Plugin knowledge and coordinated execution

### Build a small set of dependable adapters

Generic option discovery currently acknowledges that ownership can be heuristic and feature behavior or license entitlement can be unknown. That honesty should remain. Reading an option name does not establish its permitted values, its visible effect or whether a paid module is available.[^7]

Start with four adapters for the actual workflow: Elementor/Elementor Pro, Rank Math, Polylang and SiteGround Speed Optimizer. Each adapter should expose supported operations, installed versions, active modules, available runtime APIs, value constraints, dependency conditions, storage scope, effective output, affected targets and verification steps. Report license state only when a trustworthy API exposes it.

Elementor needs responsive settings, conditional controls, repeaters, dynamic tags, global styles and inherited kit/template values. Its official control system explicitly distinguishes many of these mechanisms. Extending the existing widget schema inspection is more useful than maintaining a static list of guessed controls.[^11]

Rank Math needs resolved metadata and ownership-aware schema handling. Its documented `getHead` endpoint returns generated head markup when Headless CMS support is enabled; it is a possible read adapter, not an unconditional API or a general setting-writing endpoint. Prefer existing supported runtime access when it avoids enabling unnecessary functionality.[^12]

Polylang should use detected language/translation functions and verify that translations actually resolve to the intended pages. Its function reference requires existence checks. Speed Optimizer needs installed-version inspection of cache capabilities and effects; a known option name alone should never be described as proof that the corresponding cache layer is active.[^13]

Keep a compatibility record for every supported adapter. After a plugin update, invalidate its capability snapshot and run targeted checks before allowing previously learned operations. Version-matched vendor documentation can explain the API, while installed runtime evidence establishes whether it exists on this site.

### Make a change plan an executable contract

A coordinated change set should declare its objective, target pages/settings, evidence IDs, before/after values, dependencies, impact scope, required approval and verification checks. Validate this contract before queuing it. If an adapter cannot verify a requested capability, return the missing fact and the exact next inspection.

The current whole-post hash protects against stale edits, but approving one change can invalidate other pending changes to the same page. Introduce a logical change set that validates against one base state and applies its related operations together. Do not weaken the existing safety check simply to make sequential approvals convenient.[^1]

WordPress operations can produce side effects outside one database transaction, including generated CSS, cache purges and external calls. Use explicit operation states and compensating recovery where true atomicity is impossible. After a partial failure, report what was saved, what was recovered and what still requires verification.

Add an idempotency key and an operation-status endpoint. If the connection drops after a write, the bridge should ask whether that operation completed before retrying it. Record separate states for proposed, approved, applied, stored-value verified and visible-effect verified. A successful database write alone should not finish a task whose objective is a visible page change.


## A unified technical SEO assessment

### Turn existing tools into one evidence-backed page report

URL Inspection, schema tools, link inventory and verified HTML auditing already exist. The opportunity is to combine them into a report with independent coverage states, then add collectors where the present report explicitly returns unknown. The six unknown categories in the latest homepage audit are an honest backlog, not six established SEO failures.[^1][^2][^37]

For each URL, show the intended indexability, observed HTTP response, robots directives, canonical target, sitemap membership, internal discovery paths, Google-selected canonical, last crawl and inspection time. Preserve the property and canonical mapping used for Search Console data. A page can serve healthy HTML today while Google's last observation still reflects an older failure.

The existing Inspection API integration should gain a scheduler that prioritizes changed, important and problematic URLs. Its documented per-property quota is 2,000 requests daily and 600 per minute. Cache dated results, honor quota errors and avoid spending the budget repeatedly inspecting unchanged pages. A missing or unauthorized property should return a specific coverage gap.[^19][^20]

### Validate crawl and URL relationships

Extend the existing link and redirect tools with a bounded crawl graph: robots evaluation, sitemap traversal, redirect chains, canonical relationships, alternate-language relationships and fresh destination status. Record request time and the final URL. Preserve URL differences until redirects or canonical evidence justify merging them; query parameters can represent different content.

Robots blocking and indexing exclusion are distinct. Google can know and display a blocked URL from links elsewhere, and cannot read a new page-level directive when crawling is prevented. The report should diagnose conflicting controls instead of recommending broad robots blocks as a generic deindexing fix.[^21]

Check whether internal links, sitemap entries and canonical declarations consistently point to the intended URLs. Treat canonical signals as evidence to investigate; a difference between the declared and Google-selected canonical is not automatically proof that an arbitrary rewrite is the right solution. Google's canonical and sitemap guidance should inform these checks.[^22][^23]

Multilingual validation should compare stored translation relationships with rendered language switchers and reciprocal hreflang annotations. Detect wrong-language canonicals, broken alternatives and inconsistent homepages. Polylang integration already exists, so the addition is cross-source verification of the relationship.[^24][^37]

### Schema ownership and eligibility

Extend the current syntax and schema-emitter tools into an entity graph. Record which component emits each entity, stable IDs, duplicate/conflicting properties and links between entities. Multiple JSON-LD blocks are not inherently wrong; contradictory representations of the same business are the useful defect to detect.

Validate structured facts against visible content and a verified business profile. Distinguish valid JSON, a valid vocabulary shape, compliance with a supported Google feature and actual search appearance. Google requires relevant, accurate, visible content, and syntactically valid markup does not guarantee a rich result.[^27]

For this emergency-care site, business identity, address, telephone, service availability and opening hours warrant explicit verification before propagation into schema. Do not infer clinical services or business type solely from a keyword or automatically inject a second business entity because an SEO score requests it.


## Performance, content and practical site quality

### Add performance evidence with honest scope

A shipped PageSpeed/CrUX collector was not identified in the inspected PHP/JavaScript sources. The verified audit explicitly leaves real-user Core Web Vitals unknown. Add a collector pair: repeatable Lighthouse/PageSpeed lab checks for diagnosis, and CrUX field observations for real-user experience. Integrate results into the same evidence model rather than a disconnected score.[^1]

Preserve mobile/desktop strategy, test environment and observation time for lab runs. For field data, show URL versus origin scope, form factor, collection period and availability. PageSpeed explains that lab simulations and historical field measurements can differ; a good lab score does not establish a good real-user experience.[^25]

CrUX aggregates a rolling 28-day window. A deployment cannot instantly replace that history. If page-level data is unavailable, label any origin fallback and do not present it as a measurement of the page. Insufficient data should remain unknown.[^26]

Use the existing asset and browser tooling to investigate the actual bottleneck: oversized hero media, unnecessary above-the-fold work, layout shifts, delayed interactions, fonts or conflicting optimization features. Compare repeated runs under comparable conditions before recommending changes. An automatic “enable every optimization” action would be especially unsuitable where multiple plugins already defer or cache assets.

### Extend the browser audit into an interaction check

Connect screenshots, DOM observations and accessibility findings to page/element IDs and the exact build being assessed. Add viewport coverage for navigation, sticky headers, consent banners, popups, call buttons and forms. Record loading and interaction states so lazy-loaded elements are not judged before they exist.

The current sweep targets WCAG 2.1 A/AA-oriented checks. Review additional WCAG 2.2 requirements and document what remains manual. Automated accessibility results should identify tested criteria and affected examples, without claiming complete conformance.[^35][^37]

For forms, separate validation checks from real submissions. A test that sends a lead, booking or email has a business side effect and should use an approved test destination or staging environment. Conversion events can be verified without collecting patient messages or clinical details.

### Improve content governance and local consistency

Expand the existing content, author/reviewer and local-business tools with a verified fact register: services offered, opening hours, contact details, clinician credentials, editorial ownership and supporting references. Every important fact should have an owner, source and review date. This is more useful than awarding points merely because a page contains an author box.[^37]

Google's people-first guidance emphasizes original value, demonstrable expertise, clear sourcing and audience needs. Use that as a review framework. For medical content, the plugin should identify claims needing qualified review and prevent an invented reviewer or unverified credential from being published as evidence.[^29]

Detect factual drift across page text, schema, contact pages and Business Profile data already accessible to the system. Keep reviews authentic; self-serving LocalBusiness/Organization review markup does not create eligibility for Google's review stars.[^28]

Prioritize content changes by observed demand, business importance, decay and factual risk. Show competing explanations for a performance change. A title test, a service-page correction and a new article solve different problems; a composite SEO score should not choose between them without supporting evidence.


## AI search and trustworthy measurement

### Update the SEO playbook to current Google guidance

Two recent changes materially affect this plugin. Google's dedicated generative AI performance report documents impressions from AI Overviews and AI Mode and states that insights rolled out worldwide on August 31, 2026. It also retains availability caveats, including insufficient impressions. Actual availability for erofwhiterock.com has not been checked in this assessment.[^15]

The newer AI optimization guide points to that report. An older Google AI-features page still describes AI traffic inside overall Web performance. These statements need careful reconciliation: the dedicated report is a segment of Web data, not an additional source of traffic to sum into the total. Prefer the newer report documentation for current segmentation.[^16][^18]

**Recommended feature:** add an explicitly sourced AI-visibility dataset. The inspected Search Analytics API reference does not document a dedicated generative-AI report type. Do not invent an API parameter or infer AI impressions from unrelated appearance values. Verify supported access before implementing an API collector; an authenticated report export can provide an initial import route.[^14][^15]

Preserve source report, export time, reporting period, canonical URL and aggregation level. The report documentation says unavailable values shown as “~” or “-” export as zero, so the importer must retain that ambiguity where the original display state is unavailable. Keep AI impressions separate from measured clicks and modeled click loss.[^15]

### Retire outdated promises, preserve useful content

Google ended FAQ rich results from May 7, 2026 and removed the documentation in June. The plugin still has FAQ schema generation. FAQ content can remain useful, and schema.org vocabulary existence is separate from Google feature support, but FAQ markup should not earn a promised Google rich-result benefit or create a mandatory optimization task.[^17]

The current AI guide says Google Search does not use llms.txt for rankings or visibility and does not require special AI markup or artificial content chunking. Defer a new llms.txt feature unless another identified consumer needs it. The stronger opportunity is helping maintain distinctive, accurate business information that visitors can understand and use.[^16]

### Keep measured outcomes separate from explanations

Repair the GSC ingestion defects before expanding the site's status and AEO reports. Maintain original query and appearance datasets, normalized derived views and a lineage record. The current local warehouse can support this separation, but preserving all rows fetched from an API is not equivalent to preserving every underlying Google impression. The API explicitly returns bounded top data.[^14][^37]

Treat CTR anomalies as investigation prompts. Segment by query intent, device, country, brand, page and time where those dimensions are actually available. Use site-specific historical comparisons before generic CTR curves. AI visibility, position changes, SERP layout, seasonality and query mix can all complicate interpretation.

Build on the existing outcome tracker with deployment annotations, comparable windows, a minimum-data policy and explicit confounders. Report “performance improved after this change” separately from “this change caused the improvement.” Google advises evaluating third-party claims against documented guidance and qualifying opinions or inferences.[^30]

Do not use crawler hits as proof of AI citations, long queries as proof of machine fan-out, or a CTR deficit as measured lost traffic. Store each as its own observation or heuristic, with the calculation and limitations visible.


## Connection, MCP architecture and operational quality

### Make the SiteGround connection understandable

The existing browser transport successfully reached the site's 0.84.0 plugin in the September 8 verification. Keep it as a supported connection method. Add a connection-health view that separately reports network reachability, CAPTCHA clearance, WordPress authentication, authorization, bridge/plugin versions and last successful read.[^2]

WordPress Application Passwords authenticate REST requests over HTTPS; they do not themselves resolve a hosting-layer CAPTCHA. SiteGround describes challenge mechanisms that can precede application access. Its public explanations support the distinction, but do not establish which exclusions are available on this hosting account.[^31][^32][^33]

For a more dependable direct HTTP connection, obtain SiteGround's supported method for authenticated automation, using the narrowest appropriate endpoint/IP treatment if offered. A stable egress address may help an approved arrangement. Do not assume a guessed settings toggle exists or disable protection site-wide. Browser clearance should have an explicit “human action required” state when it expires.

Reads can use bounded backoff. Writes need operation IDs and status reconciliation before retries, especially after a timeout or transport switch. Keep browser profiles isolated by site and avoid simultaneous workers competing for the same profile.

### Modernize protocol support incrementally

The bridge currently announces MCP 2024-11-05 and returns JSON primarily as text. Add typed output schemas and structured results while retaining a compatible text representation. Negotiate supported protocol versions and test real clients; changing the version string alone is insufficient. The current published specification is 2026-07-28 and includes differences in result envelopes and state handling.[^8][^37]

Reduce unnecessary tool-definition load through concise descriptions and optional capability packs for editing, SEO and diagnostics. Discoverability must remain predictable across clients. Anthropic documents on-demand tool discovery and examples as ways to improve large tool libraries, but those platform features should not be assumed available in every Claude application.[^10]

WordPress's Abilities API, introduced in 6.9, and official MCP Adapter offer a path to typed, discoverable capabilities. Expose selected existing operations through that layer where available, preserving the approval and evidence services behind both transports. Keep the current bridge for compatible deployments and hosting conditions; adapter availability does not resolve CAPTCHA by itself.[^9]

### Strengthen operation and release controls

Adopt the existing restricted operator role for daily connections after validating its required reads and proposal permissions. Generate permissions documentation from the operation registry. Bind workflow handles to authenticated identities, redact secrets from diagnostics and treat page text, plugin metadata and persistent assistant notes as untrusted content.

Preserve and test safe URL-fetch boundaries as crawling expands, including redirects and private-address handling. A remote MCP deployment also needs its own authorization and SSRF review; do not assume a local stdio bridge's trust model transfers unchanged. MCP's security guidance covers these distinctions and the risks of executing local servers.[^34]

Extend existing tests into repeatable release CI: supported WordPress/PHP combinations, plugin-adapter fixtures, data migrations, interrupted operations and packaged ZIP verification. Pin dependencies, protect update provenance, document recovery and expose scheduler/collector failures. Large crawls should run as resumable bounded jobs, with workload limits and per-site health, so audits do not degrade the site they measure.


## Prioritized implementation roadmap

### Recommended delivery order

Effort below is relative engineering scope, not a delivery promise: small means a localized change; medium crosses modules or requires migration; large introduces a service or workflow plus integration coverage. The accompanying CSV breaks this into 18 reviewable work items.

| Order | Deliverable | Effort | Release condition |
|---|---|---|---|
| P0 | Correct GSC storage and AI attribution; migrate history safely | Medium | Measured totals preserved; estimates identifiable |
| P0 | Reconcile playbook/tool claims; complete audit comparison | Small–medium | No unexplained verdict changes in fixtures |
| P0 | Document and enforce operation/approval boundaries | Medium | Every mutation has an explicit policy |
| P1 | Shared evidence ledger and report contract | Large | Factual findings carry valid evidence references |
| P1 | Rank Math and Elementor capability adapters | Medium–large | Unknown/unsupported controls cannot drive plans |
| P1 | Coordinated change sets and retry-safe execution | Large | Partial failures and duplicate calls are recoverable |
| P1 | Unified indexing, crawl and performance evidence | Large | Coverage gaps remain visible and dated |
| P2 | Polylang/optimizer depth, scheduled drift and outcome analysis | Medium–large | Stable behavior across representative fixtures |
| P2 | MCP/Abilities compatibility and distribution hardening | Medium–large | Client and environment matrix passes |

A sensible next release contains the P0 work and focused regression fixtures. Start the evidence contract in parallel only where it directly simplifies those fixes. Ship a narrow, dependable vertical workflow first: inspect one page, explain a finding, propose an evidenced change, approve it and verify its intended effect.



## Acceptance tests and future distribution
## Existing validation

Previous release validation included 33 PHP suites, 158 syntax checks and 35 real-WordPress integration checks. The available integration fixture used WordPress 6.9.4 and Elementor 4.0.4. Those results are valuable, but do not establish compatibility with every production plugin combination or support the entire advertised WordPress range.[^38]

### Acceptance scenarios that matter

The suite should exercise outcomes across boundaries, including the following cases:

1. Identical captured facts produce identical rule findings; removed rules appear as lost coverage.
2. Failed fetches and CAPTCHA yield unknown, with no substitution of an old passing audit.
3. An ordinary rich-result appearance never becomes confirmed AI Overview activity.
4. GSC ingestion preserves original counts and does not fabricate measured query/appearance combinations.
5. A missing paid module, unknown enum or conditional Elementor control blocks an unsupported plan.
6. Stored metadata that differs from emitted HTML is reported as a conflict with both sources.
7. Separate workflows sharing an Application Password cannot accidentally reuse one another's evidence.
8. Coordinated same-page edits succeed from one validated base; unrelated external edits invalidate the plan.
9. A timeout after a successful write is reconciled without applying the operation twice.
10. A global kit/template change reveals its affected-page scope and verifies representative renders.
11. A completed save with a failed visible-effect check remains partially verified.
12. Browser, CrUX and indexed-copy timestamps remain distinct in the combined report.

These supplement the existing safety suite. Synthetic fixtures should be joined by a small, deliberately chosen WordPress integration matrix and browser scenarios; merely asserting that a tool returned success would miss the problems identified here.

### Features to defer

Defer bulk autonomous page rewriting, universal plugin-setting mutation, automatic pruning based on zero clicks, a proprietary “Google quality” certification, and extra schema added solely to improve a score. Preserve expert judgment where business facts or content accuracy cannot be established mechanically.

Do not add a generic “instant Google indexing” button for ordinary service pages. Google's Indexing API is restricted to eligible job postings and livestreaming-event pages. URL Inspection is observational and is not an unrestricted indexing-submission API.[^36]

For public distribution, add onboarding, compatibility disclosures, uninstall/retention controls, localization, exportable diagnostics and an update-support policy after the core workflow is reliable. The strongest differentiator is a WordPress assistant whose factual conclusions and completed actions can be independently checked.


## Sources

External documentation accessed September 9, 2026. Local paths are relative to the installed plugin unless stated otherwise.


[^1]: CC Assistant. Installed 0.84.0 source: verified page audit and evidence gate. Local source inspected 2026-09-09. Accessed 2026-09-09. D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-verified-page-audit.php:6; includes/class-evidence-gate.php:7–150. Baseline, receipt scope, environment validation and audit limits.


[^2]: CC Assistant. 0.84.0 live verification record. Captured 2026-09-08. Accessed 2026-09-09. C:/Users/sumit/AppData/Local/Temp/cc-assistant-guard-05424d3340884adaa850a1e63d1868cb/live-084-verification.json. Two homepage captures; browser transport; no content edits.


[^3]: CC Assistant. GSC ingestion, AI classifications and SEO playbook. Local source inspected 2026-09-09. Accessed 2026-09-09. includes/class-gsc.php:1062, 1112, 2060, 2118; includes/class-seo-playbook.php:75, 188; bin/mcp-server.php:1707, 1719, 2335. Paths relative to the installed plugin.


[^4]: CC Assistant research fixtures. Read-only reproduction results. 2026-09-09. Accessed 2026-09-09. research-probes.php and research-probe-results.json, included with this report. Installed PHP methods invoked with synthetic inputs and an in-memory database double; no live database or network.


[^5]: CC Assistant. Audit comparison implementation. Local source inspected 2026-09-09. Accessed 2026-09-09. includes/class-verified-page-audit.php:84–94. Current findings are compared; removed rules are not enumerated.


[^6]: CC Assistant. Approval documentation and direct mutation handlers. Local source inspected 2026-09-09. Accessed 2026-09-09. readme.txt:5, 52, 55; includes/class-rest-assets.php:638–743; includes/class-rest-api.php:6577, 7562. Media deletion has reference checks and explicit tool parameters, but does not use the pending-change approval inbox.


[^7]: CC Assistant. Plugin settings and runtime capability boundaries. Local source inspected 2026-09-09. Accessed 2026-09-09. includes/class-stack-introspect.php:797, 817; includes/class-setting-writer.php:222–226; includes/class-widget-schema.php. Stored-option ownership and feature effects can remain unknown.


[^8]: Model Context Protocol. [Tools — specification 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/server/tools). Specification dated 2026-07-28. Accessed 2026-09-09. Output schemas, structured results, tool discovery, annotations and explicit state handles.


[^9]: WordPress / Jonathan Bossenger. [From Abilities to AI Agents: Introducing the WordPress MCP Adapter](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/). 2026-02-04. Accessed 2026-09-09. Abilities API in WordPress 6.9; typed capabilities and MCP integration.


[^10]: Anthropic. [Introducing advanced tool use on the Claude Developer Platform](https://www.anthropic.com/engineering/advanced-tool-use). 2025-11-24. Accessed 2026-09-09. On-demand tool discovery and usage examples; platform features, not a promise about every Claude client.


[^11]: Elementor. [Elementor Editor Controls](https://developers.elementor.com/docs/editor-controls/). Undated live documentation. Accessed 2026-09-09. Control types, responsive controls, conditions, global styles and dynamic content.


[^12]: Rank Math. [How to Enable Headless CMS Support in Rank Math?](https://rankmath.com/kb/headless-cms-support/). Undated live documentation. Accessed 2026-09-09. getHead returns generated metadata; requires Headless CMS support.


[^13]: Polylang. [Function reference](https://polylang.pro/documentation/support/developers/function-reference/). Undated live documentation. Accessed 2026-09-09. Language and translation APIs; runtime function-existence checks.


[^14]: Google Search Console. [Search Analytics: query](https://developers.google.com/webmaster-tools/v1/searchanalytics/query). Live API reference. Accessed 2026-09-09. Dimensions, search types, final/preliminary data and top-row limitations.


[^15]: Google Search Console. [Generative AI performance report (Search)](https://support.google.com/webmasters/answer/16984139). Contains rollout update dated 2026-08-31. Accessed 2026-09-09. AI Overviews and AI Mode impressions; dimensions, exports, availability caveats and unavailable values.


[^16]: Google Search Central. [Optimizing your website for generative AI features on Google Search](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide). Introduced 2026-05-15; live documentation. Accessed 2026-09-09. Current AI Search guidance, llms.txt limitations and dedicated performance report.


[^17]: Google Search Central. [Latest Google Search documentation updates](https://developers.google.com/search/updates). Entries dated 2026-05-08 and 2026-06-15. Accessed 2026-09-09. FAQ rich results ended May 7, 2026; documentation removed June 15.


[^18]: Google Search Central. [AI features and your website](https://developers.google.com/search/docs/appearance/ai-features). Page shows last update 2025-12-10. Accessed 2026-09-09. Older general guidance still describes AI data inside Web search; use newer report documentation for current segmentation.


[^19]: Google Search Console. [Method: index.inspect](https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect). Page shows last update 2024-07-23. Accessed 2026-09-09. Indexed-copy status only; cannot test the live URL's indexability; OAuth scopes.


[^20]: Google Search Console. [Usage limits](https://developers.google.com/webmaster-tools/limits). Page shows last update 2025-08-28. Accessed 2026-09-09. URL Inspection property quota: 2,000 per day, 600 per minute.


[^21]: Google Search Central. [Introduction to robots.txt](https://developers.google.com/search/docs/crawling-indexing/robots/intro). Live documentation. Accessed 2026-09-09. Crawl restrictions differ from preventing indexing.


[^22]: Google Search Central. [How to specify a canonical URL](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls). Live documentation. Accessed 2026-09-09. Canonical signals and consistent URL handling.


[^23]: Google Search Central. [Build and submit a sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap). Live documentation. Accessed 2026-09-09. Canonical sitemap URLs and submission.


[^24]: Google Search Central. [Tell Google about localized versions of your page](https://developers.google.com/search/docs/specialty/international/localized-versions). Live documentation. Accessed 2026-09-09. Reciprocal language annotations and language/region alternatives.


[^25]: Google. [About PageSpeed Insights](https://developers.google.com/speed/docs/insights/v5/about). Live documentation. Accessed 2026-09-09. Lab versus field measurements, test conditions and 75th-percentile interpretation.


[^26]: Chrome for Developers. [CrUX API](https://developer.chrome.com/docs/crux/api/). Live documentation. Accessed 2026-09-09. URL/origin queries, form factors, collection period and rolling 28-day data.


[^27]: Google Search Central. [General structured data guidelines](https://developers.google.com/search/docs/appearance/structured-data/sd-policies). Live documentation. Accessed 2026-09-09. Visible content, relevance, accuracy and eligibility limits.


[^28]: Google Search Central. [Review snippet structured data](https://developers.google.com/search/docs/appearance/structured-data/review-snippet). Live documentation. Accessed 2026-09-09. LocalBusiness/Organization self-serving review restrictions.


[^29]: Google Search Central. [Creating helpful, reliable, people-first content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content). Live documentation. Accessed 2026-09-09. Expertise, original value, sourcing and audience needs.


[^30]: Google Search Central. [Guidance on using third-party SEO tools, services, and advice](https://developers.google.com/search/docs/fundamentals/third-party-seo). Introduced 2026-06-05; live documentation. Accessed 2026-09-09. Qualify opinions and check recommendations against official guidance.


[^31]: WordPress. [Authentication — REST API Handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/). Updated 2025-06-04. Accessed 2026-09-09. Application Passwords over HTTPS.


[^32]: SiteGround / Hristo Pandjarov. [How Our New Anti-Bot AI Prevents Millions of Brute-Force Attacks](https://www.siteground.com/blog/new-anti-bot-ai/). Historical vendor explanation, published 2017. Accessed 2026-09-09. CAPTCHA challenges, human clearance and persistent-challenge support guidance; not evidence of current account-specific exclusions.


[^33]: SiteGround. [Block Sophisticated HTTP Attacks with SiteGround CDN's Under Attack Mode](https://www.siteground.com/blog/cdn-under-attack-mode/). Historical vendor documentation, published 2023. Accessed 2026-09-09. CDN-level CAPTCHA can precede WordPress.


[^34]: Model Context Protocol. [Security Best Practices](https://modelcontextprotocol.io/docs/2026-07-28/tutorials/security/security_best_practices). Current 2026-07-28 documentation. Accessed 2026-09-09. Auth-bound state handles, SSRF/redirect validation and local MCP execution risks.


[^35]: W3C. [Web Content Accessibility Guidelines (WCAG) 2.2](https://www.w3.org/TR/WCAG22/). W3C Recommendation; live version. Accessed 2026-09-09. Accessibility scope, including requirements beyond an automated scan.


[^36]: Google Search Central. [Using the Indexing API](https://developers.google.com/search/apis/indexing-api/v3/using-api). Live documentation. Accessed 2026-09-09. Limited to eligible JobPosting and livestream BroadcastEvent pages.


[^37]: CC Assistant. Current tool catalog and existing modules. Local source inspected 2026-09-09. Accessed 2026-09-09. bin/mcp-server.php (164 tool definitions; initialize protocol at line 4333); bin/warehouse.php; includes/class-gsc.php; includes/class-multilingual.php; includes/class-performance-tracker.php. Existing SEO, browser, warehouse and multilingual functionality.


[^38]: CC Assistant. 0.84.0 release validation records. 2026-09-08. Accessed 2026-09-09. C:/Users/sumit/AppData/Local/Temp/cc-assistant-guard-05424d3340884adaa850a1e63d1868cb/verification.json and integration-verification.json. Prior 33 PHP suites, 158 PHP syntax checks and 35 WordPress integration checks; integration fixture WordPress 6.9.4 / Elementor 4.0.4.
