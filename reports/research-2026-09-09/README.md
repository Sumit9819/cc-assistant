# CC Assistant research package

Start with CC-Assistant-Research.pdf: a 13-page report, including 38 source records and a prioritized roadmap.

- CC-Assistant-Research.md: editable report with source footnotes.
- CC-Assistant-Research.html: browser-readable version.
- CC-Assistant-Roadmap.csv: 18 work items with priority, relative effort, dependencies and acceptance criteria.
- sources.json: reference inventory.
- research-probes.php and research-probe-results.json: isolated reproductions of GSC rounding and removed-rule comparison behavior.
- source-hashes.json: hashes of relevant installed source at the assessment baseline.

This is a research package for version 0.84.0, not a plugin update. No implementation or production-site changes were made. Live results cited in the report are from September 8, 2026; external documentation was checked September 9.

The probe uses synthetic data and an in-memory database double. It reads the installed plugin path encoded in the script; running it against a later version may produce different results.

