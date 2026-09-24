<?php
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
require CC_ASSISTANT_DIR . 'includes/class-capabilities.php';
require CC_ASSISTANT_DIR . 'includes/class-seo-policy.php';
$contract = require CC_ASSISTANT_DIR . 'bin/agent-contract.php';
$tools = require CC_ASSISTANT_DIR . 'bin/tool-catalog.php';
function check( $s, $v ) { if ( ! $v ) { throw new RuntimeException( $s ); } echo "PASS $s\n"; }
check( 'manifest contract version agrees with SEO policy', CC_Assistant_SEO_Policy::VERSION === $contract['version'] );
check( 'full and short policy use canonical sections', CC_Assistant_SEO_Policy::sections() === $contract['seo_sections'] );
check( 'capability rules cannot drift from canonical instructions', CC_Assistant_Capabilities::manifest()['evidence_rules'] === $contract['evidence_rules'] );
check( 'tool names are unique', count( $tools ) === count( array_unique( array_column( $tools, 'name' ) ) ) );
foreach ( $tools as $tool ) {
    check( 'shared description exists: ' . $tool['name'], ! empty( $contract['tool_descriptions'][$tool['name']] ) );
    $schema = json_decode( json_encode( $tool['inputSchema'] ) );
    check( 'MCP properties is an object: ' . $tool['name'], ! isset( $schema->properties ) || is_object( $schema->properties ) );
}
echo "All agent contract checks passed.\n";
