---
name: project-video-training-phase
description: Faceless studio is in a TRAINING phase (owner, 2026-09-23) - nothing is being uploaded yet; build the element vocabulary in the Element Lab, do not push to publish
metadata:
  type: project
---

Owner, 2026-09-23: "I am not uploading at the moment because I am still training
it for proper video. So everything is in training phase." 11 masters exist and
none is published; that is deliberate, not an oversight. Do not re-raise
publishing as the fix.

**Why:** the owner wants the pipeline able to make a genuinely production-level
video before anything reaches the channel. They agreed the cuts were missing
whole categories of element ("B-roll, kinetic text and motion graphic, that's
it and nothing else").

**How to apply:** grow the vocabulary in the Element Lab
(`remotion/src/ElementLab.jsx`, `npx remotion studio src/lab.jsx`), one element
per standalone scene on real project material, reviewed there before any of it
reaches a production beat. Batch 1 (built 2026-09-23): TV's-eye viewfinder POV,
running screenshot counter, 10 ms waveform sampling, live browser capture
(`tools/capture_page.mjs`), graded B-roll, and `@remotion/transitions`
(push-cut / wipe / iris / clock-wipe) joining them in `Lab-Reel`. Batch 2
(approved and built 2026-09-23): Texas and study maps, a 3D TV with footage on
its screen, a Roku line chart from the 10-K, a headline stack, source icons, an
animated emoji line, a light-leak chapter card, and sound tied to each element
(CC0, provenance in library/sfx/lab). Catalogue with every licence and required
credit: production/element_lab.md. Lottie was not used (no licence-verified
source yet). Related:
[[feedback_propose_before_implementing_video_changes]],
[[feedback_look_at_every_shot_before_delivering]].
