import hashlib,json,re
from pathlib import Path
backup=Path('D:/cc-assistant/reports/preflight-0.88.0-2026-09-09/memory-backup')
replacement='''## When the site is unreachable (SiteGround challenge)

A `siteground_ip_challenge` response establishes that the particular request was challenged. It does not establish the cause, an IP-reputation diagnosis, or that every SiteGround site is blocked.

Use the selected site's configured MCP transport. Authenticated HTTP to erofwhiterock.com worked during the 2026-09-09 verification; this dated observation is not a permanent clearance guarantee. The plugin also supports integrated browser transport for the same authenticated REST routes. See `wp-content/plugins/cc-assistant/CONNECTION.md` for the actual setup and limitations. Do not assume an old ad-hoc browser script or wp-admin nonce is needed.

If the provider challenges the configured browser, complete its normal challenge or obtain a supported API-access arrangement from SiteGround. Restart MCP after bridge/configuration changes. Do not disable protection site-wide or work around the approval queue. A failed content fetch remains unverified, even if another API call succeeds.

'''
changes=[]
for i,p in enumerate([Path('D:/cc-assistant/CLAUDE.md'),Path('C:/Users/sumit/Local Sites/plugintesting/app/public/CLAUDE.md')]):
 before=p.read_bytes();s=before.decode('utf-8-sig');s,n=re.subn(r'## When the site is unreachable.*?(?=## Hard rules)',replacement,s,flags=re.S);assert n==1
 (backup/('connection-before-'+str(i)+'.md')).write_bytes(before)
 p.write_text(s,encoding='utf-8',newline='\n');changes.append({'path':str(p),'before_sha256':hashlib.sha256(before).hexdigest(),'after_sha256':hashlib.sha256(p.read_bytes()).hexdigest()})
(backup/'connection-corrections.json').write_text(json.dumps(changes,indent=2),encoding='utf-8')
print('Corrected both project connection guides; backups saved')
