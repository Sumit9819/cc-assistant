# Emit draft_patch_post_content patch arrays from a spec + its uploaded manifest.
#
# Search strings are lifted VERBATIM out of the fetched post body, never retyped:
# headings carry en dashes, curly quotes and non-breaking spaces, and a patch
# whose search string is one character off silently matches nothing.
#
# Output is dumped with ensure_ascii=True, so every one of those characters
# leaves here as a \uXXXX escape. That matters because the payload is read off a
# console that mangles UTF-8 into replacement characters; an escape survives the
# round trip and decodes back to the right character at the far end.
import html, json, os, re, sys, unicodedata

spec = json.load(open(sys.argv[1], encoding="utf-8"))
key = os.path.basename(sys.argv[1]).replace("spec-", "").replace(".json", "")
up = {r["heading"]: r for r in json.load(open("uploaded-%s.json" % key, encoding="utf-8"))}


def norm(s):
    s = re.sub(r"<[^>]+>", "", s)
    s = html.unescape(s)
    s = unicodedata.normalize("NFKD", s)
    s = s.replace("–", "-").replace("—", "-").replace("’", "'")
    return re.sub(r"[^a-z0-9]+", " ", s.lower()).strip()


by_post = {}
for card in spec["cards"]:
    by_post.setdefault(card["post_id"], []).append(card)

result = {}
for pid, cards in by_post.items():
    body = open("bodies/%d.html" % pid, encoding="utf-8", newline="").read()
    # Bodies are not consistent: some posts store CRLF, others bare LF.
    nl = "\r\n" if body.count("\r\n") else "\n"
    heads = list(re.finditer(r"<h[23][^>]*>.*?</h[23]>", body, re.S))
    patches = []
    for card in cards:
        want = norm(card["heading"])
        hit = [m for m in heads if norm(m.group(0)) == want]
        if len(hit) != 1:
            sys.exit("post %d: %r matched %d headings" % (pid, card["heading"], len(hit)))
        raw = hit[0].group(0)
        if body.count(raw) != 1:
            sys.exit("post %d: heading markup is not unique for %r" % (pid, card["heading"]))
        rec = up[card["heading"]]
        fig = ('<figure><img src="%s" alt="%s" width="1200" height="628" '
               'loading="lazy" decoding="async" /></figure>') % (rec["url"], rec["alt"])
        patches.append({"search": raw, "replace": raw + nl + fig})
    result[pid] = patches

with open("patches-%s.json" % key, "w", encoding="utf-8") as out:
    json.dump(result, out, indent=1, ensure_ascii=True)
for pid, patches in result.items():
    print("post %d: %d patches" % (pid, len(patches)))
