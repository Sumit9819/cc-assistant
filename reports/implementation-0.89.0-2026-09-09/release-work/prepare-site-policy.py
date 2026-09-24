import hashlib,json
from pathlib import Path
root=Path(__file__).parent
report=Path('D:/cc-assistant/reports/preflight-0.89.0-2026-09-09')
report.mkdir(parents=True,exist_ok=True)
policy='''Current operator preferences (2026-09-09): Preserve useful existing site layouts. New posts and pages should be practical, search-led and SEO-optimized, without magazine/editorial-style layouts or opinion essays. Use the existing ER of White Rock organization author (observed user ID 4), not adminsumit. Read current authors before selection; never invent clinician attribution or medical-review credentials. After installing 0.89, Claude should save this author through manage_content_scope using the exact current display name. Use connected Ubersuggest for scoped keyword/SERP/competitor research; preserve tool name, geography, language and provider update dates. Its estimates are not GSC observations. Zero or absent volume does not rule out useful niche topics; overlap domains are candidates, not automatically local business competitors. Compare actual pages and provide a supported reader benefit. No automatic publication.'''
(root/'site-policy.txt').write_text(policy,encoding='utf-8')
changes=[]
for project in ['d--cc-assistant','c--Users-sumit-Local-Sites-plugintesting-app-public']:
    f=Path('C:/Users/sumit/.claude/projects')/project/'memory/feedback_erofwhiterock_current_evidence_policy.md'
    if not f.exists():continue
    old=f.read_bytes();s=old.decode('utf-8')
    if policy not in s:s=s.replace('\n\n','\n\n'+policy+'\n\n',1)
    (report/(project+'-evidence-policy.before.md')).write_bytes(old)
    f.write_text(s,encoding='utf-8')
    changes.append({'path':str(f),'before':hashlib.sha256(old).hexdigest(),'after':hashlib.sha256(f.read_bytes()).hexdigest()})
for project in [Path('D:/cc-assistant'),Path('C:/Users/sumit/Local Sites/plugintesting/app/public')]:
    f=project/'.claude/skills/erofwhiterock-design/SKILL.md'
    if not f.exists():continue
    old=f.read_bytes();s=old.decode('utf-8')
    if policy not in s:s=s.replace('## TL;DR', '## Current operator direction\n\n'+policy+'\n\n## TL;DR',1)
    s=s.replace('**Numbers + named sources** beat opinion every time. Cite CDC, AHA, ASA, ACEP, Texas DSHS by name.','**Support consequential claims with suitable current sources.** Use numbers only when they help the reader; a citation count does not establish accuracy or ranking quality.')
    s=s.replace('**Triage-decisioning H2s** outperform service descriptions. "When is this an ER visit?" > "Our Services."','**Use headings that answer the actual reader task.** Question headings can help navigation; this is a writing choice, not a demonstrated ranking advantage.')
    key='d-project' if project.drive.lower()=='d:' else 'c-project'
    (report/(key+'-design.before.md')).write_bytes(old)
    f.write_text(s,encoding='utf-8')
    changes.append({'path':str(f),'before':hashlib.sha256(old).hexdigest(),'after':hashlib.sha256(f.read_bytes()).hexdigest()})
(report/'site-policy-changes.json').write_text(json.dumps(changes,indent=2),encoding='utf-8')
print(json.dumps({'reviewed_files':len(changes),'backup_directory':str(report)}))
