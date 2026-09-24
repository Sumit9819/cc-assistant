"""Chunked Gemini TTS: sections, not paragraphs, so a video fits the quota.

The probe ended the guessing: every "truncation" was HTTP 429 -
generate_content_free_tier_requests, limit 10 - swallowed by the retry
loop and re-raised under the wrong name. Thirty-nine per-paragraph
requests can never fit ten. One request per SECTION (six for this script)
can. Pauses stay controlled at chunk seams (which are section ends, where
the long pause lives anyway); inside a chunk the model's own prosody
carries paragraph breaks. Alignment goes global per chunk: Whisper words
are mapped onto the flattened script words of the chunk's paragraphs, and
each script word already knows its paragraph.

Raw strings; ast.parse before write.
"""

import ast
from pathlib import Path

STUDIO = Path("D:/faceless-studio")

# --- voice.py ----------------------------------------------------------------
p = STUDIO / "fvs/stages/voice.py"
s = p.read_text(encoding="utf-8")

# 1. Honest 429 accounting in _gemini_paragraph.
old = r'''    words = max(len(text.split()), 1)
    for attempt in range(6):'''
new = r'''    words = max(len(text.split()), 1)
    quota_hits = 0
    for attempt in range(6):'''
if "quota_hits" not in s:
    assert old in s, "retry head"
    s = s.replace(old, new, 1)

old = r'''        if r.status_code == 429:
            time.sleep(15 * (attempt + 1))
            continue'''
new = r'''        if r.status_code == 429:
            quota_hits += 1
            time.sleep(15 * (attempt + 1))
            continue'''
if "quota_hits += 1" not in s:
    assert old in s, "429 branch"
    s = s.replace(old, new, 1)

old = r'''    raise RuntimeError(f"Gemini TTS kept truncating: {text[:50]!r}")'''
new = r'''    if quota_hits >= 3:
        raise RuntimeError(
            "Gemini free-tier request quota exhausted (limit ~10/day for this "
            "model). It resets daily; re-run then, or use --engine kokoro."
        )
    raise RuntimeError(f"Gemini TTS kept truncating: {text[:50]!r}")'''
if "quota exhausted" not in s:
    assert old in s, "raise anchor"
    s = s.replace(old, new, 1)

# 2. Chunked synthesis replaces the per-paragraph loop.
old_loop = r'''    section_ends = section_ends or set()
    sr = config.TTS_SAMPLE_RATE
    chunks: list[Any] = []
    spans: list[dict[str, Any]] = []
    offset = 0.0
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
                np.save(cached, audio)
        spans.append({"paragraph": index, "start": round(offset, 4), "end": round(offset + len(audio) / sr, 4)})
        nxt = paragraphs[index] if index < len(paragraphs) else None
        hold = pause_after(para, nxt, ends_section=(index - 1) in section_ends)
        chunks.extend([audio, np.zeros(int(hold * sr), dtype=np.float32)])
        offset += len(audio) / sr + hold
        if progress:
            print(f"  [{index}/{len(paragraphs)}] {offset:6.1f}s  {para[:58]}...")
        # Free-tier pacing: never hammer the endpoint.
        time.sleep(3)
    if not chunks:
        raise ValueError("Script produced no spoken paragraphs")
    return np.concatenate(chunks[:-1]), spans'''
new_loop = r'''    section_ends = section_ends or set()
    sr = config.TTS_SAMPLE_RATE

    # One request per SECTION: the free tier allows ~10 requests/day, so
    # per-paragraph synthesis can never voice a 39-paragraph script. The
    # long controlled pause lives at section ends anyway; inside a chunk
    # the model's own prosody carries the paragraph breaks.
    groups: list[list[int]] = []
    current: list[int] = []
    for index in range(1, len(paragraphs) + 1):
        current.append(index)
        if (index - 1) in section_ends or index == len(paragraphs):
            groups.append(current)
            current = []
    if len(groups) > 9:
        raise RuntimeError(f"{len(groups)} sections would exceed the daily request quota")

    import hashlib

    cache_dir = None
    if _CACHE_SLUG:
        cache_dir = config.project_dir(_CACHE_SLUG[0]) / "tts_cache"
        cache_dir.mkdir(exist_ok=True)

    chunks: list[Any] = []
    spans: list[dict[str, Any]] = []
    offset = 0.0
    for g_index, group in enumerate(groups, start=1):
        text = "\n\n".join(paragraphs[i - 1] for i in group)
        cached = None
        if cache_dir is not None:
            digest = hashlib.sha1(f"{voice}|{note}|{text}".encode("utf-8")).hexdigest()[:16]
            cached = cache_dir / f"c{g_index:02d}_{digest}.npy"
        if cached is not None and cached.is_file():
            audio = np.load(cached)
        else:
            audio = _gemini_paragraph(text, voice, note)
            if cached is not None:
                np.save(cached, audio)
            time.sleep(6)
        spans.append({
            "paragraph": group[0],
            "paragraphs": list(group),
            "start": round(offset, 4),
            "end": round(offset + len(audio) / sr, 4),
        })
        hold = PAUSE_SECTION_END if g_index < len(groups) else 0.0
        chunks.extend([audio, np.zeros(int(hold * sr), dtype=np.float32)])
        offset += len(audio) / sr + hold
        if progress:
            print(f"  [chunk {g_index}/{len(groups)}] p{group[0]}-p{group[-1]}  {offset:6.1f}s")
    if not chunks:
        raise ValueError("Script produced no spoken paragraphs")
    return np.concatenate(chunks[:-1]), spans'''
if "One request per SECTION" not in s:
    assert old_loop in s, "chunk loop anchor"
    s = s.replace(old_loop, new_loop, 1)

ast.parse(s)
p.write_text(s, encoding="utf-8")
print("  voice.py    section-chunked synthesis, honest quota error")

# --- align.py: chunk spans hold several paragraphs ---------------------------
p = STUDIO / "fvs/stages/align.py"
s = p.read_text(encoding="utf-8")

old = r'''    for span in data["paragraph_spans"]:
        index = span["paragraph"]
        text = data["paragraphs"][index - 1]
        script_words = text.split()
        if not script_words:
            continue
        a, b = int(span["start"] * sr), int(span["end"] * sr)
        segments, _ = model.transcribe(audio[a:b], language="en", word_timestamps=True,
                                       beam_size=1, vad_filter=False, condition_on_previous_text=False)
        heard = [
            {"word": w.word, "start": span["start"] + w.start, "end": span["start"] + w.end}
            for seg in segments for w in (seg.words or [])
        ]
        spans, matched = _map_words(script_words, heard, span["start"], span["end"])
        matched_total += matched
        total_words += len(script_words)
        if matched < 0.6 * len(script_words):
            weak += 1
        for word, (s, e) in zip(script_words, spans):
            words_out.append({"word": word, "start": round(s, 4), "end": round(e, 4), "paragraph": index})'''
new = r'''    for span in data["paragraph_spans"]:
        # A span may cover one paragraph (old files) or a whole section
        # chunk; every script word carries its own paragraph either way.
        para_ids = span.get("paragraphs") or [span["paragraph"]]
        tagged = [
            (pid, w)
            for pid in para_ids
            for w in data["paragraphs"][pid - 1].split()
        ]
        if not tagged:
            continue
        script_words = [w for _, w in tagged]
        a, b = int(span["start"] * sr), int(span["end"] * sr)
        segments, _ = model.transcribe(audio[a:b], language="en", word_timestamps=True,
                                       beam_size=1, vad_filter=False, condition_on_previous_text=False)
        heard = [
            {"word": w.word, "start": span["start"] + w.start, "end": span["start"] + w.end}
            for seg in segments for w in (seg.words or [])
        ]
        spans, matched = _map_words(script_words, heard, span["start"], span["end"])
        matched_total += matched
        total_words += len(script_words)
        if matched < 0.6 * len(script_words):
            weak += 1
        for (pid, word), (s, e) in zip(tagged, spans):
            words_out.append({"word": word, "start": round(s, 4), "end": round(e, 4), "paragraph": pid})'''
if "para_ids" not in s:
    assert old in s, "align chunk anchor"
    s = s.replace(old, new, 1)

ast.parse(s)
p.write_text(s, encoding="utf-8")
print("  align.py    chunk spans map to per-paragraph words")
