# Claude-managed content strategy in 0.86.0

New blogs can be planned without an existing Search Console gap. GSC describes observed visibility for the site; it cannot enumerate every useful topic within the business's niche.

## Start a new blog

Ask Claude: "Use plan_blog_content to propose five useful blogs within our services, including topics with no GSC history. Explain the reader need, source page, existing overlap and useful contribution before drafting."

The planner uses:

- Selected published service pages or niche pillar posts.
- General reader questions recorded against those source pages.
- Practical reader tasks such as understanding a service, preparing questions, understanding a process and evaluating options.
- Optional custom topic proposals.
- Optional observed GSC queries.

Every proposal includes a source evidence ID, reader benefit, demand state, existing-content candidates, an original-value brief and outstanding research. Missing GSC history never blocks a proposal. Related service pages do not automatically rule out supporting blogs. Existing articles and drafts must still be inspected before choosing a genuinely distinct task.

The returned titles are starting proposals. They are not automatically approved topics, verified business claims or publication-ready articles.

## Claude manages the strategy

No setup form or manual post IDs are required. Ask Claude to handle the whole task: "Create a useful new blog within our actual services. Discover and maintain the strategy from the site and existing notes, research a distinct reader need, and prepare the complete draft for my review."

`plan_blog_content` initializes an inferred source profile when none is saved. Discovery reads eligible published pages/posts, identity and existing site notes. Home, utility and protected/private pages are not selected as service candidates; home/about content can provide context. Blog posts are available as editorial-pillar candidates, but blog mentions do not establish offered services. Roles inferred from titles, URLs and metadata remain interpretations to inspect.

Claude uses `discover_content_scope` to inspect source excerpts and `get_site_memory` for full recorded knowledge, then `manage_content_scope` to save relevant source selections, audience, exclusions and reader questions. It supplies current evidence IDs, the current scope revision, the site-context hash and its reasons. The restricted CC Assistant operator account can manage this specific metadata without permission to write arbitrary options or approve publication.

Omitted fields preserve current values. Removing an exclusion requires an explicit reason, and questions must reference selected sources. Source changes, newly unavailable pages, changed site notes and concurrent scope changes cannot silently be stamped as already reviewed. The last ten profiles are retained; responses expose compact history summaries. Existing saved profiles are preserved when planning runs again.

**CC Assistant > Content Strategy** is a review screen showing current sources, audience, exclusions, questions, freshness and change history. Tell Claude a correction in your conversation; it can inspect and apply the supported metadata correction. This screen does not require configuration.

## Tools and execution

| Tool | Purpose |
|---|---|
| get_content_scope | Current strategy, source evidence, revision, freshness and site context. |
| discover_content_scope | Discover source candidates and existing knowledge, with coverage and pagination. |
| manage_content_scope | Save or maintain the strategy from current evidence and an explained basis. |
| content_workflow | Save a complete agent handoff for a new blog, refresh or site review; Claude executes its tool steps. |
| plan_blog_content | Initialize missing scope metadata and generate niche proposals with stored overlap checks. |
| content_research | Capture up to three explicit comparable URLs and a claim ledger. |
| content_decision | Compare up to eight pages and record alternatives and preservation requirements. |
| content_decision_history | Retrieve recent research, decision and workflow records for this account. |

A prepared workflow is explicitly **prepared_not_executed**. Claude must perform the research, use actual installed capabilities, create the draft or pending changes and return real IDs and preview links. The operator reviews concrete results in the existing Pending Changes workflow. Saving a workflow does not claim that a draft, audit or live change has already happened.

This is automation during an active Claude session. The feature does not start Claude on a schedule or add an Anthropic API runner. Existing WordPress background analytics remain separate. Truly unattended model execution needs a configured runner, credentials, scheduling, budgets, retries and outcome tracking; this release does not claim those exist.

Planning scans up to 200 eligible posts/pages by default, configurable to 500. Inventory includes drafts, pending and scheduled content; public scope selection requires published unprotected content. Other post types are excluded. Use `offset`/`next_offset` to inspect further inventory or discovery pages and `candidate_offset`/`next_candidate_offset` for more proposals. Claude should inspect further pages when coverage is partial. A partial inventory or extraction does not prove an article is missing. Elementor uses the existing parser; dynamic content and unsupported shortcodes are explicitly partial. Stored observations do not replace rendered verification.

## Competitor and originality checks

Provide actual comparable URLs to `content_research`. It records source URLs, timestamps, hashes, extraction coverage and short excerpts. It does not invent search positions or require a paid search provider. Requests have URL validation, TLS verification, response/time limits and no automatic redirects. CAPTCHA, redirects and failed extraction remain uninspected. Successful snapshots are cached for six hours.

The claim ledger records statements observed on the site and exact passage matches in inspected excerpts. It does **not** verify factual truth, expertise, global originality or ranking impact. A statement absent from those excerpts may still appear elsewhere or be paraphrased. Claude must develop a supported reader-value brief and obtain the necessary primary evidence or subject review.

Prices, tables, quotes and government/education links are now described as surface observations. They no longer earn a claim of verified first-party information or a numerical information-gain score.

## Refreshes, links and consolidation

Use `content_decision` with the relevant post IDs and reader goal. It inspects pairs, distinguishes stored-text duplication from broader relatedness, protects language differences and records alternatives.

The dossier includes preservation requirements and missing evidence. It does not approve a merge, redirect, noindex or deletion. The existing content review workflow remains the execution path. Research records are not publication authorization.

Same actor, policy, inputs and source hashes reuse the decision. Changed source content or inputs create another record. Records belong to the current site/account; the latest 30 within 90 days are readable. They are saved without autoload, and writes use a nonblocking database lock. Busy or failed storage returns an error instead of claiming a saved record. Expired records are excluded from reads and removed during the next successful save.

## Corrected measurements

Query/page counts are stored directly, without allocating them across page-level search appearances. Appearance facts use the separate `cc_gsc_appearances` table. Date replacement requires verified InnoDB storage and a successful transaction.

Previously inferred historical rows cannot be reconstructed reliably from their stored approximations. Re-sync the reporting period to obtain corrected data. `gsc_status.measurement` lists dates re-synced by the new writer; unlisted dates may still be legacy estimates. API limits, retention and low-impression pruning can still limit historical coverage.

Non-Web appearance labels and low CTR no longer certify AI Overviews or AI-caused lost clicks. Position aggregates in the corrected SEO query paths are impression-weighted. Candidate impressions are described as exposure, not proven lost traffic.

## What this release covers

The 0.85.0 foundation and 0.86.0 agent-owned setup cover: priority decision/measurement corrections, niche-led planning, bounded competitor collection, claim observations, persistent decision dossiers and consistent short/full SEO policy.

Advanced automatic consolidation change sets, contextual link insertion, scheduled claim-drift monitoring, conversion-aware causal evaluation, external SERP/backlink integrations and the remaining capability-adapter roadmap are not implemented by these new tools. Plans should use only capabilities actually available on the connected site.

## Updating

Install the 0.86.0 plugin ZIP. Refresh the desktop bridge if it is stored separately, including all PHP files in its `bin` directory; the new bridge loads `content-tools.php`. Restart Claude's MCP connection or start a fresh session, then call `whoami` and confirm version 0.86.0 and the eight tools above. A plugin-only update does not refresh an already running desktop bridge; Claude can call `operator_brain_pull(what="bridge", mode="replace")` to copy deployed bridge files, then the connection must restart.

Claude discovers and maintains content scope from the available site evidence; no manual strategy setup is needed. Re-sync GSC history when corrected historical comparisons are needed; planning new topics does not need to wait for that sync.

Validation uses PHP CLI regression fixtures, SQLite-backed measurement tests and a real MCP initialize/tools-list smoke check. It does not certify the deployed site's plugins, live search results or rankings.

