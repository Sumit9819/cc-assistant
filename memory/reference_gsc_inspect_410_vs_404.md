---
name: reference_gsc_inspect_410_vs_404
description: "GSC URL Inspection API reports 410 Gone as \"Not found (404)\" — never infer \"no redirect exists\" from NOT_FOUND"
metadata: 
  node_type: memory
  type: reference
  originSessionId: acae98d0-ca94-4d6e-907a-aa5917f30cc0
  modified: 2026-08-06T04:13:10.845Z
---

`gsc_inspect_url` (Google's URL Inspection API) returns `coverage_state: "Not found (404)"` and `page_fetch_state: NOT_FOUND` for **both** a genuine unhandled 404 **and** a deliberate `410 Gone`. It does not distinguish them, and its `suggested_fix` string ("restore the page or add a 301 redirect") fires on 410s too — so the tool actively invites the wrong conclusion.

**Never claim "this URL 404s with no redirect" from a NOT_FOUND verdict alone.** That is an absence claim and it needs a positive control — see [[feedback_probe_discipline_positive_controls]].

**The authoritative check** is the plugin's own redirect-store diagnostic:

```
draft_create_redirect(source="/the-path/", destination="https://site.com/anything/", _diag=true)
```

`_diag=true` dumps the live Rank Math redirect rows matching that source (id, `header_code`, `url_to`, status) and queues nothing. Note `_diag` still requires a `destination` in the payload even though it is unused. Attempting the create without `_diag` also works as a probe — the dedup guard 409s with the existing redirect's id and http_code — but `_diag` is cleaner.

Second stale-data trap in the same API: **`referring_urls` is historical crawl data.** It listed live service pages as linking to retired URLs on erofirving when `audit_post_links` showed zero such links. Verify inbound links against the live DOM, not against `referring_urls`.

Burned on this 2026-08-06 diagnosing erofirving.com: I reported a deliberate, correctly-executed 410 retirement as a broken migration bleeding 600 clicks. The retirement was fine. See [[project_erofirving_duplicate_url_migration]].
