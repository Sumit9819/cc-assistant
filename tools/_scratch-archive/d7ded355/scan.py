import json, re, collections, sys

path = sys.argv[1]
d = json.load(open(path, encoding='utf-8'))
locs = d['locations']
ctx = ' '.join(l['context'] for l in locs)

# Normalise JSON-escaped slashes for shape analysis
norm = ctx.replace('\\/', '/')

# Find every absolute URL start: scheme + // + host + following slashes
pat = re.compile(r'(https?:)?//([A-Za-z0-9._-]+)(/+)')
shapes = collections.Counter()
for m in pat.finditer(norm):
    scheme = m.group(1) or '(protocol-relative)'
    host = m.group(2)
    slashes = len(m.group(3))
    shapes[(scheme, host, slashes)] += 1

print('=== URL shapes in context snippets (scheme, host, #slashes after host) ===')
for (s, h, n), c in shapes.most_common(40):
    flag = '  <-- DOUBLE SLASH' if n > 1 else ''
    print(f'{c:5d}  {s:22s} {h:35s} slashes={n}{flag}')
