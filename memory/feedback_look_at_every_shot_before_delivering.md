---
name: feedback_look_at_every_shot_before_delivering
description: Never hand over a faceless-studio render without viewing the contact sheet of EVERY shot; a stock clip of someone pulling a condom from a jeans pocket reached the cold open on 2026-09-04 because the ranker trusted a caption match and I checked only a handful of frames.
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-04T07:52:16.066Z
---

Before telling the owner a video is ready, run `fvs contact <slug>` for every
page and look at every shot. Not a sample at "interesting" timestamps: all
of them. On 2026-09-04 the query "hand reaching into jeans pocket for coins"
returned a Pexels clip captioned "a person getting condom from a jean's
pocket"; the caption scored a confident text match, the vision check was
skipped on that confidence, and the clip opened the video. The owner saw it
before I did: "the clip is so disturbing you cannot even imagine."

**Why:** stock search matches words, not subjects. "Pocket" matched. The
same day it returned a Minolta camera for "old worn pennies", pesos for
"coin minting press" and Cyrillic spines for "law books". A caption match
is evidence about the caption, never about the frame. Trust in the ranker
was the whole failure; the fix is a human eye on every frame plus the
machine checks below, not a better ranker.

**How to apply:** (1) `fvs/stages/assets.py` now refuses candidates whose
caption trips `CAPTION_BLOCKLIST`, vision-checks EVERY pick
(`VISION_QA_BELOW = 2.0`), and leaves a shot EMPTY instead of "keeping the
top pick" when the vision check refuses everything. (2) After `fvs assets`,
build both contact-sheet pages and read them as images before compose.
(3) Report what you saw, shot by shot, in the message that hands over the
render. Related: [[feedback_no_emphasis_cards_in_video]],
[[feedback_verify_rendered_visuals_after_build]],
[[feedback_dom_is_ground_truth_not_parsers]].
