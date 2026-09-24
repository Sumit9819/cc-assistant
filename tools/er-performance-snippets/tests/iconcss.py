"""Builds the replacement icon CSS for Irving and a stripped copy of the live combined CSS for testing."""
import json
import re
import zipfile

ICONS = json.load(open('../ers/irving-codepoints.json'))['cp']   # class -> hex codepoint
B64 = open('../ers/elementskit-irving-subset.b64').read().strip()

src = zipfile.ZipFile('ek.zip').read('elementskit-lite/modules/elementskit-icon-pack/assets/css/ekiticons.css').decode('utf-8', 'ignore')
rules = re.findall(r'[^{}]+\{[^{}]*\}', src)
base = [r.strip() for r in rules if 'content:' not in r and not r.strip().startswith('@font-face')]
print('base (non-glyph, non-font-face) rules kept:', base)

# replacement CSS; the glyph is written as the literal character (no backslash escapes)
parts = ['@font-face{font-family:elementskit;src:url(data:font/woff2;base64,%s) format("woff2");font-weight:400;font-style:normal;font-display:block}' % B64]
parts += base
for cls, hexcp in sorted(ICONS.items()):
    ch = chr(int(hexcp, 16))
    parts.append('.ekit-wid-con .icon.%s::before,.icon.%s::before{content:"%s"}' % (cls, cls, ch))
css = ''.join(parts)
open('irving-icons-replacement.css', 'w', encoding='utf-8').write(css)
print('replacement CSS bytes:', len(css.encode('utf-8')))

# the live combined CSS without anything that came from ekiticons.css (what Speed Optimizer would build)
live = open('../ers/irving-combined-now.css', encoding='utf-8', errors='ignore').read()
before = len(live)
live = re.sub(r'@font-face\{font-family:elementskit;[^}]*\}', '', live)
for r in base:
    live = live.replace(r, '')
live = re.sub(r'\.ekit-wid-con \.icon\.icon-[^{},]+::?before,\.icon\.icon-[^{},]+::?before\{content:"[^"]*"\}', '', live)
open('irving-combined-stripped.css', 'w', encoding='utf-8').write(live)
print('combined CSS bytes: %d -> %d (removed %d)' % (before, len(live), before - len(live)))
left = re.findall(r'[^{}]*\.icon\.icon-[^{]+::?before\{content[^}]*\}', live)
print('glyph rules left in stripped copy:', len(left), [x[:120] for x in left])
