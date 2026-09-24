---
name: project_remotion_video_pipeline
description: "Since 2026-09-15 a Remotion-composed video is built ONLY by `python tools/make_video.py <slug>` in D:\\faceless-studio; the traps that made the fifty-year-car cut fail repeatedly, and why each safeguard exists"
metadata:
  type: project
---

Remotion became the compositor on 2026-09-12. On 2026-09-14/15 the owner found
elements ahead of the voice, wrong document highlights, dangling kinetic words,
slow narration, no punctuation in captions and no cue sounds. Every one was a
step run out of order, skipped, or run against a stale input. The fix is one
entry point: `python tools/make_video.py <slug>` (highlights -> retime ->
timeline -> check --pre -> render -> finish -> check --post -> contact sheet).
Resume with `--from <step>`. `fvs run` now stops after align on any manifest
with `"compositor": "remotion"`, and `fvs plan/assets/compose/shorts/qa` refuse.

**Why:** these traps are not visible from any one file.

- **Never touch narration.wav or words.json outside `fvs`.** Voice freshness is
  the HASH of narration.wav, so an out-of-band stretch made the next `fvs run`
  re-voice (paid). Pace with `python -m fvs voice <slug> --speed N`: it rebuilds
  from `tts_cache` for free and applies tempo once (VOICE_TEMPO 1.08 x speed).
  `fvs run` used to hardcode speed=1.0 and undo it; it now reads the stored speed.
  Prove a re-voice is free by monkeypatching `voice._gemini_paragraph` to raise.
- **beats.json is the plan and is never rewritten.** `retime.py` writes
  `beats.timed.json` stamped with the sha256 of beats.json + words.json; a
  layout that rewrote its own input moved 23 boundaries on a second run.
  Anchors live INSIDE each beat (an index-keyed table silently re-pointed twice).
- **Highlights come from quotes, never rectangles.** Document beats carry
  `pdf`/`page`/`quote`; `locate_highlights.py` renders the page from the archived
  PDF at 200 dpi and finds the words. Six of eight hand-drawn rectangles were
  wrong, three on a different passage.
- **A Remotion Sequence stops drawing at its last frame**, so a kinetic slice
  that reaches past its boundary hides the dangling word only in JSON. Retime
  moves boundaries onto phrase ends and merges adjacent kinetic beats; a short
  pickup ("On safety") goes to the caption band as `tail`.
- **"No frame found at position" was memory, not media.** A 256 MB
  `--offthreadvideo-cache-size-in-bytes` cap made it deterministic; 512 MB and
  768 MB render clean. On 5.85 GB with Chrome open ~0.5 GB is free, which is why
  single-pass renders failed intermittently. Render in segments, muted, with the
  cache keyed by a hash of props + src + fonts + lockfile (a skip-if-exists cache
  once concatenated a stale segment).
- **The migration dropped the whole audio finish** (cues, ambience, bed, -14 LUFS
  mastering, stereo 384k AAC, captions.srt). `finish_master.py` restores it by
  calling compose.py's own functions. Cues sit on each element's visible moment.
  Measure every cue full-band AND behind a 250 Hz high-pass: the owner's thud
  and impact were silent on phones (-7 / -4.5 dB under the voice) until the
  owner's tick was layered on them.
- **Vendored font, pinned versions.** Antonio is in `remotion/public/fonts` and a
  failed load cancels the render (Chromium falls back silently). Dependencies
  are exact pins; `@remotion/google-fonts` was removed. The production entry
  `src/index.jsx` takes the timeline via `--props`; experiments are in `src/lab.jsx`.

- **Inner events are `marks`, not guesses** (added 2026-09-15). A beat maps
  a key (`lateAt`, `rightAt`, `landsAt`, or `items.3` / `cards.0` / `terms.2`)
  to a spoken phrase; retime resolves it at or after the beat's anchor and never
  scales it during animation compression. check --pre re-derives every mark.
- **Kinetic lines are broken in Python from Antonio's real advance widths**
  (`kinetic_pages.lay_out`, zone 768px, max 3 lines) and drawn nowrap; check
  --post measures ink width from the rendered frames. Size changes only when
  one word is wider than the zone - the first version shrank 35 of 212 pages to
  avoid a function word at a line end, and jumping sizes read worse.
- **Evidence beats:** `pair` (two located quotes side by side; letters need
  `"max_width": 0.9`), `checklist`, `cards`, `photo` (every photo must be in
  `sources/evidence/licences.json` as PD/CC0/CC BY with a credit), AgeAxis
  `from_years` (not `from`, which is the frame key), document `motion: "pan"`.
  A persistent SectionMark comes from script.md `#` headings; hidden on
  document/pair/photo.
- **`fvs voice` tempo rounding bug (NOT fixed, owner decision):** ffmpeg gets
  `atempo={tempo:.3f}` but timings divide by the unrounded tempo, so speeds whose
  1.08 x speed has more than 3 decimals fail ("timing extends outside the
  rendered audio"). Use a speed where it is exact (1.225 -> 1.323). Fixing
  voice.py changes the voice code hash for EVERY project.
- **Cue level is set RELATIVE to the measured voice, never as a fixed gain**
  (`finish_master.level_cues`, 2026-09-16). Fixed gains plus the -14 LUFS
  normalisation put every cue at -1.5 dBFS, +12 dB over the master's speech RMS
  and level with the loudest speech peaks. Now: speech RMS +2 dB under speech,
  -4 dB in a pause (nothing masks a cue in a pause, so it startles at the same
  level). Anchor: the owner's D:\src mixes clicks at 0.24-0.50 against voice 1.0.
  **Measure like for like**: I first reported "+20 dB" by comparing cue peaks in
  the MASTERED file against narration.wav, which the mastering had not yet
  gained. And a cue under speech cannot be measured from the mix at all - the
  speech peak owns the window - so measure `sfx.wav` alone and add the mastering
  gain (master speech RMS minus narration speech RMS).
- **A highlight is one band PER LINE, drawn in multiply** (`locate_lines`,
  `diagrams.Marker`). A union box over a wrapped quote marks words that are not
  in it and bleeds into the next line; a 38% normal-blend wash greys the text it
  is lifting. Group words into lines by GEOMETRY (vertical overlap > 0.6), never
  by the PDF's line index - CFR justified columns give every word its own line -
  and split a hyphenated word across both lines it occupies. The rest of the
  page dims 30% behind an SVG mask (dim-the-rest beats band beats box beats
  underline). check --pre re-derives the bands and fails one over 1.7x that
  page's own median line height.
- **A pan derives its DISTANCE from the time available, capped at 3 px/frame.**
  Measured 7.8 px/frame (peaking 9.2) on 2026-09-16, where practice puts
  readable detail near 3 - that is what the owner called "glitchy". An
  ease-in-out doubles the mid-point speed, so use near-constant speed with soft
  ends. Pushes are set by RATE (2% a second, `beats.Shot`/`evidence.Photo`): a
  fixed 1.06 over a six-second shot is 0.01 px/frame, i.e. no move at all.
  Document pages render at 300 dpi (200 left a 2.8x push at 1:1 parity).
  `tools/study_motion.py` scans a master for repeats and uneven motion;
  per-frame displacement is measured by row-profile correlation.
- **The picture has a camera and a texture pass** (`src/camera.jsx`, 2026-09-16,
  adopted from the owner's D:\src). One camera is computed per frame - a slow
  deterministic drift plus a decaying kick on every cut - and broadcast through
  context; the picture rides it at depth 1, captions and the section mark at
  0.35, so the frame has depth instead of sliding as one plate. Atmosphere adds
  a grain tile (an `<Img>`, never a CSS background, which flickers), a gentle
  vignette and a 3% flash on each cut. Keep vignettes from stacking: the shot's
  own gradient plus a frame vignette crushed corners to 19/255.
  Deliberately NOT adopted: D:\src's scanlines (right for an instrument panel,
  wrong for a federal rulebook).
- **Springs are named per material** (`src/motion.jsx`): `lock` for anything
  that must align, `heavy` for a number resolving, `glide` for pages and
  panels, `pop` for cards and rows, `snap` for marks and tags. Kinetic words
  RISE OUT OF A MASK (overflow hidden + paddingBottom/marginBottom 0.16em so
  descenders are not sheared) instead of fading - a fade crosses every
  intermediate opacity and reads soft on a dark ground. Counters carry
  `fontVariantNumeric: "tabular-nums"` or the digits jitter as they count.
- **The camera holds STILL on document and pair beats, and overscans only
  while it moves** (bug review 2026-09-16). Drift without overscan exposed a
  3 px seam at the frame edge on footage (luma 120 vs 90); overscan fixed that
  but resampled every page, costing document text 15% of its sharpness
  (Laplacian variance 4096 vs 4836). Held still: 4853 vs 4836. Measure
  sharpness with `"camera": false` in a props copy.
- **Bugs a two-reviewer pass found in one rushed day, all verified before
  fixing** - keep these in mind when touching the same code:
  a CSS `em` on a wrapper resolves against the INHERITED font size (the kinetic
  mask got 2.6 px of descender room, not 21); a component default is dead if
  the caller passes `?? 1.06` (the rate-based push never ran, and checking the
  timeline JSON did not reveal it - check what reaches the component); a sweep
  normalised per band never finishes a single band (stopped at 83%); two eased
  segments must share their SLOPE, not just their value (a 28% speed jump at
  the joint); a self-referential estimate must not iterate (`reading.needs`
  flipped 4.67 <-> 3.75 for ever); a render cache keyed by file name and size
  reuses stale segments, so hash every file the timeline loads by content; a
  marked highlight needs its tag moved after it (tag arrived 3.8 s early).
- **Render flags for text quality**: `--jpeg-quality=95 --crf=16
  --color-space=bt709`. Remotion's default frame format is JPEG at 80, which
  softens small type and a gold band against white paper.
- **Wikimedia thumbs only serve standard widths** (250/500/960/1280/1920); 640px
  returns 400. Throttle to one request every few seconds and honour retry-after,
  and never run two download jobs at once (a subagent's job and mine fought
  over the rate limit for an hour).

- **Traps found on the smart-tv film (2026-09-17), all fixed:** (1) an archived web
  page can print with its cookie banner or sticky header OVER the text - the PDF
  still has a text layer, so quote checks pass while the page image is blank;
  render the page image and look, and re-capture with Playwright removing fixed/
  sticky elements. (2) DocumentPair assumed US Letter; a landscape slide showed its
  heading, so the crop now carries `aspect`. (3) a long document `cite` runs into
  the caption line - keep cites under ~23 characters. (4) Whisper can fold several
  words into one; align.py used to invent time past the next anchor (out-of-order
  crash) and now spreads the run over released neighbours. (5) widening a pair crop
  to whole lines made the type unreadable; a tight crop that cuts edge words reads
  better. New elements `ledger` (segment gross profit) and `menu` (settings path,
  `separate` for alternatives) live in tech.jsx.

- **Evidence Cinema (approved 2026-09-22, `production/evidence_cinema.md`).**
  Beats carry `stamp` (official / court / company / filing / study / data /
  illustration, printed as a chip: bottom RIGHT on document and pair beats,
  where the tag owns bottom left and captions the centre; top left elsewhere,
  always on its own dark ground or it vanishes on white paper) and `state`
  (verified / announced / plausible; plausible also drops contrast to 0.88).
  `check --pre` now FAILS any picture beat that names no source - no quote, no
  credit, no `stamp: "illustration"` - which is the anti-decoration rule made
  mechanical (proved on a planted cards beat). Four elements in
  `src/argument.jsx`: `timeline` (with `liftFrom: "previous"`, the builder
  computes `origin` from the previous document's located highlight using
  DocumentBeat's own geometry - page 634x820 at (643,130), scaled about
  (960, focusY), lifted so the line stops at 680px - so a date leaves the page
  where it really sat), `traverse` (one token carried through stations),
  `board` (archived page thumbnails plus drawn threads) and `replay` (the chain,
  then a collapse to the thesis). All four use `items` as their list key so
  marks and the reading model work unchanged. Motion helpers: `stagger`
  (STAGGER_FRAMES 5) and `draw` (dash offset, 13 frames).
- **The dataviz skill's categorical validator does not apply to our charts.**
  Our ledger is the skill's own EMPHASIS form (one accent hue + grey, every row
  directly labelled), not a categorical palette; the accent/grey pair FAILS the
  lightness-band and chroma-floor checks by design but passes CVD separation
  (dE 19.0 protan, 21.5 normal) and contrast. Do not "fix" it by adding hues.

- **Kinetic text lagged the voice, and it was our animation** (fixed 2026-09-22).
  The word rose from a mask starting AT its spoken time: SPRING.snap is half up
  after 100ms and readable at 233ms, and whisper's word starts sit a median
  +53ms behind the real audio onset (43 words with clean silence before them).
  Text therefore landed ~0.28s late. `ScriptKinetic.INK_LEAD = 0.25` starts the
  ink early while the gold "being said" colour still switches exactly on the
  word; `Subtitles` gets the same 0.2s lead. MEASURED ON THE FINISHED FILE
  afterwards: ink reaches half height a median 127ms BEFORE the word (20 words,
  quartiles -157/-5ms). Measure this from the master, never from the code: the
  first attempt measured leftover ink from the previous page and read -900ms.
- **Full-screen kinetic was 32% of the cut and read as a wall** (same day). 82
  pages, median 3 words, median 0.8s on screen, shortest 0.2s - because
  MAX_WORDS was 4 and ANY comma closed a page. Now MAX_WORDS 6, CLAUSE_MIN 4,
  and `_merge_brief` folds any page under MIN_LIFE 1.0s forward: 44 pages,
  median 1.8s. And a kinetic beat may carry `"over": "previous"`, holding the
  document it follows behind the words (WHOLE page, opacity 0.1, blur 1.5px -
  at the document's own 2.8x zoom one headline fought the type). Full-screen
  text is now 10% and check --pre warns over 25%.

**How to apply:** for any Remotion video, run make_video.py and read every
WARN; `tools/check_video.py` was proven against eight planted defects. Open
items as of 2026-09-15: shorts and package stages have no Remotion path yet;
Gemini web image session expired (`nlm login`). Related: [[project_faceless_video_studio]],
[[reference_phone_speaker_test_for_cues]], [[feedback_look_at_every_shot_before_delivering]].
