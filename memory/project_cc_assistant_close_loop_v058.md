---
name: cc-assistant-close-loop-v058
description: "v0.58.0 SHIPPED — propose_revert (snapshot_restore change type), lead-event logging + leads in outcome_report, whoami version-drift warning"
metadata: 
  node_type: memory
  type: project
  originSessionId: 598fc17d-01d9-403a-b093-67c7c45a8e9b
  modified: 2026-07-31T03:18:41.820Z
---

v0.58.0 "Close the Loop" (built 2026-07-30, zip carries v0.54-0.58, deploy pending on ALL sites):

- `propose_revert(edit_id, force?, reasoning?)` → POST /revert/propose (includes/class-rest-close-loop.php): finds the snapshot at-or-before the edit's applied_at, queues change_type `snapshot_restore` (new case in class-apply.php dispatch; apply calls Snapshots::restore_snapshot(id, force=TRUE) — deliberate: the drift the guard would flag IS the edit being reverted, and restore takes its own pre-restore snapshot). 409 newer_edits_exist guard unless force. NOT runtime-tested (site down) — code-traced + linted only; first real revert should be watched.
- Lead events (includes/class-lead-events.php): `elementor_pro/forms/new_record` hook in cc-assistant.php increments (event_date, post_id, form_name) counter in wp_cc_lead_events (self-healing CREATE IF NOT EXISTS; counts only, no PII; post_id via url_to_postid(referer), 0 fallback). GET /leads compact columns/rows; `lead_events` tool. outcome_report folds leads{pre,post} per judged edit (best-effort /leads?days=400 — 404 on pre-0.58 sites silently skipped). Divi contact forms NOT covered (no clean hook) — Elementor Pro only.
- whoami version drift (bridge-side, zero WP changes): CC_MCP_VERSION define in mcp-server.php (keep in sync with plugin version each release!); whoami case appends `version_drift` when site plugin_version < local build or missing.

Tests: extended outcome fixture 16 checks green (incl. leads fold + absence + envelope unwrap), warehouse regression green, protocol 138 tools v0.58.0. Roadmap next: token-efficiency pass (probe diff, result slices, columns/rows shaping, widget_schema section filter — full button schema is 61KB), then Review Deck as its own release.

SHIPPED BUG FOUND LIVE (2026-07-30, fixed same day in bin): outcome_report read $resp['edits'] but /edits (like ALL legacy routes via REST_API::wrap) returns the {site, data:{...}} ENVELOPE → empty reports on real sites. v0.54+ routes I wrote (gsc-export, leads, operator-kit, attention, widget-schema) use rest_ensure_response = NO envelope. RULE: fixture stubs must mirror the real envelope per route; when consuming a legacy route from the bridge, unwrap data first. Also: /edits recent_with_status may window-limit which applied edits appear — erofirving returned zero edits; widen or add a dedicated outcome listing later. Bridge fixes need /mcp reconnect (or VS Code reload) to take effect.

DEPLOY STATE (2026-07-30): v0.58.0 LIVE + verified on erofirving, eroflufkin, erofwhiterock, irvingwellnessclinic (gsc-export/status shows 0.58.0, GSC connected). Skills pushed to all 4 operator kits (via direct REST, browser UA). Warehouses initialized: erofirving 47d/440k rows/166MB (BIG site ~9k rows/day; SiteGround intermittently drops connections under sustained sync — re-call resumes), eroflufkin 30d/153k, erofwhiterock 30d/4.9k, IWC 30d/8.4k. NOT deployed: sids-ponds, mammothmachinery, jayard39 staging, plugintesting (local, site down).
