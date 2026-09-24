---
name: reference_cron_context_bootstrap_gap
description: "cc-assistant registers cron handlers in TWO places; class-init'd cron callbacks were absent in DOING_CRON context — pill/verifier never ran via real cron"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-03T15:40:57.258Z
---

Root cause of the v0.68 "no pill in any state" mystery (found 2026-08-03, fixed v0.68.3).

cc-assistant.php has TWO cron-handler registration sites:
1. The `is_admin()` bootstrap (~L623): closure handlers for the named crons PLUS the
   class `init()` calls (post-apply audit, cosine verifier) whose init attaches cron
   callbacks.
2. A dedicated `DOING_CRON` plugins_loaded block (~L1084) that re-registers the CLOSURE
   handlers only — it was never updated with the class inits.

A wp-cron.php request has `is_admin() === false`, so in real cron context the audit's and
verifier's hooks were never attached: WordPress fired their events and consumed them with
no callback, silently, every pass. **Scheduling always worked** (it happens during admin
approve requests) — execution never did. Consequence: the cosine verification pill and the
render-health verdict were never written by a real cron pass on production; the schema
audit's "safety net" likewise — which is also why its dashboard transient having no reader
went unnoticed for many versions.

**The fix shape (v0.68.3):** add the class inits to the DOING_CRON block. Do NOT instead
widen the admin gate to `is_admin() || wp_doing_cron()` — that double-registers every
CLOSURE handler (closures never dedupe in add_action), doubling gsc_sync / site_audit per
fire. Static-method callables DO dedupe, so double-initing the classes is safe.

**Rules extracted:**
- Any class whose init() attaches a cron callback must be init'd in the DOING_CRON block
  too; the admin-block comment now warns about this.
- Debugging heuristic: "event scheduled but handler never runs, no errors anywhere" →
  check which CONTEXT registered the handler. Forced `wp-cron.php` passes returning fast
  (~1.4s) with no effect are consistent with events being consumed handler-less.
- I initially overclaimed "every plugin cron is dead" before finding registration site #2
  — verify the FULL registration picture before sizing a blast radius.

See [[project_cc_assistant_render_health_guard]], [[feedback_no_guessing_epistemic_discipline]].
