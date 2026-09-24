---
name: schema-parity-check-false-positives
description: "cc-assistant schema_parity_check over-reports schema_not_in_dom on Elementor flip-box and image-box pages. The strings ARE in rendered HTML; the parity tool's DOM-extraction misses them."
metadata: 
  node_type: memory
  type: reference
  originSessionId: b85a6e61-a8bf-48a2-9b0b-343339376ebe
---

The cc-assistant `schema_parity_check` tool reports `schema_not_in_dom` violations on pages whose Elementor flip-boxes / image-boxes DO contain the schema strings in their rendered HTML markup.

**Evidence (erofirving 2026-05-25):** Tool reported 20 violations on home 228 and 11 violations on 541 Emergency Services hub. Curl-test confirmed the flagged strings are present in static rendered HTML:
- `Trauma & Injuries` appears twice in 541 markup: `<h3 class="elementor-flip-box__layer__title"> Trauma &amp; Injuries</h3>` (front and back sides of the flip-box, both server-rendered).
- Description strings like `Broken bones, sprains, burns, cuts requiring stitches, concussions, and sports injuries.` also present.

**Why the tool fails:** Likely the parity tool uses a DOM-extraction approach that misses content inside elementor-flip-box layers (because they're CSS-hidden until hover/flip), or its 3-word-slice tokenizer doesn't tolerate HTML entity encoding (`&amp;` vs `&`).

**How to apply:**
1. When `schema_parity_check` reports violations on Elementor pages with flip-boxes / image-boxes / nested-tabs, DO NOT immediately add a new visible-text section. First curl-test the rendered HTML for each flagged string.
2. Use `grep -c "<string>" rendered.html` on the curled output. If count > 0, the parity is fine and the audit is a false positive.
3. Only add a new visible-text section for strings truly missing from rendered HTML.
4. Bug report to plugin: parity tool needs to count all-text-in-DOM (including CSS-hidden flip-box layers), not just visible-on-load text, AND needs to normalize HTML entities before slice-match.

Related: [[feedback_schema_parity_no_text_wall]] (the wall-of-text mistake I made AFTER trusting this false-positive audit).
