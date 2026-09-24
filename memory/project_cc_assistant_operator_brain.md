---
name: project_cc_assistant_operator_brain
description: v0.79.0 Operator Brain BUILT 2026-09-06 — sites store memory+skills+CLAUDE.md+bridge; whoami gives in_sync/local_missing/stale-bridge verdict + relevant_rules; push with bin/brain-sync.php; deploy status per site
metadata: 
  node_type: memory
  type: project
  originSessionId: c4f71213-6189-4708-920d-3ea9eed152af
  modified: 2026-09-06T07:46:19.337Z
---

**Why it exists.** 2026-09-06 the operator reported that a new chat on a second laptop
had "a lot of functions missing" and knew none of the rules learned here. Cause: the
234 memory files, 7 skills, CLAUDE.md and the bridge (bin/) lived on one disk, and
version_drift only warned when the SITE was older than the laptop, never the reverse.
Operator chose "skip the repo" — the sites are the store.

**What shipped (v0.79.0, all tests pass, 71 checks in tests/operator-brain-test.php).**
- Server: `includes/class-rest-operator-kit.php` — option `cc_assistant_operator_brain`
  (gzip+base64 JSON file map + uncompressed path->sha1 index). Routes
  `/operator-kit/brain/status` (GET), `/operator-kit/brain` (GET with prefix/paths,
  POST mode=replace|merge with delete[]), `/operator-kit/bridge` (GET: bin/*.php with
  hashes + bodies). whoami embeds `operator_brain` summary + `bridge_hashes`.
- Bridge: `bin/operator-brain.php` (collect, fingerprint, diff, classify, write-back,
  relevant_rules, push/pull bodies) + tools `operator_brain_status|push|pull`
  (`what=all|memory|skills|project|bridge`, `mode=missing_only|replace`, never deletes,
  never writes the live .mcp.json). whoami decorated with verdict + two-way
  version_drift + `relevant_rules` (HARD/STRICT descriptions + site-token matches).
- CLI: `php bin/brain-sync.php status|push [--only NAME]|pull --from NAME|bootstrap
  --url --user --pass` — fans out over every cc-assistant server in `.mcp.json`.
- Fingerprint = sha256 over sorted "path\n<sha1>\n"; SAME formula both sides (test 1).
- Memory folder key = project path with `[^A-Za-z0-9]` -> `-`, drive letter lowercase.

**Verified live 2026-09-06** via stdio JSON-RPC against sids-ponds (plugin 0.76.2):
163 tools listed, verdict `site_empty` + bridge `unknown` (pre-0.79), 6 HARD + 23 site
rules surfaced, brain routes 404 with the "upload zip" hint, unknown-tool message names
the pull. Push/pull NOT yet exercised against a real site: plugintesting.local was
STOPPED (port 80 refused) and no remote site had 0.79.0 yet.

**Workspace moved 2026-09-06 to `D:\cc-assistant`** (operator stopped running local WordPress).
Layout: `wp-content/plugins/cc-assistant/` (source + bridge; nesting kept so every relative
path stays valid), `php/` (standalone PHP, see [[reference_local_php]]), `.mcp.json` (9 sites,
plugintesting dropped), `memory/` (this folder, via `.claude/settings.local.json`
autoMemoryDirectory), `.claude/skills/` (project skills; ~/.claude/skills still holds the same 7,
duplicates to prune), `.claude/hooks/cc_gate.py`, `.claude/settings.hooks.json` (rename to
settings.json), `dist/`, `tools/whoami_via_bridge.py`, README.md. GSC warehouse stays at
`~/.cc-assistant/warehouse` (1.4 GB, re-syncable). Verified from the new folder: full test suite
on the standalone PHP, hook pipe-tests, bridge whoami against sids-ponds reading
memory_dir=D:/cc-assistant/memory (241 memories).

**Deploy status.** Current zip: `D:\cc-assistant\dist\cc-assistant-0.81.1.zip` (122 files,
tests excluded). Earlier zips on D:\ root (0.79.0, 0.80.0, 0.81.0) are superseded. 0.80 added the
Elementor effective-value guard ([[reference_elementor_settings_silently_inert]]); 0.81 made the
session gate HARD (409 until whoami within 8h), added the open-loop guard on Sessions notes
(`acknowledge_open_loops`), `recent_applied` verdicts in whoami, and the harness hooks
`.claude/hooks/cc_gate.py` (per-session deny before whoami / render_probe; Stop blocks while
notes lag; auto brain push). The hooks config could NOT be installed by me: the auto-mode
classifier blocked writing `.claude/settings.json`; the ready file is `.claude/settings.hooks.json`
and the operator renames it. Brain now carries project/.claude/settings.json + hooks/.
Needed on every site before the brain works there; then MCP restart (bin/ changed);
then `php bin/brain-sync.php push` from this laptop to seed all sites. Until a site has
0.79.0, `operator_brain_status` there returns site_unreachable/404 by design.

**How to apply.** Every session: read whoami.operator_brain.status and act on its
`action`. After editing memory/skills: run brain-sync push before stopping. On a new
laptop: follow `operator_kit.new_machine_steps` (PowerShell bridge fetch -> bootstrap).
Related: [[reference_plugin_dev_vs_remote_deploy]], [[reference_per_site_design_skills]],
[[feedback_no_guessing_epistemic_discipline]], [[project_wp_plugin]].
