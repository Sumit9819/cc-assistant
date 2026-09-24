import json
from pathlib import Path
root=Path(__file__).parent
policy=(root/'site-policy.txt').read_text(encoding='utf-8')
calls=[{'name':'whoami','arguments':{}},{'name':'get_site_memory','arguments':{}},
       {'name':'update_site_memory_notes','arguments':{'notes':policy,'section':'rules','mode':'append'}},
       {'name':'get_site_memory','arguments':{}},{'name':'get_content_scope','arguments':{}},
       {'name':'discover_content_scope','arguments':{'scan_limit':500}},
       {'name':'verified_page_audit','arguments':{'post_id':971}},
       {'name':'get_post','arguments':{'id':971,'slim':False}}]
source='Current stored copy promises flawless results or guaranteed immediate treatment. MedlinePlus explains that lab results require clinical context and no test is perfect: https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/ . Remove the unsupported promise, preserve the existing widget and layout. Facility-specific turnaround and accreditation claims require separate confirmation. Review-only proposal; no publication or live apply.'
edits=[
 ('1ffbaf62', {'editor':'<p>Laboratory results help your care team assess what may be causing your symptoms. A result is one part of an evaluation, alongside your health history and examination.</p><ul><li><strong>Understand the test:</strong> Ask which tests are being ordered and what questions they may help answer.</li><li><strong>Discuss the results:</strong> A result outside the reference range does not always mean you have a medical condition. A result within the range does not rule out every problem.</li><li><strong>Ask about next steps:</strong> Find out whether more testing or follow-up may be needed.</li></ul><p>See <a href="https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/">MedlinePlus guidance on understanding lab results</a>.</p>'}, 'Replace flawless-results and fixed-turnaround promises with a practical explanation of lab-result limitations.'),
 ('46afe405', {'title_text_a':'Understanding Your Results','title_text_b':'Understanding Your Results'}, 'Remove the unsupported Fast & Flawless Results claim from both faces of this card.'),
 ('77684669', {'description_text_b':'Your physician considers laboratory results alongside your symptoms, medical history and examination. Ask what the findings mean and whether you need follow-up.'}, 'Remove guaranteed immediate treatment and instant-review language.'),
 ('2a6ed6d9', {'description_text_b':'Laboratory tests can provide information that helps your care team evaluate symptoms. Some findings may require additional tests or follow-up.'}, 'Remove the claim that equipment instantly diagnoses severe conditions.')]
for widget,settings,summary in edits:
    calls.append({'name':'draft_update_elementor_widget','arguments':{'post_id':971,'widget_id':widget,'settings':settings,'summary':summary,'reasoning':source}})
calls.append({'name':'list_pending_changes','arguments':{}})
(root/'live-changes.json').write_text(json.dumps(calls,indent=2),encoding='utf-8')
print('Prepared durable preferences and four exact widget proposals; no layout changes.')
