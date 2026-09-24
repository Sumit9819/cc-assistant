---
name: purge-source-vertical-on-clone
description: "When cloning a service page across sites, purge ALL artifacts of the source service vertical (not just the name) — no FAQ items, related-conditions, workup details, or \"what to expect\" lines that referenced the source service."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

When cloning a service page from one vertical to another (e.g., Irving Wellness IV-therapy pillar → ER of White Rock chest-pain page), the rewrite must purge EVERY artifact of the source service vertical. Not just the page title and h1.

**Why:** On the chest-pain rebuild for post 2516, IV-therapy banned phrases survived in the FAQ accordion (`iv drip`, `iv therapy`), the patient-experience section, and the hero subheading even after explicit replacements. The user pushed back: "Its all about chest pain, so dont even think include anything that is related to IV therapy." The audit's industry_vocabulary check now catches these post-import via `banned_phrase_hits`, but the cleaner play is to never write them in the first place.

**How to apply:** When rewriting a cloned section:
- Replace every card body, FAQ item, "related conditions" entry, and "what to expect" line with content that ONLY references the target service. Do not preserve a single sentence that mentions the source service even tangentially.
- Run `list_sections` after import — any `banned_phrase_hits` row is a section to fully rewrite, not a token swap.
- Cross-references to other services must point to services THIS site actually offers (verified via `service_inventory`), not the source site's catalog.

Related: [[reference-cc-assistant-v0-31-audit-gaps]], [[feedback-verify-service-exists-before-service-page]], [[project-erofwhiterock-cloned-from-erofirving]].
