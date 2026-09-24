# -*- coding: utf-8 -*-
"""Two leftovers from the first sweep: the APR rules stripped the rate but left the
'up to 72 months' term claim behind inside two accordion answers."""
import json, io, os, copy, subprocess, sys

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
            if "result" not in m: return {"_err": str(m.get("error"))[:250]}
            txt = " ".join(c.get("text","") for c in m["result"].get("content",[]))
            try: return json.loads(txt)
            except Exception: return {"_raw": txt[:250]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"cf2","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

JOBS = [
    (39, "1b073777", "with flexible terms up to 72 months, making", "with flexible terms, making",
     "Wheel Loaders financing FAQ"),
    (43, "3aa1ddb6", "with terms up to 72 months, making", "with flexible terms, making",
     "Mini Skid Steers financing FAQ"),
]
WHY = ("Second pass of the claim-accuracy sweep. The first pass removed the unverifiable APR figure from "
       "this answer but left the '72 months' term claim in the same sentence, which is the other half of "
       "the same unsupported financing promise. Standing decision: those figures stay on the financing "
       "page only. Only this phrase changes; the rest of the answer, its markup and the other accordion "
       "items are byte-identical.")

for pid, wid, find, repl, label in JOBS:
    r = call("export_elementor_data", {"post_id": pid})
    tree = json.loads(r["raw_data"])
    found = None
    def walk(nodes):
        global found
        for n in nodes:
            if n["id"] == wid:
                items = copy.deepcopy(n["settings"]["ekit_accordion_items"])
                hits = 0
                for it in items:
                    for k, v in list(it.items()):
                        if isinstance(v, str) and find in v:
                            it[k] = v.replace(find, repl); hits += 1
                found = (items, hits)
                return
            walk(n.get("elements", []))
    walk(tree)
    if not found or found[1] == 0:
        print(f"  !! {pid}/{wid}: phrase not found, nothing queued"); continue
    items, hits = found
    res = call("draft_update_elementor_widget", {
        "post_id": pid, "widget_id": wid,
        "settings": {"ekit_accordion_items": items},
        "summary": f"[CLAIMS 2/2] {label}: remove leftover 72-month term claim",
        "reasoning": WHY})
    print(f"  {pid}/{wid} ({hits} item) -> {res.get('pending_id') if isinstance(res, dict) else res}")

# confirm the warranty page strip really carries the new wording
b = call("export_elementor_data", {"post_id": 1986})["raw_data"]
print("\nwarranty 1986 strip: 'Chassis' present =", "Chassis" in b, "| 'Powertrain' present =", "Powertrain" in b)
proc.stdin.close(); proc.terminate()
