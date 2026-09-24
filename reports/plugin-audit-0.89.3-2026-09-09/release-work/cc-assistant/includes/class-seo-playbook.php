<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bundled 2026 SEO playbook surfaced to the model on every session
 * bootstrap. Keeps the model from re-deriving "what does Google reward
 * this year" from scratch and stops well-intentioned but stale advice
 * (keyword density formulas, exact-match anchor stuffing, daily-fresh
 * content, AMP, etc.) from creeping back in.
 *
 * Architecture (v0.28+):
 *   - `universal()` returns the rules that apply to every site (E-E-A-T
 *     as a concept, schema parity, audit chain, AIO CTR loss data,
 *     internal linking shape, readability, the leak findings, etc).
 *   - `overlays()` returns per-industry add-ons keyed by industry slug.
 *     Each overlay names the trusted citation sources, the schema types
 *     that map to that industry's entity graph, the credentialing
 *     pattern the model should look for, and any vertical-specific
 *     content rules (e.g. medical = MedicalProcedure + Medically
 *     Reviewed pattern; legal = Attorney schema + bar number).
 *   - `top_rules()` and `full()` compose universal + the matched overlay
 *     at call time. The site's industry comes from
 *     CC_Assistant_Industry_Profile (auto-detected, operator overridable).
 *
 * Versioned by year + month so any change is auditable. The whoami
 * response surfaces `version` + `industry` + `top_rules`; the full
 * playbook is available via the `seo_playbook` MCP tool.
 */
class CC_Assistant_SEO_Playbook {

	const VERSION = '2026.09.09.3';

	/**
	 * Resolve the current industry slug. Lazy require so callers that only
	 * want the universal sections don't pay for option reads.
	 */
	private static function current_industry() {
		require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		return CC_Assistant_Industry_Profile::industry_slug();
	}

	/**
	 * Top rules surfaced in the whoami session bootstrap. Keep this short
	 * — every byte ships on every new session, so it has to earn its place.
	 * The full playbook lives in `full()` below.
	 *
	 * Composes universal_top_rules() with the matched industry overlay's
	 * top_rules so a clinic site gets the medical-clinic-specific rules at
	 * the top and a law firm gets legal-practice rules in their place.
	 */
	public static function top_rules( $industry = null ) {
        return self::universal_top_rules();
    }

    public static function universal_top_rules() {
        require_once CC_ASSISTANT_DIR . 'includes/class-seo-policy.php';
        return CC_Assistant_SEO_Policy::rules();
    }

    /** Full and short guidance share the same source-backed rule registry. */

	public static function full( $industry = null ) {
		if ( null === $industry ) {
			$industry = self::current_industry();
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		$overlays         = self::overlays();
		$industry_overlay = isset( $overlays[ $industry ] ) ? $overlays[ $industry ] : $overlays['general'];
		$industry_sections = isset( $industry_overlay['sections'] ) ? $industry_overlay['sections'] : array();
		return array(
			'version'  => self::VERSION,
			'updated'  => '2026-09-09',
			'industry' => array(
				'slug'  => $industry,
				'label' => CC_Assistant_Industry_Profile::label( $industry ),
				'available' => CC_Assistant_Industry_Profile::INDUSTRIES,
			),
			'sections' => self::universal_sections(),
			'industry_guidance' => 'Industry identity is context, not verification of capabilities, credentials, schema eligibility or business facts. Check actual site evidence.'
		);
	}

	/**
	 * Universal long-form sections. These apply to every site verbatim.
	 * Industry-specific rules live in overlays() and are merged in at the
	 * top of the section list by full().
	 */
	private static function universal_sections() {
        require_once CC_ASSISTANT_DIR . 'includes/class-seo-policy.php';
        return CC_Assistant_SEO_Policy::sections();
    }

    /** Legacy industry vocabulary retained for identity matching, not served as current SEO rules. */

	public static function overlays() {
		return array(
			'healthcare'            => self::overlay_healthcare(),
			'legal_practice'        => self::overlay_legal_practice(),
			'financial_services'    => self::overlay_financial_services(),
			'home_services'         => self::overlay_home_services(),
			'local_business'        => self::overlay_local_business(),
			'professional_services' => self::overlay_professional_services(),
			'ecommerce'             => self::overlay_ecommerce(),
			'saas'                  => self::overlay_saas(),
			'publisher'             => self::overlay_publisher(),
			'general'               => self::overlay_general(),
		);
	}

	/**
	 * Healthcare overlay. Covers primary-care clinics, urgent care,
	 * freestanding emergency rooms, hospitals, dental practices,
	 * veterinary clinics, and pharmacies. The umbrella rules apply
	 * everywhere; sub-rules call out where ER / hospital pages diverge
	 * from primary-care clinic pages (schema type, content shape,
	 * triage-decisioning H2 pattern).
	 */
	private static function overlay_healthcare() {
		return array(
			'top_rules' => array(
				'INTENT DOCTRINE (one page = one intent): service/landing pages serve the CONVERSION intent and must NOT chase informational queries. Business mode flips above-the-fold: EMERGENCY ER = transactional/local (click-to-call + Walk In 24/7 + address/directions, EmergencyService schema) vs SCHEDULED wellness/clinic = commercial/transactional (Book a Consultation, MedicalClinic schema). Informational "what/why/causes/symptoms/when to go" content belongs on a blog SPOKE that links inline to the service page. Run page_robustness_audit(post_id) before AND after every build/optimize; run win_audit against same-intent competitors only.',
				'YMYL Health classifier applies (Google pre-classifies healthcare pages on a stricter standard). Every service page names the provider + credential (MD, DO, FNP-C, APRN, PA, DDS, DVM, PharmD) inline AND in schema Person, with sameAs pointing to LinkedIn / NPI / state license registry / specialty board.',
				'Schema by facility type: clinics use MedicalClinic; urgent care uses MedicalClinic or MedicalBusiness; freestanding emergency rooms use EmergencyService; hospitals use Hospital; dental uses Dentist; vet uses VeterinaryCare; pharmacy uses Pharmacy. Service pages add MedicalProcedure + Person + FAQPage. Never ship two FAQPage entries on the same URL.',
				'Authority density: minimum 1 .gov / .edu citation per 1000 words. Trusted hosts: NIH MedlinePlus, CDC, NIDDK, FDA, NEJM, JAMA, Mayo Clinic. For pediatrics: AAP. For emergency medicine: ACEP, ENA. For dental: ADA. For veterinary: AVMA.',
				'Reviewed-by pattern: "Medically Reviewed by: NAME, CREDENTIAL | Updated: MONTH YEAR" visible on every clinical page (or "Reviewed by:" for dental/vet/pharmacy where "medically reviewed" reads odd).',
				'ER and urgent-care pages lead with triage decisioning. Question-shaped H2s like "When is this an ER visit vs urgent care?" outperform service-description H2s on emergency-intent queries.',
			),
			'sections' => array(
				array(
					'name' => 'Healthcare E-E-A-T',
					'why_matters' => 'Health is YMYL. Google\'s leaked ymylHealthScore is a real per-page classifier. Anonymous medical content does not rank; authored-by-credentialed-provider beats it by orders of magnitude.',
					'rules' => array(
						'Name the provider inline + in schema Person + in meta description (where character budget allows)',
						'Cite peer-reviewed sources (NEJM, JAMA, Lancet, Annals of Emergency Medicine for EM) for clinical claims',
						'Quarterly review pattern: "Medically Reviewed by: NAME | Updated: MONTH YEAR"',
						'Author bio populated for every contributing provider (Users → Profile). Include board certification, years in practice, and one verifiable external profile link.',
						'Schema Person.sameAs should include LinkedIn AND NPI Registry / MedScape / state license lookup / specialty board (ABMS, AOA, ABEM for EM, ADA, AVMA) where available',
					),
				),
				array(
					'name' => 'Healthcare schema markup',
					'why_matters' => 'Service pages without MedicalProcedure are invisible to Google\'s medical entity layer. The right business-entity type (EmergencyService for ER, Hospital for hospital, MedicalClinic for clinic, Dentist for dental, etc.) is how Google maps the page into its healthcare entity graph.',
					'rules' => array(
						'Pick the most specific business-entity schema: EmergencyService for freestanding ERs, Hospital for hospital facilities, MedicalClinic or MedicalBusiness for urgent care + primary care, Dentist for dental practices, VeterinaryCare for vet clinics, Pharmacy for pharmacies',
						'MedicalProcedure on every service page — name, alternateName, procedureType, performer, preparation, howPerformed, followup, indication, bodyLocation',
						'Homepage entity must have hasMap, openingHoursSpecification, telephone, and the correct sub-type',
						'Person entity for each provider with hasCredential, jobTitle, alumniOf, memberOf (specialty board)',
						'Service entity linked to the business entity via hasOfferingCatalog',
						'Self-review prohibition: aggregateRating on the site\'s own MedicalClinic / Hospital / EmergencyService / Dentist is ineligible AND a manual-action trip-wire. Use third-party reviews (Google, Healthgrades, Vitals) referenced by name without inlining the schema. Run self_review_detection before queueing any schema change to a page with a reviews widget.',
					),
				),
				array(
					'name' => 'Healthcare authority citation hosts',
					'why_matters' => 'On YMYL health pages, citation density to trusted government / academic / peer-reviewed sources is a measurable trust signal. The acceptable host list is narrower than for other verticals and varies by sub-specialty.',
					'rules' => array(
						'Primary (always trusted): cdc.gov, nih.gov, medlineplus.gov, fda.gov, niddk.nih.gov, ninds.nih.gov, your state DSHS (e.g. dshs.texas.gov)',
						'Peer-reviewed: NEJM, JAMA, Lancet, BMJ, Annals of Internal Medicine, Annals of Emergency Medicine (for EM)',
						'Specialty bodies: AAP (pediatrics), AHA (cardiology), AAOS (orthopedics), AAFP (family medicine), ACEP + ENA (emergency medicine), ADA (dental), AVMA (veterinary), ASHP (pharmacy)',
						'NEVER cite competitor providers / clinics / hospitals even if their content is high-quality — entity association leaks authority',
						'Inline anchor wraps on existing prose, not appended reference lists',
					),
				),
				array(
					'name' => 'ER and urgent-care specific content shape',
					'why_matters' => 'Freestanding emergency rooms and urgent care centers rank on triage-decisioning queries ("when is this an ER visit", "ER vs urgent care", "should I go to the ER for X"). The H2 structure and content shape for these pages differs from chronic-care clinical content — readers are making a same-hour decision, not researching a condition.',
					'rules' => array(
						'Lead the page with a definitive triage statement in the first 1-2 sentences (e.g. "Chest pain with shortness of breath needs the ER, not urgent care."). +14% AI citation per Indig.',
						'Use question-shaped H2s with severity grading: "When is this an ER visit?", "When can urgent care handle it?", "When should you call 911?"',
						'Provide a clear ER vs urgent care vs primary care decision framework on every condition page. This is the most-clicked content type in EM search.',
						'For freestanding ER schema use type: "EmergencyService" with availableService listing the actual emergency-medicine procedures',
						'Don\'t soften the language to avoid liability — vague hedging ("you might want to consider seeking care") under-ranks against specific, severity-graded guidance with a citation',
					),
				),
			),
		);
	}

	private static function overlay_legal_practice() {
		return array(
			'top_rules' => array(
				'YMYL Legal classifier applies. Every practice-area page names the attorney + bar number + jurisdiction inline AND in schema Attorney/Person with sameAs to the state bar profile.',
				'Schema: practice-area pages need LegalService + Attorney/Person + FAQPage; service area in areaServed.',
				'Authority density: minimum 1 .gov citation per 1000 words. Trusted hosts: state bar associations, .gov regulators, court records, USCIS / IRS / NLRB for federal practice areas.',
			),
			'sections' => array(
				array(
					'name' => 'Legal Practice E-E-A-T',
					'why_matters' => 'Legal is YMYL. Practice-area pages without a named, credentialed attorney rank poorly because Google can\'t map the page to a verified expert entity.',
					'rules' => array(
						'Name the attorney inline + in schema Attorney/Person + on the practice-area page header',
						'Bar number and admission jurisdiction visible on every attorney bio',
						'Schema Person.sameAs should include the state bar profile URL (Martindale-Hubbell, AVVO secondary)',
						'Cite statutes, regulations, court opinions with the proper citation format (e.g. "Tex. Penal Code § 22.04" not a paraphrase)',
						'Author bio populated for every attorney contributor',
					),
				),
				array(
					'name' => 'Legal schema markup',
					'why_matters' => 'LegalService is the entity Google uses to model law-firm practice areas. Attorney + areaServed lets Google map the firm to the jurisdiction.',
					'rules' => array(
						'LegalService schema on every practice-area page with name, serviceType, areaServed, provider',
						'Attorney schema on every bio page with hasCredential, jobTitle, alumniOf, memberOf (bar)',
						'LocalBusiness (or LegalService as LocalBusiness) on the homepage with hasMap, openingHoursSpecification',
						'FAQPage from on-page accordion where appropriate',
						'NEVER ship aggregateRating on the firm\'s own LegalService — third-party rating sources only',
					),
				),
				array(
					'name' => 'Legal authority citation hosts',
					'why_matters' => 'Trusted citation sources for legal content are narrower than for medical. Lean on primary law (statutes, regulations, court records) over secondary commentary.',
					'rules' => array(
						'Primary: state bar associations, [state].gov, uscourts.gov, supremecourt.gov, USCIS, IRS, NLRB, EEOC, FTC',
						'Secondary: state-published court opinions, Federal Register, Cornell LII (law.cornell.edu)',
						'NEVER cite competitor firms or paid directories as authority',
						'Use the proper legal citation format (Bluebook or jurisdiction\'s preferred) for credibility',
					),
				),
			),
		);
	}

	private static function overlay_financial_services() {
		return array(
			'top_rules' => array(
				'YMYL Money classifier applies. Every advisory page names the advisor + credential (CFP, CFA, CPA, ChFC) inline AND in schema Person with sameAs to FINRA BrokerCheck / SEC IAPD / state CPA registry.',
				'Schema: financial pages need FinancialService + Person; product pages need Service or Offer.',
				'Authority density: minimum 1 .gov / authoritative citation per 1000 words. Trusted hosts: SEC, IRS, FRED, FDIC, CFPB, Federal Reserve.',
				'Disclosures discipline: any forward-looking statement, return claim, or specific recommendation needs the appropriate disclaimer. Compliance review precedes SEO review.',
			),
			'sections' => array(
				array(
					'name' => 'Financial Services E-E-A-T',
					'why_matters' => 'Money is YMYL. Financial advisory pages without a credentialed, verified advisor rank poorly because the page can\'t be mapped to a real fiduciary entity.',
					'rules' => array(
						'Name the advisor + credential (CFP, CFA, CPA, ChFC, EA, JD) inline + in schema Person + on every advisory page',
						'Schema Person.sameAs should include FINRA BrokerCheck (brokercheck.finra.org/individual/summary/CRD), SEC IAPD, or the state CPA registry as primary professional verification',
						'Cite primary sources (IRS publications, SEC filings, Federal Reserve data, FRED time series) for any numerical claim',
						'Author bio populated for every contributing advisor, with credentials, firm CRD/IARD, and "years in practice" visible',
						'Compliance disclosure block on every advisory page (fiduciary status, fee structure, jurisdiction)',
					),
				),
				array(
					'name' => 'Financial schema markup',
					'why_matters' => 'FinancialService is the entity Google uses to model financial advisory and product offerings. Pair with Person so the advisor entity carries the authority.',
					'rules' => array(
						'FinancialService schema on the homepage and major advisory pages',
						'AccountingService or InsuranceAgency where appropriate instead of generic FinancialService',
						'Person schema for each advisor with hasCredential, jobTitle, memberOf (CFP Board, AICPA, etc.)',
						'Service entity for each offering with areaServed if jurisdiction-limited',
						'NEVER ship aggregateRating on the firm\'s own FinancialService — fiduciary rules + Google policy both prohibit it',
					),
				),
				array(
					'name' => 'Financial authority citation hosts',
					'why_matters' => 'Money YMYL has the strictest acceptable-source list. Lean on .gov primary sources and avoid trade publications when a regulator publishes the same data.',
					'rules' => array(
						'Primary: sec.gov, irs.gov, fred.stlouisfed.org, federalreserve.gov, fdic.gov, cfpb.gov, ssa.gov, treasury.gov',
						'Secondary: AICPA, CFP Board, FINRA investor education, Investor.gov',
						'NEVER cite paid financial directories or "best advisor" listings as authority',
						'Forward-looking statements need either a direct .gov citation or a disclaimer; never an unsourced projection',
					),
				),
			),
		);
	}

	private static function overlay_home_services() {
		return array(
			'top_rules' => array(
				'Local intent dominates. Service pages must own the [service noun] + [city/neighborhood] keyword. Never publish a blog post with both the service noun AND a location modifier.',
				'Schema: service pages need a specific LocalBusiness subtype (HVACBusiness, Plumber, Electrician, RoofingContractor, etc.) + Service + areaServed + FAQPage.',
				'License + insurance + service-area facts visible inline AND in schema. "Licensed, bonded, insured" is a trust phrase Google reads; pair with the actual license number.',
				'Authority density: at least 1 .gov / industry-association citation per 1500 words. Trusted hosts: state contractor licensing boards, EPA, DOE, OSHA, NFPA, ENERGY STAR.',
			),
			'sections' => array(
				array(
					'name' => 'Home Services / Trades E-E-A-T',
					'why_matters' => 'The March 2026 core update extended E-E-A-T scope beyond YMYL into trades. License + service-area + experience-years are the proxies for authority on a contractor site.',
					'rules' => array(
						'Visible license number and state on every service page',
						'"Licensed, bonded, insured" phrase paired with the actual license + insurance carrier name',
						'Years-in-business prominent on About page; schema Organization.foundingDate',
						'Owner / lead-tech bio with photo + experience + certifications (NATE, EPA 608, journeyman/master license)',
					),
				),
				array(
					'name' => 'Home Services schema markup',
					'why_matters' => 'Google uses LocalBusiness subtype + areaServed + Service to populate the Local Pack and Service Carousel. Generic LocalBusiness leaves entity-layer signal on the table.',
					'rules' => array(
						'Use the most specific LocalBusiness subtype: HVACBusiness, Plumber, Electrician, RoofingContractor, Locksmith, GeneralContractor, MovingCompany',
						'areaServed = explicit list of cities / counties (not "Dallas-Fort Worth metro area" — list them)',
						'Service schema per offering with serviceType + provider',
						'openingHoursSpecification + hasMap on the homepage entity',
						'NEVER ship aggregateRating on the company\'s own LocalBusiness — reference third-party review sources (Google, Angi, BBB) by name without inlining the schema',
					),
				),
				array(
					'name' => 'Home Services citation hosts',
					'why_matters' => 'Trust signals on a contractor page come from regulators (state licensing boards) and industry standards bodies, not from medical / financial sources.',
					'rules' => array(
						'Primary: state contractor licensing board (e.g. tdlr.texas.gov), epa.gov, osha.gov, energy.gov, nfpa.org, energystar.gov',
						'Trade-specific: ACCA (HVAC), PHCC (plumbing), NECA (electrical), NRCA (roofing)',
						'NEVER cite competitor contractors',
						'Reference building codes by code section (e.g. "IRC 2021 Section R602.10") not generic links',
					),
				),
			),
		);
	}

	private static function overlay_local_business() {
		return array(
			'top_rules' => array(
				'Local intent dominates. Pages must own the [business-noun] + [neighborhood] keyword. Google Business Profile parity matters as much as on-site SEO.',
				'Schema: use the most specific LocalBusiness subtype (Restaurant, BeautySalon, HealthClub, etc.) + Menu / Service / Offer + openingHoursSpecification + hasMap.',
				'Photography density beats text on local discovery pages. Original photos (not stock) of the actual location are a documented engagement signal.',
				'NAP consistency: Name + Address + Phone identical across the site, Google Business Profile, Apple Maps, Bing Places.',
			),
			'sections' => array(
				array(
					'name' => 'Local Business E-E-A-T',
					'why_matters' => 'For local businesses, authority is built through verifiable presence and consistency rather than expert credentials. NAP consistency + GBP photos + first-party content beat thin third-party citations.',
					'rules' => array(
						'Owner / chef / lead-stylist bio with photo + experience, when relevant',
						'Original photography of the actual location, staff, and product (no stock images for the hero)',
						'NAP block in the footer, identical across pages',
						'Google Business Profile category should match the on-site LocalBusiness subtype',
					),
				),
				array(
					'name' => 'Local Business schema markup',
					'why_matters' => 'The Local Pack and Knowledge Panel are populated from your LocalBusiness schema + GBP. Specific subtypes give you more carousel slots and structured-data eligibility.',
					'rules' => array(
						'Most specific LocalBusiness subtype: Restaurant, CafeOrCoffeeShop, Bakery, BeautySalon, HairSalon, DaySpa, HealthClub, SportsActivityLocation, Store',
						'Menu schema (Restaurant) or Service / Offer per service or product line',
						'openingHoursSpecification, telephone, address (PostalAddress), hasMap',
						'AggregateRating ONLY from third-party reviews referenced explicitly, never inlined as if first-party',
					),
				),
				array(
					'name' => 'Local Business authority + content',
					'why_matters' => 'Local pages rank through GBP + on-site relevance + reviews. Off-site authority signals (city tourism boards, local press, chambers of commerce) outweigh general industry citations.',
					'rules' => array(
						'Cite local press, city tourism board, chamber of commerce, Visit [City] sites',
						'Photos beat paragraphs on category / menu / room / class-schedule pages',
						'Reviews on third-party platforms (Google, Yelp, TripAdvisor) drive ranking more than on-site copy',
					),
				),
			),
		);
	}

	private static function overlay_professional_services() {
		return array(
			'top_rules' => array(
				'B2B intent. Pages should own the [service noun] + [buyer-segment OR niche] modifier, not a location.',
				'Schema: ProfessionalService + Person for principals + Service per offering + Article for thought leadership.',
				'Authority comes from named principals, case studies with metrics, and original research. "About" pages with photos and credentials outrank logo-only pages.',
				'Citation hosts vary by buyer segment — match the audience\'s trusted sources (HBR for executive, Gartner/Forrester for IT buyers, etc.).',
			),
			'sections' => array(
				array(
					'name' => 'Professional Services E-E-A-T',
					'why_matters' => 'B2B buyers verify before they convert. Named principals with verifiable backgrounds, case studies with numbers, and content with original analysis outrank anonymous agency content by a wide margin.',
					'rules' => array(
						'Principal / partner bios with photo + LinkedIn + experience + named clients (where permitted)',
						'Case studies with quantified outcomes ("reduced cycle time 38%", not "improved efficiency")',
						'Schema Person.sameAs should include LinkedIn at minimum, ideally professional association membership',
						'Author byline on every blog post; topic-expert byline outranks generic "Team [Brand]"',
					),
				),
				array(
					'name' => 'Professional Services schema markup',
					'why_matters' => 'ProfessionalService is the entity Google uses to model agencies / consultancies. Pair with Service per offering and Person per principal so the page has a complete entity graph.',
					'rules' => array(
						'ProfessionalService on the homepage (or LocalBusiness if geo matters)',
						'Service schema per offering with serviceType + provider',
						'Person schema per principal with jobTitle + hasCredential where applicable',
						'Article schema on thought-leadership posts with headline + author + datePublished',
					),
				),
			),
		);
	}

	private static function overlay_ecommerce() {
		return array(
			'top_rules' => array(
				'Commercial intent. Product pages must own the [product/category] + [modifier] keyword combinations from your real GSC query distribution.',
				'Schema: Product + Offer + AggregateRating (from verified review system) + BreadcrumbList. Review schema requires the genuine reviews collection layer — not synthesized.',
				'Image SEO is disproportionately important. Multiple original photos, descriptive alt text, structured Image markup, and OG image per product.',
				'Avoid duplicate content across colorways, sizes, regional variants. Use canonical or noindex variants; never let them split ranking signal.',
			),
			'sections' => array(
				array(
					'name' => 'E-commerce E-E-A-T',
					'why_matters' => 'Product page authority is built through verified reviews, detailed specs, expert buying guides, and clear returns / shipping / sourcing transparency. Trust signals close the conversion as much as they earn the click.',
					'rules' => array(
						'Verified-purchase review system (third-party preferred for trust)',
						'Detailed specs: dimensions, materials, sourcing, certifications',
						'Visible returns + shipping + warranty policy on every product page',
						'Buying-guide content authored by named expert with relevant background',
					),
				),
				array(
					'name' => 'E-commerce schema markup',
					'why_matters' => 'Product, Offer, AggregateRating, and Review schema drive rich-result eligibility AND AI Overview product citations.',
					'rules' => array(
						'Product per product page with name, sku, mpn, brand, image, description',
						'Offer with price, priceCurrency, availability, priceValidUntil, shippingDetails',
						'AggregateRating + Review from genuine review collection layer only — synthesized or self-generated reviews are a policy violation',
						'BreadcrumbList for category navigation',
						'Organization on homepage with logo + sameAs to verified social profiles',
					),
				),
				array(
					'name' => 'E-commerce duplicate-content discipline',
					'why_matters' => 'Color / size / region variants are the main source of self-cannibalization on ecommerce sites. Decide explicitly: canonical to a single SKU page, or noindex the variants, never let them compete.',
					'rules' => array(
						'Canonical to the primary SKU when variants are minor (color)',
						'Distinct indexed pages only when intent differs (size category for B2B vs consumer)',
						'Use hreflang for true regional variants, not duplicated content',
						'Run find_duplicate_content across SKU pages before launching new categories',
					),
				),
			),
		);
	}

	private static function overlay_saas() {
		return array(
			'top_rules' => array(
				'Buyer-journey intent. Pages map to a stage: top-of-funnel education, middle-of-funnel comparison, bottom-of-funnel pricing / demo / sign-up. Don\'t blur stages on one page.',
				'Schema: SoftwareApplication + Product / Offer for paid tiers + FAQPage + Article for blog.',
				'Authority comes from named team (especially engineering / product leads), customer logos with real case studies, and original benchmark / research content.',
				'Comparison pages ("X vs Y") drive disproportionate revenue. Build them honest and structured; Google rewards balanced comparisons over puff pieces.',
			),
			'sections' => array(
				array(
					'name' => 'SaaS E-E-A-T',
					'why_matters' => 'SaaS buyers verify through team pages, technical docs, customer logos, and third-party G2 / Capterra signals. Anonymous SaaS sites rank poorly because Google can\'t map them to verified product entities.',
					'rules' => array(
						'Team page with named principals (CEO, CTO, head of product) + LinkedIn',
						'Customer logos paired with case-study links — not just a logo wall',
						'Documentation / changelog / status page link visible in footer',
						'Author byline on every blog post, ideally engineer / product person, not generic marketer',
					),
				),
				array(
					'name' => 'SaaS schema markup',
					'why_matters' => 'SoftwareApplication is the Google entity for software products. Pair with Product / Offer for tiers and FAQPage for the on-page accordion.',
					'rules' => array(
						'SoftwareApplication on the homepage with name, applicationCategory, operatingSystem, offers',
						'Product + Offer per pricing tier with price, priceCurrency, billingDuration',
						'AggregateRating from genuine review platforms (G2, Capterra) only — never self-generated',
						'Article schema on blog posts with named author + datePublished',
					),
				),
				array(
					'name' => 'SaaS comparison-page discipline',
					'why_matters' => 'Comparison pages are the highest-converting SaaS content type. Google rewards honest, structured comparisons and penalizes puff pieces that read as competitor disparagement.',
					'rules' => array(
						'Side-by-side feature table with actual capability checkmarks, not marketing claims',
						'Pricing comparison with the competitor\'s actual published pricing (not "starts at $X")',
						'Acknowledge competitor strengths honestly; Google\'s helpful-content signal punishes one-sided comparisons',
						'Avoid duplicate "vs" pages with overlapping intent — one page per real comparison',
					),
				),
			),
		);
	}

	private static function overlay_publisher() {
		return array(
			'top_rules' => array(
				'Authored, dated, sourced content. Anonymous publisher content is filtered hard by the Helpful Content System.',
				'Schema: NewsArticle / Article + author Person + publisher Organization + datePublished + dateModified.',
				'Original reporting + first-hand experience matter more than coverage of trending topics. Information gain (Glenn Gabe) is measurable; covering the same story without new data is filtered.',
				'Author entity strength (sameAs to journalism platforms, awards, Wikipedia) is a measurable ranking signal per the leak.',
			),
			'sections' => array(
				array(
					'name' => 'Publisher E-E-A-T',
					'why_matters' => 'Editorial sites earn ranking through the authorReputationScore + isAuthor signals (leak-confirmed). Bylines must be real, biographies must be substantive, and credentials must be verifiable.',
					'rules' => array(
						'Bylines on every article, never "Staff" or "Editorial Board" for non-news content',
						'Author bio page per byline with photo, experience, awards, sameAs links',
						'Editorial guidelines / standards page accessible from footer (Trust Project pattern)',
						'Corrections policy + retraction history when applicable',
					),
				),
				array(
					'name' => 'Publisher schema markup',
					'why_matters' => 'NewsArticle and Article schema are how Google maps stories to byline + publisher entities. Without it, the entity graph is incomplete and the story under-ranks.',
					'rules' => array(
						'NewsArticle for news pieces; Article for evergreen content',
						'author = Person with name + url + sameAs',
						'publisher = Organization with name + logo (ImageObject with width >= 600)',
						'datePublished + dateModified (genuine; hollow date bumps are detectable)',
						'Image with width >= 1200 for AMP and Top Stories eligibility',
					),
				),
			),
		);
	}

	private static function overlay_general() {
		return array(
			'top_rules' => array(
				'Industry override not set — running with universal rules only. To get vertical-specific guidance (medical citation hosts, legal bar-number patterns, etc.), set the Industry in Settings or let auto-detection populate it from your schema and content.',
			),
			'sections' => array(
				array(
					'name' => 'General (no industry overlay active)',
					'why_matters' => 'No industry overlay is matched for this site. The model is running on the universal core only. This is safe but leaves vertical-specific best practices on the table.',
					'rules' => array(
						'Set the industry from the Settings page to unlock vertical-specific citation hosts, schema types, and credential patterns.',
						'Auto-detection will populate it from your schema types and content corpus on next refresh.',
					),
				),
			),
		);
	}
}
