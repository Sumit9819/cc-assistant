# READY TO QUEUE — sids-ponds footer 2077 / module 23 (Font Awesome brands removal)

Status on 2026-08-26: **fully prepared and verified, NOT queued.** sids-ponds.com
became unreachable (DNS timeout) before the real queue call. Nothing was written
to the site. The queue was empty at the time.

## The change
Post **2077** (`et_footer_layout`), **module 23** (`et_pb_code`).
Replacement `inner_content` is in **`module23.txt`** next to this file.

- length **9,160**, sha1 **1259279b7e0c30324d1d5c6c4a77efbd7fe4c53b**
- line-break holders **35** — must match the original EXACTLY (see gotcha below)
- expected `character_delta` on the post: **+7,130** (13,090 -> 20,220)

## Why
`fa-brands-400.woff2` is **109,808 bytes** and loads on every page. Only five
glyphs need it:

| glyph | icon | where |
|---|---|---|
| f429 | stripe | module 23, literal character |
| f1f0 | cc-visa | module 23, literal character |
| f1f1 | cc-mastercard | module 23, literal character |
| f1f3 | cc-amex | module 23, literal character |
| e07b | tiktok | Divi's own `divi-dynamic.min.css` |

The TikTok one is a silent fallback: Divi emits `content:"\E07B"` but its
ETmodules font has no glyph there, so the browser falls through the stack to
Font Awesome Brands and pulls 110KB for one character.

Replacing all five with inline SVG means no brands glyph is ever requested.

## What is verified
- Artwork is the official Font Awesome **6.4.2** SVG for each icon (jsDelivr npm
  mirror), same release already on the page. Nothing hand-drawn.
- Codepoints read from the live stylesheet, not memory. **f429 is `stripe`, NOT
  apple-pay** — a first pass got this wrong and a local render caught it.
- Rendered side by side vs the current webfont output: indistinguishable
  (`preview2.png`).
- TikTok rule injected into the LIVE page and screenshotted: renders identically
  (`inject-social.png` vs `live-before-social.png`).
- Divi gives `.et-social-tiktok a.icon:before` a `display:block` 44x44 box, so
  `content:""` keeps the box and the background paints. Checked, not assumed.
- Dry run 3 passed: delta +7,130, `introduced: []`, only pre-existing
  `sentence_length` failing.

## NOT verified
The actual saving (does fa-brands stop downloading?) was going to be measured by
loading the page with and without the change and diffing the font requests. The
site went unreachable first. The reasoning is sound — a webfont is fetched only
when a rendered glyph needs it, and all five consumers are replaced — but it is
**reasoned, not measured**. Measure it after applying.

## Gotcha that cost two dry runs
Divi's parser counts `[et_pb_line_break_holder]` as a module OPEN tag with no
closer:
- **more** than the original -> `rebuild_parse_mismatch` 422, hard refusal
- **fewer** -> `shortcode_preservation` lint fires (same bracket token)

So the replacement must carry **exactly 35**, and use holders rather than raw
newlines inside `<style>`. `build_module23.py` does this.

## Still open after this
The `<link>` to cdnjs is deliberately KEPT. The solid face (80KB) still serves
`\f48b` truck-fast on 5 menu items (Divi Customizer CSS) and `\f004` heart on the
wishlist (inline `<style>` #20). Removing those too would let the `<link>` go,
dropping a further ~102KB plus the third-party DNS/TLS handshake.
