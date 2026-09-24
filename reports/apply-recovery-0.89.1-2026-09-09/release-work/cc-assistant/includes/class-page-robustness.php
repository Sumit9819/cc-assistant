<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Compatibility endpoint. Retired uncalibrated conversion and citation quotas. */
class CC_Assistant_Page_Robustness {
    public static function audit( $post_id, $strict = true ) {
        require_once __DIR__ . '/class-verified-page-audit.php';
        $report = CC_Assistant_Verified_Page_Audit::run( (int) $post_id );
        if ( is_wp_error( $report ) ) { return $report; }
        $report['post_id'] = (int) $post_id;
        $report['score'] = null;
        $report['pass'] = null;
        $report['verdict'] = $report['assessment'];
        $report['blocking'] = array();
        $report['compatibility_note'] = 'The old robustness score, CTA prescriptions and citation/word-count quotas are retired. This endpoint now uses the fresh verified-page evidence engine. Review each finding; partial checks never certify overall SEO or content quality. The strict argument is retained for compatibility and does not invent blocking editorial rules.';
        return $report;
    }
}
