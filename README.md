# cc-assistant operator workspace

Everything needed to run, develop and deploy the cc-assistant WordPress plugin and its
Claude Code bridge, in one folder. No WordPress runs here. The MCP servers in `.mcp.json`
talk to the live sites over REST with application passwords.

## Layout

| Path | What | Notes |
|---|---|---|
| `wp-content/plugins/cc-assistant/` | Plugin source: WP side (`includes/`, `admin/`, `cc-assistant.php`) and bridge (`bin/`) | The nesting keeps every relative path in `.mcp.json`, the hooks and the brain tools unchanged |
| `php/` | Standalone PHP 8.2 (php.exe, DLLs, `ext/` with curl, openssl, sqlite3, mbstring) | Copied out of Local by Flywheel so the bridge no longer depends on Local being installed |
| `.mcp.json` | The 9 site servers, each running the bridge with this folder's PHP | Contains application passwords. Never commit or share |
| `CLAUDE.md` | Session protocol for Claude Code in this folder | Loaded automatically |
| `memory/` | Claude Code auto-memory for this project (234 files + `MEMORY.md` index + topic indexes) | Pointed at by `.claude/settings.local.json` `autoMemoryDirectory` |
| `.claude/skills/` | Project skills: global design system, six per-site design skills, canva-production | Loaded automatically as project skills |
| `.claude/hooks/cc_gate.py` | Harness gate: deny writes before whoami, deny render edits before render_probe, block Stop while notes lag, auto brain push | Activated by `.claude/settings.json` |
| `.claude/settings.hooks.json` | The hooks config, ready | Rename to `.claude/settings.json` to activate (see below) |
| `dist/` | Built plugin zips | Upload to each site: Plugins > Add New > Upload |
| `tools/` | `whoami_via_bridge.py` smoke test | Drives the bridge over stdio exactly as Claude Code does |
| `tools/card-generator/` | In-body infographic generator for irvingwellnessclinic (spec, render, WebP, upload, body patch) | Has its own README. The 914 files in `out/` are the sources of the live cards |
| `tools/_scratch-archive/` | Scripts and specs rescued from eight session scratchpads, uncurated | Promote reusable pieces into `tools/<name>/`, then delete here |
| `tools/playwright-probes/` | 16 browser measurement scripts (sids-ponds carousel, Font Awesome audit, screenshots) | Run from `%USERPROFILE%\.cc-assistant\wcag` or with NODE_PATH pointing at its node_modules |

Sibling projects on D: (not part of this workspace): `D:\gsc-tools` (GSC API toolchain),
`D:\faceless-studio` (video), `D:\email-platform`, `D:\cf-ai-assistant` (VS Code extension),
`D:\_from-local-sites` (holding folder for everything non-WordPress from the old Local Sites root).

The GSC warehouse (SQLite, 1.4 GB, re-syncable) stays at `%USERPROFILE%\.cc-assistant\warehouse\`.
Set `CC_WAREHOUSE_DIR` in a server's env in `.mcp.json` to move it.

## First run in this folder

```
mv .claude/settings.hooks.json .claude/settings.json
```

Then start Claude Code here, call whoami on any site, and confirm `operator_brain.status`
and `operator_brain.bridge.status` are both `in_sync`.

## Daily commands (run from this folder)

```
php/php.exe -d extension_dir=php/ext -d extension=curl -d extension=openssl -d extension=sqlite3 wp-content/plugins/cc-assistant/bin/brain-sync.php status
php/php.exe -d extension_dir=php/ext -d extension=curl -d extension=openssl -d extension=sqlite3 wp-content/plugins/cc-assistant/bin/brain-sync.php push
python tools/whoami_via_bridge.py cc-assistant-sids-ponds-com
```

## Releasing the plugin

1. Edit under `wp-content/plugins/cc-assistant/`. Bump `Version:` and `CC_ASSISTANT_VERSION` in
   `cc-assistant.php`, `CC_MCP_VERSION` in `bin/mcp-server.php`, and `readme.txt`.
2. Tests: `CC_TEST_PHP=D:/cc-assistant/php/php.exe bash wp-content/plugins/cc-assistant/tests/run.sh`
3. Zip: `python wp-content/plugins/cc-assistant/build-zip.py` writes
   `wp-content/plugins/cc-assistant-<version>.zip`; move it to `dist/`.
4. Upload the zip on every site, restart the MCP servers (bin/ changes load only on restart),
   then `brain-sync.php push`.

## New machine

Fetch `bin/` from any site (`GET /wp-json/cc-assistant/v1/operator-kit/bridge`, see the
operator_kit tool's `new_machine_steps`), then
`php bin/brain-sync.php bootstrap --url <site> --user <user> --pass "<app password>"`.
It restores memory, skills, hooks, CLAUDE.md and writes `.mcp.json` from the stored template.
