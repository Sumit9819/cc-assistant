---
name: Service pages own local-commercial keywords; new posts must not compete
description: When proposing new WP blog posts via cc-assistant, never use a title that contains both a service noun and the location — that cannibalizes the service page
type: feedback
originSessionId: fb84c00f-69ad-437a-b116-a8c629075265
---
When working on cluster-based WordPress SEO via cc-assistant, the service page is the canonical destination for its primary local-commercial keyword. Supporting blog posts in the same cluster must target a DIFFERENT query intent than the service page.

**The hard rule:** never call `draft_create_post` with a title that contains BOTH (a) a service noun like "weight loss", "iv therapy", "hormone therapy", "microneedling", "laser genesis", "wellness coaching" AND (b) a location term like "Irving", "Irving, TX", or any other city the site serves. That collides head-on with the service page.

**Why:** On irvingwellnessclinic.com (2026-05-08), I proposed a new blog post "Weight Loss Programs in Irving, TX: A 2026 Guide" targeting "weight loss irving". `brief_for_keyword` had explicitly flagged `cannibalization_risk: true` with 4 pages already competing, but I queued a 5th page anyway, framing it as informational even though the title was clearly local-commercial. User pushed back: "our first priority is to rank the service page, and all the other things should be supporting it." That is the correct hierarchy and I had it backwards.

**How to apply:**
1. Before `draft_create_post`, always read `brief_for_keyword`'s `cannibalization_risk` and `existing_pages`. If a service page already ranks for the target keyword, do not target that same keyword.
2. Drop location terms from the new post's title, slug, and meta description.
3. Pick a sharper non-competing angle:
   - **Use-case:** "IV Therapy for Fatigue" (not "IV Therapy in Irving")
   - **Comparison:** "GLP-1 vs Lipo-B vs Lifestyle for Weight Loss"
   - **Decision-making:** "How to Choose a Weight Loss Clinic: 7 Questions to Ask"
   - **Condition-specific:** "Hormones and Weight Gain: Is It Your Thyroid?"
   - **FAQ-style:** "Does Microneedling Hurt? What to Really Expect"
4. Funnel readers to the service page via inline internal links in the intro and conclusion. The blog post earns top-funnel impressions; the service page captures the conversion.

**The IV Therapy for Fatigue post (#193) is the right pattern:** targets "fatigue iv therapy" (specific use-case) without the location in the title, links to pillar 137 from intro and conclusion. That post supports the service page instead of competing with it.
