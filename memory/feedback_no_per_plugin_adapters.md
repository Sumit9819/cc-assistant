---
name: feedback_no_per_plugin_adapters
description: "When a plugin's content is unreachable, find the storage-agnostic axis instead of writing an adapter for that plugin"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-03T07:29:43.018Z
---

When the operator asks "can you make the plugin edit X" (a Brave popup, a slider, a theme
option), the tempting build is an adapter for X. Resist it.

**Why:** an adapter is one plugin's worth of coverage and permanent maintenance — it breaks
when that plugin changes its option shape, and the next unreachable plugin needs another one.
There is almost always a shared axis underneath. For media it is the URL; for settings it is
the option row. Building on that axis reached every plugin on the site at once and needed no
per-plugin knowledge at all. See [[reference_cc_assistant_v067_asset_references]].

**How to apply:** before writing plugin-specific code, ask what the actual unit of work is.
If the answer is "a string that appears in a database row", search for the string. Say the
tradeoff out loud to the operator rather than silently building the narrower thing — they
asked for the popup, and shipping something broader is only right if they know that is what
they are getting.

Corollary: do not promise "accurately execute everything". State what is verified (unit
tests, syntax) and what is not (never run against a live WordPress) separately.
See [[feedback_verify_before_proposing_fix]].
