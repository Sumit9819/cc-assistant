import io
import sys

target, insert_path, start_marker, end_marker = sys.argv[1:5]

with io.open(target, encoding='utf-8') as handle:
    lines = handle.read().split('\n')
with io.open(insert_path, encoding='utf-8') as handle:
    replacement = handle.read().split('\n')
if replacement and replacement[-1] == '':
    replacement.pop()

start = next(i for i, line in enumerate(lines) if line.strip().startswith(start_marker))
end = next(i for i, line in enumerate(lines) if i > start and line.strip().startswith(end_marker))

out = lines[:start] + replacement + lines[end:]
with io.open(target, 'w', encoding='utf-8', newline='\n') as handle:
    handle.write('\n'.join(out))
print('spliced lines %d-%d, %d new lines' % (start + 1, end, len(replacement)))
