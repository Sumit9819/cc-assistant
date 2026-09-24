"""Instrument the failing paragraph instead of guessing a third time.

Sends the exact first paragraph of roman-concrete and a short positive
control through the same request the engine makes, and prints everything
about both responses: finishReason, parts and their mime types, byte
counts, decoded duration, peak amplitude, post-trim duration, and the
threshold each is judged against."""

import base64
import json
import os
import re
import sys

import httpx
import numpy as np

sys.path.insert(0, "D:/faceless-studio")
from fvs import config  # noqa: E402
from fvs.stages.voice import _trim_silence, parse_script_sections  # noqa: E402

config.load_env()
KEY = os.environ["GEMINI_API_KEY"]
URL = f"https://generativelanguage.googleapis.com/v1beta/models/{config.GEMINI_TTS_MODEL}:generateContent"

text = (config.project_dir("roman-concrete") / "script.md").read_text(encoding="utf-8")
paragraphs = [p for p, _ in parse_script_sections(text)]
failing = next(p for p in paragraphs if p.startswith("In 2023"))
control = "The Pantheon has stood for nineteen centuries. There is no steel inside it."

for name, para in (("control", control), ("failing", failing)):
    words = len(para.split())
    body = {
        "contents": [{"parts": [{"text": f"{config.GEMINI_TTS_NOTE}\n\n{para}"}]}],
        "generationConfig": {
            "responseModalities": ["AUDIO"],
            "speechConfig": {"voiceConfig": {"prebuiltVoiceConfig": {"voiceName": config.GEMINI_TTS_VOICE}}},
        },
    }
    r = httpx.post(URL, params={"key": KEY}, json=body, timeout=180)
    print(f"== {name}: {words} words, HTTP {r.status_code}")
    if r.status_code != 200:
        print(r.text[:400])
        continue
    d = r.json()
    cand = d["candidates"][0]
    print("  finishReason:", cand.get("finishReason"), "| promptFeedback:", d.get("promptFeedback"))
    parts = cand.get("content", {}).get("parts", [])
    print(f"  parts: {len(parts)}")
    pcm_all = b""
    for i, part in enumerate(parts):
        if "inlineData" in part:
            blob = base64.b64decode(part["inlineData"]["data"])
            pcm_all += blob
            print(f"    [{i}] inlineData {part['inlineData'].get('mimeType')} {len(blob)} bytes"
                  f" = {len(blob)/2/24000:.2f}s")
        else:
            print(f"    [{i}] {json.dumps(part)[:160]}")
    audio = np.frombuffer(pcm_all, dtype=np.int16).astype(np.float32) / 32768.0
    trimmed = _trim_silence(audio, config.TTS_SAMPLE_RATE)
    print(f"  raw {len(audio)/24000:.2f}s | peak {np.abs(audio).max() if len(audio) else 0:.4f}"
          f" | trimmed {len(trimmed)/24000:.2f}s | threshold {0.12*words:.2f}s")
