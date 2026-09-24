<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Short and full SEO guidance share the same source as the agent contract. */
class CC_Assistant_SEO_Policy {
    const VERSION = '2026.09.09.3';
    public static function sections() {
        $contract = require CC_ASSISTANT_DIR . 'bin/agent-contract.php';
        return $contract['seo_sections'];
    }
    public static function rules() {
        $rules = array();
        foreach ( self::sections() as $section ) { $rules = array_merge( $rules, $section['rules'] ); }
        return $rules;
    }
}
