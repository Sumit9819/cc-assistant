import json,copy
from pathlib import Path
root=Path(__file__).resolve().parent
plans=json.loads((root/'remaining-proposals.json').read_text(encoding='utf-8'))[:2]
requests=[{'name':'whoami','arguments':{}}, {'name':'widget_schema','arguments':{'post_id':4667,'widget_id':'a440026'}}]
for plan in plans:
    pid=plan['arguments']['post_id']
    requests.append({'name':'get_post','arguments':{'id':pid,'slim':False}})
    for row in plan['arguments']['widget_updates']:
        settings=copy.deepcopy(row['settings'])
        if row['widget_id']=='510c896':
            # Keep the existing inactive custom_text unchanged. Only author type is rendered.
            settings['icon_list'][0]['custom_text']='Admin'
        requests.append({'name':'draft_update_elementor_widget','arguments':{'post_id':pid,'widget_id':row['widget_id'],'settings':settings,'dry_run':True,'summary':'Validate supported template correction'}})
requests += [{'name':'get_post','arguments':{'id':2931,'slim':False}}, {'name':'verified_page_audit','arguments':{'post_id':2931}}, {'name':'draft_update_seo_meta','arguments':{'post_id':2931,'logical_key':'canonical','value':'','summary':'WR-07: clear the incorrect blog canonical override','reasoning':'Fresh browser requests to /blog/, /blog/2/ and /blog/3/ all declare /blog/2/. Remove the explicit override so the SEO plugin can generate the appropriate URL; verify each pagination URL after applying.'}}, {'name':'list_pending_changes','arguments':{}}]
(root/'template-checks.json').write_text(json.dumps(requests,ensure_ascii=False,indent=2),encoding='utf-8')
print('Prepared',len(requests),'requests')
