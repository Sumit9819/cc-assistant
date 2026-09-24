"""Drive the LOCAL bridge (bin/mcp-server.php) over stdio JSON-RPC exactly as
Claude Code does, call whoami on the plugintesting site, and print only the
v0.79 fields. Credentials are read from .mcp.json and passed as env; they are
never printed."""
import json, os, subprocess, sys

PROJECT = r"C:\Users\sumit\Local Sites\plugintesting\app\public"
server = sys.argv[1] if len(sys.argv) > 1 else "cc-assistant-plugintesting"
cfg = json.load(open(os.path.join(PROJECT, ".mcp.json"), encoding="utf-8"))["mcpServers"][server]
env = dict(os.environ)
env.update(cfg["env"])
cmd = [cfg["command"]] + cfg["args"]

p = subprocess.Popen(cmd, cwd=PROJECT, env=env, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding="utf-8")
msgs = [
    {"jsonrpc": "2.0", "id": 1, "method": "initialize", "params": {}},
    {"jsonrpc": "2.0", "id": 2, "method": "tools/list", "params": {}},
    {"jsonrpc": "2.0", "id": 3, "method": "tools/call", "params": {"name": "whoami", "arguments": {}}},
    {"jsonrpc": "2.0", "id": 4, "method": "tools/call", "params": {"name": "operator_brain_status", "arguments": {}}},
    {"jsonrpc": "2.0", "id": 5, "method": "tools/call", "params": {"name": "no_such_tool", "arguments": {}}},
]
out, err = p.communicate("\n".join(json.dumps(m) for m in msgs) + "\n", timeout=180)
for line in out.splitlines():
    if not line.strip():
        continue
    r = json.loads(line)
    rid = r.get("id")
    if rid == 1:
        print("serverInfo:", r["result"]["serverInfo"])
    elif rid == 2:
        names = [t["name"] for t in r["result"]["tools"]]
        print("tools:", len(names), "brain tools:", [n for n in names if "brain" in n])
    elif rid in (3, 4, 5):
        txt = r["result"]["content"][0]["text"]
        try:
            body = json.loads(txt)
        except Exception:
            print(f"[{rid}] raw:", txt[:600]); continue
        if rid == 3:
            print("whoami.plugin_version:", body.get("plugin_version"))
            print("whoami.version_drift:", body.get("version_drift"))
            print("whoami.operator_brain:", json.dumps(body.get("operator_brain"), indent=1)[:2500])
            rr = body.get("relevant_rules", {})
            print("whoami.relevant_rules: memory_present=%s total=%s hard=%s site=%s tokens=%s" % (rr.get("memory_present"), rr.get("total_memories"), rr.get("hard_total"), rr.get("site_total"), rr.get("tokens")))
            for row in rr.get("hard_rules", [])[:8]:
                print("   HARD", row["name"], "|", row["description"][:90])
            for row in rr.get("site_rules", [])[:8]:
                print("   SITE", row["name"], "|", row["description"][:90])
        elif rid == 4:
            body.pop("sources", None)
            print("operator_brain_status:", json.dumps(body, indent=1)[:2000])
        else:
            print("unknown tool ->", txt[:300])
if err.strip():
    print("STDERR:", err[:800])
