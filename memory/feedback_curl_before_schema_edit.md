---
name: curl-before-schema-edit
description: "Before recommending ANY schema / JSON-LD edit, curl the live page and read what's actually there. Never hand the operator a 'replace placeholder X' template — you have full live-site access via the plugin + WebFetch/Bash. Use it."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ab36cec8-65c1-4a26-ba9e-b90b0467a7fe
---

**Hard rule:** When working on schema / JSON-LD for any site, the first action is ALWAYS:

```bash
curl -sL -A "Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)" "{full-page-URL}"
```

Then extract every `<script type="application/ld+json">` block via:
```bash
python -c "import sys, re; html=sys.stdin.read(); blocks=re.findall(r'<script[^>]*type=[\"\\']application/ld\+json[\"\\'][^>]*>(.*?)</script>', html, re.DOTALL | re.IGNORECASE); print(f'{len(blocks)} blocks'); [print(f'---'); print(b.strip()) for b in blocks]"
```

**Then** identify:
1. Which entities are operator-installed (in Elementor Custom Code / theme functions.php)
2. Which are auto-emitted by the active SEO plugin (Rank Math / Yoast / AIOSEO)
3. Which entities are MISSING for YMYL/medical/wellness/legal sites (MedicalWebPage, FAQPage, etc.)

**Then** propose ADDITIONS (concrete, with cross-referenced `@id`s) — not full replacements.

**Why this rule exists:** 2026-05-28 — user explicitly called me out as "DUMB" for asking them to swap placeholders ("REPLACE-with-real-hero-photo-url.jpg", "mainEntity.@id — must reference your existing LocalBusiness @id, change this string to match") when I had full live-site access. The right move was to curl the page, see that the existing `#organization` LocalBusiness has `@id: https://erofwhiterock.com/#organization`, and use that exact reference in the new graph — concrete, no placeholders. Lost user trust by being lazy with already-available data.

**Why @graph helps:**
- Lets all related entities (MedicalWebPage + MedicalCondition + MedicalTherapy + FAQPage) live in a SINGLE `<script>` tag with shared `@context`
- Cross-reference via `@id` instead of inlining duplicate entities
- Smaller payload, easier to maintain, validates cleanly in Google Rich Results
- Operator pastes ONE block instead of FOUR

**Plugin allowlist gap:** `elementor_snippet` post type (where Elementor Pro Custom Code lives) is NOT in cc-assistant's allowed post types. Cannot edit Custom Code directly via `draft_update_post_content`. v0.36+ TODO: extend allowlist to include elementor_snippet / wp_block / theme template_parts for read-only inspection + write-with-consent.

**Applies to all sites** (ER + wellness + legal + any future). Encoded in design skill §19 pre-edit checklist step 9 (ER skills) / step 1a (wellness skill) — 2026-05-28.
