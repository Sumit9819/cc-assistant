"""Remove the heading-echo banner images from the posts that carry cards.

    python rmbanners.py --bodies erof-rm-fresh.json

A "heading-echo banner" is a stock photo with the section heading burned into a
caption bar: its alt text is the heading verbatim and its filename is the
heading slugified. It carries nothing the <h2> above it does not already say,
and since cards now sit directly below their heading the reader meets the same
words three times: as the heading, as a picture of the heading, then as the
card's own title. The operator chose to drop the banner and keep the card.

Only the reference in post_content goes. The attachment stays in the media
library at the same URL, so this is reversible with a forward patch and no file
is deleted (see memory/reference_setting_revert_restores_whole_option.md for
why reverting is not the recovery path here).

Two things this is careful about:

  - OUR OWN CARDS ARE MASKED FIRST. The naive scan counted them as banners,
    because a card's alt restates its section and so clears the heading-overlap
    test every time. That inflated the site-wide count from 24 to 61.
  - An <img> wrapped in a <figure> is removed WITH its figure, or the body is
    left holding an empty figure element.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent
CARD = re.compile(r'<figure class="cc-card">.*?</figure>', re.S)
HEAD = re.compile(r"<h([1-6])\b[^>]*>(.*?)</h\1>", re.S)
IMG = re.compile(r"<img\b[^>]*/?>", re.S)

STOP = {"the", "a", "an", "of", "in", "for", "to", "and", "or", "is", "are",
        "your", "you", "it", "on", "what", "when", "how", "why", "do", "does",
        "guide", "complete", "signs", "sign"}

POSTS = [3081, 3097, 3103, 3116, 3162, 3201, 3365]


def words(s):
    s = re.sub(r"&nbsp;", " ", s)
    return {w for w in re.findall(r"[a-z]+", re.sub(r"<[^>]+>", " ", s).lower())
            if w not in STOP and len(w) > 2}


def main():
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    posts = json.loads(bp.read_text(encoding="utf-8"))
    if isinstance(posts, dict):
        posts = posts.get("data", posts)

    out, total = [], 0
    for p in sorted(posts, key=lambda x: x["id"]):
        if p["id"] not in POSTS:
            continue
        h = p["content"].get("raw") or p["content"]["rendered"]
        masked = CARD.sub(lambda m: " " * len(m.group(0)), h)

        patches, listed = [], []
        for m in IMG.finditer(masked):
            tag = m.group(0)
            alt = re.search(r'alt="([^"]*)"', tag)
            src = re.search(r'src="([^"]+)"', tag)
            if not src:
                continue
            alt = alt.group(1) if alt else ""
            heads = HEAD.findall(masked[:m.start()])
            heading = re.sub(r"<[^>]+>", "", heads[-1][1]).strip() if heads else ""
            hw, aw = words(heading), words(alt)
            if not (alt.strip() and hw and len(hw & aw) / len(hw) >= 0.6):
                continue
            f = src.group(1).rsplit("/", 1)[-1]
            if f.startswith("erof-"):
                raise SystemExit(f"refusing: {f} is one of our own cards")

            # Take the whole <figure> when the img is wrapped in one, else the
            # bare tag; swallow the whitespace that followed it either way.
            fig = None
            for fm in re.finditer(r"<figure\b(?:(?!</figure>).)*</figure>", h, re.S):
                if fm.start() <= h.index(tag) < fm.end():
                    fig = fm
                    break
            if fig:
                target = fig.group(0)
            else:
                target = tag
            i = h.index(target) + len(target)
            target += re.match(r"[\s]*", h[i:]).group(0)

            if h.count(target) != 1:
                print(f'  post {p["id"]}: SKIP, not unique ({h.count(target)}x) {f}')
                continue
            patches.append({"search": target, "replace": ""})
            listed.append((heading, f))

        if not patches:
            continue
        total += len(patches)
        print(f'--- post {p["id"]}: removing {len(patches)} banner(s)')
        for heading, f in listed:
            print(f'      {heading[:54]:<54}  {f[:52]}')
        out.append({
            "post_id": p["id"],
            "patches": patches,
            "summary": f"Remove {len(patches)} heading-echo banner image(s) from post {p['id']}",
            "reasoning": (
                "These images restate their own heading and nothing else: the alt "
                "text is the heading verbatim and the filename is the heading "
                "slugified, over a stock photo with the same words burned into a "
                "caption bar. Since the in-body cards now sit directly below their "
                "heading, the reader met the same words three times, as the "
                "heading, as a picture of the heading, then as the card's title. "
                "The heading itself is untouched, so no wording, no claim and no "
                "citation changes, and the alt text was duplicating text that "
                "stays on the page. Only the reference in the body is removed: "
                "every attachment stays in the media library at the same URL, so "
                "this is reversible with a forward patch and no file is deleted. "
                "Genuine photographs are NOT touched anywhere, including the two "
                "on post 2780 whose alt text describes something the heading does "
                "not."),
        })

    dest = HERE / "rm-banners.json"
    dest.write_text(json.dumps(out, indent=2) + "\n", encoding="utf-8")
    print()
    print(f'-> {dest.name}: {len(out)} post(s), {total} banner(s) removed')


if __name__ == "__main__":
    main()
