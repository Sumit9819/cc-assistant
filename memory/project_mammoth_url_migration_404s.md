---
name: project_mammoth_url_migration_404s
description: "RESOLVED 2026-08-21 - mammothmachinery.ca legacy 404s, sitemap and spec-consistency flags are all fixed; verified live, do not re-chase"
metadata:
  type: project
---

**All of this is FIXED. Verified live 2026-08-21. Do not spend another session on it.**

What the old note claimed, and what is actually true now:

| Old claim | Verified state (2026-08-21) |
|---|---|
| 29 legacy Woo URLs 404, no redirects | Top 26 GSC pages all return **200**. Zero broken. |
| Sitemap broken | 5 sitemaps, 51 URLs. **All 13 top-traffic pages present.** |
| 100MT capacity inconsistent (1,100 / 950 / 1,200 lbs) | Consistent everywhere: cards, spec table and FAQ all read **1,300 / 1,200 / 1,000 lbs** for 120MT / 100MT / 50MT, with the source named. None of the old figures exist. |
| Mini Skidsteers FAQ "made in Canada" answered with engine text | Fixed — now reads "Precision engineered in Burlington, Ontario…". The engine copy is a separate adjacent card. |

Clicks were up 89% YoY *while* those issues were believed open, which should have been the
tell that they were already handled by an earlier session.

**Two false alarms I generated checking this — both the same mistake.** Flattening a page to
text with `re.sub('<[^>]+>',' ')` and then regexing across it JOINS ADJACENT CARDS, so a
label from one card pairs with a value from the next. It produced a phantom "Rated Operating
Capacity 2,830 lb" (2,830 is an Operating Weight) and a phantom copy-paste FAQ bug. Replace
tags with a separator (`' | '`) and inspect the real structure before accusing a page of a
factual error. See [[feedback_probe_discipline_positive_controls]].

**Third false alarm, same session:** `/wheel-loaders/` looked broken (Python returned no HTTP
code) and looked catastrophically slow (3.9 → 10.4s). Both wrong. Clean sequential
measurement: **TTFB ~1.1s, total ~1.9s**, and a static CSS asset on the same host shows the
*same* 1.12s TTFB — so that latency is edge/network, not PHP. The 10s readings were my own
parallel-curl contention. Never report a timing without the static-asset control
([[feedback_never_claim_server_slow_without_control]]).

Genuinely open on this site: plugin **version drift** (runs 0.70.0, local build 0.71.3 — the
non-US authority-hosts fix matters here, mammothmachinery**.ca** was scoring citation
density 0.00 on every Canadian source), and 2 pending changes awaiting approval.
