"""Batch 2 classifier for the five category pages.
Ruling (operator 2026-08-04): remove em dashes in sentences/between words;
KEEP inside quotes + attribution lines; KEEP lone-dash table cells;
numeric ranges become EN dashes.

Output: per-widget classification. AUTO range fixes are applied and the full
fixed setting value is written to fix_<post>_<widget>.json; prose cases are
printed in full for manual rewrite. KEEP cases listed for eyeball check."""
import io, sys, json, re, glob, os

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
BASE = r"C:\Users\sumit\.claude\projects\c--Users-sumit-Local-Sites-plugintesting-app-public\bdbe9d51-2098-4917-93a9-eb1883a6d1fc\tool-results"
FILES = [
    "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785856980258.txt",  # 39
    "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785856985759.txt",  # 41
    "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785856999076.txt",  # 43
    "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785857004405.txt",  # 45
    "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785857010250.txt",  # 37
]
OUT = r"C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\bdbe9d51-2098-4917-93a9-eb1883a6d1fc\scratchpad"

EM = "\u2014"
ENT = re.compile(r"&#8212;|&mdash;")
QUOTECH = ["\u201c", "\u201d", "&#8220;", "&#8221;", "&ldquo;", "&rdquo;"]

def normalize(s):
    return ENT.sub(EM, s)

def is_lone(s):
    return re.fullmatch(r"\s*" + EM + r"\s*", normalize(s)) is not None

def is_attribution(s):
    return re.match(r"\s*(<[^>]+>\s*)*" + EM + r"\s*[A-Z]", normalize(s)) is not None

def in_quotes(s):
    n = normalize(s)
    i = n.find(EM)
    while i != -1:
        before = n[:i]
        opens = sum(before.count(q) for q in QUOTECH[::2]) + before.count("&#8220;")
        closes = sum(before.count(q) for q in QUOTECH[1::2]) + before.count("&#8221;")
        if opens <= closes:
            return False  # at least one dash outside quotes
        i = n.find(EM, i + 1)
    return True  # every dash sits inside an open quote

RANGE = re.compile(r"(?<=[\d\"%\u00b0a-z\).])(\s*)" + EM + r"(\s*)(?=\$?\d)")

def try_range_fix(s):
    n = normalize(s)
    fixed = RANGE.sub(lambda m: m.group(1) + "\u2013" + m.group(2), n)
    return fixed if EM not in fixed else None

def walk_settings(obj, path, out):
    if isinstance(obj, dict):
        for k, v in obj.items():
            walk_settings(v, f"{path}.{k}" if path else k, out)
    elif isinstance(obj, list):
        for i, v in enumerate(obj):
            walk_settings(v, f"{path}[{i}]", out)
    elif isinstance(obj, str) and (EM in obj or ENT.search(obj)):
        out.append((path, obj))

def walk(nodes, pid, res):
    for n in nodes:
        hits = []
        walk_settings(n.get("settings", {}), "", hits)
        if hits:
            res.append((pid, n, hits))
        walk(n.get("elements", []), pid, res)

def set_by_path(settings, path, value):
    # path like key[3].sub or key
    tokens = re.findall(r"[^.\[\]]+|\[\d+\]", path)
    cur = settings
    for t in tokens[:-1]:
        cur = cur[int(t[1:-1])] if t.startswith("[") else cur[t]
    last = tokens[-1]
    if last.startswith("["):
        cur[int(last[1:-1])] = value
    else:
        cur[last] = value

results = []
for f in FILES:
    d = json.load(open(os.path.join(BASE, f), encoding="utf-8"))
    walk(json.loads(d["raw_data"]), d["post_id"], results)

manual, keeps, autos = [], [], []
for pid, node, hits in results:
    wid, wtype = node.get("id"), node.get("widgetType") or node.get("elType")
    changed_top_keys = set()
    for path, val in hits:
        if is_lone(val):
            keeps.append((pid, wid, wtype, path, "LONE-DASH CELL", val.strip()[:60]))
        elif is_attribution(val):
            keeps.append((pid, wid, wtype, path, "ATTRIBUTION", normalize(val)[:90]))
        elif in_quotes(val):
            keeps.append((pid, wid, wtype, path, "INSIDE QUOTES", normalize(val)[:90]))
        else:
            rf = try_range_fix(val)
            if rf is not None:
                set_by_path(node["settings"], path, rf)
                changed_top_keys.add(re.split(r"[.\[]", path)[0])
                autos.append((pid, wid, wtype, path, normalize(val)[:100], rf[:100]))
            else:
                manual.append((pid, wid, wtype, path, val))
    for key in changed_top_keys:
        fn = os.path.join(OUT, f"fix_{pid}_{wid}_{key}.json")
        json.dump({key: node["settings"][key]}, open(fn, "w", encoding="utf-8"), ensure_ascii=False)

print(f"=== KEEP ({len(keeps)}) ===")
for r in keeps:
    print(f"  post {r[0]} {r[1]} ({r[2]}) [{r[3]}] {r[4]}: {r[5]!r}")
print(f"\n=== AUTO range->en dash ({len(autos)}) — full fixed settings written to fix_*.json ===")
for r in autos:
    print(f"  post {r[0]} {r[1]} ({r[2]}) [{r[3]}]")
    print(f"     was: {r[4]!r}")
    print(f"     now: {r[5]!r}")
print(f"\n=== MANUAL PROSE ({len(manual)}) ===")
for pid, wid, wtype, path, val in manual:
    print(f"### post {pid} widget {wid} ({wtype}) [{path}]")
    print(f"    {val if len(val)<800 else val[:800]+' ...[TRUNC]'}")
files = sorted(glob.glob(os.path.join(OUT, "fix_*.json")))
print(f"\nfix files written: {len(files)}")
for f in files:
    print("  ", os.path.basename(f), os.path.getsize(f), "bytes")
