---
name: erofirving-form-email-standard
description: "ALL SITES: Elementor form emails go to info@<site-domain> with a subject naming the page; audit forms for clone residue (staging hosts, sister-site senders, emoji-broken recipients)"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 813fe1b6-ece5-4c84-92c1-740614b56b3f
  modified: 2026-07-23T09:27:11.624Z
---

User directive (2026-07-09, reconfirmed for eroflufkin 2026-07-23): every Elementor form on each site must send to **info@<site-domain>**, with a subject that (a) names the page the form lives on and (b) says it came from the website.

**eroflufkin audit (2026-07-23, #585-612):** 28 forms; NONE went to info@ (all marketing@); 4 recipients were broken by a pasted emoji prefix (email plus emoji fails validation, submissions likely dropped silently); 4 second notifications sent as email@erofirving.com / "ER of Irving" to contact@digitallinkage.com. Operator chose: primaries to info@eroflufkin.com, ALL agency copies removed, submit_actions pinned to ["email"]. Audit method: curl every page grepping form.default widget ids, then read each widget's email_* settings via get_post(widget_id).

**Standard settings:**
- `email_to`: `info@erofirving.com`
- `email_subject`: `New website form submission - <Page name> page` (e.g. "- Bedford location page", "- Contact Us page")
- `email_from`: `info@erofirving.com`, `email_from_name`: `ER of Irving Website`
- `email_*_2` block (agency copy to contact@digitallinkage.com): keep the recipient, but fix `email_from_2` off the staging host and align subject.

**Why:** the location-page forms shipped with `email_to: info@jayard25.sg-host.com` (staging clone residue) — submissions were being lost. Contact page had been hand-fixed already.

**How to apply:** bake these settings into the carried-forward form widget during every location-page rebuild transform; for already-imported pages, queue draft_update_elementor_widget on the form widget. Also check any NEW form for jayard staging addresses before it ships.

Related: [[jayard35-staging]]

**2026-08-26 extension:** this also covers accessibility statements and any "coordinator" contact blocks: there is NO accessibility@<domain> or other role mailbox on any site. Every published contact email is info@<domain>; when a page shows another address (Lufkin statement had accessibility@eroflufkin.com plus an unverified phone), queue a fix to info@ and the main phone line rather than leaving it.
