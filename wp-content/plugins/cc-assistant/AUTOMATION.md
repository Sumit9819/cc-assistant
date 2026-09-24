# Review-only Claude automation

`bin/automation-runner.py` advances a saved job through research, drafting and verification. Claude uses existing site notes, discovers strategy and the public author, researches a niche topic and prepares a draft. The operator reviews the saved result in WordPress. The plugin itself does not start a paid model session or enable a scheduler.

Requirements: Python 3.10+, a current authenticated Claude Code CLI supporting `--permission-mode dontAsk` and `--max-budget-usd`, the complete CC Assistant 0.89 bridge and backend, and the site's existing MCP connection. Ubersuggest is optional and uses the normal Claude OAuth connection. This runner never reads Claude's private token store. Browser transport additionally needs Chrome, Node and Playwright as described in CONNECTION.md. Run only one process per dedicated browser profile.

Put configuration and job state outside the public WordPress directory. Example (replace local paths and the exact existing WordPress server name):

```json
{
  "enabled": false,
  "state_dir": "C:/Users/you/.cc-assistant/automation/example/jobs",
  "mcp_config": "D:/wordpress-project/.mcp.json",
  "wordpress_server": "cc-assistant-example-com",
  "ubersuggest_config": "C:/Users/you/.claude.json",
  "claude_command": ["C:/path/to/claude.exe"],
  "max_run_usd": 1.0,
  "max_job_usd": 3.0,
  "timeout_seconds": 300
}
```

Omit `ubersuggest_config` if disconnected. Configure the cadence and spending limits with the operator before changing `enabled` to true. The runner must use an explicit command list, never a shell command assembled from website text. Python can be selected for bridge updates with `CC_PYTHON_BINARY` if it is not on PATH.

```powershell
python "D:/wordpress-project/wp-content/plugins/cc-assistant/bin/automation-runner.py" --config "C:/private/config.json" --status
python "D:/wordpress-project/wp-content/plugins/cc-assistant/bin/automation-runner.py" --config "C:/private/config.json" --job "review-2026-09-09"
```

Each invocation advances at most one phase. With no `--job`, an unfinished job resumes first; otherwise the UTC date identifies a new job. Repeated invocations do not reopen a completed job with the same ID. A daily invocation normally takes three days to finish research, drafting and verification. A scheduler can invoke phases more often if the agreed cadence and budget permit it. No scheduled task is registered automatically.

The runner reserves the full per-run budget before launch. Retries and interrupted runs retain their reservation, even when actual cost was lower or unknown. The CLI's budget and `total_cost_usd` are client estimates, not a guaranteed invoice ceiling. Ubersuggest usage/credits are separate. Budget exhaustion stops the job. Authentication, missing evidence or review exceptions are reported instead of silently widening permissions.

Claude receives only the selected WordPress and Ubersuggest MCP connections, no shell/file tools, no inherited hooks, and an explicit tool allowlist. The bridge independently enforces that allowlist and allows at most one workflow-bound post-creation attempt per process. Only draft creation and queued SEO metadata are available during drafting; publication, approval, deletion, setting changes and messages are excluded.

Job files and actual tool traces are private local artifacts. A final model sentence cannot establish that a draft was saved: actual post/workflow IDs and a matching verifier response are required for `review_ready`. This verifies records, not clinical accuracy, rendered appearance, indexing or rankings. If a write response is lost, the next phase is read-only reconciliation; there is no automatic redraft. The operator should inspect any uncertain remote result before starting a replacement job.

If a process crashes while holding `runner.lock`, inspect its process and logs before removing that empty lock directory. Do not run two copies or automatically break a lock based only on its age. A local runner requires the computer, authenticated Claude connection and network to remain available; hosting WordPress alone does not provide unattended model execution.

CLI behavior reference: https://code.claude.com/docs/en/headless
