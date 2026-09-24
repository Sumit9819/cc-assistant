#!/usr/bin/env python3
"""Replace placeholder titles in downloaded.json with what each graphic ACTUALLY says.

Every title below was read off the rendered image, not the filename. That
matters because the filenames on this channel are Canva template leftovers and
were wrong at least six times - "Semaglutide Not Working (8).png" is a Skinvive
banner, "Retinoids for Daily Care.png" is a nutrition graphic, and so on.

Also fixes two ordering problems that only became visible once the images were
looked at, and drops one superseded duplicate.
"""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))

TITLES = {
    # chemical-peel-treatment-guide (8563)
    "F0BLSBE7HGE": "How Chemical Peels Work",
    "F0BLSBF1Q3G": "Common Conditions Treated",
    "F0BLSBF2CB0": "Who Benefits Most",
    "F0BLN3UHX5H": "Step by Step Procedure",
    # weight-loss-resistance-causes (9462)
    "F0BAD1RNKJ4": "When Dieting Fails",
    "F0B9LJ0SUTT": "Toxins and Hormonal Disruption",
    "F0B9LJ19AGH": "Reigniting Metabolism Naturally",
    # world-cup-2026-heat-safety (10043)
    "F0BAP9C8RHN": "Unacclimatized Visitors",
    "F0BAZMBH125": "Earliest Signs of Dehydration",
    "F0BBS525KSL": "Heat Exhaustion vs Heat Stroke",
    "F0BAVQ4JSJ2": "World Cup Gear List",
    "F0BARFGA03V": "Hydration Guide",
    "F0BB1F5L0A0": "Stadium Heat Safety",
    "F0BB84XNFSQ": "Know Where To Go",
    # choose-weight-loss-clinic (9871)
    "F0BB8NTEXCL": "Choose Smart",
    "F0BB8NTK19S": "Qualified Providers",
    "F0BBZCQT0CQ": "Start Bloodwork",
    "F0BBZCRTV3J": "FDA or Compounded",
    "F0BAPN04XB9": "Transparent Pricing",
    "F0BAPPX8XAT": "Maintenance Plan",
    "F0BAYP2SDV1": "Watch Progress",
    "F0BAPKSLXAB": "Program vs Diet",
    "F0BB6V1TQBT": "Red Flags to Avoid",
    # not-losing-weight-on-semaglutide (10192)
    "F0BKLR97CEM": "When Does It Stop Working",
    "F0BKLRED265": "On Semaglutide",
    "F0BKGU4BDQW": "Medical Reasons",
    "F0BKLRUNK09": "When Semaglutide Stops Working",
    "F0BKH2HU78W": "Restart Your Progress",
    # hormone-pellets-vs-injections-vs-creams (10191)
    "F0BL1U1PQJV": "Pellets Injections or Creams",
    "F0BKJKDCDBR": "Pellets vs Injections vs Creams",
    "F0BKTPAK9EX": "Hormone Pellet Therapy",
    "F0BKWN0H18V": "Hormone Injections Pros and Cons",
    "F0BL1U2T6BT": "Hormone Creams and Gels",
    "F0BLUASU68G": "Compounded Hormones Society Positions",
    # top-3-anti-aging (8559)
    "F0BL9HZBQE6": "Botox for Fine Lines",
    # Titled "Botox for Fine Lines" on the artwork, but its three labels are
    # Skin Renewal / Neuromodulators / Volume Restoration - that is the
    # three-treatment overview, not the Botox panel. Named for its content.
    "F0BL7414QEA": "Top Three Anti Aging Treatments",
    "F0BL6AF81CM": "Restore Lost Volume",
    "F0BL9P8SKFU": "Retinoids for Daily Care",
    # empower-your-wellness-journey (8567)
    "F0BLM6M3N01": "Prioritize Preventive Care",
    "F0BLNV60X9S": "Longevity Through Movement",
    "F0BLM6QCMT3": "Manage Stress Mindfully",
    "F0BMDK478V6": "Evidence Based Nutrition",
    # trt-benefits-for-men (9402)
    "F0BBKM6AZ6G": "Low Testosterone Signs",
    "F0BCAAZCG80": "TRT Benefits Physical",
    "F0BBHTE8K4Z": "TRT Benefits Cognitive",
    "F0BBKM8UQ0L": "TRT Benefits Systemic",
    "F0BB9MKKRLK": "Myth vs Fact",
    "F0BB0J7CWMV": "TRT Monitoring",
    # progesterone-deficiency-symptoms (9389)
    "F0BL3F5JMKN": "Early Warning Signs",
    "F0BKXQ9TMUN": "Progesterone the Bodys Natural Peacekeeper",
    "F0BL3F2DFUL": "Luteal Phase Defects",
    "F0BL3F43068": "Fertility Impacts",
    "F0BL3F56KB6": "Restoring Levels",
    # post-birth-control-syndrome (9398)
    "F0BKZC0ET8S": "Post Birth Control Syndrome",
    "F0BKW3MHC21": "Common Symptoms Patients Experience",
    "F0BKZBZ4CN6": "Common Nutrient Depletions",
    "F0BKZBZUPCJ": "Roadmap to Natural Cycles",
    # skinvive-vs-dermal-filler (10229)
    "F0BLD11622Y": "Skinvive vs Filler Hydration Not Volume",
    "F0BL32PK66P": "Skinvive vs Dermal Filler",
    "F0BL61LF69K": "Skinvive Side Effects",
    # functional-medicine (8577)
    "F0BMD7T7QH1": "Understanding Functional Medicine",
    "F0BNDQWR2U8": "Functional vs Conventional Medicine",
    "F0BMG4WQNAH": "Functional Medicine Principles",
    "F0BMKEWNPFU": "Your First Consultation",
    # gut-health-skin-connection (8609)
    "F0BMYHZT5KN": "What Is the Gut Skin Axis",
    "F0BNP7MU2RE": "Signs of an Unhealthy Gut",
    "F0BMYHZ4PSQ": "Skin Conditions Linked to Gut Health",
    "F0BNP7M1RLY": "Healthy Gut Healthy Skin",
    # wellness-assessment (8571)
    "F0BMDTG7GFR": "What Is a Wellness Assessment",
    "F0BMT9MDLCE": "Core Wellness Pillars",
    "F0BMRTSB1NH": "What to Expect",
    "F0BMZ20R0DA": "Benefits of Holistic Health",
    # low-testosterone-brain-fog (9270)
    "F0BQHAUPV0Q": "Brain Fog and Testosterone",
    "F0BPP03E1D0": "Recognizing Symptoms",
    "F0BPQUB1VRP": "Neurological Impact of Low T",
    "F0BP7JY5415": "Advanced Testing",
    # estrogen-dominance-weight-loss (9173)
    "F0BPVS0BELE": "What is Estrogen Dominance",
    "F0BPGDYRQTZ": "Hormonal Weight Gain",
    "F0BPU780UUD": "Correcting the Balance",
    "F0BPRHWNSM9": "When to Seek Care",
}

# The file numbering did not match the argument. Only visible once viewed:
# "Untitled design" (#1) is the SOLUTIONS panel while the intro sat at #2, so
# reading order had the answer before the question.
REORDER = {
    "F0BMYHZT5KN": 1,   # What Is the Gut-Skin Axis  (was 2)
    "F0BNP7MU2RE": 2,   # Signs of an Unhealthy Gut  (was 4)
    "F0BMYHZ4PSQ": 3,   # Skin Conditions Linked     (was 3)
    "F0BNP7M1RLY": 4,   # Healthy Gut, Healthy Skin  (was 1)
}

# Unlabelled "Restore Lost Volume"; a labelled version of the same graphic
# (VOLUME / HYDRATION / SAFETY) arrived later the same day.
DROP = {"F0BL5QPULLD"}

path = os.path.join(HERE, "downloaded.json")
rows = json.load(open(path, encoding="utf-8"))

kept, dropped, renamed, missing = [], 0, 0, []
for row in rows:
    fid = row.get("id")
    if fid in DROP:
        dropped += 1
        continue
    if fid in TITLES:
        if row.get("title") != TITLES[fid]:
            renamed += 1
        row["title"] = TITLES[fid]
    else:
        missing.append("{} ({})".format(fid, row.get("title")))
    if fid in REORDER:
        row["order"] = REORDER[fid]
    kept.append(row)

kept.sort(key=lambda r: (r["slug"], r["order"]))

# Renumber so each post runs 1..n with no gap left by the dropped duplicate.
seen = {}
for row in kept:
    seen[row["slug"]] = seen.get(row["slug"], 0) + 1
    row["order"] = seen[row["slug"]]

json.dump(kept, open(path, "w", encoding="utf-8"), indent=2)
print("{} titles corrected, {} reordered, {} superseded dropped, {} kept".format(
    renamed, len(REORDER), dropped, len(kept)))
if missing:
    print("NO VERIFIED TITLE for:\n  " + "\n  ".join(missing))
