---
name: reference-slack-file-download-playwright
description: "Slack MCP returns rendered images not file bytes; download real files with Playwright on a scoped profile, and the two bugs that make it silently fail"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 9c6f1bb7-e1aa-47b7-a180-e9cd066c7b98
  modified: 2026-09-03T07:26:16.880Z
---

`mcp__claude_ai_Slack__slack_read_file` returns a **rendered image**, not the file. Cheap to look at (~1.5K tokens, so viewing 100 images is fine and is the ONLY reliable way to identify them), but vision input is lossy and one-way — the original bytes never reach the model, so it can never be written to disk. There is no MCP path to the actual file.

**Working approach:** Playwright driving a signed-in browser, then Slack's `files.info` API for `url_private_download`. Script at `scratchpad/slack_fetch.mjs` (login | fetch modes) with `download_list.json`.

Four things that bite:

1. **Never use the real Chrome profile** (`Local/Google/Chrome/User Data`). It hands the automation every workspace, DM and signed-in site. Use `launchPersistentContext` on a scoped directory the operator logs into once.
2. **localStorage is per-origin.** The workspace host (`<name>.slack.com`) and the actual client (`app.slack.com`) are DIFFERENT origins. Reading the token from the workspace host finds nothing even after a perfect login. Check both, and sweep every localStorage key for an `xox*` value rather than trusting `localConfig_v2` (Slack renames it).
3. **A login window that opens and closes looks identical to a real login.** Poll for the token DURING login and print a confirmation; exit non-zero if the window closed without one. Otherwise the failure only surfaces later during the download.
4. **Slack serves its login page with HTTP 200** when auth lapses. Reject any response whose first byte is `<` or you write HTML into `.png` files and discover it as a corrupt upload three steps later.

Playwright's npm package may not resolve from a scratch dir with no package.json even though browsers are installed. Resolve it from an existing install via `createRequire` — [[reference_ui_screenshot_harness]] has one at `~/.cc-assistant/wcag/node_modules`.

Related: [[feedback_never_hand_copy_post_bodies]], [[reference_ui_screenshot_harness]]
