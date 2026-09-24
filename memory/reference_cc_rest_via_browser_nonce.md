---
name: reference_cc_rest_via_browser_nonce
description: When SiteGround's IP-reputation challenge kills the MCP bridge, the SAME cc-assistant REST routes are reachable through the operator's logged-in Chrome using the wp-admin nonce; tools/cc-via-browser.mjs does it, and all lint/gate/queue protections still apply
metadata:
  type: reference
---

**The situation.** SiteGround flags an IP's reputation and then answers `202` with
a JavaScript browser challenge to every non-browser client, on every site on
their platform ([[reference_siteground_ip_captcha]]). The MCP bridge is
`php.exe`, so it is locked out completely. Verified dead ends: curl with a
browser UA, curl following the challenge with a cookie jar, and replaying the
browser's own `_I_` clearance cookie from the same IP. The clearance is bound to
the browser, not the cookie.

**The way through.** A real Chrome passes the challenge, and any signed-in
wp-admin page exposes `wpApiSettings.nonce`. With that nonce in an `X-WP-Nonce`
header, `fetch()` from inside the page reaches the plugin's own REST routes.

    node tools/open-chrome.mjs https://SITE/wp-admin/ 12     # operator signs in once
    node tools/cc-via-browser.mjs https://SITE --batch calls.json

**This is NOT a bypass, and that distinction matters.** It hits exactly the routes
the bridge hits, so the content lint, the 8-hour session gate, the race-safety
guard and the pending-changes approval queue all still run. Only the HTTP client
differs. Never use the browser to poke wp-admin directly instead - that WOULD
bypass the queue, and the queue is the whole safety model.

**Route names** (namespace `cc-assistant/v1`, all POST unless noted): `/whoami`
(GET), `/assets/upload`, `/draft/post-content-patch`, `/draft/post-content`,
`/site-memory/notes`, `/site-memory` (GET). 166 routes total; grep
`register_rest_route` in `includes/` for the rest, and note the MCP tool name is
NOT the route name (`upload_media` is `/assets/upload`).

**Use batch mode.** Each launch spends about twenty seconds clearing the
challenge, so a five-call sequence run one call at a time is mostly browser
startup. Batch mode runs them in one session and stops on the first failure, so
a failed upload cannot be followed by a patch pointing at nothing.

**The operator was right and I was wrong.** I insisted twice that a browser could
not help because "the bridge is not a browser". True of the bridge, but it does
not follow that the ROUTES are unreachable, and they were reachable all along.
Distinguish "this transport is blocked" from "this capability is impossible".

**How to apply.** Proven end to end on erofirving 2026-09-07: whoami, four
`upload_media` calls, three dry-runs (lint 19/19, no warnings), three queued
pendings 1093-1095. If the bridge is dead with `sgcaptcha` in the body, reach for
this rather than waiting - but still fix the IP, because a browser-only path
cannot run the MCP tools the workflows are built around. Related:
[[project_erofirving_card_generator]], [[reference_tool_locations]].

## Stop guessing route names: read them off the source (2026-09-08)

Route names are NOT tool names and guessing costs ~20s per miss (each wrong
guess also aborts the rest of the batch). The plugin source is local, so parse it:

```python
import re, glob, io
for f in glob.glob(r'D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-rest-*.php'):
    t = io.open(f, encoding='utf-8', errors='replace').read()
    for m in re.finditer(r"register_rest_route\(\s*[^,]+,\s*'([^']+)'", t):
        print(m.group(1))
```

165 routes. The ones that bit me, and their real names:

| tool | route |
|---|---|
| `draft_create_redirect` | `POST /draft/redirect` (NOT `/draft/create-redirect`) |
| `draft_delete_redirect` | `POST /draft/redirect/delete` |
| `draft_update_elementor_widget` | `POST /draft/elementor-widget` |
| `pre_publish_check` | `GET /posts/<id>/pre-publish-check` |
| `export_elementor_data` | `GET /posts/<id>/elementor-export` |
| `links_audit_post` | `GET /posts/<id>/link-audit` |
| `get_site_memory` | `GET /site-memory` |
| `update_site_memory_notes` | `POST /site-memory/notes`, param is **`notes`** not `note` |

Handler params come from the same file: `grep -A30 'function handle_<name>'` and
read the `get_param()` calls. `/draft/redirect` takes `source`, `destination`,
`http_code` (301/302/307/410/451), `reasoning`, `summary`. `/draft/elementor-widget`
takes `post_id`, `widget_id`, `settings`.

`/kit-settings` returns only 8 summary keys and `custom_css` is NOT one of them,
so that route cannot confirm kit CSS state.

## Elementor export is DOUBLE-encoded, and that broke two of my searches

`GET /posts/<id>/elementor-export` returns `data.raw_data` as a JSON **string**;
parse it once with `json.loads` to get the element tree. Searching the response
file as raw text for a literal `—` finds nothing even when em dashes are present,
because JSON escapes them as `—`. Both my raw grep and my structured walk
missed 9 real em dashes on the Mammoth category pages before the rendered DOM
proved they existed. Parse `raw_data`, then walk `elements` recursively, and read
`settings` values; never conclude "absent" from a text search of the raw payload
([[feedback_dom_is_ground_truth_not_parsers]]).

## SUPERSEDED 2026-09-09: the plugin has its own browser transport now

Stop reaching for `tools/cc-via-browser.mjs` first. cc-assistant 0.82.0+ (bridge
is 0.88.0) carries an integrated browser transport, so the **real MCP tools work
through the challenge** instead of hand-built REST batches with guessed route
names. Per-site opt-in, three env vars in the site's `env` in
`D:\cc-assistant\.mcp.json`:

```json
"CC_MCP_TRANSPORT": "browser",
"CC_NODE_BINARY": "C:/Program Files/nodejs/node.exe",
"CC_BROWSER_PROFILE_ROOT": "D:/cc-assistant/.browser-profiles"
```

The PHP bridge runs a persistent Chrome worker that keeps SiteGround clearance in
a per-site profile (dir named base64 of the site URL). It needs **no wp-admin
login** - it authenticates with the existing Application Password - so the old
"the login is PER SITE" chore is gone. Playwright resolves from the fallback
`~/.cc-assistant/wcag`; `bin/browser/` holds only a package.json and that is fine.

Configured and verified on **erofwhiterock** and **sids-ponds** (whoami returned
plugin 0.88.0, WP 7.1, 0 pending). The other seven sites are still `http` and
still challenged - add the three vars per site as needed.

Two traps: **the change needs an MCP restart**, because the running server keeps
the transport it started with, and a pre-restart probe has to be a separate
`php.exe` run piping JSON-RPC to `bin/mcp-server.php`. That separate probe does
NOT satisfy `.claude/hooks/cc_gate.py`, which tracks whoami per chat via the MCP
tool, so writes stay gated until the restart. Also run one MCP process per
site/profile - profile locks are enforced. Source of truth is the plugin's own
`CONNECTION.md`, not this file. Related: [[reference_siteground_ip_captcha]].

## Rollout 2026-09-09: all nine remote sites switched, 8 verified, jayard39 needs one manual pass

**The config that matters lives in the PROJECT-ROOT `.mcp.json`**, i.e.
`C:\Users\sumit\Local Sites\plugintesting\app\public\.mcp.json`, NOT
`D:\cc-assistant\.mcp.json`. Claude Code loads the project-root file, and the two have
diverged: the project file carries 10 servers including `cc-assistant-plugintesting`,
the D: file 9 without it. I edited D: first and the operator's restart changed nothing,
which cost a round trip. Editing D: is still right for plugin source, memory and
skills; for MCP registration edit the project root. Check the server count to tell
which file a session actually loaded.

`cc-assistant-plugintesting` is **left on http deliberately**: it is
`http://plugintesting.local/`, a Local site, neither SiteGround nor challenged.

Verified working on browser transport by piping JSON-RPC to `bin/mcp-server.php`:
erofwhiterock, sids-ponds, erofirving (0.81.3), mammothmachinery (0.81.3), gnpn
(0.76.12), eroflufkin (0.76.8), irvingwellnessclinic (0.81.2), jayard41 (0.68.3).

**jayard39.sg-host.com is the one failure**, returning
`{"error":"browser_challenge","message":"SiteGround still requires a challenge. Use a
visible browser session (CC_BROWSER_HEADLESS=0)"}`. Headless could not clear a fresh
profile there. One-time remedy, then drop the flag once the profile holds clearance:

```
CC_BROWSER_HEADLESS=0   # in that site's env, one run, operator clicks through
```

**Refinement to the one-command check above: status code alone is NOT reliable.**
jayard41 answered **200** while still serving `sgcaptcha` in the body; the other eight
answered 202. Grep the body for `sgcaptcha`, do not test for 202:

```
curl -s https://SITE/wp-json/ | grep -q sgcaptcha && echo CHALLENGED
```
