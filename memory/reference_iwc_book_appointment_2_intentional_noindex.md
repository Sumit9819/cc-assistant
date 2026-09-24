---
name: reference-iwc-book-appointment-2-intentional-noindex
description: "irvingwellnessclinic.com /book-appointment-2/ (post 10253) is a DELIBERATE noindexed duplicate. Never propose deleting, merging or reindexing it."
metadata:
  type: reference
---

**Site: Irving Health and Wellness Clinic (https://irvingwellnessclinic.com).**

Page **10253 `/book-appointment-2/`** ("Book Appointment", 75 words) is an
intentional second booking page. The operator deliberately set it to
`noindex, follow`, which is why it is absent from `page-sitemap.xml` and shows
as an orphan in `links_orphans`. Stated by the operator on 2026-09-22:
"/book-appointment-2/ is intentional so do nothing, that is why I have put this
to noindex".

**Do NOT** propose trashing it, merging it into 128 `/book-appointment/`,
adding inbound links to it, or removing the noindex. It is not duplicate-content
debt and it is not index bloat.

Automated audits WILL keep surfacing it, because on paper it looks like a
near-duplicate thin page:
- `links_orphans` lists it (zero inbound links, by design)
- a title-duplication check pairs it with 128 (both titled "Book Appointment")
- `site_quality_score` excludes it as `utility_slug`

Treat all three as expected output, not findings. The live indexed booking page
is **128 `/book-appointment/`**.

Related: [[feedback-never-noindex-pages]] (the no-noindex rule has an explicit
campaign/utility-page exception, and this is one), [[project-iwc-provider-is-aprn]].
