# -*- coding: utf-8 -*-
"""Find every financing phrase left on the site, with enough context to tell a
short LABEL from a body SENTENCE, so the standardisation maps them correctly."""
import json, io, os, re, subprocess, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)["cc-assistant-mammothmachinery-ca"]
env = dict(os.environ); env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding="utf-8", bufsize=1)
mid = [0]
def call(n, a):
    mid[0] += 1; i = mid[0]
    proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":i,"method":"tools/call","params":{"name":n,"arguments":a}})+"\n"); proc.stdin.flush()
    while True:
        l = proc.stdout.readline()
        if not l: return None
        l = l.strip()
        if not l: continue
        try: m = json.loads(l)
        except Exception: continue
        if m.get("id") == i:
            if "result" not in m: return {"_err": str(m.get("error"))[:200]}
            txt = " ".join(c.get("text","") for c in m["result"].get("content",[]))
            try: return json.loads(txt)
            except Exception: return {"_raw": txt[:300]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"fd","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

D = json.load(open("report_data.json", encoding="utf-8"))
PAT = re.compile(r'[Ff]inancing (Available|available|Options|is available)', re.S)
out = {}
for pid in sorted(int(k) for k in D.keys()):
    r = call("export_elementor_data", {"post_id": pid})
    if not r or "raw_data" not in r: continue
    tree = json.loads(r["raw_data"])
    rows = []
    def walk(nodes):
        for n in nodes:
            st = n.get("settings", {}) or {}
            for k, v in st.items():
                pairs = []
                if isinstance(v, str): pairs = [(k, v)]
                elif isinstance(v, list) and v and isinstance(v[0], dict):
                    pairs = [(f"{k}[{i}].{ik}", iv) for i, it in enumerate(v)
                             for ik, iv in it.items() if isinstance(iv, str)]
                for kk, vv in pairs:
                    if kk.startswith("_") or "json" in vv[:40].lower(): continue
                    if PAT.search(vv):
                        rows.append({"wid": n["id"], "type": n.get("widgetType"),
                                     "key": kk, "len": len(vv), "val": vv})
            walk(n.get("elements", []))
    walk(tree)
    if rows:
        out[pid] = {"title": D[str(pid)]["title"], "rows": rows}
        print(f"\n### {pid} {D[str(pid)]['title'][:50]}")
        for r_ in rows:
            kind = "LABEL " if r_["len"] < 60 else "BODY  "
            flat = ' '.join(re.sub(r'<[^>]+>', ' ', r_["val"]).split())
            print(f"  {kind}[{r_['wid']} {r_['type']} {r_['key']}] len={r_['len']}")
            print(f"        {flat[:150]}")
json.dump(out, open("fin_hits.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print(f"\npages with a financing phrase: {len(out)} | fields: {sum(len(v['rows']) for v in out.values())}")
proc.stdin.close(); proc.terminate()
