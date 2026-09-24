"""A vision check that can actually say no (Gemini Flash), a rejected-clips
list that swaps feed and ranking honours, and a safer cold-open query."""
import json
from pathlib import Path

ROOT = Path("D:/faceless-studio")

# ---------------------------------------------------------------- relevance.py: Gemini vision
p = ROOT / "fvs/relevance.py"; s = p.read_text(encoding="utf-8")
old = '''def frame_matches(asset_key: str, video_path: str, duration: float, query: str) -> bool | None:
    """Does the clip plausibly show the query's subject?

    True/False when the model answered cleanly; None when the check could
    not run (no creds, extraction or HTTP failure, mumbled answer) - the
    caller must treat None as a pass.
    """
    cache = _load_cache()
    cache_key = f"{asset_key}|{query.lower().strip()}"
    if cache_key in cache:
        return cache[cache_key]

    image = _mid_frame(video_path, duration)
    if image is None:
        return None
    result = _run_model('''
new = '''# The vision check must be able to say NO. llava-1.5-7b on Workers AI said
# YES to keys and sunglasses for "picking up a coin", a bitcoin for "penny",
# a Minolta camera for "old worn pennies" and a map of Mexico in pesos for
# "coin minting press" (2026-09-04). Gemini Flash is the judge when a key is
# present; llava stays as the fallback so the stage never goes blind.
GEMINI_VISION_MODEL = "gemini-2.5-flash"
VISION_PROMPT = (
    "You are checking one frame of a stock video clip for a serious documentary "
    "about the US one-cent coin (the penny) and US government policy. The shot "
    "must illustrate: \\"{query}\\". Answer NO if the frame shows a different "
    "object than the one named, coins or notes of another country or a "
    "cryptocurrency, a wrong setting, unreadable or foreign-language text as "
    "the subject, sexual or suggestive content, or anything that would look "
    "wrong on screen while a narrator says \\"{text}\\". Answer YES only if a "
    "careful editor would use this frame for that line. Reply with one word: "
    "YES or NO."
)


def _gemini_verdict(image: bytes, query: str, text: str) -> bool | None:
    import base64
    import os

    key = os.environ.get("GEMINI_API_KEY")
    if not key:
        return None
    import httpx

    body = {
        "contents": [{"parts": [
            {"text": VISION_PROMPT.format(query=query, text=text or query)},
            {"inline_data": {"mime_type": "image/jpeg", "data": base64.b64encode(image).decode()}},
        ]}],
        "generationConfig": {"maxOutputTokens": 4, "temperature": 0},
    }
    try:
        r = httpx.post(
            f"https://generativelanguage.googleapis.com/v1beta/models/{GEMINI_VISION_MODEL}:generateContent",
            params={"key": key}, json=body, timeout=60,
        )
    except httpx.HTTPError:
        return None
    if r.status_code != 200:
        return None
    try:
        answer = r.json()["candidates"][0]["content"]["parts"][0]["text"]
    except (KeyError, IndexError, TypeError, ValueError):
        return None
    word = answer.strip().upper().lstrip(".:,! ")
    if word.startswith("YES"):
        return True
    if word.startswith("NO"):
        return False
    return None


def frame_matches(asset_key: str, video_path: str, duration: float, query: str,
                  text: str = "") -> bool | None:
    """Does the clip plausibly show the query's subject?

    True/False when the model answered cleanly; None when the check could
    not run (no creds, extraction or HTTP failure, mumbled answer) - the
    caller must treat None as a pass.
    """
    cache = _load_cache()
    cache_key = f"{asset_key}|{query.lower().strip()}|v2"
    if cache_key in cache:
        return cache[cache_key]

    image = _mid_frame(video_path, duration)
    if image is None:
        return None
    config.load_env()
    verdict = _gemini_verdict(image, query, text)
    if verdict is not None:
        cache[cache_key] = verdict
        _save_cache(cache)
        return verdict
    result = _run_model('''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- assets.py: pass the spoken text; honour rejected.json
p = ROOT / "fvs/stages/assets.py"; s = p.read_text(encoding="utf-8")
old = '''                verdict = relevance.frame_matches(
                    candidate.key, path, candidate.duration, query
                )'''
new = '''                verdict = relevance.frame_matches(
                    candidate.key, path, candidate.duration, query,
                    text=shot.get("text", ""),
                )'''
assert old in s; s = s.replace(old, new)
old = '''    data = json.loads(src.read_text(encoding="utf-8"))'''
new = '''    data = json.loads(src.read_text(encoding="utf-8"))
    # Clips a human displaced with `fvs swap` never come back for this
    # project, whatever the ranker thinks of them.
    rejected_path = root / "rejected.json"
    rejected: set[str] = set()
    if rejected_path.is_file():
        rejected = set(json.loads(rejected_path.read_text(encoding="utf-8")))'''
assert s.count(old) == 1; s = s.replace(old, new)
# exclusion set: find where `chosen` is first built and union the rejected keys
import re
m = re.search(r"\n(\s+)chosen(: set\[str\])? = ", s)
assert m, "chosen assignment not found"
indent = m.group(1)
insert_at = s.index("\n", m.end()) + 1
s = s[:insert_at] + f"{indent}chosen |= rejected\n" + s[insert_at:]
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- review.swap: record the displaced clip
p = ROOT / "fvs/review.py"; s = p.read_text(encoding="utf-8")
old = '''    shotlist.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")

    # Invalidate the cached segment so the next compose re-renders this shot.'''
new = '''    shotlist.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")

    # The displaced clip was refused by a human; the assets stage must
    # never pick it again for this project.
    rejected_path = root / "rejected.json"
    rejected = set(json.loads(rejected_path.read_text(encoding="utf-8"))) if rejected_path.is_file() else set()
    rejected.add(previous["key"])
    rejected_path.write_text(json.dumps(sorted(rejected), indent=1), encoding="utf-8")

    # Invalidate the cached segment so the next compose re-renders this shot.'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- the cold-open anchor
a = ROOT / "projects/penny-cost/anchors.json"
d = json.loads(a.read_text(encoding="utf-8"))
for x in d["anchors"]:
    if x["phrase"] == "There is a coin in your pocket":
        x["query"] = "coins in hand close up"
    if x["phrase"] == "Pick one up":
        x["query"] = "hand picking up coins close up"
    if x["phrase"] == "So what does a dead coin":
        x["query"] = "old coins on table macro"
    if x["phrase"] == "Two hundred and thirty-two years":
        x["query"] = "old coins collection macro"
    if x["phrase"] == "The Mint publishes":
        x["query"] = "coin counting machine close up"
a.write_text(json.dumps(d, indent=1, ensure_ascii=False), encoding="utf-8")
# mirror into the current shotlist so a refetch does not need a re-plan
sl = ROOT / "projects/penny-cost/shotlist.json"
data = json.loads(sl.read_text(encoding="utf-8"))
remap = {"hand reaching into jeans pocket for coins": "coins in hand close up",
         "fingers picking up a coin from a table": "hand picking up coins close up",
         "old penny on dusty surface macro": "old coins on table macro",
         "old worn pennies collection macro": "old coins collection macro",
         "coin minting press stamping coins": "coin counting machine close up"}
for shot in data["shots"]:
    if shot.get("query") in remap:
        shot["query"] = remap[shot["query"]]
        shot.pop("asset", None)
sl.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
cp = ROOT / "projects/penny-cost/cutplan.json"
data = json.loads(cp.read_text(encoding="utf-8"))
for shot in data["shots"]:
    if shot.get("query") in remap:
        shot["query"] = remap[shot["query"]]
cp.write_text(data and json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
# previously displaced junk goes straight onto the rejected list
rej = ROOT / "projects/penny-cost/rejected.json"
prior = {"pexels:6473432", "pexels:7986194", "pexels:7298376", "pexels:856219", "pexels:9580492",
         "pexels:30882814", "pexels:7103545", "pexels:6745972", "pexels:35120905", "pexels:4072123",
         "pexels:37000695", "pexels:6683955", "pexels:6980915"}
rej.write_text(json.dumps(sorted(prior), indent=1), encoding="utf-8")
print("patched relevance/assets/review + anchors + rejected list")
