"""Pipe-tests for cc_gate.py: feeds the exact stdin payloads Claude Code sends
and asserts the decisions. Run: python .claude/hooks/test_cc_gate.py"""
import json, os, subprocess, sys, tempfile, time, shutil

HERE = os.path.dirname(os.path.abspath(__file__))
GATE = os.path.join(HERE, "cc_gate.py")
SID = "pipetest-" + str(int(time.time()))
SRV = "cc-assistant-sids-ponds-com"
fails = 0


def run(mode, payload):
    payload.setdefault("session_id", SID)
    r = subprocess.run([sys.executable, GATE, mode], input=json.dumps(payload), capture_output=True, text=True, timeout=60)
    assert r.returncode == 0, f"exit {r.returncode}: {r.stderr}"
    return json.loads(r.stdout) if r.stdout.strip() else {}


def check(label, cond, extra=""):
    global fails
    print(("PASS  " if cond else "FAIL  ") + label + (f"  {extra}" if (extra and not cond) else ""))
    if not cond:
        fails += 1


def denied(out):
    return out.get("hookSpecificOutput", {}).get("permissionDecision") == "deny"


tool = lambda t: f"mcp__{SRV}__{t}"

# 1. mutating tool before whoami -> deny
o = run("pre", {"tool_name": tool("draft_update_seo_meta"), "tool_input": {"post_id": 12, "logical_key": "title", "value": "x"}})
check("write before whoami denied", denied(o) and "whoami" in o["hookSpecificOutput"]["permissionDecisionReason"], json.dumps(o))

# 2. read-only tool before whoami -> allowed (no output)
o = run("pre", {"tool_name": tool("list_posts"), "tool_input": {}})
check("read-only tool never gated", o == {}, json.dumps(o))

# 3. whoami recorded -> non-render write allowed
run("post", {"tool_name": tool("whoami"), "tool_input": {}, "tool_response": {"content": [{"type": "text", "text": "{}"}]}})
o = run("pre", {"tool_name": tool("draft_create_redirect"), "tool_input": {"from": "/a", "to": "/b"}})
check("after whoami, non-render write allowed", o == {}, json.dumps(o))

# 4. render-affecting write without probe -> deny naming the post
o = run("pre", {"tool_name": tool("draft_update_elementor_widget"), "tool_input": {"post_id": 77, "widget_id": "abc", "settings": {}}})
check("render edit before render_probe denied", denied(o) and "77" in o["hookSpecificOutput"]["permissionDecisionReason"], json.dumps(o))

# 5. probe on a DIFFERENT post does not unlock
run("post", {"tool_name": tool("render_probe"), "tool_input": {"id": 78}, "tool_response": {}})
o = run("pre", {"tool_name": tool("draft_update_elementor_widget"), "tool_input": {"post_id": 77, "widget_id": "abc", "settings": {}}})
check("probe on another post does not unlock", denied(o), json.dumps(o))

# 6. probe on the post (page_facts, post_id key) unlocks
run("post", {"tool_name": tool("page_facts"), "tool_input": {"post_id": 77}, "tool_response": {}})
o = run("pre", {"tool_name": tool("draft_update_elementor_widget"), "tool_input": {"post_id": 77, "widget_id": "abc", "settings": {}}})
check("probe on the post unlocks", o == {}, json.dumps(o))

# 7. another site is independent
o = run("pre", {"tool_name": f"mcp__cc-assistant-erofirving-com__draft_trash_post", "tool_input": {"post_id": 1}})
check("whoami on one site does not unlock another", denied(o), json.dumps(o))

# 8. refused write (isError) must not count as a mutation; accepted one must
d = os.path.join(tempfile.gettempdir(), "cc-hooks", SID)
run("post", {"tool_name": tool("draft_update_seo_meta"), "tool_input": {"post_id": 12}, "tool_response": {"isError": True, "content": [{"type": "text", "text": "Error: {..}"}]}})
check("refused write not recorded as mutation", not os.path.exists(os.path.join(d, SRV + ".mutated")))
run("post", {"tool_name": tool("draft_update_seo_meta"), "tool_input": {"post_id": 12}, "tool_response": {"content": [{"type": "text", "text": "{\"queued\":true}"}]}})
check("accepted write recorded as mutation", os.path.exists(os.path.join(d, SRV + ".mutated")))

# 9. stop: mutated + no notes -> block naming the site
o = run("stop", {"cwd": os.path.dirname(os.path.dirname(HERE)), "stop_hook_active": False})
check("stop blocked while notes missing", o.get("decision") == "block" and SRV in o.get("reason", ""), json.dumps(o))

# 10. notes written -> stop allowed (brain push may or may not run; it must not block)
run("post", {"tool_name": tool("update_site_memory_notes"), "tool_input": {"notes": "x"}, "tool_response": {}})
marker = os.path.join(tempfile.gettempdir(), "cc-hooks", "brain.last_push")
open(marker, "a").close(); os.utime(marker, None)  # pretend a push just happened so the test stays offline
o = run("stop", {"cwd": os.path.dirname(os.path.dirname(HERE)), "stop_hook_active": False})
check("stop allowed once notes written", o.get("decision") != "block", json.dumps(o))

# 11. stop_hook_active short-circuits (no infinite loop)
o = run("stop", {"stop_hook_active": True})
check("stop_hook_active -> no-op", o == {}, json.dumps(o))

# 12. garbage stdin never breaks the session
r = subprocess.run([sys.executable, GATE, "pre"], input="not json", capture_output=True, text=True)
check("garbage input exits 0 with no decision", r.returncode == 0 and r.stdout.strip() == "")

shutil.rmtree(d, ignore_errors=True)
print("\n" + ("FAILED: %d" % fails if fails else "ALL PASS"))
sys.exit(1 if fails else 0)
