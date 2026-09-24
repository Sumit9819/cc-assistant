---
name: feedback_page_by_page_work_orders
description: For sites without MCP write access, the deliverable is a page-by-page work order PDF (exact now/change-to text, anchors, targets, admin paths), not strategy prose; and never advise on a GBP/listing without first checking it exists
metadata:
  type: feedback
---

When a site cannot be edited through the cc-assistant queue (Shopify, anything non-WordPress), the operator wants
the equivalent of the pending-change queue as a PDF: **every page, every change, exact wording.** For each page:
current title / description / H1 quoted and the replacement with computed character counts, each internal link as
where + anchor text + target URL, content to add written out in full, schema as complete code, redirects and handle
changes, and the Shopify admin path. Pages needing nothing are listed with the reason so every sitemap URL is
accounted for. Decisions only the owner can make go in their own section instead of being guessed.

**Why:** on 2026-09-23 the operator rejected the five-week plans as the deliverable — "I asked you to provide SEO fix
for each pages one by one and not like this... what need to be internally linked to what anchor text" — and, in the
same message: "how can you provide information regarding gbp when you dont know if it exists or not". The plan had
told the client to "connect", "post weekly to" and "link to" Business Profiles that had never been checked.

**How to apply:**
- Build from `D:\seo-system\workorder\render.py` + a per-site spec (`lostaviator_spec.py`, `solocru_spec.py`).
  Read "from" values from the scan, never type them; let the renderer flag anything over 60 / 155.
- **Check external entities before writing about them.** Google Maps listings render only in a browser: load
  `https://www.google.com/maps?cid=<decimal of the second hex in the site's own hasMap link>` in Playwright and read
  name, rating, phone, hours (click the "Closes/Opens" line to get the weekly table). That check found a York Road
  phone mismatch and the Laird Road page missing its hours.
- **Verify every premise a recommendation rests on**, especially ones carried over from an earlier document. Building
  the Solo Cru work order exposed three REV B errors: the "-copy" products were different wines (the plan said to
  redirect them to their "originals", which would have removed ten products), the collection name was not on the page
  at all, and product titles already carried the current vintage.
- Write new copy only from what the page itself states; remove any claim the page does not make.

Related: [[feedback_client_report_format]], [[reference_seo_scanner_tool_benchmark]], [[project_solocru_lostaviator_engagement]].
