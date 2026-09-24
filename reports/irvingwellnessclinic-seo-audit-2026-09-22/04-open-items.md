# Open Items
**As of 2026-09-22, after the 8 applied changes.**

## Do we need new blog posts? No.

This is the clearest conclusion in the audit, and it runs against the usual instinct.

The site has 58 posts averaging 91.5 on internal quality scoring, with 68 of 73 rated strong. Content volume and content quality are not the constraint. Adding a 59th post of the same kind will produce the same result the last 58 produced: impressions without clicks.

The evidence:
- Striking-distance opportunities are nearly exhausted. Only 5 queries sit at position 5 to 20 with 40 or more impressions.
- 15 pages have never earned a single click across the whole warehouse history.
- The wellness coaching page ranks position 2 to 3 for its target terms and still gets zero clicks, because those terms have no search volume.
- 61,676 impressions sit at position 1 on four queries with zero website clicks.

None of those problems are solved by more articles.

## What content IS worth creating, in order

These are not "more blogs". Each does a job the existing 58 posts cannot.

**1. IV Therapy pricing page.** Cost queries are high-intent and the site ranks for almost none. Add `Offer` and `priceRange` schema. Highest commercial value of anything on this list.

**2. Las Colinas landing page.** Named in the site's own geo doctrine, zero coverage today.

**3. Valley Ranch landing page.** Same.

HARD CONSTRAINT on 2 and 3: these must be genuinely distinct pages with real local substance, not the same page with the city swapped. The saved location-page doctrine treats city-swap templates as doorway spam, and that rule stands.

**4. Original data piece.** For example anonymised outcome data from the clinic's own GLP-1 patients. At DA 10 with two real backlinks, this is the only realistic link-earning asset available. It directly supports the authority work rather than competing with it.

**5. Functional medicine pillar.** Four posts (allergy testing, wellness assessment, functional medicine, BMI) currently sit with no pillar above them.

**6. IV therapy glossary.** Cheap to build, strong AI-answer surface, links the whole cluster.

Everything below this line is maintenance, not creation.

---

## Decisions only the client or operator can make

| # | Question | Why it blocks work |
|---|---|---|
| 1 | **Which suite is correct, 100 or 110?** | Every citation locks it in. Site says 100, PRNewswire and Yahoo Finance say 110, and 110 is ER of Irving's suite. |
| 2 | **Did anyone buy backlinks around June or July 2026?** | Decides whether the ~100 PBN links are harmless spam to ignore or a vendor relationship to end. |
| 3 | **Who actually authors the blog posts?** | Needed before fixing the schema author. Cannot invent authorship. |
| 4 | **Is "Walk-ins are welcome" on the About page FAQ factually true?** | It contradicts the scheduled-care positioning. Needs a factual answer, not an editorial decision. |
| 5 | **What is Lori's NPI number?** | Required for Healthgrades, Vitals, WebMD, and to add NPI Registry to her schema `sameAs`. |

## Operator actions (cannot go through the change queue)

| Priority | Action | Where |
|---|---|---|
| 1 | **Instrument GBP calls and direction requests** | Google Business Profile. Until measured, nobody can tell whether this site underperforms or quietly wins. |
| 2 | **Purge SG Cache after any meta edit** | Admin bar. The CDN holds stale HTML for 48 hours. |
| 3 | Convert 3 heavy PNGs to WebP | `AestheticTreeatment-scaled.png` 2.45 MB, `Skinvive-vs-Dermal-Filler-scaled.png` 1.10 MB, `The-Clinical-Science-of-GLP-1-Therapy_.png` 1.01 MB |
| 4 | Enable HSTS | Site Tools, Security, HTTPS Enforce. No `Strict-Transport-Security` header exists today. Low priority. |
| 5 | Start review velocity program | Target 10 to 15 per month against the current 50 |
| 6 | Fix the `#website` @id collision | Lives in the Code Snippets plugin, which is outside the assistant's permitted scope |

## Queueable work, waiting on the decisions above

- Fix schema author across 38 managed-schema posts (blocked on decision 3)
- Add NPI Registry to Lori's schema `sameAs` (blocked on decision 5)
- Align site NAP if Suite 110 turns out to be correct (blocked on decision 1)
- Add `MedicalWebPage` plus `lastReviewed` to YMYL pages
- Add `Offer` and `priceRange` to service pages once a pricing page exists
- Remove the duplicate `BreadcrumbList` from the 22 affected managed-schema posts (low value, warn-level only)

## The scheduled measurement that decides the next quarter

**On or after 2026-10-12**, run `gsc_inspect_url(post_id=6296)` and read `coverage_state`.

Post 6296 (Myers' Cocktail) is the fully-treated measurement page: claims corrected with 3 authority sources, SEO title fixed, subheadings converted to query shapes, all applied and verified live.

- If it moves off "Crawled - currently not indexed", full page treatment works and is the template for the other refused posts.
- If it does not move, the constraint is domain authority rather than page quality, and per-page rewriting should STOP in favour of GBP, citations and review velocity.

This is the single most informative pending item on the site. Do not judge it earlier than 2026-10-12.

## Standing rules confirmed this session

- `/book-appointment-2/` (page 10253) is an INTENTIONAL noindex. Never delete, merge, relink or reindex it. Orphan and duplicate-title audit hits on it are expected output.
- Never noindex anything else. A page at position 8 to 15 has not been clicked yet; deindexing turns a maybe into a certain zero.
- Never remove the GBP UTM URL.
- Do not run `propose_schema` on this site.
- Verify meta changes with `verified_page_audit` or a cache-busting param, never a plain curl.
