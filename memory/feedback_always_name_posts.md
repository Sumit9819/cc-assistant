---
name: Always pair post IDs with post titles in user-facing output
description: When referencing posts in summaries, tables, or status updates, include the post title — never the bare ID alone. The user can't recall posts from numeric IDs.
type: feedback
originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---
In any user-facing reference to a post, always include the title alongside the ID. Acceptable forms:
- `8370 ("Dermaplaning: The Secret to Smooth Skin and Flawless Makeup")`
- `Dermaplaning (8370)`
- `"Dermaplaning…" (post 8370)`

Unacceptable: `8370`, `post 8370`, `pending 266 (8370)` — without the title the user has to cross-reference the WP admin to know which post.

**Why:** 2026-05-11 — after queueing pillar-link inserts and redirects, the summary table listed posts by ID-only (`262 | 8359`, `269 | 8921`). User pushed back: "can you provide me the name of the post, it is very difficult to find post with just number."

**How to apply:**
1. Any table column showing a post ID needs a sibling "Title" column or a combined `ID — Title` cell.
2. Inline mentions: write `service page 137 ("IV Therapy in Irving, TX")` not `service page 137` on first reference per turn; the bare ID is fine on subsequent mentions in the same paragraph.
3. Pending-change summaries: include the affected post's title in addition to the pending_id.
4. Memory notes and session logs (`update_site_memory_notes`) can stay terse with IDs for token efficiency — this rule is for the chat surface the user reads.
