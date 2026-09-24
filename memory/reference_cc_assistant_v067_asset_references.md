---
name: reference_cc_assistant_v067_asset_references
description: v0.67 asset-reference tools reach plugins that store content outside post_content; storage-agnostic by design
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-03T08:15:00.815Z
---

cc-assistant v0.67 adds `find_asset_references`, `replace_asset_reference`, `media_audit`
(`includes/class-asset-references.php` + `includes/class-rest-assets.php`).

**The design position, which matters more than the code:** when a plugin stores content
somewhere no tool can reach (Brave popup overlay in a serialized option, slider banners in
postmeta, theme-mod backgrounds), do NOT build a per-plugin adapter. Storage location is
the wrong axis to specialise on. The URL is the same string everywhere, so search for the
string across `wp_posts` / `wp_postmeta` / `wp_options` / `wp_termmeta`.

Non-obvious things that took work to get right:

- A URL has **many spellings**. JSON-escaped `https:\/\/`, protocol-relative, root-relative,
  http twin, `&amp;`. Miss the escaped form and page-builder JSON is invisible.
- **Never loop `str_replace` over the variant list.** Each pass re-scans text an earlier pass
  already rewrote, so when the new URL *contains* the old one the replacement grows every
  pass: `hero.jpg` → `hero.jpg.webp` came out as `hero.jpg.webp.webp.webp`. That is not an
  exotic input — it is what every WebP conversion looks like. Use `strtr($s, $map)`, which
  takes the longest key at each position and never revisits what it wrote. Counting must use
  the identical single-pass rule, or the count and the rewrite disagree (summing
  `substr_count` per variant reports 3 hits for 1 reference, because an absolute URL literally
  contains its own protocol-relative and root-relative forms).
- **Serialized values cannot be `str_replace`d.** `s:53:"..."` carries a byte-length prefix;
  a length-changing swap makes the blob un-unserializable and the plugin's settings vanish
  with no error anywhere. Unserialize with `allowed_classes => ['stdClass']`, walk, re-serialize,
  then verify it round-trips before returning. Refuse outright on `O:` of any other class.
- The pending row stores **SHA-1 hashes, not before/after text** — a site-wide swap would
  otherwise put megabytes in one DB column. Apply re-reads, re-hashes, and recomputes the
  rewrite; drift protection is identical and the row stays a few hundred bytes.
- `post_id` is NULL for this change type, which is correct: the guards in `class-apply.php`
  are all `if ( $pending->post_id && ... )` so snapshot and post_modified checks skip cleanly.

Also in v0.67: `list_divi_modules` now returns attribute VALUES (`media`, `links`,
`text_attrs`) for **structural modules too**. Before this, a section's `background_image` was
invisible and the only way to learn which slide used which banner was reading rendered CSS.

See [[reference_cc_assistant_divi_toolchain]], [[reference_plugin_dev_vs_remote_deploy]].
