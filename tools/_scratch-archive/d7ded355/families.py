import json, re, collections, sys, glob

files = sys.argv[1:]
seg = collections.Counter()
full = collections.Counter()
hosts = collections.Counter()

HOSTPAT = re.compile(
    r'(https?:)?//((?:www\.)?jayard37\.sg-host\.com|localhost|clients\.bluemuffinstudio\.com\.au|i[01]\.wp\.com|(?:www\.)?gnpn\.org)(/+)([^"\'\s\\>)]*)'
)

for f in files:
    d = json.load(open(f, encoding='utf-8'))
    for l in d['locations']:
        c = l['context'].replace('\\/', '/')
        for m in HOSTPAT.finditer(c):
            scheme = m.group(1) or '(proto-rel)'
            host = m.group(2)
            nsl = len(m.group(3))
            rest = m.group(4)
            first = rest.split('/')[0] if rest else '(root)'
            hosts[(scheme, host, nsl)] += 1
            seg[(host, nsl, first)] += 1
            full[f'{scheme}//{host}{"/"*nsl}{first}'] += 1

print('=== host + slash-count ===')
for (s, h, n), c in hosts.most_common():
    print(f'{c:5d}  {s:12s} {h:32s} slashes={n}' + ('   <-- DOUBLE' if n > 1 else ''))
print()
print('=== first path segment per host ===')
for (h, n, s0), c in seg.most_common(40):
    print(f'{c:5d}  {h:32s} slashes={n}  /{s0}')
