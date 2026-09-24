"""Drive the bridge (bin/mcp-server.php) over stdio JSON-RPC exactly as Claude
Code does and print the session-critical parts of whoami. Read-only.

    python tools/whoami_via_bridge.py [server-name]      (default: first server in .mcp.json)

Credentials come from .mcp.json and are passed as env; they are never printed."""
import json, os, subprocess, sys

PROJECT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
cfg = json.load(open(os.path.join(PROJECT, ".mcp.json"), encoding="utf-8"))["mcpServers"]
server = sys.argv[1] if len(sys.argv) > 1 else next(n for n in cfg if n.startswith("cc-assistant-"))
srv = cfg[server]
env = dict(os.environ)
env.update(srv["env"])
cmd = [srv["command"]] + srv["args"]

p = subprocess.Popen(cmd, cwd=PROJECT, env=env, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding="utf-8")
msgs = [
    {"jsonrpc": "2.0", "id": 1, "method": "initialize", "params": {}},
    {"jsonrpc": "2.0", "id": 2, "method": "tools/list", "params": {}},
    {"jsonrpc": "2.0", "id": 3, "method": "tools/call", "params": {"name": "whoami", "arguments": {}}},
]
out, err = p.communicate("\n".join(json.dumps(m) for m in msgs) + "\n", timeout=180)
for line in out.splitlines():
    if not line.strip():
        continue
    r = json.loads(line)
    rid = r.get("id")
    if rid == 1:
        print("bridge:", r["result"]["serverInfo"])
    elif rid == 2:
        print("tools:", len(r["result"]["tools"]))
    elif rid == 3:
        txt = r["result"]["content"][0]["text"]
        try:
            body = json.loads(txt)
        except Exception:
            print("whoami raw:", txt[:600]); continue
        print("site:", body.get("site_url"), "plugin", body.get("plugin_version"))
        print("version_drift:", body.get("version_drift"))
        ob = body.get("operator_brain", {})
        print("operator_brain:", ob.get("status"), "| bridge:", ob.get("bridge", {}).get("status"), "| local files:", ob.get("local_files"), "| memory dir:", ob.get("local_present"))
        print("action:", ob.get("action"))
        rr = body.get("relevant_rules", {})
        print("relevant_rules: memory_present=%s total=%s hard=%s site=%s dir=%s" % (rr.get("memory_present"), rr.get("total_memories"), rr.get("hard_total"), rr.get("site_total"), rr.get("memory_dir")))
        ra = body.get("session_recap", {}).get("recent_applied")
        print("recent_applied:", "n/a (site pre-0.81)" if ra is None else [(x["id"], x["verification"].get("attention")) for x in ra])
if err.strip():
    print("STDERR:", err[:800])
