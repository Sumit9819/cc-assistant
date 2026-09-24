# Build swap patches: replace each design-team <img> with the new <figure>.
#
# Different from mkpatch.py, which appends a figure after a heading. Here the
# old markup is the search string, so the replacement lands in exactly the same
# position and the old width/height/alt go with it. Leaving those behind would
# render a 1200x628 card inside an 800x439 box.
import html, json, os, re, sys, unicodedata

spec = json.load(open(sys.argv[1], encoding="utf-8"))
key = os.path.basename(sys.argv[1]).replace("spec-", "").replace(".json", "")
up = {r["heading"]: r for r in json.load(open("uploaded-%s.json" % key, encoding="utf-8"))}
plan = json.load(open("replacement_plan.json", encoding="utf-8"))


def norm(s):
    s = re.sub(r"<[^>]+>", "", s)
    s = html.unescape(s)
    s = unicodedata.normalize("NFKD", s)
    s = s.replace("–", "-").replace("—", "-").replace("’", "'").replace("“", '"').replace("”", '"')
    return re.sub(r"[^a-z0-9]+", " ", s.lower()).strip()


by_post = {}
for card in spec["cards"]:
    by_post.setdefault(card["post_id"], []).append(card)

result = {}
for pid, cards in by_post.items():
    rows = plan[str(pid)]
    used = set()
    patches = []
    for card in cards:
        want = norm(card["heading"])
        hit = [i for i, r in enumerate(rows) if norm(r["section"]) == want and i not in used]
        if len(hit) != 1:
            sys.exit("post %d: heading %r matched %d unused plan rows" % (pid, card["heading"], len(hit)))
        idx = hit[0]
        used.add(idx)
        rec = up[card["heading"]]
        new = ('<figure><img src="%s" alt="%s" width="1200" height="628" '
               'loading="lazy" decoding="async" /></figure>') % (rec["url"], rec["alt"])
        patches.append({"search": rows[idx]["old_markup"], "replace": new})
    if len(used) != len(rows):
        skipped = [rows[i]["old_file"] for i in range(len(rows)) if i not in used]
        print("  post %d: leaving %d image(s) alone: %s" % (pid, len(skipped), ", ".join(skipped)))
    result[pid] = patches

with open("swaps-%s.json" % key, "w", encoding="utf-8") as out:
    json.dump(result, out, indent=1, ensure_ascii=True)
for pid, patches in result.items():
    print("post %d: %d swaps (all %d images covered)" % (pid, len(patches), len(plan[str(pid)])))
