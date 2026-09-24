# -*- coding: utf-8 -*-
"""Bridge import of blog05_tree.json into post 4023 (Blog #4, mini excavator chooser).
Tree already carries layout v2 + sticky sidebar. enforce_bold_headings=False."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"b4import","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

tree = json.load(open(os.path.join(HERE, "blog05_tree.json"), encoding="utf-8"))
r = call("import_elementor_data", {
    "post_id": 4026,
    "raw_data": json.dumps(tree, ensure_ascii=False),
    "enforce_bold_headings": False,
    "summary": "[BLOG5 - STEP 5 of 7] Full page design: Landscaping Jobs a Mini Skid Steer Does Best",
    "reasoning": ("Complete Elementor design for Blog #5 (Aug 31 slot), built from the proven blog scaffold with layout v2 "
        "(26/68 two-column, 1300px box, 16px content gap) and the sticky sidebar (desktop, 160px offset, stay-in-parent) "
        "baked in from card one. Content is deliberately scoped to landscaping use cases, gate/lift access numbers, and "
        "cost, and it hands the transactional head term to the category page by exact anchor rather than competing for it. "
        "All specs verified today off the live machine pages: 50MT $27,999 CAD / 1,000 lb / 2,830 lb / 34.5 in; 100MT "
        "$33,999 / 1,200 lb / 3,208 lb / 36 in; 120MT $38,999 / 1,300 lb / 3,345 lb / 35.8 in; hinge pin 75.6-88.6 in; "
        "CII universal plate; 5-year / 3,000-hour warranty. The quote citation carries a source link."),
})
print("import ->", json.dumps({k: r.get(k) for k in ("pending_id","lint","warnings") if isinstance(r, dict) and k in r}, ensure_ascii=False)[:800] if isinstance(r, dict) else r)
proc.stdin.close(); proc.terminate()
