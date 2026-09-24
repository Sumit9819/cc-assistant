# Which attachments this toolchain uploaded are referenced by nothing?
#
# Derived from evidence rather than from a hand-kept list: the hand-kept list
# (orphan_attachments.json) captured 7 of them and then stopped being updated,
# which is exactly the failure mode a computed answer avoids.
#
# Referenced = the filename appears in a live post body, OR in a queued patch.
# Post bodies are the pre-pending state, so both sources are needed.
import glob, json, os, re

uploaded = {}          # attachment_id -> filename
for path in glob.glob("uploaded-*.json"):
    for row in json.load(open(path, encoding="utf-8")):
        uploaded[int(row["id"])] = row["url"].rsplit("/", 1)[-1]

haystack = []
for path in glob.glob("bodies/*.html"):
    haystack.append(open(path, encoding="utf-8", newline="").read())
for path in glob.glob("patches-*.json"):
    haystack.append(open(path, encoding="utf-8").read())
blob = "\n".join(haystack)

orphans = sorted(aid for aid, name in uploaded.items() if name not in blob)
placed = sorted(aid for aid in uploaded if aid not in orphans)

print("uploaded by this toolchain: %d" % len(uploaded))
print("referenced (live body or queued patch): %d" % len(placed))
print("ORPHANED: %d" % len(orphans))
for aid in orphans:
    print("  %d  %s" % (aid, uploaded[aid]))

json.dump(orphans, open("orphans_computed.json", "w"), indent=1)
