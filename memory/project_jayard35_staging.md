---
name: jayard35.sg-host.com is staging for erofwhiterock.com
description: jayard35 is a SiteGround staging mirror — production lives at erofwhiterock.com — workflow is perfect-on-staging then URL swap
type: project
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
`jayard35.sg-host.com` is the **SiteGround staging URL** for the **production site at erofwhiterock.com**. The user's workflow is: build/test/perfect content + plugin changes on staging, then SiteGround does a URL swap (jayard35 → erofwhiterock.com).

**Why:** Standard SiteGround staging workflow — temp URL during build, swap to real domain when ready. Lets the user iterate on translated content (~37 Spanish pages were the reason for the multilingual plugin pass) without touching the live production.

**How to apply:**
- Treat jayard35 as fully expendable for testing — break it, reset it, no production risk.
- GSC OAuth on jayard35 is meaningless: the verified Search Console property is `erofwhiterock.com`. Skip "Connect Search Console" on the staging dashboard — it's just noise here. Reconnect after URL swap.
- After URL swap, the user must update `.mcp.json` on their local machine: change `CC_WP_URL` from `https://jayard35.sg-host.com` → `https://erofwhiterock.com`. Server-name key in `.mcp.json` (`cc-assistant-jayard35-sg-host-com`) can be renamed for cleanliness or left as-is — only the env URL matters.
- Site fingerprint changes at swap (fingerprint = sha256(site_id + home_url), and home_url flips). The cc_assistant_site_id option survives, so it's only the URL component that shifts. Cosmetic.
- Site memory, pending changes, snapshots, GSC tokens, plugin settings, link graph — all DB-bound, all carry over with the swap.
- If a SEPARATE cc-assistant install is currently running on production erofwhiterock.com, the swap will replace it with the staging copy's state. Confirm nothing critical lives only on the production copy before swap day.
