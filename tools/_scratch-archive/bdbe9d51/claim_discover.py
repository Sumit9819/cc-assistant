import json, io, os, subprocess, sys, re, collections
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
        l=proc.stdout.readline()
        if not l: return None
        l=l.strip()
        if not l: continue
        try: m=json.loads(l)
        except: continue
        if m.get("id")==i:
            if "result" not in m: return {"_err": str(m.get("error"))[:150]}
            txt=" ".join(c.get("text","") for c in m["result"].get("content",[]))
            try: return json.loads(txt)
            except: return {"_raw":txt[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"cd","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l=proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id")==9000: break
        except: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

PAGES = {
 3291:"about", 11:"home", 1986:"warranty",
 45:"mini-excavators", 39:"wheel-loaders", 43:"mini-skidsteers", 41:"tracked-dumpers", 37:"wheeled-dumpers", 47:"track-loaders",
 16:"financing", 29:"equipment",
 1710:"MT1350",1668:"MT1750",1699:"MT2200",1648:"MT2200HL",1476:"TT570",1993:"TT900",1684:"MT2850",2396:"MT2850CB",2089:"MT1350CB",1319:"eTT900",3862:"high-dump",
 1616:"XC20",1602:"XC27",1585:"XC35",3358:"XL50",1568:"XL100",1551:"XL120",1630:"XL3000",1532:"WL4500",1518:"WL7500",2134:"TL5500",3994:"MTL1000",
 3771:"blog-buggy",3890:"blog-attach",3903:"blog-minivfull",3678:"blog-skidvtrack",3658:"blog-size",3635:"blog-wheelvtrack",3264:"blog-dumper",4012:"blog-warranty",4023:"blog4",4026:"blog5",4028:"blog6",4031:"blog7",4032:"blog8",
}
PAT = re.compile(r'1\.99|0%\s*APR|0% APR|72\s*[Mm]onth|98%|powertrain|Powertrain|assembly hub|over 100|autonomous|Canadian Manufacturers|four decades|Western Canada|active from day one|1984|mining|forestry', re.I)

found = collections.defaultdict(list)
for pid, label in PAGES.items():
    r = call("export_elementor_data", {"post_id": pid})
    if not r or "raw_data" not in r:
        print(f"  !! {pid} {label}: export failed"); continue
    tree = json.loads(r["raw_data"])
    def walk(nodes):
        for n in nodes:
            st = n.get("settings", {}) or {}
            for k, v in list(st.items()):
                if isinstance(v, str) and PAT.search(v):
                    found[(pid,label)].append((n["id"], n.get("widgetType"), k, v))
                elif isinstance(v, list):
                    for idx, item in enumerate(v):
                        if isinstance(item, dict):
                            for ik, iv in item.items():
                                if isinstance(iv, str) and PAT.search(iv):
                                    found[(pid,label)].append((n["id"], n.get("widgetType"), f"{k}[{idx}].{ik}", iv))
            walk(n.get("elements", []))
    walk(tree)
proc.stdin.close(); proc.terminate()

json.dump({f"{k[0]}|{k[1]}": v for k,v in found.items()}, open("claim_hits.json","w",encoding="utf-8"), ensure_ascii=False, indent=1)
print(f"pages with stored hits: {len(found)}   total hit fields: {sum(len(v) for v in found.values())}")
for (pid,label), hits in sorted(found.items(), key=lambda x:-len(x[1]))[:14]:
    print(f"\n--- {pid} {label}: {len(hits)} fields")
    for wid, wt, key, val in hits[:4]:
        flat = ' '.join(val.split())
        print(f"    {wid} [{wt}] {key}: {flat[:150]}")
