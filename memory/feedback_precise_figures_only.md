---
name: feedback_precise_figures_only
description: "Always use the most precise source figure; never mock/rounded/invented data (Mammoth directive, applies everywhere)"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: bdbe9d51-2098-4917-93a9-eb1883a6d1fc
  modified: 2026-07-28T08:55:20.759Z
---

Operator directive (2026-07-28, during the Mammoth catalog-alignment work): "always go with the precise number and not any mock data or made up information."

**Why:** After the X-Loader capacity audit revealed three conflicting live figures, the operator audited my sourcing. Every spec must trace to a client document; when two client sources conflict (e.g., 3000MT engine: catalog says 74 HP, Mammoth's own hosted spec-sheet PDF says 74.3 HP), the MORE PRECISE figure wins (74.3 stays). Rounding is treated as data loss, not simplification.

**How to apply:**
- Spec precedence: client spec-sheet PDF (most precise) > catalog > existing site copy. Never average, round, or "tidy" figures.
- If NO source states a value (price, country of manufacture, photo): use a placeholder / quote-CTA / flagged pending-note — never fill the gap. (Proven pattern: MTL1000 "Request a Quote for Pricing", "designed and engineered" instead of unconfirmed "built in Canada".)
- When sources conflict, tell the operator which one won and why, in the pending-change reasoning.
- Related: [[feedback_content_quality_and_research]], [[feedback_verify_before_proposing_fix]], [[feedback_check_page_history_before_removing_claims]].
