---
name: reference-accessibility-audit-reads-stale-source
description: PLUGIN DEFECT (2026-08-25) - accessibility_audit reports empty_link / empty_alt / alt_too_long from stale post_content on Elementor pages; DOM (page_facts) disproves them. Never act on its findings without a DOM check. Fix = route it through page_facts/render_probe.
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-25T07:43:36.713Z
---

Measured on three sites' homepages (eroflufkin 228, erofwhiterock 228, irvingwellnessclinic 8): `accessibility_audit` flagged 6 "empty_link" image-card anchors per ER site and 6 "empty_alt" images on IWC. `page_facts` (rendered DOM) showed every one of those anchors carries an accessible name via `img[alt]` and every IWC image has descriptive alt. The tool is reading the stale `post_content` render snapshot ([[reference_elementor_post_content_is_stale]]) and does not credit `img[alt]` inside links. Its `heading_skip` finding on White Rock WAS real (h1 -> h3 confirmed in document order).

**How to apply:** treat `accessibility_audit` link/alt output as a lead only; confirm with `page_facts` before reporting. Same class of failure as [[feedback_dom_is_ground_truth_not_parsers]]. Plugin fix candidate: rebuild `accessibility_audit` on the render-probe DOM (accessible-name resolution, document-order headings, form label association, iframe title, focus-outline CSS check). Also: `page_facts.headings.outline` is grouped by level, NOT document order - heading-skip claims need the raw HTML.
