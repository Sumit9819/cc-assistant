# Playwright probes

Sixteen one-purpose browser scripts written August and September 2026 to measure live
pages in a real browser (Chromium via Playwright), rescued on 2026-09-06 from
`%USERPROFILE%\.cc-assistant\wcag\`, where they had been dropped because that folder has a
`node_modules` with Playwright. They import `playwright` bare, so run them from that folder
or with `NODE_PATH=%USERPROFILE%\.cc-assistant\wcag\node_modules`.

| Script | Purpose |
|---|---|
| `balance.mjs`, `block.mjs`, `final-block.mjs`, `gap.mjs`, `gap-test.mjs`, `topdebug.mjs` | sids-ponds homepage product carousel measurements (DiviFlash) |
| `card-preview.mjs` | inject a proposed product-card CSS on the live sids-ponds carousel and screenshot before/after |
| `fa-audit.mjs`, `fa-inventory.mjs`, `fa-source.mjs`, `fa-which.mjs` | Font Awesome icon rendering audit (blank-icon class) |
| `footer-shot.mjs`, `shot.mjs`, `pdfshot.mjs` | screenshot helpers (footer, generic, PDF) |
| `inject-test.mjs`, `prove-saving.mjs` | route-interception injection tests (CLS root-cause work) |

All are site-specific and were written against pages as they were on the day. Treat as
patterns to copy, not as maintained tools. Browser UA is a real Chrome string because
SiteGround's WAF 403s bot agents.
