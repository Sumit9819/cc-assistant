---
name: feedback_contrast_sweep_every_text_node
description: A contrast check scoped to one colour pair is not a contrast pass; sweep every text node, and be background-image aware or you will report false failures
metadata:
  type: feedback
---

**HARD, 2026-09-08.** I ran a "contrast pass" on six Mammoth category pages that
measured only green links (`#01B51B` / `#017A12`), reported "60 links measured,
zero failing AA", and the operator then sent a screenshot of a **black box with
black text sitting directly above the H1** on a page I had just declared clean.
Their words: "I am seeing this issues in a lot of places... how can you do such
things or miss such things".

**Why:** I scoped the check to the colour pair I happened to be editing. Anything
else was invisible to it. A sweep of every text node found **1,332 failing
elements across all 53 published pages**, including 29 kicker pills whose
`title_color` and `_background_color` were set to the SAME hex.

**How to apply.** Never call a targeted check a contrast pass. Sweep properly:
walk every element's own text nodes, take computed `color`, resolve the real
backdrop, apply the correct threshold (4.5:1, or 3.0:1 when >=24px or bold
>=18.66px), and skip `display:none` / `visibility:hidden` / `opacity:0` /
zero-size. Reusable script: the contrast-sweep pattern in
[[reference_cc_rest_via_browser_nonce]] tooling notes.

**The false-positive trap that matters just as much.** My first sweep walked
ancestors for an opaque `background-color`, found none, and defaulted to white.
That reported the footer email and street address as white-on-white on all 53
pages. **It was wrong** - the footer sits on a dark background IMAGE and reads
perfectly. I only caught it because I screenshotted before reporting. So:

- If any ancestor between the text and the first opaque colour has a
  `background-image`, the contrast is INDETERMINATE. Report it separately, never
  as a failure.
- Composite translucent backgrounds instead of ignoring them.
- **Screenshot before telling the operator something is broken.** A number that
  says the client's contact details are invisible sitewide is exactly the claim
  that must be seen with eyes first ([[feedback_dom_is_ground_truth_not_parsers]]).

**Find the intended styling from a positive control, do not invent it.** For the
kicker pills I read the site's own working siblings: `#FFFFFF` on `#141414` and
`#141414` on `#01B51B` both appear dozens of times and pass. That gave the fix
without a design guess ([[feedback_probe_discipline_positive_controls]]).

Related: [[feedback_never_claim_server_slow_without_control]],
[[feedback_confirm_change_took_effect]].
