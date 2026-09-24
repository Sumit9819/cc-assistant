# -*- coding: utf-8 -*-
"""August 2026 monthly report. Client-facing: what changed, what it did, what we need.
Every figure re-pulled at report time (see aug_metrics.py)."""
import io, json, sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

BLOGS = [
 ("Aug 11", "How Does Equipment Financing Work in Canada?", "/how-does-equipment-financing-work-canada/", 1238),
 ("Aug 11", "What Does a 5-Year Equipment Warranty Actually Cover?", "/what-does-5-year-equipment-warranty-cover/", 956),
 ("Aug 17", "Mini Excavator vs Mini Skid Steer: Which Do You Need?", "/mini-excavator-vs-mini-skid-steer/", 1151),
 ("Aug 23", "Landscaping Jobs a Mini Skid Steer Does Best", "/landscaping-jobs-mini-skid-steer/", 1069),
 ("Aug 24", "How to Choose the Right Mini Excavator for Your Work", "/how-to-choose-mini-excavator/", 1018),
 ("Aug 26", "What Is a Telescopic Wheel Loader?", "/what-is-a-telescopic-wheel-loader/", 1046),
 ("Aug 26", "How to Choose a Wheel Loader for Canadian Job Sites", "/how-to-choose-a-wheel-loader/", 919),
 ("Aug 26", "Wheel Loader vs Skid Steer: Which Fits Your Job Site?", "/wheel-loader-vs-skid-steer/", 931),
]
TOP = [("412", "10,436", "10.6", "Home page", "mammothmachinery.ca"),
       ("93", "4,577", "10.2", "Mini Skid Steers", "/mini-skidsteers/"),
       ("62", "4,909", "12.1", "Mini Excavators", "/mini-excavators/"),
       ("54", "2,286", "6.4", "Tracked Mini Dumpers", "/tracked-mini-dumpers/"),
       ("35", "824", "6.1", "X-Loader 3000MT", "/x-loader-3000mt-full-size-skid-steer/"),
       ("32", "4,439", "12.8", "Wheel Loaders", "/wheel-loaders/")]

CSS = """
  * { box-sizing: border-box; margin: 0; padding: 0; }
  @page { size: A4; margin: 13mm; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1c1c1c; font-size: 9.7pt; line-height: 1.55; }
  .top { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 3px solid #141414; padding-bottom: 9px; }
  .brand { font-size: 14pt; font-weight: 800; letter-spacing: 0.5px; }
  .brand span { color: #E8890C; }
  .docid { font-family: Consolas, monospace; font-size: 8pt; color: #777; }
  .sub { margin: 7px 0 14px; color: #555; font-size: 9pt; }
  .sec { margin-top: 20px; page-break-inside: avoid; }
  .secnum { font-family: Consolas, monospace; font-size: 11pt; font-weight: 700; color: #E8890C; margin-right: 8px; }
  .sech { display: inline; font-size: 12.5pt; }
  .sec > p { margin-bottom: 6px; }
  .kpis { display: flex; gap: 10px; margin: 12px 0 2px; }
  .kpi { flex: 1; border: 1px solid #e2e2e2; border-radius: 6px; padding: 10px 12px; text-align: center; }
  .kpi .n { font-size: 19pt; font-weight: 800; letter-spacing: -0.5px; line-height: 1.1; }
  .kpi .l { font-family: Consolas, monospace; font-size: 6.8pt; letter-spacing: 1px; text-transform: uppercase; color: #888; margin-top: 3px; }
  .kpi .d { font-size: 8.3pt; margin-top: 3px; }
  .up { color: #17692f; } .dn { color: #a33; } .fl { color: #888; }
  .kpi.hero { border-color: #01B51B; background: #f7fdf8; }
  table { width: 100%; border-collapse: collapse; font-size: 8.9pt; margin-top: 10px; }
  th { text-align: left; font-family: Consolas, monospace; font-size: 6.9pt; letter-spacing: 1px; text-transform: uppercase; color: #666; border-bottom: 2px solid #141414; padding: 5px 8px 5px 0; }
  td { padding: 6px 8px 6px 0; border-bottom: 1px solid #eee; vertical-align: top; }
  td.d { font-family: Consolas, monospace; font-size: 8pt; color: #666; white-space: nowrap; width: 52px; }
  td.t { font-weight: 600; }
  td.u { font-family: Consolas, monospace; font-size: 7.6pt; color: #17692f; word-break: break-all; }
  td.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .fix { margin-top: 11px; padding-left: 13px; border-left: 3px solid #01B51B; page-break-inside: avoid; }
  .fix .h { font-weight: 700; font-size: 10pt; margin-bottom: 2px; }
  .ask { margin-top: 11px; padding-left: 13px; border-left: 3px solid #E8890C; page-break-inside: avoid; }
  .ask .h { font-weight: 700; font-size: 10pt; margin-bottom: 2px; }
  .note { background:#141414; color:#fff; border-radius:6px; padding:11px 14px; margin-top:18px; font-size:9.4pt; page-break-inside: avoid; }
  .fn { font-size: 7.4pt; color: #888; margin-top: 22px; border-top: 1px solid #ddd; padding-top: 8px; line-height: 1.5; }
  .footer { margin-top: 10px; font-family: Consolas, monospace; font-size: 7.4pt; color: #999; display: flex; justify-content: space-between; }
"""

rows_blogs = "".join(
    f'<tr><td class="d">{d}</td><td class="t">{t}</td><td class="u">{u}</td><td class="n">{w:,}</td></tr>'
    for d, t, u, w in BLOGS)
rows_top = "".join(
    f'<tr><td class="t">{n}</td><td class="u">{u}</td><td class="n">{c}</td><td class="n">{i}</td><td class="n">{p}</td></tr>'
    for c, i, p, n, u in TOP)

HTML = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mammoth Machinery, Monthly Report, August 2026</title>
<style>{CSS}</style>
</head>
<body>

<div class="top">
  <div class="brand">GROWTH<span>BOSS</span></div>
  <div class="docid">MONTHLY REPORT &nbsp;MM-2026-08-M1</div>
</div>
<div class="sub"><b>Mammoth Machinery</b> &nbsp;&middot;&nbsp; August 2026 &nbsp;&middot;&nbsp; mammothmachinery.ca</div>

<div class="sec" style="margin-top:4px">
  <span class="secnum">01</span><h2 class="sech">Search performance</h2>
  <p>August 1 to 29 against the same 29 days of July, so the two periods are the same length.</p>
  <div class="kpis">
    <div class="kpi hero"><div class="n up">917</div><div class="l">Clicks</div><div class="d up">+113 vs July, +14%</div></div>
    <div class="kpi"><div class="n">39,552</div><div class="l">Times shown</div><div class="d up">+16,990, +75%</div></div>
    <div class="kpi"><div class="n">2.32%</div><div class="l">Click rate</div><div class="d dn">down from 3.56%</div></div>
    <div class="kpi"><div class="n">10.4</div><div class="l">Avg. position</div><div class="d fl">unchanged</div></div>
  </div>
  <p style="margin-top:10px"><b>What this means.</b> More people clicked through to the website than in July, and your average ranking held steady. The click rate fell because the number of times you were shown grew much faster than the clicks did: the site now appears for 17,000 more searches a month than it did in July, and a good share of those are broader searches that were never going to click. The number to watch is clicks, and clicks went up.</p>
  <table>
    <tr><th>Page</th><th>Address</th><th class="n">Clicks</th><th class="n">Shown</th><th class="n">Position</th></tr>
    {rows_top}
  </table>
</div>

<div class="sec">
  <span class="secnum">02</span><h2 class="sech">Eight guides published</h2>
  <p>The full eight-article plan is now live. Three of these were scheduled for September and went out early, so the content calendar is ahead rather than behind.</p>
  <table>
    <tr><th>Date</th><th>Guide</th><th>Address</th><th class="n">Words</th></tr>
    {rows_blogs}
  </table>
  <p style="margin-top:9px"><b>Too early to judge them.</b> Between them these eight pages were shown 142 times in August and earned no clicks yet. That is expected: five of them went live in the last week of the month, and a new page normally takes six to twelve weeks to settle into the rankings. The first fair read on them is late September.</p>
</div>

<div class="sec">
  <span class="secnum">03</span><h2 class="sech">Problems found and fixed</h2>

  <div class="fix">
    <div class="h">Warranty registrations were not reaching you</div>
    <p>The warranty registration form on the website was sending its notifications to the agency's address only, not to Mammoth. Customers were always getting their own confirmation email, so nothing looked broken from the outside. Registrations now arrive at info@mammothmachinery.ca. This matters because registration within 30 days is what activates the full 5-year coverage.</p>
  </div>

  <div class="fix">
    <div class="h">Seven pages were showing the wrong machine</div>
    <p>Seven product pages carried a photograph of a different model, and one had no photo at all. All eight are corrected. We also wrote descriptions for around seventy machine photographs, which is what lets them appear in Google image results and what a screen reader reads aloud.</p>
  </div>

  <div class="fix">
    <div class="h">Claims that were not accurate</div>
    <p>Following your review, we checked all 53 published pages and corrected 73 inaccurate statements across 42 of them, added 3 missing disclosures, and brought 28 pieces of wording into line. The detail is in the separate document, reference MM-2026-08-C1.</p>
  </div>

  <div class="fix">
    <div class="h">Text nobody could read, and pages nothing linked to</div>
    <p>Six category pages had text set in a colour that made it invisible against its own background, including headline figures. Every category and hub page now passes our readability check. Separately, several guides could only be reached from the blog list, so we linked them from the category pages a buyer actually lands on.</p>
  </div>
</div>

<div class="sec">
  <span class="secnum">04</span><h2 class="sech">What we need from you</h2>

  <div class="ask">
    <div class="h">1. The financing figures</div>
    <p>The financing page states "98%", "1.99% to 0% O.A.C" and "terms up to 72 months". We removed these from the other 36 pages that repeated them, and left them on the financing page as you asked. If the lender terms behind them can be produced, a real rate is a strong selling point and we will put it back. If not, they should come off that page too.</p>
  </div>

  <div class="ask">
    <div class="h">2. The founding year</div>
    <p>The website had carried three different figures. We have standardised on 31 years, which the home page already used. Please confirm the actual year so it can be stated once everywhere.</p>
  </div>

  <div class="ask">
    <div class="h">3. Two prices and one set of photographs</div>
    <p>Still outstanding from our August request: a price for the X-Loader 2000MT, a price and availability answer for the MTL1000, and product photographs of the MTL1000 and the MT2850CB. Every other machine shows its price publicly, which is a genuine advantage over competitors who hide it.</p>
  </div>

  <div class="ask">
    <div class="h">4. Where you sell, in your own words</div>
    <p>Your dealer network runs from Ontario to the Maritimes, eight locations. The home page says you are trusted "from coast to coast" and the About page says you serve "customers across North America". Both may be perfectly true, since customers are not the same as dealers, but we cannot verify them and they are the next thing anyone checks after the dealer list.</p>
  </div>
</div>

<div class="note"><b>September.</b> The content plan is complete and running ahead, so the next decision is whether to start a new run of guides or hold until the results of this month's work can be measured properly. We recommend holding until the second week of September, when the guides published in August will have had time to rank and we can choose the next topics based on what is actually working rather than on a plan written in June.</div>

<div class="fn">Search figures cover August 1 to 29, 2026 against July 1 to 29, 2026, taken from Search Console on August 29, 2026 and rounded only where shown. Search Console reports roughly three days behind, so the final days of August are not yet included and the figures will rise slightly. Average position is weighted by how often each page was shown, not a simple average. Home page figures combine the www and non-www address, which Search Console lists separately although one redirects to the other. Page and content changes were verified on the live website after each change.</div>

<div class="footer"><div>GROWTHBOSS &middot; MONTHLY REPORT</div><div>MM-2026-08-M1 &middot; CONFIDENTIAL</div></div>

</body>
</html>
"""
io.open("mammoth-monthly-august.html", "w", encoding="utf-8").write(HTML)
print("wrote mammoth-monthly-august.html")
print("em dashes:", "—" in HTML)
