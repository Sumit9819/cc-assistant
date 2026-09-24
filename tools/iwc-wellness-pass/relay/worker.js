/**
 * Wellness Pass relay.
 *
 * WHY THIS EXISTS: Elementor's Webhook action cannot talk to a Google Apps Script
 * URL. Apps Script answers a POST with a 302; WordPress then re-issues that
 * redirect as a GET but keeps the form body attached, and Google answers a
 * GET-with-body with 400. Elementor sees a non-200 and tells the visitor the
 * submission failed, even though the script already ran and emailed the code.
 * Measured 2026-09-16 against the live endpoint: clean GET 200, GET with body
 * 400, POST 405.
 *
 * This sits in the middle. It answers Elementor with 200 straight away and
 * forwards the submission to Apps Script in the background, so the visitor sees
 * the success message and never waits on Google.
 *
 * SETUP: the two values below are Worker variables, NOT pasted into this file.
 * In the Cloudflare dashboard: Settings > Variables and Secrets > add
 *   SCRIPT_URL  = the Apps Script Web app URL, ending in /exec
 *   SECRET      = the same long random string as CONFIG.SECRET in Code.gs
 */

export default {
  async fetch(request, env, ctx) {
    if (request.method !== 'POST') {
      return new Response('Wellness Pass relay is running.', { status: 200 });
    }

    if (!env.SCRIPT_URL || !env.SECRET) {
      // Fail loudly here rather than silently swallowing signups.
      return new Response(JSON.stringify({ ok: false, error: 'relay not configured' }), {
        status: 500,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    // Read the body now. It is gone by the time the background forward runs.
    const body = await request.text();

    ctx.waitUntil(
      fetch(`${env.SCRIPT_URL}?key=${encodeURIComponent(env.SECRET)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
        redirect: 'follow',
      }).catch((err) => console.error('forward to Apps Script failed', err))
    );

    return new Response(JSON.stringify({ ok: true }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  },
};
