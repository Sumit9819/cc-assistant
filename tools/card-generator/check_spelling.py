# Comprehensive British -> US check. Written as a full word list rather than a
# handful of stems because the piecemeal version missed "acclimatised", then
# missed "Programme": each near-miss only proved the list was too short.
# Medical terms are included deliberately - "oestrogen" on a hormone clinic
# would be the worst one to ship.
import glob, json, re, sys

PAIRS = [
    # -ise / -isation
    ("favour", "favor"), ("colour", "color"), ("behaviour", "behavior"),
    ("stabilis", "stabiliz"), ("moisturis", "moisturiz"), ("fertilis", "fertiliz"),
    ("organis", "organiz"), ("recognis", "recogniz"), ("normalis", "normaliz"),
    ("optimis", "optimiz"), ("minimis", "minimiz"), ("maximis", "maximiz"),
    ("personalis", "personaliz"), ("prioritis", "prioritiz"),
    ("acclimatis", "acclimatiz"), ("individualis", "individualiz"),
    ("neutralis", "neutraliz"), ("metabolis", "metaboliz"),
    ("sterilis", "steriliz"), ("sanitis", "sanitiz"), ("moisturis", "moisturiz"),
    ("stabilis", "stabiliz"), ("emphasis" + "e", "emphasize"),
    ("summaris", "summariz"), ("customis", "customiz"), ("standardis", "standardiz"),
    ("utilis", "utiliz"), ("realis", "realiz"), ("apologis", "apologiz"),
    ("categoris", "categoriz"), ("characteris", "characteriz"),
    ("hospitalis", "hospitaliz"), ("immunis", "immuniz"), ("mobilis", "mobiliz"),
    ("visualis", "visualiz"), ("specialis" + "e", "specialize"),
    # -re / -mme / misc
    ("centre", "center"), ("litre", "liter"), ("fibre", "fiber"),
    ("metre", "meter"), ("theatre", "theater"), ("calibre", "caliber"),
    ("programme", "program"),
    ("practise", "practice"), ("licence", "license"), ("defence", "defense"),
    ("offence", "offense"), ("pretence", "pretense"),
    ("enrol ", "enroll "), ("fulfil ", "fulfill "), ("skilful", "skillful"),
    ("labelled", "labeled"), ("labelling", "labeling"),
    ("counsellor", "counselor"), ("travell", "travel"), ("modell", "model"),
    ("jewellery", "jewelry"), ("ageing", "aging"), ("judgement", "judgment"),
    ("speciality", "specialty"), ("whilst", "while"), ("amongst", "among"),
    ("grey", "gray"), ("kerb", "curb"), ("storey", "story"),
    ("aluminium", "aluminum"), ("vapour", "vapor"), ("plough", "plow"),
    # medical
    ("oestrogen", "estrogen"), ("oestradiol", "estradiol"), ("oedema", "edema"),
    ("foetal", "fetal"), ("foetus", "fetus"), ("haemo", "hemo"),
    ("haemat", "hemat"), ("anaemi", "anemi"), ("diarrhoea", "diarrhea"),
    ("paediatric", "pediatric"), ("gynaecolog", "gynecolog"),
    ("orthopaedic", "orthopedic"), ("leukaemia", "leukemia"),
    ("caesarean", "cesarean"), ("anaesthe", "anesthe"), ("oesophag", "esophag"),
    ("tumour", "tumor"), ("sulph", "sulf"), ("dietician", "dietitian"),
    ("orthopaedi", "orthopedi"), ("coeliac", "celiac"), ("aetiolog", "etiolog"),
    ("hypercalcaemia", "hypercalcemia"), ("glycaemi", "glycemi"),
    ("lipaemi", "lipemi"), ("uraemi", "uremi"), ("ischaemi", "ischemi"),
]
# these are correct US English and must never be flagged
SAFE = re.compile(r"specialist|specialists|exercise|promise|supervise|compromise|"
                  r"malaise|premature|comprise|surprise|advertise|franchise|"
                  r"merchandise|expertise|paradise|wise|rise|noise|precise|concise|"
                  r"realistic|realistically|phytoestrogens?|phytoestrogen|"
                  r"metabolisms?|organisms?|individualisms?|realisms?|"
                  r"neutralisms?|hypothyroidism|hyperthyroidism|metabolic",
                  re.I)

seen = {}
for spec in sorted(glob.glob("spec-*.json")):
    for c in json.load(open(spec, encoding="utf-8"))["cards"]:
        blob = json.dumps(c, ensure_ascii=False)
        for pat, fix in PAIRS:
            for m in re.finditer(re.escape(pat), blob, re.I):
                word_m = re.search(r"[A-Za-z]*%s[A-Za-z]*" % re.escape(pat),
                                   blob[max(0, m.start() - 20):m.start() + 30], re.I)
                word = word_m.group(0) if word_m else pat
                if SAFE.fullmatch(word):
                    continue
                seen.setdefault((c.get("file"), fix), (spec, word))

for (f, fix), (spec, word) in sorted(seen.items()):
    print("%-14s %-46s %-16s %s" % (spec.replace("spec-", "").replace(".json", ""),
                                    f, fix, word))
print("\n%d card/spelling hits across %d cards"
      % (len(seen), len({f for f, _ in seen})))
sys.exit(1 if seen else 0)
