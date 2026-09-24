---
name: feedback-never-hand-copy-post-bodies
description: Never manually copy large post_content bodies into draft_update_post_content for surgical edits — transcription drops chars and triggers deletion_ratio lint fail
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 8054b32b-03d7-46d2-a9bf-8d5474be45b2
---

For non-Elementor posts, the cc-assistant draft_update_post_content tool requires the FULL post body as a parameter. Manually copying 10+ KB of HTML into a tool call from get_post output is unreliable: I lost 59 chars on a 16KB body (post 5595, ER of Lufkin, 2026-05-18) doing a single inline-link insert, which tripped the deletion_ratio lint and produced a bad pending (#274) the user had to reject.

**Why:** The body contains curly apostrophes, `&amp;` entities, `&nbsp;`, nested `<span>` wrappers, CRLF newlines, and other characters where straight/curly substitutions or invisible-char drops cause cumulative loss. Eyeballing parity is not feasible at that size.

**How to apply:**
- For Elementor posts: use draft_update_elementor_widget per-widget — only touches one widget's settings, no body-rebuild risk.
- For non-Elementor (raw HTML) posts on cc-assistant v0.20.0+: use **`draft_patch_post_content`** instead — takes `{search, replace}` pairs and applies them server-side, so the body is never re-emitted by the model and CRLF/curly-char drift is impossible. Each search must match exactly once (refetch with get_post and copy the substring byte-for-byte; for ambiguous matches add surrounding context).
- For sites on pre-v0.20.0: don't queue surgical edits via draft_update_post_content. Either curl the WP REST API with auth + sed-replace + post back, or document the change as a manual edit recommendation for the user.
- Always check the `character_delta` in the response — if it's negative for an additive change, something dropped. Reject and retry programmatically.
