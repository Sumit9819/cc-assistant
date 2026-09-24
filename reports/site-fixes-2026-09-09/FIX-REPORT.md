# ER of White Rock: repair report — September 9, 2026

**One language/pairing repair is live and verified. There are 81 content, metadata and template proposals awaiting WordPress administrator application, IDs 1732–1812, across 53 distinct page/template records.** These are changes, not 81 separate pages. No live content application or new CC Assistant release occurred in this pass. The live connector remains 0.89.3.

The final read confirmed all 81 rows are pending, with no duplicate pending IDs or unexpected rows. Their recorded environment hashes agree; proposals for the same target also share the same observed source hash. Those checks establish a consistent queue at capture time, not a guarantee that no subsequent site edit will invalidate a plan.

[Review the prepared changes in WordPress](https://erofwhiterock.com/wp-admin/admin.php?page=cc-assistant-pending). `pending-changes.csv` lists every proposal, target and summary. Full before/after evidence is in the private JSON files alongside this report.

## Already live

Spanish Richardson page **5496** is now assigned to Spanish and paired with English **5495**. The current Spanish URL is [sala-de-emergencias-cerca-de-richardson](https://erofwhiterock.com/es/sala-de-emergencias-cerca-de-richardson/). Fresh hreflang checks passed for both members. A browser request to the old unprefixed URL followed a redirect to the Spanish destination, which returned 200 with `lang="es-ES"` and its correct canonical. The exact redirect status was not recorded; this report does not assume it was 301.

The user's clarification about the connected clinical review team is saved in site memory. This is an operational note, not a new review credential or a claim that these revisions have received clinical approval.

## Prepared corrections

| Area | Concrete correction | Pending IDs |
|---|---|---|
| Stroke, English/Spanish | Corrected the clot-busting treatment distinction, 911 instructions, unsupported treatment/credential promises, manual schema and copied form warnings. Corrected a Spanish directions link that named another facility. | 1732–1733 |
| Clinical review team | Preserved collective review credit; changed “Nursing Team” to “Clinical Team.” The policy now includes nurses, physicians and other healthcare practitioners. Retained the team link and ordinary updated date. | 1734–1735 |
| Appendicitis, English/Spanish | Replaced unsupported onsite surgery language with evaluation and coordination of surgical care; removed the wrong business name and repaired imaging links. | 1736–1737 |
| Imaging, English/Spanish | Removed unsupported MRI/mammography promises from titles/descriptions, clarified emergency imaging and appointment language, removed unverified pediatric-radiologist wording, and translated the insurance paragraph. | 1738–1739, 1756–1761 |
| Lab, English/Spanish | Qualified accuracy and turnaround statements, brought the Spanish explanation into line with the previously approved English correction, repaired unrelated form warnings, and prepared accessible link styling on dark sections. | 1740–1741 |
| Sore-throat form sections | Replaced copied traumatic-brain-injury warnings with relevant general emergency instructions. This is not a full clinical rewrite of these pages. | 1742–1743 |
| Neighborhood pages | Removed the under-ten-minute promise for every visit from ten pages and replaced it with an explanation of triage and variable timing. | 1744–1753 |
| Homepages | Removed selected unsupported waiting-time and credential statements, reconciled insurance acceptance with the published out-of-network disclosure, and removed the no-wait SEO promise. | 1754–1755, 1762–1763 |
| Spanish services hub / eye injury | Corrected copied service-area and driving-direction text on 4751; removed hospital waiting-room comparisons on 1338. | 1764–1765 |
| Internal links | Repaired the four confirmed broken destinations used by 11 links on four pages, plus related outdated Spanish service links. Used the appropriate current destination and avoided a new self-link. | 1766–1769 |
| Policy encoding | Repaired garbled accents, punctuation and spaces on eight pages; fixed malformed privacy/contact links and replaced Google tracking wrappers with their same direct destinations. Preserved the legal prose's meaning. | 1770–1777 |
| Authors | Changed the remaining 22 page-author assignments from adminsumit to the established public ER of White Rock author, ID 4. No individual clinician identity was invented. | 1778–1799 |
| World Cup articles | Corrected the historical study subgroup, removed unsupported studies/quotes and Dallas ER-spike claims, updated past-event wording and emergency advice, and aligned titles/excerpts/SEO descriptions in both languages. | 1800–1809 |
| Blog canonical | Confirmed the stored `rank_math_canonical_url` is `/blog/2/`; queued clearing that explicit override. Verify generated canonicals on the root and later pagination pages after application. | 1810 |
| Spanish byline / mobile layout | Changed the Spanish template to use the actual post author instead of rendering “Admin”; disabled the irrelevant date-archive link. Reduced mobile H1 size and added side padding in both language templates using supported Custom CSS. | 1811–1812 |

## Review-team clarification

The prepared badge reads **“Medically reviewed by the ER of White Rock Clinical Team”** and links to the existing policy. The policy describes the multidisciplinary team confirmed by the operator. No team members or connected links were removed, and no individual names, credentials or review dates were fabricated.

An ordinary content update remains separate from a clinical review. The clinical team should check the revised medical passages before publication; an administrator's Apply click alone is not evidence that a clinician reviewed this content version. A per-version review record remains a useful future plugin feature.

## Verification performed

- Read the current source before building proposals and reread each target before queueing. The safe queue worker compared the complete stored content, Elementor data, title and author with the reviewed source. Published targets also received a fresh usable server audit; templates received full source reads.
- Kept the plugin's evidence, source-change, environment, schema and content checks enabled. Initial attempts that lacked fresh evidence created no rows. Two template proposals failed supported-control validation; they were rebuilt and passed as 1811–1812. No failed or duplicate template proposal remains pending.
- At a 390px viewport, browser-only previews reduced the new English article H1 from 55px to 32px and its measured height from 275px to about 115px; the Spanish sample also improved. Side padding increased from 0 to 16px. Desktop styling is outside the mobile media query and is preserved by the proposed CSS.
- The lab-page preview removed the observed link color-contrast failures using white underlined links on the dark background. Focus styling is included. These are preview results; rerun after application.
- Isolated the Richardson accordion ARIA problem in a browser preview. Removing the unsupported `aria-selected` attribute removed that specific axe failure. This diagnostic DOM edit was **not** persisted to WordPress; the third-party widget issue remains open.
- Confirmed that `/blog/`, `/blog/2/` and `/blog/3/` currently all declare `/blog/2/`, then confirmed the matching explicit Rank Math value from proposal 1810's before-state. Clearing it addresses the observed override; final pagination output must still be tested.
- Verified that the supported browser bridge connects with version 0.89.3 without disabling SiteGround protection. This does not restart an already-running stale Claude MCP process.

## Apply and check

1. Review the prepared batch in **CC Assistant → Pending Changes**, with the medical team checking the revised clinical passages. Apply the content/template corrections before changing plugins, themes, kit or SEO configuration. Those changes can legitimately invalidate a proposal's environment snapshot.
2. If WordPress reports a changed-source/environment conflict, reread the current target and rebuild only the affected proposal. Do not disable the guard or requeue the entire batch blindly.
3. Verify the rendered stroke pages, imaging titles/descriptions, lab copy and contrast, review badge, Spanish byline, changed authors/schema and corrected links. Test mobile and desktop samples. Recheck the blog root and at least pages two and three for appropriate canonicals; clearing the override is not a substitute for this check.
4. After the batch is applied and verified, stage third-party updates and repeat the affected language, pagination, template and accessibility checks. Google indexing outcomes should be monitored separately and will not update immediately after Apply.

The interactive Apply requirement comes from **`D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-access.php`, `CC_Assistant_Access::can_review()`**, which states: “Automation may propose; approving requires an interactive administrator.” Application Password requests cannot approve this batch. No bypass was attempted.

## Work still open

| Item | Current state and next concrete step |
|---|---|
| Apply / clinical validation | All 81 proposals remain pending. The medical review team must check the revised clinical claims; live rendering and schema still need verification after application. |
| Actual facility capabilities and credentials | Removing unsupported statements does not independently verify equipment, accreditation, staffing, billing operations or every remaining medical sentence. Additional claims on imaging/lab/sore-throat pages need facility/clinical confirmation. |
| Spanish categories | English category names still appear on Spanish terms. The current `draft_update_term` capability supports descriptions/SEO fields, not renaming. Rename the existing terms through WP admin or add a supported rename operation; do not create duplicate categories to hide this limitation. |
| Spanish menus / redirect chain | Retired slugs still redirect successfully. Update menu items to final URLs and flatten the known Rank Math services chain. Current tools lack menu updates and an atomic redirect-edit operation; no working redirect was deleted. |
| Spanish homepage sitemap alias | The sitemap still uses the redirecting `/es/` alias. Inspect Polylang/Rank Math homepage sitemap generation and prefer the final canonical URL; do not treat blog/archive sitemap omission alone as a defect. |
| Accordion ARIA and updates | Stage Elementor 4.2.4, Polylang 3.8.9, Rank Math 1.0.278, Simple History 5.32.0 and SG Speed Optimizer 7.8.2, as advertised by this site's audit-time update inventory. Retest the legacy accordion before marking it fixed. No vendor code or plugin update was deployed here. |
| Stale running connector | The saved configuration works in a fresh bridge. Restart/reconnect the affected Claude MCP session so it loads browser transport and SQLite support. Keep SiteGround protection enabled. |
| Separate GSC server cache | The local warehouse was refreshed through September 7 during the audit. The server's separate cache remains `legacy_unverified`; schedule its supported admin backfill and verify provenance. Local sync alone does not repair it. |
| Business Profile metrics | Google's account-management API returned quota HTTP 429. The connection does not provide the Cloud project access needed to resolve quota/approval. This is not evidence that the public business listing is broken. |
| Google indexing | The prior audit found 117 indexed, 9 explicitly non-indexed and 3 unknown among 129 URLs. Monitor and investigate the established service-page exceptions after corrections. No noindex, deletion, merge or promise of indexing was introduced. |
| Diagnostic improvements | Rendered-link graph accuracy, template-aware audits, per-version review evidence and automatic translation-drift tasks remain plugin development work. Current HTML audits do not establish medical accuracy, form delivery, conversion tracking or field Core Web Vitals. |

## Sources for substantive corrections

Stroke wording follows [NHLBI treatment guidance](https://www.nhlbi.nih.gov/health/stroke/treatment) and [CDC emergency instructions](https://www.cdc.gov/stroke/signs-symptoms/index.html), with [Spanish CDC guidance](https://www.cdc.gov/stroke/es/signs-symptoms/signos-y-sintomas-de-accidente-cerebrovascular.html). Lab interpretation and imaging scope were checked against [MedlinePlus lab-results guidance](https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/) and [diagnostic-imaging information](https://medlineplus.gov/diagnosticimaging.html). Billing wording retains this site's published out-of-network disclosure and distinguishes it from [CMS patient insurance protections](https://www.cms.gov/initiatives/your-patient-rights/medical-bill-rights/know-your-medical-bill-rights/know-your-rights-insurance).

The World Cup correction uses the [original 2006 Munich study](https://pubmed.ncbi.nlm.nih.gov/18234752/): its 3.26 figure describes men, not the coronary-artery-disease subgroup. It does not measure Dallas in 2026. Practical passages use [AHA heart-attack warning signs](https://www.heart.org/en/health-topics/heart-attack/warning-signs-of-a-heart-attack), the [AHA alcohol scientific statement](https://professional.heart.org/en/science-news/alcohol-use-and-cardiovascular-disease/top-things-to-know), [AHA AFib risk guidance](https://www.heart.org/en/health-topics/atrial-fibrillation/who-is-at-risk-for-atrial-fibrillation-af-or-afib), [CDC heat guidance](https://www.cdc.gov/heat-health/about/index.html) and the [official FIFA schedule](https://www.fifa.com/en/en/mens/worldcup/canadamexicousa2026/articles/match-schedule-fixtures-results-teams-stadiums).

The canonical correction is consistent with [Rank Math's documented canonical setting](https://rankmath.com/kb/how-to-change-canonical-url/). The local database before-state, not generic documentation, establishes the actual wrong override on this site.

## Evidence files

`final-verification-results.json` records final pending state and the final hreflang check. `safe-queue-0-results.json` through `safe-queue-3-results.json`, `safe-remaining-proposals-results.json`, `safe-world-cup-proposals-results.json`, `template-checks-results.json` and `safe-template-fixes-results.json` retain the exact proposal responses. `language-fix-results.json`, `route-verification.json`, `browser-preview.json` and the preview PNGs document the live repair and browser measurements. The archived `work/` folder contains the prepared requests and before/after change logs. Some records include rejected attempts; use final pending state as the authoritative queue inventory.
