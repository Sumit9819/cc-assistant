---
name: reference-cc-assistant-v0-15-schema-safety
description: "cc-assistant v0.15.0 closes the schema-duplication safety hole — pre-queue dedup on both widget_add + container_add paths, plus a 30s post-apply rendered audit that surfaces issues in whoami"
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

The v0.14-era hormone page shipped TWO MedicalProcedure JSON-LD blocks with the same `@id` because the same logical "add schema" intent went through two different code paths (`draft_add_elementor_widget` + `draft_add_elementor_container`) in close succession. The v0.13 duplicate-proposed_value guard only caught IDENTICAL payloads — different paths with different children specs slipped through.

**v0.15 closes the hole on three levels:**

1. **Pre-queue schema-entity dedup** ([class-rest-api.php](wp-content/plugins/cc-assistant/includes/class-rest-api.php) helpers `extract_schema_entities_from_children`, `collect_live_schema_entities`, `find_schema_collisions`). On every `draft_add_elementor_widget` AND `draft_add_elementor_container`, scans JSON-LD scripts inside any HTML widget in the proposed children, parses `@type` + `@id` per entity, cross-references against the LIVE page's existing entities AND detects the Rank Math auto-FAQPage signal (nested-accordion + `faq_schema: yes`). Refuses 422 on collision. Override: `override_schema_dup=true`. Singleton types (FAQPage, MedicalProcedure, MedicalWebPage, WebPage, Article, BreadcrumbList, WebSite) collide even when ids are empty.

2. **Pre-queue HTML-widget content-similarity** (`similar_text` ≥ 80%). Catches "same comparison table re-added with one word changed" patterns. Refuses 422 with `override_dup=true` opt-out.

3. **Post-apply rendered audit** ([class-post-apply-audit.php](wp-content/plugins/cc-assistant/includes/class-post-apply-audit.php), 30s after every `elementor_*` apply). Curls the live URL with the SiteGround-safe UA, parses every JSON-LD block, detects duplicate `@id` / singleton-type multiplied / JSON-LD parse errors. If issue: records `drift_detected` in `cc_activity_log`, writes findings into the pending row's `verification_result`, sets `cc_assistant_health_issues` transient for the admin notice. Surfaces in next `whoami` session bootstrap because activity_log_48h is part of session_recap.

**Why level (3) is the safety net:** pre-queue guards work off the elementor data tree. Rank Math + the theme also inject JSON-LD via `wp_head`. Pre-queue can't see those. The rendered audit can. Any duplicate that slips through gets caught within 30 seconds and surfaces in the next session.

**Override discipline:** only set `override_schema_dup=true` when the operator has explicitly accepted the collision in the same turn. Default behavior is HARD refuse — the safer side of the trade.
