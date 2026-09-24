---
name: wp-7-armstrong-ai-apis
description: "WordPress 7.0 \"Armstrong\" (released 2026-05-20) ships core AI APIs — AI Client, Abilities API, Connector's Screen, Command Palette (⌘K). cc-assistant v0.32 candidate to integrate."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

WP 7.0 "Armstrong" shipped 2026-05-20 with the first official core AI plumbing in WordPress.

**Core APIs:**

- **AI Client** — PHP class that lets WordPress call generative AI models, provider-agnostic.
- **Abilities API** — server-side registration hook for plugins. Plugin registers an ability (callable PHP function with schema), AI Client can invoke it.
- **Client-Side Abilities** — JS package with command palette `Cmd+K / Ctrl+K`. Surfaces every registered ability for direct human use too.
- **Connector's Screen** — central wp-admin hub for managing external AI connections. Ships with 3 presets + custom-add. Replaces the per-plugin auth config maze.
- **AI Plugin (separate install)** — companion plugin adding image gen, title/excerpt suggestions, alt text.

**Why cc-assistant should integrate (v0.32 candidate):**

- Register every cc-assistant MCP tool (`audit_page_design`, `list_sections`, `replace_section_content`, `draft_update_seo_meta`, etc.) as a WP Ability. User triggers them from the Cmd+K command palette without leaving wp-admin.
- Register Anthropic Claude as a Connector preset so users authenticate through WP's UI instead of editing `.mcp.json`.
- Surface pending changes + edit outcomes inside the native AI hub.

**Effort:** Phase 1 (Abilities adapter) is ~1 week of plugin work. Phases 2-3 require reading the WP 7.0 Field Guide and Connector API docs.

**Related to v0.31 critical-error bug:** WP 7.0 introduced changes to the save pipeline that broke cc-assistant's `apply_section_content_replace` path. Pending #738 (map fix) and #739 (hero bg) silently failed apply on erofwhiterock after the WP 7.0 upgrade. Pattern: replace_section_content with whole-page section_id and root-container widget_id, OR with a nested google_maps widget. Did not damage post data — pre-snapshot taken, apply threw fatal, post_modified unchanged. Plugin v0.32 must investigate the apply path against WP 7.0's new postmeta/JSON handling.

**Resources:**
- [WP 7.0 release post (2026-05-20)](https://wordpress.org/news/2026/05/armstrong/)
- [WP 7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)

Related: [[reference-cc-assistant-v0-31-audit-gaps]], [[project-wp-plugin]].
