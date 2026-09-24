import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('runner', Path(__file__).resolve().parents[1] / 'bin/automation-runner.py')
r = importlib.util.module_from_spec(spec)
spec.loader.exec_module(r)
workflow = 'workflow-' + 'a' * 32


def events(name, arguments, value, ok=True):
    import json
    return [{'message': {'content': [{'type': 'tool_use', 'id': 'call1', 'name': 'mcp__wordpress__' + name, 'input': arguments}]}},
            {'message': {'content': [{'type': 'tool_result', 'tool_use_id': 'call1', 'content': json.dumps(value)}]}},
            {'type': 'result', 'is_error': not ok, 'result': 'Claimed complete.', 'total_cost_usd': .1}]


class RunnerTests(unittest.TestCase):
    def test_prose_cannot_establish_execution(self):
        j = r.advance({}, 'draft', [{'type': 'result', 'result': 'Post 99 saved and verified.'}], 0)
        self.assertEqual(j['phase'], 'reconcile')

    def test_lost_response_never_retries_creation(self):
        j = r.advance({}, 'draft', [], -1)
        self.assertEqual(j['phase'], 'reconcile')

    def test_returned_draft_survives_later_timeout(self):
        j = r.advance({}, 'draft', events('draft_create_post', {'workflow_id': workflow}, {'post_id': 99})[:-1], -1)
        self.assertEqual((j['phase'], j['post_id']), ('verify', 99))

    def test_dry_run_not_a_draft(self):
        j = r.advance({}, 'draft', events('draft_create_post', {'workflow_id': workflow, 'dry_run': True}, {'post_id': 99}), 0)
        self.assertEqual(j['phase'], 'reconcile')

    def test_wrong_post_and_published_records_fail(self):
        for pid, status in [(100, 'draft'), (99, 'publish')]:
            j = {'post_id': 99, 'workflow_id': workflow}
            r.advance(j, 'verify', events('verify_content_workflow', {}, {'record_integrity_pass': True, 'workflow_id': workflow, 'result': {'post_id': pid, 'post_status': status, 'pending_status': 'pending'}}), 0)
            self.assertEqual(j['phase'], 'needs_review')

    def test_actual_bound_draft_is_review_ready(self):
        j = {'post_id': 99, 'workflow_id': workflow}
        r.advance(j, 'verify', events('verify_content_workflow', {}, {'record_integrity_pass': True, 'workflow_id': workflow, 'result': {'post_id': 99, 'post_status': 'draft', 'pending_status': 'pending'}}), 0)
        self.assertEqual(j['phase'], 'review_ready')

    def test_scope_and_tool_limits(self):
        forbidden = {'approve_change', 'reject_pending_change', 'draft_trash_post', 'draft_update_post_meta', 'draft_update_plugin_setting'}
        self.assertFalse(forbidden.intersection(r.READ + r.RESEARCH + r.DRAFT))
        self.assertFalse({'create_project', 'generate_article', 'site_audit'}.intersection(r.PROVIDER))


if __name__ == '__main__':
    unittest.main()
