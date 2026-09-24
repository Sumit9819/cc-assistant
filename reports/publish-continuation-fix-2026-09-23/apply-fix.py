"""Apply the publish_draft continuation fix to the cc-assistant plugin source.

Run it yourself (Claude Code's safety classifier will not let Claude make this edit):

    python D:/cc-assistant/reports/publish-continuation-fix-2026-09-23/apply-fix.py

It changes ONE file and refuses to run if the file has already been changed or
does not match the expected original text. See README.md in this folder for the
reasoning. Afterwards tell Claude "fix applied" and it will add the tests, run
the suite, bump the version and build the 0.90.1 zip.
"""
import pathlib
import sys

F = pathlib.Path(r"D:\cc-assistant\wp-content\plugins\cc-assistant\includes\class-approval-continuation.php")

EDITS = [
    (
        "\t/** Deliberately excludes structural edits, publication, slugs and arbitrary plugin settings. */\n"
        "\tpublic static function scope( $row ) {\n"
        "\t\t$p = json_decode( (string) ( $row->proposed_value ?? '' ), true );\n"
        "\t\tif ( ! is_array( $p ) ) { return null; }\n"
        "\t\tswitch ( $row->change_type ?? '' ) {\n",
        "\t/** Deliberately excludes structural edits, slugs and arbitrary plugin settings. */\n"
        "\tpublic static function scope( $row ) {\n"
        "\t\t$p = json_decode( (string) ( $row->proposed_value ?? '' ), true );\n"
        "\t\tif ( ! is_array( $p ) ) { return null; }\n"
        "\t\tswitch ( $row->change_type ?? '' ) {\n"
        "\t\t\tcase 'publish_draft':\n"
        "\t\t\t\t// Publishing flips post_status only. Claiming post_name and\n"
        "\t\t\t\t// post_content as well keeps a slug or body change between\n"
        "\t\t\t\t// review and publish blocked; SEO meta, title, excerpt, author\n"
        "\t\t\t\t// and featured image siblings may continue in either order.\n"
        "\t\t\t\treturn 'publish' === ( $p['post_status'] ?? '' ) ? array( 'field:post_status', 'field:post_name', 'field:post_content' ) : null;\n",
    ),
    (
        "\t\tswitch ( $row->change_type ) {\n"
        "\t\t\tcase 'meta_update': $state['fields'][$p['field']] = (string) $p['value']; break;\n",
        "\t\tswitch ( $row->change_type ) {\n"
        "\t\t\t// Any other publish side effect (generated slug, plugin meta) will\n"
        "\t\t\t// not match the recorded after-hash, so the chain fails closed.\n"
        "\t\t\tcase 'publish_draft': $state['fields']['post_status'] = 'publish'; break;\n"
        "\t\t\tcase 'meta_update': $state['fields'][$p['field']] = (string) $p['value']; break;\n",
    ),
]

src = F.read_bytes().decode("utf-8")
crlf = "\r\n" in src
text = src.replace("\r\n", "\n")
if "case 'publish_draft'" in text:
    sys.exit("Already applied. Nothing changed.")
for old, new in EDITS:
    if text.count(old) != 1:
        sys.exit("File does not match the expected original text. Nothing changed.")
    text = text.replace(old, new)
if crlf:
    text = text.replace("\n", "\r\n")
F.write_bytes(text.encode("utf-8"))
print("Applied: 2 edits to", F)
