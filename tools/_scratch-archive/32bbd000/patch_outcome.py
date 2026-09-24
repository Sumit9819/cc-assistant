import io, os, sys, shutil

F = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant\bin\warehouse.php"
t = io.open(F, encoding="utf-8", newline="").read()
shutil.copy2(F, F + ".bak")
applied, failed = [], []

def sub(old, new, label):
    global t
    if t.count(old) != 1:
        failed.append(f"{label}: {t.count(old)} matches")
        return
    t = t.replace(old, new, 1)
    applied.append(label)

# ---- 1. window_totals gains an exclusion mode -----------------------------
sub(
"""/** SUM clicks/impressions for a date window, optionally path-filtered. */
function cc_wh_window_totals( $db, $start, $end, $path = null ) {
	if ( null === $path ) {
		$stmt = $db->prepare( 'SELECT COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i FROM gsc_daily WHERE date >= :s AND date <= :e' );
	} else {""",
"""/**
 * SUM clicks/impressions for a date window.
 *
 * $path         — restrict to ONE page (exact URL variants).
 * $exclude_path — sum every page EXCEPT that one. Used for the site-wide
 *                 control: a control that still contains the page being
 *                 measured is not a control. It barely matters for a small
 *                 page, but a homepage is routinely 20-40% of a site's clicks,
 *                 so including it drags the "site trend" toward the very move
 *                 being measured and understates the effect. Added 2026-08-21.
 */
function cc_wh_window_totals( $db, $start, $end, $path = null, $exclude_path = null ) {
	if ( null === $path && null !== $exclude_path ) {
		$variants = cc_wh_page_url_variants( $exclude_path );
		$names    = array();
		foreach ( $variants as $i => $v ) {
			$names[] = ':x' . $i;
		}
		$stmt = $db->prepare(
			"SELECT COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i FROM gsc_daily
			 WHERE date >= :s AND date <= :e AND page NOT IN ( " . implode( ',', $names ) . ' )'
		);
		foreach ( $variants as $i => $v ) {
			$stmt->bindValue( ':x' . $i, $v, SQLITE3_TEXT );
		}
	} elseif ( null === $path ) {
		$stmt = $db->prepare( 'SELECT COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i FROM gsc_daily WHERE date >= :s AND date <= :e' );
	} else {""",
"window_totals supports an exclusion mode")

# ---- 2. lift the stale homepage skip -------------------------------------
sub(
"""			if ( '' === $path || '/' === $path || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}/', $item['applied_at'] ) ) {
				// Homepage edits ('/' would LIKE-match every URL) and undated
				// rows cannot be attributed — count and skip honestly.
				$item['verdict'] = 'no_url';""",
"""			// The homepage USED to be skipped here because page matching was a
			// suffix LIKE, where '/' matched every URL on the site. v0.60.1
			// replaced that with exact variant matching (page IN (...8 forms)),
			// so '/' now resolves to the eight homepage spellings and nothing
			// else — but this guard was never revisited. The result was that the
			// biggest non-brand page on most of these sites could never be
			// scored at all, and homepage regressions had to be diagnosed by
			// hand. Lifted 2026-08-21; only genuinely unattributable rows
			// (no path, or no apply date) are skipped now.
			if ( '' === $path || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}/', $item['applied_at'] ) ) {
				$item['verdict'] = 'no_url';""",
"homepage is scoreable again")

# ---- 3. the control must exclude the measured page ------------------------
sub(
"""			$site_pre  = cc_wh_window_totals( $db, $pre_start, $pre_end );
			$site_post = cc_wh_window_totals( $db, $post_start, $post_end );""",
"""			// Control = the REST of the site, with the measured page removed.
			$site_pre  = cc_wh_window_totals( $db, $pre_start, $pre_end, null, $path );
			$site_post = cc_wh_window_totals( $db, $post_start, $post_end, null, $path );""",
"control excludes the measured page")

io.open(F, "w", encoding="utf-8", newline="").write(t)
print("APPLIED:")
for a in applied: print("  [ok]  " + a)
if failed:
    print("\nFAILED:")
    for f in failed: print("  [!!]  " + f)
    sys.exit(1)
