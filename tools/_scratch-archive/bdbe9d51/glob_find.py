import json, io, os, subprocess, sys, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)["cc-assistant-mammothmachinery-ca"]
env = dict(os.environ); env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding="utf-8", bufsize=1)
mid=[0]
def call(n,a):
    mid[0]+=1; i=mid[0]
    proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":i,"method":"tools/call","params":{"name":n,"arguments":a}})+"\n"); proc.stdin.flush()
    while True:
        l=proc.stdout.readline().strip()
        if not l: continue
        try: m=json.loads(l)
        except: continue
        if m.get("id")==i:
            txt=" ".join(c.get("text","") for c in m["result"].get("content",[]))
            try: return json.loads(txt)
            except: return {"_raw":txt[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"gf","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l=proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id")==9000: break
        except: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

# machine pages that showed the identical APR sentence
for pid,label in [(1710,"MT1350"),(1616,"XC-20MT"),(1568,"XL-100MT")]:
    raw = call("export_elementor_data", {"post_id": pid})["raw_data"]
    tree = json.loads(raw)
    print(f"\n===== {pid} {label}")
    # find global widgets
    def walk(nodes, depth=0):
        for n in nodes:
            wt = n.get("widgetType")
            st = n.get("settings", {}) or {}
            if wt == "global" or n.get("templateID"):
                print(f"   GLOBAL widget id={n['id']} templateID={n.get('templateID')} keys={sorted(st.keys())[:6]}")
            # look for the APR sentence inline
            blob = json.dumps(st, ensure_ascii=False)
            if "1.99" in blob or "0% APR" in blob:
                for m in re.finditer(r'.{60}1\.99.{80}', blob):
                    print(f"   INLINE in widget {n['id']} [{wt}]: ...{m.group(0)[:170]}...")
            walk(n.get("elements", []), depth+1)
    walk(tree)
proc.stdin.close(); proc.terminate()
