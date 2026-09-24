import re
import json

css = open('irving-combined.css', encoding='utf-8', errors='ignore').read()
used = json.load(open('irving-icon-classes.json'))

BS = chr(92)
# content:"\e8a1" style values (hex escape after a backslash, inside single or double quotes)
CONTENT_RE = re.compile('content:' + r'\s*' + '["' + "'" + ']' + re.escape(BS) + '([0-9a-fA-F]+)' + '["' + "'" + ']')

cp = {}
for cls in used:
    rule = re.search(re.escape('.' + cls) + r'(?![\w-])[^{]*?:before\s*\{([^}]*)\}', css)
    m = CONTENT_RE.search(rule.group(1)) if rule else None
    cp[cls] = m.group(1) if m else None
print('class -> codepoint:', cp)

other = []
for m in re.finditer(r'([^{}]+)\{([^}]*font-family:\s*["' + "'" + r']?elementskit["' + "'" + r']?[^}]*)\}', css):
    c = CONTENT_RE.search(m.group(2))
    other.append((m.group(1).strip()[-120:], c.group(1) if c else None))
print('rules that set font-family elementskit directly:', len(other))
for sel, c in other[:15]:
    print('   ', c, '|', sel)

json.dump({'cp': cp, 'other': [c for s, c in other if c]}, open('irving-codepoints.json', 'w'))
