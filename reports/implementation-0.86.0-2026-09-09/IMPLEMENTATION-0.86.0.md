# CC Assistant 0.86.0: Claude owns strategy setup

The operator no longer needs to configure Content Strategy, identify page IDs or enter an audience manually. Claude can discover existing knowledge, save a source-backed strategy and maintain it while preparing content for review.

## Implemented

- **Automatic first setup:** `plan_blog_content` initializes an inferred strategy from published source candidates when none is saved. No GSC gap is required. Existing saved strategies are preserved.
- **Discovery:** `discover_content_scope` reads eligible published content, site identity and existing site notes. Returns current source evidence, excerpts, inferred roles and pagination. Utility/private/protected pages are excluded from service candidates. Blog mentions remain editorial candidates, not proof that a service is offered.
- **Claude-managed metadata:** `manage_content_scope` saves selected sources, audience, exclusions and reader questions with explicit reasons. It works with the restricted CC Assistant operator role and can write only this strategy metadata; it does not grant arbitrary option writes or publication approval.
- **Current evidence:** every update requires the scope revision, current page evidence IDs and site-context hash. Changed sources, changed notes and concurrent corrections are rejected for re-reading. The plugin reports unavailable sources and stale interpretations.
- **Constraint preservation:** omitted fields retain their prior values; removing exclusions requires an explanation. Reader questions must refer to selected sources. Ten previous profiles are retained, with compact history summaries returned to Claude.
- **Review screen:** the Content Strategy form is replaced with sources, audience, exclusions, questions, freshness and change history. The operator can check the result and tell Claude a correction.
- **Complete-task handoff:** `content_workflow` prepares and saves a new-blog, refresh or site-review workflow with niche ideas, context, targets and ordered tool steps. Claude is instructed to execute those steps, prepare actual drafts/proposals and report their IDs and preview links. A prepared workflow is explicitly not an executed change.
- **Consistent entry instructions:** whoami, shared SEO rules, bridge descriptions and the bootstrap template now assign setup and execution to Claude. Removed conflicting fixed citation-quota and rigid page-intent instructions from whoami's page standard.

## Connection issue found

The live website reported plugin 0.85.0, while this conversation's running bridge reported 0.81.3. That old bridge could not expose the new tools. `operator_brain_pull(what="bridge", mode="replace")` successfully refreshed the deployed bridge files with no errors. The running MCP process still needs a restart.

The local active bridge will also be updated to the tested 0.86.0 release alongside the source. Install the matching website ZIP before restarting/using its new endpoints. No credentials or connection settings need re-entry.

## Validation

All **36 PHP regression files** passed, with syntax checks on **169 PHP files**. The real MCP initialize/tools-list smoke check returned **0.86.0 and 172 distinct tools**. Three tools were added in this release; eight now cover content strategy/research/decisions/workflows. The ZIP passed CRC, content-presence and byte-for-byte source checks.

New cases cover first setup without a form/GSC, restricted operator access, read-only discovery, public-source filtering, preservation of existing fields, idempotent saves, stale page evidence, concurrent site-note and profile changes, protected sources, database failure/locking, bounded history, actor-scoped workflows and the review-only admin screen.

These are local fixtures and transport discovery checks. New authenticated endpoints and live MySQL behavior have not been certified on erofwhiterock.com. No live content or site settings were changed during this implementation.

## What you need to do

1. Install **cc-assistant-0.86.0.zip** on the website, replacing the current plugin.
2. Restart the MCP connection or start a fresh Claude session so it loads the refreshed bridge. Confirm site and bridge versions are both 0.86.0.
3. Say: **Create a useful new blog within our actual services. Discover and maintain the strategy from the site and existing notes, research a distinct reader need, and prepare the complete draft for my review.**

There is no Content Strategy setup form to complete. If another machine has its own desktop bridge, Claude can refresh it with `operator_brain_pull` after the website update; its process must restart too.

## What remains missing for broader automation

This release automates setup and provides tool-backed execution instructions while Claude is connected. It does not create a continuously running AI service. Fully unattended operation still needs a configured Claude/API runner, schedule, resource budget, durable job/retry tracking, alerts and verified completion records. A saved workflow is a handoff, not a job executor or proof of completed work.

The broader research roadmap also retains coordinated multi-page consolidation, automatic contextual-link changes, scheduled claim monitoring, conversion-aware evaluation and external SERP/backlink connectors. Claude can already perform many research and drafting steps interactively using available tools; the plugin must not claim unavailable capabilities.

Scope inference is deliberately labeled. Existing notes and source excerpts are evidence for Claude to interpret, not guarantees that all business facts are correct. No software update can guarantee that a model never makes a mistaken inference. Current hashes, recorded reasons, explicit unknown states and the existing review workflow make those mistakes easier to catch.

See **CONTENT-STRATEGY.md** for the tool contract. **changed-files.json**, **test-results.json**, **mcp-smoke-results.json**, **release-validation.json** and **source-update.json** provide the manifest and verification evidence. The original reviewed 0.85.0 source and overwritten bridge files are backed up before replacement; runtime credentials and databases are excluded.
