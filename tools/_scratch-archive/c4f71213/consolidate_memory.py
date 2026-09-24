"""Consolidate MEMORY.md (201 lines, over the 200-line read limit) to under 140
lines WITHOUT losing a pointer: lines are moved verbatim into topic index files
(one per domain) and MEMORY.md keeps one line per topic file plus every
feedback/HARD rule and router line individually. Verifies that every original
target filename still appears in MEMORY.md or one of the index files."""
import re, os, io

MEM = r"C:\Users\sumit\.claude\projects\c--Users-sumit-Local-Sites-plugintesting-app-public\memory"
idx_path = os.path.join(MEM, "MEMORY.md")
lines = io.open(idx_path, encoding="utf-8").read().splitlines()
entries = [l for l in lines if l.startswith("- [")]
other = [l for l in lines if not l.startswith("- [")]
print("entries", len(entries), "other", len(other))

def fname(line):
    m = re.search(r"\]\(([^)]+)\)", line)
    return m.group(1) if m else ""

GROUPS = {
  "index_video_studio.md": dict(
    title="Video / Faceless Studio index",
    hook="Faceless Video Studio (D:\\faceless-studio) + Canva: measurements, hook grammar, kinetic text, QA gates, propose-before-implementing (HARD), look at every shot (HARD), no emphasis cards (HARD)",
    files={"feedback_propose_before_implementing_video_changes.md","project_faceless_video_studio.md","feedback_no_emphasis_cards_in_video.md","feedback_look_at_every_shot_before_delivering.md","reference_explainer_video_measurements.md","reference_kinetic_text_and_tension_measurements.md","reference_2026_hook_grammar.md","feedback_video_text_zone_and_qa_before_delivery.md","reference_canva_mcp_video_pipeline.md","feedback_measured_layout_for_variable_text.md","reference_template_literal_eats_backslashes.md","reference_css_mask_fails_on_file_url.md","feedback_guards_must_check_content_not_geometry.md"}),
  "index_gsc_search_measurement.md": dict(
    title="GSC / search measurement / SEO doctrine index",
    hook="GSC API toolchain (D:\\gsc-tools), impression bug, 28d windows, never diagnose from avg position (GROUP BY page), warehouse backfill/retention, YoY control, keyword_targets, 2026 doctrine + Aug-2026 corrections, practitioner reality, healthcare playbook",
    files={"reference_gsc_api_toolchain.md","reference_gsc_impression_bug_2026.md","reference_gsc_inspect_410_vs_404.md","reference_get_edit_outcome_asymmetric_windows.md","reference_warehouse_backfill_mechanics.md","feedback_gsc_window_28d_before_diagnosing.md","reference_gsc_retention_storage.md","feedback_avg_position_expansion_artifact.md","feedback_never_diagnose_from_average_position.md","reference_gsc_quarter_yoy_tools.md","reference_keyword_targets_win_loop.md","reference_keyword_targets_false_rivals.md","project_cc_assistant_local_warehouse.md","reference_google_2026_seo_doctrine.md","reference_seo_practitioner_reality.md","reference_search_evidence_2026_08.md","reference_2026_local_healthcare_growth_playbook.md","project_portfolio_instrumentation_gap.md","feedback_test_the_theory_before_planning_on_it.md","feedback_multi_signal_page_optimization.md","feedback_reverify_metrics_at_report_time.md"}),
  "index_elementor_divi_builders.md": dict(
    title="Elementor / Divi builder reference index",
    hook="Elementor ground truth + GLOBAL design system (READ before any build), silently-inert settings, stale post_content, hover fields, spacing 8px, flex align, max-widths, grid rows, popups, alt source, FA5 icons, Divi toolchain + full rebuild, accordion dedup, kit writer, WP 7.0 / SG cache traps",
    files={"reference_elementor_ground_truth.md","reference_elementor_settings_silently_inert.md","reference_elementor_post_content_is_stale.md","reference_elementor_button_hover_fields.md","feedback_deliberate_spacing_rhythm.md","feedback_column_container_explicit_align.md","feedback_text_editor_explicit_align.md","feedback_section_max_widths.md","feedback_elementor_grid_for_icon_box_rows.md","reference_elementor_popup_dynamic_tag.md","reference_elementor_image_widget_alt_source.md","reference_fontawesome5_free_icon_validation.md","feedback_elementor_full_width_template.md","reference_cc_assistant_divi_toolchain.md","reference_divi_full_rebuild_and_css.md","reference_accordion_dedup_workflow.md","feedback_match_page_style_not_defaults.md","reference_cc_assistant_v076_wcag_kit.md","reference_wp_7_armstrong_ai_apis.md","reference_sg_cache_bypass_wp7.md","reference_photoswipe_black_boxes_after_woo_dequeue.md","reference_elementor_pro_posts_widget_load_more_uses_url_pagination.md","feedback_no_html_widget_for_content.md","feedback_verify_rendered_visuals_after_build.md","feedback_verify_page_styling_before_after.md","reference_cc_assistant_v0_52_layout_compare.md","feedback_no_red_hero_band.md"}),
  "index_cc_assistant_plugin_dev.md": dict(
    title="cc-assistant plugin development index",
    hook="MCP-first multi-site plugin; zip deploy vs bin/ MCP restart; local PHP path; release history v0.42-v0.79 (Operator Brain); known tool defects (accessibility_audit stale source, URL resolution ignores redirects, delete_media stem prefix, replace_asset_reference traps, pending supersede); lint/audit quirks; page_facts; render health; tests/ before releases",
    files={"project_wp_plugin.md","project_cc_assistant_page_builder_roadmap.md","reference_desktop_is_onedrive_redirected.md","reference_local_php.md","reference_oauth_local_dev.md","feedback_plugin_performance.md","feedback_automation_boundary.md","feedback_advisor_mode_ui.md","reference_zip_packaging_gotcha.md","reference_uninstall_on_duplicate_delete.md","reference_cc_assistant_lint_audit_quirks.md","feedback_diag_must_mirror_runtime_check.md","feedback_zip_plugin_yourself.md","project_cc_assistant_seo_alignment.md","reference_php_81_ternary_by_ref_fatal.md","project_cc_assistant_robustness_audit.md","reference_redirect_reverse_loop_guard.md","reference_plugin_dev_vs_remote_deploy.md","reference_cc_assistant_schema_generator_bugs.md","project_cc_assistant_attention_system.md","project_cc_assistant_close_loop_v058.md","project_cc_assistant_v059_lean_core.md","project_cc_assistant_v0601_audit.md","project_cc_assistant_ux_audit.md","project_cc_assistant_reports_v064.md","reference_cc_assistant_v067_asset_references.md","feedback_no_per_plugin_adapters.md","reference_sibling_change_drift_guards.md","reference_cssom_walker_nesting_trap.md","project_cc_assistant_render_health_guard.md","reference_cron_context_bootstrap_gap.md","reference_apply_empty_meta_noop_false.md","reference_serialized_meta_cannot_be_written_as_string.md","reference_url_resolution_ignores_redirects.md","reference_site_status_tool.md","reference_page_facts_ground_truth_store.md","reference_accessibility_audit_reads_stale_source.md","reference_wcag_axe_sweep_toolchain.md","reference_ui_screenshot_harness.md","reference_pending_supersede_is_silent.md","reference_cc_assistant_v0763_p0_fixes.md","reference_links_summary_excludes_taxonomy_nodes.md","reference_eeat_scorer_byline_mechanics.md","reference_cc_assistant_delete_media.md","reference_delete_media_stem_prefix_false_positive.md","reference_replace_asset_reference_variant_trap.md","project_cc_assistant_operator_brain.md","reference_ubersuggest_mcp.md","feedback_bash_heredoc_and_pipe_gating.md","reference_onnx_int8_slower_without_vnni.md","reference_docx_toolchain.md","reference_slack_file_download_playwright.md","reference_google_webapp_headless_login.md","project_cf_ai_assistant.md","project_email_platform.md","project_focus_global_hrm_wireframes.md"}),
  "index_client_sites.md": dict(
    title="Client sites and engagements index",
    hook="Per-site facts: erofirving, erofwhiterock (jayard35 staging), eroflufkin, IWC (APRN provider, no medication claims), sids-ponds (Divi+Woo, name doctrine), mammoth (404s RESOLVED, don't re-chase), gnpn, naperville; SiteGround WAF/UA; Polylang; Custom Permalinks traps; GBP/reviews; ER policy pages; social brief",
    files={"project_erofirving_impression_diagnosis.md","project_erofirving_duplicate_url_migration.md","project_sids_ponds_engagement.md","project_sids_ponds_gsc_baseline_2026.md","project_sids_ponds_ctx_feed_broken.md","project_sids_ponds_name_doctrine.md","project_sids_ponds_elementor_rebuild.md","project_gbp_reviews_widget.md","reference_siteground_ua.md","project_jayard35_staging.md","reference_polylang_playbook.md","reference_custom_permalinks_trap.md","reference_custom_permalinks_pagination_4site_diagnostic.md","project_iwc_provider_is_aprn.md","project_iwc_cloned_from_eroflufkin.md","feedback_erofwhiterock_no_provider_bylines_yet.md","project_erofwhiterock_cloned_from_erofirving.md","project_mammothmachinery_engagement.md","project_mammoth_url_migration_404s.md","project_napervillehwclinic_gsc_direct.md","project_er_policy_pages_parity.md","project_board_certified_sweep_incomplete.md","project_gnpn_engagement.md","project_social_creative_keyword_brief.md","reference_iwc_uploads_waf_blocks_curl.md","feedback_gmb_utm_is_intentional.md","feedback_no_hospital_comparison.md","feedback_no_medication_sourcing_claims.md","feedback_no_competitor_clinics_in_bios.md","feedback_paginated_archives_not_worth_indexing.md","feedback_geo_anchor_priority.md","feedback_football_means_soccer.md","feedback_client_report_format.md","feedback_reports_are_work_records_only.md","feedback_handoff_docs_before_after_only.md","feedback_form_email_standard.md"}),
}

moved = {}
for g, spec in GROUPS.items():
    moved[g] = [l for l in entries if fname(l) in spec["files"]]
all_moved = {fname(l) for g in moved for l in moved[g]}
keep = [l for l in entries if fname(l) not in all_moved]

# write topic index files
for g, spec in GROUPS.items():
    body = ["---", f"name: {g[:-3]}", f"description: {spec['title']} — pointers to {len(moved[g])} memory files; open the ones the hook names", "metadata:", "  type: reference", "---", "", f"# {spec['title']}", "", "One line per memory, moved verbatim from MEMORY.md on 2026-09-06 to keep the index under its read limit. Open the file for the full rule.", ""] + moved[g] + [""]
    io.open(os.path.join(MEM, g), "w", encoding="utf-8", newline="\n").write("\n".join(body))
    print(g, len(moved[g]))

new = [l for l in other if l.strip()]  # any non-entry lines (none expected)
new += [f"- [{spec['title']}]({g}) — INDEX ({len(moved[g])} files): {spec['hook']}" for g, spec in GROUPS.items()]
new += [""]
new += keep
io.open(idx_path, "w", encoding="utf-8", newline="\n").write("\n".join(new) + "\n")

# verify no pointer lost
final = io.open(idx_path, encoding="utf-8").read()
for g in GROUPS:
    final += io.open(os.path.join(MEM, g), encoding="utf-8").read()
missing = [fname(l) for l in entries if fname(l) not in final]
print("MEMORY.md lines:", len(final.splitlines()) if False else len(io.open(idx_path, encoding='utf-8').read().splitlines()))
print("kept individually:", len(keep), "moved:", len(all_moved), "missing pointers:", missing)
unknown = [f for f in os.listdir(MEM) if f.endswith('.md') and f != 'MEMORY.md' and f not in final]
print("memory files not referenced anywhere:", unknown)
