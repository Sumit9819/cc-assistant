---
name: project_iwc_featured_generator
description: MULTI-BRAND featured/OG image generator (iwc + all three ER sites) at D:\cc-assistant\tools\card-generator\featured — 1200x630, 4 layouts, isnet ONNX background removal, contrast + listing-size proof; built 2026-09-06; renders only, upload/attach not built yet; round-2 rules: waist-up cutouts only (column-paint floor 28%), edge defringe on by default
metadata:
  type: project
---

**What it is.** Featured and Open Graph images at 1200x630, built beside the
in-body card generator and on the same principle: content is data, layouts are
HTML, Chromium renders, and the defects invisible at full size get measured.
Four layouts: `photo-dark`, `photo-right` (both need a cutout), `photo-panel`
(any photo, uncut, cover-cropped) and `type` (no photo). Full pipeline in its
README. Related: [[project_iwc_card_generator]].

**Photo sources, in order.** `geminibrowser.mjs` drives the operator's OWN logged-in
Gemini web chat in a headed Chrome, using a copy of ~/.gemini/antigravity-browser-profile.
This is what the operator asked for; do NOT reach for the AI Studio API keys instead.
Generation works end to end in ~20s/image. Two traps: Gemini shows a working prompt box
to SIGNED-OUT visitors, so testing for the box reports "ready" on a login page (test for
the sign-in button instead, and always screenshot the probe); and the image arrives as a
blob: URL that context.request cannot read and a page-side fetch is blocked from reading,
so capture is an element screenshot - taken at naturalWidth, since the display size is
smaller. Web-chat images come out ~1024px on the long edge.

**API fallback.** `gen.py` uses the AI Studio keys in D:\faceless-studio\.env with rotation;
out of DAILY free-tier image quota on 2026-09-06 (text models still answered, so the keys
are fine). `stock.py` pulls Pexels - **its API 403s with Cloudflare error 1010 unless the
request carries a browser User-Agent, which looks exactly like a dead key and is not one.**
Pixabay is wired but wrong for this brief.

**Status as of 2026-09-06.** Renders and proofs only. Five test images exist for
posts 10192, 10229, 8559, 8563 and 9465. **Nothing has been uploaded to the site
or attached to any post.** Still to build: WebP encode, upload, attach, and the
Rank Math OG field.

**Background removal.** `isnet-general-use.onnx` through onnxruntime directly, no
rembg. Both it and `u2net.onnx` are Apache-2.0 and fine for client work; BRIA
RMBG-1.4 is better on hair but is NOT free commercially. Models live in
`featured/models/` and are in no backup - re-download from the rembg v0.0.0
release tag.

**The finding that shapes how it is used.** Cutouts work on one obvious subject
against a plain ground - a standing figure, a studio portrait. They FAIL on
scene photography: IWC's reclining-client facial photo (attachment 9793)
returned a disembodied head from both models, because a white towel against a
white wall gives the model nothing to separate. Such photos go in `photo-panel`
uncut. `cutout.py` refuses this case by measuring the share of the subject's own
bounding box that is transparent, which plain coverage misses.

**Two things a featured image needs that a card does not.** It is read at ~400px
in the blog listing, so type is ~3x the card sizes and there is far less of it;
and Rank Math stores its OG image SEPARATELY from the featured image, so setting
one leaves the other stale. Also: `featured_image_id` exists only on
`draft_create_post`, so an existing post needs `draft_update_postmeta` with
`_thumbnail_id`, which validates nothing - a `draft_set_featured_image` tool is
the right fix.

**Operator feedback, round 1 (2026-09-06).** Liked the look; three defects, all
now fixed and guarded. (1) The panel fade was an overlay painting ground-coloured
pixels ON TOP of the photo, which read as a printing fault - it is now a mask on
the image so the photo genuinely dissolves. (2) A head was cropped, because the
source photo was cut at the chest; `proof.py` now refuses any cutout layout whose
subject touches the top edge. (3) `photo-right` had a second background colour
behind the subject, making one frame look like two images stitched together - one
ground now, edge to edge, and the yellow accent bar is on every layout.

**Operator feedback, round 2 (2026-09-08).** "The left side is good, only the
image side is lacking." Two separate defects, both now guarded.

(1) **Subject match.** The semaglutide card - an article about the scale
STALLING - was illustrated with a lean woman in activewear, which contradicts
its own headline. Regenerated as a plus-size woman, waist-up, dignified framing
(clothed, square to camera, neutral, no headless-torso crop, nothing implying a
before/after or a named patient). Ask for the reader the article is actually for.

(2) **"Too much gap" was not a spacing value.** Every cutout layout fits the
photo into the same 560x630 slot by HEIGHT, so a full-length standing figure
scales down until its body is a narrow strip: same bbox height as a waist-up
portrait, a third of the mass (bbox 68% transparent vs 70-71%). It painted 20%
of the photo column where the two approved portraits paint 46-47% and the still
life 32%. `proof.py` now measures `column_paint` from the shipped PNG and
refuses under 28% - verified as a positive control: it fires on the rejected
photo at 20% and passes the replacement at 52%. **The rule for every future
shot: waist-up, shoulders filling the column, bleeding off the BOTTOM edge
only.** Never a full-length figure in a cutout layout.

Also found and fixed on the way: the soft matte edge keeps the studio ground's
RGB (edge 177 vs subject core 140), invisible on the pale layouts and a halo on
`photo-dark`. `cutout.py` now decontaminates edge colour by default
(`--keep-fringe` opts out). That does not reach background trapped BETWEEN loose
curls, which the mask marks fully opaque; colour-keying it punches holes in a
grey T-shirt (the same light-on-light trap), so ask for smooth pulled-back hair,
which is what both cleanly-cut portraits have. Still renders only - nothing
uploaded or attached.

**Multi-brand since 2026-09-08 (iwc + erofirving + eroflufkin + erofwhiterock).**
Runs on the in-body cards' own role tokens in `brands.mjs`; `brand` on a spec
entry, defaulting to `iwc` so the five older specs still render byte-identically
(md5-checked after every change - do that check, it is cheap and it caught
nothing only because it was run). White Rock's entry was read from its own
Elementor Kit: Montserrat, and the same #DA1212/#11468F/#041562 as its sisters,
with the Kit's primary drifting to #D01010 exactly as Lufkin's does - follow the
skill, not the Kit, or the three ER sites end up with three reds.

**What could not be shared was EMPHASIS, and the answer was to derive it.** IWC's
yellow marker behind green words is unreadable in brand red (navy on red is
3.16:1). So each ground asks a measured question: accent as text if it clears
4.5:1 on that ground, else the marker swipe (light) or white text with an accent
UNDERLINE (dark), which moves red from being type to being a graphic where 3:1 is
the bar. Same for the kicker. No per-brand branch anywhere, and a new brand
cannot ship unreadable emphasis.

**ER doctrine: NO PEOPLE, and it is the inverse of the wellness rule.** An ER post
is a triage decision, so anyone in frame is a patient who does not exist or a
clinician who does not work there; both ER skills say real facility and real staff
only, and White Rock's provider bylines are blocked for want of consent. ER posts
get `type`/`type-dark` (new layout, navy ground - their DEFAULT, not a fallback)
or an object still life. Enforced by the `er` profile in `image-policy.json`,
which also refuses distress staging, ambulances, and any wait-time or
hospital-comparison wording. **Do not reuse ER site media instead: erofwhiterock's
own `uploads/2025/01/*` service images are stock (smiling nurse at a CT scanner,
red-glow pain overlays), which is what the rule excludes.** Full text in section
26 of each ER design skill. Related: [[feedback_body_imagery_doctrine]].

**Framed layout + unit tests, 2026-09-08 (operator asked for both).**
`photo-frame` / `photo-frame-dark` with a `frame` variant, ER BRANDS ONLY,
enforced from image-policy.json's brand map rather than a second list. Named for
the slot, not the shape, because two of the twelve treatments are rectangular.
The treatments live in `frame-styles.mjs`, read by BOTH the review sheet
(`frames.mjs`) and the shipping layout - they were duplicated for one round, and
that is exactly how a reviewed option becomes a shipped option that looks
different. Frame B (heavy ring, gap, outer hairline) is the operator's pick.

**No halo, and the ring carries the right side alone (operator, 2026-09-08).**
"That grey circle outside of the image, it need to be removed, and make sure the
red line start from that big grey circle." The halo was a 600px flat disc behind
a 440px frame, so the ring read as a small bubble inside a bigger circle. It also
broke both of the frame's own bounds - at cx=915 it spanned 615..1215, crossing
into the 640px copy column and clipping 15px off the canvas - which a soft tint
survives and a ring does not, so the ring could not land on its exact edge. It
landed on the largest circle that fits: `CIRCLE` is now
`{cx: 922, cy: 315, d: 482, ring: 11}`, +17% disc area, 15px to the column and
11px to the right edge. Each frame now declares its `reach` (how far its
furniture sits OUTSIDE the box: B's hairline 13, I's brackets 16, E's plate 22 at
REF 300) because the box is not the footprint and no CSS here knows where the
canvas ends - a test checks every frame's whole assembly against both bounds, and
its positive control was inflating `d` until frame A bled to 1202. Two fits, because a circle keeps only pi/4 of its box and eats
the corners: `cover` (window onto a photograph, takes `focus`) and `inscribe`
(the whole image inside the circle, for a cut-out object). CSS can only reserve
the square that fits any aspect, so the exact rectangle is applied in-page once
the image loads and cross-checked against `circle.mjs` in Node. `frame_fill:
"dark"` for a near-black subject - a radiograph in a white disc is a hard
rectangle in a circle, which is the shape problem the frame was meant to fix.

**The emphasis-wrap guard was too narrow (2026-09-09).** It refused only when the
highlighted phrase contained a HYPHEN and wrapped, so "Car Crash" breaking at its
SPACE shipped two disjoint red underlines - the exact fault the guard's own
comment described. The hyphen was one route to the fault, not the cause. It now
reads the em's computed `backgroundImage`: a painted decoration is drawn once per
line box, so a decorated em that wraps is refused, while an undecorated one (the
accent-coloured text the light grounds use) may wrap freely. Positive control:
re-rendering the card that had just slipped through now refuses by name.

**The coverage guard refuses only on an ALPHA mask.** It reads the photo's pixels
back through an in-page canvas (a data: URI is same-origin, so getImageData
works - tested before relying on it) and measures the share of subject inside the
circle. With alpha the subject is known, so a low share is a fact and it throws.
On an uncut photo the mask is "not the backdrop", which on a scene counts context
as subject: the copperhead measured 74% because its leaf bed is wider than the
circle while the snake sits centred and whole. That case WARNS. A check that
cannot see the subject must not assert an opinion about it.

**33 unit tests, `node --test` from featured/, node:test only.** They caught three
of my own errors: (1) `focus` is INERT when source and frame share an aspect
ratio - cover overflows on neither axis, so there is nothing to slide, and the
first test assumed the opposite; (2) `(bw - dw) * 0` is `-0`, failing strict
equality and serialising as "-0"; (3) the inscribed SQUARE is the maximum-area
rectangle in a circle, so an aspect-preserving fit can never beat its area, only
the same image letterboxed inside it - an assertion and a comment both claimed
otherwise. Also: a backtick inside a CSS comment ENDS the template literal it
sits in, which is a syntax error 15 lines above where node points.

**Two corrections from the operator, 2026-09-08, both worth keeping.**

(1) *"The images are below, it should be in middle."* Objects were bottom-anchored
because that is what the layouts do, and what the layouts do was written for a
cutout PERSON - who stands on the canvas, bleeds off the bottom, and gets a pool
shadow under them. An object stands nowhere, so bottom-anchoring drops it into the
lower corner with the air above it. `photo_fit: "center"` centres it and
suppresses the pool. Portrait-shaped objects need a `photo_inset` too, or
`contain` scales them to the full column height, which reads oversized and trips
the top-edge guard (whose message now says so instead of only talking about heads).

(2) *"Generate in gemini, but use headless chrome."* `geminibrowser.mjs` carried a
comment insisting headless was impossible - "Google's bot detection treats
headless Chrome very differently" - which nothing had ever tested. **It is wrong.**
A headless probe on the copied profile comes back signed in, and a full generation
runs end to end in 31s with no window. `probe` and `gen` are headless by default
now; `login` stays visible because a login needing a human has to be seen;
`--headed` forces a window back. A comment asserting a limit is not evidence of
one.

**Operator, 2026-09-08: "we did a lot better for IWC than the ERs" - correct, and
the cause was not emptiness.** Measured ink coverage was HIGHER on the ER set
(27.7% mean vs 24.8%). What they lacked was SHAPE: IWC gets one three times over
(yellow slab behind the headline words, two hued corner washes, a subject bleeding
off frame) where the ERs had a red word, two invisible circles and an object
floating mid-column. Fixes, all derived from the same measured question - can the
brand dark sit ON the accent? - so IWC stayed byte-identical: (1) the kicker
becomes a filled accent CHIP with white type when it cannot, which is the callout
strip the ER skills already document; (2) corner washes are the brand navy at low
alpha, because #F4F4F4 on #FFFFFF is 1.10:1 and vanishes while IWC's sage on
off-white is 1.14:1 and reads - same delta, hue does the work; (3) `photo_fit:
"center"` CENTRES objects in their column and drops the pool shadow.
**A red UNDERLINE under the headline words was tried first and reverted** - it
bought a shape by selling the only saturated colour on a white card and read
quieter than the red word it replaced. Look before believing a rule.

**The gap that remains is a FACE.** No object still life competes with a person
looking at the reader, and the ER no-people doctrine is what forbids it. The only
honest close is real facility photography from the operator, which the ER skills
ask for anyway; a generated ER interior would imply a building that is not theirs.

**Three guards earned by the ER prototype review.** (1) A highlight that wraps AT
A HYPHEN - "ER X-Ray" broke to "ER X-" / "Ray" with two underlines; measured from
the em's client rects, since only the browser knows where a line broke. (2) Text
colliding with the LOGO - the guard's comment had claimed this for months while
the code only measured the frame; the ER marks are SQUARE, so one costs 68px of
vertical room where IWC's wide wordmark costs almost none. (3) `photo_inset`,
because bleeding a photo off two edges is right for a figure standing on the
canvas and reads as a clipping error for a rectangular object. Also: the
shell heredoc in this harness EATS DOUBLE BACKSLASHES, which turned a `\\b` in a
regex into a JSON backspace and silently killed a rule - write regex-bearing
files with the Write tool, and let a positive control prove the rule still fires.

**How to apply.** `proof.py` is not optional: it caught a sage highlight at 2.8:1
against the pale ground that looked fine full size and would have shipped (now a
yellow marker behind green text at 13.7:1). Review the set from
`out/_listing-sheet.png`, never from the full-size render. On a YMYL medical
site, prefer `type` over a stock photo that implies a specific patient or
provider. Related: [[feedback_look_at_every_shot_before_delivering]],
[[reference_tool_locations]].

**ER upload path WORKS (2026-09-24).** Batch for erofirving 4772/4773/4774/4792:
Gemini object still lifes -> `photo-frame-dark` frame b (the 4410 look) -> encode
**JPEG q86 at 1200x630 (70-85 KB)**, NOT WebP, because FB/older WhatsApp can fail on
WebP og:image -> POST `cc-assistant/v1/assets/upload` (response is `data.attachment_id`
/ `data.url`) -> `draft_update_postmeta _thumbnail_id` (pendings 1296-1299). Rank Math
falls back to the featured image for og:image incl. width/height/alt (verified on 4410),
so no separate OG field is needed unless a custom OG image was ever set.
- Uncut scene photos trip the circle-coverage WARN (43-49%): expected proxy; set `focus`
  and look. BP monitor needed focus 40% to stop the disc cutting it in half.
- image-policy gore rule now has `unless \bblood pressure\b` (was refusing BP briefs).
- GitHub research + decision (keep Playwright): D:\cc-assistant\reports\featured-image-research-2026-09-24\
