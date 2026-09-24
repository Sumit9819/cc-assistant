# Rewrite British spellings to US in every spec. The clinic is in Irving,
# Texas; "Litres of sweat" on a Texas-heat card is the tell.
#
# Deliberately literal pairs, not a stem regex: a stem like "specialis" also
# matches "Specialist", which is correct US English, and a blind rewrite would
# have introduced an error while claiming to fix one.
import glob, json, re, sys

PAIRS = [
    ("favour", "favor"), ("Favour", "Favor"),
    ("colours", "colors"), ("Colours", "Colors"),
    ("colour", "color"), ("Colour", "Color"),
    ("discolouration", "discoloration"),
    ("moisturisers", "moisturizers"), ("moisturiser", "moisturizer"),
    ("Moisturise", "Moisturize"), ("moisturise", "moisturize"),
    ("fertilised", "fertilized"),
    ("personalised", "personalized"), ("Personalised", "Personalized"),
    ("stabilised", "stabilized"), ("stabilising", "stabilizing"),
    ("fibre", "fiber"), ("Fibre", "Fiber"),
    ("behaviour", "behavior"), ("Behaviour", "Behavior"),
    ("haemoglobin", "hemoglobin"),
    ("diarrhoea", "diarrhea"),
    ("travellers", "travelers"), ("Travellers", "Travelers"),
    ("Sterilisation", "Sterilization"), ("sterilisation", "sterilization"),
]
# -is- stems must only fire before a real verb/noun suffix. Without this,
# "metabolis" matched the correct English word "metabolism" and rewrote it to
# "metabolizm" - a misspelling introduced by the spelling fixer, on 13 cards
# that then shipped. Never anchor a stem rule without pinning what follows it.
STEM_PAIRS = [
    ("acclimatis", "acclimatiz"), ("individualis", "individualiz"),
    ("neutralis", "neutraliz"), ("metabolis", "metaboliz"),
    ("sanitis", "sanitiz"), ("organis", "organiz"), ("realis", "realiz"),
]
STEM_SUFFIX = r"(e|es|ed|ing|ation|ations|er|ers)"

# word-boundary-sensitive, so "centre" does not touch "centres of excellence"
# spelled US-style and "grey" does not touch a hex like #greyish
WORD_PAIRS = [
    ("centre", "center"), ("Centre", "Center"),
    ("litres", "liters"), ("Litres", "Liters"),
    ("grey", "gray"), ("Grey", "Gray"),
]

apply = "--apply" in sys.argv
changed = {}

for spec in sorted(glob.glob("spec-*.json")):
    raw = open(spec, encoding="utf-8").read()
    doc = json.loads(raw)
    hits = []
    for card in doc["cards"]:
        before = json.dumps(card, ensure_ascii=False)
        after = before
        for a, b in PAIRS:
            after = after.replace(a, b)
        for a, b in WORD_PAIRS:
            after = re.sub(r"\b%s\b" % a, b, after)
        if after != before:
            hits.append(card["file"])
            if apply:
                card.clear()
                card.update(json.loads(after))
    if hits:
        changed[spec] = hits
        if apply:
            with open(spec, "w", encoding="utf-8") as fh:
                json.dump(doc, fh, indent=1, ensure_ascii=False)
                fh.write("\n")

total = sum(len(v) for v in changed.values())
for spec, hits in changed.items():
    print("%-22s %d" % (spec, len(hits)))
    for h in hits:
        print("    " + h)
print("\n%s: %d cards in %d specs" % ("REWROTE" if apply else "would rewrite",
                                      total, len(changed)))
