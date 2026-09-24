# -*- coding: utf-8 -*-
"""Build Blogs #6, #7, #8 (wheel loader trio) from the 3771 scaffold.
Specs verified 2026-08-25 off the live machine pages:
  WL4500 $68,999 CAD | Cummins QSF2.8 74 HP | ROC 4,500 lb | 0.7 m3 | dump 120 in | lift 177 in | 11,464 lb | 0-29 km/h
  TL5500 $73,999 CAD | Cummins QSF 74 HP    | ROC 5,500 lb | 1.0 m3 | dump 199 in | lift 213 in | 12,345 lb | 0-36 km/h
  WL7500 $139,999 CAD| Cummins 121 HP       | 2.0 m3 bucket | dump 125 in | lift 201 in | 0-40 km/h
ANTI-CANNIBALIZATION: the Wheel Loaders category page owns "compact wheel loader",
"wheel loader for sale" and "loaders for sale". No title here leads with those exact
phrases; each leads with informational or comparison intent instead.
"""
import io, sys, json, re

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
A = "style='color:#01B51B;font-weight:600;'"
CAT_WL = "https://mammothmachinery.ca/wheel-loaders/"
CAT_SS = "https://mammothmachinery.ca/mini-skidsteers/"
TL = "https://mammothmachinery.ca/tl5500-telescopic-wheel-loader/"
WL45 = "https://mammothmachinery.ca/wl4500-wheel-loader/"
FIN = "https://mammothmachinery.ca/how-does-equipment-financing-work-canada/"
WAR = "https://mammothmachinery.ca/what-does-5-year-equipment-warranty-cover/"
VS = "https://mammothmachinery.ca/mini-skid-steer-vs-full-size/"

BLOGS = {}

# ============================ BLOG #6 =====================================
T = {}
T["4649f03c"] = {"title": "What Is a Telescopic Wheel Loader?"}
T["234a9165"] = {"editor": f"<p>A telescopic wheel loader is a compact wheel loader whose boom extends. Instead of lifting a load straight up on a fixed arm, the boom reaches out as it rises, so the machine can place material further away and higher without driving closer. On a Mammoth TL5500 that means a dumping height of 199 inches against 120 inches on the standard WL4500, from a machine of almost the same size.</p><p>This guide covers what the extending boom actually changes on a job site, how to read reach against lift, and where it fits in the <a href='{CAT_WL}' {A}>Mammoth wheel loader range</a>.</p>"}
T["210a47c5"] = {"title": "How a Telescopic Loader Differs From a Standard One"}
T["3758ba01"] = {"editor": "<p>Both machines are articulated wheel loaders: they steer by bending in the middle, run a bucket on the front, and take the same attachments. The difference is one joint. A standard loader lifts on a fixed-length arm, so the load follows a fixed arc and the highest point sits above the front wheels. A telescopic loader adds an extending section, so the load can travel up and out.</p><p>That changes what the machine can reach without repositioning. Loading over the side of a truck box, stacking to the back of a bay, feeding a hopper across a barrier: all of these need reach, not just height.</p>"}
T["33427b24"] = {"editor": "<p>&#8220;Operators ask for more lift height when what they actually need is reach. If you are driving the loader forward to place every bucket, an extending boom removes that trip, and the trips are where the hours go.&#8221;</p>"}
T["224e168b"] = {"title": "Mammoth Service Team"}
T["18e6b93a"] = {"editor": f"<p>The trade is weight and price. The telescopic boom adds structure, so the TL5500 weighs 12,345 lb against 11,464 lb for the WL4500, and lists at $73,999 CAD against $68,999. Both run the same 74 HP Cummins engine, so the extra capability comes from the boom, not from more power. Full figures sit on the <a href='{TL}' {A}>TL5500 machine page</a>.</p>"}
T["c8d2422"] = {"title": "Four Jobs the Extending Boom Changes"}
T["26bac6ce"] = {"editor": "<p>Reach earns its money in specific places. These are the four where crews notice it first.</p>"}
T["caa0845"] = {"title": "Job"}
T["36cc757e"] = {"title": "What the boom does"}
T["2017a715"] = {"title": "Why it matters"}
T["aea3cff"] = {"title": "Loading trucks"}
T["5e4cb430"] = {"title": "Places material over the side of a high box"}
T["c356d60"] = {"title": "No repositioning between buckets"}
T["4a877ef4"] = {"title": "Stacking and storage"}
T["6673beb8"] = {"title": "Reaches to the back of a bay or bunker"}
T["64f2b458"] = {"title": "Uses the full depth of the space you pay for"}
T["233c0b5a"] = {"title": "Feeding hoppers"}
T["fc2d068"] = {"title": "Clears guardrails and screens at 199 inches"}
T["6ce6d71c"] = {"title": "One machine covers a job that needed two"}
T["5950c17f"] = {"title": "Working over obstacles"}
T["61aeffe3"] = {"title": "Places material past a fence, ditch or curb"}
T["5dd9abdd"] = {"title": "Keeps the machine on stable ground"}
T["5748cd6e"] = {"editor": "<p>None of these need a bigger machine. They need the load to go somewhere the wheels cannot follow, which is exactly what an extending boom is for.</p>"}
T["1946bfc1"] = {"title": "Reach and Lift: How to Read the Numbers"}
T["38036da7"] = {"editor": "<p>Two figures get quoted and they are not the same thing. Confusing them is the most common mistake buyers make with these machines.</p>"}
T["516be8d8"] = {"title": "Lifting Height"}
T["741baf4a"] = {"editor": "<p>How high the attachment pin travels. On the TL5500 that is 213 inches, against 177 inches on the WL4500 and 201 inches on the larger WL7500. It tells you whether the machine can get above the thing you are loading.</p>"}
T["5770aa9c"] = {"editor": "<p>Tip: measure the tallest thing you load into, then add the depth of a full bucket.</p>"}
T["4041ab29"] = {"title": "Dumping Height"}
T["535c1364"] = {"editor": "<p>How high the bucket can be while still tipping cleanly, which is the number that matters when loading. Here the gap is large: 199 inches on the TL5500 against 120 inches on the WL4500. That is the extending boom doing its work.</p>"}
T["77aaf3e4"] = {"editor": "<p>Tip: dumping height, not lifting height, decides whether you can load a given truck.</p>"}
T["47778184"] = {"editor": "<p>Capacity comes third. The TL5500 carries a rated 5,500 lb and a 1.0 cubic metre bucket, against 4,500 lb and 0.7 cubic metres on the WL4500. More reach and more capacity, from a machine that still tops out at 36 km/h for moving between yards.</p>"}
T["3dd101a3"] = {"title": "What It Costs"}
T["7da10af"] = {"editor": "<p>The TL5500 lists at $73,999 CAD, which is $5,000 above the standard WL4500. Every price is published on the machine page rather than hidden behind a quote request, so the comparison is arithmetic rather than a phone call.</p>"}
T["476cfd6"] = {"editor": "<p>Whether the reach is worth $5,000 depends on how often you reposition. Crews loading trucks all day usually make that back in the first season; a yard that mostly moves piles from A to B may not.</p>"}
T["63f0d6bb"] = {"icon_list_texts": [
 "<strong style='color:#0f0f0f;'>TL5500 telescopic: $73,999 CAD</strong>: 5,500 lb, 199 in dumping height.",
 "<strong style='color:#0f0f0f;'>WL4500 standard: $68,999 CAD</strong>: 4,500 lb, 120 in dumping height.",
 "<strong style='color:#0f0f0f;'>Same 74 HP Cummins engine</strong>: the difference is the boom, not the power.",
]}
T["6d3fc163"] = {"editor": f"<p>Both qualify for equipment financing, and payments can be structured around seasonal work. Our <a href='{FIN}' {A}>equipment financing guide</a> covers what Canadian lenders look at.</p>"}
T["3731b3f2"] = {"title": "Six Checks Before You Buy One"}
T["782dbce9"] = {"editor": "<p><strong>1. Measure what you load into</strong>: the tallest truck box or hopper you feed, at its highest edge.</p><p><strong>2. Count the repositioning</strong>: how often the operator drives forward to place a load.</p><p><strong>3. Check the ground</strong>: wheels need firmer footing than tracks, whatever the boom does.</p>"}
T["3f9018cf"] = {"editor": "<p><strong>4. Match the bucket</strong> to the material, not just to the machine.</p><p><strong>5. Confirm the attachment plate</strong> takes what you already own.</p><p><strong>6. Price both</strong>: the standard and the telescopic, against the hours the reach saves.</p>"}
T["27ccfb9d"] = {"title": "The Mammoth Wheel Loader Range at a Glance"}
T["21af5d20"] = {"editor": "<p>Three machines, every price public. Where each one fits:</p>"}
T["3d6793d4"] = {"title": "Model"}
T["12e163ec"] = {"title": "Capacity and reach"}
T["2298c9db"] = {"title": "Where it fits"}
T["7eed5fd5"] = {"title": "WL4500"}
T["45ca9274"] = {"title": "4,500 lb, 120 in dump"}
T["57259a3"] = {"title": "The standard yard loader, $68,999"}
T["22d4589f"] = {"title": "TL5500 telescopic"}
T["4c2e0261"] = {"title": "5,500 lb, 199 in dump"}
T["48a69cb"] = {"title": "Reach over trucks and barriers, $73,999"}
T["38aa4c0d"] = {"title": "WL7500"}
T["71b0ab17"] = {"title": "2.0 m&sup3; bucket, 121 HP"}
T["7deffcf0"] = {"title": "High-volume loading, $139,999"}
T["554eb573"] = {"title": "All models"}
T["70db32f1"] = {"title": "Cummins diesel"}
T["5dbf68bc"] = {"title": "5-year / 3,000-hour warranty standard"}
T["5ed95583"] = {"editor": f"<p>Full specifications and spec sheets sit on each machine page in the <a href='{CAT_WL}' {A}>wheel loader range</a>. What the warranty covers is in the <a href='{WAR}' {A}>warranty guide</a>.</p>"}
T["39c11577"] = {"title": "Is a telescopic wheel loader the same as a telehandler?"}
T["37ebdf6b"] = {"editor": "<p>No. A telehandler is built around a long boom and lifts to far greater heights, usually with forks. A telescopic wheel loader is a loader first: it articulates, carries a bucket, and the boom extends to add reach at loading height rather than to lift storeys.</p>"}
T["a5baf0"] = {"title": "Does the extending boom reduce lifting capacity?"}
T["240646e"] = {"editor": "<p>Not on this machine. The TL5500 carries a rated 5,500 lb, which is above the 4,500 lb of the standard WL4500. As with any loader, rated capacity assumes the load is carried properly, so check the load chart for work at full extension.</p>"}
T["779403e3"] = {"title": "How much more does the telescopic version cost?"}
T["6c034ac9"] = {"editor": "<p>$5,000 CAD. The TL5500 lists at $73,999 against $68,999 for the WL4500, both published on their machine pages.</p>"}
T["16984099"] = {"title": "Can it run the same attachments as a standard loader?"}
T["4900ac0b"] = {"editor": f"<p>Yes. Both use the same attachment plate, so buckets and forks move between them. Details are on the <a href='{WL45}' {A}>WL4500</a> and <a href='{TL}' {A}>TL5500</a> pages.</p>"}
T["5743c0bd"] = {"title": "Know the height you load to? Compare the two."}
T["6de25e2d"] = {"title": "See the Wheel Loader Range"}
T["1ad0b190"] = {"editor": "<p>Every machine page lists the CAD price, the full specification table, and the spec sheet to bring into a dealer conversation.</p>"}
BLOGS["blog06"] = dict(T=dict(T), REL=[("Wheel Loaders for Sale", CAT_WL), ("TL5500 Telescopic Loader", TL), ("How Equipment Financing Works", FIN)],
    BUTTONS={"65e38749": ("Wheel Loaders →", CAT_WL), "1dc1d996": ("TL5500 Specs →", TL), "22cd9047": ("How Financing Works →", FIN)},
    ANCHORS={"57bc7453":"how-it-differs","31a34fa4":"reach-and-lift","3a6d07e1":"cost","27ece4c1":"checks","ddeb50a":"lineup"},
    LEFTOVER=["mini excavator", "X-Cavator", "dig depth", "concrete bugg", "mulch"])

# ============================ BLOG #7 =====================================
T = {}
T["4649f03c"] = {"title": "How to Choose a Wheel Loader for Canadian Job Sites"}
T["234a9165"] = {"editor": f"<p>Choosing a wheel loader comes down to four numbers: what you lift, how high you place it, what the ground under the machine will take, and what the whole package costs over five years. Everything else on a spec sheet is commentary.</p><p>This guide works through those four in order, using the published figures from the <a href='{CAT_WL}' {A}>Mammoth wheel loader range</a> so the comparisons are concrete rather than general advice.</p>"}
T["210a47c5"] = {"title": "Start With What You Load, Not the Brochure"}
T["3758ba01"] = {"editor": "<p>Loaders are bought on horsepower and sold on capacity, but they are used on a loop: fill the bucket, carry it, place it, come back. The machine that wins is the one that completes that loop with the fewest movements on your actual site.</p><p>So start from the loop. What material, into what, how far away, over what ground? Those four answers narrow a range of three machines to one more reliably than any comparison chart.</p>"}
T["33427b24"] = {"editor": "<p>&#8220;The number people underbuy on is dumping height. A loader that cannot clear the truck box you actually load turns every cycle into a two-machine job, and no amount of engine power fixes it.&#8221;</p>"}
T["224e168b"] = {"title": "Mammoth Service Team"}
T["18e6b93a"] = {"editor": f"<p>That is why the order below puts placement before power. If you are weighing a wheel loader against a skid steer instead, the footprint and traction differences matter more than any of this, and they have <a href='{CAT_SS}' {A}>their own machines</a> to consider.</p>"}
T["c8d2422"] = {"title": "The Four Numbers That Decide It"}
T["26bac6ce"] = {"editor": "<p>Run your own work through these four, in this order. The shortlist usually collapses to one machine.</p>"}
T["caa0845"] = {"title": "Number"}
T["36cc757e"] = {"title": "What to check"}
T["2017a715"] = {"title": "Why it decides the machine"}
T["aea3cff"] = {"title": "Rated capacity"}
T["5e4cb430"] = {"title": "Weight of your heaviest full bucket"}
T["c356d60"] = {"title": "Undersize it and every load is a part load"}
T["4a877ef4"] = {"title": "Dumping height"}
T["6673beb8"] = {"title": "Height of the box or hopper you load"}
T["64f2b458"] = {"title": "The single most common underbuy"}
T["233c0b5a"] = {"title": "Bucket volume"}
T["fc2d068"] = {"title": "Cubic metres against your material density"}
T["6ce6d71c"] = {"title": "Light material needs volume, not weight"}
T["5950c17f"] = {"title": "Machine weight"}
T["61aeffe3"] = {"title": "What your ground and your float will carry"}
T["5dd9abdd"] = {"title": "Decides transport cost and site damage"}
T["5748cd6e"] = {"editor": "<p>Across the Mammoth range those numbers run from 4,500 lb and a 0.7 cubic metre bucket on the WL4500 up to a 2.0 cubic metre bucket and 121 HP on the WL7500, with machine weights from 11,464 lb.</p>"}
T["1946bfc1"] = {"title": "Specs Worth Attention, and Specs That Distract"}
T["38036da7"] = {"editor": "<p>Two lists, from buyers who got it right and buyers who called a year later.</p>"}
T["516be8d8"] = {"title": "Check These Closely"}
T["741baf4a"] = {"editor": "<p>Dumping height against the truck you actually load. Bucket volume against your material, because mulch and gravel fill a bucket very differently. Machine weight against your trailer. Attachment plate against the tools you already own.</p>"}
T["5770aa9c"] = {"editor": "<p>Tip: articulated steering matters on tight yards. All three Mammoth loaders bend in the middle rather than skidding.</p>"}
T["4041ab29"] = {"title": "Weigh These Lightly"}
T["535c1364"] = {"editor": "<p>Horsepower on its own, top speed beyond what your yard allows, and cab features you will not use in a 40-hour week. A 121 HP machine is not automatically the right answer; it is the right answer for 2.0 cubic metre volumes.</p>"}
T["77aaf3e4"] = {"editor": "<p>Tip: if you need reach rather than height, the telescopic option changes the maths.</p>"}
T["47778184"] = {"editor": "<p>The honest lens is five-year cost: purchase price, attachments, fuel, upkeep and transport, minus resale. A long warranty moves several of those at once.</p>"}
T["3dd101a3"] = {"title": "What It Costs"}
T["7da10af"] = {"editor": "<p>The range runs from $68,999 CAD for the WL4500 to $139,999 for the WL7500, with the telescopic TL5500 at $73,999. Every figure is published on the machine page, so you can build a budget without starting a sales conversation.</p>"}
T["476cfd6"] = {"editor": "<p>Coverage is 5 years or 3,000 hours on powertrain, hydraulics and structure, whichever comes first, on every model in the range.</p>"}
T["63f0d6bb"] = {"icon_list_texts": [
 "<strong style='color:#0f0f0f;'>From $68,999 CAD</strong>: published on every machine page, no quote wall.",
 "<strong style='color:#0f0f0f;'>5-year / 3,000-hour warranty</strong>: standard across the range.",
 "<strong style='color:#0f0f0f;'>Cummins diesel power</strong>: 74 HP on the WL4500 and TL5500, 121 HP on the WL7500.",
]}
T["6d3fc163"] = {"editor": f"<p>How Canadian lenders assess equipment purchases, and how to structure payments around seasonal billing, is in our <a href='{FIN}' {A}>equipment financing guide</a>.</p>"}
T["3731b3f2"] = {"title": "A Six-Step Buying Path"}
T["782dbce9"] = {"editor": "<p><strong>1. Weigh a full bucket</strong> of your heaviest material and set your capacity floor from it.</p><p><strong>2. Measure the tallest thing you load into</strong>, at its highest edge.</p><p><strong>3. Check your ground and your float</strong> against machine weight.</p>"}
T["3f9018cf"] = {"editor": "<p><strong>4. List the attachments</strong> you already own and confirm the plate takes them.</p><p><strong>5. Decide reach or height</strong>: standard boom or telescopic.</p><p><strong>6. Price the package</strong> against five years of use, not day one.</p>"}
T["27ccfb9d"] = {"title": "The Range at a Glance"}
T["21af5d20"] = {"editor": "<p>Three machines, one warranty, every price public:</p>"}
T["3d6793d4"] = {"title": "Model"}
T["12e163ec"] = {"title": "Key figures"}
T["2298c9db"] = {"title": "Where it fits"}
T["7eed5fd5"] = {"title": "WL4500"}
T["45ca9274"] = {"title": "4,500 lb, 0.7 m&sup3;, 74 HP"}
T["57259a3"] = {"title": "The standard yard loader, $68,999"}
T["22d4589f"] = {"title": "TL5500 telescopic"}
T["4c2e0261"] = {"title": "5,500 lb, 1.0 m&sup3;, 199 in dump"}
T["48a69cb"] = {"title": "When you need reach, $73,999"}
T["38aa4c0d"] = {"title": "WL7500"}
T["71b0ab17"] = {"title": "2.0 m&sup3; bucket, 121 HP"}
T["7deffcf0"] = {"title": "High-volume loading, $139,999"}
T["554eb573"] = {"title": "All models"}
T["70db32f1"] = {"title": "Articulated steering"}
T["5dbf68bc"] = {"title": "5-year / 3,000-hour warranty standard"}
T["5ed95583"] = {"editor": f"<p>Full specifications sit on each machine page in the <a href='{CAT_WL}' {A}>wheel loader range</a>. If reach is your constraint, the <a href='https://mammothmachinery.ca/what-is-a-telescopic-wheel-loader/' {A}>telescopic loader guide</a> explains the difference.</p>"}
T["39c11577"] = {"title": "What size wheel loader do I actually need?"}
T["37ebdf6b"] = {"editor": "<p>Start from your heaviest full bucket and the height you load to. In the Mammoth range that puts most yard work on the WL4500 at 4,500 lb, moves reach-limited work to the TL5500, and reserves the 121 HP WL7500 for high-volume material handling.</p>"}
T["a5baf0"] = {"title": "Is a bigger bucket always better?"}
T["240646e"] = {"editor": "<p>No. Bucket volume has to match material density. A 2.0 cubic metre bucket filled with wet aggregate can exceed what a smaller machine should lift, while the same bucket of mulch is well within it. Match volume to the material you move most.</p>"}
T["779403e3"] = {"title": "How much does a wheel loader cost in Canada?"}
T["6c034ac9"] = {"editor": "<p>In the Mammoth range, $68,999 to $139,999 CAD depending on capacity, with the telescopic model at $73,999. Every price is listed publicly on its machine page.</p>"}
T["16984099"] = {"title": "What does the warranty cover?"}
T["4900ac0b"] = {"editor": f"<p>5 years or 3,000 hours on powertrain, hydraulics and structure, whichever comes first, with the engine covered by Cummins. The <a href='{WAR}' {A}>warranty guide</a> explains the split in plain language.</p>"}
T["5743c0bd"] = {"title": "Got your four numbers? Put machines against them."}
T["6de25e2d"] = {"title": "Compare the Wheel Loaders"}
T["1ad0b190"] = {"editor": "<p>Every machine page lists the CAD price, the full specification table, and the downloadable spec sheet.</p>"}
BLOGS["blog07"] = dict(T=dict(T), REL=[("Wheel Loaders for Sale", CAT_WL), ("What Is a Telescopic Loader", "https://mammothmachinery.ca/what-is-a-telescopic-wheel-loader/"), ("How Equipment Financing Works", FIN)],
    BUTTONS={"65e38749": ("Wheel Loaders →", CAT_WL), "1dc1d996": ("Telescopic Guide →", "https://mammothmachinery.ca/what-is-a-telescopic-wheel-loader/"), "22cd9047": ("How Financing Works →", FIN)},
    ANCHORS={"57bc7453":"start-here","31a34fa4":"specs","3a6d07e1":"cost","27ece4c1":"buying-path","ddeb50a":"lineup"},
    LEFTOVER=["mini excavator", "X-Cavator", "dig depth", "concrete bugg", "mulch crew"])

# ============================ BLOG #8 =====================================
T = {}
T["4649f03c"] = {"title": "Wheel Loader vs Skid Steer: Which Fits Your Job Site?"}
T["234a9165"] = {"editor": f"<p>Both machines load material with a bucket and take the same kinds of attachments. They are not interchangeable. A wheel loader carries more, travels faster and works on firm open ground. A skid steer is smaller, turns in its own length and gets into places a loader cannot follow.</p><p>This guide sets the two side by side on the things that actually decide it: footprint, ground, capacity and cost, using published figures from the <a href='{CAT_WL}' {A}>wheel loader range</a> and the <a href='{CAT_SS}' {A}>mini skid steer range</a>.</p>"}
T["210a47c5"] = {"title": "The Real Difference Is Footprint and Steering"}
T["3758ba01"] = {"editor": "<p>A wheel loader articulates: it bends in the middle to steer, so the wheels follow the same track through a turn and the tyres are not scrubbed sideways. That makes it kinder to finished surfaces and efficient over distance, but it needs room to swing.</p><p>A skid steer turns by driving one side against the other. It pivots on the spot, which is why it fits behind houses and inside buildings, and why it scuffs turf when it does.</p>"}
T["33427b24"] = {"editor": "<p>&#8220;If the material has to travel more than a few machine lengths, a loader wins on cycle time. If it has to fit through a gate first, none of that matters.&#8221;</p>"}
T["224e168b"] = {"title": "Mammoth Service Team"}
T["18e6b93a"] = {"editor": "<p>That is the decision in one line: distance and volume favour the loader, access and finished ground favour the skid steer. The rest is checking the numbers against your own site.</p>"}
T["c8d2422"] = {"title": "Where Each One Wins"}
T["26bac6ce"] = {"editor": "<p>The honest split, by the kind of work rather than by brochure claim.</p>"}
T["caa0845"] = {"title": "Situation"}
T["36cc757e"] = {"title": "Better choice"}
T["2017a715"] = {"title": "Why"}
T["aea3cff"] = {"title": "Loading trucks from a stockpile"}
T["5e4cb430"] = {"title": "Wheel loader"}
T["c356d60"] = {"title": "Bigger bucket, faster travel, higher dump"}
T["4a877ef4"] = {"title": "Working behind a house"}
T["6673beb8"] = {"title": "Skid steer"}
T["64f2b458"] = {"title": "Fits a standard gate and turns on the spot"}
T["233c0b5a"] = {"title": "Moving material a long way"}
T["fc2d068"] = {"title": "Wheel loader"}
T["6ce6d71c"] = {"title": "Travels at road speed between piles"}
T["5950c17f"] = {"title": "Finished lawns and tight yards"}
T["61aeffe3"] = {"title": "Skid steer"}
T["5dd9abdd"] = {"title": "Lighter, and tracks spread the load"}
T["5748cd6e"] = {"editor": f"<p>Plenty of contractors end up owning one of each, because the two cover different halves of the same business. If you are choosing between sizes within the skid steer class instead, that has <a href='{VS}' {A}>its own comparison</a>.</p>"}
T["1946bfc1"] = {"title": "Capacity, Ground and Access"}
T["38036da7"] = {"editor": "<p>Three practical differences, with the published numbers behind them.</p>"}
T["516be8d8"] = {"title": "What They Carry"}
T["741baf4a"] = {"editor": "<p>The Mammoth wheel loaders run from a rated 4,500 lb with a 0.7 cubic metre bucket up to a 2.0 cubic metre bucket on the 121 HP WL7500. The mini skid steers run 1,000 to 1,300 lb. That is not a close comparison: it is the reason both exist.</p>"}
T["5770aa9c"] = {"editor": "<p>Tip: compare full buckets of your material, not rated capacities on paper.</p>"}
T["4041ab29"] = {"title": "Where They Fit"}
T["535c1364"] = {"editor": "<p>The mini skid steers are 34.5 to 36 inches wide, which clears a standard gate. The wheel loaders start at 11,464 lb and need open ground and a float to move between sites. Access rules more purchases than capacity does.</p>"}
T["77aaf3e4"] = {"editor": "<p>Tip: measure your tightest gate at the posts before shortlisting anything.</p>"}
T["47778184"] = {"editor": "<p>Ground condition decides the rest. Rubber tracks spread weight over soft or finished surfaces; wheels want firmer footing but roll further with less effort once they have it.</p>"}
T["3dd101a3"] = {"title": "What Each One Costs"}
T["7da10af"] = {"editor": "<p>The gap is significant and it is public. Mini skid steers start at $27,999 CAD; wheel loaders start at $68,999. Both ranges publish every price on the machine page, so the budget conversation starts from real figures.</p>"}
T["476cfd6"] = {"editor": "<p>For many crews the question is not which machine is better but which one the work pays for first. A skid steer that runs five days a week earns more than a loader that runs one.</p>"}
T["63f0d6bb"] = {"icon_list_texts": [
 "<strong style='color:#0f0f0f;'>Mini skid steers from $27,999 CAD</strong>: 1,000 to 1,300 lb, gate-width access.",
 "<strong style='color:#0f0f0f;'>Wheel loaders from $68,999 CAD</strong>: 4,500 lb and up, open-ground work.",
 "<strong style='color:#0f0f0f;'>5-year / 3,000-hour warranty</strong>: standard on both ranges.",
]}
T["6d3fc163"] = {"editor": f"<p>Financing spreads either purchase over the machine's earning life; our <a href='{FIN}' {A}>equipment financing guide</a> covers how Canadian lenders assess it.</p>"}
T["3731b3f2"] = {"title": "Six Questions That Settle It"}
T["782dbce9"] = {"editor": "<p><strong>1. How wide is your tightest access point?</strong> Under 36 inches rules out a loader immediately.</p><p><strong>2. How far does material travel?</strong> Beyond a few machine lengths, the loader pulls ahead.</p><p><strong>3. What is the ground?</strong> Finished turf and soft fill favour tracks.</p>"}
T["3f9018cf"] = {"editor": "<p><strong>4. How heavy is a full bucket</strong> of your usual material?</p><p><strong>5. How do you move it between sites?</strong> A loader needs a float, not a pickup trailer.</p><p><strong>6. Which machine works more days a year?</strong> That is the one to buy first.</p>"}
T["27ccfb9d"] = {"title": "Both Ranges at a Glance"}
T["21af5d20"] = {"editor": "<p>The two classes side by side, with published prices:</p>"}
T["3d6793d4"] = {"title": "Class"}
T["12e163ec"] = {"title": "Capacity and access"}
T["2298c9db"] = {"title": "Where it fits"}
T["7eed5fd5"] = {"title": "Mini skid steers"}
T["45ca9274"] = {"title": "1,000 to 1,300 lb, 34.5 to 36 in wide"}
T["57259a3"] = {"title": "Backyards and tight access, from $27,999"}
T["22d4589f"] = {"title": "Wheel loaders"}
T["4c2e0261"] = {"title": "4,500 lb and up, open ground"}
T["48a69cb"] = {"title": "Stockpiles and truck loading, from $68,999"}
T["38aa4c0d"] = {"title": "Telescopic loader"}
T["71b0ab17"] = {"title": "5,500 lb, 199 in dumping height"}
T["7deffcf0"] = {"title": "When you need reach as well, $73,999"}
T["554eb573"] = {"title": "Both ranges"}
T["70db32f1"] = {"title": "Universal attachment plates"}
T["5dbf68bc"] = {"title": "5-year / 3,000-hour warranty standard"}
T["5ed95583"] = {"editor": f"<p>Full specifications sit on the machine pages in the <a href='{CAT_WL}' {A}>wheel loader range</a> and the <a href='{CAT_SS}' {A}>mini skid steer range</a>.</p>"}
T["39c11577"] = {"title": "Can a skid steer do a wheel loader's work?"}
T["37ebdf6b"] = {"editor": "<p>For short distances and small volumes, yes. Across a yard, no. A mini skid steer carrying 1,300 lb needs several trips to match one bucket from a 4,500 lb loader, and it travels far slower between them.</p>"}
T["a5baf0"] = {"title": "Which is better on soft or finished ground?"}
T["240646e"] = {"editor": "<p>The skid steer, because rubber tracks spread weight over a much larger area. Wheel loaders are heavier and concentrate that weight on four tyres, which is fine on compacted surfaces and hard on lawns.</p>"}
T["779403e3"] = {"title": "Do they share attachments?"}
T["6c034ac9"] = {"editor": "<p>Not directly. Each class has its own attachment plate standard and its own size of bucket and forks. Attachments move freely within a class, not between them.</p>"}
T["16984099"] = {"title": "Which should a growing contractor buy first?"}
T["4900ac0b"] = {"editor": "<p>Usually the one matching the work you already invoice. Access-limited residential work points to a skid steer; stockpile and truck-loading work points to a loader. The machine that runs more days pays for itself sooner.</p>"}
T["5743c0bd"] = {"title": "Measured your access and your loads? Compare both."}
T["6de25e2d"] = {"title": "See Both Ranges"}
T["1ad0b190"] = {"editor": "<p>Every machine page lists the CAD price, the full specification table, and the spec sheet to take into a dealer conversation.</p>"}
BLOGS["blog08"] = dict(T=dict(T), REL=[("Wheel Loaders for Sale", CAT_WL), ("Mini Skid Steers for Sale", CAT_SS), ("Mini vs Full-Size Skid Steer", VS)],
    BUTTONS={"65e38749": ("Wheel Loaders →", CAT_WL), "1dc1d996": ("Mini Skid Steers →", CAT_SS), "22cd9047": ("How Financing Works →", FIN)},
    ANCHORS={"57bc7453":"difference","31a34fa4":"capacity-access","3a6d07e1":"cost","27ece4c1":"questions","ddeb50a":"both-ranges"},
    LEFTOVER=["mini excavator", "X-Cavator", "dig depth", "concrete bugg"])

# ============================ BUILD LOOP ==================================
DELETE = {"5c7a64c1", "698f4713", "5f696988", "3f5c3a1", "132c5983", "20fd5f3c"}
LAYOUT = {
 "4f211fc0": {"width": {"unit": "px", "size": 1300, "sizes": []}, "width_tablet": {"unit": "%", "size": 100, "sizes": []}, "padding": {"unit": "px", "top": "0", "right": "24", "bottom": "0", "left": "24", "isLinked": False}},
 "7bd2d4d6": {"width": {"unit": "%", "size": 26, "sizes": []}},
 "3ebdd092": {"width": {"unit": "%", "size": 68, "sizes": []}},
 "6afddc71": {"flex_gap": {"column": "0", "row": "16", "isLinked": False, "unit": "px", "size": 16}},
 "2042f80":  {"padding": {"unit": "px", "top": "80", "right": "0", "bottom": "96", "left": "0", "isLinked": False}},
 "28be38e9": {"flex_align_items": "stretch"},
 "1ccb4821": {"sticky": "top", "sticky_on": ["desktop"], "sticky_offset": 160, "sticky_parent": "yes", "_z_index": 1},
}

for name, cfg in BLOGS.items():
    T, REL, BUTTONS, ANCHORS = cfg["T"], cfg["REL"], cfg["BUTTONS"], cfg["ANCHORS"]
    raw = json.load(open("blog_scaffold_3771.json", encoding="utf-8"))
    applied, missing = set(), []
    def walk(nodes):
        keep = []
        for n in nodes:
            if n.get("id") in DELETE:
                applied.add(n["id"]); continue
            s = n.get("settings", {}); wid = n.get("id")
            if wid in T:
                for k, v in T[wid].items():
                    if k == "icon_list_texts":
                        items = s.get("icon_list", [])
                        if len(items) != len(v): missing.append(f"{wid}: icon_list len {len(items)} != {len(v)}")
                        for it, txt in zip(items, v): it["text"] = txt
                    else:
                        if k not in s: missing.append(f"{wid}: key {k} absent")
                        s[k] = v
                applied.add(wid)
            if wid in BUTTONS:
                s["text"] = BUTTONS[wid][0]; s.setdefault("link", {})["url"] = BUTTONS[wid][1]; applied.add(wid)
            if wid in ANCHORS:
                s["_element_id"] = ANCHORS[wid]; applied.add(wid)
            if wid == "61fcb788":
                for it, (txt, url) in zip(s.get("icon_list", []), REL):
                    it["text"] = txt; it.setdefault("link", {})["url"] = url
                applied.add(wid)
            if wid == "224e168b":
                s["link"] = {"url": CAT_WL if name != "blog08" else CAT_SS, "is_external": "", "nofollow": ""}
            n["elements"] = walk(n.get("elements", []))
            keep.append(n)
        return keep
    raw = walk(raw)
    want = set(T) | set(BUTTONS) | DELETE | {"61fcb788"} | set(ANCHORS)
    unapplied = want - applied
    blob = json.dumps(raw, ensure_ascii=False)
    em = blob.count("—") + blob.count("&#8212;") + blob.count("&mdash;")
    leftovers = [w for w in cfg["LEFTOVER"] if w.lower() in blob.lower()]
    words = len(re.findall(r"[A-Za-z][A-Za-z'\-]*", re.sub(r"<[^>]+>", " ", " ".join(
        v for wid in T for v in T[wid].values() if isinstance(v, str)))))
    hit = set()
    def lwalk(nodes):
        for n in nodes:
            if n["id"] in LAYOUT: n.setdefault("settings", {}).update(LAYOUT[n["id"]]); hit.add(n["id"])
            lwalk(n.get("elements", []))
    lwalk(raw)
    json.dump(raw, open(f"{name}_tree.json", "w", encoding="utf-8"), ensure_ascii=False)
    print(f"{name}: applied {len(applied)}/{len(want)} | unapplied {sorted(unapplied) or 'none'} | key-issues {missing or 'none'}")
    print(f"    em dashes {em} | leftovers {leftovers or 'none'} | words {words} | layout {len(hit)}/{len(LAYOUT)} | tree {len(blob)} chars")
