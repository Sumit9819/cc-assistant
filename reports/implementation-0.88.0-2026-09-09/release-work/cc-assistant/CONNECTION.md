# Connecting Claude to WordPress

CC Assistant 0.82.0 supports normal HTTPS REST requests and an optional browser transport. Both use a WordPress Application Password. Human approval uses a separate administrator browser session.

## SiteGround CAPTCHA

Set `CC_MCP_TRANSPORT` to `browser` in the site's `env` object in your local `.mcp.json`, then restart its MCP server or Claude Code:

```json
{
  "CC_MCP_TRANSPORT": "browser"
}
```

Keep your existing `CC_WP_URL`, `CC_WP_USER` and `CC_WP_APP_PASSWORD` entries. Use the canonical HTTPS site URL.

The PHP bridge starts a persistent Chrome worker. Chrome loads the site's public REST index normally, retains SiteGround clearance cookies in a dedicated profile, and sends authenticated plugin requests from that same origin. The profile does not require a WordPress administrator login.

Requirements on the computer running Claude:

- PHP 8.0+ with `proc_open` available.
- Node.js 20+ and Google Chrome.
- Playwright. From the plugin folder run `npm install --prefix bin/browser --ignore-scripts`. The worker can also use an existing installation at `~/.cc-assistant/wcag`; this was the installation used for the erofwhiterock.com check.

Optional environment settings:

| Name | Purpose |
|---|---|
| `CC_NODE_BINARY` | Explicit path to Node |
| `CC_BROWSER_EXECUTABLE` | Explicit path to Chrome |
| `CC_PLAYWRIGHT_PACKAGE` | Absolute package.json path beside an existing Playwright installation |
| `CC_BROWSER_PROFILE_ROOT` | Parent directory for separate site profiles; default `~/.cc-assistant/browser` |
| `CC_BROWSER_HEADLESS=0` | Open a visible browser if SiteGround requests manual interaction |

Only run one MCP process per site/profile at a time. Restart the old process before launching another. Profile locks are respected.

The worker checks readiness with read-only requests. It never automatically retries a mutation after sending it: an interrupted response can have an unknown outcome, so inspect Pending Changes before trying again. This transport does not disable SiteGround protections. Clearance may expire or a future challenge may require manual interaction. If SiteGround continues challenging the operator, ask its support to review that operator's IP and API access policy.

On 8 September 2026, a direct authenticated browser check and two reads through the staged MCP bridge succeeded against erofwhiterock.com. The site reported plugin **0.76.7**. Installing the **0.82.0** ZIP on the site is necessary for server-side fixes; changing the local transport alone cannot update the live plugin.

## Separate automation and review

After updating the site, create a dedicated WordPress user with the **CC Assistant Operator** role. Generate an Application Password for that user and use its username/password in the site's MCP entry. Verify with `whoami`.

The role can access CC Assistant REST routes and has no core publication or administration capabilities. Both plugin approval endpoints reject Application Password authentication. Approve, reject, and roll back in the WordPress administrator inbox.

Existing administrator credentials continue to work for normal plugin calls, but still carry their account's broader WordPress privileges. Creating the new role does not downgrade existing users or scope their passwords automatically. Keep configuration/passwords private; revoke a superseded administrator Application Password after the new operator connection works.

## Recovery and upgrade behavior

- New snapshots save post fields, metadata and taxonomy. Existing snapshots remain readable but cannot reconstruct fields never captured; restoration reports their limitation.
- New pending records save explicit UTC and state hashes. Old timestamps are interpreted in the site's WordPress timezone. A historical timezone change cannot be reconstructed reliably; requeue old proposals when unsure.
- Failed or interrupted apply/rollback operations have separate inbox states. Inspect current content and the saved recovery snapshot before requeueing. A process killed before shutdown handling can leave an in-progress state for manual review.
- The nine previously unsupported rollback types now use recorded recovery data. Old redirect/asset operations without those records return an actionable error. A deleted redirect is recreated with a new Rank Math ID.
- Multi-row operations can still fail after partial work; snapshots and recorded failure states support recovery, but the plugin does not promise a database transaction across WordPress hooks, caches and third-party plugins.
- Bridge downloads verify bytes, syntax and overwrite mode and preserve `.previous` backups. Browser assets are included. A hash supplied by the same site is an integrity check, not a trusted release signature.
- Google tokens require OpenSSL AES-GCM for new storage. Legacy base64 records can still be read and become encrypted on a successful refresh; reconnect Google if credentials need rotating.

## Updating other computers

Distribute this plugin's complete `bin` folder, or after updating the trusted WordPress site run `operator_brain_pull(what="bridge", mode="replace")` and restart MCP. The default `missing_only` mode now correctly leaves existing files alone. Install Playwright separately if absent.
