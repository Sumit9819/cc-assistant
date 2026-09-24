<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** One shared contract feeds the capability index and current tool guidance. */
class CC_Assistant_Capabilities {
    public static function manifest() {
        $contract = require CC_ASSISTANT_DIR . 'bin/agent-contract.php';
        $manifest = $contract['manifest'];
        $manifest['version'] = defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : '';
        $manifest['contract_version'] = $contract['version'];
        $manifest['evidence_rules'] = $contract['evidence_rules'];
        return $manifest;
    }
}
