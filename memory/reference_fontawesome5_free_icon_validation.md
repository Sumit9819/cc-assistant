---
name: fontawesome5-free-icon-validation
description: "Elementor bundles Font Awesome 5 Free — invalid icon names (fa-stomach, fa-spine) render as blank; validate every selected_icon against FA5 Free before building"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 813fe1b6-ece5-4c84-92c1-740614b56b3f
---

Elementor renders `selected_icon` values as inline SVG via Font Awesome 5 **Free**. An icon name that does not exist in FA5 Free (e.g. `fas fa-stomach`, `fas fa-spine` — both FA6/Pro-only or nonexistent) silently renders as an empty tag: the card shows **no icon at all**, with no build-time or lint error.

**Why:** valid icons are converted to `<svg>`; invalid ones remain as unrendered `<i class="fas fa-...">`. This also gives a cheap live check: grep the rendered page for `fas fa-` — only BROKEN icons appear as literal classes (hub 541 caught `fa-stomach`, `fa-spine` this way, fixed #808-809 with fa-stethoscope / fa-procedures).

**How to apply:** when authoring icon-box/steps cards, only use icon names verified to exist in FA5 Free (safe medical set: fa-heartbeat, fa-brain, fa-lungs, fa-syringe, fa-x-ray, fa-bone, fa-vial, fa-microscope, fa-stethoscope, fa-procedures, fa-notes-medical, fa-band-aid, fa-ambulance, fa-user-md, fa-baby, fa-allergies, fa-biohazard, fa-tint, fa-temperature-high, fa-head-side-virus/-cough, fa-lungs-virus). After any card build, curl the live page and grep `fas fa-` — any hit is a broken icon.

Related: [[elementor-ground-truth]]
