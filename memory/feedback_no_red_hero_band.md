---
name: feedback-no-red-hero-band
description: Operator (2026-08-26) rejected the composer's default full-width brand-red hero band on the ER sites as "too much for the eyes"; hero/band backgrounds should use the Secondary navy token (#11468F), red stays for CTAs, icons and accents only.
metadata:
  type: feedback
---

On the three ER sites (erofirving, erofwhiterock, eroflufkin) build_page_from_spec's `hero` paints the band with the Primary token (red). The operator saw the rebuilt accessibility statements and asked for blue.

**Why:** a full-viewport red block is fatiguing and reads as an alarm; the brand system already reserves red for the ~10% accent (CTAs, icons, urgency strips). Navy bands are what the sites' own hero sections use.

**How to apply:** after any composer build on an ER site, change the hero container to `__globals__.background_color = globals/colors?id=secondary` (empty the literal) and flip the solid button's text token from primary to secondary so it does not read red-on-navy (pendings 1708/1709, 1710/1711, 795/796, 1057/1058 are the pattern). IWC's hero uses its green Primary and was not objected to. Better: ask for the composer default to be secondary on ER sites in the next plugin release.
