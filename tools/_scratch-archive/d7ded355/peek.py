import json, sys, glob, os

# Peek at the most recent tool-result file, or a named one
if len(sys.argv) > 1 and os.path.exists(sys.argv[1]):
    path = sys.argv[1]
else:
    files = glob.glob(r'C:/Users/sumit/.claude/projects/c--Users-sumit-Local-Sites-plugintesting-app-public/d7ded355-4484-4b38-ad40-0b9a84e31979/tool-results/*.txt')
    path = max(files, key=os.path.getmtime)

d = json.load(open(path, encoding='utf-8'))
print(os.path.basename(path))
print('  queued     :', d.get('queued'), ' pending_id:', d.get('pending_id'))
print('  locations  :', d.get('location_count'), ' replacements:', d.get('replacement_count'), ' truncated:', d.get('truncated'))
print('  summary    :', str(d.get('change_summary'))[:120])
if d.get('skipped'):
    print('  skipped    :', d['skipped'][:5])
rows = d.get('targets') or d.get('locations') or []
live = [x for x in rows if not str(x.get('label', '')).startswith('revision')]
print(f'  LIVE rows  : {len(live)} of {len(rows)}')
for x in live:
    print(f"    {x['kind']:12s} id={x['id']:<6} {str(x.get('meta_key') or x.get('option_name') or ''):22s} n={x.get('replacements', x.get('hits'))!s:4s} {str(x.get('label'))[:58]}")
