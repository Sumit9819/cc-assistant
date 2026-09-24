import json
# My single-post test uploaded chemical-peel first (10289-10292, clean names);
# the full run re-uploaded the same four and WordPress uniquified them to -1
# (10293-10296). Point the placements at the clean-named originals and record
# the -1 set as deletable, so we neither reference both nor silently orphan four.
CLEAN = {1: 10289, 2: 10290, 3: 10291, 4: 10292}
rows = json.load(open('uploaded.json', encoding='utf-8'))
orphans = []
for r in rows:
    if r['slug'] == 'chemical-peel-treatment-guide' and r['order'] in CLEAN:
        orphans.append(r['attachment_id'])
        r['attachment_id'] = CLEAN[r['order']]
        r['url'] = r['url'].replace('-1.webp', '.webp')
json.dump(rows, open('uploaded.json', 'w', encoding='utf-8'), indent=2)
json.dump(orphans, open('orphan_attachments.json', 'w'), indent=2)
print('repointed to', sorted(CLEAN.values()))
print('duplicates to delete:', orphans)
