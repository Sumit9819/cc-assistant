import json, io, os, subprocess, sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)["cc-assistant-mammothmachinery-ca"]
env = dict(os.environ); env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding="utf-8", bufsize=1)
_mid=[0]
def call(n,a):
    _mid[0]+=1; mid=_mid[0]
    proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":mid,"method":"tools/call","params":{"name":n,"arguments":a}})+"\n"); proc.stdin.flush()
    while True:
        l=proc.stdout.readline().strip()
        if not l: continue
        try: m=json.loads(l)
        except: continue
        if m.get("id")==mid:
            return json.loads(" ".join(c.get("text","") for c in m["result"].get("content",[])))
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"fi","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l=proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id")==9000: break
        except: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

for pid in (20, 18, 1986):
    try:
        tree = json.loads(call("export_elementor_data", {"post_id": pid})["raw_data"])
    except Exception as e:
        print(pid, "export fail", e); continue
    print(f"\n===== page {pid}")
    def walk(nodes):
        for n in nodes:
            wt = n.get("widgetType")
            if wt and "form" in str(wt).lower():
                st = n.get("settings", {}) or {}
                print(f"  widget {n['id']} type={wt}")
                for k in ("form_name","form_fields","email_to","email_subject","email_from","email_from_name",
                          "email_reply_to","email_to_2","submit_actions","button_text","success_message"):
                    if k in st:
                        v = st[k]
                        if k == "form_fields":
                            print(f"     form_fields: {len(v)} fields")
                            for f in v:
                                print(f"        - {f.get('field_type')} | label={f.get('field_label')!r} | required={f.get('required')} | id={f.get('custom_id')}")
                        else:
                            print(f"     {k} = {json.dumps(v, ensure_ascii=False)[:160]}")
            walk(n.get("elements", []))
    walk(tree)
proc.stdin.close(); proc.terminate()
