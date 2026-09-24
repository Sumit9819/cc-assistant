---
name: lint-entity-decoding-gap
description: "cc-assistant em-dash/AI-tell lint scans literal characters only — HTML-entity-encoded em dashes (&#8212;, &mdash;) bypass it"
metadata: 
  node_type: memory
  type: reference
  originSessionId: bdbe9d51-2098-4917-93a9-eb1883a6d1fc
  modified: 2026-07-23T04:53:44.319Z
---

Found 2026-07-23 on mammothmachinery.ca (plugin v0.51.0): the queue-time content lint that blocks em dashes checks only the literal `—` character. Content submitted with `&#8212;` or `&mdash;` entities passes the lint and reaches the pending inbox unflagged; the operator caught the dashes on the live page after approval.

Plugin fix needed: html_entity_decode() the payload (editor, acc_content, title fields) before running the em-dash / AI-tell / style-guide scans. Same gap likely affects any other character-based lint (ellipsis, curly quotes if ever banned).

Until fixed: self-check every content payload for all encodings before queueing (see [[Writing style for posts and pages]]).
