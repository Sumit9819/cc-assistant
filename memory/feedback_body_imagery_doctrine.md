---
name: feedback_body_imagery_doctrine
description: "Operator rule (2026-09-08, non-negotiable) - how people may be depicted in generated featured/OG images on wellness and weight-loss content: parity test, stigma bans, no body paired with a claim. Enforced in code, not memory."
metadata:
  type: feedback
---

Asked whether a larger-bodied woman was acceptable on a semaglutide post, and
whether it could hurt readers, the operator's position after the discussion was:
subject variety is free ("I dont care about generating different women everytime,
because it good have differences in images"), the standards are not ("for other
things, I cant compromise").

**Why:** a lean model on weight-loss content says two things a clinic must not
say - this is who we serve, and this is what the drug does to you (an
implied-outcome claim on a YMYL page). The harm is never in showing a larger
body; it is in showing it DIFFERENTLY from every other portrait in the set. And
the deeper failure is a pattern: if larger bodies appear only on weight-loss
posts, the site has drawn a line about who belongs where.

**How to apply.** The doctrine lives in the design skill as
`irvingwellnessclinic-design` **section 24**, and the bans live as patterns in
`featured/image-policy.json`, enforced on the generation brief and on the shipped
`alt` text. Do not restate them from memory - read section 24. The short form:
decide whether the post is about a MECHANISM (then illustrate the mechanism, or
use the `type` layout, and no body belongs in the frame) or about the experience
of care (then a person, at parity - face visible, calm, ordinary clothes, same
lighting and crop as everyone else). Never pair a body with a claim. Spread body
variety across the other topics too. Disclose that the people are illustrations.
Review `out/_listing-sheet.png` for every shot: the gates catch geometry and
described depiction, never a stigmatizing photo wearing innocent alt text.
Related: [[project_iwc_featured_generator]], [[feedback_look_at_every_shot_before_delivering]],
[[reference_per_site_design_skills]].
