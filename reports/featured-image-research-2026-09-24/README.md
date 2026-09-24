# Featured images: GitHub research and pipeline decision (2026-09-24)

## Decision

Keep the in-house Playwright pipeline (`tools/card-generator/featured`). Borrow
three ideas from GitHub rather than switching tools.

## What was checked (repo pages and READMEs fetched 2026-09-24)

| Repo | What it is | Verdict |
|---|---|---|
| anthropics/skills (Apache 2.0 examples) | canvas-design, frontend-design, brand-guidelines, theme-factory, algorithmic-art, slack-gif-creator | canvas-design and algorithmic-art make art, not readable headlines: skip. frontend-design's "one bold element" taste rules and slack-gif-creator's pass/fail platform validator are worth borrowing. |
| alonw0/web-asset-generator (MIT) | Pillow OG images plus dimension, size, format and WCAG checks | Borrow the validation checklist |
| stevysmith/og-image-skill | Playwright screenshot of an OG route | Same approach we already run: skip |
| vercel/satori, @vercel/og | JSX to SVG, flexbox only, no grid or z-index | Faster, but gives up real CSS: skip |
| kane50613/takumi (MIT/Apache) | Rust renderer with grid and text fitting | The only browser-free option worth trying, and only if Chromium becomes a burden |
| resolvetosavelives/healthicons (icons CC0) | About 2,000 medical icons: organs, BP monitor, thermometer, ER | Adopt for medical glyphs, no attribution needed |
| GitHub Actions OG generators | Build images in CI from markdown frontmatter | Overkill: content lives in WordPress behind an approval queue |

## Platform facts applied

- 1200x630 at a 1.91:1 ratio. Facebook caches by URL, so a replaced image needs a new filename.
- Serve og:image as **JPEG**. WebP is accepted almost everywhere, but Facebook and older WhatsApp clients sometimes fail to parse it (secondary sources). The first 4 images shipped as JPEG at 70 to 85 KB, under the ~300 KB WhatsApp guidance.
- Rank Math falls back to the featured image for og:image and writes width, height and alt (verified on post 4410). One attachment therefore covers both.
- Keep the headline and logo inside the central safe zone, since X trims the top and bottom and iMessage square-crops.

## Next upgrades (not built yet)

1. An `og_ready()` gate in makefeatured/proof covering: exact 1200x630, headline box inside an 80px margin, a centred 630x630 crop that keeps the headline, JPEG under 300 KB, and a new filename on replacement.
2. Healthicons as an alternative to Lucide for medical glyphs in cards and the `type` layout.
3. Version control: D:\cc-assistant has no git repository at all. A private GitHub repo, with `.mcp.json`, `.env`, models and `out/` gitignored, would give history and backup. It needs operator approval, because it publishes code to an external service.

## Shipped with this research

Posts 4772, 4773, 4774 and 4792 got featured images: attachments 4798 to 4801,
pendings #1296 to #1299. Spec: `featured/spec-featured-erof-decision4.json`.
Prompts: `featured/prompts-erof-decision4.json`.

Also changed: image-policy.json's gore rule gained `unless: \bblood pressure\b`,
because "blood pressure monitor" was refused as gore. Real gore wording is still
refused; that was tested both ways and all 40 generator tests pass.
