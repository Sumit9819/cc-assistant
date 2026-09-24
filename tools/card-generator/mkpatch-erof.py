"""
Emit draft_patch_post_content payloads that place the ER of Irving cards.

    python mkpatch-erof.py spec-erofirving-1.json uploaded-erof-1.json

Reads the post bodies cached in ../erof-bodies*.json (fetched through
tools/cc-via-browser.mjs) and writes patches-<spec>.json.

WHY THE ANCHOR IS COPIED, NEVER TYPED: draft_patch_post_content requires each
`search` to match the live body byte for byte and exactly once. A hand-typed
heading fails on a curly apostrophe, a nested <strong>, or a stray &amp; - and
post 3886's anchor is in fact `<h2><strong>...</strong></h2>`, which no one would
guess. So the anchor is sliced out of the fetched HTML and asserted unique here,
before anything is queued.

For the same reason the ANCHORS text below is a short ASCII-safe SUBSTRING of the
target heading, not the whole thing: 'When "Seeing Spots" is a Retinal Emergency'
and "Don't Wait, Protect Your Vision!" both carry typographic punctuation that
would not survive being retyped.

Each card is inserted DIRECTLY BELOW the heading of the section it
illustrates, ahead of that section's prose. Where a stock image already
sits under the heading, the card goes after that image rather than above
it. ANCHORS names the card's OWN section; entries from batches 1 to 9 name
the following heading instead and predate the change.
"""
import json
import pathlib
import re
import sys

from PIL import Image

HERE = pathlib.Path(__file__).parent
# Default: every Irving body snapshot. Pass --bodies <file> to card another
# site; ANCHORS is keyed by card filename, so sites cannot collide.
BODY_FILES = sorted(HERE.parent.glob("erof-bodies*.json"))
if "--bodies" in sys.argv:
    _i = sys.argv.index("--bodies")
    _b = pathlib.Path(sys.argv[_i + 1])
    BODY_FILES = [_b if _b.is_absolute() else HERE.parent / _b]
    del sys.argv[_i:_i + 2]

# Which heading each card sits above. Text only; the real markup is recovered
# from the body, tags and all.
# NOTE: entries below the "batch 10" marker name the card's OWN section, which
# is where the figure is now placed. Entries above it name the FOLLOWING heading,
# the pre-2026-09-08 convention. They are kept as a record of what was queued.
# The AFTER dict is gone: placement no longer depends on what a card
# restates. Every card goes directly below its section heading.

ANCHORS = {
    # batch 14 (2026-09-23): the three decision posts published that day.
    "erof-4772-flu-warning-signs": (4772, "Flu Emergency Warning Signs in Adults"),
    "erof-4772-flu-better-then-worse": (4772, "Better, Then Worse"),
    "erof-4773-180-120-threshold": (4773, "What Blood Pressure Is Dangerously High"),
    "erof-4773-call-911-symptoms": (4773, "High Reading With Symptoms: Call 911"),
    "erof-4774-vomiting-er-signs": (4774, "Vomiting: When to Go to the ER"),
    "erof-4774-dehydration-signs": (4774, "Signs of Dehydration in Adults"),
    # batch 13. Each anchor names the card's OWN section heading.
    # 3201's heading text also appears in an image alt directly beneath it;
    # the anchor is the whole <h2> tag, sliced from the body, so it stays unique.
    # 3882 and 4008 use ASCII-safe substrings (a colon-suffixed heading and a
    # curly apostrophe in "Don't").
    "erof-3879-skip-the-pharmacy": (3879, "Signs You Need a Doctor Right Now"),
    "erof-3882-burn-needs-er": (3882, "When to Seek Professional Wound Care"),
    "erof-4008-dehydration-adults-kids": (4008, "Spotting the Signs Early"),
    "erof-3867-co-alarm-checklist": (3867, "Why CO Detectors Save Lives"),
    "erof-3850-workplace-first-steps": (3850, "First Steps: What to Do the Moment an Injury Occurs"),
    "erof-3918-gout-at-night": (3918, "Why Gout Usually Hits at Night"),
    "erof-3201-dehydration-untreated": (3201, "What Happens If You Are Dehydrated"),
    # batch 12. The heading text carries a straight double quote, so the
    # anchor is the ASCII-safe leading substring, verified unique in the body.
    "erof-3937-urushiol-85": (3937, "Understanding the"),
    # batch 11. ANCHORS names each card's OWN section: the figure goes directly
    # below that heading.
    "erof-3116-traction-sites": (3116, "What is Skeletal Traction"),
    "erof-3116-traction-types": (3116, "Types of Skeletal Traction"),
    "erof-3116-traction-procedure": (3116, "Skeletal Traction Procedure"),
    "erof-3116-bone-stimulator": (3116, "How Bone Stimulators Support Skeletal Traction"),
    "erof-3921-is-103-an-emergency": (3921, "Understanding the 103"),
    "erof-3854-dka-red-flags": (3854, "Recognizing the Red Flags: DKA Symptoms"),
    "erof-3854-dka-er-treatment": (3854, "How the ER Stabilizes Blood Sugar"),
    "erof-3833-headache-er-red-flags": (3833, "When to Visit the ER for a Severe Headache"),
    "erof-3937-rash-urgent-care": (3937, "Signs Your Rash Needs Urgent Care"),
    "erof-3934-dvt-symptoms": (3934, "Recognizing DVT Symptoms"),

    # batch 10 - first batch under the two-axis rule. Each card is inserted
    # before the heading that FOLLOWS the section it illustrates, so it closes
    # that section. 3365 and 3162 had no cards at all before this.
    "erof-3365-stress-fracture-sites": (3365, "What Is a Stress Fracture"),
    "erof-3365-stress-fracture-signs": (3365, "How Do You Know If You Have a Stress Fracture"),
    "erof-3365-stress-fracture-healing": (3365, "How Long Does a Stress Fracture Take to Heal"),
    "erof-3365-stress-fracture-er": (3365, "When Should You Seek Emergency Care for a Stress Fracture"),
    "erof-3162-muscle-strain-grades": (3162, "What is muscle strain"),
    "erof-3162-muscle-strain-recovery": (3162, "7 Expert Tips to Speed Up Muscle Strain Recovery"),
    "erof-3858-kidney-stone-home-care": (3858, "Home Remedies for Kidney Stone Pain"),

    "erof-2780-er-or-urgent-care-symptoms": (2780, "What if it is late at night or on a weekend?"),
    "erof-2780-call-911-instead-of-driving": (2780, "Frequently asked questions"),
    "erof-3796-heat-illness-first-aid": (3796, "Who is Most at Risk in the Texas Heat?"),
    "erof-3886-stroke-mistakes": (3886, "Stroke Risk Factors and Prevention for DFW Adults"),
    # batch 2
    "erof-3803-button-battery-what-not-to-do": (3803, "What to Expect at the Emergency Room"),
    "erof-3978-chest-pain-red-flags": (3978, "How ER of Irving Tells Them Apart"),
    "erof-3882-burn-first-aid": (3882, "When to Seek Professional Wound Care"),
    "erof-3921-child-fever-thresholds": (3921, "Dehydration Signs"),
    "erof-3930-stitches-or-glue": (3930, "How long can you wait to get stitches"),
    "erof-3808-rsv-flu-emergency-signs": (3808, "At-Home Care and Medical Treatments"),
    # batch 3: two more per post, each on a different section
    "erof-3803-battery-red-flags": (3803, "The 2-Hour Window"),
    "erof-3803-battery-two-hour-window": (3803, "Step-by-Step Emergency Actions"),
    "erof-3978-chest-pain-er-workup": (3978, "FAQ"),
    "erof-3978-chest-pain-time-to-answer": (3978, "Red Flags That Need Emergency Care"),
    "erof-3882-burn-degrees": (3882, "Household Burn Hazards"),
    "erof-3882-burn-hazards": (3882, "Immediate First Aid"),
    "erof-3921-dehydration-signs": (3921, "When a Fever Causes Seizures"),
    "erof-3921-febrile-seizure-steps": (3921, "At-Home Comfort Measures"),
    "erof-3930-cut-needs-care": (3930, "Stitches vs. Medical Glue"),
    "erof-3930-stitches-time-window": (3930, "Caring for the wound"),
    "erof-3808-rsv-infant-signs": (3808, "The Flu in Children"),
    "erof-3808-flu-child-signs": (3808, "A Quick Comparison Guide"),
    # batch 4: count set by cardneed.py plus a read of each candidate section
    "erof-3886-fast-signs": (3886, "Atypical Stroke Symptoms You Should Not Miss"),
    "erof-3886-first-five-minutes": (3886, "4 Mistakes That Cost Critical Minutes"),
    "erof-3886-er-stroke-clock": (3886, "FAQs"),
    "erof-3816-eye-red-flags": (3816, "Chemical Splashes in the Eye"),
    "erof-3816-scratched-cornea": (3816, "is a Retinal Emergency"),
    "erof-3816-retinal-spots": (3816, "Sudden Vision Loss: Understanding the Risks"),
    "erof-3816-eye-what-not-to-do": (3816, "Protect Your Vision"),
    "erof-3930-what-to-expect": (3930, "Need Immediate Care for a Deep Cut"),
    "erof-3921-fever-home-care": (3921, "What Pediatric-Friendly Emergency Care Looks Like"),
    # batch 5
    "erof-3081-xray-what-to-expect": (3081, "When to Choose the Emergency Room"),
    "erof-3081-when-er-xray": (3081, "Fast X-ray services"),
    "erof-3977-hairline-signs": (3977, "Common Causes of Hairline Fractures"),
    "erof-3977-xray-looks-normal": (3977, "When to Visit the ER vs Wait"),
    "erof-3977-er-now-or-wait": (3977, "How ER of Irving Diagnoses"),
    "erof-3091-when-iv-needed": (3091, "How to Tell If You Need an IV"),
    "erof-3091-iv-how-long": (3091, "How Many Bags"),
    "erof-3097-slipping-rib-signs": (3097, "Which Ribs Are Affected"),
    "erof-3097-slipping-rib-when-seen": (3097, "Frequently Asked Questions"),
    # batch 6
    "erof-3103-fracture-or-bruise-diagnosis": (3103, "Treatment Options and Recovery"),
    "erof-3103-bone-bruise-care": (3103, "When to Seek Medical Attention"),
    "erof-3103-bone-injury-er": (3103, "Frequently Asked Questions"),
    "erof-3201-dehydration-signs": (3201, "1. Excessive Thirst"),
    "erof-3201-dehydration-evaluation": (3201, "Prevention Tips: How to Stay Hydrated"),
    "erof-3825-dog-bite-infection": (3825, "Critical Vaccinations"),
    "erof-3825-dog-bite-first-aid": (3825, "When to Seek Emergency Care for a Dog Bite"),
    "erof-3825-dog-bite-er": (3825, "Seek Professional Care for Dog Bites"),
    "erof-3833-aneurysm-red-flags": (3833, "Headache: Why Timing Matters"),
    "erof-3833-headache-treatment-paths": (3833, "Understanding Risk Factors for Brain Aneurysms"),
    "erof-3867-co-symptoms": (3867, "Common Sources of Carbon Monoxide"),
    "erof-3867-co-emergency-steps": (3867, "Prevention Checklist"),
    # batch 7
    "erof-3875-when-to-go-er": (3875, "What to Expect During Your Visit"),
    "erof-3800-child-dehydration-stages": (3800, "Common Causes of Fluid Loss in Children"),
    "erof-3800-child-vomiting-er": (3800, "Prevention: Keeping Your Child Hydrated"),
    "erof-3813-appendicitis-pain-shift": (3813, "Testing for Appendicitis at Home"),
    "erof-3813-appendicitis-er": (3813, "Recognize the Sym"),
    "erof-3829-concussion-symptoms": (3829, "The Critical Window"),
    "erof-3829-concussion-er": (3829, "The Danger of Rushing Back"),
    "erof-3858-kidney-stone-size": (3858, "Red Flags: When to Stop Home Care"),
    "erof-3858-kidney-stone-er": (3858, "Frequently Asked Questions"),
    "erof-3862-sepsis-signs": (3862, "Who is Most at Risk for Sepsis"),
    "erof-3862-sepsis-what-to-do": (3862, "Frequently Asked Questions"),
    # batch 8. Two cards each cover a MERGED pair of sections, so they anchor
    # after the second of the pair: 3937 (urgent care + ER tiers) and 3979 (the
    # eight emergencies), the latter placed above the first one so it reads as
    # the overview it is.
    "erof-3879-pharmacy-or-er": (3879, "Signs You Need a Doctor Right Now"),
    "erof-3897-anaphylaxis-er-care": (3897, "Monitoring for the"),
    "erof-3897-anaphylaxis-red-flags": (3897, "Immediate Recovery: The First 24 Hours"),
    "erof-3900-whiplash-symptoms": (3900, "Essential Diagnostic Imaging"),
    "erof-3900-crash-er-red-flags": (3900, "Adrenaline Mask"),
    "erof-3937-rash-urgent-or-er": (3937, "Prescription-Strength Relief"),
    "erof-3979-eight-emergencies": (3979, "1. Stroke Symptoms"),
    "erof-4008-heat-exhaustion-or-stroke": (4008, "Ruin the Match"),
    "erof-4345-food-poisoning-er": (4345, "Who Needs to Be More Careful"),
    "erof-4345-food-poisoning-recovery": (4345, "How ER of Irving Treats Food Poisoning"),
    "erof-4410-crash-symptoms": (4410, "Delayed Symptoms: What to Watch"),
    "erof-4410-911-er-or-home": (4410, "Get Checked Today, Not Next Week"),
    # batch 9, the tail
    "erof-3792-snake-bite-first-aid": (3792, "When to Seek Emergency Care"),
    "erof-3854-dka-er-triggers": (3854, "How the ER Stabilizes Blood Sugar"),
    "erof-3871-near-drowning-signs": (3871, "Water Safety for Irving Pools"),
    "erof-3894-heart-attack-signs": (3894, "Second-Guess Your Heart Health"),
    "erof-3918-gout-relief": (3918, "Distinguishing Gout from Infection"),
    "erof-3918-gout-or-infection": (3918, "When to Seek Professional Help"),
    "erof-3924-abscess-red-flags": (3924, "Is it just a Sore Throat"),
    "erof-3924-abscess-treatment": (3924, "Prevention and Long-Term Recovery"),
    "erof-3934-pe-warning-signs": (3934, "Who is Most at Risk for DVT"),
    "erof-3934-dvt-diagnosis": (3934, "Immediate Steps to Take if You Suspect"),
    "erof-4011-hantavirus-symptoms": (4011, "2026 Health Update"),
    # ER of Lufkin, batch 1
    "luf-5762-delayed-symptoms": (5762, "Step 4: Document Everything"),
    "luf-5762-follow-up-care": (5762, "Step 6: When to Go to the ER"),
    "luf-5762-crash-er-now": (5762, "Step 7: Caring for Yourself"),
    "luf-5886-back-pain-red-flags": (5886, "Other Symptoms That Need Prompt"),
    "luf-5886-back-pain-prompt": (5886, "How Doctors Diagnose Emergency Back Pain"),
    "luf-5886-back-pain-diagnosis": (5886, "When to Go to the ER vs."),
    "luf-5892-when-to-seek-care": (5892, "Home Care"),
    "luf-5892-home-care": (5892, "How to Prevent Flu"),
}


def frame(file):
    """The card's REAL pixel size, read off the encoded WebP.

    The width and height attributes are not decoration: without them the
    browser cannot reserve the box before the image loads and the article
    reflows under the reader (CLS). Card height is no longer fixed - the frame
    grows to fit its content, 630px floor - so hardcoding 628 here would have
    declared the wrong box for every tall card and caused the exact shift the
    attributes exist to prevent.
    """
    f = HERE / "out" / f"{file}.webp"
    if not f.exists():
        sys.exit(f"{file}: no out/{file}.webp - run towebp.py before patching")
    with Image.open(f) as im:
        return im.width, im.height


def heading_tag(html, text):
    """The whole <h2>...</h2> containing `text`, verbatim."""
    hits = [m for m in re.finditer(r"<h2\b[^>]*>.*?</h2>", html, re.S)
            if text in m.group(0)]
    if len(hits) != 1:
        raise SystemExit(f'anchor "{text}": found {len(hits)} h2 matches, need exactly 1')
    return hits[0].group(0)


# An image in the body, whether it is one of ours in a <figure> or a bare
# <img> the editor placed. Every adjacency check must use THIS, not a figure
# test: batch 10 stacked four cards on stock photos because all three guards
# only recognised the markup this script generates.
IMG_BLOCK = re.compile(r"<figure\b.*?</figure>|<img\b[^>]*/?>", re.S)

# How much visible text counts as a prose block worth sitting under. A stray
# caption or a one-line lead-in is not enough separation between two images.
MIN_PROSE = 80


def visible(html):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html)).strip()


def insert_anchor(html, section_text, after=None):
    """The exact string a figure is appended to: the section's heading.

    THE RULE, as the operator stated it after three of my wrong guesses: every
    card goes directly BELOW the heading of the section it illustrates. Never
    above a heading, and not after the section's paragraph either.

    Where an image already sits directly under that heading, the card goes
    after it. The objection on post 3162 was that our card had been put ABOVE
    the existing one, so going after it keeps the card below the heading
    without touching anything of theirs. Those sections end up with two images
    together, which is flagged for a separate editorial decision about the
    stock photo rather than fixed by deleting it here.

    `after` is accepted and ignored, so old call sites keep working.
    """
    tag = heading_tag(html, section_text)
    pos = html.index(tag) + len(tag)

    # Swallow any image already sitting under the heading, plus the whitespace
    # in front of it, byte for byte, so the anchor still matches exactly once.
    anchor = tag
    while True:
        m = re.match(r"\s*(?:<figure\b.*?</figure>|<img\b[^>]*/?>)",
                     html[pos:], re.S)
        if not m:
            break
        anchor += m.group(0)
        pos += m.end()
    return anchor


def main():
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    spec = json.loads((HERE / sys.argv[1]).read_text(encoding="utf-8"))
    uploads = {u["file"]: u for u in
               json.loads((HERE / sys.argv[2]).read_text(encoding="utf-8"))}
    bodies = {}
    for bf in BODY_FILES:
        for rec in json.loads(bf.read_text(encoding="utf-8")):
            # The patch target is post_content, so prefer content.raw. rendered
            # differs only by wpautop, which leaves an <h2> untouched, which is
            # why the earlier batches matched anyway - but raw is the actual
            # string being edited, so match against that.
            bodies[rec["id"]] = (rec["content"].get("raw")
                                 or rec["content"]["rendered"])
    if not bodies:
        sys.exit("No erof-bodies*.json found - fetch the post bodies first")

    by_post = {}
    for card in spec["cards"]:
        f = card["file"]
        if f not in ANCHORS:
            sys.exit(f'{f}: no anchor defined in ANCHORS')
        if f not in uploads:
            sys.exit(f'{f}: not in the upload manifest - upload first')
        pid, text = ANCHORS[f]
        if pid != card["post_id"]:
            sys.exit(f'{f}: anchor post {pid} does not match spec post {card["post_id"]}')

        rec = uploads[f]
        anchor = insert_anchor(bodies[pid], text)

        # Refuse to stack, against ANY image. This used to test only for
        # "<figure", so the stock photos in these posts, which are bare <img>
        # tags, were invisible to it and four cards shipped stacked.
        after = bodies[pid][bodies[pid].index(anchor) + len(anchor):].lstrip()
        if IMG_BLOCK.match(after):
            sys.exit(f'{f}: section "{text}" already has an image right after '
                     f'that point: {after[:90]}')
        # class="cc-card" is not optional. Without it the figure keeps the
        # browser default margin-inline:40px, which this theme never resets, so
        # the card renders 40px narrower than the content column on desktop and
        # nowhere near the screen edges on mobile. The kit CSS keys off it.
        w, h = frame(f)
        fig = ('<figure class="cc-card"><img src="%s" alt="%s" width="%d" '
               'height="%d" loading="lazy" decoding="async" /></figure>'
               % (rec["url"], card["alt"], w, h))
        by_post.setdefault(pid, []).append({
            "search": anchor,
            "replace": anchor + "\n" + fig,
        })

    out = []
    for pid, patches in by_post.items():
        out.append({
            "post_id": pid,
            "patches": patches,
            "summary": f"Add {len(patches)} in-body triage card(s) to post {pid}",
            "reasoning": ("Cards restate this post's own decision content as a graphic. "
                          "Every item is lifted from the body, and each card sits above the "
                          "heading that follows the section it illustrates. The number of "
                          "cards is set by how many distinct decisions the post actually "
                          "contains rather than by a per-post quota, and each card sits after "
                          "its section's opening paragraph so it previews that section rather "
                          "than sitting against the next heading. "
                          "Any section already answered by a table in the body was skipped."),
        })
    dest = HERE / f"patches-{pathlib.Path(sys.argv[1]).stem}.json"
    dest.write_text(json.dumps(out, indent=2) + "\n", encoding="utf-8")

    for call in out:
        print(f'post {call["post_id"]}: {len(call["patches"])} patch(es)')
        for p in call["patches"]:
            print(f'    before {p["search"][:64]!r}')
    print(f"-> {dest.name}")
    print("\nSIMULATE against the real body before queueing (the 2026-09-04 "
          "nested-figure incident), then call draft_patch_post_content per post.")


if __name__ == "__main__":
    main()
