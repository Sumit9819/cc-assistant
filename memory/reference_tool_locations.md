---
name: reference_tool_locations
description: ROUTER for where every tool and data store lives after the 2026-09-06 move off Local by Flywheel; check before saying a tool is missing; scratchpads are never storage
metadata:
  type: reference
---

**Workspace (canonical):** `D:\cc-assistant\` — plugin source + bridge, standalone PHP,
`.mcp.json` (9 sites), memory, skills, hooks, `dist/`, `tools/`:
- `tools/card-generator/` in-body infographic pipeline for IWC ([[project_iwc_card_generator]])
- `tools/card-generator/featured/` featured/OG image generator, 1200x630, with ONNX background
  removal; models/ is in no backup ([[project_iwc_featured_generator]])
- `tools/playwright-probes/` 16 browser measurement scripts (sids-ponds carousel, FA icons, shots)
- `tools/_scratch-archive/` uncurated scripts from 8 session scratchpads; promote or delete
- `tools/whoami_via_bridge.py` bridge smoke test
- `tools/wp-read-via-browser.mjs` READ WordPress's public REST API from inside the headed
  Chrome session, for when a SiteGround IP challenge has taken the bridge offline
  ([[reference_siteground_ip_captcha]]). Read-only by design; never give it a password
- `tools/sg-unblock.mjs` pass a SiteGround challenge in a real browser, then verify
  cookie-lessly (the clearance is cookie-scoped, so it does NOT revive the bridge)
- `tools/card-generator/brands.mjs` role-named brand tokens; `brandharvest.mjs` reads a
  site's real font + logo from its own rendered front end

**Separate projects on D: (not cc-assistant):**
- `D:\gsc-tools\` GSC API toolchain (pull, quarter, yoy, compare, cannibal); creds `~/.gsc/token.json`, `token-sids.json` ([[reference_gsc_api_toolchain]])
- `D:\faceless-studio\` video studio, 11 GB ([[project_faceless_video_studio]])
- `D:\email-platform\` Mailcow platform; has `admin/frontend/tools/screenshot.mjs`, the UI screenshot harness ([[reference_ui_screenshot_harness]])
- `D:\cf-ai-assistant\` VS Code extension, copied 2026-09-06 from the Local Sites root, NOT a git repo ([[project_cf_ai_assistant]])
- `D:\_from-local-sites\plugintesting-root\` everything non-WordPress that sat in the old project root: Focus Global HRM PRD/wireframes + assets, widget_discovery.py, extract.js, find-widget.js, link-polylang-pillar1.ps1, HTML prototypes, JSON audits. Uncurated.

**Machine-local data (per laptop, not in any workspace):**
- `~/.cc-assistant/warehouse/` GSC SQLite, 1.4 GB, re-syncable
- `~/.cc-assistant/wcag/` Playwright + Chromium installed by wcag_sweep; card generator and probes borrow it
- `~/.notebooklm-mcp-cli/` NotebookLM CLI profiles/cookies
- `~/.claude/skills/` duplicates of the 7 workspace skills (prune once the workspace is confirmed working)

**Retired:** `C:\Users\sumit\Local Sites\plugintesting\` (Local by Flywheel). Stale copies of the
plugin, memory and hooks. Safe to delete once cf-ai-assistant and the holding folder above are
confirmed present on D:.

**How to apply.** Before claiming a tool does not exist, check this list and `D:\cc-assistant\tools\`.
Anything built in a scratchpad that will be used again goes into `tools/<name>/` with a README
before the session ends. Related: [[project_cc_assistant_operator_brain]].
