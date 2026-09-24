---
name: project-er-policy-pages-parity
description: "2026-08-26 policy-page parity across the three ER sites (Irving/Lufkin/White Rock) - what was created, how legal pages are built, corporate phone in footers"
metadata: 
  node_type: memory
  type: project
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-26T09:28:56.174Z
---

**Canonical legal set per ER site:** Privacy Policy, Terms, HIPAA Notice, Medical Disclaimer, Billing Disclosures (or Surprise Billing Rights on Lufkin), Cookie Policy, Accessibility, Editorial Policy, Letter of Protection. Inventory with `list_posts post_type=page per_page=100` and compare to the footer legal row before claiming a gap.

**Created 2026-08-26 (pending approval at time of writing):** Irving Privacy 4433, Terms 4434, Medical Disclaimer 4435, Billing 4436, Cookie 4437; Lufkin Medical Disclaimer 7110; White Rock Cookie EN 5510 + ES 5511 (Polylang-paired). Each is a draft (publish_draft pending) + an `import_elementor_data` pending + a Rank Math description pending. Footer link updates: Irving 2224 legal-links text-editor replaces the two heading links; Lufkin 6018 c27c3a4a; WR 4486/4770 e60a368.

**How legal pages are built here:** no plain-prose section in `build_page_from_spec`, so generate the tree yourself (scratchpad `policy_gen.py` pattern): navy hero (secondary token, eyebrow p + H1 + intro + white call/email buttons) > white 850px column (effective date, H2 28/24 + text-editor 18/1.6 pairs, gap 16 + heading margin-top 24) > #F4F4F4 CTA band (H2 36 + text + primary solid/outline buttons). Tokens only, no font family, no images. `draft_create_post` (classic HTML fallback) then `import_elementor_data(post_id, raw_data)`; sister sites reuse the same tree with `replacements` (name/domain/phone/tel/address) instead of re-pasting. Never put links in hero text (site-wide text-editor link colour = navy on navy).

**Corporate office block:** 5800 Campus Cir Dr, Suite 200A, Irving, TX 75063, phone (469) 501-1520 (operator-supplied 2026-08-26). Footer widgets: Irving 2224 ac7f4d3, WR 4486 e4392ae (address_consistency lint fires on it - override with explanation), Lufkin 6018 b2d36954. WR ES footer 4770 has no corporate block. Tel links inside dark footers need `style="color: inherit"`.

Related: [[feedback-form-email-standard]] (info@ only), [[feedback-no-red-hero-band]], [[reference-cc-assistant-v076-wcag-kit]].
