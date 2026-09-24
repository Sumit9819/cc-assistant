# -*- coding: utf-8 -*-
"""Generate blog02_build.py: warranty article T-map on the blog01 pipeline."""
import io

src = open('blog01_build.py', encoding='utf-8').read()
head = src[:src.index('T = {}')]
tail = src[src.index('ANCHORS = {'):]
tail = tail.replace(
    '{"57bc7453":"loan-vs-lease","31a34fa4":"lenders","3a6d07e1":"covers","27ece4c1":"apply","ddeb50a":"mammoth"}',
    '{"57bc7453":"covered-vs-not","31a34fa4":"engines","3a6d07e1":"questions","27ece4c1":"register","ddeb50a":"mammoth-warranty"}'
).replace('blog01_tree.json', 'blog02_tree.json')

W = "https://mammothmachinery.ca/mammoth-machinery-warranty/"
F = "https://mammothmachinery.ca/how-does-equipment-financing-work-canada/"
A = "style='color:#01B51B;font-weight:600;'"

def E(s):  # keep quotes simple
    return s

body_lines = []
add = body_lines.append
add('T = {}')
add('T["4649f03c"] = {"title": "What Does a 5-Year Equipment Warranty Actually Cover?"}')
add('REL = [')
add(' ("How Equipment Financing Works in Canada", "' + F + '"),')
add(' ("Concrete Buggy Canada: Uses & Specs", "https://mammothmachinery.ca/what-is-a-concrete-buggy/"),')
add(' ("What Size Mini Excavator Do I Need?", "https://mammothmachinery.ca/what-size-mini-excavator-do-i-need-best-2026-guide/"),')
add(']')

def widget(wid, key, html):
    add('T["' + wid + '"] = {"' + key + '": ' + repr(html) + '}')

widget("234a9165", "editor",
 "<p>A real equipment warranty covers the expensive failures: the structure, the hydraulics, and the drive systems that move the machine. "
 "It runs for a set term or a set number of operating hours, whichever arrives first. "
 "The difference between a strong warranty and a weak one lives in the exclusions page, not the headline number.</p>"
 "<p>Here is how to read equipment warranty coverage before you buy, and exactly what Mammoth's 5-Year / 3,000-Hour Warranty includes.</p>")
widget("210a47c5", "title", "Years and Hours: How Warranty Clocks Work")
widget("3758ba01", "editor",
 "<p>Equipment warranties run two clocks at once: calendar time and operating hours. "
 "Coverage ends at whichever limit you reach first. "
 "That structure is fair to both sides, because a machine running 1,500 hours a year works far harder than one running 300.</p>"
 "<p>Mammoth's coverage runs five years or 3,000 total operating hours, whichever occurs first, starting on delivery to the original purchaser. "
 "A typical compact-equipment owner running 400 to 600 hours a year gets the full five calendar years.</p>")
widget("33427b24", "editor",
 "<p>&#8220;A warranty is only as good as its exclusions page. Read what is not covered first, then read what is.&#8221;</p>")
widget("224e168b", "title", "Mammoth Service Team")
widget("18e6b93a", "editor",
 "<p>That is the habit that separates buyers who get surprised from buyers who do not. "
 "For a live example of coverage laid out in plain language, the <a href='" + W + "' target='_blank' rel='noopener' " + A + ">Mammoth warranty page</a> lists the covered systems, the exclusions, and the claim terms on one page.</p>")
widget("c8d2422", "title", "What a Strong Warranty Covers (and What It Does Not)")
widget("26bac6ce", "editor",
 "<p>Coverage maps to who built each part. "
 "The machine maker warrants what it engineers: frames, hydraulics, drive systems, and structural welds. "
 "The engine maker warrants the engine under its own program. Here is how that plays out.</p>")
for wid, val in [("caa0845","Component area"),("36cc757e","Mammoth's warranty"),("2017a715","What to check on any brand"),
 ("aea3cff","Structure"),("5e4cb430","Chassis frames and structural weldments covered"),("c356d60","Term length and hours cap"),
 ("4a877ef4","Hydraulics"),("6673beb8","Hydraulic pumps, drive motors, control valves covered"),("64f2b458","Whether pumps and motors are included"),
 ("233c0b5a","Engine"),("61aeffe3","Covered by Kubota or Yanmar's own warranty"),("5dd9abdd","The engine maker's terms and network"),
 ("5950c17f","Registration"),("fc2d068","Within 30 days of delivery"),("6ce6d71c","Deadline and what proof is needed")]:
    widget(wid, "title", val)
widget("5748cd6e", "editor",
 "<p>Two details deserve special attention on any brand. "
 "First, when the clock starts: Mammoth coverage begins immediately on delivery to the original purchaser, with no waiting period. "
 "Second, the registration window: Mammoth machines register within 30 days of delivery. "
 "Registration windows are common across the industry, and missing one is the easiest way to complicate a future claim.</p>")
widget("1946bfc1", "title", "Why Engines Carry Their Own Warranty")
widget("38036da7", "editor", "<p>Buyers often expect one warranty to cover the whole machine. In practice the coverage splits, and the split works in your favour.</p>")
widget("516be8d8", "title", "The Machine Maker Covers")
widget("741baf4a", "editor",
 "<p>The systems it engineers and builds: chassis frames, hydraulic pumps and motors, control valves, drive systems, and structural welds. "
 "On Mammoth machines that means powertrain, hydraulics, and structure for the full term.</p>")
widget("5770aa9c", "editor", "<p>Tip: these are the highest-cost repairs on a compact machine, which is exactly where you want the long coverage.</p>")
widget("4041ab29", "title", "The Engine Maker Covers")
widget("535c1364", "editor",
 "<p>Engines integrated into Mammoth equipment, primarily Kubota and Yanmar, are covered by the engine manufacturer under its own warranty terms and approval process. "
 "Engine claims run through networks that specialize in exactly those engines.</p>")
widget("77aaf3e4", "editor", "<p>Tip: a global engine network often means faster parts and service for engine work than any single equipment brand could offer.</p>")
widget("47778184", "editor",
 "<p>This split is standard across the industry, and it is good for buyers. "
 "The costliest single component on the machine gets specialist coverage from the company that built it, while the machine maker stands behind everything it engineered around that engine.</p>")
widget("3dd101a3", "title", "Questions to Ask Before You Buy")
widget("7da10af", "editor",
 "<p>Five minutes of questions before purchase saves weeks of frustration after. "
 "Ask the dealer these, and expect direct answers with page references, not reassurances.</p>")
widget("476cfd6", "editor",
 "<p>Get the answers in writing where you can. "
 "A seller confident in its coverage will happily point at the exact clause, and hesitation on any of these questions tells you something useful too.</p>")
add('T["63f0d6bb"] = {"icon_list_texts": [')
add(repr("<strong style='color:#0f0f0f;'>When does the clock start?</strong> On Mammoth machines, immediately on delivery to the original purchaser.") + ',')
add(repr("<strong style='color:#0f0f0f;'>What is the registration window?</strong> Mammoth machines register within 30 days of delivery.") + ',')
add(repr("<strong style='color:#0f0f0f;'>What exactly is excluded?</strong> Ask for the exclusions list itself, not a summary of it.") + ',')
add(']}')
widget("6d3fc163", "editor",
 "<p>One more worth asking on any brand: who handles the claim, the dealer or the manufacturer, and how long approvals typically take in season.</p>")
widget("3731b3f2", "title", "How to Keep Your Coverage Valid")
widget("782dbce9", "editor",
 "<p><strong>1. Register on time</strong>, with the serial number and delivery date, inside the registration window.</p>"
 "<p><strong>2. Keep maintenance records</strong>: dates, hours, filters, and fluids for every service.</p>"
 "<p><strong>3. Use specified fluids and parts</strong>, because off-spec substitutions are a classic claim complication.</p>")
widget("3f9018cf", "editor",
 "<p><strong>4. Document issues early</strong> with photos and the hour-meter reading the day you notice them.</p>"
 "<p><strong>5. Call your dealer before third-party repairs</strong>, so the claim path stays clean.</p>"
 "<p><strong>6. Keep every receipt</strong>. A complete paper trail is the fastest route through any approval.</p>")
widget("27ccfb9d", "title", "The Mammoth 5-Year / 3,000-Hour Warranty at a Glance")
widget("21af5d20", "editor",
 "<p>Every machine in all six Mammoth equipment categories carries the same coverage. The terms in brief:</p>")
for wid, val in [("3d6793d4","Item"),("12e163ec","Mammoth's terms"),("2298c9db","Notes"),
 ("7eed5fd5","Term"),("45ca9274","5 years or 3,000 hours"),("57259a3","Whichever limit arrives first"),
 ("22d4589f","Coverage starts"),("4c2e0261","On delivery"),("48a69cb","Original purchaser, no waiting period"),
 ("38aa4c0d","Covers"),("71b0ab17","Powertrain, hydraulics, structure"),("7deffcf0","Engines by Kubota / Yanmar separately"),
 ("554eb573","Registration"),("70db32f1","Within 30 days of delivery"),("5dbf68bc","Keep your delivery date handy")]:
    widget(wid, "title", val)
widget("5ed95583", "editor",
 "<p>The full terms, covered components, and exclusions are published on the <a href='" + W + "' " + A + ">Mammoth warranty page</a>. "
 "Warranty strength also matters when you borrow: a machine that holds its value is better collateral, which is covered in our guide to <a href='" + F + "' " + A + ">how equipment financing works in Canada</a>.</p>")
widget("39c11577", "title", "Does the 5-year warranty cover the engine?")
widget("37ebdf6b", "editor",
 "<p>Engines integrated into Mammoth equipment, primarily Kubota and Yanmar, are covered by the engine manufacturer under its own warranty terms. "
 "Everything Mammoth engineers around the engine, including powertrain, hydraulics, and structure, is covered by the Mammoth warranty.</p>")
widget("a5baf0", "title", "When does warranty coverage start?")
widget("240646e", "editor",
 "<p>Immediately on delivery to the original purchaser, with no waiting period. "
 "Register the machine within 30 days of delivery to keep everything clean.</p>")
widget("779403e3", "title", "What happens after 3,000 operating hours?")
widget("6c034ac9", "editor",
 "<p>Coverage runs to whichever limit arrives first, years or hours. "
 "A high-hour operation can reach 3,000 hours before year five, so plan major maintenance with the hour meter in mind, not just the calendar.</p>")
widget("16984099", "title", "Does a warranty matter if I finance the machine?")
widget("4900ac0b", "editor",
 "<p>Yes, and to the lender as well as to you. "
 "Strong coverage supports resale value, and a machine that holds value is better collateral. "
 "See our guide to <a href='" + F + "' " + A + ">equipment financing in Canada</a> for how lenders think about it.</p>")
widget("5743c0bd", "title", "Buying this season? Read the coverage before the spec sheet.")
widget("6de25e2d", "title", "See the Mammoth Warranty in Full")
widget("1ad0b190", "editor",
 "<p>Five years or 3,000 hours on powertrain, hydraulics, and structure, standard on every machine in the lineup. "
 "Read the full terms, then pick the machine.</p>")
add('BUTTONS = {')
add(' "65e38749": ("Read the Warranty →", "' + W + '"),')
add(' "1dc1d996": ("How Financing Works →", "' + F + '"),')
add(' "22cd9047": ("Find a Dealer →", "https://mammothmachinery.ca/find-a-dealer/"),')
add('}')
add('DELETE = {"5c7a64c1", "698f4713", "5f696988", "3f5c3a1", "132c5983", "20fd5f3c"}')

out = head + "\n".join(body_lines) + "\n\n" + tail
# blog01 tail validates leftover vocab list incl 'wheelbarrow' etc — fine to reuse
open('blog02_build.py', 'w', encoding='utf-8').write(out)
print('blog02_build.py written:', len(out), 'chars')
