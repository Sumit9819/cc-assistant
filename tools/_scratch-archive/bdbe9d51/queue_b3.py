# -*- coding: utf-8 -*-
"""Queue Batch 3 em-dash fixes (b3_fixes.json) via the cc-assistant MCP
stdio bridge. Draft-only: every call lands in the Pending Changes inbox."""
import json, io, os, subprocess, sys

ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
SERVER = "cc-assistant-mammothmachinery-ca"
TITLES = {3635: "Wheeled vs Tracked guide", 3678: "Skid Steer vs Track Loader guide",
          3264: "What Is a Mini Dumper guide", 3658: "What Size Mini Excavator guide"}

fixes = json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), "b3_fixes.json"), encoding="utf-8"))

cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)[SERVER]
env = dict(os.environ)
env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
                        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                        text=True, encoding="utf-8", bufsize=1)

def send(o):
    proc.stdin.write(json.dumps(o) + "\n"); proc.stdin.flush()

def read_id(w):
    while True:
        line = proc.stdout.readline()
        if not line:
            return None
        line = line.strip()
        if not line:
            continue
        try:
            m = json.loads(line)
        except Exception:
            continue
        if m.get("id") == w:
            return m

send({"jsonrpc": "2.0", "id": 1, "method": "initialize",
      "params": {"protocolVersion": "2024-11-05", "capabilities": {},
                 "clientInfo": {"name": "b3queue", "version": "1"}}})
read_id(1)
send({"jsonrpc": "2.0", "method": "notifications/initialized"})

ok = fail = 0
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
for i, f in enumerate(fixes):
    key = list(f["settings"].keys())[0]
    args = {
        "post_id": f["post_id"],
        "widget_id": f["widget_id"],
        "settings": f["settings"],
        "summary": f"Em-dash cleanup batch 3: {TITLES[f['post_id']]}, widget {f['widget_id']} ({f['n']} fix{'es' if f['n']>1 else ''})",
        "reasoning": ("Prose em dashes removed per the operator's 2026-08-04 ruling (remove in sentences/between words; "
                      "keep in quotes and lone table cells; ranges use en dashes). Punctuation-only: replaced with comma, "
                      "period, colon, or parentheses chosen per sentence; all wording, links, en-dash ranges, and claims "
                      "byte-identical. Generated from the page export with exact-match assertions; zero em dashes remain "
                      "in this widget after the fix."),
    }
    send({"jsonrpc": "2.0", "id": 100 + i, "method": "tools/call",
          "params": {"name": "draft_update_elementor_widget", "arguments": args}})
    r = read_id(100 + i)
    txt = ""
    if r and "result" in r:
        txt = " ".join(c.get("text", "") for c in r["result"].get("content", []))
    elif r:
        txt = json.dumps(r.get("error", r))
    try:
        d = json.loads(txt)
    except Exception:
        d = {}
    pid = d.get("pending_id")
    if pid:
        ok += 1
        print(f"  ok   post {f['post_id']} {f['widget_id']} ({key}, {f['n']} fixes) -> pending {pid}")
    else:
        fail += 1
        print(f"  FAIL post {f['post_id']} {f['widget_id']} -> {txt[:220]}")

print(f"\nqueued={ok} failed={fail}")
proc.stdin.close(); proc.terminate()
