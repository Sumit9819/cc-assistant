/**
 * Geometry for the ER brands' circular photo frame.
 *
 * Pure functions, no DOM and no filesystem, for two reasons. The layout needs
 * the numbers to emit CSS and the render guard needs the same numbers to decide
 * whether the subject actually survived the crop - if those two disagree, the
 * guard is measuring a frame that is not the one being drawn. And "the important
 * part is inside the circle" is exactly the kind of claim that should be tested
 * rather than eyeballed, which needs the maths reachable without a browser.
 * See tests/run.mjs.
 *
 * A circle crops HARDER than a rectangle. The inscribed circle of a 420px box
 * keeps pi/4 of it - about 79% - and it throws away the corners, which is where
 * a cover-crop tends to leave the edge of an object. So the crop point matters
 * more here than in the panel layout, and `focus` is not decoration.
 */

/**
 * The frame, in the 1200x630 canvas. `d` is the OUTER diameter including the
 * ring, so the image lives in a circle of `d - 2 * ring`.
 *
 * Sized to fill the slot between the 640px copy column and the right edge,
 * because the pale halo that used to sit behind the frame is gone: the ring is
 * now the only circle on the card and has to carry the weight the halo was
 * lending it.
 *
 * `d` is the frame BOX, which is NOT the frame's footprint - furniture reaches
 * up to `reach` outside it (frame-styles.mjs), and it is the geometry test, not
 * the CSS, that knows where the canvas ends. Still a held object, not a bleed:
 * the widest frame leaves 15px to the copy column and 11px to the right edge.
 */
export const CIRCLE = { cx: 922, cy: 315, d: 482, ring: 11 };

/** Inner diameter: the box the photograph is cover-cropped into. */
export const innerDiameter = (c = CIRCLE) => c.d - 2 * c.ring;

/**
 * The largest SQUARE that fits inside the inner circle.
 *
 * `photo_fit: "inscribe"` gives the image this box and lets `contain` fit it, so
 * an image of any aspect ratio sits wholly inside the disc. Containing it in the
 * full-diameter box instead would still push a wide image's ends outside the
 * circle, which is the failure this exists to avoid.
 */
export const inscribedSquare = (c = CIRCLE) => Math.floor(innerDiameter(c) / Math.SQRT2);

/**
 * The largest rectangle of a given aspect that fits INSIDE the inner circle.
 *
 * Only the diagonal has to fit. Note what this does and does not beat: the
 * inscribed SQUARE is the maximum-area rectangle in a circle, so this can never
 * exceed its area. What it beats is the same image letterboxed inside that
 * square, which is what a CSS-only `contain` produces: the 448x320 film in this
 * set goes from 299x214 letterboxed to 324x232 in the padded circle, about 18%
 * more area. Needs the source's aspect ratio, so it is applied in the browser
 * once the image has loaded.
 */
export const INSCRIBE_PAD = 0.94;

export function inscribeSize({ nw, nh }, c = CIRCLE, pad = INSCRIBE_PAD) {
  if (!(nw > 0 && nh > 0)) throw new Error(`inscribeSize: bad natural size ${nw}x${nh}`);
  // `pad` keeps the corners off the ring. At an exact fit the diagonal EQUALS the
  // inner diameter, so all four corners graze the stroke and the frame reads
  // cramped; 6% back is enough to look held rather than wedged.
  const k = (innerDiameter(c) * pad) / Math.hypot(nw, nh);
  return { w: nw * k, h: nh * k, scale: k };
}

const KEYWORDS = { left: 0, top: 0, center: 0.5, centre: 0.5, right: 1, bottom: 1 };

/**
 * CSS `object-position` as fractions, so the same string drives the CSS and the
 * guard. Accepts `center`, `top`, `70% 30%`, `top right`, `right top`.
 *
 * Deliberately strict: an unparseable focus throws instead of silently falling
 * back to centre, because a silent fallback is how a subject ends up cropped out
 * of a circle by a typo that nothing reports.
 */
export function parseFocus(focus = 'center') {
  const parts = String(focus).trim().toLowerCase().split(/\s+/);
  if (parts.length > 2) throw new Error(`focus "${focus}": expected at most two values`);

  const axis = (p) => {
    if (p in KEYWORDS) return { kind: /^(left|right)$/.test(p) ? 'x' : /^(top|bottom)$/.test(p) ? 'y' : 'any', v: KEYWORDS[p] };
    const m = /^(-?\d+(?:\.\d+)?)%$/.exec(p);
    if (!m) throw new Error(`focus "${focus}": "${p}" is not a keyword or a percentage`);
    return { kind: 'any', v: Number(m[1]) / 100 };
  };

  const vals = parts.map(axis);
  if (vals.length === 1) {
    const v = vals[0];
    if (v.kind === 'x') return { fx: v.v, fy: 0.5 };
    if (v.kind === 'y') return { fx: 0.5, fy: v.v };
    return { fx: v.v, fy: v.v === 0.5 ? 0.5 : v.v };
  }
  // Two values: CSS order is x then y, but `top right` names them the other way
  // round, so an axis-specific keyword wins over position.
  let [a, b] = vals;
  if (a.kind === 'y' || b.kind === 'x') [a, b] = [b, a];
  return { fx: a.v, fy: b.v };
}

/**
 * `object-fit: cover` in numbers: the scale applied to the source, and the
 * offset of its top-left corner inside the box. Both offsets are <= 0, because
 * cover overflows the box on at most one axis.
 *
 * NOTE, because it wastes an afternoon otherwise: when the source and the box
 * share an aspect ratio there is no overflow on EITHER axis, so `focus` moves
 * nothing at all. Setting a focus on a square photo in a square frame is not
 * broken, it is inert. Reach for `photo_fit: "inscribe"` or recrop the source.
 */
export function coverTransform({ nw, nh, bw, bh, fx = 0.5, fy = 0.5 }) {
  if (!(nw > 0 && nh > 0 && bw > 0 && bh > 0)) {
    throw new Error(`coverTransform: all dimensions must be positive (got ${nw}x${nh} into ${bw}x${bh})`);
  }
  const scale = Math.max(bw / nw, bh / nh);
  const dw = nw * scale;
  const dh = nh * scale;
  // `(bw - dw) * 0` is -0, which is not === 0 and serialises into the report as
  // "-0". Adding zero turns it back into +0 and leaves every other value alone.
  return { scale, dw, dh, dx: (bw - dw) * fx + 0, dy: (bh - dh) * fy + 0 };
}

/** The source-pixel rectangle that survives the crop. */
export function visibleRegion(args) {
  const { scale, dx, dy } = coverTransform(args);
  return {
    // `+ 0` for the same reason as in coverTransform: negating a +0 offset makes
    // a -0, which is not === 0 and prints as "-0" in the render report.
    sx: -dx / scale + 0,
    sy: -dy / scale + 0,
    sw: args.bw / scale,
    sh: args.bh / scale,
  };
}

/**
 * How much of the subject lands inside the circle.
 *
 * `mask` is `{ w, h, at(x, y) -> truthy }` - whatever counts as subject, which
 * for a cutout is its alpha and for an uncut photograph is everything that is
 * not its own backdrop colour. Both are built by the caller, so this function
 * never has to know which it is looking at.
 *
 * `step` subsamples: a 1024px source is a million tests otherwise, and the share
 * is stable to a fraction of a percent at step 2-3.
 */
export function circleCoverage({ mask, bw, bh, focus = 'center', step = 2, shape = 'circle' }) {
  const { fx, fy } = typeof focus === 'string' ? parseFocus(focus) : focus;
  const { scale, dx, dy } = coverTransform({ nw: mask.w, nh: mask.h, bw, bh, fx, fy });
  const cx = bw / 2;
  const cy = bh / 2;
  const r = Math.min(bw, bh) / 2;
  const r2 = r * r;

  // A circle keeps pi/4 of its box and throws away the corners; a rectangular
  // frame keeps the whole box, so only the cover overflow is lost. Measuring a
  // bracket frame against a circle would refuse cards that crop nothing, which
  // is how a guard trains people to ignore it.
  const within = shape === 'circle'
    ? (bx, by) => (bx - cx) ** 2 + (by - cy) ** 2 <= r2
    : (bx, by) => bx >= 0 && bx <= bw && by >= 0 && by <= bh;

  let total = 0;
  let inside = 0;
  for (let y = 0; y < mask.h; y += step) {
    for (let x = 0; x < mask.w; x += step) {
      if (!mask.at(x, y)) continue;
      total++;
      if (within(dx + (x + 0.5) * scale, dy + (y + 0.5) * scale)) inside++;
    }
  }
  return { inside, total, share: total ? inside / total : 0, shape };
}

/** Build a mask from RGBA bytes: alpha if the image has any, else not-backdrop. */
export function maskFromRGBA({ data, w, h, alphaFloor = 8, colourTolerance = 26 }) {
  let transparent = 0;
  for (let i = 3; i < data.length; i += 4) if (data[i] <= alphaFloor) transparent++;
  const px = w * h;

  if (transparent / px > 0.01) {
    return { w, h, kind: 'alpha', at: (x, y) => data[(y * w + x) * 4 + 3] > alphaFloor };
  }

  // No usable alpha, so the subject is whatever differs from the backdrop, and
  // the backdrop is sampled from the corners - which is true of the studio
  // stills this generator asks for and false of a full-bleed scene. A scene
  // returns a mask covering nearly the whole frame, which makes the coverage
  // check trivially pass rather than wrongly fail. That is the right way round:
  // a check that cannot see the subject must not invent an opinion about it.
  const corner = (x, y) => {
    const i = (y * w + x) * 4;
    return [data[i], data[i + 1], data[i + 2]];
  };
  const samples = [corner(2, 2), corner(w - 3, 2), corner(2, h - 3), corner(w - 3, h - 3)];
  const bg = [0, 1, 2].map((c) => samples.reduce((s, p) => s + p[c], 0) / samples.length);
  const tol2 = colourTolerance * colourTolerance;
  return {
    w, h, kind: 'colour',
    at: (x, y) => {
      const i = (y * w + x) * 4;
      const dr = data[i] - bg[0];
      const dg = data[i + 1] - bg[1];
      const db = data[i + 2] - bg[2];
      return dr * dr + dg * dg + db * db > tol2;
    },
  };
}
