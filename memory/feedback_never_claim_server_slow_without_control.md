---
name: feedback_never_claim_server_slow_without_control
description: "HARD RULE - never diagnose caching or server speed from an option value, a HEAD request, or a raw TTFB; each needs a specific control first"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-13T12:48:12.873Z
---

Three separate traps produced three wrong claims on sids-ponds in one sitting (2026-08-12). All three are now banned without the stated control.

**1. Never read a plugin option and declare a feature on or off.**
Verify what the option *means* by grepping the plugin's own source (download from `downloads.wordpress.org/plugin/<slug>.<version>.zip`), then verify the feature's *behaviour* separately. SG Optimizer proves why: `siteground_optimizer_enable_cache` really is the "Dynamic Cache" toggle (`core/Message_Service/Message_Service.php`), but `File_Cacher::maybe_enable_dynamic()` **force-writes it to 1** whenever File-Based Caching is on and the host is SiteGround. The value is therefore not evidence the cache works.

**2. Never check cache headers with `curl -I`.**
HEAD requests are not served or populated by many caches. On this site HEAD returned `SG-F-Cache: BYPASS` on the homepage while GET returned no such header at all, and GET on `/about-us/` returned `X-Proxy-Cache: HIT`. Always GET with `-o /dev/null -D -`.

**3. Never attribute TTFB to the server without a same-host static control.**
Request a static asset (a `.css` under `/wp-content/`) on the same host and compare. On sids-ponds: static TTFB 1.167s, dynamic HTML TTFB 1.264s — so PHP costs about **100ms** and the rest is round-trip latency from the operator's location. I was one message away from sending them to SiteGround support over a non-problem. Also read the curl breakdown (`time_connect`, `time_appconnect`) — a one-off `tcp=7.5s` is connection noise, not "cold cache".

**Recurrence 2026-09-16/17, irvingwellnessclinic.** Broke rule 3 again: used google.com as the "control" (a different host, answered from a nearby edge) and told the operator every page waited 5 to 15 s and that uncached hosting was the site's biggest problem. The `x-ce` response header showed my requests hitting SiteGround CDN edges in `asia-southeast1` / `asia-northeast1`, far from the US origin. A US Lighthouse run (Ubersuggest `pagespeed_audit`, used when the keyless PSI API hits its 429 daily quota) gave desktop LCP 0.71 s and mobile LCP 3.0 s. Also read `x-ce` before quoting any SiteGround timing, and prefer a US-run lab or CrUX field number over anything measured from this machine.

**How to apply:** state the measurement and its control together, e.g. "PHP ~100ms (static asset on the same host: 1.167s vs HTML 1.264s)". If there is no control, there is no claim. See [[feedback_probe_discipline_positive_controls]] and [[feedback_no_guessing_epistemic_discipline]].

**Two more cache-evidence traps (2026-09-17, IWC).** (a) cc-assistant's own loopback fetchers (`render_probe`, render health, seo-tools) send the REQUEST header `Cache-Control: no-cache, no-store, max-age=0`, so their `x-proxy-cache: MISS` is self-inflicted and proves nothing about caching. (b) Speed Optimizer writes `X-Cache-Enabled: True` itself whenever its option is on (`Supercacher_Helper::set_cache_headers`), so that header is not evidence either. The file cache announces `SG-F-Cache: HIT` when it serves; on newer SG servers it suppresses MISS, so an absent header is not a MISS. Ground truth is Site Tools > Speed > Caching > Test URL.

**IWC measured split (2026-09-17, cold incognito-like contexts from the operator's region, Asia).** HTML first byte 1,014-1,099 ms vs a static file on the same host (cache-busted) 811-946 ms, so the WordPress build is only ~150-200 ms and the rest is Asia to US distance. SiteGround CDN serves static files from `asia-southeast1` edges at 89-132 ms (`x-proxy-cache: HIT`, `x-cdn-c: static`), but HTML is never edge-cached and always crosses to the US origin. So "the site feels slow in incognito" from Nepal is distance plus first-visit extras (sale overlay at ~5 s, ~1.2 MB of tags and chat on first mouse move), not the server; fixing the dynamic cache saves ~0.2 s at most. The patients are in Irving, TX. Don't send the operator to hosting upgrades or cache tickets for this.
