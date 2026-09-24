---
name: cc-assistant suspicious_chars lint false-positive on Spanish meta
description: Plugin's suspicious_chars lint soft-fails Spanish accented chars (á é í ó ú ñ) — misclassifies valid Latin-1 as suspicious. Items still queue.
type: reference
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
When using `draft_update_seo_meta` for ES posts, the lint check `suspicious_chars` reliably soft-fails on every Spanish accent character (á, é, í, ó, ú, ñ, ¿, ¡). Items still get a `pending_id` and apply normally — it's a soft warning, not a hard refusal.

**Why:** The plugin's character-suspicion regex was tuned only against zero-width / BiDi / control characters but the implementation also catches non-ASCII Latin-1 letters. For EN posts the lint passes; for ES posts it always fails on this check.

**How to apply:**
- When queuing ES meta and seeing only `failed: ["suspicious_chars"]`, treat as pass — the apply will succeed.
- Don't re-craft the meta to strip accents; keep proper Spanish orthography.
- If touching the plugin: the fix is in `class-pre-publish.php` — gate `suspicious_chars` on `pll_get_post_language() !== 'en'` to skip on ES, OR narrow the regex to only zero-width / BiDi codepoints (U+200B-U+200F, U+202A-U+202E, U+FEFF).

Confirmed against erofwhiterock.com on 2026-05-06 — 7 of 7 ES meta drafts in pending IDs 116-137 + 160-163 fired this flag while EN drafts (157-159) passed cleanly.
