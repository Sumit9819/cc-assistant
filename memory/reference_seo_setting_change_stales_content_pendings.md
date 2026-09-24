---
name: reference_seo_setting_change_stales_content_pendings
description: Applying any Rank Math / SEO plugin setting invalidates every content pending drafted before it (the fingerprint includes rank-math-options-titles) — queue settings first; from 0.90.0 unrelated plugin versions no longer count
metadata:
  type: reference
---

Applying a **plugin setting** change makes every **content** pending that was
drafted before it fail to apply with:

> `environment_changed` — "The plugin/theme/kit or SEO configuration changed after
> this plan was drafted. Inspect the current site and queue a new plan."

**Why.** `CC_Assistant_Evidence_Gate::environment_hash()`
(`includes/class-evidence-gate.php`, ~line 48) hashes a context blob that includes
these options by name:

```
home, siteurl, permalink_structure, show_on_front, page_on_front, polylang,
rank_math_modules, wpseo, rank-math-options-titles, rank-math-options-general,
wpseo_titles, elementor_experiment-container
```

plus WP version, active-plugin versions, mu-plugins, theme versions, stylesheet,
template, blog_public and the Elementor kit's page settings.

Every queued plan stores that hash; `validate_apply()` refuses if it no longer
matches. So **one write to `rank-math-options-titles` stales every pending drafted
earlier**, however unrelated. Rank Math Local SEO opening hours, titles, meta
templates and the Local SEO block all live in that option.

**Ordering rule.** Queue and apply SEO/plugin setting changes **first**, then draft
the content changes. Doing it the other way costs a full re-queue of every content
pending. The content is never the problem — re-queueing the identical edits after
the setting lands works.

Observed 2026-09-11 on sids-ponds during the Saturday-hours change: 436 (schema
`opening_hours.5.time`) applied, and 437/438/439 (three unrelated page bodies) all
failed at once. Re-queued unchanged as 440/441/442.

**Verifying a schema change afterwards:** SiteGround's edge cache will keep serving
the old JSON-LD. A plain fetch of the page showed the pre-change value while the
option was already updated. Cache-bust the URL (`?cb=<timestamp>`) or use
`render_probe`, which loopback-fetches. See [[feedback_confirm_change_took_effect]].

**0.90.0 (2026-09-16) narrowed the fingerprint.** It is now eight separately
hashed components, and only these block an apply: WP version, theme, mu-plugins,
the SET of active plugins, the versions of MATERIAL plugins only (SEO, page
builder, multilingual, permalink rewriters, cc-assistant), the Elementor kit, the
option list above, and blog_public. The version of any other active plugin is out,
so an overnight auto-update no longer stales a queue. Activating or deactivating
any plugin still does. The refusal now names what moved
(`environment_changed_parts`, `material_plugin_drift`). Extend the material list
with the `cc_assistant_material_plugin_slugs` filter. Stored hashes carry an `m2:`
prefix; plans queued under 0.89.x still validate against the old whole-site hash.

The ordering rule is unchanged: an SEO option write still stales earlier content
pendings, because `seo_and_routing_options` is material.

Cause of the 2026-09-15 sids-ponds failure that prompted 0.90.0: CTX Feed
8.0.21 to 8.0.22 auto-updated overnight and killed 20 queued product descriptions.

Related: [[reference_sibling_pending_stale_baseline]],
[[reference_pending_supersede_is_silent]].
