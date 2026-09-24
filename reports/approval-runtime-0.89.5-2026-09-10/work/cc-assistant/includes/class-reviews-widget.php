<?php
/**
 * Elementor widget: Google Reviews (CC).
 *
 * Renders ONLY the cached reviews stored by CC_Assistant_Reviews — zero
 * external calls on the page render. This file is required only inside the
 * 'elementor/widgets/register' hook, so it never loads (and never references
 * \Elementor\*) outside an Elementor context.
 *
 * Deliberately emits NO Review / aggregateRating JSON-LD: self-serving review
 * schema on your own business is ineligible for rich results and risks a manual
 * action. The widget DISPLAYS reviews for trust/conversion only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class CC_Assistant_Reviews_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'cc-google-reviews';
	}

	public function get_title() {
		return __( 'Google Reviews (CC)', 'cc-assistant' );
	}

	public function get_icon() {
		return 'eicon-testimonial';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'google', 'review', 'testimonial', 'rating', 'cc' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content',
			array(
				'label' => __( 'Reviews', 'cc-assistant' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'heading',
			array(
				'label'   => __( 'Heading', 'cc-assistant' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'What our patients say', 'cc-assistant' ),
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'Max reviews', 'cc-assistant' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 20,
				'default' => 5,
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'cc-assistant' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '3',
				'options' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
				),
			)
		);

		$this->add_control(
			'show_badge',
			array(
				'label'        => __( 'Show overall rating badge', 'cc-assistant' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_write_button',
			array(
				'label'   => __( 'Show "Leave a review" button', 'cc-assistant' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'write_button_text',
			array(
				'label'     => __( 'Button text', 'cc-assistant' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Leave us a review', 'cc-assistant' ),
				'condition' => array( 'show_write_button' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style',
			array(
				'label' => __( 'Style', 'cc-assistant' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg',
			array(
				'label'     => __( 'Card background', 'cc-assistant' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array( '{{WRAPPER}} .ccgr-card' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text color', 'cc-assistant' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#2c2c2c',
				'selectors' => array( '{{WRAPPER}} .ccgr-card' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'star_color',
			array(
				'label'     => __( 'Star color', 'cc-assistant' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#fbbc04',
				'selectors' => array( '{{WRAPPER}} .ccgr-stars' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		if ( ! class_exists( 'CC_Assistant_Reviews' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-reviews.php';
		}

		$settings = $this->get_settings_for_display();
		$cache    = CC_Assistant_Reviews::get_cache();
		$reviews  = CC_Assistant_Reviews::get_reviews();
		$limit    = max( 1, (int) ( $settings['count'] ?? 5 ) );
		$cols      = in_array( (string) ( $settings['columns'] ?? '3' ), array( '1', '2', '3' ), true ) ? (int) $settings['columns'] : 3;
		$reviews   = array_slice( $reviews, 0, $limit );

		if ( empty( $reviews ) ) {
			// Editor-only hint; renders nothing for visitors so an unconfigured
			// widget never leaves an empty block on the live page.
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="ccgr-empty" style="padding:24px;border:1px dashed #bbb;text-align:center;color:#777">'
					. esc_html__( 'Google Reviews: no reviews cached yet. Go to CC Assistant → Reviews and click “Fetch reviews now”.', 'cc-assistant' )
					. '</div>';
			}
			return;
		}

		$wid = 'ccgr-' . $this->get_id();
		?>
		<div class="ccgr-wrap" id="<?php echo esc_attr( $wid ); ?>">
			<?php if ( ! empty( $settings['heading'] ) ) : ?>
				<h2 class="ccgr-heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>

			<?php if ( 'yes' === ( $settings['show_badge'] ?? '' ) && ! empty( $cache['rating'] ) ) : ?>
				<div class="ccgr-badge">
					<span class="ccgr-stars" aria-hidden="true"><?php echo esc_html( str_repeat( '★', (int) round( (float) $cache['rating'] ) ) ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (float) $cache['rating'], 1 ) ); ?></strong>
					<?php if ( ! empty( $cache['total'] ) ) : ?>
						<span class="ccgr-count"><?php echo esc_html( sprintf( /* translators: %s: number of reviews */ __( 'from %s Google reviews', 'cc-assistant' ), number_format_i18n( (int) $cache['total'] ) ) ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="ccgr-grid" style="display:grid;grid-template-columns:repeat(<?php echo (int) $cols; ?>,1fr);gap:20px">
				<?php foreach ( $reviews as $rev ) : ?>
					<div class="ccgr-card" style="border-radius:10px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
						<div class="ccgr-card-head" style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
							<?php if ( ! empty( $rev['photo'] ) ) : ?>
								<img class="ccgr-avatar" src="<?php echo esc_url( $rev['photo'] ); ?>" alt="<?php echo esc_attr( $rev['author'] ); ?>" width="40" height="40" loading="lazy" style="border-radius:50%;width:40px;height:40px;object-fit:cover" />
							<?php endif; ?>
							<div>
								<div class="ccgr-author" style="font-weight:600"><?php echo esc_html( $rev['author'] ); ?></div>
								<div class="ccgr-stars" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: star rating out of 5 */ __( '%d out of 5 stars', 'cc-assistant' ), (int) $rev['rating'] ) ); ?>"><?php echo esc_html( str_repeat( '★', max( 0, min( 5, (int) $rev['rating'] ) ) ) ); ?></div>
							</div>
						</div>
						<div class="ccgr-text" style="font-size:15px;line-height:1.6"><?php echo esc_html( $rev['text'] ); ?></div>
						<?php if ( ! empty( $rev['when'] ) ) : ?>
							<div class="ccgr-when" style="margin-top:8px;font-size:13px;opacity:.6"><?php echo esc_html( $rev['when'] ); ?></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			if ( 'yes' === ( $settings['show_write_button'] ?? '' ) ) :
				$write_url = CC_Assistant_Reviews::write_review_url();
				if ( $write_url ) :
					?>
					<div class="ccgr-cta" style="text-align:center;margin-top:20px">
						<a class="ccgr-btn" href="<?php echo esc_url( $write_url ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $settings['write_button_text'] ?? __( 'Leave us a review', 'cc-assistant' ) ); ?></a>
					</div>
					<?php
				endif;
			endif;
			?>
		</div>
		<?php
	}
}
