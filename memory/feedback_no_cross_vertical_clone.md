---
name: no-cross-vertical-clone
description: Never clone a service-page template from a site in a different service vertical. Build native instead.
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

When the source site and target site serve different verticals (e.g., irvingwellnessclinic.com = IV therapy / aesthetics → erofwhiterock.com = freestanding emergency room), do NOT clone via `export_elementor_data` + `import_elementor_data`. Build the target page from scratch using `draft_add_elementor_container` / `draft_add_elementor_widget` one section at a time.

**Why:** Tried cloning the Irving Wellness IV-therapy pillar into the ER of White Rock chest-pain page (post 2516) across 5+ iterations. Each pass left residue: trust-strip vocabulary ("Physician Supervised / Sterile Clinical Environment"), FAQ accordions ("can I get an IV drip"), Google Maps with the Irving address, `application/ld+json` blocks with `irvingwellnessclinic.com` @id, button hover hex `#003017` / `#FFD900`, hide-on-mobile flags optimized for a wellness landing page. The `replacements` map only catches text fields, not setting names / icons / structural assumptions. The v0.31 audit catches the residue after the fact, but the cleaner play is to never inherit it.

**How to apply:**

- Within-vertical clones still OK: ER of Irving → ER of White Rock (both freestanding ER) is fine via `export_elementor_data` + `import_elementor_data` with `color_remap` for Kit-color hex swaps.
- Across verticals: REFUSE the clone. Open `get_page_style_context` for the target site, build native via `draft_add_elementor_container` (one container per section, one pending per section), reference the per-site design skill at `~/.claude/skills/{site}-design/SKILL.md` for component patterns.
- If user asks "can we clone X from Y?", first check: does Y's industry match target's industry? If not, propose native build instead and explain the residue problem.

Related: [[purge-source-vertical-on-clone]], [[project-erofwhiterock-cloned-from-erofirving]], [[feedback-layout-change-approval]].
