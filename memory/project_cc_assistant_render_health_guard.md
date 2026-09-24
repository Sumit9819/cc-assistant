---
name: project_cc_assistant_render_health_guard
description: "v0.68 Render Health Guard verifies the OUTCOME of applies, not the write; lint splits introduced vs pre-existing"
metadata: 
  node_type: memory
  type: project
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-03T13:34:10.862Z
---

cc-assistant v0.68 (built 2026-08-03, after the carousel incident). Two pieces:

**P0 — Render Health Guard** (`includes/class-render-health.php`):
- `queue()` in class-pending-changes.php captures a rendered-page baseline for EVERY
  post-scoped change type (one central wiring point; transient-cached 120s so batches
  fetch once). Stored as a `render` key piggybacked on `pre_check_baseline` — the cosine
  verifier tolerates it (only reads `post_ids`).
- The existing post-apply audit cron (30s after apply) re-fetches CACHE-BUSTED (the old
  plain GET could be served stale pre-change HTML by SG's path-keyed cache) and compares:
  empty-state strings appearing, element populations vanishing, shortcode leakage, PHP
  errors, HTTP change, text shrink. CRITICAL findings hit activity log + pending row
  `verification_result.render_health` + dashboard banner. REPORTS only, never auto-reverts.
- Two hard-won rules encoded: compare DELTAS never absolute presence (localized empty-state
  strings legitimately live in script bundles), and count MARKUP not substrings
  (`count_elements` requires the token inside a class attribute — CSS selectors can't
  masquerade as rendered elements). Byte-identical body after a mutation reports
  `possibly_stale_fetch` instead of a vacuous pass.

**P1 — lint attribution** (class-pre-publish.php `classify_lint_failures`):
- Every failure classified introduced-by-this-change vs pre-existing-in-current-body.
  Additive-scoped checks (em_dashes etc. on rewrites, quote checks) and diff checks are
  introduced BY CONSTRUCTION even if the current body also fails them.
- Pre-existing STRUCTURAL failures no longer escalate to hard blocks (fixes the documented
  trap where a stored wall_of_text hard-blocked every later edit to that post).
- Queue responses now carry `lint.introduced` / `lint.pre_existing`.

**Known limits:** asset_reference_replace has post_id NULL → no render baseline for
multi-post swaps. P2 (DOM counts in render_probe), P3 (attribute-effect verification),
P4 (blocking preflight) not built yet. Like v0.67, first live run after deploy is the
smoke test — unit-tested (28 assertions incl. the incident fixture) but never executed
against a running WordPress at build time.

See [[feedback_no_guessing_epistemic_discipline]], [[reference_sibling_change_drift_guards]].
