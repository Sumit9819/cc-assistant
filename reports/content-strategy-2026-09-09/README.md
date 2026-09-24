# CC Assistant content strategy research

The main report is **CC-Assistant-Content-Strategy.pdf**: 12 pages, approximately 5,000 body words, and 26 references. The Markdown and HTML versions contain the same report. All research and code observations are dated September 9, 2026.

This report supplements the existing plan at D:/cc-assistant/reports/research-2026-09-09/. Its C01–C15 items are additions; R01–R18 remain the reliability and technical SEO foundation. It is a capability and implementation roadmap, not a live competitor or site-wide content audit.

## Main files

- CC-Assistant-Content-Strategy.pdf / .md / .html: cited findings and proposed workflows.
- CC-Assistant-Content-Roadmap.csv: 15 prioritized additions with relative effort, dependencies and acceptance criteria.
- CC-Assistant-Content-Decisions.csv: 10 action policies, including refreshing, linking, merging and preserving language alternatives.
- sources.json: full references, access dates and evidence notes.
- source-hashes.json: SHA-256 hashes of 14 inspected installed plugin files.
- strategy-probes.php / strategy-probe-results.json: isolated current-behavior reproductions.
- document-validation.json: PDF page/text/link inspection.
- report-content-final.json / roadmap-data.json / decision-data.json: structured report inputs.
- build-report.py / inspect-report.py: local rendering and inspection helpers.

## Scope of validation

The probes call installed PHP methods with synthetic fixtures and stubbed WordPress helpers. They do not bootstrap WordPress, connect to a database, fetch competitors or change live data. PHP 8.2 with mbstring was used.

The WinAudit authority-host fixture uses the module's default fallback host list because CC_Assistant_SEO_Tools is not loaded in that fixture. It demonstrates the substring-matching weakness under that supported fallback. It does not report the production site's configured host list.

The position example is arithmetic illustrating an inspected AVG(position) SQL expression; that query was not executed end-to-end. The STOP/CITE_PLAY threshold example demonstrates a weak strategic decision basis, not that any threshold is inherently a software bug.

The report's release gates are proposed tests for future implementation. They have not been reported as passing. No plugin code, WordPress content or live settings were modified in this research task.

The PDF was checked for page count, extracted content, references and orphan overflow. Representative pages were visually inspected. The report has ten body pages and two source pages.

## Reproduction

Run strategy-probes.php with the local D:/cc-assistant/php/php.exe binary and its mbstring extension. The fixture refers to the installed D:/cc-assistant/wp-content/plugins/cc-assistant source, so compare source-hashes.json before interpreting changed results.

Run build-report.py with Python to regenerate Markdown, HTML and the roadmap CSV from the included JSON inputs. The PDF was printed from generated static HTML using an isolated Chrome headless profile. inspect-report.py requires pymupdf.

These are research artifacts. The next implementation step is Phase A, alongside the earlier reliability fixes.

