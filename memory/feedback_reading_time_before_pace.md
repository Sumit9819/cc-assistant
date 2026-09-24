---
name: feedback_reading_time_before_pace
description: "HARD (2026-09-16): never optimise one production metric alone - measure what the viewer must READ before changing pace; the reading model lives in D:\\faceless-studio\\tools\\reading.py and the gate enforces it"
metadata:
  type: feedback
---

On 2026-09-15 I sped the fifty-year-car narration from 146 to 156 wpm because
the measured reference channels run 154-186. The owner's verdict on the result:
"the video is too fast... when article shows up, viewers barely get any time to
read the highlighted part. We are optimizing one part and missing others."

They were right, and the failure was method, not taste. wpm was treated as a
target in isolation. Faster narration shortens every beat; the first-minute
splits shortened them again; so the passages the viewer is asked to READ got
squeezed twice. Measured afterwards: **16 of 17 evidence beats ended before
their quote could be read, 58 s short in total**, worst 11.1 s - and the pipeline
reported success, because nothing in it modelled reading at all.

**Why:** a reference number describes a whole system, not one knob. Those
channels cut every 1-2 s because their on-screen text is a headline or a
number; ours is statute. Their pace and a 20-word quote cannot both be had.

**How to apply:**

- Before changing pace, voice speed, or beat lengths on any video, run
  `python tools/study_reading.py <slug>` and read the shortfall table. The
  model is `tools/reading.py`: find the line (0.8 s) + read at 180 wpm
  (Brysbaert 2019 ~238 wpm prose; BBC/EBU subtitles 160-180), discounted to
  45% when the narration is speaking those same words while they are up.
- `retime.py` allots that time (`wants`, and the phrase-boundary snap guard)
  and `check_video.py --pre` FAILS a beat that does not get it. The first-minute
  pacing warning ignores a long hold that reading justifies.
- Measure the discount over the beat's OWN window. The first version asked over
  the whole free span in the layout and over the final beat in the gate, so the
  layout granted 8.3 s for a beat the gate said needed 9.4 s.
- When a beat cannot be given its reading time, the fix is editorial, in this
  order: trim the highlight to its operative phrase (a 21-word subsection
  becomes "examined and found to be safe"); let the evidence hold by dropping
  the unanchored kinetic/footage beat beside it; drop the weakest evidence beat
  entirely. Never shorten the dwell.
- Trimming quotes cost nothing: the whole page is still on screen, only the gold
  band is shorter, and the shortfall went 58 s -> 0 s while picture changes only
  fell 24.4 -> 23.3 a minute.

Related: [[project_remotion_video_pipeline]],
[[feedback_video_text_zone_and_qa_before_delivery]],
[[feedback_look_at_every_shot_before_delivering]].
