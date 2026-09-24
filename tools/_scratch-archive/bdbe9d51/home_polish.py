# -*- coding: utf-8 -*-
"""Homepage design-polish batch from audit_page_design findings (score 64).
Fixes: 6 green-on-white ghost buttons -> green fill + dark text; 2 green kickers on
light -> ink; Pledge section missing dark bg (invisible white text) -> #1a1c1c;
heading-order skip -> kicker demoted to div; 2 non-bold headings -> 700;
6 fleet-card image alts (widget + attachment, two-render-path rule)."""
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
                except Exception: return {"_raw": txt[:200]}
            return {"_error": str(m.get("error"))[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"polish","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

tree = json.load(open(os.path.join(HERE, "home11_tree.json"), encoding="utf-8"))
def find(nodes, wid):
    for n in nodes:
        if n["id"] == wid: return n
        r = find(n.get("elements", []), wid)
        if r: return r

def wu(wid, settings, summary, reasoning):
    r = call("draft_update_elementor_widget", {"post_id": 11, "widget_id": wid,
        "settings": settings, "summary": summary, "reasoning": reasoning})
    print(f"{wid} -> {r.get('pending_id') or r}")

# 1) six ghost buttons: green fill + dark text (WCAG: green-on-white text is 2.75:1)
BTNS = ["2e1926f2","37819f67","1335ab35","2bf4973a","5f4f05f2","1c9b32fa"]
for i, b in enumerate(BTNS, 1):
    wu(b, {"background_color": "#01B51B", "button_text_color": "#141414"},
       f"[HOME {i}/21] Fleet card button: green fill + dark text (was green text on white, 2.75:1)",
       "audit_page_design flagged all six 'View Series' ghost buttons at 2.75:1 contrast (brand green text on white cards; WCAG needs 4.5:1). Per the site design rule, brand green is used as button FILL with dark #141414 text, which passes. Hover states already exist on all six.")

# 2) two green kickers on light backgrounds
wu("3fa83e05", {"title_color": "#141414"},
   "[HOME 7/21] 'Why Mammoth' kicker: ink instead of green-on-white",
   "13px green kicker on white = 2.75:1 (fails WCAG for small text). Ink #141414 passes; green kickers stay reserved for dark sections per the design system.")
wu("25597997", {"title_color": "#141414"},
   "[HOME 8/21] 'Common Questions' kicker: ink instead of green-on-light",
   "Same fix as 7/21: green 13px on #f3f4f6 is 2.5:1. Ink passes.")

# 3) Pledge section: restore the missing dark background (text is currently invisible)
wu("34da46df", {"background_background": "classic", "background_color": "#1a1c1c"},
   "[HOME 9/21] Mammoth Pledge section: restore dark background (white text is currently invisible)",
   "The Pledge section has NO background while its content is white text on a translucent white card - built as a dark band (like the stats section) and the background was lost, so this content renders white-on-white today. #1a1c1c matches the sibling dark sections, makes the white text legible, and also fixes the audit's adjacent-identical-sections flag.")

# 4) heading-order skip: 'The Mammoth Pledge' H4 -> styled div (it is a kicker, not an outline heading)
wu("2f5e89c6", {"header_size": "div"},
   "[HOME 10/21] 'The Mammoth Pledge' label: div instead of H4 (fixes heading-level skip)",
   "audit flagged H2 -> H4 skip. This 14px label is a kicker, not a document heading; rendering it as a styled div keeps the identical look and removes it from the heading outline. Zero visual change.")

# 5) bold weights
wu("2bf1ace3", {"typography_font_weight": "700"},
   "[HOME 11/21] Hero H1: explicit 700 weight",
   "H1 renders at kit-inherit weight (non-bold, audit fail). Explicit 700 per the design system; size and color unchanged. (Its white-on-white contrast flag is a false positive: it sits over the hero photo with dark overlay, which the auditor cannot see.)")
wu("c94f28f", {"typography_typography": "custom", "typography_font_weight": "700"},
   "[HOME 12/21] 'Manufacturing Partners' heading: explicit 700 weight",
   "Second kit-inherit heading from the audit; explicit bold per design system.")

# 6) fleet-card image alts: widget-level (merge full image object) + attachment-level
ALTS = {
 "1d248bfd": ("Mammoth X-Loader mini skid steer", 3085),
 "4f29d925": ("Mammoth X-Cavator mini excavator", 3086),
 "7a83a8e7": ("Mammoth tracked mini dumper", 3087),
 "40aab2a":  ("Mammoth compact wheel loader", 3088),
 "4bead6d4": ("Mammoth full-size track loader", 3089),
 "30a86785": ("Mammoth TT660 wheeled mini dumper", 3090),
}
i = 12
for wid, (alt, att) in ALTS.items():
    i += 1
    node = find(tree, wid)
    img = dict(node["settings"].get("image") or {})
    img["alt"] = alt; img.setdefault("source", "library"); img.setdefault("size", "")
    wu(wid, {"image": img},
       f"[HOME {i}/21] Fleet card alt text: {alt}",
       "audit: 6 of 8 homepage images missing alt. Alt set on the widget image object (URL and attachment id preserved from the current tree); category identified from the card's own heading.")
for alt, att in ALTS.values():
    r = call("draft_update_postmeta", {"post_id": att, "meta_key": "_wp_attachment_image_alt", "value": alt,
        "summary": f"[HOME alt] Attachment {att}: {alt}",
        "reasoning": "Companion to the widget-level alt (two-render-path rule: whichever path Elementor uses, the alt resolves)."})
    print(f"att {att} -> {r.get('pending_id') or r}")
proc.stdin.close(); proc.terminate()
