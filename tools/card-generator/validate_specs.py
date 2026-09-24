# Spec-level audit. The "undefined" defect was a CONTENT error that every
# geometry guard passed, because the layout was valid and only the text was
# wrong. This checks the shapes the renderer trusts, so a silently-blank table
# cell or a missing alt cannot ship the way a two-part title did.
import glob, json, re, sys

problems = []


def flag(spec, card, msg):
    problems.append("%-22s %-45s %s" % (spec, card, msg))


for spec in sorted(glob.glob("spec-*.json")):
    for c in json.load(open(spec, encoding="utf-8"))["cards"]:
        f = c.get("file", "<no file>")
        lay = c.get("layout")

        t = c.get("title")
        if isinstance(t, list):
            if len(t) != 3:
                flag(spec, f, "title has %d parts, needs 3" % len(t))
            if not str(t[1]).strip():
                flag(spec, f, "title highlight (part 2) is empty - nothing gets the yellow")
            if not "".join(str(x) for x in t).strip():
                flag(spec, f, "title is entirely empty")
        elif lay != "alert" and t is None:
            flag(spec, f, "no title and not an alert layout")

        alt = (c.get("alt") or "").strip()
        if not alt:
            flag(spec, f, "alt text missing")
        elif len(alt) < 25:
            flag(spec, f, "alt text only %d chars: %r" % (len(alt), alt))

        if not (c.get("heading") or "").strip():
            flag(spec, f, "no heading - body placement cannot be matched")

        # compare tables: every row must supply one value per column, or the
        # renderer prints a short row and the cell is silently blank.
        if lay == "compare":
            cols = c.get("columns") or []
            if len(cols) < 2:
                flag(spec, f, "compare with %d columns" % len(cols))
            for r in c.get("rows") or []:
                v = r.get("values") or []
                if len(v) != len(cols):
                    flag(spec, f, "row %r has %d values for %d columns"
                         % (r.get("label"), len(v), len(cols)))
                for i, cell in enumerate(v):
                    if not str(cell).strip():
                        flag(spec, f, "row %r cell %d is empty" % (r.get("label"), i))

        if lay == "checklist":
            for side in ("good", "bad"):
                s = c.get(side)
                if not s or not s.get("items"):
                    flag(spec, f, "checklist missing %s items" % side)
                elif not (s.get("heading") or "").strip():
                    flag(spec, f, "checklist %s side has no heading" % side)

        # every repeatable, whatever key it hides under
        for key in ("items", "steps", "nodes", "signs"):
            for it in c.get(key) or []:
                if isinstance(it, dict):
                    lbl = it.get("label") or it.get("text") or ""
                    if not str(lbl).strip():
                        flag(spec, f, "%s entry with empty label" % key)
                    if "note" in it and not str(it["note"]).strip():
                        flag(spec, f, "%s entry %r has an empty note" % (key, lbl))
                elif not str(it).strip():
                    flag(spec, f, "%s entry is an empty string" % key)

        # the literal strings that mean a value stringified somewhere
        blob = json.dumps(c)
        for needle in ("undefined", "null", "NaN", "[object Object]"):
            if re.search(r'"[^"]*%s[^"]*"' % re.escape(needle), blob):
                flag(spec, f, "spec text contains %r" % needle)

print("\n".join(problems) if problems else "no spec-level problems found")
print("\n%d problem(s)" % len(problems))
