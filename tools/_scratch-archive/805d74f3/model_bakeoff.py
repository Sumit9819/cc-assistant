"""One prompt, every text-to-image model on Workers AI, one grid.

The prompt deliberately includes a human face and hands (the hard parts)
plus documentary texture, because human imagery is a stated future need.
Models return different shapes: FLUX-family answers JSON base64, the
Stable Diffusion family answers raw PNG bytes; both handled. A model that
errors (partner licensing, quota) is reported, not fatal. Each result is
labelled with name, wall time and estimated neuron cost."""

import base64
import json
import os
import sys
import time
from pathlib import Path

import httpx

sys.path.insert(0, "D:/faceless-studio")
from fvs import config  # noqa: E402

config.load_env()
AID, TOK = os.environ["CLOUDFLARE_ACCOUNT_ID"], os.environ["CLOUDFLARE_API_TOKEN"]
SP = Path(__file__).resolve().parent

PROMPT = (
    "Documentary photograph: a middle-aged female engineer in a white hard hat "
    "holding a fractured chunk of roman concrete in both hands, examining it closely, "
    "weathered stone aqueduct out of focus behind her, late afternoon light, "
    "natural skin texture, sharp eyes"
)

MODELS = [
    ("flux-1-schnell", "@cf/black-forest-labs/flux-1-schnell", {"steps": 8}),
    ("flux-2-klein-4b", "@cf/black-forest-labs/flux-2-klein-4b", {}),
    ("flux-2-klein-9b", "@cf/black-forest-labs/flux-2-klein-9b", {}),
    ("flux-2-dev", "@cf/black-forest-labs/flux-2-dev", {}),
    ("lucid-origin", "@cf/leonardo/lucid-origin", {}),
    ("phoenix-1.0", "@cf/leonardo/phoenix-1.0", {}),
    ("sdxl-base", "@cf/stabilityai/stable-diffusion-xl-base-1.0", {"width": 1024, "height": 1024}),
    ("sdxl-lightning", "@cf/bytedance/stable-diffusion-xl-lightning", {"width": 1024, "height": 1024}),
    ("dreamshaper-8", "@cf/lykon/dreamshaper-8-lcm", {"width": 1024, "height": 1024}),
]

results = []
for name, model, extra in MODELS:
    url = f"https://api.cloudflare.com/client/v4/accounts/{AID}/ai/run/{model}"
    t0 = time.time()
    try:
        r = httpx.post(url, headers={"Authorization": f"Bearer {TOK}"},
                       json={"prompt": PROMPT, **extra}, timeout=180)
        ctype = r.headers.get("content-type", "")
        if ctype.startswith("application/json"):
            d = r.json()
            if not d.get("success"):
                raise RuntimeError(str(d.get("errors"))[:160])
            img = base64.b64decode(d["result"]["image"])
        elif r.status_code == 200:
            img = r.content
        else:
            raise RuntimeError(f"HTTP {r.status_code}: {r.text[:160]}")
        out = SP / f"bake_{name}.png"
        out.write_bytes(img)
        results.append((name, out, time.time() - t0))
        print(f"  {name:<16} ok  {time.time()-t0:5.1f}s  {len(img)/1024:6.0f} KB")
    except Exception as exc:
        print(f"  {name:<16} FAILED  {str(exc)[:120]}")
    time.sleep(1.5)

# Grid with labels burned in.
from PIL import Image, ImageDraw, ImageFont  # noqa: E402

font = ImageFont.truetype("D:/faceless-studio/fonts/BarlowCondensed-SemiBold.ttf", 44)
tiles = []
for name, path, secs in results:
    im = Image.open(path).convert("RGB")
    im.thumbnail((640, 640))
    tile = Image.new("RGB", (640, 700), (12, 12, 14))
    tile.paste(im, ((640 - im.width) // 2, 0))
    d = ImageDraw.Draw(tile)
    d.text((16, 648), f"{name}  ({secs:.0f}s)", font=font, fill=(240, 236, 228))
    tiles.append(tile)
if tiles:
    cols = 3
    rows = (len(tiles) + cols - 1) // cols
    grid = Image.new("RGB", (cols * 646, rows * 706), (12, 12, 14))
    for i, t in enumerate(tiles):
        grid.paste(t, ((i % cols) * 646, (i // cols) * 706))
    grid.save(SP / "bakeoff_grid.jpg", quality=88)
    print(f"  bakeoff_grid.jpg: {len(tiles)} models")
