---
name: reference-eeat-scorer-byline-mechanics
description: "How helpful_content_score actually detects a byline and schema entity, incl. the colon bug and the org-reviewer credit added in v0.76.12"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-28T07:12:50.542Z
---

`helpful_content_score` E-E-A-T is 25 points: visible byline **+10**, schema Person **+5**, sameAs **+3**, LinkedIn sameAs **+4**, hasCredential **+3**. Verify against `includes/class-seo-tools.php` before predicting a score change.

**A team byline counts.** The second regex accepts `reviewed by / medically reviewed by / written by / revisado por / escrito por` + optional `the|el|la` + up to 80 chars + the word `team|equipo|staff`. So "Medically reviewed by the ER of Lufkin clinical team" scores the full +10 with **no named individual** — this is the honest route on sites where clinician consent is unavailable.

**THE COLON BUG (fixed v0.76.12).** Before the fix the pattern required whitespace immediately after the by-phrase, so **"Reviewed by: the ER of Lufkin clinical team" scored ZERO** while the identical line without the colon scored +10. eroflufkin's own editorial-policy page uses the colon form, so copying the site's sanctioned wording onto a service page would have earned nothing. Fixed by changing `)\s+(?:the\s+...` to `)[\s:]+(?:the\s+...` in BOTH copies (eeat_coverage_audit ~line 1998 and helpful_content_score ~line 2715). Always test candidate byline strings against the shipped regex with real PHP before queueing a rollout.

**ORG REVIEWER (added v0.76.12).** `eeat_scan_jsonld_node` previously credited `@type: Person` ONLY, so a legitimate `reviewedBy: {"@type":"Organization"}` earned nothing and an org-reviewed page measured as having no accountability signal at all. Now `reviewedBy` / `reviewer` pointing at an Organization-like type (Organization, MedicalOrganization, Hospital, EmergencyService, MedicalClinic, MedicalBusiness, Corporation, LocalBusiness) sets `has_org_reviewer` and earns **+5**, the same base as a Person, but **never** the sameAs / LinkedIn / credential bonuses. So an org-reviewed page caps near **15/25**, exactly the ceiling the operator accepted on 2026-08-28 when choosing organisation-level review.

**The critical guard:** `publisher: {"@type":"Organization"}` must NEVER count — Rank Math emits it on essentially every page, so crediting it would silently hand every page on every site 5 unearned points. Only `reviewedBy`/`reviewer` count. Test `B6` in `tests/eeat-org-reviewer-test.php` locks this in.

**Running the test suite:** `php tests/*.php`. Three suites (commodity, outcome, warehouse) fail on a bare Local PHP binary because sqlite3 cannot load (its extension dir points at a nonexistent `C:\php\ext`) — that is environmental, NOT a regression. 21 of 24 pass.

Related: [[reference_cc_assistant_lint_audit_quirks]], [[feedback_no_guessing_epistemic_discipline]]
