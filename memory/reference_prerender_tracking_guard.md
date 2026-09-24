---
name: reference-prerender-tracking-guard
description: Speculation Rules "prerender" makes Flying Scripts' 5s timer fire on pages nobody opened (37 tracker requests measured); pause loadScriptsTimer while document.prerendering and re-arm on prerenderingchange. Tested 2026-09-17 on IWC
metadata:
  type: reference
---

Instant navigation via `<script type="speculationrules">` **prerender** runs the target page's JavaScript in the background. Flying Scripts (1.2.4) loads delayed tags on first interaction OR a plain timer (`const loadScriptsTimer=setTimeout(loadScripts, N*1000)`, printed at `wp_print_footer_scripts` priority 10). A background page gets no interaction, so the timer fires and GTM, Meta, TikTok, Clarity and Google Ads count a visit to a page the person may never open.

Measured on irvingwellnessclinic homepage (live HTML, emulated `document.prerendering`): without a guard, **37** tracker requests before the page was opened. With the guard below plus a chat loader that also waits: **0** before opening, tags start +5.0s after opening, normal visits unchanged, no JS errors.

Guard (prints at `wp_print_footer_scripts` priority 20, after Flying Scripts):
`if (document.prerendering) { clearTimeout(loadScriptsTimer); document.addEventListener('prerenderingchange', () => setTimeout(loadScripts, 5000), {once:true}); }`
Any hand-written delayed loader (e.g. LeadConnector chat) needs the same `document.prerendering` check, or it opens chat sessions for unseen pages.

Also: WordPress 6.8+ prints its own prefetch rules; disable with `add_filter('wp_speculation_rules_configuration', '__return_null')` when supplying your own. Exclude `/wp-content/*`, query strings, wp-admin, wp-login.

Don't recommend deferring jQuery on these Elementor sites: a simulation on IWC (same saved HTML served to a throttled Pixel 7, as-is vs all scripts deferred) threw `wp is not defined` on every page (WordPress's own inline i18n scripts) and showed no consistent FCP gain.

Full implementation: `D:\cc-assistant\tools\iwc-performance-snippet\iwc-performance-snippet.php` (sections 5 to 8). Related: [[project-cc-hero-preload-mobile-bg-bug]].

**Update 2026-09-17: full take-over of the Flying Scripts trigger.** Section 6 of the IWC snippet removes Flying Scripts' own listeners (`userInteractionEvents.forEach(ev => removeEventListener(ev, triggerScriptLoader, {passive:true}))`, `clearTimeout(loadScriptsTimer)`) and re-arms: first interaction then 2 s then requestIdleCallback (timeout 1 s); no interaction then 5 s; prerendering then nothing until `prerenderingchange`. Chat loader: 4 s after first interaction (idle), 8 s fallback, immediate on later pages. Measured on the live homepage (served HTML, real Chrome): tags +2.0 s after first mouse move, chat +4.0 s, 0 ms long tasks in the 2 s after the move (was 66 requests / 1.18 MB on the move itself).

**Site sale popup (Elementor Custom Code "bcp", Autumn Sale).** Its delay was hard-coded (DELAY_MS=1000 after load); no Elementor setting exists for it. Rewritten trigger kept at `tools/iwc-performance-snippet/autumn-sale-popup-custom-code.html`: opens at 30 s, 50 % scroll, or desktop exit intent (mouseout with clientY <= 0), waits while a form field is focused, never arms on a prerendering page. Style, markup, endpoint and submit code verified identical to live. Tool trap: the Write tool turns backslash-u escape text into real characters; restore the escapes in Python with chr(92).
