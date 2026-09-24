/**
 * Depiction gate for the people in these images.
 *
 * The layout defects in this generator are all measured - contrast, listing-size
 * type, a head sliced at the frame, a subject too thin to fill its column. The
 * defect this file exists for cannot be measured from pixels: whether the
 * photograph depicts a person with dignity or reproduces a weight-stigma trope.
 * So it is caught the only way it can be, on the TEXT that describes the picture,
 * at both ends of the pipeline:
 *
 *   - the generation brief, before a bad shot is worth making (geminibrowser.mjs)
 *   - the shipped alt text, which every rendered image has to have
 *     (makefeatured.mjs), so a photo from any source passes through here
 *
 * Neither is a proof. An agent that picks a headless-torso stock photo and writes
 * "woman in a grey top" for its alt defeats the alt gate, and the real backstop
 * for that is still a human looking at `out/_listing-sheet.png`. What this does
 * guarantee is that the rule is refused rather than remembered: it survives a new
 * session, which prose in a README does not.
 *
 * Rules live in image-policy.json, one file read by both callers rather than two
 * copies drifting apart, and they are keyed by BRAND PROFILE because the right
 * answer genuinely inverts: on the wellness brand a person is often the correct
 * subject and the rules govern how they are shown, while on the ER brands there
 * are no people at all, because an ER post is a triage decision and anyone in
 * the frame is a patient who does not exist or a clinician who does not work
 * there. Every brand also gets the `shared` profile.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));

let POLICY = null;
const CACHE = new Map();

function policy() {
  if (!POLICY) {
    POLICY = JSON.parse(fs.readFileSync(path.join(HERE, 'image-policy.json'), 'utf8'));
  }
  return POLICY;
}

/**
 * The compiled rule set for one brand: `shared` plus its profile.
 *
 * An unknown brand gets `shared` alone rather than an error. Refusing outright
 * would mean adding a brand to brands.mjs silently breaks rendering; falling
 * through to the universal rules keeps it working while still catching the
 * fabricated-patient and trade-dress cases, and the missing profile is obvious
 * the first time someone reads this file.
 */
function rulesFor(brandSlug) {
  const key = brandSlug || 'iwc';
  if (!CACHE.has(key)) {
    const raw = policy();
    const names = ['shared'];
    const profile = raw.brands?.[key];
    if (profile && profile !== 'shared') names.push(profile);
    const compile = (list) => (list || []).map((r) => ({
      re: new RegExp(r.pattern, 'i'),
      // An optional escape for a ban with a legitimate context: a body part in a
      // radiograph is a picture of bones, not a person in the frame.
      unless: r.unless ? new RegExp(r.unless, 'i') : null,
      why: r.why,
    }));
    CACHE.set(key, {
      profiles: names,
      refuse: names.flatMap((n) => compile(raw.profiles?.[n]?.refuse)),
      warn: names.flatMap((n) => compile(raw.profiles?.[n]?.warn)),
    });
  }
  return CACHE.get(key);
}

/**
 * Which profile a brand belongs to. Exported because the depiction rules are not
 * the only thing that differs by profile - the circular frame is an ER device
 * too - and one brand map is better than two.
 */
export function profileFor(brandSlug) {
  return policy().brands?.[brandSlug || 'iwc'] || 'shared';
}

/** Every rule the text trips, as { refusals: [{hit, why}], warnings: [...] }. */
export function checkImageText(text, brandSlug) {
  const s = String(text ?? '');
  const found = (list) => list.flatMap((r) => {
    if (r.unless && r.unless.test(s)) return [];
    const m = s.match(r.re);
    return m ? [{ hit: m[0], why: r.why }] : [];
  });
  const { refuse, warn } = rulesFor(brandSlug);
  return { refusals: found(refuse), warnings: found(warn) };
}

/**
 * Throw on a refusal, print warnings. `label` names what is being checked so the
 * message says which spec entry to fix rather than leaving it to be hunted.
 */
export function enforceImagePolicy(text, label, brandSlug) {
  const { refusals, warnings } = checkImageText(text, brandSlug);
  for (const w of warnings) console.log(`  ~ ${label}: "${w.hit}" - ${w.why}`);
  if (refusals.length) {
    throw new Error(
      `${label}: refused by image-policy.json ` +
      `(profiles: ${rulesFor(brandSlug).profiles.join(' + ')}).
` +
      refusals.map((r) => `    "${r.hit}" - ${r.why}`).join('\n') +
      `\n    This is a depiction rule, not a style preference. Change the picture, ` +
      `not the wording that describes it.`
    );
  }
}
