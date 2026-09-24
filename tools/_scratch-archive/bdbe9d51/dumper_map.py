# -*- coding: utf-8 -*-
"""Map every image URL -> attachment id across the dumper machine pages,
and identify each page's hero image widget (id + current attachment)."""
import json, io, os, subprocess, sys, re

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)["cc-assistant-mammothmachinery-ca"]
env = dict(os.environ); env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    text=True, encoding="utf-8", bufsize=1)
_mid = [0]
def call(name, args):
    _mid[0] += 1; mid = _mid[0]
    proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":mid,"method":"tools/call","params":{"name":name,"arguments":args}})+"\n"); proc.stdin.flush()
    while True:
        l = proc.stdout.readline()
        if not l: return None
        l = l.strip()
        if not l: continue
        try: m = json.loads(l)
        except Exception: continue
        if m.get("id") == mid:
            if "result" in m:
                txt = " ".join(c.get("text","") for c in m["result"].get("content",[]))
                try: return json.loads(txt)
                except Exception: return {"_raw": txt[:200]}
            return {"_error": str(m.get("error"))[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"dmap","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

PAGES = {1668:"MT1750",1699:"MT2200",1648:"MT2200HL",1476:"TT570",1993:"TT900",
         1319:"eTT900",1684:"MT2850",2396:"MT2850CB",2089:"MT1350CB"}

for pid, name in PAGES.items():
    exp = call("export_elementor_data", {"post_id": pid})
    if not exp or "raw_data" not in exp:
        print(f"{pid} {name}: EXPORT FAILED"); continue
    raw = exp["raw_data"]
    tree = json.loads(raw)
    print(f"\n===== {pid} {name}")
    # url->id map from any {"url":...,"id":N} object
    seen = {}
    for m in re.finditer(r'\{"url":"(https:[^"]+?uploads[^"]+?)","id":(\d+)', raw):
        url = m.group(1).replace("\\/", "/")
        fn = url.split("/")[-1]
        if fn not in seen:
            seen[fn] = m.group(2)
    for fn, aid in sorted(seen.items()):
        print(f"  {aid:>5}  {fn}")
    # hero image widget
    def walk(nodes):
        for n in nodes:
            if n.get("widgetType") == "image":
                img = (n.get("settings", {}) or {}).get("image") or {}
                print(f"  HERO-CANDIDATE widget {n['id']}: att {img.get('id')} {str(img.get('url','')).split('/')[-1]}")
            walk(n.get("elements", []))
    walk(tree)
proc.stdin.close(); proc.terminate()
