# -*- coding: utf-8 -*-
"""Build the Social Creative Target List as a native Word document."""

from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

# ---------- palette (mirrors the artifact) ----------
ACCENT = RGBColor(0x0B, 0x6B, 0x57)
INK    = RGBColor(0x14, 0x18, 0x1A)
INK2   = RGBColor(0x4A, 0x54, 0x50)
INK3   = RGBColor(0x79, 0x83, 0x7E)
GO     = RGBColor(0x1F, 0x6B, 0x3A)
WARN   = RGBColor(0x8A, 0x4B, 0x06)
STOP   = RGBColor(0x9C, 0x2C, 0x22)

SERIF = "Georgia"
SANS  = "Calibri"
MONO  = "Consolas"

FILL_HEAD  = "E8EBE8"
FILL_PANEL = "F4F6F4"
FILL_WARN  = "F7EBDA"
FILL_STOP  = "F7E4E1"

doc = Document()

# ---------- page setup ----------
s = doc.sections[0]
s.top_margin = Cm(2.0)
s.bottom_margin = Cm(2.0)
s.left_margin = Cm(2.2)
s.right_margin = Cm(2.2)

normal = doc.styles["Normal"]
normal.font.name = SANS
normal.font.size = Pt(10)
normal.font.color.rgb = INK
normal.paragraph_format.space_after = Pt(6)
normal.paragraph_format.line_spacing = 1.15


# ---------- helpers ----------
def shade(cell, hexfill):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hexfill)
    tcPr.append(shd)


def cell_margins(cell, top=60, bottom=60, left=90, right=90):
    tcPr = cell._tc.get_or_add_tcPr()
    mar = OxmlElement("w:tcMar")
    for tag, val in (("top", top), ("left", left), ("bottom", bottom), ("right", right)):
        el = OxmlElement("w:" + tag)
        el.set(qn("w:w"), str(val))
        el.set(qn("w:type"), "dxa")
        mar.append(el)
    tcPr.append(mar)


def table_borders(table, color="DFE4E1", size=4, inside_h=True):
    tbl = table._tbl
    tblPr = tbl.tblPr
    borders = OxmlElement("w:tblBorders")
    edges = ["top", "left", "bottom", "right", "insideH", "insideV"]
    for edge in edges:
        el = OxmlElement("w:" + edge)
        if edge == "insideV" or edge in ("left", "right"):
            el.set(qn("w:val"), "none")
            el.set(qn("w:sz"), "0")
        elif edge == "insideH" and not inside_h:
            el.set(qn("w:val"), "none")
            el.set(qn("w:sz"), "0")
        else:
            el.set(qn("w:val"), "single")
            el.set(qn("w:sz"), str(size))
            el.set(qn("w:color"), color)
        el.set(qn("w:space"), "0")
        borders.append(el)
    tblPr.append(borders)


def no_borders(table):
    tblPr = table._tbl.tblPr
    borders = OxmlElement("w:tblBorders")
    for edge in ["top", "left", "bottom", "right", "insideH", "insideV"]:
        el = OxmlElement("w:" + edge)
        el.set(qn("w:val"), "none")
        el.set(qn("w:sz"), "0")
        borders.append(el)
    tblPr.append(borders)


def left_accent(cell, color):
    """Thick coloured left border on a single cell -- the callout look."""
    tcPr = cell._tc.get_or_add_tcPr()
    borders = OxmlElement("w:tcBorders")
    el = OxmlElement("w:left")
    el.set(qn("w:val"), "single")
    el.set(qn("w:sz"), "24")
    el.set(qn("w:color"), color)
    el.set(qn("w:space"), "0")
    borders.append(el)
    tcPr.append(borders)


def repeat_header(row):
    """Mark a table row as a header that repeats on every page."""
    trPr = row._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    trPr.append(el)


def cant_split(row):
    """Stop a row splitting across a page break."""
    trPr = row._tr.get_or_add_trPr()
    trPr.append(OxmlElement("w:cantSplit"))


def para(text="", size=10, bold=False, color=INK, font=SANS, space_after=6,
         space_before=0, italic=False, align=None, container=None):
    p = (container or doc).add_paragraph()
    p.paragraph_format.space_after = Pt(space_after)
    p.paragraph_format.space_before = Pt(space_before)
    if align:
        p.alignment = align
    if text:
        r = p.add_run(text)
        r.font.name = font
        r.font.size = Pt(size)
        r.font.bold = bold
        r.font.italic = italic
        r.font.color.rgb = color
    return p


def rich(p, parts):
    """parts = list of (text, {opts})"""
    for text, o in parts:
        r = p.add_run(text)
        r.font.name = o.get("font", SANS)
        r.font.size = Pt(o.get("size", 10))
        r.font.bold = o.get("bold", False)
        r.font.italic = o.get("italic", False)
        r.font.color.rgb = o.get("color", INK)
    return p


def hrule(color="14181A", size=12, space_before=10, space_after=4):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(space_before)
    p.paragraph_format.space_after = Pt(space_after)
    pPr = p._p.get_or_add_pPr()
    borders = OxmlElement("w:pBdr")
    el = OxmlElement("w:bottom")
    el.set(qn("w:val"), "single")
    el.set(qn("w:sz"), str(size))
    el.set(qn("w:color"), color)
    el.set(qn("w:space"), "1")
    borders.append(el)
    pPr.append(borders)
    return p


def eyebrow(text, color=ACCENT):
    para(text.upper(), size=7.5, bold=True, color=color, font=MONO, space_after=3)


def h2(text):
    r = hrule("14181A", 12, space_before=16, space_after=6)
    r.paragraph_format.keep_with_next = True
    p = para(text, size=17, bold=False, color=INK, font=SERIF, space_after=4)
    p.paragraph_format.keep_with_next = True
    return p


def h3(text, who=None):
    p = para(text, size=12.5, bold=True, color=INK, font=SERIF,
             space_after=2, space_before=10)
    p.paragraph_format.keep_with_next = True
    if who:
        r = p.add_run("   " + who)
        r.font.name = MONO
        r.font.size = Pt(7.5)
        r.font.bold = False
        r.font.color.rgb = INK3
    return p


def note(text):
    p = para(text, size=9.5, color=INK2, space_after=8)
    p.paragraph_format.keep_with_next = True
    return p


def callout(fill, border_color, blocks):
    """blocks = list of (heading|None, body)"""
    t = doc.add_table(rows=1, cols=1)
    t.alignment = WD_TABLE_ALIGNMENT.LEFT
    no_borders(t)
    c = t.cell(0, 0)
    shade(c, fill)
    left_accent(c, border_color)
    cell_margins(c, top=140, bottom=140, left=200, right=180)
    c.paragraphs[0].text = ""
    first = True
    for head, body in blocks:
        if head:
            p = c.paragraphs[0] if first else c.add_paragraph()
            p.paragraph_format.space_after = Pt(2)
            p.paragraph_format.space_before = Pt(0 if first else 8)
            r = p.add_run(head.upper())
            r.font.name = MONO
            r.font.size = Pt(8)
            r.font.bold = True
            r.font.color.rgb = INK
            first = False
            bp = c.add_paragraph()
        else:
            bp = c.paragraphs[0] if first else c.add_paragraph()
            first = False
        bp.paragraph_format.space_after = Pt(0)
        rich(bp, body if isinstance(body, list) else [(body, {"size": 9.5, "color": INK2})])
    doc.add_paragraph().paragraph_format.space_after = Pt(2)
    return t


def kw_table(rows, last_col_header="Format"):
    t = doc.add_table(rows=1, cols=4)
    t.alignment = WD_TABLE_ALIGNMENT.LEFT
    table_borders(t)
    widths = [Cm(5.6), Cm(2.4), Cm(1.5), Cm(6.6)]
    hdr = ["Keyword", "US vol/mo", "Diff", last_col_header]
    for i, h in enumerate(hdr):
        c = t.cell(0, i)
        shade(c, FILL_HEAD)
        cell_margins(c, top=70, bottom=70)
        p = c.paragraphs[0]
        p.paragraph_format.space_after = Pt(0)
        if i in (1, 2):
            p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
        r = p.add_run(h.upper())
        r.font.name = MONO
        r.font.size = Pt(7)
        r.font.bold = True
        r.font.color.rgb = INK3

    for kw, vol, diff, fmt in rows:
        cells = t.add_row().cells
        for i, val in enumerate((kw, vol, diff, fmt)):
            c = cells[i]
            cell_margins(c, top=60, bottom=60)
            p = c.paragraphs[0]
            p.paragraph_format.space_after = Pt(0)
            if i in (1, 2):
                p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
            r = p.add_run(str(val))
            if i == 0:
                r.font.name = SANS
                r.font.size = Pt(9.5)
                r.font.bold = True
                r.font.color.rgb = INK
            elif i in (1, 2):
                r.font.name = MONO
                r.font.size = Pt(8.5)
                is_easy = (i == 2 and int(diff) < 20)
                r.font.bold = is_easy
                r.font.color.rgb = GO if is_easy else INK
            else:
                r.font.name = SANS
                r.font.size = Pt(9)
                r.font.color.rgb = INK2

    for row in t.rows:
        cant_split(row)
        for i, c in enumerate(row.cells):
            c.width = widths[i]
    repeat_header(t.rows[0])
    doc.add_paragraph().paragraph_format.space_after = Pt(2)
    return t


# ================= MASTHEAD =================
eyebrow("Creative targeting  ·  2 September 2026")
para("Social Creative Target List", size=27, color=INK, font=SERIF, space_after=8)
para(
    "Topics these four clinics could credibly post about, sized by national US search "
    "demand rather than by what any one location already ranks for. Social has no "
    "catchment, so nothing here is geo-limited.",
    size=12, color=INK2, font=SERIF, space_after=12,
)

# provenance strip
hrule("14181A", 12, space_before=2, space_after=6)
pt = doc.add_table(rows=2, cols=4)
no_borders(pt)
prov = [
    ("Source", "Ubersuggest keyword data"),
    ("Market", "United States (loc 2840)"),
    ("Volume", "Est. searches / month"),
    ("Diff", "SEO difficulty, 0-100"),
]
for i, (k, v) in enumerate(prov):
    c1 = pt.cell(0, i)
    c1.paragraphs[0].paragraph_format.space_after = Pt(1)
    r = c1.paragraphs[0].add_run(k.upper())
    r.font.name = MONO
    r.font.size = Pt(7)
    r.font.color.rgb = INK3
    c2 = pt.cell(1, i)
    c2.paragraphs[0].paragraph_format.space_after = Pt(0)
    r = c2.paragraphs[0].add_run(v)
    r.font.name = SANS
    r.font.size = Pt(9)
    r.font.color.rgb = INK
hrule("CDD4D0", 6, space_before=6, space_after=12)


# ================= THE REFRAME =================
h2("Read the difficulty column backwards")
callout(FILL_PANEL, "0B6B57", [
    ("On social, high difficulty is not a reason to skip a topic", [
        ("SEO difficulty measures how hard it is to outrank established domains in Google. "
         "On a feed there is no ranking to lose - a post competes on the hook, not on domain "
         "authority. That means the head terms an SEO would tell you to avoid are exactly the "
         "ones with the most creative headroom: ", {"size": 9.5, "color": INK2}),
        ("semaglutide (368,000/mo, difficulty 71)", {"size": 9.5, "bold": True}),
        (", ", {"size": 9.5, "color": INK2}),
        ("microneedling (201,000, 75)", {"size": 9.5, "bold": True}),
        (", ", {"size": 9.5, "color": INK2}),
        ("botox (165,000, 72)", {"size": 9.5, "bold": True}),
        (", ", {"size": 9.5, "color": INK2}),
        ("insulin resistance (110,000, 72)", {"size": 9.5, "bold": True}),
        (".", {"size": 9.5, "color": INK2}),
    ]),
    (None, [
        ("The difficulty column is still marked below, because a low number means the same "
         "asset can plausibly earn search traffic too. Treat it as a bonus, not a filter. "
         "Values under 20 are shown in ", {"size": 9.5, "color": INK2}),
        ("green", {"size": 9.5, "bold": True, "color": GO}),
        (".", {"size": 9.5, "color": INK2}),
    ]),
])


# ================= LANE 1 =================
h2("Emergency & urgent care lane")
note("For the three ER accounts. Grouped by the creative format the keyword implies, "
     "because that is what determines whether it can be made at all.")

h3("Photo & visual comparison", "ER of Irving · Lufkin · White Rock")
note("People search these because they want to look at something. That maps directly onto "
     "a feed post and almost nothing else does as cleanly.")
kw_table([
    ("cellulitis images", "22,200", 50, "Photo card: what skin infection actually looks like"),
    ("images of cellulitis", "18,100", 43, "Same asset, alternate caption"),
    ("cellulitis is contagious", "14,800", 18, "Myth card - it isn't. Strong comment driver"),
    ("cloudy urine dehydration", "12,100", 16, "Colour-chart graphic"),
    ("bug bite cellulitis", "12,100", 42, "Bite vs. infection, side by side"),
    ("cellulitis from a bug bite", "9,900", 32, "Same asset, second cut"),
    ("what does cellulitis look like", "8,100", 51, "The definitive photo post"),
    ("cellulitis when to worry pictures", "6,600", 33, "Escalation ladder - the ER-relevant version"),
    ("what does a sprained ankle look like", "4,400", 24, "Bruising pattern photo"),
    ("urine dehydration chart", "2,900", 27, "Saveable reference graphic"),
])

h3("Timeline & healing stages", "strong for carousels and short video")
note("Multi-frame by nature. One asset covers several keywords and holds attention past "
     "the first card.")
kw_table([
    ("bone bruise", "33,100", 28, "Week-by-week recovery carousel"),
    ("how long would a sprained ankle take to heal", "22,200", 14, "Timeline card with real ranges"),
    ("recovery time of sprained ankle", "9,900", 36, "Same asset, second cut"),
    ("cellulitis healing stages", "4,400", 40, "Day-by-day with the 'call someone' marker"),
    ("cellulitis healing stages pictures", "3,600", 34, "Photo version of the above"),
])

h3("Myth-bust & correction", "highest engagement format in the set")
note("A widely believed wrong thing, corrected by a clinician, reliably out-performs a "
     "neutral explainer. All of these have a genuinely wrong popular answer.")
kw_table([
    ("sport drinks for dehydration", "60,500", 49, "Sugar load vs. actual electrolyte need"),
    ("electrolyte drinks for dehydration", "60,500", 26, "What the label doesn't tell you"),
    ("dehydration from coffee", "14,800", 18, "Coffee is not net-dehydrating at normal intake"),
    ("how to heal a sprained ankle overnight", "9,900", 14, "You can't - here's what 24 hours should look like"),
    ("is gatorade good for dehydration", "4,400", 20, "Direct answer post"),
    ("will cellulitis go away on its own", "2,400", 47, "No - and the delay is the danger"),
], last_col_header="The correction")

h3("\"Is this serious?\" decision hooks", "closest to the actual ER product")
note("These are people mid-decision. The chest-pain cluster is large, low-difficulty and "
     "almost entirely unclaimed by clinic accounts.")
kw_table([
    ("aching chest pain left side", "40,500", 21, "Cardiac vs. muscular vs. reflux, one card"),
    ("chest pain on the right side of chest", "40,500", 20, "Why side matters less than people think"),
    ("broke or sprained ankle", "27,100", 15, "Weight-bearing test explained in 15 seconds"),
    ("anxiety vs chest pain", "14,800", 12, "Enormous topic, almost no clinical voice on it"),
    ("will dehydration cause high blood pressure", "14,800", 33, "Direct answer post"),
    ("sprained ankle signs", "14,800", 27, "Checklist card"),
    ("chest pain with breathing", "12,100", 11, "Pleuritic pain explainer"),
    ("can anxiety cause chest pain", "12,100", 28, "Pairs with the row above"),
    ("chest pain gas", "9,900", 17, "The reassuring one - and when it isn't"),
    ("sprained ankle vs broken bone", "2,900", 17, "Comparison card"),
    ("left arm pain without chest pain", "2,400", 35, "High-anxiety query, high save rate"),
])

h3("Curiosity & surprising-fact", "reach plays, low commercial intent")
note("These travel furthest and convert least. Use them to grow the account, not to fill "
     "the schedule.")
kw_table([
    ("headache and dehydration", "33,100", 51, "The most common cause nobody checks"),
    ("dizzy from dehydration", "33,100", 21, "Standing-up dizziness explainer"),
    ("white tongue dehydration", "6,600", 32, "Unexpected sign, very shareable"),
    ("can dehydration cause uti", "4,400", 23, "Direct answer, strong female-audience reach"),
    ("chest pain after gym", "2,400", 6, "Fitness crossover audience"),
    ("signs of dehydration in dogs", "2,400", 18, "Pet-owner reach. Off-topic but it travels"),
    ("can dehydration cause seizures", "1,900", 20, "Severity escalation post"),
], last_col_header="Hook")


# ================= LANE 2 =================
doc.add_page_break()
h2("Wellness & aesthetics lane")
note("For Irving Health & Wellness Clinic. Bigger volumes, more native to social, and an "
     "unusual amount of high-volume / low-difficulty overlap - several of these can win in "
     "search as well as on the feed.")

h3("Before & after and visible-result posts", "the core aesthetics format")
note("Note the difficulty scores: several of the largest terms here are genuinely easy, "
     "which is rare.")
kw_table([
    ("lip filler", "90,500", 12, "Head term, and unusually winnable"),
    ("botox on masseter", "74,000", 15, "Jaw slimming. Biggest easy term in the set"),
    ("botox flip lip", "74,000", 29, "Lip flip explainer + result"),
    ("microneedling before after", "60,500", 17, "Split-frame result post"),
    ("microneedling before and after", "60,500", 32, "Same asset, alternate phrasing"),
    ("botox after and before", "33,100", 16, "Result grid"),
    ("before & after lip filler", "22,200", 14, "Result grid"),
    ("botox eyebrow lift", "14,800", 11, "Very low difficulty for the volume"),
    ("jaw slimming botox", "8,100", 12, "Pairs with masseter above"),
    ("hooded eyes from botox", "8,100", 13, "Cautionary + corrective"),
    ("botox bunny line", "6,600", 14, "Niche, named, memorable"),
    ("botox for gummy smiles", "6,600", 26, "Strong visual transformation"),
])

h3("Stages, downtime and aftercare", "the trust-building format")
note("What people actually worry about before booking. Answering it well is the difference "
     "between reach and bookings.")
kw_table([
    ("how long will botox last", "22,200", 12, "Direct answer card"),
    ("how long does lip filler last", "18,100", 14, "Direct answer card"),
    ("microneedling aftercare", "8,100", 27, "Saveable checklist"),
    ("does microneedling hurt", "4,400", 13, "Honest answer beats a polished one"),
    ("downtime for microneedling", "2,400", 12, "Day 0 to day 7"),
    ("microneedling healing stages", "2,400", 10, "Lowest difficulty in the whole list"),
    ("microneedling day by day", "2,400", 12, "Native carousel structure"),
    ("1 ml lip filler swelling stages", "1,900", 18, "Sets expectations, reduces panic calls"),
    ("does lip filler hurt", "1,900", 12, "Direct answer card"),
])

h3("Safety, \"gone wrong\" and myth-bust", "highest engagement - handle carefully")
note("These out-perform everything else in the lane. Frame every one as safety education "
     "from a provider, never as commentary on another clinic's work.")
kw_table([
    ("botox side effects", "12,100", 44, "Straight, complete, unsensational"),
    ("lip filler migrating", "8,100", 15, "Major current topic. What causes it"),
    ("lip filler migration", "6,600", 15, "Same asset, second cut"),
    ("botox is bad for you", "6,600", 9, "Lowest-difficulty large term here"),
    ("is botox bad for you", "5,400", 55, "Same question, harder phrasing"),
    ("dysport or botox", "4,400", 12, "Comparison table"),
    ("masseter botox gone wrong", "3,600", 22, "What actually causes it, from a provider"),
    ("dissolved lip filler", "3,600", 16, "Reversibility is a selling point"),
    ("long term effects of botox", "2,900", 38, "Evidence-led, cite the literature"),
    ("is microneedling safe", "1,600", 31, "Clinic vs. at-home is the real answer"),
    ("vascular occlusion lip filler", "1,900", 33, "Technical, signals genuine expertise"),
    ("can you get lip filler while pregnant", "1,900", 8, "Lowest difficulty on the page"),
])

h3("Metabolic & GLP-1", "the Pinterest lane")
note("Largest volumes in the portfolio by a wide margin. Read the compliance notes before "
     "briefing any of the semaglutide rows.")
kw_table([
    ("semaglutide", "368,000", 71, "Head term. Explainer series, no sourcing talk"),
    ("insulin resistance", "110,000", 72, "Head term. Pinterest-native"),
    ("semaglutide side effects", "60,500", 62, "Honest, complete, clinician-voiced"),
    ("weight loss insulin resistance", "40,500", 59, "Why calorie advice alone stalls"),
    ("insulin resistance symptoms", "33,100", 29, "Checklist graphic, high save rate"),
    ("insulin resistance and diet", "33,100", 43, "Meal-plan pin, the proven Pinterest shape"),
    ("insulin resistance and weight loss", "33,100", 55, "Companion to the row above"),
    ("semaglutide dosing", "33,100", 30, "Education only, no protocol advice"),
    ("food for insulin resistance", "27,100", 43, "Grocery-list pin"),
    ("insulin resistance pcos", "9,900", 69, "Highly engaged community"),
    ("how does semaglutide work", "9,900", 26, "Mechanism animation"),
    ("how i cured my insulin resistance", "8,100", 53, "Patient-story format, with consent"),
    ("insulin resistance test", "8,100", 30, "Which labs to ask for. Drives consults"),
    ("symptoms of insulin resistance in woman", "6,600", 59, "Sharpest audience fit in the lane"),
    ("microdosing semaglutide", "5,400", 21, "Trend term. Evidence-led treatment only"),
    ("mediterranean diet for insulin resistance", "4,400", 46, "Evergreen pin"),
    ("semaglutide vs tirzepatide", "4,400", 28, "Comparison table, cite the trial data"),
    ("does semaglutide cause hair loss", "3,600", 28, "High-anxiety question, few good answers"),
    ("does semaglutide make you tired", "3,600", 16, "Direct answer card"),
    ("nad iv therapy", "14,800", 29, "Highest-ticket IV service, real search demand"),
    ("iv therapy myers cocktail", "8,100", 23, "Named protocol, explains what's in the bag"),
])


# ================= COMPLIANCE =================
doc.add_page_break()
h2("Before briefing these")
note("Three constraints that would make otherwise good creative unusable.")
callout(FILL_WARN, "8A4B06", [
    ("No physician language at the wellness clinic",
     "Irving Health & Wellness is led by Lori, a Board-Certified APRN. Nothing can say or "
     "imply \"physician-led\", \"physician-supervised\" or \"formulated by physicians\". Use "
     "\"Board-Certified APRN\" or \"APRN-led\"."),
    ("Semaglutide: education only, no sourcing or pricing",
     "The compounded-semaglutide terms carry real volume and real regulatory exposure. Until "
     "dispensing is confirmed internally, keep creative to mechanism, side effects and "
     "expectations - never where it comes from, what it costs, or how to dose it."),
    ("\"Gone wrong\" content is safety education, not commentary",
     "Filler migration and masseter-botox posts are the highest-engagement items in the "
     "aesthetics lane. They stay defensible only while they explain causes and prevention. "
     "Never use another provider's result as the visual."),
])


# ================= EXCLUSIONS =================
h2("Excluded on purpose")
note("These surface high in any keyword export and are wrong for this brief.")
callout(FILL_STOP, "9C2C22", [
    ("Every \"near me\" variant",
     "botox near me shows 246,000/mo and iv therapy near me 110,000 - the biggest numbers "
     "Ubersuggest returned. They resolve in Google's local pack, which moves on "
     "business-profile category and review velocity, not creative. Chasing them with social "
     "spends budget on a lever social doesn't pull."),
    ("Product-purchase intent",
     "microneedling at home (27,100), microneedling pen (14,800), ankle braces, electrolyte "
     "powders. These searchers are buying a device on Amazon, not booking a clinic. The one "
     "exception is turning at-home microneedling into a safety post - that's the clinic's "
     "argument to make."),
    ("Practitioner-audience terms",
     "botox training course, iv therapy certification, icd 10 code chest pain, cephalexin "
     "cellulitis. Real volume, wrong people - clinicians and students, not patients."),
    ("Celebrity and off-vertical noise",
     "millie bobby brown lip filler (14,800), hair botox (18,100 - an unrelated salon "
     "treatment), food dehydration and beef-jerky terms that share the word \"dehydration\". "
     "Easy to include by accident from a raw export."),
])


# ================= TIMING =================
h2("When to run which")
note("One timing constraint carries over from the demand data: the ER lane is seasonal, the "
     "wellness lane is not.")
tt = doc.add_table(rows=3, cols=2)
table_borders(tt)
timing = [
    ("Now - Nov", "Build the ER illness and injury sets. Their demand climbs from October and "
                  "peaks in March; assets need to exist before the curve turns."),
    ("Dec - Mar", "Peak ER season. Run the library and amplify what worked rather than "
                  "building new."),
    ("Year-round", "Aesthetics and metabolic content has no season, with a January weight-loss "
                   "spike worth planning for in November."),
]
for i, (when, what) in enumerate(timing):
    cant_split(tt.rows[i])
    c1 = tt.cell(i, 0)
    cell_margins(c1, top=80, bottom=80)
    c1.width = Cm(3.2)
    p1 = c1.paragraphs[0]
    p1.paragraph_format.space_after = Pt(0)
    r = p1.add_run(when.upper())
    r.font.name = MONO
    r.font.size = Pt(8)
    r.font.bold = True
    r.font.color.rgb = ACCENT
    c2 = tt.cell(i, 1)
    cell_margins(c2, top=80, bottom=80)
    c2.width = Cm(12.9)
    p2 = c2.paragraphs[0]
    p2.paragraph_format.space_after = Pt(0)
    r = p2.add_run(what)
    r.font.name = SANS
    r.font.size = Pt(9)
    r.font.color.rgb = INK2
doc.add_paragraph().paragraph_format.space_after = Pt(2)


# ================= METHOD =================
hrule("14181A", 12, space_before=14, space_after=6)
p = doc.add_paragraph()
p.paragraph_format.space_after = Pt(6)
rich(p, [
    ("Method. ", {"size": 8.5, "bold": True, "color": INK}),
    ("Volumes and difficulty scores are Ubersuggest estimates for the United States "
     "(location 2840), pulled 2 September 2026 from seed expansions on dehydration, bone "
     "bruise, sprained ankle, chest pain, cellulitis, insulin resistance, IV therapy, botox, "
     "semaglutide, microneedling and lip filler. Difficulty is Ubersuggest's 0-100 SEO "
     "difficulty score.", {"size": 8.5, "color": INK3}),
])
p = doc.add_paragraph()
p.paragraph_format.space_after = Pt(0)
rich(p, [
    ("Treat the absolute volumes as estimates rather than counts - the largest head terms in "
     "particular look aggregated across close variants, and several appear as suspiciously "
     "round numbers. The reliable signal is the relative ordering and the difficulty spread, "
     "both of which held consistent across separate seed expansions. Nothing here has been "
     "filtered against what these sites already rank for, so some overlap with existing pages "
     "is expected and welcome.", {"size": 8.5, "color": INK3}),
])

out = r"D:\Social-Creative-Target-List-Sept-2026.docx"
doc.save(out)
print("SAVED:", out)
