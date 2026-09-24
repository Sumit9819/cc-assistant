import io, os, sys, shutil

F = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant\includes\class-pre-publish.php"
t = io.open(F, encoding="utf-8", newline="").read()
shutil.copy2(F, F + ".bak")

old = """		// Bulk-add ratio: added / (added + removed). 1.0 means nothing got cut.
		$denom    = $added_len + $removed_len;
		$add_pct  = $denom > 0 ? round( 100 * $added_len / $denom, 1 ) : 0.0;
		$checks['bulk_add_ratio'] = array(
			'pass'        => $add_pct <= 90.0 || $current_len < 1000, // Short posts are exempt — they often just need expansion.
			'added_chars' => $added_len,
			'removed_chars' => $removed_len,
			'add_percent' => $add_pct,
			'message'     => $add_pct <= 90.0 || $current_len < 1000
				? sprintf( 'Edit ratio: %d added / %d removed.', $added_len, $removed_len )
				: sprintf( '%.1f%% of the change is pure additions. Real edits cut as they expand. Look for paragraphs to merge or trim.', $add_pct ),
		);"""

new = """		// Bulk-add ratio: added / (added + removed). 1.0 means nothing got cut.
		//
		// Semantic intent, same as deletion_ratio below: on a body REWRITE the
		// model should cut redundant copy as it adds, and a pure "stack new
		// content on top of old" edit is a yellow flag. Also like deletion_ratio,
		// the check is meaningless for edits that were never meant to remove
		// anything — an inline-link insert, a new FAQ entry, one extra paragraph.
		// Those add a little and remove nothing, so added/(added+removed) is
		// ALWAYS 100% and the check failed on every single one.
		//
		// deletion_ratio already had this guard ($is_surgical). bulk_add_ratio
		// never got the matching one, so it fired on every link insert and every
		// additive edit on any body over 1000 chars — constant false positives
		// that teach the reviewer to ignore lint. Fixed 2026-08-21.
		//
		// The exemption is RELATIVE, not a flat byte count: what makes an edit
		// "bulk" is adding a lot compared with what is already there. Appending
		// 8,000 chars to a 10,000-char page still fails (80%); adding a 1,400-char
		// FAQ pair to a 20,000-char page does not (7%).
		$denom             = $added_len + $removed_len;
		$add_pct           = $denom > 0 ? round( 100 * $added_len / $denom, 1 ) : 0.0;
		$incremental_limit = max( 200, (int) round( $current_len * 0.15 ) );
		$is_incremental    = $added_len < $incremental_limit;
		$bulk_add_pass     = $add_pct <= 90.0 || $current_len < 1000 || $is_incremental;
		$checks['bulk_add_ratio'] = array(
			'pass'          => $bulk_add_pass,
			'added_chars'   => $added_len,
			'removed_chars' => $removed_len,
			'add_percent'   => $add_pct,
			'incremental'   => $is_incremental,
			'message'       => $is_incremental && $add_pct > 90.0
				? sprintf( 'Incremental addition (%d chars added to a %d-char body) — bulk-add ratio not applicable.', $added_len, $current_len )
				: ( $bulk_add_pass
					? sprintf( 'Edit ratio: %d added / %d removed.', $added_len, $removed_len )
					: sprintf( '%.1f%% of the change is pure additions. Real edits cut as they expand. Look for paragraphs to merge or trim.', $add_pct ) ),
		);"""

assert t.count(old) == 1, f"anchor matched {t.count(old)} times"
io.open(F, "w", encoding="utf-8", newline="").write(t.replace(old, new, 1))
print("  [ok]  bulk_add_ratio exempts incremental additions")
