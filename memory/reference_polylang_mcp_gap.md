---
name: cc-assistant Polylang link endpoint not exposed as MCP tool
description: The plugin's polylang/link-translations REST endpoint exists but mcp-server.php doesn't expose it as an MCP tool — fix is to add the wrapper so future sessions can pair translations without curl
type: reference
originSessionId: a48649d4-4501-45c1-b8a4-addd9908cee8
---
The cc-assistant plugin has `POST /wp-json/cc-assistant/v1/polylang/link-translations` (defined at [class-rest-api.php:558-566](wp-content/plugins/cc-assistant/includes/class-rest-api.php#L558) and handled at [class-rest-api.php:2599-2680](wp-content/plugins/cc-assistant/includes/class-rest-api.php#L2599)) that does the full Polylang setup in one call:

- `pll_set_post_language($post_id, $lang)` for each post in the pair
- `pll_save_post_translations($cleaned)` to write the post_translations taxonomy term

Request body:
```json
{
  "pairs": [
    {"en": 4986, "es": 5009},
    {"en": 4481, "es": 5010}
  ],
  "force_language": true
}
```

**The gap:** `bin/mcp-server.php` doesn't register this endpoint as an MCP tool. Searched for `polylang_link_translations|polylang` in `wp-content/plugins/cc-assistant/bin` — zero hits.

**Why it matters:** When Claude drafts ES/EN translation pairs via `draft_create_post`, the new posts have no Polylang language assigned and no translation link. The reviewer has to do this manually in wp-admin (Languages metabox + Translations metabox per post). Users who don't read Spanish (or who created many pairs) can't realistically do this by hand.

**Workaround used 2026-05-11:** PowerShell script `link-polylang-pillar1.ps1` reads creds from `.mcp.json` and POSTs to the REST endpoint directly. Works but bypasses MCP tooling.

**Fix to apply:** add a `polylang_link_translations` MCP tool to `bin/mcp-server.php` (and any matching tool registry like `class-mcp-tools.php`) that proxies to the existing REST endpoint. Schema should match the REST body: array of `{lang_slug: post_id}` pairs + optional `force_language` bool. While there, consider adding a `lang` param to `draft_create_post` so new posts land in the right language taxonomy from the start.
