# Build the 1:1 replacement plan: for each design-team image, the section it
# sits under and the exact markup to swap.
#
# Placement is kept as-is. The editorial decision about which sections earn a
# graphic was already made and is sound; what changes is the visual system, so
# a positional swap keeps the diff mechanical and reviewable.
import json, os, re, urllib.parse

POSTS = [8567, 9389, 9398, 9402, 9871, 10043, 10191, 10192, 10229]

plan = {}
total = 0
for pid in POSTS:
    body = open("bodies/%d.html" % pid, encoding="utf-8", newline="").read()
    section = ""
    rows = []
    for m in re.finditer(r"<h([23])[^>]*>(.*?)</h\1>|<img[^>]+>", body, re.S):
        chunk = m.group(0)
        if chunk.startswith("<img"):
            src = (re.search(r'src="([^"]+)"', chunk) or [None, ""])[1]
            rows.append({
                "section": section,
                "old_markup": chunk,
                "old_file": os.path.basename(urllib.parse.urlparse(src).path),
            })
        else:
            section = re.sub(r"<[^>]+>", "", m.group(2)).strip()
    plan[pid] = rows
    total += len(rows)

json.dump(plan, open("replacement_plan.json", "w", encoding="utf-8"), indent=1, ensure_ascii=True)
for pid, rows in plan.items():
    print("%-6d %d cards" % (pid, len(rows)))
print("TOTAL: %d cards across %d posts" % (total, len(POSTS)))
