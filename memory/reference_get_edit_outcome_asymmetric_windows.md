---
name: reference_get_edit_outcome_asymmetric_windows
description: "get_edit_outcome raw before/after totals use unequal windows — read click_delta_norm, not raw clicks/impressions"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 29e48602-4372-4f87-ae52-9f100ed420a5
  modified: 2026-07-27T04:30:26.012Z
---

`get_edit_outcome` compares a fixed 14-day baseline against an after-window that is only as long as the GSC data available so far (often 9-11 days). The `totals.before_clicks` / `before_impressions` vs `after_*` fields are RAW and therefore not comparable — every page looks like it collapsed 30-50% when measured early.

Read `click_delta_norm` and `impression_delta_norm` instead; those are normalized for the asymmetric windows. The `windows` object in the full single-edit response states both ranges and `days_elapsed` — check it before drawing any conclusion.

Seen on erofirving 2026-07-27: raw totals showed nearly every page in the Jul 13-14 batch losing clicks; normalized deltas showed a net +72 clicks with 8 winners and 2 real losers.

Related: [[reference_gsc_impression_bug_2026]] (a separate issue — impressions over-reported May2025-Apr2026, judge on clicks).
