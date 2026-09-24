/**
 * Render every entry in one or more featured specs to PNG at 1200x630.
 *
 *   node makefeatured.mjs spec-featured.json
 *
 * Structure follows make.mjs deliberately - same Playwright resolution, same
 * "measure the rendered DOM rather than trust the layout" stance - but the
 * guards differ because the failure modes do. A card fails by overflowing; a
 * featured image fails by being unreadable in the blog listing at 400px wide,
 * which no amount of looking at it full size will reveal.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';
import { render, W, H, LAYOUTS } from './featured.mjs';
import { enforceImagePolicy } from './imagepolicy.mjs';
import { CIRCLE, innerDiameter, inscribeSize, INSCRIBE_PAD, circleCoverage, maskFromRGBA, visibleRegion, parseFocus } from './circle.mjs';
import { frame as frameStyle } from './frame-styles.mjs';

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

const specFiles = process.argv.slice(2);
if (!specFiles.length) {
  console.log('usage: node makefeatured.mjs <spec-featured.json> [more.json]');
  console.log(`layouts: ${LAYOUTS.join(', ')}`);
  process.exit(1);
}

const items = specFiles.flatMap((s) =>
  JSON.parse(fs.readFileSync(path.resolve(HERE, s), 'utf8')).featured
);

// ---------------------------------------------------------------- pre-flight

const seen = items.map((i) => i.file);
const dupe = seen.find((f, i) => seen.indexOf(f) !== i);
if (dupe) throw new Error(`two entries both named "${dupe}" - one would overwrite the other`);

for (const it of items) {
  if (!it.file) throw new Error(`an entry has no "file"`);
  if (!it.title) throw new Error(`${it.file}: no title`);
  if (!it.alt) throw new Error(`${it.file}: no "alt" - a featured image is the OG card and the listing thumbnail, so it always needs one`);
  if (!it.post_id) throw new Error(`${it.file}: no post_id - a featured image with no post to attach to is a stray upload`);

  // Same reasoning as the cards' subject check: this asset appears in a share,
  // a feed and Google Images with none of the article around it. The three-part
  // title splitting a word is the other defect that survives every other check.
  if (Array.isArray(it.title)) {
    if (it.title.length !== 3) {
      throw new Error(`${it.file}: an array title must have exactly three parts (before, highlight, after) - got ${it.title.length}`);
    }
    const [b, m, a] = it.title.map((x) => String(x ?? ''));
    if ((b && /[A-Za-z0-9]$/.test(b) && /^[A-Za-z0-9]/.test(m)) ||
        (m && /[A-Za-z0-9]$/.test(m) && /^[A-Za-z0-9]/.test(a))) {
      throw new Error(`${it.file}: the highlight breaks a word - ${JSON.stringify(it.title)}. Move the whole word into the middle part.`);
    }
  }
  // The depiction gate. Alt text is the only description of the picture that
  // every entry is required to carry, which makes it the one chokepoint a photo
  // from any source has to pass. See imagepolicy.mjs for what it cannot prove.
  enforceImagePolicy(it.alt, `${it.file} alt`, it.brand || 'iwc');

  if ((it.layout || '').startsWith('photo') && !it.photo) {
    throw new Error(`${it.file}: layout "${it.layout}" needs a "photo"`);
  }
}

fs.mkdirSync(OUT, { recursive: true });
const { chromium } = loadPlaywright();
const browser = await chromium.launch();
const report = [];

for (const item of items) {
  const htmlPath = path.join(OUT, `${item.file}.html`);
  fs.writeFileSync(htmlPath, render(item));

  const page = await browser.newPage({
    viewport: { width: W, height: H },
    deviceScaleFactor: 2, // render 2x then downscale: keeps type and hairlines crisp
  });
  await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'load' });

  // document.fonts.ready resolves before lazily-registered faces have actually
  // loaded, so measuring here without forcing each weight measures the fallback
  // font - which is a different width and silently changes every fit decision
  // below. Load the exact faces this page uses, then wait again.
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all([
      document.fonts.load('700 76px Poppins'),
      document.fonts.load('600 21px Poppins'),
      document.fonts.load('500 25px Poppins'),
    ]);
    await document.fonts.ready;
  });
  await page.waitForTimeout(250);

  // Headlines are set at one size for the whole set, so a long one overflows its
  // column instead of wrapping neatly. Shrink to fit rather than policing title
  // length by hand - but stop at 56px, which is the floor where the type is
  // still legible once the listing scales it to 400px wide.
  const fit = await page.evaluate(() => {
    const h1 = document.querySelector('h1');
    const wrap = h1?.closest('.wrap');
    if (!h1 || !wrap) return null;
    let size = parseFloat(getComputedStyle(h1).fontSize);
    const fits = () => wrap.scrollHeight <= wrap.clientHeight;
    let shrunk = 0;
    while (!fits() && size > 56) { size -= 2; shrunk += 2; h1.style.fontSize = `${size}px`; }
    return { shrunk, final: size, overflows: !fits() };
  });
  if (fit?.shrunk) console.log(`  ~ ${item.file}: headline shrunk ${fit.shrunk}px to ${fit.final}px`);
  if (fit?.overflows) {
    throw new Error(
      `${item.file}: the headline still does not fit at the ${fit.final}px floor. ` +
      `Shorten it - a featured image that needs this many words is not one a ` +
      `reader can take in from a listing card.`
    );
  }

  // A highlight that wraps at a hyphen. "ER X-Ray" broke to "ER X-" / "Ray" with
  // two disjoint underlines under it, which looks like a rendering fault. The
  // spec-level check next door only knows whether the three title parts split a
  // word; where the LINE broke is something only the browser knows, so it is
  // measured here from the em's own client rects.
  const split = await page.evaluate(() => {
    const em = document.querySelector('h1 em');
    if (!em) return null;
    const text = em.textContent || '';
    // The underline and the marker slab are both background gradients, and a
    // background paints once per line box - so a decorated em that wraps draws
    // one stripe per fragment. Undecorated em (accent-coloured text, which is
    // what the light grounds use) may wrap without leaving a mark.
    return {
      text,
      rects: em.getClientRects().length,
      painted: getComputedStyle(em).backgroundImage !== 'none',
      hyphen: /[-\u2011\u2013]/.test(text),
    };
  });
  if (split && split.painted && split.rects > 1) {
    throw new Error(
      `${item.file}: the highlighted phrase "${split.text}" wraps across ` +
      `${split.rects} lines, and it is drawn with an underline, so it ships as ` +
      `${split.rects} disjoint stripes` +
      (split.hyphen ? ' broken mid-word at its hyphen' : ' broken at its space') +
      `. Shorten the text before the highlight, or highlight a phrase short ` +
      `enough to hold one line.`
    );
  }

  // A stringification artefact reaching the canvas is invisible to every other
  // check: it is not overflow, not a collision, and the layout is perfectly
  // valid. This shipped on 25 in-body cards before the cards grew the same check.
  const junk = await page.evaluate(() => {
    const t = document.body.innerText || '';
    return ['undefined', 'null', 'NaN', '[object Object]'].filter((n) => t.includes(n));
  });
  if (junk.length) {
    throw new Error(`${item.file}: rendered text contains ${junk.join(', ')} - a value stringified into the canvas. Check the spec for a missing field.`);
  }

  // The photo has to actually have decoded. A data URI that failed to parse
  // leaves a zero-size <img> and the composite ships as a headline on an empty
  // panel, which reads as a deliberate design choice rather than as a bug.
  //
  // It also reports how much an object-fit:cover panel throws away. A tight
  // face photograph cover-cropped into a tall panel is exactly how a head ends
  // up sliced off at the forehead, and the number says so before anyone looks.
  let circle = null;
  const photos = await page.evaluate(() =>
    [...document.querySelectorAll('img:not(.logo)')].map((i) => {
      const r = i.getBoundingClientRect();
      const fit = getComputedStyle(i).objectFit;
      let cropY = 0, cropX = 0;
      if (fit === 'cover' && i.naturalWidth && r.width) {
        const scale = Math.max(r.width / i.naturalWidth, r.height / i.naturalHeight);
        cropY = Math.max(0, 1 - r.height / (i.naturalHeight * scale));
        cropX = Math.max(0, 1 - r.width / (i.naturalWidth * scale));
      }
      return { w: i.naturalWidth, h: i.naturalHeight, fit, cropY, cropX };
    }));
  for (const p of photos) {
    if (!p.w || !p.h) throw new Error(`${item.file}: a photo failed to decode (naturalWidth 0).`);
    if (p.cropY > 0.18) {
      console.log(`  ! ${item.file}: the panel crop discards ${(p.cropY * 100).toFixed(0)}% ` +
                  `of the photo's height. If the subject is a face, check the top of the head ` +
                  `survived - set "focus" to move the crop.`);
    }
  }

  // Did the subject survive the circular crop? The circle discards the corners
  // of its box, so a focus that is slightly out puts the edge of an object -
  // or all of it - outside the frame, and the result looks deliberate.
  if ((item.layout || '').startsWith('photo-frame') && item.photo_fit === 'inscribe') {
    // The CSS could only reserve the square that fits any aspect ratio. Now that
    // the image has loaded and its real shape is known, grow it to the largest
    // rectangle of THAT shape which fits the circle - a third more area on a
    // typical film - and re-centre it.
    const grew = await page.evaluate(({ inner, pad }) => {
      const img = document.querySelector('#fr .f img');
      if (!img || !img.naturalWidth) return null;
      const k = (inner * pad) / Math.hypot(img.naturalWidth, img.naturalHeight);
      const w = img.naturalWidth * k;
      const h = img.naturalHeight * k;
      img.style.width = `${w}px`;
      img.style.height = `${h}px`;
      img.style.margin = `${(inner - h) / 2}px ${(inner - w) / 2}px`;
      return { w: Math.round(w), h: Math.round(h) };
    }, { inner: innerDiameter(), pad: INSCRIBE_PAD });
    if (grew) {
      // Same maths in Node, as the check that the browser did what was intended.
      const want = inscribeSize({ nw: photos[0]?.w || 0, nh: photos[0]?.h || 0 });
      if (Math.abs(want.w - grew.w) > 1.5 || Math.abs(want.h - grew.h) > 1.5) {
        throw new Error(
          `${item.file}: inscribed size disagrees - page says ${grew.w}x${grew.h}, ` +
          `circle.mjs says ${Math.round(want.w)}x${Math.round(want.h)}.`);
      }
      console.log(`    ${item.file}: inscribed whole at ${grew.w}x${grew.h} in a ${innerDiameter()}px circle`);
    }
    // Nothing is cropped in this mode, so there is nothing to measure; recording
    // it keeps the report honest about which cards were checked and which did
    // not need to be.
    circle = { subject: 'inscribed', share: 1, focus: null, visible: null };
  } else if ((item.layout || '').startsWith('photo-frame')) {
    const rgba = await page.evaluate(() => {
      const img = document.querySelector('#fr .f img');
      if (!img || !img.naturalWidth) return null;
      const c = document.createElement('canvas');
      c.width = img.naturalWidth;
      c.height = img.naturalHeight;
      const ctx = c.getContext('2d');
      ctx.drawImage(img, 0, 0);
      try {
        // Array.from because the structured clone of a Uint8ClampedArray across
        // the bridge is not one; a plain array is slower and always arrives.
        return { w: c.width, h: c.height, data: Array.from(ctx.getImageData(0, 0, c.width, c.height).data) };
      } catch (e) {
        return { error: `${e.name}: ${e.message}` };
      }
    });

    if (!rgba || rgba.error) {
      console.log(`  ! ${item.file}: could not read the photo's pixels back ` +
                  `(${rgba ? rgba.error : 'no image'}), so the circle crop is UNCHECKED.`);
    } else {
      const mask = maskFromRGBA(rgba);
      const inner = innerDiameter();
      const shape = frameStyle(item.frame || 'b').shape;
      const cov = circleCoverage({ mask, bw: inner, bh: inner, focus: item.focus || 'center', shape });
      const region = visibleRegion({ nw: rgba.w, nh: rgba.h, bw: inner, bh: inner, ...parseFocus(item.focus || 'center') });
      circle = {
        subject: mask.kind,
        shape,
        share: Number(cov.share.toFixed(3)),
        focus: item.focus || 'center',
        visible: { sx: Math.round(region.sx), sy: Math.round(region.sy), sw: Math.round(region.sw), sh: Math.round(region.sh) },
      };
      const floor = item.circle_floor ?? 0.9;

      // Only an ALPHA mask earns a refusal. There the subject is known exactly,
      // so a low share is a fact. A colour mask is a proxy - "everything that is
      // not the backdrop" - and on a scene it counts context as subject: the
      // copperhead shot measured 74% because the leaf litter it lies on spreads
      // wider than the circle, while the snake sits dead centre and whole.
      // Refusing on that would be asserting knowledge this check does not have,
      // so it reports the number and leaves the judgement to the contact sheet.
      if (cov.share < floor && mask.kind !== 'alpha') {
        console.log(`  ! ${item.file}: the ${shape} frame keeps ${(cov.share * 100).toFixed(0)}% of ` +
                    `what differs from this photo's backdrop (floor ${(floor * 100).toFixed(0)}%). ` +
                    `On a scene that is usually context being cropped, not the subject - ` +
                    `check it on the listing sheet, and set "focus" if the subject is off centre.`);
      } else if (cov.share < floor) {
        throw new Error(
          `${item.file}: the circular frame keeps only ${(cov.share * 100).toFixed(0)}% of the ` +
          `subject (floor ${(floor * 100).toFixed(0)}%), measured from the photo's ` +
          `${mask.kind === 'alpha' ? 'alpha channel' : 'contrast against its own backdrop'}. ` +
          (mask.kind === 'alpha'
            ? `A cut-out object usually wants photo_fit: "inscribe", which fits the whole ` +
              `thing inside the disc instead of cropping it. Use "cover" with a "focus" only ` +
              `when the photo is a scene and a circular window into it is the intent.`
            : `Move "focus" - it is "${item.focus || 'center'}" now - so the subject sits ` +
              `nearer the middle of the frame, or crop the source.`)
        );
      }
      console.log(`    ${item.file}: ${shape} frame keeps ${(cov.share * 100).toFixed(0)}% of the ${mask.kind} subject`);
    }
  }

  // Text must clear the frame AND the logo. Measured on leaf nodes only: a
  // wrapper would report its own box instead of the offending child.
  const over = await page.evaluate((h) => {
    let worst = 0;
    for (const el of document.querySelectorAll('.wrap *')) {
      const r = el.getBoundingClientRect();
      if (!r.width && !r.height) continue;
      worst = Math.max(worst, Math.ceil(r.bottom - h), Math.ceil(-r.top));
    }
    return worst;
  }, H);
  if (over > 2) console.log(`  ! ${item.file}: text outside frame by ${over}px`);

  // And clear of the LOGO, which is what the comment above always claimed and the
  // code never did. Leaf nodes only, and an 8px cushion, because type touching a
  // mark reads as a collision well before the boxes actually intersect.
  const clash = await page.evaluate(() => {
    const logo = document.querySelector('img.logo');
    if (!logo) return null;
    const L = logo.getBoundingClientRect();
    const pad = 8;
    for (const el of document.querySelectorAll('.wrap *')) {
      if (el.children.length) continue;
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) continue;
      const hit = Math.min(r.right, L.right + pad) > Math.max(r.left, L.left - pad) &&
                  Math.min(r.bottom, L.bottom + pad) > Math.max(r.top, L.top - pad);
      if (hit) return { text: (el.textContent || '').trim().slice(0, 48), cls: el.className };
    }
    return null;
  });
  if (clash) {
    throw new Error(
      `${item.file}: "${clash.text}" (.${clash.cls}) collides with the logo. ` +
      `Shorten the subtitle or the headline, move the logo to another corner, ` +
      `or reduce its width.`
    );
  }

  // Hand the headline's geometry and colours to proof.py, which does the pixel
  // work. Measuring contrast here would mean reading a canvas; measuring it
  // there means reading the actual shipped PNG, which is the thing a reader
  // sees. The second is worth the extra file.
  const type = await page.evaluate(() => {
    const h1 = document.querySelector('h1');
    if (!h1) return null;
    const r = h1.getBoundingClientRect();
    const em = h1.querySelector('em');
    return {
      box: [Math.round(r.left), Math.round(r.top), Math.round(r.right), Math.round(r.bottom)],
      size: parseFloat(getComputedStyle(h1).fontSize),
      color: getComputedStyle(h1).color,
      highlight: em ? getComputedStyle(em).color : null,
    };
  });

  const png = path.join(OUT, `${item.file}.png`);
  await page.screenshot({ path: png });
  await page.close();

  report.push({
    file: item.file, post_id: item.post_id, layout: item.layout || 'type',
    alt: item.alt, type, photos, circle,
  });
  console.log(`  + ${item.file}.png`);
}

await browser.close();
fs.writeFileSync(path.join(OUT, 'rendered.json'), JSON.stringify(report, null, 2));
console.log(`\n${report.length} rendered into out/. Now run:  python proof.py`);
