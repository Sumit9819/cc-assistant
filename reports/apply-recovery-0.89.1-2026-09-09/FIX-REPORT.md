# CC Assistant 0.89.1: partial approval diagnosis and fix

Live checks on 9 September 2026 confirmed:

- #1724 applied: post 5525 now displays ER of White Rock as author. It remains a draft.
- #1725 applied: page 971 contains the corrected laboratory-results explanation.
- #1726, #1727, #1728 remain pending: the first widget edit changed the whole-post evidence hash, so the remaining same-page proposals failed.
- #1723 remains pending: its saved environment fingerprint differs from the live configuration. The old receipt stores a hash, so the exact changed component cannot be determined from that error alone.
- The live backend reports 0.87.0. Updating the local bridge does not install the WordPress backend. These approval errors are distinct from SiteGround CAPTCHA transport challenges.

## Implemented

The administrator bulk-approval path now creates a request-local record of the selected proposals. Initially current updates to separate Elementor widgets can proceed against the exact expected state left by earlier successful selected updates. The next whole-post fingerprint is predicted before writing and verified afterward. Nothing is trusted merely because it was written by this plugin.

Whole-post and environment checks remain active. Same-widget conflicts, initially stale evidence, changed proposals, unselected rows, external edits, unexpected hook side effects and failed recovery remain blocked. The exception is limited to independent widget updates in one bulk approval. Mixed content/metadata/publication or structural changes still require fresh proposals as necessary. The generated hero-image preload cache is excluded from the content fingerprint alongside other generated caches.

Each applied row retains its recovery snapshot. Partial approval messaging explains that successful edits remain saved. The shared Claude contract explains how to inspect history and rebuild only blocked proposals after deployment. This release does not publish, revert or force-apply live content.

## Validation

- 192 PHP files passed syntax checks.
- 47 PHP regression files passed, including 17 batch-specific assertions exercising real apply/evidence/persistence classes with WordPress and Elementor fixtures.
- Real stdio MCP initialization/tools listing passed: version 0.89.1, 176 unique tools.
- ZIP CRC and every shipped file match the canonical source; installed local bridge files match the ZIP.
- Live reads confirmed the partial result. The new approval path has not been executed on production; server installation and fresh proposal review remain necessary.

## Recovery after installation

1. Install cc-assistant-0.89.1.zip on erofwhiterock.com and confirm whoami reports backend 0.89.1.
2. Read the current pending history; preserve applied #1724 and #1725.
3. Run a fresh verified_page_audit for page 971, read its complete Elementor tree and current widget controls. Rebuild only these three remaining corrections, retaining the existing layout:
   - Widget 46afe405: title_text_a and title_text_b = Understanding Your Results.
   - Widget 77684669: description_text_b = Your physician considers laboratory results alongside your symptoms, medical history and examination. Ask what the findings mean and whether you need follow-up.
   - Widget 2a6ed6d9: description_text_b = Laboratory tests can provide information that helps your care team evaluate symptoms. Some findings may require additional tests or follow-up.
4. Read draft 5525 and refresh its content-workflow verification (workflow-87ad22b181a57ee39ecea94e57aec221). Rebuild publication proposal #1723 only when current checks allow it. Preserve ER of White Rock as author and practical SEO-focused presentation.
5. Review fresh proposals in WordPress. Rechecking stale originals alone will not repair them. Do not queue replacements before the plugin update, because deployment can invalidate their environment evidence again.

The lab wording was previously supported by https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/ . Recheck the actual current target before writing; this recovery note is not new page evidence.

Release: D:\cc-assistant\wp-content\plugins\cc-assistant-0.89.1.zip
SHA256: f92d66a0e71a088ced62310032d54b6cbee34260cd30b560b8ac386c5c4dfb28
Backups: source-before.zip and active-bridge-before.zip in this report directory.
