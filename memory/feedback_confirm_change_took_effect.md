---
name: feedback-confirm-change-took-effect
description: "When a measurement does not move after a real change, suspect a stale cache before re-theorising the problem"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 805d74f3-c178-47fa-8463-c054b1293383
  modified: 2026-09-02T04:40:13.917Z
---

If a metric is **byte-identical** after a change that should have moved it, the change probably never ran. Check caching and staleness FIRST, before forming a second theory about the underlying problem.

**Why:** this has now bitten repeatedly, always the same shape - a cache keyed on a PATH or a NAME rather than on CONTENT, so re-rendering in place kept the filename and the stamp still matched. Instances: a re-rendered card kept its stale artwork in a segment; `scenes.render` fingerprinted the template's NAME, so a full template redesign never rendered; `compose` built a key as `"scene:" + path` and then stat'd that PREFIXED string, which is never a real file, so size and mtime silently never joined the stamp. In the last case a complete redesign of a motion template produced an identical measurement and I nearly re-theorised the design instead of checking the cache.

**How to apply:** compare file mtimes along the whole chain (source -> intermediate -> final output) before anything else; an output older than its input is proof. Then fix the key to hash CONTENT, not identity. Related: [[reference-cc-assistant-lint-audit-quirks]], [[feedback-probe-discipline-positive-controls]], [[project-faceless-video-studio]].
