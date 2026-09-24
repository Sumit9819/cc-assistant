---
name: reference_heredoc_backslash_corruption
description: Regex backslash escapes written into a file through a Bash heredoc python patch can arrive as control characters (\b became a 0x08 backspace), silently breaking the pattern and producing plausible wrong numbers
metadata:
  type: reference
---

Patching a Python file by piping a heredoc into `python -` corrupted a regex:
`r"<table\b"` was written to disk as `r"<table<0x08>"`, a literal backspace. The
pattern then matched nothing.

**Why it is dangerous.** Nothing errored. `has_visual()` just returned `None` for
every section that contained a table, so the analyzer reported 100 warranted
cards instead of 81 and marked table sections as needing a card. The numbers
looked reasonable and were wrong. It was caught only because one row was
self-contradictory: a section reported `enum=table` and `candidate=True` at the
same time, which the blocking rule forbids.

**How to apply.**

1. After any scripted edit that writes a regex, check for control bytes:
   `python -c "print(open(f,'rb').read().count(b'\b'))"` - expect 0. Then
   `grep -n 're\.' file | cat -A` and look for `^H`, `^I`, `^[`.
2. Prefer a backslash-free pattern when the edit path is uncertain:
   `<table[^a-z]` instead of `<table\b`.
3. Verify behaviour on a known case, not just syntax. `ast.parse` passed happily
   on the corrupted file.
4. Distrust a metric that no assertion covers. The contradiction between two
   fields is what exposed this; a single number would have shipped.

Related: [[feedback_no_guessing_epistemic_discipline]],
[[feedback_probe_discipline_positive_controls]], [[feedback_confirm_change_took_effect]].
