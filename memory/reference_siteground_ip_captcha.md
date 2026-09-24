---
name: reference_siteground_ip_captcha
description: SiteGround answers 202 + sgcaptcha on EVERY site at once when it rate-limits this machine's IP; this is NOT the older UA block, a real browser still passes, and heavy image downloading triggers it
metadata:
  type: reference
---

**Symptom.** Every SiteGround site (erofirving, erofwhiterock, eroflufkin,
irvingwellnessclinic, sids-ponds) starts answering `HTTP 202` with a body
containing `sgcaptcha` and a meta-refresh to
`/.well-known/sgcaptcha/?r=...&y=ipc:<your-ip>:<ts>` (the marker is `ipc:` or `ipr:`; same cause). The MCP bridge dies on all
of them simultaneously with `json_parse_error` and that HTML in the body.

**This is not the UA block.** Distinguish by the response, not by the effect:

| | UA block ([[reference_siteground_ua]]) | IP rate-limit (this file) |
|---|---|---|
| Status | 403 | 202 |
| Body | SiteGround-branded error page | `sgcaptcha` meta-refresh |
| Scope | any site, depends on the UA sent | every SG site at once, any UA |
| Fix | send a browser UA (shipped in v0.35.3) | wait, or pass the challenge |

Do NOT "fix" this by touching the UA. The UA is already correct.

**What triggers it.** Volume from one IP. Observed 2026-09-07 right after a run
of `media_audit` calls plus a couple of dozen curl image downloads from
irvingwellnessclinic while building the featured-image generator. Reading is
cheap per call and still counts.

**What clears it.** A real browser passes the challenge, but the clearance is
COOKIE-scoped, not IP-scoped: after passing in Chrome, cookie-less clients
(curl, the MCP bridge) still get 202. So passing it in a browser does NOT bring
the bridge back. `D:\cc-assistant\tools\sg-unblock.mjs` does the browser pass and
then tells you to re-test without cookies, precisely so this is not assumed.

Note the challenge can take ~20s of JavaScript to resolve. A 9-second wait
returned the "Robot Challenge Screen" page, and a harvest script happily saved
`robot-suspicion.svg` as the site's logo. Always assert on the page TITLE or a
known element before trusting anything scraped.

**How to apply.** When the bridge dies on several SG sites at once, curl one
`/wp-json/` and look for `sgcaptcha` before doing anything else. If present, the
site tools are unavailable until the IP cools off; use the headed Chrome in
`tools/card-generator/featured/.chrome-profile` for read-only front-end work in
the meantime, and throttle bulk asset downloads. Related:
[[reference_siteground_ua]], [[feedback_never_claim_server_slow_without_control]].

**Reading through the browser while challenged (verified 2026-09-07).**
`tools/cc-via-browser.mjs <site> --batch <calls.json>` works during the challenge and
hits the same plugin REST routes as the bridge, so lint, the session gate and the
approval queue still apply. Two gotchas that cost a full round trip each:

- **The wp-admin login is PER SITE, not per profile.** The shared profile had only
  erofirving signed in; the other four returned `no-nonce` (which means
  `wpApiSettings` is absent, i.e. not signed in - not a permissions problem).
  `open-chrome.mjs <site>/wp-admin/ <minutes>` per site, and the operator must sign
  in to each one.
- **`open-chrome.mjs` holds the profile lock.** `launchPersistentContext` cannot
  attach while it runs, so cc-via-browser must wait for it to exit (or be stopped)
  before it can pull. Sequence is: open-chrome -> sign in -> stop it -> pull.
- Closing the Chrome window makes open-chrome.mjs exit 1 on
  `page.waitForTimeout`; that is the human saying "done", not a failure.
- The `in-browser reach` line printing `plugin:403, me:401` is NOT a problem: those
  are cookie-less probes. The nonce-bearing `page.evaluate` fetch still returns 200.

**The brain push dies with it (2026-09-07).** `brain-sync.php push` uses the same
HTTP client, so during a challenge it reports `request failed` on all nine sites.
The consequence matters more than the failure: the operator brain stored ON the
sites is then STALE, `operator_brain_status` will mislead, and a new session that
pulls from a site would overwrite newer local memory with an older copy. During a
challenge, D:/cc-assistant/memory is the only source of truth. Do NOT pull.

**Why other sessions did not know any of this.** The unblock procedure lived only
in two project CLAUDE.md files (D:/cc-assistant and the plugintesting folder). A
session started anywhere else got nothing, so it re-diagnosed from scratch or
reached for a visible browser. Fixed 2026-09-07 by putting the procedure in the
USER-level file ~/.claude/CLAUDE.md, which loads in every session regardless of
working directory. Machine-level facts belong there, not in a project file.
