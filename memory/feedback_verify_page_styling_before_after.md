---
name: feedback_verify_page_styling_before_after
description: "Always screenshot-verify a page's rendered styling before AND after editing; match its existing design"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4e4eed3-3590-41bf-aad2-18859ca12214
  modified: 2026-07-27T03:29:35.419Z
---

Operator (sids-ponds) after the About-page edit looked "messed up": **always check a page's rendered styling/structure before and after editing, not just the content diff.**

**Why:** a semantically-correct edit can still wreck a page's visual design. On About (post 75) the body paragraphs were wrapped in `<h4>` — SEO-wrong, so [[reference_cc_assistant_divi_toolchain]] edits converted them to 18px `<p>`. But that `<h4>` sizing was part of the page's *visual* design; shrinking it left sparse gaps and dropped a section heading. The change was queued and approved without ever rendering the result.

**How to apply:** For any page/post edit — especially complex custom pages (About, service/landing pages, homepage) — screenshot the LIVE page first (headless Edge `--headless=new --screenshot=out.png --window-size=1300,4200 <url>`, then Read the png), match the page's own design in the edit, and screenshot again AFTER apply to confirm it looks right before calling it done. Blog posts (uniform template) are lower-risk; bespoke Divi pages are high-risk — treat them as screenshot-verified design work, not blind surgical content edits. Relates to [[feedback_verify_rendered_visuals_after_build]].
