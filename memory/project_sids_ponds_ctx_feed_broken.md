---
name: project-sids-ponds-ctx-feed-broken
description: "sids-ponds HAS a Google product feed via CTX Feed (685 items) but it is 8 months stale and fails Merchant Center on price format, identifiers, brand and category"
metadata: 
  node_type: memory
  type: project
  originSessionId: e4e4eed3-3590-41bf-aad2-18859ca12214
  modified: 2026-07-31T16:08:45.838Z
---

Discovered 2026-07-31 from operator screenshots plus direct fetch. **Corrects an earlier wrong claim of mine that "no feed plugin is installed".**

**CTX Feed (WebAppick) v6.6.44 is installed** with two feeds under Manage Feeds:
- `Product-data`, channel google, XML → `/wp-content/uploads/woo-feed/google/xml/productdata.xml`
- `Walmart-Catalogue`, channel walmart, xlsx → `/wp-content/uploads/woo-feed/walmart/xlsx/walmartcatalogue.xlsx`

Both have auto-update ON and interval "1 Hour". **Both are badly stale anyway, so the regeneration cron is broken:**
- Google feed `Last-Modified: Thu, 20 Nov 2025` (8 months old)
- Walmart feed `Last-Modified: Sun, 06 Oct 2024` (22 months old)

**Audited all 685 items in the live Google feed:**
| Defect | Count | Consequence |
|---|---|---|
| Comma thousands separator in `g:price` (e.g. `2,054.99 CAD`) | 150 (21.9%) | Item disapproved; Google requires `2054.99 CAD` |
| `g:mpn` empty | 672 / 685 | No identifier |
| `g:gtin` absent entirely | 685 / 685 | No identifier |
| `g:brand` hardcoded to `Sids-ponds` | 685 / 685 | Should be the manufacturer (Atlantic-Oase, Kichler, in-lite, Laguna, Trimetals) |
| `g:google_product_category` empty | 685 / 685 | Poor categorisation |
| `g:shipping` block absent | 685 / 685 | No shipping data |
| `g:sale_price` identical to `g:price` | 512 / 685 | Redundant, can suppress sale badges |

Availability in the stale feed reads 594 in_stock / 91 out_of_stock, but it is 8 months old, so it does not reflect reality (hornwort and other live stock have since gone out of stock). Availability mismatch is a top cause of item disapproval and account warnings.

**Why this matters:** GSC shows Merchant listings converting at 9.02% CTR (pos 5.79) vs 1.43% for ordinary product snippets (pos 21.70), and 32.18% at pos 1.70 on the magic-carpet page. The channel is the biggest lever on this site and it is far closer to working than a from-scratch build.

**Fix order:** (1) restore feed regeneration, everything else is moot while it is frozen; (2) price format, 150 items disapproved outright; (3) brand mapping to real manufacturer; (4) MPN/GTIN where the data exists; (5) google_product_category; (6) shipping. Items 2-5 are CTX Feed → Attribute Mapping / Category Mapping / Dynamic Attributes; some mapping features are Pro-only.

Separately flagged in the same admin: **Google for WooCommerce connection stops working after 2026-08-18** without a plugin update, and the **WooCommerce shop address is incomplete** (PDF Invoices notice) which is a NAP risk.

Relates to [[project_sids_ponds_gsc_baseline_2026]], [[project_sids_ponds_engagement]], [[feedback_precise_figures_only]].

## 2026-09-09: live DOM evidence, and a CORRECTION to the "no Offer" theory

Checked five high-position/low-click products in a real browser (JSON-LD parsed, not
grepped). The result narrows the thesis usefully.

| product | Offer | price | availability | add-to-cart | sku | brand |
|---|---|---|---|---|---|---|
| limestone-screening | **absent** | - | - | **no** | null | null |
| BSM200 stone lifter | yes | $2,799.00 | InStock | yes | **null** | **null** |
| iQ iQMS362i masonry saw | yes | $4,339.00 | InStock | yes | **null** | **null** |
| Probst Paver SCRIBE / MAL | yes | $288.00 | InStock | yes | **null** | **null** |
| hornwort | yes | $19.99 | **OutOfStock** | no | **null** | **null** |

**`sku` and `brand` are null on 5 of 5.** That is the July feed finding confirmed from
the live front end, and it is the real Merchant Center blocker.

**CORRECTION 1 - the limestone-screening open loop can be closed.** The recorded note
said "no price and no add-to-cart". Half wrong: there IS a visible price in the body,
but there is **no Offer node in the schema and no add-to-cart button**. It behaves as a
call-for-price item, which is why 493 impressions at position 5.6 produced zero clicks
in 90 days. Fixing it is a Woo product-config decision, not a schema patch.

**CORRECTION 2 - do NOT generalise the missing-Offer theory to the contractor tools.**
Probst, iQ Power Tools, GRABO and X-Loader product pages carry complete Offers with real
prices and InStock, and they still earn almost nothing at positions 6.8-16.7 (about
1,208 impressions, 11 clicks in 28d). A $4,339 masonry saw taking 1 click on 193
impressions at position 8 is plausible high-ticket B2B consideration behaviour. **There
is no measured defect on those pages.** Do not propose fixes for them, and do not count
them as recoverable CTR.

So the recoverable set is narrower than "every zero-click product": missing sku/brand
across the catalogue (feed-level), limestone-screening's missing Offer and cart
(product-config), and hornwort's stock (merchandising). Related:
[[project-sids-ponds-gsc-baseline-2026]], [[project-sids-ponds-engagement]].
