# Markup-level audit of in-body images. No pixels needed.
#
# The 9462 defects that mattered were invisible in a screenshot and obvious in
# the database: an alt describing a different section, two alts describing the
# same thing, and a src pointing at an 800px intermediate while declaring
# width="800" against a 1920px original. All three are readable from here.
import base64, json, os, re, sys, urllib.parse, urllib.request

cfg = json.load(open(r"D:\cc-assistant\.mcp.json", encoding="utf-8"))
env = cfg["mcpServers"]["cc-assistant-irvingwellnessclinic-com"]["env"]
SITE, USER, PW = env["CC_WP_URL"], env["CC_WP_USER"], env["CC_WP_APP_PASSWORD"]
AUTH = base64.b64encode(f"{USER}:{PW}".encode()).decode()

_dim_cache = {}


def real_dims(stem):
    """Original width/height for a file stem, from the media record."""
    if stem in _dim_cache:
        return _dim_cache[stem]
    req = urllib.request.Request(
        f"{SITE.rstrip('/')}/wp-json/wp/v2/media?search={urllib.parse.quote(stem)}"
        "&per_page=5&_fields=id,source_url,media_details",
        headers={"Authorization": "Basic " + AUTH, "User-Agent": "Mozilla/5.0"})
    try:
        rows = json.load(urllib.request.urlopen(req, timeout=60))
    except Exception:
        rows = []
    out = None
    for r in rows:
        if os.path.splitext(os.path.basename(r["source_url"]))[0] == stem:
            md = r.get("media_details") or {}
            out = (r["id"], md.get("width"), md.get("height"))
            break
    _dim_cache[stem] = out
    return out


def words(s):
    return set(re.findall(r"[a-z]{4,}", s.lower()))


STOP = words("this that with from your what when where which have been they their"
             " about into more than then also very some other such only")

posts = [int(a) for a in sys.argv[1:]]
for pid in posts:
    body = open("bodies/%d.html" % pid, encoding="utf-8", newline="").read()
    # walk headings and imgs in document order so each img knows its section
    tokens = list(re.finditer(r"<h([23])[^>]*>(.*?)</h\1>|<img[^>]+>", body, re.S))
    section = ""
    rows = []
    for m in tokens:
        chunk = m.group(0)
        if chunk.startswith("<img"):
            src = (re.search(r'src="([^"]+)"', chunk) or [None, ""])[1]
            alt = (re.search(r'alt="([^"]*)"', chunk) or [None, ""])[1]
            w = (re.search(r'width="(\d+)"', chunk) or [None, ""])[1]
            h = (re.search(r'height="(\d+)"', chunk) or [None, ""])[1]
            rows.append({"section": section, "src": src, "alt": alt, "w": w, "h": h})
        else:
            section = re.sub(r"<[^>]+>", "", m.group(2)).strip()

    print("\n=== %d  (%d images)" % (pid, len(rows)))
    seen_alts = []
    for r in rows:
        base = os.path.basename(urllib.parse.urlparse(r["src"]).path)
        stem = os.path.splitext(base)[0]
        flags = []

        variant = re.match(r"^(.*)-(\d+)x(\d+)$", stem)
        if variant:
            orig_stem, vw, vh = variant.group(1), int(variant.group(2)), int(variant.group(3))
            info = real_dims(orig_stem)
            if info and info[1]:
                flags.append("SIZE: serves %dx%d variant of a %sx%s original" % (vw, vh, info[1], info[2]))
            if r["w"] and int(r["w"]) != vw:
                flags.append("DECLARED: width=%s but the file served is %dpx" % (r["w"], vw))

        if not r["alt"].strip():
            flags.append("ALT: missing")

        for prev_sec, prev_alt in seen_alts:
            overlap = (words(r["alt"]) - STOP) & (words(prev_alt) - STOP)
            if len(overlap) >= 4:
                flags.append("ALT DUP: shares %s with the image under '%s'"
                             % (sorted(overlap)[:6], prev_sec[:40]))
        seen_alts.append((r["section"], r["alt"]))

        print("  -- %s" % (r["section"][:70] or "(no heading above)"))
        print("     file : %s  [%sx%s declared]" % (base, r["w"], r["h"]))
        print("     alt  : %s" % (r["alt"][:150] or "(none)"))
        for f in flags:
            print("     !! %s" % f)
