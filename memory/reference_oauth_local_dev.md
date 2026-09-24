---
name: Google OAuth on Local by Flywheel sites
description: Google OAuth rejects .local TLDs as redirect URIs; the cc-assistant GSC tab has a Redirect URI override field for tunnel URLs
type: reference
---
Google's OAuth client only accepts redirect URIs on public TLDs (.com, .org, etc.), or the literal hosts `localhost` / `127.0.0.1`. It rejects `.local`, `.test`, `.localhost`, `.invalid`, `.example`. This breaks Local by Flywheel sites by default, since `https://plugintesting.local/...` is not acceptable.

**Workarounds (in order of ease):**
1. **Local Live Link** — toggle Live Link in the Local app to get a temporary `https://random-words.localwp.com` URL that tunnels to the local site. Use the Live Link URL as the redirect.
2. **ngrok / Cloudflare Tunnel** — same idea, different tunnel.
3. **Authorize on the live site** — install the plugin on production, connect there.

**Plugin support:**
- Settings > Search Console > Step 2 has a "Redirect URI override" field.
- When set, `CC_Assistant_GSC::redirect_uri()` returns the override instead of `admin_url()` so OAuth flows use the tunnel URL.
- The default URI helper is `CC_Assistant_GSC::default_redirect_uri()`.
- `CC_Assistant_GSC::host_is_local_only()` detects local-only TLDs and surfaces a warning banner on the GSC settings tab.
- After OAuth completes, the tokens are bound to the user's Google account (not the URL), so daily cron sync continues working through the original `.local` URL.
