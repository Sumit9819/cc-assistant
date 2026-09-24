---
name: feedback_card_count_follows_the_post
description: In-body card count is derived from the post, never a quota; a heading earns a card on EITHER of two axes (reader decision, or measured GSC impressions) and both gates still apply
metadata:
  type: feedback
---

Card count is an OUTPUT of reading the post, not an input. Operator corrected me
twice in one session: first "whats the point of having only one in-body image per
post?", then, when I answered with three per post, "this is not about just make 3
or 4 creative but, how much actually needed for the specific blog".

**Why:** a quota makes the last card filler. Filler cards restate prose that was
already clear, duplicate a table the body already carries, or promote an
operational claim that is not a reader decision at all. Each one costs page
weight and dilutes the cards that do carry a decision. A short single-decision
post is finished at one card; a multi-decision triage post can justify four.

**How to apply.** A card earns its place only if all three hold:

1. It answers a **distinct decision** the reader makes (what do I do now / how
   bad is this / which option / how long do I have).
2. That decision's section has **no existing visual** - no comparison table, no
   already-graphic list. Check the fetched body, not the outline.
3. The content is **enumerable**: a threshold, an ordered sequence, a comparison,
   or a set of signs. Narrative background and prevention advice do NOT get
   clearer as a graphic.

Fails the test, drop it: prevention/background sections (household hazards),
self-anchored operational stats (door-to-answer minutes - that is marketing, not
triage), and a second card of the same layout covering the same axis as the first.
Posts that already ship a comparison table need FEWER cards, not the same number.

Related: [[project_erofirving_card_generator]], [[feedback_pre_post_duplication_check]],
[[feedback_blog_data_not_opinion]].

**The tool.** `tools/card-generator/cardneed.py` scores every h2 in every post
against the three tests and writes `cardneed.json` with per-section detail. It is
a SHORTLIST, not a verdict: read the candidate sections before rendering.

Two refinements it needed, both worth keeping:

- A decorative stock photo is NOT an existing answer. Only a table or an existing
  cc-card blocks a new card. Counting any `<img>` scored post 2780 at zero cards
  needed purely because its sections carry stock photography.
- A question mark does not make a decision. "What is X", "What causes X" and
  "Can you prevent X" are background; they were inflating a symptoms/causes
  listicle to 7 candidates. Rescue the heading only if it also promises signs,
  warnings, thresholds or a when-to.

**Measured on erofirving 2026-09-07:** 45 posts warrant 81 cards, spread 0 to 4
per post. The quota era both over- and under-shot: 3978/3882/3808/2780 came out
one card too many, while 3886 was three short and 3921 two short. A flat number
is wrong in both directions at once.

**The workflow.** `cardneed.py` scores and shortlists, `readcands.py` prints each
candidate section's actual content so it can be READ, `sheet.py` builds a contact
sheet at the width the card is actually read at. The read is not a formality: on
batch 4 it cut two of eleven candidates that the score had passed. 3796 lost its
card because the post's comparison chart already answered four of the six items,
which the score cannot see because the chart sits in a different section. 3921
lost one because "What Pediatric-Friendly Emergency Care Looks Like" describes the
facility rather than a decision the reader makes.



## 2026-09-08: the rule gained a SECOND axis

Operator: "there were a lot of heading that actually needed images ... images
ranks too, and the rankabale heading should get images too ... because they rank
it would be good for website". The count-follows-the-post principle above is
unchanged. What changed is what makes a heading eligible.

**Axis A - reader decision.** The original test, exactly as written above.

**Axis B - ranking evidence.** The heading already earns impressions in Search.
A heading qualifies on EITHER axis. Both gates (no existing answer, enumerable)
still apply to both.

**Why:** Axis A alone threw away every definitional and causal heading, and on
this site those carry the volume. Measured, not asserted: post 3116 was given
ZERO cards as "an inpatient procedure with no reader decision" while four of its
headings rank, and post 3365's "What Is a Stress Fracture?" is at position 4.0.
Axis B is only ever run against a real `--gsc` pull of page queries; without one
cardneed.py says so rather than scoring on one axis in silence.

**What each change was actually worth** on erofirving, past the 83 live cards:

| rule | candidates left |
|---|---|
| Axis A, original detector | 22 |
| Axis A, h3 subheads count as enumerable | 34 |
| both axes | 44 |

So the ranking axis added 10 and fixing the enumerable detector added 12. Worth
separating: most of what looked like "the rule is too narrow" was a detector bug,
not the rule.

**Four traps in measuring Axis B**, all of which produced a wrong number first:

1. **Credit each query to ONE heading, the best match.** Crediting every heading
   that overlaps counted post 3977's 4,300-impression query three times and would
   have argued for three cards to serve one query.
2. **A table IS an existing answer.** The first pass looked only for
   `<figure|img>` and reported post 3116's "Skeletal Traction vs Skin Traction"
   as the site's single biggest image gap at 4,156 impressions. That section
   carries a comparison table. It needs nothing.
3. **The first h2 usually restates the title** and vacuums up page-level queries.
   Post 3103 showed 2,797 impressions on a 98-word intro. Zero Axis B credit for
   a heading of 4+ content words that is a subset of the title - but keep the
   length floor, or short definitional headings get eaten too.
4. **Filter other-market intent.** `nhs`, `uk` and friends: post 3813's apparent
   gap was UK traffic, worth nothing to a Texas ER.

**The enumerable gate stays, and it stays binding on Axis B.** Post 3924's "What
is a Peritonsillar Abscess?" carries 2,483 impressions over 119 words of
unstructured prose. There is nothing in that section to draw a card FROM, and
building one means inventing content. High impressions on narrative prose is a
signal to restructure the prose, not to draw a graphic.

**The alt-text rewrite was MY idea and it did not survive measurement.** I
claimed alt text was "descriptive prose rather than query-shaped" and so the
bigger lever than card count. `altaudit.py` checks every live card's alt text
against the queries its own section earns. Result: mean cover 57%, and the
residual misses are function words ("long", "take", "get") plus qualifiers that
belong to OTHER sections. The alt text is already doing its job. **Do not
rewrite the live alt text**, and treat "this looks weak" as a hypothesis to
measure, not a finding to act on.

Three things that audit got wrong before it got it right, all worth knowing:

- **Never blend a scoped and an unscoped population.** Cards whose section owns
  no query were being scored against the POST's top queries, which describe
  other sections. That artefact produced a 32% mean and would have justified
  rewriting 58 live cards. Post 3886's F.A.S.T. card was marked 25% for missing
  "feel, like, woman" from "what does a stroke feel like in a woman".
- **An exact-match tokenizer measures the matcher, not the text.** Post 3930's
  alt reads "the window for getting a deep cut stitched" and scored ZERO on "how
  long can you wait to get stitches", because stitched != stitches. Post 3201
  lost a point for writing "Ten" where the query says "10". Light stemming and a
  number map moved the mean from 44% to 57%.
- Strip an ending only when four characters survive, or "irving" becomes "irv"
  and reports itself missing.

**One pattern worth carrying into NEW cards** (free there, not worth retrofitting):
where a section's queries name the individual items, the alt text should NAME
them rather than summarise. Post 3867's card says "Five symptoms of carbon
monoxide poisoning that are easily mistaken for the flu" while the queries ask
for headache, dizziness and nausea by name. Enumerating is better retrieval AND
better accessibility.

**Filenames: dropped for live cards.** Changing one means a re-upload, patching
every reference and orphaning the old attachment, three operations per card for
a weak ranking signal. Name new cards well and leave the live ones alone.

## Gate 1 has a blind spot one section wide: split comparisons

A post often writes one prose section per alternative and THEN lays the
alternatives out in a table. Every prose section scores as its own candidate,
because none of them holds a visual, and carding them separately splits an
answer the table already gives whole.

Post 3978 was the live case: "Muscular Chest Pain" and "Heart-Related Chest
Pain" scored on 759 and 1,276 impressions, and "4 Key Differences That Help You
Decide" tables Muscular / Lung / Heart against four questions. Two one-sided
cards would have restated the post's own table. This is the same defect the
3796 read caught by hand.

`cardneed.py` now reports it as `answered_elsewhere`, matched on the table's
COLUMN HEADERS, and only when TWO OR MORE sections are named by different
columns of one table. The first attempt matched topic words instead and scored
four false positives to one miss: topic overlap only detects "same subject",
which every section of a post shares by construction. Header matching finds 5
true groups site-wide (3796, 3978, 2780, 3924) and no false ones.

It is a WARNING, not a gate. A table elsewhere sometimes complements a card and
sometimes replaces it; only reading both tells you which.

## Batch 10, queued 2026-09-08

Pendings 1160 (post 3365, 4 cards), 1161 (3162, 2), 1162 (3858, 1). 3365 and
3162 both had ZERO cards under the decision-only rule while ranking 4.0 on
"stress fracture shin" and 9.8 to 10.5 on "muscle strain treatment", which is
striking distance. Every patch simulated first with `simpatch.py` (unique
anchor, figure growth, no nesting, no stacking, declared dimensions match the
encoded WebP, alt present).

Post 3365 carries a PRE-EXISTING `paragraph_length` lint failure. `introduced`
was empty, so it is not from this batch, but it is worth its own fix.

Still deferred, and both need an operator decision rather than more tooling:

- **3921** "Understanding the 103 Rule" at 3,020 impressions, but the post
  already has 4 cards including an age-and-temperature thresholds card. A second
  thresholds card is the "same layout, same axis" failure this file warns about.
- **3116** wants 4 cards on skeletal traction. It ranks, but a freestanding ER
  does not perform skeletal traction, so the cards would buy rankings for
  traffic that cannot convert. Worth asking before building.
