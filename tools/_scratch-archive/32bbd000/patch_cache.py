import io, os, sys, shutil

BASE = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant"
F = os.path.join(BASE, "includes", "class-seo-tools.php")

t = io.open(F, encoding="utf-8", newline="").read()
shutil.copy2(F, F + ".bak")
applied, failed = [], []

def sub(text, old, new, label):
    global applied, failed
    if text.count(old) != 1:
        failed.append(f"{label}: found {text.count(old)} matches, expected 1")
        return text
    applied.append(label)
    return text.replace(old, new, 1)

# --- READ: invalidate the cache when the SCORER ITSELF changed --------------
old_read = """			$cached_score = get_post_meta( $post_id, '_cc_helpful_content_score', true );
			$cached_at    = get_post_meta( $post_id, '_cc_helpful_content_score_at', true );
			$cached_body  = get_post_meta( $post_id, '_cc_helpful_content_score_body', true );"""
new_read = """			$cached_score = get_post_meta( $post_id, '_cc_helpful_content_score', true );
			$cached_at    = get_post_meta( $post_id, '_cc_helpful_content_score_at', true );
			$cached_body  = get_post_meta( $post_id, '_cc_helpful_content_score_body', true );
			// The cached score is only comparable if it was produced by THIS
			// version of the scorer. Without this, changing the scoring rules
			// leaves every site serving numbers computed by the old code until
			// each post happens to be edited — and `refresh_stale` cannot rescue
			// it, because those entries are timestamped recently and therefore
			// look fresh. Observed on sids-ponds 2026-08-20: after shipping the
			// scoring-scope fixes, site_quality_score reported rescored=0 and
			// repeated the pre-fix verdict verbatim, which reads exactly like a
			// fix that did not work.
			$cached_ver = (string) get_post_meta( $post_id, '_cc_helpful_content_score_ver', true );
			$current_ver = defined( 'CC_ASSISTANT_VERSION' ) ? (string) CC_ASSISTANT_VERSION : '';
			if ( $cached_ver !== $current_ver ) {
				$cached_score = '';
			}"""
t = sub(t, old_read, new_read, "cache read invalidates on scorer version change")

# --- WRITE: stamp the version alongside the score ---------------------------
old_write = """		update_post_meta( $post_id, '_cc_helpful_content_score', (int) $total );
		update_post_meta( $post_id, '_cc_helpful_content_score_at', gmdate( 'Y-m-d H:i:s' ) );
		update_post_meta( $post_id, '_cc_helpful_content_score_body', $result );"""
new_write = """		update_post_meta( $post_id, '_cc_helpful_content_score', (int) $total );
		update_post_meta( $post_id, '_cc_helpful_content_score_at', gmdate( 'Y-m-d H:i:s' ) );
		update_post_meta( $post_id, '_cc_helpful_content_score_body', $result );
		// Stamp which scorer produced this so a later version knows to discard it.
		update_post_meta( $post_id, '_cc_helpful_content_score_ver', defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : '' );"""
t = sub(t, old_write, new_write, "cache write stamps the scorer version")

io.open(F, "w", encoding="utf-8", newline="").write(t)

print("APPLIED:")
for a in applied:
    print("  [ok]  " + a)
if failed:
    print("\nFAILED:")
    for f in failed:
        print("  [!!]  " + f)
    sys.exit(1)
