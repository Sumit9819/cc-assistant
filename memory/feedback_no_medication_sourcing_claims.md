---
name: no-medication-sourcing-claims
description: "Never state drug brand names or sourcing (brand vs compounded, \"FDA-approved\" product claims) on irvingwellnessclinic unless the operator has explicitly confirmed what the clinic dispenses"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 501752a8-8c88-48d5-88ba-e672ff5142f9
---

On irvingwellnessclinic.com (2026-07-16), the GLP-1 sub-service pages I built claimed "FDA-approved, brand-name Wegovy/Zepbound, never compounded copies" in hero, explainer, FAQ, schema, and SEO meta. The operator caught it: "we have talked about it but never mentioned we actually use one of them." Operator ruling: **don't state sourcing at all**.

**Why:** Claiming a specific brand or "never compounded" is a factual claim about clinic inventory only the operator can verify. On a YMYL page a wrong sourcing claim is false advertising. The pillar (610) never named brands; I invented the differentiator.

**How to apply:**
1. Generic drug names (semaglutide, tirzepatide) are fine — pages are titled with them and popups sell them. BRAND names (Wegovy, Zepbound) and sourcing claims (FDA-approved product, brand-name only, never compounded, pharmaceutical-grade) are NOT unless operator confirms in writing.
2. Trial data may cite what the TRIAL used, but strip brand names from comparison-table headers anyway — cleaner and no implication.
3. This class of claim hides in 6 places: hero, explainer, steps cards, FAQ (question AND answer), CTA, schema (name/description/FAQ), SEO title/description/OG. Sweep all of them (curl + grep for the brand tokens).
4. Generalize: for ANY service claim ("physician-supervised", brand names, equipment models), verify against operator-confirmed sources first — same lesson as [[iwc-provider-is-aprn]].

Related: [[cc-assistant-page-builder-roadmap]]
