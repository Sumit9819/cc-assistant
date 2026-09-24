"""The cheap three: colour grade, motion on every clip, an ambience floor.

Measured motivation (2026-08-25, roman-concrete): brightness across 24 sampled
shots ran 48-184 (sd 31); colour cast sd 5.3. Ninety-one clips from ninety-one
shooters, played as-is, is what "assembled" looks like. Between the sixteen
sound cues the audio floor was silence.

1. GRADE - two parts, both in render_segment:
   a. per-clip exposure pull: probe the source clip's mean luma once, then
      nudge it 60% of the way toward a target. Partial on purpose: full
      normalisation flattens every clip to the same grey.
   b. a global look: slightly desaturated, mild contrast, warm shadows /
      cool highlights, a soft vignette. One palette, never changes mid-video.
2. DRIFT - a 5% push on every video clip via zoompan d=1, alternating in/out
   by shot id so a run of shots does not all breathe the same way. Stills
   keep their own 12% Ken Burns.
3. AMBIENCE - pink noise lowpassed to a low rumble at -40 dB, full duration,
   mixed under everything and NOT ducked. Felt, not heard.

A GRADE_VERSION token goes into the cache stamp so every segment re-renders
once, and never again until the look changes.

Applies to compose.py and sfx.py. Do not run while a compose is in flight.
"""

import ast
from pathlib import Path

ROOT = Path("D:/faceless-studio/fvs")


def patch(path: Path, pairs: list[tuple[str, str, str]]) -> None:
    src = path.read_text(encoding="utf-8")
    for old, new, label in pairs:
        if new.strip() and new in src:
            print(f"  {path.name:<12} already: {label}")
            continue
        assert old in src, f"{path.name}: anchor missing for {label}"
        src = src.replace(old, new, 1)
        print(f"  {path.name:<12} patched: {label}")
    ast.parse(src)
    path.write_text(src, encoding="utf-8")


# --- compose.py -------------------------------------------------------------
patch(ROOT / "stages/compose.py", [
    # constants
    (
        "CAPTION_MAX_WORDS = 8\n",
        "# --- Look ------------------------------------------------------------------\n"
        "# Bump GRADE_VERSION whenever the grade or drift changes: it is part of the\n"
        "# segment cache stamp, so a change re-renders everything exactly once.\n"
        "GRADE_VERSION = \"g1\"\n"
        "# Exposure pull: nudge each clip's mean luma this fraction of the way toward\n"
        "# LUMA_TARGET. Partial on purpose - full normalisation flattens everything.\n"
        "LUMA_TARGET = 108.0\n"
        "LUMA_PULL = 0.6\n"
        "LUMA_MAX_SHIFT = 0.18   # cap on eq=brightness, +-\n"
        "# Global look, one palette for the whole video.\n"
        "LOOK = (\n"
        "    \"eq=saturation=0.86:contrast=1.07,\"\n"
        "    \"colorbalance=rs=0.04:gs=0.01:bs=-0.05:rh=-0.03:bh=0.04,\"\n"
        "    \"vignette=angle=PI/4.6:mode=forward\"\n"
        ")\n"
        "# Drift on video clips: a slow push so no frame is dead. Stills keep their\n"
        "# own larger Ken Burns move.\n"
        "CLIP_DRIFT = 0.05\n"
        "\n"
        "CAPTION_MAX_WORDS = 8\n",
        "look constants",
    ),
    # luma probe helper, placed before render_segment
    (
        "def render_segment(shot: dict[str, Any], duration: float, target: Path) -> None:",
        "def _mean_luma(path: Path, seek: float, span: float) -> float | None:\n"
        "    \"\"\"Mean Y of a few frames from the clip, for the exposure pull.\"\"\"\n"
        "    exe = shutil.which(\"ffmpeg\")\n"
        "    if not exe:\n"
        "        return None\n"
        "    proc = subprocess.run(\n"
        "        [exe, \"-hide_banner\", \"-loglevel\", \"info\", \"-ss\", f\"{seek:.2f}\", \"-t\",\n"
        "         f\"{max(min(span, 2.0), 0.2):.2f}\", \"-i\", path.as_posix(),\n"
        "         \"-vf\", \"fps=4,signalstats,metadata=print:key=lavfi.signalstats.YAVG\",\n"
        "         \"-f\", \"null\", \"-\"],\n"
        "        capture_output=True, text=True, encoding=\"utf-8\", errors=\"replace\",\n"
        "    )\n"
        "    vals = []\n"
        "    for line in (proc.stderr or \"\").splitlines():\n"
        "        if \"YAVG=\" in line:\n"
        "            try:\n"
        "                vals.append(float(line.split(\"YAVG=\")[1]))\n"
        "            except ValueError:\n"
        "                pass\n"
        "    return sum(vals) / len(vals) if vals else None\n"
        "\n"
        "\n"
        "def exposure_filter(luma: float | None) -> str:\n"
        "    \"\"\"An eq=brightness that pulls this clip partway toward the target.\"\"\"\n"
        "    if luma is None:\n"
        "        return \"\"\n"
        "    shift = (LUMA_TARGET - luma) / 255.0 * LUMA_PULL\n"
        "    shift = max(-LUMA_MAX_SHIFT, min(LUMA_MAX_SHIFT, shift))\n"
        "    return f\"eq=brightness={shift:.4f},\" if abs(shift) > 0.005 else \"\"\n"
        "\n"
        "\n"
        "def drift_filter(duration: float, shot_id: int) -> str:\n"
        "    \"\"\"A 5% push on a video clip, in or out by shot parity.\"\"\"\n"
        "    frames = max(int(round(duration * config.VIDEO_FPS)), 1)\n"
        "    if shot_id % 2 == 0:\n"
        "        zoom = f\"1+{CLIP_DRIFT}*on/{frames}\"\n"
        "    else:\n"
        "        zoom = f\"{1 + CLIP_DRIFT}-{CLIP_DRIFT}*on/{frames}\"\n"
        "    return (\n"
        "        f\"zoompan=z='{zoom}':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'\"\n"
        "        f\":s={config.VIDEO_WIDTH}x{config.VIDEO_HEIGHT}:fps={config.VIDEO_FPS},\"\n"
        "    )\n"
        "\n"
        "\n"
        "def render_segment(shot: dict[str, Any], duration: float, target: Path) -> None:",
        "luma probe + exposure + drift helpers",
    ),
    # video clip filter: scale/crop -> exposure -> drift -> look
    (
        "    video_filter = (\n"
        "        f\"scale={config.VIDEO_WIDTH}:{config.VIDEO_HEIGHT}\"\n"
        "        \":force_original_aspect_ratio=increase,\"\n"
        "        f\"crop={config.VIDEO_WIDTH}:{config.VIDEO_HEIGHT},\"\n"
        "        f\"fps={config.VIDEO_FPS},setsar=1\"\n"
        "    )\n",
        "    luma = None\n"
        "    if still is None:\n"
        "        luma = _mean_luma(Path(asset[\"path\"]), lead_in, duration)\n"
        "    # Order matters: exposure before the look, so the pull is measured on\n"
        "    # the source; drift before the look, so the vignette stays fixed to\n"
        "    # the frame rather than sliding with the push.\n"
        "    video_filter = (\n"
        "        f\"scale={config.VIDEO_WIDTH * 2}:-2\"\n"
        "        \":force_original_aspect_ratio=increase,\"\n"
        "        f\"crop={config.VIDEO_WIDTH * 2}:{config.VIDEO_HEIGHT * 2},\"\n"
        "        f\"fps={config.VIDEO_FPS},setsar=1,\"\n"
        "        + exposure_filter(luma)\n"
        "        + drift_filter(duration, int(shot.get(\"id\", 0)))\n"
        "        + LOOK\n"
        "    )\n",
        "graded, drifting clip filter",
    ),
    # stills get the look too, after their own Ken Burns
    (
        "        video_filter = stills_lib.ken_burns_filter(\n"
        "            duration, config.VIDEO_WIDTH, config.VIDEO_HEIGHT,\n"
        "            still.get(\"direction\", \"in\"),\n"
        "        )\n",
        "        video_filter = stills_lib.ken_burns_filter(\n"
        "            duration, config.VIDEO_WIDTH, config.VIDEO_HEIGHT,\n"
        "            still.get(\"direction\", \"in\"),\n"
        "        )\n"
        "        # Same palette as the clips, so a still never reads as a cutaway\n"
        "        # to a different film.\n"
        "        video_filter = video_filter.replace(\",format=yuv420p\", \"\") + \",\" + LOOK + \",format=yuv420p\"\n",
        "stills take the look",
    ),
    # cache stamp carries the grade version
    (
        "        asset_key = ((shot.get(\"asset\") or {}).get(\"key\", \"\") + \"|\" + overlay_key)",
        "        asset_key = ((shot.get(\"asset\") or {}).get(\"key\", \"\") + \"|\" + overlay_key\n"
        "                     + \"|\" + GRADE_VERSION)",
        "stamp includes grade version",
    ),
    # ambience layer in the mux
    (
        "    if has_sfx:\n"
        "        # Effects are NOT ducked: they are placed in the pauses, and ducking\n",
        "    # Ambience: a constant low floor so the audio never drops to nothing\n"
        "    # between cues. Not ducked - it sits far below the voice already.\n"
        "    ambience = sfx_lib.ensure_ambience(audio_duration, root / \"ambience.wav\")\n"
        "    if ambience is not None:\n"
        "        inputs += [\"-i\", ambience.as_posix()]\n"
        "        parts.append(f\"[{next_index}:a]atrim=0:{audio_duration:.3f},asetpts=N/SR/TB[amb]\")\n"
        "        mix_labels.append(\"[amb]\")\n"
        "        next_index += 1\n"
        "\n"
        "    if has_sfx:\n"
        "        # Effects are NOT ducked: they are placed in the pauses, and ducking\n",
        "ambience layer",
    ),
    (
        "    layers = [\"narration\"] + ([\"music\"] if bed else []) + ([\"sfx\"] if has_sfx else [])",
        "    layers = ([\"narration\"] + ([\"music\"] if bed else []) + ([\"ambience\"] if ambience is not None else [])\n"
        "              + ([\"sfx\"] if has_sfx else []))",
        "layer report",
    ),
])

# --- sfx.py: ambience synthesis ---------------------------------------------
patch(ROOT / "sfx.py", [
    (
        "def build_track(cues: list[dict[str, Any]], duration: float, target: Path) -> Path | None:",
        "# Ambience floor level. Below the voice by a wide margin; its job is to be\n"
        "# present, not noticed. -40 dB against narration around -20.\n"
        "AMBIENCE_DB = -40.0\n"
        "\n"
        "\n"
        "def ensure_ambience(duration: float, target: Path) -> Path | None:\n"
        "    \"\"\"A low, slowly-moving noise floor for the whole runtime.\n"
        "\n"
        "    Pink noise, lowpassed hard so it reads as room tone or distant wind\n"
        "    rather than hiss, with a very slow tremolo so it is not a flat drone.\n"
        "    Cached per duration; rebuilt only if the runtime changes.\n"
        "    \"\"\"\n"
        "    if target.is_file():\n"
        "        try:\n"
        "            exe = shutil.which(\"ffprobe\")\n"
        "            out = subprocess.run(\n"
        "                [exe, \"-v\", \"error\", \"-show_entries\", \"format=duration\",\n"
        "                 \"-of\", \"default=nk=1:nw=1\", target.as_posix()],\n"
        "                capture_output=True, text=True,\n"
        "            )\n"
        "            if abs(float(out.stdout.strip()) - duration) < 0.5:\n"
        "                return target\n"
        "        except (ValueError, OSError):\n"
        "            pass\n"
        "    recipe = (\n"
        "        f\"anoisesrc=d={duration:.3f}:c=pink:r=48000:a=0.8,\"\n"
        "        \"lowpass=f=220,lowpass=f=220,highpass=f=40,\"\n"
        "        \"tremolo=f=0.07:d=0.35,\"\n"
        "        f\"volume={AMBIENCE_DB}dB\"\n"
        "    )\n"
        "    try:\n"
        "        _ffmpeg([\"-f\", \"lavfi\", \"-i\", recipe, \"-c:a\", \"pcm_s16le\", target.as_posix()], \"ambience\")\n"
        "    except RuntimeError as exc:\n"
        "        print(f\"  ambience skipped: {exc}\")\n"
        "        return None\n"
        "    return target\n"
        "\n"
        "\n"
        "def build_track(cues: list[dict[str, Any]], duration: float, target: Path) -> Path | None:",
        "ambience synthesis",
    ),
])

print("\n  patches applied; syntax verified")
