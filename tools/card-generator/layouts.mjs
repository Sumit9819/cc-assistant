/**
 * Layout library for irvingwellnessclinic in-body infographics.
 *
 * One shared shell (ground, soft shapes, title) and a body per layout, so a new
 * shape is a new function rather than a new file. Adding "timeline" or
 * "checklist" later means one more entry in BODIES.
 *
 * Every layout takes its content from a spec entry. Content stays data, which
 * is what keeps the Canva-era defects (duplicate labels, typos baked into
 * pixels) from being expressible.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { brand, rootVars } from './brands.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));

export const esc = (s) =>
  String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export function iconBody(name) {
  const file = path.join(HERE, 'icons', `${name}.svg`);
  if (!fs.existsSync(file)) throw new Error(`Missing icon: ${name}.svg`);
  const inner = fs
    .readFileSync(file, 'utf8')
    .replace(/^[\s\S]*?<svg[^>]*>/, '')
    .replace(/<\/svg>\s*$/, '');
  if (!inner.trim()) throw new Error(`Empty icon: ${name}.svg`);
  return inner.trim();
}

/**
 * The brand in force for the card currently being rendered.
 *
 * Module-level rather than threaded through all ten layout functions: make.mjs
 * renders strictly one card at a time, and passing a brand into every shell()
 * call would have touched every layout for no behavioural gain. render() sets
 * it from the card's own `brand` field, defaulting to iwc so every existing
 * spec keeps working untouched.
 */
let B = brand('iwc');

export function setBrand(slug) {
  B = brand(slug || 'iwc');
  return B;
}

/**
 * How much of a circular disc the icon inside it fills.
 *
 * The steps and icons discs used to hardcode an icon at ~47% of the disc
 * diameter, which left 30 to 54px of dead air on every side and read as a
 * glyph lost inside an empty ring. The list layout has always drawn its 46px
 * icon circle at 30px, i.e. 65%, and that one has never drawn a complaint
 * across 58 cards, so 65% is taken from inside the system rather than picked.
 *
 * Do not push this past ~0.70: the largest square that fits inside a circle is
 * diameter / sqrt(2) = 70.7% of it, so an icon whose glyph reaches the edge of
 * its 24-unit viewBox starts to clip the disc past that. Stroke width is in
 * viewBox units, so a bigger icon keeps the same relative weight.
 */
const ICON_IN_DISC = 0.65;

const ico = (name, size) =>
  `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="${B.dark}"
        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${iconBody(name)}</svg>`;

/**
 * Frame geometry.
 *
 * WIDTH IS FIXED at 1200px and is never negotiable: it is the width every
 * consumer assumes (Open Graph, Google Images, the post content column), and
 * the type scale, disc sizes and column arithmetic in every layout below are
 * tuned to it. A card of another width would be a different design system.
 *
 * HEIGHT IS A FLOOR, not a fixed value. 630px is the Open Graph minimum and
 * the shortest a card may be; a card whose content needs more room grows
 * downward instead of shrinking its type to fit. That is the whole reason this
 * exists: a 6-row comparison used to be crushed into 62px rows to stay inside
 * one 628px frame, which made the card less readable than the paragraph it was
 * meant to replace.
 *
 * make.mjs measures each rendered card and calls setHeight() before the final
 * screenshot, so layouts do not have to reason about the frame at all: content
 * is positioned from the top and the frame is sized around it afterwards.
 */
export const FRAME_W = 1200;
export const MIN_H = 630;

let H = MIN_H;

export function setHeight(h) {
  // Even numbers only: the render is 2x and towebp.py halves it, so an odd
  // frame would land on a half pixel.
  H = Math.max(MIN_H, Math.ceil(Number(h) || MIN_H));
  if (H % 2) H += 1;
  return H;
}

export const frameHeight = () => H;

const base = () => `
${rootVars(B)}
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:${FRAME_W}px;height:${H}px}
body{background:var(--ground);font-family:${B.font},system-ui,sans-serif;position:relative;overflow:hidden}
.blob{position:absolute;border-radius:50%;opacity:.5}
.b1{width:520px;height:420px;background:var(--t1);top:-170px;right:-100px}
.b2{width:380px;height:320px;background:var(--t2);bottom:-140px;left:-100px}
.b3{width:230px;height:200px;background:var(--t3);bottom:-80px;right:200px;opacity:.5}
h1{position:absolute;left:0;width:100%;text-align:center;font-weight:700;
   letter-spacing:-.5px;color:var(--dark);padding:0 70px}
h1 em{font-style:normal;color:var(--accent)}
.logo{position:absolute;height:auto;opacity:.92}
`;

/**
 * Logo as a base64 data URI rather than a file:// path.
 *
 * Chromium is inconsistent about local subresources under file://, and a logo
 * that silently fails to load would ship as a blank corner without erroring.
 * Inlining removes the failure mode entirely.
 */
let logoCache = null;
function logoDataUri(variant) {
  logoCache = logoCache || {};
  // Keyed by brand as well as variant: two brands both have a "mark" and the
  // cache would otherwise serve the first one's file to the second.
  const key = `${B.name}:${variant}`;
  if (!logoCache[key]) {
    const file = B.logo[variant];
    if (!file) {
      throw new Error(
        `Brand "${B.name}" has no "${variant}" logo. Available: ` +
        `${Object.keys(B.logo).join(', ') || '(none)'}`);
    }
    const p = path.join(HERE, 'brand', file);
    if (!fs.existsSync(p)) throw new Error(`Missing logo file: brand/${file}`);
    logoCache[key] = `data:image/png;base64,${fs.readFileSync(p).toString('base64')}`;
  }
  return logoCache[key];
}

const LOGO_POS = {
  'top-left': 'top:40px;left:48px',
  'top-right': 'top:40px;right:48px',
  'bottom-left': 'bottom:38px;left:48px',
  'bottom-right': 'bottom:38px;right:48px',
  'bottom-center': 'bottom:36px;left:50%;transform:translateX(-50%)',
};

export function logoMarkup(logo) {
  const pos = LOGO_POS[logo.position] || LOGO_POS['bottom-right'];
  return `<img class="logo" src="${logoDataUri(logo.variant || 'green')}"
               style="${pos};width:${logo.width || 168}px">`;
}

function shell(css, body, title, titleTop = 62, titleSize = 42) {
  const heading = title
    // Coalesce every part: a two-element title used to render the literal
    // string "undefined" as its tail, because esc(undefined) stringifies.
    ? `<h1 style="top:${titleTop}px;font-size:${titleSize}px">${esc(title[0] ?? '')}<em>${esc(title[1] ?? '')}</em>${esc(title[2] ?? '')}</h1>`
    : '';
  return `<meta charset="utf-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="${B.font_css}">
<style>${base()}${css}</style>
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
${heading}${body}`;
}

/* ------------------------------------------------------------------ icons */

function icons(card) {
  const n = card.items.length;
  // Real posts do not hand you tidy threes. Five and six item sets are common
  // (six causes of fatigue, five red flags), so wrap into two rows rather than
  // dropping items to fit the template - dropping one is an editorial decision
  // the layout has no business making.
  const wrapRows = n >= 5;
  // Two rows of discs plus labels plus two-line notes needs ~512px and only
  // ~356px exists between the title and the logo zone, so a 5-6 item set with
  // notes overflows by about 150px. `list` is built for exactly that shape,
  // so send it there rather than silently cropping or shrinking to illegible.
  if (wrapRows && card.items.some((i) => i.note)) {
    throw new Error(
      `${card.file}: ${n} icon items with notes will not fit two rows. ` +
      `Use layout "list" for 5-6 items with notes, or drop the notes.`
    );
  }
  const perRow = wrapRows ? Math.ceil(n / 2) : n;
  const disc = wrapRows ? 118 : n === 4 ? 152 : n === 2 ? 196 : 178;
  const gap = wrapRows ? 34 : n === 4 ? 46 : n === 2 ? 120 : 92;
  const size = Math.round(disc * ICON_IN_DISC);
  const labelSize = wrapRows ? 20 : n === 4 ? 24 : 27;
  const lineH = Math.round(labelSize * 1.2);
  const hasNotes = card.items.some((i) => i.note);
  const col = disc + 46;
  // Reserve two lines for every label once one of them wraps, or the wrapped
  // column pushes its rule and note below the others and the row stops aligning.
  const wraps = card.items.some((i) => i.label.length * labelSize * 0.56 > col);

  const body = `<div class="row">${card.items
    .map(
      (it) => `<div class="item">
      <div class="disc">${ico(it.icon, size)}</div>
      <div class="label">${esc(it.label)}</div><div class="rule"></div>
      ${it.note ? `<div class="note">${esc(it.note)}</div>` : ''}
    </div>`
    )
    .join('')}</div>`;

  return shell(
    `.row{position:absolute;top:${wrapRows ? 176 : hasNotes ? 190 : 226}px;left:0;width:100%;
          display:flex;flex-wrap:wrap;justify-content:center;
          gap:${wrapRows ? '34px ' + gap + 'px' : gap + 'px'};
          max-width:${wrapRows ? perRow * (col + gap) : 1200}px;margin:0 auto}
     .item{width:${col}px;display:flex;flex-direction:column;align-items:center}
     .disc{width:${disc}px;height:${disc}px;border-radius:50%;border:2px solid var(--mid);
           background:rgba(255,255,255,.66);display:flex;align-items:center;justify-content:center}
     .label{margin-top:24px;font-weight:600;font-size:${labelSize}px;color:var(--dark);
            text-align:center;line-height:1.2;min-height:${wraps ? lineH * 2 : lineH}px;
            display:flex;align-items:center;justify-content:center}
     .rule{width:44px;height:3px;background:var(--accent);margin-top:11px;border-radius:2px}
     .note{margin-top:13px;font-weight:500;font-size:16px;line-height:1.42;
           color:var(--ink);text-align:center;max-width:${col - 12}px}`,
    body,
    card.title,
    hasNotes ? 62 : 84
  );
}

/* ---------------------------------------------------------------- compare */

function compare(card) {
  // Built as three self-contained column cards rather than a grid of loose
  // cells. The first pass had a dark chip floating over detached white boxes,
  // so a column never read as one object. Header and body now share a border
  // and a radius, the row rail is tied to the rows by a yellow marker, and the
  // block is sized to fill the frame instead of leaving a dead band beneath it.
  // Rows are sized for LEGIBILITY, not to fit a fixed frame. They used to
  // shrink with the count - a 5-row table dropped to 62px rows and 16px cells
  // so the whole board stayed inside 628px - which made the graphic harder to
  // read than the paragraph it replaced. Now the frame grows instead (see
  // MIN_H above), so a long comparison gets a taller card at full size.
  // Two-row tables keep their more generous 100px row; everything from three
  // rows up shares one sizing, so a 3-row card is unchanged from before.
  const nRows = card.rows.length;
  const HEAD = 64;
  const ROW = nRows <= 2 ? 100 : 88;
  const TOP = 180;
  const cellFont = 19;
  const headFont = 23;

  const rail = `<div class="rail"><div class="spacer"></div>${card.rows
    .map((r) => `<div class="rl"><span>${esc(r.label)}</span><i></i></div>`)
    .join('')}</div>`;

  const cols = card.columns
    .map(
      (name, ci) => `<div class="col">
        <header>${esc(name)}</header>
        ${card.rows.map((r) => `<div class="c">${esc(r.values[ci])}</div>`).join('')}
      </div>`
    )
    .join('');

  return shell(
    `.board{position:absolute;top:${TOP}px;left:62px;right:62px;display:flex;gap:18px;align-items:flex-start}
     .rail{width:156px;flex:0 0 156px}
     .rail .spacer{height:${HEAD}px}
     .rl{height:${ROW}px;display:flex;align-items:center;justify-content:flex-end;gap:11px}
     .rl span{font-weight:600;font-size:19px;color:var(--label);text-align:right;line-height:1.25}
     .rl i{width:9px;height:9px;border-radius:2px;background:var(--accent);flex:0 0 9px}
     .col{flex:1;background:var(--surface);border:1.5px solid var(--line);border-radius:14px;overflow:hidden;
          box-shadow:0 2px 12px var(--shadow)}
     .col header{height:${HEAD}px;background:var(--dark);color:#fff;font-weight:600;font-size:${headFont}px;
                 display:flex;align-items:center;justify-content:center;letter-spacing:.2px;text-align:center;padding:0 10px}
     .c{height:${ROW}px;display:flex;align-items:center;justify-content:center;padding:0 14px;
        text-align:center;font-weight:500;font-size:${cellFont}px;line-height:1.3;color:var(--dark)}
     .c + .c{border-top:1px solid var(--hair)}`,
    `<div class="board">${rail}${cols}</div>`,
    card.title,
    62,
    40
  );
}

/* ------------------------------------------------------------------ steps */

function steps(card) {
  // Four-step flows overflow at the three-step sizing, so scale the column and
  // disc with the count instead of assuming three.
  const n = card.steps.length;
  const colW = n >= 4 ? 206 : 264;
  const discW = n >= 4 ? 112 : 132;
  const iconW = Math.round(discW * ICON_IN_DISC);
  const stepGap = n >= 4 ? 12 : 26;
  const body = card.steps
    .map(
      (s, i) => `<div class="step">
        <div class="disc">${ico(s.icon, iconW)}<div class="num">${i + 1}</div></div>
        <div class="label">${esc(s.label)}</div>
        <div class="note">${esc(s.note)}</div>
      </div>${i < n - 1 ? `<div class="arrow"><svg viewBox="0 0 40 24" width="${n >= 4 ? 30 : 40}" height="24" fill="none" stroke="${B.mid}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h34M28 4l8 8-8 8"/></svg></div>` : ''}`
    )
    .join('');

  return shell(
    `.flow{position:absolute;top:212px;left:0;width:100%;
           display:flex;justify-content:center;align-items:flex-start;gap:${stepGap}px}
     .step{width:${colW}px;display:flex;flex-direction:column;align-items:center}
     .disc{width:${discW}px;height:${discW}px;border-radius:50%;border:2px solid var(--mid);
           background:rgba(255,255,255,.75);display:flex;align-items:center;justify-content:center;
           position:relative}
     .num{position:absolute;top:-6px;left:-6px;width:44px;height:44px;border-radius:50%;
          background:var(--accent);color:var(--on-accent);font-weight:700;font-size:22px;
          display:flex;align-items:center;justify-content:center}
     .label{margin-top:22px;font-weight:600;font-size:${n >= 4 ? 23 : 26}px;color:var(--dark)}
     .note{margin-top:11px;font-weight:500;font-size:${n >= 4 ? 16 : 17}px;line-height:1.45;
           color:var(--ink);text-align:center;max-width:${colW - 22}px}
     .arrow{margin-top:${n >= 4 ? 48 : 56}px;flex:0 0 ${n >= 4 ? 30 : 40}px}`,
    `<div class="flow">${body}</div>`,
    card.title,
    66
  );
}

/* ------------------------------------------------------------------- stat */

function stat(card) {
  // The dot grid reads as "this many out of that many", so it only belongs on a
  // proportion. On a count like "6 to 9 sessions" it rendered nine filled dots
  // and implied a ratio that does not exist. Omit it unless a proportion is
  // actually being shown, and centre the figure instead.
  const hasDots = card.dots && card.dots.total >= 20;
  const dots = hasDots
    ? Array.from({ length: card.dots.total }, (_, i) =>
        `<span class="${i < card.dots.filled ? 'd on' : 'd'}"></span>`
      ).join('')
    : '';

  return shell(
    `.wrap{position:absolute;top:170px;left:78px;right:78px;display:flex;
           align-items:center;justify-content:${hasDots ? 'space-between' : 'center'};gap:56px;
           text-align:${hasDots ? 'left' : 'center'}}
     .left{width:${hasDots ? 560 : 900}px}
     .big{font-weight:700;font-size:${hasDots ? 132 : 118}px;line-height:.92;color:var(--dark);letter-spacing:-4px}
     .gloss{display:inline-block;margin-top:14px;background:var(--accent);color:var(--on-accent);
            font-weight:700;font-size:21px;padding:7px 16px;border-radius:7px}
     .line{margin-top:20px;font-weight:500;font-size:25px;line-height:1.4;color:var(--ink);
           max-width:${hasDots ? 520 : 760}px;${hasDots ? '' : 'margin-left:auto;margin-right:auto;'}}
     .src{margin-top:22px;font-weight:500;font-size:16px;color:var(--muted)}
     /* Sized so a 10-row grid ends clear of the logo keep-out zone. At 28px
        with 12px gaps it ran to y=558 and the mark sits at y=556, which read as
        a collision. 24/10 ends at y=500. */
     .dots{width:330px;display:grid;grid-template-columns:repeat(10,1fr);gap:10px}
     .d{width:24px;height:24px;border-radius:50%;background:var(--dot)}
     .d.on{background:var(--dark)}`,
    `<div class="wrap">
       <div class="left">
         <div class="big">${esc(card.stat)}</div>
         <div class="gloss">${esc(card.gloss)}</div>
         <div class="line">${esc(card.statLine)}</div>
         <div class="src">${esc(card.source)}</div>
       </div>
       ${hasDots ? `<div class="dots">${dots}</div>` : ''}
     </div>`,
    card.title,
    62,
    40
  );
}

/* ------------------------------------------------------------------ quote */

function quote(card) {
  return shell(
    `.qwrap{position:absolute;top:0;left:0;width:100%;height:100%;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:0 116px;text-align:center}
     .mark{font-family:Georgia,serif;font-size:132px;line-height:.6;color:var(--accent);
           height:66px}
     .q{margin-top:22px;font-weight:500;font-size:31px;line-height:1.46;color:var(--dark)}
     .rule{width:64px;height:3px;background:var(--accent);margin:30px 0 20px;border-radius:2px}
     .who{font-weight:700;font-size:23px;color:var(--dark)}
     .role{margin-top:7px;font-weight:500;font-size:17px;color:var(--ink)}`,
    `<div class="qwrap">
       <div class="mark">&ldquo;</div>
       <div class="q">${esc(card.quote)}</div>
       <div class="rule"></div>
       <div class="who">${esc(card.attribution)}</div>
       <div class="role">${esc(card.role)}</div>
     </div>`,
    null
  );
}

/* -------------------------------------------------------------- checklist */

function checklist(card) {
  // tone "contrast" (default) is do-this / avoid-that. tone "neutral" is for two
  // groups that are simply different, not opposed - before and after a session,
  // for instance, where colouring one side amber would wrongly read as a
  // warning. Each side can also name its own icon.
  const neutral = card.tone === 'neutral';
  // Which panel carries the emphasis colour. Default 'bad' keeps every existing
  // card identical. 'good' exists because on an emergency brand the accent red
  // is an URGENCY signal, not a "wrong answer" signal: a triage card wants the
  // ER column red and the urgent-care column calm, while still reading ER first
  // from the left. Without this the card said "Urgent care is enough" in alarm
  // red and "Come to the ER" in calm navy, which is the opposite of the advice.
  const urgent = card.urgentSide === 'good' ? 'good' : 'bad';
  const side = (data, kind) => `<div class="panel ${kind}">
      <header>${ico(data.icon || (kind === 'good' ? 'circle-check' : 'circle-x'), 26)}<span>${esc(data.heading)}</span></header>
      ${data.items.map((t) => `<div class="li"><i></i><span>${esc(t)}</span></div>`).join('')}
    </div>`;

  return shell(
    `.pair{position:absolute;top:170px;left:62px;right:62px;display:flex;gap:26px;align-items:stretch}
     .panel{flex:1;background:var(--surface);border:1.5px solid var(--line);border-radius:16px;overflow:hidden;
            box-shadow:0 2px 12px var(--shadow);display:flex;flex-direction:column}
     .panel header{display:flex;align-items:center;gap:11px;padding:17px 22px;
                   font-weight:700;font-size:22px;color:#fff}
     .panel header svg{stroke:#fff}
     .good header{background:${neutral || urgent !== 'good' ? 'var(--dark)' : 'var(--warn-head)'}}
     /* The "avoid" side takes the brand's warn-head token. On IWC that is a
        darkened yellow, because that palette has no red and inventing one for a
        single card would be off-system; on ER of Irving it is the brand red,
        which is what an emergency palette wants there anyway. Under tone
        "neutral" both sides take --dark, because the two groups are simply
        different rather than opposed. */
     .bad header{background:${neutral || urgent === 'good' ? 'var(--dark)' : 'var(--warn-head)'}}
     .li{display:flex;align-items:flex-start;gap:13px;padding:15px 22px;
         font-weight:500;font-size:19px;line-height:1.38;color:var(--dark)}
     .li + .li{border-top:1px solid var(--hair)}
     .li i{width:9px;height:9px;border-radius:2px;margin-top:8px;flex:0 0 9px}
     /* The bullets follow the SAME side as the header. They used to be pinned
        to good/bad, so flipping the emphasis left the calm column with alarm-red
        bullets under a navy header. */
     .good .li i{background:${neutral || urgent !== 'good' ? 'var(--dark)' : 'var(--accent)'}}
     .bad .li i{background:${neutral || urgent === 'good' ? 'var(--dark)' : 'var(--accent)'}}`,
    `<div class="pair">${side(card.good, 'good')}${side(card.bad, 'bad')}</div>`,
    card.title,
    62,
    40
  );
}

/* --------------------------------------------------------------- timeline */

function timeline(card) {
  const n = card.nodes.length;
  const nodes = card.nodes
    .map(
      (nd) => `<div class="node">
        <div class="time">${esc(nd.time)}</div>
        <div class="dot"></div>
        <div class="label">${esc(nd.label)}</div>
        <div class="note">${esc(nd.note)}</div>
      </div>`
    )
    .join('');

  return shell(
    `.tl{position:absolute;top:206px;left:56px;right:56px}
     /* Rail inset by half a column so it starts and ends under the outer dots
        rather than running past them into empty space. */
     .rail{position:absolute;top:64px;left:${100 / n / 2}%;right:${100 / n / 2}%;
           height:3px;background:var(--track);border-radius:2px}
     .nodes{position:relative;display:flex}
     .node{flex:1;display:flex;flex-direction:column;align-items:center;padding:0 12px}
     .time{font-weight:700;font-size:19px;color:var(--dark);height:30px}
     .dot{width:26px;height:26px;border-radius:50%;background:var(--accent);
          border:5px solid var(--ground);margin-top:22px}
     .label{margin-top:24px;font-weight:600;font-size:23px;color:var(--dark);text-align:center}
     .note{margin-top:9px;font-weight:500;font-size:17px;line-height:1.42;
           color:var(--ink);text-align:center;max-width:210px}`,
    `<div class="tl"><div class="rail"></div><div class="nodes">${nodes}</div></div>`,
    card.title,
    62,
    40
  );
}

/* ------------------------------------------------------------------ alert */

function alert(card) {
  const signs = card.signs
    .map((s) => `<div class="sign">${ico(s.icon, 30)}<span>${esc(s.text)}</span></div>`).join('');

  return shell(
    `body{background:var(--dark)}
     .blob{display:none}
     .card{position:absolute;inset:0;display:flex;flex-direction:column;
           align-items:center;justify-content:center;padding:0 86px;text-align:center}
     .kick{font-weight:600;font-size:21px;letter-spacing:.16em;text-transform:uppercase;
           color:var(--accent)}
     .big{margin-top:12px;font-weight:700;font-size:96px;line-height:1;color:#fff;letter-spacing:-2px}
     .signs{margin-top:34px;display:flex;gap:16px}
     .sign{background:rgba(255,255,255,.09);border:1.5px solid rgba(255,255,255,.22);
           border-radius:12px;padding:16px 18px;display:flex;flex-direction:column;
           align-items:center;gap:11px;width:290px}
     .sign svg{stroke:var(--accent)}
     .sign span{font-weight:500;font-size:17px;line-height:1.4;color:var(--on-dark)}
     .foot{margin-top:30px;font-weight:600;font-size:19px;color:var(--accent)}`,
    `<div class="card">
       <div class="kick">${esc(card.kicker)}</div>
       <div class="big">${esc(card.headline)}</div>
       <div class="signs">${signs}</div>
       <div class="foot">${esc(card.footer)}</div>
     </div>`,
    null
  );
}

/* --------------------------------------------------------------- numbered */

function numbered(card) {
  const half = Math.ceil(card.items.length / 2);
  const col = (list, start) => `<div class="ncol">${list
    .map(
      (t, i) => `<div class="nrow"><div class="num">${start + i + 1}</div><span>${esc(t)}</span></div>`
    )
    .join('')}</div>`;

  return shell(
    `.cols{position:absolute;top:172px;left:62px;right:62px;display:flex;gap:30px;align-items:flex-start}
     .ncol{flex:1;display:flex;flex-direction:column;gap:13px}
     .nrow{display:flex;align-items:center;gap:15px;background:var(--surface);border:1.5px solid var(--line);
           border-radius:12px;padding:15px 18px;box-shadow:0 2px 9px var(--shadow-soft)}
     .num{width:38px;height:38px;flex:0 0 38px;border-radius:50%;background:var(--accent);
          color:var(--on-accent);font-weight:700;font-size:19px;
          display:flex;align-items:center;justify-content:center}
     .nrow span{font-weight:500;font-size:18px;line-height:1.35;color:var(--dark)}`,
    `<div class="cols">${col(card.items.slice(0, half), 0)}${col(card.items.slice(half), half)}</div>`,
    card.title,
    62,
    40
  );
}

/* ------------------------------------------------------------------- list */

/**
 * One-sided set of 4 to 6 items, each with its own icon.
 *
 * The gap the first nine layouts left: `checklist` needs two opposing columns
 * and `numbered` implies an order these sets do not have. Causes of fatigue and
 * reasons to see a doctor are neither paired nor sequenced.
 */
function list(card) {
  const n = card.items.length;
  const twoCol = n > 4;
  const half = Math.ceil(n / 2);
  const rows = (arr) => arr
    .map(
      (it) => `<div class="lrow">
        <div class="lic">${ico(it.icon, 30)}</div>
        <div class="ltxt"><b>${esc(it.label)}</b>${it.note ? `<span>${esc(it.note)}</span>` : ''}</div>
      </div>`
    )
    .join('');

  const body = twoCol
    ? `<div class="lcols"><div class="lcol">${rows(card.items.slice(0, half))}</div><div class="lcol">${rows(card.items.slice(half))}</div></div>`
    : `<div class="lcols"><div class="lcol">${rows(card.items)}</div></div>`;

  return shell(
    `.lcols{position:absolute;top:174px;left:${twoCol ? 62 : 210}px;right:${twoCol ? 62 : 210}px;
            display:flex;gap:26px}
     .lcol{flex:1;display:flex;flex-direction:column;gap:12px}
     .lrow{display:flex;align-items:center;gap:15px;background:var(--surface);border:1.5px solid var(--line);
           border-radius:12px;padding:14px 18px;box-shadow:0 2px 9px var(--shadow-soft)}
     .lic{width:46px;height:46px;flex:0 0 46px;border-radius:50%;background:var(--icon-bg);
          display:flex;align-items:center;justify-content:center}
     .ltxt{display:flex;flex-direction:column;gap:3px}
     .ltxt b{font-weight:600;font-size:19px;line-height:1.25;color:var(--dark)}
     .ltxt span{font-weight:500;font-size:15px;line-height:1.35;color:var(--ink)}`,
    body,
    card.title,
    62,
    40
  );
}

export const BODIES = { icons, compare, steps, stat, quote, checklist, timeline, alert, numbered, list };

export function render(card) {
  // Per-card so one spec can mix brands, and so a spec with no brand field
  // renders exactly as it did before this existed.
  setBrand(card.brand);
  const fn = BODIES[card.layout || 'icons'];
  if (!fn) throw new Error(`${card.file}: unknown layout "${card.layout}"`);
  // Appended rather than threaded through every layout's shell() call: the mark
  // is absolutely positioned, so document order does not matter, and a new
  // layout picks up logo support without having to remember to pass it on.
  return fn(card) + (card.logo ? logoMarkup(card.logo) : '');
}
