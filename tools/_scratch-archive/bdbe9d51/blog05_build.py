# -*- coding: utf-8 -*-
"""Build Blog #5 (landscaping jobs / compact loaders) from the 3771 scaffold.
Every spec verified 2026-08-23 off the live X-Loader machine pages:
 50MT  $27,999 CAD | ROC 1,000 lb | 2,830 lb | 34.5 in wide | Yanmar 3TNV80F 24.2 HP | 11-13.5 GPM | hinge pin 75.6 in
 100MT $33,999 CAD | ROC 1,200 lb | 3,208 lb | 36 in wide   | Kubota D1105 24.8 HP   | 10-15 GPM   | hinge pin 85.6 in
 120MT $38,999 CAD | ROC 1,300 lb | 3,345 lb | 35.8 in wide | Yanmar 3TNV80FT 24.7 HP| 13.8-17 GPM | hinge pin 88.6 in, tipping load 4,000 lb
All models: CII Universal mini skid steer plate, 5-year / 3,000-hour warranty.
ANTI-CANNIBALIZATION: title/H1 lead with the landscaping job, never the bare head
term; the transactional term is handed to /mini-skidsteers/ by exact anchor."""
import io, sys, json, re

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
A = "style='color:#01B51B;font-weight:600;'"
CAT = "https://mammothmachinery.ca/mini-skidsteers/"
VS = "https://mammothmachinery.ca/mini-skid-steer-vs-full-size/"
FIN = "https://mammothmachinery.ca/how-does-equipment-financing-work-canada/"
WAR = "https://mammothmachinery.ca/what-does-5-year-equipment-warranty-cover/"

T = {}
T["4649f03c"] = {"title": "Landscaping Jobs a Mini Skid Steer Does Best"}
REL = [
 ("Mini Skid Steers for Sale", CAT),
 ("Mini vs Full-Size Skid Steer", VS),
 ("How Equipment Financing Works", FIN),
]
T["234a9165"] = {"editor": f"<p>Landscaping crews buy compact loaders for one reason: the work happens behind the house. Material has to cross a finished lawn, through a gate, around a deck, and land where a wheelbarrow crew would spend a day. A machine that fits that path earns its money back in labour hours, not in spec-sheet bragging.</p><p>This guide covers the jobs the compact class actually does best, the access numbers that decide whether it fits, and what the three <a href='{CAT}' {A}>mini skid steers for sale</a> at Mammoth cost.</p>"}

T["210a47c5"] = {"title": "Why Landscapers Reach for the Compact Class"}
T["3758ba01"] = {"editor": "<p>A full-size machine moves more material per pass. It also weighs three times as much, and on finished ground that weight is the problem. Compact loaders spread their load over rubber tracks and stay under the width of a standard gate, so the same crew can work a backyard without rebuilding the lawn afterwards.</p><p>The trade is real, not marketing. You give up lift capacity and speed on open sites. You gain access to jobs a bigger machine cannot reach at all.</p>"}
T["33427b24"] = {"editor": "<p>&#8220;A full-size machine can do the digging in half the time. It also leaves ruts across the lawn you just built. On finished ground, the compact class is the machine that does not create a second job.&#8221;</p>"}
T["224e168b"] = {"title": "Mammoth Service Team"}
T["18e6b93a"] = {"editor": f"<p>That second job is the hidden cost landscapers price wrong. Turf repair, re-grading, and a callback all come out of the same margin. If you are still deciding between the two classes on capacity alone, the <a href='{VS}' {A}>mini vs full-size comparison</a> works through it properly.</p>"}

T["c8d2422"] = {"title": "Four Landscaping Jobs It Earns Its Keep On"}
T["26bac6ce"] = {"editor": "<p>These are the jobs where a compact loader replaces the most labour hours. Run your own job list against them before shopping specs.</p>"}
T["caa0845"] = {"title": "Job"}
T["36cc757e"] = {"title": "What the machine does"}
T["2017a715"] = {"title": "Why it beats the alternative"}
T["aea3cff"] = {"title": "Backyard material moves"}
T["5e4cb430"] = {"title": "Soil, mulch, gravel and pallets through a side gate"}
T["c356d60"] = {"title": "One operator replaces a wheelbarrow crew"}
T["4a877ef4"] = {"title": "Grading and levelling"}
T["6673beb8"] = {"title": "Spreads and levels base material before sod or stone"}
T["64f2b458"] = {"title": "Straighter grade than hand raking, in a fraction of the time"}
T["233c0b5a"] = {"title": "Hardscape builds"}
T["fc2d068"] = {"title": "Carries pallets of pavers and block to the work face"}
T["6ce6d71c"] = {"title": "Saves the crew's backs and the client's driveway"}
T["5950c17f"] = {"title": "Tear-outs and cleanup"}
T["61aeffe3"] = {"title": "Lifts out old sod, spoil and demolition debris"}
T["5dd9abdd"] = {"title": "Loads a trailer directly, no double handling"}
T["5748cd6e"] = {"editor": "<p>Every X-Loader takes the CII universal mini skid steer plate, which is the same standard the common landscaping attachments are built to: buckets, forks, augers, and grapples. One carrier covers most of the season.</p>"}

T["1946bfc1"] = {"title": "Access: The Number That Decides Everything"}
T["38036da7"] = {"editor": "<p>Before capacity, before horsepower, measure your tightest gate. Access is the spec that rules a machine in or out of your work.</p>"}
T["516be8d8"] = {"title": "Measure These First"}
T["741baf4a"] = {"editor": "<p>Your narrowest gate opening, the clear width of your side yards, and the ground you cross to get there. The X-Loader range runs 34.5 to 36 inches wide, which clears a standard 36-inch gate on the narrowest model with room to work.</p>"}
T["5770aa9c"] = {"editor": "<p>Tip: measure the gate posts, not the gate. The hinge hardware is what catches.</p>"}
T["4041ab29"] = {"title": "Then Match the Lift"}
T["535c1364"] = {"editor": "<p>Rated operating capacity runs 1,000 lb on the 50MT to 1,300 lb on the 120MT. A full pallet of pavers is the honest test: if your supplier ships heavy pallets, size up rather than plan to split every load by hand.</p>"}
T["77aaf3e4"] = {"editor": "<p>Tip: hinge pin height matters for loading trailers, and it climbs from 75.6 to 88.6 inches across the range.</p>"}
T["47778184"] = {"editor": "<p>Weight is the third access number. At 2,830 to 3,345 lb these machines tow behind a heavy-duty pickup on a standard equipment trailer, so a two-property day does not need a second truck.</p>"}

T["3dd101a3"] = {"title": "What It Costs"}
T["7da10af"] = {"editor": "<p>The X-Loader line starts at $27,999 CAD, with every price published on the machine page instead of behind a quote form. For a landscaping business the comparison that matters is against labour: a crew moving material by hand for a season costs more than the payment schedule on a machine that does it in a morning.</p>"}
T["476cfd6"] = {"editor": "<p>Coverage runs 5 years or 3,000 hours on powertrain, hydraulics and structure, whichever comes first. For a seasonal landscaping operation, 3,000 hours is a lot of springs.</p>"}
T["63f0d6bb"] = {"icon_list_texts": [
"<strong style='color:#0f0f0f;'>From $27,999 CAD</strong>: listed on every machine page, no quote wall.",
"<strong style='color:#0f0f0f;'>5-year / 3,000-hour warranty</strong>: standard across the range.",
"<strong style='color:#0f0f0f;'>Seasonal financing</strong>: payments can be structured around your billing months.",
]}
T["6d3fc163"] = {"editor": f"<p>How lenders assess a seasonal business, and how to structure payments around a Canadian landscaping year, is covered in our <a href='{FIN}' {A}>equipment financing guide</a>.</p>"}

T["3731b3f2"] = {"title": "A Six-Step Check Before You Buy"}
T["782dbce9"] = {"editor": "<p><strong>1. List last season's jobs</strong>: what you moved, how far, and what the access looked like.</p><p><strong>2. Measure your tightest gate</strong> and your typical side yard, at the posts.</p><p><strong>3. Weigh a typical pallet</strong> from your usual supplier and set your lift requirement from it.</p>"}
T["3f9018cf"] = {"editor": "<p><strong>4. Pick year-one attachments</strong>: a bucket and forks cover most crews before anything specialised.</p><p><strong>5. Check the haul</strong>: machine plus attachments against your trailer rating.</p><p><strong>6. Price the season</strong>: payment against the labour hours the machine removes.</p>"}

T["27ccfb9d"] = {"title": "The X-Loader Lineup at a Glance"}
T["21af5d20"] = {"editor": "<p>Three machines, one attachment standard, every price public. Where each fits a landscaping crew:</p>"}
T["3d6793d4"] = {"title": "Model"}
T["12e163ec"] = {"title": "Lift and width"}
T["2298c9db"] = {"title": "Where it fits"}
T["7eed5fd5"] = {"title": "X-Loader 50MT"}
T["45ca9274"] = {"title": "1,000 lb, 34.5 in"}
T["57259a3"] = {"title": "Tightest gates, easiest to tow, from $27,999"}
T["22d4589f"] = {"title": "X-Loader 100MT"}
T["4c2e0261"] = {"title": "1,200 lb, 36 in"}
T["48a69cb"] = {"title": "The all-round crew machine, $33,999"}
T["38aa4c0d"] = {"title": "X-Loader 120MT"}
T["71b0ab17"] = {"title": "1,300 lb, 35.8 in"}
T["7deffcf0"] = {"title": "Heavy pallets and long days, $38,999"}
T["554eb573"] = {"title": "All models"}
T["70db32f1"] = {"title": "CII universal plate"}
T["5dbf68bc"] = {"title": "5-year / 3,000-hour warranty standard"}
T["5ed95583"] = {"editor": f"<p>Full specifications, photos and spec sheets sit on each machine page in the <a href='{CAT}' {A}>mini skid steer lineup</a>. What the warranty covers in plain language is in the <a href='{WAR}' {A}>warranty guide</a>.</p>"}

T["39c11577"] = {"title": "Will a compact loader damage a finished lawn?"}
T["37ebdf6b"] = {"editor": "<p>Rubber tracks spread the load far better than wheels, and at 2,830 to 3,345 lb these machines are light for their output. On soft or saturated ground any machine marks turf, so work when the ground is firm and keep turns wide rather than pivoting on the spot.</p>"}
T["a5baf0"] = {"title": "What attachments should a landscaping crew buy first?"}
T["240646e"] = {"editor": "<p>A general purpose bucket and a set of pallet forks cover most of the season. Augers and grapples come next, depending on whether you plant trees or clear brush. All of them mount on the CII universal plate that every X-Loader carries.</p>"}
T["779403e3"] = {"title": "Which model suits a small crew starting out?"}
T["6c034ac9"] = {"editor": "<p>The 50MT is the usual starting point: narrowest at 34.5 inches, lightest to tow at 2,830 lb, and lowest priced at $27,999 CAD. Crews that regularly handle full pallets tend to move up to the 100MT or 120MT for the extra lift.</p>"}
T["16984099"] = {"title": "Can one machine cover a whole landscaping season?"}
T["4900ac0b"] = {"editor": f"<p>For most residential crews, yes. The limits show up on large open sites where a full-size machine simply moves more per hour, which is the trade the <a href='{VS}' {A}>class comparison</a> lays out.</p>"}

T["5743c0bd"] = {"title": "Know your gate width? Put a machine against it."}
T["6de25e2d"] = {"title": "Compare the X-Loader Models"}
T["1ad0b190"] = {"editor": "<p>Every machine page lists the CAD price, the full specification table, and the spec sheet to take into a dealer conversation.</p>"}

BUTTONS = {
 "65e38749": ("Mini Skid Steers →", CAT),
 "1dc1d996": ("Mini vs Full-Size →", VS),
 "22cd9047": ("How Financing Works →", FIN),
}
DELETE = {"5c7a64c1", "698f4713", "5f696988", "3f5c3a1", "132c5983", "20fd5f3c"}
ANCHORS = {"57bc7453":"why-compact","31a34fa4":"access","3a6d07e1":"cost","27ece4c1":"buying-check","ddeb50a":"lineup"}

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
            for k, v in T[wid].items():
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
        if wid == "61fcb788":
            items = s.get("icon_list", [])
            for it, (txt, url) in zip(items, REL):
                it["text"] = txt
                it.setdefault("link", {})["url"] = url
            applied.add(wid)
        n["elements"] = walk(n.get("elements", []))
        keep.append(n)
    return keep

raw = walk(raw)

want = set(T) | set(BUTTONS) | DELETE | {"61fcb788"} | set(ANCHORS)
unapplied = want - applied
blob = json.dumps(raw, ensure_ascii=False)
em = blob.count("—") + blob.count("&#8212;") + blob.count("&mdash;")
leftovers = [w for w in ["concrete bugg", "power bugg", "X-Cavator", "mini excavator", "dig depth",
                         "tonne", "tub volume", "2,300 kg", "wheelbarrow crew cost"]
             if w.lower() in blob.lower()]
words = len(re.findall(r"[A-Za-z][A-Za-z'\-]*", re.sub(r"<[^>]+>", " ", " ".join(
    v for wid in T for v in T[wid].values() if isinstance(v, str)))))
print(f"applied: {len(applied)}/{len(want)}  unapplied: {sorted(unapplied) if unapplied else 'none'}")
print(f"missing-key issues: {missing if missing else 'none'}")
print(f"em dashes in tree: {em}")
print(f"leftover foreign vocab: {leftovers if leftovers else 'none'}")
print(f"approx new-copy word count: {words}")
json.dump(raw, open("blog05_tree.json", "w", encoding="utf-8"), ensure_ascii=False)
print("tree written: blog05_tree.json", len(blob), "chars")

# ---- LAYOUT v2 + sticky sidebar baked in ----
LAYOUT = {
 "4f211fc0": {"width": {"unit": "px", "size": 1300, "sizes": []}, "width_tablet": {"unit": "%", "size": 100, "sizes": []}, "padding": {"unit": "px", "top": "0", "right": "24", "bottom": "0", "left": "24", "isLinked": False}},
 "7bd2d4d6": {"width": {"unit": "%", "size": 26, "sizes": []}},
 "3ebdd092": {"width": {"unit": "%", "size": 68, "sizes": []}},
 "6afddc71": {"flex_gap": {"column": "0", "row": "16", "isLinked": False, "unit": "px", "size": 16}},
 "2042f80":  {"padding": {"unit": "px", "top": "80", "right": "0", "bottom": "96", "left": "0", "isLinked": False}},
 "28be38e9": {"flex_align_items": "stretch"},
 "1ccb4821": {"sticky": "top", "sticky_on": ["desktop"], "sticky_offset": 160, "sticky_parent": "yes", "_z_index": 1},
}
tree2 = json.load(open("blog05_tree.json", encoding="utf-8"))
hit = set()
def lwalk(nodes):
    for n in nodes:
        if n["id"] in LAYOUT:
            n.setdefault("settings", {}).update(LAYOUT[n["id"]]); hit.add(n["id"])
        lwalk(n.get("elements", []))
lwalk(tree2)
print("layout+sticky patched:", len(hit), "of", len(LAYOUT), "| missing:", set(LAYOUT)-hit or "none")
json.dump(tree2, open("blog05_tree.json", "w", encoding="utf-8"), ensure_ascii=False)
