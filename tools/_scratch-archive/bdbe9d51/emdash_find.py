"""Walk every settings string in the two page exports; list each em-dash-
bearing value with widget id/type/setting path so rewrites can be composed."""
import io, sys, json, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

FILES = [
    r"C:\Users\sumit\.claude\projects\c--Users-sumit-Local-Sites-plugintesting-app-public\bdbe9d51-2098-4917-93a9-eb1883a6d1fc\tool-results\mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785855502466.txt",
    r"C:\Users\sumit\.claude\projects\c--Users-sumit-Local-Sites-plugintesting-app-public\bdbe9d51-2098-4917-93a9-eb1883a6d1fc\tool-results\mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785855513968.txt",
]
RX = re.compile(r"\u2014|&#8212;|&mdash;")

def walk_settings(obj, path, out):
    if isinstance(obj, dict):
        for k, v in obj.items():
            walk_settings(v, f"{path}.{k}" if path else k, out)
    elif isinstance(obj, list):
        for i, v in enumerate(obj):
            walk_settings(v, f"{path}[{i}]", out)
    elif isinstance(obj, str) and RX.search(obj):
        out.append((path, obj))

def walk_elements(nodes, post_id, results):
    for n in nodes:
        hits = []
        walk_settings(n.get("settings", {}), "", hits)
        if hits:
            results.append((post_id, n.get("id"), n.get("widgetType") or n.get("elType"), hits))
        walk_elements(n.get("elements", []), post_id, results)

results = []
for f in FILES:
    d = json.load(open(f, encoding="utf-8"))
    raw = json.loads(d["raw_data"])
    walk_elements(raw, d["post_id"], results)

for post_id, wid, wtype, hits in results:
    print(f"### post {post_id}  widget {wid}  ({wtype})")
    for path, val in hits:
        v = val if len(val) < 700 else val[:700] + " ...[TRUNC]"
        print(f"  [{path}]")
        print(f"    {v}")
    print()
print(f"total widgets affected: {len(results)}")
