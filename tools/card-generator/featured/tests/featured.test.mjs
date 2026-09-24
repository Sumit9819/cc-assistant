/**
 * Unit tests for the featured generator.
 *
 *   node --test                          (from the featured/ directory)
 *   node --test-reporter=spec --test     (one line per assertion)
 *
 * The filename ends in .test.mjs because that is what Node's runner discovers on
 * its own; `--test tests/` looks for a module called "tests" and fails.
 *
 * node:test and node:assert only - no dependencies, because a test suite that
 * needs an install is a test suite nobody runs.
 *
 * What is covered here is the part that can be wrong WITHOUT looking wrong: the
 * circle geometry, the derived emphasis, the ER-only rule, and the depiction
 * policy. What is not covered is whether the result is any good, which no
 * assertion can tell you - that is what out/_listing-sheet.png is for.
 */
import test from 'node:test';
import assert from 'node:assert/strict';

import {
  CIRCLE, innerDiameter, inscribedSquare, inscribeSize, INSCRIBE_PAD, parseFocus,
  coverTransform, visibleRegion, circleCoverage, maskFromRGBA,
} from '../circle.mjs';
import { render, LAYOUTS } from '../featured.mjs';
import { checkImageText, profileFor } from '../imagepolicy.mjs';
import { FRAMES, frame as frameStyle, reachPx } from '../frame-styles.mjs';
import { brand, contrast } from '../../brands.mjs';

// ---------------------------------------------------------------- focus

test('parseFocus: keywords, percentages, and both orders', () => {
  assert.deepEqual(parseFocus('center'), { fx: 0.5, fy: 0.5 });
  assert.deepEqual(parseFocus(), { fx: 0.5, fy: 0.5 });
  assert.deepEqual(parseFocus('top'), { fx: 0.5, fy: 0 });
  assert.deepEqual(parseFocus('right'), { fx: 1, fy: 0.5 });
  assert.deepEqual(parseFocus('70% 30%'), { fx: 0.7, fy: 0.3 });
  // CSS writes x then y, English says "top right"; both must land the same way.
  assert.deepEqual(parseFocus('right top'), { fx: 1, fy: 0 });
  assert.deepEqual(parseFocus('top right'), { fx: 1, fy: 0 });
});

test('parseFocus: refuses nonsense instead of silently centring', () => {
  // A silent fallback is how a subject gets cropped out of a circle by a typo
  // that nothing reports.
  assert.throws(() => parseFocus('middle'), /not a keyword or a percentage/);
  assert.throws(() => parseFocus('top left bottom'), /at most two values/);
});

// ---------------------------------------------------------------- cover maths

test('coverTransform: fills the box and overflows on one axis only', () => {
  // A landscape source into a square box: scales to the box HEIGHT, overflows
  // horizontally, and centres that overflow.
  const t = coverTransform({ nw: 400, nh: 200, bw: 100, bh: 100 });
  assert.equal(t.scale, 0.5);
  assert.equal(t.dw, 200);
  assert.equal(t.dh, 100);
  assert.equal(t.dx, -50);   // (100 - 200) * 0.5
  assert.equal(t.dy, 0);     // no vertical overflow
});

test('coverTransform: focus moves the crop window, not the scale', () => {
  const left = coverTransform({ nw: 400, nh: 200, bw: 100, bh: 100, fx: 0 });
  const right = coverTransform({ nw: 400, nh: 200, bw: 100, bh: 100, fx: 1 });
  assert.equal(left.scale, right.scale);
  assert.equal(left.dx, 0);
  assert.equal(right.dx, -100);
});

test('coverTransform: refuses degenerate input', () => {
  assert.throws(() => coverTransform({ nw: 0, nh: 10, bw: 10, bh: 10 }), /positive/);
});

test('visibleRegion: reports the source rectangle that survives', () => {
  const r = visibleRegion({ nw: 400, nh: 200, bw: 100, bh: 100 });
  assert.equal(r.sw, 200);   // 100px box / 0.5 scale
  assert.equal(r.sh, 200);
  assert.equal(r.sx, 100);   // centred: 100px of the 400 dropped each side
  assert.equal(r.sy, 0);
});

// ---------------------------------------------------------------- coverage

/** A mask with a solid rectangle in it, for arranging subjects on purpose. */
function boxMask(w, h, x0, y0, x1, y1) {
  return { w, h, kind: 'test', at: (x, y) => x >= x0 && x < x1 && y >= y0 && y < y1 };
}

test('circleCoverage: a centred subject survives, a cornered one does not', () => {
  const centred = circleCoverage({ mask: boxMask(400, 400, 150, 150, 250, 250), bw: 200, bh: 200, step: 1 });
  assert.equal(centred.share, 1);

  const cornered = circleCoverage({ mask: boxMask(400, 400, 0, 0, 100, 100), bw: 200, bh: 200, step: 1 });
  assert.ok(cornered.share < 0.35, `expected a corner subject to be mostly outside, got ${cornered.share}`);
});

test('circleCoverage: focus can rescue an off-centre subject', () => {
  // The source must be a different shape from the box, or there is no overflow
  // for the focus to slide - see the next test. A landscape source into a square
  // frame overflows horizontally, so a subject at its left edge is reachable.
  const mask = boxMask(400, 200, 10, 50, 110, 150);
  const centre = circleCoverage({ mask, bw: 200, bh: 200, focus: 'center', step: 1 });
  const aimed = circleCoverage({ mask, bw: 200, bh: 200, focus: 'left', step: 1 });
  assert.ok(aimed.share > centre.share,
    `aiming at the subject should keep more of it (${aimed.share} vs ${centre.share})`);
  assert.ok(aimed.share > 0.85, `expected the aimed crop to keep it, got ${aimed.share}`);
});

test('focus is INERT when the source and the frame share an aspect ratio', () => {
  // Not a bug and not a rounding artefact: cover then overflows on neither axis,
  // so there is nothing for object-position to move. This test exists because
  // the first version of the test above assumed the opposite and failed, and the
  // next person to set a focus on a square photo deserves to find the answer
  // here rather than in the layout.
  const mask = boxMask(400, 400, 20, 20, 120, 120);
  const centre = circleCoverage({ mask, bw: 200, bh: 200, focus: 'center', step: 1 });
  const aimed = circleCoverage({ mask, bw: 200, bh: 200, focus: 'top left', step: 1 });
  assert.equal(aimed.share, centre.share);
  const t = coverTransform({ nw: 400, nh: 400, bw: 200, bh: 200, fx: 0, fy: 0 });
  assert.equal(t.dx, 0);
  assert.equal(t.dy, 0);
});

test('circleCoverage: a full-frame subject loses the corners, about 1 - pi/4', () => {
  // The circle inscribed in a square keeps pi/4 of it. This is the number that
  // makes the check meaningful: a subject filling its frame CANNOT score 100%,
  // so the floor has to sit below it.
  const full = circleCoverage({ mask: boxMask(200, 200, 0, 0, 200, 200), bw: 200, bh: 200, step: 1 });
  assert.ok(Math.abs(full.share - Math.PI / 4) < 0.01,
    `expected about ${(Math.PI / 4).toFixed(3)}, got ${full.share.toFixed(3)}`);
});

test('circleCoverage: empty mask reports zero rather than dividing by zero', () => {
  const none = circleCoverage({ mask: boxMask(100, 100, 0, 0, 0, 0), bw: 50, bh: 50, step: 1 });
  assert.equal(none.total, 0);
  assert.equal(none.share, 0);
});

// ---------------------------------------------------------------- masks

/** RGBA bytes for a w*h image, painted by a callback. */
function rgba(w, h, paint) {
  const data = new Array(w * h * 4).fill(0);
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const [r, g, b, a] = paint(x, y);
      const i = (y * w + x) * 4;
      data[i] = r; data[i + 1] = g; data[i + 2] = b; data[i + 3] = a;
    }
  }
  return { data, w, h };
}

test('maskFromRGBA: uses alpha when the image has any', () => {
  const img = rgba(40, 40, (x, y) => (x > 10 && x < 30 && y > 10 && y < 30 ? [0, 0, 0, 255] : [0, 0, 0, 0]));
  const mask = maskFromRGBA(img);
  assert.equal(mask.kind, 'alpha');
  assert.ok(mask.at(20, 20));
  assert.ok(!mask.at(2, 2));
});

test('maskFromRGBA: falls back to contrast against the corners when opaque', () => {
  // A dark object on a light studio ground, no transparency anywhere.
  const img = rgba(40, 40, (x, y) => (x > 15 && x < 25 && y > 15 && y < 25 ? [20, 20, 20, 255] : [232, 232, 232, 255]));
  const mask = maskFromRGBA(img);
  assert.equal(mask.kind, 'colour');
  assert.ok(mask.at(20, 20));
  assert.ok(!mask.at(1, 1));
});

// ---------------------------------------------------------------- geometry

test('the circle fits the canvas with room for the text column', () => {
  const left = CIRCLE.cx - CIRCLE.d / 2;
  const right = CIRCLE.cx + CIRCLE.d / 2;
  assert.ok(left > 640, `the frame must clear the 640px text column, starts at ${left}`);
  assert.ok(right < 1200, `the frame must not bleed off the 1200px canvas, ends at ${right}`);
  assert.ok(CIRCLE.cy - CIRCLE.d / 2 > 0 && CIRCLE.cy + CIRCLE.d / 2 < 630);
});

test('every frame, its furniture included, fits the slot it is given', () => {
  // The frame BOX passing is not enough: what bleeds off a card is a hairline or
  // a bracket, and the CSS that draws those has no idea where the canvas ends.
  for (const key of Object.keys(FRAMES)) {
    const r = reachPx(key, CIRCLE.d);
    const left = CIRCLE.cx - CIRCLE.d / 2 - r;
    const right = CIRCLE.cx + CIRCLE.d / 2 + r;
    const top = CIRCLE.cy - CIRCLE.d / 2 - r;
    const bottom = CIRCLE.cy + CIRCLE.d / 2 + r;
    assert.ok(left > 640,
      `frame ${key} reaches into the 640px copy column, to ${left.toFixed(1)}`);
    assert.ok(right < 1200,
      `frame ${key} bleeds off the right edge, to ${right.toFixed(1)}`);
    assert.ok(top > 0 && bottom < 630,
      `frame ${key} bleeds vertically: ${top.toFixed(1)}..${bottom.toFixed(1)}`);
  }
});

test('the inscribed square really fits inside the inner circle', () => {
  const s = inscribedSquare();
  const diagonal = Math.hypot(s, s);
  assert.ok(diagonal <= innerDiameter() + 1,
    `a ${s}px square has a ${diagonal.toFixed(1)}px diagonal, which must fit ${innerDiameter()}px`);
});

test('inscribeSize: fills the padded circle, and beats contain-in-square', () => {
  const inner = innerDiameter();
  for (const [nw, nh] of [[448, 320], [226, 316], [1024, 572], [500, 500]]) {
    const { w, h } = inscribeSize({ nw, nh });
    assert.ok(Math.abs(Math.hypot(w, h) - inner * INSCRIBE_PAD) < 0.01,
      `${nw}x${nh}: the diagonal must fill the padded circle, got ` +
      `${Math.hypot(w, h).toFixed(2)} vs ${(inner * INSCRIBE_PAD).toFixed(2)}`);
    assert.ok(Math.hypot(w, h) < inner, `${nw}x${nh}: must stay clear of the ring`);
    assert.ok(Math.abs(w / h - nw / nh) < 1e-9, `${nw}x${nh}: aspect ratio must be preserved`);
    if (nw !== nh) {
      // The square is the MAXIMUM-area rectangle in a circle, so an
      // aspect-preserving fit can never beat the square's own area - an earlier
      // version of this test asserted that and was wrong. What it beats is the
      // same image letterboxed INSIDE that square, which is what the CSS-only
      // path actually produced.
      const k = inscribedSquare() / Math.max(nw, nh);
      const contained = nw * k * nh * k;
      // Strictly larger, with the actual ratio in the message rather than a magic
      // margin in the assertion - a hardcoded 1.2 here failed the moment the
      // padding was added, which told me nothing about the code.
      assert.ok(w * h > contained,
        `${nw}x${nh}: the exact fit should beat contain-in-square ` +
        `(${(w * h).toFixed(0)} vs ${contained.toFixed(0)}, ` +
        `${(((w * h) / contained - 1) * 100).toFixed(0)}% more)`);
    }
  }
});

test('inscribeSize: a square source lands on the safe square, less the padding', () => {
  const { w, h } = inscribeSize({ nw: 300, nh: 300 });
  assert.ok(Math.abs(w - inscribedSquare() * INSCRIBE_PAD) <= 1);
  assert.equal(Math.round(w), Math.round(h));
});

test('inscribeSize: refuses a zero-sized image', () => {
  assert.throws(() => inscribeSize({ nw: 0, nh: 100 }), /bad natural size/);
});

// ---------------------------------------------------------------- layouts

const ER = { file: 't', post_id: 1, alt: 'a', title: ['A ', 'B', '?'], photo: 'cut/er-co-plugin.png' };

test('the framed layout is available to the ER brands, in their own accent', () => {
  for (const slug of ['erofirving', 'eroflufkin', 'erofwhiterock']) {
    const html = render({ ...ER, brand: slug, layout: 'photo-frame', frame: 'b' });
    assert.match(html, /id="fr"/);
    // The ring must be the BRAND's accent, not a hue baked into frame-styles.mjs.
    assert.ok(html.includes(`solid ${brand(slug).accent}`),
      `${slug}: the ring should be drawn in ${brand(slug).accent}`);
    assert.match(html, /class="hair"/, 'frame B draws its outer hairline');
  }
});

test('each frame renders its own furniture', () => {
  const furniture = {
    b: /class="hair"/,
    c: /conic-gradient/,
    i: /class="br tl"/,
    j: /border-bottom:/,
    k: /class="mark"/,
  };
  for (const [key, re_] of Object.entries(furniture)) {
    const html = render({ ...ER, brand: 'eroflufkin', layout: 'photo-frame', frame: key });
    assert.match(html, re_, `frame ${key} did not render its own parts`);
  }
});

test('a framed layout refuses inscribe, which cannot fill a disc', () => {
  for (const layout of ['photo-frame', 'photo-frame-dark']) {
    assert.throws(
      () => render({ ...ER, brand: 'erofirving', layout, frame: 'b', photo_fit: 'inscribe' }),
      /cannot fill a frame/,
      `${layout} must refuse inscribe`);
  }
  // ...and cover is still fine, or the refusal would be blocking the whole layout.
  assert.match(render({ ...ER, brand: 'erofirving', layout: 'photo-frame', frame: 'b' }),
    /object-fit:cover/);
});

test('the framed layout is refused to the wellness brand', () => {
  // Not a style preference: that palette already has a marker, hued washes and a
  // human subject, and the disc exists because the ER palette has none of those.
  for (const layout of ['photo-frame', 'photo-frame-dark']) {
    assert.throws(() => render({ ...ER, brand: 'iwc', layout }), /ER brands only/);
  }
});

test('a framed card always emits a cover crop, and honours focus', () => {
  // There is no longer a second fit to compare against: a frame must be filled,
  // so this pins the one thing it may emit and proves focus still reaches the CSS.
  const html = render({ ...ER, brand: 'erofirving', layout: 'photo-frame', frame: 'b', focus: '70% 30%' });
  assert.match(html, /#fr [.]f img[{][^}]*object-fit:cover/);
  assert.match(html, /object-position:70% 30%/);
  // Absence of the inscribe branch is what proves the frame is filled: only that
  // branch sizes the img to the safe square. NOT `object-fit:contain`, which the
  // shared `.photo` rule carries in every layout - that was my own bad assertion.
  assert.doesNotMatch(html, new RegExp('width:' + inscribedSquare() + 'px'));
});

test('a bad focus fails at render, not in the picture', () => {
  assert.throws(() => render({ ...ER, brand: 'erofirving', layout: 'photo-frame', frame: 'b', focus: 'middle' }),
    /not a keyword or a percentage/);
});

test('every layout name renders for the brand that owns it', () => {
  for (const layout of LAYOUTS) {
    const slug = layout.startsWith('photo-frame') ? 'erofirving' : 'iwc';
    const spec = { ...ER, brand: slug, layout, photo: 'cut/gem-skincare-tray.png' };
    assert.doesNotThrow(() => render(spec), `${layout} failed to render`);
  }
});

test('coverage: a rectangular frame keeps its corners, a circle does not', () => {
  const mask = boxMask(200, 200, 0, 0, 200, 200);
  const circle = circleCoverage({ mask, bw: 200, bh: 200, step: 1, shape: 'circle' });
  const rect = circleCoverage({ mask, bw: 200, bh: 200, step: 1, shape: 'rect' });
  assert.ok(Math.abs(circle.share - Math.PI / 4) < 0.01);
  assert.equal(rect.share, 1);
});

test('every frame declares a shape the guard understands', () => {
  for (const [key, f] of Object.entries(FRAMES)) {
    assert.ok(['circle', 'rect'].includes(f.shape), `frame ${key} has shape "${f.shape}"`);
    assert.ok(f.name && f.note, `frame ${key} needs a name and a note`);
  }
});

test('an unknown frame is refused by name', () => {
  assert.throws(() => frameStyle('zz'), /Unknown frame/);
});

test('the logo badge uses the real mark, not a drawn cross', () => {
  const withLogo = frameStyle('k').over({ logo: 'data:image/png;base64,AAA', size: 440 });
  assert.match(withLogo, /<img class="mark"/);
  assert.match(withLogo, /src="data:image\/png;base64,AAA"/);
  // and degrades to nothing rather than to a clip-path plus sign
  assert.equal(frameStyle('k').over({ logo: null, size: 440 }), '');
});

// ---------------------------------------------------------------- emphasis

test('emphasis is derived from contrast, per brand and per ground', () => {
  // IWC's dark reads on its yellow (10.58:1), so the accent can be a slab behind
  // the words. Brand red cannot carry navy (3.16:1), so it stays in the words on
  // light grounds and becomes an underline on dark ones.
  const iwcLight = render({ ...ER, brand: 'iwc', layout: 'type' });
  assert.match(iwcLight, /52%,var\(--accent\) 52%/, 'IWC light should swipe the accent behind the words');

  const erLight = render({ ...ER, brand: 'erofirving', layout: 'type' });
  assert.match(erLight, /h1 em\{color:var\(--accent\)\}/, 'ER light should colour the words');
  assert.match(erLight, /\.kicker\{background:var\(--accent\)/, 'ER light should chip the kicker');

  const erDark = render({ ...ER, brand: 'erofirving', layout: 'type-dark' });
  assert.match(erDark, /88%,var\(--accent\) 88%/, 'ER dark should underline the words');
});

test('IWC keeps a plain kicker; the chip is for brands with no slab', () => {
  const iwc = render({ ...ER, brand: 'iwc', layout: 'type' });
  assert.doesNotMatch(iwc, /\.kicker\{background:var\(--accent\)/);
});

// ---------------------------------------------------------------- brands

test('every brand validates and can carry text on its own accent', () => {
  for (const slug of ['iwc', 'erofirving', 'eroflufkin', 'erofwhiterock']) {
    const b = brand(slug);
    assert.ok(contrast(b.accent, b.on_accent) >= 4.5, `${slug}: on_accent fails on accent`);
    assert.ok(contrast(b.dark, '#ffffff') >= 4.5, `${slug}: white fails on dark`);
    assert.equal(b.featured_shapes.length, 2, `${slug}: needs two featured shapes`);
    assert.ok(b.glow && b.on_dark_soft, `${slug}: missing a featured token`);
  }
});

test('an unknown brand is refused by name', () => {
  assert.throws(() => brand('erofnowhere'), /Unknown brand/);
});

// ---------------------------------------------------------------- policy

test('the depiction rules invert between the profiles', () => {
  const portrait = 'A studio portrait of a plus-size woman from the waist up, calm expression';
  assert.equal(checkImageText(portrait, 'iwc').refusals.length, 0);
  assert.ok(checkImageText(portrait, 'erofirving').refusals.length > 0,
    'the ER profile must refuse a person');
});

test('the ER profile refuses what the ER skills call anti-patterns', () => {
  const cases = [
    ['a nurse beside a CT scanner', /no people/],
    ['an ambulance with flashing lights', /transport/],
    ['shorter wait time than the hospital ED', /self-anchored/],
    ['a hand holding a thermometer', /body part/],
  ];
  for (const [text, why] of cases) {
    const { refusals } = checkImageText(text, 'eroflufkin');
    assert.ok(refusals.length, `should have refused: ${text}`);
    assert.match(refusals[0].why, why);
  }
});

test('the wellness profile refuses the weight-stigma tropes', () => {
  for (const text of ['a headless torso of a heavy woman',
                      'a before and after weight loss transformation',
                      'a woman standing on a scale']) {
    assert.ok(checkImageText(text, 'iwc').refusals.length, `should have refused: ${text}`);
  }
});

test('an `unless` clause exempts a legitimate context', () => {
  // "a forearm" is a body part; a radiograph OF a forearm is a picture of bones.
  assert.ok(checkImageText('a forearm resting on a table', 'erofirving').refusals.length);
  assert.equal(checkImageText('a radiograph film of a forearm', 'erofirving').refusals.length, 0);
});

test('photographic idiom is not read as a person', () => {
  // Both cost a false refusal of a real object brief before they were fixed.
  for (const text of ['a detector lying face up on a grey surface',
                      'a wall clock, the hands of a clock at ten past two']) {
    assert.equal(checkImageText(text, 'eroflufkin').refusals.length, 0, text);
  }
});

test('profileFor maps every brand, and defaults rather than throwing', () => {
  assert.equal(profileFor('iwc'), 'wellness');
  assert.equal(profileFor('erofirving'), 'er');
  assert.equal(profileFor('erofwhiterock'), 'er');
  assert.equal(profileFor('eroflufkin'), 'er');
  assert.equal(profileFor('somethingelse'), 'shared');
});
