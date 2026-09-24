import json, re
body = open("bodies/9462.html", encoding="utf-8", newline="").read()
up = {r["heading"]: r for r in json.load(open("uploaded-9462.json", encoding="utf-8"))}
imgs = re.findall(r"<img[^>]+>", body)
assert len(imgs) == 3, len(imgs)

order = [
    "When Dieting Fails: Understanding the Survival Switch",
    "Cellular Bottlenecks: The Science of Stalled Progress",
    "Reigniting Metabolism: Beyond the Calorie Deficit",
]
patches = []
for old, heading in zip(imgs, order):
    rec = up[heading]
    new = ('<figure><img src="%s" alt="%s" width="1200" height="628" '
           'loading="lazy" decoding="async" /></figure>') % (rec["url"], rec["alt"])
    patches.append({"search": old, "replace": new})

print(json.dumps(patches, ensure_ascii=True))
