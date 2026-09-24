"""Queue offers.price into each product's Rank Math Product schema.
Draft-only: every call lands in the Pending Changes inbox for human approval.
Usage:  python queue_prices.py --dry [post_id]   |   python queue_prices.py --go
"""
import json, io, os, subprocess, sys

ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
SERVER = "cc-assistant-mammothmachinery-ca"

# (post_id, label, price) — price re-scraped from each LIVE page 2026-08-02.
PAGES = [
    (1616, "X-Cavator 20MT Mini Excavator",       "37999"),
    (1602, "X-Cavator 27MT Mini Excavator",       "47999"),
    (1585, "X-Cavator 35MT Mini Excavator",       "58999"),
    (3358, "X-Loader 50MT Mini Skid Steer",       "27999"),
    (1568, "X-Loader 100MT Mini Skid Steer",      "33999"),
    (1551, "X-Loader 120MT Mini Skid Steer",      "38999"),
    (1476, "TT570 Mini Dumper",                    "3199"),
    (1993, "TT900 Mini Track Dumper",              "5499"),
    (1319, "eTT900 Articulating Mini Dumper",      "5499"),
    (1710, "MT1350 Mini Dumper",                  "12499"),
    (2089, "MT1350CB Concrete Buggy",             "22999"),
    (1699, "MT2200 Mini Dumper",                  "19999"),
    (1648, "MT2200HL High Lift Dumper",           "21499"),
    (1684, "MT2850 Track Carrier",                "23999"),
    (2396, "MT2850CB Tracked Mini Dumper",        "28499"),
    (1532, "WL4500 Wheel Loader",                 "68999"),
    (1518, "WL7500 Wheel Loader",                "139999"),
    (2134, "TL5500 Telescopic Wheel Loader",      "73999"),
]

cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)[SERVER]
env = dict(os.environ)
env.update({k: str(v) for k, v in (s.get("env") or {}).items()})

proc = subprocess.Popen(
    [s["command"]] + list(s.get("args") or []),
    cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    text=True, encoding="utf-8", bufsize=1,
)

def send(o):
    proc.stdin.write(json.dumps(o) + "\n"); proc.stdin.flush()

def read_id(want):
    while True:
        line = proc.stdout.readline()
        if not line:
            return None
        line = line.strip()
        if not line:
            continue
        try:
            m = json.loads(line)
        except Exception:
            continue
        if m.get("id") == want:
            return m

send({"jsonrpc": "2.0", "id": 1, "method": "initialize",
      "params": {"protocolVersion": "2024-11-05", "capabilities": {},
                 "clientInfo": {"name": "queue", "version": "1"}}})
read_id(1)
send({"jsonrpc": "2.0", "method": "notifications/initialized"})

dry = "--go" not in sys.argv
only = [int(a) for a in sys.argv[1:] if a.isdigit()]
rows = [p for p in PAGES if not only or p[0] in only]
print(("DRY RUN" if dry else "QUEUEING") + f" — {len(rows)} page(s)\n")

ok = fail = 0
for i, (pid, label, price) in enumerate(rows):
    args = {
        "post_id": pid,
        "set": {"offers.price": price},
        "summary": f"Add missing schema price (${int(price):,} CAD) to {label}",
        "reasoning": (
            f"The Rank Math Product schema on this page has an Offer node with priceCurrency CAD and "
            f"availability InStock but NO price key at all. An Offer without a price is invalid to Google, "
            f"so this page can never show a price in search results. {price} is the price rendered on the "
            f"live page ($" + f"{int(price):,}" + " CAD), re-verified 2026-08-02. Only the price leaf is "
            f"added; every other value, including the %url% placeholder, is untouched. "
            f"Note: MT1750 (the one product whose schema already has a price) also carries "
            f"offers.priceValidUntil 2027-12-31 — not added here, since that date is a commercial claim "
            f"for you to confirm rather than one I can read off the page."
        ),
        "dry_run": dry,
    }
    send({"jsonrpc": "2.0", "id": 200 + i, "method": "tools/call",
          "params": {"name": "draft_update_rank_math_schema", "arguments": args}})
    r = read_id(200 + i)
    txt = ""
    if r and "result" in r:
        txt = " ".join(c.get("text", "") for c in r["result"].get("content", []))
    elif r:
        txt = json.dumps(r.get("error", r))
    try:
        d = json.loads(txt)
    except Exception:
        d = None
    if d and (d.get("pending_id") or d.get("dry_run") or d.get("would_queue") is not None):
        ok += 1
        pid_out = d.get("pending_id", "(dry)")
        notes = d.get("notes") or []
        print(f"  ok   {pid:>5} {label:36} ${int(price):>8,}  pending={pid_out}"
              + (f"  notes={notes}" if notes else ""))
    else:
        fail += 1
        print(f"  FAIL {pid:>5} {label:36} -> {txt[:300]}")

print(f"\nok={ok} fail={fail}")
proc.stdin.close(); proc.terminate()
err = proc.stderr.read()
if err.strip():
    print("[stderr]", err.strip()[:500])
