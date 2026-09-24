import json, re, collections, sys

d = json.load(open(sys.argv[1], encoding='utf-8'))
print('url          :', d['url'])
print('variants     :', d['variants_searched'])
print('locations    :', d['location_count'], ' occurrences:', d['occurrence_count'], ' truncated:', d['truncated'])
print('by_kind      :', d['by_kind'])
print()

kindcount = collections.Counter((l['kind'], l.get('post_type'), l.get('status')) for l in d['locations'])
print('=== breakdown ===')
for k, v in kindcount.most_common():
    print(f'{v:5d}  {k}')
print()

print('=== NON-revision locations ===')
for l in d['locations']:
    if l.get('post_type') == 'revision':
        continue
    print(f"{l['kind']:14s} id={l['id']:<6} {str(l.get('post_type')):20s} {str(l.get('status')):8s} hits={l['hits']:<4} ser={l['serialized']} {l['label'][:70]}")
print()

# URL shapes
ctx = ' '.join(l['context'] for l in d['locations']).replace('\\/', '/')
shapes = collections.Counter()
for m in re.compile(r'(https?:)?//([A-Za-z0-9._-]+)(/+)').finditer(ctx):
    shapes[(m.group(1) or '(proto-rel)', m.group(2), len(m.group(3)))] += 1
print('=== URL shapes in snippets ===')
for (s, h, n), c in shapes.most_common(30):
    print(f'{c:5d}  {s:14s} {h:32s} slashes={n}' + ('  <-- DOUBLE' if n > 1 else ''))
