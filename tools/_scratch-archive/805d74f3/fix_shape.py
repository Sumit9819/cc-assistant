"""Four fixes to the shaped planner, each traced to a measured failure.

1. Hold beats punch on a section-start paragraph (p29 was both, punch won).
2. Holds are exempt from the tight-open position factor, and the hold floor
   is raised so the shot cannot settle on the nearest 4-5s full stop.
3. A punch paragraph renders as ONE shot when it fits, never splintered.
4. The candidate search always reaches the next sentence end when it is
   within 2x the window, so a long opening sentence is not cut mid-phrase.
"""

import ast
from pathlib import Path

p = Path("D:/faceless-studio/fvs/stages/plan.py")
src = p.read_text(encoding="utf-8")


def swap(old: str, new: str, label: str) -> None:
    global src
    assert old in src, f"anchor missing: {label}"
    src = src.replace(old, new)
    print(f"  patched  {label}")


# --- constants -------------------------------------------------------------
swap(
    "HOLD_MIN_FACTOR = 2.1        # first shot of a new section: floor x2.1\n"
    "HOLD_MAX_FACTOR = 1.7        # ...ceiling x1.7",
    "HOLD_MIN_FACTOR = 2.4        # first shot of a new section: floor x2.4\n"
    "HOLD_MAX_FACTOR = 1.8        # ...ceiling x1.8\n"
    "# A punch line is delivered as ONE shot. Cutting it faster splinters it.\n"
    "PUNCH_MAX_SECONDS = 4.5",
    "hold factors + punch ceiling",
)

# --- shot-start logic ------------------------------------------------------
swap(
    "        shot_start = words[i][\"start\"]\n"
    "        para = words[i][\"paragraph\"]\n"
    "        pace = local_pace(words, i) * position_pace(shot_start, total)\n"
    "\n"
    "        min_factor = max_factor = 1.0\n"
    "        if para in punch_paragraphs:\n"
    "            min_factor = max_factor = PUNCH_FACTOR\n"
    "        elif para in hold_paragraphs and para not in held:\n"
    "            # Only the FIRST shot of the section is held; the rest of the\n"
    "            # paragraph cuts normally.\n"
    "            min_factor, max_factor = HOLD_MIN_FACTOR, HOLD_MAX_FACTOR\n"
    "            held.add(para)\n"
    "\n"
    "        min_s = base_min * pace * min_factor\n"
    "        max_s = base_max * pace * max_factor\n"
    "        ideal = (min_s + max_s) / 2",
    "        shot_start = words[i][\"start\"]\n"
    "        para = words[i][\"paragraph\"]\n"
    "\n"
    "        # A punch line is one shot. Take the whole paragraph if it fits.\n"
    "        if para in punch_paragraphs and para not in hold_paragraphs:\n"
    "            last = i\n"
    "            while last + 1 < len(words) and words[last + 1][\"paragraph\"] == para:\n"
    "                last += 1\n"
    "            if words[last][\"end\"] - shot_start <= PUNCH_MAX_SECONDS:\n"
    "                _emit(shots, words, i, last)\n"
    "                i = last + 1\n"
    "                continue\n"
    "\n"
    "        is_hold = para in hold_paragraphs and para not in held\n"
    "        # A hold is a deliberate exception to the tight open, so it does\n"
    "        # not take the position factor.\n"
    "        pace = local_pace(words, i) * (1.0 if is_hold else position_pace(shot_start, total))\n"
    "\n"
    "        min_factor = max_factor = 1.0\n"
    "        if is_hold:\n"
    "            min_factor, max_factor = HOLD_MIN_FACTOR, HOLD_MAX_FACTOR\n"
    "            held.add(para)\n"
    "        elif para in punch_paragraphs:\n"
    "            min_factor = max_factor = PUNCH_FACTOR\n"
    "\n"
    "        min_s = base_min * pace * min_factor\n"
    "        max_s = base_max * pace * max_factor\n"
    "        ideal = (min_s + max_s) / 2",
    "punch-as-one-shot, hold-beats-punch, hold exempt from tight open",
)

# --- candidate search reaches the next sentence end -----------------------
swap(
    "        candidates = [\n"
    "            j\n"
    "            for j in range(i, len(words))\n"
    "            if min_s * SEARCH_LOW <= words[j][\"end\"] - shot_start <= max_s * SEARCH_HIGH\n"
    "        ]\n",
    "        candidates = [\n"
    "            j\n"
    "            for j in range(i, len(words))\n"
    "            if min_s * SEARCH_LOW <= words[j][\"end\"] - shot_start <= max_s * SEARCH_HIGH\n"
    "        ]\n"
    "        # Always let the next sentence end compete, even past the window.\n"
    "        # A tight opening window could not reach a 5.6s sentence end and\n"
    "        # cut the first line of the video into three fragments.\n"
    "        for j in range(i, len(words)):\n"
    "            if words[j][\"end\"] - shot_start > max_s * 2.0:\n"
    "                break\n"
    "            if _ends_with(words[j][\"word\"], SENTENCE_END):\n"
    "                if j not in candidates:\n"
    "                    candidates.append(j)\n"
    "                break\n",
    "search reaches next sentence end",
)

# --- factor the shot emitter so the punch path can reuse it ---------------
swap(
    "        span = words[i : end_index + 1]\n"
    "        shots.append(\n"
    "            {\n"
    "                \"id\": len(shots) + 1,\n"
    "                \"start\": round(shot_start, 3),\n"
    "                \"end\": round(span[-1][\"end\"], 3),\n"
    "                \"duration\": round(span[-1][\"end\"] - shot_start, 3),\n"
    "                \"text\": \" \".join(w[\"word\"] for w in span),\n"
    "                \"paragraph\": span[0][\"paragraph\"],\n"
    "                \"query\": \"\",\n"
    "                \"kind\": \"video\",\n"
    "            }\n"
    "        )\n"
    "        i = end_index + 1\n",
    "        _emit(shots, words, i, end_index)\n"
    "        i = end_index + 1\n",
    "use _emit in main path",
)

swap(
    "def plan_cuts(\n",
    "def _emit(shots: list[dict[str, Any]], words: list[dict[str, Any]], i: int, end_index: int) -> None:\n"
    "    span = words[i : end_index + 1]\n"
    "    shots.append(\n"
    "        {\n"
    "            \"id\": len(shots) + 1,\n"
    "            \"start\": round(span[0][\"start\"], 3),\n"
    "            \"end\": round(span[-1][\"end\"], 3),\n"
    "            \"duration\": round(span[-1][\"end\"] - span[0][\"start\"], 3),\n"
    "            \"text\": \" \".join(w[\"word\"] for w in span),\n"
    "            \"paragraph\": span[0][\"paragraph\"],\n"
    "            \"query\": \"\",\n"
    "            \"kind\": \"video\",\n"
    "        }\n"
    "    )\n"
    "\n"
    "\n"
    "def plan_cuts(\n",
    "define _emit",
)

p.write_text(src, encoding="utf-8")
ast.parse(src)
print("  syntax OK")
