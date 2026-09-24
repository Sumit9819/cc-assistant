---
name: per-site-design-skills
description: Per-website UX/UI + brand design skills live as proper Claude Code skill files under ~/.claude/skills/. Each one is self-contained — design system rules + site-specific brand voice + catchment vocabulary + service inventory + anti-patterns. Load the matching skill at the start of any session that involves visual edits on that site.
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

Per-site design specifications live at:

- `C:\Users\sumit\.claude\skills\erofwhiterock-design\SKILL.md` — ER of White Rock
- `C:\Users\sumit\.claude\skills\erofirving-design\SKILL.md` — ER of Irving (the original, source for cloned content)
- `C:\Users\sumit\.claude\skills\eroflufkin-design\SKILL.md` — ER of Lufkin (small-market calibration)
- `C:\Users\sumit\.claude\skills\irvingwellnessclinic-design\SKILL.md` — Irving Wellness Clinic (wellness/aesthetics, NOT ER)

**Each skill is self-contained.** Design-system rules (typography, spacing, components, mobile, accessibility, performance) are repeated across files for easy reading. Site-specific content (brand voice, geo priority, catchment vocabulary, service inventory, authority sources, E-E-A-T constraints, anti-patterns) is the per-file differentiation.

**Common rule set across all sites:**
- Long-form text container max-width 650px (65ch at 18px)
- 8px grid for all spacing
- Body + lists `text-align: left` (never center)
- `list-style-position: outside`, `padding-left: 24px`, 8-12px between items
- Button 2px corner radius, ≥44px tap target, one primary CTA per section
- No em dashes, no AI tells (lint-enforced)
- Sentences ≤25 words ≥85% of the time, grade 7-9
- All images alt text mandatory, WebP, ≤200KB hero
- WCAG 2.1 AA: 4.5:1 body contrast, visible focus, descriptive aria-labels on phone CTAs
- 8px-grid spacing, section padding 80-120px desktop / 48-64px mobile
- No shape dividers (wave/slash/zigzag) — whitespace + bg shift only
- Pull from Elementor Kit globals; never hardcode hex
- Wall-of-text guard: text-editor >200 words without internal heading/list/table/image is lint-refused
- Curl-test URL before queueing any edit (mandatory)

**Key per-site differentiators:**

| Site | Voice | Geo lead | Provider byline | URL slug pattern |
|---|---|---|---|---|
| erofwhiterock | Direct, calm, ER-clinical | White Rock first | **BLOCKED** (no consent yet) | `/emergency-room-{city}-tx/` |
| erofirving | Direct, calm, ER-clinical | Irving first | Per-operator confirm | `/emergency-services-in-{city}-tx/` |
| eroflufkin | Direct, calm, neighborly small-market | Lufkin / Angelina County first | Per-operator confirm | (verify per site) |
| irvingwellnessclinic | Confident, warm, aspirational, no-urgency | Irving / Las Colinas first | **APPROVED** for Lori Secerovic, FNP-C, MSN | (verify per site) |

**How to use:**
- At the start of a session that involves visual / structural edits on a specific site, read the matching skill file.
- Lint rules (em dashes, AI tells, sentence length, wall-of-text) are enforced at queue time by the plugin — those are already automatic.
- Layout, spacing, alignment, color-token, geo-priority, provider-byline, authority-source, and anti-pattern rules are model-enforced and need active reading.

**Update cadence:**
- Refresh the per-site skill when brand profile, service inventory, or voice rules change materially.
- Last refresh date is at the bottom of each SKILL.md.
- A future plugin path could auto-sync brand profile + service inventory from the MCP into a generated "site facts" block in each skill — useful but not built yet.

Related: [[reference_cc_assistant_v0_28_industry_playbook]] (the SEO playbook is the topic-level complement to these design skills), [[feedback_curl_test_url_before_editing]] (the universal pre-edit discipline), [[feedback_geo_anchor_priority]] (the geo-priority memory specifically for erofwhiterock).
