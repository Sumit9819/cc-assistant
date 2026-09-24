# -*- coding: utf-8 -*-
"""Sitewide claim correction. Exact-string replacements only, no regex on content.
Financing page (16) is EXCLUDED from the rate/approval rules: there is a standing
client decision that those claims stay there and nowhere else.
Run with --apply to queue; default is dry run."""
import json, io, os, subprocess, sys, copy

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
APPLY = "--apply" in sys.argv
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)["cc-assistant-mammothmachinery-ca"]
env = dict(os.environ); env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding="utf-8", bufsize=1)
mid = [0]
def call(n, a):
    mid[0] += 1; i = mid[0]
    proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":i,"method":"tools/call","params":{"name":n,"arguments":a}})+"\n"); proc.stdin.flush()
    while True:
        l = proc.stdout.readline()
        if not l: return None
        l = l.strip()
        if not l: continue
        try: m = json.loads(l)
        except Exception: continue
        if m.get("id") == i:
            if "result" not in m: return {"_err": str(m.get("error"))[:200]}
            txt = " ".join(c.get("text","") for c in m["result"].get("content",[]))
            try: return json.loads(txt)
            except Exception: return {"_raw": txt[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"cfix","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

# ---- rate / approval claims: every page EXCEPT the financing page ----
RATES = [
 ("Financing is available from 1.99% APR with flexible terms up to 72 months,",
  "Financing is available with flexible terms,"),
 ("financing from 1.99% to 0% APR</a>", "equipment financing</a>"),
 ("Financing As Low As 0% APR", "Financing Available"),
 ("Financing runs up to 72 months O.A.C.", "Financing is available on approved credit."),
 ("financing</a> up to 72 months O.A.C.", "financing</a> on approved credit."),
 ("flexible financing from 1.99% to 0% O.A.C.", "flexible financing on approved credit."),
 ("flexible financing up to 72 months</a>.", "flexible financing</a>."),
 ("equipment financing from <strong>1.99% to 0% APR</strong> with flexible terms up to 72 months on approved credit",
  "equipment financing with flexible terms on approved credit"),
 ("financing from 1.99% to 0% APR, and a parts-and-service support network",
  "equipment financing, and a parts-and-service support network"),
 ("available with financing from 1.99% to 0% APR", "available with equipment financing"),
 ("flexible financing up to 72 months from 1.99% to 0% O.A.C.", "flexible financing on approved credit."),
 ("flexible financing up to 72 months O.A.C.", "flexible financing on approved credit."),
 ("financing up to 72 months O.A.C.", "financing on approved credit."),
 ("Financing Available From 1.99% to 0% APR", "Financing Available"),
 ("Financing From 1.99% to 0% APR", "Financing Available"),
 ("Financing from 1.99% to 0% APR", "Financing available"),
 ("is available from 1.99% to 0% APR", "is available"),
 ("Financing up to 72 Months", "Financing Options"),
 ("Flexible terms up to 72 months", "Flexible terms"),
 ("98% approval rate", "Fast approvals"),
 ("98% Approval Rating", "Fast approvals"),
]
# ---- warranty scope + activation: every page ----
WARRANTY = [
 ("covering powertrain, hydraulic system, and structural components",
  "covering hydraulics, chassis and structural components"),
 ("Every Mammoth machine ships with this warranty active from day one.",
  "Every Mammoth machine is covered from delivery, and registering within 30 days secures the full 5-year term."),
 ("covering powertrain, hydraulics, and structure", "covering hydraulics, chassis and structural components"),
 ("powertrain, hydraulics, and structural components", "hydraulics, chassis and structural components"),
 ("powertrain, hydraulics, and structure", "hydraulics, chassis and structure"),
 ("powertrain, hydraulics and structure", "hydraulics, chassis and structure"),
 ("Powertrain \u00b7 Hydraulics \u00b7 Structure", "Hydraulics \u00b7 Chassis \u00b7 Structure"),
 ("Powertrain, hydraulics, structure", "Hydraulics, chassis, structure"),
]
# ---- dealer footprint ----
FOOTPRINT = [
 ("dealer network sees the same pattern across Ontario and Western Canada",
  "dealer network sees the same pattern from Ontario to the Maritimes"),
]
# ---- About page only: fabricated company facts ----
ABOUT = [
 ("our three state-of-the-art assembly hubs in Canada", "our assembly facility in Burlington, Ontario"),
 ("We have built a network of over 100 dedicated service partners who share our commitment to excellence.",
  "We have built a dealer and service network reaching from Ontario to the Maritimes, sharing our commitment to excellence."),
 ("four decades of Canadian manufacturing excellence", "31 years of Canadian equipment experience"),
 ("Mammoth Machinery is also a proud member of <a href=\"https://www.cme-mec.ca/\" target=\"_blank\">Canadian Manufacturers &amp; Exporters</a>, the country&#39;s largest industry and trade association. ", ""),
 ("We are currently expanding our manufacturing capacity with a new facility focused entirely on our <a href=\"/browse-models/\">next-generation electric and autonomous heavy equipment fleet</a>, designed to meet the evolving needs of a sustainable global economy.",
  "We continue to invest in our <a href=\"/browse-models/\">electric equipment range</a>, including the eTT900 electric mini dumper, as demand grows for lower-emission machines."),
]

hits = json.load(open("claim_hits.json", encoding="utf-8"))
FINANCING_PID = 16
queued = skipped = 0
log = []

for key in sorted(hits.keys(), key=lambda k: int(k.split("|")[0])):
    pid = int(key.split("|")[0]); label = key.split("|")[1]
    rules = list(WARRANTY) + list(FOOTPRINT)
    if pid != FINANCING_PID:
        rules = list(RATES) + rules
    if pid == 3291:
        rules = list(ABOUT) + rules
    r = call("export_elementor_data", {"post_id": pid})
    if not r or "raw_data" not in r:
        print(f"  !! {pid} {label}: export failed"); continue
    tree = json.loads(r["raw_data"])
    changes = {}   # widget_id -> {settings_key: new_value}
    def fix_str(v):
        out = v
        for find, repl in rules:
            if find in out:
                out = out.replace(find, repl)
        return out
    def walk(nodes):
        for n in nodes:
            st = n.get("settings", {}) or {}
            wid = n["id"]
            for k, v in list(st.items()):
                if isinstance(v, str):
                    nv = fix_str(v)
                    if nv != v:
                        changes.setdefault(wid, {})[k] = nv
                        log.append((pid, label, wid, k, v, nv))
                elif isinstance(v, list) and v and isinstance(v[0], dict):
                    newlist = copy.deepcopy(v); touched = False
                    for item in newlist:
                        for ik, iv in list(item.items()):
                            if isinstance(iv, str):
                                niv = fix_str(iv)
                                if niv != iv:
                                    item[ik] = niv; touched = True
                                    log.append((pid, label, wid, f"{k}.{ik}", iv, niv))
                    if touched:
                        changes.setdefault(wid, {})[k] = newlist
            walk(n.get("elements", []))
    walk(tree)
    if not changes:
        skipped += 1; continue
    for wid, sett in changes.items():
        n_repl = len(sett)
        if APPLY:
            res = call("draft_update_elementor_widget", {
                "post_id": pid, "widget_id": wid, "settings": sett,
                "summary": f"[CLAIMS {label}] Corrected unsupported or contradictory claim ({n_repl} field(s))",
                "reasoning": ("Sitewide claim-accuracy pass requested after the client's own review found false and "
                    "self-contradictory statements. Only exact known phrases were replaced; surrounding copy, markup and "
                    "styling are untouched. Corrections applied here: unverifiable financing rate and approval-rate claims "
                    "removed (the standing decision keeps those on the financing page only); warranty scope reworded from "
                    "\"powertrain\" to \"hydraulics, chassis and structure\" because the engine IS the powertrain and the "
                    "warranty page states engines are covered by Kubota/Yanmar, not Mammoth; the \"active from day one\" "
                    "claim reconciled with the 30-day registration requirement stated elsewhere on the same page; dealer "
                    "footprint corrected to Ontario-to-the-Maritimes, matching the actual dealer list. Every replacement "
                    "uses wording the site already supports elsewhere.")})
            pidq = res.get("pending_id") if isinstance(res, dict) else None
            print(f"  {pid:5} {label:18} {wid:10} {n_repl} field(s) -> {pidq or res}")
            queued += 1
        else:
            print(f"  [dry] {pid:5} {label:18} {wid:10} {n_repl} field(s): {list(sett.keys())}")

print(f"\n{'QUEUED' if APPLY else 'WOULD QUEUE'}: {queued} cards | pages with no change: {skipped}")
print(f"total field replacements: {len(log)}")
json.dump([{"pid":a,"label":b,"wid":c,"key":d,"before":e[:300],"after":f[:300]} for a,b,c,d,e,f in log],
          open("claim_fix_log.json","w",encoding="utf-8"), ensure_ascii=False, indent=1)
proc.stdin.close(); proc.terminate()
