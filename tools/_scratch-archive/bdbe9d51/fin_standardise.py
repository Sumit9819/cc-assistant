# -*- coding: utf-8 -*-
"""Standardise the financing wording left behind by the claim sweep.

Three contexts, three forms:
  body sentence   -> "Financing is available on approved credit."
  nav sidebar     -> "Financing Options"   (siblings are page names: Mini Excavators, Warranty Coverage)
  section heading -> "Financing Available" (already consistent on all 6 category pages, untouched)

Queues in batches of 20. Run:  --batch 1 [--apply]   then  --batch 2 [--apply]
"""
import json, io, os, re, copy, subprocess, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
APPLY = "--apply" in sys.argv
BATCH = 1
if "--batch" in sys.argv: BATCH = int(sys.argv[sys.argv.index("--batch") + 1])
SIZE = 20

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
            if "result" not in m: return {"_err": str(m.get("error"))[:300]}
            txt = " ".join(c.get("text","") for c in m["result"].get("content",[]))
            try: return json.loads(txt)
            except Exception: return {"_raw": txt[:300]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"fs","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

MACHINES = [1319,1476,1518,1532,1551,1568,1585,1602,1616,1630,1648,1668,1684,
            1699,1710,1993,2089,2134,2396,3358,3994]

# (pid, find, repl, kind, note)
JOBS = []
for pid in MACHINES:
    JOBS.append((pid, ">Financing</a> is available.", ">Financing</a> is available on approved credit.",
                 "body", "machine page price paragraph"))
for pid in (39, 43):
    JOBS.append((pid, "Financing is available with flexible terms, making",
                 "Financing is available on approved credit, making", "body", "pricing FAQ answer"))
for pid in (3658, 3678, 3264, 3635):
    JOBS.append((pid, "Financing available", "Financing Options", "nav", "sidebar / related links label"))

REASON = ("Consistency pass after the claim-accuracy sweep. Removing the interest-rate figures left the site "
          "saying the same thing fifteen different ways, from a bare \"Financing is available.\" to \"Financing "
          "Options\". This settles it into three forms by context: body sentences read \"Financing is available "
          "on approved credit\", sidebar and related-links labels read \"Financing Options\" to match their "
          "siblings, which are page names, and the six category-page headings keep \"Financing Available\" and "
          "are not touched. \"On approved credit\" promises no rate and no term, states that there is a credit "
          "decision, and is the standard phrase in this industry. Exact-string replacement only: no other "
          "wording, markup or styling changes.")

def apply_to_tree(tree, find, repl):
    """returns list of (widget_id, settings_payload, before, after)"""
    hits = []
    def walk(nodes):
        for n in nodes:
            st = n.get("settings", {}) or {}
            for k, v in list(st.items()):
                if k.startswith("_"): continue
                if isinstance(v, str) and find in v:
                    hits.append((n["id"], {k: v.replace(find, repl)}, v, v.replace(find, repl)))
                elif isinstance(v, list) and v and isinstance(v[0], dict):
                    newlist = copy.deepcopy(v); touched = False; b = a = ""
                    for it in newlist:
                        for ik, iv in list(it.items()):
                            if isinstance(iv, str) and find in iv:
                                b = iv; a = iv.replace(find, repl); it[ik] = a; touched = True
                    if touched:
                        hits.append((n["id"], {k: newlist}, b, a))
            walk(n.get("elements", []))
    walk(tree)
    return hits

work = []
for pid, find, repl, kind, note in JOBS:
    r = call("export_elementor_data", {"post_id": pid})
    if not r or "raw_data" not in r:
        print(f"  !! {pid}: export failed"); continue
    hits = apply_to_tree(json.loads(r["raw_data"]), find, repl)
    if not hits:
        print(f"  -- {pid}: phrase not present, skipped ({note})"); continue
    for wid, payload, before, after in hits:
        n = note if len(hits) == 1 else f"{note} ({'structured data' if payload.get('html') else 'visible text'})"
        work.append((pid, wid, payload, before, after, kind, n))
    if len(hits) > 1:
        print(f"  ** {pid}: {len(hits)} widgets carry this sentence (visible answer + FAQ structured data); both queued so they stay in sync")

start, end = (BATCH - 1) * SIZE, BATCH * SIZE
sel = work[start:end]
print(f"\ntotal edits found: {len(work)} | batch {BATCH} = items {start+1}-{min(end,len(work))} ({len(sel)} cards)\n")
for pid, wid, payload, before, after, kind, note in sel:
    flat_b = ' '.join(re.sub(r'<[^>]+>', ' ', before).split())
    flat_a = ' '.join(re.sub(r'<[^>]+>', ' ', after).split())
    i = 0
    while i < min(len(flat_b), len(flat_a)) and flat_b[i] == flat_a[i]: i += 1
    print(f"  {pid:5} [{wid:10}] {kind:4} {note}")
    print(f"        -> ...{flat_a[max(0,i-60):i+70]}...")
    if APPLY:
        res = call("draft_update_elementor_widget", {
            "post_id": pid, "widget_id": wid, "settings": payload,
            "summary": f"[FINANCING WORDING {BATCH}] {note}: use the standard phrasing",
            "reasoning": REASON})
        print(f"        queued -> {res.get('pending_id') if isinstance(res, dict) else res}")

if not APPLY:
    print("\n(dry run. add --apply to queue this batch)")
elif end < len(work):
    print(f"\nnext: --batch {BATCH+1} --apply  ({len(work)-end} left)")
else:
    print("\nall batches queued.")
proc.stdin.close(); proc.terminate()
