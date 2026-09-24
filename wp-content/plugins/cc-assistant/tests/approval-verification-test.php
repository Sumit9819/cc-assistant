<?php
class CC_Assistant_Site_Identity { public static function fingerprint() { return 'test-site'; } }
class WP_REST_Request {
    public function __construct(private $id) {}
    public function get_param($key) { return $key==='id' ? $this->id : null; }
}
require __DIR__.'/approval-batch-test.php';
require_once CC_ASSISTANT_DIR.'includes/class-rest-pending.php';
function evidence_result($id) { return CC_Assistant_REST_Pending::handle_verify(new WP_REST_Request($id))['data']['evidence_check']; }
batch_fixture(); $a=batch_pending('first'); $b=batch_pending('second');
check(evidence_result($b)['status']==='current','Verification distinguishes current evidence from lint');
CC_Assistant_Apply::apply_pending($a,17,true);
$r=evidence_result($b);
check($r['status']==='compatible_approved_changes' && $r['approved_sibling_ids']===array($a),'Verification names the approved change that explains the new state');
$GLOBALS['meta']['external']=array('Unexplained mutation');
$r=evidence_result($b);
check($r['status']==='blocked' && $r['code']==='evidence_state_changed','Passing lint does not conceal stale evidence');
check(evidence_result($a)['status']==='not_pending','An already approved change is not represented as ready to apply');
batch_fixture(); $a=batch_pending('first');
$GLOBALS['wpdb']->update('wp_cc_pending_changes',array('superseded_by'=>99),array('id'=>$a));
check(err(CC_Assistant_Apply::apply_pending($a,17,true),'proposal_superseded'),'A superseded proposal cannot regain approval through the continuation path');
echo "Verification regression checks passed: $tests\n";
