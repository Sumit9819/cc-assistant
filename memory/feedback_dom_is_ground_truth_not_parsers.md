---
name: feedback-dom-is-ground-truth-not-parsers
description: "HARD RULE: never assert that a link/heading/element is MISSING from a page based on a plugin parser; the rendered DOM is the only ground truth. Parsers have blind spots and one produced a confident false gap that was acted on."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-25T04:14:02.008Z
---

**What happened (2026-08-25, irvingwellnessclinic).** I told the operator the homepage's six service cards "link to nothing" and queued six pendings to add heading links. The operator checked the page: every card was linked. Ground truth (curl of the live HTML): each card is a CONTAINER rendered as `<a class="e-con" href="/service/">`. The plugin's `class-elementor-parser.php` read `link` on 13 widget types and never on `elType=container`, so `audit_post_links` and `get_elementor_widgets` both reported a false gap. My pendings would have nested `<a>` inside `<a>` on the homepage.

**The operator's words:** "This has always been the issue, I have to always correct you, that's why I want a system that can never go wrong."

**Why:** a parser answers "what does this static structure contain, as far as I know how to read it". The DOM answers "what does the page actually serve". Only the second can support a claim of ABSENCE. A parser can prove presence (it found a link) but never absence (it may not know how to look).

**How to apply, in order:**
1. Before stating that any page LACKS a link, heading, image, schema or text: fetch the rendered page (`render_probe`, or curl with a browser UA) and confirm the absence there. A parser result alone is insufficient for a negative claim.
2. Before QUEUEING any change premised on an absence, repeat step 1 against the live DOM. The cost is one fetch; the cost of skipping it was six wrong pendings and the operator's trust.
3. When a parser and the DOM disagree, the DOM wins and the parser has a bug: file it, fix it, add a regression test that feeds the parser the exact structure it missed.
4. Fixed in v0.74.1: the parser now reads container links (`tests/elementor-parser-links-test.php` pins it), and `audit_post_links` cross-checks its parsed links against `CC_Assistant_Render_Probe::live_internal_links()` and reports `rendered_cross_check.missed_by_parser` so the TOOL flags its own blind spots instead of relying on me noticing.

Related: [[feedback_probe_discipline_positive_controls]], [[feedback_no_guessing_epistemic_discipline]], [[reference_elementor_post_content_is_stale]], [[feedback_verify_before_proposing_fix]].

## 2026-09-10: `textContent` of a HIDDEN element is not what the reader sees

I told the operator, and put in a client report, that four sids-ponds products were
"out of stock" and "cannot be bought". The operator pushed back: "when I read it, it
say buy from somwhere else". They were right and I was wrong.

The `.stock` element on those pages really does contain the words "Out of stock", and
its class really is `stock out-of-stock`. But its computed style is **`display: none`**,
0 by 0 pixels. The words appear nowhere on the rendered page. What a customer actually
reads, in blue italics under the price, is **"Available in-store!"** (silica sand says
"Available in-store only"). These are in-store-only goods with the cart switched off,
not unavailable goods.

**The failure was reading `element.textContent` from a selector without checking that
the element renders.** Being in the DOM is not the same as being on the page. The
rule at the top of this file said parsers lie and the DOM is truth; the refinement is
that the **visible** DOM is truth.

**How to apply.** For any claim about what a page says to a person:

```js
const s = getComputedStyle(el), r = el.getBoundingClientRect();
const visible = s.display!=='none' && s.visibility!=='hidden' && s.opacity!=='0'
                && r.width>0 && r.height>0;
```

Better still, cross-check with `document.body.innerText`, which contains only rendered
text: `/out of stock/i.test(document.body.innerText)` returned **false** on all four
pages and would have caught this in one line. And take a screenshot of the region before
asserting what it says, which is what finally settled it.

**Second lesson, on framing.** Once corrected, the finding got *better*, not weaker:
the page tells a customer "Available in-store!" while the same page tells Google
`OutOfStock`. That mismatch is a real, specific, checkable defect, and schema.org has
`InStoreOnly` for exactly this case. My wrong version ("these cannot be bought") also
libelled the client's own shop to the client. Verify what a page says to a human
before writing it into anything that leaves the building.
