# Priority Plan: ER of Irving
**Date:** 2026-09-23

Ordered by what moves patients through the door, not by what is easiest.

## Operator actions (cannot go through the change queue)

| # | Action | Where | Why |
|---|---|---|---|
| 1 | **Find out why the homepage takes 9 to 11 s** | SiteGround support or Site Tools, plus a look at what loads on the homepage | Biggest conversion risk on the site |
| 2 | **Find out why the page cache never hits** | SiteGround → Speed → Caching, then Purge. Check for cookies or headers that bypass the cache | Every page is built from scratch |
| 3 | Business Profile metrics | Not available: Google has not granted the API quota | Unmeasurable for now |
| 4 | **Give me a location split** | GA4 (Reports → Demographics → Location) or call tracking | Decides how much the national blog is worth |
| 5 | Enable HSTS | SiteGround Site Tools → Security → HTTPS Enforce | Low |
| 6 | Enable AI crawler tracking | CC Assistant → Settings → General | Currently off, so AI visibility is unknown |
| 7 | Fix redirects #2 and #4 | Rank Math → Redirections | 2 minutes |

## Work I can do through the queue

| # | Task | Expected effect |
|---|---|---|
| 1 | ~~Link the orphans~~ **Queued 2026-09-23 as #1282 to #1287** (7 links). 4 more were already linked by today's posts. 5 have no natural host text yet: CO poisoning, gout, hantavirus, drowning, concussion protocol | |
| 2 | ~~Kidney stones~~ **Queued #1286 (inbound link) and #1288 (bath, position, ER vs urgent care)**. Service page 3990 needs nothing: it ranks 2.0 locally | |
| 3 | ~~Reframe 7 service pages~~ **Not needed.** All seven already have a decision section and an ER-vs-urgent-care FAQ; their weak numbers are out-of-area searches | |
| 4 | In-body cards for the three new posts | Flagged by the publish check |
| 5 | Next decision posts: asthma flare, allergic reaction, severe headache, testicular pain | Same format as today's three |

## Scheduled reads

| Date | What |
|---|---|
| ~2026-10-21 | Dehydration description test (#1264) |
| ~2026-11-04 | First read on today's six in-post gap fills (#1267 to #1272) |
| ~2026-11-22 | First read on the three new posts and the 2780 rebuild |

## What I would not do

- Chase national head terms like "emergency room near me" with new pages. The map pack owns them.
- Build more city pages. The ten you have are distinct; more would risk looking like doorway pages.
- Buy links. For a single-location ER, the Business Profile and reviews matter far more.
