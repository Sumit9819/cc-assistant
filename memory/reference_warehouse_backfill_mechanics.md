---
name: reference_warehouse_backfill_mechanics
description: gsc_warehouse_sync spends max_dates on refresh_days first — set refresh_days=1 when you actually need backfill
metadata: 
  node_type: memory
  type: reference
  originSessionId: 130cc6ff-5f2e-4578-922a-b3979c9bd445
  modified: 2026-08-04T05:04:40.189Z
---

`gsc_warehouse_sync(max_dates, refresh_days)` spends its date budget in this order: re-pull the trailing `refresh_days`, fill gaps, *then* backfill older history newest-first. **`refresh_days` defaults to 7 and comes out of `max_dates`**, so a call with `max_dates=5` can consume the whole budget re-pulling recent days and move the `earliest` date not at all. When you are chasing history, pass `refresh_days=1`.

`max_dates` caps at 120. Measured throughput 2026-08-04: erofwhiterock (tiny, ~50 rows/day) 120 dates in 282s; eroflufkin 120 dates / 214,698 rows in 297s; erofirving (~10k rows/day) 120 dates / 1,484,499 rows in **570s**. Budget roughly 5–10 minutes per 120-date call on a busy property and run the four sites in parallel — they are independent MCP servers.

Disk cost is real: backfilling erofirving from 88 to 215 dates took its SQLite file from 372 MB to **940 MB**. The four warehouses live in `C:\Users\sumit\.cc-assistant\warehouse\<site>.sqlite`.

Coverage gotcha found the same day: **erofwhiterock.com has no GSC data at all before 2026-02-16** — that is the property's true start, not a sync gap. Always check `MIN(date)` inside the requested window before reporting a period, and show absent months as blank rather than zero. See [[reference_gsc_impression_bug_2026]] for why clicks, not impressions, carry any judgement over a window spanning April 2026.
