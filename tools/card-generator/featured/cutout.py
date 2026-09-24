"""
Background removal for featured images.

Runs a saliency-segmentation model directly through onnxruntime. Deliberately
not rembg: onnxruntime, Pillow and numpy were already installed here for other
work, so the only thing rembg would add is a dependency tree and a download path
we do not control. The model file is the whole dependency.

  python cutout.py src/photo.png                      -> cache/<sha1>.png (RGBA)
  python cutout.py src/photo.png --out cut/photo.png
  python cutout.py src/photo.png --model u2net        -> pick a model
  python cutout.py src/photo.png --trim               -> crop to the subject
  python cutout.py src/photo.png --contact sheet.png  -> side-by-side proof

Results cache on the sha1 of the source bytes plus the model name, so re-running
a spec after a layout change costs nothing. Delete cache/ to force a re-cut.

MODEL LICENCES matter here because this is client work. Both models below are
Apache-2.0 and fine commercially. BRIA RMBG-1.4 is better than either on hair
and is NOT free for commercial use - do not add it without buying the licence.
"""
import argparse
import hashlib
import pathlib
import sys

import numpy as np
import onnxruntime as ort
from PIL import Image, ImageFilter

HERE = pathlib.Path(__file__).parent
MODELS_DIR = HERE / "models"
CACHE = HERE / "cache"

# Each network was trained at one input size with one normalisation. These are
# not tunable: change them and the mask quietly degrades without erroring.
MODELS = {
    "u2net": {
        "file": "u2net.onnx",
        "size": 320,
        "mean": (0.485, 0.456, 0.406),
        "std": (0.229, 0.224, 0.225),
    },
    "isnet": {
        "file": "isnet-general-use.onnx",
        "size": 1024,
        "mean": (0.5, 0.5, 0.5),
        "std": (1.0, 1.0, 1.0),
    },
}
DEFAULT_MODEL = "isnet"

_sessions = {}


def session(name):
    if name not in _sessions:
        cfg = MODELS[name]
        path = MODELS_DIR / cfg["file"]
        if not path.exists():
            sys.exit(f"Missing model: {path}\nDownload {cfg['file']} into models/ first.")
        _sessions[name] = ort.InferenceSession(
            str(path), providers=["CPUExecutionProvider"]
        )
    return _sessions[name]


def mask_for(im, model=DEFAULT_MODEL):
    """Return a single-channel mask (mode L) at the image's own size."""
    cfg = MODELS[model]
    sess = session(model)
    n = cfg["size"]

    small = im.convert("RGB").resize((n, n), Image.LANCZOS)
    a = np.array(small, dtype=np.float32)
    # Both reference implementations scale by the image maximum rather than a
    # flat 255. On a photo with no pure-white pixel the two differ, and matching
    # the reference matters more here than being principled about it.
    peak = a.max()
    a = a / (peak if peak > 0 else 1.0)
    a = (a - np.array(cfg["mean"], dtype=np.float32)) / np.array(cfg["std"], dtype=np.float32)
    x = a.transpose(2, 0, 1)[None].astype(np.float32)

    # These networks emit several side outputs at descending depths; the first
    # is the fused one and the only one worth reading.
    out = sess.run(None, {sess.get_inputs()[0].name: x})[0]
    pred = out[:, 0, :, :].squeeze()
    lo, hi = float(pred.min()), float(pred.max())
    pred = (pred - lo) / (hi - lo) if hi > lo else np.zeros_like(pred)

    return Image.fromarray((pred * 255).astype(np.uint8)).resize(im.size, Image.LANCZOS)


def coverage(mask):
    """Fraction of the frame the subject occupies, counting solid pixels only."""
    return float((np.asarray(mask, dtype=np.float32) / 255.0 > 0.5).mean())


def holes(mask):
    """
    Fraction of the subject's own bounding box that is transparent.

    The guard that coverage alone misses. On a clinical photo the subject is
    often light-on-light - a white towel against a white wall - and the model
    returns a mask with the face solid and the body punched out. Coverage still
    reads as a plausible 20%, so only the SHAPE of the mask reveals it.
    """
    a = np.asarray(mask, dtype=np.float32) / 255.0
    solid = a > 0.5
    if not solid.any():
        return 1.0
    rows, cols = np.where(solid)
    box = solid[rows.min():rows.max() + 1, cols.min():cols.max() + 1]
    return float(1.0 - box.mean())


def edge_contact(mask, thresh=8, ignore=0.02):
    """
    Which edges of the frame the subject runs off, as {edge: fraction}.

    This is the defect no layout can repair, and the one that kept coming back:
    if the PHOTOGRAPH already cut the subject, compositing it onto a clean canvas
    just presents the amputation on a nicer background. Measured on the mask
    rather than judged by eye, because at full size the brain reads a cropped
    shoulder as an intentional crop and moves on.

    'bottom' is reported but not a fault: a standing figure rests on the bottom
    edge and a bust portrait ends there, both correctly. Top, left and right
    contact all mean a head or a shoulder is missing.
    """
    a = np.asarray(mask, dtype=np.float32)
    h, w = a.shape
    out = {}
    for name, strip in (("top", a[0, :]), ("bottom", a[-1, :]),
                        ("left", a[:, 0]), ("right", a[:, -1])):
        frac = float((strip > thresh).mean())
        if frac > ignore:
            out[name] = frac
    return out


def alpha_bbox(im):
    """
    Bounding box of the non-transparent pixels.

    Image.getbbox() reads every channel, and the RGB of a cut-out photo is
    non-zero everywhere, so it always returns the full frame. Only alpha says
    where the subject actually is.
    """
    a = np.asarray(im.getchannel("A"))
    rows, cols = np.where(a > 8)
    if not len(rows):
        return None
    return (int(cols.min()), int(rows.min()), int(cols.max()) + 1, int(rows.max()) + 1)


def contact_sheet(original, cut_im, dest, width=1500):
    """
    Original beside the cutout on magenta, so every edge defect is visible.

    Written because judging a cutout from the RGBA file alone is impossible:
    transparent and white look identical in most viewers, which is exactly how a
    subject with its body missing gets approved.
    """
    half = width // 2
    h = int(original.height * half / original.width)

    over = Image.new("RGBA", cut_im.size, (255, 0, 255, 255))
    over.alpha_composite(cut_im)

    sheet = Image.new("RGB", (width, h), (255, 255, 255))
    # Each half is FITTED, not stretched to the other's aspect. --trim changes
    # the cutout's proportions, so forcing it into the original's box made a
    # correctly cut figure look squat and wrong - the review then reports a
    # defect the file does not have, which is worse than no review at all.
    for i, im in enumerate((original.convert("RGB"), over.convert("RGB"))):
        t = im.copy()
        t.thumbnail((half, h), Image.LANCZOS)
        sheet.paste(t, (i * half + (half - t.width) // 2, (h - t.height) // 2))
    sheet.save(dest)
    return dest


def defringe(im, radius=6):
    """
    Replace the COLOUR of part-transparent edge pixels with the subject's own,
    leaving alpha untouched.

    A segmentation matte is soft at the edge, and those soft pixels keep the RGB
    of the photograph - which at the edge of the subject is mostly the studio
    background. Measured on this set: the edge band averages RGB 177 while the
    subject core averages 140, so every cutout carries a light-grey rim.

    On the pale layouts that rim composites into a pale ground and nobody sees
    it. On `photo-dark` it is a halo, and curly hair - 2.9% of the frame in
    part-transparent pixels against 1.9% for tied-back hair - turns it into a
    visible glow around the head. This is a colour-decontamination step, the
    same idea as unpremultiplying against the wrong background: extend the
    subject's colour outward under the existing alpha rather than nudging the
    silhouette, so the mask keeps the shape the model found.

    Implemented as a blur of the opaque colours divided by a blur of the opaque
    mask, which is the cheap way to get "nearest solid colour" without a
    distance transform and without scipy.
    """
    a = np.asarray(im, dtype=np.float64)
    rgb, alpha = a[:, :, :3], a[:, :, 3] / 255.0
    solid = (alpha > 0.9).astype(np.float64)
    if solid.sum() < 64:
        return im

    def blur(chan):
        img = Image.fromarray(np.clip(chan, 0, 255).astype(np.uint8))
        return np.asarray(img.filter(ImageFilter.GaussianBlur(radius)), dtype=np.float64)

    den = blur(solid * 255.0) / 255.0
    ok = den > 0.02
    filled = np.zeros_like(rgb)
    for c in range(3):
        num = blur(rgb[:, :, c] * solid) 
        filled[:, :, c] = np.where(ok, num / np.maximum(den, 1e-6), rgb[:, :, c])

    edge = ((alpha > 0.004) & (alpha < 0.9))[:, :, None]
    out = np.where(edge, filled, rgb)
    return Image.fromarray(
        np.concatenate([np.clip(out, 0, 255), (alpha * 255)[:, :, None]], axis=2)
        .astype(np.uint8))


def cut(path, out=None, model=DEFAULT_MODEL, trim=False, sheet=None,
        floor=0.05, ceiling=0.95, max_holes=0.55, max_edge=0.04, strict=True,
        fringe=True):
    src = pathlib.Path(path)
    original = Image.open(src)
    im = original.convert("RGBA")

    digest = hashlib.sha1(src.read_bytes() + model.encode()).hexdigest()[:16]
    CACHE.mkdir(exist_ok=True)
    dest = pathlib.Path(out) if out else CACHE / f"{digest}.png"
    dest.parent.mkdir(parents=True, exist_ok=True)

    mask = mask_for(im, model)
    cov, hol = coverage(mask), holes(mask)
    edges = edge_contact(mask)

    # The failure these guards exist for is silent. A model that finds nothing
    # returns a near-empty mask and the composite ships as a headline floating
    # over a blank panel, which looks intentional. A model that finds everything
    # returns the photo with its background intact, which looks like the cutout
    # step never ran. A model that finds half the subject returns a person with
    # no torso. All three are visible in the mask and in none of the other
    # checks, so they are measured here rather than left to a human noticing.
    problems = []
    if cov < floor:
        problems.append(f"subject covers only {cov:.1%} of the frame "
                        f"(floor {floor:.0%}) - no clear subject found")
    if cov > ceiling:
        problems.append(f"subject covers {cov:.1%} (ceiling {ceiling:.0%}) "
                        f"- nothing was removed")
    if hol > max_holes:
        problems.append(f"{hol:.0%} of the subject's own bounding box is transparent "
                        f"(max {max_holes:.0%}) - the subject is full of holes, which "
                        f"is what happens when it is the same value as its background")

    # The one no layout can repair. If the photograph already cut the subject,
    # compositing it onto a clean canvas only presents the amputation on a nicer
    # background - which is exactly the "her shoulder is cut" and "her head is
    # cut" the operator kept seeing after two rounds of layout fixes.
    cropped = {k: v for k, v in edges.items() if k != "bottom" and v > max_edge}
    if cropped:
        where = ", ".join(f"{k} {v:.0%}" for k, v in sorted(cropped.items()))
        problems.append(f"the subject runs off the frame ({where}) - the PHOTOGRAPH "
                        f"crops it, so a head or a shoulder is missing and no layout can "
                        f"put it back. Pick a shot with the whole subject inside the frame")

    im.putalpha(mask)
    if fringe:
        im = defringe(im)
    if trim:
        box = alpha_bbox(im)
        if box:
            im = im.crop(box)

    if sheet:
        contact_sheet(original, im, sheet)

    if problems and strict:
        for p in problems:
            print(f"  ! {src.name}: {p}", file=sys.stderr)
        sys.exit(f"REFUSED {src.name} [{model}] - see above. Pass --force to write "
                 f"it anyway, then look at the contact sheet before using it.")

    im.save(dest)
    flag = "  <-- PROBLEM" if problems else ""
    edge_note = ("  edges " + ",".join(f"{k}:{v:.0%}" for k, v in sorted(edges.items()))
                 if edges else "  edges clear")
    print(f"{src.name} [{model}] -> {dest.name}  subject {cov:.0%}  holes {hol:.0%}"
          f"{edge_note}  {im.size[0]}x{im.size[1]}{flag}")
    for p in problems:
        print(f"  ! {p}")
    return dest


if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("src", nargs="+")
    p.add_argument("--out")
    p.add_argument("--model", default=DEFAULT_MODEL, choices=sorted(MODELS))
    p.add_argument("--trim", action="store_true", help="crop to the subject bounding box")
    p.add_argument("--contact", help="write an original-vs-cutout proof sheet here")
    p.add_argument("--force", action="store_true", help="write even if a guard fires")
    p.add_argument("--keep-fringe", action="store_true",
                   help="skip edge colour decontamination (leaves a light halo on dark grounds)")
    a = p.parse_args()
    if a.out and len(a.src) > 1:
        sys.exit("--out takes a single source")
    for s in a.src:
        cut(s, a.out, a.model, a.trim, a.contact, strict=not a.force,
            fringe=not a.keep_fringe)
