# -*- coding: utf-8 -*-
"""Quarterly report: three months to 29 Aug 2026 vs the three before.
Clicks lead, because Google's impression counting changed in May and impressions
either side of that are not comparable. Same agreed section flow, no asks."""
import io, json, re, sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

K = json.load(open("quarter_kw.json", encoding="utf-8"))

def kwrows():
    out = ""
    for x in K["rows"]:
        if x["move"] is None:
            cls, txt, prev = "new", "newly visible", "not ranking"
        else:
            mv = x["move"]
            cls = "up" if mv > 0.3 else ("dn" if mv < -0.3 else "fl")
            txt = f"up {mv:.1f}" if mv > 0.3 else (f"down {abs(mv):.1f}" if mv < -0.3 else "held")
            prev = f'{x["prev"]:.1f}'
        out += (f'<tr><td class="t">{x["q"]}</td><td class="n">{prev}</td>'
                f'<td class="n"><b>{x["now"]:.1f}</b></td><td class="n {cls}">{txt}</td>'
                f'<td class="n">{x["clicks"] or ""}</td></tr>')
    return out

TRAJ = [("May", 492, 16.5), ("June", 581, 13.7), ("July", 866, 10.4), ("August", 917, 10.4)]
trows = "".join(f'<tr><td class="t">{m}</td><td class="n">{c}</td><td class="n">{p}</td></tr>'
                for m, c, p in TRAJ)

CSS = """
  * { box-sizing: border-box; margin: 0; padding: 0; }
  @page { size: A4; margin: 13mm; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1c1c1c; font-size: 9.7pt; line-height: 1.55; }
  .top { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 3px solid #141414; padding-bottom: 9px; }
  .brand { font-size: 14pt; font-weight: 800; letter-spacing: 0.5px; }
  .brand span { color: #E8890C; }
  .docid { font-family: Consolas, monospace; font-size: 8pt; color: #777; }
  .sub { margin: 7px 0 14px; color: #555; font-size: 9pt; }
  .sec { margin-top: 19px; page-break-inside: avoid; }
  .secnum { font-family: Consolas, monospace; font-size: 11pt; font-weight: 700; color: #E8890C; margin-right: 8px; }
  .sech { display: inline; font-size: 12.5pt; }
  .sec > p { margin-bottom: 6px; }
  ul { margin: 6px 0 0 17px; } li { margin-bottom: 7px; }
  .exec { border: 1px solid #141414; border-left: 5px solid #01B51B; border-radius: 6px; padding: 13px 16px 14px; }
  .exec ul { margin: 0 0 0 16px; } .exec li { margin-bottom: 8px; } .exec li:last-child { margin-bottom: 0; }
  .kpis { display: flex; gap: 10px; margin: 11px 0 2px; }
  .kpi { flex: 1; border: 1px solid #e2e2e2; border-radius: 6px; padding: 10px 12px; text-align: center; }
  .kpi .n { font-size: 18pt; font-weight: 800; letter-spacing: -0.5px; line-height: 1.1; }
  .kpi .l { font-family: Consolas, monospace; font-size: 6.6pt; letter-spacing: 1px; text-transform: uppercase; color: #888; margin-top: 3px; }
  .kpi .d { font-size: 8.2pt; margin-top: 3px; }
  .up { color: #17692f; } .dn { color: #a33; } .fl { color: #888; } .new { color: #17692f; }
  .kpi.hero { border-color: #01B51B; background: #f7fdf8; }
  table { width: 100%; border-collapse: collapse; font-size: 8.9pt; margin-top: 10px; }
  th { text-align: left; font-family: Consolas, monospace; font-size: 6.9pt; letter-spacing: 1px; text-transform: uppercase; color: #666; border-bottom: 2px solid #141414; padding: 5px 8px 5px 0; }
  td { padding: 5px 8px 5px 0; border-bottom: 1px solid #eee; vertical-align: top; }
  td.t { font-weight: 600; }
  td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .half { width: 49%; display: inline-block; vertical-align: top; }
  .pri { margin-top: 12px; padding-left: 14px; border-left: 3px solid #E8890C; page-break-inside: avoid; }
  .pri .h { font-weight: 700; font-size: 10.2pt; margin-bottom: 2px; }
  .callout { background: #f6f7f8; border-radius: 6px; padding: 10px 13px; margin-top: 10px; font-size: 9.2pt; }
  .fn { font-size: 7.4pt; color: #888; margin-top: 22px; border-top: 1px solid #ddd; padding-top: 8px; line-height: 1.5; }
  .footer { margin-top: 10px; font-family: Consolas, monospace; font-size: 7.4pt; color: #999; display: flex; justify-content: space-between; }
"""

HTML = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mammoth Machinery, Quarterly Report, June to August 2026</title>
<style>{CSS}</style>
</head>
<body>

<div class="top">
  <div class="brand">GROWTH<span>BOSS</span></div>
  <div class="docid">QUARTERLY REPORT &nbsp;MM-2026-Q3</div>
</div>
<div class="sub"><b>Mammoth Machinery</b> &nbsp;&middot;&nbsp; The three months to 29 August 2026, against the three months before them &nbsp;&middot;&nbsp; mammothmachinery.ca</div>

<div class="exec">
  <ul>
    <li><b>Clicks from Google rose 37%.</b> 2,364 visits over the quarter against 1,725 in the preceding three months, an increase of 639. Average position improved from 12.8 to 10.9, which is the difference between the bottom of page one and the middle of it.</li>
    <li><b>Eleven of the fifteen priority searches improved, and two appeared for the first time.</b> "Wheel loaders for sale" moved from 18.2 to 8.9, "mini skid steer for sale" from 16.5 to 9.3, and "mini skid loader" from 18.8 to 10.9. One declined.</li>
    <li><b>Most of the growth so far is people searching for Mammoth by name.</b> Branded searches brought 828 clicks against 570 last quarter, while searches that never mention the brand grew from 165 to 179. That is the normal shape early in a content programme, and the non-branded side is now accelerating: it rose 45% in August alone.</li>
  </ul>
</div>

<div class="sec">
  <span class="secnum">01</span><h2 class="sech">Traffic quality</h2>
  <div class="kpis">
    <div class="kpi hero"><div class="n up">2,364</div><div class="l">Clicks this quarter</div><div class="d up">+639, +37%</div></div>
    <div class="kpi"><div class="n up">10.9</div><div class="l">Avg. position</div><div class="d up">improved from 12.8</div></div>
    <div class="kpi"><div class="n">179</div><div class="l">Non-branded clicks</div><div class="d up">up from 165</div></div>
    <div class="kpi"><div class="n">2,668</div><div class="l">Searches found for</div><div class="d up">up from 2,231</div></div>
  </div>
  <p style="margin-top:11px">The site is now found through 2,668 different non-branded searches, 437 more than last quarter. That is the practical measure of reach: each one is a different way a buyer who has never heard of Mammoth can arrive.</p>

  <div class="callout">
    <b>Why this report leads with clicks rather than impressions.</b> Google changed the way it counts impressions at the start of May. The site's recorded impressions fell by roughly half overnight while clicks barely moved, and the click rate doubled from 2.6% to 4.4% in a single month. Nothing changed on the website that week. Impressions before and after that date therefore cannot be compared, and neither can click rate. Clicks and rankings are unaffected, so those are what this report measures.
  </div>

  <p style="margin-top:11px"><b>The trend since the change.</b> Every month below sits after the counting change, so these four are directly comparable with each other.</p>
  <table>
    <tr><th>Month</th><th class="n">Clicks</th><th class="n">Avg. position</th></tr>
    {trows}
  </table>
  <p style="margin-top:8px">Clicks have risen 86% since May and average position has improved by nearly six places.</p>
</div>

<div class="sec">
  <span class="secnum">02</span><h2 class="sech">Priority search movement</h2>
  <p>The fifteen commercial searches bringing the most demand to the site, this quarter against last. Lower position is better.</p>
  <table>
    <tr><th>Search term</th><th class="n">Before</th><th class="n">Now</th><th class="n">Movement</th><th class="n">Clicks</th></tr>
    {kwrows()}
  </table>
  <p style="margin-top:9px">Eleven improved, two became visible for the first time, one held, and one declined. The single decline is "wheel loader", which fell from 7.4 to 22.2. The gains are concentrated in the skid steer and mini loader range, which is where the quarter's content was aimed.</p>
</div>

<div class="sec">
  <span class="secnum">03</span><h2 class="sech">Work completed this quarter</h2>
  <ul>
    <li><b>Twelve guides published across July and August</b>, bringing the library to sixteen. They cover equipment financing, warranty coverage, machine comparisons, sizing, attachments, and how to choose an excavator, a wheel loader and a skid steer.</li>
    <li><b>Warranty registration form repaired.</b> Registration notifications had been reaching the agency only and not Mammoth. They now arrive at info@mammothmachinery.ca. Registration within 30 days is what activates the full five year coverage, so this was a live gap in a customer commitment.</li>
    <li><b>Product and warranty claims corrected across 42 pages.</b> 104 individual edits covering financing figures, what the warranty covers, when it starts, dealer coverage and company history. Supplied separately under reference MM-2026-08-C1.</li>
    <li><b>Machine photography corrected.</b> Seven product pages were showing a different machine and one had no photograph. All eight fixed, and around seventy machine photographs given descriptions so they can appear in image search.</li>
    <li><b>Unreadable text fixed on six category pages.</b> Headline figures and labels had been set in colours invisible against their own backgrounds. Every category and hub page now passes a readability check.</li>
    <li><b>Guides connected to the category pages</b>, so buyers browsing machines reach them instead of only finding them from the blog list.</li>
  </ul>
</div>

<div class="sec">
  <span class="secnum">04</span><h2 class="sech">Priorities for the next quarter</h2>

  <div class="pri">
    <div class="h">1. Convert non-branded visibility into clicks</div>
    <p>The rankings moved this quarter but many sit at positions eight to eleven, where a search result is seen and rarely clicked. Moving a term from ninth to fourth typically multiplies its clicks several times over without needing any new search demand. This is the highest-return work available, and the priority list above is the target list.</p>
  </div>

  <div class="pri">
    <div class="h">2. Fix the wheel loader range</div>
    <p>Wheel loaders are the weakest part of the site. The category page was shown 4,439 times in August for 32 clicks, and "wheel loader" was the quarter's only significant decline. The same approach that lifted the skid steer terms applies directly.</p>
  </div>

  <div class="pri">
    <div class="h">3. Measure the phone</div>
    <p>For machines at these prices the first contact is usually a call, and calls are not currently counted anywhere. Until they are, every figure in this report understates what the website actually produces for the business.</p>
  </div>
</div>

<div class="fn">Search figures compare 1 June to 29 August 2026 against 3 March to 31 May 2026, two periods of exactly 90 days, taken from Search Console on 1 September 2026. Search Console reports roughly three days behind. Average position is weighted by how often each page was shown rather than averaged flat. A search term's previous position is only treated as a baseline where that period had at least 30 impressions behind it; below that a position reflects too few appearances to be meaningful, so the term is shown as newly visible instead. Branded and non-branded figures are calculated on the searches Search Console names, and it withholds rare ones, so both periods are compared on the same basis rather than as absolute totals.</div>

<div class="footer"><div>GROWTHBOSS &middot; QUARTERLY REPORT</div><div>MM-2026-Q3 &middot; CONFIDENTIAL</div></div>

</body>
</html>
"""
io.open("mammoth-quarterly.html", "w", encoding="utf-8").write(HTML)
print("wrote mammoth-quarterly.html | em dashes:", "—" in HTML)
