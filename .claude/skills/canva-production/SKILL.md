---
name: canva-production
description: Producing images and video through the Canva MCP connectors. Invoke whenever a task involves creating, editing, or exporting a Canva design, or producing a featured image / social graphic / video for any client site. Carries the verified tool surface, the working generate-correct-export recipe, and the hard limits that are NOT discoverable from tool descriptions.
---

# Canva production via MCP

Everything below was **measured against the live connectors on 2026-08-04**, not read from docs.
Where something is inferred rather than measured, it says so.

## 0. TL;DR — the 8 things that will save you an hour

1. **Two accounts.** `mcp__claude_ai_Canva__*` = Focus Academy (tax education). `mcp__canva-alt__*` = **client work** (ER sites, IWC). Same endpoint `https://mcp.canva.com/mcp`, different OAuth tokens. Ask which one before acting.
2. **Use `canva-alt` for client work.** It exposes editing transactions; the claude.ai connector does not.
3. **`generate-design` accepts `design_type: "presentation"` on `canva-alt`** with no approval widget. The `request-outline-review` gate only exists on the claude.ai connector.
4. **Canva honours exact hex from the prompt.** Verified: `#DA1212`, `#041562`, `#11468F`, `#555555` all rendered exactly. Put the palette in the prompt.
5. **Canva ignores your content.** It follows aesthetics and invents its own copy. Expect to replace nearly every string.
6. **You cannot preview candidates.** Thumbnail URLs 403 outside a Canva session. Create the design, then export PNG to look at it.
7. **`commit-editing-transaction` or everything is lost.** No exceptions.
8. **Do not ship Canva-native MP4 for text-carrying video.** See §5. The headline is legible for ~0.5s of each 5s slide.

## 1. Account routing

| Connector | Account | Editing transactions | Use for |
|---|---|---|---|
| `mcp__claude_ai_Canva__*` | Focus Academy / focusyourfinance | No | tax-education decks |
| `mcp__canva-alt__*` | client work | **Yes** | ER sites, IWC, everything client-facing |

`canva-alt` is registered at user scope in `~/.claude.json`; its OAuth token lives in
`~/.claude/.credentials.json` under `mcpOAuth` keyed `canva-alt|<url-hash>`. Removing the server
**wipes the token**, so a rename forces re-auth.

Canva OAuth **binds silently to whatever account the browser is signed into** and never shows a
chooser. To connect a different account, switch the active Canva account in the browser first.

## 2. What the connector can and cannot reach

**No access to Canva's stock library.** There is no search over Canva photos, videos, or elements.
`get-assets` needs asset IDs you already hold. `search-designs` / `search-brand-templates` /
`search-folders` only see your own content.

**Indirect access:** `generate-design` pulls from Canva's stock library internally. You cannot steer
which asset, but the imagery it returns is real stock photography and it is good.

**Direct ingest:** `upload-asset-from-url` accepts any **already-public** HTTPS URL (image or video).
It refuses to be used to publish the user's private files. Pexels/Unsplash URLs qualify. A WordPress
media-library URL would qualify.

**Video CAN be placed:** `perform-editing-operations` supports `asset_type: "video"` on both
`insert_fill` and `update_fill`.

## 3. The working recipe (stills)

```
generate-design(design_type, query with palette + imagery direction)
  -> create-design-from-candidate(job_id, candidate_id)
  -> export-design(png, pages:[1])          # ONLY way to see what you made
  -> start-editing-transaction(design_id)   # returns full element tree + element_ids
  -> perform-editing-operations(...)        # bulk ops across all pages in ONE call
  -> commit-editing-transaction(...)        # MANDATORY
  -> get-export-formats(design_id)          # required before export
  -> export-design(...)
```

`perform-editing-operations` needs `transaction_id`, `page_index` (first page touched), and the
`pages` array returned by `start-editing-transaction`. Pass many operations at once; 14 in a single
call all succeeded.

### Prompt shape that worked

State the palette as explicit hex, name the banned colours, describe the photography, and
**forbid people** on medical sites. Canva will honour all of that. Do not bother specifying detailed
slide copy; it will be ignored.

## 4. What Canva injects that you MUST strip

Every generated design arrived carrying template boilerplate:

- `[Presenter Name]` and a role line like "Texas Emergency Room Specialist"
  — **on ER sites this is a HARD rule violation** (no provider bylines, consent blocked)
- `hello@reallygreatsite.com`, `@reallygreatsite` — Canva's placeholder brand
- Decorative squiggle-arrow doodles (`asset_id: MAFolTK8s_Y`), reported as `editable: false`
  in `fills` but **`delete_element` removes them successfully**

Replace the byline with brand-safe text rather than deleting it, or the layout leaves a hole.

## 5. Video: the hard limits (measured)

| Property | Measured |
|---|---|
| Page duration | **exactly 5.000s** (measured on two separate designs: 3 pages = 15.000s) |
| Audio | **none**. Zero audio streams. No TTS/music/timeline tools exist |
| Resolution | 1920x1080, h264, 30fps via `quality: "horizontal_1080p"` |
| `generate-design` video type | **does not exist** — presentation is the only MP4 path |
| Animation control | **none exposed** |

**The killer:** Canva auto-applies a typewriter entrance plus a scroll-away exit. Slide 1 timeline:

```
0.2s "H"   1.2s "Heat Strok"   2.2s "Heat Stroke vs Heat"
3.2s FULL TEXT   4.2s scrolling off   4.7s gone
```

The headline is fully legible for roughly **0.5 of the 5.0 seconds**. This is not fixable through
the MCP.

**Conclusion: Canva MP4 export is unusable for video that must carry a message.** Use Canva for
stills. Produce video elsewhere, or have the human finish it in the Canva editor where the timeline
and animation controls exist.

## 6. Other verified gotchas

- **Candidate thumbnails (`design.canva.ai/...`) are not fetchable.** Plain GET returns Canva's SPA
  shell; with a browser UA it returns **403**. Export URLs (`export-download.canva.com`) *are*
  fetchable without auth.
- **PNG export is faithful.** The still matched the design exactly, including full un-truncated text.
- **PNG at `width: 1920` came out 1.5 MB.** Site standard is WebP ≤200KB, so compress before use.
- **Font family cannot be changed.** `format_text` covers colour, size, weight, style, alignment,
  line height, lists, links — **not family**. If the generated design uses a serif and the brand
  requires Montserrat, the MCP cannot fix it. Only a hand-built Brand Template can.
- **No brand templates exist** in the client account (`search-brand-templates` -> `{"items":[]}`).
  Building them by hand in Canva is the only route to font-exact output.
- Brand kits over the API are gated: Pro adds design resizing, **Enterprise** adds brand kits,
  autofill, and brand templates. The client account returned one brand kit id (`kAGwVabY5sA`) with
  no name; ownership unverified, so do not apply it blindly.

## 7. Verdict: when to use Canva

**Use it for** blog featured images, social stills, and anything where real stock photography beats
drawn shapes. The imagery is the reason to use Canva, and it is genuinely good.

**Do not use it for** brand-font-exact output (no family control), or for video carrying text
(§5). For a strict brand system, generating and then correcting still leaves the typeface wrong.

Related memory: `reference_canva_mcp_video_pipeline`, `reference_per_site_design_skills`.
Per-site brand tokens live in the `{site}-design` skills; read those before writing any prompt.
