---
name: cc-assistant-attention-system
description: "v0.55.0 attention-flow system SHIPPED — archetype specs + predicted-heatmap audit + Clarity opt-in; call attention_spec before builds, attention_audit after"
metadata: 
  node_type: memory
  type: project
  originSessionId: 598fc17d-01d9-403a-b093-67c7c45a8e9b
  modified: 2026-07-30T05:29:05.522Z
---

v0.55.0 (built 2026-07-30, zip at wp-content/cc-assistant.zip, deploy to remote sites pending — same zip carries v0.54 warehouse routes). Answers user's "builds are generic" critique: pages are composed as controlled eye paths, not section stacks.

Components:
- `attention_spec` tool → 4 archetypes (emergency_transactional / consideration_conversion / service_local / informational_guide) each with 5-second job, first-viewport requirements, scroll story, CTA rules, scan pattern. Auto-resolves per post from classify_page family + business_mode (emergency/scheduled/generic); archetype arg overrides. Files: includes/class-attention-spec.php, class-rest-attention.php.
- `attention_audit` tool ("theoretical heatmap") → per-band est_attention_pct (position decay 0.75/band x visual weight from heading px, CTAs, imagery, bg treatment, text density) + flags: buried_h1, no_h1, no_cta, cta_below_fold, competing_ctas, no_tel_above_fold (emergency only), cta_without_proof, flat_hierarchy (= the generic-page smell), monotone_rhythm, text_wall_band. Score 100 - 15/major - 6/minor. Elementor full fidelity, Divi basic. File: includes/class-attention-audit.php (pure core score_bands/extractors — CLI-testable).
- Clarity opt-in: settings field cc_assistant_clarity_project_id → wp_head async tag, admins excluded, autoloaded option (zero FE queries). Calibrate predicted vs real heatmaps once user creates a Clarity project.
- Design skill: section L added to design-system-global/SKILL.md — read attention_spec BEFORE composing, attention_audit AFTER; squint test; 1 CTA/viewport; tel above fold on emergency.

WORKFLOW (every page build/restructure now): attention_spec(post_id) → compose to spec → attention_audit(id) → fix majors → queue.

Review lessons (fixed pre-ship, 34-check suite green): theme-post-title defaults h1 (Elementor persists only non-defaults); tel: detection must scan ALL widget link settings via JSON (linked headings!); form widget = CTA; typography units em/rem/vw need conversion; str_word_count breaks on Spanish (use \p{L} regex); Divi fullwidth_header renders h1+buttons from ATTRIBUTES; bare 'review' substring falsely matched "Preview".

Also this session: Four Principles section appended to CLAUDE.md (think-before-coding, simplicity-first, surgical-changes, goal-driven-execution) — follow them.

v0.56.0 (same day): `widget_schema` tool — live Elementor controls registry (includes/class-widget-schema.php + class-rest-widget-schema.php, routes /widget-schema[/{type}]). Compacted schema per widget: type/default/options/units/responsive-collapse/global_token/section/condition, cached 12h per Elementor version. draft_update_elementor_widget now attaches WARN-ONLY unknown_setting_key + invalid_option_value warnings with did-you-mean (levenshtein<=3) — follows the popup-coverage warning pattern in class-rest-api.php. Design skill section M: call widget_schema before using unverified setting keys; supersedes memorized key lists. 17-check pure fixture suite green; zip rebuilt (98 files) — one zip carries v0.54+0.55+0.56, deploy still pending on all remote sites.
