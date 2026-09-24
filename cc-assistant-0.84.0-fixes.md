# CC Assistant 0.84.0 — fixes and remaining work

Prepared for Sumit on 8 September 2026. This update addresses the highest-priority reliability gaps identified after 0.83.0. The local source and Claude bridge are updated; the WordPress ZIP still needs uploading to the live site.

| Problem | What changed |
| --- | --- |
| A wrong-domain link could pass verification if its path matched. | Compare resolved URLs, retaining domain, scheme, query, fragment and trailing slash; respect HTML base URLs. |
| Empty expected metadata or unresolved SEO variables could falsely pass. | Return inconclusive; retain complete literal expectations. |
| Another client or editing route could bypass local Claude hooks. | WordPress owns short-lived evidence receipts and checks them at the shared REST proposal queue. |
| Page or relevant configuration changed after inspection. | Drafting and approval compare observed state and block stale proposals. |
| Nested Elementor edits, imports and rebuilds bypassed existing validators. | Shared checks cover those paths, repeater fields, composed drafts and publication; recheck at approval and persistence. |
| An Elementor save could fail or be filtered. | Read back the stored tree before reporting success. |
| Undoing a widget edit left newly introduced settings behind. | Restore previous settings exactly and remove introduced keys. |
| Queue database failure could appear successful. | Return and propagate a storage error. |
| Different approvals or rollbacks could overlap. | A database lock serializes CC Assistant approvals and rollbacks per site. |

Claude receives these evidence rules in `whoami` and tool discovery. Local operator instructions were updated. Existing SiteGround browser transport and credentials were preserved.

Workflow: `whoami`, inspect the target, propose a supported change, approve in WordPress, verify fresh output. Published pages use `verified_page_audit`; drafts/templates use full `get_post(slim=false)`; Kit edits use `get_kit_settings`; option edits use `get_plugin_settings`. Observations expire after ten minutes when drafting. Pending work may be reviewed later if its captured state still matches.

One behavior change matters: approving one proposal changes page state for its siblings. Remaining proposals against that old state need a fresh read and rebuilt plan. Consolidate related work when possible. Older proposals do not gain receipts automatically; recreate them for the stronger binding.

Validation passed: 33 standalone PHP suites; 158 PHP files checked for syntax; hook tests; browser-transport JavaScript syntax; token storage with and without encryption support; offline MCP initialization/discovery of 164 tools; and 35 WordPress/MySQL/Elementor integration checks. Integration covers real persistence and REST hooks, content and Elementor approval/rollback, stale/forged evidence, CAPTCHA invalidation, settings observations, invalid drafts and locks tested through a second database connection.

The isolated runtime used WordPress 6.9.4, Elementor 4.0.4 and MySQL 8.0.35. Page HTTP responses were controlled fixtures. Production Elementor 4.2.3, Pro 4.0.2 and Rank Math 1.0.276 still need staging compatibility checks. This update did not edit production pages. Live 0.83.0 and two consistent homepage audits were verified previously; live 0.84.0 cannot be verified until installed.

Upload `D:\cc-assistant\dist\cc-assistant-0.84.0.zip` through WordPress's plugin update screen, then restart the Claude MCP connection. Confirm `whoami` reports plugin and bridge 0.84.0 and repeat the homepage audit. Originals and validation logs are in `D:\cc-assistant\backups\guards-20260908-0840-05424d33`.

Remaining improvements from the review:

- Feature-level adapters for Rank Math, Polylang, Elementor Kit/Pro templates and Speed Optimizer, with explicit version and license coverage. Discovery still cannot prove every feature or UI option.
- A combined SEO report linking deterministic HTML findings with Search Console URL Inspection, browser checks and Core Web Vitals. Keep current HTML and Google's indexed snapshot distinct; see [Google's URL Inspection API documentation](https://developers.google.com/search/blog/2022/01/url-inspection-api).
- A production-version staging test with real browser rendering, templates and SiteGround transport.
- Migrate automation to the existing operator role with its own Application Password, then retire the old credential after verification. See [WordPress Application Password authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/). No credentials were created or revoked here.
- Signed bridge distribution and separation of private per-site memory before syncing operator knowledge across sites.

The database lock coordinates CC Assistant only; third-party editors and plugins do not participate. Unknown custom controls, visual effects, content truth and Google indexing still require their own evidence. These fixes prevent concrete false-success and stale-execution paths; they cannot guarantee Claude never guesses in conversation.
