---
name: erofwhiterock-design
description: UX/UI design system and brand rules for ER of White Rock (erofwhiterock.com). Invoke whenever proposing visual changes, new Elementor widgets, restructured sections, or copy that lands on this site. Covers typography, color, spacing, components, mobile, accessibility, performance, site-specific brand voice, catchment vocabulary, and the ER-specific UX patterns that make an emergency-care site feel trustworthy.
---

# ER of White Rock — Design + Brand Skill

This document is the canonical UX/UI specification for erofwhiterock.com. Anything queued via `draft_*` tools that visually affects the site must comply. Lint-fail rules are enforced server-side; everything else is reviewer-enforced.

## TL;DR — read this in 30 seconds before any edit

The 12 rules below catch 90% of mistakes. Read the full skill before queueing visual or structural changes — but at minimum memorize these:

1. **Address (memorize):** 10705 Northwest Hwy, Dallas, TX 75238 · **Phone:** (469) 943-2939
2. **Geo lead:** White Rock first, East Dallas second, generic Dallas only for citations
3. **Brand primary:** `#DA1212` (icons, primary CTAs, urgency accents). **Secondary:** `#11468F` (eyebrows, secondary CTAs, button hover). **Text:** `#041562` (H1/H2/H3 titles). See §21 for full role table.
4. **Body text:** ALWAYS `text-align: left`. Center alignment is for hero copy and standalone H2s only — NEVER for body or lists.
5. **Long-form text widget width:** 650px standalone, 800–900px in multi-column layouts. **Plugin lint floor:** heading widgets inside `container_add` must be `_element_custom_width ≥ 700px` (use 800). See §23.
6. **Containers:** root `boxed_width: 1200` max. Hero `min_height: 90vh` desktop. Section padding 80–120px desktop / 48–64px mobile.
7. **Buttons:** `border_radius: 2px`, ≥44px tap target, primary red bg + white text + navy hover. Hover requires BOTH `button_background_hover_color` AND `background_hover_color` fields set. See §9, §21.
8. **No hospital ED comparisons** in any copy (even cited stats — see §14 and §18 anti-pattern #11). Self-anchored facts only. Hospitals are referral partners.
9. **No off-brand hex.** Only: `#DA1212 #11468F #041562 #F4F4F4 #FFFFFF #000000` + semantic greys `#555555 #777777 #DDDDDD #E0E0E0`. **Banned (lint should refuse):** `#FFC107 #B45309 #15803D #28A745 #FFE5E5 #FFF1F1` and any other yellow / amber / green. See §22.
10. **Step 0 of any edit:** load this skill via the `Skill` tool. Then run `get_page_map(post_id)` immediately before any `container_add` (gate is consumed per call, not 10-min session). See §23.
11. **Race-safety:** if the post was modified externally (or by a prior approval batch) after your pending was queued, the apply guard refuses with "Refusing apply to avoid clobbering." Re-fetch + re-queue. See §23.
12. **For a new service page: BUILD FROM SCRATCH — never clone/duplicate** (no `build_service_page` mirror, no page copy). Cloning leaves source-condition residue. Author fresh content for THIS condition with its own analyzed section set; you may SAMPLE another page's visual style (`get_page_style_context`) but never copy its content. See memory `feedback_build_from_scratch_never_duplicate`.

**Section index:** §1 identity · §2 geo · §3 voice · §4 tokens · §7 widths · §9 components · §14 ER trust signals · §18 anti-patterns · §19 pre-edit · §20 quick checklist · §21 color role assignment · §22 banned hex / palette · §23 plugin lint quirks · §24 image placeholder protocol · §25 widget pattern library

## 1. Site identity

| Field | Value |
|---|---|
| Site name | ER of White Rock |
| URL | https://erofwhiterock.com |
| Category | Freestanding emergency room (YMYL Health) |
| Industry overlay | `healthcare` (auto-detected, manual override available) |
| Primary catchment | White Rock, Lake Highlands, Lakewood, Casa Linda, Casa View, Forest Hills |
| Secondary catchment | Garland, Mesquite, Richardson (East Dallas-adjacent) |
| Sister sites | ER of Irving (this site was cloned from it — plagiarism risk active) |
| Provider byline status | **Blocked** — no physician has approved consent. Do NOT propose bylines or schema Person until told otherwise. |
| Phone | (469) 943-2939 |
| Address | 10705 Northwest Hwy, Dallas, TX 75238 |

## 2. Geo-priority (mandatory anchor rule)

**Always lead with White Rock. East Dallas is secondary. Generic Dallas only for citations.**

- ✅ "Serving Lake Highlands, White Rock Lake, Lakewood, and East Dallas"
- ✅ "Dallas County DSHS reports..." (citation context)
- ✅ "10705 Northwest Hwy, Dallas, TX 75238" (literal address)
- ❌ "Dallas residents trust us" (leads with generic Dallas)
- ❌ "Patients across Dallas choose us" (Irving copy ghost)
- ❌ "Best ER in Dallas" (unwinnable competition; rank where you can own)

## 3. Voice & tone

- **Direct, calm, clinical-but-warm.** A reader in panic should feel held, not lectured.
- **Numbers + named sources** beat opinion every time. Cite CDC, AHA, ASA, ACEP, Texas DSHS by name.
- **Triage-decisioning H2s** outperform service descriptions. "When is this an ER visit?" > "Our Services."
- **No em dashes** (project style rule, lint-enforced).
- **No AI-tell phrases:** "delve into", "in today's fast-paced", "navigating the complexities", "unlock", "leverage" — banned (lint-enforced).
- **Sentences ≤25 words for ≥85% of content.** Reading grade 7–9 for laypeople.
- **No opinion framing.** "We believe X is best" is hollow. Anchor every claim to a citation or first-hand operational fact.
- **No provider names** until consent is in writing.

## 4. Design tokens

Pull from the Elementor Kit via `CC_Assistant_Brand_Profile::get()`. Never hardcode hex.

| Token | Use |
|---|---|
| `primary` | Hero CTAs, primary buttons, brand accents |
| `secondary` | Secondary buttons, link hover, accents |
| `accent` | Pull-quotes, subtle highlights only |
| `text` | All body text (single token, no per-section variation) |
| `card_bg` | Cards, panels, alternating section bg |
| `page_bg` | Default page background |
| `heading_font` | All H1–H6 |
| `body_font` | All body, lists, captions |

## 5. Typography

- **Body size:** 16px minimum desktop, 18px ideal for long-form. Never below 16px on body copy.
- **Line height:** 1.5 to 1.7 for body, 1.2 to 1.3 for headings.
- **Max line length:** 65 characters per line. **At 18px this is ~650px container width.** Long-form text containers max-width = 650px. Anything wider creates eye strain.
- **Heading scale ratio:** 1.25× to 1.5× between H1 → H2 → H3 → body. Don't let H2 size equal bold body.
- **Font weight contrast:** 400 body / 600 or 700 headings. Skip 500 (too close to body).
- **One body font + one heading font max.** If they're the same family, that's fine.

## 6. Spacing — 4px sub-grid for components, 8px for layout

**Two-tier grid.** Layout spacing (sections, page padding, large gaps) sticks to multiples of 8: **8, 16, 24, 32, 48, 64, 80, 120**. Component spacing (button padding, card padding, list-item gap, icon-to-text) drops to multiples of 4: **4, 8, 12, 16, 20, 24**. This matches Material Design and Tailwind conventions and stops the "we need an exception for this one button" debate.

| Spacing role | Value |
|---|---|
| List item to list item | 8–12px |
| Heading to its paragraph | 16px |
| Paragraph to paragraph | 24px |
| Component to component (within section) | 32–48px |
| Section vertical padding (desktop) | **80–120px** |
| Section vertical padding (mobile) | **48–64px** |
| Widget internal padding (text-editor, cards) | 16–32px |
| Page horizontal gutters (mobile) | 16–24px |

## 7. Layout & containers

- **Hero section:** Full or near-full viewport height (90vh–100vh). Phone + primary CTA visible without scroll.
- **Long-form text container:** **max-width 650px**. This is the 65ch rule applied.
- **Multi-column or image grid container:** max-width 1200px.
- **Single-image hero or full-bleed feature:** can extend page-edge to page-edge.
- **Text-editor widget max-width:** 650px when standalone. 800–900px ONLY when alternating with images / inside multi-column layouts.
- **Containers must NOT exceed 1200px** except for full-bleed photo backgrounds.

## 8. Section distinction (no two adjacent sections same bg)

- Alternate `page_bg` and `card_bg`. Never two sections in a row with identical background.
- **Do NOT use shape dividers** (waves, slashes, zigzags). They read as dated and amateur.
- Separate sections by **whitespace + subtle background shift only.**
- Spacing carries the visual hierarchy; never decorative shapes.

## 9. Components

### Buttons

- **Corner radius: 2px** (matches input fields, images — site-wide consistent).
- **Minimum tap target: 44px tall.**
- **Padding: 16px vertical, 24px horizontal** (on the 4px component sub-grid).
- **Primary CTA:** solid fill (primary color), white text, brand voice action verb ("Get Care Now", "Call Us").
- **Secondary CTA:** outlined or ghost, primary color text + border.
- **Hover state:** pick ONE site-wide mechanism — darken 10%, or scale 1.02, or add small shadow. Don't mix.
- **One primary CTA per section.** Multiple primary buttons in one section dilutes intent.
- **All `tel:` links** on phone-number buttons. Always.
- **Descriptive aria-labels** for screen readers: button text "Call Now" must pair with `aria-label="Call ER of White Rock now at (469) 943-2939"`.

### Lists

- **Body content + lists: `text-align: left`.** NEVER center-align body or lists. Center is for hero copy and standalone H2s only.
- `list-style-position: outside`, `padding-left: 24px`. Bullets sit at left margin, text starts ~12px to the right.
- 8–12px between list items.
- Numbered lists for sequential steps; bullets for parallel items.
- Maximum bullet list length before splitting: 8 items. Beyond that, group into sub-headings or use an accordion.

### Headings

- Exactly **one H1 per page.** The hero H2 is the canonical H1 on Elementor pages without theme-rendered titles.
- 30% or more of H2s should be question-shaped on YMYL pages ("When is this an ER visit?").
- Each question-H2 needs a 40–60 word direct answer in the SAME section (featured-snippet eligibility).
- Never skip levels (no H2 → H4).

### Images

- All images need alt text (lint-enforced). Empty alt is a fail.
- **Hero image:** 1920×1080 or 1920×1200, WebP, ≤200KB.
- **Aspect ratio consistency** within a section.
- **Corner radius matches buttons (2px) OR fully square.** Don't mix rounded and sharp images on the same page.
- **No stock photos for hero.** Real facility, real staff, real exterior shots only. Stock-doctor smiles tank YMYL trust signals.

### Forms

- Labels above inputs (not placeholders).
- Required-field markers visible.
- Submit button = primary CTA style.
- Error states visible inline, not just at submit.

### Cards

- Padding inside card: 24px or 32px.
- Card bg = `card_bg` token (lighter than `page_bg`).
- Corner radius matches buttons (2px) for visual consistency.
- Subtle shadow optional; if used, site-wide consistent.

## 10. Wall-of-text guard (lint-enforced)

A single text-editor widget **over ~200 words with no internal heading, list, table, or image is refused at queue time.** Route long content into the right widget by shape:

- Enumeration → icon-list or bullet list
- Q&A → accordion item
- Steps → icon-box row or numbered list
- Comparison → table or two-column container
- Quotes → blockquote
- Stats → counter or pricing-table-like layout

## 11. Mobile rules (375px iPhone SE width minimum)

- Section vertical padding: ~60–70% of desktop value (so 80px desktop = 48–56px mobile).
- Stack columns vertically below 768px.
- Touch targets ≥44px on all interactive elements.
- Tap-to-call on every phone number.
- Hamburger menu on right, logo on left.
- Test specifically at 375px (narrowest common viewport) AND 768px (iPad portrait).
- Images: `max-width: 100%`, height auto.

## 12. Accessibility (WCAG 2.1 AA)

- **Contrast: 4.5:1 minimum for body, 3:1 for large text.**
- **Skip-to-content link** for screen readers (Elementor theme should include this; verify).
- **Focus states visible** on all interactive elements. Never `outline: none` without a replacement.
- **Descriptive aria-labels** on icon-only buttons AND on phone CTAs (location + number).
- **Heading order with no skips.**
- **Form labels associated with inputs** (`<label for="...">`).
- **`prefers-reduced-motion` respected** in CSS for any animations.
- **Color is never the only signal.** Form errors paired with icon + text, not just red border.

## 13. Performance

- **WebP images, lazy-loaded below the fold.**
- **LCP ≤2.5s, CLS ≤0.1** (Google Core Web Vitals thresholds).
- **Defer non-critical JS.**
- **No autoplay video with sound.**
- **Single subtle hero animation max.** No scroll-jacking.
- **Transitions ≤300ms.** Longer feels sluggish.

## 14. ER-specific UX (trust signals + emergency psychology)

- **Phone number above the fold, every page.** Visible without scroll. Tap-to-call.
- **"Open 24/7" badge near hero** — reassurance signal.
- **EMTALA mention somewhere** — "we evaluate every patient regardless of insurance" is a legal differentiator and a trust signal.
- **Door-to-provider stat prominently displayed (self-anchored — no hospital comparison)** — "Median door-to-provider: under 10 minutes. Open 24/7. No appointment needed." Display as a red callout strip below the hero. **DO NOT compare against hospital ED wait times** (even with cited stats). Hospitals are referral partners; comparative wording erodes patient trust in hospitals and damages referral relationships. See §18 anti-pattern #11.
- **Triage-decisioning section** on every service page — "When is this an ER visit vs urgent care?"
- **ER vs urgent care vs primary care framework** — readers in a non-emergency are evaluating where to go. Help them.
- **Insurance logos block** somewhere on the page (BCBS, Aetna, Cigna, UnitedHealth) — visual trust.
- **Real photos of the actual facility** (exterior, lobby, X-ray room) — not stock.
- **"Call 911 if life-threatening" disclaimer** somewhere visible — liability + responsibility.
- **No urgency-pressure tactics** ("Don't wait!"). The medical situation IS the urgency; piling on copy reads as cheap.

## 15. Authority sources (citation allow-list)

For any cited stat or guideline, use only:

**Primary (.gov):** cdc.gov, nih.gov, medlineplus.gov, fda.gov, niddk.nih.gov, ninds.nih.gov, dshs.texas.gov

**Peer-reviewed:** NEJM, JAMA, Lancet, BMJ, Annals of Emergency Medicine

**Specialty bodies:** AAP (pediatrics), AHA (cardiology), AAOS (orthopedics), AAFP (family medicine), ACEP + ENA (emergency medicine), ASA (stroke)

**Operational benchmarks:** CMS Hospital Compare, ED Benchmarking Alliance, Surviving Sepsis Campaign

**NEVER cite:** competitor clinics or ERs, paid medical directories, Wikipedia, WebMD, Healthline (aggregators), AI-generated content.

## 16. Service inventory (what this site actually treats)

Confirmed sold/listed at this facility:
- Chest pain & cardiac emergencies
- Stroke (BE-FAST screening, CT, transfer to Comprehensive Stroke Center)
- Pediatric emergency
- High fever (adult + pediatric)
- Sore throat / strep / severe upper-respiratory
- Bronchitis / pneumonia / respiratory difficulty
- Rashes / cellulitis / skin infections
- Abdominal pain / appendicitis
- Back pain emergency
- Dehydration & IV fluid therapy
- Food poisoning / gastroenteritis
- Lab testing (on-site, 10–20 min turnaround)
- Diagnostic imaging (CT, X-ray, ultrasound)
- TBI / concussion / head injury
- Eye injury / trauma
- Fractures (digital X-ray + casting)
- Blood clots / DVT
- Gastrointestinal emergencies

**Do not propose content for services not on this list** without checking `service_inventory` MCP first.

## 17. Cluster + pillar structure

| Cluster | Pillar (EN / ES) | Notes |
|---|---|---|
| Cluster 1 — ER vs Hospital | 4986 / 5009 | "Freestanding ER vs Hospital ER" decision content |
| Cluster 2 — Cardiovascular | 5032 / 5041 | Heart attack, stroke, blood clots |
| Cluster 3 — Bone / Joint / Trauma | 5054 / 5058 | Fractures, head injuries, eye injuries |
| Cluster 4 — Pediatric | 4421 / 5067 | Children's emergency care |
| Cluster 5 — Stomach / Digestive | 5061 / 5063 | Appendicitis, food poisoning |
| Cluster 6 — Infectious Disease & Vector-Borne | 5134 / 5136 | Tick bites, West Nile, sepsis |

## 18. Anti-patterns — what NOT to do on this site

1. Don't add provider names or "Medically Reviewed by:" lines (consent blocked).
2. Don't write "Dallas residents" or "Patients across Dallas" — White Rock first.
3. Don't reference Coppell, Grand Prairie, Farmers Branch, Carrollton, Bedford, Grapevine, Addison, Arlington as "our neighbors" — those are Irving's catchment. This site was cloned from Irving; clean up any residual references.
4. Don't link to `/emergency-services-in-{city}-tx/` URLs — they're Irving's slug pattern. The WR location pages are at `/emergency-room-{city}-tx/`.
5. Don't queue edits to post 3505 (`/emergency-services-in-dallas-tx/`) — Rank Math 301-redirects this URL to post 541. All visible edits should target post 541.
6. Don't add wave / slash / zigzag section divider shapes.
7. Don't center-align body text or lists.
8. Don't hardcode hex colors. Always use Brand Profile / Kit globals.
9. Don't write generic content without a cited stat or first-hand operational fact.
10. Don't propose H1 changes without checking GSC top-impression queries first.
11. **Don't position our ER's speed by comparing against hospital EDs** — even with cited industry stats (e.g. ED Benchmarking Alliance). Knock-marketing against hospitals erodes patient trust in hospitals over time, and hospitals are our REFERRAL DESTINATIONS for trauma / ICU / inpatient care. Self-anchored door-to-provider claims only ("under 10 minutes, every visit"). Comparison phrasing is allowed only for ER vs urgent care vs primary care decision aids (those help patients pick the right venue, not knock anyone).
12. **Don't invent the address** — copy literally from §1 each time (`10705 Northwest Hwy, Dallas, TX 75238`). A previous chat invented "9220 Garland Rd" and shipped it to 5 widgets before the operator caught it.
13. **Don't hardcode hex outside §22.** Eyebrow yellow `#FFC107`, success green `#15803D` / `#28A745`, amber `#B45309`, light-red wash `#FFF1F1` are all off-brand and will require a pass to fix.

## 19. Pre-edit checklist (mandatory)

Before queueing any visual or structural edit:

0. **Load this skill via the `Skill` tool** — `skill: erofwhiterock-design`. If you've already loaded it in this session, jump to step 1. The TL;DR at the top is a fallback for short-context conversations but the full skill is the authoritative spec.
1. **Curl-test the URL.** `WebFetch` or `curl -s -I -L` the post's URL field. Verify 200 OK, no redirect.
2. **Confirm the rendered URL matches the post URL** from `get_post`. Rank Math redirects can route users away.
3. **For internal links:** verify destination URL via `list_posts` search. Do not pattern-match from sibling sites.
4. **Read `seo_playbook.fit`** from whoami. Treat industry-specific rules as advisory if fit is `drift_detected`.
5. **Check `recent_pending`** for existing changes on the same post — supersede rather than stack.
6. **For new sections at page root:** always pass explicit `position`. Plugin will refuse silent appends on a 5+ section page.
7. **For a NEW service page: BUILD FROM SCRATCH — never clone (`build_service_page` / page copy is banned).** Cloning a pillar carries its CONTENT, not just its shell, and leaves source-condition residue that must be hunted section by section (YMYL hazard). Instead: (a) analyze what sections THIS condition actually needs (may differ from any sibling), (b) author each section's content fresh, (c) SAMPLE visual style from a well-built page via `get_page_style_context` so design stays consistent (sampling style ≠ copying content), (d) build via `build_page_from_spec` or container-by-container. Then run `page_robustness_audit` + `win_audit`. See memory `feedback_build_from_scratch_never_duplicate`.
8. **Re-fetch state between approval batches.** The race-safety guard refuses any pending whose snapshot is older than the last post modification — so if the operator approves batch A and you have batch B queued from before, batch B will fail. Re-queue after each approval cycle.
9. **Before recommending ANY schema / JSON-LD edit, curl the live page and read what's actually there.** Never hand the operator a "replace placeholder X" template — that wastes their time when you have full live-site access. Workflow: `curl -sL -A "Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)" "URL"` → extract every `<script type="application/ld+json">` block → identify which entities exist + which are auto-generated by Rank Math vs. operator-installed → propose ADDITIONS (with concrete cross-referenced `@id`s) rather than full replacements. Use `@graph` to bundle related entities (MedicalWebPage + MedicalCondition + MedicalTherapy + FAQPage) in one block. Reasoning: the operator-installed schema in Elementor Custom Code lives in a custom post type that isn't currently in the plugin allowlist, so direct edit isn't possible — but curl reveals the rendered output, which is all you need to design the additional block.

## 20. Quick reference — common rule violations to self-check before queuing

- [ ] Sentences all ≤25 words?
- [ ] No em dashes (use periods, commas, parentheses)?
- [ ] No AI-tell phrases?
- [ ] Body text left-aligned (not center)?
- [ ] Lists with `list-style-position: outside`?
- [ ] Spacing on the 8px grid?
- [ ] Long-form text container ≤650px?
- [ ] Button 2px radius, ≥44px tap target?
- [ ] Internal links verified via `list_posts`, not guessed?
- [ ] Cited stat from the authority allow-list (Section 15)?
- [ ] White Rock named first in any geo reference?
- [ ] No provider name?
- [ ] No shape divider between sections?

## 21. Brand-color role assignment (which token for which widget role)

Token usage is NOT abstract — every widget role has an assigned color. Picking colors by feel produces the off-brand drift this skill exists to prevent. If you find yourself reaching for a hex not in the table below, stop and reconsider.

| Widget role | Field name | Color | Notes |
|---|---|---|---|
| Eyebrow heading (uppercase span above H2) | `title_color` | `#11468F` (secondary) | Use `#FFFFFF` on navy hero |
| H1 | `title_color` | `#FFFFFF` on hero / `#041562` on light bg | One H1 per page |
| H2 (section header) | `title_color` | `#041562` (text) | White on dark bg |
| H3 in cards / accordions | `title_color` | `#041562` | |
| Body / description text | `text_color` | `#555555` (semantic grey) | Never `#000000` |
| Footnote / disclosure / source line | `text_color` | `#777777` (semantic grey) | |
| Subtitle on dark bg (hero sub) | `text_color` | `#E0E0E0` (semantic light grey) | |
| Icon-box icon glyph | `primary_color` | `#DA1212` (primary) | This is the brand-emergency signal |
| Icon-box title | `title_color` | `#041562` | |
| Icon-box description | `description_color` | `#555555` | |
| Icon-list icon | `icon_color` | `#DA1212` (primary) | |
| Icon-list text | `text_color` | `#555555` or `#FFFFFF` on dark | |
| Button primary bg | `background_color` | `#DA1212` | |
| Button primary text | `button_text_color` | `#FFFFFF` | |
| Button primary hover bg | `button_background_hover_color` AND `background_hover_color` | `#11468F` (secondary) | BOTH fields must be set |
| Button primary hover text | `hover_color` | `#FFFFFF` | |
| Button secondary bg (ghost) | `background_color` | `rgba(0,0,0,0)` or transparent | |
| Button secondary text | `button_text_color` | `#DA1212` (or `#FFFFFF` on dark bg) | |
| Button secondary border | `border_color` | `#DA1212` (or `#FFFFFF` on dark bg) | |
| Card container bg | `background_color` | `#F4F4F4` (card_bg) or `#FFFFFF` | Alternate per section |
| Section root container bg | `background_color` | `#FFFFFF` (page_bg) or `#F4F4F4` | Alternate adjacent sections (§8) |
| Hero container bg | `background_color` | `#11468F` (deep navy) | Or solid + image with 78% overlay |
| Callout strip (high-urgency) bg | `background_color` | `#DA1212` | White text inside |
| Subtle dividers / card borders (1-2px) | `border_color` | `#DDDDDD` | |
| Card left-border accent bar (4px) | `border_color` | `#DA1212` (urgency) or `#11468F` (informational) | |
| Accordion title color | `title_color` | `#041562` | |
| Accordion active title / icon | `tab_active_color` | `#DA1212` | |
| Accordion content text | `tab_content_color` | `#555555` | |
| Accordion border between items | `border_color` | `#DDDDDD` | |
| Form field border | `field_border_color` | `#DDDDDD` | |
| Form submit button | (same as button primary) | `#DA1212` bg, `#FFFFFF` text | |

**Common mistakes the table prevents:**
- Eyebrow yellow on hero (`#FFC107`) — use `#FFFFFF` on navy bg, `#11468F` on light bg
- Navy as icon-box primary_color — should be RED `#DA1212` (brand-emergency signal)
- White card on white section — use `#F4F4F4` cards so they read as distinct from section bg
- "Card_bg" interpreted as "darker than page" → many builders pick a custom grey; the kit token IS `#F4F4F4`

## 22. Banned hex + semantic palette

**Brand palette (Elementor Kit globals — always use these for brand roles):**
- `#DA1212` — primary red
- `#11468F` — secondary navy
- `#041562` — text (dark navy)
- `#000000` — accent (use sparingly — for body text use `#555555` instead)
- `#FFFFFF` — white
- `#F4F4F4` — card_bg (light grey)

**Allowed semantic greys (NOT in Kit, but acceptable for the documented roles in §21):**
- `#555555` — body description / paragraph text
- `#777777` — footnote / disclosure / source attribution
- `#DDDDDD` — subtle borders / dividers / accordion separators
- `#E0E0E0` — light text on dark backgrounds (hero subtitle)

**Banned hexes — lint should refuse on queue, reviewer must catch otherwise:**
- `#FFC107` (yellow / amber) — never. Eyebrow text on navy hero is `#FFFFFF`, not yellow
- `#B45309` `#92400E` (dark amber) — off-brand
- `#15803D` `#28A745` `#16A34A` `#22C55E` (greens) — off-brand. Skill has no green role
- `#FFE5E5` `#FFF1F1` `#FFEEEE` (light reds / pinks) — section backgrounds must be `#FFFFFF` or `#F4F4F4`. The S2 911 section's red urgency comes from `#DA1212` icons and the bottom callout strip, NOT a tinted section bg
- Any blue other than `#11468F` and `#041562`
- Any red other than `#DA1212`
- Any teal, purple, orange, brown

**Why hexes outside this list are banned:** The brand identity is medical-emergency (red) on professional-trust (navy) — two-color disciplined. Adding green, yellow, or pink reads as either marketing-noise (yellow CTAs feel like consumer apps) or off-vertical (green = wellness / dental, not emergency). The two-color rule is the single biggest brand differentiator on the site.

## 23. Plugin lint quirks (cc-assistant — work-around these)

The `cc-assistant` plugin enforces its own rules at queue time. Some conflict with this skill or behave surprisingly. Document each so the next agent doesn't waste cycles diagnosing them:

1. **Heading-width floor on `container_add`:** Inside a `draft_add_elementor_container` call, heading widgets need `_element_custom_width.size ≥ 700` even though §7 of this skill says 650px for standalone long-form text. **Use 800px** for headings inside multi-column / image+text layouts. The SAME heading passes `draft_update_elementor_widget` at 650 — only `container_add` enforces the 700-floor (different code paths). Cross-endpoint inconsistency, document for fix.

2. **`_element_custom_width` requires `_element_width: "initial"`** to take effect. Always set both together:
    ```json
    "_element_width": "initial",
    "_element_custom_width": {"unit": "px", "size": 800, "sizes": []}
    ```

3. **wall_of_text aggregation false-positive on `container_add`:** Lint sums text from ALL text-bearing fields across sibling widgets in a single container_add (text-editor `editor` + icon-box `description_text` + heading `title`). A 6-card grid where each card has a 30-word description triggers the 200-word wall-of-text guard even though no single widget is a wall. **Workaround:** tighten descriptions to ≤20 words each OR pass `override_lint: true`. The same content via 6 separate `draft_add_elementor_widget` calls would lint clean — bug in the aggregation. Per-widget evaluation is the correct fix.

4. **Em-dash lint runs on every text-bearing field** including button text, heading titles, icon-box `title_text`. Replace `—` with `.` `,` or `(parens)` before queueing. Common trip: `(469) 943-2939 — we triage every fever immediately` → use `(469) 943-2939. We triage every fever immediately`.

5. **Map gate (`map_not_consulted`) is consumed per `container_add` call, not 10-min session** as docs claim. Sequence for adding N sections:
    - `get_page_map(post_id)` → ONE `draft_add_elementor_container` → `get_page_map` → next `draft_add_elementor_container` → ...
    - Parallel container_adds without paired gpm calls all fail except the first.

6. **Race-safety guard refuses stale pendings** if `post_modified > max(queued_at, last_internal_apply)`. After an approval batch, the post_modified shifts to "now" so any pending queued BEFORE that batch with snapshots from before-now becomes stale. Symptom: `Refusing apply to avoid clobbering or being clobbered by the concurrent edit`. **Workaround:** reject stale pendings + re-fetch state + re-queue against the current snapshot.

7. **Section-pair safe positions:** `get_page_map` returns `safe_insert_positions` and `risky_insert_positions`. Risky = inside an H2-alone container + body container pair (would split the logical section). Use safe positions, or pass `override_section_continuity: true` with explicit reasoning.

8. **Hard violations refuse queue silently OR with 422:**
    - `em_dashes`, `ai_tells`, `style_guide`, `wall_of_text`, `placeholders` → 422 unless `override_lint: true`
    - `accessibility_contrast` (title × bg ratio < 4.5 WCAG AA) → 422 unless `override_a11y: true`
    - `section_width_violation` (heading widget too narrow on `container_add`) → 422 unless `override_section_width: true`
    - `map_not_consulted` → 422, no override (must call gpm first)

9. **Elementor field-name gotchas (cost me cycles to discover):**
    - Container width is `boxed_width` (NOT `boxed_content_width`)
    - Button hover requires BOTH `button_background_hover_color` AND `background_hover_color` set to same value (different Elementor versions read different fields)
    - Button hover text is `hover_color` (NOT `button_text_hover_color`)
    - Google Maps widget shows business name in the pin only if `address` includes the business name: `"ER of White Rock, 10705 Northwest Hwy, Dallas, TX 75238"` (not just the street)
    - Get Directions deep link should be the GBP `/place/` URL with the listing's CID, not a `?q=` query (preserves attribution analytics)
    - Icon-box `selected_icon.value` must be FA-Free 5.x compatible (e.g. `fas fa-head-side-medical` is FA Pro 6+ only and renders blank — use `fas fa-exclamation-triangle` for generic warnings)

10. **Form widget submit button is configured ON the form widget**, not as a separate button widget. Settings: `button_text`, `button_background_color`, `button_text_color`, `button_typography_*`.

11. **Accordion `_id` field on each tab must be unique across the whole page**, not just within one accordion. If two accordions share `faq1`, the second one is broken silently. Use prefixed IDs like `fever-faq-1`, `cost-faq-1` etc.

## 24. Image handling when no real facility photo exists

Skill §9 says **no stock photos for facility imagery.** When the operator hasn't uploaded a real photo yet, use a clearly-marked placeholder that signals "swap me" without breaking the layout.

**Placeholder URL pattern (use via.placeholder.com — NOT Unsplash, NOT Pexels):**

```
https://via.placeholder.com/{WIDTH}x{HEIGHT}/F4F4F4/041562?text=PLACEHOLDER+{DESCRIPTIVE+LABEL}
```

The `F4F4F4` background and `041562` text color match the brand palette so the placeholder visually blends as a brand-aware block.

**Examples:**
- Lab / imaging area: `https://via.placeholder.com/800x600/F4F4F4/041562?text=PLACEHOLDER+Lab+%2B+Imaging+Suite`
- Facility exterior: `https://via.placeholder.com/800x600/F4F4F4/041562?text=PLACEHOLDER+Facility+Exterior`
- Hero background: `https://via.placeholder.com/1920x1080/11468F/FFFFFF?text=PLACEHOLDER+Hero+Photo`

**MUST do when using a placeholder:**
- Width × height matches the slot's intended aspect ratio (typical: 800×600 for cards, 1920×1080 for hero backgrounds)
- Include the word "PLACEHOLDER" in BOTH the URL text param AND the widget's `image.alt` field
- In the pending change `summary`, write: `USER: swap with real {description} photo via Media Library` — so the operator sees the TODO on inbox review
- Set `image.id` to `""` (empty) — Elementor will treat as external URL

**NEVER:**
- Use Unsplash / Pexels / other CC stock URLs (skill §14 + §18: no stock for facility imagery, even temporary)
- Use a relative path to a placeholder file that doesn't exist
- Skip the descriptive label — "PLACEHOLDER" alone tells the operator nothing

**Recommended cadence:** queue placeholder + send operator a swap-list at end of build ("upload these 4 photos: lab, exterior, lobby, staff group; total ~600KB WebP, you can swap in 5 min").

## 25. Common Elementor widget patterns (copy-paste-ready JSON)

When building a section, copy the closest pattern below and edit only the content fields. These are battle-tested against the plugin lint, brand compliance, and mobile responsiveness.

### Hero (90vh, navy bg, eyebrow + H1 + sub + 2 CTAs + trust strip)

```json
{
  "settings": {
    "min_height": {"unit":"vh","size":90,"sizes":[]},
    "min_height_tablet": {"unit":"vh","size":75,"sizes":[]},
    "min_height_mobile": {"unit":"vh","size":70,"sizes":[]},
    "boxed_width": {"unit":"px","size":1200,"sizes":[]},
    "flex_direction":"column", "flex_justify_content":"center", "flex_align_items":"center",
    "padding": {"unit":"px","top":"96","right":"24","bottom":"96","left":"24","isLinked":false},
    "background_background":"classic", "background_color":"#11468F"
  }
}
```

Children pattern: `heading[span, white, uppercase 14px, ls 2px, width 800]` → `heading[h1, white, 52/40/30px, width 800]` → `text-editor[#E0E0E0, 18/16px, width 800]` → `container[row, gap 16, width 720][button[primary red bg, white text, navy hover], button[ghost, white border]]` → `icon-list[4 items, icon #DA1212, text white, width 900]`.

### Callout strip (red bg, full-width, icon + text, between hero and content)

```json
{
  "settings": {
    "boxed_width": {"unit":"px","size":1200,"sizes":[]},
    "flex_direction":"row", "flex_wrap":"wrap", "flex_align_items":"center", "flex_justify_content":"center",
    "flex_gap": {"unit":"px","size":16,"sizes":[],"column":"16","row":"16","isLinked":true},
    "padding": {"unit":"px","top":"20","right":"32","bottom":"20","left":"32","isLinked":false},
    "background_background":"classic", "background_color":"#DA1212"
  }
}
```

Children: `icon[selected_icon, primary_color #FFFFFF, size 28px]` + `text-editor[white, 15/13px, width 900]`.

Use for: wait-time stat strip, urgent disclaimer, 911-now banner. Content MUST be self-anchored (no hospital comparison — see §14 / §18 #11).

### 3-card row (icon-box per card, on white section)

Section: `bg #FFFFFF, boxed 1200, padding 80/24, column align-center`.
Header pattern: eyebrow + H2 (`#041562, 32/26/22px, weight 700, width 800, center`) + intro text-editor (`#555555, 16px, width 800, center`).
Card-grid container: `row, flex_wrap wrap, flex_align_items stretch, flex_gap 20, width 100%`.
Each card container: `width 31% desktop / 100% tablet, column, padding 24px, border-left 4px solid #DA1212 (urgency) or #11468F (informational), bg #F4F4F4`.
Card widget: `icon-box[selected_icon, title_text, description_text, position top, primary_color #DA1212, title_color #041562, description_color #555555, text_align left]`.

### 4-card row

Identical to 3-card row but each card `width 23% desktop / 48% tablet / 100% mobile`. Section padding stays 80/24.

### Accordion section (8 FAQ items, navy headings + red active icon)

Section: `bg #F4F4F4, boxed 1200, padding 80/24, column align-center`.
Header pattern: eyebrow + H2 + intro (same as 3-card row).
Accordion widget settings:
```json
{
  "tabs": [{"tab_title":"...","tab_content":"<p>40-60 word answer.</p>","_id":"fever-faq-1"}, ...],
  "selected_icon": {"value":"fas fa-plus","library":"fa-solid"},
  "selected_active_icon": {"value":"fas fa-minus","library":"fa-solid"},
  "title_color":"#041562", "tab_active_color":"#DA1212", "tab_content_color":"#555555",
  "border_color":"#DDDDDD",
  "title_typography_typography":"custom", "title_typography_font_family":"Montserrat",
  "title_typography_font_weight":"700", "title_typography_font_size":{"unit":"px","size":17,"sizes":[]},
  "content_typography_typography":"custom", "content_typography_font_family":"Montserrat",
  "content_typography_font_size":{"unit":"px","size":15,"sizes":[]},
  "content_typography_line_height":{"unit":"em","size":1.6,"sizes":[]},
  "_element_width":"initial",
  "_element_custom_width":{"unit":"px","size":900,"sizes":[]},
  "_flex_align_self":"center"
}
```
**Lint reminder:** accordion `_id` fields must be unique across the whole page (see §23 #11).

### Image + text 50/50 split

Section: `bg #FFFFFF or #F4F4F4, boxed 1200, padding 80/24, flex_direction row, flex_wrap wrap, flex_align_items center, flex_gap column 48 / row 32`.

Image column: `width 48% desktop / 100% tablet, column`. Image widget: `image.url = placeholder URL per §24, image_border_radius 2px, _element_custom_width 100%`.

Text column: `width 48%, column, flex_align_items flex-start`. Heading widgets at `_element_custom_width 800` (lint floor), text-editor `_element_custom_width 800`. Optional button at the bottom.

**Alternate direction** (text-left vs image-left) section to section for visual rhythm.

### CTA bookend (final section, navy bg, 60/40 split text+form)

Section: `boxed 1200, navy #11468F bg, padding 80/24, column align-center`.
Inner 2-col row: `gap 40, width 100%, wrap`.
Left col (58%): eyebrow + H2 (`white, 36/28/24px`) + sub paragraph (`#E0E0E0, 16px`) + 2-button row (Call + Get Directions, primary white + ghost).
Right col (38%): `white card with 32px padding, border-radius 2px` containing `H3 + form widget` (name + phone + message fields, submit button red bg white text).

### Get Directions deep link

Use the GBP `/place/` canonical URL (not `?q=`):
```
https://www.google.com/maps/place/ER+of+White+Rock+-+Emergency+Room/@32.8646409,-96.7000306,947m/data=!3m2!1e3!4b1!4m6!3m5!1s0x864ea131f0fa1a1f:0x4aa8c9e03bdf42c2!8m2!3d32.8646409!4d-96.7000306!16s%2Fg%2F11vynyfmnc?entry=ttu&g_ep=EgoyMDI2MDUyNS4wIKXMDSoASAFQAw%3D%3D
```

### Google Maps widget address (shows business name in pin)

```json
"address": "ER of White Rock, 10705 Northwest Hwy, Dallas, TX 75238"
```

If you only pass the street address, the pin shows a naked address marker. Including the business name lets Google match the verified GBP listing.

---

**Last refreshed:** 2026-05-28. Update this skill when brand, voice, catchment, service inventory, or design system changes materially. Major revision 2026-05-28: added TL;DR header, §21 color role assignment, §22 banned hex / palette, §23 plugin lint quirks, §24 image placeholder protocol, §25 widget pattern library. Removed hospital ED comparison from §14 (was line: "Median door-to-provider under 10 minutes vs 142 minutes at Dallas-area hospital EDs"); operator policy bans positioning against hospital partners.

## Design Language v2 (2026-06-11) — supersedes defaults above where they conflict
Goal: from "well-built template" to "designed." Apply to every new/revamped page; hub 3505 is the pilot.
1. ANCHOR CHIP-NAV under the hero on any page with 6+ sections: row of small outline buttons linking to #section ids (set _element_id on each target container). Labels: plain words (Conditions, Pricing, FAQ, Find Us).
2. SECTION VARIETY BY INTENT - never two identical section shapes adjacent: card grids ONLY for catalogs; stat bands for proof; asymmetric photo+text splits (58/38) for trust; quote blocks for authority; tables (clean <table> in text-editor, NOT html widget) for comparisons.
3. TYPE: H2 40-44px desktop, body 17px, line-height 1.6. LEFT-ALIGN multi-line text inside cards (centered only for icons, card titles, and section headings).
4. ICONS ARE ACCENTS, photography leads. Generic FontAwesome circles never carry a section alone once real photos exist. Real facility/staff photos > stock, always.
5. WHITESPACE: section padding 72-96px, 20-24px gaps; fewer, larger elements beat many small ones.
6. NAVIGATION TEST before queueing: can a first-time phone user reach call/directions and their section in <=2 scrolls or 1 tap? If not, restructure.

## CANONICAL COMPONENT RECIPES (extracted from live pages 2026-06-11 — USE VERBATIM, never invent)
BUTTON PRIMARY (solid, any band): bg #DA1212, text #FFFFFF, border solid 1px #DA1212, radius 2, text_padding 18/32, Montserrat 16/700, size md, hover: bg #FFFFFF + text #DA1212 (on light sections hover bg #041562 + text #FFFFFF variant exists on FIND US).
BUTTON OUTLINE (secondary, dark bands): bg rgba(0,0,0,0), text #FFFFFF, border solid 1px #FFFFFF, radius 2, same padding/type, hover: bg #FFFFFF + text #11468F.
SECTIONS: boxed_width 1280 (site standard; conform any 1200/1300 strays when touched), padding 80/20 (hero 96), bg rotation white -> #F4F4F4 (kit Light Grey id 318f08e) -> #11468F dark band.
CARDS (hub recipe, user-approved): icon 26 in #11468F, title 16/700 Montserrat #041562 (h4 tag), white bg, border-top 3px #11468F, radius 2, _padding 24/12, desc default text token. KNOWN VARIANCE: chest-pain 2516 cards use 4px top border + icon 32 + title 18 — conform 2516 to hub recipe during its next touch.
H2: Montserrat 36/700 lh1.2 (28 mobile) color #041562 (kit text token). H1 hero: 48/700 white (34 mobile). Eyebrow: header_size "p", 14/600 caps ls2.
RULE: before building on ANY page, extract recipes from the newest user-approved page rather than trusting this list blindly; update this list when recipes evolve.

## 26. Featured / OG images: NO PEOPLE (non-negotiable)

Generated at 1200x630 by `D:/cc-assistant/tools/card-generator/featured`, which is
brand-aware: this site's tokens live in `../brands.mjs` and its type is Montserrat.
Five layouts - `type`, `type-dark`, `photo-right`, `photo-dark`, `photo-panel`.

**No people in any of them, and no body parts.** This is the opposite of the
wellness sister site's rule, and it follows from what these posts are. An ER post
is a triage decision - "is this chest pain the ER?" - so anyone in the frame is
either a patient who does not exist or a clinician who does not work here, and
this skill already says real facility and real staff only. That leaves two honest
options and they cover every post: the typographic layouts, or an object still
life (a radiograph, an unbranded detector, a thermometer, the snake itself).
`type-dark` is the DEFAULT, not the fallback - navy is this brand's hero ground,
the question in the headline is the subject, and a navy tile is the one that stops
the scroll in a listing grid of white cards.

**The bans are enforced in code, not remembered.** `featured/image-policy.json`
carries them as patterns, checked on the generation brief before Chrome opens and
on the shipped `alt` at render. Refused: people and body parts, distress staging,
ambulances and sirens and stretchers (this ER does not run transport), blood and
wounds, before/after pairs, branded devices, any claim the subject is a patient or
staff, and **any wait-time or hospital-comparison wording** - door-to-provider
claims are self-anchored and belong in copy, never in a graphic, and knocking
hospital EDs damages the referral relationships this ER depends on (see the
anti-patterns section). When a refusal arrives, change the picture, not the wording
that describes it.

**Do not reuse the site's own stock service images.** Verified 2026-09-08 on White
Rock: `uploads/2025/01/*` includes a smiling nurse at a CT scanner and red-glow
pain overlays on a torso and a leg. They are already live, and they are exactly
what this rule excludes. Site media is not a way around it.

**Two measured rules the layouts enforce for you.** Emphasis is derived from
contrast, never chosen: brand red as TEXT on white measures 5.15:1 and is used on
the light layouts, while red on navy is only 3.16:1, so on the dark ones the words
stay white and the red becomes an underline (a graphic, which needs 3:1, not type,
which needs 4.5:1). And a cutout subject must paint at least 28% of its photo
column or `proof.py` refuses the render - frame objects with `photo_inset` so they
float rather than bleeding off two edges.

**Where a two-colour brand gets its SHAPE.** Compared side by side with the
wellness sister site, these cards were not emptier - measured ink coverage was
actually higher - they were flatter, because IWC gets a graphic element three
times over (a yellow slab behind its headline words, two hued corner washes, and
a subject that bleeds off the frame) and these had a red word, two invisible
circles and an object floating mid-column. Three fixes, all derived rather than
chosen:

- **The kicker is a filled accent chip** with white type in it - the "callout
  strip, white text inside" this skill already lists as a component. It exists
  precisely because the accent cannot go BEHIND the headline words here: navy on
  red is 3.16:1. Putting a red underline under the words instead was tried and
  reverted - it bought a shape by selling the only saturated colour on a white
  card, and read quieter than what it replaced. The words stay red; the chip
  carries the shape.
- **The corner washes are the brand navy at low alpha**, not the card grey.
  #F4F4F4 on #FFFFFF measures 1.10:1 and vanishes, while IWC's sage on its
  off-white is 1.14:1 and reads - nearly the same delta, but hue does the work
  and a neutral-on-white shift has none. A tint of a Kit colour is not one of the
  banned hexes.
- **Objects are CENTRED in their column** (`photo_fit: "center"`, usually with a
  `photo_inset`), never bottom-anchored. Bottom anchoring belongs to a cutout
  person, who stands on the canvas; an object stands nowhere, and anchoring it low
  drops it into the corner with all the air above it.

**What is still missing, and cannot be fixed here:** a human face. The wellness
set is stronger for having one, and no object still life competes with it. The
honest way to close that gap on an ER brand is REAL facility photography - the
building, the entrance, an empty bay - which this skill asks for anyway. Do not
let a model invent it: a generated interior on this site implies a building that
is not theirs, which is worse than an object.

**The photo frame** (`photo-frame`, `photo-frame-dark`, with `frame: "b"`) is
this brand's, and is refused to the wellness site. A disc with a brand-red ring
is a shape a two-colour palette can own without inventing a colour. Frame B is
the operator's pick from twelve reviewed treatments: heavy ring, a true gap in
the ground colour, hairline outside it.

**The image must COVER the disc and stop at it.** Not fit inside it. An image
fitted whole within the circle reads as two separate objects - "circle is its own
and the image is on its own" - so `photo_fit: "inscribe"` is REFUSED on this
layout and the generator says so by name. The consequence is a sourcing rule: the
photo has to be croppable to fill a circle. A scene works. A subject cropped
tight to itself works. An isolated cut-out object on a plain backdrop does not -
that belongs in `photo-panel`, which is the layout for an object held whole.
Reach for `focus` to decide WHICH part survives the crop, since a circle keeps
only pi/4 of its box and eats the corners.

The render measures how much of the subject lands inside the circle and refuses
when it can see the subject exactly (a cut-out's alpha); on a scene it can only
warn, so that one is yours to check. Lower `circle_floor` only when the subject
has no silhouette to lose - a radiograph is a rectangle of image data, and losing
its ends to a round window is a crop, not a severed object.

**Review at listing size.** `out/_listing-sheet.png` at 400px wide, every shot,
every time. The gates catch geometry and described depiction; they cannot catch a
photo that contradicts its own alt text. That one is yours.

**Open item:** ER of White Rock has a real horizontal wordmark
(`uploads/2026/02/erofwhiterocklogo`). Irving and Lufkin have only the wordless
red/navy cross, which is square, so it costs 68px of vertical room in a corner and
reads as a speck at listing size. A horizontal wordmark for those two would be
worth having. A reversed lockup for the navy grounds already exists for all three:
navy half turned white, red half kept.

---

**Last refreshed:** 2026-09-08. Revision 2026-09-08: added §26, the featured/OG image doctrine (no people on the ER brands, enforced in `featured/image-policy.json`; emphasis derived from measured contrast; column-paint floor).
