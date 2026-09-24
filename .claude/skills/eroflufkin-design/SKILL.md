---
name: eroflufkin-design
description: UX/UI design system and brand rules for ER of Lufkin (eroflufkin.com). Invoke whenever proposing visual changes, new Elementor widgets, restructured sections, or copy that lands on this site. Covers typography, color, spacing, components, mobile, accessibility, performance, site-specific brand voice, catchment vocabulary, and the small-market emergency-care UX patterns.
---

# ER of Lufkin — Design + Brand Skill

This document is the canonical UX/UI specification for eroflufkin.com. Anything queued via `draft_*` tools that visually affects the site must comply.

## TL;DR — read this in 30 seconds before any edit

The 12 rules below catch 90% of mistakes. Small-market voice differences in §3 / §14 are critical — Lufkin reads warmer than the urban sister sites.

1. **Address + phone:** see §1 (or `get_site_memory()` if §1 is stale)
2. **Geo lead:** Lufkin first, then Diboll / Huntington / Pollok / Nacogdoches / Angelina County / Deep East Texas. NEVER reference Dallas / DFW / White Rock / Irving / Coppell (Lufkin is 170 miles southeast — different region entirely).
3. **Brand primary:** `#DA1212` (icons, primary CTAs, urgency accents). **Secondary:** `#11468F` (eyebrows, secondary CTAs, button hover). **Text:** `#041562` (H1/H2/H3 titles). See §21 for full role table.
4. **Body text:** ALWAYS `text-align: left`. Center is for hero copy and standalone H2s only — NEVER for body or lists.
5. **Long-form text widget width:** 650px standalone, 800–900px in multi-column layouts. **Plugin lint floor:** heading widgets inside `container_add` must be `_element_custom_width ≥ 700px` (use 800). See §23.
6. **Containers:** root `boxed_width: 1200` max. Hero `min_height: 90vh` desktop. Section padding 80–120px desktop / 48–64px mobile.
7. **Buttons:** `border_radius: 2px`, ≥44px tap target, primary red bg + white text + navy hover. Hover requires BOTH `button_background_hover_color` AND `background_hover_color` fields set. See §9, §21.
8. **No hospital ED comparisons** in any copy (even cited stats — see §14 and §18 anti-pattern). Self-anchored facts only. Hospitals are referral partners — and in small-market Lufkin this matters even more (the nearest hospital ED is a known local entity and probably a partner facility).
9. **No off-brand hex.** Only: `#DA1212 #11468F #041562 #F4F4F4 #FFFFFF #000000` + semantic greys `#555555 #777777 #DDDDDD #E0E0E0`. **Banned:** `#FFC107 #B45309 #15803D #28A745 #FFE5E5 #FFF1F1` and any other yellow / amber / green. See §22.
10. **Step 0 of any edit:** load this skill via the `Skill` tool. Then `get_page_map(post_id)` before any `container_add` (gate is per-call). See §23.
11. **Race-safety:** post modified externally after pending queued → apply refuses. Re-fetch + re-queue.
12. **Small-market voice:** warmer than the urban sites (§3). Use local Lufkin landmarks (Hwy 59 / 69, Angelina County) and drive-time data. Mention community ties ONLY with operator approval.

**Sister-site reminder:** This site was cloned from ER of Irving. Service-page bodies likely still carry Irving content. Always diff against the Irving counterpart before any service-page rewrite — near-identical word counts signal cloned bodies. Strip Dallas/DFW/Irving references on sight.

**Section index:** §1 identity · §2 geo · §3 voice · §4 tokens · §7 widths · §9 components · §14 ER trust signals · §18 anti-patterns · §19 pre-edit · §20 quick checklist · §21 color role assignment · §22 banned hex / palette · §23 plugin lint quirks · §24 image placeholder protocol · §25 widget pattern library

## 1. Site identity

| Field | Value |
|---|---|
| Site name | ER of Lufkin |
| URL | https://eroflufkin.com |
| Category | Freestanding emergency room (YMYL Health) |
| Industry overlay | `healthcare` |
| Primary catchment | Lufkin, TX and surrounding Angelina County |
| Secondary catchment | Nacogdoches, Diboll, Hudson, Huntington, Pollok, Zavalla, Apple Springs |
| Sister sites | Cloned from ER of Irving — plagiarism risk active. Service-page bodies likely still carry Irving content. |
| Provider byline status | Confirm with operator before naming any provider |
| Market context | **Small market** (Lufkin metro ~50k, Angelina County ~85k). Less SEO competition; more reliance on local trust signals (community ties, local hospital affiliations). |

## 2. Geo-priority (mandatory anchor rule)

**Always lead with Lufkin. Angelina County is the broader regional anchor. East Texas / Deep East Texas for broader marketing context.**

- ✅ "Serving Lufkin, Diboll, Huntington, and Angelina County"
- ✅ "The largest freestanding ER in Deep East Texas" (if true — verify with operator)
- ✅ Local Lufkin landmarks, Highway 59 / Highway 69 references
- ❌ "Patients across Dallas / DFW" (wrong region entirely — Lufkin is 170 miles southeast)
- ❌ References to White Rock, Lake Highlands, Lakewood, Irving, Coppell (sister-site cloning leakage)

## 3. Voice & tone

- **Direct, calm, neighborly.** Small-market voice is warmer than the urban sites — your reader knows their neighbor works here.
- **Community-rooted.** Mention local high schools / community events ONLY when factually appropriate and operator-approved.
- **Numbers + named sources** beat opinion.
- **Triage-decisioning H2s** outperform service descriptions.
- **No em dashes** (lint-enforced).
- **No AI-tell phrases** (lint-enforced).
- **Sentences ≤25 words for ≥85%.** Grade 7–9 reading level.
- **No opinion framing.** Cite or fact, not "we believe."

## 4. Design tokens

Pull from Elementor Kit via `CC_Assistant_Brand_Profile::get()`. Never hardcode hex. Same token model as sister sites.

## 5. Typography

- Body 16px minimum, 18px ideal for long-form.
- Line height 1.5–1.7 body, 1.2–1.3 headings.
- Max line length 65 characters. **Long-form text containers max-width 650px.**
- Heading scale ratio 1.25×–1.5× between levels.
- Font weight contrast 400/600.

## 6. Spacing — 4px sub-grid for components, 8px for layout

**Two-tier grid.** Layout spacing (sections, page padding, large gaps) sticks to multiples of 8: **8, 16, 24, 32, 48, 64, 80, 120**. Component spacing (button padding, card padding, list-item gap, icon-to-text) drops to multiples of 4: **4, 8, 12, 16, 20, 24**. Sections 80–120px desktop / 48–64px mobile.

## 7. Layout & containers

- Hero 90vh–100vh.
- Long-form text container max-width 650px.
- Multi-column / image grid max-width 1200px.
- Containers ≤1200px except full-bleed photo backgrounds.

## 8. Section distinction

- Alternate `page_bg` and `card_bg`.
- **No shape dividers.**
- Whitespace + subtle bg shift only.

## 9. Components

### Buttons
- **2px corner radius**, **≥44px tap target**.
- **Padding: 16px vertical, 24px horizontal** (on the 4px component sub-grid).
- Primary solid + secondary outlined/ghost. One primary per section.
- `tel:` links on phone CTAs. Descriptive aria-labels: `aria-label="Call ER of Lufkin now at (insert-number)"`.
- One hover mechanism site-wide.

### Lists
- **Body + lists `text-align: left`.**
- `list-style-position: outside`, `padding-left: 24px`, 8–12px between items.
- Max 8 items before splitting.

### Headings
- One H1 per page (hero H2 is canonical H1).
- 30%+ question-shaped H2s on YMYL pages.
- Question H2s need 40–60 word answers in the same section.
- No skipped levels.

### Images
- Alt text mandatory.
- Hero 1920×1080 or 1920×1200, WebP, ≤200KB.
- Aspect ratio consistent within section.
- 2px corner radius OR fully square. Never mixed.
- **Real Lufkin photos.** Local facility, local staff. Stock breaks small-market trust faster than urban-market trust.

### Forms
- Labels above inputs.
- Required markers visible.
- Submit = primary CTA.
- Inline error states.

### Cards
- 24–32px inner padding.
- `card_bg` token.
- 2px corner radius.

## 10. Wall-of-text guard

>200 words without heading/list/table/image is refused.

## 11. Mobile rules

- Section padding 60–70% of desktop.
- Stack columns below 768px.
- ≥44px tap targets.
- Tap-to-call everywhere.

## 12. Accessibility (WCAG 2.1 AA)

- Contrast 4.5:1 body / 3:1 large.
- Skip-to-content link.
- Visible focus states.
- Descriptive aria-labels on phone CTAs.
- Heading order no skips.
- Form labels associated with inputs.

## 13. Performance

- WebP, lazy-loaded below the fold.
- LCP ≤2.5s, CLS ≤0.1.
- Defer non-critical JS.
- No autoplay video with sound.
- Transitions ≤300ms.

## 14. ER-specific UX (small-market trust calibration)

- Phone above the fold, every page. Tap-to-call.
- "Open 24/7" badge near hero.
- EMTALA mention.
- **Local hospital affiliations** (CHI St. Luke's Health Memorial Lufkin, Woodland Heights Medical Center — verify before naming) — small-market trust signal.
- **Drive-time data** for the catchment: "10 minutes from downtown Lufkin," "20 minutes from Diboll" etc.
- **Door-to-provider stat (self-anchored — no hospital comparison)** — "Median door-to-provider: under 10 minutes. Open 24/7. No appointment needed." Display as a red callout strip. **DO NOT compare against any hospital ED wait time** (including the local hospital). The two nearest hospital EDs are referral partners and named entities in this small market — comparison language damages those relationships AND erodes patient trust in hospitals. Self-anchored only. See §18 anti-pattern.
- Triage-decisioning section on every service page.
- ER vs urgent care decision framework — urgent care is rarer in this market, so the comparison may differ from urban sites.
- Real facility photos.
- "Call 911 if life-threatening" disclaimer.

## 15. Authority sources

Same medical allow-list as sister sites: CDC, NIH, MedlinePlus, FDA, NEJM, JAMA, AAP, AHA, ACEP, ENA, Texas DSHS.

**For East Texas regional data:** Texas DSHS regional reports (Health Service Region 5), Angelina County health department, Texas Hospital Association.

**Never cite:** competitor ERs (local or distant), paid directories, aggregators.

## 16. Service inventory

Same baseline service list as the sister sites (cloned from Irving). **Verify per-page via `service_inventory` MCP** — small market may have a narrower set of offerings than the urban facilities.

## 17. Sister-site differentiation

- This site was cloned from ER of Irving. Service-page bodies likely still contain Irving-specific catchment references that need rewriting to Lufkin/Angelina County.
- Run duplication audit before any service-page rewrite: pull the Irving counterpart and diff word counts. Near-identical word counts (within 5%) signal cloned bodies.

## 18. Anti-patterns

1. Don't reference Dallas, DFW, Irving, White Rock, Lake Highlands, Lakewood, Coppell — wrong region entirely (Lufkin is 170 miles southeast of Dallas).
2. Don't compare against ANY hospital ED — including the local one — for wait-time / speed / quality claims. This is even more important in a small market than in urban sister sites: the local hospitals are named entities, referral partners, and known to every prospective patient. Self-anchored "under 10 minutes" only.
3. Don't add wave/slash/zigzag dividers.
4. Don't center-align body text.
5. Don't hardcode hex colors.
6. Don't write generic content without a cited stat OR a local fact.
7. Don't propose competing-with-Dallas content. This is a regional ER serving Deep East Texas.
8. **Don't hardcode hex outside §22.** Eyebrow yellow `#FFC107`, success green `#15803D` / `#28A745`, amber `#B45309`, light-red wash `#FFF1F1` are all off-brand. See §22 for the full allowed/banned list.

## 19. Pre-edit checklist (mandatory)

0. **Load this skill via the `Skill` tool** — `skill: eroflufkin-design`. If already loaded in this session, jump to step 1.
1. Curl-test the URL. 200 OK, no redirect.
2. Confirm rendered URL matches post URL.
3. Verify internal links via `list_posts`.
4. Read `seo_playbook.fit`.
5. Check `recent_pending`.
6. New sections at page root: explicit `position` required.
7. **For a NEW service page from scratch:** strongly prefer `build_service_page(mirror_post_id=<lufkin_pillar>, replacements={...}, title=...)` macro. Find the canonical pillar via `service_inventory` MCP. Macro preserves flex layouts, hover states, and Kit globals byte-for-byte vs the 13-container manual build path.
8. **Re-fetch state between approval batches.** Race-safety guard refuses pendings whose snapshot is older than the last post modification. Reject + re-queue after each approval cycle.
9. **Before recommending ANY schema / JSON-LD edit, curl the live page and read what's actually there.** Never hand the operator a "replace placeholder X" template — workflow: `curl -sL -A "Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)" "URL"` → extract all `<script type="application/ld+json">` → identify what's operator-installed vs Rank Math auto-emit → propose concrete ADDITIONS (with cross-referenced `@id`s) rather than full replacements.

## 20. Quick-check before queue

- [ ] Sentences ≤25 words?
- [ ] No em dashes?
- [ ] No AI tells?
- [ ] Body left-aligned?
- [ ] Lists with `list-style-position: outside`?
- [ ] 8px grid spacing?
- [ ] Long-form text container ≤650px?
- [ ] Button 2px radius, ≥44px tap?
- [ ] Internal links verified?
- [ ] Lufkin / Angelina County / East Texas named first?
- [ ] Local-trust signal present (regional hospital, drive-time, community)?
- [ ] No urban-market references that don't apply here?

## 21. Brand-color role assignment

Token usage is NOT abstract — every widget role has an assigned color. Picking colors by feel produces off-brand drift.

| Widget role | Field name | Color | Notes |
|---|---|---|---|
| Eyebrow heading (uppercase span) | `title_color` | `#11468F` (secondary) | `#FFFFFF` on navy hero |
| H1 | `title_color` | `#FFFFFF` on hero / `#041562` on light | One H1 per page |
| H2 / H3 | `title_color` | `#041562` | White on dark bg |
| Body / description text | `text_color` | `#555555` (semantic grey) | Never `#000000` |
| Footnote / disclosure | `text_color` | `#777777` | |
| Subtitle on dark bg | `text_color` | `#E0E0E0` | |
| Icon-box icon glyph | `primary_color` | `#DA1212` | Brand-emergency signal |
| Icon-box description | `description_color` | `#555555` | |
| Icon-list icon | `icon_color` | `#DA1212` | |
| Button primary bg / text | `background_color` / `button_text_color` | `#DA1212` / `#FFFFFF` | |
| Button primary hover bg | `button_background_hover_color` AND `background_hover_color` | `#11468F` | BOTH fields needed |
| Button primary hover text | `hover_color` | `#FFFFFF` | |
| Button secondary bg / text | `background_color` / `button_text_color` | `rgba(0,0,0,0)` / `#DA1212` | |
| Card container bg | `background_color` | `#F4F4F4` or `#FFFFFF` | |
| Section root container bg | `background_color` | `#FFFFFF` or `#F4F4F4` | Alternate (§8) |
| Hero container bg | `background_color` | `#11468F` | |
| Callout strip (urgency) bg | `background_color` | `#DA1212` | White text |
| Subtle dividers / borders | `border_color` | `#DDDDDD` | |
| Card left-border accent (4px) | `border_color` | `#DA1212` (urgency) or `#11468F` (info) | |
| Accordion active title / icon | `tab_active_color` | `#DA1212` | |

**Common mistakes:** eyebrow yellow on hero (use `#FFFFFF` or `#11468F`), navy as icon-box `primary_color` (should be RED `#DA1212`), white card on white section.

## 22. Banned hex + semantic palette

**Brand palette (Elementor Kit globals — use for brand roles):**
- `#DA1212` primary red, `#11468F` secondary navy, `#041562` text dark navy, `#000000` accent, `#FFFFFF` white, `#F4F4F4` card_bg light grey

**Allowed semantic greys (acceptable for §21 documented roles):**
- `#555555` body description, `#777777` footnote, `#DDDDDD` borders, `#E0E0E0` light text on dark

**Banned hexes (refuse on queue):**
- `#FFC107` (yellow), `#B45309` `#92400E` (amber), `#15803D` `#28A745` `#16A34A` (greens), `#FFE5E5` `#FFF1F1` (pinks), any blue other than `#11468F`/`#041562`, any red other than `#DA1212`, any teal/purple/orange/brown.

The two-color brand discipline (red on navy) is the single biggest brand differentiator. Adding green/yellow/pink reads as consumer-app or wellness-vertical — wrong vertical for emergency care.

## 23. Plugin lint quirks (cc-assistant — work-around these)

1. **Heading-width floor on `container_add`:** Heading widgets need `_element_custom_width.size ≥ 700` even though §7 says 650 standalone. Use 800px for headings in multi-column layouts. Only `container_add` enforces this floor — `draft_update_elementor_widget` passes 650.

2. **`_element_custom_width` requires `_element_width: "initial"`** to take effect. Always set both.

3. **wall_of_text aggregation false-positive on `container_add`:** Lint sums text across sibling widgets. 6-card grid with 30-word descriptions trips 200-word guard. Workaround: tighten to ≤20 words OR `override_lint: true`.

4. **Em-dash lint runs on every text-bearing field.** Replace `—` with `.` `,` or parens.

5. **Map gate consumed per `container_add` call**, not 10-min session. Sequence: gpm → ca → gpm → ca → ...

6. **Race-safety guard refuses stale pendings** if post_modified > queued_at. Re-fetch + re-queue.

7. **Hard violations (422 on queue):** em_dashes, ai_tells, style_guide, wall_of_text, placeholders, accessibility_contrast, section_width_violation, map_not_consulted. Overrides: `override_lint`, `override_a11y`, `override_section_width` (no override for map_not_consulted).

8. **Elementor field-name gotchas:**
    - Container width: `boxed_width` (NOT `boxed_content_width`)
    - Button hover bg: BOTH `button_background_hover_color` AND `background_hover_color`
    - Button hover text: `hover_color`
    - Google Maps `address` should include business name for labeled pin
    - Get Directions deep link: GBP `/place/` URL with CID (not `?q=`)
    - Icon-box icons must be FA-Free 5.x compatible (FA Pro 6 icons render blank)

9. **Accordion `_id` unique across whole page** (not just within one accordion). Use prefixed IDs like `lufkin-fever-faq-1`.

## 24. Image handling when no real facility photo exists

**via.placeholder.com is DEAD (verified 2026-07: it broke all 3 images on the LOP page 6910). NEVER use it, or any external placeholder host.** In a small market like Lufkin, stock photos are even MORE damaging to trust than in urban markets.

**Preferred order when no purpose-shot photo exists:**
1. **Reuse a REAL library photo already live on this site** (verified working 2026-07): `2025/12/Lufkin.jpeg` (facility, attachment 6138), `2025/11/ExperiencedPhysicans.webp`, `2025/11/onsitectscan.webp`, `2025/11/Foreign-Objects-in-the-Eye.webp`, `2025/11/Emergency-Eye-Injury-Care-in-Lufkin-TX.webp`. Match the section topic loosely and write an honest alt.
2. If nothing fits, use Elementor's bundled local placeholder (`/wp-content/plugins/elementor/assets/images/placeholder.png`) with "PLACEHOLDER" in the alt and `USER: swap with real Lufkin facility photo via Media Library` in `summary`.

**MUST do:** match aspect ratio, honest alt text, note the operator swap in `summary` whenever a placeholder ships, set `image.id` to `""` for non-library URLs.

## 25. Common Elementor widget patterns

### Hero (90vh, navy bg, eyebrow + H1 + sub + 2 CTAs + trust strip)

```json
{
  "min_height": {"unit":"vh","size":90,"sizes":[]},
  "min_height_tablet": {"unit":"vh","size":75,"sizes":[]},
  "min_height_mobile": {"unit":"vh","size":70,"sizes":[]},
  "boxed_width": {"unit":"px","size":1200,"sizes":[]},
  "flex_direction":"column", "flex_justify_content":"center", "flex_align_items":"center",
  "padding": {"unit":"px","top":"96","right":"24","bottom":"96","left":"24","isLinked":false},
  "background_background":"classic", "background_color":"#11468F"
}
```

Children: eyebrow → H1 → text-editor sub → button row (Call + Get Directions) → 4-item icon-list trust strip.

### Callout strip (red bg, full-width, icon + text)

```json
{
  "boxed_width": {"unit":"px","size":1200,"sizes":[]},
  "flex_direction":"row", "flex_wrap":"wrap", "flex_align_items":"center", "flex_justify_content":"center",
  "flex_gap": {"unit":"px","size":16,"sizes":[],"column":"16","row":"16","isLinked":true},
  "padding": {"unit":"px","top":"20","right":"32","bottom":"20","left":"32","isLinked":false},
  "background_background":"classic", "background_color":"#DA1212"
}
```

Children: `icon[primary_color #FFFFFF, size 28]` + `text-editor[white, 15/13px, width 900]`. **Content MUST be self-anchored** — no hospital comparison (§14, §18).

### 3-card and 4-card rows

Section: `bg #FFFFFF, boxed 1200, padding 80/24, column align-center`. Header: eyebrow + H2 (`#041562, 32/26/22px, width 800, center`) + intro (`#555555, 16px, width 800, center`). Card-grid: row, wrap, stretch, gap 20. Card width 31%/100% (3-card) or 23%/48%/100% (4-card). Card border-left 4px solid `#DA1212` or `#11468F`, bg `#F4F4F4`, padding 24px. Card widget: icon-box with `primary_color #DA1212`, title `#041562`, description `#555555`, text_align left.

### Accordion section

Section: `bg #F4F4F4, boxed 1200, padding 80/24, column align-center`. Header (eyebrow + H2 + intro). Accordion widget: tabs array with `_id` unique per page (prefix `lufkin-`), `selected_icon fas fa-plus`, `selected_active_icon fas fa-minus`, `title_color #041562`, `tab_active_color #DA1212`, `tab_content_color #555555`, `border_color #DDDDDD`, `_element_custom_width 900`, `_flex_align_self center`.

### Image + text 50/50 split

Section: `bg #FFFFFF or #F4F4F4, boxed 1200, padding 80/24, row, wrap, center, flex_gap col 48 row 32`. Image column: width 48%/100%, image widget with placeholder src per §24. Text column: width 48%, flex_align_items flex-start, heading widgets at `_element_custom_width 800` (lint floor). Alternate direction (text-left vs image-left) section to section.

### CTA bookend

Section: `boxed 1200, navy #11468F bg, padding 80/24, column align-center`. 2-col 60/40 split: left = eyebrow + H2 (white, 36/28/24px) + sub (`#E0E0E0`) + 2-button row; right = white card padding 32 radius 2 with H3 + form widget.

### Get Directions deep link

Use this site's GBP `/place/` canonical URL with CID. Get from Google Business Profile dashboard:
```
https://www.google.com/maps/place/{Business+Name}/@{LAT},{LNG},Nm/data=...
```

### Google Maps widget address

```json
"address": "ER of Lufkin, [street address from §1], Lufkin, TX [zip]"
```

Including business name makes Google match the verified GBP listing and render the labeled pin (instead of a naked street-address marker).

---

**Last refreshed:** 2026-09-08. Revision 2026-09-08: added §26, the featured/OG image doctrine (no people on the ER brands, enforced in `featured/image-policy.json`; emphasis derived from measured contrast; column-paint floor). Major revision 2026-05-28: added TL;DR header, §21 color role assignment, §22 banned hex / palette, §23 plugin lint quirks, §24 image placeholder protocol, §25 widget pattern library. Strengthened §14 + §18 to ban hospital ED comparisons (even within local market) — operator policy applied across all 3 ER sites because comparison wording damages hospital referral relationships and patient trust in hospitals.

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

**Open item:** this site has only the wordless red/navy cross, which is square, so
it costs 68px of vertical room in a corner and reads as a speck at listing size.
Its sister ER of White Rock has a real horizontal wordmark; one for this site would
be worth having. A reversed lockup for the navy grounds already exists for all three:
navy half turned white, red half kept.

---
