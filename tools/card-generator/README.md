# In-body card generator (irvingwellnessclinic.com)

Spec-driven infographic cards for blog posts, 1200px wide by at least 630px tall,
rendered with Playwright from
HTML layouts, encoded to WebP, uploaded through the site's REST API, and swapped into
post bodies through the cc-assistant pending queue. Built September 2026 to replace the
Canva/design-team cards, whose defects were all manual-assembly slips.

Rescued from a Claude Code session scratchpad on 2026-09-06. That folder can be cleaned
up at any time; this copy is the one that counts.

## Pipeline

0. `python cardneed.py --bodies <bodies>.json --gsc ../_gq`: decide WHICH headings
   get a card, before writing any spec. A heading qualifies on either axis - it
   answers a reader decision, or it already earns GSC impressions - and must also
   clear both gates: no table or cc-card already answering it, and content that is
   actually enumerable. Writes `cardneed-<stem>.json` with per-section detail.
   Then `python readcands.py --bodies <bodies>.json <post_id...>` prints each
   candidate's real content and the queries behind it, because the score is a
   shortlist and every candidate gets READ before it is drawn. The count is an
   output: there is no per-post quota. See
   `memory/feedback_card_count_follows_the_post.md`.
1. `spec-*.json`: `{ "cards": [ { file, post_id, layout, subject, heading, title[3],
   logo, alt, items|rows|steps... } ] }`. One card per object. `title` is a 3-part
   array, middle part highlighted. Layouts (see `layouts.mjs`, `BODIES`): icons,
   compare, steps, stat, quote, checklist, timeline, alert, numbered, list.
2. `python validate_specs.py`: content-shape audit (title arity, alt present, empty
   cells/labels, row arity). Run before rendering.
3. `python check_spelling.py`: British to US, full word list plus a correct-US
   allowlist. Must exit 0. `fix_spelling.py` rewrites with literal pairs only.
4. `node make.mjs spec-X.json`: renders `out/<file>.png` at 2x. Guards refuse
   duplicate repeatable text, `undefined`/`null`/`NaN` in rendered text, and a
   highlight boundary that splits a word. Width is fixed at 1200; height starts at
   the 630px floor and GROWS to fit the content, so a long comparison becomes a
   taller card instead of one with crushed rows (see the geometry note at the top of
   `layouts.mjs`). A spec may set `height`, which is treated as a floor. Playwright
   is borrowed from
   `~/.cc-assistant/wcag` (installed by the plugin's wcag_sweep) or
   `D:/faceless-studio/motion`.
5. `python towebp.py spec-X.json`: LANCZOS downsample to 1x WebP. The target size is
   read off each PNG, not hardcoded, because heights vary per card.
6. `python upload.py spec-X.json`: uploads to the media library with alt, writes
   `uploaded-X.json`. Credentials come from `D:\cc-assistant\.mcp.json`; the script
   refuses to run against any host other than irvingwellnessclinic.
7. `python mkpatch.py` (append a figure after a heading) or `python mkswap.py`
   (replace an existing design-team `<img>` in place, carrying its width/height/alt)
   emits `draft_patch_post_content` patch arrays. Search strings are lifted verbatim
   from `bodies/<post_id>.html` (fetched by `fetch_posts.py`), never retyped.
8. SIMULATE the patch set against the real body before queueing (see the 2026-09-04
   nested-figure incident), then queue through the plugin and approve in the inbox.

## Rules learned the hard way

- Look at every rendered card before uploading, not a sample. Twenty cards reading
  "undefined" shipped past geometry-only guards.
- Bitmap copy cannot be fixed by a text edit; a wrong word costs a re-render, a
  re-upload and a body swap.
- A stem rewrite must pin what follows it: `metabolis` to `metaboliz` turned
  `metabolism` into `metabolizm` on 12 live cards.
- One `post_content` pending per post; two collide and the first is hidden.
- Compare output mtimes to spec mtimes before claiming a re-render happened.

## State files

`work_state.json` (which card is live on which post, with old attachment ids),
`superseded_generated_ids.json` and `v3_uploads.json` (deletion backlog, blocked until
the swap pendings are approved), `uploaded-*.json` (per-batch upload manifests),
`patches-*.json` / `swaps-*.json` (queued patch payloads), `out/` (914 rendered files,
the source of what is live), `bodies/` (fetched post bodies at the time of patching).

## Brands (added 2026-09-07)

The layouts were written for irvingwellnessclinic, with its palette spread across
507 lines as CSS variables named after hues (`--green`, `--yellow`) plus one-off
hex literals for tints, borders and hairlines. `brands.mjs` replaces that with
ROLE-named tokens, so a second brand is a token set rather than a fork.

A spec selects one per card: `"brand": "erofirving"`. Omitting the field means
`iwc`, so **every existing spec renders exactly as before** - verified by
re-rendering nine shipped cards and comparing pixel by pixel, max channel delta 0.
Re-run that check after touching layouts.mjs:

```
python -c "import pathlib,numpy as np; from PIL import Image; print([f.name for f in pathlib.Path('_baseline').glob('*.png')  if np.abs(np.asarray(Image.open(f).convert('RGB'),dtype=np.int16)  - np.asarray(Image.open(pathlib.Path('out')/f.name).convert('RGB'),dtype=np.int16)).max()>0])"
```

`brand()` refuses a token set that is incomplete, that uses one of the hexes the
erofirving skill bans, or whose `on_accent` fails 4.5:1 against `accent`. That
last gate is the point of the exercise: IWC's accent is a bright yellow needing
DARK text, ER of Irving's is a saturated red needing WHITE, and a plain value
swap would have shipped navy-on-red at about 2:1.

### ER of Irving

Palette is fixed by the `erofirving-design` skill sections 21 and 22: red
`#DA1212` on navy `#11468F` / `#041562`, with yellow, amber, green, pink, teal,
purple, orange and brown all banned. That leaves no decorative hues, so the three
soft background shapes are `#F4F4F4` greys. For an emergency brand that is the
right answer anyway - a pastel wash reads as a consumer wellness app.

Font is **Montserrat**, measured from the live site's computed styles (the
Elementor Kit custom properties came back empty, so the browser was the only
ground truth). Logo is the 512x512 site icon, a wordless red/navy cross; its
filename on the Irving site is `cropped-LufkinLogoNewHorizontalNew.png`, which is
clone residue worth flagging. **A horizontal wordmark is still wanted.**

`urgentSide: "good"` flips which checklist panel carries the emphasis colour.
On an emergency brand the accent red is an URGENCY signal, not a "wrong answer"
signal, so a triage card wants the ER column red and the urgent-care column calm
while still reading ER first from the left. Without it the first render said
"Urgent care is enough" in alarm red and "Come to the ER" in calm navy.

### Adding another brand

1. Add a token set to `brands.mjs` (all 18 roles, three tints).
2. Drop the logo files into `brand/`, keyed in the brand's `logo` map.
3. `node -e "import('./brands.mjs').then(m=>m.brand('slug'))"` to validate.
4. Render, then re-run the IWC identity check above.
