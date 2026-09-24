<?php
/** Compatibility index; schemas and instructions are owned by the shared catalog/contract. */
$cc_content_catalog = require __DIR__ . '/tool-catalog.php';
$cc_content_contract = require __DIR__ . '/agent-contract.php';
$cc_names = array( 'get_content_authors', 'record_external_research', 'get_content_scope', 'discover_content_scope', 'manage_content_scope', 'plan_blog_content', 'content_research', 'content_decision', 'content_decision_history', 'content_workflow', 'verify_content_workflow', 'inspect_plugin_capability' );
$cc_selected = array();
foreach ( $cc_content_catalog as $cc_entry ) {
    if ( in_array( $cc_entry['name'], $cc_names, true ) ) {
        $cc_entry['description'] = $cc_content_contract['tool_descriptions'][$cc_entry['name']];
        $cc_selected[] = $cc_entry;
    }
}
return array( 'tools' => $cc_selected, 'descriptions' => $cc_content_contract['tool_descriptions'] );
