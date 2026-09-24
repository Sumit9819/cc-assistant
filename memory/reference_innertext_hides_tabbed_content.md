---
name: reference-innertext-hides-tabbed-content
description: Playwright innerText returns "" for anything hidden, so tabs, hover panels and closed accordions read as empty and get reported as missing content
metadata:
  type: reference
---

`element.innerText` only returns **rendered** text. Anything in a tab, hover panel,
carousel slide or closed `<details>` that is not the active one comes back as an
empty string. `textContent` returns it regardless of display state.

**This produced a false finding on growthboss.co (2026-09-22).** The home page's
nine-service hover panel has nine `h3.svcs-panel-title` elements; only the hovered
one is displayed. `innerText` reported 8 of 9 as empty, and I shipped an instruction
telling the client to "fill in the eight blank headings". They were never blank:
Content Creation, Web Design & Development, Video Production, Google Ads, Facebook &
Instagram Ads, SEO, Branding & Design, AI & Automation. The operator caught it by
simply visiting the page and hovering.

**Always capture both, plus a visibility flag:**

```js
const vis = el => { const st = getComputedStyle(el), r = el.getBoundingClientRect();
  return el.offsetParent !== null && st.visibility !== 'hidden' && +st.opacity > 0.01
      && r.width > 0 && r.height > 0; };
heads.push({ inner: tx(h.innerText), text: tx(h.textContent), visible: vis(h) });
```

`inner === "" && text !== ""` means **hidden, not missing**. Never report that as a defect.

Two other traps found in the same pass:

- **`textContent` on `document.body` picks up `<script>` contents**, including the
  whole Next.js RSC payload (`$L34`, `"dangerouslySetInnerHTML"`). Use it per element,
  never for word counts or "does this text appear" checks. For "what a reader sees",
  `innerText` is the correct reading. Say which one a finding is based on.
- **FAQ questions live in `<summary>`, not headings.** growthboss.co uses
  `<details><summary class="X-faq-q"><span class="X-faq-q-text">`. Recommending
  "wrap the question in an H3" is right, but the H3 must go *inside* the existing
  `<summary>`; replacing `<summary>` breaks the fold-out.

Related: [[feedback_dom_is_ground_truth_not_parsers]],
[[reference_fonts_ready_lazy_faces_trap]], [[feedback_probe_discipline_positive_controls]].
