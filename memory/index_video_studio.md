---
name: index_video_studio
description: Video / Faceless Studio index — pointers to 14 memory files; open the ones the hook names
metadata:
  type: reference
---

# Video / Faceless Studio index

One line per memory, moved verbatim from MEMORY.md on 2026-09-06 to keep the index under its read limit. Open the file for the full rule.

- [Propose before implementing (video)](feedback_propose_before_implementing_video_changes.md) - HARD; written proposal + approval before any template/planner/script change or rebuild
- [Canva MCP: stills yes, video no](reference_canva_mcp_video_pipeline.md)
- [Faceless Video Studio](project_faceless_video_studio.md) - D:\faceless-studio; Kokoro timings
- [Template literals eat backslashes](reference_template_literal_eats_backslashes.md) - use String.raw for embedded scripts
- [Measured layout for variable text](feedback_measured_layout_for_variable_text.md) - wrap + measure, never fixed offsets
- [CSS mask dies on file:// URLs](reference_css_mask_fails_on_file_url.md) - inline as data URI; element vanishes silently
- [No emphasis cards in videos](feedback_no_emphasis_cards_in_video.md) - HARD; rejected twice; cards must add info; shots >=2.2s, stills >=5s
- [Look at EVERY shot before delivering a video](feedback_look_at_every_shot_before_delivering.md) - HARD; condom clip reached the cold open; contact sheets, all pages, every time
- [Reference explainer measurements](reference_explainer_video_measurements.md) - 7 videos measured 2026-09-04; median shot 1.5-3s, 83-95% cuts mid-phrase, 154-186 wpm, documents as footage
- [Guards must check content, not geometry](feedback_guards_must_check_content_not_geometry.md) - layout guards passed "undefined"; read rendered text + spec shape; look at every image
- [Kinetic text + tension measurements](reference_kinetic_text_and_tension_measurements.md) - 2026-09-05; no pops anywhere in 2026: type types (30-60ms/char), blurs in on the word, counts up, swaps in place, flashes INTO a card; study_type.py
- [2026 hook grammar](reference_2026_hook_grammar.md) - place+date cold open, turn in 20s, slug/giant word/label swap; regex-in-patch backspace trap
- [Text zone 40% + QA before delivery](feedback_video_text_zone_and_qa_before_delivery.md) - HARD; type never edge to edge; no clip flash before a card; run tools/qa_master.py on every master
- [Remotion video pipeline: make_video.py only](project_remotion_video_pipeline.md) - HARD 2026-09-15; never edit narration/words outside fvs; beats.json is never rewritten; highlights from PDF quotes; cache>=768MB; cues measured through a phone
- [Reading time before pace](feedback_reading_time_before_pace.md) - HARD: measure what must be READ (tools/reading.py, study_reading.py) before touching voice speed or beat lengths; 16 of 17 evidence beats were unreadable after chasing a wpm target
- [Demand before topic](feedback_demand_before_topic.md) - HARD 2026-09-17: run tools/study_demand.py before choosing a topic; yt-dlp subscriber counts can be wrong; three pillars (got here / doing to you now / already decided), Future only on paper
- [Training phase, Element Lab](project_video_training_phase.md) - 2026-09-23: nothing is being uploaded by choice; build elements in the Element Lab first; batch 1 built, batch 2 proposed
- [Never circle highlighted text](feedback_never_circle_highlighted_text.md) - HARD 2026-09-23; band alone, arrow or note; gate fails mark:"circle"
