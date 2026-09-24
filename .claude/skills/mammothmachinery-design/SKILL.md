---
name: mammothmachinery-design
description: UX/UI design system and brand rules for Mammoth Machinery (mammothmachinery.ca). Invoke whenever proposing visual changes, new Elementor widgets, restructured sections, or copy that lands on this site. Covers the verified Kit tokens (id 70), typography, color-contrast traps (the #01B51B green), container rhythm, the product/category page templates, CTA rules, imagery, the SEO/plugin stack, and the known site-wide defect inventory as of 2026-07-21.
---

# Mammoth Machinery — Design + Brand Skill

Canonical UX/UI specification for mammothmachinery.ca. Anything queued via `draft_*` tools that visually affects this site must comply. Sourced from live Kit (id 70), `get_page_style_context` on Home (11) / category (41) / product (1616), `audit_page_design`, and `render_probe` — all sampled 2026-07-21.

**This is a heavy-equipment retail/manufacturer site — NOT healthcare.** No YMYL-medical rules, no ER/wellness tropes, no medical schema. Trust = warranty + Canadian manufacturing + component provenance, not clinicians.

## TL;DR — 12 rules that catch 90% of mistakes

1. **Brand green `#01B51B` is a contrast trap.** 2.75:1 against white — NEVER small green text or green-text buttons on white. Use green on dark (`#141414`/`#1A1C1C`) backgrounds, as accent borders/icons, or as button FILL with dark text. (10 live violations exist on Home.)
2. **Exactly one H1 per page.** The current home + ALL product pages have `h1_count=0` (name and even price render as H2). Every new/edited page must fix this, never copy it. Blog listing proves the theme renders H1 fine.
3. **Headings = Alata 700, set weight explicitly.** Kit-inherit renders non-bold (audit fail). **Body text: EXPLICIT custom typography Raleway 500 at 18px, line-height 1.6em — OPERATOR-MANDATED SIZE (2026-07-21, after rejecting 16px AND the kit "text" token, which resolves ~16px, AND preset `0f5c3ff`, which is smaller still).** Always clear the kit typography ref (`__globals__ typography_typography: ""`) so nothing overrides the 18px. Icon-lists: same explicit `icon_typography_*` 18px, `icon_color` = primary green `#01B51B` (globals primary), item text color = body color (`secondary` token).
4. **Never hardcode hex — use Kit tokens** (`globals/colors?id=...`). De-facto extended palette (`#141414`, `#1A1C1C`, `#F9F9F9`, `#F3F4F6`) is on-brand in practice but off-Kit; prefer nearest Kit token (`secondary`, Overlay 1/2) for new work.
5. **Body text `#787878` (kit `text`) on white fails 4.5:1 by a hair (≈4.48).** On white use `#141414`/`#1F2937`-class ink; keep `#787878` for large/secondary text only.
6. **Kit `accent` = `#FBFBFB` (near-white).** It is a BACKGROUND token here, not a text accent. Do not use it for text on light backgrounds.
7. **One primary CTA per page.** Category pages currently carry 10 distinct CTA labels (audit-flagged). Primary = "Request a Quote" / "Find a Dealer" (equipment buyers get quotes; they don't add-to-cart). "Call Now" (1-800-686-1036) is secondary. Prices shown in **CAD**.
8. **Price standard (operator-chosen 2026-07-22):** render as a div (never a heading tag), text always "$X,XXX CAD" with comma + CAD, styled EXPLICITLY: Alata 700, 28px desktop / 24px mobile, color `#141414`. Never leave a price on kit-inherit or bare custom-size — converting H2→div strips the kit heading style and the price collapses to small grey text (happened across 12 pages; fixed in pending 214-228). Product hero bg = black + light overlay at 0.75, renders light, so dark price text is correct there.
9. **Alternate section backgrounds.** Product template runs 3 consecutive `#000000` sections (monotony fail); alternate dark / `#F8F8F8` / white. Root sections boxed 1200–1300px; heading+intro column 800–900px; spacing on the 8-px scale (16/24/32/40/56/96).
10. **Every image needs alt text.** 8/8 home images and 18/20 product-template images lack it. Elementor image widget bakes alt into widget settings (`widget.image.alt`), not just media library.
11. **Widget kit available:** heading, text-editor, icon-list, button, image, form, `eael-data-table` (spec tables), `elementskit-accordion` (FAQ), `htmega-thumbgallery-addons` (product gallery), `eael-woo-product-carousel`. Match these; NO raw HTML widget for content. Sample the page first (`get_page_style_context`) — Home body widgets run IBM Plex Sans, category pages run Alata everywhere; match the page you're on. **EXCEPTION (operator-mandated 2026-07-28): FAQ accordions use ONE style site-wide, homepage included — the machine-page spec, copied verbatim from widget 9f49845 on page 1551** (closed: kit-tint `c6e7c89` card, 1px green border, green title; open: green `primary` bg, white `astglobalcolor4` title; 7px radius; content 25px padding, Raleway 16/500 lh 26 via preset `0f5c3ff` + custom override; plain `<p>` answers, NO inline styles). **KEY-SET TRAP (confirmed 2026-07-28): `ekit_acc_*` settings keys are DEAD in this ElementsKit version — they generate ZERO CSS (post-11.css and post-43.css have no rules for their accordions; only `ekit_accordion_*` keys produce rules, verified in post-1551.css).** Never style an accordion with `ekit_acc_*`; the category-page accordions (43/39) carry dead keys and render plugin defaults + inline answer styles. Verify styling landed by grepping the page's generated `post-<id>.css` for the widget id.
12. **Step 0 of any edit:** load this skill + `design-system-global`, run `get_page_map(post_id)` before `container_add`, `render_probe(id)` before AND after. Wall-of-text lint (≥200 words, no structure) refused at queue; `_element_custom_width` floor ≥700 applies.
13. **WRAPPER-CENTERING TRAP (confirmed twice, 2026-07-21).** `draft_add_elementor_container` wraps the heading+first-text pair in a NEW sub-container at apply time and stamps it `_flex_align_self: "center"` + `_margin: left/right auto` — REGARDLESS of what alignment you submit. Auto cross-axis margins steal free space, so the block centers even when children are flex-start. **After EVERY section apply: fetch the tree, find the wrapper, queue `{flex_align_items: flex-start, _flex_align_self: flex-start, _margin: 0}` on it.** This site is left-aligned; operator has rejected centered blocks twice.
14. **Inline text links:** class `mm-body-links` + scoped style (one per page): `#01B51B`, no underline; hover = SAME color + underline appears. Never let theme hover colors apply (they blend into the background — operator-reported bug). Post-approval verify routine: `render_probe` + `audit_page_design` + `page_robustness_audit` on every touched page, every time.

## 1. Site identity

| Field | Value |
|---|---|
| Site name | Mammoth Machinery |
| URL | https://mammothmachinery.ca |
| Category | Compact heavy equipment manufacturer/retailer (mini excavators, mini skid steers, track loaders, wheel loaders, mini dumpers, concrete buggies) |
| Market | **Canada-wide** ("coast to coast") — no city-level geo anchoring; national dealer network instead |
| Phone | 1 (800) 686-1036 |
| Currency | CAD — always display "$X CAD" |
| Trust assets | **5-Year / 3,000-Hour Warranty** (flagship differentiator), "The Mammoth Pledge", Canadian manufacturing, financing from 1.99% APR, parts & service support, dealer partnerships |
| Component provenance | Named suppliers are a trust pattern in live copy (Kubota engines, Nachi hydraulic pumps) — keep using real component names, verified from client spec sheets only |
| Industry overlay | `ecommerce` (AUTO-detected, 25% confidence, drift_detected — vocabulary match only 6%; verify each ecommerce playbook rule against real content) |
| Business mode | generic |
| Sister sites | None — standalone brand, no cross-site cloning risk |

## 2. Stack (verified)

- **WP 7.0.2, Elementor (active), Kit id 70.** Page builder addons in use: Essential Addons (eael-*), ElementsKit (elementskit-*), HT Mega (htmega-*).
- **SEO: Rank Math** — emits Organization/WebSite/WebPage/Product/BreadcrumbList JSON-LD. Route meta via `draft_update_seo_meta` logical keys. A second, hand-built `LocalBusiness` block (emitter "unknown") ships on every page — do not add a third org-level node; extend via @graph only after `render_probe`.
- **WooCommerce installed but dormant** — Shop/Cart/Checkout/My-account pages are drafts. Product pages are ordinary Elementor **pages**, not Woo products (except an `eael-woo-product-carousel` on the product template). Do not resurrect Woo pages.
- Behind a proxy cache (`x-proxy-cache` headers). External curl may be unreliable — always verify with `render_probe` (loopback).

## 3. Voice & tone

From live copy: "Built for Canada. Engineered for Performance." / "Straight Answers from a Canadian Equipment Brand."

- **Plain, confident, contractor-to-contractor.** Short declarative sentences. No hype, no superlative soup.
- **Canadian identity is the brand spine** — Canadian-built, Canadian winters, coast-to-coast support. Lead trust with the 5-yr/3,000-hr warranty.
- **Spec-anchored claims only.** Every capability claim traces to the client's own spec sheet or an existing page. NEVER invent specs, capacities, or compatibility. Derived claims (e.g. "fits a standard gate" from width) must be flagged for client confirmation in the pending-change note.
- **Buyer-decision framing** beats feature lists: what jobs, what ground, what trailer class, which model up/down the lineup.
- No em dashes (lint). No AI-tell phrases (lint). Sentences ≤25 words for ≥85%. Grade 7–9.
- Blog quotes: only verified, previously-published statements (manufacturer engineering releases, dealer associations, WorkSafeBC/CCOHS, .gc.ca/.edu). Pull-quote card styling per network rule: border-left 4px **#01B51B**, light neutral bg, radius 0 8px 8px 0.

## 4. Color tokens (Kit id 70) + contrast law

| Token | Hex | Use |
|---|---|---|
| primary | `#01B51B` | Brand green. Accents, icon color, borders, button FILL on dark sections, large display text on dark. **Never small text on white (2.75:1). White text on green fill also fails 4.5:1 — pair green fills with `#141414` text or reserve for ≥24px text.** |
| secondary | `#000000` | Dark section backgrounds, ink |
| text | `#787878` | Secondary/large text only on white (4.48:1 — borderline fail). Fine on `#F8F8F8`? No — worse. Use for captions ≥18px or on dark. |
| accent | `#FBFBFB` | Near-white — light background/text-on-dark token. NOT a text accent on light. |
| Overlay Color 1 | `#E7E7E7` | Borders, dividers |
| Overlay Color 2 | `#F8F8F8` | Alternate light section bg |
| New Global Color | `#01B51B0F` | 6% green tint — subtle brand wash bg |
| White | `#FFFFFF` | White |
| Transparent 1 | `#00000000` | Utility |

De-facto (off-Kit but ubiquitous): heading ink `#141414`, dark section `#1A1C1C`, light bgs `#F9F9F9`/`#F3F4F6`. When editing existing widgets keep consistency; for new sections prefer Kit tokens. Body ink on white: use `#141414`.

## 5. Typography

Kit: primary/secondary = **Poppins**, text = **Raleway 500**, accent = Poppins 600. Custom presets are nearly all **Alata 500–700** (headings/buttons). Reality on page: headings + display = **Alata 700**; category pages run Alata for everything (111 uses on page 41); Home body widgets run **IBM Plex Sans**. → **Sample the target page first and match it**; for brand-new pages: headings Alata 700 via custom presets (`c2b9635` "2 heading"), body via `globals/typography?id=text` or preset `0f5c3ff` "3 text".

Observed scale: H1 48px, H2 42px (36 on interior), H3 22–24px, body 16–17px/1.6em, kickers as styled divs 13–15px. **Trap:** product template has H2 at 20px < H3 24px (inversion, audit-flagged) — set explicit sizes on new headings.

## 6. Layout & spacing

- Containers are **flexbox** (`e_swap_sections` era). Common shapes: root `content_width: full` with inner boxed; inner columns `flex_direction: column, nowrap`.
- Root sections boxed **1200–1300px**. Heading+intro columns **800–900px**. Long-form standalone text 650px.
- Spacing on the 8-px scale: 16/24/32/40/56; section padding 56–96px desktop (industrial sites run tighter than wellness — Home uses ~60–80).
- Column-direction containers holding text MUST set `flex_align_items: flex-start` AND text-editor `align: left` (defaults center — global gotcha).
- Icon-box/card rows: CSS Grid (`container_type: grid`, `grid_columns_grid: Nfr`), not flex-wrap.
- Alternate adjacent section backgrounds (dark `#1A1C1C` / white / `#F8F8F8`); never two identical resolved bgs back-to-back.

## 7. Page templates (as-built patterns)

**Product page** (e.g. 1616): hero [name — currently H2, should be H1 + price styled as p/div + `icon-list` key specs + buttons] → `htmega-thumbgallery-addons` gallery → `eael-data-table` full specifications → Features & Benefits (`icon-list`/`elementskit-accordion`) → `eael-woo-product-carousel` related. All on black — alternate bgs when rebuilding. ~300–350 words (needs depth per SEO plan: in-lineup comparison table, warranty block, step-up/step-down links, FAQ accordion).

**Category page** (e.g. 41 Tracked Mini Dumpers): hero (H1 present, center) → intro text (17px Alata, `#E0E0E0` on dark) → product cards (icon + text-editor + button, heavy repetition) → accordion FAQ. Audit score 81. Fix pattern: reduce to ONE primary CTA label, add authority citation, add question-shaped H2s.

**Home** (11): 7 root sections; animated-headline + image-carousel in hero; design_score 61 (contrast + alt + heading-order fails). Kickers/eyebrows currently use real heading tags — render kickers as styled `<p>`/div, never headings.

**Footer** (global, on every page): "Copyright © 2026" H2 (should be p), Quick Links / Categories / Contact Info / Policies as H4 columns, 3× "Call Now" H3s. Site-wide empty `tel:` wrapper link (`eael-wrapper-link`) — known a11y defect; logo link's img lacks alt.

## 8. CTA rules

- Primary: **"Request a Quote"** or **"Find a Dealer"** — one label per page, repeated; everything else secondary/outline. (No cart — buying journey is quote/dealer.)
- Secondary: "Call Now" tel:1-800-686-1036.
- Buttons: Alata 500 (`103eb48` preset), ≥44px tap target, hover state mandatory (`button_background_hover_color` — note the field name).
- No urgency-pressure fakery ("Only 2 left!") — industrial buyers, long consideration cycle.

## 9. Imagery

- Real machine photography only, hosted on this domain (`/wp-content/uploads/`). Never placeholder services, never stock that misrepresents the actual machines.
- Every image widget: descriptive alt with machine model + context ("X-Cavator 20MT mini excavator digging trench"). Image SEO is disproportionately valuable in equipment retail.
- Product galleries via `htmega-thumbgallery-addons`; keep multiple angles per machine.
- Existing library naming is messy (`imgi_*`, `Untitled-design-*`) — prefer meaningful filenames for new uploads.

## 10. Known site-wide defect inventory (as of 2026-07-21 — don't re-audit, fix through pending changes)

1. `h1_count=0` on Home + all 21 product pages (blog listing is the only sampled page with H1).
2. 93% of pages score "weak" helpful-content (mean 47.5, site verdict FAIL); product pages ~300–350 words.
3. Green-on-white contrast: 10 violations on Home (buttons + headings `#01B51B` on `#FFFFFF`, 2.75:1) + 2 white-on-white headings.
4. Alt text missing: 8/8 Home images, 18/20 product-template images.
5. Heading-order chaos: kickers as H2/H3/H4 before any H1; footer copyright as H2.
6. Product template: price as H2; H2(20px) < H3(24px) inversion; 3 consecutive black sections.
7. Index bloat: `/full-size-track-loaders-copy/` (2655, 8 words, published+linked); duplicate X-Loader 3000MT (1630 canonical vs 2096 at wrong slug `wl7500-wheel-loader-2` → merge/301); TL5500 on `-2` slug (2134); 3 author archives + uncategorized product-cat in sitemap.
8. Blog posts reachable only via /blog/ listing widget — zero contextual inbound links from money pages.
9. Category pages: ~10 distinct CTA labels; 0 authority citations; few question-H2s.
10. Rank Math breadcrumb `position` emitted as string not integer (info-level, fix at Rank Math source).
11. GSC not connected (client access pending) — no query data; do not claim ranking facts.
12. Draft junk to eventually clean: "Wheeled Mini Dumpers - Copy" (2050), Elementor #1891/#1666 (empty), duplicate Warranty (2212), old About (14), Mini Skidsteers copy (1549), Woo pages (24–27).

## 11. Pre-edit checklist

1. `Skill: mammothmachinery-design` + `Skill: design-system-global` loaded.
2. `whoami` done this session; `list_pending_changes` before queueing (esp. after compaction).
3. `render_probe(id)` BEFORE the edit (live DOM: H1 count, schema emitters, alt, links).
4. `get_page_style_context(post_id)` — match the page's actual fonts/colors/containers, not defaults.
5. `get_page_map(post_id)` before any `container_add`.
6. Layout-altering changes (widget add/remove, >25% rewrite) → propose to operator first; surgical text edits queue freely.
7. After queueing: `verify_change(pending_id)`; after apply: `render_probe` again + `audit_page_design`.
8. Before stopping: `update_site_memory_notes` one-liner.
