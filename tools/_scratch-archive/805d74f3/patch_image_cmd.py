"""`fvs image`: prompt -> FLUX.1 schnell on Cloudflare Workers AI -> the
paragraph's still, in one command.

Free tier: 10,000 neurons/day, roughly 130 generations at steps=8. Output
is 1024x1024 JPEG; import_still upscales to 2400 wide and the Ken Burns
crop hides the rest. Generated imagery still means `fvs package
--synthetic`, exactly as with a Gemini image.
"""

import ast
from pathlib import Path

STUDIO = Path("D:/faceless-studio")

# --- stills.py: the generator ------------------------------------------------
p = STUDIO / "fvs/stills.py"
src = p.read_text(encoding="utf-8")
if "def generate_flux" not in src:
    src += '''

# --- generated stills (Cloudflare Workers AI) -------------------------------

FLUX_MODEL = "@cf/black-forest-labs/flux-1-schnell"
# House style appended to every prompt so generations sit in the grade
# rather than fighting it.
FLUX_STYLE = (
    ", documentary photograph, natural light, muted colour, shallow depth of field, "
    "no text, no watermark"
)


def generate_flux(prompt: str, steps: int = 8, seed: int | None = None) -> bytes:
    """One image from FLUX.1 schnell; returns JPEG bytes.

    Free allocation is 10,000 neurons/day (~130 images at 8 steps), so
    failures here are quota or auth, never billing.
    """
    import base64
    import os

    import httpx

    config.load_env()
    account = os.environ.get("CLOUDFLARE_ACCOUNT_ID")
    token = os.environ.get("CLOUDFLARE_API_TOKEN")
    if not account or not token:
        raise RuntimeError("CLOUDFLARE_ACCOUNT_ID / CLOUDFLARE_API_TOKEN not set (see .env)")
    body: dict[str, Any] = {"prompt": (prompt + FLUX_STYLE)[:2048], "steps": min(max(steps, 1), 8)}
    if seed is not None:
        body["seed"] = seed
    r = httpx.post(
        f"https://api.cloudflare.com/client/v4/accounts/{account}/ai/run/{FLUX_MODEL}",
        headers={"Authorization": f"Bearer {token}"}, json=body, timeout=120,
    )
    data = r.json() if r.headers.get("content-type", "").startswith("application/json") else {}
    if r.status_code != 200 or not data.get("success"):
        raise RuntimeError(f"Workers AI HTTP {r.status_code}: {str(data.get('errors'))[:200]}")
    return base64.b64decode(data["result"]["image"])
'''
    ast.parse(src)
    p.write_text(src, encoding="utf-8")
print("  stills.py   generate_flux")

# --- cli.py ------------------------------------------------------------------
p = STUDIO / "fvs/cli.py"
src = p.read_text(encoding="utf-8")
if "def cmd_image" not in src:
    cmd = '''def cmd_image(args: argparse.Namespace) -> int:
    import tempfile
    from pathlib import Path as _P

    from . import stills

    slug = _slugify(args.slug)
    try:
        data = stills.generate_flux(args.prompt, steps=args.steps, seed=args.seed)
        with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as fh:
            fh.write(data)
            tmp = _P(fh.name)
        rec = stills.import_still(
            slug, args.paragraph, tmp, args.direction,
            args.why or args.prompt[:80], "generated (FLUX.1 schnell, Cloudflare Workers AI)",
        )
        tmp.unlink(missing_ok=True)
    except Exception as exc:
        print(f"  {type(exc).__name__}: {exc}")
        return 1
    print(f"  p{args.paragraph} -> {rec['file']}  {rec['width']}x{rec['height']}")
    print("  next: fvs plan, fvs compose. Generated imagery: fvs package --synthetic")
    return 0


def cmd_package('''
    assert src.count("def cmd_package(") == 1
    src = src.replace("def cmd_package(", cmd, 1)

    parser = '''    p = sub.add_parser("image", help="generate a still with FLUX (free) and book it to a paragraph")
    p.add_argument("slug")
    p.add_argument("paragraph", type=int)
    p.add_argument("prompt", help="what the image shows; house style is appended")
    p.add_argument("--steps", type=int, default=8)
    p.add_argument("--seed", type=int, default=None)
    p.add_argument("--direction", default="in", choices=["in", "out"], help="Ken Burns push")
    p.add_argument("--why", default="", help="what this shows and why this paragraph")
    p.set_defaults(func=cmd_image)

    p = sub.add_parser("shorts", '''
    assert src.count('    p = sub.add_parser("shorts", ') == 1
    src = src.replace('    p = sub.add_parser("shorts", ', parser, 1)
    ast.parse(src)
    p.write_text(src, encoding="utf-8")
print("  cli.py      fvs image <slug> <paragraph> PROMPT [--steps 8] [--seed N]")
