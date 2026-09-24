---
name: CC Assistant performance principles
description: Hard rules for keeping the cc-assistant plugin off the front-end critical path so it never slows the WP site
type: feedback
originSessionId: 4bb11e9a-33e3-4141-be26-b4503505ff06
---
The cc-assistant plugin must never measurably slow front-end page loads. Follow these rules for every feature:

- **No plugin code on front-end requests.** The main plugin file may register hooks, but class files load only inside admin, REST, or cron contexts. Do not `require_once` class files at the top of the main plugin file. Use `is_admin()` gating and `add_action('rest_api_init', ...)` lazy loads.
- **No DB queries on front-end requests** unless explicitly required by a feature the user enabled (and document why). The plugin currently makes zero front-end queries; keep it that way.
- **No autoloaded options larger than a few KB.** Heartbeat option is set with `autoload=false`. New options follow the same default unless tiny and accessed every request.
- **All custom tables have indexes** on every column used in WHERE or ORDER BY. Already done for `wp_cc_snapshots`, `wp_cc_pending_changes`, `wp_cc_embeddings`. New tables must follow.
- **Embeddings, similarity scoring, and any external API call run via WP-Cron**, never on `save_post` or any synchronous editor action. Editor saves must stay fast.
- **Bulk operations chunk through cron**, not synchronous loops. A "scan all posts" action queues 10 to 50 posts per cron tick, never iterates the full set in one request.
- **No external HTTP calls during admin page renders.** GSC, embeddings, and similar queries hit a local cache first; refresh happens via cron with TTLs.
- **Heartbeat write is acceptable** because it only fires on authorized REST calls (Claude Code clients), not on front-end traffic.
- **OPcache friendly.** Files are stable, no eval, no dynamic includes. Already true; do not introduce dynamic class loading.

**Why:** User runs multiple WP sites and explicitly required this plugin not to hamper site speed. Local by Flywheel sites cache aggressively, and a slow plugin breaks that cache benefit.

**How to apply:** When adding any new feature, ask three questions before writing code: (1) Will this code run on a front-end request? If yes, redesign. (2) Does this query/call block an editor save or admin page render? If yes, move to cron. (3) Does this option need to be autoloaded? If not, set `autoload=false`. Run the front-end-load test (count plugin files included on a public page view) after major features and confirm the count stays at 1 (just the main plugin file).
