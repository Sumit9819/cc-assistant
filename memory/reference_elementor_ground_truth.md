---
name: reference-elementor-ground-truth
description: "Elementor 4.0.4 SOURCE-VERIFIED reference for programmatic _elementor_data building: how defaults resolve, container/grid/flex cheat sheet, widget schemas, 20-trap list (grid 2-row default, grid_gaps plural, button hover keys, alt branching), golden snippets. READ BEFORE any Elementor build/builder-code change."
metadata: 
  node_type: memory
  type: reference
  originSessionId: cf6b72ff-7286-4f35-bac6-cc72e684abbc
---

Source: Elementor 4.0.4 read directly (wp-content/plugins/elementor/), file:line verified 2026-06-11.

## How defaults resolve (the trap mechanism)
Two layers: (1) control default → get_init_settings merges via Base_Data_Control::get_value (base-data.php:56-67): `isset($settings[k]) ? verbatim : control default` — a control default with a real value EMITS CSS even if you never wrote the key. (2) frontend.css hard fallbacks (e.g. `--e-con-grid-template-rows: repeat(2,1fr)` frontend.css:1463). NO DEEP MERGE: partial value arrays (slider without unit, dimensions missing sides) render garbage — always write complete shapes. `null`=absent (default applies); `""`=present (kills default).

## Value shapes
- SLIDER `{"unit":"px","size":N,"sizes":[]}` (slider.php:47)
- GAPS `{"unit":"px","column":"24","row":"24","isLinked":true}` — column/row are STRINGS (gaps.php:42)
- DIMENSIONS `{"unit":"px","top":"8","right":"8","bottom":"8","left":"8","isLinked":true}` (strings)
- URL `{"url":"...","is_external":"","nofollow":"","custom_attributes":""}`
- MEDIA `{"url":"...","id":123,"size":"","alt":"(URL-only)"}`; ICONS `{"value":"fas fa-star","library":"fa-solid"}`
- Responsive: bare key = desktop; `_tablet`/`_mobile` suffixes; desktop-first inheritance; control 'default' sets desktop ONLY (controls-stack.php:924). Group prefixes: flex group → flex_direction; grid group field columns_grid → grid_columns_grid. Activators: typography_typography:"custom", background_background:"classic", border_border:"solid".
- Shells: `{"id":"7charhex","elType":"container","isInner":false,"settings":{},"elements":[]}`; child containers `"isInner":true`.

## Container cheat sheet (container.php)
- container_type default "flex" (:377). content_width default "boxed" → .e-con-inner capped at kit width (:396); child `width` control ONLY works when content_width:"full" (:435).
- padding absent → 10px ALL SIDES (frontend.css:1449); gap absent → kit 20px (frontend.css:1456).
- FLEX: flex_direction absent → COLUMN (css 1508) and injects --container-widget-width:100%; "row" injects --container-widget-flex-grow:1 + height 100% (flex-container.php:53) = widgets GROW/stretch in rows. flex_align_items absent → stretch. flex_wrap absent → nowrap desktop, mobile auto-wraps (css 1472,1659).
- GRID: grid_columns_grid default {fr,3}; mobile_default {fr,1} AUTO (grid-container.php:59); tablet has NO default (inherits desktop — set explicitly). **grid_rows_grid default {fr,2} = THE 2-ROW TRAP** (:84, css 1463). Correct fix: `{"unit":"custom","size":"auto","sizes":[]}` → template-rows:auto (:81-83). grid_auto_flow default "row". Gap key is **grid_gaps** (plural; grid_gap is dead). grid_justify/align_content are NO-OPS unless the axis unit is "custom" (:219,259).
- Background: background_background:"classic"+color/image/position/size; overlay background_overlay_color + **background_overlay_opacity default 0.5** (:816) — set it.
- Child-of-flex keys: _flex_align_self, _flex_size ("grow"/"none"/"custom"; _flex_grow dead unless "custom"). Container link dead unless html_tag:"a".

## MANDATORY-SET for programmatic builds
Flex: container_type, content_width(+boxed_width|width), flex_direction, flex_align_items ("flex-start" on every column holding text), flex_justify_content, flex_gap (full GAPS), padding (full DIMENSIONS), flex_wrap for card rows.
Grid: same minus flex_* plus grid_columns_grid+_tablet+_mobile, **grid_rows_grid {"unit":"custom","size":"auto","sizes":[]}**, grid_auto_flow:"row", grid_gaps.

## Widget cheat sheet
- COMMON: _element_width ""/"inherit"/"auto"/"initial"; _element_custom_width dead unless _element_width:"initial" (common-base.php:373) and sets flex-grow 0 (:371). Self-align = **_flex_align_self** (no _element_self_align). _margin/_padding/_background_*/_border_*/_element_id.
- heading: title (default "Add Your Heading Text Here"!), header_size default h2, align responsive no-default (start|center|end), title_color, typography_* group. Hover color styles only links inside title.
- text-editor: editor (default lorem!), **align has NO default → inherits ancestor text-align — always set "left"**; text_color, typography_*.
- button: text (default "Click here"), **link default {"url":"#"}** — always set. Hover truth-table: text=hover_color; **bg=button_background_hover_color**; border=button_hover_border_color (background_hover_color doesn't exist). Padding key = text_padding. size default "sm". align is class-based.
- image: image MEDIA (default grey placeholder), image_size default "large". ALT IS BRANCH-DEPENDENT (image-size.php:102, media.php:479): valid attachment id + registered size → postmeta _wp_attachment_image_alt; URL-only/custom-size → settings image.alt. Fix alts at attachment for library images, in widget for URL images. object-fit dead unless height.size set.
- icon-box: selected_icon, title_text, **description_text default = lorem — write it**, title_size default h3, position default "block-start", text_align no-default, icon_space default 15, primary_color/hover_primary_color.
- icon-list: repeater icon_list[{_id,text,selected_icon,link}] (default 3 "List Item" rows — replace wholesale), view "traditional"|"inline", icon_size default 14.
- google_maps: **address default "London Eye, London"!**, zoom default 10, height responsive.
- nested-accordion: DUAL structure — settings.items[{_id,item_title}] index-paired with elements[] (Nth child container = Nth body, content_width:"full" each). title_tag default "div" (set h3), default_state default "expanded" (first OPEN — set "all_collapsed"), key spelled max_items_expended, faq_schema default "no".

## Trap list (top items, all source-verified)
1. grid rows omitted → repeat(2,1fr). 2. mobile grid auto-1col, tablet inherits desktop. 3. row containers grow widgets (counter: _element_width initial + custom width, or _flex_size none). 4. omitted flex_direction = column. 5. align_items omitted = stretch; text-editor align must be explicit. 6. content_width omitted = boxed (+inner wrapper). 7. default padding 10px / gap 20px. 8. mobile auto-stacks children (width 100% + wrap). 9. grid content-alignment dead with fr units. 10. button hover bg key. 11. lorem/placeholder/London defaults ship if keys omitted. 12. partial value arrays = broken CSS. 13. conditional-dead keys (_flex_grow, _element_custom_width, flex_align_content, container link, object-fit). 14. overlay opacity 0.5 default. 15. never write _is_row/_is_column. 16. accordion expanded-by-default + index-sync. 17. image alt branching. 18. responsive 'default' = desktop only. 19. e_optimized_markup removes .elementor-widget-container (don't target it). 20. heading hover only styles links.

## Golden snippets
3-col grid (responsive 3→2→1, auto rows): container_type grid, content_width boxed+boxed_width 1240, grid_columns_grid {fr,3,sizes:[]} + _tablet {fr,2} + _mobile {fr,1}, grid_rows_grid {"unit":"custom","size":"auto","sizes":[]}, grid_auto_flow row, grid_gaps {px,24,"24","24",isLinked}, padding full-shape; each card = isInner flex column container (flex_align_items flex-start, gap 12, padding 24, bg classic #FFF, radius 8) holding icon-box (text_align left).
Hero bg+overlay: flex column center, min_height vh, background classic image {url,id,size:""} cover center, overlay classic color + EXPLICIT overlay_opacity; white H1 center + text-editor + buttons w/ full hover set.
58/38 photo+text split stacking mobile: outer flex row + flex_direction_mobile column + flex_wrap wrap + gap %4; children isInner content_width full + width {%,58}/{%, 38} + width_mobile {%,100}, inner flex column flex-start.

Supersedes the 1fr-rows advice in [[feedback-elementor-grid-for-icon-box-rows]] (1fr works for fixed-count equal-height grids; custom:auto is the general fix). cc-assistant builder fixed v0.42.2+.
