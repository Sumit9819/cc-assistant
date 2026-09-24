# -*- coding: utf-8 -*-
"""Close the whole job: confirm the wording pass landed, confirm the FAQ answer and
its structured data agree, and re-run the claim residual scan one last time."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"fv","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

r = call("list_pending_changes", {})
pend = r.get("pending", []) if isinstance(r, dict) else []
print(f"1. PENDING QUEUE: {len(pend)} cards\n")

MACHINES = [1319,1476,1518,1532,1551,1568,1585,1602,1616,1630,1648,1668,1684,
            1699,1710,1993,2089,2134,2396,3358,3994]
blobs = {}
def blob(pid):
    if pid not in blobs:
        blobs[pid] = call("export_elementor_data", {"post_id": pid})["raw_data"]
    return blobs[pid]

ok = sum("is available on approved credit." in blob(p) for p in MACHINES)
print(f"2. MACHINE PAGES: {ok}/21 say \"Financing is available on approved credit.\"")

print("\n3. PRICING FAQ ANSWERS")
for pid, nm in ((39, "Wheel Loaders"), (43, "Mini Skid Steers")):
    b = blob(pid)
    good = "Financing is available on approved credit, making" in b
    stale = "with flexible terms, making" in b
    print(f"   {nm}: standard phrase = {good} | old phrase left = {stale}")

print("\n4. WHEEL LOADERS: visible answer vs FAQ structured data")
tree = json.loads(blob(39))
vis = sch = None
def walk(ns):
    global vis, sch
    for n in ns:
        if n["id"] == "1b073777":
            for it in n["settings"]["ekit_accordion_items"]:
                c = it.get("acc_content", "")
                if "Financing is available" in c: vis = c
        if n["id"] == "62ed9eb7":
            for m in re.finditer(r'Financing is available[^"]{0,90}', n["settings"]["html"]):
                sch = m.group(0)
        walk(n.get("elements", []))
walk(tree)
def sent(t):
    m = re.search(r'Financing is available[^.]*\.', re.sub(r'<[^>]+>', '', t or ''))
    return m.group(0) if m else None
v, c = sent(vis), sent((sch or "") + ".")
print(f"   visible   : {v}")
print(f"   structured: {c}")
print(f"   IN SYNC   : {bool(v) and bool(c) and v.split(',')[0] == c.split(',')[0]}")

print("\n5. SIDEBAR / RELATED-LINKS LABELS")
for pid in (3264, 3635, 3658, 3678, 3771, 3862, 3890, 3903):
    b = blob(pid)
    has_opt = "Financing Options" in b
    has_old = re.search(r'Financing available(?!\s*:)', b) is not None
    print(f"   {pid}: \"Financing Options\" = {has_opt} | old label left = {has_old}")

print("\n6. FINAL CLAIM RESIDUAL SCAN (all touched pages + financing page)")
D = json.load(open("report_data.json", encoding="utf-8"))
PAT = re.compile(r'1\.99|0% ?APR|72 ?month|98% ?approv|assembly hub|over 100 dedicated|autonomous heavy|four decades|since 1984', re.I)
flagged = []
for pid in sorted(int(k) for k in D.keys()) + [16]:
    t = re.sub(r'<[^>]+>', ' ', blob(pid))
    hits = sorted({' '.join(t[max(0, m.start()-40):m.end()+40].split()) for m in PAT.finditer(t)})
    if hits: flagged.append((pid, hits))
for pid, hits in flagged:
    tag = "financing page, deliberate" if pid == 16 else "UNEXPECTED"
    print(f"   {pid} ({tag}): {len(hits)} hit(s)")
print(f"\n   pages still carrying a questioned figure: {len(flagged)}"
      f"{' (only the financing page)' if len(flagged)==1 and flagged[0][0]==16 else ''}")
proc.stdin.close(); proc.terminate()
