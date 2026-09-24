# -*- coding: utf-8 -*-
"""Verify the boss's three paragraphs against the LIVE homepage, plus count the
real dealer locations. No assumptions: dump every matching field verbatim."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"hcc","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

def fields(pid):
    """yield (widget_id, widgetType, key, value) for every string field"""
    r = call("export_elementor_data", {"post_id": pid})
    if not r or "raw_data" not in r:
        print(f"  !! export failed for {pid}"); return
    tree = json.loads(r["raw_data"])
    out = []
    def walk(nodes):
        for n in nodes:
            st = n.get("settings", {}) or {}
            for k, v in st.items():
                if isinstance(v, str):
                    out.append((n["id"], n.get("widgetType", n.get("elType")), k, v))
                elif isinstance(v, list) and v and isinstance(v[0], dict):
                    for it in v:
                        for ik, iv in it.items():
                            if isinstance(iv, str):
                                out.append((n["id"], n.get("widgetType", n.get("elType")), f"{k}.{ik}", iv))
            walk(n.get("elements", []))
    walk(tree)
    return out

def plain(v):
    return ' '.join(re.sub(r'<[^>]+>', ' ', v).split())

PROBES = [
    ("DEALER COVERAGE", r'Quebec|Qu\u00e9bec|Western Canada|coast to coast|spans|Maritimes|nationwide|across Canada'),
    ("WARRANTY SCOPE",  r'powertrain|Powertrain'),
    ("WARRANTY START",  r'ship[s]? with|day one|register|registration|30 day|30-day'),
    ("YEARS CLAIM",     r'31\+? years|four decades|1984|since 19'),
    ("DEALER COUNT",    r'over 100|100 dedicated|service partners|dealer network'),
]

for pid, name in ((11, "HOME"), (1986, "WARRANTY PAGE")):
    fl = fields(pid) or []
    print(f"\n{'#'*70}\n### {name} (post {pid}) - {len(fl)} text fields\n{'#'*70}")
    for label, pat in PROBES:
        rx = re.compile(pat)
        found = False
        for wid, wt, k, v in fl:
            if k.startswith("_") or k in ("css_classes",): continue
            if rx.search(v):
                if not found: print(f"\n--- {label} ---"); found = True
                print(f"  [{wid} {wt} {k}]")
                for m in rx.finditer(v):
                    seg = plain(v[max(0, m.start()-130):m.end()+130])
                    print(f"      ...{seg}...")
        if not found:
            print(f"\n--- {label} --- NO MATCH")

# real dealer count: the Find a Dealer page
print(f"\n{'#'*70}\n### DEALER PAGE: count locations\n{'#'*70}")
lp = call("list_posts", {"search": "dealer", "per_page": 20})
cands = []
for p in (lp.get("posts") if isinstance(lp, dict) else []) or []:
    print(f"  {p.get('id')} {p.get('title')} {p.get('url')}")
    cands.append(p.get("id"))
proc.stdin.close(); proc.terminate()
