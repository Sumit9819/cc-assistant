---
name: cc-assistant-schema-generator-bugs
description: "propose_schema/page-jsonld generator writes invalid dates, invalid JSON, and duplicate breadcrumbs - found on eroflufkin 2026-07-22"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 1e79afee-4083-48c4-95aa-a68819103e12
  modified: 2026-07-27T06:07:16.415Z
---

managed_schema audit on eroflufkin (20 posts with managed schema) exposed three defects in the cc-assistant schema generator ([[wp-plugin-cc-assistant]] roadmap items):

1. **Invalid dates**: page-jsonld on 6910 had `"datePublished": "-001-11-30T00:00:00+00:00"` (year -001) - date sourcing falls back to a zero/epoch-negative value when the post date is missing/unparsed. Also emitted `?page_id=N` URLs instead of permalinks and an empty description.
2. **Invalid JSON writes**: 6 posts store ~10KB of syntactically invalid JSON in `_cc_assistant_schema_jsonld` (valid:false, types:[]) - the writer saved unparseable output; the renderer silently skips it, so the meta is dormant garbage (eroflufkin posts 6663, 6660, 5655, 5565, 5904, 6625).
3. **Duplicate BreadcrumbList**: valid page-jsonld nodes include a BreadcrumbList even when the SEO plugin (Rank Math) already emits one - the generator should detect the active SEO plugin's breadcrumb and omit its own.
4. Also: 5 location pages carry empty 0-byte `_cc_emergency_service_schema` meta (no-op rows worth garbage-collecting).

**Status: FIXED in v0.51.4 (built 2026-07-22, zip at plugintesting/app/public/cc-assistant-0.51.4.zip).** Generator-side fixes (permalink not ?page_id, date guards vs -001, empty-description omission, WebPage-vs-Article, breadcrumb dedup vs Yoast/Rank Math, Organization author) were ALREADY in the dev copy (class-schema-generator.php) but never deployed to eroflufkin - the live defects came from the older deployed version. v0.51.4 adds what was missing: (1) queue-time JSON validation in handle_draft_postmeta (422 invalid_schema_json), (2) apply-time validation in apply_post_meta + empty-value = delete_post_meta for the two schema keys, (3) DB 0.9.0 migration that garbage-collects invalid/empty schema meta on upgrade (counts in option cc_assistant_schema_gc_result).

**How to apply:** deploy 0.51.4 zip to each remote site (editing local code does not reach remotes); on eroflufkin the migration auto-cleans the 6 invalid blobs + empty rows. When auditing a client site, run managed_schema (no args) - schema_scan alone misses dormant invalid meta because it only sees rendered output.

---

## Defect 5: FAQPage builder is over-inclusive and flattens tables (found 2026-07-27, irvingwellnessclinic post 10216)

`propose_schema` treats **every question-shaped H2 as an FAQ entry**, not just the ones inside the actual FAQ section. On a post with 12 question H2s plus a real 5-question FAQ block, it emitted **12 `Question` nodes**, including body sections that are not FAQs at all.

Worse, when a section contains a `<table>`, the generator flattens the table into `acceptedAnswer.text` with **no cell delimiters**, producing unreadable run-together strings:

```
"ClaimEvidence statusSource\n\n\nNAD+ is essential to cellular energy and DNA repairEstablished
biochemistryClinical Evidence for Targeting NAD Therapeutically, NIH PMC\nNAD+ levels decline
with ageWell documentedNIH PMC review..."
```

```
"NAD+ IV dripNAD+ injection\n\n\nDose250mg or 500mg25mg to 200mg\nPrice$325 or $475$50..."
```

Note the built-in lint **passes all 3 checks** here (`faq_questions_in_body`, `faq_answers_in_body`, duplicate advisory), because the mangled text technically does derive from the body. So the lint does not protect you. **Always read the generated `jsonld` before queueing.**

There is no parameter to select which questions to include (`propose_schema` accepts only id / dry_run / include_breadcrumb / reasoning / success_metrics), so it cannot be constrained from the MCP side.

**Plugin fix needed:** (a) only harvest questions from the designated FAQ section, or expose an `faq_items` / `include_headings` parameter; (b) skip or properly serialise `<table>` content in answers rather than concatenating cells; (c) cap answer length.

**Relevant context:** FAQ rich results were retired 2026-05-07 **except for government and health sites** ([[reference_google_2026_seo_doctrine]]), so on these clinic sites FAQPage markup still earns a real SERP feature and is worth getting right rather than skipping. Until this is fixed, hand-build the FAQPage `@graph` from the genuine FAQ Q&As only.
