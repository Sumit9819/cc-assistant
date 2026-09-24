/**
 * Brand token sets for the card generator.
 *
 * The layouts were written for irvingwellnessclinic and had its palette spread
 * across 507 lines as a mix of CSS variables named after colours (`--green`,
 * `--yellow`) and one-off hex literals for tints, hairlines and borders. That is
 * fine for one brand and impossible for two: ER of Irving is red-on-navy with
 * yellow and green explicitly BANNED by its design skill, so `--yellow` could
 * not simply be given a new value.
 *
 * So every colour here is named by ROLE, not by hue, and each brand supplies the
 * whole set. `iwc` reproduces the previous values exactly - verified by
 * re-rendering existing specs and pixel-comparing against the shipped PNGs -
 * so adding brands cannot disturb the 914 files already live.
 *
 * ON-ACCENT IS NOT OPTIONAL. IWC's accent is a bright yellow that needs DARK
 * text on it; ER of Irving's is a saturated red that needs WHITE. A brand that
 * only declared its accent would silently ship dark navy on red at about 2:1.
 */

export const BRANDS = {
  /**
   * Irving Wellness Clinic. Values lifted verbatim from the original layouts.mjs.
   */
  iwc: {
    name: 'Irving Wellness Clinic',
    font: 'Poppins',
    font_css: 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
    logo: { green: 'iwc-logo-green.png', white: 'iwc-logo-white.png', plain: 'iwc-logo.png' },

    dark: '#003017',        // dominant brand dark: headings, column headers
    mid: '#96A681',         // secondary
    accent: '#FFD900',      // highlight / badges / marker
    on_accent: '#003017',   // ink that sits ON accent
    ground: '#f7f8f4',      // card background
    surface: '#fff',        // panels and rows sitting on the ground
    ink: '#4a5a48',         // body copy
    label: '#5c6b59',       // row labels
    muted: '#7a887a',       // source lines and footnotes
    line: '#e3e9dd',        // panel borders
    hair: '#eef2e9',        // dividers between rows
    track: '#dde5d6',       // timeline rail
    dot: '#dfe6d8',         // unfilled dot
    icon_bg: '#f2f5ee',     // icon circle behind a glyph
    on_dark: '#eef3ea',     // copy on a dark panel
    warn_head: '#8a6a00',   // the "avoid" side of a compare panel
    tints: ['#e4ebdc', '#eef2e8', '#f2ecd6'],   // the three soft background shapes
    // Panel shadows were a green-tinted black. Kept exactly, so IWC output is
    // unchanged; ER of Irving uses a neutral black instead of inheriting a
    // green cast it has no colour for.
    shadow_soft: 'rgba(0,48,23,.045)',
    shadow: 'rgba(0,48,23,.055)',
    // Featured-image tokens (1200x630 layouts next door). `glow` is the radial
    // wash on the dark layout - a lightened brand dark, NOT `mid`, which on IWC
    // is a sage that would read as a different colour entirely. `on_dark_soft` is
    // the subtitle sitting on that dark ground.
    glow: '#0b4a26',
    on_dark_soft: '#c8d4c2',
    // The two soft washes on the `type` layout. IWC's existing tint values,
    // kept verbatim so its output does not move.
    featured_shapes: ['#e4ebdc', '#f2ecd6'],
  },

  /**
   * ER of Irving. Palette is fixed by the erofirving-design skill, sections 21
   * and 22: red #DA1212 on navy #11468F / #041562, with yellow, amber, green,
   * pink, teal, purple, orange and brown all BANNED as off-brand.
   *
   * That leaves no decorative hues at all, so the soft background shapes are
   * greys rather than colour washes. For an emergency brand that is the right
   * answer anyway: restraint reads as clinical, and a decorative pastel would
   * read as a consumer wellness app.
   *
   * `warn_head` is the one place this brand fits the layouts better than IWC
   * does. The "avoid" side of a compare panel wants red, and IWC had to darken
   * its yellow to a muddy olive (#8a6a00) to get white text to sit on it.
   */
  erofirving: {
    name: 'ER of Irving',
    // Montserrat, measured from the live site's computed styles rather than
    // assumed - the Elementor Kit custom properties came back empty, so the
    // browser's own getComputedStyle is the only ground truth available.
    font: 'Montserrat',
    font_css: 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap',
    // The harvested file is the 512x512 SITE ICON (a wordless red/navy cross
    // with an ECG trace), not a horizontal wordmark. Its filename on the Irving
    // site is "cropped-LufkinLogoNewHorizontalNew.png" - clone residue, since
    // the skill says Irving is the original. The MARK itself carries no text, so
    // it is brand-safe to use; a proper horizontal wordmark is still wanted.
    // `white` is the REVERSED lockup: navy half turned white so it survives a
    // navy ground, red half kept because red is the emergency signal.
    logo: { mark: 'erofirving-logo.png', white: 'erofirving-logo-white.png' },

    dark: '#041562',        // text navy: headings, column headers
    mid: '#11468F',         // secondary navy
    accent: '#DA1212',      // primary red: badges, marker, urgency
    on_accent: '#FFFFFF',   // white on red, not navy
    ground: '#FFFFFF',
    surface: '#FFFFFF',
    ink: '#555555',         // skill section 21: body text, never #000
    label: '#555555',
    muted: '#777777',       // footnote / disclosure
    line: '#DDDDDD',
    hair: '#E0E0E0',
    track: '#DDDDDD',
    dot: '#DDDDDD',
    icon_bg: '#F4F4F4',
    on_dark: '#E0E0E0',     // skill: light text on dark backgrounds
    warn_head: '#DA1212',
    tints: ['#F4F4F4', '#F4F4F4', '#F4F4F4'],
    shadow_soft: 'rgba(4,21,98,.05)',
    shadow: 'rgba(4,21,98,.07)',
    glow: '#11468F',       // secondary navy as the radial wash on the navy ground
    on_dark_soft: '#E0E0E0',  // skill section 21: light text on dark backgrounds
    // A tint of the brand navy, not the card grey. #F4F4F4 on #FFFFFF measures
    // 1.10:1 and disappears; IWC's sage on its off-white is 1.14:1 and reads,
    // because it carries HUE and a neutral-on-white shift carries none. So these
    // are the brand's own navy at low alpha - a tint of a Kit colour, not one of
    // the hexes section 22 bans.
    featured_shapes: ['rgba(17,70,143,.10)', 'rgba(17,70,143,.055)'],
  },
  eroflufkin: {
    name: 'ER of Lufkin',
    // Montserrat, read from this site's own Elementor Kit system_typography
    // (primary/secondary/text all Montserrat), not inferred from the sister site.
    font: 'Montserrat',
    font_css: 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap',
    // Lufkin's site icon (attachment 4673) is
    // uploads/2025/02/cropped-LufkinLogoNewHorizontalNew.png, 512x512, titled
    // "er-of-lufkin-logo" - the SAME file Irving serves, because Irving was
    // built from this mark. So erofirving-logo.png is Lufkin's own logo, not a
    // borrowed one. It is a wordless red/navy cross, brand-safe for both.
    // `white` is the REVERSED lockup: navy half turned white so it survives a
    // navy ground, red half kept because red is the emergency signal.
    logo: { mark: 'erofirving-logo.png', white: 'erofirving-logo-white.png' },

    // Identical palette to Irving BY SPEC: the eroflufkin-design skill fixes the
    // brand red at #DA1212 and section 22 bans "any red other than #DA1212".
    // NOTE the live Elementor Kit reports primary #D01010, a drift from the
    // skill. Following the skill, because inventing a third red would break the
    // two-colour discipline that section 22 calls the brand's differentiator.
    // Flagged to the operator to reconcile Kit vs skill.
    dark: '#041562',        // text navy: headings, column headers
    mid: '#11468F',         // secondary navy
    accent: '#DA1212',      // primary red: badges, marker, urgency
    on_accent: '#FFFFFF',   // white on red, never navy
    ground: '#FFFFFF',
    surface: '#FFFFFF',
    ink: '#555555',         // skill section 21: body text, never #000
    label: '#555555',
    muted: '#777777',       // footnote / disclosure
    line: '#DDDDDD',
    hair: '#E0E0E0',
    track: '#DDDDDD',
    dot: '#DDDDDD',
    icon_bg: '#F4F4F4',
    on_dark: '#E0E0E0',
    warn_head: '#DA1212',
    tints: ['#F4F4F4', '#F4F4F4', '#F4F4F4'],
    shadow_soft: 'rgba(4,21,98,.05)',
    shadow: 'rgba(4,21,98,.07)',
    glow: '#11468F',       // secondary navy as the radial wash on the navy ground
    on_dark_soft: '#E0E0E0',  // skill section 21: light text on dark backgrounds
    // A tint of the brand navy, not the card grey. #F4F4F4 on #FFFFFF measures
    // 1.10:1 and disappears; IWC's sage on its off-white is 1.14:1 and reads,
    // because it carries HUE and a neutral-on-white shift carries none. So these
    // are the brand's own navy at low alpha - a tint of a Kit colour, not one of
    // the hexes section 22 bans.
    featured_shapes: ['rgba(17,70,143,.10)', 'rgba(17,70,143,.055)'],
  },
  /**
   * ER of White Rock. Cloned from ER of Irving, and the palette is the same by
   * spec: the erofwhiterock-design skill fixes red #DA1212 with "any red other
   * than #DA1212" banned, navy #11468F / #041562, card grey #F4F4F4.
   *
   * Read from the site's own Elementor Kit (id 5) on 2026-09-08 rather than
   * inherited from the sister site: all four system_typography roles are
   * Montserrat, and h1_color / button_background_color are #DA1212. The Kit's
   * system_colors.primary is #D01010 - the SAME drift Lufkin has. Following the
   * skill, as the Lufkin entry does, so the three ER sites do not end up with
   * three different reds.
   *
   * Unlike its sisters this site has a real horizontal WORDMARK
   * (uploads/2026/02/erofwhiterocklogo), not just the wordless cross.
   */
  erofwhiterock: {
    name: 'ER of White Rock',
    font: 'Montserrat',
    font_css: 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap',
    // `white` is a REVERSED lockup, not a whitened one: the navy half becomes
    // white so it survives a navy ground, the red half stays red because red is
    // this brand's emergency signal and whitening it throws that away.
    logo: { mark: 'erofwhiterock-logo.png', white: 'erofwhiterock-logo-white.png' },

    dark: '#041562',
    mid: '#11468F',
    accent: '#DA1212',
    on_accent: '#FFFFFF',
    ground: '#FFFFFF',
    surface: '#FFFFFF',
    ink: '#555555',
    label: '#555555',
    muted: '#777777',
    line: '#DDDDDD',
    hair: '#E0E0E0',
    track: '#DDDDDD',
    dot: '#DDDDDD',
    icon_bg: '#F4F4F4',
    on_dark: '#E0E0E0',
    warn_head: '#DA1212',
    tints: ['#F4F4F4', '#F4F4F4', '#F4F4F4'],
    shadow_soft: 'rgba(4,21,98,.05)',
    shadow: 'rgba(4,21,98,.07)',
    glow: '#11468F',
    on_dark_soft: '#E0E0E0',
    // A tint of the brand navy, not the card grey. #F4F4F4 on #FFFFFF measures
    // 1.10:1 and disappears; IWC's sage on its off-white is 1.14:1 and reads,
    // because it carries HUE and a neutral-on-white shift carries none. So these
    // are the brand's own navy at low alpha - a tint of a Kit colour, not one of
    // the hexes section 22 bans.
    featured_shapes: ['rgba(17,70,143,.10)', 'rgba(17,70,143,.055)'],
  },
};

/** The colours the erofirving skill refuses, so a brand file cannot smuggle one in. */
const BANNED = [
  '#ffc107', '#b45309', '#92400e', '#15803d', '#28a745', '#16a34a', '#22c55e',
  '#ffe5e5', '#fff1f1', '#ffeeee',
];

const ROLES = ['dark', 'mid', 'accent', 'on_accent', 'ground', 'surface', 'ink',
               'label', 'muted', 'line', 'hair', 'track', 'dot', 'icon_bg',
               'on_dark', 'warn_head', 'shadow_soft', 'shadow', 'glow', 'on_dark_soft'];

/**
 * Relative luminance and WCAG contrast, so on_accent can be checked rather than
 * trusted. Duplicated from proof.py on purpose: a brand file is validated at
 * render time in Node, long before any Python runs.
 */
function luminance(hex) {
  const h = hex.replace('#', '');
  const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16) / 255)
    .map((c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(a, b) {
  const [x, y] = [luminance(a), luminance(b)];
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

export function brand(slug) {
  const b = BRANDS[slug];
  if (!b) {
    throw new Error(`Unknown brand "${slug}". Known: ${Object.keys(BRANDS).join(', ')}`);
  }
  for (const role of ROLES) {
    if (!b[role]) throw new Error(`Brand "${slug}" is missing the "${role}" colour`);
  }
  if (!Array.isArray(b.tints) || b.tints.length !== 3) {
    throw new Error(`Brand "${slug}" needs exactly three tints`);
  }
  for (const [role, v] of Object.entries(b)) {
    if (typeof v === 'string' && BANNED.includes(v.toLowerCase())) {
      throw new Error(`Brand "${slug}" uses banned colour ${v} for "${role}"`);
    }
  }
  // The check that stops a value swap from shipping unreadable text.
  const c = contrast(b.accent, b.on_accent);
  if (c < 4.5) {
    throw new Error(
      `Brand "${slug}": on_accent ${b.on_accent} on accent ${b.accent} is ` +
      `${c.toFixed(1)}:1, below the 4.5 floor. Badges and markers would be unreadable.`);
  }
  const d = contrast(b.dark, '#ffffff');
  if (d < 4.5) {
    throw new Error(
      `Brand "${slug}": dark ${b.dark} carries white text in column headers at ` +
      `${d.toFixed(1)}:1, below 4.5.`);
  }
  return b;
}

/** The :root block every layout's CSS is prefixed with. */
export function rootVars(b) {
  return `:root{` +
    `--dark:${b.dark};--mid:${b.mid};--accent:${b.accent};--on-accent:${b.on_accent};` +
    `--ground:${b.ground};--surface:${b.surface};--ink:${b.ink};--label:${b.label};` +
    `--muted:${b.muted};--line:${b.line};--hair:${b.hair};--track:${b.track};` +
    `--dot:${b.dot};--icon-bg:${b.icon_bg};--on-dark:${b.on_dark};` +
    `--warn-head:${b.warn_head};--shadow-soft:${b.shadow_soft};--shadow:${b.shadow};` +
    `--t1:${b.tints[0]};--t2:${b.tints[1]};--t3:${b.tints[2]}}`;
}
