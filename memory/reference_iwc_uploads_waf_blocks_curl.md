---
name: reference-iwc-uploads-waf-blocks-curl
description: irvingwellnessclinic.com WAF returns 403 with an HTML body for most /wp-content/uploads/ files, so image pixels cannot be audited by curl
metadata:
  type: reference
---

On irvingwellnessclinic.com most `/wp-content/uploads/` files return **403 with a
~75KB HTML block page** to curl/urllib, with or without a browser UA, referer, or
Basic auth. Freshly uploaded files sometimes return 200, which makes it look like
a per-file existence problem. It is not.

Two traps this creates:

- **403 is not "missing".** A file that definitely does not exist returns 403 too,
  so you cannot infer absence from the status code.
- **Check the body before believing the status.** The 403 carries a real payload;
  `size_download` looked like a plausible image size until the first four bytes
  turned out to be `<!DOCTYPE html>`.

To confirm an attachment exists, query `wp-json/wp/v2/media?search=<stem>` with
app-password auth — it returns the record and `media_details.width/height`.
To actually SEE an image, use the Playwright harness against the live page;
programmatic fetching of the file will not work.

Related: [[reference_siteground_ua]], [[feedback_probe_discipline_positive_controls]]
