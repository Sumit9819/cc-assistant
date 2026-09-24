---
name: reference-serialized-meta-cannot-be-written-as-string
description: "draft_update_postmeta takes value as a STRING, so it can NEVER write array-valued meta (rank_math_robots etc) - WordPress double-serializes an already-serialized string and the reader gets a string back"
metadata:
  node_type: memory
  type: reference
---

**Proven empirically 2026-08-21 (PHP 8.2), after a failed noindex batch on irvingwellnessclinic.**

`draft_update_postmeta` declares `value` as `type: string`. Passing a PHP-serialized array as that string DOES NOT WORK, and it fails **silently** — the apply reports success, a `cc_edits` row is written, and the front end is unchanged.

**Why.** WordPress `maybe_serialize()` has a guard:
```php
if ( is_serialized( $data, false ) ) { return serialize( $data ); }
```
So a string that already looks serialized gets serialized a SECOND time. Verified:

| Input | Stored | Read back |
|---|---|---|
| string `a:2:{i:0;s:7:"noindex";...}` | `s:41:"a:2:{i:0;s:7:"noindex";...}";` | **string** |
| real array `['noindex','follow']` | `a:2:{i:0;s:7:"noindex";...}` | **array** |

Rank Math needs an array. It got a string, so it ignored the override and kept emitting `follow, index`.

**Symptom to recognise:** pending applies fine, `list_recent_edits` shows the row, `X-Proxy-Cache: MISS` proves it is not a cache, and the rendered page is still unchanged.

**Do NOT** reach for `force_raw=true` on `rank_math_robots`. The block on SEO keys exists for exactly this reason — see [[reference_url_resolution_ignores_redirects]] for the sibling lesson about trusting a tool's own guard rails.

**Working routes for noindex today:**
1. Operator ticks "No Index" in the Rank Math Advanced tab (writes a proper array, and overwrites any junk string left behind).
2. `draft_create_redirect` (301/410) when removal, not just deindexing, is the intent.

**FIXED in v0.71.4.** Original gap (v0.71.3): `draft_update_seo_meta` supports description / title / focus_keyword / canonical / og_title / og_description but NOT `robots`, despite robots being one of the commonest SEO meta operations. The fix is a `robots` logical key that builds a real PHP array server-side and hands it to `update_post_meta` unstringified. Until then no MCP path can set noindex on any of the four sites.

## Fixed in v0.71.4

`draft_update_seo_meta` now accepts `logical_key: "robots"`. Pass a plain directive list as the value: `"noindex"`, `"noindex,follow"`, `"noindex,nofollow"`, or `"index"` to clear the override. The endpoint translates per plugin and skips the prose lint:

| Plugin | Key | Stored shape |
|---|---|---|
| Rank Math | `rank_math_robots` | real PHP **array** `['noindex','follow']` |
| Yoast | `_yoast_wpseo_meta-robots-noindex` | string `'1'` (noindex) / `'2'` (index) |
| SEOPress | `_seopress_robots_index` | `'yes'` MEANS noindex |
| AIOSEO | n/a | refused - custom tables, not postmeta |

Defaults to `follow` when only `noindex` is given, so internal link equity keeps flowing. `nofollow` on Yoast/SEOPress is refused with a clear message rather than silently dropped (it lives under a second key).

Also fixed in the same release: `class-apply.php::apply_post_meta` idempotence guard was scalar-only, so re-approving an unchanged ARRAY value returned false and reported `apply_failed`. Now compares arrays.

Tests: `tests/robots-meta-test.php` (21 assertions) pins the ORIGINAL bug as a regression test - it asserts the old string write round-trips to a string and the new transformer round-trips to an array.
