---
name: reverify-metrics-at-report-time
description: "Every live metric in a client report must be re-pulled at report-writing time, never reused from earlier session context"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: bdbe9d51-2098-4917-93a9-eb1883a6d1fc
  modified: 2026-08-19T15:43:14.287Z
---

Caught on mammoth report MM-2026-08-P2 (2026-08-19): I wrote a client report using AI-crawl and pending-card figures measured 1-2 days earlier in the same session. User: "the number has changed today... re verify everything, so that we are actually providing accurate data." Live re-pull showed llm_crawls had grown 27→166 (window rolled AND traffic accelerated), the "most-read topic" claim no longer held, queued cards had been applied (69→88/83 scores now claimable), and a "~100→~230/day" figure from memory was actually 59→159/day on the honest window.

**Why:** Rolling-window metrics (7-day crawl logs, pending-queue state, daily averages) go stale within hours. A report circulates; a wrong number is a credibility hit with the client. This is the reporting-side twin of [[no-guessing-epistemic-discipline]] and [[precise-figures-only]].

**How to apply:** Before rendering any client report or dashboard: re-pull EVERY live metric in it (GSC, llm_crawls, lead_events, audit scores, pending-card status) in that same working block, and state each metric's window and pull date in the footnote. Numbers quoted from earlier session context, memory files, or prior reports are only allowed as clearly-labeled historical baselines.
