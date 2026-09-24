---
name: project_cf_ai_assistant
description: "Sumit's VS Code extension (a Claude Code-alike on Cloudflare Workers AI) at app/public/cf-ai-assistant; TS, no bundler, tsc + smoke test only"
metadata: 
  node_type: memory
  type: project
  originSessionId: 452688c6-0b59-44bb-a594-0508aa3427e6
  modified: 2026-09-01T00:00:00.000Z
---

`c:\Users\sumit\Local Sites\plugintesting\app\public\cf-ai-assistant` — a VS Code extension that runs an autonomous coding agent on Cloudflare Workers AI. Unrelated to the WordPress work in the parent directory; it just lives there.

**Build reality:** plain `tsc` to `dist/`, no bundler and no test framework. `npm run check` type-checks, `npm test` compiles then runs `scripts/smoke-test.cjs`, which mocks the `vscode` module via `Module._load` and asserts against `dist/`. `scripts/deploy.cjs` (`npm run deploy`) now packages AND installs the .vsix via npx vsce; the user still has to run **Developer: Reload Window**.

**Architecture:** `agentEngine` runs a bounded ReAct loop over `ToolRegistry` (read_file, write_file, edit_file, list_directory, search_codebase, execute_terminal_command, scaffold_project_structure, get_workspace_tree). `accountPoolManager` holds tokens in SecretStorage and fails over across accounts. Permission modes are plan / edit / auto.

**Reviewed and repaired 2026-08-31 (v1.3.0 to v1.4.0):** added `edit_file` (exact-snippet replacement — the 8192-token output cap made full-file rewrites impossible above ~600 lines), made the loop execute every returned tool call, replayed turns as real `tool_calls` + `tool_call_id`, moved function-calling detection to the Workers AI model catalogue, streamed agent turns, made round-robin account distribution opt-in (`accountPoolingMode`, defaults to `failover`) because quota-spreading is a billing-terms risk, removed package installs from the Auto-mode allowlist, and added Markdown rendering. Also fixed [[reference_template_literal_eats_backslashes]].

**The default model `@cf/qwen/qwen3-30b-a3b-fp8` is a REASONING model.** It spends output budget on a chain of thought before emitting anything into `content`, so a small `max_tokens` yields an empty response and a misleading "completed without returning text" error. This cost a debugging round when the connection test used `maxTokens: 16` (now 512). Confirmed 2026-08-31: same request 0.57s/empty at 16 tokens vs 1.77s/"OK" at 512. Any future budget-tightening here must leave reasoning headroom. Empty responses now self-diagnose by logging response field NAMES only (never values), preserving the no-content-logging promise.

Its security work is genuinely good — do not "simplify" `assertRealPathInsideWorkspace` (symlink escape guard) or the webview's textContent-only rendering under the strict CSP.

**Built through v1.7.0 (2026-09-01):** Karpathy core principles (`corePrinciples.ts`, compressed wire form), plan gate, checkpoints/revert, post-write diagnostics feedback, `verify_project`, ripgrep, PageRank project-structure map, semantic index + rerank, MCP client (raw JSON-RPC over stdio, NO SDK — `.vscodeignore` excludes node_modules so a runtime dep would be missing from the .vsix), assist-model triage + summarise-instead-of-truncate, AI Gateway routing, vision/screenshot-to-code, image generation, translation, collapsed-by-default reasoning panel.

**Payload shapes are SCHEMA-VERIFIED (2026-09-01), live-unverified.** `@cloudflare/workers-types` is a devDependency; its `AiModels` keys and per-model `*_Input`/`*_Output` interfaces ARE Cloudflare's generated schemas and are the authoritative offline source - use it, don't guess or trust the docs site (WebFetch can't render its raw-schema panels). Confirmed matching: bge-m3 `{text}` -> `{data: number[][]}`; bge-reranker-base `{query, contexts:[{text}]}` -> `{response:[{id,score}]}`; llama-3.2-11b-vision `{prompt, image: number[] | base64 string, max_tokens}` -> `{response}` (BOTH encodings valid); flux-1-schnell `{prompt, steps}` -> `{image}` base64; m2m100-1.2b `{text, source_lang, target_lang}` -> `{translated_text}`. Final live proof is `Cloudflare AI: Run Diagnostics` (exercises 4 of 5; image gen is deliberately excluded as it costs a generation).

**Caught by this: I had invented `@cf/meta/llama-3.1-8b-instruct-fast`** as the assistModel default, pattern-matched from the real `@cf/meta/llama-3.3-70b-instruct-fp8-fast`. It does not exist. Because AssistModel degrades silently, triage + summarisation would have been permanently dead with no visible symptom. Real id: `@cf/meta/llama-3.1-8b-instruct-fp8`. A smoke test now checks every `@cf/...` string in src/ + package.json against the workers-types catalogue, so this fails the build. LESSON: never write a Workers AI model id from memory - grep workers-types.

`scripts/smoke-test.cjs` has positive-control-verified tests for: gateway routing (stubbed fetch, asserts the real URL), context compression, the collapsible reasoning panel (real DOM mock), the probe PNG's validity, and settings parity (every contributed setting is read and vice versa - 46/46).

**Windows spawn trap:** `shell: true` breaks any command whose path contains a space (the default Node install path does) and is an injection surface. `mcpClient.ts` resolves the executable against PATH/PATHEXT and uses a shell ONLY for `.cmd`/`.bat`, with quoting. Do not "simplify" this back.

**LIVE diagnostics run 2026-09-01 (v1.7.1, Personal Account, free tier):** PASS chat (qwen3-30b, 99/2048 tokens), assist model (llama-3.1-8b-instruct-fp8 - the fix works), **embeddings bge-m3 returned a 1024-dim vector**, **reranker bge-reranker-base ordered correctly**. So embed() and rerank() are now LIVE-PROVEN, not just schema-proven. FAIL: vision (`@cf/meta/llama-3.2-11b-vision-instruct`) - real HTTP status unknown, see below. Translation was never sent (collateral). Image gen still untested.

**The vision failure tripped the single account into cooldown**, which then failed every later check. Retryable status per `createHttpError` = 401/403/429/>=500, so the vision error was one of those. If it recurs on v1.7.2 the log will now name the status; a 403/404-class answer likely means llama-3.2-11b-vision is not available on this account/plan - try `@cf/unum/uform-gen2-qwen-500m` or `@cf/llava-hf/llava-1.5-7b-hf` in `cloudflareAi.visionModel`.

**Bug this exposed (fixed v1.7.2):** a retry loop that keeps the LAST error lets a downstream symptom erase the cause. First encoding got the real HTTP error AND cooled the account; second encoding then failed instantly with "All Cloudflare accounts are disabled..." which is what the user saw. Keep the FIRST error, log each attempt's reason WITH status, and break out on AccountPoolExhaustedError. Same shape of bug to watch for anywhere else with a fallback loop over one shared account pool.
