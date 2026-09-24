---
name: cc-assistant-ux-audit
description: "Full admin UX audit (2026-07-30) — 14-page IA sprawl, broken reject-reason, fabricated calendar dates, CSS collisions; 6-item target IA + quick wins; blueprint for the UX release"
metadata: 
  node_type: memory
  type: project
  originSessionId: 598fc17d-01d9-403a-b093-67c7c45a8e9b
  modified: 2026-07-31T05:34:21.033Z
---

UX audit of all admin screens (agent read every view + admin.css 4152 lines + 2 pages registered outside class-admin). Verdict: decision-engine screens good (onboarding A-, dashboard B, editor-sidebar B+); wrapper is the mess. Operator complaint accurate; fix = consolidation more than redesign.

**Top defects (file:line evidence):**
1. IA sprawl: 14 menu pages, registration in 3 files (class-admin.php:25-144 pri10, class-performance-tracker.php:66-81 pri15, class-reviews.php:201-215 pri20 — Reviews/Page Performance render BELOW Settings).
2. pending.php reject-reason DEAD: handler reads cc_pending_note (:20) but NO such input in form (:1587-1605); single Reject has no confirm (Rollback does :1595). AI never learns why rejected.
3. Every decision = full-page POST reload; state (tab/expansion/scroll) lost; 37-item batch = 37 reloads. REST endpoints exist (/pending/{id}/diff, /preview) — fetch + remove-in-place.
4. calendar.php FABRICATES dates: refresh suggestions at crc32(post_id)%last_day (:50-57) rendered as real green calendar entries; footnote (:159) claims priority-based. Violates precise-figures doctrine. Fix: ranked "Refresh next" list below grid, no dates.
5. CSS collisions: .cc-rollup-stat big-stat (admin.css:3249) vs pill (:3727 — later wins → Check up tiles squashed); .cc-failed-line (:417 vs :2658); .cc-tag (:673 vs :2056); codified cc-lint-cc-lint-pass (:3426, PHP bug pending.php:500).
6. Machine slugs shown to humans: pending.php:1486 raw change_type while cc_change_type_label() exists (:952-974); reindex-tracker:233; checkup:299.
7. No mobile for tables: performance 10-col (:268), calendar grid (css:3302), reindex 6-col, checkup — no overflow wrappers; body scrolls horizontally @390px. Fix: shared .cc-table-scroll + card-ify checkup/performance <600px.
8. Same question on 4 screens w/ different thresholds (decay: dashboard/page-perf/checkup/calendar; bots: dashboard+llm-activity; health: settings-health+db-health+dashboard-cron). One canonical surface per question.
9. Approve not risk-aware: hard-violation items get identical approve button (:1592); bulk confirm (:2037) omits violation counts. Fix: danger-style approve + counts in confirm.
10. 5 clipboard impls, 3 confirm mechanisms; reindex-tracker = snowflake (40 inline styles, no i18n, no .cc-card). One admin/js/cc-admin.js w/ ccCopy/ccConfirm.
Honorable: checkup runs 25 sync check_post() per render (:29-38); fails-only filter after pagination = lying counts (:34,:217); pending 5s polling forever (:1795); inbox 100-row cap w/o indicator; clusters 3×500-option selects/cluster (:60-66) + N+1 cluster_health (:174); db-health shows raw SQL/WP-CLI to non-tech operator (:283,:267); page-perf alien palette #003017 (css:4034-4141).

**Target IA (6 items):** Home (merge 3 outcome cards into one) | Changes (inbox + absorb Reindex Tracker as "Needs re-index" filter on Applied tab) | Performance (page-perf + checkup + AI bots as 3 tabs) | Planning (clusters + calendar + brief) | Snapshots | Settings (absorb db-health→Health tab, Reviews→Integrations tab). 14→6, nothing lost.

**Quick wins (ship first):** (1) pending.php:1486 use cc_change_type_label — one line; (2) ~20 CSS lines fix collisions + cc-lint- doubling; (3) ~15 lines wire reject-reason into existing ccConfirm modal; (4) ~10 lines menu consolidation (move 2 registrations, Settings last, db-health redirect); (5) calendar honesty — refresh list replaces fabricated cell dates.

**v0.61.0 SHIPPED (2026-07-30, zip 104 files):** human change-type labels (slug→tooltip); reject-with-reason WIRED (textarea in ccConfirm modal → cc_pending_note; reject now type=button routed through modal); risk-aware Approve (amber .cc-approve-risk + title when lint_report contains "hard_violations":[" — data-hard attr on rows); bulk-approve confirm names hard-violation count; cc-lint- doubling fixed BOTH sides (pending.php:500 + css 3426-3428); CSS collisions resolved (pill → .cc-rollup-pill, trend tag → .cc-trend-tag w/ dashboard.php usages updated, dead .cc-failed-line chip block deleted); calendar fabricated dates REMOVED (ranked "Refresh next" cc-card list below grid; .cc-cal-refresh CSS now unused — clean up in v0.62); Settings registers at admin_menu priority 30 = last; .cc-table-scroll shared wrapper (applied: page-performance main table, checkup table); editor-sidebar em dash fixed. All views lint clean, suite green, bridge 0.61.0.

**v0.62.0 SHIPPED (2026-07-30):** lint entity-decode at top of lint_html_block (closes &#8212; bypass for ALL checks); paragraph_length pre-existing downgrade — new optional $current_html param, violations keyed by snippet md5, widget-update path (class-rest-api ~2678) threads current_settings text; suite green; polling 5s→30s. Body-update path (lint_post_content_change) NOT yet threaded with current — check if same trap applies there. suspicious_chars Spanish FP was ALREADY fixed v0.20.2 — reference_suspicious_chars_lint_bug memory is STALE. Zip = v0.54-0.62, DEPLOY PENDING everywhere.

**v0.63.0 SHIPPED (2026-07-30):** Review Deck core — POST /pending/{id}/decide JSON endpoint (class-rest-pending.php, reuses apply_pending/reject internals) + fetch-based approve/reject in pending.php (card fades in place, badge decrement, error shows + card stays, network fallback = classic POST). IA 14→6: hidden via parent null (house onboarding pattern): checkup, reindex, calendar, brief, llm(cc-assistant-llm), db-health (class-admin) + reviews (class-reviews:208); tab navs added on page-performance/checkup/llm-activity (Rankings|Quality check|AI bots → slug cc-assistant-performance) and clusters/calendar/brief (Clusters|Calendar|Brief); pending h1 gains Reindex tracker page-title-action; Settings>Health gains db-console + Reviews buttons. CAUTION LEARNED: a lazy-regex re-parent hit the WRONG five pages first (Dashboard/Pending/Clusters/Checkup/Snapshots) — caught by grep verify, fixed by line-targeted sed; always verify menu edits by grepping parents after. NOT visually verified (site down) — first admin load is acceptance. Zip = v0.54-0.63 FINAL DEPLOY ARTIFACT.

**REMAINING (v0.64+): "Consolidation leftovers"** = 6-item IA (Home/Changes+reindex/Performance 3-tab/Planning/Snapshots/Settings+dbhealth+reviews), shared admin/js/cc-admin.js (ccCopy/ccConfirm — 5 clipboard impls today), reindex-tracker port onto design system, checkup sync-25-post fix (cron+cache) + pagination-count lie, clusters 500-option selects → search input, db-health de-DBA, dashboard 3-outcome-card merge, page-perf alien palette → tokens, inbox 100-row cap indicator, 5s polling backoff. **v0.63 "Review Deck"** = fetch-based decisions (REST /pending/{id}/diff + /preview exist), checks row, preview-first cards.
