---
name: feedback-bash-heredoc-and-pipe-gating
description: Two bash traps that cost four failed runs in one session - multiple heredocs on one line assign bodies in operator order, and a pipe after a gating command masks its exit code so && never gates
metadata:
  type: feedback
---

Two failure modes, both self-inflicted, both silent until the damage was done (2026-08-25, faceless-studio build).

**1. Two heredocs on one command line.** `python - <<'A' ... && ... && python - <<'B'` followed by bodies written B-then-A: bash assigns bodies to `<<` operators in the order the operators appear on the line, not the order the bodies are written. The first heredoc swallowed BOTH bodies (including the literal `B` terminator line, which became a `NameError`), the second got end-of-file. Also: long heredocs (~100+ lines) with mixed quote styles broke bash parsing outright, three separate times.

**2. A pipe after a gating step.** `python smoke.py | grep -v Warning && next_step` - the exit status is grep's, not python's. The smoke test failed, grep matched lines, exit 0, and `&&` ran the full render chain past the failure it existed to prevent. The bug the smoke test was written to catch then surfaced inside a 3-minute compose instead of a 10-second test.

**Why:** Both look correct when written and produce plausible partial output, so the failure is only discovered by reading the tail of a long run. Together they turned a 10-second gate into a wasted 5-minute chain plus a diagnostic round.

**How to apply:**
- **Broke this rule again 10 minutes after writing it** (two heredocs on one line, second time that day). The threshold was too soft. New rule: **a heredoc is only for a single, short, standalone `python -` with nothing chained after it.** Any chain of two or more steps goes in ONE .py file that drives the steps via `subprocess.run` and checks each exit code. No exceptions for "it's only 60 lines."
- Anything with mixed quotes goes in a FILE via the Write tool, then `python file.py`.
- Never more than one heredoc per command line.
- Never pipe a command whose exit code gates a `&&` chain. Suppress noise with `export PYTHONWARNINGS=ignore` or redirect, or check `${PIPESTATUS[0]}` explicitly.
- On Windows, `/d/path` is a bash-only convention; Python's `open()` needs `D:\path` or `D:/path`. A `FileNotFoundError` from a `/d/` path in `python -c` is the diagnostic being wrong, not the file being missing.

Related: [[project-faceless-video-studio]], [[feedback-no-guessing-epistemic-discipline]].

STRIKE 3 (2026-08-26): a patch script written inline in a bash heredoc had its `\n` escape collapse into a real newline inside a replacement block; ast.parse caught it, but the run proceeded with unpatched code. HARD RULE: any patch that embeds string literals with escapes is ALWAYS a Write-tool file (raw strings inside), never an inline heredoc. ast.parse before write remains mandatory - it is what contained all three strikes.

**Dollar signs in arguments (2026-09-05):** `fvs new --title "The $800 Loophole"` reached
the manifest as "The 00 Loophole" because bash expanded `$8` inside double quotes, and
the cold open rendered with the wrong title before anyone looked. Pass titles, prices and
anything with `$` through single quotes or a Python script, and read the manifest back.
Also: a heredoc containing both `'''` and apostrophes fails to parse in this Bash tool -
write such scripts to a file with Write and run the file.
