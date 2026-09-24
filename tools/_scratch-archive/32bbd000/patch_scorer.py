import io, os, sys, shutil

BASE = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant"
F_TOOLS = os.path.join(BASE, "includes", "class-seo-tools.php")

def read(p):
    return io.open(p, encoding="utf-8", newline="").read()

def write(p, s):
    io.open(p, "w", encoding="utf-8", newline="").write(s)

applied, failed = [], []

def sub(text, old, new, label, count=1):
    global applied, failed
    n = text.count(old)
    if n != count:
        failed.append(f"{label}: expected {count} occurrence(s), found {n}")
        return text
    applied.append(label)
    return text.replace(old, new, count)

t = read(F_TOOLS)
shutil.copy2(F_TOOLS, F_TOOLS + ".bak")

# ---------------------------------------------------------------- BUG 1: scope
old1 = """		$fetched = self::fetch_rendered_html_for_audit( $permalink );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		$html      = (string) $fetched['body'];
		$body_text = wp_strip_all_tags( $html );
		$word_count = max( 1, str_word_count( $body_text ) );

		$breakdown = array();
		$issues    = array();"""
new1 = """		$fetched = self::fetch_rendered_html_for_audit( $permalink );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		// $html_full keeps the WHOLE document because JSON-LD (Rank Math and
		// friends) is emitted in <head>, outside any content container — the
		// E-E-A-T bucket still has to see it.
		//
		// Everything that scores WRITING, however, must see only the post's own
		// body. Scoring the full page charged every post for site-wide chrome:
		// on sids-ponds the header promo bar ("...on orders $100 or more!") and
		// the popup ("Shop Now!" x2) produced an identical 3-exclamation penalty
		// on /outdoor-lighting-2/, /contact/ AND /delivery/, which no edit to any
		// page could ever clear. It also dragged site_quality_score to a false
		// verdict=fail. Diagnosed 2026-08-20.
		$html_full = (string) $fetched['body'];
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$html = method_exists( 'CC_Assistant_Pre_Publish', 'isolate_main_content' )
			? CC_Assistant_Pre_Publish::isolate_main_content( $html_full )
			: $html_full;
		// Never score an empty body: if isolation finds no usable region the page
		// would otherwise read as 0 words and be flagged thin.
		if ( '' === trim( wp_strip_all_tags( (string) $html ) ) ) {
			$html = $html_full;
		}
		$body_text = wp_strip_all_tags( $html );
		$word_count = max( 1, str_word_count( $body_text ) );

		$breakdown = array();
		$issues    = array();"""
t = sub(t, old1, new1, "BUG1 scope helpful_content_score to main content")

# ------------------------------------------------- BUG 1b: schema needs full doc
old1b = """		if ( preg_match_all( '#<script[^>]+type=["\\']application/ld\\+json["\\'][^>]*>([\\s\\S]*?)</script>#i', $html, $jm2 ) ) {"""
new1b = """		// $html_full, not $html: JSON-LD lives in <head>, which isolate_main_content
		// deliberately drops. Using the isolated body here would zero out every
		// schema-derived E-E-A-T signal.
		if ( preg_match_all( '#<script[^>]+type=["\\']application/ld\\+json["\\'][^>]*>([\\s\\S]*?)</script>#i', $html_full, $jm2 ) ) {"""
t = sub(t, old1b, new1b, "BUG1b JSON-LD reads full document")

# ------------------------------------------- BUG 2: question headings incl. H3
old2 = """		$q_h2 = preg_match_all( '/<h2\\b[^>]*>([\\s\\S]*?)<\\/h2>/i', $html, $h2m ) ? $h2m[1] : array();
		$question_h2 = 0;
		foreach ( $q_h2 as $h ) {
			if ( false !== strpos( trim( wp_strip_all_tags( $h ) ), '?' ) ) {
				$question_h2++;
			}
		}"""
new2 = """		$q_h2 = preg_match_all( '/<h2\\b[^>]*>([\\s\\S]*?)<\\/h2>/i', $html, $h2m ) ? $h2m[1] : array();
		$q_h3 = preg_match_all( '/<h3\\b[^>]*>([\\s\\S]*?)<\\/h3>/i', $html, $h3m ) ? $h3m[1] : array();
		$question_h2 = 0;
		// Count question-shaped headings at H2 *and* H3. The FAQ-card pattern this
		// plugin itself builds — and Divi/Elementor accordion FAQs generally — put
		// each question in an H3 beneath a single "FAQs" H2, so an H2-only count
		// reported question_h2s = 0 on pages carrying seven real FAQ questions.
		foreach ( array_merge( $q_h2, $q_h3 ) as $h ) {
			if ( false !== strpos( trim( wp_strip_all_tags( $h ) ), '?' ) ) {
				$question_h2++;
			}
		}"""
t = sub(t, old2, new2, "BUG2 count H3 FAQ questions")

# -------------------------------------------------- BUG 3: 'landscape' AI-tell
old3 = """			'/\\b(delve into|navigating the complexities|in today.?s fast.?paced|landscape|leverage(?: the| our)? expertise|elevat(?:e|ing) your|unlock the (?:power|secrets|potential)|tapestry of)\\b/i',"""
new3 = """			// "landscape" was previously a BARE word here, so every ordinary use of
			// it counted as an AI-tell. On a landscape-supply company that is the
			// core noun of the business: /outdoor-lighting-2/ was penalised 4 times
			// for "landscape lighting" (a product category) and the footer tagline
			// "Pond, garden, and landscape supply". Only the filler constructions
			// are tells. Fixed 2026-08-20.
			'/\\b(delve into|navigating the complexities|in today.?s fast.?paced|(?:digital|evolving|changing|shifting|competitive|ever.?changing|modern) landscape|leverage(?: the| our)? expertise|elevat(?:e|ing) your|unlock the (?:power|secrets|potential)|tapestry of)\\b/i',"""
t = sub(t, old3, new3, "BUG3 'landscape' no longer a bare AI-tell")

# ------------------------------------ BUG 4: authority hosts are US-only + no retail
old4 = """		$hosts = array( '.gov', '.edu', 'who.int' );

		$overlays = array("""
new4 = """		// `.gov` and `.edu` are US-only TLDs. A Canadian, UK, Australian or EU
		// tenant citing its own government, health service or university scored
		// zero citation density no matter how well sourced the page was. Found on
		// sids-ponds (Mississauga, Ontario) 2026-08-20, which cites mississauga.ca
		// and ontario.ca and reported citation_density 0.00 on every page.
		$hosts = array(
			'.gov', '.edu', 'who.int',
			// Canada
			'.gc.ca', 'canada.ca', 'ontario.ca', 'alberta.ca', 'quebec.ca',
			'gov.bc.ca', 'gov.mb.ca', 'novascotia.ca', 'saskatchewan.ca',
			// UK / IE
			'.gov.uk', '.nhs.uk', '.ac.uk', '.gov.ie',
			// AU / NZ
			'.gov.au', '.edu.au', '.govt.nz', '.ac.nz',
			// EU
			'europa.eu',
		);

		$overlays = array("""
t = sub(t, old4, new4, "BUG4a international government/academic authorities")

old4b = """			'home_services' => array("""
new4b = """			// Retail / product sites cite standards bodies, safety regulators and
			// consumer-protection agencies rather than medical journals. Without
			// this overlay an ecommerce tenant fell back to the .gov/.edu
			// catchalls alone and could never register citation density.
			'ecommerce' => array(
				'energystar.gov', 'epa.gov', 'ftc.gov', 'cpsc.gov', 'nist.gov',
				'astm.org', 'ansi.org', 'iso.org', 'ul.com', 'csagroup.org',
				'nrcan.gc.ca', 'competitionbureau.gc.ca', 'ccohs.ca',
				'consumerreports.org',
			),
			'home_services' => array("""
t = sub(t, old4b, new4b, "BUG4b ecommerce authority overlay")

write(F_TOOLS, t)

print("APPLIED:")
for a in applied:
    print("  [ok]  " + a)
if failed:
    print("\nFAILED (file still written; these anchors did not match):")
    for f in failed:
        print("  [!!]  " + f)
    sys.exit(1)
print("\nbackup: " + F_TOOLS + ".bak")
