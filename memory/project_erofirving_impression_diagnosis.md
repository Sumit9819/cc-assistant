---
name: project_erofirving_impression_diagnosis
description: "erofirving.com blog/impression-drop engagement (2026-07-24) — audit done, GSC diagnostic pending MCP"
metadata: 
  node_type: memory
  type: project
  originSessionId: d2d3c86d-6864-40bf-8493-8110b476d9bf
  modified: 2026-07-24T05:02:04.117Z
---

**Job (opened 2026-07-24):** operator reports blog + overall GSC impressions "dropped a lot" on erofirving.com (ER of Irving). Wants senior-SEO diagnosis + 2026 best-practice fixes.

**Public audit already done (no MCP needed, via curl/WebFetch) — site is WELL-optimized:**
- Homepage: correct `["MedicalOrganization","EmergencyService"]` schema, strong local title `Emergency Room in Irving, TX | (972) 893-3148`, full NAP (8200 N MacArthur Blvd Ste 110, Irving TX 75063), 24/7 hours, geo, 22 Service blocks, service-area City markup, self-canonical. nginx, HTTP 200.
- Blog: 44 posts, 35 updated in last 60 days (freshness NOT the problem). Posts carry BlogPosting+BreadcrumbList+Person schema, "Medically reviewed by the…", ~3,100 words, 6 authority citations (CDC/NIH/MedlinePlus). i.e. blog already does the GEO things.
- robots.txt: AI crawlers NOT blocked (GPTBot/ClaudeBot/PerplexityBot OK). Mixed /blog/x vs /x URLs but canonicals clean.

**Diagnosis (senior read):** impression drop is overwhelmingly EXTERNAL, not a site defect —
1. GSC impression-logging bug (see [[reference_gsc_impression_bug_2026]]): over-reported May 2025–Apr 27 2026, corrected forward → phantom drop. Clicks unaffected.
2. AI Overviews absorbing informational blog queries (food poisoning, heat stroke, chest pain, kidney stone = exactly AIO-answered). Low business impact for an ER.
Blog topics are informational; ER money = local/near-me/service pages, which AIO can't take.

**Real fixable gaps found:** (1) NO reviews/ratings surfaced anywhere — biggest local-conversion gap (do NOT inject fake aggregateRating; surface REAL Google reviews). (2) Homepage FAQ section not marked up as FAQPage. (3) Author byline is org-level (marketing account), not a named credentialed reviewer.

**STILL PENDING (needs MCP `cc-assistant-erofirving-com`, whoami first):** the clicks-vs-impressions split — the ONE test that sizes phantom (bug) vs real (AIO/local) loss. Plan when connected:
1. whoami (session recap / working_state)
2. GSC: clicks vs impressions last 90d + 16mo, side by side (phantom test)
3. Same split filtered /blog/ vs service+home URLs (is drop isolated to informational?)
4. "near me"/"emergency room Irving"/branded local query trend (is local intent intact = the emergency check)
5. Top-20 URLs by impression loss (post-Apr-27 clean window) = refresh worklist
6. page_robustness_audit + win_audit + render_probe on biggest losers
Then queue approval-gated fixes, starting with reviews gap. Read erofirving-design skill before any edit.

**Note:** operator's active-engagement router memory says erofwhiterock; this erofirving job is a context switch. Also fixed a plugin bug this session: mcp-server.php list_theme_templates schema (properties=>array() → new stdClass()); one shared file serves all 8 sites; restart to clear warning.
