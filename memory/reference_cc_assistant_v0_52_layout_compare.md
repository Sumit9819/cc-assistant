---
name: reference_cc_assistant_v0_52_layout_compare
description: v0.52 layout_spec + layout_compare tools — provable build-matches-reference diff
metadata: 
  node_type: memory
  type: reference
  originSessionId: bdbe9d51-2098-4917-93a9-eb1883a6d1fc
  modified: 2026-07-27T05:07:49.944Z
---

cc-assistant **v0.52.0** adds two READ-only MCP tools so a "make X match Y" build is provable, not eyeballed (built after a long session where a WooCommerce product page kept not matching the machine pages because there was no way to diff full widget settings):

- **`layout_spec(id)`** — full normalized layout spec: EVERY node (container+widget) with path, structural position, type, child_count, and ALL settings. Volatile element/repeater ids stripped so clones diff clean; `__globals__` kit-token refs kept verbatim. Works on pages AND Theme Builder templates.
- **`layout_compare(id, reference_id)`** — deep diff of target vs reference. Walks both trees in lockstep by structural position (ids ignored), returns every delta: structural (`missing_in_target` / `extra_in_target` / `type_mismatch`) and `setting` (path + setting key + target vs reference value). `match:true` means byte-identical (minus ids). Both sides may be a page OR a template (e.g. compare a Single Product template to a machine page).

WORKFLOW RULE for any match-a-reference build: `layout_spec(reference)` → build → `layout_compare(build, reference)` until match=true → render_probe. Turns "I think it matches" into an empty diff.

Implementation: methods `build_layout_spec` / `compare_specs` in [[reference_elementor_ground_truth]]'s sibling `includes/class-elementor-map.php`; REST routes `/posts/{id}/layout-spec` + `/layout-compare`; MCP schemas + dispatch in `bin/mcp-server.php`. Reads `_elementor_data`; for a Woo product the layout lives in the Single Product template (compare the TEMPLATE id, not the product — product has has_elementor=false). Also in v0.52: **`entity_lookup(q)`** — finds a name/slug across ALL post types at once (product, page, post, elementor_library templates, any public CPT); returns id/title/slug/post_type/status/url/has_elementor/template_type/exact_slug_match. Run FIRST before building a new page so a duplicate that exists as a Woo product never gets missed (the 2000MT trap). REST `/entity-lookup?q=`, handler handle_entity_lookup in class-rest-api.php.

Zip built at plugins/cc-assistant-0.52.0.zip (3 tools: entity_lookup + layout_spec + layout_compare). Needs deploy to remote + MCP reconnect to activate (see [[reference_plugin_dev_vs_remote_deploy]]). Still-open idea not built: a rendered-screenshot / computed-style verifier so visual match is checked against pixels too.
