import io, shutil, sys

F = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant\includes\class-pending-changes.php"
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

# ---- 1. probe set = removed PHRASES + distinctive singles -----------------
old_probe = """		// Only tokens of length >= 4 participate in sibling matching — 3-char
		// tokens produce too many incidental hits in summaries. Exception:
		// 3-letter tokens written as ALL-CAPS acronyms in the removed text
		// (MRI, EKG, DKA...) are exactly the capability claims this guard
		// exists for, so they stay in the probe set.
		$acronyms = array();
		if ( preg_match_all( '/\\b[A-Z]{3}\\b/', (string) $old_text, $acr_m ) ) {
			$acronyms = array_fill_keys( $acr_m[0], true );
		}
		$probe = array();
		foreach ( $removed as $t ) {
			if ( strlen( $t ) >= 4 || isset( $acronyms[ $t ] ) ) {
				$probe[] = $t;
			}
		}
		if ( empty( $probe ) ) {
			return array();
		}"""

new_probe = """		// What counts as EVIDENCE that this change undoes a sibling change.
		//
		// This used to probe every removed token of length >= 4 and fire if any
		// ONE of them appeared anywhere in a sibling's summary or payload. One
		// ordinary word in common is not evidence. Real false positives, 2026-08:
		// a homepage title swapping "Garden Supply" for "Garden Store" removed the
		// lone token SUPPLY and matched an unrelated product-carousel revert; on
		// mammothmachinery the tokens BEST and MODELS fired the same way and were
		// hand-dismissed, which is precisely how a guard teaches people to ignore
		// it.
		//
		// Evidence now means either:
		//   1. a removed PHRASE — 2+ significant tokens that sat ADJACENT in the
		//      old text and are all absent from the new one. That is the guard's
		//      literal claim: "text you deliberately added has been removed".
		//   2. a DISTINCTIVE single token — an ALL-CAPS acronym (MRI, EKG, DKA:
		//      the capability claims this guard was built for, cf. page 852),
		//      anything containing a digit (prices, 24/7, model numbers), or a
		//      word of 8+ chars, which is rarely incidental.
		//
		// Both paths stay advisory; the cost of a miss is a warning that does not
		// appear, the cost of the old behaviour was every warning being noise.
		$acronyms = array();
		if ( preg_match_all( '/\\b[A-Z]{3}\\b/', (string) $old_text, $acr_m ) ) {
			$acronyms = array_fill_keys( $acr_m[0], true );
		}
		$removed_set = array_fill_keys( $removed, true );

		// Ordered, NOT de-duplicated token stream of the old text so adjacency
		// survives — significant_tokens() returns a set, which loses it. No
		// stopword filtering is needed here: stopwords were already excluded from
		// both token sets, so they are never in $removed_set and simply break a
		// run, which is the conservative direction.
		$phrases = array();
		$run     = array();
		if ( preg_match_all( '/[A-Z0-9]{3,}/', strtoupper( (string) $old_text ), $ord_m ) ) {
			foreach ( $ord_m[0] as $tok ) {
				if ( isset( $removed_set[ $tok ] ) ) {
					$run[] = $tok;
					continue;
				}
				if ( count( $run ) >= 2 ) {
					$phrases[] = $run;
				}
				$run = array();
			}
		}
		if ( count( $run ) >= 2 ) {
			$phrases[] = $run;
		}

		$probe = array();
		foreach ( $phrases as $ph ) {
			$probe[] = $ph;
		}
		foreach ( $removed as $tk ) {
			if ( isset( $acronyms[ $tk ] ) || preg_match( '/[0-9]/', $tk ) || strlen( $tk ) >= 8 ) {
				$probe[] = array( $tk );
			}
		}
		if ( empty( $probe ) ) {
			return array();
		}
		$probe = array_slice( $probe, 0, 200 );"""
sub(old_probe, new_probe, "probe = removed phrases + distinctive singles")

# ---- 2. matching requires the phrase to stay adjacent --------------------
old_match = """			$haystack = strtoupper( $summary . ' ' . (string) $s->proposed_value );
			$matched  = array();
			foreach ( $probe as $t ) {
				if ( preg_match( '/(?<![A-Z0-9])' . preg_quote( $t, '/' ) . '(?![A-Z0-9])/', $haystack ) ) {
					$matched[] = $t;
				}
			}"""
new_match = """			$haystack = strtoupper( $summary . ' ' . (string) $s->proposed_value );
			$matched  = array();
			foreach ( $probe as $ph ) {
				// A phrase must appear with its words still ADJACENT (punctuation
				// or whitespace between them is fine), not merely scattered
				// somewhere in the sibling payload.
				$parts = array();
				foreach ( $ph as $w ) {
					$parts[] = preg_quote( $w, '/' );
				}
				$rx = '/(?<![A-Z0-9])' . implode( '[^A-Z0-9]{1,4}', $parts ) . '(?![A-Z0-9])/';
				if ( preg_match( $rx, $haystack ) ) {
					$matched[] = implode( ' ', $ph );
				}
			}"""
sub(old_match, new_match, "phrase-adjacent matching")

io.open(F, "w", encoding="utf-8", newline="").write(t)
print("APPLIED:")
for a in applied:
    print("  [ok]  " + a)
if failed:
    print("FAILED:")
    for f in failed:
        print("  [!!]  " + f)
    sys.exit(1)
