---
name: design-system-global
description: GLOBAL design system for ALL Elementor sites (erofwhiterock, erofirving, eroflufkin, irvingwellnessclinic + future). Numeric tokens + checkable invariants + Elementor Kit-token (__globals__) mechanics. Read before ANY page build, widget styling, or design review on any site. Per-site skills carry only brand voice/geo; THIS file owns the visual system.
---

# Global Design System (token-based, site-agnostic)

Two principles: (1) BRAND comes from each site's Elementor Kit via tokens, never hardcoded hexes. (2) STRUCTURE (sizes, spacing, alignment) comes from the fixed numeric scales below, identical on every site. Source-verified against Elementor 4.0.4 + Material 3 / Practical Typography / WCAG 2.2 / Refactoring UI.

## A. Brand tokens: Elementor `__globals__` (per-site auto-branding)
- Syntax: sibling key inside settings: `"__globals__": {"title_color": "globals/colors?id=primary", "typography_typography": "globals/typography?id=primary"}`. Group key = prefix+`typography` (icon-box title: `title_typography_typography`). Global ref ALWAYS beats the literal twin; when writing a ref, empty the literal.
- System ids: primary, secondary, text, accent (+ custom rows' 8-hex `_id`s). MISSING id fails SILENTLY (no CSS emitted) — validate against the kit (`get_post_meta(kit_id,'_elementor_page_settings')`) before writing.
- **DON'T-WRITE list** (absence = automatic per-site branding via widget-type defaults): heading title_color/typography (→primary), text-editor text_color/typography (→text), icon-box icon+title (→primary) and description (→text), button typography (→accent), body/link/h1-h6 styling, container_width, container padding, widget gaps.
- Write a `__globals__` ref ONLY to pick a different token than the default. Literal hexes ONLY for values with no kit token (rare; never literal-copy a hex that equals a kit color). Neutral greys allowed: 50 #F9FAFB / 100 #F3F4F6 / 200 #E5E7EB / 400 #9CA3AF / 600 #4B5563 / 800 #1F2937.
- Token USAGE budget (60-30-10): neutral ~60% (page bg, cards, borders), primary ~30% (dark section bgs, headings, icons, links), accent ~10% (THE CTA only; one element type per viewport), text = body copy. NOTE: some kits misuse tokens (e.g. accent=#000); buttons therefore default to primary bg + secondary hover (see D).

## B. Type scale (ONLY these sizes; ratio 1.25, base 18)
desktop/mobile px @ line-height, weight: h1 48/34 @1.1-1.15 700 (one per page, hero only) · h2 36/28 @1.2 700 · h3 28/24 @1.25 600 · h4 22/20 @1.3 600 · body-lg 20/18 @1.5 (hero sub, intros) · body 18/16 @1.6 · small 14 @1.5.
Invariants: any size outside {14,16,18,20,22,24,28,34,36,48} = FAIL; heading LH <=1.3, body 1.5-1.65; body max 75ch (~720-800px), min 45ch; ONE heading family + ONE body family, weights subset of {400,600,700}; same heading level = same px page-wide; never skip levels.

## C. Spacing (8pt tokens: 0,4,8,16,24,32,40,48,64,80,96)
Section padding 80 desktop (64-96) / 48-64 mobile · heading->intro 16 · intro->content 40 · grid gap 24 · card padding 32(24-32) · icon->title 16 · title->desc 8 · para->para 16 · content->CTA 32.
Invariants: off-scale value (20,25,30,72...) = FAIL · proximity: gap-within <= 0.5 x gap-between · heading attaches DOWN (space above >= 2x below) · no flat rhythm (>=3 distinct steps per section) · root sections boxed 1200px (sample site's existing roots; some run 1280-1300 — match the site), text blocks <=800px.

## D. Components
BUTTONS: min-height 48 (hit >=44x44) · text_padding {14 vert, 28 horiz} (ratio 2:1; <1.5 = default-button tell) · ONE radius site-wide from {4,8,24} (default 8) · exactly ONE solid primary CTA per viewport, others outline · hover = one ramp step shift + must pass 4.5:1 · standard build block:
```
settings: { text, link, "button_text_color":"#FFFFFF", "hover_color":"#FFFFFF",
 "border_radius":{"unit":"px","top":"8","right":"8","bottom":"8","left":"8","isLinked":true},
 "text_padding":{"unit":"px","top":"14","right":"28","bottom":"14","left":"28","isLinked":false},
 "__globals__":{"background_color":"globals/colors?id=primary","button_background_hover_color":"globals/colors?id=secondary"} }
```
(hover bg key is button_background_hover_color; background_hover_color does not exist.)
CARDS: equal heights per row (grid stretch) · identical structure + ONE icon size per row (32/40/48) · border OR shadow never both (border 1px neutral-200 OR shadow 0 1px 3px rgba(0,0,0,.10)) · alignment uniform per row; text-editor align:left + parent flex_align_items:flex-start explicit.
HERO: min-height 520 desktop / 420 mobile (60-70vh, cap 90vh) · overlay 0.40-0.60 verified vs lightest image region · contents max: 1 h1 + 1 body-lg + 2 buttons.
FORMS: input 48px, font >=16, visible labels (placeholder-only = FAIL), <=7 fields, width <=480.

## E. Alignment / grid
One root max-width per site · columns in {1,2,3,4}; 5+ per row = FAIL · CSS Grid for equal cards (never flex-wrap) with grid_rows_grid {"unit":"custom","size":"auto","sizes":[]} + grid_auto_flow row + grid_gaps (plural, strings) · every left edge aligns to the container content edge (delta <=4px) · CENTER only if <=3 lines AND (hero | section heading+intro | CTA band); paragraphs >3 lines always left · 3->2->1 columns at 1024/768 · mobile side padding >=16.

## F. The 9 amateur tells (audit priority)
1 off-scale/mixed type sizes · 2 full-width text >75ch · 3 default-looking buttons (no padding ratio/hover/styling) · 4 mixed radii · 5 >4 hues or accent on 2+ element types · 6 off-grid spacing / flat rhythm · 7 ragged card rows (unequal heights, mixed icons/alignment) · 8 proximity inversion (heading floats) · 9 contrast sins (pure #000 body, grey <4.5:1, unscrimmed hero text, hover <4.5:1).

## G. Build workflow (every page, every site)
1. Read the site's Kit (validate token ids exist). 2. Compose with DON'T-WRITE defaults + scale tokens + standard blocks above. 3. Section variety by intent (cards only for catalogs; stat bands; photo/text 58-38 splits stacking mobile; tables/quotes in text-editor; chip anchor-nav on 6+ section pages). 4. Image placeholders (Elementor placeholder.png + "PLACEHOLDER:" alt) where the operator supplies photos; hero bg left to operator unless provided. 5. Audit against B-F before showing the operator.

Cross-refs: Elementor mechanics ground truth = memory reference_elementor_ground_truth (20-trap list). Per-site brand voice = {site}-design skills. cc-assistant builder implements section D button block from v0.42.3.

## H. Accessibility pairing rules (added 2026-06-11, user-flagged red-on-blue)
- NEVER place a brand-color solid on another brand-color background (red CTA on blue band = ~1.4:1). On dark brand bands the solid button INVERTS: white bg + band-color text (8:1), hover neutral-200; outline twin = white border/text.
- COMPUTE, don't eyeball: text >=4.5:1 (large >=3:1), UI component vs adjacent bg >=3:1 (WCAG 1.4.11) - includes button-on-section, icon-on-card, border-on-bg. Before queueing any colored element, check its pair ratio.
- Eyebrows/kickers are STYLED TEXT, not headings: heading widget header_size:"p" (an h6 before the h1 breaks heading order for screen readers).
- Audit sweep per page: every text/bg pair, every button/band pair, heading order h1->h2->h3 no skips + no decorative heading tags, touch targets >=44px, image alts present (PLACEHOLDER alts count until operator swaps), link text descriptive.

## I. Section variety — DESIGN, do not template (added 2026-06-19, user-flagged "4 sections look identical")
You are the designer. A page is NOT a stack of identical card grids — that reads as a template and gets rejected. Each section earns its layout from its CONTENT TYPE:
- **Sequential / process** (what happens when you arrive, how we treat, steps over time) -> `steps` template (numbered, no card chrome). NEVER as another card_grid.
- **Explainer / "what is X" / definition** -> `text_image` split (image + prose). This is also how you hit the image quota.
- **Parallel catalog of peers** (types, causes, symptom domains) -> `card_grid`. Cards are for genuinely parallel items only.
- **Comparison / location->cause / timing** -> a table inside a text-editor (or a card_grid if 2 cols feel wrong).
- **Q&A** -> `faq` accordion. **CTA** -> `cta_band` (navy).
- INVARIANTS (build_page_from_spec ENFORCES these — a fresh chat that ignores them gets a 422): at least **2 image-bearing `text_image` sections** per page; **never 4 card_grids back-to-back**; if 4+ card_grids total you need >=2 `steps`/`text_image` sections breaking them up. Hero stays imageless for LCP unless the operator gives a hero photo.
- Vary backgrounds (white / #F4F4F4 alternate) AND layout shape, so no two adjacent sections look the same.

## J. Icon names: FA5 only (Elementor bundles Font Awesome 5)
FA6-only names render BLANK on these sites. The composer auto-normalizes (normalize_icon maps FA6->FA5), but author FA5 names anyway: info-circle (NOT circle-info), check-circle (NOT circle-check), times-circle (NOT circle-xmark), exclamation-circle/exclamation-triangle (NOT circle-/triangle-exclamation), car-crash (NOT car-burst), fist-raised (NOT hand-fist), hard-hat (NOT helmet-safety), heartbeat (NOT heart-pulse), shield-alt (NOT shield-halved), walking (NOT person-falling), dizzy/frown (NOT face-*), user-md, ambulance, tint, tasks. Every icon-box gets a DISTINCT, meaningful icon; danger cards (severe/avoid/911) auto-color red via card_tone (EN+ES), everything else navy.

## K. Operating principle: assume expertise, then execute
For whatever the task is (page design, SEO, medical-content framing), act as an expert in it from the first move — bring the standard, don't wait to be corrected into it. All of this file's rules + the cc-assistant composer/audit enforcement exist so a brand-new chat builds to standard by default. Read this skill at session start; the plugin blocks the rest.

## L. Attention flow — compose the eye path BEFORE choosing sections (added 2026-07-30, v0.55)
A page is a controlled path for the eyes, not a stack of sections. BEFORE composing: call `attention_spec(post_id)` — it resolves the archetype (emergency_transactional / consideration_conversion / service_local / informational_guide) and returns the 5-second job, first-viewport requirements, scroll story, and CTA rules. Compose to serve that spec. AFTER building: call `attention_audit(id)` — flat_hierarchy is the generic-page smell; fix majors before queueing.
Non-negotiables: (1) first viewport carries ~60-80% of attention — the 5-second job lives there, nothing else competes; (2) ONE primary CTA per viewport, proof element immediately before every conversion ask; (3) emergency archetype = tap-to-call tel: link above the fold, always; (4) headings are the only text a scanner is guaranteed to read (layer-cake) — front-load keywords at the left edge (F-pattern); (5) alternate band treatments (white/tinted, boxed/full-bleed) so the eye gets landmarks — deliberate weight contrast between hero and support bands; (6) squint test: blur the page — what still stands out must be, in order, what matters most; (7) compose for 390x700 mobile first, thumb-zone CTAs bottom-center. Validate predictions against real Microsoft Clarity heatmaps when the site has a project ID configured (settings).

## M. Widget settings: never guess keys — widget_schema is ground truth (added 2026-07-30, v0.56)
Before styling ANY widget with a setting key you have not verified on this site, call `widget_schema(widget_type)` — it reads Elementor's live controls stack (core+Pro+addons, version-accurate): valid keys, defaults, enum options, units, responsive collapse, and which controls take `__globals__` kit tokens. A typo'd key is a silent no-op; the queue now warns (unknown_setting_key / invalid_option_value with did-you-mean) but the professional move is to check first. This supersedes memorized key lists when they conflict.
