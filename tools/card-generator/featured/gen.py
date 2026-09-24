"""
Generate featured-image photography with Gemini.

    python gen.py prompts-v2.json            # generate everything missing
    python gen.py prompts-v2.json --force    # regenerate even if the file exists

Keys come from D:\\faceless-studio\\.env (GEMINI_API_KEY, GEMINI_API_KEY_2, ...),
the same rotation that project uses. Keys are never printed; logs say "key 1".

THE POINT OF GENERATING RATHER THAN SOURCING: the background is ours to specify.
Every prompt gets the destination layout's own ground colour baked into it, so
the photograph and the canvas are the same colour and the seam between them
disappears. That removes the two defects that a sourced photo forces on the
layout - a visible edge needing a fade to hide it, and a set where every image
has a different background - by construction rather than by retouching.

It also removes the third: a generated subject can be asked for headroom, so
nobody's head is cropped at the frame edge.

Rate limits are per-minute on the free tier, not just per-day, so this paces
itself and rotates keys on 429. A batch of five takes a few minutes. That is
expected; run it in the background.
"""
import argparse
import base64
import json
import pathlib
import re
import sys
import time
import urllib.error
import urllib.request

HERE = pathlib.Path(__file__).parent
GEN = HERE / "src" / "gen"
ENV = pathlib.Path(r"D:\faceless-studio\.env")

MODEL_ORDER = ["gemini-3.1-flash-image", "gemini-2.5-flash-image"]
PACE = 32.0          # seconds between calls; the free tier is per-minute
MAX_ATTEMPTS = 4

# Appended to every prompt. Consistency across the set matters more than any
# single image being interesting: these sit in a blog grid together.
HOUSE_STYLE = (
    "Editorial photograph, shot on a 50mm lens at f/2.8. Soft diffused north-facing "
    "daylight from the left, gentle natural shadows, no harsh specular highlights. "
    "Muted natural colour, slightly desaturated, calm and clinical rather than glossy. "
    "Absolutely no text, no lettering, no watermarks, no logos, no brand names, "
    "no borders and no collage."
)


def keys():
    if not ENV.exists():
        sys.exit(f"No .env at {ENV}")
    env = {}
    for line in ENV.read_text(encoding="utf-8").splitlines():
        m = re.match(r"^([A-Z0-9_]+)=(.*)$", line.strip())
        if m:
            env[m.group(1)] = m.group(2).strip().strip('"')
    out = []
    if env.get("GEMINI_API_KEY"):
        out.append(env["GEMINI_API_KEY"])
    n = 2
    while env.get(f"GEMINI_API_KEY_{n}"):
        out.append(env[f"GEMINI_API_KEY_{n}"])
        n += 1
    if not out:
        sys.exit("No GEMINI_API_KEY in .env")
    return out


def build_prompt(item):
    """Subject + house style + the exact background the layout needs."""
    bits = [item["prompt"].strip().rstrip(".") + "."]
    ground = item.get("ground")
    if ground:
        bits.append(
            f"The background is a completely plain, seamless, evenly lit studio "
            f"backdrop in the exact colour {ground}, filling the entire background "
            f"with no gradient banding, no vignette, no visible floor line, no wall "
            f"corner and no props behind the subject."
        )
    if item.get("headroom", True):
        bits.append(
            "Compose with generous empty space above the subject and around all four "
            "edges. Nothing is cropped by the frame: the whole head, the whole object "
            "and any hands are fully inside the picture with room to spare."
        )
    bits.append(HOUSE_STYLE)
    return " ".join(bits)


def generate(prompt, ks, aspect="3:4"):
    """Return PNG bytes, rotating keys and models until something answers."""
    spent = set()
    for attempt in range(1, MAX_ATTEMPTS + 1):
        for model in MODEL_ORDER:
            for i, k in enumerate(ks, 1):
                if (model, i) in spent:
                    continue
                cfg = {"responseModalities": ["IMAGE"]}
                if model.startswith("gemini-3"):
                    cfg["imageConfig"] = {"aspectRatio": aspect}
                url = (f"https://generativelanguage.googleapis.com/v1beta/models/"
                       f"{model}:generateContent?key={k}")
                body = {"contents": [{"parts": [{"text": prompt}]}],
                        "generationConfig": cfg}
                req = urllib.request.Request(
                    url, json.dumps(body).encode(), {"Content-Type": "application/json"})
                try:
                    r = json.load(urllib.request.urlopen(req, timeout=240))
                except urllib.error.HTTPError as e:
                    if e.code == 429:
                        print(f"      {model} key {i}: rate limited")
                        spent.add((model, i))
                        continue
                    print(f"      {model} key {i}: HTTP {e.code} "
                          f"{e.read()[:120].decode(errors='replace')}")
                    continue
                except Exception as e:
                    print(f"      {model} key {i}: {e}")
                    continue
                for p in r["candidates"][0]["content"]["parts"]:
                    if "inlineData" in p:
                        return base64.b64decode(p["inlineData"]["data"]), model
                print(f"      {model} key {i}: responded with no image")
        if attempt < MAX_ATTEMPTS:
            wait = 65 * attempt
            print(f"      every key is limited; waiting {wait}s (attempt {attempt})")
            time.sleep(wait)
            spent.clear()
    return None, None


def check_background(path, want_hex, tolerance=42):
    """
    Verify the generated background is the colour we asked for.

    A model that ignores the background instruction produces an image that looks
    fine alone and wrecks the set, because the whole point is that every tile
    shares one ground. Sampling the corners catches it before the render does.
    """
    from PIL import Image
    import numpy as np
    want = tuple(int(want_hex.lstrip("#")[i:i + 2], 16) for i in (0, 2, 4))
    im = Image.open(path).convert("RGB")
    a = np.asarray(im, dtype=np.float64)
    h, w = a.shape[:2]
    s = max(8, min(h, w) // 20)
    corners = np.concatenate([
        a[:s, :s].reshape(-1, 3), a[:s, -s:].reshape(-1, 3),
        a[-s:, :s].reshape(-1, 3), a[-s:, -s:].reshape(-1, 3),
    ])
    got = np.median(corners, axis=0)
    dist = float(np.linalg.norm(got - np.array(want, dtype=np.float64)))
    return dist <= tolerance, tuple(int(v) for v in got), dist


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("spec")
    ap.add_argument("--force", action="store_true")
    a = ap.parse_args()

    items = json.loads((HERE / a.spec).read_text(encoding="utf-8"))["images"]
    GEN.mkdir(parents=True, exist_ok=True)
    ks = keys()
    print(f"{len(ks)} key(s), {len(items)} image(s)\n")

    made, skipped, failed, offcolour = 0, 0, [], []
    for n, item in enumerate(items, 1):
        dest = GEN / f"{item['name']}.png"
        if dest.exists() and not a.force:
            print(f"[{n}/{len(items)}] {item['name']}: exists, skipping")
            skipped += 1
            continue
        print(f"[{n}/{len(items)}] {item['name']}")
        png, model = generate(build_prompt(item), ks, item.get("aspect", "3:4"))
        if not png:
            print("      FAILED")
            failed.append(item["name"])
            continue
        dest.write_bytes(png)
        made += 1
        note = ""
        if item.get("ground"):
            ok, got, dist = check_background(dest, item["ground"])
            note = (f"  bg {'ok' if ok else 'OFF'} rgb{got} d={dist:.0f}")
            if not ok:
                offcolour.append(item["name"])
        print(f"      {model}: {len(png)//1024}KB{note}")
        if n < len(items):
            time.sleep(PACE)

    print(f"\n{made} generated, {skipped} skipped, {len(failed)} failed")
    if offcolour:
        print("Background does not match the request (regenerate these):")
        for f in offcolour:
            print(f"  ! {f}")
    if failed:
        print("Failed:", ", ".join(failed))
        sys.exit(1)


if __name__ == "__main__":
    main()
