<?php
require __DIR__.'/cc-assistant/tests/fixtures/safety-harness.php';
require __DIR__.'/legacy-integrity.php';
foreach(array('', 'Escaped "quote" and \\path', 'Español: información cardíaca') as $text) {
    reset_review(); $GLOBALS['post']->post_title=$text;
    $GLOBALS['meta']=array('z'=>array('last'), 'a'=>array('a:1:{s:3:"key";s:5:"value";}'), '_elementor_data'=>array('{"title":"\\u00e1"}'), '_edit_lock'=>array('123'), '_cc_assistant_last_internal_hash'=>array('ignored'));
    $GLOBALS['terms']=array('post_tag'=>array(4,2), 'category'=>array(10,1));
    check(CC_Assistant_Integrity::post_hash(1)===CC_Assistant_Legacy_Integrity::post_hash(1),'Fingerprint remains byte-compatible with released 0.89.3');
}
echo "Fingerprint compatibility checks passed: $tests\n";
