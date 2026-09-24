"""Apply a patch set to the fetched bodies IN MEMORY and check the result.

    python simpatch.py patches-spec-erofirving-10.json --bodies erof-bodies-b10.json

Queueing a patch is cheap to do and expensive to undo: rejecting a pending that
has already been approved does NOT revert it (see
memory/feedback_reject_does_not_undo_an_applied_pending.md), and a nested
figure shipped on 2026-09-04 because the patch looked right in isolation. So
every patch is applied to a copy of the live body first and the OUTCOME is
checked, not the intent.

Checks, all of which have caught a real defect at least once:

  unique      each `search` string occurs exactly once, which is what
              draft_patch_post_content itself requires
  growth      the body gains exactly one cc-card figure per patch
  nesting     no <figure> ends up inside another <figure>
  stacking    no two figures end up adjacent with no prose between them
  dimensions  the width/height on each new img match the actual WebP, or the
              browser reserves the wrong box and the article shifts as it loads
  alt         every new img carries a non-empty alt
"""
import json
import pathlib
import re
import sys

from PIL import Image

HERE = pathlib.Path(__file__).parent
FIG = re.compile(r'<figure class="cc-card">.*?</figure>', re.S)


def main():
    patches = json.loads((HERE / sys.argv[1]).read_text(encoding="utf-8"))
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    bodies = {r["id"]: (r["content"].get("raw") or r["content"]["rendered"])
              for r in json.loads(bp.read_text(encoding="utf-8"))}

    bad = 0
    for call in patches:
        pid = call["post_id"]
        body = bodies[pid]
        before_n = len(FIG.findall(body))
        print(f'--- post {pid}: {len(call["patches"])} patch(es), '
              f'{before_n} card(s) already live')

        for p in call["patches"]:
            hits = body.count(p["search"])
            if hits != 1:
                print(f'    FAIL unique: anchor occurs {hits} times, need 1: '
                      f'{p["search"][:60]!r}')
                bad += 1
                continue
            body = body.replace(p["search"], p["replace"], 1)

        after = FIG.findall(body)
        # Expected change is DERIVED FROM THE PATCHES, not assumed from the
        # kind of batch. An insert set adds one per patch, a move set adds and
        # removes in pairs, and a set that deliberately drops a card removes
        # one on its own. Assuming "a move is net zero" failed a patch set whose
        # intent was to drop post 3162's recovery card, which was correct.
        delta = sum(len(FIG.findall(p["replace"])) - len(FIG.findall(p["search"]))
                    for p in call["patches"])
        want = before_n + delta
        if len(after) != want:
            print(f'    FAIL growth: {len(after)} figures, expected {want}')
            bad += 1
        else:
            print(f"    ok growth: {before_n} -> {len(after)} figures (expected {delta:+d})")

        # THE RULE, as the operator stated it directly: every card sits DIRECTLY
        # BELOW its section heading. Not after the section's paragraph, and
        # never above a heading.
        #
        # This check has now been wrong three times because I kept deriving the
        # rule instead of asking. It said "never above any heading", then "never
        # above an h2", then "must follow the content it restates" - and the
        # last one actively failed the correct placement, because a card
        # directly below its heading is by definition NOT after its content.
        # It is written from the operator's own words now, not from a pattern I
        # spotted in the data.
        #
        # An image already under the heading is allowed between the two: the
        # card goes after it rather than above it.
        def under_heading(html):
            good = bad_ = 0
            for m in FIG.finditer(html):
                head = html[:m.start()]
                cut = max(head.rfind("</h2>"), head.rfind("</h3>"))
                if cut == -1:
                    bad_ += 1
                    continue
                between = head[cut + 5:]
                # Images between the heading and the card do not count as text.
                between = re.sub(r"<figure\b.*?</figure>|<img\b[^>]*/?>", " ",
                                 between, flags=re.S)
                if len(re.sub(r"<[^>]+>", " ", between).strip()) < 40:
                    good += 1
                else:
                    bad_ += 1
            return good, bad_

        wg, wb = under_heading(bodies[pid])
        ng, nb_ = under_heading(body)
        if nb_ > wb:
            print(f'    FAIL placement: {nb_ - wb} card(s) would NOT sit '
                  f'directly below their heading')
            bad += 1
        else:
            fixed = f', {wb - nb_} fixed' if nb_ < wb else ''
            note = f' ({nb_} still misplaced)' if nb_ else ''
            print(f'    ok placement: {ng} card(s) directly below a heading'
                  f'{fixed}{note}')

        if re.search(r"<figure[^>]*>(?:(?!</figure>).)*<figure", body, re.S):
            print("    FAIL nesting: a figure ended up inside another figure")
            bad += 1

        # Adjacency against ANY image, in BOTH directions, and counted as a
        # delta. This check used to be `</figure>\s*<figure`, which could only
        # ever see the markup this pipeline generates. The stock photos in
        # these posts are bare <img> tags, so four batch-10 cards shipped
        # stacked directly on one and every guard reported clean.
        # One exception, and it comes straight from the placement rule rather
        # than from convenience: where a stock photo already sits directly
        # under a heading, "card directly below the heading" necessarily puts
        # the card right after that photo. Those pairs are ALLOWED and counted,
        # because the alternative is either putting our card above theirs,
        # which the operator rejected, or deleting their photo, which is not a
        # placement decision. Any other new pair still fails.
        pair = re.compile(
            r"(?:</figure>|<img\b[^>]*/?>)\s*(?:<figure\b|<img\b)", re.S)
        allowed = re.compile(
            r"</h[1-6]>\s*<img\b[^>]*/?>\s*<figure class=\"cc-card\">", re.S)
        was = len(pair.findall(bodies[pid]))
        now = len(pair.findall(body))
        under_head = len(allowed.findall(body)) - len(allowed.findall(bodies[pid]))
        net = (now - was) - under_head
        if net > 0:
            print(f'    FAIL stacking: {net} new adjacent image pair(s) with no '
                  f'text between them and no heading above them')
            bad += 1
        else:
            fixed = f', {was - now} fixed' if now < was else ''
            note = (f', {under_head} card(s) placed under an existing photo '
                    f'per the placement rule' if under_head else '')
            print(f'    ok stacking: no unexplained new pairs{fixed}{note}')

        # Only the figures THIS patch set introduces. An earlier batch's card
        # is already live at whatever size it was encoded at, and its local
        # out/*.webp may since have been re-rendered at a different height, so
        # comparing those two reports a mismatch that does not exist on the
        # site: batch 7's kidney-stone cards are live at 628 and correctly
        # declare 628, while the local files are now 630.
        # Only figures this patch set AUTHORS. A figure that also appears in
        # some patch's `search` is being RELOCATED verbatim: its width/height
        # already match the live file, and comparing it against a local
        # out/*.webp is meaningless because those were re-rendered at the new
        # 630px floor today while the live attachments are still 628. Checking
        # them reported 14 dimension failures and 7 missing files on a patch
        # set that changes no image at all.
        relocated = {f for p in call["patches"] for f in FIG.findall(p["search"])}
        for fig in [f for p in call["patches"] for f in FIG.findall(p["replace"])
                    if f not in relocated]:
            src = re.search(r'src="([^"]+)"', fig)
            w = re.search(r'width="(\d+)"', fig)
            h = re.search(r'height="(\d+)"', fig)
            alt = re.search(r'alt="([^"]*)"', fig)
            name = src.group(1).rsplit("/", 1)[-1] if src else "?"
            if not alt or not alt.group(1).strip():
                print(f'    FAIL alt: {name} has no alt text')
                bad += 1
            local = HERE / "out" / name
            if not local.exists():
                print(f'    FAIL missing: out/{name} not found, cannot verify size')
                bad += 1
                continue
            with Image.open(local) as im:
                real = (im.width, im.height)
            declared = (int(w.group(1)) if w else 0, int(h.group(1)) if h else 0)
            if real != declared:
                print(f'    FAIL dimensions: {name} declares {declared[0]}x'
                      f'{declared[1]}, file is {real[0]}x{real[1]}')
                bad += 1
            else:
                print(f'    ok dimensions: {name} {real[0]}x{real[1]}')

    print()
    print("SIMULATION FAILED" if bad else "All checks passed. Safe to queue.")
    sys.exit(1 if bad else 0)


if __name__ == "__main__":
    main()
