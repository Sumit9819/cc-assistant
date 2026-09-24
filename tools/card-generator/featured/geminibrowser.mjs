/**
 * Drive the operator's own logged-in Gemini web chat to generate images.
 *
 *   node geminibrowser.mjs probe
 *   node geminibrowser.mjs login
 *   node geminibrowser.mjs gen prompts-v2.json
 *
 * WHY NOT THE API: the AI Studio keys have a daily free-tier image quota that is
 * spent in a handful of requests. The operator's Gemini subscription does not,
 * and it is the account they actually want these made on. So this drives the web
 * app the way a person would, in their own browser profile, rather than paying
 * per image through a second account.
 *
 * PROFILE: a COPY of ~/.gemini/antigravity-browser-profile, which is already a
 * Chrome profile kept for browser automation. Copied rather than used in place so
 * that a crash here cannot corrupt the profile another tool depends on, and so
 * Chrome's own single-instance lock is never contested. Chrome's cookie
 * encryption is per-user, not per-directory, so a copy on the same account still
 * decrypts.
 *
 * HEADLESS by default since 2026-09-08, for `gen` and `probe`. The earlier note
 * here claimed headless had to be avoided because "Google's bot detection treats
 * headless Chrome very differently" - which was a belief, never measured in this
 * file. It is measured now: see the probe screenshot, which is written every run
 * precisely so a claim about the session state has evidence behind it. `login`
 * stays headed, because a login that needs a human has to be visible to get one,
 * and `--headed` forces a window back for any command.
 *
 * A visible browser is not free: it steals focus, and six generations in one
 * session means six windows opening over whatever the operator is doing.
 */
import fs from 'node:fs';
import { enforceImagePolicy } from './imagepolicy.mjs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const SRC_PROFILE = 'C:/Users/sumit/.gemini/antigravity-browser-profile';
const PROFILE = path.join(HERE, '.chrome-profile');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = path.join(HERE, 'src', 'gen');
const SHOTS = path.join(HERE, 'src', '_browser');

function loadPlaywright() {
  for (const base of ['C:/Users/sumit/.cc-assistant/wcag/package.json',
                      'D:/faceless-studio/motion/package.json']) {
    if (!fs.existsSync(base)) continue;
    try { return createRequire(base)('playwright'); } catch { /* next */ }
  }
  throw new Error('No Playwright found');
}

function ensureProfile() {
  if (fs.existsSync(PROFILE)) return;
  if (!fs.existsSync(SRC_PROFILE)) {
    console.log(`No profile at ${SRC_PROFILE}; starting a blank one (you will have to log in).`);
    fs.mkdirSync(PROFILE, { recursive: true });
    return;
  }
  console.log('Copying the Chrome profile (about 400MB, once)...');
  // Singleton* are the lock files of the live profile; copying them makes Chrome
  // think another instance owns this directory.
  fs.cpSync(SRC_PROFILE, PROFILE, {
    recursive: true, force: true, errorOnExist: false,
    filter: (s) => !path.basename(s).startsWith('Singleton'),
  });
  console.log('Copied.');
}

async function open({ headless = false } = {}) {
  ensureProfile();
  fs.mkdirSync(SHOTS, { recursive: true });
  const { chromium } = loadPlaywright();
  const ctx = await chromium.launchPersistentContext(PROFILE, {
    headless,
    executablePath: fs.existsSync(CHROME) ? CHROME : undefined,
    viewport: { width: 1440, height: 900 },
    args: ['--disable-blink-features=AutomationControlled', '--start-maximized'],
  });
  const page = ctx.pages()[0] || await ctx.newPage();
  return { ctx, page };
}

/**
 * Is this page a logged-in Gemini chat, a login wall, or something else?
 *
 * The presence of the prompt box is NOT evidence of being signed in: Gemini
 * shows a working "Ask Gemini" input to anonymous visitors. An earlier version
 * of this function tested for the box, reported "ready" on a signed-out page,
 * and only the screenshot gave it away. So the test is now the SIGN-IN button:
 * if the page is offering to sign you in, you are not signed in.
 */
async function state(page) {
  const url = page.url();
  if (/accounts\.google\.com|ServiceLogin|signin/i.test(url)) return 'login-required';

  const signedOut = await page.evaluate(() => {
    const wanted = /^(sign in|log in)$/i;
    for (const el of document.querySelectorAll('a,button')) {
      if (wanted.test((el.textContent || '').trim())) return true;
    }
    return false;
  });
  if (signedOut) return 'login-required';

  const box = await page.$('rich-textarea div[contenteditable="true"], div[contenteditable="true"][role="textbox"]');
  return box ? 'ready' : 'unknown';
}

/**
 * Clear the promo cards Gemini opens with.
 *
 * Not cosmetic: the "Your business, organized with Gemini" panel overlays the
 * top-right of the app, and a stray overlay is the usual reason a click lands on
 * nothing and a script then waits four minutes for a reply that was never sent.
 */
async function dismissOverlays(page) {
  for (const label of ['Not now', 'No thanks', 'Dismiss', 'Got it']) {
    try {
      const b = page.getByRole('button', { name: label, exact: true }).first();
      if (await b.isVisible({ timeout: 900 })) {
        await b.click({ timeout: 2500 });
        await page.waitForTimeout(500);
      }
    } catch { /* not present, which is the normal case */ }
  }
  await page.keyboard.press('Escape').catch(() => {});
}

async function probe({ keepOpen = false } = {}) {
  const { ctx, page } = await open({ headless: !HEADED });
  await page.goto('https://gemini.google.com/app', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(6000);
  await dismissOverlays(page);
  const s = await state(page);
  const shot = path.join(SHOTS, 'probe.png');
  await page.screenshot({ path: shot });
  console.log(`state: ${s}`);
  console.log(`url:   ${page.url()}`);
  console.log(`shot:  ${shot}`);
  if (s === 'login-required') {
    console.log('\nThe stored session has expired. Run:  node geminibrowser.mjs login');
    console.log('That opens the same window and waits while you sign in; the session persists after.');
  }
  if (!keepOpen) await ctx.close();
  return s;
}

/** Open the window and wait for a human to finish signing in. */
async function login() {
  const { ctx, page } = await open({ headless: !HEADED });
  await page.goto('https://gemini.google.com/app', { waitUntil: 'domcontentloaded', timeout: 90000 });
  console.log('Sign in in the window that just opened. Waiting up to 5 minutes...');
  const deadline = Date.now() + 5 * 60 * 1000;
  while (Date.now() < deadline) {
    if (await state(page) === 'ready') {
      console.log('Signed in. The session is saved in this profile; gen can run unattended now.');
      await page.waitForTimeout(2500);
      await ctx.close();
      return true;
    }
    await page.waitForTimeout(3000);
  }
  console.log('Timed out waiting for sign-in.');
  await ctx.close();
  return false;
}

/**
 * Send one prompt and save the image it produces.
 *
 * The web app streams: the reply container appears long before the image inside
 * it does, so this waits for an <img> whose src is a real generated asset rather
 * than for the turn to "finish".
 */
async function generate(page, prompt, dest, ctx) {
  const box = await page.waitForSelector(
    'rich-textarea div[contenteditable="true"], div[contenteditable="true"][role="textbox"]',
    { timeout: 60000 });

  // Everything already on the page, so a NEW picture can be told from the promo
  // art and the avatar without knowing what class Gemini gives it this week.
  const seen = await page.evaluate(() =>
    [...document.images].map((i) => i.currentSrc || i.src));

  await box.click();
  await page.keyboard.insertText(prompt);
  await page.waitForTimeout(600);
  await page.keyboard.press('Enter');

  // Poll for a new, LARGE image rather than matching a selector.
  //
  // The first version waited on `img[src*="googleusercontent"]` and timed out
  // after four minutes on a turn that had in fact produced a perfect photograph -
  // the picture was simply served from a host the selector did not name. Asking
  // "is there a new image bigger than a UI icon" is a question about the thing we
  // actually want, so it survives Gemini renaming its DOM.
  const before = Date.now();
  const deadline = before + 240000;
  let src = null;
  while (Date.now() < deadline) {
    src = await page.evaluate((known) => {
      for (const i of document.images) {
        const u = i.currentSrc || i.src;
        if (!u || known.includes(u)) continue;
        if (i.naturalWidth >= 400 && i.naturalHeight >= 400) return u;
      }
      return null;
    }, seen);
    if (src) break;
    await page.waitForTimeout(2500);
  }
  if (!src) {
    throw new Error('no new image appeared within 240s');
  }
  await page.waitForTimeout(3000); // let the full-resolution asset swap in

  // Re-read: the thumbnail is often replaced by a larger asset moments later.
  const finalSrc = await page.evaluate((known) => {
    let best = null, area = 0;
    for (const i of document.images) {
      const u = i.currentSrc || i.src;
      if (!u || known.includes(u)) continue;
      const a = i.naturalWidth * i.naturalHeight;
      if (a > area) { area = a; best = u; }
    }
    return best;
  }, seen) || src;

  // The element's natural size is the blob's TRUE resolution, and the number
  // that decides whether this route can feed a 1200x630 frame at all. The
  // screenshot fallback captures display pixels, which is a different and
  // smaller number - conflating the two is how a 708px file looked like a
  // resolution ceiling that it may not be.
  const nat = await page.evaluate((u) => {
    for (const i of document.images) {
      if ((i.currentSrc || i.src) === u) return [i.naturalWidth, i.naturalHeight,
                                                 Math.round(i.getBoundingClientRect().width),
                                                 Math.round(i.getBoundingClientRect().height)];
    }
    return null;
  }, finalSrc);
  console.log(`      src: ${finalSrc.slice(0, 60)}`);
  if (nat) console.log(`      natural ${nat[0]}x${nat[1]}, displayed ${nat[2]}x${nat[3]}`);

  // Diagnostic: what controls does the turn actually offer? The download path
  // has to be discovered rather than assumed, and the accessible names are the
  // only stable handle on a DOM this obfuscated.
  if (process.env.GB_DEBUG) {
    const names = await page.evaluate(() => {
      const out = [];
      for (const b of document.querySelectorAll('button,[role="button"],a[download]')) {
        const n = (b.getAttribute('aria-label') || b.getAttribute('title') ||
                   (b.textContent || '').trim()).slice(0, 44);
        if (n) out.push(n);
      }
      return [...new Set(out)];
    });
    console.log('      controls: ' + names.join(' | '));
  }

  // Ask for the ORIGINAL, not the thumbnail.
  //
  // The <img> in the chat carries a size-capped Google asset URL, so the first
  // working download produced a 708x387 file - the size it happened to be
  // displayed at, which is useless for a 1200x630 featured image. Google's asset
  // hosts accept a size directive in the path, so the cap is rewritten upward and
  // the original URL kept as a fallback.
  const candidates = [];
  if (/=[swh]\d+/.test(finalSrc) || /=[a-z0-9-]*$/.test(finalSrc)) {
    candidates.push(finalSrc.replace(/=[^=/]*$/, '=s2048'));
  }
  candidates.push(finalSrc.includes('=') ? finalSrc.replace(/=[^=/]*$/, '=s0') : finalSrc + '=s0');
  candidates.push(finalSrc);

  // Download through the BROWSER CONTEXT, not the page.
  //
  // A page-side fetch() of the asset fails with "Failed to fetch": the image is
  // served cross-origin without CORS headers, so the document is allowed to
  // DISPLAY it but not to read its bytes. context.request is not a page request,
  // carries the same cookies, and is not subject to CORS at all.
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  let ok = false;

  // A blob: URL belongs to THIS document, so a page-side fetch of it is
  // same-origin and allowed - and it is the only way to read one, since
  // context.request has no idea what an in-page blob is. The https case is the
  // opposite: cross-origin without CORS, so it must go through the context.
  // Getting these two the wrong way round is what produced a 708x387 file - the
  // size the picture happened to be displayed at.
  if (finalSrc.startsWith('blob:')) {
    try {
      const b64 = await page.evaluate(async (u) => {
        const r = await fetch(u);
        const buf = new Uint8Array(await r.arrayBuffer());
        let out = '';
        const CH = 0x8000;
        for (let i = 0; i < buf.length; i += CH) {
          out += String.fromCharCode.apply(null, buf.subarray(i, i + CH));
        }
        return btoa(out);
      }, finalSrc);
      const body = Buffer.from(b64, 'base64');
      if (body.length > 4096) { fs.writeFileSync(dest, body); ok = true; }
    } catch (e) {
      console.log(`      blob read failed (${e.message.slice(0, 60)})`);
    }
  }

  if (!ok && ctx && /^https?:/.test(finalSrc)) {
    for (const u of candidates) {
      try {
        const r = await ctx.request.get(u, { timeout: 90000 });
        if (!r.ok()) continue;
        const body = await r.body();
        if (body.length < 4096) continue;         // an error page, not a picture
        fs.writeFileSync(dest, body);
        ok = true;
        break;
      } catch { /* try the next size */ }
    }
    if (!ok) console.log('      every asset URL failed; falling back to a screenshot');
  }
  if (!ok) {
    // Photograph the element - but at its NATURAL size, not its layout size.
    //
    // A plain element screenshot captures display pixels: 708x386 for a blob
    // that is actually 1024x559, throwing away a third of the linear resolution
    // for nothing. So the image is temporarily pinned to the top-left at its own
    // natural dimensions, with the viewport grown to match, and photographed
    // there. Nothing is upscaled - this just stops the capture from downscaling.
    if (!nat) throw new Error('could not locate the image element to screenshot');
    const [nw, nh] = nat;
    await page.setViewportSize({ width: Math.min(nw + 40, 2400), height: Math.min(nh + 40, 2400) });

    // Isolate the picture before photographing it.
    //
    // Pinning the original element and raising its z-index is not enough:
    // z-index only orders siblings within one stacking context, so Gemini's
    // sidebar kept painting straight over the top-left corner and the first
    // batch came out with a navigation menu across the model's face. Hiding
    // every other top-level child and appending a fresh copy of the image
    // sidesteps stacking entirely - there is nothing left to paint over it.
    const handle = await page.evaluateHandle(({ u, w, h }) => {
      let src = null;
      for (const i of document.images) {
        if ((i.currentSrc || i.src) === u) { src = i; break; }
      }
      if (!src) return null;
      for (const child of Array.from(document.body.children)) {
        child.setAttribute('data-gb-hidden', '1');
        child.style.visibility = 'hidden';
      }
      document.documentElement.style.background = '#ffffff';
      document.body.style.background = '#ffffff';
      const clone = src.cloneNode(true);
      clone.id = 'gb-capture';
      clone.style.cssText =
        `position:fixed;left:0;top:0;margin:0;padding:0;border:0;border-radius:0;` +
        `width:${w}px;height:${h}px;max-width:none;max-height:none;` +
        `object-fit:fill;visibility:visible;z-index:2147483647`;
      document.body.appendChild(clone);
      return clone;
    }, { u: finalSrc, w: nw, h: nh });

    const asEl = handle.asElement();
    if (!asEl) throw new Error('could not locate the image element to screenshot');
    await page.waitForTimeout(500);
    await asEl.screenshot({ path: dest });

    // Put the page back, or the next turn in this tab is invisible to the poll.
    await page.evaluate(() => {
      document.getElementById('gb-capture')?.remove();
      for (const c of document.querySelectorAll('[data-gb-hidden]')) {
        c.style.visibility = '';
        c.removeAttribute('data-gb-hidden');
      }
    });
  }

  const kb = (fs.statSync(dest).size / 1024).toFixed(0);
  console.log(`      ${((Date.now() - before) / 1000).toFixed(0)}s, ${kb}KB -> ${path.basename(dest)}`);
}

async function runSpec(specFile) {
  const spec = JSON.parse(fs.readFileSync(path.join(HERE, specFile), 'utf8'));

  // Before Chrome, not after: a brief that describes a stigmatizing shot is
  // cheaper to refuse than to generate, look at, and throw away.
  for (const item of spec.images) {
    enforceImagePolicy(item.prompt, `${item.name} prompt`, item.brand || spec.brand || 'iwc');
  }

  const { ctx, page } = await open({ headless: !HEADED });
  await page.goto('https://gemini.google.com/app', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(5000);
  await dismissOverlays(page);

  if (await state(page) !== 'ready') {
    await ctx.close();
    console.log('Not signed in. Run:  node geminibrowser.mjs login');
    process.exit(2);
  }

  fs.mkdirSync(OUT, { recursive: true });
  let made = 0;
  for (const [n, item] of spec.images.entries()) {
    const dest = path.join(OUT, `${item.name}.png`);
    if (fs.existsSync(dest)) { console.log(`[${n + 1}] ${item.name}: exists`); continue; }
    console.log(`[${n + 1}/${spec.images.length}] ${item.name}`);
    try {
      await generate(page, buildPrompt(item), dest, ctx);
      made++;
    } catch (e) {
      console.log(`      FAILED: ${e.message.split('\n')[0]}`);
      await page.screenshot({ path: path.join(SHOTS, `fail-${item.name}.png`) });
    }
    // A fresh chat per image: a long thread makes the model reference the
    // previous picture, which is how a set ends up with five variations of one
    // photograph instead of five different ones.
    await page.goto('https://gemini.google.com/app', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(4000);
    await dismissOverlays(page);
  }
  await ctx.close();
  console.log(`\n${made} generated into src/gen/`);
}

const HOUSE = (
  'Editorial photograph, 50mm lens at f/2.8. Soft diffused daylight from the left, ' +
  'gentle natural shadows. Muted natural colour, calm and clinical rather than glossy. ' +
  'No text, no lettering, no watermarks, no logos, no borders, no collage.'
);

function buildPrompt(item) {
  const bits = [`Generate a photograph. ${item.prompt.trim().replace(/\.$/, '')}.`];
  if (item.ground) {
    bits.push(`The background is a completely plain seamless evenly lit studio backdrop ` +
              `in the exact colour ${item.ground}, with no gradient, no vignette, no floor ` +
              `line and no props behind the subject.`);
  }
  bits.push('CRITICAL FRAMING: the entire subject is inside the frame with generous empty ' +
            'space on all four sides. The top of the head, both shoulders and both arms are ' +
            'fully visible and well clear of every edge. Nothing is cropped by the frame.');
  bits.push(HOUSE);
  return bits.join(' ');
}

async function one(promptText) {
  const { ctx, page } = await open({ headless: HEADLESS });
  await page.goto('https://gemini.google.com/app', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(5000);
  await dismissOverlays(page);
  if (await state(page) !== 'ready') { await ctx.close(); console.log('not signed in'); process.exit(2); }
  const dest = path.join(OUT, '_one.png');
  try { await generate(page, promptText, dest, ctx); }
  catch (e) {
    console.log('FAILED:', e.message.split(String.fromCharCode(10))[0]);
    await page.screenshot({ path: path.join(SHOTS, 'fail-one.png'), fullPage: false });
    console.log('shot:', path.join(SHOTS, 'fail-one.png'));
  }
  await ctx.close();
}

const argv = process.argv.slice(2);
// --headed / --headless anywhere in the arguments; the default depends on the
// command, since only `login` genuinely needs a person looking at it.
const HEADED = argv.includes('--headed');
const HEADLESS = argv.includes('--headless');
const [cmd, arg] = argv.filter((a) => !a.startsWith('--'));
if (cmd === 'probe') await probe();
else if (cmd === 'one') await one(arg || 'Generate a photograph of a single green eucalyptus sprig lying on a plain seamless light grey studio background, soft daylight, no text.');
else if (cmd === 'login') await login();
else if (cmd === 'gen') await runSpec(arg || 'prompts-v2.json');
else {
  console.log('usage: node geminibrowser.mjs <probe|login|gen [spec.json]> [--headed|--headless]');
  console.log('       probe and gen run headless by default; login runs visible.');
  process.exit(1);
}
