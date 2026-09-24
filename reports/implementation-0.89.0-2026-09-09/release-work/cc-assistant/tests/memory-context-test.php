<?php
require __DIR__ . '/content-scope-test.php';
$GLOBALS['options']['cc_assistant_site_notes'] = "# Rules\nNever invent services.\n# Decisions\nEnglish first.\n# Sessions\nFirst check.";
$before = CC_Assistant_Content_Scope::context();
$GLOBALS['options']['cc_assistant_site_notes'] .= "\n[2026-09-09] Routine verification completed.";
check( 'routine sessions do not invalidate strategy context', $before['context_hash'] === CC_Assistant_Content_Scope::context()['context_hash'] );
$GLOBALS['options']['cc_assistant_site_notes'] = str_replace( 'Never invent services.', 'Never invent services. No provider bylines without consent.', $GLOBALS['options']['cc_assistant_site_notes'] );
check( 'new durable constraints invalidate context', $before['context_hash'] !== CC_Assistant_Content_Scope::context()['context_hash'] );
$GLOBALS['options']['cc_assistant_site_notes'] = 'Legacy constraint'; $before = CC_Assistant_Content_Scope::context();
$GLOBALS['options']['cc_assistant_site_notes'] .= '. New correction';
check( 'unstructured legacy constraints remain freshness inputs', $before['context_hash'] !== CC_Assistant_Content_Scope::context()['context_hash'] );
$notes = "# Rules\nCTR leak is an AI-absorption signal.\nUse citation density.\n# Decisions\nKeep no-bylines consent rule.\n# Sessions\nHistoric robustness score 95.";
$audit = CC_Assistant_Memory_Policy::audit( $notes );
check( 'conflicts identify durable source sections and remain review prompts', array_column( $audit['findings'], 'rule_id' ) === array( 'ai_ctr_attribution', 'fixed_citation_quota' ) && 'review' === $audit['findings'][0]['status'] );
check( 'memory audit preserves the exact original fingerprint', hash( 'sha256', $notes ) === $audit['notes_sha256'] );
foreach ( array( 'Career', 'HIPAA Privacy Notice', 'Insurance and Billing', 'Accessibility Statement', 'About Us Emergency Room', 'Medical Disclaimer', 'Billing Disclosures', 'Sobre Nosotros', 'Facturación' ) as $i => $title ) { post_fixture( 800 + $i, $title ); }
post_fixture( 850, 'Contact dermatitis treatment' ); post_fixture( 851, 'Emergency imaging' );
$discovery = CC_Assistant_Content_Scope::discover( array( 'scan_limit' => 500 ) );
$found = array_column( $discovery['candidates'], 'post_id' );
check( 'branded legal and translated utility pages are not service anchors', empty( array_intersect( range( 800,808 ), $found ) ) );
check( 'actual services are retained despite similar utility vocabulary', in_array( 850, $found, true ) && in_array( 851, $found, true ) );
echo "All memory context regressions passed.\n";
