---
name: reference_2026_hook_grammar
description: "Measured on eight Feb-Aug 2026 explainer uploads (2026-09-05) - hooks open on a place and a date with no title and no question, the first \"but\" lands inside 20s with a visual change; turns 0.78-1.53/100w; type devices are a small dated slug, one giant word for the turn, and labels that swap in place. Also two patch traps hit that day."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-05T03:59:08.104Z
---

Owner's rule: research hooks and tension on NEW uploads, not the archive
("people are adapting"). Measured with `tools/study_script.py` on json3
transcripts in `D:\faceless-studio\study\recent\` and half-second frame
sheets in `study/kin2/`; page section "Part three" via
`tools/build_tension_page.py`; notes in DECISIONS.md "2026 hooks".

**2026 hook grammar (7 of 8):** open on a PLACE + DATE or a named person,
no title, no question ("In January 2026, the US military captured...",
"Once upon a 2016, a man boarded..."); first turn inside 20s with a
visual change; questions after the scene. Turns 0.78-1.53/100w (older set
0.69-1.07); first person 0.5-4.1; figures 0.8-4.4; 130-211 wpm.

**2026 type devices:** small dated SLUG top-left over the scene (Search
Party), ONE giant word/number on the turn ("CUBA", "$2,385"), LABELS THAT
SWAP in place as the thing is renamed (neo "BOEING VC-25" -> "AIR FORCE
ONE"), numbers changing on a prop document (HAI). 2019 HAI yellow-caps
everywhere is gone; 2026 HAI is mixed case, fewer words, flat brand grounds.
Cleo Abram: a face and a place, almost no type, 9M views.

**In the pipeline:** `fvs/keywords.py` marks `*word*`, `*~old~ new*`,
`*?*`, `*!BIG*`, `*@Slug*`, `*=swap*` + auto figures; `tools/check_script.py`
enforces the grammar (scene + turn in 60 words, turns >= 0.8/100w, first
person per chapter, no "so/then" paragraph openers).

**Traps hit:** (1) `_MD_INLINE` in voice.py stripped `*` as markdown, so
the mark prefixes `@`/`!` reached the TTS - three chunks voiced wrong
before the chain was killed; (2) writing a regex through a non-raw Python
patch string turned `\b` into a literal backspace (0x08) and the rule
silently never matched. Write regex patches as raw strings and test the
compiled pattern's `.pattern` repr. Related:
[[reference_kinetic_text_and_tension_measurements]],
[[feedback_bash_heredoc_and_pipe_gating]].
