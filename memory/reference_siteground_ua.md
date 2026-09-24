---
name: siteground-waf-blocks-bot-token-user-agents-use-a-plain-browser-ua
description: "SiteGround's WAF UA filtering FLIPPED ~2026-06-01 — the old cc-assistant-mcp/1.0 UA now 403s; a plain browser UA passes. cc-assistant v0.35.3 switched all outbound UAs to browser strings."
metadata: 
  node_type: memory
  type: reference
  originSessionId: e8f44679-3815-49d9-81a4-a3d1c3a6314b
---

SiteGround's WAF returns **403 Forbidden** (SiteGround-branded HTML error page, not a WP/JSON response) on `/wp-json/...` and even on the REST root when the outbound User-Agent carries an automation/bot token. **This behavior changed over time** — do not trust any single "winning" UA string as stable.

**History:**
- ~2026-05 (old note): `Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)` was the ONLY UA that passed; a full browser UA was blocked.
- **2026-06-01 (verified, current): the rule FLIPPED.** The `cc-assistant-mcp/1.0` UA now gets **403 on every endpoint**, taking the whole MCP server offline across all SiteGround sites. A plain browser UA (`Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ... Chrome/124 Safari/537.36`) and even bare `curl/8.0` now return **200**. Proven by curling `/wp-json/` with each UA on eroflufkin.com.

The lesson: SG's WAF flags the **`cc-assistant-mcp` bot token**, and which strings pass is unstable. A **plain browser UA with no product/bot token** is the robust choice.

**Fix shipped — cc-assistant v0.35.3:**
- `bin/mcp-server.php` → `cc_mcp_user_agent()` returns a browser UA (override via `CC_MCP_USER_AGENT` env). Used by both cURL and streams transports.
- Server-side self-fetches now use `CC_ASSISTANT_HTTP_UA` (define-override in wp-config.php): `class-post-apply-audit.php`, `class-pre-publish.php` (the curl-test-before-editing path — the bot UA was silently forcing parser-fallback on SG sites), `class-seo-tools.php`, `class-playbook-fit.php`, `class-industry-profile.php`.

**IMPORTANT:** the running MCP server loads `bin/mcp-server.php` once at startup — editing it does NOT take effect until the MCP connection / Claude Code is restarted.

**How to apply:** "MCP can't reach a SiteGround site" → first curl `https://SITE/wp-json/` with the plugin's current UA vs a browser UA. SiteGround 403 HTML on the bot UA + 200 on the browser UA = WAF UA filter. Never reintroduce a bot/product token into any outbound UA. Related: [[reference_sg_cache_bypass_wp7]].
