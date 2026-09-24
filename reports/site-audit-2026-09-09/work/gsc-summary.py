import sqlite3,json
from pathlib import Path
c=sqlite3.connect('file:C:/Users/sumit/.cc-assistant/warehouse/erofwhiterock-com.sqlite?mode=ro',uri=True);c.row_factory=sqlite3.Row
q=lambda sql:[dict(r) for r in c.execute(sql)]
windows="CASE WHEN date<'2026-08-11' THEN 'previous' ELSE 'current' END"
where="date BETWEEN '2026-07-14' AND '2026-09-07'"
out={'windows':{'previous':['2026-07-14','2026-08-10'],'current':['2026-08-11','2026-09-07']},'scope':'Retrieved query/page rows; anonymized queries and upstream API omissions mean these are not whole-property totals.',
'coverage':q("SELECT MIN(date) first,MAX(date) last,COUNT(*) dates,SUM(complete=0) incomplete FROM sync_log WHERE "+where),
'daily':q("SELECT date,COUNT(*) rows,SUM(clicks) clicks,SUM(impressions) impressions FROM gsc_daily WHERE "+where+" GROUP BY date ORDER BY date"),
'totals':q("SELECT "+windows+" period,SUM(clicks) clicks,SUM(impressions) impressions,ROUND(1.0*SUM(clicks)/SUM(impressions),5) ctr,ROUND(SUM(position*impressions)/SUM(impressions),2) weighted_position FROM gsc_daily WHERE "+where+" GROUP BY period"),
'pages':q("SELECT "+windows+" period,page,SUM(clicks) clicks,SUM(impressions) impressions,ROUND(1.0*SUM(clicks)/SUM(impressions),5) ctr,ROUND(SUM(position*impressions)/SUM(impressions),2) weighted_position FROM gsc_daily WHERE "+where+" GROUP BY period,page ORDER BY period,SUM(impressions) DESC"),
'queries':q("SELECT query,SUM(clicks) clicks,SUM(impressions) impressions,COUNT(DISTINCT page) pages,ROUND(SUM(position*impressions)/SUM(impressions),2) weighted_position FROM gsc_daily WHERE date BETWEEN '2026-08-11' AND '2026-09-07' GROUP BY query ORDER BY SUM(impressions) DESC LIMIT 60"),
'query_pages':q("SELECT query,page,SUM(clicks) clicks,SUM(impressions) impressions,ROUND(SUM(position*impressions)/SUM(impressions),2) weighted_position FROM gsc_daily WHERE date BETWEEN '2026-08-11' AND '2026-09-07' GROUP BY query,page HAVING SUM(impressions)>=50 ORDER BY SUM(impressions) DESC LIMIT 100")}
Path('D:/cc-assistant/reports/site-audit-2026-09-09/gsc-analysis.json').write_text(json.dumps(out,indent=2),encoding='utf-8')
print('COVERAGE',json.dumps(out['coverage']));print('TOTALS',json.dumps(out['totals']))
print('CURRENT TOP PAGES',json.dumps([p for p in out['pages'] if p['period']=='current'][:20]));print('TOP QUERIES',json.dumps(out['queries'][:20]))
