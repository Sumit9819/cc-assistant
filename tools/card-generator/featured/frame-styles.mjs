/**
 * The frame treatments, in one place.
 *
 * Consumed by BOTH `frames.mjs` (the review sheet, where each frame is rendered
 * alone on a plain ground) and `featured.mjs` (the real 1200x630 layout). They
 * were duplicated for one round and that is exactly how a reviewed option turns
 * into a shipped option that looks different.
 *
 * Weights are authored against a 300px frame - the size the review sheet uses -
 * and scaled by `size / 300`, so a frame keeps the same visual weight at the
 * 440px the card gives it.
 *
 * `shape` is not decoration: the render guard measures how much of the subject
 * survives the frame, and a circle discards the corners of its box (keeping
 * pi/4) where a rectangle keeps all of it. Measuring a bracket frame against an
 * inscribed circle would fail cards that crop nothing.
 */

export const RED = '#DA1212';
export const REF = 300;   // the size these weights are authored at

/** Scale a stroke authored at REF to the frame's actual size. */
const w = (px, size) => +(px * (size / REF)).toFixed(2);

/**
 * `reach`: how far a frame's furniture sits OUTSIDE the frame box, in REF px.
 *
 * The box is not the footprint. B's hairline sits 13px beyond it, I's brackets
 * 16px, E's plate 22px down-right, K's badge 14px. None of that CSS knows where
 * the 1200x630 canvas ends, so this is the number the geometry test uses to
 * prove the whole assembly still clears the copy column and the right edge.
 * Read symmetrically, which over-states the one-sided ones and is therefore a
 * safe bound rather than an exact one.
 */
export const reachPx = (key, size) => w(frame(key).reach || 0, size);

/*
 * The accent comes from the CONTEXT, not from this file: every frame reads
 * `red` off its argument and only falls back to RED for the standalone review
 * sheet. The real layout passes the brand's own token, so a frame can never
 * hardcode a hue the brand does not own.
 */

/**
 * Each frame takes `{ size, ground, photo, logo, dark }` and returns
 * `{ css, back, over, extraCss, svg }`. The caller scopes `extraCss` selectors
 * and supplies the element that `css` applies to.
 */
export const FRAMES = {
  a: {
    name: 'A. Plain ring',
    note: 'The one that shipped, for reference. An avatar bubble.',
    shape: 'circle',
    css: ({ size, red = RED }) => `border-radius:50%;overflow:hidden;border:${w(8, size)}px solid ${red}`,
  },

  b: {
    name: 'B. Ringed seal',
    note: 'Heavy ring, a true gap in the ground colour, hairline outside it.',
    shape: 'circle',
    reach: 13,
    // A symmetric negative inset on its own element is the only concentric one of
    // the three attempts: stacked box-shadows drew a second ring that read
    // off-centre, and `outline-offset` on a 50% radius is approximated by
    // Chromium into a visible ellipse.
    css: ({ size, red = RED }) => `border-radius:50%;overflow:hidden;border:${w(7, size)}px solid ${red}`,
    over: ({ red = RED } = {}) => `<i class="hair"></i>`,
    extraCss: ({ size, red = RED }) => `.hair{position:absolute;inset:-${w(13, size)}px;
      border:${w(2, size)}px solid ${red};border-radius:50%}`,
  },

  c: {
    name: 'C. Open arc',
    note: 'The ring stops short. Asymmetry reads as chosen rather than default.',
    shape: 'circle',
    // The gap is a transparent wedge in a conic gradient behind the disc, so the
    // ring is a painted ring rather than a border with a hole in it.
    wrap: ({ size, red = RED }) => `background:conic-gradient(from 128deg, ${red} 0 300deg, transparent 300deg);
      border-radius:50%;padding:${w(7, size)}px`,
    css: ({ red = RED } = {}) => `border-radius:50%;overflow:hidden`,
  },

  d: {
    name: 'D. ECG break',
    note: "The ring breaks and the brand's own trace crosses the gap.",
    shape: 'circle',
    reach: 10,
    css: ({ red = RED } = {}) => `border-radius:50%;overflow:hidden`,
    over: ({ size, red = RED }) => `<svg class="ecg" viewBox="0 0 320 320" aria-hidden="true">
        <circle cx="160" cy="160" r="152" fill="none" stroke="${red}"
                stroke-width="${w(7, size) * (320 / size)}" stroke-linecap="round"
                stroke-dasharray="720 235" stroke-dashoffset="-118"
                transform="rotate(-90 160 160)"/>
        <path d="M232,160 h22 l7,-26 l9,54 l9,-72 l10,44 h24" fill="none" stroke="${red}"
              stroke-width="${w(7, size) * (320 / size)}"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`,
    extraCss: ({ size, red = RED }) => `.ecg{position:absolute;inset:-${w(10, size)}px;
      width:calc(100% + ${w(20, size)}px);height:calc(100% + ${w(20, size)}px);overflow:visible}`,
  },

  e: {
    name: 'E. Offset plate',
    note: 'A solid red disc behind, shifted down-right. Layered and editorial.',
    shape: 'circle',
    reach: 22,
    css: ({ red = RED } = {}) => `border-radius:50%;overflow:hidden`,
    back: ({ red = RED } = {}) => `<i class="plate"></i>`,
    extraCss: ({ size, red = RED }) => `.plate{position:absolute;left:${w(22, size)}px;top:${w(22, size)}px;
      width:100%;height:100%;border-radius:50%;background:${red}}`,
  },

  f: {
    name: 'F. D-shape',
    note: 'Flat edge faces the headline, curve faces out. Directional.',
    shape: 'rect',
    css: ({ size, red = RED }) => `border-radius:0 ${size}px ${size}px 0;overflow:hidden;
      border:${w(7, size)}px solid ${red}`,
  },

  g: {
    name: 'G. Squircle',
    note: 'Rounded square. Keeps far more of the photo than a circle.',
    shape: 'rect',
    css: ({ size, red = RED }) => `border-radius:26%;overflow:hidden;border:${w(7, size)}px solid ${red}`,
  },

  h: {
    name: 'H. Hexagon',
    note: 'Clinical and technical. Reads as instrumentation.',
    shape: 'rect',
    // One SVG: the photo clipped by a polygon and the SAME polygon stroked over
    // it, which gives an outline of even weight. A scaled-up clip path drifted -
    // scaling a hexagon moves its points further than its edges.
    svg: ({ id, photo, size, red = RED }) => `<svg class="hex" viewBox="0 0 300 300" preserveAspectRatio="none">
        <defs><clipPath id="hex-${id}">
          <polygon points="82,14 218,14 292,150 218,286 82,286 8,150"/>
        </clipPath></defs>
        <image href="${photo}" x="0" y="0" width="300" height="300"
               preserveAspectRatio="xMidYMid slice" clip-path="url(#hex-${id})"/>
        <polygon points="82,14 218,14 292,150 218,286 82,286 8,150" fill="none"
                 stroke="${red}" stroke-width="13" stroke-linejoin="round"/>
      </svg>`,
    extraCss: ({ red = RED } = {}) => `.hex{position:absolute;inset:0;width:100%;height:100%}`,
  },

  i: {
    name: 'I. Viewfinder brackets',
    note: 'Radiology marker corners. Frames the photo without enclosing it.',
    shape: 'rect',
    reach: 16,
    css: ({ size, red = RED }) => `overflow:hidden;border-radius:${w(2, size)}px`,
    over: ({ red = RED } = {}) => `<i class="br tl"></i><i class="br tr"></i><i class="br bl"></i><i class="br bb"></i>`,
    extraCss: ({ size, red = RED }) => `
      .br{position:absolute;width:30%;height:30%;border:${w(8, size)}px solid ${red}}
      .tl{left:-${w(16, size)}px;top:-${w(16, size)}px;border-right:0;border-bottom:0}
      .tr{right:-${w(16, size)}px;top:-${w(16, size)}px;border-left:0;border-bottom:0}
      .bl{left:-${w(16, size)}px;bottom:-${w(16, size)}px;border-right:0;border-top:0}
      .bb{right:-${w(16, size)}px;bottom:-${w(16, size)}px;border-left:0;border-top:0}`,
  },

  j: {
    name: 'J. Bottom slab',
    note: 'One weighted red edge, like the callout strip the skills already use.',
    shape: 'rect',
    css: ({ size, red = RED }) => `overflow:hidden;border-radius:${w(3, size)}px;
      border-bottom:${w(16, size)}px solid ${red}`,
  },

  k: {
    name: 'K. Logo badge',
    note: "The brand mark sits on the ring, bitten out of it. Most brand-specific.",
    shape: 'circle',
    reach: 14,
    // The bite is a hole punched in the frame's own mask so the mark is not
    // sitting on top of the photograph; the mark is the real logo file, not a
    // drawn plus sign - a generic cross is exactly what makes a medical graphic
    // look like clip art.
    css: ({ size, red = RED }) => `border-radius:50%;overflow:hidden;border:${w(7, size)}px solid ${red};
      -webkit-mask-image:radial-gradient(circle at 93% 89%, transparent 0 19%, #000 19%);
      mask-image:radial-gradient(circle at 93% 89%, transparent 0 19%, #000 19%)`,
    over: ({ logo }) => (logo ? `<img class="mark" src="${logo}" alt="">` : ''),
    extraCss: ({ size, red = RED }) => `.mark{position:absolute;right:-${w(14, size)}px;
      bottom:-${w(14, size)}px;width:26%;height:auto}`,
  },

  l: {
    name: 'L. Inset keyline',
    note: 'The ring sits inside the photo edge rather than around it. Restrained.',
    shape: 'circle',
    // Drawn ABOVE the photograph. As an inset box-shadow on the container it was
    // painted under the child <img> and the option rendered as a plain disc.
    css: ({ red = RED } = {}) => `border-radius:50%;overflow:hidden`,
    over: ({ red = RED } = {}) => `<i class="key"></i>`,
    extraCss: ({ size, red = RED }) => `.key{position:absolute;inset:0;border-radius:50%;
      box-shadow:inset 0 0 0 ${w(7, size)}px ${red};pointer-events:none}`,
  },
};

/** Class names the caller must scope, so twelve frames can share one document. */
export const SCOPED = /\.(plate|hex|ecg|hair|key|br|tl|tr|bl|bb|mark)\b/g;

export function frame(key) {
  const f = FRAMES[key];
  if (!f) throw new Error(`Unknown frame "${key}". Known: ${Object.keys(FRAMES).join(', ')}`);
  return f;
}
