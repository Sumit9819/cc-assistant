---
name: Verify before proposing a fix the human may have already applied
description: Before queueing redirects, slug changes, or URL-shape fixes derived from GSC/Coverage data, verify the fix isn't already in place. Coverage data is stale.
type: feedback
originSessionId: aaa9af36-e4be-477a-9165-090e90063ca2
---
Before proposing a redirect, slug change, or any URL-shape fix derived from GSC coverage data: verify the fix isn't already applied.

**Why:** I queued **28 duplicate Rank Math redirects in a single batch** (PCs #195–#222 on eroflufkin) sourced from a GSC "Crawled - currently not indexed" Coverage export. Curl-testing every source URL after the fact showed **100% of them (28/28)** were already 301'd to exactly the destination I proposed — the user had already worked through the list and applied every redirect. GSC was reporting CNI on URLs that had been fixed days/weeks earlier because Google had not re-crawled them yet. Wasted tokens, wasted human review time, and a polluted inbox the user had to bulk-reject. This is exactly the kind of "AI re-suggesting things humans already did" failure that erodes trust in the workflow. The dupe rate (100%) is strong evidence that the GSC Coverage export, by itself, is not a valid input for deriving fix-needed work.

**How to apply:**
- GSC Coverage data is a SNAPSHOT. `last_crawl_time` is frequently days or weeks stale. A `coverage_state` of "Crawled - currently not indexed" does NOT mean the underlying issue still exists today — it means it existed at last crawl.
- Cheapest pre-check: `curl -I -A "Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)" <source_url>` — if the response is 3xx, the redirect is already in place; skip and note the existing redirect rather than queueing a dupe.
- For batches of redirects sourced from a Coverage export, curl-test all of them in parallel before queueing — cheap (~50ms each) vs. the cost of polluting the human's review inbox.
- For slug changes: `list_recent_edits` defaults to `limit=25` which only covers ~last 4 days of activity. Pass `limit=100+` and grep for the post slug or post_id before proposing.
- Same logic applies to any fix where the input is GSC-derived, Coverage report data, GA / GSC analytics, or anything else with a multi-day data-staleness window.

**Plugin enhancement to file (cc-assistant v0.8.x):**
`draft_create_redirect` should query Rank Math's redirects table for an existing matching `source` URL before queueing. If a matching redirect exists (regardless of destination or http_code), refuse with a `redirect_already_exists` error that cites the existing entry's destination + when it was created. Same defensive check for any slug-change pending: detect if the destination slug is already live on the post or if a Rank Math redirect already maps the source. This is defense in depth — even if the operator forgets the curl pre-check, the plugin catches it server-side. File under "duplicate-suggestion prevention."
