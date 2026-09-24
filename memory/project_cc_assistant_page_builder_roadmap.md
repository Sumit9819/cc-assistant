---
name: cc-assistant-page-builder-roadmap
description: Post-mortem of the filler-page build friction (2026-07-14) and the agreed feature roadmap to fix page-building; popup support shipped v0.50.0
metadata: 
  node_type: memory
  type: project
  originSessionId: 501752a8-8c88-48d5-88ba-e672ff5142f9
---

Building ONE sub-service page (irvingwellnessclinic dermal fillers, post 10120) took 4 full-tree imports and 8 pending changes because of plugin gaps, not content problems. Root causes and the roadmap that came out of it:

**Shipped in v0.50.0 (2026-07-15):** first-class Elementor popup support — `list_popups` tool (with per-popup display-condition coverage check via covers_post_id), `popup_id` accepted on all button/card link inputs (server composes the #elementor-action URL), and a warn-only `popup_not_displayed_on_target` guard on every queue path including import scans. Root cause: buttons wired to popups 294/288 shipped silently dead because conditions didn't cover the new page.

**Roadmap (operator = plugin owner, picks priority):**
1. **P0 `clone_sections`** — copy selected sections BY ID from a reference page into a new/target page (id-regen + text replacements), so builds start from the site's REAL hero/provider/pricing patterns instead of build_page_from_spec's generic templates. Would have eliminated ~all 4 rebuild rounds. (Note tension with the no-clone content rule: cloning LAYOUT patterns within the same site is fine; the rule bans cloning CONTENT across intents/sites.)
2. **P0 dead-image guard** — build/import dry_run HEAD-checks every external image URL and refuses dead hosts (via.placeholder.com shipped broken heroes).
3. **P1 `revise_pending`** — replace a queued elementor_full_import in place instead of reject+requeue churn (pendings 857/859 were burned this way).
4. **P1 `page_parity_report(post_id, reference_post_id)`** — diff section inventory/patterns vs a sibling (map? provider section? aftercare? image frames? hero buttons?) so parity gaps surface before the operator sees them.
5. **P2 visual verify** — some form of rendered-screenshot check; heavy, needs infra decision.

Related: [[wp-plugin]] [[cc-assistant-seo-alignment]]
