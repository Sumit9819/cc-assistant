---
name: erofirving-design
description: UX/UI design system and brand rules for ER of Irving (erofirving.com). Invoke whenever proposing visual changes, new Elementor widgets, restructured sections, or copy that lands on this site. Covers typography, color, spacing, components, mobile, accessibility, performance, site-specific brand voice, catchment vocabulary, and the ER-specific UX patterns that make an emergency-care site feel trustworthy.
---

# ER of Irving — Design + Brand Skill

This document is the canonical UX/UI specification for erofirving.com. Anything queued via `draft_*` tools that visually affects the site must comply. Lint-fail rules are enforced server-side; everything else is reviewer-enforced.

## TL;DR — read this in 30 seconds before any edit

The 12 rules below catch 90% of mistakes. Read the full skill before queueing visual or structural changes — but at minimum memorize these:

1. **Address + phone:** see §1 (or `get_site_memory()` if §1 hasn't been updated)
2. **Geo lead:** Irving first, then Las Colinas / Valley Ranch / Coppell / Grand Prairie / Farmers Branch. NEVER reference White Rock / Lakewood / Lake Highlands (that's ER of White Rock's catchment).
3. **Brand primary:** `#DA1212` (icons, primary CTAs, urgency accents). **Secondary:** `#11468F` (eyebrows, secondary CTAs, button hover). **Text:** `#041562` (H1/H2/H3 titles). See §21 for full role table.
4. **Body text:** ALWAYS `text-align: left`. Center alignment is for hero copy and standalone H2s only — NEVER for body or lists.
5. **Long-form text widget width:** 650px standalone, 800–900px in multi-column layouts. **Plugin lint floor:** heading widgets inside `container_add` must be `_element_custom_width ≥ 700px` (use 800). See §23.
6. **Containers:** root `boxed_width: 1200` max. Hero `min_height: 90vh` desktop. Section padding 80–120px desktop / 48–64px mobile.
7. **Buttons:** `border_radius: 2px`, ≥44px tap target, primary red bg + white text + navy hover. Hover requires BOTH `button_background_hover_color` AND `background_hover_color` fields set. See §9, §21.
8. **No hospital ED comparisons** in any copy (even cited stats — see §14 and §18 anti-pattern). Self-anchored facts only. Hospitals are referral partners.
9. **No off-brand hex.** Only: `#DA1212 #11468F #041562 #F4F4F4 #FFFFFF #000000` + semantic greys `#555555 #777777 #DDDDDD #E0E0E0`. **Banned (lint should refuse):** `#FFC107 #B45309 #15803D #28A745 #FFE5E5 #FFF1F1` and any other yellow / amber / green. See §22.
10. **Step 0 of any edit:** load this skill via the `Skill` tool. Then run `get_page_map(post_id)` immediately before any `container_add` (gate is consumed per call, not 10-min session). See §23.
11. **Race-safety:** if the post was modified externally (or by a prior approval batch) after your pending was queued, the apply guard refuses with "Refusing apply to avoid clobbering." Re-fetch + re-queue. See §23.
12. **For a new service page:** try `build_service_page(mirror_post_id=137, ...)` macro FIRST (IV Therapy `137` is the canonical pillar on Irving). The macro clones the working pillar with text replacements in one call.

**Sister-site reminder:** This site is the ORIGINAL. ER of White Rock and ER of Lufkin were cloned from it. If you find Irving content on the sister sites, that's pull-direction. If sister-site content appears here (White Rock catchment, Lufkin community references), flag and fix.

**Section index:** §1 identity · §2 geo · §3 voice · §4 tokens · §7 widths · §9 components · §14 ER trust signals · §18 anti-patterns · §19 pre-edit · §20 quick checklist · §21 color role assignment · §22 banned hex / palette · §23 plugin lint quirks · §24 image placeholder protocol · §25 widget pattern library

## 1. Site identity

| Field | Value |
|---|---|
| Site name | ER of Irving |
| URL | https://erofirving.com |
| Category | Freestanding emergency room (YMYL Health) |
| Industry overlay | `healthcare` |
| Primary catchment | Irving, Las Colinas, Valley Ranch |
| Secondary catchment | Coppell, Grand Prairie, Farmers Branch, Carrollton, Bedford, Grapevine, Addison, Arlington, Euless |
| Sister sites | This site is the original. ER of White Rock and ER of Lufkin were cloned from it. |
| Provider byline status | Check per-session — confirm with operator before naming any provider |

## 2. Geo-priority (mandatory anchor rule)

**Lead with Irving. Las Colinas + Valley Ranch as primary suburb references. DFW suburb names from the catchment list above are fair game for location-specific pages.**

- ✅ "Serving Irving, Las Colinas, Valley Ranch, and the wider DFW area"
- ✅ "Emergency room near Coppell" (on the Coppell location page)
- ✅ Use suburb-specific location pages for hub-and-spoke geo coverage
- ❌ "Serving Dallas residents" (Dallas is east of Irving; not our catchment)
- ❌ Reference White Rock, Lake Highlands, Lakewood, East Dallas (those are ER of White Rock's catchment)

## 3. Voice & tone

- **Direct, calm, clinical-but-warm.** Same as the sister sites.
- **Numbers + named sources** beat opinion.
- **Triage-decisioning H2s** outperform service descriptions.
- **No em dashes** (lint-enforced).
- **No AI-tell phrases** (lint-enforced).
- **Sentences ≤25 words for ≥85%.** Grade 7–9 reading level.
- **No opinion framing.** Anchor every claim to a citation or operational fact.

## 4. Design tokens

Pull from the Elementor Kit via `CC_Assistant_Brand_Profile::get()`. Never hardcode hex. Same token model as the sister sites — primary, secondary, accent, text, card_bg, page_bg, heading_font, body_font.

## 5. Typography

- Body size: 16px desktop minimum, 18px ideal for long-form.
- Line height: 1.5–1.7 body, 1.2–1.3 headings.
- Max line length 65 characters. **Long-form text containers max-width 650px.**
- Heading scale ratio 1.25×–1.5× between levels.
- Font weight contrast 400 body / 600 or 700 headings.

## 6. Spacing — 4px sub-grid for components, 8px for layout

**Two-tier grid.** Layout spacing (sections, page padding, large gaps) sticks to multiples of 8: **8, 16, 24, 32, 48, 64, 80, 120**. Component spacing (button padding, card padding, list-item gap, icon-to-text) drops to multiples of 4: **4, 8, 12, 16, 20, 24**. This matches Material Design and Tailwind conventions.

| Spacing role | Value |
|---|---|
| List item to list item | 8–12px |
| Heading to its paragraph | 16px |
| Paragraph to paragraph | 24px |
| Component to component | 32–48px |
| Section vertical padding (desktop) | 80–120px |
| Section vertical padding (mobile) | 48–64px |
| Widget internal padding | 16–32px |
| Page horizontal gutters (mobile) | 16–24px |

## 7. Layout & containers

- Hero section: 90vh–100vh, phone + primary CTA visible without scroll.
- Long-form text container: max-width 650px (65ch rule).
- Multi-column or image grid: max-width 1200px.
- Containers never exceed 1200px except for full-bleed photo backgrounds.

## 8. Section distinction

- Alternate `page_bg` and `card_bg`. No two adjacent sections share background.
- **No shape dividers** (waves, slashes, zigzags).
- Separate sections by whitespace + subtle background shift only.

## 9. Components

### Buttons
- **Corner radius 2px** (consistent with inputs, images).
- **Minimum tap target 44px tall.**
- **Padding: 16px vertical, 24px horizontal** (on the 4px component sub-grid).
- Primary CTA solid fill, secondary outlined/ghost. One primary CTA per section.
- All `tel:` links on phone-number buttons. Descriptive aria-labels: `aria-label="Call ER of Irving now at (insert-number)"`.
- One hover mechanism site-wide.

### Lists
- **Body + lists `text-align: left`** (never center).
- `list-style-position: outside`, `padding-left: 24px`.
- 8–12px between list items.
- Max 8 items before splitting / using accordion.

### Headings
- One H1 per page (hero H2 is canonical H1 on Elementor pages).
- 30%+ of H2s should be question-shaped on YMYL pages.
- Question H2s need 40–60 word answers in the same section.
- No skipped levels.

### Images
- Alt text mandatory.
- Hero: 1920×1080 or 1920×1200, WebP, ≤200KB.
- Aspect ratio consistent within a section.
- Corner radius matches buttons (2px) OR fully square — never mixed.
- **No stock photos in hero.** Real facility, real staff.

### Forms
- Labels above inputs.
- Required markers visible.
- Submit = primary CTA style.
- Inline error states.

### Cards
- Inner padding 24px or 32px.
- `card_bg` token (lighter than page).
- Corner radius matches buttons.

## 10. Wall-of-text guard

Text-editor widget >200 words without internal heading/list/table/image is refused at queue time. Route long content to the right widget shape.

## 11. Mobile rules (375px minimum)

- Section vertical padding 60–70% of desktop.
- Stack columns below 768px.
- Touch targets ≥44px.
- Tap-to-call on every phone number.
- Hamburger right, logo left.

## 12. Accessibility (WCAG 2.1 AA)

- Contrast 4.5:1 body / 3:1 large.
- Skip-to-content link.
- Visible focus states.
- Descriptive aria-labels on icon-only buttons and phone CTAs.
- Heading order with no skips.
- Form labels associated with inputs.
- `prefers-reduced-motion` respected.

## 13. Performance

- WebP images, lazy-loaded below the fold.
- LCP ≤2.5s, CLS ≤0.1.
- Defer non-critical JS.
- No autoplay video with sound.
- Single subtle hero animation max.
- Transitions ≤300ms.

## 14. ER-specific UX

- Phone number above the fold, every page. Tap-to-call.
- "Open 24/7" badge near hero.
- EMTALA mention as legal differentiator.
- **Door-to-provider stat (self-anchored — no hospital comparison)** — "Median door-to-provider: under 10 minutes. Open 24/7. No appointment needed." Display as a red callout strip below the hero. **DO NOT compare against hospital ED wait times** (even with cited stats from ED Benchmarking Alliance or similar). Hospitals are referral partners; comparative wording erodes patient trust in hospitals and damages referral relationships. See §18 anti-pattern.
- Triage-decisioning section on every service page.
- ER vs urgent care vs primary care decision framework.
- Insurance logos block.
- Real facility photos.
- "Call 911 if life-threatening" disclaimer visible.

## 15. Authority sources

Same allow-list as the sister sites: CDC, NIH, MedlinePlus, FDA, NEJM, JAMA, Lancet, Annals of Emergency Medicine, AAP, AHA, ACEP, ENA, Texas DSHS. Plus CMS Hospital Compare and ED Benchmarking Alliance for operational stats.

**Never cite:** competitor ERs or clinics, paid medical directories, Wikipedia, WebMD, Healthline, AI-generated aggregators.

## 16. Service inventory

This site is the canonical source for the shared service inventory across the cloned sister sites. Run `service_inventory` MCP if uncertain — content should map to actual offerings.

## 17. Sister-site differentiation rules

- This site IS the original. ER of White Rock cloned from it without full content rewrite. ER of Lufkin similar.
- If you find content on this site that names "White Rock", "Lake Highlands", "Lakewood", or "Casa Linda" as the catchment — that's WRONG-DIRECTION cloning (something was pulled FROM a clone back to here). Flag and fix.
- This site's clone-protection responsibility: when sister sites need original content, write it on THIS site first, then let the operator port it to the sister with site-specific geo rewrites.

## 18. Anti-patterns

1. Don't write copy that names White Rock, Lake Highlands, Lakewood, Casa Linda, Forest Hills, Garland, Mesquite as the catchment — those are East Dallas; this site serves Irving and the western DFW suburbs.
2. Don't link to `/emergency-room-{city}-tx/` URLs — that's White Rock's slug pattern. Irving uses `/emergency-services-in-{city}-tx/`.
3. Don't add wave/slash/zigzag dividers.
4. Don't center-align body text or lists.
5. Don't hardcode hex colors.
6. Don't write generic content without a cited stat.
7. Don't propose H1 changes without checking GSC top-impression queries first.
8. **Don't position our ER's speed by comparing against hospital EDs** — even with cited industry stats. Knock-marketing against hospitals erodes patient trust in hospitals over time, and hospitals are our REFERRAL DESTINATIONS for trauma / ICU / inpatient care. Self-anchored door-to-provider claims only. Comparison phrasing is allowed only for ER vs urgent care vs primary care decision aids (helping patients pick the right venue, not knocking anyone).
9. **Don't hardcode hex outside §22.** Eyebrow yellow `#FFC107`, success green `#15803D` / `#28A745`, amber `#B45309`, light-red wash `#FFF1F1` are all off-brand. See §22 for the full allowed/banned list.

## 19. Pre-edit checklist (mandatory)

Before queueing any visual or structural edit:

0. **Load this skill via the `Skill` tool** — `skill: erofirving-design`. If already loaded in this session, jump to step 1. The TL;DR is a fallback; the full skill is authoritative.
1. **Curl-test the URL** — `WebFetch` or `curl -s -I -L`. Verify 200 OK, no redirect.
2. Confirm rendered URL matches post URL from `get_post`.
3. For internal links: verify via `list_posts` search, do not pattern-match.
4. Read `seo_playbook.fit` from whoami.
5. Check `recent_pending` for stacked changes.
6. For new sections at page root: always pass explicit `position`.
7. **For a NEW service page from scratch:** strongly prefer `build_service_page(mirror_post_id=137, replacements={...}, title=...)` macro over building 13+ containers manually. IV Therapy `137` is the canonical pillar on Irving. Macro preserves flex layouts, hover states, responsive properties, and Kit globals byte-for-byte.
8. **Re-fetch state between approval batches.** The race-safety guard refuses pendings whose snapshot is older than the last post modification — if the operator approved batch A while you had batch B queued, batch B will fail. Reject + re-queue against current snapshot.
9. **Before recommending ANY schema / JSON-LD edit, curl the live page and read what's actually there.** Never hand the operator a "replace placeholder X" template — workflow: `curl -sL -A "Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)" "URL"` → extract all `<script type="application/ld+json">` → identify what's operator-installed vs Rank Math auto-emit → propose concrete ADDITIONS (with cross-referenced `@id`s) rather than full replacements. Use `@graph` to bundle related entities in one block.

## 20. Quick-check before queue

- [ ] All sentences ≤25 words?
- [ ] No em dashes?
- [ ] No AI tells?
- [ ] Body left-aligned?
- [ ] Lists with `list-style-position: outside`?
- [ ] Spacing on 8px grid?
- [ ] Long-form text container ≤650px?
- [ ] Button 2px radius, ≥44px tap target?
- [ ] Internal links verified?
- [ ] Cited stat from allow-list?
- [ ] Irving named first in any geo reference?
- [ ] No shape dividers?

## 21. Brand-color role assignment (which token for which widget role)

Token usage is NOT abstract — every widget role has an assigned color. Picking colors by feel produces off-brand drift. If you find yourself reaching for a hex not in the table below, stop and reconsider.

| Widget role | Field name | Color | Notes |
|---|---|---|---|
| Eyebrow heading (uppercase span above H2) | `title_color` | `#11468F` (secondary) | Use `#FFFFFF` on navy hero |
| H1 | `title_color` | `#FFFFFF` on hero / `#041562` on light bg | One H1 per page |
| H2 (section header) | `title_color` | `#041562` (text) | White on dark bg |
| H3 in cards / accordions | `title_color` | `#041562` | |
| Body / description text | `text_color` | `#555555` (semantic grey) | Never `#000000` |
| Footnote / disclosure | `text_color` | `#777777` (semantic) | |
| Subtitle on dark bg (hero sub) | `text_color` | `#E0E0E0` (semantic) | |
| Icon-box icon glyph | `primary_color` | `#DA1212` (primary) | Brand-emergency signal |
| Icon-box title | `title_color` | `#041562` | |
| Icon-box description | `description_color` | `#555555` | |
| Icon-list icon | `icon_color` | `#DA1212` | |
| Icon-list text | `text_color` | `#555555` or `#FFFFFF` on dark | |
| Button primary bg | `background_color` | `#DA1212` | |
| Button primary text | `button_text_color` | `#FFFFFF` | |
| Button primary hover bg | `button_background_hover_color` AND `background_hover_color` | `#11468F` | BOTH fields needed |
| Button primary hover text | `hover_color` | `#FFFFFF` | |
| Button secondary bg (ghost) | `background_color` | `rgba(0,0,0,0)` | |
| Button secondary text | `button_text_color` | `#DA1212` or `#FFFFFF` on dark | |
| Card container bg | `background_color` | `#F4F4F4` (card_bg) or `#FFFFFF` | |
| Section root container bg | `background_color` | `#FFFFFF` or `#F4F4F4` | Alternate (§8) |
| Hero container bg | `background_color` | `#11468F` | |
| Callout strip (urgency) bg | `background_color` | `#DA1212` | White text |
| Subtle dividers / borders | `border_color` | `#DDDDDD` | |
| Card left-border accent (4px) | `border_color` | `#DA1212` (urgency) or `#11468F` (info) | |
| Accordion title color | `title_color` | `#041562` | |
| Accordion active title / icon | `tab_active_color` | `#DA1212` | |
| Accordion content text | `tab_content_color` | `#555555` | |
| Form field border | `field_border_color` | `#DDDDDD` | |

**Common mistakes the table prevents:** eyebrow yellow on hero (use `#FFFFFF` on navy, `#11468F` on light), navy as icon-box `primary_color` (should be RED `#DA1212`), white card on white section (use `#F4F4F4` cards).

## 22. Banned hex + semantic palette

**Brand palette (Elementor Kit globals — use these for brand roles):**
- `#DA1212` — primary red
- `#11468F` — secondary navy
- `#041562` — text dark navy
- `#000000` — accent
- `#FFFFFF` — white
- `#F4F4F4` — card_bg light grey

**Allowed semantic greys (not in Kit, but acceptable for documented §21 roles):**
- `#555555` — body description text
- `#777777` — footnote / disclosure
- `#DDDDDD` — borders / dividers
- `#E0E0E0` — light text on dark backgrounds

**Banned hexes — lint should refuse, reviewer must catch otherwise:**
- `#FFC107` (yellow / amber) — never
- `#B45309` `#92400E` (dark amber) — off-brand
- `#15803D` `#28A745` `#16A34A` `#22C55E` (greens) — off-brand
- `#FFE5E5` `#FFF1F1` `#FFEEEE` (light reds / pinks) — sections must be `#FFFFFF` or `#F4F4F4`
- Any blue other than `#11468F` and `#041562`
- Any red other than `#DA1212`
- Any teal, purple, orange, brown

**Why hexes outside this list are banned:** The brand identity is medical-emergency (red) on professional-trust (navy) — two-color disciplined. Adding green / yellow / pink reads as consumer-app or wellness-vertical, not emergency.

## 23. Plugin lint quirks (cc-assistant — work-around these)

1. **Heading-width floor on `container_add`:** Heading widgets in a container_add need `_element_custom_width.size ≥ 700` even though §7 says 650px standalone. Use 800px for headings in multi-column layouts. The SAME heading passes `draft_update_elementor_widget` at 650 — only `container_add` enforces the floor (cross-endpoint inconsistency).

2. **`_element_custom_width` requires `_element_width: "initial"`** to take effect. Always set both.

3. **wall_of_text aggregation false-positive on `container_add`:** Lint sums text across sibling text-bearing widgets (icon-box descriptions, text-editor, headings). 6-card grid with 30-word descriptions trips the 200-word guard. Workaround: tighten descriptions to ≤20 words OR pass `override_lint: true`.

4. **Em-dash lint runs on every text-bearing field.** Replace `—` with `.` `,` or parens.

5. **Map gate consumed per `container_add` call**, not 10-min session. Sequence N adds: gpm → ca → gpm → ca → ...

6. **Race-safety guard refuses stale pendings** if post_modified > queued_at. Re-fetch + re-queue after each approval batch.

7. **Section-pair safe positions:** `get_page_map` returns `safe_insert_positions` and `risky_insert_positions`. Risky = inside an H2-alone + body pair. Use safe positions.

8. **Hard violations (422 on queue):** em_dashes, ai_tells, style_guide, wall_of_text, placeholders, accessibility_contrast, section_width_violation, map_not_consulted. Override flags exist for the first 6 (`override_lint`, `override_a11y`, `override_section_width`); map_not_consulted has no override.

9. **Elementor field-name gotchas:**
    - Container width: `boxed_width` (NOT `boxed_content_width`)
    - Button hover bg: BOTH `button_background_hover_color` AND `background_hover_color`
    - Button hover text: `hover_color` (not `button_text_hover_color`)
    - Google Maps `address` should include business name to render labeled pin
    - Get Directions deep link: use the GBP `/place/` URL with CID (not `?q=`)
    - Icon-box `selected_icon.value` must be FA-Free 5.x compatible (FA Pro 6 icons like `fa-head-side-medical` render blank)

10. **Accordion `_id` must be unique across the whole page** (not just within one accordion). Use prefixed IDs.

## 24. Image handling when no real facility photo exists

When the operator hasn't uploaded a real photo yet, use a clearly-marked placeholder. **Use via.placeholder.com — NOT Unsplash or Pexels.**

**Placeholder URL pattern:**
```
https://via.placeholder.com/{WIDTH}x{HEIGHT}/F4F4F4/041562?text=PLACEHOLDER+{DESCRIPTIVE+LABEL}
```

**Examples:**
- Lab / imaging: `https://via.placeholder.com/800x600/F4F4F4/041562?text=PLACEHOLDER+Lab+%2B+Imaging+Suite`
- Facility exterior: `https://via.placeholder.com/800x600/F4F4F4/041562?text=PLACEHOLDER+Facility+Exterior`
- Hero background: `https://via.placeholder.com/1920x1080/11468F/FFFFFF?text=PLACEHOLDER+Hero+Photo`

**MUST do:** match aspect ratio to the slot, include "PLACEHOLDER" in URL text AND `image.alt`, write `USER: swap with real {description} photo via Media Library` in the pending change `summary`, set `image.id` to `""`.

**NEVER:** Unsplash / Pexels / other CC stock URLs (skill §14: no stock for facility imagery, even temporary).

## 25. Common Elementor widget patterns (copy-paste-ready)

When building a section, copy the closest pattern below and edit only the content fields.

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

Children: `heading[span, white, uppercase 14px, ls 2px, width 800]` → `heading[h1, white, 52/40/30px, weight 800, width 800]` → `text-editor[#E0E0E0, 18/16px, width 800]` → `container[row, gap 16, width 720][button[primary red], button[ghost white]]` → `icon-list[4 items, icon #DA1212, text white, width 900]`.

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

Children: `icon[primary_color #FFFFFF, size 28px]` + `text-editor[white, 15/13px, width 900]`.

Use for: wait-time stat strip, urgent disclaimer, 911-now banner. **Content MUST be self-anchored** — no hospital comparison (§14, §18).

### 3-card row (icon-box per card)

Section: `bg #FFFFFF, boxed 1200, padding 80/24, column align-center`. Header: eyebrow + H2 (`#041562, 32/26/22px, width 800, center`) + intro (`#555555, 16px, width 800, center`). Card-grid: `row, wrap, stretch, gap 20, width 100%`. Each card: `width 31%/100%, column, padding 24px, border-left 4px solid #DA1212 or #11468F, bg #F4F4F4`. Card widget: `icon-box[selected_icon, title_text, description_text, position top, primary_color #DA1212, title_color #041562, description_color #555555, text_align left]`.

### 4-card row

Same as 3-card row but each card `width 23%/48%/100%`.

### Accordion section (FAQ)

Section: `bg #F4F4F4, boxed 1200, padding 80/24, column align-center`. Header: eyebrow + H2 + intro.
Accordion widget: tabs array with `_id` unique-per-page (use prefix like `irving-fever-faq-1`), `selected_icon fas fa-plus`, `selected_active_icon fas fa-minus`, `title_color #041562`, `tab_active_color #DA1212`, `tab_content_color #555555`, `border_color #DDDDDD`, `_element_custom_width 900`, `_flex_align_self center`.

### Image + text 50/50 split

Section: `bg #FFFFFF or #F4F4F4, boxed 1200, padding 80/24, row, wrap, center, flex_gap col 48 row 32`.

Image column: `width 48%/100%, column`. Image widget: placeholder URL per §24, `image_border_radius 2px, _element_custom_width 100%`.

Text column: `width 48%, column, flex_align_items flex-start`. Heading widgets at `_element_custom_width 800` (lint floor), text-editor 800. Optional button.

**Alternate direction** (text-left vs image-left) section to section.

### CTA bookend (final section, navy bg, 60/40 text+form)

Section: `boxed 1200, navy #11468F bg, padding 80/24, column align-center`. Inner 2-col row: `gap 40, width 100%, wrap`. Left col (58%): eyebrow + H2 (white, 36/28/24px) + sub (`#E0E0E0`) + 2-button row. Right col (38%): `white card, padding 32px, border-radius 2px`, H3 + form widget.

### Get Directions deep link

Use the site's GBP `/place/` canonical URL (not `?q=`). Replace with this site's specific listing URL from Google Business Profile. Example structure:
```
https://www.google.com/maps/place/{Business+Name}/@{LAT},{LNG},Nm/data=!3m2!1e3!4b1!4m6!3m5!1s{HEX_CID_1}:{HEX_CID_2}!8m2!3d{LAT}!4d{LNG}!16s{PLACE_PATH}
```

### Google Maps widget address

```json
"address": "ER of Irving, [street address from §1], Irving, TX [zip]"
```

Including the business name lets Google match the verified GBP listing and render the labeled pin.

---

**Last refreshed:** 2026-09-08. Revision 2026-09-08: added §26, the featured/OG image doctrine (no people on the ER brands, enforced in `featured/image-policy.json`; emphasis derived from measured contrast; column-paint floor). Major revision 2026-05-28: added TL;DR header, §21 color role assignment, §22 banned hex / palette, §23 plugin lint quirks, §24 image placeholder protocol, §25 widget pattern library. Removed any hospital ED comparison language from §14; operator policy bans positioning against hospital partners across all 3 ER sites.

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
