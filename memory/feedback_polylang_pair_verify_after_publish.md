---
name: polylang-pair-via-draft-create-post-is-unreliable-verify-after-publish
description: "The polylang_translation_of arg on draft_create_post returns success but doesn't always actually link the pair; the pair must be re-applied via polylang_link_translations after the ES post is published/approved."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: a48649d4-4501-45c1-b8a4-addd9908cee8
---

When creating a Spanish twin of an English post via `draft_create_post` with `polylang_translation_of: <en_id>`, the plugin returns no error and the post lands in the pending queue. **But the Polylang pair often does not actually link** — after the ES post is approved and published, the WP admin post list shows a "+" icon (no translation) in the EN column next to the new ES post, and another "+" in the ES column next to the EN twin.

This happened on Pillar 4 ES (post 5058 ⇔ 5054) and was caught by the user noticing the "+" icons in the WP admin list.

**Why:** the create_draft_post path appears to insert the post as draft, queue the publish_draft pending, and run `pll_set_post_language` + `pll_save_post_translations` immediately on the draft. After approval (which republishes/republishes via the apply layer), Polylang's post_translations taxonomy term may get re-evaluated and the pair drops, OR the pair was never actually written because Polylang requires the post to exist before pll_save_post_translations can pair it.

**How to apply:**

1. After the user approves the ES post and it goes live, **always** call `polylang_link_translations` with the explicit `{en: X, es: Y}` pair to guarantee the link.
2. The fix is idempotent — calling it on an already-linked pair returns ok:1 with no harm.
3. Watch for "+" icons in the language columns on WP admin post list as the canary signal — if the user sees a "+", the pair is broken.
4. Pairing direction matters: keys map to actual language taxonomy assignment, not source/target intent. See [[feedback_polylang_pair_direction]].
5. The underlying plugin gap is tracked in [[reference_polylang_mcp_gap]] — the standalone `polylang_link_translations` MCP tool exists and works, but `draft_create_post`'s inline pairing is the unreliable path.

**Better workflow for bilingual cluster builds:**

For each EN→ES translation pair:
1. Queue ES post via `draft_create_post` with `lang: "es"` (skip `polylang_translation_of`).
2. User approves; ES post goes live.
3. Immediately run `polylang_link_translations` with the pair — this writes the post_translations term against the now-published rows.
4. Verify in WP admin: both posts should show a pencil/flag icon (not "+") in the opposite-language column.
