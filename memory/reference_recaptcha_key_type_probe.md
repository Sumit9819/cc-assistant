---
name: reference-recaptcha-key-type-probe
description: How to tell from outside whether a site's public reCAPTCHA site key is v3, v2 checkbox or v2 invisible, and whether its domain is allowed - without the secret; caught a v2 checkbox key pasted into Elementor's v3 slot on mammoth
metadata:
  type: reference
---

The public site key (`data-sitekey` in the page HTML) can be tested against Google's
anchor endpoint with curl. No secret needed, nothing submitted to the client's forms.

```
V=$(curl -s https://www.google.com/recaptcha/api.js?render=explicit | grep -o 'releases/[A-Za-z0-9_-]*' | head -1 | cut -d/ -f2)
CO=$(printf 'https://DOMAIN:443' | base64 | tr '=' '.')
curl -s "https://www.google.com/recaptcha/api2/anchor?ar=1&k=KEY&co=$CO&hl=en&v=$V&size=invisible"   # or size=normal
```
`v` must be a real release id; an empty `v` makes EVERY key return "Invalid input".

Answers, each verified 2026-09-23 against Google's own demo keys at
recaptcha-demo.appspot.com (v3 / v2-invisible / v2-checkbox pages):

| key type        | size=invisible  | size=normal     |
|-----------------|-----------------|-----------------|
| v3              | recaptcha-token | Invalid input   |
| v2 invisible    | recaptcha-token | (not tested)    |
| v2 checkbox     | Invalid input   | recaptcha-token |
| wrong domain    | "Invalid domain for site key" |   |
| nonexistent key | "Invalid site key" |              |

v3 and v2-invisible both pass size=invisible, so separate them with size=normal (v3 fails).

**Why:** on mammothmachinery.ca the operator pasted a v2 checkbox key into Elementor's
reCAPTCHA v3 slot. Every Contact Us submission failed with the generic "Invalid form,
reCAPTCHA validation failed" + "suspected as abusive usage", which reads like a low score.
Lowering the threshold 0.5 -> 0.3 (#1257) could not help, and I only probed after that.

**How to apply:** after anyone saves reCAPTCHA keys, probe the live key's type and domain
BEFORE adding a captcha field to a lead form, and before touching the threshold. Elementor
Pro's generic failure message covers wrong key type, low score and action mismatch alike;
only a missing or invalid secret gets its own wording. Related:
[[feedback_probe_discipline_positive_controls]], [[feedback_no_guessing_epistemic_discipline]].
