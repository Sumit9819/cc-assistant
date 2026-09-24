---
name: feedback-guards-must-check-content-not-geometry
description: "Build guards that measure layout cannot see wrong text; add a rendered-text and spec-shape check, and look at the output"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 9c6f1bb7-e1aa-47b7-a180-e9cd066c7b98
  modified: 2026-09-04T11:40:12.833Z
---

A generator's guards must check **what the output says**, not only how it is arranged. On irvingwellnessclinic's card generator (2026-09-04) every guard measured geometry — frame overflow, logo keep-out, duplicate labels, title fit — and all of them passed a card whose headline read the literal word **"undefined"**. The layout was valid; only the text was wrong. Twenty of those reached live posts. The same blind spot hid British spellings on 51 cards ("Litres of sweat" on a Texas-heat card) and a highlight that split a word ("Hormone" in yellow with a dark "s").

**Why:** geometry guards answer "does it fit", never "is it right". A stringified `undefined`, a wrong-locale spelling, and a mid-word highlight all fit perfectly. Spot-checking a few renders does not close the gap either — sampling 2-3 per batch missed a defect present in 25 cards, because the sample happened to draw only well-formed ones.

**How to apply:**
1. Read the **rendered text** after paint and refuse `undefined`, `null`, `NaN`, `[object Object]`. These mean a value stringified into the canvas.
2. Validate the **spec shape** separately: array arity (a 3-part title must have 3 parts), non-empty highlight, alt text present, table rows supplying one value per column, no empty cells or labels.
3. Keep a **locale word list** for baked-in copy and run it before upload. Write it as a full list, not a few stems — a stem list missed "acclimatised", then "Programme", each near-miss only proving the list was too short. Include an allowlist for correct-US lookalikes (Specialist, Realistic, phytoestrogens).
4. **Prove each new guard fires** against a deliberately broken input before trusting it.
5. **Look at every generated image before shipping it**, not a sample — the same discipline as [[feedback-look-at-every-shot-before-delivering]] for video. Copy baked into a bitmap cannot be fixed by any later text edit; it costs a re-render, a re-upload and a body swap.
6. Before claiming a rebuild happened, compare artifact mtime to source mtime — see [[feedback-confirm-change-took-effect]].
