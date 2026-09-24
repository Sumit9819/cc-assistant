# mammothmachinery.ca — Google Search Console export, last 16 months (2025-03-30 to 2026-07-29)
Source: 10 GSC PDF exports supplied by the operator 2026-07-31. Search type: Web.
NOTE: a known GSC impression over-reporting bug affects May 2025 - Apr 2026. JUDGE ON CLICKS, not impressions.

## SITE TOTALS (16 months)
Clicks 8,643 | Impressions 425,548 | Mobile 5045 clicks/175,707 impr/2.87% CTR/pos 15.02 |
Desktop 3467/245,914/1.41%/26.54 | Tablet 131/3,927/3.34%/8.26
Search appearance: Product snippets 3,053 clicks / 218,812 impr / 1.40% / pos 19.57
                   Merchant listings 115 clicks / 814 impr / 14.13% CTR / pos 2.01

## CLICKS PER DAY BY MONTH (computed from daily site-wide series)
2026-01: 22.1/day (686 clicks)
2026-02: 17.5/day (491)
2026-03: 16.7/day (519)
2026-04: 24.6/day (738)   <- last full month before URL migration
2026-05: 15.9/day (492)   <- migration dip
2026-06: 19.4/day (581)
2026-07 (1-29): 27.7/day (804)  <- BEST month in dataset
Last 14d (Jul 16-29): 31.4 clicks/day, 903 impr/day, avg pos ~9.5
Prior 14d (Jul 2-15): 24.8 clicks/day, 674 impr/day, avg pos ~11.5
Impressions/day: Apr 943 -> Jul 778 (-17.5%). Clicks/day Apr 24.6 -> Jul 27.7 (+12.6%).
Avg position improved from ~11-15 (spring) to ~8.5-10 (late July).

## THE URL MIGRATION (verified live 2026-07-31)
Every /product/ and /product-category/ URL stops receiving impressions between 2026-04-28 and 2026-05-27.
Live probe results: 29 of 38 legacy URLs return **404 with NO redirect**.
Total 16-month clicks landing on 404: 2,593 (30% of all site clicks).
Total 16-month impressions on those 404s: ~190,000.

### DEAD (404, no redirect) — path | 16mo clicks | 16mo impressions
/product-category/mini-skid-steers/ | 885 | 69,022   <-- BIGGEST SINGLE LOSS
/product/x-loader-100mt-mini-skid-steer/ | 232 | 6,433
/product/x-loader-120mt/ | 205 | 11,135
/product/x-loader-3000mt-full-size-skid-steer/ | 203 | 15,583
/products/ | 152 | 18,058
/product/mt2850-track-carrier/ | 150 | 8,501
/product/mt1750-mini-dumper/ | 148 | 13,373
/product/wl4500-wheel-loader/ | 138 | 3,496
/product-tag/mini-skid-steer-canada/ | 70 | 2,453
/product/wl7500-wheel-loader/ | 56 | 927
/product/tl5500-telescopic-wheel-loader/ | 47 | 1,894
/product/x-cavator-20mt-mini-excavator/ | 39 | 4,327
/product/mt1350-mini-dumper/ | 36 | 2,125
/product/tt570-mini-dumper/ | 35 | 2,253
/product/mt2200-mini-dumper/ | 30 | 1,852
/product/tt1000-mini-dumper/ | 29 | 659      <-- NO replacement page exists
/product/x-cavator-27mt-mini-excavator/ | 27 | 2,992
/product/tt660-mini-dumper/ | 22 | 1,415     <-- NO replacement page exists
/product/x-cavator-35mt-mini-excavator/ | 19 | 2,508
/product/x-loader-50mt-mini-skid-steer/ | 16 | 478
/product/mt2200hl-high-lift-dumper/ | 9 | 1,269
/product/ett570-electric-mini-dumper/ | 8 | 1,244   <-- NO replacement page exists
/product/ett900-articulating-mini-dumper/ | 7 | 620
/product/sst1000/ | 5 | 477                  <-- NO replacement page exists
/product/tt570/ | 5 | 34
/product/ett1000-electric-mini-dumper/ | 2 | 256    <-- NO replacement page exists
/shop-2/ | 3 | 1,432
/roi-calculator/ | 8 | 1,174                 <-- NO replacement page exists
/terms-of-sale-warranty/ | 7 | 1,295

### STILL LIVE (200) but EMPTY + noindex — legacy Woo category archives, 0 products listed
/product-category/wheel-loaders/ | 400 clicks | 72,911 impr
/product-category/tracked-mini-dumpers/ | 306 | 15,064
/product-category/wheeled-mini-dumpers/ | 227 | 11,314
/product-category/mini-excavators/ | 177 | 37,484
/product-category/full-size-track-loaders/ | 177 | 21,591
/product/x-loader-2000mt-mini-track-loader/ | 0 | 1  (only surviving Woo product; indexable)
/shop/ | 5 | 708 (INDEXABLE, canonical=?page_id=24, near-empty — index bloat)

### CORRECTLY REDIRECTING (301, 1 hop)
/about-us/ -> /about-mammoth-machinery/ | 24 | 10,936
/full-size-track-loaders-copy/ -> /full-size-track-loaders/ | 0 | 139

## CURRENT LIVE PAGES (Elementor) AND THEIR 16-MONTH PERFORMANCE
/ (homepage) | 3,270 clicks | 103,781 impr | 3.15% | pos 25.60
www.mammothmachinery.ca/ | 357 | 11,911 | 3.00% | pos 5.46   (separate host in GSC)
http://www.mammothmachinery.ca/ | 137 | 4,583 | 2.99% | pos 4.95
/mini-skidsteers/ | 149 | 3,111 | 4.79% | pos 12.48
/tracked-mini-dumpers/ | 103 | 3,628 | 2.84% | pos 7.23
/mini-excavators/ | 94 | 6,159 | 1.53% | pos 14.16
/find-a-dealer/ | 127 | 14,399 | 0.88% | pos 12.30
/x-loader-3000mt-full-size-skid-steer/ | 56 | 661 | 8.47% | pos 5.18
/x-loader-100mt-mini-skid-steer/ | 42 | 627 | 6.70% | pos 5.63
/wheel-loaders/ | 41 | 3,682 | 1.11% | pos 13.63
/contact-us/ | 36 | 5,871 | 0.61% | pos 11.21
/wheeled-mini-dumpers/ | 36 | 929 | 3.88% | pos 7.48
/x-loader-120mt-mini-skid-steer/ | 18 | 316 | 5.70% | pos 6.85
/full-size-track-loaders/ | 15 | 1,006 | 1.49% | pos 9.01
/financing/ | 13 | 17,199 | 0.08% | pos 41.28
/x-loader-50mt-mini-skid-steer/ | 13 | 161 | 8.07% | pos 4.80
/about-mammoth-machinery/ | 11 | 1,401 | 0.79% | pos 5.63
/equipment/ | 21 | 1,805 | 1.16% | pos 5.80
/mtl1000-track-loader/ | 0 | 1 | pos 4.00  (new page, built 2026-07-28)
/x-loader-2000mt-mini-track-loader/ | 0 | 1 | pos 6.00

## SITE-WIDE TOP QUERIES (16 months) — query | clicks | impr | CTR | avg position
### BRAND (approx 2,300 clicks = 27% of all clicks)
mammoth machinery | 1514 | 5328 | 28.42% | 7.28    <-- only pos 7.28 on OWN BRAND
mammoth equipment | 269 | 2753 | 9.77% | 6.73
mammoth skid steer | 133 | 342 | 38.89% | 1.36
mammoth machinery canada | 125 | 182 | 68.68% | 1.00
mammoth machines | 66 | 302 | 21.85% | 3.13
mammoth mini skid steer | 58 | 106 | 54.72% | 1.03
mammoth loader | 48 | 182 | 26.37% | 1.27
mammoth equipment canada | 85 | 309 | 27.51% | 1.60
mammoth machine | 40 | 1914 | 2.09% | 14.76
mammoth excavator | 20 | 292 | 6.85% | 4.57
mammoth construction | 17 | 470 | 3.62% | 6.19
mammoth canada | 8 | 462 | 1.73% | 6.62
NOTE: "Mammoth" is a crowded brand name. Competing entities appearing in the query set include
Mammoth Equipment & Exhausts, Mammoth shoring/scaffolding Hamilton, Mammoth Movers,
Mammoth Crane, Mammoth Mechanical Nelson, and Mammoet (Dutch heavy-lift firm).

### NON-BRAND COMMERCIAL — the money cluster
mini skid steer | 377 | 21,278 | 1.77% | 9.43     <-- top non-brand query
mini skid steer canada | 38 | 1,186 | 3.20% | 11.06
mini skidsteer | 23 | 2,133 | 1.08% | 11.94
mini skid steer loader | 13 | 2,209 | 0.59% | 9.56
mini skid | 12 | 1,038 | 1.16% | 11.04
mini skid steer for sale canada | 15 | 276 | 5.43% | 8.92
mini skid steer for sale | 11 | 613 | 1.79% | 16.54
mini skid steers | 11 | 559 | 1.97% | 13.65
mini skid steer ontario | 7 | 195 | 3.59% | 8.61
skid steer | 16 | 11,763 | 0.14% | 16.55
skidsteer | 0 | 5,127 | 0.00% | 33.48
mini dumper | 46 | 2,758 | 1.67% | 22.63
tracked dumper | 24 | 858 | 2.80% | 10.68
tracked carrier | 19 | 2,768 | 0.69% | 45.74
track dumper | 13 | 911 | 1.43% | 9.01
tracked wheelbarrow | 14 | 812 | 1.72% | 12.77
tracked mini dumper | 11 | 503 | 2.19% | 20.49
wheel loader | 23 | 12,642 | 0.18% | 8.89    <-- POS 8.89, 0.18% CTR = severe CTR leak
wheel loaders for sale | 8 | 1,603 | 0.50% | 15.00
wheel loader for sale ontario | 9 | 1,182 | 0.76% | 10.63
wheel loaders | 7 | 1,501 | 0.47% | 22.97
loader | 5 | 7,199 | 0.07% | 10.15           <-- POS 10.15, 0.07% CTR
loaders | 5 | 5,090 | 0.10% | 14.33
pay loaders | 0 | 983 | 0.00% | 6.02        <-- POS 6.02, ZERO clicks
mini wheel loader | 4 | 866 | 0.46% | 16.63
telescopic wheel loader | 7 | 517 | 1.35% | 17.38
mini excavator | 17 | 3,877 | 0.44% | 30.59
mini excavator canada | 18 | 949 | 1.90% | 32.50
excavator | 7 | 6,825 | 0.10% | 19.44
x loader | 12 | 4,175 | 0.29% | 7.25        <-- POS 7.25, 0.29% CTR (product-name query)
track loader | 1 | 1,075 | 0.09% | 18.15
compact wheel loader | 1 | 1,092 | 0.09% | 27.60
full size stand on skid steer | 8 | 130 | 6.15% | 2.16
3000mt | 6 | 60 | 10.00% | 3.40
100mt | 3 | 546 | 0.55% | 7.22
120mt | 2 | 96 | 2.08% | 3.19
27mt | 0 | 494 | 0.00% | 23.88
20mt | 0 | 51 | 0.00% | 5.76
50mt | 0 | 48 | 0.00% | 37.73
tt660 | (see /product/tt660-mini-dumper/) 2 clicks | 391 impr | pos 6.44
tt570 | 3 | 49 | 6.12% | 4.80
tt1000 | 0 | 56 | 0.00% | 9.30
sst1000 | 0 | 40 | 0.00% | 5.05
mt2200 | 0 | 256 | 0.00% | 7.22
tl5500 | 1 | 142 | 0.70% | 7.54

### HIGH-IMPRESSION ZERO-CLICK (junk or CTR-leak — needs triage)
machinery | 0 | 3,020 | 0.00% | 25.00
construction equipment supplier | 1 | 2,368 | 0.04% | 16.52
machinery canada | 1 | 1,515 | 0.07% | 40.93
heavy equipment suppliers | 0 | 1,727 | 0.00% | 12.77
equipment financing ontario | 1 | 1,465 | 0.07% | 30.48
construction equipment | 0 | 1,438 | 0.00% | 43.05
excavation florenceville-bristol | 0 | 1,244 | 0.00% | 55.79
construction equipment financing | 0 | 1,119 | 0.00% | 40.44
heavy equipment financing ontario | 0 | 1,069 | 0.00% | 35.41
canada stand on mini skid steer loader market | 0 | 1,875 | 0.00% | 35.70
construction equipment manufacturers in canada | 1 | 824 | 0.12% | 18.62

## PER-PAGE QUERY DETAIL FOR THE DEAD /product-category/mini-skid-steers/ (885 clicks, 69,022 impr)
mini skid steer | 368 | 20,661 | 1.78% | 9.27
mammoth skid steer | 80 | 228 | 35.09% | 1.23
mammoth machinery | 31 | 2,468 | 1.26% | 2.68
mini skidsteer | 23 | 2,070 | 1.11% | 11.69
mammoth mini skid steer | 17 | 38 | 44.74% | 1.03
mini skid steer loader | 13 | 2,153 | 0.60% | 9.44
mini skid | 9 | 841 | 1.07% | 10.17
mini skid steers | 9 | 537 | 1.68% | 13.45
skid steer | 3 | 5,582 | 0.05% | 25.87
skidsteer | 0 | 3,122 | 0.00% | 46.97
Device: Mobile 440 clicks/28,734 impr/pos 12.90 | Desktop 165/28,157/pos 25.53
Search appearance: Product snippets 715 clicks / 48,019 impr / 1.49% / pos 17.79

## PER-PAGE QUERY DETAIL FOR LIVE /product-category/wheel-loaders/ (400 clicks, 72,911 impr)
mammoth loader | 42 | 181 | 23.20% | 1.39
wheel loader | 22 | 12,476 | 0.18% | 8.65
wheel loader for sale ontario | 9 | 1,177 | 0.76% | 10.59
wheel loaders for sale | 8 | 1,554 | 0.51% | 15.22
mammoth machinery | 7 | 1,865 | 0.38% | 1.79
wheel loaders | 7 | 1,463 | 0.48% | 22.84
loader | 5 | 7,159 | 0.07% | 10.15
loaders | 5 | 4,906 | 0.10% | 13.75
mini wheel loader | 4 | 782 | 0.51% | 16.06
pay loaders | 0 | 983 | 0.00% | 6.02
wheeled loader | 0 | 1,332 | 0.00% | 14.66
Search appearance: Product snippets 227 clicks / 48,220 impr / 0.47% / pos 17.56

## PER-PAGE: /product/x-loader-120mt/ (205 clicks) — Merchant listings 23 clicks/254 impr/9.06% CTR/pos 1.59
## PER-PAGE: /product/x-loader-3000mt-full-size-skid-steer/ (203) — Merchant listings 8 clicks/77 impr/10.39%/pos 2.34
## PER-PAGE: /product/x-loader-100mt-mini-skid-steer/ (232) — Merchant listings 3 clicks/17 impr/17.65%/pos 1.12
Merchant listings (Google free Shopping listings) only ever appeared on the now-404 /product/ URLs.

## COUNTRY
Canada 6,779 clicks / 282,843 impr / 2.40% / pos 18.24
United States 928 / 57,847 / 1.60% / 24.87
(all others trivial; India 105, UK 67, Hong Kong 62, Ireland 62, China 61)
