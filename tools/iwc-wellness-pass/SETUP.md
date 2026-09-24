# Wellness Pass: Google Sheet + unique codes (one-time setup, about 5 minutes)

Do this in the Google account that should SEND the code emails (ideally the clinic's
Google Workspace account, so emails come from the clinic and the daily limit is 1,500).
A personal Gmail account works too but can only send about 100 emails a day.

1. Go to https://sheets.new and name the sheet **Wellness Pass Signups**.
2. In the sheet: **Extensions > Apps Script**.
3. Delete everything in `Code.gs`, paste the whole contents of `Code.gs` from this folder.
4. At the top, replace `PASTE-A-LONG-RANDOM-STRING-HERE` with a long random string
   (any 30+ letters and numbers). Keep it; it goes into the website in step 9.
5. Click **Save**, choose `setup` in the function dropdown, click **Run**.
   Google asks for permission (Sheets + send email). Allow it.
   The sheet now has the header row and a Status dropdown.
6. Optional test: choose `testSignup`, click **Run**. You should get a code email, the
   clinic inbox gets a notification, and a row appears. Delete that test row afterwards.
7. Click **Deploy > New deployment**, gear icon **Web app**:
   - Execute as: **Me**
   - Who has access: **Anyone**
   Click **Deploy** and copy the **Web app URL** (ends in `/exec`).
8. Send the Web app URL and the secret string to Claude (or paste them below):
   `WEB_APP_URL?key=SECRET`
9. Claude puts that into the landing page form's webhook, then the page can go live.

## Front desk: redeeming a code

1. Patient shows a code starting with `IHW20-` and says their email. Each code is
   20% off ONE service: the one in the **Service chosen** column.
2. In the sheet, press Ctrl+F and search the code. Confirm the email matches.
3. Check **Status** says `Not redeemed`, then apply 20% to that one service.
4. Set Status to `Redeemed`, fill **Redeemed on** and **Staff initials**.
5. If Status already says `Redeemed`, the code has been used.

## If you change the script later

Deploy > Manage deployments > edit (pencil) > Version: **New version** > Deploy.
This keeps the same URL, so the website does not need to change.

## Why the emails are sent a minute later, not instantly

WordPress abandons an outgoing webhook after five seconds. Writing the row is
fast, but sending the code email and the staff notification took six to seven
seconds, so Elementor showed the visitor "something went wrong" even though the
code had already been created and emailed. The visitor would then submit again.

So `doPost` now only writes the row and answers immediately, and a trigger named
`sendQueuedEmails` runs once a minute and sends anything still marked **Queued**
in the "Code email sent" column. A signup is therefore recorded instantly and the
email arrives within about a minute.

What this means day to day:

- "Queued" in the last column means the email is on its way, not that it failed.
- If a row sits on "Queued" for more than a few minutes, open Apps Script >
  Triggers and check that `sendQueuedEmails` exists and is not erroring.
- Someone who submits twice keeps their original code; the second submission just
  re-queues the same code, so nobody ends up with two.

**After pasting this updated script you must:** run `setup` once (it creates the
minute trigger, and Google will ask for one extra permission), then
Deploy > Manage deployments > New version.
