---
name: reference-canva-mcp-video-pipeline
description: "Canva MCP is good for stills, unusable for text-carrying video - auto typewriter animation leaves the headline legible ~0.5s of each 5s slide"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 84e2db8e-8283-4179-b7e0-05ac749ff89a
  modified: 2026-08-04T05:56:07.199Z
---

Measured 2026-08-04 against the live connectors. Full detail in the **`canva-production` skill**
(`~/.claude/skills/canva-production/SKILL.md`) - read that, not this.

**Headline finding:** Canva-native MP4 export is **unusable for video that carries a message**.
Canva auto-applies a typewriter entrance plus scroll-away exit with no MCP control. Slide 1 measured:
0.2s "H" -> 3.2s full text -> 4.7s gone. The headline is fully legible for roughly **0.5 of each
5.0 second slide**. Page duration is exactly 5.000s, confirmed on two separate designs. No audio
stream at all.

**Canva IS good for stills.** PNG export is faithful and the stock photography is the whole reason
to use it. `generate-design` reaches Canva's stock library indirectly; there is no way to search it.

**Two corrections to earlier assumptions I got wrong:**
- Canva **does** honour exact hex from the prompt. Verified `#DA1212 #041562 #11468F #555555`
  rendered exactly. My "AI can't guarantee hex" claim was wrong.
- `generate-design` **does** accept `design_type: "presentation"` on `canva-alt`. The
  `request-outline-review` approval widget only gates the claude.ai connector.

**The real blocker for brand-exact output is the typeface.** `format_text` cannot set font family.
A generated serif cannot be corrected to Montserrat via MCP. Only a hand-built Brand Template fixes
it, and none exist in the client account yet.

**Canva injects boilerplate that must be stripped every time:** `[Presenter Name]` + role line
(a HARD rule violation on ER sites), `hello@reallygreatsite.com`, `@reallygreatsite`, and decorative
doodles. Canva follows aesthetic instructions and ignores content instructions.

Accounts: `mcp__claude_ai_Canva__*` = Focus Academy. `mcp__canva-alt__*` = client work, and it is
the only one with editing transactions. See [[reference-per-site-design-skills]] for brand tokens.
