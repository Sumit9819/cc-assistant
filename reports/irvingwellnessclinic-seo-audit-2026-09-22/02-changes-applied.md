# Changes Applied: 2026-09-22

All eight went through the cc-assistant Pending Changes queue, were approved by the operator, and were verified live on the server afterwards.

| Pending | Edit | Post | Change |
|---|---|---|---|
| 1430 | 1168 | 924 Lori bio | Meta description 199 to 147 chars |
| 1431 | 1169 | 8559 Anti-Aging | Title 92 to 54 chars |
| 1432 | 1170 | 6359 Glutathione | Compliance rewrite of description |
| 1433 | 1171 | 6290 IV Benefits | Compliance rewrite of description |
| 1434 | 1172 | 9456 Sema vs Tirz | Inline link resolving the site orphan |
| 1435 | 1173 | 9871 Choose a Clinic | Title 96 to 60 chars |
| 1436 | 1174 | 631 Wellness Coaching | Title retargeted |
| 1437 | 1175 | 631 Wellness Coaching | Description retargeted |

## Before and after

**924 Lori bio** (the only description on the site over 160 chars, was truncating)
- Before: `Meet Lori Secerovic, APRN, FNP-C, board-certified nurse practitioner at Irving Health and Wellness Clinic. 17+ years of clinical experience in critical care, hormone therapy, and medical weight loss.` (199)
- After: `Meet Lori Secerovic, APRN, FNP-C, board-certified nurse practitioner at Irving Health and Wellness Clinic. 17+ years in critical care and hormones.` (147)

**8559 Anti-Aging** (worst zero-click page: 2,408 lifetime impressions, 0 clicks ever)
- Before: `Top 3 Anti-Aging Treatments for Fine Lines & Wrinkles | Effective Solutions for Youthful Skin` (92)
- After: `Top 3 Anti-Aging Treatments for Fine Lines and Wrinkles` (55)
- Head keyword kept byte-identical. Only the truncated generic tail removed.

**9871 Choose a Clinic** (1,092 lifetime impressions, 0 clicks)
- Before: `How to Choose a Weight Loss Clinic: 7 Questions to Ask First - Irving Health and Wellness Clinic` (96)
- After: `How to Choose a Weight Loss Clinic: 7 Questions to Ask First` (60)
- The 36-char site-name suffix was Rank Math's default template. Now matches the H1 exactly.

**6359 Glutathione** (YMYL claim exposure)
- Before: `How glutathione IV drips support detox, immunity, and skin clarity. Why IV beats oral supplements, plus dose, frequency, and clinical safety.`
- After: `What glutathione IV drips do, where the evidence is strong and where it stays mixed, who should avoid them, plus dosing and clinical safety.`
- Reason: the body never claims an immunity benefit, says skin findings are "mixed" and expectations "should be modest", frames detox narrowly as phase II conjugation, and on IV versus oral says the "choice depends on goals, budget, and clinical context". The snippet was over-promising relative to the clinic's own carefully hedged page.

**6290 IV Benefits** (YMYL claim exposure)
- Before: `Beyond hydration: seven IV vitamin drip benefits including energy, immunity, skin, hangover relief, jet lag recovery, athletic performance, and stress.`
- After: `Seven common reasons people choose IV vitamin drips, and what the evidence actually supports for each, from hydration to recovery and energy.`
- Reason: the page H1 is "IV Drip Benefits: 7 Common Uses and the Evidence Behind Them" and it carries a section headed "What antioxidants actually do, and what detox does not mean". The snippet asserted more than the article supports. Title left alone: already 54 chars and the page earns impressions.

**631 Wellness Coaching** (demand retarget, not a snippet fix)
- Before: `Wellness Coaching Irving, TX | Longevity & Performance` / `Personalized wellness coaching in Irving, TX. Longevity, sleep optimization, stress recovery, and human performance plans guided by clinicians.`
- After: `Wellness Coach in Irving, TX | Health and Longevity Coaching` / `Work with a clinician-led wellness coach in Irving, TX. Personalized plans for longevity, sleep, stress recovery, and lasting health habits.`
- Reason: this page is NOT ranking badly. It sits at position 2.09 for "best health optimization coaching irving", 2.33 for "best longevity success coach irving" and 2.94 for "lifestyle longevity coaching irving". Those terms have essentially no volume, several look like AI fan-out phrasings, and the largest drew 30 impressions in 28 days. Ranking second for terms nobody searches cannot produce clicks. The demand sits in the noun the page never used: "wellness coach near me", 1,600 monthly searches at SEO difficulty 9, winnable at DA 10.

**9456 Semaglutide vs Tirzepatide** (orphan fix)
- Patch: `Some patients plateau on one medication` became `Some patients <a href="https://irvingwellnessclinic.com/not-losing-weight-on-semaglutide/">plateau on one medication</a>`
- Reason: `/not-losing-weight-on-semaglutide/` was the only indexed orphan with no editorial inbound link. It appears in the theme's "You Might Also Like" widget, but that is template output rather than post_content, so it carries no editorial signal and the nightly link graph correctly counted zero inbound edges. The "Prior response" bullet in this post already discusses patients who plateau on one GLP-1 and respond after switching, which is exactly the orphan's subject.

## VERIFICATION TRAP, worth remembering

After approval, a plain external curl showed ALL SIX meta pages still serving the OLD values. That is NOT a failed apply.

SiteGround's CDN returns `Cache-Control: max-age=172800` with `X-SG-CDN: 1`, so the edge holds stale HTML for up to 48 hours. The database and origin had the new values immediately.

How to verify correctly on this site:
- `verified_page_audit` (loopback fetch, bypasses the edge) shows the truth immediately, and its `comparison` block prints before and after
- Appending `?cb=<random>` to the URL also bypasses the edge
- A plain curl does NOT

Consequence: after any meta edit here, the operator should click "Purge SG Cache" in the admin bar, or Googlebot keeps seeing the old title for up to 48 hours.

## Deliberately NOT done

- **`propose_schema` was not used.** A dry run on post 6220 showed it would scrape H2 headings into FAQ entries, one of which was a call-to-action containing a phone number.
- **The 22 duplicate BreadcrumbList posts were not bulk-edited.** The lint rates it "warn", Google supports multiple breadcrumb trails, and it was not worth 44 queue operations.
- **Titles on ranking pages were not touched** beyond the two zero-click pages. Site history records a title pivot de-ranking a cluster here.
- **6290's title was left alone** despite naming immunity and skin: it is 54 chars, the page earns impressions, and the H1 already qualifies the claim.
