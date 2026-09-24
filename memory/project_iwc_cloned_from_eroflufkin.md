---
name: project_iwc_cloned_from_eroflufkin
description: Irving Wellness Clinic was cloned from ER of Lufkin — Lufkin residue lives in OG meta + schema sameAs (NOT JSON-LD authorship)
metadata: 
  node_type: memory
  type: project
  originSessionId: ce230c56-9945-46a4-ad73-0056289dd069
---

irvingwellnessclinic.com was cloned from **eroflufkin.com**. Confirmed 2026-06-24 via live HTML on every page (home/about/lori/blog/privacy).

**Where the residue is (precise):**
- Open Graph meta: `article:author = "ER of Lufkin - Emergency Room"`, `article:publisher = https://www.facebook.com/ERofLufkin`
- Schema `sameAs`: the MedicalClinic/Organization JSON-LD node lists `"sameAs":["https://www.facebook.com/ERofLufkin"]`

**Where it is NOT:** the JSON-LD Article/BlogPosting author + publisher are CORRECT (point to the Irving entity / Lori). An external (non-schema-reading) audit wrongly claimed the schema authorship named the wrong entity — it does not. Fix target is Rank Math → Titles & Meta → Social (FB profile URL + OG author/publisher) and the hand-built schema's sameAs, not the Knowledge Graph default author.

Same clone-residue family as [[project_erofwhiterock_cloned_from_erofirving]] and [[feedback_purge_source_vertical_on_clone]]. Provider context: [[project_iwc_provider_is_aprn]].
