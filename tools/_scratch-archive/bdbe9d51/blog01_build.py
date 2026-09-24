# -*- coding: utf-8 -*-
"""Build Blog #1 (equipment financing) Elementor tree from the 3771 scaffold.
Every fact: BDC Equipment Financing 101 (verified 2026-08-11). Quotes verbatim:
Concetta Farina, Account Manager, Virtual Business Centre, BDC.
Validations: every mapped id exists+replaced, no em dashes, no leftover
concrete-buggy vocabulary, word count in 1200-2200."""
import io, sys, json, re

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
BDC = "https://www.bdc.ca/en/articles-tools/money-finance/get-financing/equipment-financing-101-everything-you-need-know"
A = "style='color:#01B51B;font-weight:600;'"

T = {}  # widget id -> {setting: new value}

# ---- HERO ----
T["4649f03c"] = {"title": "How Does Equipment Financing Work in Canada?"}
# badge 6b918ec and byline 123a341b kept as-is (Buyer's Guide / team byline)

# ---- SIDEBAR: related posts ----
REL = [
    ("Concrete Buggy Canada: Uses & Specs", "https://mammothmachinery.ca/what-is-a-concrete-buggy/"),
    ("Mini Skid Steer vs Full Size", "https://mammothmachinery.ca/mini-skid-steer-vs-full-size/"),
    ("What Size Mini Excavator Do I Need?", "https://mammothmachinery.ca/what-size-mini-excavator-do-i-need-best-2026-guide/"),
]

# ---- INTRO ----
T["234a9165"] = {"editor": (
 "<p>Equipment financing is a loan or lease that pays for a machine, with the machine itself acting as the security. "
 "Instead of pulling $30,000 to $140,000 out of your operating account, you spread the cost over a term that can run up to 12 years. "
 "Your cash stays on payroll, materials, and fuel.</p>"
 "<p>For many Canadian contractors, that is the difference between adding a machine this season and waiting three more years. "
 "Here is how the process works, what lenders look for, and how to walk in prepared.</p>")}

# ---- S1 ----
T["210a47c5"] = {"title": "What Is Equipment Financing?"}
T["3758ba01"] = {"editor": (
 "<p>An equipment loan is a term loan used to buy a specific machine, and the machine is the collateral. "
 "If the payments stop, the lender can take the equipment back. "
 "Because the loan is secured by an asset the lender can value and resell, equipment loans are generally easier to get than unsecured credit of the same size.</p>"
 "<p>The alternative is paying the full price in cash. It feels cheaper because there is no interest. "
 "But it concentrates risk in the worst place: your operating account.</p>")}
# quote card 1 (heading 68d81bf7 kept: Field Insight)
T["33427b24"] = {"editor": (
 "<p>&#8220;Financing a piece of equipment out of your everyday cash can put your business at risk.&#8221;</p>")}
T["224e168b"] = {"title": "Concetta Farina, Account Manager, BDC"}
T["18e6b93a"] = {"editor": (
 "<p>That warning comes from <a href='" + BDC + "' target='_blank' rel='noopener' " + A + ">BDC's own guide to equipment financing</a>, and it matches what contractors see on the ground. "
 "One slow season plus one big repair on a machine you drained your account for, and suddenly payroll is the problem. "
 "Financing exists to keep that risk off your books.</p>")}

# ---- S2: loan vs lease + table 1 ----
T["c8d2422"] = {"title": "Loan or Lease: What Is the Difference?"}
T["26bac6ce"] = {"editor": (
 "<p>With a loan, you own the machine from day one and build equity with every payment. "
 "With a lease, the financing company owns it and you pay for the use, usually with a buyout option at the end. "
 "Both get the machine on your site; they differ in ownership, upfront cost, and tax treatment.</p>")}
# table 1: header row + 4 data rows (drop rows 698f4713, 5f696988)
T["caa0845"]  = {"title": ""}
T["36cc757e"] = {"title": "Equipment loan"}
T["2017a715"] = {"title": "Lease"}
T["aea3cff"]  = {"title": "Ownership"}
T["5e4cb430"] = {"title": "Yours from day one"}
T["c356d60"]  = {"title": "Lessor's until buyout"}
T["4a877ef4"] = {"title": "Upfront cost"}
T["6673beb8"] = {"title": "Down payment usually required"}
T["64f2b458"] = {"title": "Often little or none"}
T["233c0b5a"] = {"title": "Payments"}
T["61aeffe3"] = {"title": "Principal plus interest"}
T["5dd9abdd"] = {"title": "Use-based, fixed term"}
T["5950c17f"] = {"title": "End of term"}
T["fc2d068"]  = {"title": "You own it outright"}
T["6ce6d71c"] = {"title": "Return, renew, or buy out"}
T["5748cd6e"] = {"editor": (
 "<p>Tax treatment is where the two paths really part. Lease payments may be deductible as a business expense, while ownership follows depreciation rules instead. "
 "The right answer is different for a landscaper replacing dumpers every four years than for an excavation company running one wheel loader for a decade. "
 "Have your accountant run both before you sign.</p>")}

# ---- S3: what lenders look at (two boxes) ----
T["1946bfc1"] = {"title": "What Do Lenders Actually Look At?"}
T["38036da7"] = {"editor": "<p>Approval is not a mystery. Lenders evaluate a short, predictable list, and you can prepare every item on it before you apply.</p>"}
T["516be8d8"] = {"title": "The Paperwork They Want"}
T["741baf4a"] = {"editor": (
 "<p>Financial statements for the last two years, plus monthly cash-flow forecasts covering the rest of this year and the next 12 months. "
 "They will also look at your debt-to-equity ratio and working capital.</p>")}
T["5770aa9c"] = {"editor": "<p>Tip: previously purchased equipment can often be financed too, which frees up cash you already spent.</p>"}
T["4041ab29"] = {"title": "The Story They Want"}
T["535c1364"] = {"editor": (
 "<p>A specific explanation of how the machine increases sales or cuts costs. "
 "Company history, operations, and management experience round out the picture. "
 "The stronger the numbers behind the story, the easier the approval.</p>")}
T["77aaf3e4"] = {"editor": "<p>Tip: name the jobs the machine unlocks and the margin on each. Specifics beat adjectives.</p>"}
T["47778184"] = {"editor": (
 "<p>As BDC account manager Concetta Farina puts it in the <a href='" + BDC + "' target='_blank' rel='noopener' " + A + ">same guide</a>: &#8220;Bankers need numbers.&#8221; "
 "The strongest application is not a perfect credit history. It is a cash-flow forecast that shows exactly how the machine pays for itself.</p>")}

# ---- S4: what financing covers ----
T["3dd101a3"] = {"title": "What Can Financing Actually Cover?"}
T["7da10af"] = {"editor": (
 "<p>More than the sticker price. BDC, Canada's bank for entrepreneurs, can finance up to 125% of the upfront cost of equipment. "
 "That lets transportation, shipping, installation, and operator training ride along in the same loan instead of hitting your cash.</p>")}
T["476cfd6"] = {"editor": (
 "<p>Terms and structure are flexible in ways that matter for seasonal work. "
 "Equipment loan terms run up to 12 years, and principal postponement is available for up to the first two years on some loans. "
 "Repayment can also be structured seasonally, with smaller payments through the winter and larger ones in your busy months.</p>")}
T["63f0d6bb"] = {"icon_list_texts": [
 "<strong style='color:#0f0f0f;'>Up to 125% financed</strong>: delivery, installation, and training can be included, not just the machine.",
 "<strong style='color:#0f0f0f;'>Terms up to 12 years</strong>: longer terms lower the payment; match the term to the machine's earning life.",
 "<strong style='color:#0f0f0f;'>Seasonal structures</strong>: postpone principal up to 2 years on some loans, or shape payments around your season.",
]}
T["6d3fc163"] = {"editor": (
 "<p>For Canadian landscapers and excavation contractors whose revenue arrives between April and November, the seasonal structure is often the single most valuable feature.</p>")}

# ---- S5: how to apply ----
T["3731b3f2"] = {"title": "How to Apply: Step by Step"}
T["782dbce9"] = {"editor": (
 "<p><strong>1. Get a written quote</strong> for the machine, with delivery and attachments itemized.</p>"
 "<p><strong>2. Pull your financial statements</strong> for the last two years.</p>"
 "<p><strong>3. Build a monthly cash-flow forecast</strong> covering the rest of this year plus the next 12 months.</p>")}
T["3f9018cf"] = {"editor": (
 "<p><strong>4. Write the revenue case</strong> in two or three sentences: which jobs the machine unlocks and what they pay.</p>"
 "<p><strong>5. Apply and compare</strong> at least two offers on rate, term, and flexibility, not rate alone.</p>"
 "<p><strong>6. Close and take delivery</strong>, then register your warranty the same week.</p>")}

# ---- S6: Mammoth section + table 2 ----
T["27ccfb9d"] = {"title": "Financing a Mammoth Machine"}
T["21af5d20"] = {"editor": (
 "<p>Every Mammoth machine can be financed. Here are the current price ranges to plan around, all in CAD and all covered by the same warranty.</p>")}
# table 2: header + 4 rows (drop rows 3f5c3a1, 132c5983, 20fd5f3c)
T["3d6793d4"] = {"title": "Machine class"}
T["12e163ec"] = {"title": "Price range (CAD)"}
T["2298c9db"] = {"title": "Warranty"}
T["7eed5fd5"] = {"title": "Mini dumpers"}
T["45ca9274"] = {"title": "$3,199 to $28,499"}
T["57259a3"]  = {"title": "5-Year / 3,000-Hour"}
T["22d4589f"] = {"title": "Mini skid steers"}
T["4c2e0261"] = {"title": "$27,999 to $38,999"}
T["48a69cb"]  = {"title": "5-Year / 3,000-Hour"}
T["38aa4c0d"] = {"title": "Mini excavators"}
T["71b0ab17"] = {"title": "$37,999 to $58,999"}
T["7deffcf0"] = {"title": "5-Year / 3,000-Hour"}
T["554eb573"] = {"title": "Wheel loaders"}
T["70db32f1"] = {"title": "$68,999 to $139,999"}
T["5dbf68bc"] = {"title": "5-Year / 3,000-Hour"}
T["5ed95583"] = {"editor": (
 "<p>Prices are current listed prices on each machine page. "
 "Our <a href='https://mammothmachinery.ca/financing/' " + A + ">financing page</a> covers the available options, including leasing and seasonal payment structures built around Canadian work seasons. "
 "Every machine also carries the <a href='https://mammothmachinery.ca/mammoth-machinery-warranty/' " + A + ">5-Year / 3,000-Hour Warranty</a> as standard, which matters to lenders too: a machine that holds value is better collateral.</p>")}

# ---- FAQ ----
T["39c11577"] = {"title": "Can I finance used or already-purchased equipment?"}
T["37ebdf6b"] = {"editor": (
 "<p>Often, yes. BDC notes that even previously purchased equipment can be financed, which lets you refinance a recent cash purchase and put working capital back in the business. "
 "Policies vary by lender, so ask before assuming.</p>")}
T["a5baf0"] = {"title": "How long are equipment loan terms in Canada?"}
T["240646e"] = {"editor": (
 "<p>Equipment loans commonly run up to 12 years, with the machine as collateral. "
 "Longer terms mean lower payments but more total interest. "
 "Match the term to how long the machine will actually earn for you.</p>")}
T["779403e3"] = {"title": "Is leasing or buying better for taxes?"}
T["6c034ac9"] = {"editor": (
 "<p>It depends on your situation. Lease payments may be deductible as a business expense, while ownership follows depreciation rules. "
 "The difference is real money either way, so have your accountant run both before you sign.</p>")}
T["16984099"] = {"title": "Can a newer business get equipment financing?"}
T["4900ac0b"] = {"editor": (
 "<p>It is harder without two years of statements, but not impossible, because the machine secures the loan. "
 "Expect a larger down payment, and be ready with a detailed cash-flow forecast that shows exactly how the equipment pays for itself.</p>")}

# ---- CTA ----
T["5743c0bd"] = {"title": "Ready to run the numbers on your next machine?"}
T["6de25e2d"] = {"title": "See Mammoth's Financing Options"}
T["1ad0b190"] = {"editor": (
 "<p>Flexible financing and leasing on the full Mammoth lineup, with seasonal payment structures built for Canadian work. "
 "Start with the options, pick the machine, and keep your cash where it belongs: in the business.</p>")}
BUTTONS = {
 "65e38749": ("Financing Options →", "https://mammothmachinery.ca/financing/"),
 "1dc1d996": ("Browse Wheel Loaders →", "https://mammothmachinery.ca/wheel-loaders/"),
 # 22cd9047 View Warranty kept as-is
}
DELETE = {"5c7a64c1",            # concrete-buggy body image
          "698f4713", "5f696988",            # table 1 surplus rows
          "3f5c3a1", "132c5983", "20fd5f3c"} # table 2 surplus rows

ANCHORS = {"57bc7453":"loan-vs-lease","31a34fa4":"lenders","3a6d07e1":"covers","27ece4c1":"apply","ddeb50a":"mammoth"}
raw = json.load(open("blog_scaffold_3771.json", encoding="utf-8"))
applied, missing = set(), []

def walk(nodes):
    keep = []
    for n in nodes:
        if n.get("id") in DELETE:
            applied.add(n["id"]); continue
        s = n.get("settings", {})
        wid = n.get("id")
        if wid in T:
            spec = T[wid]
            for k, v in spec.items():
                if k == "icon_list_texts":
                    items = s.get("icon_list", [])
                    if len(items) != len(v):
                        missing.append(f"{wid}: icon_list len {len(items)} != {len(v)}")
                    for it, txt in zip(items, v):
                        it["text"] = txt
                else:
                    if k not in s:
                        missing.append(f"{wid}: key {k} absent")
                    s[k] = v
            applied.add(wid)
        if wid in BUTTONS:
            s["text"] = BUTTONS[wid][0]
            s.setdefault("link", {})["url"] = BUTTONS[wid][1]
            applied.add(wid)
        if wid in ANCHORS:
            s["_element_id"] = ANCHORS[wid]
            applied.add(wid)
        if wid == "61fcb788":  # related icon-list
            items = s.get("icon_list", [])
            for it, (txt, url) in zip(items, REL):
                it["text"] = txt
                it.setdefault("link", {})["url"] = url
            applied.add(wid)
        n["elements"] = walk(n.get("elements", []))
        keep.append(n)
    return keep

raw = walk(raw)

# ---- validations ----
want = set(T) | set(BUTTONS) | DELETE | {"61fcb788"} | set(ANCHORS)
unapplied = want - applied
blob = json.dumps(raw, ensure_ascii=False)
text = re.sub(r"<[^>]+>", " ", re.sub(r'\\[ntr]', ' ', blob))
em = blob.count("—") + blob.count("&#8212;") + blob.count("&mdash;")
leftovers = [w for w in ["concrete bugg", "wheelbarrow", "Walk-Behind", "Ride-On", "tub volume", "2,300 kg", "power bugg"]
             if w.lower() in blob.lower()]
words = len(re.findall(r"[A-Za-z][A-Za-z'\-]*", " ".join(
    v for wid in T for v in T[wid].values() if isinstance(v, str))))
print(f"applied: {len(applied)}/{len(want)}  unapplied: {sorted(unapplied) if unapplied else 'none'}")
print(f"missing-key issues: {missing if missing else 'none'}")
print(f"em dashes in tree: {em}")
print(f"leftover concrete-buggy vocab: {leftovers if leftovers else 'none'}")
print(f"approx new-copy word count: {words}")
json.dump(raw, open("blog01_tree.json", "w", encoding="utf-8"), ensure_ascii=False)
print("tree written: blog01_tree.json", len(blob), "chars")
