---
name: feedback-framed-image-must-fill-the-frame
description: A framed photo must COVER its frame and stop at the edge - fitting an image whole inside the frame reads as two separate objects
metadata:
  type: feedback
---

An image inside a frame must **cover** the frame and be clipped by it. It must
not be fitted whole inside it. The operator, on the ER X-ray card (2026-09-08):
"the image should cover the whole frame... but the image should not exceed the
circle frame... this image looks so bad right now, circle is its own and xray
image is on its own."

**Why:** a picture floating inside a shape reads as two objects that happen to be
near each other, not as one composed image. The frame stops being a frame and
becomes a second graphic competing with the photograph. It is the same failure as
the pale halo behind the ring (see [[project_iwc_featured_generator]]) - a
decoration that gives the eye a boundary the subject does not share.

**How to apply:** `photo_fit: "inscribe"` is now REFUSED on `photo-frame*` in the
featured generator, because it cannot satisfy this at any crop point. That turns
the rule into a sourcing constraint, which is where it actually bites: the photo
must be croppable to fill the frame. A scene works. A subject cropped tight to
itself works - the X-ray card was fixed by cropping the radiograph out of the
photograph OF a radiograph lying on a table, which is what made the disc fillable.
An isolated cut-out object on a plain backdrop does NOT work; that belongs in
`photo-panel`, the layout built for an object held whole.

Corollary worth remembering: a "cutout" is not automatically a cut-out subject.
`cut/er-xray-forearm.png` was 97.8% opaque across its whole frame, so its alpha
carried no silhouette at all and the coverage guard was about to refuse a
"severed object" that was really just a cropped rectangle. Check the alpha bbox
before trusting a cutout. Related: [[feedback_body_imagery_doctrine]].
