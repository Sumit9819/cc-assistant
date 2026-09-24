"""Wire archival stills through plan -> assets -> compose -> package.

A still replaces the video clip on the first shot of its paragraph and is
rendered with a slow push. Still paragraphs are also holds: a push needs
5-8 seconds to be perceptible, and the establishing shot of a section is
where a held archival image belongs anyway.

DO NOT run while a compose is in flight - compose.py is imported fresh by
each stage process, and a half-applied patch would break the render.
"""

import ast
from pathlib import Path

ROOT = Path("D:/faceless-studio/fvs")


def patch(path: Path, pairs: list[tuple[str, str, str]]) -> None:
    src = path.read_text(encoding="utf-8")
    for old, new, label in pairs:
        if new.strip() and new in src:
            print(f"  {path.name:<14} already: {label}")
            continue
        assert old in src, f"{path.name}: anchor missing for {label}"
        src = src.replace(old, new)
        print(f"  {path.name:<14} patched: {label}")
    ast.parse(src)
    path.write_text(src, encoding="utf-8")


# --- plan.py: still paragraphs are holds, and their first shot is marked --
patch(ROOT / "stages/plan.py", [
    (
        "    counts: dict[int, int] = {}\n"
        "    for w in data[\"words\"]:",
        "    # Still paragraphs are holds: a push needs 5-8s to be perceptible.\n"
        "    still_plan: dict[int, dict] = {}\n"
        "    still_path = root / \"stillplan.json\"\n"
        "    if still_path.is_file():\n"
        "        raw = json.loads(still_path.read_text(encoding=\"utf-8\"))\n"
        "        still_plan = {int(k): v for k, v in raw.get(\"paragraphs\", {}).items()}\n"
        "        hold_paragraphs |= set(still_plan)\n"
        "\n"
        "    counts: dict[int, int] = {}\n"
        "    for w in data[\"words\"]:",
        "load stillplan, add to holds",
    ),
    (
        "    # Literal-subject paragraphs (a named landmark) trust search rank",
        "    # Mark the first shot of each still paragraph. It takes the still\n"
        "    # instead of a video clip, so it needs no query and no asset.\n"
        "    marked: set[int] = set()\n"
        "    for shot in shots:\n"
        "        para = shot.get(\"paragraph\")\n"
        "        if para in still_plan and para not in marked:\n"
        "            spec = still_plan[para]\n"
        "            shot[\"kind\"] = \"still\"\n"
        "            shot[\"still\"] = {\n"
        "                \"path\": spec[\"path\"],\n"
        "                \"direction\": spec.get(\"direction\", \"in\"),\n"
        "                \"licence\": spec.get(\"licence\", \"\"),\n"
        "                \"attribution\": spec.get(\"attribution\", \"\"),\n"
        "            }\n"
        "            shot[\"query\"] = \"\"\n"
        "            marked.add(para)\n"
        "    if marked:\n"
        "        print(f\"  {len(marked)} archival still(s) placed on section holds\")\n"
        "\n"
        "    # Literal-subject paragraphs (a named landmark) trust search rank",
        "mark still shots",
    ),
])

# --- assets.py: skip still shots entirely ----------------------------------
patch(ROOT / "stages/assets.py", [
    (
        "    missing = [s[\"id\"] for s in shots if not s.get(\"query\", \"\").strip()]",
        "    shots = [s for s in shots if s.get(\"kind\") != \"still\"]\n"
        "    missing = [s[\"id\"] for s in shots if not s.get(\"query\", \"\").strip()]",
        "exclude still shots from fetch",
    ),
])

# --- compose.py: render a still with a push instead of a clip --------------
patch(ROOT / "stages/compose.py", [
    (
        "    asset = shot.get(\"asset\")\n"
        "    if not asset:\n"
        "        raise ValueError(f\"Shot {shot['id']} has no asset - run the assets stage\")",
        "    still = shot.get(\"still\") if shot.get(\"kind\") == \"still\" else None\n"
        "    asset = shot.get(\"asset\")\n"
        "    if still is None and not asset:\n"
        "        raise ValueError(f\"Shot {shot['id']} has no asset - run the assets stage\")",
        "accept still shots",
    ),
    (
        "    args = [\n"
        "        # -ss before -i seeks fast; the clip is re-encoded anyway.\n"
        "        \"-ss\", f\"{lead_in:.3f}\",\n"
        "        \"-t\", f\"{duration:.3f}\",\n"
        "        \"-i\", Path(asset[\"path\"]).as_posix(),\n"
        "    ]",
        "    if still is not None:\n"
        "        from .. import stills as stills_lib\n"
        "\n"
        "        # A looped still, pushed by zoompan. The filter carries its own\n"
        "        # fps resample and format, so it replaces video_filter entirely.\n"
        "        video_filter = stills_lib.ken_burns_filter(\n"
        "            duration, config.VIDEO_WIDTH, config.VIDEO_HEIGHT,\n"
        "            still.get(\"direction\", \"in\"),\n"
        "        )\n"
        "        args = [\n"
        "            \"-loop\", \"1\",\n"
        "            \"-t\", f\"{duration:.3f}\",\n"
        "            \"-i\", Path(still[\"path\"]).as_posix(),\n"
        "        ]\n"
        "    else:\n"
        "        args = [\n"
        "            # -ss before -i seeks fast; the clip is re-encoded anyway.\n"
        "            \"-ss\", f\"{lead_in:.3f}\",\n"
        "            \"-t\", f\"{duration:.3f}\",\n"
        "            \"-i\", Path(asset[\"path\"]).as_posix(),\n"
        "        ]",
        "still input + ken burns",
    ),
    (
        "        graph = (\n"
        "            f\"[0:v]{video_filter},format=yuv420p[base];\"",
        "        # A still's filter already ends in format=yuv420p; a clip's does not.\n"
        "        base_chain = video_filter if still is not None else f\"{video_filter},format=yuv420p\"\n"
        "        graph = (\n"
        "            f\"[0:v]{base_chain}[base];\"",
        "overlay graph handles both",
    ),
    (
        "    else:\n"
        "        args += [\"-vf\", f\"{video_filter},format=yuv420p\"]",
        "    else:\n"
        "        args += [\"-vf\", video_filter if still is not None else f\"{video_filter},format=yuv420p\"]",
        "plain path handles both",
    ),
    (
        "        overlay = shot.get(\"overlay\") or {}\n"
        "        overlay_key = overlay.get(\"card\", \"\")",
        "        overlay = shot.get(\"overlay\") or {}\n"
        "        overlay_key = overlay.get(\"card\", \"\")\n"
        "        # A still replaces the asset, so it must be part of the stamp.\n"
        "        if shot.get(\"kind\") == \"still\":\n"
        "            overlay_key = \"still:\" + (shot.get(\"still\") or {}).get(\"path\", \"\") + \"|\" + overlay_key",
        "cache stamp includes still",
    ),
])

# --- package.py: carry still attributions --------------------------------
patch(ROOT / "stages/package.py", [
    (
        "    music = root / \"music.json\"",
        "    still_plan = root / \"stillplan.json\"\n"
        "    if still_plan.is_file():\n"
        "        raw = json.loads(still_plan.read_text(encoding=\"utf-8\"))\n"
        "        credits[\"stills\"] = sorted({\n"
        "            v[\"attribution\"] for v in raw.get(\"paragraphs\", {}).values()\n"
        "            if v.get(\"attribution\")\n"
        "        })\n"
        "\n"
        "    music = root / \"music.json\"",
        "collect still credits",
    ),
    (
        "    if credits[\"footage\"]:\n"
        "        parts.append(f\"Footage: {', '.join(credits['footage'])}\")",
        "    if credits[\"footage\"]:\n"
        "        parts.append(f\"Footage: {', '.join(credits['footage'])}\")\n"
        "    for line in credits.get(\"stills\", []):\n"
        "        parts.append(f\"Image: {line}\")",
        "write still credits",
    ),
    (
        "    credits: dict[str, Any] = {\"footage\": [], \"music\": \"\", \"sources\": []}",
        "    credits: dict[str, Any] = {\"footage\": [], \"music\": \"\", \"sources\": [], \"stills\": []}",
        "credits has stills key",
    ),
])

print("\n  all patches applied; syntax verified on every file")
