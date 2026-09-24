---
name: reference-elementor-import-validator-traps
description: import_elementor_data refusals on irvingwellnessclinic (plugin 0.89.5, 2026-09-15) - webhook sub-settings flagged inert, no _mobile container keys, error names no key; bisect with tiny real probes then reject them
metadata:
  type: reference
---

Building the Wellness Pass landing page (post 10902), `import_elementor_data` failed with `unverified_elementor_settings` and the deployed plugin's message named NO key (newer source adds "Blocked: key (code)").

What was refused, verified by bisection:
- **Form `webhooks` and `webhooks_advanced_data`.** Both are gated on `submit_actions` (a multi-select array). The deployed validator compares the array to a scalar, reads the gate as off, and calls them `inert_setting`. The local source has the fix, but it is not deployed. `submit_actions: ["save-to-database","webhook"]` itself passes. The webhook URL has to be pasted in the Elementor editor by the operator, or wait for the plugin update.
- **`flex_direction_mobile` and `width_mobile` on containers.** The site's schema marks those controls non-responsive, which trips `unknown_setting_key`. Put mobile rules in the container's `custom_css` instead (`@media(max-width:767px){selector{--width:100%;width:100%}}`).

How to find the bad key fast: `dry_run: true` does NOT run this validation. Queue tiny real payloads (one container, then one widget with a subset of settings), reject each accepted probe straight away, and keep going until the refusal reproduces. Page evidence can go stale between probes; re-run verified_page_audit + whoami before the real send.

Also: `draft_create_post` queues its publish pending immediately and the operator approved it within minutes, before the layout or noindex existed. For a noindex page, queue robots noindex (and ideally the layout) BEFORE telling the operator about the publish pending, or say plainly "do not approve 1409 yet". Related: [[feedback-never-noindex-pages]] (campaign pages are the explicit exception).

**Sibling evidence trap (2026-09-15, same page).** Four pendings were queued at once on 10902 (page template, _thumbnail_id, SEO title, SEO description). The operator approved all four; only the first applied. The other three failed `evidence_state_changed / no_matching_complete_snapshot`: applying ANY change to a post invalidates every pending on that post queued before it, even an unrelated meta key. verify_change showed no real conflict. Rule: **one pending per post at a time**, and queue the next only after the previous one applies. When several changes must land on one page, prefer one bundled pending (replace_section_content / import) over several small ones, and drop nice-to-haves rather than making the operator approve them in sequence. The operator was "totally fed up" with these failures.
