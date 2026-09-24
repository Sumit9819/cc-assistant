<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attention-flow archetypes (v0.55).
 *
 * A page is a controlled path for the eyes, not a stack of sections. Each
 * archetype declares the attention arc the build must serve BEFORE sections
 * are chosen: the 5-second job, what the first viewport must contain, the
 * scroll story band by band, and the CTA rules. The model composes against
 * the spec; class-attention-audit.php then checks the result.
 *
 * Grounded in scan-pattern research (Nielsen scroll studies: ~60-80% of
 * attention in the first viewport; F-pattern for text-heavy bands; Z-pattern
 * for sparse hero bands; layer-cake heading-only scanning on long pages).
 */
class CC_Assistant_Attention_Spec {

	/** Archetype key => full spec. */
	public static function archetypes() {
		return array(

			'emergency_transactional' => array(
				'label'          => 'Emergency / urgent care (panic-mode visitor)',
				'user_state'     => 'Stressed, often mobile, often at night. Will not scroll to find a phone number. Cognitive load must be near zero.',
				'five_second_job' => 'Phone number, "Open 24/7", and location visible without scrolling or thinking.',
				'first_viewport' => array(
					'tap-to-call phone link (tel:)',
					'open-now / 24-7 signal',
					'location or "near {city}" anchor',
					'ONE primary CTA (call), directions as quiet secondary',
				),
				'scroll_story'   => array(
					'reassure: capability + speed (board-certified, no wait, on-site imaging)',
					'prove: reviews / credentials immediately BEFORE the next CTA',
					'detail: conditions treated as scannable cards, not prose',
					'act: repeat call CTA + directions; bottom-center for thumb reach',
				),
				'cta_rules'      => array(
					'primary_action'    => 'tel: call',
					'max_per_viewport'  => 1,
					'second_prize'      => 'directions link',
					'tel_above_fold'    => true,
				),
				'scan_pattern'   => 'Z in hero, layer-cake below — headings must answer "can they treat this, how fast, where".',
			),

			'consideration_conversion' => array(
				'label'          => 'Wellness / med-spa / scheduled service (researching visitor)',
				'user_state'     => 'Unhurried, comparing providers, needs trust before action. Multiple visits before converting.',
				'five_second_job' => 'What this is, who it is for, and one credible reason to trust — then a soft CTA.',
				'first_viewport' => array(
					'outcome-focused headline (benefit, not service name)',
					'one human image (gaze directed toward headline or CTA)',
					'soft primary CTA (book consultation), not a hard sell',
				),
				'scroll_story'   => array(
					'explain: what happens, how it works, what it feels like',
					'prove: real reviews, before/after, provider credentials',
					'reduce risk: pricing clarity, FAQ accordion, financing',
					'act: booking CTA with proof element directly above it',
				),
				'cta_rules'      => array(
					'primary_action'    => 'book / consultation form',
					'max_per_viewport'  => 1,
					'second_prize'      => 'read a guide / call with questions',
					'tel_above_fold'    => false,
				),
				'scan_pattern'   => 'F in explainer bands — front-load keywords at the left edge of headings and first sentences.',
			),

			'service_local' => array(
				'label'          => 'Local service page (transactional, not urgent)',
				'user_state'     => 'Knows what they need, deciding who to hire. Scans for fit signals: area served, proof, price shape.',
				'five_second_job' => 'Service + area + one differentiator, with the contact path visible.',
				'first_viewport' => array(
					'service + geo headline (H1, left-anchored)',
					'primary CTA (call or quote)',
					'one fit signal (years, rating, guarantee)',
				),
				'scroll_story'   => array(
					'scope: what is included, as cards or icon-list — scannable',
					'prove: reviews with names/places, real photos over stock',
					'differentiate: why this provider, one band, concrete claims',
					'act: repeat CTA; service-area anchor for the geo scanner',
				),
				'cta_rules'      => array(
					'primary_action'    => 'call or quote form',
					'max_per_viewport'  => 1,
					'second_prize'      => 'view related service / gallery',
					'tel_above_fold'    => true,
				),
				'scan_pattern'   => 'Z in hero, F in scope bands.',
			),

			'informational_guide' => array(
				'label'          => 'Blog / guide (research visitor, layer-cake scanner)',
				'user_state'     => 'Wants the answer fast, will scan headings only, converts (if ever) via a soft bridge to a service.',
				'five_second_job' => 'Confirm "this page answers my exact question" — the H1 mirrors the query, the first paragraph gives the short answer.',
				'first_viewport' => array(
					'H1 mirroring the typed query',
					'direct short answer in the first 2-3 sentences (AI-Overview-ready)',
					'scannable structure visible (TOC or first H2 peeking)',
				),
				'scroll_story'   => array(
					'answer: each H2 is a question the reader would type',
					'evidence: cited data (.gov/.edu), no opinion framing',
					'bridge: ONE contextual service link where urgency naturally peaks',
					'act: soft CTA band at the end only — never interrupt the answer',
				),
				'cta_rules'      => array(
					'primary_action'    => 'contextual service bridge (inline link)',
					'max_per_viewport'  => 1,
					'second_prize'      => 'related guide (inline, not a "related posts" grid)',
					'tel_above_fold'    => false,
				),
				'scan_pattern'   => 'Layer-cake: headings carry ALL the attention — write them as queries, front-load keywords.',
			),
		);
	}

	/** Composition rules that apply to EVERY archetype (the audit checks these). */
	public static function checklist() {
		return array(
			'one_dominant_element'  => 'Each viewport has exactly one visually dominant element. If two things are equally loud, neither leads.',
			'one_cta_per_viewport'  => 'One primary CTA per screen. A second equal CTA halves both.',
			'proof_before_cta'      => 'A proof element (review, rating, credential) sits immediately before or beside every conversion CTA.',
			'left_edge_priority'    => 'In text-heavy bands, key words start headings and sentences — the F-pattern left edge is prime real estate.',
			'rhythm'                => 'Alternate band treatments (full-bleed vs boxed, tinted vs white) so the eye gets landmarks. 5+ identical treatments in a row reads as one grey blur.',
			'weight_is_a_budget'    => 'Size, contrast, and whitespace buy attention. Spend them on 2-3 moments per page, not everywhere.',
			'squint_test'           => 'Blur the page: the things that still stand out must be, in order, the things that matter most.',
			'mobile_first_viewport' => 'Compose for ~390x700 first. Thumb reach favors bottom-center CTAs; hover states do not exist.',
			'exit_path'             => 'Most visitors will not convert today — the top and bottom of the page each carry a deliberate second-prize action.',
		);
	}

	/**
	 * Pick the archetype for a post from its intent classification + the
	 * site's business mode. Explicit override always wins.
	 */
	public static function for_post( $post_id, $override = '' ) {
		$archetypes = self::archetypes();
		if ( '' !== $override && isset( $archetypes[ $override ] ) ) {
			return array( 'archetype' => $override, 'source' => 'override', 'spec' => $archetypes[ $override ] );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-page-intent.php';
		$intent = CC_Assistant_Page_Intent::classify_page( $post_id );
		$mode   = CC_Assistant_Page_Intent::business_mode();

		if ( 'research' === $intent['family'] ) {
			$key = 'informational_guide';
		} elseif ( 'emergency' === $mode ) {
			$key = 'emergency_transactional';
		} elseif ( 'scheduled' === $mode ) {
			$key = 'consideration_conversion';
		} else {
			$key = 'service_local';
		}

		return array(
			'archetype' => $key,
			'source'    => 'auto (' . $intent['page_type'] . '/' . $intent['family'] . ', mode ' . $mode . ')',
			'spec'      => $archetypes[ $key ],
		);
	}
}
