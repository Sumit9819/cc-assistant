---
name: reference-paragraph-length-lint-preexisting
description: "paragraph_length lint can fail on a post's pre-existing body, blocking every edit to it; diagnose with a 1-char dry_run before assuming your text caused it"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ae37a612-eb3c-4f37-b85d-76f7f880700a
  modified: 2026-07-27T06:00:35.539Z
---

The `paragraph_length` lint scans the **whole resulting body**, not just the text a patch introduces. If a published post already trips it, every future `draft_patch_post_content` / `draft_update_post_content` on that post fails lint no matter how small the change.

**Diagnostic (do this before rewriting your own text):** re-run `draft_patch_post_content` with `dry_run: true` and a single trivial patch, e.g. swapping one URL for another (5-character delta). If lint still reports `paragraph_length`, the failure is pre-existing and nothing you wrote caused it.

**Observed 2026-07-27, irvingwellnessclinic post 6356** (IV therapy / chronic fatigue, 9,366 chars):
- Full 3-patch info-gain edit: `paragraph_length` fail
- Same edit with the long paragraph split into a `<ul>`: still fail
- Link-only patch, 5-char delta: still fail
- Splitting the 60-word intro into two paragraphs: still fail

**ROOT CAUSE CONFIRMED (2026-07-27).** The `lint_report` returned by `verify_change` exposed the actual violations, and they prove the check is measuring the wrong unit. It counts sentences in the **whole block between two headings**, not in an individual `<p>`. Evidence from post 6356:

| Reported "paragraph" | Sentences | What it actually is |
|---|---|---|
| "Fatigue can stem from dehydration…" | 6 | the Key Takeaways `<ul>`, one sentence per `<li>` |
| "Dehydration: Even mild dehydration…" | 6 | the causes `<ul>`, one per `<li>` |
| "These often point to thyroid…" | **24** | the entire FAQ section, all five Q&A pairs merged |
| "Oral supplements have to survive…" | 6 | two adjacent short paragraphs merged across the blank line |

No real prose paragraph on that page exceeds three sentences. A `<ul>` with six items and a well-built FAQ block both trip it, meaning **the check penalises exactly the structure the rest of the playbook demands** (lists, tables, question H2s with short answers). Any post with a decent FAQ will fail it permanently.

**MINIMAL REPRO (2026-07-27, via `draft_create_post` dry_run).** Two probes isolate it exactly:

Probe A, **PASSES** 13/13:
```html
<h2>Short section</h2>
One sentence here. Two sentences here. Three sentences here.
<h2>Another short section</h2>
One sentence here. Two sentences here.
```

Probe B, **FAILS** `paragraph_length`:
```html
<h2>Frequently Asked Questions</h2>
<h3>First question here?</h3>
One sentence here. Two sentences here.
<h3>Second question here?</h3>
One sentence here. Two sentences here.
<h3>Third question here?</h3>
One sentence here. Two sentences here.
```

**The check breaks blocks on `<h2>` only and ignores `<h3>` entirely.** Probe B is 6 short sentences spread across three Q&A pairs, and it still trips the 5-sentence ceiling. Consequence: **any FAQ section with 3 or more questions fails permanently, on every post, forever.** The lint therefore forbids exactly what the SEO playbook mandates (FAQPage schema, question-shaped subheads, 40-60 word answers). The two cannot both be satisfied.

**Plugin fix needed:** break paragraph blocks on ALL heading levels (h1-h6), not just h2, and count sentences per `<p>` / `<li>` rather than per inter-heading block. Exclude `<ul>`, `<ol>` and `<table>` from the prose measure.

Until fixed, `override_lint: true` is the only route for any post containing an FAQ, and it is legitimate: state in the reasoning field that the failure is the known h3 bug, cite the probe, and confirm no hard violations were bypassed. Hard violations (em dashes, ai_tells, banned phrases, placeholders) are reported separately and must never be overridden.

Joins the known false-positive list with [[reference_suspicious_chars_lint_bug]], [[reference_address_consistency_false_positive]], [[reference_schema_parity_false_positives]], and [[reference_section_width_guard_nesting]].

**Resolution:** `override_lint: true` is the intended escape hatch, but the tool's contract states it may only be set *after showing the lint failure to the human and getting explicit acceptance*. Show the diagnostic, get the OK, then queue. Do not override silently.
