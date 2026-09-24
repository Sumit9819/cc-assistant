"""Probe the rerank + vision endpoints with positive AND negative controls
before wiring them into the assets stage. No guessing about API shapes."""

import json
import sys

sys.path.insert(0, "D:/faceless-studio")
from fvs import config, relevance  # noqa: E402

config.load_env()

# --- rerank: obvious ordering ------------------------------------------------
result = relevance._run_model(
    relevance.RERANK_MODEL,
    {"query": "ancient roman temple interior",
     "contexts": [{"text": "pantheon rome interior dome ancient"},
                  {"text": "aerial view of modern city traffic"},
                  {"text": "man typing on laptop in office"}]},
    timeout=30,
)
print("rerank raw:", json.dumps(result)[:300])

# --- vision: one frame, its real subject, and a nonsense subject -------------
sl = json.loads((config.project_dir("roman-concrete") / "shotlist.json")
                .read_text(encoding="utf-8"))["shots"]
shot = next(s for s in sl if s.get("asset") and s.get("query"))
asset = shot["asset"]
print(f"control clip: {asset['key']}  query={shot['query']!r}")

pos = relevance.frame_matches(asset["key"] + "|probe", asset["path"],
                              float(asset.get("duration") or 4), shot["query"])
neg = relevance.frame_matches(asset["key"] + "|probe-neg", asset["path"],
                              float(asset.get("duration") or 4),
                              "a penguin colony on antarctic ice")
print(f"vision positive control: {pos}   (expect True)")
print(f"vision negative control: {neg}   (expect False)")
