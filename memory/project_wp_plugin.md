---
name: WP plugin (cc-assistant) project context
description: The active project: a WordPress plugin that connects to Claude Code via MCP for content ops on Elementor sites
type: project
originSessionId: 4bb11e9a-33e3-4141-be26-b4503505ff06
---
Building a WordPress plugin (working name: cc-assistant) at `wp-content/plugins/cc-assistant/` inside a Local by Flywheel install at `c:\Users\sumit\Local Sites\plugintesting\app\public`.

**Architecture decided (as of 2026-04-27):**
- MCP-first, project-scoped per site (`.mcp.json` in each site folder)
- Uses user's Claude Team subscription via Claude Code (no separate Anthropic API key)
- Plugin exposes MCP tools + REST fallback
- Strong site identity guards: `whoami` tool, site fingerprint in every response, wp-admin connection-status banner
- Safety floor: snapshot table (`wp_cc_snapshots`) + rollback before any write tool ships
- Plugin only writes inside its own DB rows + own custom tables + its own folder. Never touches themes, other plugins, core, uploads.
- **Draft-only writes (hard rule).** Plugin never modifies live `post_content` directly. Edits to existing posts create a draft revision or a clone draft. New posts are created in `draft` status only. Meta changes go to a pending queue. Nothing goes live without explicit human approval via the Pending Changes inbox.
- **Friendly UX is a first-class requirement.** Empty states with clear next actions. Inline help. No jargon in UI. Onboarding wizard. Visible status indicators. Friendly error messages.

**v1 feature set:**
- Read tools: list_posts, get_elementor_widgets, list_internal_links
- GSC integration: query opportunities (high-impression + position 5 to 15, ranks-for-but-doesn't-mention)
- Cannibalization detection via embeddings (Voyage or OpenAI), analyze_cluster tool
- Snapshots + rollback
- Settings page for keys/allowlist/GSC OAuth

**Out of scope:** in-WP chat UI (would require separate API key), live analytics dashboards, generic SEO score widgets, anything outside the plugin folder.

**User context:** Runs multiple WP sites, hence the project-scoped MCP design. Has Claude Team plan. Cares about content quality (EEAT, no AI tells) and not damaging sites.

**Why:** This shape is the result of explicit refinement with the user. Do not redesign without checking.

**How to apply:** When working on this plugin, follow this architecture. If a feature request would violate the safety rails (touching files outside plugin folder, unscoped writes, mixing sites), flag it instead of implementing.

**SEO playbook (includes/class-seo-playbook.php) — updated 2026-06-17 to VERSION 2026.06.17.1 (plugin 0.43.6):** universal core now ALSO covers (do not re-add): Answer-first + passage chunking / Island Test; Factual density, entities + extractable formats (tables); Topical clusters, decay + refresh discipline; Local pack + GBP mechanics; Off-page brand mentions / digital PR / link quality; Technical access for AI + crawl/index hygiene; GEO/AEO + citation tracking; and a consolidated "Debunked / do-not-waste-time" section (llms.txt, schema-as-AI-trick, GBP-posts-for-ranking, geotagging, sitemap priority/changefreq, keyword density). Also enhanced the existing Heading, Internal-linking, and Readability sections. Vendor stats are tagged "(directional)". Structure: universal_top_rules() (ships every session, keep tight) + universal_sections() (full, via seo_playbook tool) + per-industry overlays().

**GOTCHA when editing the playbook arrays:** multi-line Edit replacements that end on a section's closing brackets can silently collapse the 4-tab `'rules' => array()` close into the 3-tab section close (it happened on 3 sections this session, leaving `array(` unclosed). The file has an ABSPATH guard so it can't be run standalone. ALWAYS verify after editing: `php -l` with the Local-bundled PHP at `C:\Users\sumit\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe`, AND a token_get_all paren-balance check (count `(` vs `)` among single-char tokens; report lines of unclosed `(`). Then `python build-zip.py` (forward-slash zip) and verify the zip with Python zipfile (CLI PHP has no ZipArchive ext). [[reference_zip_packaging_gotcha]] [[reference_local_php]]
