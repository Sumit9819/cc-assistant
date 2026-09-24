# CC Assistant 0.82.0 — fixes and connection report

Source: `D:\cc-assistant`. Prepared on 8 September 2026.

The reviewed source was 0.81.3. This release fixes the reproduced approval, persistence, rollback, escaping, bridge-update and hook failures. It also adds a tested browser transport for the SiteGround-protected site.

**Deployment boundary:** these changes update the local source and Claude bridge. The read-only check of **erofwhiterock.com** reported live plugin **0.76.7**. Install `dist/cc-assistant-0.82.0.zip` on that site for the server-side fixes to take effect. No live content edits or plugin deployment were performed.

## What was fixed

| Area | Change |
|---|---|
| Automation could approve itself | Both `/pending/{id}/apply` and `/pending/{id}/decide` now require an administrator review session with a valid WordPress REST nonce and reject Application Password authentication. |
| Overprivileged automation account | Added the optional **CC Assistant Operator** role with access to plugin routes, no core publishing/admin capabilities, and a REST namespace restriction. Existing administrator users are not silently downgraded. |
| Wrong timezone comparisons | New pending records carry explicit UTC timestamps; historical local timestamps use WordPress timezone conversion. Conflict checks include stable post fields, metadata and taxonomy. |
| False approvals and interrupted requests | Changes remain `applying` until required writes/recovery records complete. Failures and rollback failures have separate inbox states. Exceptions and normal PHP shutdown interruptions are recorded. |
| Approval/rejection races | Rejection and rollback claim only the expected prior status. A losing approval request cannot mark another request's in-progress operation failed. Rejection failures are reported accurately. |
| Missing snapshots | Required snapshot and recovery-record failures stop content writes. Import, section replacement and restore paths check snapshot errors. |
| Incomplete snapshot restoration | New snapshots include slug, status, excerpt, author, dates, parent/order, other post fields, metadata and taxonomy. Restore removes introduced CC Assistant/Elementor keys while retaining unrelated newly added metadata. Legacy snapshot limitations are reported. |
| Nine missing rollback handlers | Added recovery for asset-reference replacement, category updates, redirect creation/deletion/untrash, full Elementor import, section replacement, emergency-service schema and post trashing. Exact asset values and redirect state are recorded before mutation; later edits can block recovery. |
| JSON corruption | Content and metadata persistence now supplies the slashing WordPress expects; structured metadata is read back to detect failed persistence. Snapshot restoration preserves escaped values. |
| Missing reviewer attribution | REST review actions now pass the numeric WordPress user ID. Historical missing attribution is not guessed. |
| Unsafe external fetches | Competitor validation rejects bracketed IPv6 literals and uses WordPress URL validation; external GET/HEAD requests use safe HTTP helpers, including redirect validation. |
| Unsafe bridge replacement | Bridge pull honors `missing_only`, checks downloaded hashes, validates PHP/JavaScript/JSON, writes via a temporary file and retains a `.previous` backup. Browser worker assets are included in bridge distribution. |
| Failed reads unlocking Claude writes | Failed whoami/probe responses no longer create success markers; failed automatic brain pushes no longer mark themselves successful. |
| Token encryption fallback | Google token storage no longer falls back to base64 when encryption fails or OpenSSL is missing. Errors propagate through token exchange and refresh. Legacy records remain readable. |
| Maintenance | Deactivation removes all owned cron hooks, including jobs with arguments. ZIP builds fail when PHP syntax validation is unavailable. Version/readme/setup instructions now reflect actual reviewed-publication behavior. |

## Talking to erofwhiterock.com despite SiteGround CAPTCHA

The browser transport worked through the site's normal browser challenge flow. An authenticated direct browser check returned **HTTP 200**, and the PHP MCP bridge subsequently completed **two authenticated whoami reads** with no stderr errors. The site fingerprint was `c5bc5dbe61b689ce`.

The erofwhiterock.com entry in `D:\cc-assistant\.mcp.json` is configured to use `CC_MCP_TRANSPORT=browser`. Restart Claude Code/the site's MCP process, open `D:\cc-assistant`, and ask:

> Connect to erofwhiterock.com, run whoami, then tell me the site name, plugin version and pending-change count.

Chrome uses a separate persistent profile and the existing Application Password. It does not require a WordPress administrator login. No SiteGround protection was disabled. A future challenge can still need visible browser interaction; `CC_BROWSER_HEADLESS=0` enables that. SiteGround documents that its anti-bot system can challenge requests before they reach the site: [SiteGround CAPTCHA explanation](https://www.siteground.com/kb/seeing-captcha-website/).

The worker never automatically replays a mutation after an interrupted response. Check Pending Changes first if a write's outcome is unknown. Other site entries retain their existing transport.

Final verification used the actual installed MCP command, arguments and environment: bridge **0.82.0**, two successful whoami reads, exit code 0 and no stderr output. A fresh browser profile remained challenged, so the dedicated profile that had completed SiteGround's normal challenge was copied into `D:\cc-assistant\.browser-profiles\`. The uncleared profile was preserved in the backup folder. Future clearance expiry may still require browser interaction.

After installing the ZIP, create a dedicated **CC Assistant Operator** user and its Application Password, update this site's MCP credentials, and verify whoami. Existing administrator passwords retain their wider WordPress privileges until replaced/revoked. [WordPress Application Passwords](https://developer.wordpress.org/advanced-administration/security/application-passwords/).

More setup and migration details: `wp-content/plugins/cc-assistant/CONNECTION.md`.

## Validation

- **31/31 standalone PHP test files passed**: all 27 existing suites plus four new suites.
- **151/151 PHP files passed syntax checks**.
- New coverage includes approval races, both registered approval endpoints, positive/negative timezone offsets, metadata-only drift, snapshot failures, full restoration, all nine added rollback types, bridge tampering/overwrite mode, token encryption and cron cleanup.
- Python hook regression checks passed.
- JavaScript syntax check passed; offline MCP initialization/tool listing returned **0.82.0** and **163 tools**.
- Authenticated browser/MCP connection checks against the selected live site passed. No live writes were used as tests.
- The upload ZIP has forward-slash paths, includes the browser assets, and excludes tests, backups and configured passwords.

These are standalone tests with WordPress doubles and in-memory SQLite, plus read-only live connectivity checks. They are not a full WordPress/MySQL/Elementor/Rank Math integration suite. Recovery under actual third-party plugin hooks should be exercised on a staging clone before production deployment.

## Remaining limits and recommended improvements

1. **Finish the credential separation.** Use the new operator role for Claude and a separate administrator for review. Move plaintext application passwords to a private launcher or OS credential store; keep them out of Git, archives and shared memory.
2. **Add a trusted release channel.** A hash from the same WordPress site cannot authenticate its PHP/hooks. Sign releases from a separately trusted source and separate each client's memory from shared rules. Those architecture changes remain recommendations, not claimed fixes.
3. **Add integration CI and a mutation registry.** Test queue → approve → verify → rollback against real WordPress/MySQL with supported Elementor and Rank Math versions. Declare each mutation's snapshot, apply, verification and recovery behavior in one registry.
4. **Improve recovery UX.** Add a recovery wizard for stale in-progress operations, operation IDs/idempotency keys and locks around changes to the same resource. Abrupt process kills can leave an in-progress state; multi-row operations are not a transaction across database writes, hooks and cache effects.
5. **Improve connection visibility.** A dashboard showing bridge/site versions, CAPTCHA readiness, credential scope and last successful call would make support easier. Group the 163 tools into optional capability sets and add accurate MCP tool annotations to reduce model context use.

Historical snapshots cannot recover fields never saved, and old redirect/asset changes cannot invent missing recovery records. Old queue timestamps remain ambiguous if the site's timezone changed after they were created. Failed or conflicting operations require inspecting current state and recovery data before resubmitting.

Backups and a normalized source patch are saved under `D:\cc-assistant\backups\fix-20260908-0820-b2acb45c\`; the install script verifies unchanged source hashes before replacing files. The upload package is `D:\cc-assistant\dist\cc-assistant-0.82.0.zip`.

ZIP SHA-256: `3855153225bdcac8fbd28a8c92e8e97a1ef93b809ea31ef25e280217baf29cfc`.
