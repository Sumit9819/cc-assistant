import os, zipfile
os.chdir(r"C:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins")
src = 'cc-assistant'
out = r'D:\cc-assistant-0.81.0.zip'
skip_dirs = {'.git', 'node_modules', '__pycache__', '.tmp-warehouse', '.tmp-outcome', '.tmp-commodity', '.tmp-kw', '.tmp-wcag', 'outcome-test-data'}
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(src):
        dirs[:] = [d for d in dirs if d not in skip_dirs]
        for f in files:
            if f.endswith(('.zip', '.bak')):
                continue
            p = os.path.join(root, f)
            z.write(p, p.replace('\\', '/'))
with zipfile.ZipFile(out) as z:
    names = z.namelist()
    print(out)
    print('entries', len(names))
    print('backslash entries', len([x for x in names if '\\' in x]))
    print('main present', 'cc-assistant/cc-assistant.php' in names)
    print('operator-brain.php present', 'cc-assistant/bin/operator-brain.php' in names)
    print('brain-sync.php present', 'cc-assistant/bin/brain-sync.php' in names)
    print('rest-operator-kit present', 'cc-assistant/includes/class-rest-operator-kit.php' in names)
print('size MB', round(os.path.getsize(out) / 1e6, 2))
