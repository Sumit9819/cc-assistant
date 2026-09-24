---
name: cc-assistant-v0-28-industry-playbook
description: "cc-assistant v0.28.0 made the SEO playbook industry-aware. Universal core + per-industry overlay composed at runtime, driven by CC_Assistant_Industry_Profile (auto-detect from schema + Yoast/Rank Math local-SEO type + WooCommerce + title keyword corpus; operator override in Settings → General → Industry)."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

The SEO playbook used to be a static array biased toward Irving Wellness Clinic — clinician credential rules, MedicalProcedure schema, NEJM/CDC citations were baked into top_rules and full() for every site.

v0.28.0 restructured `class-seo-playbook.php` into:
- `universal_top_rules()` + `universal_sections()` — apply everywhere (meta CTR, heading hierarchy, AIO CTR cannibalization, leak findings, audit chain, helpful content, etc.)
- `overlays()` returning an array keyed by industry slug. Each overlay has `top_rules` (industry-specific bullets) and `sections` (long-form). Available overlays: `healthcare`, `legal_practice`, `financial_services`, `home_services`, `local_business`, `professional_services`, `ecommerce`, `saas`, `publisher`, `general`.
- `top_rules()` and `full()` prepend the matched overlay onto universal at call time.

Resolution order: `CC_Assistant_Industry_Profile::get()` reads `cc_assistant_industry_profile` option. If absent, runs `detect()` which scores all industries from:
1. Yoast/Rank Math Local SEO business_type (40 pts)
2. JSON-LD `@type` values fetched from the homepage HTML (5 pts/hit, capped at 25)
3. WooCommerce / EDD presence (25 pts → ecommerce)
4. Title-corpus keyword frequency (1 pt/hit, capped at 20)

Operator override saved with `source: manual` in Settings → General → Industry. Setting to "Auto-detect" re-runs detection.

`whoami` session_recap surfaces:
- `industry` block: slug, label, source, confidence
- `seo_playbook.industry` slug
- `seo_playbook.top_rules` is now composed: matched overlay rules prepended to universal rules

`get_site_memory.detect()` also exposes industry for transparency.

**How to apply:**
- The `healthcare` slug covers clinic, urgent care, freestanding ER, hospital, dental, vet, and pharmacy. The overlay sections call out where ER pages diverge (EmergencyService schema instead of MedicalClinic; triage-decisioning H2s instead of chronic-care descriptions). Legacy slug `medical_clinic` is auto-aliased to `healthcare` on read.
- For ER of White Rock specifically: industry auto-detects to `healthcare` from existing EmergencyService / MedicalClinic schema + ER + emergency-room title corpus. Playbook now includes an "ER and urgent-care specific content shape" section.
- For non-medical sites (Focus Your Finance → financial_services, future SaaS / agency / contractor sites): they now get the right overlay automatically without medical-specific advice bleeding in.
- If detection picks wrong, set it manually at /wp-admin/admin.php?page=cc-assistant-settings → General → Industry.

**Playbook-fit self-audit (added same session):** `class-playbook-fit.php` runs on every whoami. Compares overlay vocabulary (significant terms repeated ≥2× in overlay text) against the site's top-30 vocabulary (titles + excerpts + JSON-LD @type values, ≥3 hits). Returns `seo_playbook.fit` block: `{industry, status: matched|thin|drift_detected, coverage 0-100, unused_overlay_terms (overlay says X but site doesn't), missing_from_overlay (site says Y but overlay doesn't), hint}`. Bootstrap hint instructs the AI to read fit before applying industry rules verbatim and log specific gaps via `update_site_memory_notes` with a "Gap observed:" prefix. Cached 24h in `cc_assistant_playbook_fit` transient. Thresholds: <20% drift, 20-50% thin, 50%+ matched. The "missing_from_overlay" list is the operationally useful field — it names site themes the overlay should grow to address.

Zip path: `wp-content/plugins/cc-assistant.zip` (built via `build-zip.py`).

Related: [[reference_zip_packaging_gotcha]], [[feedback_zip_plugin_yourself]].
