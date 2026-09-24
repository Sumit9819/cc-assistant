# -*- coding: utf-8 -*-
"""Verify Blog #4 import by SIGNATURE (import regenerated element ids)."""
import json, io, os, subprocess, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
HERE = os.path.dirname(os.path.abspath(__file__))
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
                except Exception: return {"_raw": txt[:400]}
            return {"_error": str(m.get("error"))[:400]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"b4verify2","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

exp = call("export_elementor_data", {"post_id": 4023})
live = json.loads(exp["raw_data"])

def flat(tree):
    out = []
    def walk(nodes):
        for n in nodes:
            out.append(n.get("settings", {}) or {})
            walk(n.get("elements", []))
    walk(tree)
    return out

S = flat(live)
def count(pred): return sum(1 for st in S if pred(st))
def W(st): return st.get("width") or {}

sigs = [
 ("box 1300px + tablet 100% + 24px side pad", count(lambda st: W(st).get("size")==1300 and W(st).get("unit")=="px"
      and (st.get("width_tablet") or {}).get("size")==100
      and (st.get("padding") or {}).get("left")=="24"), 1),
 ("sidebar col 26%",  count(lambda st: W(st).get("unit")=="%" and W(st).get("size")==26), 1),
 ("content col 68%",  count(lambda st: W(st).get("unit")=="%" and W(st).get("size")==68), 1),
 ("content gap row=16", count(lambda st: (st.get("flex_gap") or {}).get("row")=="16"), 1),
 ("section pad 80/96", count(lambda st: (st.get("padding") or {}).get("top")=="80" and (st.get("padding") or {}).get("bottom")=="96"), 1),
 ("row align stretch", count(lambda st: st.get("flex_align_items")=="stretch"), 1),
 ("sticky top/desktop/160/parent/z1", count(lambda st: st.get("sticky")=="top" and st.get("sticky_offset")==160
      and st.get("sticky_parent")=="yes" and st.get("sticky_on")==["desktop"] and st.get("_z_index")==1), 1),
]
ok = 0
for name, got, want in sigs:
    good = got == want
    ok += good
    print(f"{'OK ' if good else 'FAIL'} {name}: found {got}, expected {want}")
print(f"signature checks: {ok}/{len(sigs)}")

raw = exp["raw_data"]
em = raw.count("\u2014") + raw.count("\\u2014")
print(f"em dashes in live tree: {em}")
for token in ["How to Choose the Right Mini Excavator", "$37,999", "8,488 lbs", "Six-Step Buying Path", "what-size-mini-excavator"]:
    print(f"{'PRESENT' if token in raw else 'MISSING'} {token}")
proc.stdin.close(); proc.terminate()
