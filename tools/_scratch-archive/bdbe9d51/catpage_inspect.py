# -*- coding: utf-8 -*-
"""Inspect flagged contrast widgets on pages 45 + 39: print each widget's own
settings (size, text) and the ancestor chain's backgrounds to separate real
violations from over-image false positives. Read-only."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"inspect","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

TARGETS = {
 45: ["35c20e8a","4b05750c","c45edd1","2a36135e","1d4cd478","5ccd9f2","61465eb7",
      "ff9ed37"],   # + non-bold heading
 39: ["52e8bc18","1ebd3c91","34bf54a8","6cf4ee88","45791cc0","3a3c605b","71d1c2d8",
      "4fa81b82","6b2bca2a","729e2d98","7dd8a5b7","1d2677e0","36aee674","1cb5740e"],
}
# also find the heading-skip widgets by title text
SKIP_TITLES = {45: "Up To 10 ft 11 in Dig", 39: "Cummins Diesel Power"}
# and the adjacent same-bg FAQ sections
for pid, wids in TARGETS.items():
    exp = call("export_elementor_data", {"post_id": pid})
    tree = json.loads(exp["raw_data"])
    json.dump(tree, open(os.path.join(HERE, f"cat{pid}_tree.json"), "w", encoding="utf-8"), ensure_ascii=False)

    # build parent map
    parent = {}
    def walk(nodes, par):
        for n in nodes:
            parent[n["id"]] = par
            walk(n.get("elements", []), n)
    walk(tree, None)
    byid = {}
    def idx(nodes):
        for n in nodes:
            byid[n["id"]] = n
            idx(n.get("elements", []))
    idx(tree)

    def bg(n):
        st = n.get("settings", {}) or {}
        out = {}
        for k in ("background_background","background_color","background_image","background_overlay_background","background_overlay_color","background_overlay_opacity"):
            v = st.get(k)
            if v:
                if isinstance(v, dict): v = v.get("url") or v.get("id") or str(v)[:40]
                out[k.replace("background_","")] = str(v)[:60]
        return out

    print(f"\n===== PAGE {pid} =====")
    for wid in wids:
        n = byid.get(wid)
        if not n:
            print(f"{wid}: NOT FOUND"); continue
        st = n.get("settings", {}) or {}
        title = (st.get("title") or st.get("editor") or "")[:60].replace("\n"," ")
        size = st.get("typography_font_size") or {}
        hdr = st.get("header_size", "")
        print(f"\n{wid} [{n.get('widgetType') or n.get('elType')}] '{title}' size={size.get('size','?')} tag={hdr} color={st.get('title_color','-')}")
        chain, cur = [], parent.get(wid)
        while cur:
            b = bg(cur)
            if b: chain.append(f"  ^ {cur['id']}: {b}")
            cur = parent.get(cur["id"])
        print("\n".join(chain) if chain else "  ^ (no ancestor backgrounds)")

    # locate the h4 skip widget
    want = SKIP_TITLES[pid]
    for wid, n in byid.items():
        st = n.get("settings", {}) or {}
        if st.get("title") and want.lower() in str(st.get("title")).lower():
            print(f"\nSKIP-WIDGET {wid}: '{str(st.get('title'))[:60]}' tag={st.get('header_size')} size={(st.get('typography_font_size') or {}).get('size','?')}")

    # root sections + resolved bg for uniqueness fix
    print("\nroot sections:")
    for i, sec in enumerate(tree):
        b = bg(sec)
        # first heading text as label
        label = ""
        def firsth(nodes):
            for m in nodes:
                stt = m.get("settings", {}) or {}
                if m.get("widgetType") == "heading" and stt.get("title"):
                    return str(stt["title"])[:40]
                r = firsth(m.get("elements", []))
                if r: return r
        label = firsth([sec]) or ""
        print(f"  [{i}] {sec['id']} '{label}' {b}")
proc.stdin.close(); proc.terminate()
