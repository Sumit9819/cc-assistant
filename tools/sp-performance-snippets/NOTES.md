# Sid's Ponds speed work (started 2026-09-17)

Same loop as `..\er-performance-snippets\HANDOFF.md`: measure live, find cause,
simulate on live HTML, write snippet, operator pastes, re-measure, US Lighthouse.
Stack differs: Divi 4.27.8 + WooCommerce + 56 active plugins, Speed Optimizer 7.8.2,
Flying Scripts 1.2.4. Site is due to be rebuilt on Elementor; Divi-only fixes die then.

## Baseline (2026-09-17)
US Lighthouse, one run: mobile FCP 5.6 s, LCP 18.6 s, SI 10.6 s, TBT 13 ms, CLS 0;
desktop FCP 0.75 s, LCP 2.0 s, CLS 0.072.
Headless Chrome from Nepal: home 170 req / 4.7 MB (mobile); category and product
~3.1-4.0 MB; HTML 330-370 KB per page; DOM ~1,550-1,900.

## Snippets already on the site before this work
10 woff2 upload, 11 self-hosted fonts + hero preload, 12 Divi module CSS moved to head
(CLS), 13 homepage script diet. 9 (non-shop script diet) is inactive.

## Round 1 (files in this folder)
- `sids-ponds-performance-round1.php` (new snippet): remove Divi Google Fonts CSS,
  head cleanup, jQuery Migrate off, global-styles off, prefetch moderate.
- `snippet-11-self-hosting-fonts-UPDATED.php`: hero preload on front page only.
Simulated (sim1.mjs, shots2.mjs): font requests 17-20 -> 9-12, Google font files 8-9 -> 0,
~220-245 KB less per page, hero PNG no longer fetched off the homepage, speculation
rules accepted, JS errors unchanged, text pixel-identical (cart differs only by random
related products).

## Round 2 (desktop CLS)
Cause traced (trace*.mjs): the desktop main menu is Lato 700 and the top-bar company name
Lato 900; neither was preloaded. First paint used the wider fallback, the menu wrapped
(bar 112 px), then snapped to one line (83 px) and the page moved up 29 px.
Fix: add lato-700 and lato-900 to snippet 11's preload list
(`snippet-11-self-hosting-fonts-UPDATED.php`; round 1 copy kept as `-ROUND1.php`).
Simulated 3 runs x category/product/page: CLS 0.36-0.42 -> 0.042-0.045.
Rejected: loading Divi's JS-inserted late CSS in the head (no change), nowrap on the
company name (no change), fixed 83 px bar height (no change, and the menu legitimately
needs 2 lines below ~1600 px).

## Rejected / corrected during round 1
- Product main image is NOT lazy: data-src is WooCommerce zoom's large image.
- Fixed height on header contact icons: clipped the row (99 -> 37 px), CLS unchanged.

## Open, not yet diagnosed
- Mobile CLS 0.19 on the ponds category from the Divi video (mejs wrapper 270 -> 312 px).
- Home slider downloads every slide background (~1 MB).
- Duplicate Font Awesome: Divi's own + cdnjs FA 6.4.2 in the footer code module.
- Third party: GTM 623 KB, Intercom 279 KB, Facebook 247 KB, Stripe 256 KB (product),
  Affirm 151 KB, Videopack video.js 182 KB (product). Four tracking plugins
  (PixelYourSite erroring "pysOptions is not defined", Pixel Manager, Meta, Site Kit).
- Prerender NOT enabled: too many trackers that would count prerendered visits.
- Mixed content: header menu logo tablet src points at http://kestonl1... (staging host).
