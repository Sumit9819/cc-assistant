# -*- coding: utf-8 -*-
"""Verify the claim sweep landed: for every page touched, re-export the SAVED
Elementor data and assert (a) no 'find' string survives, (b) the 'repl' text is
present. Reads the rule tables straight out of claim_fix.py so they cannot drift."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"vfy","version":"1"}}})+"\n"); proc.stdin.flush()
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
    ns.update({})
    exec(re.search(rf'^{name} = \[.*?^\]$', src, re.S | re.M).group(0), ns)
ALL = ns["RATES"] + ns["WARRANTY"] + ns["FOOTPRINT"] + ns["ABOUT"]

log = json.load(open("claim_fix_log.json", encoding="utf-8"))
# per page: which find-strings should be gone, which repl-strings should be present
want = {}
for e in log:
    want.setdefault((e["pid"], e["label"]), set())
for e in log:
    b, a = e["before"], e["after"]
    for find, repl in ALL:
        if find in b and find not in a:
            want[(e["pid"], e["label"])].add((find, repl))

FINANCING_PID = 16
bad = 0; ok = 0
for (pid, label) in sorted(want.keys()):
    r = call("export_elementor_data", {"post_id": pid})
    if not r or "raw_data" not in r:
        print(f"  !! {pid} {label}: export failed"); bad += 1; continue
    blob = r["raw_data"]
    survivors = [f for f, _ in want[(pid, label)] if f in blob]
    missing = [rp for _, rp in want[(pid, label)] if rp and rp not in blob]
    if survivors or missing:
        bad += 1
        print(f"  FAIL {pid} {label}")
        for f in survivors: print(f"        old text SURVIVED: {f[:80]!r}")
        for m in missing:   print(f"        new text MISSING : {m[:80]!r}")
    else:
        ok += 1
        print(f"  ok   {pid:5} {label:18} {len(want[(pid,label)])} replacement(s) confirmed")

print(f"\npages verified clean: {ok} | pages with problems: {bad}")

# residual sweep: any flagged number left anywhere outside the financing page
print("\n=== residual scan: flagged figures still present anywhere ===")
PAT = re.compile(r'1\.99|0% ?APR|72 ?month|98% ?[Aa]pprov|powertrain|Powertrain|assembly hub|over 100 dedicated|autonomous heavy|four decades|since 1984', re.I)
pages = sorted({pid for pid, _ in want.keys()}) + [FINANCING_PID]
for pid in pages:
    r = call("export_elementor_data", {"post_id": pid})
    if not r or "raw_data" not in r: continue
    text = re.sub(r'<[^>]+>', ' ', r["raw_data"])
    hits = set()
    for m in PAT.finditer(text):
        seg = ' '.join(text[max(0, m.start()-55):m.end()+55].split())
        hits.add(seg)
    if hits:
        tag = " (financing page - deliberate)" if pid == FINANCING_PID else ""
        print(f"  {pid}{tag}:")
        for h in sorted(hits): print(f"      ...{h}...")
proc.stdin.close(); proc.terminate()
