---
name: ubersuggest-official-mcp
description: "Ubersuggest ships an official remote MCP server (OAuth, read-only, any plan incl. lifetime); connect as sibling server, do NOT proxy through cc-assistant"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 598fc17d-01d9-403a-b093-67c7c45a8e9b
  modified: 2026-07-29T05:07:33.094Z
---

Official Ubersuggest MCP server (announced by Neil Patel, live as of July 2026):

- Endpoint: `https://ubersuggest-mcp.neilpatelapi.com/mcp` (remote HTTP transport)
- Auth: OAuth 2.0 against the user's Ubersuggest account, read-only. User has a **lifetime subscription**; the connector is advertised as "available on any Ubersuggest plan".
- 37 tools / 7 categories: Domain Analysis (8), Keyword Research (7), Backlinks (5), Site Audit (4), Content (2), Projects (7), Utilities (4).
- Connect from Claude Code: `claude mcp add --transport http ubersuggest https://ubersuggest-mcp.neilpatelapi.com/mcp` then `/mcp` in an interactive session to run the OAuth flow (non-interactive sessions cannot authenticate).
- Ubersuggest has NO public REST API (confirmed by their support); only unofficial scrapers (Apify/RapidAPI) exist — avoid, ToS risk.

Architecture decision (2026-07-29): do NOT integrate Ubersuggest into the cc-assistant plugin. The plugin's PHP backend would have to implement an MCP client + OAuth token storage for read-only data, and external API calls from WP violate the [[plugin-performance]] doctrine. Instead run it as a sibling MCP connector next to the per-site cc-assistant servers; Claude blends Ubersuggest volume/difficulty with GSC data from cc-assistant tools (brief_for_keyword, keyword_research, win_audit) at conversation time. If a durable store is ever needed, add a cc-assistant tool that *accepts* keyword data Claude fetched (plugin stays API-free).

Rate limits: undocumented; lifetime plans have daily report limits in the app which MCP calls likely share — watch for 429s.

CONNECTED 2026-07-29: authenticated as hello@focusyourfinance.com, tier2. Registered at user scope via `claude mcp add` (CLI was missing — installed globally via npm, v2.1.220, at %APPDATA%\npm).

KNOWN QUIRK (2026-07-29): `keyword_overview` and `keyword_suggestions` return volume=0, cpc=0, empty monthly_searches/results even for huge keywords ("emergency room near me"), though sd/pd populate. NOT a connection problem — `domain_overview` returns full real data including per-keyword volumes (e.g. erofirving.com ranks #12 for "emergency er near me", vol 450,000). Workaround: get volumes via domain_overview/domain_keywords/serp_analysis, or retry later (connector is new; likely server-side bug or plan gating on the keyword-DB endpoints). `keyword_metrics` consumes monthly quota — don't use for smoke tests. Pass locId 2840 for US on every call; default is Global which zeroes local data.
