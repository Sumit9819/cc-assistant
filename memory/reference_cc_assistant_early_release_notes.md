---
name: reference_cc_assistant_early_release_notes
description: "Rollup of cc-assistant v0.11–v0.31 release notes (superseded by current code; kept for archaeology) — one line per version, details in linked files"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-05T10:53:03.584Z
---

Historical release facts for cc-assistant v0.11–v0.31. The CURRENT code is the authority; these matter only when digging into why something is shaped the way it is.

- v0.11 Elementor builder tools: draft_add/remove widget+container+accordion. [[reference_elementor_builder_v0_11]]
- v0.12 hard guards: contrast/position/accordion-dup return 422. [[reference_cc_assistant_v0_11_2_guards]]
- v0.14 bootstrap: whoami gained activity_log_48h + playbook + hint. [[reference_cc_assistant_v0_14_bootstrap]]
- v0.15 schema-safety: @type/@id collision detect + post-apply audit. [[reference_cc_assistant_v0_15_schema_safety]]
- v0.16 race-safety: post_modified guard + verify re-read + flush; v0.18.1 fixed its false positive by stamping last_internal_apply. [[reference_cc_assistant_v0_16_race_safety]], [[reference_v0_16_race_safety_false_positive]]
- v0.18 empty-tree bootstrap: tolerate empty _elementor_data. [[reference_cc_assistant_v0_18_empty_tree_bootstrap]]
- v0.19 template clone: build_service_page + completeness + inventory. [[reference_cc_assistant_v0_19_template_clone]]
- v0.28 industry playbook: core + per-industry overlay, auto-detect. [[reference_cc_assistant_v0_28_industry_playbook]]
- v0.31 audit + section tools: 13-check audit + replace_section_content. [[reference_cc_assistant_v0_31_audit_gaps]]
