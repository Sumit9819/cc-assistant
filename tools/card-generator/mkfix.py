# Build ONE patch set per post covering both body-side defects:
#   1. <figure><figure><img></figure></figure> - my mkswap replaced a bare <img>
#      that already sat inside a <figure>, so every swapped card got a second
#      wrapper. Each wrapper costs 40px left and right of browser-default
#      figure margin, so on a 390px phone the card rendered at 210px.
#   2. alt text still carrying the British spellings fixed in the bitmaps -
#      the swap patches only rewrote the image URL, never the alt attribute.
#
# Both live in post_content, and post_content_update supersedes on post_id
# alone, so they MUST ship as a single pending per post.
import glob, html, json, os, re

# spec alt is the corrected, US-spelled text
alt_by_stem = {}
for spec in sorted(glob.glob("spec-*.json")):
    if os.path.basename(spec) in ("spec-redo.json", "spec-splitfix.json", "spec-late.json"):
        continue
    for c in json.load(open(spec, encoding="utf-8"))["cards"]:
        if c.get("alt"):
            alt_by_stem[c["file"]] = c["alt"]

FIG = re.compile(
    r"(?P<open><figure>\s*(?P<inner><figure>\s*)?)"
    r"(?P<img><img[^>]*/wp-content/uploads/2026/09/[^>]*>)"
    r"(?P<close>\s*</figure>(?P<inner2>\s*</figure>)?)",
    re.S,
)

# the metabolizm repair: stem -> the v3 URL that replaces what is live
v3 = {}
if os.path.exists("v3_uploads.json"):
    for r in json.load(open("v3_uploads.json", encoding="utf-8")):
        v3[r["stem"]] = r

result, stats = {}, {"unnest": 0, "alt": 0, "src": 0, "posts": 0}
for path in sorted(glob.glob("bodies/*.html")):
    pid = int(os.path.basename(path)[:-5])
    body = open(path, encoding="utf-8", newline="").read()
    patches, notes = [], {"unnest": 0, "alt": 0, "src": 0}

    for m in FIG.finditer(body):
        whole = m.group(0)
        img = m.group("img")
        nested = bool(m.group("inner")) and bool(m.group("inner2"))

        src = re.search(r"/2026/09/([^\"'?]+)\.webp", img)
        if not src:
            continue
        stem = src.group(1)
        want = alt_by_stem.get(stem) or alt_by_stem.get(re.sub(r"-v2$", "", stem))

        new_img = img
        cur = re.search(r'alt="([^"]*)"', img)
        alt_changed = False
        if want and cur and html.unescape(cur.group(1)) != want:
            new_img = img[:cur.start(1)] + html.escape(want, quote=True) + img[cur.end(1):]
            alt_changed = True

        src_changed = False
        rec = v3.get(re.sub(r"-v\d$", "", stem))
        if rec and rec["old_url"] in new_img:
            new_img = new_img.replace(rec["old_url"], rec["new_url"])
            src_changed = True

        if not nested and not alt_changed and not src_changed:
            continue

        replacement = "<figure>" + new_img + "</figure>"
        if body.count(whole) != 1:
            print("  post %d: markup not unique, skipped: %s" % (pid, stem))
            continue
        patches.append({"search": whole, "replace": replacement})
        notes["unnest"] += 1 if nested else 0
        notes["alt"] += 1 if alt_changed else 0
        notes["src"] += 1 if src_changed else 0

    if patches:
        result[pid] = {"patches": patches, **notes}
        stats["unnest"] += notes["unnest"]
        stats["alt"] += notes["alt"]
        stats["src"] += notes["src"]
        stats["posts"] += 1

json.dump(result, open("body_fix_patches.json", "w", encoding="utf-8"),
          indent=1, ensure_ascii=True)
for pid in sorted(result):
    r = result[pid]
    print("post %-6s %d patch(es)  un-nest=%d  alt=%d  src=%d" %
          (pid, len(r["patches"]), r["unnest"], r["alt"], r["src"]))
print("\n%d posts, %d un-nests, %d alt fixes" % (stats["posts"], stats["unnest"], stats["alt"]))
