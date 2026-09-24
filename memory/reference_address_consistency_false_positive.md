---
name: reference_address_consistency_false_positive
description: "v0.35.3 address_consistency lint false-positives on emergency copy (\"call 911 right away. Do not drive\"); fixed in v0.35.4"
metadata: 
  node_type: memory
  type: reference
  originSessionId: cf6b72ff-7286-4f35-bac6-cc72e684abbc
---

The v0.35 `address_consistency_check` (class-pre-publish.php ~line 1765) hard-blocked any widget edit containing emergency-instruction copy like **"call 911 right away. Do not drive yourself."** The street-address regex matched it as the address "911 right away. Do not drive" because: (1) the gap char class included `\.`, letting a match span a sentence boundary; (2) the `/i` flag let the verb "drive" match the "Drive" street suffix; (3) the street-name segment accepted a lowercase start.

**Fixed in v0.35.4** (regex now `\b\d{2,5}\s+[A-Z][A-Za-z0-9\s\-]{2,30}?\s+(?:St|...|Drive|...)\b/u`): street name must start `[A-Z]`, gap can't cross `.`, suffix is case-sensitive. Verified: false-positives no longer match; canonical address + capitalized wrong addresses (incl. ones ending "Drive") still caught. Trade-off: a fully-lowercase wrong address is no longer flagged — correct bias for a hard-blocking lint. Single source of truth — rest-api / build-service-page call `address_consistency_check()`, no duplicate regex.

Same family as [[reference_suspicious_chars_lint_bug]]. **Workaround until v0.35.4 deploys to a site:** place inline links in a widget WITHOUT number+street-suffix prose, or `override_lint=true` after confirming the false positive. See [[reference_plugin_dev_vs_remote_deploy]] — the fix only takes effect on each production site after the zip is uploaded there.
