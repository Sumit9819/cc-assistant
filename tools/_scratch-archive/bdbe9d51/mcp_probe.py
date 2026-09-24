"""Drive the cc-assistant MCP server over stdio using the credentials already
in .mcp.json, so a new tool can be exercised before the MCP client restarts.
Read-only calls only. Never prints the environment."""
import json, io, os, subprocess, sys

ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
SERVER = "cc-assistant-mammothmachinery-ca"

cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
servers = cfg.get("mcpServers", cfg)
s = servers[SERVER]

env = dict(os.environ)
env.update({k: str(v) for k, v in (s.get("env") or {}).items()})

proc = subprocess.Popen(
    [s["command"]] + list(s.get("args") or []),
    cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    text=True, encoding="utf-8", bufsize=1,
)

def send(obj):
    proc.stdin.write(json.dumps(obj) + "\n")
    proc.stdin.flush()

def read_id(want):
    while True:
        line = proc.stdout.readline()
        if not line:
            return None
        line = line.strip()
        if not line:
            continue
        try:
            msg = json.loads(line)
        except Exception:
            continue
        if msg.get("id") == want:
            return msg

send({"jsonrpc": "2.0", "id": 1, "method": "initialize",
      "params": {"protocolVersion": "2024-11-05", "capabilities": {},
                 "clientInfo": {"name": "probe", "version": "1"}}})
init = read_id(1)
print("initialize:", "ok" if init and "result" in init else init)
send({"jsonrpc": "2.0", "method": "notifications/initialized"})

send({"jsonrpc": "2.0", "id": 2, "method": "tools/list", "params": {}})
tl = read_id(2)
names = [t["name"] for t in (tl or {}).get("result", {}).get("tools", [])]
print("tools advertised:", len(names))
for n in ("get_rank_math_schema", "draft_update_rank_math_schema"):
    print("  ", "PRESENT" if n in names else "MISSING", n)

targets = [int(x) for x in sys.argv[1:]] or [1616]
for i, pid in enumerate(targets):
    send({"jsonrpc": "2.0", "id": 100 + i, "method": "tools/call",
          "params": {"name": "get_rank_math_schema", "arguments": {"post_id": pid}}})
    r = read_id(100 + i)
    print("\n===== post", pid, "=====")
    if not r:
        print("no response"); continue
    if "error" in r:
        print("error:", json.dumps(r["error"])[:600]); continue
    for c in r.get("result", {}).get("content", []):
        print(c.get("text", "")[:4000])

proc.stdin.close()
proc.terminate()
err = proc.stderr.read()
if err.strip():
    print("\n[stderr]", err.strip()[:800])
