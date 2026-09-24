---
name: irvingwellnessclinic-design
description: UX/UI design system and brand rules for Irving Wellness Clinic (irvingwellnessclinic.com). Invoke whenever proposing visual changes, new Elementor widgets, restructured sections, or copy that lands on this site. Covers typography, color, spacing, components, mobile, accessibility, performance, site-specific brand voice, catchment vocabulary, AND wellness/aesthetics-specific UX patterns (which differ from the emergency-care sister sites).
---

# Irving Wellness Clinic — Design + Brand Skill

This document is the canonical UX/UI specification for irvingwellnessclinic.com. Anything queued via `draft_*` tools that visually affects the site must comply.

**IMPORTANT:** This is a **wellness clinic**, not an emergency room. The voice, urgency, and trust signals differ materially from the ER sister sites. Do NOT carry ER tropes here.

## TL;DR — read this in 30 seconds before any edit

The 12 rules below catch 90% of mistakes. The wellness voice differs sharply from the ER sites — read §2 (voice) and §15 (wellness UX patterns) carefully.

1. **Address + phone:** see §1 (or `get_site_memory()`)
2. **Provider:** Lori Secerovic, FNP-C, MSN is the named clinical lead — schema Person + "Medically Reviewed by" pattern allowed on this site only. Bio rule §18: hospital + academic affiliations only, NO competitor clinics.
3. **Geo lead:** Irving / Las Colinas / Valley Ranch first. NEVER reference White Rock / Lake Highlands / Lakewood.
4. **Brand colors:** wellness palette differs from ER sites — softer, spa-adjacent. Always pull from `CC_Assistant_Brand_Profile::get()`, never hardcode hex. See §5.
5. **Body text:** ALWAYS `text-align: left`. Center is for hero copy only.
6. **Long-form text widget:** 650px standalone, 800–900px in multi-column layouts. **Plugin lint floor:** heading widgets in `container_add` need `_element_custom_width ≥ 700` (use 800). See §22.
7. **Containers:** `boxed_width: 1200` max. Section padding 96–128px desktop (wellness gets more generous spacing than ER sites for premium positioning).
8. **Buttons:** 2px radius, ≥44px tap, **"Book Consultation" / "Schedule Lab Panel"** as primary CTA — NOT "Call Now" or "Walk In." Wellness buyers schedule.
9. **No urgency-pressure tactics.** No "Limited time!" / "Only 3 spots!" / "Don't wait!" — undermines medical positioning. §19 #1.
10. **Step 0 of any edit:** load this skill via `Skill` tool. Run `get_page_map(post_id)` before any `container_add` (gate is per-call, not 10-min session). See §22.
11. **Race-safety:** if the post was modified externally after your pending was queued, the apply guard refuses. Re-fetch + re-queue.
12. **No ER tropes here:** no "24/7", no "walk in", no EMTALA, no "911 disclaimer." This is a scheduled-care clinic.

**Section index:** §1 identity · §2 voice · §3 E-E-A-T (Lori byline) · §4 geo · §10 components · §15 wellness UX · §18 bio rule · §19 anti-patterns · §20 pre-edit · §21 quick checklist · §22 plugin lint quirks · §23 image placeholder protocol

## 1. Site identity

| Field | Value |
|---|---|
| Site name | Irving Wellness Clinic |
| URL | https://irvingwellnessclinic.com |
| Category | Wellness clinic — IV therapy, hormone optimization, aesthetics, longevity (YMYL Health) |
| Industry overlay | `healthcare` (with wellness sub-context — applies the medical playbook but tone diverges) |
| Primary catchment | Irving, Las Colinas, Valley Ranch |
| Secondary catchment | Coppell, Grand Prairie, Farmers Branch, DFW |
| Sister sites | The ER sites (erofirving, erofwhiterock, eroflufkin) — but the brand, voice, and visual tone here is DIFFERENT, not shared |
| Lead clinician | **Lori Secerovic, FNP-C, MSN** — verified and consented for byline use on this site (do NOT carry her name to any ER sister site) |
| Provider byline status | **Approved for Lori Secerovic.** Schema Person + "Medically Reviewed by:" pattern allowed here. |

## 2. Voice & tone (DIFFERENT from ER sites)

- **Confident, warm, aspirational — but never hyped.** Wellness buyers are evaluating a long-term relationship, not making a panic decision.
- **Outcomes-focused, evidence-anchored.** "Quarterly hormone panels" / "3D body composition scan" / "GLP-1 protocol with safety monitoring" — concrete services, not vague promises.
- **Acknowledge the medical context.** This is a clinic, not a med spa. Reference labs, protocols, peer-reviewed evidence.
- **No urgency-pressure tactics** ("Limited time!", "Only 5 spots!") — undermines the medical positioning.
- **Cite peer-reviewed sources** for hormone, weight-loss, longevity claims.
- **No em dashes** (lint-enforced).
- **No AI-tell phrases** (lint-enforced).
- **Sentences ≤25 words for ≥85%.** Grade 7–9.
- **Honest framing.** Don't promise outcomes you can't back with a cited mechanism. Tirzepatide weight-loss data, not "transform your life."

## 3. E-E-A-T — provider authority (the differentiator)

- **Lori Secerovic, FNP-C, MSN** is the named clinical lead. Use her byline + schema Person + Medically Reviewed pattern on every YMYL page.
- Schema Person fields: `name`, `jobTitle`, `hasCredential` (FNP-C + MSN), `sameAs` → LinkedIn (verify URL with operator) + NPI Registry.
- "Medically Reviewed by: Lori Secerovic, FNP-C, MSN | Updated: MONTH YYYY" visible on every clinical / service page.
- Bio page populated with credentials, training, philosophy, and (per the saved rule) **only hospital/academic affiliations** — no competitor clinics in the Professional Path section.

## 4. Geo-priority (mandatory anchor rule)

**Lead with Irving / Las Colinas / Valley Ranch. DFW for broader context. Don't drift into Dallas, Plano, or White Rock catchment language.**

- ✅ "Serving Irving, Las Colinas, and Valley Ranch"
- ✅ "IV therapy clinic in Las Colinas"
- ❌ "Wellness clinic near Dallas" (too generic — Dallas brand competition is fierce)
- ❌ References to White Rock, Lake Highlands, Lakewood (those are ER of White Rock's catchment)

## 5. Design tokens

Pull from the Elementor Kit via `CC_Assistant_Brand_Profile::get()`. Never hardcode hex. **The wellness brand palette differs from the ER sister sites** — typically softer, more spa-adjacent. Verify with brand profile before assuming.

## 6. Typography

- Body 16px minimum, 18px ideal.
- Line height 1.5–1.7 body, 1.2–1.3 headings.
- Max line length 65 characters. **Long-form text containers max-width 650px.**
- Heading scale 1.25×–1.5× between levels.
- **Aspirational sites can lean slightly larger on H1 (1.7× ratio)** for hero impact. Don't go larger than 1.7×.

## 7. Spacing — 4px sub-grid for components, 8px for layout

**Two-tier grid.** Layout spacing (sections, page padding, large gaps) sticks to multiples of 8: **8, 16, 24, 32, 48, 64, 80, 120**. Component spacing (button padding, card padding, list-item gap, icon-to-text) drops to multiples of 4: **4, 8, 12, 16, 20, 24**. **Sections can run slightly more generous on wellness/aesthetics** (96–128px desktop, on the 8px layout grid) to convey premium positioning. Mobile stays 48–64px.

## 8. Layout & containers

- Hero 90vh–100vh.
- Long-form text container max-width 650px (65ch rule).
- Multi-column / image grid max-width 1200px.
- **Wellness sites often use full-bleed hero photos** of the facility, IV chairs, treatment rooms. These can extend page-edge to page-edge.

## 9. Section distinction

- Alternate `page_bg` and `card_bg`. Wellness brands often use a soft secondary background (e.g. blush, sage, off-white) as the `card_bg` — verify with brand profile.
- **No shape dividers.**
- Whitespace + subtle bg shift.

## 10. Components

### Buttons
- **2px corner radius**, **≥44px tap target**.
- **Padding: 16px vertical, 24px horizontal** (on the 4px component sub-grid).
- Primary CTA solid fill, secondary outlined. One primary per section.
- "Book Consultation" / "Schedule Lab Panel" — wellness action verbs, not urgency.
- `tel:` links on phone CTAs. Descriptive aria-labels.
- One hover mechanism site-wide.

### Lists
- **Body + lists `text-align: left`.**
- `list-style-position: outside`, `padding-left: 24px`, 8–12px between items.
- Max 8 items before splitting.
- **For service tiers (Myers' Cocktail, GLP-1, Tirzepatide, etc.):** use price-list or icon-list widget, not naked bullets.

### Headings
- One H1 per page.
- 30%+ question-shaped H2s on YMYL pages ("Who is GLP-1 right for?").
- 40–60 word answers in the same section.
- No skipped levels.

### Images
- Alt text mandatory.
- Hero 1920×1080 or 1920×1200, WebP, ≤200KB.
- Aspect ratio consistent.
- 2px corner radius OR fully square. Wellness sites often go round/portrait for staff photos — that's fine if consistent within that section.
- **NO stock photos.** Real clinic interior, real IV chairs, real Lori. Stock breaks wellness/aesthetics trust faster than ER trust.
- Generated featured/OG images are a separate category with their own rules — see §24, which is non-negotiable and enforced in code.

### Forms
- Labels above inputs.
- Required markers visible.
- Submit = primary CTA style ("Book Consultation").
- Inline error states.
- Consider 2-step intake forms for high-intent services (GLP-1, hormone optimization) to reduce form abandonment.

### Cards
- 24–32px inner padding.
- `card_bg` token.
- 2px corner radius.

### Service / pricing tiers
- Use Elementor's **price-list** widget for SKU-priced services (Myers' Cocktail, single session vs package).
- **Verify tier prices via `service_inventory` MCP before publishing.** Do not invent prices.
- "Starting at $X" framing for services with variable scope.

## 11. Wall-of-text guard

>200 words without heading/list/table/image is refused.

## 12. Mobile rules

- Section padding 60–70% of desktop.
- Stack columns below 768px.
- ≥44px tap targets.
- Tap-to-call on phone.

## 13. Accessibility (WCAG 2.1 AA)

- Contrast 4.5:1 body / 3:1 large.
- Skip-to-content link.
- Visible focus states.
- Descriptive aria-labels.
- Heading order no skips.
- Form labels associated with inputs.

## 14. Performance

- WebP, lazy-loaded.
- LCP ≤2.5s, CLS ≤0.1.
- Defer non-critical JS.
- No autoplay video with sound.
- Transitions ≤300ms.

## 15. Wellness-specific UX (NOT ER patterns)

- **Phone above the fold, but secondary to "Book Consultation" CTA.** Wellness buyers schedule; they don't walk in.
- **"Book Consultation" or "Schedule Lab Panel" as primary CTA.** Phone is secondary.
- **Lab-result transparency** — quarterly labs, 3D body comp, baseline + follow-up tracking. These are the wellness differentiators.
- **Outcomes data when available** — average weight loss on GLP-1, average testosterone restoration time, etc. (Cited or operator-verified.)
- **Real provider photo + bio above the fold** on hub pages.
- **Insurance / payment block** — wellness is often cash-pay or HSA-eligible. Be explicit.
- **No ER-style "open 24/7" badges** unless that's actually accurate for the clinic.
- **No urgency tactics.** Wellness conversion happens over weeks, not in 30 seconds.

## 16. Service inventory (SKU-driven)

This site sells **specific SKUs**, not category-only services. Examples (verify against `service_inventory` MCP before referencing):
- IV therapy (Myers' Cocktail, NAD+, Glutathione, custom blends)
- GLP-1 / Tirzepatide weight loss
- Hormone optimization (TRT, HRT)
- Aesthetics (Skinvive, Sculptra, etc. — verify)
- Longevity & peptide therapy (verify)

**Gate content on actual SKUs**, not umbrella categories. Per saved rule: read service-page Elementor cards to build SKU inventory; umbrella titles like "Aesthetics" aren't the niche scope.

## 17. Authority sources

For wellness / hormone / weight-loss content:
- **Peer-reviewed:** NEJM, JAMA, Lancet, Endocrine Society, AACE, AAEP
- **.gov:** NIH, NIDDK, FDA (specifically FDA prescribing info for GLP-1, hormone protocols)
- **Specialty bodies:** Endocrine Society guidelines, American College of Lifestyle Medicine, A4M (verify operator approval — some longevity-medicine bodies are controversial)
- **For IV therapy:** trickier — limited peer-reviewed evidence base; cite primary nutrient deficiency studies and FDA-approved indications, not "IV vitamin therapy benefits" listicles.

**Never cite:** competitor wellness clinics, med spas, longevity practices (per the saved no-competitor rule). Hospitals and academic medical centers (Baylor, UT Southwestern, etc.) are pure authority signals and stay.

## 18. Provider bio rule (specific to this site)

When writing or rewriting a provider bio:
- **Include:** hospitals, academic medical centers, university programs (Baylor, UT Southwestern, etc.), specialty board certifications.
- **Exclude:** other wellness clinics, med spas, longevity practices, hormone clinics, IV bars — even if Lori is still active there.
- This rule is saved as a memory entry; honor it on every bio edit.

## 19. Anti-patterns

1. Don't write urgency-pressure copy ("Limited time! Only 3 spots!") — undermines medical positioning.
2. Don't promise outcomes you can't back with cited mechanism / peer-reviewed evidence.
3. Don't reference ER-specific tropes ("24/7 emergency care") — this is not an ER.
4. Don't reference Dallas / White Rock / Lake Highlands catchment language — Irving / Las Colinas / Valley Ranch only.
5. Don't include competitor wellness clinics in Lori's bio (per saved rule).
6. Don't use shape dividers.
7. Don't center-align body text.
8. Don't hardcode hex colors.
9. Don't write generic IV-therapy listicles. Anchor every benefit claim to a primary-source study (NIH / FDA / peer-reviewed).
10. Don't invent service prices or SKUs — verify via `service_inventory`.

## 20. Pre-edit checklist (mandatory)

0. **Load this skill via the `Skill` tool** — `skill: irvingwellnessclinic-design`. If already loaded in this session, jump to step 1. Wellness-vertical rules differ sharply from the ER sister sites — do NOT carry ER tropes here.
1. Curl-test the URL. 200 OK, no redirect.
1a. **Before recommending ANY schema / JSON-LD edit, curl the live page and read what's actually there.** Never hand the operator a "replace placeholder X" template. Extract all `<script type="application/ld+json">` blocks, identify what's operator-installed vs Rank Math auto-emit, propose concrete ADDITIONS (with cross-referenced `@id`s) using `@graph` to bundle related entities in one block. For wellness-specific schema: `Physician` (Lori Secerovic with consent), `MedicalProcedure`, `Service` with `offers`, and `FAQPage` are the high-value entity types.
2. Confirm rendered URL matches post URL.
3. Verify internal links via `list_posts`.
4. Read `seo_playbook.fit`.
5. Check `recent_pending`.
6. **For pricing or service tiers: verify via `service_inventory` first.**
7. **For Lori's bio edits: re-read the no-competitor rule before queueing.**
8. New sections at page root: explicit `position` required.

## 21. Quick-check before queue

- [ ] Sentences ≤25 words?
- [ ] No em dashes?
- [ ] No AI tells?
- [ ] Body left-aligned?
- [ ] Lists with `list-style-position: outside`?
- [ ] 8px grid spacing?
- [ ] Long-form text container ≤650px?
- [ ] Button 2px radius, ≥44px tap?
- [ ] Internal links verified?
- [ ] Irving / Las Colinas / Valley Ranch first in geo?
- [ ] Lori's byline + schema Person where applicable?
- [ ] No competitor clinics in bios?
- [ ] No ER tropes (24/7 emergency, walk-ins)?
- [ ] No urgency-pressure framing?
- [ ] Cited source for any wellness claim?
- [ ] No invented prices?

## 22. Plugin lint quirks (cc-assistant — work-around these)

These quirks are portable from the ER sister skills — same plugin, same behavior:

1. **Heading-width floor on `container_add`:** Heading widgets need `_element_custom_width.size ≥ 700` even though §6 says 650px standalone. Use 800px for headings in multi-column layouts. The SAME heading passes `draft_update_elementor_widget` at 650 — only `container_add` enforces the floor (cross-endpoint inconsistency).

2. **`_element_custom_width` requires `_element_width: "initial"`** to take effect. Always set both:
    ```json
    "_element_width": "initial",
    "_element_custom_width": {"unit": "px", "size": 800, "sizes": []}
    ```

3. **wall_of_text aggregation false-positive on `container_add`:** Lint sums text across sibling text-bearing widgets (text-editor `editor` + icon-box `description_text` + heading `title`). A multi-card section with 30-word descriptions can trip the 200-word guard. Workaround: tighten descriptions to ≤20 words each OR pass `override_lint: true`.

4. **Em-dash lint runs on every text-bearing field** including button text, headings, icon-box title_text. Replace `—` with `.`, `,`, or `(parens)`.

5. **Map gate (`map_not_consulted`) is consumed per `container_add` call**, not 10-min session. Sequence for adding N sections: `get_page_map → container_add → get_page_map → container_add → ...`

6. **Race-safety guard refuses stale pendings** if `post_modified > queued_at`. After approval batches, re-fetch state + re-queue.

7. **Section-pair safe positions:** `get_page_map` returns `safe_insert_positions` and `risky_insert_positions`. Risky = inside an H2-alone + body pair. Use safe positions.

8. **Hard violations refuse queue (422):** em_dashes, ai_tells, style_guide, wall_of_text, placeholders, accessibility_contrast, section_width_violation. Overrides exist (`override_lint`, `override_a11y`, `override_section_width`). `map_not_consulted` has no override — call gpm first.

9. **Elementor field-name gotchas:**
    - Container width: `boxed_width` (NOT `boxed_content_width`)
    - Button hover bg: BOTH `button_background_hover_color` AND `background_hover_color`
    - Button hover text: `hover_color` (not `button_text_hover_color`)
    - Google Maps `address` should include business name to render labeled pin
    - Icon-box `selected_icon.value` must be FA-Free 5.x compatible (FA Pro 6+ icons render blank)

10. **Accordion `_id` unique across whole page** (not just within one accordion). Use prefixed IDs like `iv-faq-1`, `glp1-faq-1`.

## 23. Image handling when no real facility photo exists

Skill §10 says **no stock photos for facility imagery.** Wellness/aesthetics trust collapses faster than ER trust with stock — buyers can tell a "stock-doctor-smile" isn't your real provider.

**DEAD SERVICE — do NOT use via.placeholder.com.** Verified 2026-07-14: the host returns nothing (status 000, 0 bytes). Pages built with it render broken hero backgrounds and empty image slots (white-on-white hero text). No external placeholder hosts at all.

**When the operator hasn't uploaded a real photo yet, reuse an EXISTING same-site Media Library image from the parent pillar (curl the pillar page and grep `wp-content/uploads` srcs) with an honest alt. VIEW the image before placing it (download + Read as image) — filenames lie: an "IHW-FraceMedia" file turned out to be Lori's staff portrait, and "Untitled-design-5-3" is an old event flyer (2026-07-14).**

The bg/text hex placeholders above need to be swapped for the wellness brand's actual values — DO NOT hardcode ER colors (`#F4F4F4` + `#041562`). Pull from the Kit via Brand Profile.

**Examples (replace bg/text with wellness brand hex):**
- IV chair / treatment room: `?text=PLACEHOLDER+IV+Therapy+Suite`
- Lori provider photo: `?text=PLACEHOLDER+Lori+Secerovic+FNP-C`
- Clinic exterior: `?text=PLACEHOLDER+Clinic+Exterior`
- Hero background: 1920×1080 with brand primary bg and white text

**MUST do when using a placeholder:**
- Match aspect ratio to slot (typical: 800×800 round for staff, 800×600 for cards, 1920×1080 for hero)
- Include "PLACEHOLDER" in BOTH the URL text param AND the widget's `image.alt` field
- In the pending change `summary`: `USER: swap with real {description} photo via Media Library` so the operator sees the TODO on inbox review
- Set `image.id` to `""` (empty — Elementor treats as external URL)

**NEVER:** Unsplash / Pexels / other CC stock URLs — skill §10 + §19 prohibits stock for facility imagery. Stock for wellness is a faster trust-killer than stock for ER.

---

## 24. Featured / OG images: depicting people (NON-NEGOTIABLE)

Third category, distinct from §10 and §23. Not stock, not a real facility photo: a
generated illustration, made by `D:/cc-assistant/tools/card-generator/featured`
(1200x630, four layouts, README carries the pipeline). §10's "no stock photos" bans
stock for FACILITY and PROVIDER imagery and still holds - it does not ban these, and
it does not license stock.

**Decide what the article is about before choosing a subject.** Most clinical posts
are about a mechanism - a dose plateau, protein intake, how a peel takes off layers.
Then the honest illustration is the mechanism or the `type` layout, and no body
belongs in the frame at all. A person is right when the post is about the experience
of care. A body is never a decoration.

**When a person is right, the test is PARITY.** Same photographic treatment as every
other portrait in the set: face visible, eye contact, calm expression, ordinary
well-fitting clothes, same lighting, same waist-up crop, same dignity. A larger-bodied
subject on weight-loss content is CORRECT - that is who the article is for, and a lean
model there is both a statement about who the clinic serves and an implied-outcome
claim on a YMYL page. The harm was never in showing the body. It is in showing it
differently from everyone else's.

**Spread body variety across the OTHER topics too.** If every larger-bodied subject
appears only on weight-loss posts and every lean subject only on aesthetics posts, the
site has drawn a line about who belongs where, and that pattern stigmatizes more than
any single image. Subject variety is otherwise unconstrained - a different person every
time is wanted, and the generator is not deterministic anyway.

**The bans are mechanical, not advisory.** `featured/image-policy.json` carries them as
patterns and they are enforced twice: on the generation brief before Chrome opens, and
on the shipped `alt` at render time, which every image must pass. Headless or faceless
torsos, belly/thigh crops, before-and-after pairs, staged scales and measuring tapes,
shame poses, low-angle "exaggerate the size" optics, undress or ill-fitting clothes,
branded pens (Ozempic/Wegovy/Mounjaro - trade dress implies the clinic dispenses that
brand; use an unbranded injector and the generic name in copy), any claim that the
person is a patient or staff, and "doctor"/"physician" (this clinic's provider is an
APRN). Food-as-blame props and blunt body language warn. **When a refusal arrives,
change the picture - not the wording that describes it.**

**Never pair a body with a claim.** "Not Losing Weight on Semaglutide?" beside a
larger-bodied portrait is a question about a drug and is fine. The same portrait beside
"Lose 20 lbs" is an implied outcome. Keep the scale out of the picture even when the
copy names it.

**Framing is enforced numerically.** Cutout layouts fit the photo by HEIGHT, so a
full-length figure shrinks to a narrow strip and the tile reads hollow with the headline
marooned - what looks like a spacing bug is a framing bug. Ask for waist-up, shoulders
filling the column, bleeding off the BOTTOM edge only. `proof.py` refuses under 28%
column paint; approved portraits measure 46-53%.

**Disclose.** These people are synthetic. Carry one line in the blog footer or media
credit - "images are illustrations, not patients or staff" - which also answers "is
that your patient?" before it is asked.

**Review at listing size.** `out/_listing-sheet.png` at 400px wide, every shot, every
time. The gates catch geometry and described depiction; they cannot catch a stigmatizing
photo paired with innocent alt text. That one is yours.

---

## Operator review needed — wellness-specific gaps not yet filled

This skill's most recent revision (2026-05-28) added the portable sections (TL;DR header, §22 plugin lint quirks, §23 image placeholder protocol, pre-edit Step 0) that are common to the cc-assistant plugin across all 4 sites. Two wellness-specific sections are NOT yet written because they need brand input from the operator:

**1. Brand-color role assignment table (analogous to ER skill §21)** — needs the actual wellness brand palette from the Elementor Kit. Get from `CC_Assistant_Brand_Profile::get()` on the site, then build a table mapping each widget role (eyebrow, H1/H2, icon-box icon, button bg/hover, card bg, etc.) to a specific kit token + hex. Without this table, color choices drift even with the best intentions. **Owner: operator. Estimated effort: 30 minutes once palette is confirmed.**

**2. Common wellness widget pattern library (analogous to ER skill §25)** — pre-built JSON snippets for: hero (wellness aesthetic, not ER navy), service tier price-list (Myers' Cocktail vs NAD+ vs Glutathione, etc.), provider bio block (photo + name + credentials + bio), consultation booking form, FAQ accordion, before/after image pair (where consent permits), testimonial / outcomes block. These patterns are sharply different from ER patterns (no red callout strips, no 911 disclaimers, no "Find Us 24/7"). **Owner: agent + operator. Estimated effort: 2 hours of session time to write + validate against existing wellness pages.**

**Banned hex list for wellness** (analogous to ER skill §22) — wellness brand may legitimately allow softer colors (blush, sage, off-white) that would be banned on ER sites. The banned list must be derived from the actual wellness palette + "everything else is banned" rule. **Owner: operator confirms the allowed palette.**

---

**Last refreshed:** 2026-09-08. Revision 2026-09-08: added §24, the depiction doctrine for generated featured/OG images (parity test, stigma bans enforced in `featured/image-policy.json`, waist-up framing floor, disclosure). Major revision 2026-05-28: added TL;DR header, §22 plugin lint quirks, §23 image placeholder protocol, pre-edit Step 0. Wellness-specific gaps (color role table, widget pattern library, banned hex list) flagged for operator follow-up — these need brand palette confirmation before writing.
