---
name: reference-wcag-axe-sweep-toolchain
description: "WCAG 2.1 AA sweep toolchain (Playwright + axe-core in scratchpad, sitemap URL inventory) and its two known artefacts - lazy-loaded backgrounds fake contrast failures unless the page is scrolled first; Elementor toggle/accordion ARIA failures are vendor markup. Fixed 2026-08-25 sweep on Lufkin/White Rock/IWC."
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-25T08:05:01.526Z
---

**Toolchain (2026-08-25):** `axe-run.js` in the session scratchpad: Playwright (1.62, Chromium 151 from `~/AppData/Local/ms-playwright`) + `axe-core`, URLs from each site's Rank Math `sitemap_index.xml` (browser UA required, SiteGround WAF blocks bot UAs). Runs `wcag2a, wcag2aa, wcag21a, wcag21aa, best-practice`; 4 workers; ~350 pages in ~25 min. Summaries via `axe_summary.py <site>` and `axe_detail.py <site> <rule>`.

**Artefact 1 (measured, positive control):** SiteGround Optimizer lazy-loads background images AND gradients. Without scrolling, Elementor flip-boxes computed to the default teal `#1abc9c` with kit-secondary titles, and axe reported 61 "contrast 3.79" failures on 15 Lufkin service pages. After `scrollIntoView` the back layer computed to the real gradient with a white title. **Always auto-scroll the page before axe measures contrast**; `axe-run.js` now does this. Never queue colour changes on an unscrolled axe result.

**Artefact 2:** `aria-allowed-attr` / `aria-prohibited-attr` on `.elementor-tab-title` (role=button on h3/div) and `.e-n-accordion` / testimonial icon `aria-label` on a div are Elementor core markup, not content. Report as vendor issues; do not try to patch.

**Real, content-level patterns found:** empty citation anchors (`<a href><span>&nbsp;</span></a>`, often `?utm_source=chatgpt.com`) = `link-name` failures, fix by moving the link onto adjacent claim text (deleting them trips the `authority_citation_preservation` lint); pages with no H1 (careers/insurance/booking); custom-SVG social icons with no accessible name; `outline:none` on custom inputs; autoplay carousels; iframes without `title`. Template-level ones (post-date `#adadad`, TOC h4, duplicate `<footer>` landmark, missing `#content` skip target, kit link colour `#d85d68` on IWC, `#da1212` links on grey) need the operator in Elementor Theme Builder / Site Settings because `elementor_library` and the kit are outside the plugin allowlist.

Related: [[reference_accessibility_audit_reads_stale_source]], [[feedback_dom_is_ground_truth_not_parsers]], [[feedback_probe_discipline_positive_controls]].

**2026-08-26 runner hang, root-caused (bin/wcag-sweep.php + bin/wcag/axe-run.js, MCP restart needed):** (1) On Windows, PHP cannot make proc_open pipes non-blocking, so `stream_get_contents` blocked until the child exited and the "hard timeout" never fired; the MCP tool sat for 30+ min. Fix: child stdout/stderr to temp FILES, poll `proc_get_status`, `taskkill /T /F` the tree on timeout, `bypass_shell` for real executables (not .cmd). (2) The keyboard form probe clicked submit on forms with NO required fields (a blog-archive search box), which really navigated the page mid-audit and wedged the renderer; every sweep stalled on its last page. Fix: probe only forms with required fields, add a capture-phase submit preventDefault, per-page 150s watchdog, bounded page.close/browser.close, results written before browser.close, and a `<raw>.progress.log` with stage markers (goto/scrolled/axe/keyboard) plus `<site>.log`. Run a suspect page alone via `cc_tool_wcag_sweep(['urls'=>[url]])` in plain PHP to reproduce; never launch sweeps in parallel across sites.
