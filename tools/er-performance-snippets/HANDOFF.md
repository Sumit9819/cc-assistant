# Speed work handoff: IWC + the three ER sites (2026-09-17)

Read this before touching speed on irvingwellnessclinic.com, erofirving.com,
erofwhiterock.com or eroflufkin.com. Everything below is live and verified
unless marked otherwise.

## 0. Ground rules the operator set (do not break)

- All speed code lives in the **Code Snippets** plugin, never in theme files. The
  operator pastes snippets; Claude writes them into `D:\cc-assistant\tools\...`.
- **No Cloudflare** (operator refused).
- Content changes still go through Pending Changes (draft-only). Never drive
  wp-admin to get around the approval queue. Reading wp-admin is fine.
- Never noindex (campaign pages excepted). Never remove the GBP UTM URL.
- No em dashes. No "walk-ins" wording on IWC.
- Secrets never go into repo files.

## 1. Measurement: how to not fool yourself

This machine egresses from Asia (Nepal). Timing requests from here goes through
SiteGround's Asia edge and makes the sites look 5 to 15 s slow. **That is
distance, not the server.** Two wrong conclusions were drawn from it before being
retracted.

- Use a **US Lighthouse**: Ubersuggest MCP `pagespeed_audit` (mobile + desktop).
  The keyless PageSpeed Insights API is 429 quota-limited.
- If you must time from here, time a **static file on the same host** as a control
  (a `wp-content/uploads` image). Page build on these sites is ~150 to 200 ms.
- `X-Cache-Enabled` is self-reported by Speed Optimizer and proves nothing.
  `SG-F-Cache: HIT` means the file cache actually served.
- One Lighthouse run varies. Say "one run" when quoting it.
- Elementor's **element cache (24 h TTL)** can hide widget-level PHP filter changes.
  A test render that still shows old HTML may be that cache, not your code
  (Elementor > Tools > Clear Files & Data).
- Test injections of `<link rel="preload" media=...>` must go AFTER
  `<meta name="viewport">`. Before it, phones evaluate media at 980 px and fetch the
  desktop image. That produced a false "bug" once.
- Check in a clean (incognito) context: logged-in admin pages load extra assets.

## 2. How data was pulled (SiteGround challenges this machine's IP)

curl, PHP and the MCP bridge get `HTTP 202 sgcaptcha`. Real Chrome passes it.

- **Plugin / WP REST (read + queue):**
  `node D:/cc-assistant/tools/cc-via-browser.mjs https://SITE --batch calls.json`
  (headless, uses the signed-in profile's `wpApiSettings.nonce`). `no-nonce` = not
  signed in to that site: `node D:/cc-assistant/tools/open-chrome.mjs https://SITE/wp-admin/ 12`,
  operator signs in, then stop that process before running cc-via-browser.
- **Code Snippets check:** REST route `code-snippets/v1/snippets` through the same
  transport: confirms each snippet is active, has no error, and its code matches
  the file on D:.
- **Theme functions.php (read-only):** `tests/themes.mjs` opens the persistent
  profile `D:/cc-assistant/tools/card-generator/featured/.chrome-profile`, reads
  `wp/v2/themes?status=active`, then reads the `#newcontent` textarea on
  `theme-editor.php?file=functions.php`. It never saves.
- **Front-end measurement:** Playwright from
  `C:/Users/sumit/.cc-assistant/wcag/package.json` with real Chrome
  (`C:/Program Files/Google/Chrome/Application/chrome.exe`), Pixel 7 + 1440x900
  contexts, PerformanceObserver for LCP/CLS, CDP for request bytes and
  Speculation Rules errors (`Preload.ruleSetUpdated`).
- **Proving a change before the operator pastes it:** Playwright `page.route()`
  serves the live HTML/CSS with the change applied, then compares against the
  untouched live page (computed styles, CLS, requests, JS errors).

Test scripts are kept in `tests/` next to this file (they expect to be run from a
scratch folder; some read/write relative files such as `irving-combined.css`):

| Script | What it does |
|---|---|
| `audit.mjs SITE` | Per-site audit: LCP element, CLS sources, render-blocking CSS/JS, preloads, above-fold images, fonts, request bytes |
| `facts.mjs` | First look at each homepage: preload tags, speculation rules, ElementsKit icons, scripts held by Flying Scripts, Lufkin lazy image markup |
| `fontfmt.mjs` | Which font files a phone downloads and their size/format |
| `themes.mjs`, `childcss.mjs` | Read active theme, functions.php, child style.css, installed themes (read-only) |
| `lufkincls.mjs` | Lufkin phone layout-shift sources + H1 position every 50 ms |
| `lufkinfix.mjs` | Lufkin fix simulated: live vs un-lazied image, twice each |
| `lufkinbust.mjs` | Cached vs cache-busted Lufkin HTML (`SG-F-Cache` header) |
| `lastchecks.mjs` | Irving hero breakpoint (766 to 1025 px); jQuery Migrate removed vs kept on White Rock + Lufkin (errors, warnings, mobile menu) |
| `irvinghero3.mjs` | Which hero files phone / tablet / desktop fetch with the new preloads |
| `getekit.mjs`, `irvingicons2.mjs`, `mapicons.py`, `glyphs.mjs`, `iconcss.py`, `icontest2.mjs` | ElementsKit icon pipeline (section 5) |
| `verify.mjs` | All 3 snippets simulated on live HTML before pasting |
| `livecheck.mjs` | All 3 ERs after pasting: rules valid, tag timing, hero files, big font, CLS, JS errors, leftovers, mobile menu |
| `round2.mjs`, `inspect2.mjs` | After round 2 paste: Migrate / global styles gone, Lufkin image state, Irving combined CSS saved |
| `wrtitle.mjs`, `round3.mjs` | White Rock theme update: titles, meta descriptions, H1s, Hello CSS, feed links, JSON-LD, screenshots |

## 2b. How every check was actually done (step by step)

The loop for each site was always the same: **measure live -> find the
cause -> simulate the fix on the live HTML -> write the snippet -> operator
pastes -> re-measure live -> US Lighthouse**. Nothing was claimed from one
source alone.

**A. Speed numbers (the scoreboard).**
Ubersuggest MCP `pagespeed_audit` for each homepage, mobile and desktop (Google
Lighthouse run from the US). Recorded FCP, LCP, Speed Index, TBT, CLS and the
"opportunities" (unused CSS/JS, render-blocking). Run once before, once after
each round. Search Console Core Web Vitals is the real-user check later.

**B. Ruling out "the server is slow".**
Timed the page and a static image on the same host from this machine. Both
were equally slow, so the delay was network distance to Asia, not WordPress.
The HTML itself built in ~150 to 200 ms. Also checked cache headers:
`SG-F-Cache: HIT/MISS` (real), not `X-Cache-Enabled` (self-reported).

**C. Headless Chrome page audits (`audit.mjs`).**
Playwright launches the real Chrome headless as a Pixel 7 and as 1440x900
desktop. Inside the page, before any site code runs (`addInitScript`):
- `PerformanceObserver('largest-contentful-paint')`: time, image URL, element,
  whether it was lazy (`data-src`) and natural vs shown width.
- `PerformanceObserver('layout-shift')`: every shift, its size, and which
  element moved.
Through the Chrome DevTools Protocol (`Network.responseReceived` /
`loadingFinished`): every request with its real transferred bytes, grouped by
type, first vs third party, biggest files. From the DOM: stylesheets in the head
(render-blocking), blocking scripts, preloads, above-fold images and CSS
backgrounds, loaded fonts, DOM size, popups. From the raw HTML: leftover head
tags (generator, RSD, shortlink, REST, oEmbed), emoji, jQuery Migrate, block
CSS, speculation rules, Flying Scripts, GTM IDs. Output saved as JSON per site.

**D. Finding root causes.**
- Irving icons: saw a 454 KB `elementskit.woff` in the requests
  (`fontfmt.mjs`), fetched all 98 published URLs and collected every `icon-*`
  class (`irvingicons2.mjs`), found the codepoints in the combined CSS
  (`mapicons.py`).
- Irving hero: saw both hero files downloaded on phones; traced the extra one to
  CC Assistant's `cc-hero-preload` tag; found Elementor's switch width by
  loading the page at 766, 767, 768, 769, 1024, 1025 px and reading the
  computed `background-image` (`lastchecks.mjs`).
- Lufkin CLS: `lufkincls.mjs` logged each shift with the element's before/after
  position, and the H1 position every 50 ms. The H1 dropped 348 px when the lazy
  image arrived.
- Theme code: read each site's `functions.php` from wp-admin's theme editor
  textarea (read-only, `themes.mjs`) and compared with stock Hello Elementor.
- Flying Scripts: counted `data-type="lazy"` scripts in the HTML to see what it
  actually holds back (only GTM).

**E. Proving a fix BEFORE it goes live (no site changes).**
Playwright `page.route()` intercepts the request for the live page and serves
the live HTML with the fix edited in (script removed, image un-lazied, preload
added, CSS file swapped). Then the same measurements run on both versions:
- Lufkin image: CLS live 0.105 to 0.241 vs fixed 0 to 0.004, each run twice
  (`lufkinfix.mjs`).
- jQuery Migrate: with vs without on home + Contact, counting JS errors, Migrate
  warnings, and clicking the mobile menu to confirm it opens (`lastchecks.mjs`).
- Irving icons: rendered original vs subset glyphs side by side as screenshots
  (`glyphs.mjs`), then served the page with the stripped combined CSS + inline
  block and compared every icon's computed `::before` content, font, size,
  line-height, weight, colour and box against live on 3 pages x 2 devices
  (`icontest2.mjs`). 0 differences.
- Hero preloads: which files each screen size requests and what triggered each
  request, via CDP `Network.requestWillBeSent` (`irvinghero3.mjs`). Injection
  placed AFTER the viewport meta (see the trap in section 1).
- Whole snippet: `verify.mjs` applies every block of each site's snippet to the
  live homepage and checks rules accepted, tag timing, CLS, hero files, font,
  menu, JS errors.

**F. Checking tags, prerender and navigation.**
- Speculation Rules validity: CDP `Preload.enable` + `Preload.ruleSetUpdated`;
  any `errorType` means Chrome rejected the JSON (this caught the lost backslash
  on IWC).
- Tag timing: load the page, wait, move the mouse 6 times, record when the first
  googletagmanager / facebook / clarity / doubleclick request fires relative to
  the move. Target: about +2 s, and never before interaction or 5 s.
- Prerender guard (IWC): served live HTML with `document.prerendering` emulated
  and counted tracker requests before the page was "opened" (37 without the
  guard, 0 with it).
- Chat (IWC): same timing method, target +4 s.
- Rejected jQuery defer (IWC): served the same saved HTML to a throttled Pixel 7
  as-is vs all scripts deferred; deferred threw `wp is not defined`.

**G. After the operator pastes: live verification.**
- `code-snippets/v1/snippets` via `cc-via-browser.mjs`: each snippet active, no
  error, code identical to the file on D:.
- `livecheck.mjs` on home + Contact, phone + desktop: markers present
  (`er-tag-scheduler`, `er-view-transitions`, icon block, preloads), leftovers
  gone, shortlink header gone, rules accepted, tags +2 s, CLS, JS errors, mobile
  menu opens, 454 KB font not requested.
- When a change did not show: loaded the page normally AND with `?fresh=<time>`
  and compared `SG-F-Cache` (`lufkinbust.mjs`). A MISS that still lacked the fix
  proved the code was wrong, not the cache (that is how the Lufkin filter was
  found not to work and switched to `sgo_lazy_load_exclude_images`).
- Downloaded Irving's combined CSS and confirmed the icon rules were gone
  (size 863 -> ~782 KB raw, 138 -> 127 KB gzip).

**H. Theme update safety (White Rock, Lufkin).**
- Safety-net snippets tested in a local PHP harness: with the theme code present
  they add 0 hooks; with it removed they register every working hook.
- After the updates: `wrtitle.mjs` / `round3.mjs` on EN home, Contact and ES home,
  phone + desktop: `<title>` count, meta description count, H1 count and text,
  Hello page-title element, Hello CSS files, RSS feed links, JSON-LD count and
  WebPage schema, CLS, DOM size, JS errors, plus screenshots viewed by eye.
  Lufkin `/feed/` and `/contact-us/feed/` requested without following redirects
  to confirm 301.

**I. Things that looked like findings but were not (don't repeat).**
- 5 to 15 s load times from this machine: distance, not server.
- "Enable caching and it's fixed": cache was already serving.
- Flying Scripts delaying Elementor: it only holds GTM.
- Phones fetching the desktop hero with the new preloads: test injection was
  above the viewport meta.
- Lufkin fix "blocked by cache": the filter itself never reached the image.
- Headless screenshots of single elements timed out; computed styles were used
  instead.

Pre-change theme `functions.php` copies are in `theme-backups/`.

## 3. Stack facts (all four sites)

- SiteGround hosting, **Speed Optimizer 7.8.x** (file cache, CSS combine, lazy
  load), Elementor + Elementor Pro, Rank Math, **Flying Scripts 1.2.4** (holds back
  the GTM snippet until interaction), Hello Elementor **3.5.1** on all three ERs
  now (no child themes left).
- Speed Optimizer's CSS combiner parses the **final HTML**. A stylesheet whose
  `<link>` never prints never enters the combined file.
- Speed Optimizer lazy-load exclusions that actually work:
  `sgo_lazy_load_exclude_images` (exact src URL) or the
  `siteground_optimizer_excluded_lazy_load_classes` option.
  `wp_get_attachment_image_attributes` did NOT reach the rendered Elementor image.
- Code Snippets runs at `plugins_loaded`, BEFORE the theme. Copying theme functions
  into a snippet unguarded = fatal "Cannot redeclare". Guard pattern:
  `add_action('after_setup_theme', function(){ if (function_exists('x')) return; ... }, 1);`
- CC Assistant plugin bug: `CC_Assistant_Hero_Preload::emit` (wp_head priority 2)
  preloads the DESKTOP hero for every screen when a page has a different mobile
  background. Worked around per site (below). Real fix belongs in
  `includes/class-hero-preload.php`; when it ships, delete the workarounds.

## 4. Irving Wellness Clinic (done first)

File: `D:\cc-assistant\tools\iwc-performance-snippet\iwc-performance-snippet.php`
(active in Code Snippets as the Performance snippet). Old theme code backed up at
`functions-live-backup-2026-09-17.php`; Hello Elementor restored to stock.

1. jQuery Migrate removed (`wp_default_scripts`).
2. Head cleanup: emoji, RSD, wlwmanifest, generator, shortlink tag + header, REST
   link + header, oEmbed discovery at priorities 4 AND 10, XML-RPC off, Gutenberg
   CSS dequeued, Dashicons for logged-in only, no self-pings, 5 revisions.
   `hello_elementor_description_meta_tag` off (would duplicate Rank Math).
3. Homepage-only responsive hero preloads (desktop
   `2026/03/Untitled-design-18-1024x480-9.webp` >= 768 px, mobile
   `2026/04/Untitled-design-6.webp` <= 767 px) + CC Assistant's preload removed on
   the front page. The old code preloaded the hero on every page.
4. Rank Math credit off, Elementor Google Fonts off, Rank Math schema filters kept
   (managed schema blocks supply page schema; removing the filters duplicates it).
5. **Instant navigation:** Speculation Rules `prerender`, eagerness `moderate`,
   excludes wp-admin, wp-login, wp-content, links with `?`, nofollow,
   `.no-prerender`. Built with `wp_json_encode` (a hand-typed `\?` lost its
   backslash and Chrome silently ignored the invalid JSON). WordPress's own rules
   disabled with `wp_speculation_rules_configuration` -> `__return_null`. The old
   code pointed at the staging domain and never matched.
6. **Tag scheduler** (`wp_print_footer_scripts` priority 20): removes Flying
   Scripts' listeners (`userInteractionEvents`, `triggerScriptLoader`), clears
   `loadScriptsTimer`, then: first interaction -> 2 s -> `requestIdleCallback`
   (timeout 1 s) -> `loadScripts()`; no interaction -> 5 s. While
   `document.prerendering`, nothing loads until `prerenderingchange` (otherwise a
   hover counted as a visit: 37 tracker requests measured on an unopened page).
   Before: 66 requests / 1.18 MB landed on the first mouse move.
7. **View Transitions:** `@view-transition{navigation:auto}`, 0.18 s crossfade,
   off for reduced motion. Header appears not to reload.
8. **LeadConnector chat:** 4 s after first interaction (idle), 8 s fallback,
   immediate on later pages of the same visit (sessionStorage), never while
   prerendering.

Other IWC changes:
- Popup 211 unpublished (operator).
- Autumn Sale popup (Elementor Custom Code "bcp") trigger rewritten:
  `autumn-sale-popup-custom-code.html`. Waits while a form field is focused, never
  arms on a prerendering page, exit intent on desktop. Operator set
  `SHOW_AFTER_MS=10000`.
- Not speed, same session: Wellness Pass form relay mu-plugin
  (`D:\cc-assistant\tools\iwc-wellness-pass\`), see memory
  `reference_elementor_webhook_apps_script_incompatible.md`.

Rejected on purpose: deferring jQuery (`wp is not defined` on every page, no FCP
gain); a Heartbeat filter (Speed Optimizer manages it).

## 5. The three ERs

Source: `build.py` in this folder assembles one snippet per site from shared
blocks. **Edit `build.py`, run `python build.py`, never hand-edit the .php
outputs.** Each output is pasted below its `<?php` line into Code Snippets,
"Run everywhere".

| Site | Blocks | Snippets active |
|---|---|---|
| erofirving.com | HEAD_CLEANUP, MIGRATE_AND_BLOCKS, IRVING_EXTRA, NAVIGATION | Performance |
| erofwhiterock.com | HEAD_CLEANUP, MIGRATE_AND_BLOCKS, NAVIGATION | Performance + Theme Code (`erofwhiterock-theme-code.php`) |
| eroflufkin.com | HEAD_CLEANUP, MIGRATE_AND_BLOCKS, LUFKIN_EXTRA, NAVIGATION | Performance + Theme Code (`eroflufkin-theme-code.php`) |

### Shared blocks
- **HEAD_CLEANUP:** generator, RSD, wlwmanifest, shortlink tag + header, REST link +
  header, oEmbed at 4 and 10, Rank Math credit.
- **MIGRATE_AND_BLOCKS:** jQuery Migrate removed; `wp-block-library`,
  `wp-block-library-theme`, `global-styles` dequeued at priority 100. Tested: no JS
  errors, Lufkin mobile menu still opens.
- **NAVIGATION:** same prerender rules, tag scheduler (script id `er-tag-scheduler`)
  and View Transitions as IWC sections 5 to 7.

### Irving extras (IRVING_EXTRA)
- **ElementsKit icon swap.** ElementsKit loaded a 454 KB WOFF (955 icons) plus
  `ekiticons.css` (84 KB, inside the render-blocking combined CSS) for 10 icons.
  - Found the icons: `irvingicons2.mjs` fetched all 98 published pages/posts
    (`irving-urls.json`) and collected `icon-*` classes.
  - Mapped class -> codepoint from the live combined CSS (`mapicons.py` ->
    `irving-codepoints.json`): down-arrow1 e994, envelope1 e818, facebook eb43,
    instagram-1 eb6c, linkedin eb45, location e835, menu-11 eaaa, phone-call e992,
    twitter eb44, yelp-1 eb8a.
  - Subset with fontTools to a 2,164-byte WOFF2 (`elementskit-irving-subset.woff2`,
    base64 in `.b64`). Rebuild:
    `pyftsubset elementskit.woff --unicodes=U+E994,U+E818,... --flavor=woff2 --output-file=elementskit-irving-subset.woff2`
  - `style_loader_tag` blanks handle `elementor-icons-ekiticons` (not in admin or
    `?elementor-preview`), and a 4 KB inline `<style id="er-ekit-icons">` prints at
    `wp_head` 999: @font-face data URI + base rule + 10 glyph rules. Glyphs are
    written via `html_entity_decode('&#xHEX;')` so no backslash reaches the page.
  - Verified with `icontest2.mjs`: 13 icons identical (content, font, size,
    line-height, weight, colour, box) on home, Contact and a blog post, phone and
    desktop; big font no longer requested.
  - **Risk:** a NEW ElementsKit icon added later renders blank. Fix: add its
    codepoint, re-subset, rebuild, or delete the block.
- **Homepage hero preloads:** `2025/01/Fast-Expert-Care-scaled.webp`
  (min-width 768) and `2026/06/ER-of-Irving-Mobile-Background-Image-of-Hero-Section.webp`
  (max-width 767), CC Assistant preload removed on the front page. Elementor's
  switch point checked at 766/767/768.
- Irving's child theme was deleted on 2026-09-17, which is why
  MIGRATE_AND_BLOCKS was added to Irving.

### Lufkin extras (LUFKIN_EXTRA)
- **Layout shift fix:** image `2026/01/ER-near-lufkin-tx.jpeg` (attachment 6458)
  sits above the H1 on phones, was lazy-loaded, arrived ~6 s in and pushed the H1
  down 348 px (CLS 0.105 to 0.241, CrUX CLS "SLOW"). Excluded via
  `sgo_lazy_load_exclude_images`. Measured un-lazied: 0 to 0.004.
- **Slider LCP preload:** `2025/11/Lufkin-Facality.webp` (first slide, CSS
  background, LCP on phone and desktop), front page only.

### Theme safety nets
- White Rock's functions.php had ~200 lines of custom code and NO Hello stock code.
  `erofwhiterock-theme-code.php` carries the parts that do something (header image
  helpers + "Custom Header Image" meta box, emoji removal, head cleanup, WebPage
  JSON-LD on inner pages), guarded on `hello_child_get_header_image`. Dropped as
  no-ops: Flying Scripts exclusion filter, nonexistent `FlyingScripts.loadNow()`,
  empty style.css enqueue, Google Fonts preconnects.
- Lufkin's functions.php had feed-link removal + `/feed/` 301 redirect.
  `eroflufkin-theme-code.php` carries both, guarded on `disable_feed_links`.
- Both themes were then updated to 3.5.1; the safety nets took over (verified).

## 6. Results (US Lighthouse, one run each)

| Site | Metric | Before | After |
|---|---|---|---|
| Irving mobile | FCP / LCP / Speed Index | 6.3 / 8.3 / 6.3 s | 2.4 / 2.9 / 3.4 s |
| Irving desktop | LCP | 1.5 s | 0.83 s |
| White Rock mobile | LCP / Speed Index | 3.2 / 5.0 s | 3.0 / 3.1 s |
| Lufkin mobile | LCP / TBT | 9.0 s / 98 ms | 3.2 s / 0 ms |
| Lufkin desktop | LCP | 1.6 s | 0.71 s |

Round 3 live checks (after theme updates): all snippets active, error-free and
identical to the files; White Rock 1 title / 1 meta description / 1 H1 per page,
no Hello page title, no JS errors, screenshots normal, WebPage schema on inner
pages; Irving `ekiticons.css` absent from combined CSS (gzip 138 -> 127 KB);
Lufkin image 6458 loads normally, feed links 0, `/feed/` and `/contact-us/feed/` 301.

## 7. Open items (none requested yet)

1. Irving still has ~115 KB unused CSS (Lighthouse est. 560 ms on mobile), mostly
   ElementsKit `common.css` (202 KB). It holds the nav menu styles; left alone.
2. Hello 3.5.1 now prints 2 RSS feed links on White Rock and Irving. Harmless;
   removable with the Lufkin feed code if wanted.
3. GTM tags (Snapchat/TikTok etc.) cleanup is the operator's marketing decision.
4. Check Search Console Core Web Vitals around 2026-10-15 (field data lags ~28 days).
5. Fix the CC Assistant hero preload bug in the plugin, then delete the per-site
   preload workarounds (IWC section 3, Irving hero block).
6. Lufkin: Flying Scripts did not rewrite GTM at first (unexplained); it is held
   back now. Re-check if tag timing looks wrong there.

Memory: `D:\cc-assistant\memory\project_er_sites_performance_snippets.md`,
`reference_prerender_tracking_guard.md`, `project_cc_hero_preload_mobile_bg_bug.md`,
`feedback_never_claim_server_slow_without_control.md`.
