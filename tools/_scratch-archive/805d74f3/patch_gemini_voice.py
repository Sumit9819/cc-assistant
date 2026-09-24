"""Gemini TTS as a narration engine, with Whisper word alignment behind it.

Kokoro gives phoneme timings for free; Gemini gives none, and the whole
cut-to-the-word rhythm depends on them. So the Gemini path synthesises
paragraph by paragraph (exact paragraph spans, cheap retries), guards
against the truncation seen in the trial (speech then a minute of
silence), and the align stage runs faster-whisper on each paragraph's
slice, mapping transcript words back onto the script words with a
sequence match. Matched words take Whisper's times; the rest are spread
between their matched neighbours.

Applies to: fvs/config.py, fvs/stages/voice.py, fvs/stages/align.py,
fvs/cli.py.
"""

import ast
import re
from pathlib import Path

STUDIO = Path("D:/faceless-studio")


def patch(rel, fn):
    p = STUDIO / rel
    src = p.read_text(encoding="utf-8")
    out = fn(src)
    ast.parse(out)
    p.write_text(out, encoding="utf-8")
    print(f"  {rel}")


# --- config ------------------------------------------------------------------
def config_patch(src):
    if "GEMINI_TTS_MODEL" in src:
        return src
    anchor = 'DEFAULT_VOICE = "am_onyx"\n'
    assert anchor in src
    return src.replace(anchor, anchor + '''
# Gemini TTS (free tier verified 2026-08). Per-paragraph requests; the
# director's note is honoured, so it carries the read's tone.
GEMINI_TTS_MODEL = "gemini-2.5-flash-preview-tts"
GEMINI_TTS_VOICE = "Sadaltager"
GEMINI_TTS_NOTE = (
    "Read this as the narrator of a calm, serious documentary. Measured pace, "
    "low and even, no salesmanship. Let short sentences land."
)
# Whisper for word timings when the engine gives none. `small` is the
# largest that fits beside everything else on a 6 GB machine.
WHISPER_MODEL = "small"
''', 1)


# --- voice -------------------------------------------------------------------
def voice_patch(src):
    if "def synthesize_gemini" not in src:
        anchor = "def run(\n    slug: str,\n    record: dict[str, Any],\n"
        assert anchor in src, "voice.run anchor"
        src = src.replace(anchor, '''MIN_SPOKEN_PER_WORD = 0.12   # seconds; less than this and the take was cut off
TRAILING_SILENCE_MAX = 1.2   # seconds kept after the last speech


def _trim_silence(audio, sr: int, floor: float = 0.01):
    """Cut the dead air Gemini sometimes appends; keep a natural tail."""
    import numpy as np

    loud = np.flatnonzero(np.abs(audio) > floor)
    if len(loud) == 0:
        return audio[:0]
    end = min(len(audio), int(loud[-1] + TRAILING_SILENCE_MAX * sr))
    return audio[:end]


def _gemini_paragraph(text: str, voice: str, note: str) -> Any:
    """One paragraph through Gemini TTS; returns float32 mono at 24 kHz."""
    import base64
    import os
    import time

    import httpx
    import numpy as np

    key = os.environ.get("GEMINI_API_KEY")
    if not key:
        raise RuntimeError("GEMINI_API_KEY is not set (see .env)")
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{config.GEMINI_TTS_MODEL}:generateContent"
    body = {
        "contents": [{"parts": [{"text": f"{note}\\n\\n{text}"}]}],
        "generationConfig": {
            "responseModalities": ["AUDIO"],
            "speechConfig": {"voiceConfig": {"prebuiltVoiceConfig": {"voiceName": voice}}},
        },
    }
    words = max(len(text.split()), 1)
    for attempt in range(4):
        r = httpx.post(url, params={"key": key}, json=body, timeout=180)
        if r.status_code == 429:
            time.sleep(15 * (attempt + 1))
            continue
        if r.status_code != 200:
            raise RuntimeError(f"Gemini TTS HTTP {r.status_code}: {r.text[:200]}")
        part = r.json()["candidates"][0]["content"]["parts"][0]["inlineData"]
        pcm = np.frombuffer(base64.b64decode(part["data"]), dtype=np.int16).astype(np.float32) / 32768.0
        audio = _trim_silence(pcm, config.TTS_SAMPLE_RATE)
        # A take that speaks too little for its word count was truncated.
        if len(audio) / config.TTS_SAMPLE_RATE >= MIN_SPOKEN_PER_WORD * words:
            return audio
        time.sleep(2)
    raise RuntimeError(f"Gemini TTS kept truncating: {text[:50]!r}")


def synthesize_gemini(
    paragraphs: list[str],
    voice: str = config.GEMINI_TTS_VOICE,
    note: str = config.GEMINI_TTS_NOTE,
    progress: bool = True,
    section_ends: set[int] | None = None,
) -> tuple[Any, list[dict[str, Any]]]:
    """Paragraph-by-paragraph Gemini narration with exact paragraph spans.

    Returns the waveform and a list of {paragraph, start, end} spans; word
    timings come later from the align stage's Whisper pass.
    """
    import time

    import numpy as np

    section_ends = section_ends or set()
    sr = config.TTS_SAMPLE_RATE
    chunks: list[Any] = []
    spans: list[dict[str, Any]] = []
    offset = 0.0
    for index, para in enumerate(paragraphs, start=1):
        audio = _gemini_paragraph(para, voice, note)
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
    return np.concatenate(chunks[:-1]), spans


''' + anchor, 1)

    # run(): engine parameter and branch.
    old_sig = "    voice: str = config.DEFAULT_VOICE,\n    speed: float = 1.0,\n) -> None:\n    \"\"\"Stage entry point. Writes narration.wav and phonemes.json.\"\"\""
    new_sig = "    voice: str = config.DEFAULT_VOICE,\n    speed: float = 1.0,\n    engine: str = \"kokoro\",\n) -> None:\n    \"\"\"Stage entry point. Writes narration.wav and phonemes.json.\"\"\""
    if old_sig in src:
        src = src.replace(old_sig, new_sig, 1)
    assert new_sig in src, "run signature"

    old_call = '''    audio, timings = synthesize(
        paragraphs, voice=voice, speed=speed, section_ends=section_ends
    )
'''
    new_call = '''    spans: list[dict[str, Any]] = []
    if engine == "gemini":
        if voice == config.DEFAULT_VOICE:
            voice = config.GEMINI_TTS_VOICE
        audio, spans = synthesize_gemini(paragraphs, voice=voice, section_ends=section_ends)
        timings = []
    elif engine == "kokoro":
        audio, timings = synthesize(
            paragraphs, voice=voice, speed=speed, section_ends=section_ends
        )
    else:
        raise ValueError(f"Unknown engine {engine!r}; choose kokoro or gemini")
'''
    if old_call in src:
        src = src.replace(old_call, new_call, 1)
    assert new_call in src, "run call"

    old_json = '''                "voice": voice,
                "speed": speed,
'''
    new_json = '''                "engine": engine,
                "voice": voice,
                "speed": speed,
                "paragraph_spans": spans,
'''
    if old_json in src:
        src = src.replace(old_json, new_json, 1)
    assert new_json in src, "phonemes json"

    old_rec = "        words=words,\n        voice=voice,\n    )"
    new_rec = "        words=words,\n        voice=voice,\n        engine=engine,\n    )"
    if old_rec in src:
        src = src.replace(old_rec, new_rec, 1)
    assert new_rec in src, "record"
    return src


# --- align -------------------------------------------------------------------
def align_patch(src):
    if "def align_whisper" not in src:
        anchor = "def run(slug: str, record: dict[str, Any]) -> None:\n"
        assert anchor in src, "align.run anchor"
        src = src.replace(anchor, '''def _norm_token(word: str) -> str:
    return "".join(ch for ch in word.lower() if ch.isalnum())


def _map_words(script_words: list[str], heard: list[dict[str, Any]], start: float, end: float):
    """Give each script word a span from the Whisper words it matches.

    A sequence match on normalised tokens pairs script words with heard
    words; unmatched runs are spread between their matched neighbours, so a
    number Whisper wrote as digits or a name it misheard still lands in
    the right place to within its neighbours.
    """
    import difflib

    a = [_norm_token(w) for w in script_words]
    b = [_norm_token(h["word"]) for h in heard]
    spans: list[tuple[float, float] | None] = [None] * len(a)
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, a, b, autojunk=False).get_opcodes():
        if tag == "equal":
            for k in range(i2 - i1):
                spans[i1 + k] = (heard[j1 + k]["start"], heard[j1 + k]["end"])
    matched = sum(s is not None for s in spans)
    # Fill gaps proportionally between anchors.
    i = 0
    while i < len(spans):
        if spans[i] is not None:
            i += 1
            continue
        j = i
        while j < len(spans) and spans[j] is None:
            j += 1
        lo = spans[i - 1][1] if i > 0 else start
        hi = spans[j][0] if j < len(spans) else end
        if hi <= lo:
            hi = lo + 0.05 * (j - i)
        for k, span in enumerate(_proportional(script_words[i:j], lo, hi)):
            spans[i + k] = span
        i = j
    return [s for s in spans], matched


def align_whisper(root, data: dict[str, Any]) -> tuple[list[dict[str, Any]], int, float]:
    """Word timings from faster-whisper, one paragraph slice at a time."""
    import numpy as np
    import soundfile as sf
    from faster_whisper import WhisperModel

    audio, sr = sf.read(str(root / "narration.wav"), dtype="float32")
    if audio.ndim > 1:
        audio = audio.mean(axis=1)
    if sr != 16000:
        # Whisper wants 16 kHz; linear resample is fine for speech timing.
        n = int(len(audio) * 16000 / sr)
        audio = np.interp(np.linspace(0, len(audio), n, endpoint=False), np.arange(len(audio)), audio).astype(np.float32)
        sr = 16000
    model = WhisperModel(config.WHISPER_MODEL, device="cpu", compute_type="int8")

    words_out: list[dict[str, Any]] = []
    weak = 0
    matched_total = 0
    total_words = 0
    for span in data["paragraph_spans"]:
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
            words_out.append({"word": word, "start": round(s, 4), "end": round(e, 4), "paragraph": index})
    return words_out, weak, matched_total / max(total_words, 1)


''' + anchor, 1)

    old = '''    data = json.loads(src.read_text(encoding="utf-8"))
    if not data.get("timings"):
        raise RuntimeError(
            "phonemes.json has no timings - the audio came from a fallback engine. "
            "ASR alignment for that path is not implemented yet."
        )

    words, mismatches = align(data)
'''
    new = '''    data = json.loads(src.read_text(encoding="utf-8"))
    if data.get("timings"):
        words, mismatches = align(data)
    elif data.get("paragraph_spans"):
        words, mismatches, ratio = align_whisper(root, data)
        print(f"  whisper matched {ratio:.0%} of script words directly")
    else:
        raise RuntimeError("phonemes.json has neither timings nor paragraph_spans")
'''
    if old in src:
        src = src.replace(old, new, 1)
    assert new in src, "align run"
    return src


# --- cli ---------------------------------------------------------------------
def cli_patch(src):
    src2 = src.replace(
        'return _run_stage(args.slug, "voice", voice.run, voice=args.voice, speed=args.speed)',
        'return _run_stage(args.slug, "voice", voice.run, voice=args.voice, speed=args.speed, engine=args.engine)',
    )
    src2 = src2.replace(
        '("voice", lambda: _run_stage(slug, "voice", voice.run, voice=args.voice, speed=1.0)),',
        '("voice", lambda: _run_stage(slug, "voice", voice.run, voice=args.voice, speed=1.0, engine=args.engine)),',
    )
    # Add --engine next to every --voice argument (voice and run parsers).
    if "--engine" not in src2:
        src2 = re.sub(
            r'(\n(\s+)p\.add_argument\("--voice", default=config\.DEFAULT_VOICE\))',
            r'\1\n\2p.add_argument("--engine", default="kokoro", choices=["kokoro", "gemini"], help="narration engine")',
            src2,
        )
    assert src2.count("--engine") >= 2, src2.count("--engine")
    return src2


patch("fvs/config.py", config_patch)
patch("fvs/stages/voice.py", voice_patch)
patch("fvs/stages/align.py", align_patch)
patch("fvs/cli.py", cli_patch)
