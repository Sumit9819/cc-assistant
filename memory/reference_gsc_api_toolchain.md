---
name: reference_gsc_api_toolchain
description: "Direct GSC API access works for sids-ponds, mammothmachinery, rockandsoul and growthboss - scripts live in D:\\gsc-tools, no more manual xlsx exports"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-19T15:20:22.022Z
---

**Search Console API access is live.** Stop asking the operator to export spreadsheets.

Scripts: `D:\gsc-tools\` — see `README.md` there for full usage.

```
python D:\gsc-tools\gsc-pull.py       # per-page queries, brand-flagged
python D:\gsc-tools\gsc-cannibal.py   # queries where 2+ of our pages rank
python D:\gsc-tools\gsc-compare.py    # before/after with a site-wide control
python D:\gsc-tools\gsc-login.py      # re-auth
```

Token: `C:\Users\sumit\.gsc\token-sids.json` (read-only scope). Shared auth in
`gsc_common.py`. Output to `D:\gsc-tools\out\`.

Properties readable: **sids-ponds.com, mammothmachinery.ca, rockandsoul.com,
growthboss.co** (siteFullUser on all).

**The long-standing "client cannot provide API access" belief was wrong.** The API
uses the signed-in Google account's existing permissions — no client action needed.
The old token (`~/.gsc/token.json`) only saw napervillehwclinic because it was
created with a different account. One re-login with the right account fixed it.

Gotchas:
- The OAuth client must be **Desktop app** type. `D:\API\client_secret*.json` is a
  *web* client with no `redirect_uris` and will not work; the older
  `D:\client_secret*...json` (installed) does.
- Add each new site's brand terms to `BRAND_PATTERNS` in `gsc_common.py` BEFORE
  trusting a priority list. Unflagged brand queries make cannibalisation look like
  a content problem — see [[feedback_gsc_window_28d_before_diagnosing]].

Related: [[reference_gsc_impression_bug_2026]], [[project_sids_ponds_gsc_baseline_2026]].
