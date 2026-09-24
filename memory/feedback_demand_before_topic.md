---
name: feedback_demand_before_topic
description: HARD 2026-09-17 - measure YouTube demand (tools/study_demand.py) before choosing any video topic; supply-first picked a topic whose explainers had 583-1,386 views
metadata:
  type: feedback
---

Choose a video topic from measured demand, never from what our elements
can draw. The TLS 47-day certificate film was picked because it suited the
pipeline; the owner asked "do you think this topic will get us anything?" and
independent explainers on it had 1,386 and 583 views.

**Why:** every check in the studio measured craft; nothing asked whether anyone
wanted to watch. The owner wants topics "that interest many people": what is
coming, what has arrived, how tech is evolving.

**How to apply:**
- `python tools/study_demand.py outliers channels.txt` (views / channel median)
  and `topics queries.txt` (recent median views, breakout = views/subs, fresh
  share, giant share, small-channel wins). Inputs and dated results live in
  `D:\faceless-studio\study\demand\`.
- yt-dlp returns WRONG subscriber counts for some channels (Business Insider
  106, BBC News 201). The tool now treats views over 100x subs as a bad count;
  read small-channel wins by eye anyway.
- Search queries drift ("high tech cars aging like smartphones" returned the
  Apple event), so a low recent-median can be query noise. Cross-check with
  channel outliers.
- Channel structure agreed 2026-09-17: three pillars, "how it got here / what
  it's doing to you now / what's already decided". The Future pillar is only
  futures on paper (a ballot, a filing, a ship date), never speculation, and
  Present explains rather than races news (our build takes days).
- A topic still needs a documented spine (primary sources) and visuals we can
  license. Present the shortlist for owner approval before script or voice.

Related: [[project_remotion_video_pipeline]], [[feedback_precise_figures_only]].
