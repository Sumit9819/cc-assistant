---
name: football-means-soccer
description: "When user says \"football\" with international/global context signals, read it as soccer (not American football), even if the site is US-based"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: cec5ab6a-29cc-4829-b135-c1a8eff355c6
---

When the user says "football" alongside any of these signals, interpret it as **soccer** (the global sport), not American football:

- "USA and a few other countries" / multi-country event
- "the future" / upcoming international tournament
- Any reference to "the World Cup" / FIFA / international teams
- User has used international English phrasing earlier in the session

**Why:** 2026-05-22 — wrote an entire 1,650-word draft + queued 3 pendings tied to American football season at North Texas stadiums before user clarified they meant **FIFA World Cup 2026** (USA + Canada + Mexico, June 11 – July 19, 2026, with AT&T Stadium in Arlington as a host venue). "Football in USA and a few other countries" should have been an unmistakable World Cup signal. User said "how can you be this wrong" — that was fair. The site being in Texas made me default to NFL/college football context; that defaulting cost a full draft cycle.

**How to apply:**
- On any "future football event in multiple countries" framing: WebFetch or grep your own knowledge for FIFA / World Cup before drafting.
- If still ambiguous, ASK ONCE before drafting — the cost of one clarifying question is far less than rebuilding a 1,650-word post.
- For irvingwellnessclinic specifically: AT&T Stadium (Arlington, ~15 min from Irving) is a 2026 World Cup host venue. Any "soccer fans visiting North Texas" content has direct local-traveler SEO value through July 2026 and is evergreen-adjacent for future soccer events in the region.
- Related: [[feedback_niche_search_discipline]] — World Cup content still needs to bridge to a declared service line (e.g., heat illness → IV hydration), not just chase the trending keyword.
