---
name: feedback_heading_echo_banners_lose_to_cards
description: When a decorative banner that just repeats its heading sits above an in-body card, remove the BANNER and keep the card; operator decided 2026-09-08 for erofirving, 20 banners on 7 posts
metadata:
  type: feedback
---

**A stacked pair of images is resolved by deleting the older decorative
banner, not our information card.** Operator decision, 2026-09-08, after
spotting the stacking on post 3201.

Because cards now sit directly below their section heading
([[feedback_card_sits_inside_its_own_section]]), any decorative image already
under that heading ends up immediately above the card. On erofirving those
older images turned out to be **heading-echo banners**: a stock photo with the
section heading burned into a red caption bar, so the reader met the same words
three times, as the heading, as a picture of the heading, then as the card's
own title.

## How to recognise one

All three signals agree, and it takes one glance at the file to confirm:

- **alt text is the heading verbatim** (`alt="What Happens If You Are Dehydrated"`
  under `<h2>What Happens If You Are Dehydrated?</h2>`)
- **filename is the heading slugified**
  (`What-Happens-If-You-Are-Dehydrated-1024x551.webp`)
- opening the image shows a stock photo with that heading in a caption bar

Removing one loses nothing: the words stay on the page in the `<h2>`, which is
untouched. The measured banner was 154KB at 1024x551; the cards are 20 to 32KB
at 1200px.

**A real photograph is NOT a banner, but it still must not stack.** On
2026-09-08 post 2780's two photo-above-card pairs were left in place because the
photos had their own alt text. On 2026-09-23 the operator looked at the live
post and said "I see multiple images in one place... fix it". So the rule is now:
**no two images back to back, ever.** A banner is deleted; a genuine photo is
MOVED to a section that has no image (directly below that heading), never
deleted. Done on 2780 as pending #1266. A live re-scan of all 98 erofirving
posts/pages that day found 2780 was the only remaining stack.

## Scope chosen

Site-wide there were 24 banners on 9 posts, 13 of them stacked. The operator
chose **every banner on the affected posts**, not just the stacked ones: 20
banners on 3081, 3097, 3103, 3116, 3162, 3201, 3365. Reason: removing only the
stacked ones leaves a post with a banner under one heading and none under the
next two, which reads as inconsistent. 3091 (3 banners) and 2780 (1) were left
alone as no stacking was reported there.

After this, **every remaining inline image on those 7 posts is a card.**

## Two traps

**Mask the cards before counting banners.** My first audit reported 61 banners
on 28 posts. Wrong by 2.5x: the image regex alternated `<figure>|<img>`, so it
matched the `<img>` INSIDE each `cc-card` figure, and a card's alt restates its
section, which clears any heading-overlap test. Blank out
`<figure class="cc-card">...</figure>` first, then scan. The real number was 24
on 9 posts. Related: [[feedback_dom_is_ground_truth_not_parsers]].

**`image_preservation` WILL fail, and that is correct.** The plugin counts
inline `<img>` before and after and fails on any drop, because
`class-pre-publish.php` wants the reviewer to see image loss before approving
rather than find it live. On a deliberate removal this is the guard working, not
an artifact. Do not wave it through the way the `paragraph_length`
classification artifact gets explained away: hand the operator the per-post
before/after counts so the number they see in the queue is one they can check.
This was the only lint failure in the whole run that reflected the actual
change; everything else was pre-existing.

Removing the reference from `post_content` does **not** delete the attachment.
Every file stays in the media library at the same URL, so the change is
reversible with a forward patch. Never reach for `delete_media` here.

**The sister sites were cloned from erofirving**, so eroflufkin and
erofwhiterock likely carry the same banner template. Expect this decision to
apply there too.

Related: [[feedback_card_sits_inside_its_own_section]],
[[feedback_no_blog_images_on_service_pages]],
[[feedback_layout_change_approval]],
[[feedback_reject_does_not_undo_an_applied_pending]].
