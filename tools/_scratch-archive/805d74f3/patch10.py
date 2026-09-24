"""Three more keyword kinds from the 2026 hooks (slug, big, swap), and the two
scripts rewritten to the 2026 grammar with their plans re-keyed."""
import json
import re
from pathlib import Path

ROOT = Path("D:/faceless-studio")

# ---------------------------------------------------------------- keywords.py: prefixes
p = ROOT / "fvs/keywords.py"; s = p.read_text(encoding="utf-8")
old = '''        if inner.strip() == "?":
            following = strip_marks(raw[m.end():]).split()
            out.append({"text": "?", "kind": "question", "anchor": following[0] if following else ""})'''
new = '''        if inner.strip() == "?":
            following = strip_marks(raw[m.end():]).split()
            out.append({"text": "?", "kind": "question", "anchor": following[0] if following else ""})
        elif inner[:1] in "!@=":
            # ! a giant reveal word   @ a dated slug, small, top-left
            # = a label that replaces the previous item in place
            kind = {"!": "big", "@": "slug", "=": "swap"}[inner[0]]
            body = inner[1:].strip()
            out.append({"text": body, "kind": kind, "anchor": body})'''
assert old in s; s = s.replace(old, new)
old = '''    def repl(m: re.Match) -> str:
        inner = STRIKE.sub("", m.group(1))
        return "" if inner.strip() == "?" else inner'''
new = '''    def repl(m: re.Match) -> str:
        inner = STRIKE.sub("", m.group(1))
        if inner.strip() == "?":
            return ""
        return inner[1:].strip() if inner[:1] in "!@=" else inner'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- keyword_run.html: the kinds
p = ROOT / "motion/keyword_run.html"; s = p.read_text(encoding="utf-8")
old = '''  .kw.question { font-size: 220px; color: #deab4e; }'''
new = '''  .kw.question { font-size: 220px; color: #deab4e; }
  /* A giant reveal word: one per shot, fills the width. */
  .kw.big { font-size: 260px; letter-spacing: -0.04em; color: #deab4e; }
  /* A dated slug: small, mono-spaced feel, top-left, the way 2026 hooks
     stamp a date over a scene before anything is explained. */
  #slug { position: absolute; left: 96px; top: 120px; font: 600 34px "Sora"; letter-spacing: 4px;
          text-transform: uppercase; color: #f6f2eb; opacity: 0;
          text-shadow: 0 2px 0 rgba(8,9,12,.9), 0 0 24px rgba(8,9,12,.8); }
  #slug::before { content: ""; display: inline-block; width: 4px; height: 30px; background: #deab4e;
                  margin-right: 16px; vertical-align: -4px; }'''
assert old in s; s = s.replace(old, new)
old = '''<div id="row"></div>'''
new = '''<div id="slug"></div>
<div id="row"></div>'''
assert old in s; s = s.replace(old, new)
old = '''    els = S.items.map((it) => {
      const d = document.createElement("div");
      d.className = "kw " + (it.kind || "word") + (/\\d/.test(it.text) ? " num" : "");
      d.textContent = it.kind === "question" ? "?" : it.text;
      row.appendChild(d);
      return d;
    });'''
new = '''    const slugEl = document.getElementById("slug");
    slugEl.textContent = "";
    slugItem = null;
    els = S.items.map((it) => {
      if (it.kind === "slug") { slugItem = it; slugEl.textContent = it.text; return null; }
      const d = document.createElement("div");
      d.className = "kw " + (it.kind || "word") + (/\\d/.test(it.text) ? " num" : "");
      d.textContent = it.kind === "question" ? "?" : it.text;
      row.appendChild(d);
      return d;
    });
    // A swap replaces the item before it: both occupy one slot.
    swapOf = {};
    S.items.forEach((it, i) => { if (it.kind === "swap" && i > 0) swapOf[i - 1] = i; });'''
assert old in s; s = s.replace(old, new)
old = '''  let els = [];'''
new = '''  let els = [];
  let slugItem = null, swapOf = {};'''
assert old in s; s = s.replace(old, new)
old = '''    els.forEach((el, i) => {
      const it = S.items[i];
      const q = (t - it.at) / 0.28;              // 0.28s pop with overshoot
      const inn = back(q);
      const vis = t >= it.at ? 1 : 0;
      el.style.opacity = vis * Math.min(easeOut(q * 2.5), out);
      el.style.transform = `translateY(${(1 - easeOut(q)) * 22}px) scale(${0.82 + 0.18 * inn})`;'''
new = '''    if (slugItem) {
      const sq = easeOut((t - slugItem.at) / 0.35);
      const slugEl = document.getElementById("slug");
      slugEl.style.opacity = (t >= slugItem.at ? sq : 0) * out;
      slugEl.style.transform = `translateX(${(1 - sq) * -18}px)`;
    }
    els.forEach((el, i) => {
      if (!el) return;
      const it = S.items[i];
      const q = (t - it.at) / 0.28;              // 0.28s pop with overshoot
      const inn = back(q);
      let vis = t >= it.at ? 1 : 0;
      // The item a swap replaces flips away as the swap lands.
      if (swapOf[i] !== undefined && t >= S.items[swapOf[i]].at) vis = 0;
      if (it.kind === "swap") {
        el.style.opacity = vis * Math.min(easeOut(q * 2.5), out);
        el.style.transform = `perspective(900px) rotateX(${(1 - easeOut(q)) * -80}deg)`;
        return;
      }
      el.style.opacity = vis * Math.min(easeOut(q * 2.5), out);
      el.style.transform = `translateY(${(1 - easeOut(q)) * 22}px) scale(${0.82 + 0.18 * inn})`;'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- REAL ID v2
rid = ROOT / "projects/real-id"
(rid / "script.md").write_text("""# Cold open

*@May 7, 2025.* At every airport in America, the officer at the front of the security line starts reading your licence differently.

I have one of the old cards in my wallet. It flew for years. But on that morning it stopped being an ID at all.

The rule behind that took *twenty years* to arrive. *!And when it did, it sent a bill.*

# It is not a national ID

Take your licence out. If there is a small star in the corner, you are fine. If not, it is not you the rule is judging. It is your state.

[slowly] *REAL ID* is not a card. It is a *standard*.

Congress passed it in *2005*, the year after the nine-eleven commission asked for one. It told every state how to check a person before printing a licence, and it told federal agencies to stop accepting licences that did not.

The star just means your state complied.

# The twenty years

The first deadline was *May 2008*. No state was ready.

The deadline moved. *2011*. *2013*. *2020*. *2021*. *2023*. Each one printed in the Federal Register, each one giving the states more time.

*May 7, 2025* was the one that held.

# At the checkpoint

Since that morning, a licence without the star does not work at a TSA checkpoint. A passport still does.

When enforcement began, *81%* of travellers were already carrying something acceptable. The rest went through extra screening.

The slow way then got a price. On *February 1, 2026*, TSA began charging *$45* to verify you another way, good for *ten days* of travel.

That is the bill I promised you. It arrived nine months after the deadline.

# What it tells you

Here is what I take from it. A rule is not what the law says. It is what happens at the desk.

For all those years the desk waved everyone through, and the law was a suggestion.

[slowly] One deadline that held, and a fee, made it real.

# Outro

Thanks for watching. If you want more of what a rule actually does, subscribe, and the next one will find you.
""", encoding="utf-8")
cards = {
 "note": "Re-keyed to the 2026-grammar script (2026-09-05). Figures come from tsa.gov and dhs.gov pages read directly.",
 "paragraphs": {
  "4":  {"kind": "section", "number": "01", "title": "It is not a national ID"},
  "8":  {"kind": "section", "number": "02", "title": "The twenty years"},
  "9":  {"kind": "timeline", "variant": "track", "eyebrow": "Seven deadlines, six extensions",
         "source": "DHS rules in the Federal Register, 2008 to 2023", "span": 21, "unit": "years",
         "stops": [{"at": 3, "label": "May 2008", "note": "first deadline"}, {"at": 8, "label": "Jan 2013", "note": "moved"},
                   {"at": 15, "label": "Oct 2020", "note": "moved"}, {"at": 20, "label": "May 2025", "note": "held"}]},
  "12": {"kind": "section", "number": "03", "title": "At the checkpoint"},
  "13": {"kind": "stat", "value": "81%", "label": "of travellers were already carrying acceptable ID when enforcement began", "source": "TSA, 24 Apr 2025"},
  "16": {"kind": "section", "number": "04", "title": "What it tells you"},
 }}
(rid / "cards.json").write_text(json.dumps(cards, indent=1, ensure_ascii=False), encoding="utf-8")
sp = json.loads((rid / "sceneplan.json").read_text(encoding="utf-8"))
new_sp = {"note": sp["note"], "paragraphs": {"6": sp["paragraphs"]["5"], "11": sp["paragraphs"]["10"], "14": sp["paragraphs"]["14"], "19": sp["paragraphs"]["19"]}}
(rid / "sceneplan.json").write_text(json.dumps(new_sp, indent=1, ensure_ascii=False), encoding="utf-8")
anchors = {"note": "2026 grammar: the picture changes on the word, to the thing named; the first turn lands with a visual change. No fabricated ID documents.",
 "anchors": [
  {"phrase": "At every airport in America", "query": "airport security checkpoint queue"},
  {"phrase": "the officer at the front", "query": "tsa security checkpoint airport travelers"},
  {"phrase": "I have one of the old cards", "query": "hand taking card out of wallet close up"},
  {"phrase": "But on that morning", "query": "airport departures board close up"},
  {"phrase": "The rule behind that", "query": "congress capitol building exterior"},
  {"phrase": "Take your licence out", "query": "person holding wallet airport"},
  {"phrase": "It is your state", "query": "dmv counter clerk hands paperwork"},
  {"phrase": "REAL ID is not a card", "query": "plastic card printing machine close up"},
  {"phrase": "Congress passed it", "query": "government building columns"},
  {"phrase": "The star just means", "query": "number ticket dispenser waiting room"},
  {"phrase": "The first deadline", "query": "people waiting in line government office"},
  {"phrase": "The deadline moved", "query": "calendar pages turning"},
  {"phrase": "Each one printed", "query": "printed regulations pages"},
  {"phrase": "was the one that held", "query": "airport security checkpoint sign"},
  {"phrase": "Since that morning", "query": "airport security line bags"},
  {"phrase": "A passport still does", "query": "american passport in hand airport"},
  {"phrase": "When enforcement began", "query": "travelers walking airport terminal"},
  {"phrase": "The rest went through", "query": "airport security screening bags conveyor"},
  {"phrase": "The slow way then got a price", "query": "airport check in kiosk screen"},
  {"phrase": "That is the bill I promised", "query": "credit card payment terminal close up"},
  {"phrase": "A rule is not what the law says", "query": "law books on shelf library"},
  {"phrase": "It is what happens at the desk", "query": "airport check in desk agent hands"},
  {"phrase": "For all those years", "query": "airport terminal time lapse crowd"},
  {"phrase": "One deadline that held", "query": "calendar date circled red pen"}]}
(rid / "anchors.json").write_text(json.dumps(anchors, indent=1, ensure_ascii=False), encoding="utf-8")
q = json.loads((rid / "queries.json").read_text(encoding="utf-8"))
pools = q["paragraphs"]
# paragraphs shifted: old 3->4 ... map by content roughly; simplest: rebuild with the anchors' queries plus generic airport pools
generic = ["airport terminal wide", "airport security line bags", "travelers walking airport terminal", "boarding gate travelers"]
q["paragraphs"] = {str(i): generic for i in range(1, 20)}
q["paragraphs"]["4"] = ["person holding wallet airport", "cards in wallet macro"]
q["paragraphs"]["8"] = ["waiting room chairs empty", "queue of people indoors"]
q["paragraphs"]["9"] = ["calendar pages turning", "clock hands moving time lapse", "desk calendar flipping", "printed regulations pages"]
q["paragraphs"]["14"] = ["contactless card payment", "receipt printing terminal"]
q["paragraphs"]["16"] = ["law books on shelf library", "courtroom empty wide"]
(rid / "queries.json").write_text(json.dumps(q, indent=1, ensure_ascii=False), encoding="utf-8")

# ---------------------------------------------------------------- penny v2
pc = ROOT / "projects/penny-cost"
(pc / "script.md").write_text("""# Cold open

*@November 12, 2025.* Philadelphia. A press that has run for *232 years* strikes one more coin, and the Treasurer keeps it.

It is a penny. I have a jar of them. So do you. But that one was the last.

*!Nobody voted to end it.*

# It is not copper

Pick one up. Feel the weight. Now guess what it is made of.

[slowly] It is barely copper at all.

Since *1982*, a penny has been *97.5% zinc*, wrapped in a copper skin thin enough to scratch off with a key. Copper had got too expensive to spend on a penny.

# The number

The Mint publishes what each coin costs to make. I went looking for the penny's line.

*3.69 cents*. To make a coin worth one.

The Mint's own figure. Metal, presses, wages, shipping.

Picture four pennies going in, and one cent coming out.

Across *3.2 billion* coins, that is *$85.3 million* lost in a single year. And the Mint had been losing on the cent every year since *2006*.

# How it ended

Here is the part that surprised me. Not that it ended. How.

It did not end with a law.

In *February 2025*, the President told the Treasury to stop. In May, the Mint stopped buying blanks. In November, it struck the last one.

[slowly] Ended by a memo, not a vote.

# What it tells you

What does a dead coin tell you about how a rule works?

*300 billion* pennies are still out there. Still legal. Still spendable.

The bill that formally retires it passed the House in July and the Senate in August. But nobody has signed it.

I said nobody voted to end it. They voted after it was already dead.

# Outro

Thanks for watching. If you want more of what a rule actually does, subscribe, and the next one will find you.
""", encoding="utf-8")
cards = json.loads((pc / "cards.json").read_text(encoding="utf-8"))
old = cards["paragraphs"]
new_cards = {
 "4": {"kind": "section", "number": "01", "title": "It is not copper"},
 "6": old["5"],
 "7": {"kind": "section", "number": "02", "title": "The number"},
 "8": old["8"], "10": old["10"], "11": old["11"],
 "12": {"kind": "section", "number": "03", "title": "How it ended"},
 "14": old["15"],
 "16": {"kind": "section", "number": "04", "title": "What it tells you"},
}
cards["paragraphs"] = new_cards
(pc / "cards.json").write_text(json.dumps(cards, indent=1, ensure_ascii=False), encoding="utf-8")
stills = json.loads((pc / "stillplan.json").read_text(encoding="utf-8"))
sp_old = stills["paragraphs"]
stills["paragraphs"] = {"6": sp_old["5"], "11": sp_old["11"], "14": sp_old["15"]}
(pc / "stillplan.json").write_text(json.dumps(stills, indent=1, ensure_ascii=False), encoding="utf-8")
sc = json.loads((pc / "sceneplan.json").read_text(encoding="utf-8"))
so = sc["paragraphs"]
sc["paragraphs"] = {"9": so["9"], "15": so["14"], "17": so["18"], "20": so["21"]}
sc["paragraphs"]["15"]["highlight_at"] = 0.6
(pc / "sceneplan.json").write_text(json.dumps(sc, indent=1, ensure_ascii=False), encoding="utf-8")
anchors = json.loads((pc / "anchors.json").read_text(encoding="utf-8"))
anchors["anchors"] = [
  {"phrase": "Philadelphia", "query": "coin minting press stamping coins"},
  {"phrase": "strikes one more coin", "query": "coin press machinery factory workers"},
  {"phrase": "It is a penny", "query": "hand holding a single penny close up"},
  {"phrase": "I have a jar of them", "query": "coins in glass jar close up"},
  {"phrase": "But that one was the last", "query": "empty factory floor machines idle"},
  {"phrase": "Nobody voted to end it", "query": "united states capitol building exterior"},
  {"phrase": "Pick one up", "query": "hand picking up coins close up"},
  {"phrase": "Now guess what it is made of", "query": "penny in open palm close up"},
  {"phrase": "It is barely copper at all", "query": "scratched copper coin macro"},
  {"phrase": "wrapped in a copper skin", "query": "copper electroplating industrial process"},
  {"phrase": "Copper had got too expensive", "query": "copper wire spools warehouse"},
  {"phrase": "The Mint publishes", "query": "coin counting machine close up"},
  {"phrase": "I went looking", "query": "annual report pages turning close up"},
  {"phrase": "Three point six nine cents", "query": "single penny on black background macro"},
  {"phrase": "The Mint's own figure", "query": "financial report table close up"},
  {"phrase": "Metal, presses, wages, shipping", "query": "coin press machinery factory workers"},
  {"phrase": "Picture four pennies", "query": "pennies stacked on table"},
  {"phrase": "lost in a single year", "query": "stack of coins falling over slow motion"},
  {"phrase": "Here is the part that surprised me", "query": "hands turning pages of a report close up"},
  {"phrase": "How.", "query": "empty senate chamber wide shot"},
  {"phrase": "It did not end with a law", "query": "law books on shelf library"},
  {"phrase": "In May", "query": "coin blanks conveyor factory"},
  {"phrase": "Ended by a memo", "query": "official document on desk overhead"},
  {"phrase": "What does a dead coin", "query": "old coins on table macro"},
  {"phrase": "Three hundred billion", "query": "pennies pile close up macro"},
  {"phrase": "Still legal", "query": "cashier hand giving coins as change"},
  {"phrase": "The bill that formally retires it", "query": "united states capitol building exterior"},
  {"phrase": "the Senate in August", "query": "capitol dome dusk"},
  {"phrase": "But nobody has signed it", "query": "pen lying on unsigned document close up"},
  {"phrase": "I said nobody voted", "query": "old coins collection macro"}]
(pc / "anchors.json").write_text(json.dumps(anchors, indent=1, ensure_ascii=False), encoding="utf-8")
print("patched keywords, template, both scripts and plans")
