---
name: reference_gsc_generative_ai_report_2026
description: Search Console DOES have a Generative AI performance report (rolled out 31 Aug 2026) - impressions only, no queries, and no API type value to pull it
metadata:
  type: reference
---

**Verified 2026-09-11 against Google's own docs.** I told the operator that
Search Console does not break out AI Overviews. That was TRUE before, and is now
WRONG. Google announced Search Generative AI performance reports in June 2026 and
rolled them out to all sites worldwide on **31 August 2026**.

**What the report gives** (support.google.com/webmasters/answer/16984139):

- **Impressions ONLY.** No clicks, no CTR, no average position. "Impressions are
  how many times links to your site were shown to a user in a generative AI
  feature on Google Search."
- Dimensions: **pages, countries, devices, dates**. **Queries are NOT available.**
- Covers AI Overviews and AI Mode. A separate equivalent exists for Discover.
- The same impressions remain inside the overall Web search type totals, so
  **never add the generative AI segment on top of Web traffic** - it is a subset,
  not an addition.

**The API cannot pull it (checked the reference).** `searchAnalytics.query`
accepts only these `type` values: `discover`, `googleNews`, `news`, `image`,
`video`, `web`. There is no generative AI type. Filterable dimensions are
`country`, `device`, `page`, `query`, `searchAppearance`. It may eventually
surface as a `searchAppearance` value but that is UNVERIFIED - do not claim it
without testing on a property you can actually reach.

**So the only route today is the UI export.** Ask the operator to open the
Generative AI report in Search Console and export it, then build from that file.
The D:\gsc-tools API toolchain cannot produce this report.

**Watch the date range.** Data begins around the 31 Aug 2026 rollout, so a
request for "the last 3 months" cannot be satisfied yet; say so before promising
a quarter of data. Confirm the real earliest date from the export itself.

**Scope note worth telling clients:** this report covers GOOGLE's AI surfaces
only. ChatGPT, Perplexity, Claude and Copilot are invisible to it. The site's own
AI-crawler log ([[feedback_contrast_sweep_every_text_node]] tooling sits beside it
in D:\cc-assistant\tools) is the only view of those, and the two together are the
honest full picture. Neither proves citation.

## The plugin has its OWN GSC connection - check it before touching D:\gsc-tools

Resolved 2026-09-11. I spent a long time on a 403 from the Python toolchain
(`D:\gsc-tools`, OAuth token at `~/.gsc/token-sids.json`) whose account had lost
Mammoth access. The whole detour was avoidable: **`gsc_status` on the plugin
showed `configured:true, connected:true, property:sc-domain:mammothmachinery.ca`.**
The plugin holds a separate Google connection, authorised in wp-admin, and it was
working the entire time.

**Order of operations for any GSC work from now on: call `gsc_status` FIRST.** If
the plugin is connected, use `gsc_warehouse_sync` then `gsc_warehouse_query` and
ignore the Python toolchain entirely. whoami's `health_warnings.gsc_never_connected`
can be STALE within the same session - it was, here.

`gsc_warehouse_sync(max_dates=95)` pulled 31,784 rows covering 95 days in 167
seconds. Warehouse schema is `gsc_daily(date, page, query, clicks, impressions,
position)` - there is **no searchAppearance column**, which is why
`gsc_ai_overview` returns `count:0` even on a fully synced warehouse. That is not
a bug and not a sync problem: the generative-AI view genuinely is not in the API.

**Client-secret shapes matter.** A `web` OAuth client cannot do the local
loopback flow - `gsc-login.py` uses a random port and Google returns
`redirect_uri_mismatch` because web clients require an exactly pre-registered
redirect. Only `installed` (Desktop) clients are exempt. The `plugin-cc-assistant`
client the operator created is a WEB client whose only redirect is the wp-admin
callback, i.e. it belongs to the plugin's own connection, not to the CLI. Read
`redirect_uris` before assuming a client secret is interchangeable.

`gsc-login.py` now passes `open_browser=False` so it prints the URL instead of
seizing whichever Chrome profile is default, and it must be run with `python -u`
or its output is buffered and the URL never appears.
