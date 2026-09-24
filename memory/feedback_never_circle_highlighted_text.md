---
name: feedback-never-circle-highlighted-text
description: HARD 2026-09-23 - never draw a circle/ring on highlighted text in a video; the gold band already marks the line. Enforced in check_video.py and diagrams.jsx
metadata:
  type: feedback
---

Owner, 2026-09-23, after seeing the ring on "a selection of pixels", "approximately
$2.3 billion" and the Roku "may still be shared with third parties" line:
"never circle again on the highlighted part."

**Why:** the band already says "this line"; a ring drawn around the same words
says it twice and reads as clutter. Every annotation is computed from the located
band, so a ring could ONLY ever land on highlighted text - there is no valid use.

**How to apply:** band alone, or an arrow / margin note (those point at the line,
they do not sit on it). `Annotation kind="circle"` now throws, and
`check_video.py --pre` FAILS any beat with `mark: "circle"`. Do not reintroduce a
ring under another name (ellipse, loop, scribble-around). Related:
[[project-video-training-phase]].
