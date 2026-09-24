---
name: feedback_never_noindex_pages
description: "HARD operator rule 2026-09-06: NEVER propose or queue noindex on any page, any site. A page near position 10 cannot earn clicks yet; noindex removes the chance. Zero-click pages are improved or merged. Plugin dead_inventory advice text was reworded to match."
metadata:
  type: feedback
---

**The rule.** Never recommend, queue, or approve `noindex` on a page. Not for zero-click
"dead inventory", not for cannibalisation, not for thin pages. The remedy for a page that
shows in search and earns nothing is to improve it or merge it into the page that already
wins the topic, keeping the URL indexable.

**Why (operator, 2026-09-06, irvingwellnessclinic audit).** The audit listed 15 zero-click
pages and repeated the plugin's own advice to noindex them. The operator: "how come a
10-ranking page can get clicks?" A page at position 8 to 12 is being shown and simply has
not been clicked yet; deindexing it converts a maybe into a certain zero, and the site had
already lost the whole older weight-loss cluster that way (six noindex removals were
queued on 2026-09-04 to undo it). Zero clicks is a symptom of position and snippet, not of
worth.

**How to apply.**
- When site_status `dead_inventory`, commodity_audit `STOP`, or any tool suggests
  deindexing: translate it to "improve title/snippet, add inbound links, or merge". Say
  explicitly that noindex is off the table.
- If asked to "clean up" indexing, the only allowed direction is REMOVING existing noindex
  (as on 2026-09-04) or fixing canonicals/redirects.
- The plugin's `site_status` dead_inventory action text now says improve-or-merge (bin/
  site-status.php, workspace copy, 2026-09-06).

Related: [[feedback_intent_doctrine]], [[feedback_paginated_archives_not_worth_indexing]]
(archives are a different class: they were never content), [[project_cc_assistant_operator_brain]].
