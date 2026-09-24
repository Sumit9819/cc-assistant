---
name: elementor-popup-dynamic-tag
description: "The RIGHT way to wire popup buttons - __dynamic__ link with [elementor-tag name=\"popup\"] self-loads the popup; raw action URLs need display conditions and ship dead"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 501752a8-8c88-48d5-88ba-e672ff5142f9
---

Verified working format (copied from the operator's own hand-wired button, irvingwellnessclinic post 10145 widget 526ab15):

```json
"settings": {
  "link": {"url": "https://fallback-url/", "is_external": "", "nofollow": ""},
  "__dynamic__": {
    "link": "[elementor-tag id=\"4447f5f\" name=\"popup\" settings=\"%7B%22popup%22%3A%2210147%22%7D\"]"
  }
}
```

- `settings` attr = urlencoded `{"popup":"<POPUP_ID>"}`; tag `id` = unique 7-hex per instance.
- **Dynamic-tag buttons self-load the popup document** on any page — no display conditions needed. Raw `#elementor-action%3Aaction%3Dpopup%3Aopen...` URLs only work when the popup's display conditions cover the page; otherwise they silently no-op (the v0.50 guard warns on exactly this).
- The plain `link.url` stays as a fallback; the dynamic tag overrides it at render.
- Operator's stated procedure in the editor UI: button link -> dynamic tag icon -> Popup -> select the popup.
- Plugin gap: cc-assistant's `popup_id` feature (v0.50.0) emits the RAW action URL. It should emit the dynamic tag instead — roadmap item, see [[cc-assistant-page-builder-roadmap]].
