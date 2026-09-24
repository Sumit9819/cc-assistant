# -*- coding: utf-8 -*-
"""Generate blog03_build.py: Mini Excavator vs Mini Skid Steer on the blog01 pipeline.
All specs verified in-engagement: X-Cavator 2-3.5t, dig depth up to 10ft11in, Kubota,
$37,999-$58,999; X-Loader mini skid steers $27,999-$38,999; 5yr/3000hr warranty."""

src = open('blog01_build.py', encoding='utf-8').read()
head = src[:src.index('T = {}')]
tail = src[src.index('ANCHORS = {'):]
tail = tail.replace(
    '{"57bc7453":"loan-vs-lease","31a34fa4":"lenders","3a6d07e1":"covers","27ece4c1":"apply","ddeb50a":"mammoth"}',
    '{"57bc7453":"head-to-head","31a34fa4":"choose","3a6d07e1":"attachments","27ece4c1":"job-test","ddeb50a":"mammoth-lineup"}'
).replace('blog01_tree.json', 'blog03_tree.json')

EXC = "https://mammothmachinery.ca/mini-excavators/"
SKD = "https://mammothmachinery.ca/mini-skidsteers/"
FIN = "https://mammothmachinery.ca/how-does-equipment-financing-work-canada/"
ATT = "https://mammothmachinery.ca/mini-excavator-attachments-canada/"
SIZE = "https://mammothmachinery.ca/what-size-mini-excavator-do-i-need-best-2026-guide/"
A = "style='color:#01B51B;font-weight:600;'"

body_lines = []
add = body_lines.append
add('T = {}')
add('T["4649f03c"] = {"title": "Mini Excavator vs Mini Skid Steer: Which Do You Need?"}')
add('REL = [')
add(' ("What Size Mini Excavator Do I Need?", "' + SIZE + '"),')
add(' ("Mini Skid Steer vs Full Size", "https://mammothmachinery.ca/mini-skid-steer-vs-full-size/"),')
add(' ("How Equipment Financing Works in Canada", "' + FIN + '"),')
add(']')

def widget(wid, key, html):
    add('T["' + wid + '"] = {"' + key + '": ' + repr(html) + '}')

widget("234a9165", "editor",
 "<p>The short answer: a mini excavator digs below the ground you stand on, and a mini skid steer moves material across it. "
 "If your jobs are trenches, footings, and stumps, you want the excavator. "
 "If your jobs are loading, grading, and running attachments through tight gates, you want the skid steer.</p>"
 "<p>Most crews eventually run both. This guide is about which one earns its payment first, using the jobs you already do as the test.</p>")
widget("210a47c5", "title", "What Each Machine Is Built to Do")
widget("3758ba01", "editor",
 "<p>A mini excavator is a digging machine: a tracked base with a boom, arm, and bucket that reach below grade. "
 "It trenches for utilities and drainage, digs footings, pulls stumps, and places soil exactly where the bucket swings. "
 "Depth and reach are the whole point.</p>"
 "<p>A mini skid steer is a carrying and powering machine: a compact loader that lifts, hauls, and drives attachments. "
 "It moves gravel, soil, and pallets, and it turns into a different tool every time you swap the attachment on the front.</p>")
widget("33427b24", "editor",
 "<p>&#8220;Pick the machine for the ground you break most weeks, not for the busiest week you can imagine. Rent the other one when that week arrives.&#8221;</p>")
widget("224e168b", "title", "Mammoth Service Team")
widget("18e6b93a", "editor",
 "<p>That is the pattern behind most first purchases that work out. "
 "Buy for the recurring revenue, rent for the exception, and upgrade to owning both when the rental invoices start to sting. "
 "Browse the <a href='" + EXC + "' " + A + ">mini excavator lineup</a> and the <a href='" + SKD + "' " + A + ">mini skid steer lineup</a> to see what each class offers.</p>")
widget("c8d2422", "title", "Head to Head: The Differences That Decide It")
widget("26bac6ce", "editor",
 "<p>Spec sheets bury the decision in numbers. "
 "These four rows are what actually separates the two machines for a Canadian crew deciding where the first payment goes.</p>")
for wid, val in [("caa0845",""),("36cc757e","Mini excavator"),("2017a715","Mini skid steer"),
 ("aea3cff","Main job"),("5e4cb430","Digging below grade"),("c356d60","Loading, carrying, powering attachments"),
 ("4a877ef4","Mammoth range"),("6673beb8","2 to 3.5 tonne X-Cavator models"),("64f2b458","Stand-on X-Loader compact class"),
 ("233c0b5a","Price from (CAD)"),("61aeffe3","$37,999"),("5dd9abdd","$27,999"),
 ("5950c17f","First machine for"),("fc2d068","Excavation, utilities, drainage work"),("6ce6d71c","Landscaping, hardscape, material handling")]:
    widget(wid, "title", val)
widget("5748cd6e", "editor",
 "<p>Budget rarely settles it alone: the gap between the entry machines is about $10,000 CAD, both classes finance the same way, and every model carries the same 5-year / 3,000-hour warranty. "
 "The real question is which machine's main job shows up on your invoices every week.</p>")
widget("1946bfc1", "title", "Which One Fits Your Work?")
widget("38036da7", "editor", "<p>Run your last three months of jobs against these two lists. One of them will read like your calendar.</p>")
widget("516be8d8", "title", "Choose the Mini Excavator If")
widget("741baf4a", "editor",
 "<p>Your work is trenching for utilities or drainage, digging footings and frost walls, pulling stumps, or shaping grade below the surface. "
 "The X-Cavator line digs to 10 ft 11 in at the top end, with Kubota diesel power across the range.</p>")
widget("5770aa9c", "editor", "<p>Tip: not sure which size? Our <a href='" + SIZE + "' " + A + ">mini excavator size guide</a> matches tonnage to job types.</p>")
widget("4041ab29", "title", "Choose the Mini Skid Steer If")
widget("535c1364", "editor",
 "<p>Your work is moving soil, gravel, and mulch, loading and backfilling, hardscape builds, or any job where one machine must become five tools. "
 "The stand-on X-Loader class works in the tight residential access where full-size machines cannot go.</p>")
widget("77aaf3e4", "editor", "<p>Tip: comparing against a full-size machine instead? See <a href='https://mammothmachinery.ca/mini-skid-steer-vs-full-size/' " + A + ">mini vs full-size skid steers</a>.</p>")
widget("47778184", "editor",
 "<p>If both lists sound like your month, you are a two-machine operation buying in stages. "
 "Start with the machine tied to your highest-margin work, and let its revenue carry the second purchase.</p>")
widget("3dd101a3", "title", "Attachments Can Change the Answer")
widget("7da10af", "editor",
 "<p>Both machines are tool carriers, and the attachment you need most can override everything above. "
 "Mini excavators take augers, breakers, thumbs, and specialty buckets, which turns a digging machine into a drilling and demolition machine.</p>")
widget("476cfd6", "editor",
 "<p>Mini skid steers run an even wider ecosystem, from forks and grapples to trenchers. "
 "A skid steer with a trencher attachment can handle light trenching, but it will not replace an excavator for depth, and an excavator will never load a truck as fast as a skid steer.</p>")
add('T["63f0d6bb"] = {"icon_list_texts": [')
add(repr("<strong style='color:#0f0f0f;'>Digging attachments</strong>: augers, breakers, and thumbs extend the excavator well past trenching.") + ',')
add(repr("<strong style='color:#0f0f0f;'>Carrier attachments</strong>: forks, grapples, and buckets make the skid steer the yard workhorse.") + ',')
add(repr("<strong style='color:#0f0f0f;'>The overlap is thin</strong>: each machine only imitates the other slowly and expensively.") + ',')
add(']}')
widget("6d3fc163", "editor",
 "<p>Full breakdown of what fits the diggers in our <a href='" + ATT + "' " + A + ">mini excavator attachments guide</a>.</p>")
widget("3731b3f2", "title", "The Six-Question Job Test")
widget("782dbce9", "editor",
 "<p><strong>1. List your last three months of jobs</strong>, straight from the invoices, not from memory.</p>"
 "<p><strong>2. Split the hours</strong>: digging below grade versus moving and loading above it.</p>"
 "<p><strong>3. Measure your typical access</strong>: gates, side yards, and trailer width limits.</p>")
widget("3f9018cf", "editor",
 "<p><strong>4. Price the attachments</strong> you would actually buy in year one, not the whole catalog.</p>"
 "<p><strong>5. Run the financing numbers</strong> both ways using our <a href='" + FIN + "' " + A + ">equipment financing guide</a>.</p>"
 "<p><strong>6. Talk to your dealer</strong> with the split from step 2. The answer is usually obvious by then.</p>")
widget("27ccfb9d", "title", "The Mammoth Options at a Glance")
widget("21af5d20", "editor",
 "<p>Both classes are priced in CAD on every machine page, and both carry the same coverage. The short version:</p>")
for wid, val in [("3d6793d4","Class"),("12e163ec","What you get"),("2298c9db","From (CAD)"),
 ("7eed5fd5","Mini excavators"),("45ca9274","X-Cavator 20MT to 35MT, 2 to 3.5 tonnes, Kubota diesel"),("57259a3","$37,999"),
 ("22d4589f","Mini skid steers"),("4c2e0261","Stand-on X-Loader class, 50MT to 120MT"),("48a69cb","$27,999"),
 ("38aa4c0d","Warranty"),("71b0ab17","5 years / 3,000 hours on both classes"),("7deffcf0","Standard"),
 ("554eb573","Financing"),("70db32f1","Loans, leases, seasonal structures"),("5dbf68bc","Available")]:
    widget(wid, "title", val)
widget("5ed95583", "editor",
 "<p>Dig depth, payloads, and full specifications live on each machine page in the <a href='" + EXC + "' " + A + ">mini excavator</a> and <a href='" + SKD + "' " + A + ">mini skid steer</a> lineups, with spec sheets you can take to your accountant or lender.</p>")
widget("39c11577", "title", "Which is cheaper, a mini excavator or a mini skid steer?")
widget("37ebdf6b", "editor",
 "<p>Mini skid steers start lower: the Mammoth X-Loader class begins at $27,999 CAD, while the X-Cavator mini excavators start at $37,999 CAD. "
 "Attachments can narrow or widen that gap, so price the package, not just the machine.</p>")
widget("a5baf0", "title", "Can a mini skid steer dig like an excavator?")
widget("240646e", "editor",
 "<p>It can scrape, grade, and run a trencher attachment for shallow work. "
 "It cannot match an excavator below grade: depth, reach, and precision digging are what the boom and arm exist for.</p>")
widget("779403e3", "title", "Do mini excavators take attachments too?")
widget("6c034ac9", "editor",
 "<p>Yes: augers, hydraulic breakers, thumbs, and specialty buckets are the common ones. "
 "Our <a href='" + ATT + "' " + A + ">attachments guide</a> covers what fits and what each attachment is for.</p>")
widget("16984099", "title", "What if my work needs both machines?")
widget("4900ac0b", "editor",
 "<p>Buy in stages. Start with the machine tied to your most frequent billable work, rent the other for the occasional job, and finance the second machine once the first is earning. "
 "Both carry the same 5-year / 3,000-hour warranty, so the order does not change the coverage.</p>")
widget("5743c0bd", "title", "Ready to put real numbers against your jobs?")
widget("6de25e2d", "title", "Compare Both Lineups Side by Side")
widget("1ad0b190", "editor",
 "<p>Every machine page lists the CAD price, full specifications, and downloadable spec sheets. "
 "Run your job list against both classes and the right first machine picks itself.</p>")
add('BUTTONS = {')
add(' "65e38749": ("Mini Excavators →", "' + EXC + '"),')
add(' "1dc1d996": ("Mini Skid Steers →", "' + SKD + '"),')
add(' "22cd9047": ("How Financing Works →", "' + FIN + '"),')
add('}')
add('DELETE = {"5c7a64c1", "698f4713", "5f696988", "3f5c3a1", "132c5983", "20fd5f3c"}')

out = head + "\n".join(body_lines) + "\n\n" + tail
open('blog03_build.py', 'w', encoding='utf-8').write(out)
print('blog03_build.py written:', len(out), 'chars')
