---
name: reference_kinetic_text_and_tension_measurements
description: "Measured 2026-09-05 on 2026 uploads (fern, HAI, neo, Harris, Jake Tran, Hormozi) - \"hyper animated\" type never pops with a scale overshoot; it types (30-60 ms/char), blurs in word by word on the voice, counts up (0.25-0.4s), swaps in place, flashes INTO a card, and holds 1.5-13s. Tension proxies per 100 words are in DECISIONS.md."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-05T06:12:50.493Z
---

Tooling: `D:\faceless-studio\tools\study_type.py` (scan 1 fps sheets, burst 20 fps
sheets, diff) - sheets in `study/hyper`; older 0.5s sheets in `study/kin*`;
transcript counts via `tools/study_script.py`. Notes: DECISIONS.md "Hyper
animated text, measured on 2026 uploads" and "Text that moves, scripts that pull".

**Type motion, measured (six 2026 uploads, 20 fps bursts):** no scale-overshoot
pop anywhere, Hormozi included. Typewriter 45-60 ms/char (fern, no cursor),
30-40 (Harris parchment card, lines on their beat), 36-40 (Tran, yellow caps,
block cursor, continues across a cut). Spoken sentence = word-by-word blur-in,
~0.2s each, 0.15-0.2s apart (fern). Numbers count up 0.25-0.35s with digits
resolving left to right and the footage BLURRED behind (HAI). Labels fade in
0.3s and swap by 0.2s cross-dissolve in place (neo) or one hard frame with a
colour change (HAI). Giant caps are present on the cut and drift with the
camera; a word can outlast the picture (Harris "POWER" over 5 map cuts in
0.8s). Light-leak flash INTO a type card, never out. Holds 1.5-13s, leave on
the cut. Mixed case is the default; caps only for giant words and labels.

**Where it lives now:** `motion/keyword_run.html` (kinds word/num/type/line/
big/swap/strike/slug/question; v1 pop kept as `keyword_run_v1_pop.html`);
marks in `fvs/keywords.py`: `*word*`, `*~old~ new*`, `*?*`, `*!BIG*`, `*@Slug*`,
`*=swap*`, `*>typed line*`, `*^spoken line*` (per-word aligner timings);
`shot["plate"]` blurs/darkens footage under big/typed type (compose.py).

**Tension (transcripts, per 100 words):** our turns were half theirs (0.34 vs
0.7-1.5), numbers 3-10x denser, first person zero vs 0.4-4.1; fixed by
`tools/check_script.py`. Related: [[reference_2026_hook_grammar]],
[[reference_explainer_video_measurements]], [[feedback_no_emphasis_cards_in_video]].
