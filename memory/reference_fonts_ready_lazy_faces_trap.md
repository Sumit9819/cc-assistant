---
name: reference_fonts_ready_lazy_faces_trap
description: document.fonts.ready resolves before lazily-loaded @font-face faces on an empty page; text measured in setup() used the fallback font. Force every face with FontFace.load() first.
metadata: 
  node_type: memory
  type: reference
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-06T05:24:19.873Z
---

In headless Chromium (Playwright) a template that declares @font-face and
awaits `document.fonts.ready` BEFORE any text uses the faces gets an
immediate resolve: faces load lazily, only once text needs them. Any
`getBoundingClientRect` fit done in `setup()` then measures the FALLBACK
face; the real font arrives wider and rows fold or overflow the zone.

Fix used in D:\faceless-studio\motion\render.mjs and snap.mjs (2026-09-06):
`await page.evaluate(() => Promise.all([...document.fonts].map(f => f.load())).then(() => document.fonts.ready))`.

**Why:** the zoned keyword layer of 2026-09-05 had passed QA only because
its sizes had margin; a phrase row with a 1.6x word exposed it.

**How to apply:** any Playwright/HTML render that measures text must load
the faces explicitly first. Related: [[feedback_measured_layout_for_variable_text]],
[[reference_kinetic_text_and_tension_measurements]].
