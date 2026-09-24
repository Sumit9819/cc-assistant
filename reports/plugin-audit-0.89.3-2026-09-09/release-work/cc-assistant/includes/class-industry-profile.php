<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-site industry profile. The SEO playbook is composed at runtime from
 * a universal core plus a per-industry overlay, so a medical clinic gets
 * "name the clinician + cite CDC/NIH" while a law firm gets "name the
 * attorney + cite state bar / .gov regulator" without anyone editing the
 * playbook source. Detection is cheap and runs on-demand; the operator can
 * override the result from the Settings page.
 *
 * Storage: `cc_assistant_industry_profile` option, shape:
 *   [
 *     'industry'     => 'medical_clinic',     // slug from INDUSTRIES
 *     'source'       => 'auto'|'manual',
 *     'confidence'   => 0..100,               // 100 when manual
 *     'detected_at'  => 'YYYY-MM-DD HH:ii:ss',
 *     'signals'      => [ ...debug-only evidence ],
 *   ]
 */
class CC_Assistant_Industry_Profile {

	const OPTION_KEY = 'cc_assistant_industry_profile';

	const INDUSTRIES = array(
		'healthcare'            => 'Healthcare (clinic, ER, hospital, dental, vet, pharmacy)',
		'legal_practice'        => 'Legal Practice (YMYL Legal)',
		'financial_services'    => 'Financial Services (YMYL Money)',
		'home_services'         => 'Home Services / Trades',
		'local_business'        => 'Local Business (retail, hospitality, fitness)',
		'professional_services' => 'Professional Services (agency, consulting, B2B)',
		'ecommerce'             => 'E-commerce / Product Retail',
		'saas'                  => 'SaaS / Software Product',
		'publisher'             => 'Publisher / Editorial',
		'general'               => 'General (catch-all)',
	);

	/**
	 * Legacy slug compat. If an older install or auto-detect run saved
	 * a slug that has since been renamed, map it forward on read so the
	 * overlay still resolves correctly without forcing a re-detect.
	 */
	const SLUG_ALIASES = array(
		'medical_clinic' => 'healthcare',
		'medical'        => 'healthcare',
		'clinic'         => 'healthcare',
	);

	private static function canonical_slug( $slug ) {
		$slug = (string) $slug;
		if ( isset( self::SLUG_ALIASES[ $slug ] ) ) {
			return self::SLUG_ALIASES[ $slug ];
		}
		return $slug;
	}

	/**
	 * v0.31: Phrases that should NOT appear in body text on a site of this
	 * industry. Used by the page-audit's industry_vocabulary check to flag
	 * cross-industry content leakage (e.g. wellness/IV phrases on an ER page,
	 * clinical terms on a SaaS site, etc.). Returns a flat lowercase array.
	 *
	 * Detection is substring-based, case-insensitive. Keep entries focused —
	 * a flagged term should be unambiguously wrong on the target industry.
	 * Words that are merely "less ideal" don't belong here.
	 *
	 * @param string|null $industry Override; falls back to current resolved slug.
	 * @return array Lowercase phrases to flag in content.
	 */
	public static function banned_phrases( $industry = null ) {
		if ( null === $industry ) {
			$industry = self::industry_slug();
		}
		$banks = array(
			'healthcare' => array(
				// Wellness / IV-therapy crossover (the failure mode that hit on
				// the Irving 137 -> WR 2516 chest-pain clone).
				'infusion therapy', 'iv drip', 'iv therapy', 'iv hydration',
				'iv vitamin', 'premium drip', 'signature drip', 'beauty drip',
				'metabolic care', 'metabolic health', 'wellness coaching',
				'high performance lifestyle', 'rapid relief',
				// Specific wellness SKUs that don't apply to most healthcare sites
				'myers cocktail', 'glutathione drip', 'nad+ infusion', 'nad+ drip',
				'b-complex injection', 'lipo+ shot', 'weekend hangover',
				'hangover iv', 'jet lag drip',
				// Aesthetic/beauty crossover
				'microneedling', 'aesthetic treatment',
			),
			'legal_practice' => array(
				'treatment', 'patient', 'wellness', 'iv', 'infusion',
				'clinical', 'symptoms', 'diagnosis', 'physician',
				'drip', 'vitamin', 'hydration',
			),
			'financial_services' => array(
				'treatment', 'patient', 'wellness', 'iv', 'infusion',
				'clinical', 'physician', 'attorney', 'lawyer', 'litigation',
				'drip', 'cocktail',
			),
			'home_services' => array(
				'patient', 'physician', 'wellness coaching', 'iv therapy',
				'attorney', 'litigation', 'medical procedure',
			),
			'local_business' => array(
				'patient', 'physician', 'attorney', 'litigation',
				'iv therapy', 'infusion', 'glutathione',
			),
			'professional_services' => array(
				'patient', 'physician', 'iv therapy', 'infusion',
				'medical procedure', 'clinical',
			),
			'ecommerce' => array(
				'patient', 'physician', 'medical procedure', 'attorney',
				'iv therapy', 'infusion',
			),
			'saas' => array(
				'patient', 'physician', 'attorney', 'litigation',
				'iv therapy', 'infusion', 'wellness coaching',
			),
			'publisher' => array(
				// Publishers cover many topics — fewer hard bans
				'iv therapy session', 'premium drip',
			),
			'general' => array(),
		);
		return isset( $banks[ $industry ] ) ? $banks[ $industry ] : array();
	}

	/**
	 * v0.31: Vocabulary the AI should PREFER on this industry. Used by the
	 * audit to suggest replacements when a banned phrase is found. Returns
	 * a hash of {wrong_phrase => preferred_replacement} per industry.
	 */
	public static function preferred_vocabulary( $industry = null ) {
		if ( null === $industry ) {
			$industry = self::industry_slug();
		}
		$maps = array(
			'healthcare' => array(
				'infusion therapy' => 'emergency care',
				'iv drip'          => 'emergency treatment',
				'metabolic care'   => 'emergency medicine',
				'wellness coaching' => 'follow-up care',
				'patient experience' => 'visit experience',
				'high performance lifestyle' => 'busy schedule',
				'premium drip'     => 'workup',
				'rapid relief'     => 'rapid evaluation',
			),
			'legal_practice' => array(
				'treatment' => 'representation',
				'patient'   => 'client',
				'clinical'  => 'case',
				'diagnosis' => 'case assessment',
			),
			'financial_services' => array(
				'treatment' => 'service',
				'patient'   => 'client',
			),
		);
		return isset( $maps[ $industry ] ) ? $maps[ $industry ] : array();
	}

	/**
	 * Read the active profile. Auto-detects on first read; respects
	 * operator override once `source` is set to manual.
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );
		$saved = is_array( $saved ) ? $saved : array();
		if ( ! empty( $saved['industry'] ) ) {
			// Walk legacy slugs forward to their current canonical name so
			// an upgrade doesn't have to force a re-detect on every site.
			$saved['industry'] = self::canonical_slug( $saved['industry'] );
			if ( isset( self::INDUSTRIES[ $saved['industry'] ] ) ) {
				return $saved;
			}
		}
		// First-read auto-detect, persisted so subsequent reads are free.
		$auto = self::detect();
		update_option( self::OPTION_KEY, $auto, false );
		return $auto;
	}

	/**
	 * Just the slug, for callers that don't need provenance.
	 */
	public static function industry_slug() {
		$p = self::get();
		return isset( $p['industry'] ) ? $p['industry'] : 'general';
	}

	public static function label( $slug = null ) {
		if ( null === $slug ) {
			$slug = self::industry_slug();
		}
		return isset( self::INDUSTRIES[ $slug ] ) ? self::INDUSTRIES[ $slug ] : self::INDUSTRIES['general'];
	}

	/**
	 * Operator override from Settings. Skips detection from now on.
	 * Pass null/empty/'auto' to clear the override and re-detect.
	 */
	public static function set_manual( $slug ) {
		if ( empty( $slug ) || 'auto' === $slug ) {
			delete_option( self::OPTION_KEY );
			return self::get();
		}
		$slug = self::canonical_slug( $slug );
		if ( ! isset( self::INDUSTRIES[ $slug ] ) ) {
			return new WP_Error( 'cc_invalid_industry', 'Unknown industry slug: ' . $slug );
		}
		$profile = array(
			'industry'    => $slug,
			'source'      => 'manual',
			'confidence'  => 100,
			'detected_at' => current_time( 'mysql' ),
			'signals'     => array( 'manual_override' => true ),
		);
		update_option( self::OPTION_KEY, $profile, false );
		return $profile;
	}

	/**
	 * Re-run auto-detection (e.g. operator clicked "Re-detect" or schema
	 * landscape changed materially). Overwrites manual overrides — caller
	 * should confirm intent.
	 */
	public static function refresh() {
		$auto = self::detect();
		update_option( self::OPTION_KEY, $auto, false );
		return $auto;
	}

	/**
	 * Score every industry from the signals and pick the top. Confidence
	 * is the winning score normalized to 0..100. If nothing scores, we
	 * land on 'general' with confidence 0.
	 */
	public static function detect() {
		$signals = self::gather_signals();
		$scores  = array_fill_keys( array_keys( self::INDUSTRIES ), 0 );

		// 1) Active SEO plugin's Local SEO business type, if set. Weighted
		// heavily because it's an operator declaration, not a heuristic.
		if ( ! empty( $signals['seo_business_type'] ) ) {
			$mapped = self::map_local_business_type( $signals['seo_business_type'] );
			if ( $mapped ) {
				$scores[ $mapped ] += 40;
			}
		}

		// 2) Schema types observed in JSON-LD on published pages. Worth
		// less than a declaration but more than a keyword guess.
		foreach ( $signals['schema_types'] as $type => $hits ) {
			$mapped = self::map_schema_type( $type );
			if ( $mapped ) {
				$scores[ $mapped ] += min( 25, $hits * 5 );
			}
		}

		// 3) WooCommerce / EDD presence — strong signal for ecommerce.
		if ( $signals['has_woocommerce'] || $signals['has_edd'] ) {
			$scores['ecommerce'] += 25;
		}

		// 4) Title-corpus keyword frequency. Lots of false positives so
		// each hit is worth only a few points; the cumulative effect on
		// a clearly themed site still rises above the noise.
		foreach ( $signals['title_keyword_hits'] as $industry => $hits ) {
			if ( isset( $scores[ $industry ] ) ) {
				$scores[ $industry ] += min( 20, $hits );
			}
		}

		arsort( $scores );
		$winner = key( $scores );
		$top    = current( $scores );
		// Need at least a meaningful signal to commit. Otherwise general.
		if ( $top < 10 ) {
			$winner = 'general';
			$top    = 0;
		}

		return array(
			'industry'    => $winner,
			'source'      => 'auto',
			'confidence'  => min( 100, $top ),
			'detected_at' => current_time( 'mysql' ),
			'signals'     => $signals,
			'scores'      => $scores,
		);
	}

	/**
	 * Collect raw evidence for detection. Read-only, safe to call on demand.
	 */
	private static function gather_signals() {
		global $wpdb;
		$signals = array(
			'seo_business_type'  => null,
			'schema_types'       => array(),
			'has_woocommerce'    => class_exists( 'WooCommerce' ),
			'has_edd'            => class_exists( 'Easy_Digital_Downloads' ),
			'title_keyword_hits' => array(),
		);

		// SEO plugin Local SEO type. Yoast and Rank Math both surface this.
		if ( class_exists( 'WPSEO_Options' ) ) {
			$opt = get_option( 'wpseo_titles', array() );
			if ( is_array( $opt ) && ! empty( $opt['company_or_person_user_type'] ) ) {
				$signals['seo_business_type'] = (string) $opt['company_or_person_user_type'];
			} elseif ( is_array( $opt ) && ! empty( $opt['business_type'] ) ) {
				$signals['seo_business_type'] = (string) $opt['business_type'];
			}
		}
		if ( null === $signals['seo_business_type'] && class_exists( 'RankMath\Helper' ) ) {
			$type = get_option( 'rank_math_options_titles', array() );
			if ( is_array( $type ) && ! empty( $type['local_business_type'] ) ) {
				$signals['seo_business_type'] = (string) $type['local_business_type'];
			} elseif ( is_array( $type ) && ! empty( $type['knowledgegraph_type'] ) ) {
				$signals['seo_business_type'] = (string) $type['knowledgegraph_type'];
			}
		}

		// Scan published page/post titles for industry keywords. Cap at
		// 500 rows so a giant publisher site doesn't blow query time.
		$titles = $wpdb->get_col(
			"SELECT post_title FROM {$wpdb->posts}
			 WHERE post_status='publish' AND post_type IN ('page','post')
			 ORDER BY post_modified DESC LIMIT 500"
		);
		$haystack = strtolower( implode( ' || ', (array) $titles ) );

		$keyword_map = array(
			'healthcare'            => array( 'clinic', 'hospital', 'doctor', 'physician', 'nurse', 'treatment', 'emergency room', ' er ', 'urgent care', 'medical', 'health', 'dental', 'dentist', 'orthodontist', 'therapy', 'wellness', 'iv ', 'symptom', 'diagnosis', 'pediatric', 'cardio', 'pharmacy', 'veterinary', 'vet ', 'triage' ),
			'legal_practice'        => array( 'attorney', 'lawyer', 'law firm', 'legal', 'litigation', 'paralegal', 'plaintiff', 'defense', 'injury claim', 'estate planning', 'divorce', 'bankruptcy' ),
			'financial_services'    => array( 'financial advisor', 'wealth', 'investment', 'accounting', 'cpa', 'tax', 'retirement', 'planner', 'mortgage', 'insurance' ),
			'home_services'         => array( 'hvac', 'plumber', 'plumbing', 'electrician', 'roofer', 'roofing', 'locksmith', 'contractor', 'remodel', 'landscaping', 'pest control', 'cleaning service', 'handyman', 'garage door' ),
			'local_business'        => array( 'restaurant', 'cafe', 'salon', 'spa', 'gym', 'fitness', 'yoga', 'pilates', 'studio', 'bakery', 'boutique' ),
			'professional_services' => array( 'consulting', 'consultant', 'agency', 'marketing', 'coaching', 'coach', 'recruiting', 'staffing' ),
			'ecommerce'             => array( 'shop', 'store', 'product', 'buy ', 'order ', 'collection', 'sale' ),
			'saas'                  => array( 'software', 'platform', 'app ', 'api ', 'integration', 'dashboard', 'cloud' ),
			'publisher'             => array( 'news', 'magazine', 'review', 'guide to', 'how to', 'best of', 'top 10' ),
		);
		foreach ( $keyword_map as $industry => $words ) {
			$hits = 0;
			foreach ( $words as $w ) {
				$hits += substr_count( $haystack, $w );
			}
			if ( $hits > 0 ) {
				$signals['title_keyword_hits'][ $industry ] = $hits;
			}
		}

		// Schema types in JSON-LD on the homepage (cheap proxy for the
		// site's primary entity declaration). One fetch, no per-post work.
		$home_url = home_url( '/' );
		$resp     = wp_remote_get(
			$home_url,
			array(
				'timeout'    => 8,
				'user-agent' => CC_ASSISTANT_HTTP_UA,
				'sslverify'  => true,
				'redirection' => 0,
				'limit_response_size' => 2097152,
			)
		);
		if ( ! is_wp_error( $resp ) ) {
			$html = wp_remote_retrieve_body( $resp );
			if ( $html && preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m ) ) {
				foreach ( $m[1] as $block ) {
					$json = json_decode( trim( $block ), true );
					if ( ! is_array( $json ) ) {
						continue;
					}
					self::collect_schema_types( $json, $signals['schema_types'] );
				}
			}
		}

		return $signals;
	}

	private static function collect_schema_types( $node, array &$bucket ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		if ( isset( $node['@type'] ) ) {
			$types = (array) $node['@type'];
			foreach ( $types as $t ) {
				$t = (string) $t;
				if ( '' !== $t ) {
					$bucket[ $t ] = isset( $bucket[ $t ] ) ? $bucket[ $t ] + 1 : 1;
				}
			}
		}
		foreach ( $node as $v ) {
			if ( is_array( $v ) ) {
				self::collect_schema_types( $v, $bucket );
			}
		}
	}

	private static function map_schema_type( $type ) {
		$type = (string) $type;
		// Healthcare (clinic, urgent care, ER, hospital, dental, vet, pharmacy)
		if ( in_array( $type, array( 'MedicalClinic', 'Hospital', 'EmergencyService', 'Dentist', 'Physician', 'MedicalOrganization', 'MedicalBusiness', 'PhysicalTherapy', 'Pharmacy', 'VeterinaryCare' ), true ) ) {
			return 'healthcare';
		}
		// Legal
		if ( in_array( $type, array( 'LegalService', 'Attorney', 'Notary' ), true ) ) {
			return 'legal_practice';
		}
		// Financial
		if ( in_array( $type, array( 'FinancialService', 'AccountingService', 'InsuranceAgency', 'BankOrCreditUnion', 'AutomatedTeller' ), true ) ) {
			return 'financial_services';
		}
		// Home services
		if ( in_array( $type, array( 'HomeAndConstructionBusiness', 'HVACBusiness', 'Plumber', 'Electrician', 'RoofingContractor', 'Locksmith', 'GeneralContractor', 'HousePainter', 'MovingCompany' ), true ) ) {
			return 'home_services';
		}
		// Local
		if ( in_array( $type, array( 'Restaurant', 'CafeOrCoffeeShop', 'Bakery', 'BeautySalon', 'HairSalon', 'DaySpa', 'HealthClub', 'SportsActivityLocation', 'Store' ), true ) ) {
			return 'local_business';
		}
		// Professional services
		if ( in_array( $type, array( 'ProfessionalService' ), true ) ) {
			return 'professional_services';
		}
		// Ecommerce
		if ( in_array( $type, array( 'OnlineStore', 'Product', 'ProductGroup', 'Offer' ), true ) ) {
			return 'ecommerce';
		}
		// SaaS / Software
		if ( in_array( $type, array( 'SoftwareApplication', 'WebApplication', 'MobileApplication' ), true ) ) {
			return 'saas';
		}
		// Publisher
		if ( in_array( $type, array( 'NewsArticle', 'NewsMediaOrganization', 'Newspaper', 'Periodical' ), true ) ) {
			return 'publisher';
		}
		return null;
	}

	/**
	 * Yoast/Rank Math both surface a "business type" string. Map the common
	 * values to our industry slugs. Unknown strings return null so the
	 * keyword + schema signals take over.
	 */
	private static function map_local_business_type( $value ) {
		$v = strtolower( (string) $value );
		if ( '' === $v ) {
			return null;
		}
		if (
			str_contains( $v, 'medical' )
			|| str_contains( $v, 'clinic' )
			|| str_contains( $v, 'hospital' )
			|| str_contains( $v, 'emergency' )
			|| str_contains( $v, 'urgent' )
			|| str_contains( $v, 'dentist' )
			|| str_contains( $v, 'physician' )
			|| str_contains( $v, 'pharmacy' )
			|| str_contains( $v, 'veterinary' )
		) {
			return 'healthcare';
		}
		if ( str_contains( $v, 'legal' ) || str_contains( $v, 'attorney' ) || str_contains( $v, 'lawfirm' ) ) {
			return 'legal_practice';
		}
		if ( str_contains( $v, 'financial' ) || str_contains( $v, 'accounting' ) || str_contains( $v, 'insurance' ) || str_contains( $v, 'bank' ) ) {
			return 'financial_services';
		}
		if ( str_contains( $v, 'construction' ) || str_contains( $v, 'hvac' ) || str_contains( $v, 'plumber' ) || str_contains( $v, 'electrician' ) || str_contains( $v, 'roof' ) || str_contains( $v, 'locksmith' ) || str_contains( $v, 'contractor' ) ) {
			return 'home_services';
		}
		if ( str_contains( $v, 'restaurant' ) || str_contains( $v, 'cafe' ) || str_contains( $v, 'bakery' ) || str_contains( $v, 'salon' ) || str_contains( $v, 'spa' ) || str_contains( $v, 'gym' ) || str_contains( $v, 'store' ) || str_contains( $v, 'shop' ) ) {
			return 'local_business';
		}
		if ( str_contains( $v, 'professional' ) ) {
			return 'professional_services';
		}
		return null;
	}
}
