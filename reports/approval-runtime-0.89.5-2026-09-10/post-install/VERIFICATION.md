# 0.89.5 production verification — September 10, 2026

The live WordPress plugin and a freshly launched configured MCP bridge both report 0.89.5, with no version drift. The older native connection attached to this chat still identifies itself as 0.89.3; it reached the updated backend. Fresh-process checks independently confirmed the current bridge and both affected article histories.

All six unchanged proposals pass the production evidence gate:

| Pending IDs | Post | Proven approved predecessor | Result |
|---|---|---|---|
|1814, 1815, 1816|5092|1813|compatible_approved_changes|
|1818, 1819, 1820|5122|1817|compatible_approved_changes|

Every result reports diagnostics.safe=true and reason=compatible_approved_history. legacy_runtime_hash_matches names 1813 or 1817 respectively. This is production confirmation that the old recorded hashes require the narrowly bounded WordPress job-marker compatibility; the previous title saves are fully accounted for by their approved changes plus runtime markers. The diagnostic policy ignores exactly _pingme, _encloseme and _trackbackme. It does not identify which individual marker combination matched, and does not establish that all three were present.

The proposed values, old values, original evidence baselines, status and superseded pointers are unchanged from the pre-install record. No new proposals were created, and no live content was applied during this verification. Four SEO rows retain passing lint; excerpt rows have no attached lint report.

Next: refresh the WordPress Pending Changes inbox and review/apply these six original rows. Application requires the interactive administrator session. Apply-time permission, recovery, payload and current-state checks still run. After application, verify the saved excerpts and served SEO titles/descriptions before marking the work complete.

Evidence: native-checks.json contains all six production results. fresh-bridge-results.json confirms bridge/backend versions and repeats the evidence check for one row on each affected article. This report supersedes the release report's earlier statement that production verification was pending.