---
name: ui-screenshot-harness
description: How to SEE a local web UI before/after changes on this machine — Playwright chromium is installed; scratch harness + repo copy for the email-platform admin
metadata: 
  node_type: memory
  type: reference
  originSessionId: b4a29995-a741-497d-a7aa-8232ec273439
  modified: 2026-08-26T05:06:34.986Z
---

Playwright's Chromium (build 1234) lives in ~/AppData/Local/ms-playwright but the npm package is not installed globally. Working harness: C:\Users\sumit\AppData\Local\Temp\ui-shots (playwright 1.62 installed there) with shoot.mjs; repo copy at D:\email-platform\admin\frontend\tools\screenshot.mjs (logs in, shoots each page + element close-ups at a DPR, e.g. 1.25 to match Windows scaling). Read the PNGs with the Read tool.

**Why:** On 2026-08-21 the user reported "blurry icons" and a bad sign-out block; the code alone did not show it. Screenshots revealed hand-drawn 1.4px-stroke SVGs scaled to 40px (soft gray), low-contrast footer text, a naming mismatch (Mailboxes vs Email Accounts), dead disabled buttons, and `display:flex` on a `<td>` misaligning table rows. Fix that stuck: Lucide icons with absoluteStrokeWidth, user-card footer, margins not flex inside td.

**How to apply:** For any UI complaint or after any visual change, take before/after screenshots at the user's DPR and look at them before claiming it is fixed (extends [[verify-rendered-visuals-after-build]]). Never put display:flex on a td; never hand-draw icon sets when a mature set exists.
