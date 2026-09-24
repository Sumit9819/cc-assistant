/**
 * Download the #designfor-sumit blog images out of Slack, sorted by destination post.
 *
 * Why a browser at all: the MCP Slack tool hands back a RENDERED image, not the
 * file, so the original bytes never reach the model. Slack's own download URLs
 * need a session. Driving a logged-in browser is the only route that does not
 * involve handing a token around in chat.
 *
 * Deliberately NOT using the real Chrome profile. That would give this script
 * the whole browser session — every workspace, every DM, plus whatever else is
 * signed in. It keeps its own profile directory instead, scoped to this job,
 * which you log into once.
 *
 *   node slack_fetch.mjs login            # visible window: sign in, then close it
 *   node slack_fetch.mjs fetch  <outdir>  # headless: pull every file in the list
 *
 * Files land in <outdir>/<post-slug>/NN-<title>.png, numbered in reading order,
 * which is exactly the layout prepare_images.py expects.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

/**
 * Borrow Playwright from wherever it is already installed on this machine.
 *
 * This script lives in a scratch directory with no package.json of its own, so
 * a bare `import 'playwright'` finds nothing. Installing a second copy would
 * also be wasteful: the browser binaries under ms-playwright are shared, and
 * only the JS package is missing. So resolve it out of an existing project.
 */
function loadPlaywright() {
  const candidates = [
    'C:/Users/sumit/.cc-assistant/wcag/package.json',
    'D:/faceless-studio/motion/package.json',
  ];
  const tried = [];
  for (const base of candidates) {
    if (!fs.existsSync(base)) { tried.push(`${base} (no package.json)`); continue; }
    try {
      return createRequire(base)('playwright');
    } catch (e) {
      tried.push(`${base} (${e.code || e.message})`);
    }
  }
  throw new Error(
    'Could not load Playwright from any known install:\n  ' + tried.join('\n  ') +
    '\nFix: npm install playwright in this folder, or add a path to candidates[].'
  );
}
const { chromium } = loadPlaywright();

const HERE = path.dirname(fileURLToPath(import.meta.url));
const PROFILE = path.join(HERE, 'slack-profile');
const LIST = path.join(HERE, 'download_list.json');
const WORKSPACE = 'https://focusyourfinance.slack.com';

const mode = process.argv[2];
const outDir = process.argv[3] || path.join(HERE, 'slack-images');

function safe(s) {
  return s.replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 70);
}

async function login() {
  const ctx = await chromium.launchPersistentContext(PROFILE, {
    headless: false,
    viewport: { width: 1280, height: 900 },
  });
  const page = ctx.pages()[0] || (await ctx.newPage());
  await page.goto(WORKSPACE, { waitUntil: 'domcontentloaded' });

  console.log('\n  A browser window is open.');
  console.log('  Sign in to Slack and open #designfor-sumit.');
  console.log('  WAIT for "TOKEN CAPTURED" to appear here before closing it.\n');

  // Poll while the operator signs in. The first version just waited for the
  // window to close and declared success, so a window opened and closed without
  // signing in looked identical to a real login — and the failure only surfaced
  // later, during the download. Confirm the token exists while we can still say
  // so.
  let captured = false;
  const poll = setInterval(async () => {
    if (captured || ctx.pages().length === 0) return;
    try {
      const res = await ctx.pages()[0].evaluate(scrapeToken);
      if (res.token) {
        captured = true;
        console.log('  TOKEN CAPTURED — signed in. You can close the window now.\n');
      }
    } catch { /* page mid-navigation; try again next tick */ }
  }, 2500);

  await new Promise((resolve) => ctx.on('close', resolve));
  clearInterval(poll);

  if (captured) {
    console.log('  Session saved. Now run:  node slack_fetch.mjs fetch <outdir>');
  } else {
    console.log('  WINDOW CLOSED WITHOUT SIGNING IN — nothing usable was saved.');
    console.log('  Re-run login and complete the sign-in before closing.');
    process.exitCode = 1;
  }
}

/**
 * Pull the web client's API token out of the signed-in page.
 *
 * This is the token the Slack web app uses for its own requests; reading it is
 * what lets us ask files.info for a real download URL instead of scraping the
 * DOM, which breaks every time Slack ships a redesign.
 */
function scrapeToken() {
  // Runs inside the page. Slack has moved this key around between releases, so
  // rather than trusting one name, sweep localStorage for anything holding an
  // xox* string. Returns the token or a diagnostic of what was actually there.
  const keys = Object.keys(localStorage);
  for (const k of keys) {
    const raw = localStorage.getItem(k) || '';
    if (!raw.includes('xox')) continue;
    try {
      const parsed = JSON.parse(raw);
      const stack = [parsed];
      while (stack.length) {
        const node = stack.pop();
        if (!node || typeof node !== 'object') continue;
        for (const v of Object.values(node)) {
          if (typeof v === 'string' && /^xox[a-z]-/.test(v)) return { token: v };
          if (v && typeof v === 'object') stack.push(v);
        }
      }
    } catch {
      const m = raw.match(/xox[a-z]-[A-Za-z0-9-]+/);
      if (m) return { token: m[0] };
    }
  }
  return { token: null, url: location.href, keys: keys.slice(0, 25) };
}

/**
 * Find the Slack web token, checking BOTH origins.
 *
 * The workspace host (foo.slack.com) and the actual client (app.slack.com) are
 * separate origins with separate localStorage. Signing in on one leaves nothing
 * readable on the other, which is exactly how the first attempt came back empty
 * while looking, from the outside, like a clean run.
 */
async function getToken(page) {
  const origins = ['https://app.slack.com/client', WORKSPACE];
  let diag = null;
  for (const origin of origins) {
    try {
      await page.goto(origin, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForTimeout(2500);
      const res = await page.evaluate(scrapeToken);
      if (res.token) return res.token;
      diag = res;
    } catch (e) {
      diag = { url: origin, keys: [`navigation failed: ${e.message}`] };
    }
  }
  throw new Error(
    'No Slack token in this profile — the browser is not signed in.\n' +
    `  Last page: ${diag?.url}\n` +
    `  localStorage keys seen: ${(diag?.keys || []).join(', ') || '(none)'}\n` +
    '  Run `node slack_fetch.mjs login`, sign in until you can SEE the channel\n' +
    '  message list, wait for "TOKEN CAPTURED" in the terminal, then close it.'
  );
}

async function fetchAll() {
  if (!fs.existsSync(PROFILE)) {
    throw new Error('No saved session. Run `node slack_fetch.mjs login` first.');
  }
  const list = JSON.parse(fs.readFileSync(LIST, 'utf8'));

  const ctx = await chromium.launchPersistentContext(PROFILE, { headless: true });
  const page = ctx.pages()[0] || (await ctx.newPage());
  await page.goto(WORKSPACE, { waitUntil: 'domcontentloaded' });
  const token = await getToken(page);

  let ok = 0, failed = 0, bytes = 0;
  const report = [];

  for (const post of list.posts) {
    const dir = path.join(outDir, post.slug);
    fs.mkdirSync(dir, { recursive: true });
    console.log(`\n${post.slug}  (post ${post.post_id}, ${post.files.length} files)`);

    for (let i = 0; i < post.files.length; i++) {
      const f = post.files[i];
      const n = String(i + 1).padStart(2, '0');
      try {
        const info = await ctx.request.get(
          `https://slack.com/api/files.info?file=${f.id}`,
          { headers: { Authorization: `Bearer ${token}` } }
        );
        const body = await info.json();
        if (!body.ok) throw new Error(body.error || 'files.info refused');

        const url = body.file.url_private_download || body.file.url_private;
        const ext = (body.file.filetype || 'png').toLowerCase();

        // Cookies from the browser context authorise files.slack.com.
        const dl = await ctx.request.get(url, {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (!dl.ok()) throw new Error(`download HTTP ${dl.status()}`);
        const buf = await dl.body();

        // Slack serves its login page with a 200 when auth is wrong; a real
        // PNG/JPEG/WebP never starts with '<'. Catch it here rather than
        // discovering it as a corrupt upload three steps later.
        if (buf.length < 1024 || buf[0] === 0x3c) {
          throw new Error('got HTML, not an image - session likely expired');
        }

        const name = `${n}-${safe(f.title)}.${ext}`;
        fs.writeFileSync(path.join(dir, name), buf);
        bytes += buf.length;
        ok++;
        console.log(`  ok   ${name}  (${(buf.length / 1048576).toFixed(1)} MB)`);
        report.push({ ...f, post_id: post.post_id, slug: post.slug, order: i + 1, file: name });
      } catch (err) {
        failed++;
        console.log(`  FAIL ${f.id}  ${f.title}  -  ${err.message}`);
        report.push({ ...f, post_id: post.post_id, slug: post.slug, order: i + 1, error: err.message });
      }
    }
  }

  fs.writeFileSync(path.join(HERE, 'downloaded.json'), JSON.stringify(report, null, 2));
  await ctx.close();
  console.log(`\n${ok} downloaded, ${failed} failed, ${(bytes / 1048576).toFixed(1)} MB total`);
  console.log(`Into: ${outDir}`);
  console.log(`Next: python prepare_images.py --src "${outDir}"`);
}

const run = mode === 'login' ? login : mode === 'fetch' ? fetchAll : null;
if (!run) {
  console.log('usage: node slack_fetch.mjs login | fetch <outdir>');
  process.exit(1);
}
run().catch((e) => { console.error('\n' + e.message); process.exit(1); });
