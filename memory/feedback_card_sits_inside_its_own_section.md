---
name: feedback_card_sits_inside_its_own_section
description: Every in-body card goes DIRECTLY BELOW the heading of its section, never above a heading and never after the section paragraph; operator stated this after I guessed the rule wrong three times
metadata:
  type: feedback
---

**Every card goes directly BELOW the heading of the section it illustrates.**
Not after that section's paragraph. Never above a heading.

Operator, verbatim, after three wrong guesses from me:

> "no no. all the images should be below the heading... and not above the
> headings. If there is heading and paragraph, dont put it after paragraphs but
> after heading. because it still looks bad when putting it after paragraphs..."

```
<h2>Heading</h2>
<figure class="cc-card">CARD</figure>     <- here
That section's prose...
```

**Where a stock photo already sits under the heading, the card goes after that
photo**, not above it. The objection on post 3162 was specifically that our card
had been placed above their existing one. Those sections end up with two images
together. That question is now SETTLED for the heading-echo banners: the
operator chose on 2026-09-08 to remove the banner and keep the card, 20 of
them across 7 posts. See [[feedback_heading_echo_banners_lose_to_cards]]. A
genuine photograph still stays.

## Why this is written from the operator's words and not from the data

I derived this rule three times and was wrong three times. Each time the
reasoning was plausible and each time it cost a review cycle:

1. **"After the section's opening paragraph."** Broke because the prose in these
   posts is not wrapped in `<p>` at all, so the code fell back to "right after
   the heading" and four cards stacked on stock photos.
2. **"Never above any heading, then never above an h2."** I invented an h3
   exemption to make broken placement pass its own check, and wrote in the
   comment that flagging h3s "fired on a card that had just been placed
   correctly". It had not been.
3. **"A card must FOLLOW the content it restates."** This one was properly
   measured - `neighbours.py` showed 67 of the 83 approved cards sitting
   directly after the bulleted list they visualise, all 83 after their content.
   The pattern was real and the conclusion was still wrong: the operator wants
   them BELOW the heading, and the 83 approved cards simply had not been looked
   at closely yet.

**The lesson is about the method, not the rule.** A strong pattern in what a
client has previously accepted is evidence about the past, not a statement of
their preference. When placement, layout or anything else visual is in
question, ASK for the rule in one sentence instead of inferring it from an
approved corpus. Three round trips of live content edits is a bad way to
discover a preference the operator could have stated in one line.

## Implementation

`insert_anchor(html, section_text)` in `mkpatch-erof.py` returns the section's
heading plus any image already directly beneath it, and the figure is appended
to that. There is no AFTER dict, no end-of-section fallback and no
opening-paragraph logic; all three were mine and all three are gone.

`replace_placement.py` re-places an already-live batch: removals and inserts in
ONE pending per post so a post is never half-moved, anchors computed against the
post-removal body.

`simpatch.py` checks that every card sits within 40 characters of the heading
above it, ignoring any image in between, and fails a patch set that increases
the number of misplaced cards. Its adjacency check allows exactly one kind of
new pair - an existing photo under a heading followed by our card - because the
placement rule requires it; every other new pair still fails.

## The scale, measured

90 live cards on erofirving:

- **6 already sit directly below their heading** (the batch-10 cards, once
  pendings 1170 to 1172 apply)
- **84 sit after the section prose, above the next heading** - the pre-2026-09-08
  convention, spread across 40 posts
- 6 of those 84 would land under a stock photo already below the heading

So the stated rule meant the ENTIRE existing set was misplaced. The operator
confirmed by showing a screenshot of post 3858 with two cards still after the
prose: "The issues is still not fixed". MIGRATED 2026-09-08 as pendings 1173 to
1212, one per post, 40 posts, 164 patches. Verified by simulation before
queueing: 89 of 90 cards land directly below a heading.

The one exception is post 3979, whose card sits in the intro above the FIRST h2,
so there is no heading above it to sit under. Moving it under "1. Stroke
Symptoms" would attach a whole-post overview card to one symptom section, so it
was left alone and reported.

`migrate_placement.py` does this from the LIVE BODY with no per-card config:
under the old convention every card was inserted before the next h2, so the h2
enclosing a card IS its section. It targets the enclosing H2 and not the nearest
heading on purpose - a card at the end of a section with subheadings would
otherwise attach to the last h3 when what it summarises is the whole section.

## Traps that cost time in this episode

- **The prose is NOT in `<p>` tags.** wpautop. Find prose as text between block
  tags.
- **Every adjacency guard tested for `<figure`** and the stock photos are bare
  `<img>`, so three guards reported clean while four cards shipped stacked. A
  guard that only recognises the markup this pipeline emits cannot see a
  collision with markup someone else emitted.
- **Refetch immediately before building patches.** A snapshot taken while a
  pending was queued went stale the moment it was approved and `patch_no_match`
  rejected the set.
- **Expected figure count must be derived from the patch set**, never assumed
  from the kind of batch.
- **0x08 corruption, second occurrence.** A shell-driven edit turned three `\b`
  word boundaries into literal backspace bytes; `ast.parse` accepts them and
  `re.search(r"<h2" + chr(8), ...)` matches nothing, so the section-end search
  silently returned None and the anchor would have swallowed the rest of the
  post. Use `[^a-z]` in any regex written by a script and grep for chr(8)
  after. See [[reference_heredoc_backslash_corruption]].

Related: [[feedback_card_count_follows_the_post]],
[[feedback_reject_does_not_undo_an_applied_pending]],
[[feedback_layout_change_approval]], [[project_iwc_card_generator]].


## Two false alarms in the migration, both worth knowing

**The dimension check must only cover figures a patch set AUTHORS.** A relocated
figure carries its existing width/height, which already match the live file. My
local `out/*.webp` had been re-rendered at the new 630px floor that day while the
live attachments were still 628, so checking relocated figures reported 14
dimension failures and 7 missing files on a patch set that changes no image at
all. `simpatch.py` now skips any figure that also appears in a patch's `search`.

**`introduced` can list a check that is also `pre_existing`.** Pendings 1184
(post 3813) and 1203 (post 3924) reported `introduced: ["paragraph_length"]`
while listing the same check under pre-existing. The flagged snippets were those
posts' own 7 and 8 sentence intro paragraphs. Verified rather than assumed: the
set of paragraphs is byte-identical before and after the patch on both posts, 37
and 35 paragraphs, zero new, longest unchanged. A patch that only moves figures
cannot lengthen a paragraph. Treat that combination as a classification
artifact, but prove it per case by diffing the paragraph sets.
