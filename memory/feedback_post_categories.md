---
name: New blog posts must always include category assignment
description: When creating a new post via cc-assistant, always pass category_ids; otherwise the post lands uncategorized and disrupts taxonomy alignment
type: feedback
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
When creating a blog post via `mcp__cc-assistant-*__draft_create_post` (or rewriting one via `draft_update_post_content`), **always include the appropriate category**. Posts that land uncategorized lose:
- The category archive page as an inbound-link surface
- WordPress's taxonomy signal to Google
- Editorial alignment with the pillar/cluster architecture

**Why:** This came up when post 4986 (Pillar 1 brand-defense post) was created without a category and the user noticed in the WP admin. The cc-assistant plugin pre-v0.10.23 didn't even support the `category_ids` parameter on draft_create_post — a real gap.

**How to apply:**
- For erofwhiterock.com, pass `category_ids` matching the pillar:
  - Pillar 1 (ER vs Urgent Care decisions) → "Emergency Decision Guides" or current "Emergency Care" (ID 113 EN)
  - Pillar 2 (Pediatric) → "Pediatric Care" or current "Medical Guide" (ID 111 EN)
  - Pillar 3 (Cardiovascular) → "Heart and Stroke" (TBD)
  - Pillar 4 (Trauma) → "Trauma and Injury" (TBD)
  - Pillar 5 (Digestive) → "Stomach and Digestive" (TBD)
- For Polylang sites, the EN and ES categories are SEPARATE term IDs; assign EN posts to EN categories and ES posts to ES categories.
- If `list_topic_clusters` shows no cluster→category mapping, fall back to fetching `wp/v2/categories` via the REST API to get current IDs.
- The cc-assistant `draft_update_categories` endpoint (added in v0.10.23) lets you fix uncategorized posts after the fact.

Plugin support added v0.10.23: `draft_create_post` now accepts `category_ids` array; new `draft_update_categories` endpoint allows category re-assignment for existing posts. Older versions silently ignore category_ids — verify plugin version with `whoami` if unsure.
