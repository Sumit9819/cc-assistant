# -*- coding: utf-8 -*-
"""Add the engine carve-out to the three pages that state what the warranty covers
but say nothing about who covers the engine. Pure append: one sentence before the
closing tag, nothing else touched. Engine brands taken from each page's own spec table."""
import json, io, os, copy, subprocess, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
APPLY = "--apply" in sys.argv
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"eco","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

# pid, widget, field ("editor" or the accordion list), anchor text that must be present, sentence
JOBS = [
 (39, "1b073777", "ekit_accordion_items", "hydraulics, chassis and structural components",
  " The engine is covered by Cummins under its own warranty.",
  "Wheel Loaders warranty FAQ", "Cummins (WL4500, TL5500, WL7500, per this page's own spec table)"),
 (43, "3aa1ddb6", "ekit_accordion_items", "hydraulics, chassis and structural components",
  " Engines are covered by Yanmar or Kubota under their own warranty.",
  "Mini Skid Steers warranty FAQ", "Yanmar 3TNV80FT / Kubota D1105 / Yanmar 3TNV80F, per this page's own spec table"),
 (4026, "d57edcff", "editor", "hydraulics, chassis and structure",
  " The engine is covered by Yanmar or Kubota under its own warranty.",
  "Landscaping jobs guide, warranty paragraph", "Yanmar and Kubota, the engines in the X-Loader range"),
]

def add_sentence(val, anchor, sentence):
    if anchor not in val: return None
    if val.count("</p>") != 1: return None
    return val.replace("</p>", sentence + "</p>")

for pid, wid, field, anchor, sentence, label, brands in JOBS:
    tree = json.loads(call("export_elementor_data", {"post_id": pid})["raw_data"])
    payload = None; before = None; after = None
    def walk(nodes):
        global payload, before, after
        for n in nodes:
            if n["id"] == wid:
                st = n["settings"]
                if field == "editor":
                    before = st["editor"]
                    nv = add_sentence(before, anchor, sentence)
                    if nv: after = nv; payload = {"editor": nv}
                else:
                    items = copy.deepcopy(st[field]); hit = 0
                    for it in items:
                        v = it.get("acc_content", "")
                        if anchor in v:
                            nv = add_sentence(v, anchor, sentence)
                            if nv:
                                before = v; after = nv; it["acc_content"] = nv; hit += 1
                    if hit == 1: payload = {field: items}
                return
            walk(n.get("elements", []))
    walk(tree)
    if not payload:
        print(f"  !! {pid}/{wid}: anchor not found or ambiguous, nothing queued"); continue
    if sentence.strip() in before:
        print(f"  -- {pid}/{wid}: sentence already present, skipped"); continue
    print(f"\n[{pid}] {label}")
    print(f"   BEFORE: ...{' '.join(before[-170:].split())}")
    print(f"   AFTER : ...{' '.join(after[-230:].split())}")
    if APPLY:
        res = call("draft_update_elementor_widget", {
            "post_id": pid, "widget_id": wid, "settings": payload,
            "summary": f"[ENGINE CARVE-OUT] {label}: state who covers the engine",
            "reasoning": (
                "Follow-up to the claim-accuracy sweep. The earlier pass removed the false claim that the "
                "Mammoth warranty covers the powertrain, because the engine IS the powertrain and the warranty "
                "page states engines are covered solely by the original manufacturer. That left this page "
                "accurate but silent: it lists what is covered and names the engine brand in the spec table, "
                "without saying who warrants the engine. This adds one sentence so a buyer forms the correct "
                "expectation on the page where they compare the warranty, matching the disclosure already live "
                "on the home page and the warranty page. Engine brand verified from this page's own content: "
                f"{brands}. Pure addition: one sentence before the closing tag, no existing wording, markup or "
                "styling changed.")})
        print(f"   -> pending {res.get('pending_id') if isinstance(res, dict) else res}")

if not APPLY:
    print("\n(dry run, pass --apply to queue)")
proc.stdin.close(); proc.terminate()
