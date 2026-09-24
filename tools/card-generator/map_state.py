# Which cards need a re-render, and of those, which are already LIVE (so the
# body needs a swap) versus never uploaded (so they ride the normal flow).
#
# "Live" is decided by the post body on disk, refetched from WordPress this
# session - not by the uploaded-*.json ledger, which only records that an
# upload happened, never that it reached a page.
import glob, json, os

REDO = set()  # cards whose text changed and whose bitmap is therefore stale
for f in ("title_fix_manifest.json",):
    for files in json.load(open(f, encoding="utf-8")).values():
        REDO |= set(files)
SPELLING = json.load(open("spelling_fix_manifest.json", encoding="utf-8"))
REDO |= set(SPELLING)

# every card, and the spec it lives in
card_spec, card_obj = {}, {}
for spec in sorted(glob.glob("spec-*.json")):
    for c in json.load(open(spec, encoding="utf-8"))["cards"]:
        card_spec[c["file"]] = spec
        card_obj[c["file"]] = c

# recorded uploads, newest ledger wins
prior = {}
for f in sorted(glob.glob("uploaded-*.json"), key=os.path.getmtime):
    for r in json.load(open(f, encoding="utf-8")):
        prior[r["url"].rsplit("/", 1)[-1].replace(".webp", "")] = r

ALIAS = {"laser-hair-removal-hair-color": "laser-hair-removal-hair-colour"}

bodies = {}
for p in glob.glob("bodies/*.html"):
    bodies[int(os.path.basename(p)[:-5])] = open(p, encoding="utf-8", newline="").read()

live, notlive, missing_spec = [], [], []
for stem in sorted(REDO):
    c = card_obj.get(stem)
    if c is None:
        missing_spec.append(stem)
        continue
    pid = c["post_id"]
    # the spelling pass renamed one card's file, so its live bitmap is still
    # filed under the British stem
    rec = prior.get(stem) or prior.get(ALIAS.get(stem, ""))
    body = bodies.get(pid, "")
    if rec and body.count(rec["url"]) == 1:
        live.append({"post_id": pid, "stem": stem, "spec": card_spec[stem],
                     "alt": c.get("alt", ""), "old_id": rec["id"],
                     "old_url": rec["url"]})
    else:
        why = "no upload recorded" if not rec else (
            "url appears %dx in body" % body.count(rec["url"]))
        notlive.append({"post_id": pid, "stem": stem, "spec": card_spec[stem],
                        "why": why})

print("=== LIVE, needs re-render + swap (%d) ===" % len(live))
for r in live:
    print("  %-6s %-46s %-16s old=%s" % (r["post_id"], r["stem"],
                                         r["spec"].replace("spec-", "").replace(".json", ""),
                                         r["old_id"]))
print("\n=== not live (%d) ===" % len(notlive))
for r in notlive:
    print("  %-6s %-46s %-16s %s" % (r["post_id"], r["stem"],
                                     r["spec"].replace("spec-", "").replace(".json", ""), r["why"]))
if missing_spec:
    print("\n=== renamed away / no spec (%d) ===" % len(missing_spec))
    for s in missing_spec:
        print("  " + s)

json.dump({"live": live, "not_live": notlive, "missing": missing_spec},
          open("work_state.json", "w", encoding="utf-8"), indent=1)
specs = sorted({r["spec"] for r in live} | {r["spec"] for r in notlive})
print("\nspecs to re-render: %s" % " ".join(specs))
