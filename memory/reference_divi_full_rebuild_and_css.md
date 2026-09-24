---
name: reference_divi_full_rebuild_and_css
description: How to do a ground-up Divi page redesign + add hover/:hover CSS via cc-assistant (draft_update_post_content + Code module)
metadata: 
  node_type: memory
  type: reference
  originSessionId: e4e4eed3-3590-41bf-aad2-18859ca12214
  modified: 2026-07-27T06:00:15.020Z
---

When the Divi module toolchain ([[reference_cc_assistant_divi_toolchain]]) can't fix a layout because it can't **restructure columns/rows** (e.g. a locked 4+1 logo grid, fixed-width capped text), do a full-body rebuild:

**Full rebuild:** `draft_update_post_content(post_id, content=<full Divi shortcode>, force_no_outline=true, override_lint=true)`. REST path is `POST /draft/post-content`. The `divi_hint` on get_post says "don't regenerate shortcode" — that's generic caution; a full redesign with operator authority is a valid exception. Reuse the page's OWN attr patterns (fetch via get_post first as a revert backup). Keep the `<!-- wp:divi/placeholder -->` wrapper. Most reliable pattern: native `[et_pb_section]/[et_pb_row]/[et_pb_column]` wrappers + `[et_pb_text]` modules whose inner HTML carries inline styles (flex/grid cards, clamp() for responsive, inline `font-size:NNpx !important` to beat the theme's `.et_pb_text h2{20px!important}`). Rule: **no `[` or `]` inside the HTML content** (breaks the shortcode parser).

**:hover / any real CSS:** inline styles can't do `:hover`. Put a `<style>` block inside an `[et_pb_code]` module. `apply_post_content` (class-apply.php ~L736) saves via `wp_update_post` with **NO wp_kses_post on post_content** (kses only runs on new-draft creation), so `<style>`/`<script>` survive. Give elements classes and target them: e.g. `.sp-link:hover{text-decoration:underline!important;color:<same>!important}` (underline, no colour change), `.sp-btn:hover{...invert...}`.

**Lint (draft_update_post_content):** hard = em dashes / AI-tells / banned phrases (refuse unless override). Soft/expected on a rebuild: `shortcode_preservation` (you're replacing structure), `bulk_add_ratio`/`redundancy` (near-identical re-queue), and a `sentence_length` **false positive** when a pill/chip row has no sentence punctuation (the splitter globs the labels). Verify real long sentences with a strip-tags word count.

**Verify:** edits are draft-only, so you can only see the render AFTER approval. Screenshot (headless Edge) for layout; fetch live HTML with a browser UA (SiteGround WAF) and grep for the `:hover` rules + class counts to confirm CSS landed. See [[feedback_verify_page_styling_before_after]]. Applied via sids-ponds hardscaping page 1125 (2026-07).
