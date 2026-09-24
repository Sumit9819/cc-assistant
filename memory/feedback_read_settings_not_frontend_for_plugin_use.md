---
name: feedback-read-settings-not-frontend-for-plugin-use
description: Never judge whether a plugin is used by probing front-end pages - read stored settings and stored builder data; front-end absence gave 4 false negatives on sids-ponds and, on mammoth, hid 20 broken product pages
metadata:
  type: feedback
---

To decide whether a plugin is in use, read its **stored settings**
(`get_plugin_settings(slug)`), not its front-end output. On sids-ponds I probed
four pages, called five plugins idle, and was wrong about four of them:

- **CURCY** - store sells CAD *and* USD; `conditional_tags` deliberately hides the
  switcher on the homepage and 8 other pages, which is exactly where I looked.
- **Fluid Checkout** - 60 options configured since 2023. `/checkout/` **302s to
  /cart/ when the basket is empty**, so I was measuring the cart page.
- **Themify Product Filter** - 4 filter sets, live on the lighting categories only.
  I tested `/shop/` and a pond category, where it correctly does not appear.
- **ClearSale** - live production fraud screening on every order. Invisible because
  it runs at checkout.
- **Klaviyo** - consent-gated by Complianz, so it appears only in the cookie policy.

**Why:** front-end absence has at least five innocent causes - conditional display
rules, session-gated pages, category scoping, consent gating, and admin/email-only
function. Presence proves use; absence proves nothing. Recommending deletion on
absence would have broken a live checkout and live fraud screening.

**How to apply:** `get_plugin_settings` first, always. Use front-end probes only to
*confirm* something is rendering. State verdicts as "verified from settings" vs
"unresolved, here is the test that settles it" - never guess. And when the operator
inherited the site recently, do not hand usage questions back to them; go and find
out. Related: [[feedback_dom_is_ground_truth_not_parsers]],
[[feedback_no_guessing_epistemic_discipline]],
[[feedback_probe_discipline_positive_controls]],
[[project_sids_ponds_elementor_rebuild]].

## Second instance, mammothmachinery 2026-09-14: the plugin was switched OFF

I scanned 53 live pages for form widgets, found no MetForm, and told the operator
MetForm was safe to drop. The operator caught it: **MetForm had been deactivated
before the scan ran.** A deactivated plugin renders nothing, so the scan could not
tell "never used" from "switched off". The conclusion was circular and I retracted it.

Re-tested properly against `_elementor_data` (stored builder JSON, which survives
deactivation) via `find_asset_references("\"widgetType\":\"<prefix>")`:

- **MetForm** - exactly one stored widget, in `elementor_library` #339 "Contact",
  a section template with no conditions that nothing inserts. Genuinely unused.
- **HT Mega** - `htmega-thumbgallery-addons` stored on the LIVE data of 16 product
  pages, and HT Mega was already deactivated. **20 of 21 machine pages were serving
  a half-empty hero with no product photos.** Elementor drops an unregistered widget
  type silently: no error, no placeholder, and no empty box to find in the DOM. The
  only way to see it was a geometric test (is the right half of the hero band
  unpainted?) plus a screenshot.

**Why:** deactivation makes a plugin invisible in BOTH directions - it hides real
usage, and it hides the damage the deactivation itself caused. Either way the front
end is the wrong instrument.

**How to apply:** before calling any plugin unused, (1) check `list_installed_plugins`
that it was ACTIVE when you looked, and say so; (2) search stored builder data for its
widget-type prefix, filtering `post_type == "page"/"post"` because revisions dominate
the hits; (3) after ANY plugin is deactivated, search stored data for its widget types
on live pages before declaring the removal clean. Related:
[[feedback_dom_is_ground_truth_not_parsers]], [[feedback_probe_discipline_positive_controls]].
