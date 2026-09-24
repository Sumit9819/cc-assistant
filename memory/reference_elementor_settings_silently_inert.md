---
name: reference_elementor_settings_silently_inert
description: Two Elementor container settings that are stored but do nothing unless a second setting enables them - overlay opacity and boxed_width
metadata: 
  node_type: memory
  type: reference
  originSessionId: c4f71213-6189-4708-920d-3ea9eed152af
  modified: 2026-09-06T06:18:48.519Z
---

Elementor stores a value, the editor shows it, and it has **zero effect** because a
second setting gates it. Both found on the mammothmachinery Find a Dealer hero,
2026-09-03. Check for these before concluding a value "is set, so it must be applying".

## 1. `background_overlay_color` alpha is MULTIPLIED by `background_overlay_opacity`

`background_overlay_opacity` defaults to **0.5**. If it was never set, an overlay of
`rgba(15,18,15,0.78)` renders at `0.78 x 0.5 = 0.39`.

Proof is in the generated CSS (`/wp-content/uploads/elementor/css/post-<id>.css`): the
`::before` rule emits only `background-color`, and `--overlay-opacity: 0.5` governs.

On the dealer hero that left ~RGB 161 over the bright part of the photo, so white body
text measured **2.5:1** while the H1 still looked fine because it is huge and bold. The
operator's report was "heading visible, paragraph impossible to read" - that asymmetry
is the signature.

Fix: set `background_overlay_opacity` to 1 and let the colour's own alpha govern.
0.78 over white computes to ~9.8:1 white text, photo still visible.

**Audit method:** effective = colour alpha x (opacity if set else 0.5); composite over
white; compute contrast. The other 9 heroes on that site all set opacity explicitly
(#141414 at 0.55, #1a1c1c at 0.82 ...) and passed, so this was one omission, not a pattern.

## 2. `boxed_width` is ignored unless `content_width: "boxed"`

A container with `content_width: "full"` and `boxed_width: 900` renders **full width**.
Someone set the house 900px column width and it never applied, so the block and the form
inside it stretched the whole section. Fix is `content_width: "boxed"`, which activates
the stored value and centres the block (Elementor wraps children in `.e-con-inner` with
`margin-inline:auto`). Text stays left-aligned inside it.

**Guarded in plugin v0.80.0 (2026-09-06).** Every Elementor write now validates on
EFFECTIVE settings (new > stored > default) with Elementor's own `condition` rule:
`inert_setting` fires for boxed_width under content_width=full, `defaults_in_effect`
names background_overlay_opacity=0.5 when unset, `global_token_overrides_literal`
catches a literal written over a bound Kit token. `widget_schema(type, post_id,
widget_id)` shows stored vs effective vs applies for one element. Read the warnings;
they are not noise. Pinned in tests/schema-test.php.

Related: [[feedback_verify_page_styling_before_after]], [[reference_elementor_ground_truth]],
[[feedback_dom_is_ground_truth_not_parsers]].

## A third: `placeholder` does nothing on a SELECT field (2026-09-14)

Elementor Pro form fields take `placeholder`, but only text-type inputs honour it.
Set it on a `select` and it stores cleanly, queues cleanly, applies cleanly, and
changes nothing: the select still opens on its first real option. On mammoth's
financing form that meant every untouched Country field would have been recorded
as "Afghanistan", the first entry alphabetically.

**The working form** is Elementor's documented `label|value` syntax as the first
line of `field_options`:

    Select country|
    Afghanistan
    Albania

That renders a visible first option whose submitted value is empty, which is what
a Forminator blank option does. Verified on a cache-busted load: 251 options, first
is "Select country" with value "", selected.

**Why it matters beyond this field:** a pending change applying successfully proves
the value was STORED, never that it DID anything. Only the rendered page proves
effect, and it has to be cache-busted or SiteGround will hand back the old HTML and
look like a second failure. Related:
[[feedback_confirm_change_took_effect]], [[feedback_no_guessing_epistemic_discipline]].
