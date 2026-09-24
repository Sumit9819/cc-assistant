---
name: cc-assistant draft_update_post_content gotchas
description: Line-ending normalization, soft-lint failures, and override_lint behavior when surgically editing post bodies via the cc-assistant MCP plugin
type: reference
originSessionId: fb84c00f-69ad-437a-b116-a8c629075265
---
When using `mcp__cc-assistant-*__draft_update_post_content` to make surgical edits (URL fixes, anchor rewrites) on existing WordPress posts, two reproducible quirks affect every call:

**1. Line-ending normalization inflates the diff.** Posts saved via the WP editor are stored with `\r\n` line endings. The MCP tool returns and accepts content with `\n`. So even a 1-character URL fix shows a `character_delta` of -100 to -250 chars (one byte per line in the post). The diff reviewer sees noisy diffs where every line appears "changed."

**Why:** Round-trip through Claude normalizes CRLF -> LF.
**How to apply:** Before each surgical edit, warn the user (or note in your `reasoning` field) that the diff will look larger than the actual change. Don't try to fix this by manually re-inserting `\r\n` — the tool accepts either, and matching CRLF in your output is fragile across tools.

**2. The lint always fails on existing posts.** Soft warnings: `paragraph_length`, `reading_level`, `html_cruft`, `deletion_ratio` — these trip on virtually any pre-existing irvingwellnessclinic.com post (long sentences, `<span style="font-weight: 400;">` cruft from a prior editor migration, line-ending delta). One post tripped `em_dashes` (legitimate hit on existing `—` in body that the user doesn't want).

**Why:** The lint runs against the full body, not just your delta — pre-existing quality issues fail every change.
**How to apply:** For surgical link/anchor fixes, set `override_lint=true` and explain in `reasoning` that the failures are pre-existing. The hard violations (em_dashes, AI-tells, banned phrases) WILL refuse the change without the override even when you didn't introduce them. If you see `em_dashes` failing, audit whether the post body has `—` characters that should also be cleaned per the user's writing-style rule (no em dashes).

**3. dry_run is not strictly necessary.** The dry_run response gives the same lint diagnostics as the real submission. For surgical edits where you know the failures will be pre-existing, you can skip dry_run and submit live with override_lint=true to halve the round-trips.
