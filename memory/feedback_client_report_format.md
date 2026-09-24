---
name: feedback_client_report_format
description: The client-facing report formats - monthly report structure and the ad-hoc work update - plus the hard rules on asks and framing
metadata:
  type: feedback
---

Two client-facing report shapes, both GROWTHBOSS house style, both rendered to PDF on
`D:\`. Author as HTML in the scratchpad, print with headless Chrome
(`chrome --headless --print-to-pdf=...`), then **screenshot and actually look at it**
before delivering ([[feedback_verify_rendered_visuals_after_build]]).

## Monthly report (agreed 2026-09-01, mammothmachinery)

Six sections in this order. Busy stakeholders read only the first.

1. **Executive summary** - exactly 3 bullets: major win, second win, bottom-line impact.
2. **Enquiries** - form submissions, calls, revenue. The ROI section.
3. **Traffic quality** - lead with **non-branded** growth; that is what proves the work
   pulls new prospects rather than riding existing brand awareness.
4. **Priority keyword movement** - a focused 10-20 "money" keywords, before/after
   position. Never dump hundreds of rankings.
5. **Work completed** - deliverables finished in the billing cycle.
6. **Next month's priorities** - 2 or 3 strategic focus areas.

## HARD RULES

- **No "what we need from you" section, and no asks anywhere in the report.** The
  operator sends outstanding client items separately, in their own words. A report
  full of requests reads as chasing and the client reacts badly to it.
- **Never mention which calendar slot a post belonged to, and never mention
  publishing pace at all.** Saying posts were "scheduled for September" invites the
  client to feel short-changed. Equally banned, and worse: "publishing runs three
  posts ahead of the calendar", "already live", "published early". The operator was
  alarmed by exactly that line on 2026-09-07 ("my client will kill me if they get to
  know that I already published the content"). Production pace is an internal matter
  and disclosing it puts the operator at risk with their own client. Report each post
  by title and actual publish date, full stop. Publishing early is not something to
  reframe or defend in a report; it is something to leave out.
- **State declines plainly and explain them.** A falling CTR gets its own honest
  paragraph, not omission. Hiding it is what loses the account later.
- Plain language, no internal vocabulary: no pending-change ids, widget ids, tool or
  plugin names, and never any mention of API access ([[feedback_reports_are_work_records_only]]).
- Re-pull every figure at report time ([[feedback_reverify_metrics_at_report_time]]);
  precise numbers only ([[feedback_precise_figures_only]]).
- Compare **equal-length windows** (Aug 1-29 vs Jul 1-29), never a partial month
  against a full one, and say so in the footnote.

## Metric traps that produce wrong client numbers

- **Impression-weight average position.** Never average the per-page averages.
- **Combine www and non-www.** GSC lists them separately even when one 301s to the
  other. On mammoth the home page reads 357 clicks non-www alone but 412 combined.
- **Query rows do not sum to site totals.** GSC withholds rare queries, so brand /
  non-brand splits are a *share of named queries*, not absolutes. Say so, and compare
  both months on the same basis rather than quoting an absolute non-brand click count.

## Ad-hoc work update

Same header, doc id `<CLIENT>-<YYYY-MM>-<code>`. Per-URL before/after in two-column
boxes, grouped where many pages share the identical change. Used for the claim
corrections doc MM-2026-08-C1.

Related: [[feedback_handoff_docs_before_after_only]], [[feedback_client_report_format]],
[[reference_gsc_api_toolchain]], [[feedback_never_diagnose_from_average_position]].

## Quarterly report — canonical design reference (2026-09-01)

**`D:\Mammoth-Quarterly-Report-Jun-Aug-2026.pdf` is the house template.** Match it,
do not invent a layout. Render it to images with pymupdf and look at it first.

Structure, 3 pages: masthead (**GROWTH** dark + **BOSS** orange `#E8912D`, mono doc id
top right, 2.2pt dark rule) / client line `**Name** · Jun, Jul and Aug 2026 against Mar,
Apr and May 2026 · domain` / 3-bullet summary in a rounded box with a thick coloured
left border / `01` The two quarters, month by month (4 KPI cards, first outlined
heavier, then a month table with a small mono `prev quarter` / `this quarter` prefix
before each month name) / `02` Priority search movement (15 commercial terms,
before | after | movement | clicks, movement coloured green "up 7.8" / red "down 14.7"
/ grey "held" / green "newly visible") / `03` Work completed this quarter (bullets,
bold lead-in) / `04` Priorities for the next quarter (3 numbered blocks, orange left
border) / method note in 7.6pt grey + mono strip `GROWTHBOSS · QUARTERLY REPORT` and
`<ID> · CONFIDENTIAL`.

Fonts Lato + IBM Plex Mono. Green `#16A34A`, red `#DC2626`, orange `#E8912D`.

**Comparison is calendar quarters** — Mar/Apr/May against Jun/Jul/Aug. Do NOT add a
year-on-year section; the operator asked for it and rejected it. August runs to the
29th because Search Console lags ~3 days: mark it `*` and footnote it.

**On a down quarter, use the orange accent for the summary box left border, not
Mammoth's green** — green chrome over a decline reads as a false positive. Highlight
the lead KPI with a dark outline rather than green for the same reason.

**Check, do not copy, the caveats.** Mammoth greys out March/April impressions because
Google changed impression counting between April and May 2026. Sids-ponds shows no such
break (Apr 62,160 → May 73,716, a smooth curve), so that caveat was correctly omitted.

## Fleet two-section report (agreed 2026-09-07, the 3 ERs + IWC)

When the operator asks for "what we did last month and what we're doing this month,
just two things and nothing else": one page per site, four sites, same masthead and
mono footer strip as the monthly. Per site: `NN  Site name` + domain right-aligned,
then `COMPLETED IN <MONTH>` as bullets with a bold lead-in sentence, then
`PRIORITIES FOR <MONTH>` as orange-left-border numbered blocks. **No metrics
section** - figures appear only inside a bullet where they justify the point.
`page-break-before:always` on every site block after the first; at 9.3pt/1.41 with
12mm margins a site with 9 completed items plus 4 priorities fits one A4 page, and
anything looser spills two items onto a blank page. Built at
`D:\ER-Fleet-Monthly-Report-September-2026.pdf`, doc id shape `GB-<YYYY-MM>-ERW`.

Reporting judgement that survived review: where a site's own record contradicted
itself, the report states the measured thing and not the inference - Irving's
decline is written as "establish whether it is seasonal or structural", because
nine months of data cannot separate them, rather than asserting either.

## Weekly work-completed sheet (2026-09-07, mammoth + sids-ponds)

When the operator says "a report for last week, only what we did, no other
elements": ONE page, ONE section headed `Work completed`, doc id
`<CLIENT>-<YYYY>-W<isoweek>`. Client line carries the week in full words —
`Monday 31 August to Sunday 6 September 2026`. Each item is a bold sentence-case
title plus one plain-language paragraph inside a 2.8pt left-border block (client
accent colour; orange `#E8912D` on sids-ponds, green on Mammoth). Reports issued
that week are items too, named by reference and delivery date. Footer method line
`Items are dated from the website's own change record.`

**No KPI cards, no week-over-week table, no programme history, no priorities, no
next steps.** Canonical pair: `D:\Mammoth-Work-Completed-2026-W36.pdf` and
`D:\Sids-Ponds-Work-Completed-2026-W36.pdf`.

**Check the week has content before writing it.** On sids-ponds the Mon-Sun W36
window held exactly one item, because nothing had been applied to the site since
29 August; the doc was built on the literal last seven days (1-7 Sep) instead and
still only reached three items. Pull the applied-edit log FIRST and tell the
operator what the window actually contains — a three-page weekly analysis was
written and thrown away because the week did not support it.

A queued-but-unapplied change is not "completed". Report the finding as the work
("hours found wrong on all seven days ... corrections have been prepared"), never
as if it were live, and never with an approval ask
([[feedback_reports_are_work_records_only]]).

## 2026-09-09: I built a report WITHOUT reading this file first, and it was rejected

Asked for "a simple report" on sids-ponds, I published a web artifact instead of a PDF,
paraphrased the before/after instead of quoting it, and included no links to the pages
that changed. The operator's reply: "This is very bad report." All three faults are
already answered above and in [[feedback_handoff_docs_before_after_only]].

**Read this file BEFORE authoring any client-facing deliverable, not after.** The
formats, the render path and the hard rules are all here. Rebuilt correctly as
`D:\Sids-Ponds-Work-Update-SP-2026-09-C1.pdf` (6pp, doc id `SP-2026-09-C1`).

Three specifics the rebuild pinned down, worth keeping:

- **Before/after means the actual wording, quoted.** Old title and new title, old
  meta description and new one, the old section headings against the new ones, and the
  word count. Not a summary of the change. Two-column boxes, `page-break-inside: avoid`,
  and at 9.3pt with 12mm margins exactly two boxes fit an A4 page.
- **Every changed page carries its full URL as visible clickable text**, so the reader
  can open it and check. The operator's words: "the other side people need to
  understand what we actually meant".
- **Monospace is for figures only.** Setting a whole sentence in IBM Plex Mono made
  prose read as code; put `.wc` on the numeral, not the paragraph.

**And check the direction of every claim before shipping it.** My first draft implied
the Soil and Aggregates rewrites ADDED the product links. Those pages already had them,
my rewrite dropped them, and a follow-up restored them, so the honest line is "the same
three, kept in place". A report that credits itself with repairing its own regression is
worse than one with a layout fault. Related:
[[feedback_reports_are_work_records_only]], [[feedback_reverify_metrics_at_report_time]].

## Reports live in a PER-SITE FOLDER, and split by subject (operator, 2026-09-10)

**`D:\Client-Reports\<Site-Name>\`** is the home for every client report. Not `D:\`
root, which is where they used to land and where they get lost. Create the site folder
if it does not exist. Superseded versions go in a `_superseded` subfolder rather than
being deleted, so an issued document can always be produced again.

```
D:\Client-Reports\
  Sids-Ponds\
    Sids-Ponds-Changes-Applied-2026-09-10.pdf          SP-2026-09-C1
    Sids-Ponds-Conflicting-Statements-2026-09-10.pdf   SP-2026-09-F1
    Sids-Ponds-Out-of-Stock-2026-09-10.pdf             SP-2026-09-S1
    _superseded\
```

**One subject per document, not one big report.** The operator's boss asked for the
9 September work to be split three ways: what changed, what contradicts itself, and
what is out of stock. Doc-id suffixes now carry the subject: `C` changes, `F` findings,
`S` stock. Filename is `<Site>-<Subject>-<YYYY-MM-DD>.pdf`; the doc id stays inside.

**A findings report needs SCREENSHOTS.** Words alone did not satisfy the ask. Capture
them with Playwright at `~/.cc-assistant/wcag`, `deviceScaleFactor: 2`, cropped tight to
the statement with ~14px padding, each under a mono caption saying what it proves
("1 of 3 · the bar at the top of every page · a $100 minimum"). Two traps: **remove the
Complianz cookie banner and the Brave popup first** or they cover the evidence, and
**clipping to an element's own box cuts overflowing text** - for anything wide, clip
full viewport width and set the height yourself. A single frame showing WooCommerce's
"Showing the single result" above a patio box, on a page titled "Bike Sheds", carried
the whole argument better than the paragraph did.

**Build mechanics.** Write `house.css` once in the scratchpad and `<link>` it from each
report, instead of pasting the same style block into three files. Chrome resolves both
the stylesheet and `shots/*.png` relative to the `file:///` URL. Then per report:
`chrome --headless --no-pdf-header-footer --print-to-pdf=D:\Client-Reports\<Site>\<name>.pdf`.
Check page fill with pymupdf (`len(page.get_text())` per page) before looking at images -
it catches an orphan final page instantly, which happened twice here. **pymupdf needs
`D:/...`, not the `/d/...` MSYS path.**

## Internal/technical answers go IN CHAT, not as an artifact (2026-09-14)

Operator asked for a plugin keep/remove report, I published it as an Artifact, and
the reply was "I dont want artifact, tell me everything in this chat."

**Why:** the PDF format in this file is for CLIENT deliverables. Anything the
operator is going to act on themselves - audits, decision lists, keep/remove calls,
diagnostics - they want to read and answer in the conversation, not open in a
browser tab. An artifact adds a click and splits the thread.

**How to apply:** default to a full answer in chat for internal work on this
account. Keep the PDF route for client-facing reports only. Do not publish an
artifact for an operator-facing audit unless they ask for a shareable page.

## The "no plugin names" clause does NOT apply to plugin-maintenance reports (2026-09-15)

Writing the Mammoth work-completed sheet MM-2026-09-C2 I obeyed the "no tool or plugin
names" line above literally, and wrote "a separate program", "a tool the website already
has", "an add-on program". The operator's reply: **"This is still bad, what is tool the
website already had?"** followed by the plain version he wanted: *we already have
Elementor and its form function, but there was another plugin named Forminator running a
single form, so Forminator was replaced with an Elementor form.*

**Why:** the ban is on OUR internal vocabulary - cc-assistant, MCP, pending-change ids,
widget ids, draft_* tool names, API access. The CLIENT'S OWN plugins are the subject
matter of a maintenance report, and stripping their names turns every sentence into a
riddle. "Forminator has been replaced by Elementor's own form" is one clear sentence;
"a separate program was replaced with a tool the website already has" is unreadable and
tells the client nothing they can act on or check.

**How to apply:** name the client's own stack plainly - Elementor, Elementor's form
tool, Forminator, HT Mega, ElementsKit, Jeg Elementor Kit, Rank Math - and say what each
one does in the same sentence the first time it appears ("a plugin called HT Mega, which
produces the photo galleries on the machine pages"). Keep hiding ours. The test is
whether the name belongs to something the client is paying for and could open in their
own admin: if yes, name it.

Two other things that came out of the same rewrite:

- **Explain the mechanism, not just the outcome.** "The galleries disappeared" means
  nothing; "when a plugin that draws part of a page is switched off, Elementor drops that
  part and closes the gap, so the page still looks finished" is why nobody caught it.
- **Lead the document with the shape of the site.** One opening sentence saying the site
  is built in Elementor with extra plugins added over the years by different people makes
  every item underneath legible.

Related: [[feedback_reports_are_work_records_only]].

## Build the item list from the SESSION NOTES, not from list_recent_edits (2026-09-15)

Same document, next fault. I built MM-2026-09-C2 from `list_recent_edits` and reported
a single day's work. The operator: **"is it this much work we have done from yesterday?
or we have missed something... I think we have done work from saturday and we havent
included it."** He was right. The edit log under-reported the nine-day period by roughly
two thirds, for three separate reasons:

- **The biggest item of the whole period produced no edit row at all.** The home page was
  shipping a 167 MB background video (169.23 MB total page weight). Re-encoded to 1.56 MB,
  page down to 3.74 MB, 97.8% less. It was applied by the operator by hand in Elementor
  because the evidence gate was deadlocked, so nothing was ever queued and nothing appears
  in the change record.
- **Reports issued in the window are deliverables** and never appear in an edit log:
  MM-2026-R1 (8 Sep, redirects) and MM-2026-AI1 (11 Sep, AI crawler activity).
- **Bulk cards carry useless auto-summaries.** The 29 contrast fixes appear only as
  "Update Elementor widget 78c8e365" x28. Unreadable without the note that explains them.

**How to apply:** `get_site_memory`, split the notes on the `## YYYY-MM-DD HH:MM` session
headers, and read every entry in the window before writing a line. `list_recent_edits`
is a cross-check on dates, not the source of the item list. Then confirm the window
against the last report issued (here W36 covered 31 Aug to 6 Sep, so the honest window
was 7 to 15 Sep, not "yesterday").

## Never report a fix for a regression WE caused (2026-09-15, restating the 09-09 rule)

The same document credited us with restoring photo galleries on 20 machine pages. The
galleries only broke because HT Mega had been deactivated during our own plugin
clean-up. The operator: **"Few of the things happened because we changed something on
our end... also remove such things."**

Cut the whole item. Also cut the Rank Math reactivation for the same reason: it was
found deactivated mid-clean-up, and I cannot show it was not us. **When a fault's cause
cannot be cleanly separated from our own work in the same window, it does not go in the
report at all.** Self-inflicted breakage is absorbed silently, fixed, and left out.
This is the second time; see also the Soil and Aggregates product links under the
2026-09-09 heading above.
