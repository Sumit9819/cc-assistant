/**
 * Irving Health and Wellness Clinic - 20% Wellness Pass
 *
 * Receives the landing page form (Elementor Pro webhook), gives every person
 * ONE unique code, records them in this Google Sheet, emails them the code,
 * and notifies the clinic.
 *
 * One code per email address: if the same email signs up again, the SAME code
 * is emailed again and no new row is added.
 *
 * Setup is in SETUP.md next to this file.
 */

const CONFIG = {
  // Must match the ?key= value at the end of the webhook URL in Elementor.
  SECRET: 'PASTE-A-LONG-RANDOM-STRING-HERE',

  SHEET_NAME: 'Signups',
  CLINIC_NOTIFY_EMAIL: 'info@irvingwellnessclinic.com',

  CLINIC_NAME: 'Irving Health and Wellness Clinic',
  PHONE: '972-891-8650',
  PHONE_TEL: '+19728918650',
  ADDRESS: '8200 N MacArthur Blvd Suite 100, Irving, TX 75063',
  HOURS: 'Monday to Friday, 9 AM to 5 PM',
  BOOKING_URL: 'https://irvingwellnessclinic.com/book-appointment/?utm_source=wellness-pass&utm_medium=email&utm_campaign=wellness-pass',
  OFFER_PAGE_URL: 'https://irvingwellnessclinic.com/wellness-pass/',

  CODE_PREFIX: 'IHW20-',
  CODE_LENGTH: 6,
};

const HEADERS = [
  'Signed up', 'Code', 'First name', 'Last name', 'Email', 'Phone',
  'Service chosen', 'Agreed to terms', 'Page', 'Status', 'Redeemed on',
  'Staff initials', 'Code email sent',
];
const COL = Object.fromEntries(HEADERS.map((h, i) => [h, i + 1]));

/** Run once from the editor: builds the sheet, headers and Status dropdown. */
function setup() {
  const sheet = getSheet_();
  sheet.getRange(1, 1, 1, HEADERS.length).setValues([HEADERS])
    .setFontWeight('bold').setBackground('#003017').setFontColor('#FFFFFF');
  sheet.setFrozenRows(1);
  const statusRule = SpreadsheetApp.newDataValidation()
    .requireValueInList(['Not redeemed', 'Redeemed', 'Void'], true)
    .setAllowInvalid(false).build();
  sheet.getRange(2, COL['Status'], sheet.getMaxRows() - 1, 1).setDataValidation(statusRule);
  sheet.getRange(2, COL['Code'], sheet.getMaxRows() - 1, 1).setFontFamily('Roboto Mono');
  sheet.autoResizeColumns(1, HEADERS.length);
  installMailTrigger_();
}

/**
 * The emails are sent by a background trigger, not by doPost. WordPress abandons
 * an outgoing webhook after five seconds, and sending two emails pushes a signup
 * past that, so Elementor showed the visitor an error even though the code had
 * already gone out. doPost now only writes the row and answers, which keeps it
 * well inside the timeout; this runs a moment later and does the slow part.
 */
function installMailTrigger_() {
  const already = ScriptApp.getProjectTriggers()
    .some(t => t.getHandlerFunction() === 'sendQueuedEmails');
  if (!already) ScriptApp.newTrigger('sendQueuedEmails').timeBased().everyMinutes(1).create();
}

function doPost(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    const p = (e && e.parameter) || {};
    if (p.key !== CONFIG.SECRET) return json_({ ok: false, error: 'unauthorized' });

    const lead = readLead_(p);
    if (!isEmail_(lead.email)) return json_({ ok: false, error: 'invalid email' });

    const sheet = getSheet_();
    const existing = findByEmail_(sheet, lead.email);

    // Same person again: keep their one code, queue another copy of the email.
    if (existing) {
      sheet.getRange(existing.row, COL['Code email sent']).setValue(QUEUED_RESEND);
      return json_({ ok: true, duplicate: true });
    }

    const code = newUniqueCode_(sheet);
    const row = [
      new Date(), code, lead.first, lead.last, lead.email, lead.phone,
      lead.interest, lead.consent, lead.page, 'Not redeemed', '', '', QUEUED_NEW,
    ].map(safeCell_);
    sheet.appendRow(row);

    return json_({ ok: true });
  } catch (err) {
    console.error(err);
    return json_({ ok: false, error: String(err) });
  } finally {
    lock.releaseLock();
  }
}

const QUEUED_NEW = 'Queued';
const QUEUED_RESEND = 'Queued resend';

/**
 * Runs every minute. Sends the code email for any row still marked Queued, then
 * stamps the row so it is never sent twice. A row that fails (quota, bad address)
 * is stamped with the reason instead of being retried forever.
 */
function sendQueuedEmails() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(10000)) return;
  try {
    const sheet = getSheet_();
    const last = sheet.getLastRow();
    if (last < 2) return;

    const width = HEADERS.length;
    const rows = sheet.getRange(2, 1, last - 1, width).getValues();

    for (let i = 0; i < rows.length; i++) {
      const state = String(rows[i][COL['Code email sent'] - 1]).trim();
      if (state !== QUEUED_NEW && state !== QUEUED_RESEND) continue;

      const isResend = state === QUEUED_RESEND;
      const code = String(rows[i][COL['Code'] - 1]);
      const lead = {
        first: String(rows[i][COL['First name'] - 1]),
        last: String(rows[i][COL['Last name'] - 1]),
        email: String(rows[i][COL['Email'] - 1]),
        phone: String(rows[i][COL['Phone'] - 1]),
        interest: String(rows[i][COL['Service chosen'] - 1]),
        page: String(rows[i][COL['Page'] - 1]),
      };
      if (!isEmail_(lead.email)) {
        sheet.getRange(i + 2, COL['Code email sent']).setValue('NO - bad address');
        continue;
      }

      let sent = false;
      try {
        sent = sendCodeEmail_(lead, code, isResend);
      } catch (err) {
        console.error(err);
      }
      sheet.getRange(i + 2, COL['Code email sent'])
        .setValue(sent ? (isResend ? 'Resent ' : 'Yes ') + stamp_() : 'NO - check quota');

      if (sent && !isResend) notifyClinic_(lead, code, true);
    }
  } finally {
    lock.releaseLock();
  }
}

/** Elementor sends either advanced data (fields[id][value]) or simple label keys. */
function readLead_(p) {
  const get = (id, label) => String(
    p['fields[' + id + '][value]'] ?? p[id] ?? p[label] ?? ''
  ).trim();
  return {
    first: get('first_name', 'First name'),
    last: get('last_name', 'Last name'),
    email: get('email', 'Email').toLowerCase(),
    phone: get('phone', 'Phone'),
    interest: get('interest', 'Which service do you want 20% off?'),
    consent: get('terms', 'Terms') ? 'Yes' : '',
    page: String(p['meta[page_url][value]'] ?? p['Page URL'] ?? ''),
  };
}

function getSheet_() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  return ss.getSheetByName(CONFIG.SHEET_NAME) || ss.insertSheet(CONFIG.SHEET_NAME);
}

function findByEmail_(sheet, email) {
  const last = sheet.getLastRow();
  if (last < 2) return null;
  const emails = sheet.getRange(2, COL['Email'], last - 1, 1).getValues();
  const codes = sheet.getRange(2, COL['Code'], last - 1, 1).getValues();
  for (let i = 0; i < emails.length; i++) {
    if (String(emails[i][0]).trim().toLowerCase() === email) {
      return { row: i + 2, code: String(codes[i][0]) };
    }
  }
  return null;
}

/** No 0/O or 1/I/L, so a code read aloud at the front desk cannot be misheard. */
function newUniqueCode_(sheet) {
  const alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  const last = sheet.getLastRow();
  const taken = new Set(last < 2 ? [] :
    sheet.getRange(2, COL['Code'], last - 1, 1).getValues().map(r => String(r[0])));
  for (let attempt = 0; attempt < 50; attempt++) {
    let s = '';
    for (let i = 0; i < CONFIG.CODE_LENGTH; i++) {
      s += alphabet[Math.floor(Math.random() * alphabet.length)];
    }
    const code = CONFIG.CODE_PREFIX + s;
    if (!taken.has(code)) return code;
  }
  throw new Error('Could not generate a unique code');
}

function sendCodeEmail_(lead, code, isResend) {
  if (MailApp.getRemainingDailyQuota() < 1) return false;
  const name = lead.first || 'there';
  const subject = 'Your 20% Wellness Pass code: ' + code;
  const text = [
    'Hi ' + name + ',',
    '',
    (isResend ? 'Here is your code again. ' : '') + 'Your code for 20% off one session of ' + (lead.interest || 'one service') + ' at ' + CONFIG.CLINIC_NAME + ' is:',
    '',
    code,
    '',
    'How to use it:',
    '1. Book your visit: ' + CONFIG.BOOKING_URL + ' or call ' + CONFIG.PHONE + '.',
    '2. At check-in, show this code and tell us this email address (' + lead.email + '). We take 20% off ' + (lead.interest || 'the service you chose') + '.',
    '',
    'Offer terms: 20% off one session of the service named above. Single treatments are discounted in full; on packages, courses and monthly programs the discount applies to one session or one month. One use per person. Not combinable with other promotions or sale pricing. ' +
      'Valid on new and existing patient visits. Prescription treatments require a clinical evaluation, ' +
      'and eligibility is decided by our clinician. No cash value. The offer may change or end.',
    '',
    CONFIG.CLINIC_NAME,
    CONFIG.ADDRESS,
    CONFIG.HOURS,
  ].join('\n');

  const htmlBody =
    '<div style="margin:0;padding:24px 0;background:#F1F2ED;font-family:Helvetica,Arial,sans-serif;color:#202020">' +
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">' +
    '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#FFFFFF;border-radius:2px">' +
    '<tr><td style="background:#003017;padding:28px 32px;color:#FFFFFF">' +
      '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#FFD900;font-weight:bold">Your Wellness Pass</div>' +
      '<div style="font-size:26px;font-weight:bold;margin-top:8px">20% off one session</div>' +
    '</td></tr>' +
    '<tr><td style="padding:28px 32px;font-size:16px;line-height:1.6">' +
      '<p style="margin:0 0 16px">Hi ' + esc_(name) + ',</p>' +
      '<p style="margin:0 0 20px">' + (isResend ? 'Here is your code again. ' : '') +
        'Your personal code for 20% off one session of <strong>' + esc_(lead.interest || 'one service') + '</strong> at ' + esc_(CONFIG.CLINIC_NAME) + ' is:</p>' +
      '<div style="border:2px dashed #003017;background:#FFFBE0;text-align:center;padding:18px;margin:0 0 24px">' +
        '<div style="font-family:Courier New,monospace;font-size:30px;font-weight:bold;letter-spacing:3px;color:#003017">' + esc_(code) + '</div>' +
      '</div>' +
      '<p style="margin:0 0 8px;font-weight:bold">How to use it</p>' +
      '<ol style="margin:0 0 24px;padding-left:22px">' +
        '<li style="margin-bottom:8px">Book your visit online or call <a href="tel:' + CONFIG.PHONE_TEL + '" style="color:#003017">' + CONFIG.PHONE + '</a>.</li>' +
        '<li>At check-in, show this code and tell us this email address (' + esc_(lead.email) + '). We take 20% off ' + esc_(lead.interest || 'the service you chose') + '.</li>' +
      '</ol>' +
      '<p style="margin:0 0 28px"><a href="' + CONFIG.BOOKING_URL + '" style="display:inline-block;background:#003017;color:#FFFFFF;text-decoration:none;font-weight:bold;padding:16px 24px;border-radius:2px">Book Consultation</a></p>' +
      '<p style="margin:0;font-size:13px;line-height:1.5;color:#555555">Offer terms: 20% off one session of the service named above. Single treatments are discounted in full; on packages, courses and monthly programs the discount applies to one session or one month. One use per person. Not combinable with other promotions or sale pricing. ' +
        'Valid on new and existing patient visits. Prescription treatments require a clinical evaluation, and eligibility is decided by our clinician. ' +
        'No cash value. The offer may change or end.</p>' +
    '</td></tr>' +
    '<tr><td style="padding:20px 32px;border-top:1px solid #E5E7E0;font-size:13px;line-height:1.5;color:#555555">' +
      esc_(CONFIG.CLINIC_NAME) + '<br>' + esc_(CONFIG.ADDRESS) + '<br>' + esc_(CONFIG.HOURS) +
    '</td></tr></table></td></tr></table></div>';

  MailApp.sendEmail({
    to: lead.email,
    subject: subject,
    body: text,
    htmlBody: htmlBody,
    name: CONFIG.CLINIC_NAME,
    replyTo: CONFIG.CLINIC_NOTIFY_EMAIL,
  });
  return true;
}

function notifyClinic_(lead, code, codeEmailSent) {
  if (!CONFIG.CLINIC_NOTIFY_EMAIL || MailApp.getRemainingDailyQuota() < 1) return;
  MailApp.sendEmail({
    to: CONFIG.CLINIC_NOTIFY_EMAIL,
    subject: 'New Wellness Pass signup: ' + (lead.first + ' ' + lead.last).trim() + ' (' + code + ')',
    body: [
      'Code: ' + code,
      'Name: ' + lead.first + ' ' + lead.last,
      'Email: ' + lead.email,
      'Phone: ' + lead.phone,
      'Service chosen for the 20%: ' + (lead.interest || 'NOT SPECIFIED'),
      'Code email sent: ' + (codeEmailSent ? 'Yes' : 'NO, daily email quota reached. Send the code manually.'),
      '',
      'Sheet: ' + SpreadsheetApp.getActiveSpreadsheet().getUrl(),
    ].join('\n'),
  });
}

/** Stops a signup like "=IMPORTXML(...)" from running as a formula in the Sheet. */
function safeCell_(v) {
  return (typeof v === 'string' && /^[=+\-@]/.test(v)) ? "'" + v : v;
}

function isEmail_(s) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(s);
}

function esc_(s) {
  return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function stamp_() {
  return Utilities.formatDate(new Date(), 'America/Chicago', 'MMM d, h:mm a');
}

function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

/** Editor test: runs a fake signup through the same path (sends a real email to TEST_EMAIL). */
function testSignup() {
  const TEST_EMAIL = Session.getActiveUser().getEmail();
  const res = doPost({ parameter: {
    key: CONFIG.SECRET,
    'fields[first_name][value]': 'Test',
    'fields[last_name][value]': 'Signup',
    'fields[email][value]': TEST_EMAIL,
    'fields[phone][value]': '972-555-0100',
    'fields[interest][value]': 'IV therapy or vitamin injections',
    'fields[terms][value]': 'on',
  }});
  console.log(res.getContent());
  sendQueuedEmails();   // the live flow waits up to a minute for this; here, run it now
  console.log('Queued email flushed. Check ' + TEST_EMAIL + ' and delete the test row.');
}
