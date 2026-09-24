---
name: feedback-bulk-text-rewrites-pin-and-simulate
description: "A stem-based find-and-replace must pin what follows the stem, and any multi-patch body edit must be simulated end to end before queueing"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 9c6f1bb7-e1aa-47b7-a180-e9cd066c7b98
  modified: 2026-09-04T12:24:20.830Z
---

Two rules for any bulk text rewrite, both learned by shipping the failure.

**Pin what follows a stem.** Rewriting British `-ise` to US `-ize` with the stem pair `("metabolis", "metaboliz")` also matched the correct English word **metabolism** and turned it into **metabolizm** — on 12 cards that then went live on irvingwellnessclinic (2026-09-04). The same class had already produced a near-miss (`specialis` matching `Specialist`) that was caught only by eye. Fix: constrain the stem to a real suffix, e.g. `metabolis(e|es|ed|ing|ation)\b`, and keep a separate allowlist of correct lookalikes (`metabolism`, `organism`, `hypothyroidism`, `Specialist`, `Realistic`, `phytoestrogens`). Add a reverse check too: any word ending `-izm` is never English and means a stem ate an `-ism`.

**Simulate the whole patch set against the real content before queueing.** Compact search/replace patches for un-nesting `<figure><figure>` each passed a "matches exactly once" check individually, yet the set was wrong: it removed only the closing tag, leaving unbalanced markup on 16 posts. Replaying the patches in order against the actual post bodies — then asserting the invariants (`<figure>` count equals `</figure>` count, no nesting remains, no corrupted words) — caught it before anything was queued. A per-patch uniqueness check is not a correctness check.

**Why:** find-and-replace at scale silently changes things nobody asked it to change, and each patch can be individually valid while the sequence is not.

**How to apply:** write the rule as a full word list rather than a few stems (see [[feedback-guards-must-check-content-not-geometry]]); run the corrective script, then re-run the *detector* and require it to exit clean; simulate the applied result and assert structural invariants; and look at the rendered output afterwards, because a bitmap keeps whatever the rewrite put there.
