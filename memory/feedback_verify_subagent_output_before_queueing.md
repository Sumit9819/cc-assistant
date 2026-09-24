---
name: feedback-verify-subagent-output-before-queueing
description: "Never queue a subagent's copy as-is: re-measure lengths yourself and fact-check every product/brand claim against the live page"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4e4eed3-3590-41bf-aad2-18859ca12214
  modified: 2026-07-31T09:27:25.319Z
---

A 14-agent draft+adversarial-verify workflow on sids-ponds metas (2026-07-31) produced output that had passed a dedicated hostile reviewer, and it still contained three defects I only caught by checking myself:

1. **A self-reported character count was wrong.** One verifier reported `measured_title_length: 54` for a string that is actually **60** characters — right at the truncation boundary. Another reported 152 for a 144-char description. Agents claim to have "counted programmatically" and are still wrong.
2. **A fabricated product claim survived review.** The fish-and-pond-plants description promised "water lilies". Fetching the live archive and listing all 12 products showed **no lily product exists** (7 of the 12 are fish food). The *live* meta had the same false claim, so the error was inherited, not invented — but shipping it would have re-published it.
3. **A contested claim was treated as verified.** Agents reused "free shipping over $100" because it appeared on a verified-facts list, while the site itself contradicts it three ways (delivery page heading "All Online Products", delivery page body "select products", homepage "orders of $100 or more").

**Why:** adversarial verification catches reasoning errors well and mechanical/factual errors poorly. A reviewer instructed to refute an SEO argument checks the argument, not the arithmetic or the inventory.

**How to apply:** before queueing subagent-authored copy, always (a) re-measure every string with code, never trust `measured_*` fields; (b) fetch the live page and confirm every named product, brand, and count actually appears; (c) drop any claim tied to a known open loop rather than picking a side. Pairs with [[feedback_precise_figures_only]] and [[feedback_verify_before_proposing_fix]].
