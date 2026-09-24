---
name: cc-assistant session protocol (call whoami first, log before stopping)
description: How to use the cc-assistant MCP server for cross-session memory — call whoami to read the session_recap, append a one-liner before stopping
type: feedback
originSessionId: ea116cb0-87a6-4912-b9d9-be19c7d2c2f5
---
On any WordPress site connected via the **cc-assistant** MCP server:

1. **At session start:** call the `whoami` MCP tool first. Its `session_recap` field carries memory across Claude Code restarts — pending_count, last 5 pending changes (id, summary, status), last_snapshot, and `notes_tail` (last ~500 chars of the site memory log). Read it before proposing changes so you don't duplicate work already in the queue.
2. **Before stopping:** call `update_site_memory_notes` with one short line about what changed. Default mode is append (timestamped). Keep it terse — only the last ~500 chars surface in the next session's recap.

**Why:** The user explicitly asked for cross-session memory that's token-efficient. Full transcript replay would defeat the purpose; a rolling summary fed via `whoami` is one round trip (~600 tokens) at session start instead of dumping history into context.

**How to apply:** Any project where `.mcp.json` lists a `cc-assistant-*` server (the plugin is multi-site / project-scoped). Each WP project root has a `CLAUDE.md` repeating this convention, so following it is the documented contract — not an optional optimization.

Skip this protocol only if the user is doing a one-shot read-only query (e.g. "show me the last snapshot") where there's nothing worth logging.
