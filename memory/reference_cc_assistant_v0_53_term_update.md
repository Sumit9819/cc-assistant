---
name: reference_cc_assistant_v0_53_term_update
description: "cc-assistant v0.53.0 taxonomy-term toolchain: list_product_categories + draft_update_term for thin WooCommerce category pages"
metadata: 
  node_type: memory
  type: reference
  originSessionId: e4e4eed3-3590-41bf-aad2-18859ca12214
  modified: 2026-07-28T04:40:30.623Z
---

v0.53.0 adds the category-page ("money page") optimization loop, built because sids-ponds product_cat archives had **no H1 and no term descriptions** (bare grids; only Rank Math metas existed).

- `list_product_categories(taxonomy?)` → `GET /terms/list`: term_id, name, slug, parent, count, permalink, description (+`description_words` — 0 = thin archive), rank_math term seo_title/seo_description. Taxonomies allowed: `product_cat` (default), `category`.
- `draft_update_term(term_id, taxonomy?, description?, seo_title?, seo_description?)` → `POST /draft/term`: queues change_type `term_update` (post_id=0). Only keys present are touched; current values snapshot into the pending row = revert payload. Empty `seo_*` string deletes the term meta. Description lints via `lint_html_block` (RAW checks array — compute hard names per the v0.51.3 dual-shape pattern), seo fields via `lint_seo_meta`. Supersede subkey `term:{id}`. Apply = `wp_update_term` + `update_term_meta` (`rank_math_title`/`rank_math_description`) + `clean_term_cache`.
- Wiring a new change type requires SIX places: rest routes/handler, pending-changes supersede list + subkey, apply.php `$no_post_snapshot` (BOTH occurrences — apply and revert paths) + both switch cases + worker, class-diff-render case, **admin/views/pending.php has its OWN `cc_render_human_diff` switch + `cc_change_type_label` map** (miss it and the inbox card renders empty), mcp-server tool def + case.
- **kses bug found in review + fixed**: WP's `pre_term_description` filter is MINIMAL kses (strips h2/h3/p/ul/li!) whenever the applying user lacks `unfiltered_html` (multisite/editor/REST contexts) — single-site admin masks it. apply_term_update now swaps `wp_filter_kses` → `wp_filter_post_kses` for the one write, then restores. Still verify render after first live apply (canary: queue on a small category first, revert available).
- Archives missing H1 is a THEME setting (Flatsome shop-title), not plugin-fixable — flag, don't touch theme.

Related: [[reference_cc_assistant_divi_toolchain]], [[feedback_zip_plugin_yourself]], [[reference_plugin_dev_vs_remote_deploy]].
