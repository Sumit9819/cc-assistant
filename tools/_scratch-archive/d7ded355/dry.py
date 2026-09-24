import json, collections, sys

d = json.load(open(sys.argv[1], encoding='utf-8'))
needle = sys.argv[2]           # string that MUST be present in a legit target's context
print('summary   :', d.get('change_summary'))
print('locations :', d.get('location_count'), 'replacements:', d.get('replacement_count'), 'truncated:', d.get('truncated'))
print('skipped   :', d.get('skipped'))
print()

t = d['targets']
kinds = collections.Counter((x['kind'], x.get('meta_key') or x.get('option_name') or '') for x in t)
print('=== targets by kind ===')
for k, v in kinds.most_common():
    print(f'{v:5d}  {k}')
print()

# Rows whose context does NOT contain the needle => possible false positive from a loose variant
sus = [x for x in t if needle not in x['context'].replace('\\/', '/')]
print(f'=== rows whose snippet lacks "{needle}" (context is only a window, so verify) : {len(sus)} ===')
for x in sus[:25]:
    print(f"  {x['kind']} id={x['id']} meta={x.get('meta_key')} opt={x.get('option_name')} repl={x['replacements']} :: {x['label'][:55]}")
    print(f"      {x['context'][:200]}")
print()
print('=== NON-revision targets ===')
for x in t:
    if x['label'].startswith('revision'):
        continue
    print(f"  {x['kind']:12s} id={x['id']:<6} repl={x['replacements']:<4} ser={x['serialized']} meta={str(x.get('meta_key')):22s} opt={str(x.get('option_name')):26s} {x['label'][:52]}")
