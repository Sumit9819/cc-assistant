---
name: feedback_er_featured_images_use_medical_icons
description: ER featured images = REAL PHOTOS that show the topic (people allowed), e.g. Pexels; operator rejected object still lifes ("sofa for dizziness") AND Healthicons pictograms ("looks so bad") on 2026-09-24
metadata:
  type: feedback
---

**Rule (operator, 2026-09-24, after three rounds):** featured/OG images on the ER sites use a
**real photo that visibly shows the symptom or care moment**: a woman holding her head for
dizziness, a person reading a thermometer for flu, a cuff on an arm for blood pressure.
**Layout (operator, 2026-09-24, later the same day): `photo-panel` + `"fade": "soft"` on ALL THREE
ER sites** ("these are more engaging than the current ones"). The navy `photo-frame-dark` disc
is retired. Irving/Lufkin logo = `mark` (square cross) at width 84; WR = wordmark at ~170.

**Why:**
- Round 1, AI object still lifes: an empty armchair for dizziness and crackers for vomiting did
  not name the topic. "Why dizziness have sofa as an image? few other felt so bad".
- Round 2, Healthicons pictograms in a white disc: "Oh no this looks so bad... We actually need
  images... either AI image or image from web".
- Round 3, Pexels photos with people: queued as #1308-1312 for 4772/4773/4774/4792/4802
  (attachments 4811/4812/4813/4814/4810).

**How to apply:**
- `python stock.py --search "<topic>" --orientation landscape --preview`, READ the contact
  sheet, pick an index, then rerun with `--index N`. Prefer a clear subject on a plain
  background; avoid shirtless, graphic or staged-distress shots; keep sensitive topics tasteful
  (testicle pain = clothed man with a clinician).
- **Downscale sources to 1400px before rendering.** Pexels originals are 6000px/20MB and
  makefeatured's circle guard runs out of Node heap (2GB OOM) on them.
- image-policy.json ER profile: the people rule is WARN since 2026-09-24 by explicit operator
  approval. The body-part rules were also moved to warn in the same change. Still REFUSED:
  presenting anyone as our patient/provider/staff, distress words, ambulance, hospital or
  wait-time comparisons. Alt text must describe the photo honestly ("a woman holding her
  head"), never "our patient".
- Two node tests in featured/ still assert the old no-people rule and now fail. They were left
  unchanged because updating safety tests needs operator sign-off.
- Always show a preview sheet before queueing. Use new filenames for replacements.
Related: [[project_iwc_featured_generator]].

**White Rock house style (2026-09-24):** WR's existing featured images are a full-bleed photo
fading to white on the left, navy and red headline, and the WR wordmark. Use `photo-panel` with
**`"fade": "soft"`** and logo width ~170 (the wide WR wordmark is unreadable at 60). The
default panel fade (560px, linear 0-26%) left a HARD SEAM mid-card that the operator rejected
("the center has hard fade"). Soft = 760px panel with an eased mask to 62%, and headline
contrast still passes. Spanish twins get a Spanish headline and Spanish alt.
First set: 5525 -> 5563, ES 5424 -> 5564, ES 5438 -> 5565 (#1841-1843).
- **Subject must sit in the CLEAR zone** (right ~40% of the card with soft fade). If the photo's
  subject is left-of-centre, mirror it (`ImageOps.mirror`, only when the photo has no text or
  logos) and set focus `0% <y>`. The operator caught 5424's woman sitting "more on the fading
  side than the clear side". Check this on every photo-panel render before queueing.

**Irving restyle (2026-09-24):** our 6 Irving images redone in photo-panel soft fade, #1313-1318
(4410->4815, 4802->4816, 4772->4817, 4773->4818, 4774->4819, 4792->4820). The flu photo was
swapped (Pexels 5207332, thermometer in mouth) because the old one's face sat in the fade and its
thermometer carries a printed label, so it could NOT be mirrored. **Check for printed text
before mirroring.** A portrait source in the 760px panel zooms in too far and loses the prop.
The other 44 Irving and ~112 Lufkin featured images are designer-made (Canva style), not ours.
Uploader: `featured/upload_featured.py <spec> <site>` (JPEG q86; needs the BROWSER User-Agent,
the bot UA 403s at SiteGround). Each _thumbnail_id write needs its own `verified_page_audit`
first (evidence is consumed per write).

**CLEAR-ZONE RULE (operator, 2026-09-24, HARD): "make sure the actual thing is seen in the clear
area".** With `fade: soft` the photo is >=80% opaque only from canvas x=820 (right ~1/3). Run
`python clearzone.py <spec>` after every render and READ the sheet: if the topic cannot be named
from the crop alone, it fails. First run failed 4 of 6 Irving images I had already shipped
(thermometer, cuff, hands-on-stomach and the patient were all in the fade). Fix = pre-crop the
source to aspect 1.2 so the subject sits in the RIGHT HALF of the crop box (record it as `_crop`
in the spec), or pick a photo whose subject is compact. A subject wider than ~45% of the photo
cannot fit; choose another photo. Re-queued as #1319-1322 (4821-4824).
