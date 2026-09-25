---
name: reference_elementor_custom_attributes_comma
description: Elementor link custom_attributes are "key|value" pairs separated by COMMAS, so a comma inside an aria-label value truncates the label and emits a junk attribute like 0=""
metadata:
  type: reference
---

Elementor's link `custom_attributes` field is `key|value,key2|value2`. A comma inside a value
splits it: `aria-label|Get directions to ER of Irving, 8200 N MacArthur Blvd` renders as
`aria-label="Get directions to ER of Irving" 0=""` (verified on erofirving 2026-09-25). The old
Lufkin-residue label there had the same shape, and Lufkin's own header label still does.

**How to apply:** never put a comma in an aria-label (or any value) written through
`custom_attributes`. Use "at" or a dash-free rewording: "Get directions to ER of Irving at 8200 N
MacArthur Blvd". After any such edit, curl the page and grep the rendered `<a>` tag.
