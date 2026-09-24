# -*- coding: utf-8 -*-
"""Roll blog layout v2 (fixed two-column) to all blog posts.
- Posts 4006/4012: patch pristine local trees (blog01/blog02) -> re-import (undoes forced bolding too).
- Old posts: export CURRENT live tree (preserves em-dash fixes), patch by wrap-bug
  signature (28%->26%, 70%->68%, 1300px box, content gap 16), re-import.
All imports: enforce_bold_headings=False. Every import = one pending card.
"""
import json, io, os, subprocess, sys, copy

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
SERVER = "cc-assistant-mammothmachinery-ca"
HERE = os.path.dirname(os.path.abspath(__file__))

cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)[SERVER]
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
                except Exception: return {"_raw": txt[:300]}
            return {"_error": str(m.get("error"))[:300]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"rollout","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

def patch_tree(tree):
    """Apply layout v2. Returns (n_width_fixes, n_box, n_gap)."""
    stats = {"w28": 0, "w70": 0, "box": 0, "gap": 0, "pad": 0}
    def walk(nodes, parent=None):
        for n in nodes:
            st = n.setdefault("settings", {})
            w = st.get("width") or {}
            if isinstance(w, dict) and w.get("unit") == "%":
                if w.get("size") == 28: w["size"] = 26; stats["w28"] += 1
                elif w.get("size") == 70: w["size"] = 68; stats["w70"] += 1
            # the boxed wrap: full-width container with 64px side padding directly
            # under the white body section (holds the sidebar+content row)
            pad = st.get("padding") or {}
            if (n.get("elType") == "container" and isinstance(pad, dict)
                    and pad.get("left") == "64" and pad.get("right") == "64"
                    and pad.get("top") == "0" and pad.get("bottom") == "0"):
                st["width"] = {"unit": "px", "size": 1300, "sizes": []}
                st["width_tablet"] = {"unit": "%", "size": 100, "sizes": []}
                st["padding"] = {"unit": "px", "top": "0", "right": "24", "bottom": "0", "left": "24", "isLinked": False}
                stats["box"] += 1
            # content column: tiny 6px widget gap -> 16px
            g = st.get("flex_gap") or {}
            if isinstance(g, dict) and g.get("row") == "6":
                st["flex_gap"] = {"column": "0", "row": "16", "isLinked": False, "unit": "px", "size": 16}
                stats["gap"] += 1
            # body section breathing room 72/88 -> 80/96
            if (n.get("elType") == "container" and isinstance(pad, dict)
                    and pad.get("top") == "72" and pad.get("bottom") == "88"):
                st["padding"] = {"unit": "px", "top": "80", "right": "0", "bottom": "96", "left": "0", "isLinked": False}
                stats["pad"] += 1
            walk(n.get("elements", []), n)
    walk(tree)
    return stats

def do_import(pid, tree, label):
    return call("import_elementor_data", {
        "post_id": pid,
        "raw_data": json.dumps(tree, ensure_ascii=False),
        "enforce_bold_headings": False,
        "summary": label,
        "reasoning": ("Blog layout v2 rollout (operator-approved after visual sign-off on the live Blog #3): "
            "fixes the column-wrap bug (28%+70%+48px gap never fit on one row, so the sidebar/content "
            "two-column design always collapsed to stacked full-width), constrains the body to a 1300px box "
            "(~850px readable text column per the design system), widget gap 6->16px, and no forced heading "
            "bolding. Content is byte-identical to what is live; only layout container settings change."),
    })

results = []

# --- Part A: blogs #1 and #2 from pristine local trees ---
for pid, fname, name in [(4006, "blog01_tree.json", "financing guide"), (4012, "blog02_tree.json", "warranty guide")]:
    tree = json.load(open(os.path.join(HERE, fname), encoding="utf-8"))
    stats = patch_tree(tree)
    r = do_import(pid, tree, f"[LAYOUT] Blog layout v2: {name} (post {pid})")
    results.append((pid, name, stats, r.get("pending_id") or r))
    json.dump(tree, open(os.path.join(HERE, fname.replace(".json", "_v2.json")), "w", encoding="utf-8"), ensure_ascii=False)

# --- Part B: the 8 older posts, from their CURRENT live trees ---
OLD = [(3264, "mini dumper guide"), (3635, "wheeled vs tracked"), (3658, "what size excavator"),
       (3678, "skid vs track loader"), (3771, "concrete buggy"), (3862, "high dump dumper"),
       (3890, "excavator attachments"), (3903, "mini skid vs full size")]
for pid, name in OLD:
    exp = call("export_elementor_data", {"post_id": pid})
    if not exp or "raw_data" not in exp:
        results.append((pid, name, "EXPORT FAILED", str(exp)[:120])); continue
    tree = json.loads(exp["raw_data"])
    stats = patch_tree(tree)
    if stats["w28"] == 0 and stats["w70"] == 0:
        results.append((pid, name, stats, "SKIPPED - no wrap-bug signature (different design era)")); continue
    r = do_import(pid, tree, f"[LAYOUT] Blog layout v2: {name} (post {pid})")
    results.append((pid, name, stats, r.get("pending_id") or r))

print(f"{'POST':>5}  {'NAME':24} {'PATCH (w28/w70/box/gap/pad)':28} RESULT")
for pid, name, stats, res in results:
    sstr = stats if isinstance(stats, str) else f"{stats['w28']}/{stats['w70']}/{stats['box']}/{stats['gap']}/{stats['pad']}"
    print(f"{pid:>5}  {name:24} {sstr:28} {res}")
proc.stdin.close(); proc.terminate()
