---
name: sg-cache-bypass-after-wp7
description: "After WordPress 7.0 Armstrong (released 2026-05-20), SG Optimizer's dynamic cache started bypassing every request site-wide on multiple sites in the user's SiteGround account. Update SG Optimizer first; check Site Tools second."
metadata: 
  node_type: memory
  type: reference
  originSessionId: b85a6e61-a8bf-48a2-9b0b-343339376ebe
  modified: 2026-07-23T09:07:22.688Z
---

When a user reports a slow site on SiteGround and curl shows `SG-F-Cache: BYPASS` + `X-Proxy-Cache-Info: DT:1` on every page (including anonymous, no-cookie requests):

**Diagnostic curl:**
```
curl -sLI -A "Mozilla/5.0" "https://<site>/" | grep -iE "sg-f-cache|x-cache|x-proxy"
```

If this returns BYPASS on a logged-out, no-cookie request, the SiteGround cache layer is refusing to serve from cache for everyone.

**Evidence from erofirving session (2026-05-29):** All 4 sister sites in the user's SG account (erofirving, eroflufkin, irvingwellnessclinic — and erofwhiterock with no `SG-F-Cache` header at all) showed cache bypass on every request after WP 7.0 Armstrong shipped 2026-05-20. TTFB sat at 1.0–1.8s on repeat anonymous requests. User had a clean child theme `functions.php` (no `session_start`, no `setcookie`, no `nocache_headers`, no `header(Cache-Control...)`); the bypass was upstream of the theme.

**Most likely cause:** SG Optimizer plugin needs an update to handle WP 7.0's new core APIs. Until updated, its cache-eligibility logic silently fails and every request bypasses.

**Fix order (in order, time-cheapest first):**
1. WP Admin → Plugins → update SG Optimizer (often resolves it).
2. WP Admin → SG Optimizer → Caching → confirm Dynamic Caching ON, Logged-In User Caching OFF, Excluded URLs list has no wildcards, click "Purge Cache".
3. SiteGround Site Tools → Speed → Caching → Dynamic Cache → confirm enabled per domain, click "Flush Cache".
4. Re-run the diagnostic curl. Should now show `SG-F-Cache: HIT` after a couple of warm-up requests.

**What is NOT the cause (don't chase these):**
- Child theme `functions.php` performance snippets (emoji removal, oEmbed disable, jQuery Migrate removal, dashicons dequeue, Gutenberg block CSS removal) — none of these set cookies or send no-cache headers, so they cannot cause BYPASS.
- `is_user_logged_in()` checks in conditional enqueues — these read a cookie but don't set one; SG cache only bypasses on cookies sent in the RESPONSE, not the request.

**ESCALATION observed 2026-07-23 (eroflufkin session):** the upstream proxy cache served a CROSS-SITE page — a plain browser-UA curl of `https://eroflufkin.com/` returned irvingwellnessclinic.com's full homepage (title, 237 irvingwellnessclinic URLs, 0 eroflufkin) while a cache-busted `/?cb=N` request returned the correct site. Both sites share the SG account. So the collision is in SiteGround's proxy layer keyed wrong across hosts, not in WP. Implications: (1) any audit curl of a bare homepage URL on these sites MUST use a cache-buster query before trusting the HTML; (2) Google can crawl the wrong site's content on the homepage — treat as urgent; operator should flush the proxy cache in Site Tools and open a SiteGround ticket.

Related: [[feedback_plugin_performance]] (cc-assistant should also stay off the front-end path).
