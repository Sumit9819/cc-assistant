import json, re, sys, collections

d = json.load(open(sys.argv[1], encoding='utf-8'))
needle = sys.argv[2]
maxn = int(sys.argv[3]) if len(sys.argv) > 3 else 8

seen = 0
paths = collections.Counter()
for l in d['locations']:
    c = l['context'].replace('\\/', '/')
    if needle not in c:
        continue
    # collect full URLs for this host
    for m in re.finditer(r'(https?:)?//' + re.escape(needle) + r'[^"\'\s\\>)]*', c):
        paths[m.group(0)] += 1
    if seen < maxn:
        print(f"--- {l['kind']} id={l['id']} {l.get('post_type')} {l.get('status')} ser={l['serialized']} :: {l['label'][:60]}")
        print('   ', c[:300])
        seen += 1

print()
print(f'=== distinct URLs containing "{needle}" ({len(paths)}) ===')
for p, c in paths.most_common(25):
    print(f'{c:4d}  {p[:160]}')
