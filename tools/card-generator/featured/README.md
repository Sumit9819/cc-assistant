# Featured image generator (multi-brand)

Featured / Open Graph images at 1200x630, built on the same idea as the in-body
card generator next door: content stays data, layouts are HTML, Chromium
renders, and the defects that cannot be seen at full size are measured instead
of eyeballed.

Built September 2026. Nothing here uploads yet - see **Not built yet** below.

## Brands

`brand` on a spec entry, over the role tokens in `../brands.mjs` - the same set
the in-body cards use, so a colour is never named after its hue. Omit it and you
get `iwc`, which is why the five specs written before this file went multi-brand
still render byte-for-byte identically (checked by md5 after every change here).

| slug | site | type | accent | dark ground |
|---|---|---|---|---|
| `iwc` | irvingwellnessclinic.com | Poppins | yellow `#FFD900` | green `#003017` |
| `erofirving` | erofirving.com | Montserrat | red `#DA1212` | navy `#041562` |
| `eroflufkin` | eroflufkin.com | Montserrat | red `#DA1212` | navy `#041562` |
| `erofwhiterock` | erofwhiterock.com | Montserrat | red `#DA1212` | navy `#041562` |

**Emphasis is derived, not declared.** IWC's highlight is a yellow marker swiped
behind green words. In brand red that is unreadable - navy on red measures
3.16:1 - so each ground asks the brand a measured question instead. On a light
ground: can the accent carry TEXT (red on white, 5.15:1)? Then use it, otherwise
swipe it behind `dark` text (IWC, 13.7:1). On the dark ground: is the accent
legible on it (IWC yellow on green, 10.58:1)? Then accent text, otherwise white
text with an accent UNDERLINE, which makes the red a graphic at a 3:1 bar rather
than type at 4.5:1. The kicker follows the same rule. A new brand therefore
cannot ship unreadable emphasis, and nothing here is per-brand hardcoded.

**A two-colour brand needs its shape from somewhere else.** IWC's accent can sit
behind its headline words because its dark reads on that yellow (10.58:1). Brand
red cannot carry navy (3.16:1), so those cards had a coloured word and no graphic
at all, which is most of why they read flatter side by side. The same measured
question now also decides the KICKER: a brand that cannot slab its accent gets a
filled accent chip with `on_accent` type instead, which is the callout strip its
own skill documents. An underline under the headline words was tried first and
reverted - it traded the only saturated colour on a white card for a thin rule.

**Objects are CENTRED in their column**, never bottom-anchored:
`photo_fit: "center"`, usually with a `photo_inset`. Bottom anchoring belongs to a
cutout PERSON - they stand on the canvas, bleed off the bottom edge, and the pool
shadow puts them on a surface. An object stands nowhere, so anchoring it low drops
it into the lower corner with all the air above it, which is exactly what it
looked like. The same flag suppresses the pool, because a shadow under a floating
object sits on nothing. A portrait-shaped object needs the inset too, or `contain`
scales it to the full column height, which reads oversized and trips the top-edge
guard.

Corner washes come from `featured_shapes`. IWC's are its existing tints; the ER
brands use their navy at low alpha, because #F4F4F4 on #FFFFFF is 1.10:1 and
vanishes while IWC's sage on off-white is 1.14:1 and reads - the delta is the
same and the hue is what does the work.

The ER logos are a two-colour mark, so the `white` variant is a REVERSED lockup
rather than a whitened one: the navy half becomes white to survive a navy ground,
the red half stays red because red is the emergency signal. Note the ER marks are
SQUARE - at 68px one costs 68px of vertical room in a corner, where IWC's wide
short wordmark costs almost none. That is why ER cards use a smaller logo.

## Why it is separate from the cards

An in-body card is read at article width. A featured image is read at three
sizes and the smallest one governs: the blog listing card is about 400px wide.
A card's type ramp is unreadable there, so these layouts carry roughly a third
of the text at roughly triple the size. The frame is 1200x630, not the cards'
1200x628, because this is the Open Graph frame.

## Getting photography

Three sources, in order of preference.

**`geminibrowser.mjs` - the operator's own Gemini chat (preferred). HEADLESS.**
`node geminibrowser.mjs probe` then `... gen prompts-v2.json`. Drives the real
Gemini web app in a headed Chrome using a COPY of
`~/.gemini/antigravity-browser-profile`, so images are made on the operator's own
subscription rather than metered against a separate API project. Generation works
end to end in about 20 seconds per image.

  `probe` and `gen` run headless; `login` opens a window because a login that
  needs a human has to be visible to get one, and `--headed` forces a window back
  for any command. The file used to insist headless was impossible because
  "Google's bot detection treats headless Chrome very differently" - that was a
  belief nothing here had tested. Measured 2026-09-08: a headless probe on the
  copied profile comes back signed in, and a full generation runs end to end in
  31 seconds with no window. The probe screenshot is the evidence, which is why it
  is written every run.

  Two traps, both already paid for. The prompt box is present when SIGNED OUT, so
  testing for it reports "ready" on a login page - the check is now the sign-in
  button's absence, and the probe writes a screenshot precisely because the
  selector lied once. And the picture arrives as a `blob:` URL, which
  `context.request` cannot read and a page-side `fetch` is blocked from reading,
  so capture falls back to screenshotting the element.

**`gen.py` - the Gemini API.** Same prompts, via AI Studio keys in
`D:aceless-studio\.env` with rotation on 429. Out of DAILY free-tier image
quota as of 2026-09-06 (text models still answer, so the keys are fine); resets
midnight Pacific, or billing removes the ceiling at roughly 3-4 cents an image.
Kept because it is unattended and deterministic when it has quota.

**`stock.py` - Pexels.** `python stock.py --search "..." --screen` downloads each
candidate, cuts it out, and reports which have the WHOLE SUBJECT inside the frame;
`--preview` writes a plain contact sheet instead. Commercial use, no attribution.

  Use `--screen`, not `--preview`. A cropped shoulder or a head cut level with the
  top edge is close to invisible in a 300px thumbnail, and it survives into the
  render where nothing can fix it. On a search for beauty close-ups, 0 of 8
  candidates had the whole subject in frame - which is the real reason those shots
  belong in `photo-panel` rather than in a cutout layout.

**`stock.py --source pixabay`.** Present, tested, and wrong for this brief: asked
for a mature woman's portrait it returns documentary travel photography, and
asked for a clinical still life it returns Christmas wine and a cappuccino. Use
it only if Pexels is down.

## Pipeline

1. **Get a photo** into `src/` (skip for the `type` layout).
2. **`python cutout.py src/x.png --trim --out cut/x.png --contact src/_sheet_x.png`**
   removes the background with a segmentation model and writes a magenta
   contact sheet. Look at the sheet. Always.
3. **Write a spec** - `spec-featured-*.json`, `{ "featured": [ {...} ] }`. Fields:
   `file`, `post_id`, `layout`, `title` (string, or the cards' 3-part array
   where the middle part is highlighted), `alt` (required), and optionally
   `kicker`, `sub`, `photo`, `focus`, `logo`.
4. **`node makefeatured.mjs spec-featured-x.json`** renders `out/<file>.png` at 2x.
5. **`python proof.py`** writes `out/_listing-sheet.png` at listing size and
   measures headline contrast. Exits non-zero if anything fails.
6. **Look at `_listing-sheet.png`.** This is the review, not the full-size render.

## Layouts

| Layout | Use it when |
|---|---|
| `photo-dark` | One clear subject, and you want the tile to stand out in a grid of white cards. The strongest of the set. Needs a cutout. |
| `photo-right` | One clear subject on the light ground. Needs a cutout. |
| `photo-panel` | **The workhorse.** Any photo, uncut, cover-cropped into the right third. Use `focus` to set the crop point. |
| `photo-circle` / `photo-circle-dark` | **ER brands only.** The photo inside a disc with a brand-red ring. `photo_fit: "cover"` (default) is a window onto a photograph and takes a `focus`; `photo_fit: "inscribe"` fits the whole image inside the circle, which is what a cut-out object wants. `frame_fill: "dark"` fills the disc with the brand dark, for a near-black subject like a radiograph. |
| `type` | No photo at all. Available for every post. |
| `type-dark` | `type` on the brand's dark ground. Written for the ER brands, where it is the DEFAULT rather than the fallback: those posts may not show a person, the question in the headline is the subject, and a dark tile stops the scroll in a grid of white cards. |

## When a cutout works, and when it does not

Cutouts work on one obvious subject against a plain ground: a person standing, a
product, a portrait. They fail on scene photography - two people in a treatment
room, a light subject against a light wall - because there is no single salient
object to find. The failure is a floating fragment, typically a head with no
body, and it looks deliberate rather than broken.

Measured on this site's own library: a standing figure and a studio portrait cut
cleanly. A photo of a client reclining under a white towel returned a
disembodied head from both models tried. **That photo belongs in `photo-panel`.**

`cutout.py` refuses three failures rather than leaving them to be spotted:
subject under 5% of the frame (nothing found), over 95% (nothing removed), and
over 55% of the subject's own bounding box transparent (subject full of holes -
the light-on-light case). Coverage alone misses the third, which is why the
shape of the mask is measured too.

## Models

`models/` is not in any backup - re-download if missing.

| Model | Size | Notes |
|---|---|---|
| `isnet-general-use.onnx` | 178 MB | Default. Clearly better than u2net on this site's photos. |
| `u2net.onnx` | 176 MB | Kept as a second opinion. Lost on every test here. |

Both Apache-2.0, fine for client work. BRIA RMBG-1.4 is better again on hair and
is **not free commercially** - do not add it without buying the licence.
Sources: `https://github.com/danielgatis/rembg/releases/download/v0.0.0/<name>.onnx`.
Run directly through onnxruntime; rembg itself is not installed and is not needed.

## Guards

Node, at render time: three-part title arity, a highlight that splits a word,
missing alt or post_id, `undefined`/`null`/`NaN` reaching the canvas, a photo
that failed to decode, text outside the frame, and a headline that still will
not fit at the 56px floor.

Python, after render: WCAG contrast of the headline against the pixels actually
behind it in the shipped PNG, the headline's size once scaled to 400px, and - on
the cutout layouts - whether the subject touches the TOP edge, which means a head
sliced off level with the frame, plus how much of the photo column the subject
actually paints (`--paint`, floor 28%).

Two more found by looking at the ER prototypes, both at render time. A highlighted
phrase that wraps AT A HYPHEN: "ER X-Ray" broke to "ER X-" / "Ray" with two
disjoint underlines, which no spec-level check can see because only the browser
knows where the line broke. And text colliding with the LOGO - the comment above
that guard had claimed it for months while the code only ever measured the frame;
a three-line ER headline pushes the subtitle onto the mark, and an 8px cushion
catches it. `photo_inset` exists for the same review: the cutout layouts bleed the
photo off the right and bottom, which is right for a figure standing on the canvas
and wrong for a rectangular object, where it reads as a clipping error.

All three have already earned themselves. The contrast check caught a sage
highlight at 2.8:1 against the pale ground that looked fine at full size; it is
now a yellow marker behind green text at 13.7:1. The top-edge check caught a
weight-loss photo cropped at the chest, so the figure had no head at all. The
paint check is round 2 of the same weight-loss card - see below.

## Frame the subject waist-up, not head-to-toe

Every cutout layout gives the photo the same 560x630 slot and fits it by HEIGHT.
A full-length standing figure therefore scales down until its body is a narrow
strip in the middle of the slot: the same bounding-box height as a waist-up
portrait, a third of the mass. The tile reads hollow and the headline looks
marooned, which is invisible in the source photo and obvious in the render.

Measured on this set, as the share of the photo column the subject paints:

| Shot | Paint | Verdict |
|---|---|---|
| Waist-up portrait, shoulders to the column edges | 46-53% | approved |
| Still life, mass along the bottom edge | 32% | reads fine |
| Full-length standing figure | 20% | operator rejected it on sight |

`proof.py` refuses under 28%. The fix is never a spacing tweak - it is asking for
a waist-up shot whose shoulders fill the column and which bleeds off the BOTTOM
edge only.

## The halo on the dark layouts

A segmentation matte is soft at the edge, and those soft pixels keep the RGB of
the photograph, which at the subject's edge is mostly studio background. The edge
band on this set averages RGB 177 against a subject core of 140. On the pale
layouts it composites into a pale ground and nobody sees it; on `photo-dark` it
is a visible halo. `cutout.py` now decontaminates that colour by default -
extending the subject's own colour outward UNDER the existing alpha, so the
silhouette keeps the shape the model found. `--keep-fringe` skips it.

That fixes the edge. It does NOT fix loose curly hair, where the background
trapped BETWEEN the strands is marked fully opaque by the mask and so survives
any edge treatment - it renders as a pale cloud around the head that no
defringing reaches. Keying it out by colour was tried and rejected: the studio
ground sits close enough to a grey T-shirt that a global key punches holes in the
clothing, which is the light-on-light failure this tool already knows about. Ask
for smooth, pulled-back hair instead. Both portraits that cut cleanly have it.

## Not built yet

Nothing here uploads or attaches. Still to do, in order:

1. WebP encode (borrow `../towebp.py`, retarget 1200x630).
2. Upload via `upload_media` with the spec's `alt`.
3. Attach as the featured image. `featured_image_id` only exists on
   `draft_create_post`, so an existing post needs `draft_update_postmeta` with
   `_thumbnail_id` - which works but validates nothing. A `draft_set_featured_image`
   tool is the right fix.
4. **Rank Math stores its OG image separately from the featured image.** Setting
   one does not set the other, and a post will show the new graphic in the blog
   listing and the old one on Facebook.

## The circular frame (ER only)

`photo-circle` and `photo-circle-dark`. The ER palette is two colours with every
decorative hue banned, which left those cards with no shape of their own beyond
the type, and a rectangular object photograph inside a rectangular tile made it
worse. A disc is a shape the brand can own without inventing a colour: white or
navy disc, brand-red ring, concentric halo behind it.

It is refused to `iwc`, from image-policy.json's own brand map so there is no
second list to drift. That brand already has a marker, hued corner washes and a
human subject; a disc on top would be one device too many.

**A circle crops harder than a rectangle** - the inscribed circle keeps pi/4 of
its box, about 79%, and it discards the corners, which is exactly where a
cover-crop leaves the edge of an object. So there are two fits and a measured
guard:

- `cover` (default) fills the disc and crops, taking a `focus`. Right for a
  photograph, where the circle is a window into a scene.
- `inscribe` puts the WHOLE image inside the circle. Right for a cut-out object.
  The CSS can only reserve the square that fits any aspect ratio, so the exact
  rectangle for the real aspect is applied in the browser once the image has
  loaded - 18% more area on the 7:5 film in this set - and `circle.mjs` computes
  the same number in Node as a cross-check that the page did what was intended.

The guard reads the photo's pixels back through a canvas (a data: URI is
same-origin, so `getImageData` returns rather than throwing) and measures how much
of the subject lands inside the circle. **It refuses only on an alpha mask**,
where the subject is known exactly. On an uncut photograph the mask is a proxy -
"everything that is not the backdrop colour" - and on a scene that counts context
as subject: the copperhead shot measures 74% because the leaf litter it lies on
spreads wider than the circle, while the snake sits dead centre and whole. So
that case warns and leaves the judgement to the contact sheet. A check that
cannot see the subject must not assert an opinion about it.

## Tests

    node --test                       # from featured/
    node --test-reporter=spec --test  # one line per assertion

33 of them, `node:test` and `node:assert` only. They cover what can be wrong
without LOOKING wrong: the circle geometry, focus parsing, the coverage maths,
the derived emphasis per brand and ground, the ER-only rule, and the depiction
policy for both profiles. They do not cover whether the result is any good - no
assertion knows that, which is what `out/_listing-sheet.png` is for.

Three things the suite has already caught, all of them mine:

- `focus` does NOTHING when the source and the frame share an aspect ratio,
  because cover then overflows on neither axis and there is nothing to slide. The
  first version of that test assumed otherwise and failed; the trap now has a
  test of its own so the next person finds the answer here.
- `(bw - dw) * 0` is `-0`, which fails a strict equality and would serialise into
  the render report as "-0".
- The inscribed SQUARE is the maximum-area rectangle in a circle, so an
  aspect-preserving fit can never beat its area - only the same image letterboxed
  inside it. An assertion claiming otherwise was wrong, and so was the comment
  next to it.

## Depicting people (enforced, not advised)

**The rules invert by brand, so read the profile, not your memory.** On `iwc` a
person is often the right subject and the rules govern HOW they are shown. On the
three ER brands there are no people at all and no body parts: an ER post is a
triage decision, so anyone in the frame is a patient who does not exist or a
clinician who does not work there, and every ER skill says real facility and real
staff only. ER posts get `type`/`type-dark` or an object still life. The ER
profile also refuses distress staging, ambulances and sirens, and any wait-time or
hospital-comparison wording, which all three ER skills list as anti-patterns.

Do not reach for the sites' own media as a way around it: on erofwhiterock the
`uploads/2025/01/*` service images are stock - a smiling nurse at a CT scanner,
red-glow pain overlays - which is exactly what the rules exclude.

`image-policy.json` carries the depiction bans as patterns; `imagepolicy.mjs`
enforces them in two places - on the generation brief, before Chrome opens, and
on the shipped `alt` at render time, which every entry is required to have. A
refusal names the phrase and the reason. Change the picture, not the wording.

The doctrine behind the list is section 24 of the `irvingwellnessclinic-design`
skill: decide whether the post is about a mechanism (illustrate the mechanism, or
use `type`, and no body belongs in the frame) or about the experience of care
(then a person, at parity with every other portrait in the set). Subject variety
is deliberately unconstrained - a different person every time is wanted.

Neither gate is a proof. A stigmatizing photo described by innocent alt text
passes both, and the backstop for that is a human reading `out/_listing-sheet.png`.

## Sourcing photos

The photos used in the first test are stock images already in this site's own
media library. Worth deciding deliberately: on a YMYL medical site, a stock
photo that implies a specific patient or provider is a trust problem. `type`
needs no photograph at all and is the honest option for clinical topics.
