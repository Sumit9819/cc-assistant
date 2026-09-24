"""Build the cc-via-browser batch that uploads one spec's cards.

    python mkupload-erof.py spec-erofirving-10.json          # writes the batch
    node ../cc-via-browser.mjs https://erofirving.com --batch ../calls-up-10.json
    python mkupload-erof.py spec-erofirving-10.json --collect # writes the manifest

Why a batch file and not a direct POST: while the SiteGround IP challenge is up,
`urllib` gets an HTTP 202 sgcaptcha page instead of the REST API, so upload.py
and reupload_v2.py cannot reach this site at all. The plugin route is the same
one they used, `cc-assistant/v1/assets/upload` with a JSON body carrying
base64, and a JSON POST goes through the browser transport unchanged. No
multipart is involved, so nothing here needs a password.

One browser session covers every upload: each launch spends about twenty
seconds clearing the challenge, and cc-via-browser stops on the first failure,
so a failed upload cannot be followed by a patch pointing at nothing.
"""
import base64
import json
import pathlib
import sys

HERE = pathlib.Path(__file__).parent
TOOLS = HERE.parent
ROUTE = "cc-assistant/v1/assets/upload"


def main():
    spec_path = HERE / sys.argv[1]
    spec = json.loads(spec_path.read_text(encoding="utf-8"))
    stem = spec_path.stem.replace("spec-erofirving-", "")
    batch = TOOLS / f"calls-up-{stem}.json"
    resp_dir = TOOLS / f"_up{stem}"
    manifest = HERE / f"uploaded-erof-{stem}.json"

    if "--collect" in sys.argv:
        out = []
        for card in spec["cards"]:
            f = resp_dir / f'{card["file"]}.json'
            if not f.exists():
                sys.exit(f'{card["file"]}: no response at {f} - run the batch first')
            d = json.loads(f.read_text(encoding="utf-8"))
            d = d.get("data", d)
            if not d.get("url") or not d.get("attachment_id"):
                sys.exit(f'{card["file"]}: upload returned no url/attachment_id: '
                         f'{json.dumps(d)[:200]}')
            out.append({"file": card["file"], "heading": card["heading"],
                        "url": d["url"], "attachment_id": d["attachment_id"],
                        "alt": card["alt"]})
        manifest.write_text(json.dumps(out, indent=1) + "\n", encoding="utf-8")
        for r in out:
            print(f'{r["attachment_id"]:>6}  {r["url"].rsplit("/", 1)[-1]}')
        print(f"-> {manifest.name} ({len(out)} uploads)")
        return

    resp_dir.mkdir(exist_ok=True)
    calls = []
    for card in spec["cards"]:
        webp = HERE / "out" / f'{card["file"]}.webp'
        if not webp.exists():
            sys.exit(f'{card["file"]}: no out/{card["file"]}.webp - run towebp.py first')
        calls.append({
            "route": ROUTE,
            "method": "POST",
            # alt travels WITH the upload so the media library carries it too,
            # not just the img tag in the body.
            "body": {"filename": f'{card["file"]}.webp',
                     "content_base64": base64.b64encode(webp.read_bytes()).decode(),
                     "alt": card["alt"]},
            "out": f'_up{stem}/{card["file"]}.json',
        })
    batch.write_text(json.dumps(calls), encoding="utf-8")
    kb = batch.stat().st_size / 1024
    print(f'-> {batch.name} ({len(calls)} uploads, {kb:.0f}KB)')
    print(f'   node cc-via-browser.mjs https://erofirving.com --batch {batch.name}')


if __name__ == "__main__":
    main()
