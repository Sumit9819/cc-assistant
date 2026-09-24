"""Raw vision probe: print status and body for candidate models/shapes."""

import json
import os
import sys

import httpx

sys.path.insert(0, "D:/faceless-studio")
from fvs import config, relevance  # noqa: E402

config.load_env()
ACCT = os.environ["CLOUDFLARE_ACCOUNT_ID"]
TOKEN = os.environ["CLOUDFLARE_API_TOKEN"]

sl = json.loads((config.project_dir("roman-concrete") / "shotlist.json")
                .read_text(encoding="utf-8"))["shots"]
shot = next(s for s in sl if s.get("asset") and s.get("query"))
asset = shot["asset"]
image = relevance._mid_frame(asset["path"], float(asset.get("duration") or 4))
print(f"clip {asset['key']} query={shot['query']!r} frame {len(image)} bytes")

prompt = ("Could this footage plausibly illustrate the subject "
          f"\"{shot['query']}\"? Answer with only YES or NO.")

attempts = [
    ("@cf/meta/llama-3.2-11b-vision-instruct", {"prompt": prompt, "image": list(image), "max_tokens": 8}),
    ("@cf/meta/llama-3.2-11b-vision-instruct",
     {"messages": [{"role": "user", "content": prompt}], "image": list(image), "max_tokens": 8}),
    ("@cf/llava-hf/llava-1.5-7b-hf", {"prompt": prompt, "image": list(image), "max_tokens": 8}),
]
for model, payload in attempts:
    r = httpx.post(
        f"https://api.cloudflare.com/client/v4/accounts/{ACCT}/ai/run/{model}",
        headers={"Authorization": f"Bearer {TOKEN}"}, json=payload, timeout=120,
    )
    body = r.text[:400].replace("\n", " ")
    print(f"== {model} [{'prompt' if 'prompt' in payload else 'messages'}] HTTP {r.status_code}: {body}")
