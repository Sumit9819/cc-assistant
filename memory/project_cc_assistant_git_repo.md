---
name: project_cc_assistant_git_repo
description: D:\cc-assistant became a local git repo 2026-09-24 (commit 40e52ec, 4629 files); .gitignore excludes secrets, browser profiles, backups, models, images; one mcp.json copy held all 9 site app passwords; GitHub target = github.com/Sumit9819/cc-assistant (private, operator account Sumit9819); operator runs remote/push commands (auto mode blocks them)
metadata:
  type: project
---

**2026-09-24:** `git init` on D:\cc-assistant, first commit 40e52ec (4,629 text files,
43 MB). Operator approved a PRIVATE GitHub repo; not pushed yet because this machine
has no `gh` CLI and no GitHub credentials (git identity is sumit-focus /
sumit@focusyourfinance.com from global config).

**Why the .gitignore is strict:**
- `reports/implementation-0.89.0-2026-09-09/c-project-mcp.before.json` was a full copy of
  `.mcp.json` with all 9 sites' live app passwords. Now covered by `**/mcp.json`,
  `**/*mcp*.before.json`.
- `tools/card-generator/featured/.chrome-profile/` is a copy of the signed-in Gemini
  Chrome profile (live Google session cookies). `backups/` also holds a browser profile.
- Models (*.onnx, 350 MB), php/ runtime, dist/ zips, and all rendered images are excluded;
  specs are the source of truth.

**How to apply:** before EVERY commit, re-run the known-secret scan: load every
PASS/KEY/TOKEN/SECRET value from .mcp.json and D:\faceless-studio\.env and assert no staged
file contains one. Pattern-only scans false-positive on English ("just care that...") and
miss real values. Never widen the ignore list's secret section. Related:
[[project_cc_assistant_operator_brain]], [[reference_tool_locations]].
