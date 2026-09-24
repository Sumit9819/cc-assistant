/**
 * Layout library for irvingwellnessclinic FEATURED images (1200x630).
 *
 * Separate from layouts.mjs on purpose. In-body cards are read at full article
 * width; a featured image is consumed at three sizes and the SMALLEST one
 * governs - the blog listing card is roughly 400px wide, where anything under
 * about 56px of source type turns to mush. So these layouts carry a fraction of
 * the text a card does, at roughly triple the size. Sharing a file with
 * layouts.mjs would have meant one type ramp trying to serve both, and the
 * featured set would have lost that argument every time.
 *
 * Geometry is 1200x630, not the cards' 1200x628: this is the Open Graph frame,
 * and 630 is what Facebook, LinkedIn and Google Discover specify.
 *
 * `photo_fit: "center"` (spec, optional) is the framing for an OBJECT: centred
 * in its column at full width, with no pool shadow. The default bottom-anchored
 * framing belongs to a cutout PERSON, who stands on the canvas; an object
 * anchored to the bottom just sinks into the corner.
 *
 * `photo_inset` (spec, optional, px) floats the photo off the right and bottom
 * edges instead of bleeding it. Default 0 keeps the original framing, which is
 * correct for a cutout PERSON - they stand on the canvas and run off the bottom.
 * An object does not stand anywhere, and a rectangular one bled to both edges
 * reads as a clipping error, so give objects an inset.
 *
 * MULTI-BRAND since 2026-09-08, over the same role tokens the in-body cards use
 * (../brands.mjs), so a colour is never named after its hue here. What could not
 * be shared is the EMPHASIS treatment, and it is derived rather than declared:
 * IWC's highlight is a yellow marker swiped behind green words, which is
 * unreadable in brand red - navy on red measures 3.16:1. So each ground asks the
 * brand a measured question. On a light ground: is accent-coloured TEXT legible
 * (ER red on white, 5.15:1)? Then use it; otherwise swipe the accent behind
 * `dark` text (IWC yellow, 13.7:1). On the dark ground: is the accent legible on
 * it (IWC yellow on green, 10.58:1)? Then accent text; otherwise white text with
 * an accent UNDERLINE, which moves the red from being type to being a graphic,
 * where 3:1 is the bar it has to clear. Same rule for the kicker. A new brand
 * therefore cannot ship unreadable emphasis, and IWC's output is unchanged -
 * verified by md5 against the shipped PNGs.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { brand, contrast } from '../brands.mjs';
import { CIRCLE, innerDiameter, inscribedSquare, parseFocus } from './circle.mjs';
import { frame as frameStyle, SCOPED } from './frame-styles.mjs';
import { profileFor } from './imagepolicy.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const CARDS = path.join(HERE, '..');

/** WCAG AA for normal text. Below this an accent cannot carry type. */
const AA = 4.5;

export const W = 1200;
export const H = 630;

export const esc = (s) =>
  String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/**
 * Assets inline as data URIs rather than file:// paths.
 *
 * Chromium is inconsistent about local subresources under file://, and the
 * failure is silent: a photo that does not load ships as an empty panel that
 * looks like a deliberate design choice. Inlining removes the failure mode.
 */
const cache = new Map();
function dataUri(abs, mime) {
  if (!cache.has(abs)) {
    if (!fs.existsSync(abs)) throw new Error(`Missing asset: ${abs}`);
    cache.set(abs, `data:${mime};base64,${fs.readFileSync(abs).toString('base64')}`);
  }
  return cache.get(abs);
}

const logoUri = (b, variant) => {
  const file = b.logo?.[variant];
  if (!file) {
    throw new Error(`Brand "${b.name}" has no logo variant "${variant}". ` +
                    `Available: ${Object.keys(b.logo || {}).join(', ') || 'none'}`);
  }
  return dataUri(path.join(CARDS, 'brand', file), 'image/png');
};

const photoUri = (rel) => {
  const abs = path.isAbsolute(rel) ? rel : path.join(HERE, rel);
  const mime = abs.toLowerCase().endsWith('.webp') ? 'image/webp'
    : /\.jpe?g$/i.test(abs) ? 'image/jpeg' : 'image/png';
  return dataUri(abs, mime);
};

const BASE = (b) => `
:root{
  --dark:${b.dark}; --mid:${b.mid}; --accent:${b.accent}; --on-accent:${b.on_accent};
  --ground:${b.ground}; --ink:${b.ink}; --glow:${b.glow}; --on-dark-soft:${b.on_dark_soft};
  --surface:${b.surface}; --icon-bg:${b.icon_bg};
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:${W}px;height:${H}px}
body{background:var(--ground);font-family:${b.font},system-ui,sans-serif;
     position:relative;overflow:hidden}

/* One shared type ramp. Sizes are deliberately blunt: at 400px wide these
   become 25px, 7px and 8px respectively, which is the real reading size. */
.kicker{font-weight:600;font-size:21px;letter-spacing:.18em;text-transform:uppercase}
h1{font-weight:700;font-size:76px;line-height:1.06;letter-spacing:-1.6px}
h1 em{font-style:normal}
/* The chip must not stretch to the column width: a flex column stretches
   its children by default, which would turn the kicker into a full-width
   band. align-items:flex-start on the wrap keeps every child its own size,
   and the text blocks below set their own max-widths anyway. */
.wrap{align-items:flex-start}
.sub{font-weight:500;font-size:25px;line-height:1.4}

.logo{position:absolute;height:auto;opacity:.95}
.photo{position:absolute;object-fit:contain;object-position:bottom center}

/* A cutout with nothing behind it reads as a sticker. A soft radial pool under
   the subject grounds it without implying a background that is not there. */
.pool{position:absolute;border-radius:50%}

/* The brand accent edge, on EVERY layout. It was on two of the four, which read
   as an accident rather than a choice once the tiles sat side by side in a grid. */
.accent{position:absolute;left:0;top:0;width:14px;height:${H}px;background:var(--accent);z-index:4}
`;

function head(b, css) {
  return `<!doctype html><meta charset="utf-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="${b.font_css}">
<style>${BASE(b)}${css}</style>`;
}

const LOGO_POS = {
  'top-left': 'top:38px;left:52px',
  'top-right': 'top:38px;right:52px',
  'bottom-left': 'bottom:38px;left:52px',
  'bottom-right': 'bottom:38px;right:52px',
};

function logoMarkup(b, spec) {
  const { variant = 'green', position = 'bottom-left', width = 168 } =
    typeof spec === 'string' ? { position: spec } : spec;
  const pos = LOGO_POS[position];
  if (!pos) throw new Error(`Unknown logo position "${position}"`);
  return `<img class="logo" src="${logoUri(b, variant)}" style="${pos};width:${width}px">`;
}

/**
 * Headline accepts either a plain string or the cards' three-part array, where
 * the middle part is the highlight. Keeping both shapes means a featured spec
 * can be lifted straight from a card spec without rewriting the title.
 *
 * The <em> carries no inline colour: each layout styles it, because the two
 * grounds need opposite treatments, and what those are depends on the brand -
 * see emphasisOnLight / emphasisOnDark below.
 */
function headline(title) {
  if (Array.isArray(title)) {
    const [a, m, b] = title.map((x) => esc(x ?? ''));
    return `${a}<em>${m}</em>${b}`;
  }
  return esc(title);
}

/**
 * Emphasis on a LIGHT ground.
 *
 * Accent-coloured TEXT is the simpler answer and the one the in-body cards use,
 * but it only works if the accent is legible on the ground: brand red on white
 * measures 5.15:1, IWC's yellow about 1.2:1. So when the accent cannot carry
 * type, fall back to swiping it behind `dark` words instead.
 *
 * IWC's history, kept because it is the reason the fallback exists: the first
 * attempt tinted the highlight sage, which measured 2.8:1 against the pale
 * ground and proof.py refused the whole set. Darkening the sage far enough to
 * pass (#616c50) reached 5.2:1 but turned the highlight into a muddy olive that
 * no longer read as emphasis. The marker keeps the text at 13.7:1 and is
 * unmistakable at listing size.
 *
 * box-decoration-break:clone so the swipe repeats on a wrapped second line
 * rather than stretching across the gap between them.
 */
function emphasisOnLight(b) {
  // The question is NOT "can the accent carry text". Red on white can (5.15:1),
  // and answering that one gave the ER cards a coloured word and no graphic at
  // all, which is most of why they read flatter than the IWC set beside them.
  // The question is whether the brand DARK can sit ON the accent, because that
  // is what lets the accent become a slab BEHIND the words: IWC green on yellow
  // is 10.58:1, ER navy on red is 3.16:1. When it cannot, the accent goes UNDER
  // the words instead. Either way the headline gains a shape.
  if (contrast(b.dark, b.accent) >= AA) {
    return `
h1 em{color:var(--dark);padding:0 .05em;
      background:linear-gradient(180deg,rgba(0,0,0,0) 52%,var(--accent) 52%);
      -webkit-box-decoration-break:clone;box-decoration-break:clone}
`;
  }
  // The accent cannot go behind the words, so it stays IN them. An underline was
  // tried instead and read quieter than the coloured word it replaced - it bought
  // a shape by selling the only saturated colour on a white card. The shape comes
  // from the kicker chip below instead, so this brand gets both.
  return `h1 em{color:var(--accent)}`;
}

/**
 * The kicker, which is where a two-colour brand can afford a SHAPE.
 *
 * A brand whose dark sits happily on its accent already has one - the slab behind
 * its headline words - so its kicker stays plain type, and IWC's output does not
 * move. A brand that cannot do that has a headline made only of coloured words,
 * and on a white ground that is flat. Filling the kicker gives it an object: an
 * accent block with `on_accent` type in it, which is the "callout strip, white
 * text inside" every ER skill lists as a component of the brand rather than
 * anything invented here.
 */
function kickerChip(b) {
  if (contrast(b.dark, b.accent) >= AA) return '';
  return `
.kicker{background:var(--accent);color:var(--on-accent) !important;
        align-self:flex-start;padding:9px 15px 8px;letter-spacing:.14em}
`;
}

/**
 * Emphasis on the DARK ground, and the kicker that sits above it.
 *
 * IWC's yellow reads at 10.58:1 on its green, so it is simply the text colour.
 * Brand red on navy is 3.16:1 - fine for a graphic, not for type - so the words
 * stay white and the accent becomes an UNDERLINE beneath them. Same gradient
 * trick as the marker, just a thin bar at the baseline.
 */
function emphasisOnDark(b) {
  if (contrast(b.accent, b.dark) >= AA) {
    return { kicker: 'var(--accent)', em: `h1 em{color:var(--accent)}` };
  }
  return {
    kicker: '#fff',
    em: `
h1 em{color:#fff;
      background:linear-gradient(180deg,rgba(0,0,0,0) 88%,var(--accent) 88%);
      -webkit-box-decoration-break:clone;box-decoration-break:clone}
`,
  };
}

/**
 * `photo_fit: "center"` - the framing an OBJECT wants.
 *
 * The default is `bottom center` with a pool shadow beneath, which is right for a
 * cutout person: they stand on the canvas and run off the bottom edge. An object
 * stands nowhere. Anchored to the bottom it sinks into the lower corner with all
 * the air above it; centred it reads as a still life. The pool goes with it,
 * because a shadow under a floating object is a shadow with nothing to sit on.
 */
const OBJECT_FRAMING = (c) => (c.photo_fit === 'center'
  ? '.photo{object-position:center}\n    .pool{display:none}'
  : '');

/**
 * The circular frame, for the ER brands.
 *
 * Their palette is two colours with every decorative hue banned, so the set had
 * no shape of its own beyond the type - and a rectangular object photograph
 * inside a rectangular tile compounded that. A disc is a shape the brand can own
 * without inventing a colour: white disc, brand-red ring, on either ground.
 *
 * It crops hard - the inscribed circle keeps about 79% of its box and discards
 * the corners - so `focus` matters, and makefeatured.mjs measures whether the
 * subject actually survived rather than trusting the crop.
 */
function frameBody(isDark) {
  return (c, b) => {
    const inner = innerDiameter();
    const { fx, fy } = parseFocus(c.focus || 'center');  // throws early on a typo
    const left = CIRCLE.cx - CIRCLE.d / 2;
    const top = CIRCLE.cy - CIRCLE.d / 2;
    const fr = frameStyle(c.frame || 'b');
    const id = 'fr';
    const ctx = {
      size: CIRCLE.d, dark: isDark, id, photo: photoUri(c.photo),
      red: b.accent,
      ground: isDark ? b.dark : b.ground,
      logo: c.frame === 'k' ? logoUri(b, isDark ? 'white' : 'mark') : null,
    };
    const scope = (css) => css.replace(SCOPED, `#${id} .$1`);
    return head(b, `
    body{background:${isDark ? 'var(--dark)' : 'var(--ground)'}}
    ${isDark ? `.glow{position:absolute;width:760px;height:760px;border-radius:50%;
          background:radial-gradient(circle,var(--glow) 0%,rgba(0,0,0,0) 70%);
          top:-90px;right:-140px}` : ''}
    #${id}{position:absolute;left:${left}px;top:${top}px;
           width:${CIRCLE.d}px;height:${CIRCLE.d}px}
    #${id} .fwrap{position:relative;width:100%;height:100%}
    #${id} .f{position:relative;width:100%;height:100%;
           background:${c.frame_fill === 'dark' ? 'var(--dark)'
             : isDark ? 'var(--surface)' : 'var(--icon-bg)'};
           box-shadow:0 18px 44px ${isDark ? 'rgba(0,0,0,.35)' : b.shadow};
           ${fr.css ? fr.css(ctx) : ''}}
    ${fr.wrap ? `#${id} .fwrap{${fr.wrap(ctx)}}` : ''}
    ${fr.extraCss ? scope(fr.extraCss(ctx)) : ''}
    /* cover is a window onto a photograph; inscribe is an object held whole
       inside the frame. No backticks in here: this comment sits inside a
       template literal and one would end it. */
    #${id} .f img{display:block;${c.photo_fit === 'inscribe'
      ? `width:${inscribedSquare()}px;height:${inscribedSquare()}px;object-fit:contain;`
        + `margin:${(inner - inscribedSquare()) / 2}px`
      : `width:100%;height:100%;object-fit:cover;`
        + `object-position:${fx * 100}% ${fy * 100}%`}}
    .wrap.copy{position:absolute;top:0;left:0;width:640px;height:${H}px;
          display:flex;flex-direction:column;justify-content:center;padding:0 0 0 68px}
    .kicker{color:${isDark ? emphasisOnDark(b).kicker : 'var(--mid)'};margin-bottom:20px}
    ${kickerChip(b)}
    h1{color:${isDark ? '#fff' : 'var(--dark)'}}
    .sub{color:${isDark ? 'var(--on-dark-soft)' : 'var(--ink)'};margin-top:24px;max-width:470px}
    ${isDark ? emphasisOnDark(b).em : emphasisOnLight(b)}
  `) + `
    ${isDark ? '<div class="glow"></div>' : ''}
    <div id="${id}">
      ${fr.back ? fr.back(ctx) : ''}
      ${fr.svg ? fr.svg(ctx) : `<div class="fwrap"><div class="f"><img src="${photoUri(c.photo)}" alt=""></div></div>`}
      ${fr.over ? fr.over(ctx) : ''}
    </div>
    <div class="accent"></div>
    <div class="wrap copy">
      ${c.kicker ? `<div class="kicker">${esc(c.kicker)}</div>` : ''}
      <h1>${headline(c.title)}</h1>
      ${c.sub ? `<div class="sub">${esc(c.sub)}</div>` : ''}
    </div>`;
  };
}

const BODIES = {
  /**
   * Headline on the left over the light ground, subject bleeding off the right.
   * The default. Left-weighted text survives the square crops that some feeds
   * apply to the right edge.
   */
  'photo-right': (c, b) => head(b, `
    body{background:var(--ground)}
    .wrap{position:absolute;top:0;left:0;width:660px;height:${H}px;
          display:flex;flex-direction:column;justify-content:center;padding:0 0 0 68px}
    .kicker{color:var(--mid);margin-bottom:20px}
    ${kickerChip(b)}
    h1{color:var(--dark)}
    .sub{color:var(--ink);margin-top:24px;max-width:520px}
    /* ONE ground colour, edge to edge. This layout used to put a pale green
       band behind the subject, which made the frame read as two images stitched
       together at a hard vertical seam - obvious in a grid where the tile next
       to it had a single ground. The subject now sits directly on the canvas and
       only the pool shadow separates it. */
    .pool{width:430px;height:74px;background:var(--dark);opacity:.15;
          bottom:44px;right:78px;filter:blur(26px)}
    .photo{right:${c.photo_fit === 'center' ? 0 : (c.photo_inset || 0) - 10}px;
           bottom:${c.photo_inset || 0}px;
           height:${H - (c.photo_inset || 0) * 2}px;width:${560 - (c.photo_inset || 0)}px}
    ${OBJECT_FRAMING(c)}
    ${emphasisOnLight(b)}
  `) + `
    <div class="pool"></div><div class="accent"></div>
    <img class="photo" src="${photoUri(c.photo)}" alt="">
    <div class="wrap">
      ${c.kicker ? `<div class="kicker">${esc(c.kicker)}</div>` : ''}
      <h1>${headline(c.title)}</h1>
      ${c.sub ? `<div class="sub">${esc(c.sub)}</div>` : ''}
    </div>`,

  /**
   * Dark ground, subject to the right, headline reversed out in white with the
   * brand yellow as the highlight. The strongest of the set at listing size,
   * because a dark tile stands out in a grid of white article cards.
   */
  'photo-dark': (c, b) => head(b, `
    body{background:var(--dark)}
    .glow{position:absolute;width:760px;height:760px;border-radius:50%;
          background:radial-gradient(circle,var(--glow) 0%,rgba(0,0,0,0) 70%);
          top:-90px;right:-140px}
    .wrap{position:absolute;top:0;left:0;width:640px;height:${H}px;
          display:flex;flex-direction:column;justify-content:center;padding:0 0 0 68px}
    .kicker{color:${emphasisOnDark(b).kicker};margin-bottom:20px}
    ${kickerChip(b)}
    h1{color:#fff}
    .sub{color:var(--on-dark-soft);margin-top:24px;max-width:500px}
    .pool{width:430px;height:74px;background:#000;opacity:.34;
          bottom:40px;right:82px;filter:blur(30px)}
    .photo{right:${c.photo_fit === 'center' ? 0 : (c.photo_inset || 0) - 6}px;
           bottom:${c.photo_inset || 0}px;
           height:${H - (c.photo_inset || 0) * 2}px;width:${560 - (c.photo_inset || 0)}px}
    ${OBJECT_FRAMING(c)}
    ${emphasisOnDark(b).em}
  `) + `
    <div class="glow"></div><div class="pool"></div><div class="accent"></div>
    <img class="photo" src="${photoUri(c.photo)}" alt="">
    <div class="wrap">
      ${c.kicker ? `<div class="kicker">${esc(c.kicker)}</div>` : ''}
      <h1>${headline(c.title)}</h1>
      ${c.sub ? `<div class="sub">${esc(c.sub)}</div>` : ''}
    </div>`,

  /**
   * The photo as a PANEL, uncut, cover-cropped into the right-hand third.
   *
   * The workhorse, and the answer to the fact that most clinical photography is
   * scene photography: two people in a treatment room, a light subject against a
   * light wall. Cutting that out produces a floating fragment - a head with no
   * body - because there is no single salient object to find. A panel keeps the
   * scene intact and still gives the headline a clean field to sit on. Reach for
   * a cutout only when the photo has one obvious subject on a plain ground.
   */
  'photo-panel': (c, b) => head(b, `
    body{background:var(--ground)}
    .panel{position:absolute;top:0;right:0;width:560px;height:${H}px;overflow:hidden}
    /* The fade is a MASK on the photograph, not a tinted panel laid over it.
       The overlay version washed the left of the photo into a pale smear that
       read as a printing fault, because it painted ground-coloured pixels ON TOP
       of the image instead of dissolving the image into the ground. Masking
       makes the photo genuinely transparent at its inner edge, so whatever the
       canvas is behind it shows through cleanly. */
    .panel img{width:100%;height:100%;object-fit:cover;
               object-position:${c.focus || 'center'};
               -webkit-mask-image:linear-gradient(90deg,transparent 0,#000 26%);
               mask-image:linear-gradient(90deg,transparent 0,#000 26%)}
    .wrap{position:absolute;top:0;left:0;width:660px;height:${H}px;z-index:3;
          display:flex;flex-direction:column;justify-content:center;padding:0 0 0 68px}
    .kicker{color:var(--mid);margin-bottom:20px}
    ${kickerChip(b)}
    h1{color:var(--dark);font-size:70px}
    .sub{color:var(--ink);margin-top:24px;max-width:500px}
    ${emphasisOnLight(b)}
  `) + `
    <div class="panel"><img src="${photoUri(c.photo)}" alt=""></div>
    <div class="accent"></div>
    <div class="wrap">
      ${c.kicker ? `<div class="kicker">${esc(c.kicker)}</div>` : ''}
      <h1>${headline(c.title)}</h1>
      ${c.sub ? `<div class="sub">${esc(c.sub)}</div>` : ''}
    </div>`,

  /**
   * No photo, on the DARK ground. `type` above, in the brand's dark.
   *
   * Written for the ER brands and it is their default, not their fallback: those
   * posts cannot show a person (see the `er` profile in image-policy.json), the
   * question in the headline IS the subject on a triage post, and a navy tile
   * stands out in a listing grid of white cards exactly as photo-dark does. The
   * soft shapes are `glow` rather than the tints, because a brand whose tints are
   * a near-white grey would paint them invisibly on its own dark.
   */
  'type-dark': (c, b) => head(b, `
    body{background:var(--dark)}
    .b1{position:absolute;width:620px;height:520px;border-radius:50%;
        background:var(--glow);opacity:.5;top:-190px;right:-120px}
    .b2{position:absolute;width:420px;height:360px;border-radius:50%;
        background:var(--glow);opacity:.32;bottom:-150px;left:-90px}
    .wrap{position:absolute;inset:0;display:flex;flex-direction:column;
          justify-content:center;padding:0 110px 0 68px}
    .kicker{color:${emphasisOnDark(b).kicker};margin-bottom:22px}
    ${kickerChip(b)}
    h1{color:#fff;font-size:82px}
    .sub{color:var(--on-dark-soft);margin-top:26px;max-width:760px}
    ${emphasisOnDark(b).em}
  `) + `
    <div class="b1"></div><div class="b2"></div><div class="accent"></div>
    <div class="wrap">
      ${c.kicker ? `<div class="kicker">${esc(c.kicker)}</div>` : ''}
      <h1>${headline(c.title)}</h1>
      ${c.sub ? `<div class="sub">${esc(c.sub)}</div>` : ''}
    </div>`,

  /**
   * No photo. Every post can have this one, which matters more than it sounds:
   * the constraint on covering the blog is finding 40 usable photographs, not
   * rendering 40 images. A typographic featured image is also the honest option
   * for a clinical topic where a stock photo would imply a patient or a provider
   * who is not real.
   */
  'photo-frame': frameBody(false),
  'photo-frame-dark': frameBody(true),

  'type': (c, b) => head(b, `
    body{background:var(--ground)}
    .b1{position:absolute;width:620px;height:520px;border-radius:50%;
        background:${b.featured_shapes[0]};opacity:.55;top:-190px;right:-120px}
    .b2{position:absolute;width:420px;height:360px;border-radius:50%;
        background:${b.featured_shapes[1]};opacity:.6;bottom:-150px;left:-90px}
    .wrap{position:absolute;inset:0;display:flex;flex-direction:column;
          justify-content:center;padding:0 110px 0 68px}
    .kicker{color:var(--mid);margin-bottom:22px}
    ${kickerChip(b)}
    h1{color:var(--dark);font-size:82px}
    .sub{color:var(--ink);margin-top:26px;max-width:760px}
    ${emphasisOnLight(b)}
  `) + `
    <div class="b1"></div><div class="b2"></div><div class="accent"></div>
    <div class="wrap">
      ${c.kicker ? `<div class="kicker">${esc(c.kicker)}</div>` : ''}
      <h1>${headline(c.title)}</h1>
      ${c.sub ? `<div class="sub">${esc(c.sub)}</div>` : ''}
    </div>`,
};

export const LAYOUTS = Object.keys(BODIES);

export function render(spec) {
  const fn = BODIES[spec.layout || 'type'];
  if (!fn) {
    throw new Error(`${spec.file}: unknown featured layout "${spec.layout}". ` +
                    `Known: ${LAYOUTS.join(', ')}`);
  }
  // Default 'iwc' because the five specs written before this file went
  // multi-brand carry no brand key and must keep rendering exactly as they did.
  const slug = spec.brand || 'iwc';
  const b = brand(slug);

  // The circular frame is an ER device. It exists because that palette is two
  // colours with every decorative hue banned, which left those cards with no
  // shape of their own; IWC has a yellow marker, hued corner washes and a human
  // subject, and a disc on top of that is one device too many. Enforced from
  // image-policy.json's brand map so there is no second list to drift.
  if (String(spec.layout || '').startsWith('photo-frame') && profileFor(slug) !== 'er') {
    throw new Error(
      `${spec.file}: layout "${spec.layout}" is for the ER brands only, and ` +
      `"${slug}" is profile "${profileFor(slug)}". Use photo-right, photo-dark, ` +
      `photo-panel or type.`
    );
  }
  // A framed photo has to FILL its disc and stop at it. `inscribe` fits the whole
  // rectangle inside the circle, so it cannot do that at any crop point - the
  // frame ends up reading as one object and the picture as another. Refused here
  // rather than caught at review, because it came back twice.
  if (String(spec.layout || '').startsWith('photo-frame') && spec.photo_fit === 'inscribe') {
    throw new Error(
      `${spec.file}: photo_fit "inscribe" cannot fill a frame - it fits the whole ` +
      `image INSIDE the disc, which leaves the gap it is meant to avoid. Either ` +
      `crop the subject so "cover" fills the disc, or use photo-panel, which is ` +
      `the layout for an object held whole.`
    );
  }
  return fn(spec, b) + (spec.logo ? logoMarkup(b, spec.logo) : '');
}
