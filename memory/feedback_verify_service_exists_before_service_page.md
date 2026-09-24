---
name: feedback-verify-service-exists-before-service-page
description: "HARD RULE — never recommend or build a service page for a service the clinic doesn't actually sell. Verify via service_inventory + Aesthetic-style service-card inventory on the relevant pillar BEFORE proposing anything"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: c4bee66e-97d8-42cc-9334-0bf623e1e192
---

**Rule:** Before recommending OR building a new service page, verify the underlying service exists as a real clinic offering with at least one of: existing pricing tier in `service_inventory`, an existing service card on the Aesthetic/services pillar, or explicit user confirmation. If zero evidence → no service page.

**Why (the failure):** 2026-05-18 — built `/laser-genesis-irving-tx/` (post 9983) as a "service pillar" for cluster #3 (Laser Genesis). I reasoned the cluster needed a service-page pillar instead of blog post 8135 to "anchor cluster authority." Wrong on two counts:

1. The clinic does NOT actually sell Laser Genesis. `service_inventory` returns 0 LG tiers anywhere on the site. The Aesthetic pillar 618 has 43 service cards (Botox / Fillers / Microneedling / Hair Removal sizes / Hyperpigmentation areas / etc.) — zero are Laser Genesis.
2. I shipped a 9-section page with `MedicalProcedure` JSON-LD claiming the clinic performs the procedure. That's a YMYL/EEAT violation on a medical clinic site — potentially false advertising and a Google Rich-Results misuse.

User caught it after I'd already built + iterated on the page multiple times. Page was indexed by Google. Required trash + 301 redirect to blog 8135 + GSC URL removal request to clean up.

**How to apply (mandatory checks before recommending any service page):**

1. **Run `service_inventory`** — surfaces every page with `MedicalProcedure` schema + their pricing tiers. If the proposed service isn't there with at least one tier, the clinic isn't selling it.
2. **Inspect the Aesthetic / primary services pillar** (post 618 on irvingwellnessclinic) — read the service card titles. They are the canonical "what we sell" list. If your proposed service name doesn't appear in those titles (or a clearly-related synonym), the clinic doesn't sell it.
3. **Ask the user explicitly**: "The clinic offers X based on [evidence from steps 1-2]. Do you want a dedicated service page for it?" If the user-facing answer is unclear, fall back to: blog-pillar architecture is fine. Don't build service pages on assumption.

**Hard refuse list — do NOT propose a service page when:**
- service_inventory returns 0 tiers for that service AND
- Aesthetic page service-card titles don't include the service name AND
- The user hasn't explicitly confirmed the clinic performs it

**Blog content about a topic ≠ a service the clinic sells.** Cluster #3's blog posts (Laser Genesis benefits, Laser Genesis for rosacea, etc.) are legitimate informational content. They don't justify a service page just because the cluster needs a "pillar."

**What the right move was:** Keep blog 8135 as the cluster pillar (it's 1062 words of informational content about Laser Genesis as a topic, not a service claim). Build inline-link batches FROM other pillars TO 8135 as the canonical destination — same approach as the IV/Hormone/WL/Aesthetic spoke batches from 2026-05-15.

Linked: [[reference-cc-assistant-v0-19-template-clone]] (build_service_page should be gated on this verification — feature for v0.20+).
