"""Harden Gemini TTS: relative silence floor, 6 varied retries, paragraph cache.

Two different paragraphs "kept truncating" in two runs. Suspect one true
cause: _trim_silence uses an ABSOLUTE floor (0.01), so a legitimately quiet
take trims to almost nothing and is misread as truncated. Floor becomes
relative to the take's own peak. Retries also go to six with growing waits
and a per-attempt prompt nudge, and good paragraphs are cached to disk so
a failure late in a 39-paragraph run costs nothing on the re-run.

Raw strings throughout: this patch's predecessor died to backslash
collapse inside a bash heredoc (third such strike - scripts go in files).
"""

import ast
from pathlib import Path

p = Path("D:/faceless-studio/fvs/stages/voice.py")
s = p.read_text(encoding="utf-8")

# 1. Relative silence floor.
old = r'''def _trim_silence(audio, sr: int, floor: float = 0.01):
    """Cut the dead air Gemini sometimes appends; keep a natural tail."""
    import numpy as np

    loud = np.flatnonzero(np.abs(audio) > floor)'''
new = r'''def _trim_silence(audio, sr: int):
    """Cut the dead air Gemini sometimes appends; keep a natural tail.

    The floor is relative to the take's own peak: an absolute floor once
    trimmed a quiet-but-valid take to nothing, which then read as a
    truncation and burned every retry.
    """
    import numpy as np

    peak = float(np.abs(audio).max()) if len(audio) else 0.0
    floor = max(peak * 0.04, 1e-4)
    loud = np.flatnonzero(np.abs(audio) > floor)'''
if old in s:
    s = s.replace(old, new, 1)
assert "peak * 0.04" in s, "trim floor"

# 2. Six retries, growing backoff, per-attempt nudge.
old = r'''    words = max(len(text.split()), 1)
    for attempt in range(4):
        r = httpx.post(url, params={"key": key}, json=body, timeout=180)
        if r.status_code == 429:
            time.sleep(15 * (attempt + 1))
            continue'''
new = r'''    words = max(len(text.split()), 1)
    for attempt in range(6):
        # A truncated take replayed verbatim can truncate again; a trailing
        # nudge makes the request distinct without changing the speech.
        body["contents"][0]["parts"][0]["text"] = note + "\n\n" + text + "\n" * attempt
        r = httpx.post(url, params={"key": key}, json=body, timeout=180)
        if r.status_code == 429:
            time.sleep(15 * (attempt + 1))
            continue'''
if old in s:
    s = s.replace(old, new, 1)
assert "range(6)" in s, "retries"

old = "        if len(audio) / config.TTS_SAMPLE_RATE >= MIN_SPOKEN_PER_WORD * words:\n            return audio\n        time.sleep(2)\n"
new = "        if len(audio) / config.TTS_SAMPLE_RATE >= MIN_SPOKEN_PER_WORD * words:\n            return audio\n        time.sleep(8 * (attempt + 1))\n"
if old in s:
    s = s.replace(old, new, 1)
assert "8 * (attempt + 1)" in s, "backoff"

# 3. Per-paragraph cache, slug handed over via a module slot set by run().
if "_CACHE_SLUG" not in s:
    s = s.replace("MIN_SPOKEN_PER_WORD = 0.12", "_CACHE_SLUG: list = []\n\nMIN_SPOKEN_PER_WORD = 0.12", 1)
    old = r'''    offset = 0.0
    for index, para in enumerate(paragraphs, start=1):
        audio = _gemini_paragraph(para, voice, note)'''
    new = r'''    offset = 0.0
    # Per-paragraph cache: a failure at paragraph 35 must not cost the
    # first 34, and the re-run after any failure is nearly free.
    import hashlib

    cache_dir = None
    if _CACHE_SLUG:
        cache_dir = config.project_dir(_CACHE_SLUG[0]) / "tts_cache"
        cache_dir.mkdir(exist_ok=True)
    for index, para in enumerate(paragraphs, start=1):
        cached = None
        if cache_dir is not None:
            digest = hashlib.sha1(f"{voice}|{note}|{para}".encode("utf-8")).hexdigest()[:16]
            cached = cache_dir / f"p{index:03d}_{digest}.npy"
        if cached is not None and cached.is_file():
            audio = np.load(cached)
        else:
            audio = _gemini_paragraph(para, voice, note)
            if cached is not None:
                np.save(cached, audio)'''
    assert old in s, "cache anchor"
    s = s.replace(old, new, 1)

    old = r'''        if voice == config.DEFAULT_VOICE:
            voice = config.GEMINI_TTS_VOICE
        audio, spans = synthesize_gemini(paragraphs, voice=voice, section_ends=section_ends)'''
    new = r'''        if voice == config.DEFAULT_VOICE:
            voice = config.GEMINI_TTS_VOICE
        _CACHE_SLUG.clear()
        _CACHE_SLUG.append(slug)
        audio, spans = synthesize_gemini(paragraphs, voice=voice, section_ends=section_ends)'''
    assert old in s, "slug anchor"
    s = s.replace(old, new, 1)

ast.parse(s)
p.write_text(s, encoding="utf-8")
print("  voice.py: relative trim floor, 6 varied retries, paragraph cache")
