---
name: reference_google_webapp_headless_login
description: "Headless Chrome with exported Google cookies renders gemini.google.com LOGGED OUT even when all 38 cookies import; the RPC client on the same cookies works. Verify sign-in by avatar/Sign-in text, never by URL."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-04T04:17:52.792Z
---

Driving gemini.google.com (or other Google web apps) in headless Playwright
with cookies exported from Chrome (the `nlm login` jar) does **not** sign in,
even when every cookie — SID, HSID, SSID, APISID, SAPISID, the `__Secure-1P/3P*`
set, fresh `SIDCC`/`__Secure-1PSIDCC` — imports cleanly. The page shows a
"Sign in" button and greyed tools. Google binds the *web* session to more
than cookies. Meanwhile `gemini_webapi` on the same `__Secure-1PSID` /
`__Secure-1PSIDTS` pair works, because the RPC endpoint is lenient.

Three traps that cost a pass each:
- **A URL check is not a sign-in check.** `not url.includes("accounts.google.com")`
  returned true on a logged-out page. Test for the account avatar and the
  absence of "Sign in" / "Sign in to try tools" — or look at the screenshot.
- **`__Host-*` cookies** (`__Host-1PLSID`, `__Host-3PLSID`, `__Host-GAPS`) are
  refused by `addCookies` when given a `domain`, and also when given `url`
  *plus* `path`. Pass `{name, value, url, secure:true, sameSite:"None"}` only.
- One bad cookie fails the whole `addCookies` batch with "Invalid cookie
  fields" and names nothing. Add singly in try/catch to find the offender.

The only reliable headless path is `launchPersistentContext` on the real
Chrome profile — which needs Chrome closed. When a human is present, asking
them to look at the UI is faster than any of this.

Related: [[feedback_dom_is_ground_truth_not_parsers]],
[[feedback_probe_discipline_positive_controls]], [[project_faceless_video_studio]].
