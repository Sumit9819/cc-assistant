---
name: Writing style for posts and pages
description: Hard rules for any content the WP plugin generates or edits, and for chat replies in this project
type: feedback
originSessionId: 4bb11e9a-33e3-4141-be26-b4503505ff06
modified: 2026-07-23T04:53:26.777Z
---
When writing or editing post/page content (and when replying in chat for this project), follow these rules:

- **No em dashes (—) ever.** Use periods, commas, parentheses, or colons instead. This includes EVERY encoding: the literal character, `&#8212;`, `&mdash;`, `—`. (2026-07-23: operator caught em dashes I wrote as `&#8212;` entities on mammothmachinery — the plugin lint scans only the literal character and does NOT decode HTML entities, so entity-encoded dashes sail through unflagged. Self-check every editor/acc_content payload for all encodings before queueing; also fix the lint to decode entities.) En dashes in number ranges (5–6, 13–13.5) remain allowed.
- **Jargon-free.** If a non-specialist would not understand a term, swap it or explain it inline.
- **Natural human voice, not robotic.** Avoid AI-tell phrases: "in today's fast-paced world", "it's important to note", "whether you're X or Y", "dive into", "unleash", "leverage", "delve", "tapestry", "navigate", "unlock", "game-changer", "in conclusion".
- **AI/LLM citation friendly.** Crisp factual claims early. Definition sentences ("X is Y that does Z"). Question-style H2s where natural. TL;DR or key-takeaways block when the post is long.
- **Don't over-explain.** Trust the reader. Cut filler.
- **Paragraph breaks follow MEANING, not sentence counts.** The old "2-3 sentences max" rule was a band-aid for wall-of-text paste-ins; it is NOT the actual writing principle. The real rule:
  - **Break the paragraph when** the idea shifts, the argument pivots, the path/direction changes, you want a sentence to be remembered (emphasis), or you set up a contrast.
  - **Stay together when** every sentence develops the same point and breaking would lose the thread.
  - **Single-sentence paragraphs are GREAT** when the sentence carries weight on its own ("Real drowning is silent."). Use them for punchlines, pivots, contrasts, or memorability anchors.
  - **4-5 sentence paragraphs are FINE** when they build one coherent step in the argument and the sentences truly need each other to land.
  - Sentence counts are not the standard; flow, emphasis, and meaning are. NYT, Atlantic, NEJM patient-facing material all write this way.
- **Other formatting.** Subheadings every 200 to 300 words. Bullet lists for scannable info. Bold key sentences for skimmers.
- **EEAT signals.** Include quotes from real, named professionals with sources (search the web for real ones, never fabricate). First-hand experience markers when realistic and only when the user has direct experience (do not fake). Primary source links. Visible last-updated dates. Author byline with credentials.
- **Semantic keywords.** Use related terms, entities, and co-occurring concepts naturally throughout, not just the primary target keyword. Helps both topical authority and AI/LLM understanding.
- **Source citation domain rules.** Cite only .gov and .edu by default. .com sources allowed only if (a) not in the user's competitor list AND (b) high authority (major publications, recognized institutions, established expert blogs). Never cite low-authority .com sources or direct competitors. The plugin enforces this at the citation-insertion level.

**Why:** This is the user's house style for content quality and search performance. Robotic, em-dashed, jargon-heavy text is the failure mode they want avoided.

**How to apply:** Apply automatically whenever generating or rewriting WP content via the plugin. Apply to chat replies in this project too unless the user is asking for code or technical output where these rules do not fit. Never generate fake quotes or fabricated attributions; if a quote is needed and none is on hand, ask the user or pull from a verified source.
