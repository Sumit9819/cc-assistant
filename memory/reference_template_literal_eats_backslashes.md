---
name: reference_template_literal_eats_backslashes
description: "JS/TS template literals silently delete backslashes, which corrupts any script or regex embedded in one (e.g. VS Code webview HTML); use String.raw"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 452688c6-0b59-44bb-a594-0508aa3427e6
  modified: 2026-08-31T12:53:58.314Z
---

A plain template literal processes escape sequences, so `\s` becomes `s` and `\n` becomes a real newline. Any **code** embedded inside one is therefore corrupted before it ships:

```ts
const html = `<script>const m = /(?:^|\s)@([^\s@]*)$/.exec(v);</script>`;
// delivered to the browser as:  /(?:^|s)@([^s@]*)$/
```

This fails **silently** — it compiles, it lints, the smoke test that only checks "does the script parse" still passes. Found it in cf-ai-assistant v1.3.0, where it had broken `@`-mention autocomplete and two newline escapes in the shipped webview.

**Fix:** emit the embedded script from `String.raw` in its own method, and keep the interpolating template only for the surrounding HTML that actually needs `${nonce}`:

```ts
private getScript(): string { return String.raw`  ...code with \s intact...  `; }
```

Two consequences inside a `String.raw` block: `${` still interpolates (so avoid it), and a literal backtick is impossible — spell it `String.fromCharCode(96)` and build such regexes with `new RegExp`.

**Verify, do not assume:** reconstruct the delivered string and assert on it, e.g. `String.raw({raw:[body]})` then `assert.match(script, /\(\?:\^\|\\s\)/)`. See [[feedback_probe_discipline_positive_controls]] and [[feedback_dom_is_ground_truth_not_parsers]].

Related trap in the same family: `String.replace(str, replacement)` interprets `$&`, `` $` ``, `$'`, `$1` in the replacement. For a literal replacement always pass a function: `content.replace(old, () => next)`.
