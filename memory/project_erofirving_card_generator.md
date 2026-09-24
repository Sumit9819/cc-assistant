---
name: project_erofirving_card_generator
description: In-body cards for ER of Irving run through the SAME generator as IWC via brands.mjs role tokens; palette is red/navy with yellow+green BANNED, font Montserrat; 3 demo cards rendered 2026-09-07, no post_ids yet
metadata:
  type: project
---

**What changed.** `D:/cc-assistant/tools/card-generator` is no longer
single-brand. `brands.mjs` holds ROLE-named token sets (dark, mid, accent,
on_accent, ground, surface, ink, label, muted, line, hair, track, dot, icon_bg,
on_dark, warn_head, two shadows, three tints) and a spec picks one per card with
`"brand": "erofirving"`. No brand field means `iwc`, so every existing spec is
untouched - proven by re-rendering nine shipped cards at max channel delta 0.
Keep `_baseline/` and re-run that comparison after any layouts.mjs edit.

**Why it could not be a value swap.** The layouts named colours by hue
(`--green`, `--yellow`). ER of Irving bans yellow AND green outright
([[erofirving-design]] skill sections 21-22), and its accent red needs WHITE
text where IWC's accent yellow needs DARK. `brand()` therefore gates
`on_accent` at 4.5:1 against `accent`; a naive swap would have shipped navy on
red at about 2:1.

**ER of Irving facts, measured not assumed.** Font **Montserrat** from the live
site's computed styles (the Elementor Kit CSS custom properties came back empty).
Logo available is only the 512x512 wordless red/navy cross site icon, whose
filename on Irving is `cropped-LufkinLogoNewHorizontalNew.png` - clone residue,
since the skill says Irving is the ORIGINAL. **A horizontal wordmark is still
needed from the operator.**

**`urgentSide` option.** On an emergency brand the accent red signals URGENCY,
not "wrong answer". The first checklist render put "Urgent care is enough" in
alarm red and "Come to the ER" in calm navy. `urgentSide: "good"` moves the
emphasis to the left panel so the ER column is red and still reads first.
Bullets follow the same side as the header.

**Status 2026-09-07.** Operator approved the three demo cards. First REAL batch
is `spec-erofirving-1.json`, four cards against real post ids: 2780 (ER-vs-urgent
symptoms checklist, and a call-911 list), 3796 (heat-illness first aid checklist),
3886 (four stroke mistakes). Every item is lifted from the target post's own body,
so no card asserts anything its article does not. **Nothing uploaded yet** - the
bridge is still behind the IP challenge.

**Deploy is staged and waiting on the bridge only.** WebP encoded (26-33KB each,
`towebp.py` works unchanged), and `mkpatch-erof.py` turns an upload manifest into
`draft_patch_post_content` payloads. It slices each anchor heading out of the
FETCHED body rather than retyping it, because `search` must match byte for byte
and exactly once: post 3886's anchor is `<h2><strong>Stroke Risk Factors...`,
which no hand-typed string would have matched. Remaining steps when the IP
clears: `upload_media` x4 -> write the manifest -> `mkpatch-erof.py` -> SIMULATE
-> `draft_patch_post_content` per post.

**The site has 45 published posts**, inventory cached at `tools/erof-posts.json`,
read through `tools/wp-read-via-browser.mjs` because the bridge was down. Rich
seam for these layouts: it is almost entirely triage-decision content.

**Duplication check matters here.** 2780 and 3978 already carry comparison TABLES
in their bodies, so a compare card would repeat them. 2780's table compares
FACILITY attributes (hours, staffing, cost), which is why a SYMPTOM checklist
complements it instead; 3978's table already does muscular-vs-lung-vs-heart, so
its card should be the red-flags section, not a comparison. Always read the body
and list its tables before choosing a layout.

**How to apply.** Content rules for this site are stricter than IWC's: no
hospital-ED comparison of any kind ([[feedback_no_hospital_comparison]]), Irving
named first, sentences under 25 words, cited stats from the skill's allow-list
only. ER vs urgent care vs primary care IS an allowed comparison. Related:
[[project_iwc_card_generator]], [[reference_tool_locations]].

**Coverage complete 2026-09-07.** All 45 published posts decided: 83 cards on 41
posts, spread 1 to 4 per post (eleven posts at 1, twenty-two at 2, four at 3,
four at 4). Four posts get none on purpose - 3116 skeletal traction, 3162 muscle
strain, 3365 stress fracture, 3850 workplace injuries - because their content is
either an inpatient procedure, generic listicle tips, or entirely prose. Tools
added: `cardneed.py` (scores every h2), `readcands.py` (prints candidate sections
to read), `sheet.py` (contact sheet at reading width). See
[[feedback_card_count_follows_the_post]].

**ON-ACCENT TOKEN (fixed 2026-09-07).** Text sitting on the red accent disc or
pill must use `--on-accent`, never `--dark`. Three rules had it wrong - steps
`.num`, stat `.gloss`, numbered `.num` - which put navy #041562 on red #DA1212 at
roughly 2:1. The operator spotted it on the step badges. IWC is immune to the
change because its `dark` and `on_accent` are both #003017, and that was VERIFIED
pixel-identical (max channel delta 0) on all 9 baseline cards rather than
assumed. Fixing a live card means new attachments plus a URL-swap patch, since
`upload_media` has no overwrite: the filename collides and WP appends `-1`.

**ICON-IN-DISC RATIO, 0.65 (set 2026-09-09).** The `steps` and `icons` layouts
hardcoded their icon at ~47% of the disc diameter, leaving 30 to 54px of empty
ring around every glyph. Operator: "the image should cover the whole circle
frame". `ICON_IN_DISC = 0.65` in `layouts.mjs` now derives both sizes from the
disc, and 0.65 is not a taste call: the `list` layout has always drawn a 30px
icon in a 46px circle and has never drawn a complaint across 58 cards.

- **Ceiling is ~0.70.** The largest square inscribed in a circle is
  diameter/sqrt(2) = 70.7% of it, so past that an icon reaching its 24-unit
  viewBox edge clips the ring. Rendered 0.75 to check: the phone and clipboard
  glyphs read cramped against the ring. Stroke width is in viewBox units, so a
  bigger icon keeps its relative weight and does NOT need a stroke change.
- **This DELIBERATELY breaks the IWC pixel-identity rule above.** IWC's steps
  and icons cards are still drawn at 47%, so the two sites now differ on purpose.
  Do not "restore" it.
- **A bigger icon can grow the frame.** 3803's swallowed-battery card went 630 ->
  732px, because the two-pass sizing measures real content extent. So rewrite the
  WHOLE figure on a swap, never just the `src`, or the declared height lies.
- 17 live cards were re-rendered and swapped (12 steps, 5 icons, 16 posts,
  pendings 1236-1251). Uploading under the same stem collides and WP appends
  `-1`, so 2 of 17 came back as `-r2-1.webp`: always use the `url` from the
  upload response verbatim. `simpatch.py` then cannot find `out/<served name>`
  and reports "missing", which is the CHECK being confused by the rename, not a
  bad patch - stage a copy under the served name so the dimension check is real
  instead of skipped.

**SIGNED OFF 2026-09-07.** 83 cards live on 41 of 45 posts, verified against the
rendered html and not just the DB. Two lessons worth carrying:

- `render_probe` returns a STRUCTURED report (schema, links, images, headings),
  NOT raw markup. Grepping its response for `cc-card` matches nothing and proves
  nothing. Check `images.total` / `missing_alt`, or read `content.rendered`.
- The front end serves cards through a lazy-load optimizer: the real URL sits in
  `data-src` with a `<noscript>` fallback, and `src` is a 1x1 base64 GIF. A check
  that greps `src="` for the filename will report a false negative.

11 orphan card attachments are left in place on purpose. See the on-accent note
above for why the superseded ones cannot be deleted: `delete_media` scans for
references with `LIKE %stem%`, and the kept `-1` filename contains the orphan's
stem, so it over-reports and refuses. That is the safe direction.


**Batch 14 (2026-09-23).** 6 cards on the three new decision posts (4772 flu, 4773 BP,
4774 vomiting), attachments 4786-4791, pendings #1289-1291. Two layout defects found:
- `alert` layout's two-pass sizing blows the frame up to ~1540px (the `.card` is
  absolutely positioned with `inset:0`, so the measured extent is wrong), and its
  small red kicker/footer on navy is poor contrast. Use `stat` for a threshold card.
- `icons` with 5 items (wrapRows) renders LEFT-aligned with the right half empty on
  erofirving. Use `list` for 5-6 items until fixed. Neither defect is fixed in code.
- Uploads can now go direct to `cc-assistant/v1/assets/upload` via urllib + app password
  (no SG challenge); the response keys are not `attachment_id`/`url`, so read the ids
  back from `wp/v2/media?search=<stem>` and assert exactly one hit per card.
