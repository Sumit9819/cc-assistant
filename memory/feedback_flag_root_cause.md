---
name: When fixing a broken config, also flag the source instructions
description: User is the plugin author — if a broken .mcp.json entry came from the plugin's onboarding/docs, surface the doc/UX bug back to them instead of silently patching the file
type: feedback
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
When the user's broken artifact (a `.mcp.json` entry, a misconfigured option, a malformed shortcode, etc.) clearly came from following the plugin's own onboarding/docs/snippets, **flag the source defect** in addition to fixing the artifact. Don't just silently patch.

**Why:** The user is building cc-assistant for non-technical operators. If they (a sophisticated user with full context) hit a footgun in their own onboarding flow, every new user will hit it too. Confirmed: in the jayard35 setup, the user pasted a snippet that didn't work, manually deleted `"command"`, and never restored a working value. The Connection tab's generated snippet uses bare `"command": "php"` (which fails on Local + Windows because PHP isn't on PATH) and the Local-by-Flywheel card with the right args is shown separately — non-technical users won't know to splice them.

**How to apply:** When a broken-looking config matches the shape of something the plugin generates, find the generator (admin views, README, template files), confirm it's the source, and surface the gap to the user — they may want to fix the generator before more users hit it. Do this *before* finishing the patch task, not as an afterthought.
