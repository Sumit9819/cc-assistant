# Wellness Pass relay (5 minutes, free, no command line)

## Why this is needed

Elementor's Webhook action cannot call a Google Apps Script URL successfully.
Measured against the live endpoint on 2026-09-16:

| What is sent | Google answers |
|---|---|
| The original POST | 302 redirect |
| A clean GET to that redirect (what a browser does) | 200 |
| A GET with the form body still attached (what WordPress does) | **400** |
| A POST to that redirect | 405 |

WordPress converts a POST to a GET when it follows a 302, but it keeps the body
attached. Google rejects that shape, Elementor sees a non-200 and shows the
visitor "something went wrong" even though the script already created the code
and emailed it. The visitor then submits again.

This relay answers Elementor itself, so the form succeeds.

## Steps

1. Go to https://dash.cloudflare.com and sign in (a free account is enough).
2. Left menu: **Workers & Pages** > **Create** > **Start with Hello World!** >
   **Get started**.
3. Name it `iwc-wellness-pass` and click **Deploy**.
4. Click **Edit code**. Delete everything in the editor, paste the whole contents
   of `worker.js` from this folder, then **Deploy**.
5. Open the Worker's **Settings** tab > **Variables and Secrets** > **Add**:
   - `SCRIPT_URL` = the Apps Script Web app URL, the one ending in `/exec`
   - `SECRET` = the same long random string as `CONFIG.SECRET` in `Code.gs`

   Add them as **Secret** (not plain text), then **Deploy** again.
6. Copy the Worker URL. It looks like
   `https://iwc-wellness-pass.<your-subdomain>.workers.dev`.
7. Open the Worker URL in a browser. It should say
   "Wellness Pass relay is running." That confirms steps 1 to 5 worked.
8. Send that URL to Claude, who puts it into the Elementor form's webhook field
   in place of the Apps Script URL.

## What changes afterwards

- The visitor sees the success message, because the relay answers instantly
  instead of making WordPress wait on Google.
- Nothing else moves: the same Apps Script, the same Sheet, the same codes.
- The secret stops travelling through the website's page data. It lives in
  Cloudflare, and the form only holds the relay URL.

## If signups ever stop arriving

Open the Worker > **Logs** > **Begin log stream**, then submit the form once.
A line starting `forward to Apps Script failed` means Google refused the
forward; anything else means the relay is fine and the problem is in the script
or the Sheet.
