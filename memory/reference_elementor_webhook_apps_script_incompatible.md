---
name: reference-elementor-webhook-apps-script-incompatible
description: Elementor Pro's Webhook action ALWAYS reports failure against a Google Apps Script /exec URL (302 then GET-with-body = 400), while the script still runs; fix with a must-use plugin that posts and ignores the reply
metadata:
  type: reference
---

Found on irvingwellnessclinic Wellness Pass (post 10902), 2026-09-16. Cost most of a day and two wrong theories.

**Symptom:** visitor sees Elementor's error message, yet the Sheet row exists and the code email was sent. People then resubmit.

**Mechanism (measured against the live endpoint, and read in wp-includes/class-wp-http.php):**
- Apps Script answers any POST to /exec with 302 to script.googleusercontent.com.
- `WP_Http::handle_redirects()` switches the method to GET on a 302/303 but never clears `$args['body']`, then calls `wp_remote_request()` with the body still attached.
- googleusercontent answers: clean GET 200, GET with body 400, POST 405.
- Elementor sees 400 and throws. The script already ran on the first POST, so the work is done.
- node fetch / curl / browsers send a clean GET and get 200, which is why direct probes "work" and hide the bug.

**Wrong theories to skip:** the 5s WordPress HTTP timeout (moving emails to a trigger made it faster, still failed), and Flying Scripts delaying Elementor JS (check its `flying_scripts_include_list`; on IWC it holds trackers only).

**Fix that worked (operator refused Cloudflare):** `wp-content/mu-plugins/iwc-wellness-pass.php` hooks `elementor_pro/forms/new_record`, filters on `form_name`, posts `fields[<id>][value]` itself and ignores the reply; the form's `submit_actions` reduced to `["save-to-database"]`. Source kept at `D:\cc-assistant\tools\iwc-wellness-pass\mu-plugin\`. Keep the Apps Script replying fast: emails go out from a 1-minute `sendQueuedEmails` trigger, rows marked "Queued" meanwhile. `setup()` must be re-run after pasting to create that trigger; a row stuck on "Queued" means no trigger.

**Diagnostic trick:** re-POST the test email straight to the script. `duplicate:true` means the site's request DID arrive; `ok:true` means it never did.

**Testing trap:** headless Playwright submits on this site became unreliable (click reset the form with no request). A human submit in a normal browser settled it in one try. Ask for one early instead of iterating automation.

Related: [[reference-elementor-import-validator-traps]] (why the webhook URL itself could not be queued).
