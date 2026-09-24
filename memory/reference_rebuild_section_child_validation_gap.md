---
name: reference_rebuild_section_child_validation_gap
description: draft_rebuild_section queues without validating child widget settings, then fails at apply with unverified_elementor_settings and no usable detail - validate the payload with container_add dry_run first
metadata:
  type: reference
---

`draft_rebuild_section` (plugin v0.89.5) validates only the TOP-LEVEL `settings`
against the live control schema at queue time. It does **not** validate the
`children` array. The pending queues successfully, often with zero warnings, and
then fails at apply with:

```
unverified_elementor_settings
"Settings were not queued: the live control schema rejects or cannot verify this
change. Read widget_schema, fix the plan, then retry."
```

The apply-time error carries **no finding detail**, so it does not tell you which
key is wrong. The pending is consumed and disappears from the inbox. Nothing is
half-applied (the rebuild is genuinely atomic), so the page is left intact.

**Validate before queueing.** `draft_add_elementor_container` with `dry_run: true`
DOES validate children recursively and names the offending key with a did-you-mean.
Feed it the identical `settings` + `children` payload first. Calibrated 2026-09-14
by planting a known-bad key in a child: it was caught precisely.

Note the a11y contrast check runs BEFORE the schema check on that path, so a
contrast false positive masks schema findings. Pass `override_a11y: true` on the
PROBE so schema findings surface.

**Verified 2026-09-14, irvingwellnessclinic post 128, pending 1330.** Every single
component of the payload (children keys, nested container, html widget with
style+script, root-level `_element_id` + `boxed_width`) was afterwards proven valid
via container_add dry runs. So the apply-time rejection was the TOOL, not the
content. Do not keep re-queueing through rebuild_section hoping to find a bad key.

**Fallback that works:** collapse the change into one `draft_update_elementor_widget`
on a single existing html widget, styling with Elementor kit CSS variables
(`var(--e-global-color-primary,#hex)`, custom colors as `--e-global-color-<7charId>`)
so brand colors still track the Kit instead of being hardcoded. One pending, one
proven call path, no transient broken state, no sibling-staleness.

Related: [[reference_sibling_pending_stale_baseline]], [[reference_elementor_ground_truth]]
