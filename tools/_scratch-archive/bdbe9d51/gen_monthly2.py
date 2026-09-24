# -*- coding: utf-8 -*-
"""August 2026 monthly report, client-approved structure:
exec summary / enquiries / traffic quality / keyword movement / deliverables / priorities.
No asks, no calendar-slot references. Figures pulled at report time."""
import io, json, re, sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

KW = json.load(open("money_kw.json", encoding="utf-8"))
LEADS = json.load(open("leads.json", encoding="utf-8")) if __import__("os").path.exists("leads.json") else None

def kwrows():
    out = ""
    for k in KW["keywords"]:
        mv = k["move"]
        if mv is None:
            cls, txt = "new", "new"
        elif mv > 0.3:
            cls, txt = "up", f"up {mv:.1f}"
        elif mv < -0.3:
            cls, txt = "dn", f"down {abs(mv):.1f}"
        else:
            cls, txt = "fl", "held"
        jp = f'{k["jul_pos"]:.1f}' if k["jul_pos"] else "not ranking"
        out += (f'<tr><td class="t">{k["q"]}</td><td class="n">{jp}</td>'
                f'<td class="n"><b>{k["aug_pos"]:.1f}</b></td><td class="n {cls}">{txt}</td>'
                f'<td class="n">{k["aug_clicks"] or ""}</td></tr>')
    return out

NB = KW["nonbrand"]
a, j = NB["aug"], NB["jul"]

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
  .exec ul { margin: 0 0 0 16px; } .exec li { margin-bottom: 8px; }
  .exec li:last-child { margin-bottom: 0; }
  .kpis { display: flex; gap: 10px; margin: 11px 0 2px; }
  .kpi { flex: 1; border: 1px solid #e2e2e2; border-radius: 6px; padding: 10px 12px; text-align: center; }
  .kpi .n { font-size: 18pt; font-weight: 800; letter-spacing: -0.5px; line-height: 1.1; }
  .kpi .l { font-family: Consolas, monospace; font-size: 6.6pt; letter-spacing: 1px; text-transform: uppercase; color: #888; margin-top: 3px; }
  .kpi .d { font-size: 8.2pt; margin-top: 3px; }
  .up { color: #17692f; } .dn { color: #a33; } .fl { color: #888; }
  .kpi.hero { border-color: #01B51B; background: #f7fdf8; }
  table { width: 100%; border-collapse: collapse; font-size: 8.9pt; margin-top: 10px; }
  th { text-align: left; font-family: Consolas, monospace; font-size: 6.9pt; letter-spacing: 1px; text-transform: uppercase; color: #666; border-bottom: 2px solid #141414; padding: 5px 8px 5px 0; }
  td { padding: 5px 8px 5px 0; border-bottom: 1px solid #eee; vertical-align: top; }
  td.t { font-weight: 600; }
  td.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  th.n { text-align: right; }
  .pri { margin-top: 12px; padding-left: 14px; border-left: 3px solid #E8890C; page-break-inside: avoid; }
  .pri .h { font-weight: 700; font-size: 10.2pt; margin-bottom: 2px; }
  .fn { font-size: 7.4pt; color: #888; margin-top: 22px; border-top: 1px solid #ddd; padding-top: 8px; line-height: 1.5; }
  .footer { margin-top: 10px; font-family: Consolas, monospace; font-size: 7.4pt; color: #999; display: flex; justify-content: space-between; }
"""

ENQ = ""
lead_block = ""
if LEADS:
    lead_block = f"""
  <div class="kpis">
    <div class="kpi hero"><div class="n">{LEADS['aug']}</div><div class="l">Enquiries in August</div><div class="d">{LEADS['aug_note']}</div></div>
    <div class="kpi"><div class="n">{LEADS['q90']}</div><div class="l">Last 90 days</div><div class="d">{LEADS['rate']} per month</div></div>
  </div>
  <p style="margin-top:10px">{LEADS['prose']}</p>"""
    ENQ = f"""<div class="sec">
  <span class="secnum">01</span><h2 class="sech">Enquiries</h2>{lead_block}
</div>"""

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

<div class="exec">
  <ul>
    <li><b>Growth is coming from new prospects, not from your existing brand.</b> Searches where nobody typed "Mammoth" more than doubled, from 7,930 to 17,424, and the clicks from them rose from 62 to 90. Non-branded demand is now three quarters of the searches we can identify, up from two thirds in July.</li>
    <li><b>Nine of the fifteen priority keywords improved.</b> "Mini skid steer" moved from position 12.5 to 9.7 and is now the single biggest source of non-branded clicks. "Skid steer" moved from 12.8 to 7.5. Four slipped, all in the wheel loader range.</li>
    <li><b>Bottom line.</b> 917 clicks in August against 804 in the same stretch of July, a 14% rise, with average position holding steady. Eight new guides went live and the product, warranty and financing claims across the site were corrected to match fact.</li>
  </ul>
</div>

{ENQ}

<div class="sec">
  <span class="secnum">02</span><h2 class="sech">Traffic quality</h2>
  <p>The figure that matters here is non-branded search: people who described a machine rather than typing your company name. That is new demand rather than existing customers finding you again.</p>
  <div class="kpis">
    <div class="kpi hero"><div class="n up">917</div><div class="l">Clicks</div><div class="d up">+14% on July</div></div>
    <div class="kpi"><div class="n up">90</div><div class="l">Non-branded clicks</div><div class="d up">up from 62, +45%</div></div>
    <div class="kpi"><div class="n">74.7%</div><div class="l">Non-branded share</div><div class="d up">up from 67.1%</div></div>
    <div class="kpi"><div class="n">10.4</div><div class="l">Avg. position</div><div class="d fl">held steady</div></div>
  </div>
  <p style="margin-top:10px">Non-branded impressions grew 120%, and the site now appears for 1,900 distinct non-branded searches against 1,340 in July, an increase of 560 different ways a buyer can find you.</p>
  <p><b>One number went down, and it is worth explaining.</b> The overall click rate fell from 3.56% to 2.32%. That is arithmetic rather than a decline: the site was shown 17,000 more times than in July while clicks rose 14%, so the same clicks are divided across a much larger pool of impressions. Many of those new impressions are broad searches that were never going to click. Clicks rose, position held, and non-branded clicks rose faster than total clicks, which is the healthier signal.</p>
</div>

<div class="sec">
  <span class="secnum">03</span><h2 class="sech">Priority keyword movement</h2>
  <p>The fifteen commercial searches driving the most demand to the site, August against July. Lower position is better.</p>
  <table>
    <tr><th>Search term</th><th class="n">July</th><th class="n">August</th><th class="n">Movement</th><th class="n">Clicks</th></tr>
    {kwrows()}
  </table>
  <p style="margin-top:9px">Nine improved, four slipped, one is new, one held. The gains are concentrated in the skid steer range, which is where this month's content was aimed. The four that slipped are all wheel loader terms, and that is the focus for September.</p>
</div>

<div class="sec">
  <span class="secnum">04</span><h2 class="sech">Work completed</h2>
  <ul>
    <li><b>Eight guides published, 8,328 words.</b> Equipment financing, warranty coverage, mini excavator versus mini skid steer, landscaping uses, how to choose a mini excavator, telescopic wheel loaders, how to choose a wheel loader, and wheel loader versus skid steer.</li>
    <li><b>Warranty registration form repaired.</b> Registration notifications were reaching the agency only and not Mammoth. They now arrive at info@mammothmachinery.ca. Registration within 30 days is what activates the full five year coverage, so this was a live gap in a customer commitment.</li>
    <li><b>Product and warranty claims corrected across 42 pages.</b> 104 individual edits, covering financing figures, what the warranty covers, when it starts, dealer coverage and company history. Detail supplied separately under reference MM-2026-08-C1.</li>
    <li><b>Machine photography corrected.</b> Seven product pages were showing a different machine and one had no photograph. All eight fixed, and roughly seventy machine photographs given descriptions so they can appear in image search.</li>
    <li><b>Unreadable text fixed on six category pages.</b> Headline figures and labels had been set in colours invisible against their own backgrounds. Every category and hub page now passes a readability check.</li>
    <li><b>Guides connected to the category pages.</b> Several guides could previously only be reached from the blog list, so buyers browsing machines never saw them.</li>
  </ul>
</div>

<div class="sec">
  <span class="secnum">05</span><h2 class="sech">Priorities for September</h2>

  <div class="pri">
    <div class="h">1. Turn wheel loader visibility into clicks</div>
    <p>The wheel loader page was shown 4,439 times in August and earned 32 clicks, sitting at position 12.8, and four wheel loader search terms slipped this month. It is the largest pool of demand on the site that is not converting. The skid steer approach that produced this month's gains applies directly to it.</p>
  </div>

  <div class="pri">
    <div class="h">2. Measure the phone</div>
    <p>For machines at this price the first contact is usually a call, and calls are currently invisible in reporting. Until they are counted, every figure in this report understates what the website actually generates. Setting up call measurement is the next step to showing the full return.</p>
  </div>

  <div class="pri">
    <div class="h">3. Score August's guides and build on what works</div>
    <p>The eight guides published this month are too new to judge; a new page normally takes six to twelve weeks to settle. In late September we will have the first real read on which topics earn traffic, and the next run of content will follow that evidence rather than a plan written in advance.</p>
  </div>
</div>

<div class="fn">Search figures cover August 1 to 29, 2026 against July 1 to 29, 2026, equal length periods, taken from Search Console on August 29, 2026. Search Console reports roughly three days behind, so the closing days of August are not yet included and the totals will rise slightly. Average position is weighted by how often each page was shown. Branded and non-branded figures are calculated on the searches Search Console names; it withholds rare searches, so both months are compared on the same basis rather than as absolute totals.</div>

<div class="footer"><div>GROWTHBOSS &middot; MONTHLY REPORT</div><div>MM-2026-08-M1 &middot; CONFIDENTIAL</div></div>

</body>
</html>
"""
# renumber sections so the sequence is continuous whichever sections are present
_n = [0]
def _renum(m):
    _n[0] += 1
    return f'<span class="secnum">{_n[0]:02d}</span>'
HTML = re.sub(r'<span class="secnum">\d+</span>', _renum, HTML)
io.open("mammoth-monthly-v2.html", "w", encoding="utf-8").write(HTML)
print("wrote mammoth-monthly-v2.html | leads section:", "present" if LEADS else "MISSING")
print("em dashes:", "—" in HTML)
