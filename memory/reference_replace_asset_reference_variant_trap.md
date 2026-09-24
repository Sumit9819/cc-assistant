---
name: reference_replace_asset_reference_variant_trap
description: "replace_asset_reference/find_asset_references derive a root-relative variant by stripping scheme+host — a trailing-slash host URL becomes bare \"//\" and matches everything; also a hard 300-location cap"
metadata: 
  node_type: memory
  type: reference
  originSessionId: d7ded355-4484-4b38-ad40-0b9a84e31979
  modified: 2026-09-02T06:12:59.351Z
---

`find_asset_references` and `replace_asset_reference` search **eight variants** of the URL you pass, including a **root-relative** one produced by stripping `scheme://host`.

**The trap (verified on gnpn.org 2026-09-02):** passing a host-level URL that ends in a slash makes the root-relative variant a bare `/` or `//`, which matches virtually the whole database.
- `https://jayard37.sg-host.com/` → variants included bare `/` → 41,680 bogus "occurrences".
- `https://www.jayard37.sg-host.com//` → variants included bare `//` → a dry run targeted `//cdn-images.mailchimp.com/...` (would break the stylesheet) and `http://john.do/` (would become `http:/john.do/`).

**Rule: always include a path segment** so the derived root-relative variant is specific — `https://host//wp-content/uploads/2016`, never `https://host//`. And **always `dry_run: true` first**, then check that every returned target actually contains the old host (grep the `context` field for the hostname, not for the path — the path matches trivially).

**Hard 300-location cap.** The `limit` argument does NOT raise it; passing `limit: 2000` still returns 300 and `truncated: true`, and the change queues as `PARTIAL`. Post revisions eat the cap (266 of 300 on gnpn), silently pushing live pages and all `postmeta`/`option` rows out of the change. Partition the work (e.g. by upload year `/uploads/2016`, `/uploads/2017`) so each pending completes in one pass, and confirm `truncated: false`.

The `change_summary` is cosmetically misleading — it prints the trimmed tail, so a correct host swap reads "Replace wp-content with wp-content". Judge scope by `location_count` and the target list, not the summary.

Serialized rows are unserialized/rewritten/re-serialized safely; rows that "looked serialized but did not unserialize" appear in `skipped` and are refused rather than risked. Related: [[reference_cc_assistant_v067_asset_references]], [[feedback_verify_before_proposing_fix]].
