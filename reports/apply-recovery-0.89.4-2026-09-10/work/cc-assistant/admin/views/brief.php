<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';

$keyword     = isset( $_POST['cc_brief_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_brief_keyword'] ) ) : '';
$intent_hint = isset( $_POST['cc_brief_intent'] )  ? sanitize_text_field( wp_unslash( $_POST['cc_brief_intent'] ) )  : '';
$brief       = null;
if ( $keyword && check_admin_referer( 'cc_brief_generate', 'cc_brief_nonce' ) ) {
	$brief = CC_Assistant_SEO_Tools::brief_for_keyword( array(
		'keyword'     => $keyword,
		'intent_hint' => $intent_hint,
	) );
	if ( is_wp_error( $brief ) ) {
		$brief = array( 'error' => $brief->get_error_message() );
	}
}
?>
<div class="wrap cc-assistant cc-brief-page">
	<h1><?php esc_html_e( 'Brief generator', 'cc-assistant' ); ?></h1>
	<nav class="nav-tab-wrapper" style="margin-bottom:12px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-clusters' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Clusters', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-calendar' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Calendar', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-brief' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Brief generator', 'cc-assistant' ); ?></a>
	</nav>
	<p class="description"><?php esc_html_e( 'Pre-flight pack for a new post around a target keyword. Pulls existing GSC presence, cannibalization risk, the closest cluster, internal link plan, and intent hints. Copy the prompt at the bottom into Claude Code to start drafting.', 'cc-assistant' ); ?></p>

	<form method="post" class="cc-brief-form">
		<?php wp_nonce_field( 'cc_brief_generate', 'cc_brief_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="cc_brief_keyword"><?php esc_html_e( 'Target keyword', 'cc-assistant' ); ?></label></th>
				<td>
					<input type="text" name="cc_brief_keyword" id="cc_brief_keyword" class="regular-text" required value="<?php echo esc_attr( $keyword ); ?>" placeholder="e.g. signs of dehydration in adults">
				</td>
			</tr>
			<tr>
				<th><label for="cc_brief_intent"><?php esc_html_e( 'Intent hint (optional)', 'cc-assistant' ); ?></label></th>
				<td>
					<select name="cc_brief_intent" id="cc_brief_intent">
						<option value=""><?php esc_html_e( 'Auto-detect', 'cc-assistant' ); ?></option>
						<option value="informational" <?php selected( $intent_hint, 'informational' ); ?>><?php esc_html_e( 'Informational', 'cc-assistant' ); ?></option>
						<option value="commercial" <?php selected( $intent_hint, 'commercial' ); ?>><?php esc_html_e( 'Commercial', 'cc-assistant' ); ?></option>
						<option value="transactional" <?php selected( $intent_hint, 'transactional' ); ?>><?php esc_html_e( 'Transactional', 'cc-assistant' ); ?></option>
						<option value="navigational" <?php selected( $intent_hint, 'navigational' ); ?>><?php esc_html_e( 'Navigational', 'cc-assistant' ); ?></option>
						<option value="local" <?php selected( $intent_hint, 'local' ); ?>><?php esc_html_e( 'Local', 'cc-assistant' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate brief', 'cc-assistant' ); ?></button>
		</p>
	</form>

	<?php if ( $brief && ! empty( $brief['error'] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $brief['error'] ); ?></p></div>
	<?php elseif ( $brief ) : ?>
		<hr />
		<h2><?php /* translators: %s: keyword */ printf( esc_html__( 'Brief for "%s"', 'cc-assistant' ), esc_html( $brief['keyword'] ) ); ?></h2>

		<div class="cc-brief-grid">
			<div class="cc-brief-cell">
				<h3><?php esc_html_e( 'Detected intent', 'cc-assistant' ); ?></h3>
				<strong><?php echo esc_html( ucfirst( $brief['intent'] ) ); ?></strong>
				<?php $hints = $brief['format_hints']; ?>
				<p><?php
					/* translators: 1: min words 2: max words 3: schema */
					printf(
						esc_html__( 'Suggested length %1$d–%2$d words. Schema: %3$s.', 'cc-assistant' ),
						(int) $hints['min_words'],
						(int) $hints['max_words'],
						esc_html( $hints['schema'] )
					);
				?></p>
				<ul>
					<?php foreach ( (array) $hints['structure'] as $h ) : ?>
						<li><?php echo esc_html( $h ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="cc-brief-cell">
				<h3><?php esc_html_e( 'Existing rankings', 'cc-assistant' ); ?></h3>
				<?php if ( empty( $brief['existing_pages'] ) ) : ?>
					<p class="description"><?php esc_html_e( 'No existing pages rank for this query — clean slate.', 'cc-assistant' ); ?></p>
				<?php else : ?>
					<?php if ( ! empty( $brief['cannibalization_risk'] ) ) : ?>
						<p class="cc-brief-warn"><?php esc_html_e( 'Cannibalization risk: 2+ of your pages already compete for this query. Consider fixing existing pages before writing new.', 'cc-assistant' ); ?></p>
					<?php endif; ?>
					<ul>
						<?php foreach ( $brief['existing_pages'] as $p ) : ?>
							<li>
								<?php if ( $p['post_id'] ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $p['post_id'], 'raw' ) ); ?>"><?php echo esc_html( $p['title'] ?: $p['page'] ); ?></a>
								<?php else : ?>
									<a href="<?php echo esc_url( $p['page'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $p['page'], PHP_URL_PATH ) ?: $p['page'] ); ?></a>
								<?php endif; ?>
								&mdash; <?php
								/* translators: 1: position 2: impressions */
								printf( esc_html__( 'pos %1$s, %2$s impr', 'cc-assistant' ), esc_html( number_format_i18n( $p['avg_position'], 1 ) ), esc_html( number_format_i18n( $p['impressions'] ) ) );
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $brief['best_cluster'] ) ) : ?>
				<div class="cc-brief-cell">
					<h3><?php esc_html_e( 'Cluster fit', 'cc-assistant' ); ?></h3>
					<strong><?php echo esc_html( $brief['best_cluster']['name'] ); ?></strong>
					<p class="description"><?php
						/* translators: %d: member count */
						printf( esc_html__( '%d existing members. Use the link plan below.', 'cc-assistant' ), (int) $brief['best_cluster']['member_count'] );
					?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $brief['cluster_link_plan'] ) ) : ?>
				<div class="cc-brief-cell">
					<h3><?php esc_html_e( 'Internal link plan', 'cc-assistant' ); ?></h3>
					<ul>
						<?php foreach ( $brief['cluster_link_plan'] as $link ) : ?>
							<li>
								<strong><?php echo esc_html( ucfirst( $link['role'] ) ); ?>:</strong>
								<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['title'] ); ?></a>
								<small class="description"><?php echo esc_html( $link['note'] ); ?></small>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $brief['competitor_domains'] ) ) : ?>
				<div class="cc-brief-cell">
					<h3><?php esc_html_e( 'Competitor domains for WebFetch', 'cc-assistant' ); ?></h3>
					<ul>
						<?php foreach ( $brief['competitor_domains'] as $d ) : ?>
							<li><code><?php echo esc_html( $d ); ?></code></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<h3><?php esc_html_e( 'Claude prompt', 'cc-assistant' ); ?></h3>
		<textarea id="cc-brief-prompt" rows="14" class="large-text code" readonly><?php echo esc_textarea( $brief['claude_prompt'] ); ?></textarea>
		<p>
			<button type="button" class="button button-primary" id="cc-brief-copy"><?php esc_html_e( 'Copy prompt', 'cc-assistant' ); ?></button>
			<span class="description"><?php esc_html_e( 'Paste into Claude Code to start the draft. The plugin will queue any drafts as Pending Changes.', 'cc-assistant' ); ?></span>
		</p>

		<script>
		document.getElementById('cc-brief-copy').addEventListener('click', function () {
			var ta = document.getElementById('cc-brief-prompt');
			ta.select();
			try { document.execCommand('copy'); this.textContent = '<?php echo esc_js( __( 'Copied!', 'cc-assistant' ) ); ?>'; } catch (e) {}
			window.getSelection().removeAllRanges();
		});
		</script>
	<?php endif; ?>
</div>
