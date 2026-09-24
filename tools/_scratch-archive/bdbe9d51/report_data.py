# -*- coding: utf-8 -*-
"""Build the per-URL before/after dataset for the claim-corrections report.
Uses claim_hits.json (the PRE-change field values, captured before the sweep) and
the rule tables from claim_fix.py, so every row is a real fired replacement."""
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
            except Exception: return {"_raw": txt[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"rpt","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

src = io.open("claim_fix.py", encoding="utf-8").read()
ns = {}
for name in ("RATES", "WARRANTY", "FOOTPRINT", "ABOUT"):
    exec(re.search(rf'^{name} = \[.*?^\]$', src, re.S | re.M).group(0), ns)

def rules_for(pid):
    r = list(ns["WARRANTY"]) + list(ns["FOOTPRINT"])
    if pid != 16: r = list(ns["RATES"]) + r
    if pid == 3291: r = list(ns["ABOUT"]) + r
    return r

hits = json.load(open("claim_hits.json", encoding="utf-8"))
pages = {}
for key in sorted(hits.keys(), key=lambda k: int(k.split("|")[0])):
    pid = int(key.split("|")[0]); label = key.split("|")[1]
    if pid == 16: continue          # financing page: deliberately untouched
    fired = []
    for wid, wt, k, val in hits[key]:
        cur = val
        for find, repl in rules_for(pid):
            if find in cur:
                fired.append((find, repl))
                cur = cur.replace(find, repl)
    if fired:
        pages[pid] = {"label": label, "fired": fired}

# the two second-pass leftovers
pages.setdefault(39, {"label":"wheel-loaders","fired":[]})["fired"].append(
    ("Financing is available with flexible terms up to 72 months, making",
     "Financing is available with flexible terms, making"))
pages.setdefault(43, {"label":"mini-skidsteers","fired":[]})["fired"].append(
    ("Financing is available with terms up to 72 months, making",
     "Financing is available with flexible terms, making"))

out = {}
for pid, d in pages.items():
    p = call("get_post", {"id": pid, "slim": True}) or {}
    seen = []
    for f, r in d["fired"]:
        if (f, r) not in seen: seen.append((f, r))
    out[str(pid)] = {"label": d["label"], "title": p.get("title"), "url": p.get("url"),
                     "type": p.get("type"), "changes": [{"before": f, "after": r} for f, r in seen]}
    print(f"{pid:5} {p.get('title','?')[:44]:44} {len(seen)} change(s)")

json.dump(out, open("report_data.json","w",encoding="utf-8"), ensure_ascii=False, indent=1)
print(f"\npages: {len(out)} | total distinct replacements: {sum(len(v['changes']) for v in out.values())}")
proc.stdin.close(); proc.terminate()
