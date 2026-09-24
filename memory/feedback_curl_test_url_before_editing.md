---
name: curl-test-url-before-editing
description: "BEFORE queueing ANY edit on a WordPress post, curl-test the post's URL to verify (a) it returns 200 (not 301 redirect, not 404), (b) the rendered content actually matches the post being edited, AND (c) any internal links you're about to write actually resolve. Skipping this check stranded 12 widget edits on a redirected page in one session."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

**The rule:** for any non-trivial edit (more than a single typo fix, definitely any rewrite or new section), run `WebFetch` or `curl -s -I -L` on the post's `url` field BEFORE the first `draft_*` call. Verify:

1. HTTP status is 200, not 301/302/404
2. The rendered URL matches the post URL the plugin reports (a Rank Math redirect can send users elsewhere without changing the slug)
3. The H1 / hero content visible on the rendered page matches what `get_post` returns for that post (drift_detected on the live render means an intermediate redirect or theme override is in play)
4. For every internal link you plan to write, the destination URL returns 200 — verify via curl OR by confirming the destination's actual slug via `list_posts`, NOT by assuming a slug pattern from a sibling site

**Why:** 2026-05-20, erofwhiterock.com — I queued 12 widget edits on post 3505 (`/emergency-services-in-dallas-tx/`) to rewrite Dallas emergency-services content. After the user approved them, they reported "I don't see any changes." Investigation: post 3505 has a Rank Math 301 redirect to /emergency-services-er-of-white-rock/ (post 541). The 12 edits applied to the database, but the URL redirects away from 3505 so no one reaches the page. Same session, I assumed Lakewood / Lake Highlands / Garland location pages used Irving's `/emergency-services-in-{city}-tx/` slug pattern. The actual slugs are `/emergency-room-{city}-tx/`. Eight of my "this URL is 404" findings were just me using stale Irving URLs without verification.

GSC signal was there and I misread it: post 3505 had 0 queries in 28 days. I called that "Google sees the duplicate and is not indexing it." Wrong — the real cause was "the URL redirects away so it cannot accumulate impressions." A single curl test at the start would have caught the redirect AND the wrong slug pattern in 30 seconds.

There's already a saved rule for this: [[feedback_verify_before_proposing_fix]] — "curl-test source URLs before queueing redirects/slug fixes." That rule was about redirect proposals specifically; this expands it to EVERY edit. The discipline is universal: trust the post-ID-to-URL mapping the plugin reports only as far as the next curl.

**How to apply:**
- First action on any new post-edit session: `WebFetch` the post URL, OR `curl -s -I -L` it. Read the final Location header.
- If the final URL differs from the requested URL: STOP. Investigate the redirect (Rank Math redirects, Custom Permalinks postmeta, .htaccess) before editing.
- For internal links: if the destination URL is for a location-modified service page (e.g. "X in Lakewood TX"), look up the actual slug via `list_posts(search="lakewood")`, do not pattern-match from a sibling site.
- The plugin should grow a guard: at queue time on any `draft_*` call, fetch the post URL and warn if it redirects to a different post_id. This is a P1 plugin gap to file.

Related: [[feedback_verify_before_proposing_fix]] (curl-test before redirect proposals), [[reference_custom_permalinks_trap]] (custom_permalink postmeta can hide redirects from get_permalink), [[project_erofwhiterock_cloned_from_erofirving]] (clone-site URL patterns drift between sites; never assume).
