---
name: seo-practitioner-reality
description: "Real-world SEO findings (2024 Google API leak + DOJ testimony + practitioner case studies) that contradict or extend Google's public docs — for use when Google's stated rules don't match what's moving rankings"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 0148947e-9e56-42d0-abd7-8d217c330a37
---

Research conducted 2026-05-19 across Reddit/forums, SEO publications, the 2024 Google Content Warehouse API leak, US v. Google DOJ testimony, and named practitioners (Lily Ray, Aleyda Solís, Mike King, Cyrus Shepard, Glenn Gabe, Kevin Indig, Britney Muller, Marie Haynes, Crystal Carter, Olga Zarr, Rand Fishkin).

**Where Google docs + practitioner data converge** (trust these):
- llms.txt is empirically useless (Otterly 90-day log: 0.1% of AI bot traffic; Ahrefs 1,885-page schema test confirms no AI markup boost)
- Foundational SEO drives AI citation (Shepard's #1 factor: Search Rank 9.4/10)
- Schema-for-AI is hype (Ahrefs A/B: −4.6% AIO citations after adding schema, ~0 effect on ChatGPT/Gemini)
- FAQ deprecation was narrow (rich result gone, schema type still valid)
- Back-button hijacking spam policy uncontroversial

**Where Google says one thing, leak/DOJ shows another** (CRUCIAL):
- **Click signals**: Google says "CTR too noisy for ranking." Leak: NavBoost module uses goodClicks/badClicks/lastLongestClicks on a 13-month rolling window. Eric Lehman testified: "pretty much everyone knows we're using clicks in rankings."
- **Domain authority**: Google says "no DA." Leak: `siteAuthority` integer, `siteFocusScore`, `hostNsr`. Denial was Moz-DA-specific, not concept-specific.
- **Sandbox**: Mueller said "no sandbox." Leak: `hostAge` attribute, source-commented "used to sandbox fresh spam."
- **Chrome data**: Cutts/Mueller denied. Leak: `chromeInTotal`, `chrome_trans_clicks` — feeds NavBoost + sitelinks.
- **E-E-A-T score**: "Not a ranking factor." Leak: `authorReputationScore`, `isAuthor`, `WebrefMentionRatings`. Author entities tracked across the web.
- **YMYL classifier**: "Just a rater concept." Leak: `ymylHealthScore`, `ymylNewsScore`, `isCovidLocalAuthority`, `isElectionAuthority` whitelists + "golden documents" manual labels.
- **contentEffort** (undisclosed): LLM-derived "how hard was this article to make" score — likely the spine of the Helpful Content System. Multimedia, original data, structural complexity, replication difficulty.

**AIO CTR collapse — 4 converging studies**: Google's "higher-quality clicks" framing contradicted by Ahrefs 58%, Seer 49–65%, Indig 50%+, Authoritas 47.5% CTR loss at position 1 when AIO is present. Plan AIO-prone pages for brand-impression value, not just clicks.

**Practitioner-only signals Google doesn't name**:
- Fan-out query coverage (+161% AIO citation odds per SEL; Mike King's Qforia tool reverse-engineers fan-outs)
- Direct opening sentence (+14% citation rate across 7 sectors — Kevin Indig)
- Brand mentions in training corpora (Muller, Fishkin, Indig — separate lever from RAG retrieval, built via PR/earned media)
- Information gain (Glenn Gabe: proprietary data + first-hand experience as 2026 emphasis)

**Two-system divergence** post-March 2026 update:
- Classic SERP: aggregators DOWN (YouTube -567 vis pts, Reddit -64), first-party UP (IMDb +79, Amazon +59)
- AI surfaces: Reddit/YouTube/LinkedIn most-cited across ChatGPT/AI Mode/Gemini/Perplexity
- Strategy: be first-party AND have brand presence on aggregator platforms

**Open practitioner disagreements** (no consensus yet):
- King vs Solís on chunking: King says RAG chunks regardless, optimize passages; Solís says skip
- Gabe vs Ray on March 2026: Gabe page-level (information gain rewrites); Ray domain-level (product moat, not SEO)
- Shepard vs Muller on AI citation = SEO logic: Shepard yes; Muller says also needs training-data presence

**Why this matters for clinic sites** (erofwhiterock + 4 others):
- Helpful Content downranking is a site embedding poisoned by aggregate page-level scores (siteFocusScore + aggregated contentEffort). One thin section drags the domain — confirms [[google-2026-seo-doctrine]] site-wide signal.
- Topical drift is internally measurable (siteRadius). [[feedback_service_page_cannibalization]] is correct.
- NavBoost rewards engagement on existing rankings — pogo-sticking = death. Post-publish optimization (intent-matching titles, fast first answer) matters more than chasing new rankings.
- Every YMYL page needs visible byline + schema Person + LinkedIn sameAs (authorReputationScore proxy).
- AIO CTR loss is the #1 2026 risk on medical "what is" / "when to go to ER" queries. Pages can rank #1 and lose half their clicks.

**Plugin implications** (additions to [[cc-assistant-seo-alignment]]):
- NEW P1 `fan_out_query_audit` (Qforia-style + GSC ranking overlay)
- NEW P1 `aio_ctr_drop_alert` (rank 1-3 + high impressions + low CTR → likely AIO cannibalization)
- NEW P1 `content_effort_score` (proxy leak attribute: media count, original data citations, word uniqueness vs SERP)
- NEW P1 `topical_drift_audit` (proxy siteFocusScore/siteRadius: detect posts drifting from site's primary embedding)
- EXTEND P1 #11 `eeat_coverage_audit` to require schema Person.sameAs → LinkedIn
