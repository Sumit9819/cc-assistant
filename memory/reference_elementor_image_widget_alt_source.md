---
name: elementor-image-widget-alt-source
description: "Elementor image alt has TWO render paths: rendered tag with class wp-image-N/attachment-<size> = wp_get_attachment_image = ATTACHMENT alt wins; bare tag = widget image.alt wins. Read the rendered tag to know which; when queueing blind, set BOTH."
metadata: 
  node_type: memory
  type: reference
  originSessionId: ab36cec8-65c1-4a26-ba9e-b90b0467a7fe
  modified: 2026-08-03T15:33:19.852Z
---

**CORRECTED 2026-08-03 (mammothmachinery footer logo).** The 2026-05-25 erofwhiterock
diagnosis below is real but is only ONE of two render paths. On mammoth, footer logo
widget alt was set (approved + applied) and the rendered tag STILL had alt="" — because
that widget renders via `wp_get_attachment_image()` (tag carries `class="attachment-large
size-large wp-image-77"`), which reads the ATTACHMENT's `_wp_attachment_image_alt`, not
the widget setting. Decision rule: **look at the rendered `<img>` tag first.**
- `class="wp-image-<id> attachment-<size> size-<size>"` → attachment alt wins → fix via
  `draft_update_postmeta(attachment_id, _wp_attachment_image_alt, ...)`
- bare Elementor-built tag (no wp-image class) → widget `image.alt` wins → fix per-widget
Which path fires depends on the widget's image_size setting and Elementor version. When
you can't check the rendered tag, set BOTH layers (the advice further down was right).

When fixing image alt text on Elementor sites:

**Setting `_wp_attachment_image_alt` postmeta on the attachment does NOT change what's rendered for existing Elementor image widgets.** The widget bakes the alt into its `_elementor_data` settings (`image.alt` field) at the moment the image is inserted via the Elementor editor. Subsequent attachment-level updates only affect:
- WP Media Library admin UI display
- New widgets inserted AFTER the update
- Google Image Search indexing at the attachment URL level

To fix rendered alt on an existing Elementor `image.default` widget, the fix path is:

```
draft_update_elementor_widget(
  post_id=<post_id>,
  widget_id=<8-char-hex>,
  settings={"image": {
    "url": "<full original image URL, no size suffix>",
    "id": <attachment_id>,
    "size": "",
    "alt": "<new alt text>",
    "source": "library"
  }}
)
```

Pass the FULL image object — partial-path merges may not work reliably on nested objects.

## On Polylang multi-language sites

Each translation has its OWN `_elementor_data` postmeta. If a widget id appears on both EN post 228 AND its ES sibling post 4687 with the same hash, BOTH need separate `draft_update_elementor_widget` calls. Updating only the EN post does not propagate to ES.

## Both layers are valuable

For complete alt coverage, set BOTH:
1. **Attachment-level** (`_wp_attachment_image_alt` postmeta) — for Image Search SEO at the attachment URL + accessibility metadata in Media Library
2. **Widget-level** (`image.alt` in `_elementor_data`) — for the alt that actually renders on the public page

## audit_post_images server-side cache lag

The `audit_post_images` MCP tool has a server-side fetch cache that lags behind actual changes by some interval (~minutes to hours, undetermined). After applying widget alt updates and purging SiteGround cache, the audit tool may STILL report old alt issues for a while. Don't trust audit_post_images alone — verify with cache-bypass curl if the audit disagrees with what you just changed.

## Why this matters

Diagnosed on erofwhiterock 2026-05-25. Spent 2+ hours queueing attachment-level alts (15 updates #857-#871) then re-running audit, getting confused when audit kept showing empty alts. Curl-bypass of the rendered HTML proved the widget setting is what renders. Then queued 30 widget-level updates (#872-#901). After cache purge + curl, every page rendered the correct alt. But `audit_post_images` STILL reported the old empty-alt state because its server-side cache hadn't refreshed.

Lesson: when a sanity-check tool disagrees with your live-rendered HTML, trust the HTML. Cache-bypass curl is the ground truth. Tool caches are a layer you can't always see.

## How to apply

1. For any image alt fix on an Elementor site: do BOTH attachment-level AND widget-level updates.
2. After applying + cache purge: verify via cache-bypass curl (`curl -H "Cache-Control: no-cache" "$URL?_cb=$RANDOM"`) and parse the rendered HTML for the alt — don't rely on audit_post_images alone for verification.
3. On Polylang sites: queue widget-level updates for both EN post + ES sibling, both must be edited independently.
