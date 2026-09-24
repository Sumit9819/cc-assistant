---
name: reference_cssom_walker_nesting_trap
description: "CSSOM rule-walkers that recurse on r.cssRules before checking r.selectorText silently skip EVERY style rule and report \"no matches found\""
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-12T07:45:13.101Z
---

When walking `document.styleSheets` to find rules, **check `r.selectorText` FIRST**, before any `if (r.cssRules) recurse` branch.

Browsers that support CSS nesting give `CSSStyleRule` its own (usually empty) `.cssRules` list. So the common pattern:

```js
if (r.cssRules) { walk(r.cssRules); continue; }   // WRONG
if (!r.selectorText) continue;
```

treats every plain style rule as a container, recurses into an empty list, and `continue`s past it. The walk returns only `@media`/`@keyframes` content and reports **zero style rules**, which reads as "nothing matches" rather than "the probe is broken".

Correct order:

```js
if (r.selectorText) { /* handle rule */ continue; }
if (r.type === CSSRule.KEYFRAMES_RULE) { /* ... */ continue; }
if (r.cssRules) walk(r.cssRules, r.conditionText || ctx);
```

This produced a wrong answer on sids-ponds (2026-08-12): a collision scan reported "zero rules touch these elements" when Divi actually had three (a global `margin:0` reset, `h1..h6 {padding-bottom:10px;line-height:1em}`, and `.et-l--body ul {padding:0 0 23px 1em}`). A correct walk on the same page saw 13,087 style rules.

**Always print a positive control** — assert the walker finds a selector known to exist, and count rules scanned. A scan that reports 0 rules seen is a broken scan, not a clean result. See [[feedback_probe_discipline_positive_controls]].

Related trap in the same session: `page.setContent('<html>...')` without a doctype runs in **quirks mode**, which accepts unitless lengths (`left: -100` parses as `-100px`). Standards-mode live pages drop them. Always include `<!doctype html>` in CSS-parsing harnesses or the harness will disagree with the real site.
