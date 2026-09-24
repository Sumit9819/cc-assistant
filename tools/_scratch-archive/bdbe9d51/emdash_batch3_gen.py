# -*- coding: utf-8 -*-
"""Batch 3 fix generator. Applies authored per-instance replacements to the
four blog exports, asserts each search hits exactly once and no em dash
remains in fixed widgets, writes b3_fixes.json for the queue bridge.
Keeps untouched: quote/attribution widgets 26660fd3, 4e3cb14c, 304da788,
615b51, 339b11f5; navigator _title labels (never rendered)."""
import io, sys, json, re, os

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
BASE = r"C:\Users\sumit\.claude\projects\c--Users-sumit-Local-Sites-plugintesting-app-public\bdbe9d51-2098-4917-93a9-eb1883a6d1fc\tool-results"
FILES = {
    3635: "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785902365770.txt",
    3678: "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785902393567.txt",
    3264: "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785902399605.txt",
    3658: "mcp-cc-assistant-mammothmachinery-ca-export_elementor_data-1785902407216.txt",
}
EM = "\u2014"

# (post, widget, key, [(old, new), ...])  — key "icon_list" means apply inside items' text fields
FIXES = [
 (3635,"7f15310c","editor",[("Everything else — speed, cost, maintenance, payload — is secondary","Everything else (speed, cost, maintenance, payload) is secondary")]),
 (3635,"4194c2b1","editor",[("this is efficient — the machine rolls fast","this is efficient: the machine rolls fast")]),
 (3635,"54d0628b","editor",[("That single number — ground pressure — decides","That single number (ground pressure) decides")]),
 (3635,"65dcabf0","html",[("slope grip — the one number","slope grip, the one number")]),
 (3635,"45605db3","html",[("Wheeled — Built for Speed","Wheeled: Built for Speed"),("cheap to maintain — there are no tracks to replace",
   "cheap to maintain. There are no tracks to replace"),("Tracked — Built for Traction","Tracked: Built for Traction")]),
 (3635,"3a491db0","editor",[("accessible — and when speed","accessible, and when speed")]),
 (3635,"67dabd0b","html",[("paved yards</strong> — indoor demolition","paved yards</strong>: indoor demolition"),
   ("gravel</strong> — prepared sites","gravel</strong>: prepared sites"),
   ("established ground</strong> — dry, firm","established ground</strong>: dry, firm"),
   ("Indoor use</strong> — many models","Indoor use</strong>: many models"),
   ("Frequent transport</strong> — lighter tare","Frequent transport</strong>: lighter tare"),
   ("Lower operating cost</strong> — no tracks","Lower operating cost</strong>: no tracks")]),
 (3635,"4c095b51","editor",[("sloped ground — which describes","sloped ground, which describes")]),
 (3635,"31ea91d4","html",[("fall in Canada</strong> — the thaw","fall in Canada</strong>: the thaw"),
   ("after rain</strong> — a 3 PSI","after rain</strong>: a 3 PSI"),
   ("Clay soil sites</strong> — southern Ontario","Clay soil sites</strong>: southern Ontario"),
   ("Slopes</strong> — most tracked models","Slopes</strong>: most tracked models"),
   ("beach access</strong> — cottage-country","beach access</strong>: cottage-country")]),
 (3635,"28dc20f5","editor",[("rarely regret it — lower cost","rarely regret it: lower cost")]),
 (3635,"19cf0e22","editor",[("you can carry — it determines","you can carry. It determines")]),
 (3635,"28b6bbe3","editor",[("without damaging them — they are just slower","without damaging them. They are just slower")]),
 (3635,"361b9587","editor",[("concrete or paving — the steel scratches","concrete or paving: the steel scratches")]),
 (3635,"62fdc52e","html",[("landscaping in Canada — wheeled or tracked?","landscaping in Canada: wheeled or tracked?"),
   ("perform well — but a tracked machine","perform well, but a tracked machine"),
   ("mild slopes — generally up to","mild slopes, generally up to"),
   ("abrasive surfaces — gravel, concrete edges, rock — reduces","abrasive surfaces (gravel, concrete edges, rock) reduces")]),
 (3678,"7563547d","editor",[("do the same job — lift, carry, grade, and push","do the same job: lift, carry, grade, and push")]),
 (3678,"528b4fc9","editor",[("on one side — it skids and pivots","on one side. It skids and pivots"),
   ("That one difference — tyres vs. tracks — drives","That one difference (tyres vs. tracks) drives")]),
 (3678,"1a303dfb","editor",[("matters much — on a residential lawn","matters much. On a residential lawn")]),
 (3678,"1c7f5819","editor",[("not inferior — they are different","not inferior. They are different")]),
 (3678,"5e08060a","icon_list",[("firm surfaces</strong> — concrete","firm surfaces</strong>: concrete"),
   ("semi-enclosed work</strong> — warehouses","semi-enclosed work</strong>: warehouses"),
   ("pushing</strong> — on firm ground","pushing</strong>: on firm ground"),
   ("Tighter budgets</strong> — typically","Tighter budgets</strong>: typically"),
   ("Lower maintenance</strong> — no tracks","Lower maintenance</strong>: no tracks")]),
 (3678,"343385be","editor",[("uneven ground — which describes","uneven ground, which describes")]),
 (3678,"558a7eb2","icon_list",[("wet ground</strong> — wet clay","wet ground</strong>: wet clay"),
   ("landscaping</strong> — on finished turf","landscaping</strong>: on finished turf"),
   ("fall</strong> — 6","fall</strong>: 6"),
   ("Slopes</strong> — tracks hold contact","Slopes</strong>: tracks hold contact"),
   ("rough terrain</strong> — tracks absorb","rough terrain</strong>: tracks absorb")]),
 (3678,"65f188d0","editor",[("attachment — augers, buckets, pallet forks, snow blowers, hydraulic breakers, grapples, trenchers, tillers — fits both",
   "attachment (augers, buckets, pallet forks, snow blowers, hydraulic breakers, grapples, trenchers, tillers) fits both")]),
 (3678,"29636d1e","editor",[("at both sizes — but weight class","at both sizes, but weight class")]),
 (3678,"2242e281","editor",[("favour tracks — spring thaw","favour tracks: spring thaw")]),
 (3678,"79ab713a","editor",[("revenue enabler — sites that would be","revenue enabler: sites that would be")]),
 (3678,"3a72a1bf","editor",[("PSI — roughly one-third","PSI, roughly one-third")]),
 (3678,"cd6dabf","editor",[("attachments — buckets, pallet forks, augers, snow blowers, breakers, grapples and more — are fully compatible",
   "attachments (buckets, pallet forks, augers, snow blowers, breakers, grapples and more) are fully compatible")]),
 (3678,"7fd8abb3","editor",[("in Canada — especially","in Canada, especially"),("turf or soft ground — yes","turf or soft ground, yes")]),
 (3264,"5d5f340","editor",[("</strong> — and which type suits your work — is the first step","</strong>, and which type suits your work, is the first step")]),
 (3264,"141b8ebb","editor",[("The skip — the bucket or tub at the front — fills","The skip (the bucket or tub at the front) fills")]),
 (3264,"64aeea71","html",[("Residential landscaping — moving topsoil","Residential landscaping: moving topsoil"),
   ("foundation work — moving wet concrete","foundation work: moving wet concrete"),
   ("pathway construction — aggregate transport","pathway construction: aggregate transport"),
   ("renovation — removing rubble","renovation: removing rubble"),
   ("Agricultural applications — moving feed","Agricultural applications: moving feed")]),
 (3264,"51a25162","editor",[("passes per load — or replaces","passes per load, or replaces")]),
 (3264,"7b68fb8a","html",[("cheaper to maintain — no tracks to replace","cheaper to maintain: no tracks to replace"),
   ("raised hopper — no extra ground-transfer step","raised hopper: no extra ground-transfer step"),
   ("tipping forward — useful in narrow passages","tipping forward, useful in narrow passages")]),
 (3264,"4efbfbc6","html",[("Diesel HP — affects hill climbing","Diesel HP (affects hill climbing"),
   ("&amp; speed</td>","&amp; speed)</td>")]),
 (3264,"1ce02b89","html",[("loose material — soil, gravel, concrete, rubble, aggregate, or organic material — across",
   "loose material (soil, gravel, concrete, rubble, aggregate, or organic material) across"),
   ("wet concrete — usually with a deeper","wet concrete, usually with a deeper"),
   ("health-and-safety law — such as","health-and-safety law, such as"),
   ("Safety Act</a> — requires operators","Safety Act</a>, requires operators")]),
 (3658,"15ec2f39","editor",[("compact equipment — and one of the most costly","compact equipment, and one of the most costly")]),
 (3658,"13934543","editor",[("configuration first — the right weight class","configuration first: the right weight class")]),
 (3658,"1aff804","editor",[("commercial work — most fit a standard","commercial work. Most fit a standard")]),
 (3658,"76fdf847","editor",[("One Call</a> — it is the law","One Call</a>. It is the law")]),
 (3658,"5c170b62","title",[("Key Specs — and What They Mean","Key Specs and What They Mean")]),
 (3658,"8195338","editor",[("(kN) — the force the bucket","(kN): the force the bucket")]),
 (3658,"7c39fec2","editor",[("with tolerances — measure before you commit","with tolerances. Measure before you commit")]),
 (3658,"3c07d5ce","editor",[("track width — essential next to fences","track width, essential next to fences")]),
 (3658,"69f03d2d","title",[("Rarely — check blade angle","Rarely (check blade angle)")]),
 (3658,"4d7f7006","editor",[("/year) — lower than rental","/year), lower than rental")]),
 (3658,"4824b468","editor",[("winter excavation — confirm attachment","winter excavation. Confirm attachment")]),
 (3658,"292bec19","editor",[("spring thaw — not because","spring thaw, not because")]),
]

def norm(s):
    return s.replace("&#8212;", EM).replace("&mdash;", EM)

def find_widget(nodes, wid):
    for n in nodes:
        if n.get("id") == wid:
            return n
        r = find_widget(n.get("elements", []), wid)
        if r:
            return r

trees = {}
for pid, f in FILES.items():
    trees[pid] = json.loads(json.load(open(os.path.join(BASE, f), encoding="utf-8"))["raw_data"])

out, errors = [], []
for pid, wid, key, pairs in FIXES:
    node = find_widget(trees[pid], wid)
    if not node:
        errors.append(f"{pid}/{wid}: widget not found"); continue
    if key == "icon_list":
        items = node["settings"]["icon_list"]
        blob = json.dumps(items, ensure_ascii=False)
        blob_n = norm(blob)
        for old, new in pairs:
            if blob_n.count(old) != 1:
                errors.append(f"{pid}/{wid}: '{old[:40]}' matched {blob_n.count(old)}x"); continue
            blob_n = blob_n.replace(old, new)
        if EM in blob_n:
            errors.append(f"{pid}/{wid}: em dash remains in icon_list")
        out.append({"post_id": pid, "widget_id": wid, "settings": {"icon_list": json.loads(blob_n)},
                    "n": len(pairs)})
    else:
        val = norm(node["settings"][key])
        for old, new in pairs:
            if val.count(old) != 1:
                errors.append(f"{pid}/{wid}: '{old[:40]}' matched {val.count(old)}x"); continue
            val = val.replace(old, new)
        if EM in val:
            errors.append(f"{pid}/{wid}: em dash remains: ...{val[val.find(EM)-50:val.find(EM)+50]}...")
        out.append({"post_id": pid, "widget_id": wid, "settings": {key: val}, "n": len(pairs)})

json.dump(out, open("b3_fixes.json", "w", encoding="utf-8"), ensure_ascii=False)
print(f"fixes generated: {len(out)} widgets, {sum(f['n'] for f in out)} replacements")
print(f"errors: {len(errors)}")
for e in errors:
    print("  !!", e)
