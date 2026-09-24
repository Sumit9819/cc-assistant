---
name: Diagnostic functions must mirror their runtime counterpart's coverage
description: When a plugin has a `_diag` mode for a runtime check, the diag MUST test the same variants/markers/paths as the production code. Diverging coverage produces silently-clean diags while production correctly finds the bug — the worst kind of debugging deadlock.
type: feedback
originSessionId: 58e296d8-9c2d-49c5-8de5-e36dda810e6c
---
When a `_diag` (or similar diagnostic) endpoint exists alongside the actual runtime check it's meant to mirror, the two must use identical variant/marker/path coverage.

**Why:** On 2026-05-08 I confidently told the user "Rank Math has zero matching redirects for /contact-us/" because cc-assistant's `diagnose_redirect_dedup` returned `like_match_count: 0`. The redirect did exist — `find_existing_redirect_for_source` (the actual runtime dedup at queue-time) tested both leading-slash and no-leading-slash path variants, but the diag only tested the leading-slash form. Rank Math stores patterns without leading slash, so the diag's LIKE filter found nothing while the runtime check would have found the row. Two pages were broken in a redirect loop and I sent the user down the wrong root-cause path (Custom Permalinks postmeta) for an extra round trip.

**How to apply:** When patching any cc-assistant function whose job is to "report what the matching algorithm sees," diff the runtime function's variant/marker/path-handling code against the diag's. If they diverge, fix the diag — its sole job is to be a faithful mirror of the runtime check. A diag that tests less than its runtime counterpart is worse than no diag, because it produces false confidence. Patched in v0.10.25: diag now runs `array_unique(array_filter([$rm_path, $path]))` to test both variants, identical to what the dedup helper does. Also includes a table-wide `self_redirects` audit on every diag call so the operator sees the full loop class even when investigating a single URL.
