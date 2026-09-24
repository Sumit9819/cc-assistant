---
name: reference-cc-assistant-v0-31-audit-gaps
description: "v0.31 closes the audit + clone gaps that let the chest-pain page ship with Irving brand colors, banned wellness vocabulary, foreign JSON-LD schema, and a half-rendered hero — see body for the new tools and what each gap was"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

cc-assistant v0.31.0 — shipped 2026-05-20. Built after the [[project-erofwhiterock-cloned-from-erofirving]] chest-pain page (post 2516) rebuild repeatedly missed audit gaps that should have been catchable automatically.

**Why:** The pre-v0.31 `audit_page_design` returned a passing score on pages that still had Irving green `#003017`, "Weekend Hangovers" content, `irvingwellnessclinic.com` JSON-LD @id, and a half-broken hero — because the audit only checked hex colors in widget settings (not inline `style=` HTML or rgb()), only system-token globals (not custom ids resolved through Kit), and had no concept of industry-specific banned vocabulary or cross-domain schema.

**How to apply:**

After ANY `build_service_page` / `import_elementor_data` on a cloned page, the AI must:

1. Call `audit_page_design(post_id)` — 13 checks now, not 8. Score the verdict at 80+ pass / 60-79 warn / <60 fail.
2. Call `list_sections(post_id)` — returns per-section `has_banned_phrases`, `has_foreign_schema`, `cross_domain_image_count`, `content_fingerprint`. Sections still holding source-site content surface as flagged rows.
3. For each flagged section, call `replace_section_content(post_id, section_id, widget_updates=[{widget_id, settings}, ...])` — ONE pending change per section instead of N separate widget updates.

New audit checks (v0.31):

- **wcag_contrast** — now resolves rgb()/rgba() + inline `<p style="color:#xx">` + `__globals__` references through Kit tokens. The Irving green that survived prior audits hid in editor HTML; this catches it.
- **heading_size_hierarchy** — fails if H2's computed px ≥ H1's, etc. Mismatch was visible on every cloned page.
- **industry_vocabulary** — flags banned_phrases() per detected industry. Healthcare bans "infusion therapy", "Myers cocktail", "weekend hangover", "Glutathione" etc. Legal/finance/saas have their own banks. See [[reference-cc-assistant-v0-28-industry-playbook]] for industry overlay.
- **cross_domain_images** — image URLs on sister-site CDN.
- **schema_cross_domain** — JSON-LD @id / url / sameAs pointing outside `home_url()`.
- **map_address_geo** — empty / stale google_maps.address.
- **empty_containers**.
- **section_uniqueness** — now compares RESOLVED bg colors (through globals), so all-white-sections monotony surfaces.

New import options (`import_elementor_data`):

- `sort_replacements_by_length: true` (default) — fixes the "White Rockwellnessclinic" substring-overlap bug.
- `color_remap: { "#003017": "#11468F", ... }` — explicit source-hex → target-hex rewrites applied to widget color settings AND inline editor HTML (`replace_in_inline_styles=true`).
- `strip_cross_domain_schema_html: true` (default) — drops html/text-editor widgets whose JSON-LD entities link to a foreign domain.

Related: [[reference-per-site-design-skills]], [[feedback-curl-test-url-before-editing]], [[feedback-erofwhiterock-no-provider-bylines-yet]].
