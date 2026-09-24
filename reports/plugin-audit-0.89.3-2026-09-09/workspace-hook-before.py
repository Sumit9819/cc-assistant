"""cc-assistant harness gate (v0.81). Claude Code hooks for the cc-assistant
MCP servers. Enforcement the model cannot skip: it runs in the harness, per
session, regardless of what the model remembers.

Modes (argv[1]):
  post  PostToolUse on mcp__cc-assistant-*: record what ran this session.
  pre   PreToolUse  on mcp__cc-assistant-*: deny a mutating tool until whoami
        ran on that site this session, and deny a render-affecting edit until
        render_probe/page_facts ran on that post this session.
  stop  Stop: refuse to end the turn while a site was mutated and its Sessions
        note is more than 20 minutes behind; then push the operator brain to
        the sites when local memory/skills changed since the last push.

State: <tmp>/cc-hooks/<session_id>/<marker files>. Nothing here is secret.
Exit codes: always 0; decisions are returned as JSON on stdout.
"""
import json
import os
import re
import subprocess
import sys
import tempfile
import time

MUTATING = re.compile(
    r"^(draft_|propose_|build_|replace_|import_|upload_media$|delete_|create_category$|bulk_|"
    r"reject_pending_change$|refresh_theme_builder_conditions$|polylang_link_translations$|links_rebuild$)"
)
RENDER_AFFECTING = {
    "draft_update_elementor_widget", "draft_add_elementor_widget", "draft_add_elementor_container",
    "draft_remove_elementor_widget", "draft_rebuild_section", "draft_add_section",
    "draft_update_post_content", "draft_patch_post_content", "replace_section_content",
    "draft_update_divi_modules", "draft_remove_divi_module", "draft_add_accordion_item",
    "draft_remove_accordion_item", "draft_update_rank_math_schema", "propose_schema",
    "build_page_from_spec", "draft_update_seo_meta",
}
PROBES = {"render_probe", "page_facts"}
NOTES_GRACE_SECONDS = 20 * 60
PUSH_MIN_INTERVAL = 10 * 60
PUSH_TIMEOUT = 150

TOOL_RE = re.compile(r"^mcp__(cc-assistant-[^_]+(?:_[^_]+)*?)__([a-z_]+)$")


def parse_tool(name):
    """('cc-assistant-sids-ponds-com', 'draft_update_post_content') or (None, None)."""
    if not name or not name.startswith("mcp__cc-assistant-"):
        return None, None
    rest = name[len("mcp__"):]
    server, sep, tool = rest.rpartition("__")
    if not sep:
        return None, None
    return server, tool


def state_dir(session_id):
    d = os.path.join(tempfile.gettempdir(), "cc-hooks", re.sub(r"[^A-Za-z0-9_-]", "_", session_id or "nosession"))
    os.makedirs(d, exist_ok=True)
    return d


def touch(path):
    with open(path, "a"):
        os.utime(path, None)


def mtime(path):
    try:
        return os.path.getmtime(path)
    except OSError:
        return 0.0


def post_id_of(tool_input):
    if not isinstance(tool_input, dict):
        return None
    for k in ("post_id", "id"):
        v = tool_input.get(k)
        if isinstance(v, (int, str)) and str(v).isdigit():
            return str(v)
    return None


def mode_post(payload):
    server, tool = parse_tool(payload.get("tool_name", ""))
    if not server:
        return {}
    d = state_dir(payload.get("session_id"))
    if tool == "whoami":
        touch(os.path.join(d, server + ".whoami"))
    elif tool in PROBES:
        pid = post_id_of(payload.get("tool_input"))
        if pid:
            touch(os.path.join(d, f"{server}.probe.{pid}"))
    elif tool == "update_site_memory_notes":
        touch(os.path.join(d, server + ".notes"))
    elif tool == "operator_brain_push":
        touch(os.path.join(d, "brain.pushed"))
    if MUTATING.match(tool or ""):
        # Only count a write that the server accepted: a 409/422 refusal is
        # not a mutation and must not demand a session note.
        resp = payload.get("tool_response")
        text = json.dumps(resp) if not isinstance(resp, str) else resp
        if '"isError": true' not in text and '"isError":true' not in text and not text.startswith("Error:"):
            touch(os.path.join(d, server + ".mutated"))
    return {}


def deny(reason):
    return {
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": reason,
        }
    }


def mode_pre(payload):
    server, tool = parse_tool(payload.get("tool_name", ""))
    if not server or not tool or not MUTATING.match(tool):
        return {}
    d = state_dir(payload.get("session_id"))
    if not os.path.exists(os.path.join(d, server + ".whoami")):
        return deny(
            f"cc_gate: {tool} refused. No whoami on {server} in THIS chat session. "
            f"Call mcp__{server}__whoami first (working_state, pending inbox, rules, operator_brain, relevant_rules), "
            f"then retry. This is the harness gate, not the plugin's; it keys on the session id."
        )
    if tool in RENDER_AFFECTING:
        pid = post_id_of(payload.get("tool_input"))
        if pid and not os.path.exists(os.path.join(d, f"{server}.probe.{pid}")):
            return deny(
                f"cc_gate: {tool} on post {pid} refused. No render_probe or page_facts for post {pid} on {server} in this session. "
                f"Read the LIVE page first (mcp__{server}__render_probe id={pid}), then retry. Absence claims from parsers alone caused 6 wrong pendings once."
            )
    return {}


def find_php_and_project(cwd):
    """PHP binary from the project's .mcp.json (first cc-assistant server), so the push works on any laptop."""
    mcp = os.path.join(cwd, ".mcp.json")
    try:
        cfg = json.load(open(mcp, encoding="utf-8"))
    except Exception:
        return None
    for name, srv in cfg.get("mcpServers", {}).items():
        if name.startswith("cc-assistant-") and isinstance(srv, dict) and srv.get("command"):
            args = [a for a in srv.get("args", []) if not a.endswith("mcp-server.php")]
            return [srv["command"]] + args
    return None


def newest_mtime(paths):
    newest = 0.0
    for p in paths:
        if os.path.isfile(p):
            newest = max(newest, mtime(p))
        elif os.path.isdir(p):
            for root, dirs, files in os.walk(p):
                dirs[:] = [x for x in dirs if x not in (".git", "node_modules", "__pycache__")]
                for f in files:
                    newest = max(newest, mtime(os.path.join(root, f)))
    return newest


def project_key(path):
    if re.match(r"^[A-Za-z]:", path):
        path = path[0].lower() + path[1:]
    return re.sub(r"[^A-Za-z0-9]", "-", path)


def mode_stop(payload):
    if payload.get("stop_hook_active"):
        return {}
    d = state_dir(payload.get("session_id"))
    cwd = payload.get("cwd") or os.getcwd()

    # 1. Session notes must not lag the last mutation by more than the grace window.
    behind = []
    for f in os.listdir(d):
        if f.endswith(".mutated"):
            server = f[: -len(".mutated")]
            last_mut = mtime(os.path.join(d, f))
            last_note = mtime(os.path.join(d, server + ".notes"))
            if last_mut - last_note > NOTES_GRACE_SECONDS:
                behind.append(server)
    if behind:
        return {
            "decision": "block",
            "reason": "cc_gate: you queued changes on " + ", ".join(sorted(behind)) +
                      " and the site notes are more than 20 minutes behind (or missing). Call update_site_memory_notes on each with one terse line about what changed, then stop.",
        }

    # 2. Push the operator brain when local memory/skills changed since the last push.
    home = os.environ.get("USERPROFILE") or os.environ.get("HOME") or ""
    memory = os.path.join(home, ".claude", "projects", project_key(cwd), "memory")
    # autoMemoryDirectory (project settings.local.json, then user settings) moves memory into the workspace.
    for f in (os.path.join(cwd, ".claude", "settings.local.json"), os.path.join(home, ".claude", "settings.json")):
        try:
            cfg = json.load(open(f, encoding="utf-8"))
            p = cfg.get("autoMemoryDirectory")
            if isinstance(p, str) and p:
                memory = os.path.join(home, p[2:]) if p.startswith("~/") else p
                break
        except Exception:
            continue
    skills = os.path.join(home, ".claude", "skills")
    local_newest = newest_mtime([memory, skills, os.path.join(cwd, "CLAUDE.md"), os.path.join(cwd, ".claude")])
    last_push_marker = os.path.join(tempfile.gettempdir(), "cc-hooks", "brain.last_push")
    last_push = mtime(last_push_marker)
    if local_newest > last_push and time.time() - last_push > PUSH_MIN_INTERVAL:
        php = find_php_and_project(cwd)
        script = os.path.join(cwd, "wp-content", "plugins", "cc-assistant", "bin", "brain-sync.php")
        if php and os.path.isfile(script):
            log_path = os.path.join(tempfile.gettempdir(), "cc-hooks", "brain-push.log")
            try:
                r = subprocess.run(php + [script, "push", "--project", cwd], capture_output=True, text=True, timeout=PUSH_TIMEOUT, cwd=cwd)
                out = (r.stdout or "") + (r.stderr or "")
                with open(log_path, "a", encoding="utf-8") as lf:
                    lf.write(time.strftime("%Y-%m-%d %H:%M:%S") + f" exit={r.returncode}\n{out}\n")
                touch(last_push_marker)
                in_sync = out.count(" in_sync ")
                return {"systemMessage": f"cc_gate: operator brain pushed ({in_sync} site(s) in_sync; details {log_path})"}
            except Exception as e:  # never block the stop on a push problem
                with open(log_path, "a", encoding="utf-8") as lf:
                    lf.write(time.strftime("%Y-%m-%d %H:%M:%S") + f" push failed: {e}\n")
                touch(last_push_marker)
                return {"systemMessage": f"cc_gate: brain push failed ({e}); see {log_path}"}
    return {}


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else ""
    try:
        payload = json.load(sys.stdin)
    except Exception:
        payload = {}
    if not isinstance(payload, dict):
        payload = {}
    try:
        out = {"post": mode_post, "pre": mode_pre, "stop": mode_stop}.get(mode, lambda p: {})(payload)
    except Exception as e:  # a broken gate must never break the session
        out = {"systemMessage": f"cc_gate {mode} error: {e}"}
    if out:
        sys.stdout.write(json.dumps(out))
    return 0


if __name__ == "__main__":
    sys.exit(main())
