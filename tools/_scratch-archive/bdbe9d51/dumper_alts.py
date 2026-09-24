# -*- coding: utf-8 -*-
"""Dumper-lineup image pass: 29 attachment alts (every image visually verified)
+ 6 hero fixes (5 wrong-machine swaps, 1 empty hero fill)."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"dalts","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

ALT_REASON = ("Dumper-lineup image pass (continuation of the approved MT1350 batch): attachment-level alt "
    "fixes every page rendering this file. Image content verified by downloading and viewing the photo "
    "before writing the alt; model named only where the photo sits in that model's own product gallery.")

ALTS = [
 # MT1750 (tracked)
 (129,  "MT1750 tracked mini dumper side view with self-loading shovel"),
 (130,  "MT1750 tracked mini dumper with hopper raised on scissor lift"),
 (2005, "MT1750 tracked mini dumper three-quarter view in travel position"),
 (2006, "MT1750 tracked mini dumper with gas engine and operator platform"),
 (2008, "MT1750 tracked mini dumper with hopper lifted high on scissor arms"),
 (2012, "MT1750 tracked mini dumper dumping with self-loading shovel raised"),
 # MT2200 (tracked, drop-side box)
 (2017, "MT2200 tracked dumper with drop-side dump box"),
 (2018, "MT2200 tracked dumper with dump box tipped and side panel open"),
 (2019, "MT2200 tracked dumper close-up of raised dump box and tipping frame"),
 (2021, "MT2200 tracked dumper rear view with drop-side box"),
 (2022, "MT2200 tracked dumper dumping with drop side open"),
 (2023, "MT2200 tracked dumper with gas engine, front three-quarter view"),
 (2025, "MT2200 tracked dumper with dump box raised showing tip frame"),
 (2024, "MT2200 tracked dumper with box tipped for discharge"),
 # MT2200HL (tracked high-lift)
 (135,  "MT2200HL high lift dumper side view in travel position"),
 (136,  "MT2200HL high lift dumper with large hopper, three-quarter view"),
 (134,  "MT2200HL high lift dumper with hopper raised on scissor lift for high dumping"),
 # TT570 page (wheeled line; filenames disagree on exact model, so model-neutral)
 (95,   "Mammoth wheeled mini dumper three-quarter view with handlebars"),
 (97,   "Mammoth wheeled mini dumper side view with dump skip"),
 (93,   "Mammoth wheeled mini dumper with skip in travel position"),
 (94,   "Close-up of Mammoth wheeled mini dumper handle and skip mount"),
 # TT900 (tracked walk-behind)
 (2065, "TT900 mini track dumper side view with walk-behind handles"),
 (2061, "TT900 mini track dumper with hopper tipped forward"),
 (2063, "TT900 mini track dumper front view with gas engine"),
 (2060, "TT900 mini track dumper dumping, three-quarter view"),
 (2062, "TT900 mini track dumper rear three-quarter view"),
 # MT2850 (ride-on track carrier)
 (124,  "MT2850 track carrier with drop-side flat bed"),
 (126,  "MT2850 track carrier with bed tipped for dumping"),
 (125,  "MT2850 ride-on track carrier side view with operator seat"),
 # MT1350CB (tracked concrete buggy)
 (2192, "MT1350CB tracked concrete buggy with polyethylene tub"),
 (2193, "MT1350CB tracked concrete buggy rear view with gas engine"),
 (1983, "MT1350CB tracked concrete buggy with tub tipped to pour"),
]
n = len(ALTS)
for i, (att, alt) in enumerate(ALTS, 1):
    r = call("draft_update_postmeta", {"post_id": att, "meta_key": "_wp_attachment_image_alt",
        "value": alt, "summary": f"[DUMPERS alt {i}/{n}] Attachment {att}: {alt}", "reasoning": ALT_REASON})
    print(f"alt {att} -> {r.get('pending_id') if isinstance(r, dict) else r}")

B = "https://mammothmachinery.ca/wp-content/uploads"
HEROES = [
 (1668, "1e01854", 129,  f"{B}/2025/09/imgi_65_Machinewh7.png",
  "MT1750 tracked mini dumper side view with self-loading shovel",
  "MT1750 hero: fill the EMPTY image widget with the machine's own side profile",
  "The hero image widget on the MT1750 page is empty (no attachment set). Filled with the machine's own side-profile gallery shot, visually verified."),
 (1699, "f2fe301", 2017, f"{B}/2026/04/DSC09073.png",
  "MT2200 tracked dumper with drop-side dump box",
  "MT2200 hero shows the WRONG machine (wheeled) - replace with its own photo",
  "Same shared-placeholder bug as the approved MT1350 fix: hero shows a wheeled dumper but the MT2200 is a track dumper (its own title says so). Replaced with its cleanest gallery shot."),
 (1648, "7d0696f", 135,  f"{B}/2025/09/imgi_45_Untitled-design-6.png",
  "MT2200HL high lift dumper side view in travel position",
  "MT2200HL hero shows the WRONG machine (wheeled) - replace with its own photo",
  "Shared-placeholder bug: hero shows a wheeled dumper; the MT2200HL is a tracked high-lift machine. Replaced with its own side profile, visually verified."),
 (1993, "1e01854", 2065, f"{B}/2026/04/DSC09241.png",
  "TT900 mini track dumper side view with walk-behind handles",
  "TT900 hero shows the WRONG machine (wheeled) - replace with its own photo",
  "Shared-placeholder bug: hero shows a wheeled dumper; the TT900 is a mini TRACK dumper (page title). Replaced with its own side profile, visually verified."),
 (1684, "26ff0ec", 124,  f"{B}/2025/09/imgi_49_Untitled-design-6-1-1024x1024-1.png",
  "MT2850 track carrier with drop-side flat bed",
  "MT2850 hero shows the WRONG machine (wheeled) - replace with its own photo",
  "Shared-placeholder bug: hero shows a wheeled dumper; the MT2850 is a ride-on track carrier. Replaced with its own drop-side bed shot, visually verified."),
 (2089, "f2fe301", 2192, f"{B}/2026/04/DSC09326.png",
  "MT1350CB tracked concrete buggy with polyethylene tub",
  "MT1350CB hero shows the WRONG machine (wheeled dumper) - replace with the actual buggy",
  "Shared-placeholder bug: hero shows a wheeled dumper; the MT1350CB is a tracked concrete buggy with a white poly tub. Replaced with its own product shot, visually verified. Supports the concrete-buggy SERP cluster (1,277 impressions) just re-snippeted."),
]
for pid, wid, att, url, alt, summary, reasoning in HEROES:
    r = call("draft_update_elementor_widget", {"post_id": pid, "widget_id": wid,
        "settings": {"image": {"url": url, "id": att, "alt": alt, "source": "library", "size": ""}},
        "summary": f"[DUMPERS hero] {summary}", "reasoning": reasoning + " NOT swapped: eTT900 (its wheeled hero is plausibly the correct electric wheeled model) and MT2850CB (no photos of its own machine exist in the library - client photo request)."})
    print(f"hero {pid}/{wid} -> {r.get('pending_id') if isinstance(r, dict) else r}")
proc.stdin.close(); proc.terminate()
