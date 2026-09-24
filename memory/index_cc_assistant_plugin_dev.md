---
name: index_cc_assistant_plugin_dev
description: cc-assistant plugin development index — pointers to 56 memory files; open the ones the hook names
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4f71213-6189-4708-920d-3ea9eed152af
  modified: 2026-09-06T07:46:23.034Z
---

# cc-assistant plugin development index

One line per memory, moved verbatim from MEMORY.md on 2026-09-06 to keep the index under its read limit. Open the file for the full rule.

- [WP plugin context](project_wp_plugin.md) - MCP-first, multi-site
- [Page-builder roadmap](project_cc_assistant_page_builder_roadmap.md) - P0 clone_sections
- [Desktop is OneDrive-redirected](reference_desktop_is_onedrive_redirected.md) - reports to D:\
- [Local PHP path](reference_local_php.md) - PHP 8.2.29 syntax checks
- [Google OAuth on Local](reference_oauth_local_dev.md) - .local rejected; tunnel URI
- [Plugin performance](feedback_plugin_performance.md) - off FE path; cron
- [Automation boundary](feedback_automation_boundary.md) - never auto-queue pending
- [Advisor-mode UI](feedback_advisor_mode_ui.md) - headline+narrative+CTA
- [WP zips need forward slashes](reference_zip_packaging_gotcha.md) - Python zipfile
- [WP-Delete on dup folder wipes data](reference_uninstall_on_duplicate_delete.md)
- [Lint & audit quirks (ROLLUP)](reference_cc_assistant_lint_audit_quirks.md) - check FIRST on odd lint/audit
- [Diag mirrors runtime checks](feedback_diag_must_mirror_runtime_check.md)
- [Build the plugin zip yourself](feedback_zip_plugin_yourself.md)
- [cc-assistant SEO alignment plan](project_cc_assistant_seo_alignment.md)
- [PHP 8.1+ ternary-by-ref fatal](reference_php_81_ternary_by_ref_fatal.md)
- [3-pillar robustness audit](project_cc_assistant_robustness_audit.md)
- [Redirect reverse-loop trap](reference_redirect_reverse_loop_guard.md)
- [Plugin edits need zip deploy](reference_plugin_dev_vs_remote_deploy.md)
- [Schema generator bugs](reference_cc_assistant_schema_generator_bugs.md) - audit managed_schema
- [Ubersuggest official MCP](reference_ubersuggest_mcp.md) - sibling, don't proxy
- [Attention-flow system v0.55](project_cc_assistant_attention_system.md) - spec BEFORE, audit AFTER
- [Close-loop v0.58](project_cc_assistant_close_loop_v058.md) - CC_MCP_VERSION sync
- [v0.59 Lean Core](project_cc_assistant_v059_lean_core.md) - run tests/ before releases
- [v0.60.1 audit hardening](project_cc_assistant_v0601_audit.md) - ROTATE 7 app passwords
- [UX audit + fix plan](project_cc_assistant_ux_audit.md) - v0.61 to v0.63
- [v0.64 Reports + CSV](project_cc_assistant_reports_v064.md) - 4 measurement bugs open
- [v0.67 asset references](reference_cc_assistant_v067_asset_references.md) - recompute length prefixes
- [No per-plugin adapters](feedback_no_per_plugin_adapters.md)
- [Sibling-change drift guards](reference_sibling_change_drift_guards.md) - semantic guard, not hash
- [CSSOM walker nesting trap](reference_cssom_walker_nesting_trap.md)
- [v0.68 Render Health Guard](project_cc_assistant_render_health_guard.md) - P2-P4 pending
- [Cron-context bootstrap gap](reference_cron_context_bootstrap_gap.md) - DOING_CRON block
- [Apply "false" on empty meta clear](reference_apply_empty_meta_noop_false.md) - no-op
- [Focus Global HRM wireframes](project_focus_global_hrm_wireframes.md) - rev2 71 screens
- [URL resolution ignores redirects](reference_url_resolution_ignores_redirects.md) - PLUGIN DEFECT
- [ONNX int8 slower without VNNI](reference_onnx_int8_slower_without_vnni.md) - benchmark first
- [Serialized meta can't be written as string](reference_serialized_meta_cannot_be_written_as_string.md) - noindex no-ops
- [Email-platform build (Mailcow)](project_email_platform.md) - D:\email-platform; BLOCKED: no VPS/domains; scripts untested
- [site_status tool](reference_site_status_tool.md) - v0.72.0 one-call site synthesis; bin/ tool = MCP restart not zip
- [page_facts ground-truth store](reference_page_facts_ground_truth_store.md) - v0.75.0 rendered facts + freshness + after-apply verification; read before any presence/absence claim
- [Bash heredoc + pipe gating traps](feedback_bash_heredoc_and_pipe_gating.md) - one heredoc per line; never pipe a gating command; long scripts go in files
- [accessibility_audit reads stale source](reference_accessibility_audit_reads_stale_source.md) - PLUGIN DEFECT; confirm link/alt findings with page_facts; outline not doc-order
- [WCAG axe sweep toolchain](reference_wcag_axe_sweep_toolchain.md) - scroll before contrast; Elementor ARIA = vendor; empty citation anchors pattern
- [UI screenshot harness (Playwright)](reference_ui_screenshot_harness.md) - SEE the UI before/after; Temp\ui-shots + repo tools/screenshot.mjs
- [Pending supersede is silent](reference_pending_supersede_is_silent.md) - 2nd content pending HIDES the 1st; fixed v0.76.2
- [SEO setting change stales content pendings](reference_seo_setting_change_stales_content_pendings.md) - fingerprint includes rank-math-options-titles; queue plugin settings BEFORE drafting content; 0.90.0 made it component-based so unrelated plugin auto-updates no longer stale a queue
- [v0.76.5 P0 fixes](reference_cc_assistant_v0763_p0_fixes.md) - isolate_main_content read related-post CARDS; class_exists silent-skip; verified site scores
- [links_summary excludes taxonomy nodes](reference_links_summary_excludes_taxonomy_nodes.md) - categories look orphaned but are not; read the DOM
- [E-E-A-T scorer byline mechanics](reference_eeat_scorer_byline_mechanics.md) - colon bug + org-reviewer credit v0.76.12
- [cf-ai-assistant extension](project_cf_ai_assistant.md) - VS Code agent on Workers AI; tsc+smoke only, no vsce
- [replace_asset_reference traps](reference_replace_asset_reference_variant_trap.md) - trailing slash matches everything; hard 300 cap
- [Word .docx toolchain](reference_docx_toolchain.md) — python-docx + Word COM + pymupdf; no pandoc/LibreOffice
- [Slack file download via Playwright](reference_slack_file_download_playwright.md) - MCP returns pixels not bytes; scoped profile + app.slack.com origin
- [Google web app headless login](reference_google_webapp_headless_login.md) - cookies alone render logged out; __Host- via url-only; verify by avatar not URL
- [v0.78 delete_media tool](reference_cc_assistant_delete_media.md) - only irreversible tool; guards incl. pending-changes check; MCP restart
- [delete_media stem-prefix false positive](reference_delete_media_stem_prefix_false_positive.md) - "-v2" successor makes the original look still-referenced; blocks all supersede cleanup
- [Operator Brain v0.79 + guards v0.80/v0.81](project_cc_assistant_operator_brain.md) - sites store memory+skills+bridge+hooks; whoami verdict + relevant_rules + recent_applied; HARD session gate; open-loop guard; cc_gate.py hooks (settings.hooks.json awaits rename); D:\cc-assistant-0.81.0.zip awaiting deploy
- [Elementor import validator traps (IWC 0.89.5)](reference_elementor_import_validator_traps.md) - webhook keys flagged inert, no _mobile container keys, dry_run skips validation; bisect with real probes + reject
- [Elementor Webhook + Apps Script never succeeds](reference_elementor_webhook_apps_script_incompatible.md) - 302 then WP GET-with-body = 400; script still runs; fix = mu-plugin posting on new_record and ignoring reply
- [Hero preload ignores a separate mobile hero](project_cc_hero_preload_mobile_bg_bug.md) - class-hero-preload preloads desktop image on phones when mobile bg differs; IWC snippet works around it; fix in plugin then drop workaround
- [Prerender vs Flying Scripts tracking guard](reference_prerender_tracking_guard.md) - prerender fires delayed tags on unseen pages (37 reqs); pause loadScriptsTimer while document.prerendering; jQuery defer tested and rejected
- [ER sites performance snippets](project_er_sites_performance_snippets.md) - Irving ekit icon subset (new icons go blank), Lufkin CLS image 6458, WR/Lufkin custom code in parent theme, Lufkin GTM not held back, preload-before-viewport test trap
- [Queue refusals when importing Elementor trees](reference_cc_queue_elementor_schema_gates.md) - device-suffix + hide_* keys blocked (use custom_css), dead button hover key, start/end not left/right, whoami-10min + slim=false evidence gates
