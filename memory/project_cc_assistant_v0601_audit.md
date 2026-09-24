---
name: cc-assistant-v0601-audit-hardening
description: "v0.60.1 SHIPPED — full adversarial+security audit of v0.54-0.60 (2 agents), 22 findings ALL fixed; TLS verify default ON, uninstall guarded, propose_revert actually works now"
metadata: 
  node_type: memory
  type: project
  originSessionId: 598fc17d-01d9-403a-b093-67c7c45a8e9b
  modified: 2026-07-31T05:48:22.062Z
---

v0.60.1 (2026-07-30, zip 104 files, 140 tools, all 5 test suites green). Two background audit agents reviewed everything v0.54-0.60 + full security/perf/standards sweep. 22 confirmed findings, ALL fixed:

CRITICAL: (1) propose_revert selected `type` (real column: snapshot_type) — EVERY revert 422'd since v0.58; also added same-second bulk-approve rank disambiguation. (2) Bridge TLS verify-off default carried admin app passwords unverified to 7 production sites → verify ON by default via CURLSSLOPT_NATIVE_CA (Windows cert store, empirically green vs erofirving); .local/.test/localhost exempt; CC_MCP_VERIFY_TLS=0/1 overrides. **User should still ROTATE all 7 app passwords** (transmitted unverified for months). (3) uninstall.php now needs cc_assistant_delete_data_on_uninstall opt-in + folder-name check.

MAJOR fixed: probe_diff rewritten shape-aware (facets are KEYED reports; old diff wrong/bloated; multiset list deltas); rollback-of-applied-revert path (pre_restore id written into pending current_value at apply; new rollback case); commodity REVIEW verdict on unresolvable URLs (never blind STOP); leads post_id-0 surfacing + home_url referer retry; win_audit internal_support queried nonexistent cc_internal_links (→ cc_link_graph; dimension dead since v0.41); 6 FE options never seeded (notoptions miss per pageview → activator seeds); admin blobs autoloaded (site_notes etc → autoload no + daily flip); win-audit transient buildup (daily expired cc_* sweep in storage maintenance).

MINOR fixed: endpoint-aware transport-404 messages; info_gain unslash for Elementor JSON (escaped hrefs); snapshot_restore in elementor cache-regen list; retry stops on definitive status; outcome page matching = exact URL permutations (kills Polylang same-slug pooling — cc_wh_page_url_variants); mkdir 0700; lead DDL off hot path (activator dbDelta + insert-fail self-heal); Throwable in JSON-RPC main loop; i18n before-init literal; stale CC_MCP_INSECURE doc; Clarity consent/masking note in settings.

Verified clean by auditors: all 144 REST routes manage_options-gated, zero __return_true; tokens AES-256-GCM at rest; SSRF guard solid; new-route input validation solid; llm IP hashing real; PHP 8.0 compat.

DEPLOY STATE 2026-07-31: v0.63.0 VERIFIED live on erofirving, eroflufkin, erofwhiterock, IWC (GSC data through 07-29). NOT deployed: sids-ponds, mammothmachinery, jayard39 (rest_no_route). Passwords NOT yet rotated (old creds still authenticate). First production runs: outcome_report IWC = 15× too_early (About batch applied 07-30/31 — honest; real verdicts ~Aug 7; older judgeable edits need limit>40). commodity_audit erofirving = 1 INVEST / 6 CITE_PLAY / 2 BRIDGE / 0 STOP / 6 REVIEW — REVIEW guard fired exactly as designed on Custom-Permalinks /blog/ URLs incl the 120k-imp dehydration guide; absorption thesis confirmed page-by-page (hairline 57k imp 0.42% vs 2.5% expected). v0.64 improvement noted: strip #fragments before url_to_postid in /commodity/signals (GSC reports anchor URLs; two fragment rows hit REVIEW), and consider a slug-lookup fallback for Custom Permalinks sites.

STILL OPEN (deliberate, not forgotten): rotate 7 app passwords (user action); least-privilege WP role instead of admin app passwords; old lint FP backlog (paragraph_length blocker, entity decode, Spanish chars); empirical per-site CTR curve to replace generic cc_expected_ctr; server-side envelope standardization; Review Deck; v0.61 Fleet+Links; GBP reviews tooling. Tests: suite is now 5 files / 110+ checks but still stubs the WP side — the WP-side gap is where all critical bugs lived; consider a wp-env integration harness someday.
