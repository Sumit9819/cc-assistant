"""Gemini TTS trial: the polish-test narration in three voices, next to Kokoro.

Same text, same director's note, three prebuilt voices with documentary
descriptions. Output is 24 kHz 16-bit mono PCM, written as WAV into the
project so the user can A/B it against narration.wav by ear."""

import base64
import json
import os
import re
import sys
import time
import wave
from pathlib import Path

import httpx

sys.path.insert(0, "D:/faceless-studio")
from fvs import config  # noqa: E402

config.load_env()
KEY = os.environ["GEMINI_API_KEY"]
ROOT = Path("D:/faceless-studio/projects/polish-test")
OUT = ROOT / "tts_trial"
OUT.mkdir(exist_ok=True)

MODELS = ["gemini-2.5-flash-preview-tts", "gemini-3.1-flash-tts-preview"]
VOICES = ["Charon", "Sadaltager", "Algenib"]
NOTE = (
    "Read this as the narrator of a calm, serious documentary about materials science. "
    "Measured pace, low and even, no salesmanship. Pause briefly at paragraph breaks. "
    "Let short sentences land.\n\n"
)

text = ROOT.joinpath("script.md").read_text(encoding="utf-8")
paras = [p.strip() for p in re.split(r"\n\s*\n", text) if p.strip() and not p.strip().startswith("#")]
script = "\n\n".join(paras)
print(f"  script: {len(script.split())} words, {len(paras)} paragraphs")

with wave.open(str(ROOT / "narration.wav"), "rb") as w:
    print(f"  kokoro narration.wav: {w.getnframes() / w.getframerate():.1f}s")


def synth(model: str, voice: str) -> bytes:
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"
    body = {
        "contents": [{"parts": [{"text": NOTE + script}]}],
        "generationConfig": {
            "responseModalities": ["AUDIO"],
            "speechConfig": {"voiceConfig": {"prebuiltVoiceConfig": {"voiceName": voice}}},
        },
    }
    r = httpx.post(url, params={"key": KEY}, json=body, timeout=180)
    if r.status_code != 200:
        raise RuntimeError(f"HTTP {r.status_code}: {r.text[:300]}")
    data = r.json()
    part = data["candidates"][0]["content"]["parts"][0]["inlineData"]
    return base64.b64decode(part["data"]), part.get("mimeType", "")


model_used = None
for voice in VOICES:
    for model in ([model_used] if model_used else MODELS):
        try:
            t0 = time.time()
            pcm, mime = synth(model, voice)
            model_used = model
            break
        except Exception as exc:
            print(f"  {voice:<11} {model}: {exc}")
            pcm = None
    if not pcm:
        continue
    target = OUT / f"{voice.lower()}.wav"
    with wave.open(str(target), "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(24000)
        w.writeframes(pcm)
    secs = len(pcm) / 2 / 24000
    print(f"  {voice:<11} {secs:5.1f}s  {time.time() - t0:4.1f}s wall  {mime}  -> {target.name}")
    time.sleep(2)

print(f"  model: {model_used}")
print(f"  listen: {OUT}")
