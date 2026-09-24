---
name: Verify post language before sending Polylang pair, don't infer from source/target intent
description: When sending pairs to polylang/link-translations, the keys (en, es) MUST match each post's actual language taxonomy term, not the source/target direction of the translation work
type: feedback
originSessionId: a48649d4-4501-45c1-b8a4-addd9908cee8
---
When calling `polylang/link-translations` with a payload like `{"en": X, "es": Y}`, the keys map directly to Polylang's language slugs. They are not "source language" / "target language" — they are "the post in this exact language is post-id N."

**Why this matters:** got bitten 2026-05-11. Pair was written as `{en: 4638, es: 5012}` because the workflow direction was "ES source 4638 → new EN translation 5012." But 4638 is the existing ES post and 5012 is the new EN post. With `force_language=true`, the endpoint flipped 4638 to English and 5012 to Spanish, producing an inverted pair. Caught it on verification (post 5012 came back as `lang:"es"` with `/es/` URL).

**How to apply:**
- Before constructing the pair, look up each post's current `lang` field via `list_posts` or `get_post`.
- The pair key for each post must equal that observed `lang` value, not the role in the translation workflow.
- Verify after the call: re-fetch the post and confirm `lang` + `translations` match expectations.
- If many pairs are flowing through one call, list_posts both sides upfront and assemble pairs from observed languages, not from a remembered "new vs original" mapping.
