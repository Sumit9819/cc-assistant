# Content strategy in 0.85.0

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

## Keep the niche explicit

Open **CC Assistant → Content Strategy** as an administrator.

1. Enter the post IDs of published service pages or niche pillars.
2. Describe the intended readers.
3. List excluded topics, one per line.
4. Add general reader questions as: `123 | What should I ask about this service?`

Question IDs must refer to selected source pages. Save the profile. Claude can read it with `get_content_scope`.

Without a profile, the planner suggests published pages and labels the scope as inferred. Claude should confirm suitable source pages or pass `service_post_ids` to focus an individual plan. A selected page establishes an editorial relationship; it does not independently verify all of that page's factual claims.

Custom ideas outside explicit exclusions or without a supported niche relationship are returned for scope review. Lexical matching is a candidate filter, not a semantic guarantee; inspect the actual reader purpose.

## Tools

| Tool | Purpose |
|---|---|
| get_content_scope | Read the source pages, audience, exclusions and general questions. |
| plan_blog_content | Generate source-backed niche proposals and inspect stored coverage. |
| content_research | Fetch up to three explicit competitor URLs and retain source observations and a claim ledger. |
| content_decision | Compare up to eight pages, record alternatives and preservation requirements before an intervention. |
| content_decision_history | Retrieve recent evidence and decisions for the connected account. |

Planning scans up to 200 eligible posts/pages by default, configurable to 500. It includes drafts, pending and scheduled content. Private/password-protected content and other post types are excluded; the response reports scope and pagination. A partial inventory or extraction is not evidence that an article is missing. Elementor uses the existing parser; dynamic Elementor content and unsupported shortcodes remain explicitly partial. Stored observations do not replace rendered verification. Use `offset`/`next_offset` to inspect further inventory pages and `candidate_offset`/`next_candidate_offset` to retrieve more proposals with the same plan inputs. These are separate pagination controls.

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

This is the first implementation of the research roadmap: priority decision/measurement corrections, niche-led planning, bounded competitor collection, claim observations, persistent decision dossiers and consistent short/full SEO policy.

Advanced automatic consolidation change sets, contextual link insertion, scheduled claim-drift monitoring, conversion-aware causal evaluation, external SERP/backlink integrations and the remaining capability-adapter roadmap are not implemented by these new tools. Plans should use only capabilities actually available on the connected site.

## Updating

Install the 0.85.0 plugin ZIP. Refresh the desktop bridge if it is stored separately, including all PHP files in its `bin` directory; the new bridge loads `content-tools.php`. Restart Claude's MCP connection or start a fresh session, then call `whoami` and confirm version 0.85.0 and the five tools above.

Configure content scope for the most reliable niche boundaries. Re-sync GSC history when corrected historical comparisons are needed; planning new topics does not need to wait for that sync.

Validation uses PHP CLI regression fixtures, SQLite-backed measurement tests and a real MCP initialize/tools-list smoke check. It does not certify the deployed site's plugins, live search results or rankings.

