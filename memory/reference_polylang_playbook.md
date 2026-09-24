---
name: reference_polylang_playbook
description: "Rollup of all Polylang/multilingual rules: WPML compat gate, MCP wrapper gap, pair direction, EN-first drafting, pair verification after publish"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-05T10:53:16.134Z
---

Everything Polylang, in one place:

- Polylang defines ICL_LANGUAGE_CODE too — gate on pll_current_language BEFORE any WPML check. [[reference_polylang_wpml_compat]]
- Link-translations REST route works; the MCP wrapper was the missing piece. [[reference_polylang_mcp_gap]]
- Verify a post's language before pairing: {en,es} keys map to the language taxonomy — never assume direction. [[feedback_polylang_pair_direction]]
- Draft EN first, ES after — the reviewer can't triage a mixed-language inbox. [[feedback_english_first_then_spanish]]
- Pairing via create is unreliable: re-apply the pair AFTER the ES post is live and verify. [[feedback_polylang_pair_verify_after_publish]]
