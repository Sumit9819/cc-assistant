---
name: reference_css_mask_fails_on_file_url
description: CSS mask-image silently fails under file:// (opaque origin) and hides the whole element; inline it as a data URI
metadata: 
  node_type: memory
  type: reference
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-03T07:29:11.040Z
---

In headless Chromium rendering a local page (Playwright, `file://` URL), a
CSS `mask-image` / `-webkit-mask-image` referenced by relative path **fails
to load and takes its element with it** — the element renders fully masked
out, invisible, with no console error.

Cause: Chromium treats every `file://` URL as an *opaque origin*, and a CSS
mask requires a CORS-clean image. Same-directory does not help; there is no
same-origin under `file://`.

**Background images are exempt**, so a texture used as `background-image`
loads fine while the same file used as a mask does not — which makes the
failure look like a layout or font bug rather than a resource bug.

Fix: inline the mask as a `data:image/png;base64,...` URI. The renderer
should base64 the texture and pass it in the spec rather than referencing a
path. Diagnose with `page.on("requestfailed", ...)` — it prints
`net::ERR_FAILED` for the mask URL while everything else loads.

Applies to any local headless render, not just [[project_faceless_video_studio]].
