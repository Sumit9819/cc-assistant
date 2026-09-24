---
name: feedback_video_text_zone_and_qa_before_delivery
description: "Owner rule (2026-09-05) - on-screen type lives in a dedicated 40%-wide zone (centre, or left/right), never edge to edge; no clip may flash for a fraction of a second before a card; run tools/qa_master.py on every master before delivering."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-05T10:22:33.542Z
---

Owner, after watching the three 2026-09-05 masters: "a kinetic text was touching
left to right of the screen ... in the middle it should cover 40% in the middle,
or left or right, so a person doesn't have to read from left to right"; and "a
video was there for just milliseconds ... then changed to motion graphic".

**Why:** measured against the 2026 references, their type spans 25-60% of the
frame (fern 58%, HAI 55%, Harris 40-42%, neo 25%); ours ran 88-94%. On a phone a
full-width line is tracked, not read. The flash was the section dip: black, then
0.6s of footage, then the chapter card's dim.

**How to apply:** `motion/keyword_run.html` zones every run into 768px (40%),
centred or seated opposite a card; giant and spoken lines wrap into a stacked
block; nothing enters the 5% edge margin. Compose starts chapter cards at 0.
Overlays last the SEGMENT (to the next shot's start), not the word span, so type
leaves on the cut. Before any master goes to the owner run
`python tools/qa_master.py <slug> --scdet` and clear every FLAG: runts and read
floors, chapter lead, text zone (measured from the overlay's own alpha), text
hold (0.8s after a line finishes), slug/mark collision, repeats, small type,
odd cut gaps. Anchor walls no longer override floors (`plan.py`, "anchors
relaxed"). Related: [[feedback_look_at_every_shot_before_delivering]],
[[reference_kinetic_text_and_tension_measurements]],
[[feedback_no_emphasis_cards_in_video]].
