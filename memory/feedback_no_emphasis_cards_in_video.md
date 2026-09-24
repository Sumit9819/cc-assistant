---
name: feedback_no_emphasis_cards_in_video
description: "Faceless-studio videos must never carry \"emphasis\" cards (big type restating the spoken line, e.g. \"Ended by a memo.\"); the owner rejected them on 2026-09-02 and again on 2026-09-04 after I re-added them."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-04T05:21:43.328Z
---

No emphasis cards in any faceless-studio video. An emphasis card is large
type that repeats the sentence being spoken ("Ended by a memo.", "Nobody
has signed it."). The owner cut five from roman-concrete on 2026-09-02
("they didn't put much effort... the design is awful") and, when I put two
back into penny-cost, said on 2026-09-04: "I already told you to skip such
thing like 'Ended by a memo', this style was not good."

**Why:** a card must carry information the narration does not. Stat cards
(figure plus source), chapter cards (structure), timelines and comparisons
do; an emphasis card only restates. Restating in big type reads as low
effort, and the rule was already written in DECISIONS.md when I broke it.

**How to apply:** `fvs/cardplan.py` now has `REFUSED_KINDS = {"emphasis"}`
and skips them with a message; do not add the kind back under another
name (highlighter sweeps of the spoken phrase are the same thing). When a
line needs weight, give it a longer shot and a pause, not a caption. Also
part of the same feedback round: shots under 2.2s, Polaroids under 5s and
cards under their read floor all read as "too fast"; the planner floors in
`fvs/stages/plan.py` encode that. Related: [[project_faceless_video_studio]],
[[feedback_precise_figures_only]].
