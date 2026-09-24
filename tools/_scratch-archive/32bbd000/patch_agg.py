import io, os, sys, shutil

F = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant\includes\class-seo-tools.php"
t = io.open(F, encoding="utf-8", newline="").read()
shutil.copy2(F, F + ".bak")
applied, failed = [], []

def sub(old, new, label):
    global t, applied, failed
    if t.count(old) != 1:
        failed.append(f"{label}: {t.count(old)} matches")
        return
    t = t.replace(old, new, 1)
    applied.append(label)

# 1. Mirror the scorer-version gate into the aggregate's staleness test.
sub(
"""			$is_stale = ( '' === $scored_at ) || ( strcmp( (string) $scored_at, $stale_cutoff ) < 0 );
			if ( ! $is_stale ) {
				$p = get_post( (int) $pid );
				if ( $p && strcmp( (string) $p->post_modified_gmt, (string) $scored_at ) > 0 ) {
					$is_stale = true;
				}
			}
""",
"""			$is_stale = ( '' === $scored_at ) || ( strcmp( (string) $scored_at, $stale_cutoff ) < 0 );
			if ( ! $is_stale ) {
				$p = get_post( (int) $pid );
				if ( $p && strcmp( (string) $p->post_modified_gmt, (string) $scored_at ) > 0 ) {
					$is_stale = true;
				}
			}
			// Mirror the SCORER-VERSION gate as well. A score produced by an
			// older scorer is not comparable to one produced by this build no
			// matter how recently it was written. Missing this mirror is what
			// made a correctly shipped scoring fix look like a no-op: the
			// aggregate reported rescored=0 and repeated the pre-fix verdict
			// verbatim, because every entry was timestamped "today" and so
			// passed the freshness and post_modified gates. sids-ponds,
			// 2026-08-20. If you add a gate to helpful_content_score, add it
			// here too — this loop reads the cached postmeta directly and does
			// NOT go through that function's cache path.
			$row_ver = (string) get_post_meta( $pid, '_cc_helpful_content_score_ver', true );
			if ( $row_ver !== $scorer_version ) {
				$is_stale = true;
				$stale_scorer_rows++;
			}
""",
"aggregate mirrors the scorer-version gate")

# 2. Declare the counters next to the existing ones.
sub(
"""		$stale_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $freshness_days * DAY_IN_SECONDS ) );
		$rescored     = 0;
		$rows         = array();""",
"""		$stale_cutoff      = gmdate( 'Y-m-d H:i:s', time() - ( $freshness_days * DAY_IN_SECONDS ) );
		$rescored          = 0;
		$rows              = array();
		$scorer_version    = defined( 'CC_ASSISTANT_VERSION' ) ? (string) CC_ASSISTANT_VERSION : '';
		$stale_scorer_rows = 0;""",
"declare scorer_version + stale counter")

# 3. Surface it, so a mixed-generation verdict is never reported as clean.
sub(
"""		$scored = array_filter( $rows, function ( $r ) { return null !== $r['score']; } );
		$unscored_count = count( $rows ) - count( $scored );""",
"""		$scored = array_filter( $rows, function ( $r ) { return null !== $r['score']; } );
		$unscored_count = count( $rows ) - count( $scored );
		// How many rows still carry a score from a DIFFERENT scorer build. Any
		// value above zero means this verdict mixes scorer generations and is
		// not directly comparable — re-run with refresh_stale=true.
		$mixed_scorer_rows = 0;
		foreach ( $rows as $r ) {
			if ( null === $r['score'] ) { continue; }
			if ( (string) get_post_meta( (int) $r['post_id'], '_cc_helpful_content_score_ver', true ) !== $scorer_version ) {
				$mixed_scorer_rows++;
			}
		}""",
"count rows scored by a different build")

io.open(F, "w", encoding="utf-8", newline="").write(t)
print("APPLIED:")
for a in applied: print("  [ok]  " + a)
if failed:
    print("\nFAILED:")
    for f in failed: print("  [!!]  " + f)
    sys.exit(1)
