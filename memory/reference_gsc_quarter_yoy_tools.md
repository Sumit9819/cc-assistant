---
name: reference-gsc-quarter-yoy-tools
description: D:\gsc-tools\gsc-quarter.py and gsc-yoy.py for client quarterly reports, plus the withheld-query trap that makes query-row sums 40%+ short of true totals
metadata:
  type: reference
---

Two scripts written 2026-08-29 for the sids-ponds quarterly report. Both pull
straight from the Search Console API (the local warehouse only held 2026-05-01
onward, far too short for a quarter-on-quarter).

- **`gsc-quarter.py`** - equal-length 90-day windows anchored to the last date
  Google actually has data for, so a partial current month is never compared with
  a full prior one. Writes `out/quarter-<site>.json`.
- **`gsc-yoy.py`** - the same 90 days one year earlier, offset by **364 days** to
  keep weekday alignment. Writes `out/yoy-<site>.json`.

**Always run both.** On a seasonal business quarter-on-quarter is meaningless on its
own: for a pond and garden supplier Mar-May is spring peak and Jun-Aug is off
season, so QoQ manufactures a decline. Only the year-on-year window holds season
constant and tells you whether a movement is performance or weather.

## Traps these exist to avoid

**Query rows do not sum to site totals.** Google withholds rare queries. On
sids-ponds the page+query sum was **41-44% below** the unfiltered total in every
window (current 2,381 vs 4,177 true clicks). The withholding rate is consistent
across windows, so brand/non-brand splits are still comparable, but they are a
**share of named terms**, not absolutes. Take headline totals from `fetch(..., [])`
with no dimensions, and say which basis each figure uses.

**Page-dimension sums differ again** from the unfiltered total (5,143 vs 5,051 for
one window). Pick one basis per figure and footnote it; never mix them inside a
single percentage.

**Impressions mislead more than clicks in 2026.** See
[[reference_gsc_impression_bug_2026]]. Band the loss by position before reporting
it: on sids-ponds non-brand impressions fell 47.9% but 80% of that sat below
position 20 and produced only 5% of the click loss, and sub-50 terms lost 33,001
impressions while *gaining* 15 clicks.

Related: [[reference_gsc_api_toolchain]], [[feedback_client_report_format]],
[[feedback_never_diagnose_from_average_position]].
