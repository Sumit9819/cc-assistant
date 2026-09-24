---
name: check-page-history-before-removing-claims
description: "Before removing a factual claim from a page, check that page's OWN pending-change history — the operator may have already confirmed the fact"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 813fe1b6-ece5-4c84-92c1-740614b56b3f
---

On erofirving 852 (Diagnostic Imaging), I removed MRI from the rebuild because MRI appeared nowhere else on the site — but the page's own change history (verify_change siblings #675/#676, 2026-07-08) showed the operator had EXPLICITLY confirmed the facility has an MRI machine and deliberately restored the claim after a previous session made the same removal. Same wrong removal, twice, by two sessions.

**Why:** Site-wide absence of a claim is evidence, not proof. The pending-change ledger records operator ground truth ("operator confirmed X exists") that outranks any inference from other pages. Related: [[verify-before-proposing-fix]].

**How to apply:** Before deleting or contradicting a factual/capability claim (equipment, staff credentials, services, hours), run `verify_change` on any pending for that post or `list_pending_changes(post_id)` and scan sibling change summaries for prior operator confirmations or reverts of the same claim. If a prior change says "operator confirmed", the claim stays unless the operator says otherwise NOW. Mammography on 852 remains unconfirmed — MRI yes, mammography no.
