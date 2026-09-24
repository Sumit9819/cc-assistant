---
name: project_iwc_card_generator
description: In-body infographic card generator for irvingwellnessclinic (spec -> Playwright render -> WebP -> REST upload -> body patch), now at D:\cc-assistant\tools\card-generator with README; frame is 1200px wide fixed and >=630px tall auto-grown per card since 2026-09-08; rescued 2026-09-06 from a Temp scratchpad; 147+ cards live across ~40 posts; deletion backlog of superseded attachments still open
metadata:
  type: project
---

**What it is.** The tool that produces the in-body graphics on irvingwellnessclinic blog
posts: JSON specs, ten HTML layouts (`layouts.mjs`), Playwright render at 2x, LANCZOS to
1200x628 WebP, REST upload with alt, and `draft_patch_post_content` payloads that swap the
old design-team images in place. Content guards (no `undefined`, no duplicate labels, no
split highlights), `validate_specs.py`, and the full British-to-US word list live with it.
Full pipeline in its README.

**Where it is.** `D:\cc-assistant\tools\card-generator\` (57 MB, 914 rendered files in
`out/`). Until 2026-09-06 it existed ONLY in a Claude Code session scratchpad under
`AppData\Local\Temp\claude\...\9c6f1bb7...\scratchpad\gen`, a folder the harness may
delete. The operator asked "do you know we were able to generate in-body images?" and I
had not carried it into the workspace. Its six upload/fetch scripts pointed at the old
Local Sites `.mcp.json`; repointed to `D:\cc-assistant\.mcp.json` the same day.

**Why it matters.** Blog graphics for this site are generated here, not in Canva, and
Apsara/Bibek have not yet been told creative work moved to the generator (open loop on the
site). Every content-quality rule in [[feedback_guards_must_check_content_not_geometry]],
[[feedback_bulk_text_rewrites_pin_and_simulate]] and
[[feedback_look_at_every_shot_before_delivering]] was learned on this tool.

**Open.** Deletion backlog: 65 superseded generated attachments + 76 old design-team
images + strays, blocked on pending approvals and on the delete_media stem-prefix false
positive ([[reference_delete_media_stem_prefix_false_positive]]). Playwright is borrowed
from `~/.cc-assistant/wcag`, so a fresh machine must run wcag_sweep once first.

**How to apply.** Any new blog post or card refresh on IWC uses this pipeline, not Canva.
Anything built in a scratchpad that will be used again must be moved into
`D:\cc-assistant\tools\` before the session ends; scratchpads are not storage.
Related: [[project_cc_assistant_operator_brain]].


## 2026-09-08: the frame is 1200 wide, 630+ tall, sized per card

Operator: "the width should be 1200px default and should never be changed and
minimum height should be 628 or 630 ... however, the height can be changed
according to the need. but minimum height should be around 630, not less."

**Width 1200 is not negotiable.** Every consumer assumes it (Open Graph, Google
Images, the post content column) and the type scale, disc sizes and column
arithmetic in all ten layouts are tuned to it.

**Height is a FLOOR of 630, never a ceiling.** `make.mjs` renders at the floor,
measures the real content bottom plus the room the bottom of the frame owes it,
and re-renders taller if needed. Layouts position content from the top and know
nothing about the frame; the frame is sized around them afterwards. A spec may
name its own `height`, treated as a floor too. Set with `setHeight()` in
`layouts.mjs`, which also rounds to an even number because the render is 2x.

The reserve is measured, not guessed: with a bottom-anchored logo it is the mark
plus the same 22px keep-out the collision check enforces, otherwise a 56px
margin. `quote` and `alert` centre their content in the frame, so for those the
measurement sizes from the content's extent instead of its current bottom edge,
or growing the frame would just re-centre and never converge.

**Why it mattered.** `compare` used to shrink to fit: a 5-row table dropped to
62px rows and 16px cells so the board stayed inside one 628px frame, which made
the graphic harder to read than the paragraph it replaced. Rows now size for
legibility (100px at two rows, 88px from three up, 19px cells throughout) and
the card gets taller. Measured: `skinvive-vs-filler-compared` renders 1200x776
at full size instead of 1200x628 crushed. 2- and 3-row compares are unchanged.
Nothing already live changes until it is re-rendered, and no erofirving compare
card exceeds 3 rows, so this only affects IWC on re-render.

**Two downstream files had 628 baked in and now read the real pixels off disk:**

- `towebp.py` resized to a hardcoded (1200, 628), which would have squashed
  every tall card back into the old frame and distorted its type. It now halves
  the PNG's own dimensions and refuses anything not 2400px wide.
- `mkpatch-erof.py` wrote `width="1200" height="628"` into the figure. Those
  attributes are what lets the browser reserve the box before the image loads,
  so a wrong height causes the exact layout shift they exist to prevent. It now
  reads the encoded WebP with PIL.

`sheet.py` needed no change; it already scaled each card by its own aspect.
The one-shot IWC-era scripts `mkpatch.py`, `mkswap.py` and `mk9462.py` still
have 628 hardcoded. Their batches are long applied; fix them if ever reused.

Related: [[project_erofirving_card_generator]],
[[feedback_card_count_follows_the_post]].
