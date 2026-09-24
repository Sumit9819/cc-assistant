import io, shutil, sys

F = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant\includes\class-pre-publish.php"
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

# ---- BUG A: a decimal point is not a sentence terminator -------------------
sub(
"""		// Count terminators.
		preg_match_all( '/[\\.\\?\\!]+/u', $text, $m );""",
"""		// Mask DECIMAL numbers before counting. "$1,259.99" carries a period
		// that is not a sentence terminator, and this function counts every
		// period it sees, so each price in a paragraph invented an extra
		// sentence. Found on sids-ponds 2026-08-23: a 4-sentence paragraph
		// quoting three prices was counted as 7 and failed paragraph_length,
		// which is a guaranteed false positive on any e-commerce copy that
		// states what something costs. Same masking idea as the abbreviation
		// pass above, applied repeatedly so 1.2.3 collapses fully.
		$text = preg_replace( '/(\\d)\\.(\\d)/u', '$1$2', $text );
		$text = preg_replace( '/(\\d)\\.(\\d)/u', '$1$2', $text );

		// Count terminators.
		preg_match_all( '/[\\.\\?\\!]+/u', $text, $m );""",
"count_sentences ignores decimal points")

# ---- BUG B: bounding period needs a SPACE or the heading still glues -------
sub(
"""		$bounded = preg_replace(
			'/([^\\.\\?\\!\\s>])\\s*(<\\/(?:p|h[1-6]|li|td|th|blockquote|caption|figcaption|div)>)/iu',
			'$1.$2',
			$html
		);""",
"""		// NOTE the space in the replacement. wp_strip_all_tags() removes tags
		// without inserting whitespace, so '$1.$2' produced "Inserts.</h2>" ->
		// "Inserts.A fire pit insert is..." with nothing between them. The
		// splitter below requires terminator + WHITESPACE, so the heading never
		// separated and was measured as part of the next sentence: a 5-word
		// heading plus a 21-word sentence read as one 26-word run-on and tripped
		// sentence_length. This normalization was already meant to prevent
		// exactly that gluing; it just needed the separator as well as the
		// terminator. Found on sids-ponds 2026-08-23.
		$bounded = preg_replace(
			'/([^\\.\\?\\!\\s>])\\s*(<\\/(?:p|h[1-6]|li|td|th|blockquote|caption|figcaption|div)>)/iu',
			'$1. $2',
			$html
		);""",
"heading bounding inserts a separator, not just a terminator")

io.open(F, "w", encoding="utf-8", newline="").write(t)
print("APPLIED:")
for a in applied:
    print("  [ok]  " + a)
if failed:
    print("FAILED:")
    for f in failed:
        print("  [!!]  " + f)
    sys.exit(1)
