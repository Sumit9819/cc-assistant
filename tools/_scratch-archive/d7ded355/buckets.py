import json, re, collections, sys

buckets = collections.Counter()
files = collections.Counter()
nonupload = collections.Counter()

for f in sys.argv[1:]:
    d = json.load(open(f, encoding='utf-8'))
    rows = d.get('locations') or d.get('targets') or []
    for l in rows:
        c = l['context'].replace('\\/', '/')
        for m in re.finditer(r'https?://www\.jayard37\.sg-host\.com//([^"\'\s\\>)]*)', c):
            rest = m.group(1)
            mm = re.match(r'wp-content/uploads/(\d{4})/(\d{2})/(.*)$', rest)
            if mm:
                buckets[f'{mm.group(1)}/{mm.group(2)}'] += 1
                if mm.group(3):
                    files[f'{mm.group(1)}/{mm.group(2)}/{mm.group(3)}'] += 1
            else:
                nonupload[rest.split('/')[0] or '(root)'] += 1

print('=== staging upload buckets (YYYY/MM) ===')
for b, c in sorted(buckets.items()):
    print(f'{c:5d}  wp-content/uploads/{b}')
print()
print('=== staging NON-upload first segments ===')
for b, c in nonupload.most_common(30):
    print(f'{c:5d}  /{b}')
print()
print(f'=== distinct staging upload files seen: {len(files)} (snippets are windows, so names may be clipped) ===')
