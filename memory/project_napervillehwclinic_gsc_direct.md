---
name: napervillehwclinic-gsc-direct
description: "napervillehwclinic.com (Next.js, NO repo access) — direct GSC API via OAuth token; developer-handoff model, no plugin"
metadata: 
  node_type: memory
  type: project
  originSessionId: 33e0645f-988e-4995-a9c7-3d2b8a8ffa21
  modified: 2026-08-12T06:10:20.681Z
---

New engagement (2026-08-12): **napervillehwclinic.com** — Next.js site, NOT WordPress, so no cc-assistant plugin. User has NO repo access; all fixes go to the site's developer as handoff docs (exact strings/snippets + Next.js placement notes). Verify shipped changes by re-fetching live pages, then measure in GSC.

**GSC access (verified working):**
- OAuth Desktop client JSON: `D:\client_secret_223276909387-088ur9s9imiab2hboq0e04gajr7ghlo8.apps.googleusercontent.com.json` (project `naperville-website`)
- Refresh token: `C:\Users\sumit\.gsc\token.json` (scope webmasters.readonly)
- Property: `sc-domain:napervillehwclinic.com`, permission **siteOwner**
- Org policy blocked service-account keys ([[reference-oauth-local-dev]] adjacent); OAuth desktop flow was the workaround. Search Console API enabled in that project.
- Query scripts in session scratchpad (`gsc-check.js`, `gsc-sample.js`) — rewrite as needed; pattern: refresh token POST → searchAnalytics/query.

**HARD CONSTRAINT: clinic does NOT provide hormone therapy.** TRT post was deleted deliberately for this reason. Never propose TRT/HGH/hormone-therapy content or framing. BioPro+ page must be positioned as peptide therapy (its live H1 still says "Growth Hormone Therapy" = flagged). Still-live hormone posts flagged for clinic decision: bhrt-guide, 7-signs-of-low-testosterone.

**Doctrine:** apply the Irving [[cc-assistant]] seo_playbook to this site (user directive 2026-08-12). Homepage title carries NO service tokens — one page one intent; homepage owns brand + "wellness center"/"naperville clinic" umbrella only. Providers (verified from live /team): Dawn Bergin FNP-BC MSN (board-certified, medically reviews content), Kayla Freitas RN injector. NO physician on team → "physician-supervised" claims flagged false, use "nurse practitioner led". Name Dawn Bergin in about-page meta (E-E-A-T; "dawn bergin" gets queries).

**Handoff #1 (2026-08-12, artifact https://claude.ai/code/artifact/34b855ca-d211-4343-a4cd-3325cd5d3eae):** titles/H1s/meta descriptions for 16 pages. P0 = 301 the deleted TRT post `/blog/trt-first-6-months-what-to-expect` (404+noindex, was 61 clicks @ pos 9.3) → /blog/7-signs-of-low-testosterone-in-men; do NOT restore. P1 = layout title.template appends brand onto titles that already contain it (88-96 char titles); urgent-care title lacked "urgent care" token. Claims stripped pending clinic verification: physician-supervised (x2), board-certified, open-6-days, 8-modalities. After dev ships: re-crawl to verify strings match, then GSC at 28d.

**Baseline 28d (2026-07-13→08-09):** 439 clicks / 61.6k impressions / 0.71% CTR. Clicks are brand-dominated (~100 of 439 from "naperville health (and|&) wellness clinic", pos 1). Non-brand striking distance: "hocatt near me" pos 8.5, "iv therapy near me" pos 10.3, "peptide therapy near me" pos 10.2; "naperville weight loss clinic" pos 28. Wellness/aesthetics vertical (HOCATT, IV therapy, peptides, weight loss) — [[feedback-intent-doctrine]] and healthcare content rules apply.
