/**
 * Render every card in one or more spec files to PNG.
 *
 * usage: node make.mjs spec.json spec2.json
 *
 * Width is always 1200px. Height starts at the 630px floor and GROWS to fit
 * the content: the frame is sized around the card, not the card squeezed into
 * the frame. A spec may also name its own `height`, which is treated as a
 * floor, never a ceiling. See the geometry note at the top of layouts.mjs.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';
import { render, setHeight, frameHeight, MIN_H, FRAME_W } from './layouts.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(HERE, 'out');

function loadPlaywright() {
  const candidates = [
    'C:/Users/sumit/.cc-assistant/wcag/package.json',
    'D:/faceless-studio/motion/package.json',
  ];
  const tried = [];
  for (const base of candidates) {
    if (!fs.existsSync(base)) { tried.push(`${base} (missing)`); continue; }
    try { return createRequire(base)('playwright'); }
    catch (e) { tried.push(`${base} (${e.code || e.message})`); }
  }
  throw new Error('No Playwright found:\n  ' + tried.join('\n  '));
}

const specs = process.argv.slice(2);
if (!specs.length) { console.log('usage: node make.mjs <spec.json> [more.json]'); process.exit(1); }

const cards = specs.flatMap((s) =>
  JSON.parse(fs.readFileSync(path.resolve(HERE, s), 'utf8')).cards
);

// The guard that makes this pipeline worth having: the "Energy boost printed
// twice" class of defect cannot reach a render. Layouts carry their repeatable
// text under different keys and in different shapes - `numbered` uses bare
// strings, the rest use objects - so normalise before comparing.
const repeatables = (c) => [
  ...(c.items || []),
  ...(c.steps || []),
  ...(c.nodes || []),
  ...(c.signs || []),
  ...(c.good?.items || []),
  ...(c.bad?.items || []),
].map((v) => (typeof v === 'string' ? v : v.label ?? v.text ?? '')).filter(Boolean);

for (const c of cards) {
  const labels = repeatables(c).map((s) => s.toLowerCase());
  const dupe = labels.find((l, i) => labels.indexOf(l) !== i);
  if (dupe) throw new Error(`${c.file}: duplicate label "${dupe}"`);
}
// The yellow highlight is the middle part of a three-part title. If a part
// boundary lands flush against a letter it splits a word, and the card ships
// reading "Hormone" in yellow with a dark "s" hanging off it.
for (const c of cards) {
  if (!Array.isArray(c.title) || c.title.length !== 3) continue;
  const [before, mark, after] = c.title.map((x) => String(x ?? ''));
  const splitsBefore = before && /[A-Za-z0-9]$/.test(before) && /^[A-Za-z0-9]/.test(mark);
  const splitsAfter = mark && /[A-Za-z0-9]$/.test(mark) && /^[A-Za-z0-9]/.test(after);
  if (splitsBefore || splitsAfter) {
    throw new Error(
      `${c.file}: the highlight breaks a word - ${JSON.stringify(c.title)}. ` +
      `Move the whole word into the middle part.`
    );
  }
}

const files = cards.map((c) => c.file);
const dupeFile = files.find((f, i) => files.indexOf(f) !== i);
if (dupeFile) throw new Error(`two cards both named "${dupeFile}" - one would overwrite the other`);

// A card is a STANDALONE asset. It appears in Google Images, in a share, in a
// lightbox, with none of the surrounding article. "How Many Sessions?" over
// "6 to 9" tells a stranger nothing: sessions of what, and 6 to 9 what.
//
// Word-overlap with the slug is not enough to catch this - "How Many Sessions?"
// shares "sessions" with its own filename and still fails to say laser hair
// removal. So each card declares its `subject` explicitly and the visible text
// must contain it. Explicit beats clever here; it forces the decision at
// authoring time instead of hoping a heuristic notices.
for (const c of cards) {
  if (!c.subject) throw new Error(`${c.file}: missing "subject" - name what the card is about`);
  const visible = [(c.title || []).join(' '), c.statLine, c.gloss, c.headline, c.kicker]
    .filter(Boolean).join(' ').toLowerCase();
  if (!visible.includes(c.subject.toLowerCase())) {
    throw new Error(
      `${c.file}: subject "${c.subject}" does not appear in the visible text ` +
      `("${(c.title || []).join('')}"). A reader seeing this image alone would not know what it is about.`
    );
  }
}

fs.mkdirSync(OUT, { recursive: true });
const { chromium } = loadPlaywright();
const browser = await chromium.launch();

/**
 * Fit the title, then keep the content clear of it.
 *
 * Titles are absolutely positioned at a fixed top, and so is the content
 * beneath them, so a title long enough to wrap lands on top of the first row.
 * Rather than police title length by hand, measure and adapt: shrink the type
 * until it fits one line, and if it still wraps at the floor, push the content
 * block down so nothing is ever overlapped.
 *
 * Called once per sizing pass, because a re-render resets the inline styles it
 * applies.
 */
async function fitTitle(page) {
  return page.evaluate(() => {
    const h1 = document.querySelector('h1');
    if (!h1) return null;
    let size = parseFloat(getComputedStyle(h1).fontSize);
    const wraps = () => h1.getBoundingClientRect().height > size * 1.45;
    // Floor at 36px. Lower than that and titles vary so much card to card that
    // the set stops looking like one system; past the floor it is better to let
    // the title take two lines and move the content down.
    let shrunk = 0;
    while (wraps() && size > 36) { size -= 2; shrunk += 2; h1.style.fontSize = `${size}px`; }

    let pushed = 0;
    const content = [...document.body.children].find(
      (el) => el !== h1 && !el.classList.contains('blob') && !el.classList.contains('logo')
        && getComputedStyle(el).position === 'absolute'
    );
    if (content) {
      const gap = 22;
      const bottom = h1.getBoundingClientRect().bottom;
      const r = content.getBoundingClientRect();
      if (r.top < bottom + gap) {
        pushed = Math.ceil(bottom + gap - r.top);
        content.style.top = `${r.top + pushed}px`;
      }
    }
    return { shrunk, pushed, final: size };
  });
}

const sizes = {};

for (const card of cards) {
  const htmlPath = path.join(OUT, `${card.file}.html`);

  const page = await browser.newPage({
    viewport: { width: FRAME_W, height: MIN_H },
    deviceScaleFactor: 2, // render 2x then downscale: keeps type and hairlines crisp
  });

  // ---------------------------------------------------------------- geometry
  //
  // Render at the floor, measure what the content actually needs, and if it
  // needs more room, re-render taller. Nothing is cropped and nothing is
  // shrunk to fit: a six-row comparison simply becomes a taller card.
  //
  // Two passes settle every top-anchored layout, because their content is
  // positioned from the top and does not move when the frame grows. The two
  // centred layouts (quote, alert) DO re-centre, so for those the measurement
  // sizes from the content's own extent rather than its current bottom edge,
  // which also converges in one growth.
  let H = setHeight(card.height || MIN_H);
  let fit = null;

  for (let pass = 0; pass < 4; pass++) {
    const html = render(card);
    fs.writeFileSync(htmlPath, html);
    await page.setViewportSize({ width: FRAME_W, height: H });
    // setContent rather than goto: every subresource is already inline (icons
    // are read from disk into the markup, the logo is a data URI) except the
    // remote font stylesheet, so there is nothing to resolve relatively, and
    // re-rendering the same path four times would otherwise fight the cache.
    await page.setContent(html, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(350);

    fit = await fitTitle(page);

    const need = await page.evaluate((frameH) => {
      const logo = document.querySelector('.logo');
      let top = Infinity;
      let bottom = -Infinity;
      for (const el of document.querySelectorAll('body *')) {
        if (el.classList.contains('blob') || el === logo) continue;
        const r = el.getBoundingClientRect();
        if (!r.width && !r.height) continue;
        top = Math.min(top, r.top);
        bottom = Math.max(bottom, r.bottom);
      }
      if (bottom === -Infinity) return null;

      // What the bottom of the frame owes the content. With a bottom-anchored
      // logo that is the mark plus the same 22px keep-out the clash check
      // enforces; otherwise a plain margin on the 48-64px layout step.
      let reserve = 56;
      if (logo) {
        const L = logo.getBoundingClientRect();
        if (L.top > frameH / 2) reserve = Math.ceil(frameH - L.top) + 22;
      }

      // A content root as tall as the frame is centred in it, so growing the
      // frame pushes the content down by half the growth. Size from the
      // content's extent instead, and reserve at both ends.
      const centred = [...document.body.children].some(
        (el) => el !== logo && !el.classList.contains('blob')
          && Math.abs(el.getBoundingClientRect().height - frameH) < 2
      );
      return centred
        ? { needed: Math.ceil(bottom - top) + reserve * 2, reserve }
        : { needed: Math.ceil(bottom) + reserve, reserve };
    }, H);

    if (need && need.needed > H) {
      H = setHeight(need.needed);
      continue;
    }
    break;
  }
  sizes[card.file] = H;
  if (H > MIN_H) console.log(`  + ${card.file}: frame grown to ${FRAME_W}x${H}`);

  if (fit && (fit.shrunk || fit.pushed)) {
    const bits = [];
    if (fit.shrunk) bits.push(`title shrunk ${fit.shrunk}px to ${fit.final}px`);
    if (fit.pushed) bits.push(`content pushed down ${fit.pushed}px`);
    console.log(`  ~ ${card.file}: ${bits.join(', ')}`);
  }

  // A JS stringification artefact reaching the canvas is invisible to every
  // other check here: it is not overflow, not a collision, and the layout is
  // perfectly valid. "undefined" shipped on 25 live cards because a two-part
  // title hit esc(title[2]). Read the rendered text and refuse the literals.
  const junk = await page.evaluate(() => {
    const text = document.body.innerText || '';
    return ['undefined', 'null', 'NaN', '[object Object]']
      .filter((needle) => text.includes(needle));
  });
  if (junk.length) {
    throw new Error(
      `${card.file}: rendered text contains ${junk.join(', ')} - a value ` +
      `stringified into the canvas. Check the spec for a missing field ` +
      `(a title array must have exactly three parts: before, highlight, after).`
    );
  }

  // Catch content that silently overflows the frame instead of shipping a card
  // with its last row cut off. Measured per element and excluding .blob: the
  // background shapes are positioned outside the frame on purpose, and counting
  // them made this fire on every card, which is the same as not checking at all.
  const over = await page.evaluate((frame) => {
    let worst = 0;
    for (const el of document.querySelectorAll('body *')) {
      if (el.classList.contains('blob')) continue;
      const r = el.getBoundingClientRect();
      if (!r.width && !r.height) continue;
      worst = Math.max(worst, Math.ceil(r.bottom - frame.h), Math.ceil(r.right - frame.w), Math.ceil(-r.top), Math.ceil(-r.left));
    }
    return worst;
  }, { w: FRAME_W, h: H });
  if (over > 2) console.log(`  ! ${card.file}: content outside frame by ${over}px`);

  // The logo needs clear space around it, not just a corner to sit in. On the
  // B12 stat card the dot grid came within 2px of the mark, which looked like a
  // collision. Enforce a keep-out rectangle so this is caught at build time
  // rather than by spotting it in a finished image.
  const clash = await page.evaluate((pad) => {
    const logo = document.querySelector('.logo');
    if (!logo) return [];
    const L = logo.getBoundingClientRect();
    const zone = { l: L.left - pad, t: L.top - pad, r: L.right + pad, b: L.bottom + pad };
    const hits = [];
    for (const el of document.querySelectorAll('body *')) {
      if (el === logo || el.classList.contains('blob')) continue;
      // Only leaf-ish nodes: an <svg> is atomic, and anything with element
      // children would just report its wrapper instead of the real offender.
      const isLeaf = el.tagName.toLowerCase() === 'svg' || el.children.length === 0;
      if (!isLeaf) continue;
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) continue;
      if (r.right > zone.l && r.left < zone.r && r.bottom > zone.t && r.top < zone.b) {
        const gapY = Math.round(L.top - r.bottom);
        hits.push(`${el.className || el.tagName} (${gapY}px above logo)`);
      }
    }
    return hits;
  }, 22);
  if (clash.length) {
    console.log(`  ! ${card.file}: ${clash.length} element(s) inside the logo keep-out zone`);
    for (const h of clash.slice(0, 3)) console.log(`      ${h}`);
  }

  await page.screenshot({ path: path.join(OUT, `${card.file}.png`), clip: { x: 0, y: 0, width: FRAME_W, height: H } });
  await page.close();
  console.log(`  ${(card.layout || 'icons').padEnd(9)} ${String(FRAME_W + 'x' + H).padEnd(9)} ${card.file}`);
}

await browser.close();

// Height is now a property of each card rather than of the pipeline, so say
// what shipped. towebp.py and mkpatch-erof.py both read the real pixel size off
// the rendered file, so nothing downstream has to trust this line.
const tall = Object.entries(sizes).filter(([, h]) => h > MIN_H);
console.log();
console.log(`${cards.length} cards -> ${OUT}`);
console.log(`${cards.length - tall.length} at the ${FRAME_W}x${MIN_H} floor` +
  (tall.length ? `, ${tall.length} grown: ` + tall.map(([f, h]) => `${f} ${h}px`).join(', ') : ''));
