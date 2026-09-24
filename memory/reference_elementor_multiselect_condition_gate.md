---
name: reference-elementor-multiselect-condition-gate
description: Elementor multi-select controls (submit_actions) store an ARRAY; comparing it to a scalar made every such gate read as off and refused correct Elementor Pro form email settings. Fixed 0.89.11
metadata:
  type: reference
---

**Symptom.** `draft_add_elementor_widget` for an Elementor Pro `form` refused with
422 `unverified_elementor_settings`, reporting every email key as inert:

> Setting "email_to" ... its gate is off - submit_actions is ["email","save-to-database"] (needs email)

The array plainly contains `email`. The same wrong verdict showed up as
`applies: false` on the LIVE, working Contact Us form in `widget_schema`.

**Cause.** `CC_Assistant_Widget_Schema::control_visible()` evaluated a control's
`condition` as if the stored value were always scalar:

```php
$contains = is_array( $cvalue ) ? in_array( $instance, $cvalue, true ) : ( $instance === $cvalue );
```

`submit_actions` is a MULTIPLE select, so `$instance` is an array. Elementor asks
whether the required value is IN that array; this asked whether the array IS the
value. Every multi-select gate therefore read as closed: `submit_actions`,
`motion_fx_devices`, `sticky_on`, `submissions_metadata`.

**Fix (0.89.11).** Branch on the stored value, mirroring Elementor's
`Conditions::check()`: array instance uses `in_array($cvalue, $instance)`, or
`array_intersect` when both sides are arrays; scalar path unchanged. Seven
assertions in `tests/schema-test.php`, verified by reverting the fix and watching
three of them fail.

**How to apply.** When the queue calls a setting inert, check whether the gating
control is a multi-select before believing it. A gate verdict is a claim about
Elementor's behaviour and deserves the same scepticism as any other probe: the
live Contact Us form was the positive control that showed the verdict was wrong,
because that form demonstrably sends email. Related:
[[feedback_probe_discipline_positive_controls]],
[[reference_elementor_settings_silently_inert]],
[[reference_evidence_gate_eael_view_counter]].
