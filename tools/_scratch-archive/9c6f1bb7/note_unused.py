import json
# Post 9462 already had these three graphics in-body since March, placed under
# the same H2s. Uploading them again was wasted; record the new attachments as
# unused so they can be deleted rather than sitting as silent duplicates.
rows = json.load(open('uploaded.json', encoding='utf-8'))
orphans = json.load(open('orphan_attachments.json', encoding='utf-8'))
added = [r['attachment_id'] for r in rows
         if r['slug'] == 'weight-loss-resistance-causes' and r.get('attachment_id')]
orphans = sorted(set(orphans + added))
json.dump(orphans, open('orphan_attachments.json', 'w'), indent=2)
print('9462 duplicates:', added)
print('total unused attachments to delete:', orphans)
