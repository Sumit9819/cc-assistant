---
name: project-sids-ponds-elementor-rebuild
description: Sid's Ponds is moving off Divi to a fresh WordPress + Elementor build; scope, verified plugin stack, and the constraints that decide it
metadata:
  type: project
---

Decided 2026-08-27: sids-ponds.com rebuilds on **fresh WordPress + Elementor**,
Divi retired. Client brief:
https://claude.ai/code/artifact/703ecc89-5d07-48f1-8a26-c46fb78e4776

**Scope is smaller than it looks.** 511 URLs, but 322 products + 74 categories move
as data. Only **8 hand-designed pages** need rebuilding (Home, About, Contact,
Delivery, Hardscaping, Outdoor Lighting, Partners, Blog) plus header, footer and
archive templates. The 22 blog posts ARE Divi but are trivial linear stacks of
`et_pb_text` + `et_pb_image`, so they convert near-mechanically.

**Theme: Hello Elementor + child theme.** A custom theme only makes sense without a
page builder; custom theme + Elementor means paying for both.

**Plugin target is roughly 32-39, NOT 12.** An aggressive 12 was reachable only by
rewriting working configured features as code, which is the wrong trade on a store
with live fraud screening, two currencies and a tuned checkout. Operator's rule:
*prefer a maintained plugin where it is better; nothing should break long term.*

Verified KEEP (from stored settings, see
[[feedback_read_settings_not_frontend_for_plugin_use]]): WooCommerce, WooPayments,
Affirm, **ClearSale** (production fraud screening), **CURCY** (CAD+USD @ 0.73482),
**Fluid Checkout** (60 options, single-step, branded), **Themify Filter** (4 sets on
lighting categories), FiboSearch, WPC Wishlist, Back In Stock Notifier, Klaviyo,
Rank Math, Complianz, ClickShip.

Safe removals: 6 Divi add-ons, Legacy REST API, PixelYourSite (erroring), WP
Statistics, Flying Scripts, Bloom, SG AI Studio, 2 one-off admin tools, 2 inactive,
Broken Link Checker, Post Types Order, All-in-One WP Migration after cutover.

Unresolved, settle in Phase 1: **JWT Auth** (installed 2026-03-02, tokens issued,
consumer unknown - check access logs), ACF (likely only feeds Divi Machine), Auto
Coupons / Free Gifts (coupon audit), PDF Embedder / Videopack (shortcode search),
Conditional Shipping (5 majors behind).

**Hard constraint: URLs must not change**, including `/outdoor-lighting-2/`. ~100k
non-brand impressions/quarter and page-one rankings are at stake.

Incidental finds: **zero reviews on all 322 products** and the review form does not
render (fix, do not just remove the plugin); ClearSale debug mode on in production.
