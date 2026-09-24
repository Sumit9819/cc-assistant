---
name: reference-keyword-targets-false-rivals
description: "keyword_targets false outranked_internally sources fixed v0.75.2/0.75.3 (utm tracking variant; 301 redirect residue) - both bin/-side, need MCP restart; verify with tracking_variant / redirect_fold keys in the report"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-25T06:00:21.153Z
---

Two ways `keyword_targets` reported the owner as beaten by "its own page" when it was not (measured 2026-08-25):

1. **Tracking variant (v0.75.2).** `https://eroflufkin.com/?utm_source=gmb` (the Business Profile link) is its own GSC row from a different surface. Folding it into the owner dragged the homepage 1.5 -> 2.2 on "er of lufkin". Now excluded from owner position, reported as `tracking_variant`.
2. **Redirect residue (v0.75.3).** `erofirving.com/blog/iv-for-dehydration` (301 -> root URL since 2026-07-27) kept reporting at 6.9 vs owner 7.1 -> false `outranked_internally`. Now `cc_kw_redirect_map()` resolves every page on every target query through `/commodity/signals` (chunks of 50) and folds 301 sources into the destination, listed as `folded_redirects`; report carries `redirect_fold.status` (`applied | unavailable | none`). If the site is unreachable the map is empty and the report SAYS so instead of silently listing residue as rivals.

**How to apply:** both fixes live in `bin/keyword-targets.php` -> they activate only after the MCP process restarts ([[reference_plugin_dev_vs_remote_deploy]]). Proof the new code is running: the report contains the `redirect_fold` key. Absent key = old process. Before acting on any `outranked_internally`, check the rival is neither a `?utm_` variant nor a known redirect. See [[reference_keyword_targets_win_loop]].
