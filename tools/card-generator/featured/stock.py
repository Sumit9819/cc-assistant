"""
Fetch stock photography for featured images.

    python stock.py --search "woman drinking water" --name hydration
    python stock.py --spec stock-v2.json          # a whole set
    python stock.py --search "eucalyptus" --preview   # contact sheet, no commit

Two sources, Pexels first. Both licences permit commercial use with no
attribution, which is what client work needs.

Pexels is the default because Pixabay's library is wrong for this brief: asked
for a mature woman's portrait it returns documentary travel photography, and
asked for a clinical still life it returns Christmas wine and a cappuccino.
Pexels answers the same queries with studio editorial work.

Pexels 403s with Cloudflare error 1010 unless the request carries a browser
User-Agent. That looks exactly like a dead key and is not one.

WHY THIS EXISTS ALONGSIDE gen.py: generated photography can be asked for the
exact background, headroom and framing the layout needs, so it is the better
source. This is the fallback for when the generation quota is spent, and the way
to get a real photograph when a real one is wanted.

Downloads land in src/stock/<name>.png. Nothing is uploaded anywhere.
"""
import argparse
import json
import pathlib
import re
import sys
import urllib.parse
import urllib.request

# Photographer names carry emoji, and a Windows console defaults to cp1252, so
# printing a credit line is enough to abort a download that already succeeded.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

HERE = pathlib.Path(__file__).parent
DEST = HERE / "src" / "stock"
ENV = pathlib.Path(r"D:\faceless-studio\.env")
UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36")

# A featured image is cropped into a 560x630 slot or cut out. Anything small,
# heavily textured or already busy with its own text fails at that size, so the
# search is constrained rather than the results being sifted by hand later.
MIN_W, MIN_H = 1600, 1000


def env():
    if not ENV.exists():
        sys.exit(f"No .env at {ENV}")
    out = {}
    for line in ENV.read_text(encoding="utf-8").splitlines():
        m = re.match(r"^([A-Z0-9_]+)=(.*)$", line.strip())
        if m:
            out[m.group(1)] = m.group(2).strip().strip('"')
    return out


def pexels(query, orientation="portrait", per_page=12, safe=True):
    key = env().get("PEXELS_API_KEY")
    if not key:
        sys.exit("No PEXELS_API_KEY in .env")
    params = {"query": query, "per_page": per_page, "orientation": orientation}
    url = "https://api.pexels.com/v1/search?" + urllib.parse.urlencode(params)
    # The User-Agent is load-bearing: without it Cloudflare answers 403/1010.
    req = urllib.request.Request(url, headers={"Authorization": key, "User-Agent": UA})
    d = json.load(urllib.request.urlopen(req, timeout=60))
    return [{
        "id": p["id"],
        "w": p["width"], "h": p["height"],
        "url": p["src"].get("original") or p["src"]["large2x"],
        "page": p.get("url"),
        "by": p.get("photographer"),
        "tags": (p.get("alt") or "")[:70],
    } for p in d.get("photos", []) if p["width"] >= MIN_W]


def pixabay(query, orientation="horizontal", per_page=12, safe=True):
    key = env().get("PIXABAY_API_KEY")
    if not key:
        sys.exit("No PIXABAY_API_KEY in .env")
    params = {
        "key": key, "q": query, "image_type": "photo",
        "orientation": orientation, "per_page": per_page,
        "safesearch": "true" if safe else "false",
        "order": "popular", "min_width": MIN_W, "min_height": MIN_H,
    }
    url = "https://pixabay.com/api/?" + urllib.parse.urlencode(params)
    d = json.load(urllib.request.urlopen(url, timeout=60))
    return [{
        "id": h["id"],
        "w": h["imageWidth"], "h": h["imageHeight"],
        "url": h.get("largeImageURL") or h.get("webformatURL"),
        "page": h.get("pageURL"),
        "by": h.get("user"),
        "tags": h.get("tags", ""),
    } for h in d.get("hits", [])]


def fetch(url, dest):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    data = urllib.request.urlopen(req, timeout=120).read()
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_bytes(data)
    # Normalise to PNG so downstream (cutout, data URI, mime sniffing) has one
    # format to think about. Costs disk, saves a class of bug.
    from PIL import Image
    im = Image.open(dest).convert("RGB")
    im.save(dest)
    return im.size


def preview(hits, dest, cols=4, tile=300):
    """Contact sheet of candidates, so a photo is chosen by looking at it."""
    from PIL import Image
    ims = []
    for h in hits:
        try:
            tmp = DEST / f"_tmp_{h['id']}.png"
            fetch(h["url"], tmp)
            im = Image.open(tmp).convert("RGB")
            im.thumbnail((tile, tile), Image.LANCZOS)
            ims.append((h, im))
            tmp.unlink(missing_ok=True)
        except Exception as e:
            print(f"  skip {h['id']}: {e}")
    if not ims:
        return None
    rows = (len(ims) + cols - 1) // cols
    sheet = Image.new("RGB", (cols * tile, rows * tile), (240, 240, 240))
    for i, (_, im) in enumerate(ims):
        r, c = divmod(i, cols)
        sheet.paste(im, (c * tile + (tile - im.width) // 2,
                         r * tile + (tile - im.height) // 2))
    dest.parent.mkdir(parents=True, exist_ok=True)
    sheet.save(dest)
    for i, (h, _) in enumerate(ims):
        print(f"  [{i}] {h['id']}  {h['w']}x{h['h']}  {h['tags'][:52]}")
    return dest


def screen(hits, limit=8):
    """
    Download candidates and rank them by whether their subject is INSIDE the frame.

    The selection step that eyeballing kept getting wrong. A cropped shoulder, or
    a head cut level with the top edge, is nearly invisible in a 300px contact
    sheet, survives into the render, and cannot be fixed there - so the cutout
    guard runs at CHOOSING time rather than at building time.
    """
    from cutout import mask_for, edge_contact, coverage, holes
    from PIL import Image

    rows = []
    for h in hits[:limit]:
        tmp = DEST / f"_screen_{h['id']}.png"
        try:
            fetch(h["url"], tmp)
            im = Image.open(tmp).convert("RGBA")
            # The mask is computed at 1024 regardless, so a 6000px original costs
            # many seconds for an identical verdict.
            if max(im.size) > 2200:
                im.thumbnail((2200, 2200), Image.LANCZOS)
            m = mask_for(im)
            e = edge_contact(m)
            bad = {k: v for k, v in e.items() if k != "bottom" and v > 0.04}
            cov = coverage(m)
            rows.append({"hit": h, "file": tmp, "cov": cov, "holes": holes(m),
                         "bad": bad, "ok": (not bad) and 0.05 < cov < 0.95})
        except Exception as ex:
            print(f"  skip {h['id']}: {ex}")
    rows.sort(key=lambda r: (not r["ok"], sum(r["bad"].values())))

    print(f"  {'#':>2} {'id':>10} {'subj':>6} {'holes':>6}  verdict")
    for i, r in enumerate(rows):
        if r["ok"]:
            v = "USABLE - whole subject inside the frame"
        elif r["bad"]:
            v = "runs off " + ", ".join(f"{k} {x:.0%}" for k, x in sorted(r["bad"].items()))
        else:
            v = f"coverage {r['cov']:.0%} out of range"
        print(f"  {i:>2} {r['hit']['id']:>10} {r['cov']:>5.0%} {r['holes']:>5.0%}  {v}")
    return rows


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--search")
    ap.add_argument("--name")
    ap.add_argument("--spec")
    ap.add_argument("--index", type=int, default=0, help="which hit to keep")
    ap.add_argument("--orientation", default="portrait",
                    choices=["portrait", "landscape", "square"])
    ap.add_argument("--source", default="pexels", choices=["pexels", "pixabay"])
    ap.add_argument("--preview", action="store_true",
                    help="write a contact sheet of candidates instead of committing")
    ap.add_argument("--screen", action="store_true",
                    help="cut out each candidate and report which have the whole subject in frame")
    a = ap.parse_args()

    if a.spec:
        items = json.loads((HERE / a.spec).read_text(encoding="utf-8"))["photos"]
    elif a.search:
        items = [{"name": a.name or re.sub(r"\W+", "-", a.search).strip("-"),
                  "query": a.search, "index": a.index,
                  "orientation": a.orientation, "source": a.source}]
    else:
        sys.exit("Pass --search or --spec")

    for it in items:
        src = it.get("source", a.source)
        fn = pexels if src == "pexels" else pixabay
        orient = it.get("orientation", a.orientation)
        if src == "pixabay":
            orient = {"portrait": "vertical", "landscape": "horizontal"}.get(orient, "all")
        hits = fn(it["query"], orient)
        print(f"{it['name']}: {len(hits)} candidates for \"{it['query']}\" via {src}")
        if not hits:
            print("  nothing matched; loosen the query")
            continue
        if a.screen:
            rows = screen(hits)
            usable = [r for r in rows if r["ok"]]
            print(f"  {len(usable)} of {len(rows)} usable")
            continue
        if a.preview:
            sheet = preview(hits, DEST / f"_candidates_{it['name']}.png")
            print(f"  -> {sheet}")
            continue
        h = hits[min(it.get("index", 0), len(hits) - 1)]
        dest = DEST / f"{it['name']}.png"
        size = fetch(h["url"], dest)
        print(f"  {size[0]}x{size[1]} -> {dest.relative_to(HERE)}  "
              f"({src} {h['id']}, by {h['by']})")


if __name__ == "__main__":
    main()
