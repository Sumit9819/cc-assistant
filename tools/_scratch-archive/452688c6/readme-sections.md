## Spending the cheap model instead of the expensive one

Workers AI prices an 8B model far below a 30B reasoning model, and embeddings roughly two orders of magnitude below either. Most of what makes this extension usable on a free tier comes from moving work down that ladder rather than from asking the frontier model to be brief.

**Request triage.** Before a run starts, a small model classifies the request as a question or as real work. A question is answered by the small model with a reduced iteration budget; anything else keeps the agent's own model. Every guard fails towards the expensive model: an unclear verdict, a prompt whose wording implies workspace changes, or a small model without tool support all leave the routing alone. The classification costs a few hundred tokens and can save an entire run.

**Summarising instead of truncating.** A long tool observation used to have its middle cut out, which is the worst possible loss: the line the agent was about to act on disappears, and it stays gone for every remaining iteration. Older observations are now compressed once by the small model, permanently, and the compressed form is reused. Errors, exceptions, and verification failures are never compressed — they are the highest-value tokens in the context.

Both are on by default and controlled by `cloudflareAi.assistModel`, `cloudflareAi.triageSimpleRequests`, and `cloudflareAi.summariseOldToolResults`.

## AI Gateway

Set `cloudflareAi.aiGatewayId` to the name of a Cloudflare AI Gateway and every inference request routes through it, gaining request logging, analytics, and rate limiting without any other change. The model catalogue still goes direct, because it is a control-plane call rather than inference.

With `cloudflareAi.aiGatewayCacheTtlSeconds` above zero, requests whose output is a pure function of their input — embeddings, vision, translation — ask the gateway to cache the response. A cache hit costs nothing, which makes re-indexing an unchanged repository free. Agent turns are deliberately never cached: replaying a cached decision would repeat a stale one.

## Images and translation

**Screenshot to Code** (`cloudflareAi.screenshotToCode`) takes a UI screenshot or mockup, has a vision model describe it in structural terms — layout, verbatim text, controls, colours, spacing — and hands that description to the agent as a build specification. The vision model cannot write the application and the text model cannot see the picture, so each does only the half it is good at.

The agent can do the same thing itself with `read_image` when a task refers to a mockup already in the repository.

`generate_image` and **Cloudflare AI: Generate Placeholder Image** produce decorative placeholder assets, so generated markup does not ship broken image references. Generated images are captured by the checkpoint system, so **Revert Agent Changes** removes them along with the code that referenced them.

**Cloudflare AI: Translate Selection** translates an editor selection between languages, for user-facing strings and i18n resource files.
