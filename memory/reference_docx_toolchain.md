---
name: reference_docx_toolchain
description: "How to produce and VERIFY a .docx on this machine: python-docx builds it, Word COM converts to PDF, pymupdf renders it to look at. No pandoc, no LibreOffice."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 18814fd7-f825-40ce-b88b-9a6a4b18a82e
  modified: 2026-09-03T07:10:52.442Z
---

Verified 2026-09-03 on this machine.

**Available:** Python 3.13.7, `python-docx` 1.2.0, `pymupdf` 1.28.2, `pywin32`, and Word at
`C:\Program Files\Microsoft Office\root\Office16\WINWORD.EXE`.
**NOT available:** `pandoc`, `soffice` / LibreOffice. Do not plan a docx or PDF route around either.

**Build:** author with `python-docx` directly - a native Word document, never an HTML dump.
Word ignores CSS, so the artifact look has to be rebuilt with Word primitives:
- Cell shading needs raw XML (`w:shd` on `tcPr`); so do cell margins (`w:tcMar`),
  table borders (`w:tblBorders`) and the coloured callout left-border (`w:tcBorders`).
- Callout boxes = a single-cell table with a fill + a thick `w:left` border.
- Fonts that exist on Windows: Georgia (serif headings), Calibri (body), Consolas (mono
  numerals). Newsreader/IBM Plex are web fonts and will NOT resolve.

**Verify (do this every time - it caught two real defects):**
1. Word COM -> PDF: `win32com.client.Dispatch("Word.Application")`, `Documents.Open(path, ReadOnly=True)`,
   `SaveAs(pdf, FileFormat=17)`, then `word.Quit()` in a `finally`.
2. `pymupdf` -> PNG per page at ~110 dpi, then actually Read the images.

**The two defects this caught, worth pre-empting in any new docx:**
- Long tables split across pages **without repeating the header row**. Fix: set `w:tblHeader`
  on row 0.
- Rows split mid-cell across a page break. Fix: `w:cantSplit` on every row.
- Also set `keep_with_next` on headings and their intro paragraph so a heading never orphans
  at a page bottom.

Client deliverables file to `D:\` ([[reference_desktop_is_onedrive_redirected]]). House style and
report structure live in [[feedback_client_report_format]]; the verify-what-you-rendered rule is
[[feedback_verify_rendered_visuals_after_build]].
