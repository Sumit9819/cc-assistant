<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $_POST['cc_onboarding_nonce'] )
	&& wp_verify_nonce( $_POST['cc_onboarding_nonce'], 'cc_onboarding_finish' ) ) {
	update_option( 'cc_assistant_onboarding_complete', true );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Setup complete. Welcome aboard.', 'cc-assistant' ) . '</p></div>';
}

$heartbeat    = CC_Assistant_Site_Identity::get_last_heartbeat();
$is_connected = $heartbeat && ( time() - $heartbeat['timestamp'] < 86400 );
$identity     = CC_Assistant_Site_Identity::whoami();
$plugin_path  = wp_normalize_path( CC_ASSISTANT_DIR );
$mcp_path     = $plugin_path . '.mcp.json.template';
$project_root = wp_normalize_path( ABSPATH );

// Build the .mcp.json snippet via the shared helper. On Windows + Local-by-Flywheel
// it auto-detects the bundled CLI PHP (with curl/openssl extension flags) so
// the snippet works copy-paste. Elsewhere it falls back to "php" on PATH.
$snippet      = CC_Assistant_Site_Identity::mcp_config_snippet( 'YOUR_USERNAME' );
$is_local     = CC_Assistant_Site_Identity::is_local_install();
$detected_php = ! empty( $snippet['detected'] ) ? $snippet['detected']['php'] : null;
$mcp_json     = wp_json_encode( $snippet['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

$steps = array(
	array(
		'title' => __( 'Plugin activated', 'cc-assistant' ),
		'done'  => true,
		'detail' => __( 'CC Assistant is installed and running. Site fingerprint generated.', 'cc-assistant' ),
	),
	array(
		'title' => __( 'Application password created', 'cc-assistant' ),
		'done'  => $is_connected,
		'detail' => $is_connected
			? __( 'A connection has been seen, so this is set up.', 'cc-assistant' )
			: __( 'Create an Application Password named "Claude Code" in your profile, then keep it ready for the next step.', 'cc-assistant' ),
		'action' => array(
			'label' => __( 'Open profile', 'cc-assistant' ),
			'url'   => admin_url( 'profile.php#application-passwords-section' ),
		),
	),
	array(
		'title' => __( 'Add MCP config on your computer', 'cc-assistant' ),
		'done'  => $is_connected,
		'detail' => $is_connected
			? __( 'Connected, so .mcp.json is in place.', 'cc-assistant' )
			: ( $is_local
				? sprintf(
					/* translators: %s: local site path */
					__( 'Save the JSON shown below as %s. Then open that folder in Claude Code. Replace YOUR_USERNAME with your WP login and YOUR_APP_PASSWORD with the password you just generated.', 'cc-assistant' ),
					'<code>' . esc_html( $project_root . '.mcp.json' ) . '</code>'
				)
				: __( 'Make a folder on YOUR computer (anywhere you like, e.g. C:\\Users\\you\\projects\\my-site\\) and save the JSON shown below as .mcp.json inside it. Then open that folder in Claude Code. The file lives on your computer, NOT on this live server. Replace YOUR_USERNAME and YOUR_APP_PASSWORD with the values from above.', 'cc-assistant' )
			),
	),
	array(
		'title' => __( 'Connection verified', 'cc-assistant' ),
		'done'  => $is_connected,
		'detail' => $is_connected
			? sprintf(
				/* translators: %s: fingerprint */
				__( 'Last contact verified. Site fingerprint matches: %s.', 'cc-assistant' ),
				'<code>' . esc_html( $identity['fingerprint'] ) . '</code>'
			)
			: __( 'Open Claude Code in this site folder and ask: "Use the whoami tool to tell me about the connected site."', 'cc-assistant' ),
	),
);

$done_count = count( array_filter( $steps, function ( $s ) {
	return ! empty( $s['done'] );
} ) );
$total      = count( $steps );
$percent    = (int) round( ( $done_count / $total ) * 100 );
?>
<div class="wrap cc-assistant cc-onboarding">
	<h1><?php esc_html_e( 'Get started with CC Assistant', 'cc-assistant' ); ?></h1>
	<p class="cc-tagline"><?php esc_html_e( 'Four short steps. The plugin checks each one for you.', 'cc-assistant' ); ?></p>

	<div class="cc-progress" data-onboarding-progress>
		<div class="cc-progress-bar">
			<div class="cc-progress-fill" style="width: <?php echo (int) $percent; ?>%"></div>
		</div>
		<div class="cc-progress-text">
			<?php
			printf(
				/* translators: 1: completed steps, 2: total steps, 3: percent */
				esc_html__( '%1$d of %2$d steps complete (%3$d%%)', 'cc-assistant' ),
				(int) $done_count,
				(int) $total,
				(int) $percent
			);
			?>
		</div>
	</div>

	<?php if ( ! $is_connected ) : ?>
		<div class="cc-card cc-card-wide cc-mcp-config-card">
			<div class="cc-mcp-header">
				<div>
					<h2><?php esc_html_e( 'Your MCP config', 'cc-assistant' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Pre-filled with your site URL. Save this as .mcp.json in the location shown above, then fill in the two credentials.', 'cc-assistant' ); ?></p>
				</div>
				<button type="button" class="button cc-copy-btn" data-copy-target="cc-mcp-json">
					<span class="dashicons dashicons-clipboard"></span>
					<span class="cc-copy-label"><?php esc_html_e( 'Copy JSON', 'cc-assistant' ); ?></span>
				</button>
			</div>
			<pre class="cc-code-block" id="cc-mcp-json"><?php echo esc_html( $mcp_json ); ?></pre>
			<div class="cc-mcp-paths">
				<?php if ( $is_local ) : ?>
					<div class="cc-mcp-path-row">
						<span class="cc-mcp-path-label"><?php esc_html_e( 'Save as', 'cc-assistant' ); ?></span>
						<code><?php echo esc_html( $project_root . '.mcp.json' ); ?></code>
						<button type="button" class="button-link cc-copy-btn" data-copy-text="<?php echo esc_attr( $project_root . '.mcp.json' ); ?>" title="<?php esc_attr_e( 'Copy path', 'cc-assistant' ); ?>"><span class="dashicons dashicons-clipboard"></span></button>
					</div>
				<?php else : ?>
					<div class="cc-mcp-path-row">
						<span class="cc-mcp-path-label"><?php esc_html_e( 'Save where', 'cc-assistant' ); ?></span>
						<span class="description"><?php esc_html_e( 'In any folder on YOUR computer — e.g. C:\\Users\\you\\projects\\my-site\\.mcp.json. Not on this live server.', 'cc-assistant' ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $detected_php ) : ?>
				<div class="cc-mcp-path-row">
					<span class="cc-mcp-path-label"><?php esc_html_e( 'PHP auto-detected', 'cc-assistant' ); ?></span>
					<code><?php echo esc_html( $detected_php ); ?></code>
					<span class="description cc-mcp-hint"><?php esc_html_e( 'Already in your config above with curl/openssl extension flags. Works copy-paste.', 'cc-assistant' ); ?></span>
				</div>
				<?php endif; ?>
			</div>
			<div class="cc-mcp-status" data-connection-status>
				<span class="cc-mcp-status-dot"></span>
				<span class="cc-mcp-status-text"><?php esc_html_e( 'Waiting for Claude Code to connect…', 'cc-assistant' ); ?></span>
				<button type="button" class="button button-small cc-mcp-reload"><?php esc_html_e( 'Reload', 'cc-assistant' ); ?></button>
			</div>
			<p class="description cc-mcp-test-hint">
				<?php esc_html_e( 'Once your config is in place, open Claude Code in this folder and ask: "Use the whoami tool to confirm the connection." Then click Reload.', 'cc-assistant' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<ol class="cc-steps-list">
		<?php foreach ( $steps as $i => $step ) : ?>
			<li class="cc-step <?php echo $step['done'] ? 'cc-step-done' : 'cc-step-todo'; ?>">
				<div class="cc-step-marker">
					<?php if ( $step['done'] ) : ?>
						<span class="dashicons dashicons-yes-alt"></span>
					<?php else : ?>
						<span class="cc-step-num"><?php echo (int) ( $i + 1 ); ?></span>
					<?php endif; ?>
				</div>
				<div class="cc-step-body">
					<h3><?php echo esc_html( $step['title'] ); ?></h3>
					<p><?php echo wp_kses_post( $step['detail'] ); ?></p>
					<?php if ( ! $step['done'] && ! empty( $step['action'] ) ) : ?>
						<a href="<?php echo esc_url( $step['action']['url'] ); ?>" class="button button-secondary">
							<?php echo esc_html( $step['action']['label'] ); ?>
						</a>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php if ( $is_connected ) : ?>
		<div class="cc-card cc-card-wide cc-tutorial-card">
			<h2>
				<?php esc_html_e( 'Try your first rewrite', 'cc-assistant' ); ?>
				<span class="cc-card-meta"><?php esc_html_e( 'A 4-step walkthrough so you see the workflow once before going live', 'cc-assistant' ); ?></span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Pick a post that already ranks (page 1, low CTR is ideal). Run the four prompts in order. The plugin enforces every guardrail behind the scenes — body lint, meta lint, success_metrics tracking, drift detection, the full set.', 'cc-assistant' ); ?>
			</p>

			<ol class="cc-tutorial-steps">
				<li class="cc-tutorial-step" data-step="1">
					<div class="cc-tutorial-marker"><span>1</span></div>
					<div class="cc-tutorial-body">
						<h3><?php esc_html_e( 'Find a candidate post', 'cc-assistant' ); ?></h3>
						<p><?php esc_html_e( 'In Claude Code, paste:', 'cc-assistant' ); ?></p>
						<div class="cc-tutorial-prompt-wrap">
							<pre class="cc-code-block cc-tutorial-prompt" id="cc-tutorial-prompt-1">Use cc-assistant tools. Run gsc_opportunities to find a striking-distance page (position 5–15, ≥100 impressions). Pick the one with the worst CTR vs expected — that is the best lever for a rewrite.</pre>
							<button type="button" class="button button-small cc-copy-btn" data-copy-target="cc-tutorial-prompt-1">
								<span class="dashicons dashicons-clipboard"></span>
								<span class="cc-copy-label"><?php esc_html_e( 'Copy', 'cc-assistant' ); ?></span>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'Claude returns a list. Note the post_id of the one you want to rewrite.', 'cc-assistant' ); ?></p>
					</div>
				</li>

				<li class="cc-tutorial-step" data-step="2">
					<div class="cc-tutorial-marker"><span>2</span></div>
					<div class="cc-tutorial-body">
						<h3><?php esc_html_e( 'Pull the full pre-flight brief', 'cc-assistant' ); ?></h3>
						<p><?php esc_html_e( 'One call, all the context. Replace POST_ID with the id from step 1:', 'cc-assistant' ); ?></p>
						<div class="cc-tutorial-prompt-wrap">
							<pre class="cc-code-block cc-tutorial-prompt" id="cc-tutorial-prompt-2">Run prepare_rewrite_brief(post_id=POST_ID). Read the dossier, structure, brief, competitor_brief, cannibalization, accessibility, style guide, ctr_diagnostics, and the 11-point checklist. Then propose an OUTLINE — H2 by H2, with merge / cut / keep markers — for me to approve before you write any HTML.</pre>
							<button type="button" class="button button-small cc-copy-btn" data-copy-target="cc-tutorial-prompt-2">
								<span class="dashicons dashicons-clipboard"></span>
								<span class="cc-copy-label"><?php esc_html_e( 'Copy', 'cc-assistant' ); ?></span>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'You will see a structural plan, not HTML. Approve or modify the outline before moving on.', 'cc-assistant' ); ?></p>
					</div>
				</li>

				<li class="cc-tutorial-step" data-step="3">
					<div class="cc-tutorial-marker"><span>3</span></div>
					<div class="cc-tutorial-body">
						<h3><?php esc_html_e( 'Submit the rewrite + meta in one batch', 'cc-assistant' ); ?></h3>
						<p><?php esc_html_e( 'Once the outline is approved, ask for the body + the meta title + meta description as three queued changes, all with success_metrics so future you can score what worked:', 'cc-assistant' ); ?></p>
						<div class="cc-tutorial-prompt-wrap">
							<pre class="cc-code-block cc-tutorial-prompt" id="cc-tutorial-prompt-3">Outline approved. Queue three pendings: (a) draft_update_post_content with the rewritten body, (b) draft_update_seo_meta logical_key=title with a flipped, hook-led title under 60 chars, (c) draft_update_seo_meta logical_key=description, 120–160 chars, contrastive lead. Set the same success_metrics on all three: target_query, target_position, target_ctr=0.04, eval_window_days=60, hypothesis line.</pre>
							<button type="button" class="button button-small cc-copy-btn" data-copy-target="cc-tutorial-prompt-3">
								<span class="dashicons dashicons-clipboard"></span>
								<span class="cc-copy-label"><?php esc_html_e( 'Copy', 'cc-assistant' ); ?></span>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'Each pending lands in the inbox with lint already run. Check the structure-diff card and Preview button before approving.', 'cc-assistant' ); ?></p>
					</div>
				</li>

				<li class="cc-tutorial-step" data-step="4">
					<div class="cc-tutorial-marker"><span>4</span></div>
					<div class="cc-tutorial-body">
						<h3><?php esc_html_e( 'Self-audit, then approve', 'cc-assistant' ); ?></h3>
						<p><?php esc_html_e( 'Have Claude verify its own work before you commit:', 'cc-assistant' ); ?></p>
						<div class="cc-tutorial-prompt-wrap">
							<pre class="cc-code-block cc-tutorial-prompt" id="cc-tutorial-prompt-4">For each pending_id you just created, run verify_change. Quote the verdict line. If any pending shows hard_violations or sibling overlaps, tell me before I approve.</pre>
							<button type="button" class="button button-small cc-copy-btn" data-copy-target="cc-tutorial-prompt-4">
								<span class="dashicons dashicons-clipboard"></span>
								<span class="cc-copy-label"><?php esc_html_e( 'Copy', 'cc-assistant' ); ?></span>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'Then open the inbox and approve. In ~7 days the dashboard "What\'s working" card will start scoring the change against the targets you set.', 'cc-assistant' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-pending' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Open Pending Changes inbox', 'cc-assistant' ); ?>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</a>
					</div>
				</li>
			</ol>
		</div>

		<div class="cc-card cc-card-wide cc-finish-card">
			<h2><?php esc_html_e( 'You are all set', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Connection verified. You can dismiss this guide and head to the dashboard.', 'cc-assistant' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'cc_onboarding_finish', 'cc_onboarding_nonce' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Mark setup complete', 'cc-assistant' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Go to dashboard', 'cc-assistant' ); ?></a>
			</form>
		</div>
	<?php else : ?>
		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Need a hand?', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'The Connection tab in Settings has the full instructions plus the exact MCP config block to paste in.', 'cc-assistant' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=connection' ) ); ?>" class="button"><?php esc_html_e( 'Open Connection settings', 'cc-assistant' ); ?></a>
		</div>
	<?php endif; ?>
</div>

<script>
(function () {
	// ---- Copy buttons (works for both inline text via data-copy-text and target element via data-copy-target) ----
	function flashCopied(btn, originalLabel) {
		var label = btn.querySelector('.cc-copy-label');
		if (label) {
			label.textContent = '<?php echo esc_js( __( 'Copied', 'cc-assistant' ) ); ?>';
			setTimeout(function () { label.textContent = originalLabel; }, 1600);
		} else {
			btn.classList.add('cc-copy-flash');
			setTimeout(function () { btn.classList.remove('cc-copy-flash'); }, 800);
		}
	}
	function doCopy(text, btn, originalLabel) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				flashCopied(btn, originalLabel);
			}).catch(function () {
				fallbackCopy(text, btn, originalLabel);
			});
		} else {
			fallbackCopy(text, btn, originalLabel);
		}
	}
	function fallbackCopy(text, btn, originalLabel) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); flashCopied(btn, originalLabel); } catch (e) {}
		document.body.removeChild(ta);
	}
	document.querySelectorAll('.cc-copy-btn').forEach(function (btn) {
		var labelEl     = btn.querySelector('.cc-copy-label');
		var origLabel   = labelEl ? labelEl.textContent : '';
		btn.addEventListener('click', function () {
			var text = btn.dataset.copyText;
			if (!text) {
				var t = document.getElementById(btn.dataset.copyTarget);
				if (t) text = t.textContent;
			}
			if (text) doCopy(text, btn, origLabel);
		});
	});

	// ---- Manual reload (no live polling: an authed poll would record its own
	// heartbeat and false-trigger "connected"). User clicks Reload after verifying. ----
	var reloadBtn = document.querySelector('.cc-mcp-reload');
	if (reloadBtn) {
		reloadBtn.addEventListener('click', function () { window.location.reload(); });
	}
})();
</script>
