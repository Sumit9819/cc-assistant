---
name: project_gnpn_engagement
description: "gnpn.org (Global Nepali Professional Network) — SiteOrigin/WCK non-profit site with a broken migration; four dead hosts in content, whatwedo CPT 500s/404s"
metadata: 
  node_type: memory
  type: project
  originSessionId: d7ded355-4484-4b38-ad40-0b9a84e31979
  modified: 2026-09-02T06:13:14.380Z
---

**Global Nepali Professional Network** — https://www.gnpn.org, MCP server `cc-assistant-www-gnpn-org`. Non-profit / diaspora association: conferences, a journal, disaster-preparedness work. Engagement started 2026-09-02.

**Stack (verified, and `get_site_memory` auto-detect is WRONG):** page builder is **SiteOrigin Page Builder + SO Widgets Bundle**, not Gutenberg — Classic Editor is active. Custom theme `gnpn` by Bluemuffinstudio. Contact Form 7. **No SEO plugin at all** (so no meta descriptions, no schema anywhere). Redirection, Popup Maker, Duplicator, UpdraftPlus, iThemes Security Pro, SiteGround Speed/Security Optimizer.

**SiteOrigin mechanics:** `panels_data` postmeta is what renders; `post_content` is a cached copy. Fix BOTH. Same principle as [[reference_elementor_post_content_is_stale]].

**CPTs via WCK:** `event`, `whatwedo`, `announcements`, plus `wpcf7_contact_form`. `allowed_post_types` is only `page`/`post`, so `get_post` and every `draft_*` tool **403s** on them. `replace_asset_reference` is the only tool that reaches their rows.

**Botched migration — four dead hosts still in content:**
1. `www.jayard37.sg-host.com` (SiteGround staging) — **NXDOMAIN**, always with a **double slash** after the host
2. `localhost/gnpn//` — from the original 2016 local build
3. `clients.bluemuffinstudio.com.au/gnpn/website/` — the agency's dev server
4. `i0/i1.wp.com/gnpn.org/gnpn/website/` — Jetpack Photon on a legacy path, in `srcset`

**Open items (not yet fixed):** `/whatwedo/<slug>/` singles return **500** (WP fatal) or **404**; `/what-we-do/` links all 12 of its items at the dead staging host and the correct destinations are the `/what-we-do/gnpn-<slug>/` pages instead; `/announcements/` and `/events/categories/past-events/` 404; heavy post-revision bloat; widespread missing alt text.

See [[reference_replace_asset_reference_variant_trap]] for the tool traps hit here.
