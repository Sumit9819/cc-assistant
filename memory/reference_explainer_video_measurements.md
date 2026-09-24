---
name: reference_explainer_video_measurements
description: "Measured numbers from seven top money/policy explainers (WSJ, Vox, PolyMatter, Search Party, Johnny Harris; Wendover and HAI frames-only) on 2026-09-04 - median shot 1.5-3.1s, first minute fastest, 83-95% of cuts mid-phrase, 154-186 wpm, -16 to -21 LUFS, footage is documents/archive not stock."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-04T08:13:28.644Z
---

Measured 2026-09-04 with `D:\faceless-studio\tools\study_video.py` and
`study_align.py` (ffmpeg scdet threshold 6, auto-caption timing, audio
pauses). Data in `D:\faceless-studio\study\out\`; report page built by
`tools/build_study_page.py`; full notes in DECISIONS.md "Seven videos,
measured".

| channel | cuts/min | median shot | first-min median | wpm | LUFS | cuts mid-phrase |
|---|---|---|---|---|---|---|
| WSJ (penny) | 16.7 | 2.57s | 1.71s | 186 | -20.7 | 83.5% |
| Vox (prices) | 10.9 | 2.17s | 1.04s | 177 | -20.5 | 87.8% |
| PolyMatter (India notes) | 7.8 | 3.07s | 1.93s | 154 | -16.1 | 95.2% |
| Search Party (Putin) | 13.6 | 1.92s | 3.65s | 164 | -18.6 | 93.0% |
| Johnny Harris (war) | 24.9 | 1.46s | 1.38s | 155 | -20.5 | 87.3% |

What the frames show: footage is EVIDENCE (news footage of the actual
event, documents and headlines with a highlighter, a stat card with its
source), stock B-roll is a minority; every channel keeps a persistent frame
(corner mark, colour system, dated lower-thirds); five of seven use faces.

Use: pace target for faceless-studio is PolyMatter's (2.5-3s median,
~2s first minute) reached only through phrase anchors; narration 150+ wpm;
documents as a shot kind; do not chase -14 LUFS. The detector cannot
measure animated-map or kinetic-type channels (Wendover, HAI): frames only.
Related: [[project_faceless_video_studio]],
[[feedback_look_at_every_shot_before_delivering]].
