---
name: feedback_er_featured_images_use_medical_icons
description: ER-site featured images use a Healthicons (CC0) medical pictogram in the navy framed disc, NOT object still-life photos; operator rejected "armchair for dizziness" and felt several object photos were bad (2026-09-24)
metadata:
  type: feedback
---

**Rule (operator decision 2026-09-24):** featured/OG images on the ER sites use the
`photo-frame-dark` frame b layout with a **Healthicons medical pictogram** (navy #041562 on a
white disc, red ring) instead of an AI object photo.

**Why:** the ER image policy bans people and body parts, so object still lifes were the
fallback, and for many topics the object is a non-sequitur: an empty armchair for dizziness,
crackers for vomiting. The operator: "Why dizziness have sofa as an image? also few other felt
so bad". A pictogram names the symptom at thumbnail size. Healthicons are CC0 (README
states public domain), about 2,700 icons with symptom-level coverage (dizzy, fever, coughing,
blood-pressure, nausea, vomiting, testicles, ultrasound-scanner, heart-cardiogram...).

**How to apply:**
- `node featured/healthicon.mjs <icon> src/icon/<name>.png` rasterises from
  `src/icon/healthicons.json` (the @iconify-json/healthicons set) at 62% of a 1000px canvas.
- Prefer the least graphic accurate icon: the operator picked nausea over the vomiting figure
  and the ultrasound scanner over the anatomical testicles icon. X-eyes `dizzy` was approved.
- Alt text must avoid "person"/body words (image-policy refuses them). Write "a nausea
  pictogram" rather than "a person with nausea".
- Show a preview sheet and let the operator pick before uploading a new style.
- Replacements need NEW filenames (Facebook caches og:image by URL).
- JPEG q86 at 1200x630 (60-85 KB). Rank Math uses the featured image as og:image.
First set: erofirving 4772/4773/4774/4792/4802 -> attachments 4806/4807/4808/4809/4805,
pendings #1303-1307. Related: [[project_iwc_featured_generator]].
