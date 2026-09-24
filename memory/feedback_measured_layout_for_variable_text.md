---
name: measured-layout-for-variable-text
description: "In generated visuals (cards, motion templates, scenes), layout must measure its text, never assume its length - wrap long content, position decorations from measured edges, skip ornaments when space runs out."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-01T05:47:20.635Z
---

In the faceless-studio scene template, a fixed connector offset let "Calcium
dissolves" run into its own connector line. The user: "when the words got
longer, it slight break the layout... it should have wrapped. I want you to
think this way too."

**Why:** Templates render arbitrary future content. Any coordinate derived
from "the text is probably about this wide" is a deferred bug that surfaces
on the first long label - exactly like the Pillow textbbox trap and the
emphasis widow guard in [[project-faceless-video-studio]].

**How to apply:** In every generated visual: (1) give text a max-width and
let it wrap; (2) position rules, connectors, arrows from MEASURED extents
(getBoundingClientRect / textbbox from origin), re-measured after fonts
load; (3) when the measured gap is too small for an ornament, omit the
ornament rather than squeeze it; (4) test templates with the longest
realistic content, not the demo content.
