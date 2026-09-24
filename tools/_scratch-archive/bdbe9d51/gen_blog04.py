# -*- coding: utf-8 -*-
"""Generate blog04_build.py: How to Choose a Mini Excavator (non-size factors;
sizing delegated to the existing size guide to avoid cannibalization).
Verified facts only: X-Cavator 20/27/35MT = 2/2.7/3.5 t, Kubota diesel, from $37,999,
top dig depth 10ft11in, 35MT weight 8,488 lbs (from its live page), 5yr/3000hr warranty."""

src = open('blog01_build.py', encoding='utf-8').read()
head = src[:src.index('T = {}')]
tail = src[src.index('ANCHORS = {'):]
tail = tail.replace(
    '{"57bc7453":"loan-vs-lease","31a34fa4":"lenders","3a6d07e1":"covers","27ece4c1":"apply","ddeb50a":"mammoth"}',
    '{"57bc7453":"factors","31a34fa4":"specs","3a6d07e1":"money","27ece4c1":"buying-path","ddeb50a":"lineup"}'
).replace('blog01_tree.json', 'blog04_tree.json')

# bake layout v2 + sticky into the tree (the complete post-August recipe)
tail += '''

# ---- LAYOUT v2 + sticky sidebar baked in (post-Aug-17 blog recipe) ----
LAYOUT = {
 "4f211fc0": {"width": {"unit": "px", "size": 1300, "sizes": []}, "width_tablet": {"unit": "%", "size": 100, "sizes": []}, "padding": {"unit": "px", "top": "0", "right": "24", "bottom": "0", "left": "24", "isLinked": False}},
 "7bd2d4d6": {"width": {"unit": "%", "size": 26, "sizes": []}},
 "3ebdd092": {"width": {"unit": "%", "size": 68, "sizes": []}},
 "6afddc71": {"flex_gap": {"column": "0", "row": "16", "isLinked": False, "unit": "px", "size": 16}},
 "2042f80":  {"padding": {"unit": "px", "top": "80", "right": "0", "bottom": "96", "left": "0", "isLinked": False}},
 "28be38e9": {"flex_align_items": "stretch"},
 "1ccb4821": {"sticky": "top", "sticky_on": ["desktop"], "sticky_offset": 160, "sticky_parent": "yes", "_z_index": 1},
}
tree2 = json.load(open("blog04_tree.json", encoding="utf-8"))
hit = set()
def lwalk(nodes):
    for n in nodes:
        if n["id"] in LAYOUT:
            n.setdefault("settings", {}).update(LAYOUT[n["id"]]); hit.add(n["id"])
        lwalk(n.get("elements", []))
lwalk(tree2)
print("layout+sticky patched:", len(hit), "of", len(LAYOUT), "| missing:", set(LAYOUT)-hit or "none")
json.dump(tree2, open("blog04_tree.json", "w", encoding="utf-8"), ensure_ascii=False)
'''

EXC = "https://mammothmachinery.ca/mini-excavators/"
SIZE = "https://mammothmachinery.ca/what-size-mini-excavator-do-i-need-best-2026-guide/"
ATT = "https://mammothmachinery.ca/mini-excavator-attachments-canada/"
FIN = "https://mammothmachinery.ca/how-does-equipment-financing-work-canada/"
WAR = "https://mammothmachinery.ca/what-does-5-year-equipment-warranty-cover/"
A = "style='color:#01B51B;font-weight:600;'"

body_lines = []
add = body_lines.append
add('T = {}')
add('T["4649f03c"] = {"title": "How to Choose the Right Mini Excavator for Your Work"}')
add('REL = [')
add(' ("What Size Mini Excavator Do I Need?", "' + SIZE + '"),')
add(' ("Mini Excavator vs Mini Skid Steer", "https://mammothmachinery.ca/mini-excavator-vs-mini-skid-steer/"),')
add(' ("Mini Excavator Attachments Guide", "' + ATT + '"),')
add(']')

def widget(wid, key, html):
    add('T["' + wid + '"] = {"' + key + '": ' + repr(html) + '}')

widget("234a9165", "editor",
 "<p>Choosing a mini excavator comes down to five things: the size class your jobs demand, the engine and service access behind it, the attachments it can run, the weight your trailer can legally haul, and the support you get after the cheque clears. "
 "Most buyers obsess over the first one and discover the other four the hard way.</p>"
 "<p>Size class has its own guide: <a href='" + SIZE + "' " + A + ">what size mini excavator do I need</a>. "
 "This one covers everything after the tonnage decision.</p>")
widget("210a47c5", "title", "Start With the Job, Not the Spec Sheet")
widget("3758ba01", "editor",
 "<p>Spec sheets reward whoever prints the biggest number. Jobs reward the machine that shows up running, fits the site, and takes the attachment the work needs. "
 "So start from your last three months of invoices: what did you dig, in what ground, behind what gate, hauled on what trailer?</p>"
 "<p>Every factor below is a filter. Run your real jobs through them in order and the shortlist usually collapses to one or two machines.</p>")
widget("33427b24", "editor",
 "<p>&#8220;Buyers shop horsepower. Owners live with service access and parts availability. Choose the machine you can keep running, not the one that wins the brochure.&#8221;</p>")
widget("224e168b", "title", "Mammoth Service Team")
widget("18e6b93a", "editor",
 "<p>That is the difference between price and cost. "
 "A machine that is down waiting for parts earns nothing, whatever its spec sheet said. "
 "It is why engine brand and dealer support sit on this list at all: browse the <a href='" + EXC + "' " + A + ">X-Cavator lineup</a> and you will see the engine named on every page, because it matters.</p>")
widget("c8d2422", "title", "The Four Factors After Size")
widget("26bac6ce", "editor",
 "<p>Once the size guide has given you a class, these four factors separate the machine you want from the machine you will wish you had bought.</p>")
for wid, val in [("caa0845","Factor"),("36cc757e","What to check"),("2017a715","Why it matters"),
 ("aea3cff","Engine and service"),("5e4cb430","Brand-name diesel, accessible service points"),("c356d60","Uptime, parts availability, resale value"),
 ("4a877ef4","Attachments"),("6673beb8","Auxiliary hydraulics, coupler, what fits today"),("64f2b458","One machine becomes a fleet of tools"),
 ("233c0b5a","Transport"),("fc2d068","Machine weight vs your trailer and licence"),("6ce6d71c","Every job starts and ends with a haul"),
 ("5950c17f","Support"),("61aeffe3","Warranty terms, dealer network, spec sheets"),("5dd9abdd","Total cost over five years, not day one")]:
    widget(wid, "title", val)
widget("5748cd6e", "editor",
 "<p>On the Mammoth side those answers are short: every X-Cavator runs Kubota diesel power, takes augers, breakers, and thumbs, and the heaviest machine in the range, the 35MT, weighs 8,488 lbs, inside the reach of a heavy-duty pickup and equipment trailer. "
 "Check your own province's towing rules against the exact model weight on its machine page.</p>")
widget("1946bfc1", "title", "Specs Worth Your Attention, and Specs That Distract")
widget("38036da7", "editor", "<p>Two lists, learned from buyers who got it right and buyers who called us a year later.</p>")
widget("516be8d8", "title", "Check These Closely")
widget("741baf4a", "editor",
 "<p>Dig depth against your typical trench, not your deepest-ever job. Auxiliary hydraulic flow against the attachments you will actually buy. "
 "Transport weight against your trailer's rated capacity. Service access: can you reach the filters without removing panels?</p>")
widget("5770aa9c", "editor", "<p>Tip: dig depth per model is listed on every machine page, up to 10 ft 11 in at the top of the range.</p>")
widget("4041ab29", "title", "Weigh These Lightly")
widget("535c1364", "editor",
 "<p>Horsepower on its own, cab options you will not use in a 40-hour week, and any spec quoted without the operating conditions behind it. "
 "A smaller machine that fits the site beats a bigger one parked at the gate.</p>")
widget("77aaf3e4", "editor", "<p>Tip: comparing against a skid steer instead? That decision has <a href='https://mammothmachinery.ca/mini-excavator-vs-mini-skid-steer/' " + A + ">its own guide</a>.</p>")
widget("47778184", "editor",
 "<p>The honest lens is five-year cost: purchase price plus attachments, transport, and upkeep, minus what the machine is worth when you upgrade. "
 "A strong warranty moves every one of those numbers in your favour.</p>")
widget("3dd101a3", "title", "Match the Machine to the Money")
widget("7da10af", "editor",
 "<p>The X-Cavator line starts at $37,999 CAD with every price listed on its machine page, so the budget conversation starts from real numbers instead of a quote request. "
 "Financing spreads the cost over the machine's earning life, and Canadian lenders can structure payments around seasonal work.</p>")
widget("476cfd6", "editor",
 "<p>The warranty is part of the money decision too: 5 years or 3,000 hours on powertrain, hydraulics, and structure, standard on every model. "
 "Coverage that long protects resale value, which lenders notice.</p>")
add('T["63f0d6bb"] = {"icon_list_texts": [')
add(repr("<strong style='color:#0f0f0f;'>Prices from $37,999 CAD</strong>: listed on every machine page, no quote wall.") + ',')
add(repr("<strong style='color:#0f0f0f;'>5-year / 3,000-hour warranty</strong>: standard across the lineup, engines covered by Kubota.") + ',')
add(repr("<strong style='color:#0f0f0f;'>Financing available</strong>: loans, leases, and seasonal structures for Canadian crews.") + ',')
add(']}')
widget("6d3fc163", "editor",
 "<p>How the lending side works, what lenders check, and how seasonal structures fit landscaping cash flow: our <a href='" + FIN + "' " + A + ">equipment financing guide</a> walks through it.</p>")
widget("3731b3f2", "title", "The Six-Step Buying Path")
widget("782dbce9", "editor",
 "<p><strong>1. List your last three months of jobs</strong> from invoices: ground, depth, access, haul distance.</p>"
 "<p><strong>2. Pick your size class</strong> with the <a href='" + SIZE + "' " + A + ">size guide</a>, then stop revisiting it.</p>"
 "<p><strong>3. Shortlist year-one attachments</strong> and confirm the machine runs them.</p>")
widget("3f9018cf", "editor",
 "<p><strong>4. Check the haul</strong>: machine weight plus attachments against your trailer and licence.</p>"
 "<p><strong>5. Price the package</strong>: machine, attachments, financing, using real listed prices.</p>"
 "<p><strong>6. Talk to the dealer</strong> with the job list from step 1 and ask for the spec sheet, not the brochure.</p>")
widget("27ccfb9d", "title", "The X-Cavator Lineup at a Glance")
widget("21af5d20", "editor",
 "<p>Three models, one warranty, every price public. Where each one fits:</p>")
for wid, val in [("3d6793d4","Model"),("12e163ec","Class"),("2298c9db","Where it fits"),
 ("7eed5fd5","X-Cavator 20MT"),("45ca9274","2 tonnes"),("57259a3","Tight access, first machine, from $37,999"),
 ("22d4589f","X-Cavator 27MT"),("4c2e0261","2.7 tonnes"),("48a69cb","The all-round middle of the range"),
 ("38aa4c0d","X-Cavator 35MT"),("71b0ab17","3.5 tonnes"),("7deffcf0","Full-day production, 10 ft 11 in dig depth"),
 ("554eb573","All models"),("70db32f1","Kubota diesel"),("5dbf68bc","5-year / 3,000-hour warranty standard")]:
    widget(wid, "title", val)
widget("5ed95583", "editor",
 "<p>Full specifications, photos, and downloadable spec sheets live on each machine page in the <a href='" + EXC + "' " + A + ">mini excavator lineup</a>. "
 "What the warranty covers, in plain language, is in the <a href='" + WAR + "' " + A + ">warranty guide</a>.</p>")
widget("39c11577", "title", "Which mini excavator is best for a first machine?")
widget("37ebdf6b", "editor",
 "<p>The one matched to the jobs you already invoice, which for tight residential work is usually the smaller end of the range. "
 "Renting your shortlisted class for one job before buying is the cheapest mistake-proofing there is.</p>")
widget("a5baf0", "title", "What attachments should I budget for in year one?")
widget("240646e", "editor",
 "<p>A bucket set sized to your trenching, then usually an auger or a breaker depending on your work. "
 "Our <a href='" + ATT + "' " + A + ">attachments guide</a> covers what fits and what each one is for.</p>")
widget("779403e3", "title", "How heavy is a mini excavator to transport?")
widget("6c034ac9", "editor",
 "<p>In the X-Cavator range, up to 8,488 lbs for the 35MT, with the smaller models lighter. "
 "Check the exact weight on the machine page against your trailer rating and provincial towing rules before you buy, not after.</p>")
widget("16984099", "title", "Does the warranty change by model?")
widget("4900ac0b", "editor",
 "<p>No. Every X-Cavator carries the same 5-year / 3,000-hour coverage on powertrain, hydraulics, and structure, with the Kubota engine covered by Kubota's own program. "
 "The <a href='" + WAR + "' " + A + ">warranty guide</a> explains the split.</p>")
widget("5743c0bd", "title", "Shortlist built? Put real prices against it.")
widget("6de25e2d", "title", "Compare the X-Cavator Models")
widget("1ad0b190", "editor",
 "<p>Every machine page lists the CAD price, full specifications, and the spec sheet your dealer conversation should start from.</p>")
add('BUTTONS = {')
add(' "65e38749": ("Mini Excavators →", "' + EXC + '"),')
add(' "1dc1d996": ("Size Guide →", "' + SIZE + '"),')
add(' "22cd9047": ("How Financing Works →", "' + FIN + '"),')
add('}')
add('DELETE = {"5c7a64c1", "698f4713", "5f696988", "3f5c3a1", "132c5983", "20fd5f3c"}')

out = head + "\n".join(body_lines) + "\n\n" + tail
open('blog04_build.py', 'w', encoding='utf-8').write(out)
print('blog04_build.py written:', len(out), 'chars')
