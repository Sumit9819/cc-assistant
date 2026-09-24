"""
Post-render proof for featured images.

Two jobs, both about the size a featured image is actually READ at rather than
the size it is designed at:

  1. A contact sheet at 400px wide - the blog listing thumbnail. This is the
     only view that matters for whether the set works, and it is the one nobody
     looks at, because the render opens at 1200.
  2. A WCAG contrast reading of the headline against the pixels genuinely behind
     it in the shipped PNG. Measured, not asserted from the CSS: a headline over
     a photo panel sits on whatever the photograph happens to be, and a sage
     highlight that passes over the pale ground can fail over a bright one.

  python proof.py                 # sheet + contrast for everything in out/
  python proof.py --min 4.5       # fail below a chosen ratio (default 4.5, AA)

Exits non-zero if any headline is below the floor, so this can gate an upload.
"""
import argparse
import json
import pathlib
import sys

import numpy as np
from PIL import Image

HERE = pathlib.Path(__file__).parent
OUT = HERE / "out"
LISTING_W = 400  # the blog archive card on this theme


def srgb_luminance(rgb):
    """Relative luminance per WCAG 2.x. rgb is 0..255."""
    c = np.asarray(rgb, dtype=np.float64) / 255.0
    c = np.where(c <= 0.04045, c / 12.92, ((c + 0.055) / 1.055) ** 2.4)
    return float(0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2])


def contrast(a, b):
    la, lb = srgb_luminance(a), srgb_luminance(b)
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)


def parse_css_rgb(s):
    nums = [float(x) for x in s.replace("rgba(", "").replace("rgb(", "")
            .replace(")", "").split(",")[:3]]
    return tuple(int(round(n)) for n in nums)


def backdrop_under(png, box, text_rgbs, scale=2):
    """
    Median colour of the pixels inside the headline box that are NOT the text.

    Classifying by distance to the declared text colours rather than by
    thresholding luminance: on the dark layout the text is the bright thing and
    on the light layouts it is the dark thing, so any fixed rule gets one of
    them backwards.
    """
    x0, y0, x1, y1 = [v * scale for v in box]
    crop = np.asarray(png.convert("RGB").crop((x0, y0, x1, y1)), dtype=np.float64)
    flat = crop.reshape(-1, 3)
    if not len(flat):
        return (255, 255, 255)
    near_text = np.zeros(len(flat), dtype=bool)
    for rgb in text_rgbs:
        d = np.linalg.norm(flat - np.array(rgb, dtype=np.float64), axis=1)
        near_text |= d < 90
    rest = flat[~near_text]
    if len(rest) < 32:            # headline fills its box; fall back to the frame
        rest = flat
    return tuple(int(v) for v in np.median(rest, axis=0))


CUTOUT_LAYOUTS = {"photo-right", "photo-dark"}


def top_bleed(png, scale=2, band=6, tol=26):
    """
    Fraction of the top edge occupied by something other than the flat canvas.

    On a cutout layout the subject is meant to stand ON the canvas and bleed off
    the BOTTOM only. Anything reaching the top edge is a subject taller than its
    slot, which in practice means a head sliced off level with the frame - the
    single most obvious way one of these images looks broken, and one that is
    easy to miss at full size because the eye reads it as a deliberate crop.

    The canvas colour is taken from the top-left corner, which every layout keeps
    clear for the headline column.
    """
    a = np.asarray(png.convert("RGB"), dtype=np.int16)
    h, w = a.shape[:2]
    canvas = np.median(a[: band * scale, : 40 * scale].reshape(-1, 3), axis=0)
    strip = a[: band * scale, :, :]
    diff = np.linalg.norm(strip - canvas, axis=2)
    return float((diff > tol).mean())


def column_paint(png, scale=2, column=560, tol=26):
    """
    Fraction of the photo column the subject actually paints.

    The gap guard, and it is not about a spacing value. Every cutout layout gives
    the photo the same 560x630 slot and fits it by HEIGHT, so a full-length
    standing figure is scaled down until its body is a narrow strip in the middle
    of the slot: same bounding-box height as a waist-up portrait, a third of the
    mass. The result reads as a hollow tile with the headline marooned on the far
    side, which is invisible in the source photo and obvious in the render.

    Measured on this set: the two waist-up portraits paint 46% and 47% of the
    column and were approved; a still life whose mass sits along the bottom edge
    paints 32% and reads fine; the standing figure painted 19.8% and the operator
    called it out on sight. Hence a floor of 28%.

    Paint is anything more than `tol` from the canvas colour, sampled at the
    top-left where every layout keeps a clear field. The soft radial glow and the
    pool shadow both fall inside the tolerance, so they do not inflate the number
    - checked against the same figure computed from the cutout's alpha, which
    agrees to within a point on all four photo layouts.
    """
    a = np.asarray(png.convert("RGB"), dtype=np.int16)
    canvas = np.median(a[: 12 * scale, : 40 * scale].reshape(-1, 3), axis=0)
    col = a[:, (png.width - column * scale):, :]
    return float((np.linalg.norm(col - canvas, axis=2) > tol).mean())


def contact_sheet(entries, dest, per_row=2):
    """Every featured image at the size the blog listing shows it."""
    tiles = []
    for e in entries:
        im = Image.open(OUT / f"{e['file']}.png").convert("RGB")
        h = round(im.height * LISTING_W / im.width)
        tiles.append((e["file"], im.resize((LISTING_W, h), Image.LANCZOS)))
    if not tiles:
        return None
    tw, th = tiles[0][1].size
    pad, label = 16, 20
    rows = (len(tiles) + per_row - 1) // per_row
    sheet = Image.new("RGB",
                      (per_row * tw + (per_row + 1) * pad,
                       rows * (th + label) + (rows + 1) * pad),
                      (238, 238, 238))
    for i, (_, t) in enumerate(tiles):
        r, c = divmod(i, per_row)
        sheet.paste(t, (pad + c * (tw + pad), pad + r * (th + label + pad)))
    sheet.save(dest)
    return dest


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--min", type=float, default=4.5,
                    help="minimum WCAG contrast for headline text (default 4.5)")
    ap.add_argument("--paint", type=float, default=0.28,
                    help="minimum share of the photo column a cutout subject must "
                         "paint (default 0.28)")
    a = ap.parse_args()

    manifest = OUT / "rendered.json"
    if not manifest.exists():
        sys.exit("No out/rendered.json - run makefeatured.mjs first.")
    entries = json.loads(manifest.read_text(encoding="utf-8"))

    failures = []
    print(f"{'file':<34}{'layout':<14}{'h1 px':>6}{'@400':>6}{'main':>8}{'hilite':>8}"
          f"{'paint':>8}")
    print("-" * 84)
    for e in entries:
        t = e.get("type")
        png = Image.open(OUT / f"{e['file']}.png")
        if not t:
            print(f"{e['file']:<34}{e['layout']:<14}{'-':>6}{'-':>6}{'-':>8}{'-':>8}")
            continue

        main_rgb = parse_css_rgb(t["color"])
        hi_rgb = parse_css_rgb(t["highlight"]) if t["highlight"] else None
        back = backdrop_under(png, t["box"], [c for c in (main_rgb, hi_rgb) if c])

        c_main = contrast(main_rgb, back)
        c_hi = contrast(hi_rgb, back) if hi_rgb else None
        at400 = t["size"] * LISTING_W / png.width * 2  # png is 2x

        flags = []
        if c_main < a.min:
            flags.append(f"headline {c_main:.1f}:1")
        if c_hi is not None and c_hi < a.min:
            flags.append(f"highlight {c_hi:.1f}:1")
        # 18px is where a bold sans stops being readable in a listing card.
        if at400 < 18:
            flags.append(f"{at400:.0f}px at listing size")
        paint = None
        if e["layout"] in CUTOUT_LAYOUTS:
            bleed = top_bleed(png)
            if bleed > 0.06:
                flags.append(f"subject touches the top edge ({bleed:.0%} of it) - "
                             f"on a person that means the head is cropped; on an "
                             f"object it means a portrait shot filled the column "
                             f"height, so give it a photo_inset")
            paint = column_paint(png)
            if paint < a.paint:
                flags.append(f"subject paints only {paint:.0%} of the photo column "
                             f"(floor {a.paint:.0%}) - the tile will read hollow. "
                             f"Frame the shot waist-up so the shoulders fill the "
                             f"column, rather than fitting a whole standing figure "
                             f"into it")
        if flags:
            failures.append((e["file"], flags))

        print(f"{e['file']:<34}{e['layout']:<14}{t['size']:>6.0f}{at400:>6.0f}"
              f"{c_main:>8.1f}{(f'{c_hi:.1f}' if c_hi else '-'):>8}"
              f"{(f'{paint:.0%}' if paint is not None else '-'):>8}"
              + ("   <-- " + "; ".join(flags) if flags else ""))

    sheet = contact_sheet(entries, OUT / "_listing-sheet.png")
    print(f"\nListing-size contact sheet: {sheet}")

    if failures:
        print(f"\n{len(failures)} below the floor:")
        for f, flags in failures:
            print(f"  ! {f}: {'; '.join(flags)}")
        sys.exit(1)
    print("\nAll headlines clear the floor.")


if __name__ == "__main__":
    main()
