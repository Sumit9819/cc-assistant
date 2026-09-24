# -*- coding: utf-8 -*-
"""Sticky sidebar rollout: on every blog post, (1) row container -> align stretch,
(2) sidebar inner container -> sticky top, desktop only, 110px offset, stay-in-parent.
Queues 2 widget-update cards per post via the pending pipeline."""
import json, io, os, subprocess, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
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
                except Exception: return {"_raw": txt[:200]}
            return {"_error": str(m.get("error"))[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"sticky","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

POSTS = [(4016,"exc vs skid steer"),(4006,"financing"),(4012,"warranty"),(3264,"mini dumper"),
         (3635,"wheeled vs tracked"),(3658,"what size exc"),(3678,"skid vs track loader"),
         (3771,"concrete buggy"),(3862,"high dump"),(3890,"attachments"),(3903,"mini vs full skid")]

STICKY = {"sticky": "top", "sticky_on": ["desktop"], "sticky_offset": 110, "sticky_parent": "yes"}

def find_chain(tree):
    """Return (row_id, sidebar_wrap_id, sticky_target_id) or None."""
    res = {}
    def walk(nodes, parent):
        for n in nodes:
            w = (n.get("settings", {}).get("width") or {})
            if isinstance(w, dict) and w.get("unit") == "%" and w.get("size") == 26 and "row" not in res:
                kids = [c for c in n.get("elements", []) if c.get("elType") == "container"]
                res["row"] = parent["id"] if parent else None
                res["wrap"] = n["id"]
                res["sticky"] = kids[0]["id"] if kids else n["id"]
                res["inner_is_child"] = bool(kids)
            walk(n.get("elements", []), n)
    walk(tree, None)
    return res if "row" in res and res["row"] else None

print(f"{'POST':>5}  {'NAME':20} {'ROW':10} {'STICKY-EL':10} CARDS")
for pid, name in POSTS:
    exp = call("export_elementor_data", {"post_id": pid})
    if not exp or "raw_data" not in exp:
        print(f"{pid:>5}  {name:20} EXPORT FAILED: {str(exp)[:80]}"); continue
    tree = json.loads(exp["raw_data"])
    chain = find_chain(tree)
    if not chain:
        print(f"{pid:>5}  {name:20} SKIP - no 26% sidebar found"); continue
    r1 = call("draft_update_elementor_widget", {
        "post_id": pid, "widget_id": chain["row"],
        "settings": {"flex_align_items": "stretch"},
        "summary": f"[STICKY {name} 1/2] Stretch columns to full article height",
        "reasoning": "Sticky-sidebar rollout (operator request: sidebar disappears on scroll leaving dead space). The row must stretch its columns so the sticky element has room to travel the full article length. Visual change: none by itself."})
    r2 = call("draft_update_elementor_widget", {
        "post_id": pid, "widget_id": chain["sticky"],
        "settings": STICKY,
        "summary": f"[STICKY {name} 2/2] Sidebar follows the reader on scroll",
        "reasoning": "Makes the TOC + warranty card + related links stick to the top (110px offset clears the fixed header, matching the TOC scroll offset) and follow the reader down the article on desktop. Stays inside its column, so it stops cleanly at the section end. Mobile/tablet unchanged (sidebar already stacks above content there)."})
    p1 = r1.get("pending_id") if isinstance(r1, dict) else None
    p2 = r2.get("pending_id") if isinstance(r2, dict) else None
    print(f"{pid:>5}  {name:20} {chain['row']:10} {chain['sticky']:10} {p1}, {p2}" + ("" if (p1 and p2) else f"  ERR {str(r1)[:60]} {str(r2)[:60]}"))
proc.stdin.close(); proc.terminate()
