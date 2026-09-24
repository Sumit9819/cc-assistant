---
name: read-service-page-elementor-cards-to-build-sku-inventory-gate-content-on-actual-skus
description: "Service-page titles only show the umbrella category — the real niche scope lives in the Elementor service cards (Myers' Cocktail, GLP-1 Tirzepatide 1mo, Skinvive, Bioidentical HRT, etc.). Pull this SKU inventory before proposing content or evaluating marketing news for relevance."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

Every cc-assistant-managed site has service pages whose titles name the umbrella category ("IV Therapy in Irving, TX", "Aesthetic Treatments", etc.). The actual list of services offered — what you can/can't write content about, what marketing news is relevant, what products to watchlist — lives inside the Elementor service cards (pricing boxes, icon-boxes, heading h3s with $ prices). Don't gate content scope on the umbrella alone; gate it on the specific SKU.

**The discipline:**
1. On first connect to a new site (or first session per quarter), call `get_elementor_widgets` (format=summary) on every service page to enumerate the service-card headings. The headings contain the SKU names; the span/h4 nodes adjacent contain prices and durations.
2. Build a per-site SKU inventory and save it to the site's plugin memory (Decisions section) — see Irving Health & Wellness Clinic's Decisions for the format template.
3. Before proposing any content (new post, body refresh, schema, meta), check: does this topic bridge to a specific SKU? If yes, name the SKU in the reasoning. If no, refuse or pivot.
4. When external news arrives in the niche (FDA approvals, new clinical guidance, product launches, technique studies), cross-reference the SKU inventory. News that affects an existing SKU → propose content refresh. News for a non-offered service → flag as "clinic expansion gap" in session notes; don't write the content.
5. Re-pull the SKU inventory if pricing/services change. Treat the inventory as a living document.

**Why:** 2026-05-12 — User asked me to ensure newly emerging marketing/medical news in the niche actually finds its way onto the website if the clinic offers a related service. The natural gate is the service-card SKU list, not the page title — e.g. a "new NAD+ research" post is in-scope because IV Therapy page lists NAD+ 250mg and 500mg drips. Without the SKU inventory, an AI session would either (a) miss in-scope news because the umbrella title didn't match, or (b) propose out-of-scope content because the umbrella looked broad enough to fit. Cross-references [[feedback_niche_search_discipline]] and [[feedback_multi_signal_page_optimization]].

**How to apply:**
1. The SKU inventory format: for each service page, list SKU name + price/duration + "in-niche content bridges" (concrete topics that connect to this SKU). Include a "watchlist" for adjacent SKUs the clinic might add (peptides for hormone clinics, Daxxify/Dysport for aesthetic clinics, etc.).
2. Save to Decisions section of plugin site memory (it's the persistent reference layer; Sessions rotate).
3. When marketing news arrives, follow this decision tree:
   - SKU listed? → propose content refresh on the relevant service page or a supporting blog post.
   - Adjacent watchlist SKU? → flag for user as "clinic expansion gap, news suggests demand."
   - Out-of-scope? → note and discard.
4. If the plugin one day adds a structured `get_service_inventory` MCP tool that returns this without manual parsing, switch to that. Until then, parse Elementor cards manually.
