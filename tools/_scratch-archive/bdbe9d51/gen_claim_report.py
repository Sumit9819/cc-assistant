# -*- coding: utf-8 -*-
"""Claim-corrections work update. Structured to answer the client's own audit point
by point, then list every page changed. URL lists come from report_data.json."""
import json, io, sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
D = json.load(open("report_data.json", encoding="utf-8"))

def short(pid):
    u = D[str(pid)]["url"].replace("https://", "")
    return "mammothmachinery.ca (home page)" if u.rstrip("/") == "mammothmachinery.ca" else u
def title(pid):
    return D[str(pid)]["title"]

MACHINES = [1319,1476,1518,1532,1551,1568,1585,1602,1616,1630,1648,1668,1684,
            1699,1710,1993,2089,2134,2396,3358,3994]
CATS3 = [37, 41, 45]
GUIDES_FIN = [3264, 3635, 3658, 3678, 3771, 3862, 3890, 3903]
GUIDES_WAR = [4012, 4023, 4026, 4031]
assert len(MACHINES) == 21

def urlgrid(pids, cols=3):
    return (f'<div class="grid" style="grid-template-columns:repeat({cols},1fr)">'
            + "".join(f"<div>{short(p)}</div>" for p in pids) + "</div>")

def urlrows(pids):
    return "".join(f'<tr><td class="pg">{title(p)}</td><td class="u2">{short(p)}</td></tr>' for p in pids)

def ba(before, after):
    return (f'<div class="ba"><div><span class="lbl">BEFORE</span>{before}</div>'
            f'<div class="after"><span class="lbl">AFTER</span>{after}</div></div>')

def claimrows(rows):
    out = ""
    for was, now in rows:
        out += f'<tr><td class="was">{was}</td><td class="now">{now}</td></tr>'
    return out

CSS = """
  * { box-sizing: border-box; margin: 0; padding: 0; }
  @page { size: A4; margin: 12mm; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1c1c1c; font-size: 9.5pt; line-height: 1.5; }
  .top { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 3px solid #141414; padding-bottom: 9px; }
  .brand { font-size: 14pt; font-weight: 800; letter-spacing: 0.5px; }
  .brand span { color: #E8890C; }
  .docid { font-family: Consolas, monospace; font-size: 8pt; color: #777; }
  .sub { margin: 7px 0 12px; color: #555; font-size: 9pt; }
  .banner { background: #141414; color: #fff; font-family: Consolas, monospace; font-size: 8.5pt; letter-spacing: 1.2px; padding: 8px 12px; border-radius: 4px; border-left: 4px solid #01B51B; }
  .intro { margin: 12px 0 4px; font-size: 9.5pt; }
  .sec { margin-top: 17px; }
  .secnum { font-family: Consolas, monospace; font-size: 11pt; font-weight: 700; color: #E8890C; margin-right: 8px; }
  .sech { display: inline; font-size: 12pt; }
  .item { margin-top: 13px; page-break-inside: avoid; }
  .u { font-family: Consolas, monospace; font-size: 7.8pt; color: #17692f; word-break: break-all; }
  .h { font-size: 10.5pt; font-weight: 700; margin: 2px 0 5px; }
  .item p, .sec > p { margin-bottom: 5px; }
  .ba { display: flex; gap: 10px; margin-top: 8px; }
  .ba > div { flex: 1; border: 1px solid #e2e2e2; border-radius: 5px; padding: 7px 10px; font-size: 8.7pt; }
  .ba .lbl { font-family: Consolas, monospace; font-size: 7pt; letter-spacing: 1.2px; color: #999; display: block; margin-bottom: 3px; }
  .ba .after { border-color: #01B51B; background: #f7fdf8; }
  .ba .after .lbl { color: #17692f; }
  .grid { display: grid; gap: 2px 14px; margin-top: 8px; font-family: Consolas, monospace; font-size: 7.4pt; color: #17692f; }
  table { width: 100%; border-collapse: collapse; font-size: 8.8pt; margin-top: 9px; }
  th { text-align: left; font-family: Consolas, monospace; font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #666; border-bottom: 2px solid #141414; padding: 4px 8px 4px 0; }
  td { padding: 6px 8px 6px 0; border-bottom: 1px solid #eee; vertical-align: top; }
  td.pg { font-weight: 600; width: 44%; }
  td.u2 { font-family: Consolas, monospace; font-size: 7.6pt; color: #17692f; word-break: break-all; }
  td.was { width: 47%; color: #8a2b16; }
  td.now { padding-left: 12px; border-left: 3px solid #01B51B; }
  .note { background:#141414; color:#fff; border-radius:5px; padding:10px 14px; margin-top:16px; font-size:9.2pt; page-break-inside: avoid; }
  .open { border-left: 3px solid #E8890C; padding-left: 13px; margin-top: 13px; page-break-inside: avoid; }
  .fixed { font-family: Consolas, monospace; font-size: 7pt; letter-spacing: 1px; color: #17692f; text-transform: uppercase; }
  .fn { font-size: 7.3pt; color: #888; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 7px; line-height: 1.45; }
  .footer { margin-top: 10px; font-family: Consolas, monospace; font-size: 7.3pt; color: #999; display: flex; justify-content: space-between; }
"""

ABOUT_ROWS = [
 ('Google listing: "family-owned heavy equipment manufacturer <b>since 1984</b>"',
  'Year removed. The listing now reads "Canada\'s family-owned compact equipment manufacturer."'),
 ('"what <b>four decades</b> of Canadian manufacturing excellence looks like"',
  'Now "31 years of Canadian equipment experience", matching the "over 31 years" already on the home page.'),
 ('"<b>three</b> state-of-the-art assembly hubs in Canada"',
  'Now "our assembly facility in Burlington, Ontario".'),
 ('"a network of <b>over 100</b> dedicated service partners"',
  'Number removed. Now "a dealer and service network reaching from Ontario to the Maritimes".'),
 ('Google listing: builds machines for "<b>mining, forestry</b> &amp; construction"',
  'Now "mini excavators, skid steers, dumpers and wheel loaders in Burlington, Ontario".'),
 ('"next-generation <b>electric and autonomous</b> heavy equipment fleet"',
  'Autonomous removed. Now "our electric equipment range, including the eTT900 electric mini dumper".'),
 ('"proud member of <b>Canadian Manufacturers &amp; Exporters</b>"',
  'Sentence removed entirely.'),
]

HTML = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mammoth Machinery, Claim Corrections, August 2026</title>
<style>{CSS}</style>
</head>
<body>

<div class="top">
  <div class="brand">GROWTH<span>BOSS</span></div>
  <div class="docid">WORK UPDATE &nbsp;MM-2026-08-C1</div>
</div>
<div class="sub"><b>Mammoth Machinery</b> &nbsp;&middot;&nbsp; Claim corrections, August 28 to 29, 2026 &nbsp;&middot;&nbsp; mammothmachinery.ca</div>

<div class="banner">42 PAGES &nbsp;&middot;&nbsp; 104 EDITS &nbsp;&middot;&nbsp; 73 CLAIMS CORRECTED &nbsp;&middot;&nbsp; ALL LIVE AND CHECKED</div>

<div class="intro">Your review listed statements that were not accurate, and statements that contradicted other statements on the same website. We went through all 53 published pages. The claims were repeated far more widely than the review found: the same financing figures appeared on 36 pages. Sections 1 and 2 answer the review point by point. Sections 3 to 5 list every page we changed. Section 6 is what we did not change and why.</div>

<div class="intro" style="margin-top:8px"><b>Where the site stands now.</b> Every figure your review questioned has been removed from every page except the financing page, which you had asked us to leave alone. We re-read all 42 pages after the changes to confirm it. In total: 73 claims corrected, 3 missing disclosures added, and 28 pieces of wording brought into line, all live.</div>

<div class="sec">
  <span class="secnum">01</span><h2 class="sech">The About page: seven claims</h2>
  <div class="u">{short(3291)}</div>
  <p>All seven are corrected. Where we could not verify a replacement figure, we removed the claim rather than putting a different number in its place.</p>
  <table><tr><th>What the page said</th><th>What it says now</th></tr>{claimrows(ABOUT_ROWS)}</table>
</div>

<div class="sec">
  <span class="secnum">02</span><h2 class="sech">The three contradictions</h2>
  <p>All three are resolved on the live website. Two of them you had already corrected before our pass; we checked them rather than assuming, and we have said below which is which.</p>

  <div class="item">
    <div class="h">1. Dealer coverage <span class="fixed">&nbsp;&nbsp;resolved, corrected on your side</span></div>
    <p>The home page question section had claimed the network spans Ontario, Quebec and Western Canada. Your dealer list is six Ontario locations plus Nova Scotia and New Brunswick, and no Quebec.</p>
    {ba('Our network spans Ontario, Quebec, and Western Canada.',
        'Our network runs from Ontario to the Maritimes, six locations across southern Ontario plus certified service partners in Nova Scotia and New Brunswick, and we are actively adding dealers.')}
    <p>The Find a Dealer page and the home page now say the same thing. One guide still described the network as covering Western Canada; we corrected that, and it is listed in section 5.</p>
  </div>

  <div class="item">
    <div class="h">2. The warranty and the engine <span class="fixed">&nbsp;&nbsp;resolved, half by you and half by us</span></div>
    <p>The engine is the powertrain. The home page promised powertrain coverage in two places while the warranty page correctly stated that engines are not warranted by Mammoth. A buyer refused a Kubota engine claim could have pointed at the home page.</p>
    <p>You had added the engine carve-out to the home page question section. The second mention, in the home page warranty block, was still live and we corrected it, along with six other pages carrying the same wording.</p><p>Removing the word was only half the job. Three pages were then accurate but silent: they listed what the warranty covers and named the engine brand in the spec table, without saying who warrants the engine. We have added that sentence to all three. Section 4 has both halves.</p>
    {ba('backed by a 5-Year / 3,000-Hour Warranty covering powertrain, hydraulics, and structure',
        'backed by a 5-Year / 3,000-Hour Warranty covering hydraulics, chassis and structural components')}
  </div>

  <div class="item">
    <div class="h">3. When the warranty starts <span class="fixed">&nbsp;&nbsp;resolved, corrected by us</span></div>
    <p>Section 07/08 of the warranty page requires registration within 30 days, and says late registration dates coverage back to manufacture. The button text at the bottom of that same page told the customer the opposite.</p>
    {ba('Every Mammoth machine ships with this warranty active from day one.',
        'Every Mammoth machine is covered from delivery, and registering within 30 days secures the full 5-year term.')}
    <p>The home page question section now also states the 30 day requirement, which it previously did not mention at all.</p>
    <div class="u">{short(1986)}</div>
  </div>
</div>

<div class="sec">
  <span class="secnum">03</span><h2 class="sech">Financing rates, terms and approval rate: 36 pages</h2>
  <p>This was the largest problem and the review did not reach the scale of it. Thirty-six pages advertised an interest rate, a loan length, or an approval percentage. Every figure has been removed from these pages. The financing page itself was left alone, so those numbers now appear in one place instead of thirty-seven.</p>

  <div class="item">
    <div class="h">21 machine pages: the same sentence on every model</div>
    {ba("Financing is available from 1.99% to 0% APR.", "Financing is available on approved credit.")}
    {urlgrid(MACHINES)}
  </div>

  <div class="item">
    <div class="h">3 category pages: the heading above the financing block</div>
    {ba("Financing Available From 1.99% to 0% APR", "Financing Available")}
    {urlgrid(CATS3)}
  </div>

  <div class="item">
    <div class="h">Full Size Track Loaders</div>
    {ba("Financing As Low As 0% APR", "Financing Available")}
    <div class="u">{short(47)}</div>
  </div>

  <div class="item">
    <div class="h">Wheel Loaders and Mini Skid Steers: four claims each, including the approval rate</div>
    {ba("Financing From 1.99% to 0% APR<br>Flexible terms up to 72 months<br>98% approval rate<br>Financing is available with terms up to 72 months",
        "Financing Available<br>Flexible terms<br>Fast approvals<br>Financing is available on approved credit")}
    <div class="u">{short(39)}<br>{short(43)}</div>
  </div>

  <div class="item">
    <div class="u">{short(11)}</div>
    <div class="h">Home page: three mentions, two of them in the question section</div>
    {ba("equipment financing from <b>1.99% to 0% APR</b> with flexible terms up to 72 months on approved credit<br>financing from 1.99% to 0% APR, and a parts-and-service support network<br>available with financing from 1.99% to 0% APR",
        "equipment financing with flexible terms on approved credit<br>equipment financing, and a parts-and-service support network<br>available with equipment financing")}
  </div>

  <div class="item">
    <div class="h">8 guides: closing paragraphs and sidebar labels</div>
    {ba("flexible financing from 1.99% to 0% O.A.C.<br>Financing runs up to 72 months O.A.C.<br>Financing up to 72 Months (sidebar label)",
        "flexible financing on approved credit.<br>Financing is available on approved credit.<br>Financing Options (sidebar label)")}
    <table><tr><th>Guide</th><th>Address</th></tr>{urlrows(GUIDES_FIN)}</table>
  </div>

  <div class="item">
    <div class="h">And then one phrase, used consistently</div>
    <p>Taking the rates out left a second problem behind. Because each sentence was edited where it stood, the site ended up saying the same thing fifteen different ways, from a bare "Financing is available." on the machine pages to "Financing Options" in a sidebar. We have settled it into three forms, one for each place it appears.</p>
    <table><tr><th>Where it appears</th><th>What it now reads</th></tr>
      <tr><td class="pg">Price paragraph, 21 machine pages</td><td class="now">"Financing is available on approved credit."</td></tr>
      <tr><td class="pg">Pricing question, Wheel Loaders and Mini Skid Steers</td><td class="now">"Financing is available on approved credit, making..."</td></tr>
      <tr><td class="pg">Sidebars and related links, 8 places in the guides</td><td class="now">"Financing Options"</td></tr>
      <tr><td class="pg">Section heading, 6 category pages</td><td class="now">"Financing Available"</td></tr>
    </table>
    <p><b>"On approved credit" is the important part.</b> It promises no rate and no term, and it tells the buyer there is a credit decision, which is true and protects you. It is also the phrase this industry already uses, so it reads as normal rather than evasive.</p>
    <p>One page needed two edits for one sentence. On Wheel Loaders, that answer exists both in the text a visitor reads and in the structured data Google reads to build a rich result. We changed both together, so your page and your Google listing cannot drift apart.</p>
  </div>
</div>

<div class="sec">
  <span class="secnum">04</span><h2 class="sech">Warranty wording: 8 pages, and 3 new disclosures</h2>
  <p>Eight pages said the warranty covers the powertrain. The wording now names the parts Mammoth actually covers, and leaves the engine to Kubota and Yanmar, which is what your warranty page has always said.</p>
  {ba("5 years or 3,000 hours covering powertrain, hydraulics and structure",
      "5 years or 3,000 hours covering hydraulics, chassis and structure")}
  <table><tr><th>Page</th><th>Address</th></tr>
    <tr><td class="pg">Home</td><td class="u2">{short(11)}</td></tr>
    <tr><td class="pg">Wheel Loaders</td><td class="u2">{short(39)}</td></tr>
    <tr><td class="pg">Mini Skid Steers</td><td class="u2">{short(43)}</td></tr>
    <tr><td class="pg">Warranty</td><td class="u2">{short(1986)}</td></tr>
    {urlrows(GUIDES_WAR)}
  </table>
  <p>On the warranty page itself, the panel headed "Powertrain &middot; Hydraulics &middot; Structure Covered" now reads "Hydraulics &middot; Chassis &middot; Structure Covered".</p>

  <div class="item">
    <div class="h">The sentence we added, and where</div>
    <p>Five pages already told the reader who covers the engine: the home page, the warranty page, and three of the guides. Three did not. They were no longer wrong, but a buyer comparing warranties on those pages had no way to know the engine is excluded. Each now carries one sentence, using the engine brand named in that page's own specification table.</p>
    <table><tr><th>Page</th><th>Sentence added</th></tr>
      <tr><td class="pg">Wheel Loaders<br><span class="u">{short(39)}</span></td><td class="now">"The engine is covered by Cummins under its own warranty."</td></tr>
      <tr><td class="pg">Mini Skid Steers<br><span class="u">{short(43)}</span></td><td class="now">"Engines are covered by Yanmar or Kubota under their own warranty."</td></tr>
      <tr><td class="pg">Guide: landscaping jobs<br><span class="u">{short(4026)}</span></td><td class="now">"The engine is covered by Yanmar or Kubota under its own warranty."</td></tr>
    </table>
    <p>Every page on the website that now states what the warranty covers also states who covers the engine.</p>
  </div>
</div>

<div class="sec">
  <span class="secnum">05</span><h2 class="sech">Dealer coverage in a guide</h2>
  <div class="item">
    <div class="u">{short(3635)}</div>
    <div class="h">Wheeled vs Tracked Mini Dumper guide</div>
    {ba("our dealer network sees the same pattern across Ontario and Western Canada",
        "our dealer network sees the same pattern from Ontario to the Maritimes")}
  </div>
</div>

<div class="sec">
  <span class="secnum">06</span><h2 class="sech">Not changed: four decisions for you</h2>

  <div class="open">
    <div class="h">1. The financing page still carries the numbers</div>
    <p>It still shows "98%", "1.99% to 0% O.A.C", "Terms up to 72 months", and the line "Flexible terms, fast approvals, and rates as low as 1.99% to 0% O.A.C." We left these because you had previously asked for them to stay.</p>
    <p><b>This is now the only page on the website carrying those figures.</b> That turns thirty-seven edits into one decision. If you can show the lender terms behind them, they can stay. If not, they should come off this page too.</p>
    <div class="u">mammothmachinery.ca/financing/</div>
  </div>

  <div class="open">
    <div class="h">2. Two claims about where you reach</div>
    <p>Neither of these was in your review, and neither is provably wrong, because where your customers are is not the same as where your dealers are. But they are the next two lines an auditor reads after the dealer list, so you should decide on them.</p>
    <table><tr><th>Page</th><th>The claim</th></tr>
      <tr><td class="pg">Home page</td><td>"trusted by contractors, landscapers, and crews <b>from coast to coast</b>"</td></tr>
      <tr><td class="pg">About page</td><td>"proudly serves customers <b>across North America</b>"</td></tr>
    </table>
    <p>If you have sold into Western Canada or the United States, both can stand. If your customers are effectively Ontario to the Maritimes like your dealers, tell us and we will bring these two lines in line with the rest of the site.</p>
  </div>

  <div class="open">
    <div class="h">3. The founding year</div>
    <p>The site had carried three different figures: "since 1984", "four decades", and "over 31 years". We removed the first two and kept 31 years, because that is the one the home page already used. <b>Please confirm the actual founding year</b> so it can be stated once, in one form, on every page.</p>
  </div>

  <div class="open">
    <div class="h">4. Whether to publish the dealer count</div>
    <p>We removed "over 100 service partners" and replaced it with the geography rather than the real number. If you are comfortable publishing it, the About page can say eight locations, matching what the home page and the Find a Dealer page already describe. Numbers convert better than vague coverage claims, provided the number is true.</p>
  </div>
</div>

<div class="note"><b>One correction on our side.</b> Four of the guides we wrote for you this month repeated the "powertrain" warranty wording, because we took it from pages that already carried it. That was our error. It is fixed in this same pass and listed in section 4 with everything else, rather than left out.</div>

<div class="fn">Every page was read again after the changes, checking the stored page content rather than the version a visitor sees from cache, so nothing is reported as fixed on the strength of a cached copy. Two pages needed a second correction: on the Wheel Loaders and Mini Skid Steers question sections the first pass removed the interest rate but left "72 months" in the same sentence, and both were corrected on August 28, 2026. The dealer figures quoted are your own: six southern Ontario locations plus Nova Scotia and New Brunswick, as stated on the home page. The 53 published addresses were taken from the website's own sitemap on August 26, 2026. Final check, August 29, 2026: across all 42 pages the only remaining instance of any questioned figure is on the financing page, and the only remaining use of the word "powertrain" anywhere is the home page sentence stating that the engine and powertrain are warranted by Kubota or Yanmar rather than by Mammoth, which is correct. The financing wording was checked the same way: 21 of 21 machine pages, both pricing questions, and all 8 sidebar labels carry the agreed phrasing, and the Wheel Loaders answer and its structured data were compared word for word and match exactly.</div>

<div class="footer"><div>GROWTHBOSS &middot; WORK UPDATE</div><div>MM-2026-08-C1 &middot; CONFIDENTIAL</div></div>

</body>
</html>
"""

io.open("mammoth-claim-corrections.html", "w", encoding="utf-8").write(HTML)
print("wrote mammoth-claim-corrections.html")
print("em dashes in report prose:", "—" in HTML)
