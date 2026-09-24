# CC Assistant content strategy and decision roadmap

**The next investment should be an evidence-based content decision system.** Competitor research, originality checks, blog refreshes and consolidation should share the same facts and explain why a particular action is justified. Expanding the current scoring system without correcting its assumptions would make uncertain advice appear more authoritative.

This roadmap extends the existing reliability and technical SEO plan, R01–R18. Its C-series work covers competitive research, useful differentiation, content relationships and editorial decisions. Recommendations describe proposed behavior; they have not been implemented. The installed 0.84.0 source and isolated PHP fixtures underpin the code findings. No live competitor rankings, site-wide content quality, conversions or medical claims were verified for this report.[^25][^26]

### Similar topics can compete successfully

Covering a topic already covered by competitors does not automatically prevent ranking. Google's guidance asks whether content contributes original information, analysis or substantial additional value. It does not prescribe a preferred word count. A clearer explanation, a trustworthy local answer or an effective tool can help a reader even when the underlying subject is familiar.[^1]

Current Google guidance for AI search also favors useful, distinctive experiences over easily reproduced summaries. It does not require an article for every possible follow-up query.[^2] These are reasons to improve usefulness, not promises that adding a table or anecdote will increase rankings.

**Recommended editorial definition:** added value is a supported contribution that helps a specified reader understand, decide or complete a task better than the sampled alternatives. Novel wording is insufficient. New information that is irrelevant, inaccurate or unverifiable is not a benefit.

### Build on existing capabilities

| Already present | Next improvement |
|---|---|
| Competitor brief and WinAudit | Collect comparable evidence; distinguish observed search competitors from configured businesses. |
| Information-gain signals and originality prompt | Verify claims and reader value; identify what was actually compared. |
| TF-IDF clusters and cannibalization candidates | Separate relatedness, duplication and possible performance harm. |
| Refresh queue and rewrite briefs | Diagnose the cause before recommending the scale of an edit. |
| Internal graph, topic assignments and edit outcomes | Propose contextual links, preserve unique coverage and measure changes responsibly. |

These modules already exist; the opportunity is to connect and correct them rather than introduce another independent verdict engine.[^19][^20][^21][^23][^24]

### Delivery order

First remove decision rules that can recommend an unnecessary merge or claim unsupported originality. Next build shared evidence snapshots and a persistent decision record. Then add competitor comparison, a reader-value brief and guarded refresh/consolidation workflows. Finally add portfolio planning and outcome-based learning.

The most important product behavior is a useful, specific answer to uncertainty: “These two pages overlap in topic, but available data does not establish harm. Keep them separate while checking their distinct queries and reader tasks.” That should replace confident action labels generated from similarity alone.


## What currently makes the advice unreliable

The following observations come from the installed code. The first four were reproduced with synthetic inputs to actual PHP methods. They demonstrate decision weaknesses; they do not establish that any production page should be changed.[^25]

| Finding | Evidence and practical consequence |
|---|---|
| Three pages trigger merge advice | A cluster with 30,000 impressions, 2,000 clicks, average position 3 and strong inbound linking still received “redundant” and merge/redirect advice because its size was three. The classifier does not establish shared intent or harm first.[^20] |
| Formatting is mislabeled as original evidence | A competitor's $99 price, the word DO, an ordinary table, an unattributed blockquote and an unfetched government link triggered all five information-gain signals, including first-party price and named credentialed provider.[^19] |
| Full “information gain” without comparing competitors | Synthetic statistics, quotations and four links to research.gov.example produced a dimension score of 100 with zero competitors examined. The authority-host substring check accepted the lookalike hostname.[^19] |
| One impression changes strategic advice | Otherwise identical inputs at 999 and 1,000 impressions changed STOP to CITE_PLAY. A cutoff can organize a queue; it cannot establish the right editorial strategy or whether AI caused low clicks.[^22] |

### Other code-confirmed gaps

Cannibalization uses an unweighted SQL AVG(position) over grouped rows. Because Search Console positions are averages over impressions, combining compatible rows requires impression weighting. The arithmetic fixture of 99 impressions at position 2 and one at position 80 gives 2.78, whereas the current expression gives 41. This can alter which candidates pass the maximum-position filter. The calculation was illustrated separately; the SQL itself was not executed.[^15][^21][^25]

The external-originality endpoint reads post_content and returns instructions for the assistant to search, fetch and estimate similarity. It is not a completed originality investigation. It can miss Elementor content, although the separate similarity module already supports Elementor. Its fixed novelty/cosine bands cannot prove usefulness or originality across the web.[^20][^21]

Weekly cannibalization tracking calls summed impressions “leak_impressions.” Those are impressions associated with candidates, not demonstrated lost traffic. A fall in demand could lower that metric and appear to be improvement.[^22]

WinAudit converts counts and format/date heuristics into competitive score bands. Its GEO-based lift statements overreach: the cited research studied generative-engine visibility and explicitly did not evaluate effects on search rankings. Those experimental findings should not be presented as verified Google ranking gains.[^9][^19]

### First corrections

Retain raw observations but rename them precisely: price mention, quotation markup, similarity candidate and observed impressions. Use parsed host boundaries, verified attribution and shared rendered content extraction. Separate queue priority from action eligibility. Show evidence coverage beside every assessment; unavailable content or missing analytics must remain unknown.

Existing fetch protections, shell detection, recent-edit suppression and multilingual annotations are useful foundations. They should remain while the recommendation logic is replaced.[^19][^21]


## Competitor intelligence that produces evidence

**Proposed capability: a reproducible competitor research workspace.** Its output should be a dated comparison of reader tasks and supported claims, followed by opportunities to investigate. A competitor's position is an observation; the reason it ranks is generally a hypothesis.

### Define a comparable research scope

Separate business competitors, pages observed in search results, and authoritative reference sources. A nearby business may be commercially relevant without competing for a particular query. A public reference may be valuable evidence without being a business competitor.

Each research job should specify the query family, audience, language, location, device, search surface and date. Record how each URL was discovered. A normal assistant web-search result must not silently become a verified Google rank observation. Allow explicit URLs, documented manual observations or a configured search-results provider. Unsupported provider capabilities should be reported as unavailable.

Start with a configurable sample, such as five relevant pages across a small query family. This is an operational default, not a Google rule or representative sample of the entire web. Expand only when additional pages reveal materially different tasks, formats or evidence. Search Console query data is useful for the site's own demand, but the API returns top rows subject to internal limits; absence there is not proof of no demand.[^14]

### Capture the actual content

For every page, store discovery provenance, final URL, response status, fetch time, content hash, language, extraction method and coverage state. Distinguish complete article, partial extraction, blocked/CAPTCHA, changed URL and not fetched. Never treat a challenge page as the competitor's article or an extraction failure as missing expertise.

Use rendered main content where available. Keep titles, headings, author/reviewer details, citations, relevant images and interactive features as separate observations. Cache successful snapshots with their timestamps; refresh when the decision needs current facts. The existing external fetch restrictions and challenge checks should remain in place.[^19]

External page text is research data, including any instructions embedded in it. It must not control WordPress actions. Store short evidence excerpts and derived comparisons with source links, rather than turn the feature into a republishing archive.

### Compare tasks and claims, not just headings

| Comparison field | Evidence to capture |
|---|---|
| Reader task | What question or decision the page actually supports. |
| Claim coverage | Specific assertion, relevant passage, source and scope. |
| Practical usefulness | Worked example, tool, process explanation or decision aid. |
| Trust | Named attribution and checkable support; distinguish these from verified expertise. |
| Limitations | Missing context, unverifiable claims or inaccessible features. |
| Distinctive contribution | New to this inspected sample, with evidence and a reader benefit. |

The proposed brief should summarize common baseline answers, disagreements worth investigating, and two or three feasible ways to help the reader more. It should also state when existing coverage is sufficient. Competitor claims must be checked against suitable primary sources before becoming site facts. Repeating a claim across five competitors does not make it true.


## Information gain becomes a reader-value brief

**Proposed capability: explain what a contribution adds, to whom, and how it is supported.** Keep topical similarity, factual support, distinctiveness and usefulness as separate dimensions. A low cosine similarity could reflect different wording or irrelevant material; a highly similar page can contain the clearest answer.

The information-gain patent describes additional information relative to documents previously viewed in a particular context. Its publication does not establish a universal deployed ranking score.[^10] Likewise, experimental GEO visibility gains are not evidence that adding a quota of quotations, statistics or references will improve an individual site's organic rankings.[^9]

### A practical claim ledger

For each important contribution, record the claim, reader task, source or method, date, scope, owner, relevant page section and verification state. Compare it with the inspected competitor set. The honest distinction is “not found in these five successfully inspected pages,” not “unique on the internet.”

Use explicit states: verified first-party fact; supported external fact; expert interpretation with attribution; proposed research; unsupported; contradicted; or outdated. A citation's presence is different from whether the cited source supports the surrounding assertion. “Verified first-party” requires an authorized business source or documented observation, not simply publication on the business website.

| Contribution type | What would make it useful and credible |
|---|---|
| Original data | A relevant question, collection method, sample, period and limitations. |
| Expert explanation | A real contributor, appropriate expertise, approval and an explanation that helps the intended reader. |
| Local operational detail | Confirmed location-specific facts, current photographs and a clear owner for updates. |
| Transparent comparison | Consistent criteria, dated evidence, limitations and a fair account of tradeoffs. |
| Calculator or checklist | A real task, documented assumptions, tested behavior and accessible output. |
| Better explanation | An accurate example, diagram or sequence that resolves a demonstrated point of confusion. |

These are proposed editorial options, not mandatory ranking features. Conventional facts may still deserve space when necessary to understand the subject. The goal is to preserve a sound baseline and add justified value, rather than remove every sentence shared with competitors.

### Example: a visit-preparation article

A hypothetical local emergency-care visit guide might already explain a familiar process. Useful additions could include an authorized entrance photograph, confirmed parking directions, a current facility-specific preparation checklist and a verified explanation of the facility's administrative process.

The plugin should identify missing evidence and create assignments: confirm the parking information with its owner, obtain an approved image, or have an appropriate reviewer check a relevant section. It must not invent prices, wait times, available services, credentials or clinical claims to improve an originality score. This example is a workflow illustration, not an assessment of erofwhiterock.com.

### Produce a brief before producing prose

The proposed output is a short baseline summary, unresolved reader needs, supported contributions available now, evidence still needed, and a preservation list for the existing article. Every suggested addition should explain its benefit and cost. If the site has nothing useful to add yet, the right next step may be research, an expert interview or maintaining the current page.

A strong brief can legitimately recommend a small clarification. Google's self-assessment centers substantive added value; it does not require every useful page to introduce a new scientific finding.[^1]


## A decision engine that can explain and reconsider

**Proposed capability: one persistent decision record for each content intervention.** Claude may interpret evidence and draft alternatives; the plugin should enforce evidence requirements, action capabilities and consistency. Better prompts alone cannot prevent a size threshold or missing-data default from producing a poor plan.

### Separate observation, explanation and action

“Two URLs appeared for a query during the reporting window” is an observation. “They may serve overlapping needs” is an interpretation. “Merge them” is an action requiring additional evidence. The system should preserve those boundaries in both tool responses and the explanation shown to the operator.

First establish the actual reader and business objective. Then check data readiness, page purpose and technical health. Generate realistic alternatives, including keeping the page, a small edit, linking, differentiation and investigation. Choose an eligible action with the least unnecessary disruption that adequately addresses the problem.

The existing action/research intent classifier can provide a starting hypothesis, but a service page can also answer supporting research questions. Store a primary task and compatible secondary tasks. Do not split a helpful page solely because a coarse classifier recognizes both.[^24]

### Minimum decision contract

| Record | Purpose |
|---|---|
| Decision ID, policy version and snapshot IDs | Reproduce the assessment and identify what changed later. |
| Scope and objective | Identify URLs, language, query family, reader need and intended outcome. |
| Observations with evidence links | Make each material assertion inspectable. |
| Hypotheses and missing evidence | Show alternative explanations and uncertainty explicitly. |
| Eligible alternatives | Explain why plausible options were accepted or rejected. |
| Proposed changes and preservation requirements | Identify affected sections, settings, links and URLs. |
| Expected benefit, risk and effort | Use justified qualitative estimates, not invented percentage lifts. |
| Preconditions and verification plan | Ensure the actual plugin capabilities support the plan. |
| Review, execution and follow-up states | Track what was approved, applied, verified and later observed. |

Do not display numeric confidence as calibrated probability unless evaluation demonstrates calibration. Separate evidence coverage from model confidence. Ten retrieved pages can still provide weak evidence for the wrong query or location.

### Stability without refusing useful work

For the same evidence and policy version, deterministic checks and action eligibility should remain stable. A new model narrative must not silently overwrite an existing reviewed decision. Material disagreement should record the differing interpretation and enter review. New evidence, a changed policy or an explicit editorial correction can justify revising the decision.

Missing conversion data might block a confident recommendation to retire a commercial page; it should not block reading that page or preparing alternatives. A stale snapshot should trigger re-fetching, not a categorical verdict. Unknown analytics must never become zero performance.

### Plans must match available capabilities

Before offering execution, resolve the installed editor, SEO provider, translation layer and redirect mechanism using the earlier capability and adapter work. Validate every proposed action against its actual schema. Create a concrete draft diff and run the shared change-set checks before applying authorized changes.[^26]

This connects the earlier reliability work to content strategy: the decision record explains why an action is appropriate, while the execution contract verifies that the plugin can perform and check it.


## When to link, differentiate, merge or leave alone

**Similarity is a discovery signal, not a verdict.** Topical relatedness, textual duplication, competing page purposes and measurable performance harm are different conditions. Google's systems can surface more than one result from a site when appropriate. Its general SEO guidance also distinguishes duplicate content from a spam violation.[^3][^4]

A query/page overlap in a 28-day report does not prove that both URLs competed in the same search result at the same time. Segment repeated observations by relevant query family, geography, device, language and search type where data permits. Search results and reported position depend on aggregation context.[^14][^15] Even persistent overlap establishes a candidate for diagnosis, not causation.

### Proposed action policy

| Action | Minimum justification | Do not infer it from |
|---|---|---|
| Keep and optionally link | Distinct reader tasks or useful complementary coverage. | A requirement to change every similar pair. |
| Differentiate | Confusing overlap, but valid separate purposes can be made clearer. | Different keywords alone. |
| Refresh in place | A verified factual, usability or coverage deficiency in an otherwise appropriate URL. | Age or a short traffic dip alone. |
| Merge and permanently redirect | Substantially substitutable purposes; a destination can preserve valuable unique material and important user journeys. | Three-page cluster size or cosine similarity. |
| Canonicalize | Duplicate or very similar versions need to remain accessible, with a defensible preferred version. | Two related articles on different questions. |
| Noindex | A documented reason to keep a page available while excluding it from Search. | A shortcut for consolidating related pages. |
| Retire with 404/410 | No continuing purpose and no suitable replacement, after checking obligations and dependencies. | Zero reported clicks or missing analytics. |
| Fix technical issue | Verified fetching, indexing, rendering or measurement fault. | A belief that every decline needs new prose. |
| Observe or investigate | Evidence is insufficient for the proposed change. | An assumption that uncertainty means failure. |

The table is a proposed conservative editorial policy. Canonicalization is specifically intended for duplicate or very similar URLs; Google does not recommend noindex as a substitute for canonical selection within a site.[^5] Actual removal should return an appropriate HTTP status rather than an empty success page.[^17]

### Validate purpose before consolidation

Compare audience, task, entities, location, language, time sensitivity and required format. Then inspect distinctive queries, conversions where available, links, useful sections and navigational dependencies. Existing translations or regional alternatives need their own treatment; similar meaning across languages is not a reason to merge them.[^18]

An article explaining a process and a service page enabling the next action may belong together through links. Two outdated articles answering the same question might suit consolidation, but only after identifying a suitable destination and preserving their useful differences. These are examples, not live-site findings.

Connected similarity clusters also need pairwise review: page A resembling B and B resembling C does not establish that all three serve the same purpose. Replace the current cluster-size decision with a comparison dossier. When analytics are unavailable, a documented editorial duplicate may still justify consolidation, but the system must state that basis and avoid claiming measured SEO harm.[^20]


## Revamp the right article, for the right reason

**Proposed capability: a refresh diagnosis followed by the smallest effective intervention.** Keep the existing refresh queue as a source of candidates, not as authorization to rewrite. It already considers click decline and age and suppresses recently edited pages; those are useful operational controls, but they do not identify the cause of a decline.[^21]

### Diagnose before changing content

| Observed pattern | Investigation | Possible response |
|---|---|---|
| Page inaccessible or intended content absent | HTTP, canonical, indexing directives, rendered content and cache evidence. | Correct the verified technical issue. |
| Impressions fall across a topic | Comparable periods, query mix, industry demand and seasonality. | Observe demand or adjust the portfolio. |
| Impressions stable, clicks lower | Query/device mix, position and an observed change in result presentation. | Inspect title/snippet alignment or changed reader needs. |
| Readers arrive but cannot complete a task | Available conversion evidence and direct usability checks. | Clarify the next step or fix the interaction. |
| Important facts have changed | Check the authoritative business or external source. | Make a targeted factual update promptly. |
| Comparable pages answer a material need better | Inspect their evidence and the actual unmet task. | Add supported coverage or restructure the relevant section. |

These are diagnostic hypotheses, not one-to-one causal rules. Google's traffic-drop guidance explicitly includes technical problems, indexing, demand and seasonal explanations.[^6] A decline alone should not choose the remedy.

### Preserve value before editing

Build a section map that identifies supported facts, successful query coverage, useful examples, links, media, calls to action and translation dependencies. Record which elements must survive and why. Distinguish mandatory factual corrections from optional optimization.

Offer an intervention scale: fact patch, clarification, section improvement, structure change or full rewrite. Avoid a minimum deletion quota, automatic word-count expansion or a new URL merely because a blog is old. Current rewrite validation already has exceptions for small edits; extend that into purpose-specific checks instead of another rigid percentage policy.[^24]

A revised outline should explicitly connect each addition to a reader need and an evidence source. Show removed material separately, including anything that lacks a replacement. A human reviewer can then assess a concrete diff instead of approving a vague “SEO revamp.”

### Verify both publication and later outcomes

Use the earlier coordinated change-set system to verify the saved editor data, final HTML, metadata, links and cache result.[^26] Check that the page still performs its intended tasks and that no important section disappeared during builder serialization.

Later measurement should account for the change date, recrawl/index evidence when available, reporting completeness and other interventions. Google's core-update guidance discourages drastic reactions to small ranking movements and notes that effects can take days to months.[^8] The existing short outcome windows can support early monitoring; they cannot serve as universal proof that a rewrite worked.

Prevent repeated incompatible rewrites during an observation period unless new evidence identifies a concrete error. Record “too early to assess” when appropriate, while still correcting verified factual or functional defects.


## Contextual linking and safe consolidation

**Proposed capability: links that support the reader's next task.** Extend the existing link graph and topic assignments, rather than add a separate graph with different URL identities. These modules already provide useful relationship and audit data.[^23]

A link suggestion should identify the source passage, proposed anchor, verified destination, reader benefit and relationship type: explanation, prerequisite, comparison, example or next action. Similar vocabulary can discover candidates; it should not determine the final placement.

Google recommends crawlable links, understandable anchors and context that helps readers. It does not prescribe an ideal number of links.[^7] Replace blanket rules such as always placing the pillar in the introduction and conclusion or choosing the first four supporting pages. Those patterns currently appear in keyword briefs and can produce repetitive links without a contextual reason.[^21]

### Link eligibility and review

Resolve the intended destination and check its language, response, canonical identity and indexing status. An internal link to a necessary utility or noindex page can still help people; the plugin should distinguish that purpose from a proposed search-discovery link.

Detect already-linked destinations, awkward anchors, redirects, self-links and broken targets. Prioritize important pages that lack useful incoming paths, then inspect relevance at the actual paragraph. Avoid turning an orphan-page count into a demand to link every page everywhere.

Preview changes in context and support batch review with exclusions. Verify both the saved content and the rendered link. Preserve author-defined links unless a concrete problem justifies replacing them.

### A consolidation is a content migration

Before recommending a merge, produce a source-to-destination preservation map. For every source page, list the unique facts, sections, reader tasks, useful queries, media and incoming dependencies that matter. Include conversions and external-link evidence when available; identify those fields as unknown when they are not connected.

Select a destination based on fit and preservation, not merely the shortest URL or highest current clicks. Prepare the combined article and validate it before retiring source URLs. Confirm that the destination remains useful, accessible and consistent with its title, canonical and navigation.

Google supports redirects to a relevant consolidated destination and warns against sending unrelated retired URLs to a generic destination.[^12] Permanent moves should use an appropriate permanent redirect; canonical hints are a different mechanism.[^16]

### Execution and follow-up

Use a reviewed change set covering content, exact redirect mappings, internal links, sitemap-facing state and cache verification. Apply in a recoverable order, record intermediate states and stop if the destination fails verification. Check redirect chains, loops and final content.

Monitor the whole affected URL set and query family, not only the surviving page. Keep a rollback record for content and mappings, while making clear that reversing an implementation does not guarantee immediate reversal of search effects. A merge is therefore a higher-risk action than adding one contextual link.

The proposed planner should first consider whether linking or clearer differentiation would solve the problem while preserving both useful pages.


## Additional features and a trustworthy learning loop

The strongest expansion is a connected editorial workspace. Each feature should improve a real decision rather than introduce another opaque SEO score.

| Proposed feature | Practical benefit |
|---|---|
| Unanswered-question inbox | Turn approved support, onsite search and editorial questions into research opportunities; store minimal necessary information. |
| Fact and citation watchlist | Flag changed, expired or unsupported claims and identify the responsible reviewer. |
| Cross-page consistency checks | Detect conflicting business facts across articles, service pages and structured data. |
| Evidence assignments | Request a specific photo, observation, expert explanation or documented fact before drafting. |
| Content portfolio map | Show reader tasks covered, useful journeys and gaps, alongside the existing topic graph. |
| Competitive change alerts | Surface material new evidence or changed tasks, rather than every heading edit. |
| Multimedia opportunity brief | Suggest a diagram, original image or tool only when it helps explain or complete a task. |
| Decision history and review feedback | Explain changed advice and preserve corrections with their evidence. |

For a healthcare-related site, operational research inputs should exclude unnecessary patient details. Clinical claims require suitable sources and an appropriate review workflow; the assistant must not fabricate expert approval or original findings. These are product design requirements for this context, not a medical assessment.

### Prioritize eligible opportunities

Use a transparent queue ordered by reader/business importance, evidence strength, expected practical benefit, effort and risk. Fix confirmed factual or functional problems first. Then improve valuable existing pages before commissioning additional content without a demonstrated purpose.

Do not convert a guessed traffic gain into a precise revenue forecast. If measurement is disconnected, label commercial impact unmeasured. If search demand is only an estimate, preserve its source, period and limitations.

Google's spam policies address doorway pages and scaled content made primarily to manipulate rankings without helping users.[^13] A page-per-location or page-per-query generator should therefore require a distinct user purpose and sufficient supported material, not just changed place names. Google's AI guidance similarly gives no basis for producing every follow-up variation as a separate page.[^2]

### Learn from outcomes without learning false causes

The existing edit-outcome module can anchor an intervention registry.[^24] Record the changed URLs, hypothesis, intended metric, baseline, rollout time, competing changes and expected observation needs. Measure useful outcomes such as qualified actions, task completion and relevant organic visibility, subject to available tracking.

A before/after increase is an association. Seasonality, demand, algorithm changes and other edits can produce the same pattern. Counterfactual methods can help when adequate controls and assumptions exist, but they do not manufacture certainty from sparse data.[^11] Start with transparent comparisons and annotate confounders; add more advanced analysis only when justified.

Corrected editorial decisions should enter a reviewed evaluation set. Do not train future recommendations to imitate an earlier confident answer simply because it was accepted. Track unsupported claims, unnecessary rewrite recommendations and decision reversals alongside business outcomes.

Control research cost with URL/query budgets, cached evidence, freshness policies and provider usage reporting. Manual URL input should remain a useful fallback; paid search-result or backlink data can be optional capabilities.


## Implementation sequence and acceptance criteria

This is an additive roadmap. Keep the prior R01–R18 reliability, capability, SEO-policy and execution work. In particular, trustworthy content analytics depend on fixing the earlier GSC ingestion issues; safe changes depend on the shared operation registry and coordinated change sets.[^26]

| Phase | New work | Dependency and release condition |
|---|---|---|
| A: correct misleading decisions | C01–C04: retire unsupported labels, fix aggregation and authority matching, share content extraction. | R01–R04 and R06. Reproduction fixtures no longer yield unjustified action or evidence claims. |
| B: research and decide | C05–C08: competitor snapshots, claim ledger, persistent decisions and relationship diagnosis. | R06, R08 and R09. Every material recommendation has evidence and eligible alternatives. |
| C: execute useful changes | C09–C11: refresh planner, contextual links and consolidation change sets. | R05, R10–R12. Preview, preservation and rendered verification pass. |
| D: operate and learn | C12–C15: editorial portfolio, fact watchlist, outcome analysis and evaluation/budget controls. | R14 and R16 where applicable. Monitoring distinguishes observations, unknowns and inferred causes. |

Relative effort, acceptance criteria and detailed dependencies are included in the accompanying CSV. They are planning estimates, not a delivery commitment. Ship each phase behind versioned policies and migrate existing stored reports without silently reinterpreting historical scores.

### Required evaluation scenarios

1. Three healthy related articles remain separate unless their purpose and preservation review justifies consolidation.
2. Identical query overlap with different language, geography or reader tasks does not automatically become harmful cannibalization.
3. Missing, stale or partial analytics produces an explicit data state; it never becomes proof that a page has no value.
4. A price mention, the word DO, an unattributed quote or a lookalike hostname cannot establish first-party evidence or expert authority.
5. A blocked competitor fetch remains unknown; an Elementor article is assessed from available substantive content.
6. Adding superficial statistics cannot earn a claim of verified originality; similarity thresholds cannot certify global uniqueness.
7. The weighted-position arithmetic remains correct, and crossing 999 to 1,000 impressions alone cannot dictate a strategic action.
8. A traffic drop caused by a known indexing problem yields a technical diagnosis rather than an automatic rewrite.
9. A proposed merge preserves required sections and relevant destinations; failed destination verification stops retirement.
10. The same evidence and policy preserve action eligibility; changed advice records its new evidence or reviewed interpretation.

These are proposed release gates, not tests already passed. The bundled PHP probes document the current behavior only.

### Definition of success

The operator should be able to inspect why a decision was made, what remains unknown, what will change and how success will be assessed. Evaluation should target complete traceability for material claims and zero unsupported high-impact recommendations in the agreed fixture set. Those are engineering targets, not guarantees about every future model response.

The final standard is better editorial judgment with fewer unnecessary changes. More articles, more internal links and higher local scores are not success measures by themselves. No feature in this roadmap guarantees a particular Google position.


## Sources

External documentation accessed September 9, 2026. Local paths are relative to the installed plugin unless stated otherwise.


[^1]: Google Search Central. [Creating helpful, reliable, people-first content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content). Live documentation; updated 2025-12-10. Accessed 2026-09-09. Original value, first-hand expertise, content self-assessment, and avoidance of arbitrary length or superficial freshness.


[^2]: Google Search Central. [Optimizing your website for generative AI features on Google Search](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide). Live documentation. Accessed 2026-09-09. Non-commodity content and useful experiences; not a requirement to create a page for every query variation.


[^3]: Google Search Central. [A guide to Google Search ranking systems](https://developers.google.com/search/docs/appearance/ranking-systems-guide). Live documentation. Accessed 2026-09-09. Passage understanding, conceptual matching, and site diversity. A second URL is not automatically evidence of harmful competition.


[^4]: Google Search Central. [Search Engine Optimization (SEO) Starter Guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide). Live documentation. Accessed 2026-09-09. Duplicate content and general content guidance; similarity alone does not establish a spam penalty.


[^5]: Google Search Central. [How to specify a canonical URL with rel=canonical and other methods](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls). Live documentation. Accessed 2026-09-09. Canonicalization applies to duplicate or very similar URLs; signals and limitations.


[^6]: Google Search Central. [Debugging drops in Google Search traffic](https://developers.google.com/search/docs/monitor-debug/debugging-search-traffic-drops). Live documentation. Accessed 2026-09-09. Separate technical, indexing, search demand, seasonal, and other explanations before prescribing a rewrite.


[^7]: Google Search Central. [Link best practices for Google](https://developers.google.com/search/docs/crawling-indexing/links-crawlable). Live documentation. Accessed 2026-09-09. Crawlable links, descriptive anchors, useful context, and no magical ideal link count.


[^8]: Google Search Central. [Google Search's core updates and your website](https://developers.google.com/search/docs/appearance/core-updates). Live documentation; updated 2025-12-10. Accessed 2026-09-09. Avoid drastic responses to small movements; meaningful changes and variable observation times; deletion as a last resort.


[^9]: Aggarwal et al., KDD 2024. [GEO: Generative Engine Optimization](https://arxiv.org/html/2311.09735v3). arXiv v3, 2024-06-28. Accessed 2026-09-09. Experimental generative-engine visibility. Section 9 explicitly does not evaluate effects on search rankings. Results are conditional on the studied systems and benchmark.


[^10]: Google LLC; WIPO patent publication. [Contextual estimation of link information gain](https://patents.google.com/patent/WO2020081082A1/en). Published 2020. Accessed 2026-09-09. Primary patent document hosted by Google Patents. Describes additional information relative to previously viewed documents; publication does not demonstrate deployment as a universal ranking score.


[^11]: Brodersen et al.; Google Research. [Inferring causal impact using Bayesian structural time-series models](https://research.google/pubs/inferring-causal-impact-using-bayesian-structural-time-series-models/). Annals of Applied Statistics 9 (2015), 247–274. Accessed 2026-09-09. Counterfactual methods for intervention analysis; a conceptual reference, not proof that an SEO edit caused a change.


[^12]: Google Search Central. [How to move a site](https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes). Live documentation. Accessed 2026-09-09. Relevant URL mapping, consolidation destinations, and avoiding irrelevant bulk redirects.


[^13]: Google Search Central. [Spam policies for Google web search](https://developers.google.com/search/docs/essentials/spam-policies). Live documentation. Accessed 2026-09-09. Doorway abuse and scaled content abuse; relevance to mass-producing low-value location or query variants.


[^14]: Google Search Console API. [Search Analytics: query](https://developers.google.com/webmaster-tools/v1/searchanalytics/query). Live documentation. Accessed 2026-09-09. Dimensions, Pacific Time date boundaries, data state, and incomplete top-row returns.


[^15]: Google Search Console Help. [What are impressions, position, and clicks?](https://support.google.com/webmasters/answer/7042828). Live documentation. Accessed 2026-09-09. Impression-based average position, aggregation context, and variability of search results.


[^16]: Google Search Central. [Redirects and Google Search](https://developers.google.com/search/docs/crawling-indexing/301-redirects). Live documentation. Accessed 2026-09-09. Permanent versus temporary redirects and indexing signals.


[^17]: Google Crawling Infrastructure. [How HTTP status codes affect Google's crawlers](https://developers.google.com/crawling/docs/troubleshooting/http-status-codes). Live documentation; updated 2026-02-04. Accessed 2026-09-09. HTTP success does not guarantee indexing; 404/410 and other 4xx handling.


[^18]: Google Search Central. [Tell Google about localized versions of your page](https://developers.google.com/search/docs/specialty/international/localized-versions). Live documentation. Accessed 2026-09-09. Language and regional alternatives are not inherently candidates for consolidation.


[^19]: CC Assistant local source. includes/class-rest-api.php; includes/class-win-audit.php. Installed version 0.84.0. Accessed 2026-09-09. info_gain_signals at line 6404; WinAudit fingerprint and authority-host matching around lines 330–410; dim_info_gain at 555; dimension weights, status bands, intent and freshness logic. See source-hashes.json for the inspected files.


[^20]: CC Assistant local source. includes/class-topical-authority.php; includes/class-similarity.php. Installed version 0.84.0. Accessed 2026-09-09. classify_cluster at 182 and recommendation at 203; title/body content_gaps at 291. Similarity uses corpus TF-IDF and weighted title/heading/body terms; Elementor extraction is present in the similarity module.


[^21]: CC Assistant local source. includes/class-seo-tools.php. Installed version 0.84.0. Accessed 2026-09-09. cannibalization at 78; refresh_queue at 306; prepare_rewrite_brief at 759; competitor_brief at 993; brief_for_keyword at 1058; external_originality_check around 3147. Review findings distinguish suggestions/prompts from executed collection.


[^22]: CC Assistant local source. includes/class-cannibalization-trends.php; bin/warehouse.php. Installed version 0.84.0. Accessed 2026-09-09. Trend collection labels summed candidate impressions leak_impressions. cc_commodity_classify around warehouse.php line 839 uses an assumed CTR curve and fixed thresholds.


[^23]: CC Assistant local source. includes/class-topic-clusters.php; includes/class-internal-links.php; includes/class-link-audit.php. Installed version 0.84.0. Accessed 2026-09-09. Existing persistent pillar/supporting assignments, internal graph and link audit functionality; extend these modules rather than create a competing graph.


[^24]: CC Assistant local source. includes/class-edit-outcomes.php; includes/class-page-intent.php; includes/class-pre-publish.php. Installed version 0.84.0. Accessed 2026-09-09. Existing outcome windows; coarse action/research intent policy; rewrite quality ratios with surgical/incremental exceptions. Preserve working safety and small-edit paths.


[^25]: Isolated local validation. strategy-probes.php and strategy-probe-results.json. Executed 2026-09-09. Accessed 2026-09-09. Actual installed methods called with synthetic fixtures and stubbed WordPress helpers, PHP 8.2 plus mbstring. No WordPress bootstrap, database access, competitor fetching, or live changes. The position example is arithmetic, not an executed SQL fixture.


[^26]: CC Assistant prior research. CC-Assistant-Research.pdf and CC-Assistant-Roadmap.csv. 2026-09-09. Accessed 2026-09-09. D:/cc-assistant/reports/research-2026-09-09/. Earlier reliability and technical SEO roadmap R01–R18 remains the foundation. New C-series items supplement that work.
