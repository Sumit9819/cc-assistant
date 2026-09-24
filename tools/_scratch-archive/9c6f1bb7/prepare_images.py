#!/usr/bin/env python3
"""Compress the Slack blog images to WebP and push them into the media library.

Why a local script rather than the MCP tool directly: slack_read_file and the
upload_media tool both move bytes through the model's context, and this batch
is ~70 files averaging 3 MB. That is hundreds of megabytes of base64 through a
conversation, which is not viable. The REST endpoint added in v0.77.0 does not
need the model in the loop at all -- this script reads the files, re-encodes
them, and POSTs straight to /assets/upload. Only the returned URLs come back.

Usage:
    python prepare_images.py --src  D:/slack-images        # convert only
    python prepare_images.py --src  D:/slack-images --upload

--src must contain one folder per post slug, named exactly as in manifest.json:

    D:/slack-images/
        low-testosterone-brain-fog/
            Brain Fog & Testosterone.png
            Recognizing Symptoms.png
            ...
        estrogen-dominance-weight-loss/
            ...

Converted files land in <src>/../webp-out/<slug>/ and are never written back
over the originals.
"""
import argparse
import base64
import json
import os
import sys
import unicodedata
import re

try:
    from PIL import Image
except ImportError:
    sys.exit("Pillow is required:  python -m pip install Pillow")

HERE = os.path.dirname(os.path.abspath(__file__))

# downloaded.json, not manifest.json. manifest.json was the first-pass
# arrangement keyed on ORIGINAL Slack filenames, and those filenames turned out
# to be Canva template leftovers that lie about their contents. slack_fetch.mjs
# renamed every file on the way down, so the only trustworthy record of what is
# actually on disk is what the downloader itself wrote.
DOWNLOADED = os.path.join(HERE, "downloaded.json")

# In-body images do not need the 1600px the featured slot uses. The theme's
# content column is far narrower; 1200 covers 2x on a ~600px column.
MAX_WIDTH = 1200
QUALITY = 82


def slugify_stem(name, slug, index):
    """Build a greppable filename: <post-slug>-<n>-<descriptive-stem>.webp

    Numeric source names (1.png, 7.png) carry no meaning, so those become
    just <slug>-<n>.webp. Descriptive names are kept because they are the only
    clue to what the graphic actually shows.
    """
    stem = os.path.splitext(os.path.basename(name))[0]
    stem = unicodedata.normalize("NFKD", stem).encode("ascii", "ignore").decode()
    stem = re.sub(r"[^A-Za-z0-9]+", "-", stem).strip("-").lower()
    stem = re.sub(r"-+", "-", stem)
    if not stem or re.fullmatch(r"\d+", stem):
        return "{}-{}".format(slug, index)
    return "{}-{}-{}".format(slug, index, stem)[:120]


def convert(src_path, dest_path):
    with Image.open(src_path) as im:
        im.load()
        # WebP has no palette/alpha quirks worth inheriting from PNG; RGBA is
        # safe and keeps transparency where the designer used it.
        if im.mode not in ("RGB", "RGBA"):
            im = im.convert("RGBA" if "A" in im.mode else "RGB")
        if im.width > MAX_WIDTH:
            ratio = MAX_WIDTH / float(im.width)
            im = im.resize((MAX_WIDTH, int(round(im.height * ratio))), Image.LANCZOS)
        os.makedirs(os.path.dirname(dest_path), exist_ok=True)
        im.save(dest_path, "WEBP", quality=QUALITY, method=6)
        return im.width, im.height


SERVER = "cc-assistant-irvingwellnessclinic-com"


def load_credentials():
    """Read the site URL + app password the MCP server already uses.

    Keys are CC_WP_* , not WP_* . Matching on the server NAME rather than
    sniffing the URL, because ten sites share this file and picking the wrong
    one would upload every image to somebody else's media library.
    """
    for candidate in (
        r"c:\Users\sumit\Local Sites\plugintesting\app\public\.mcp.json",
        os.path.join(HERE, ".mcp.json"),
    ):
        if not os.path.exists(candidate):
            continue
        with open(candidate, encoding="utf-8") as fh:
            cfg = json.load(fh)
        server = cfg.get("mcpServers", {}).get(SERVER)
        if not server:
            continue
        env = server.get("env", {})
        url = env.get("CC_WP_URL")
        if url and "irvingwellnessclinic" not in url:
            sys.exit("Refusing to run: {} points at {}, not irvingwellnessclinic.".format(SERVER, url))
        if url:
            return url, env.get("CC_WP_USER"), env.get("CC_WP_APP_PASSWORD")
    return None, None, None


def upload(url, user, password, filename, path, post_id, alt):
    import urllib.request

    with open(path, "rb") as fh:
        payload = base64.b64encode(fh.read()).decode()

    body = json.dumps(
        {
            "filename": filename,
            "content_base64": payload,
            "post_id": post_id,
            "alt": alt or "",
        }
    ).encode()

    req = urllib.request.Request(
        url.rstrip("/") + "/wp-json/cc-assistant/v1/assets/upload",
        data=body,
        method="POST",
    )
    req.add_header("Content-Type", "application/json")
    token = base64.b64encode("{}:{}".format(user, password).encode()).decode()
    req.add_header("Authorization", "Basic " + token)
    req.add_header("User-Agent", "cc-assistant-image-loader/1.0")

    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode()).get("data", {})


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", required=True, help="Folder holding one subfolder per post slug.")
    ap.add_argument("--upload", action="store_true", help="Also POST each converted file to the media library.")
    ap.add_argument("--only", help="Process a single slug (for a trial run).")
    args = ap.parse_args()

    with open(DOWNLOADED, encoding="utf-8") as fh:
        rows = json.load(fh)

    out_root = os.path.join(os.path.dirname(os.path.abspath(args.src)), "webp-out")
    creds = (None, None, None)
    if args.upload:
        creds = load_credentials()
        if not creds[0]:
            sys.exit("Could not read WP credentials from .mcp.json. Convert without --upload, or fix the path.")

    results, before, after, skipped = [], 0, 0, 0
    current = None

    for row in rows:
        slug = row["slug"]
        if args.only and args.only != slug:
            continue
        if row.get("error") or not row.get("file"):
            print("  --  {} {} did not download - skipped".format(slug, row.get("id", "")))
            skipped += 1
            continue

        if slug != current:
            current = slug
            print("\n{}  (post {})".format(slug, row["post_id"]))

        src_path = os.path.join(args.src, slug, row["file"])
        if not os.path.exists(src_path):
            print("  !!  MISSING {}/{}".format(slug, row["file"]))
            skipped += 1
            continue

        i = row["order"]
        dest_name = slugify_stem(row["title"], slug, i) + ".webp"
        dest_path = os.path.join(out_root, slug, dest_name)
        w, h = convert(src_path, dest_path)

        b0, b1 = os.path.getsize(src_path), os.path.getsize(dest_path)
        before += b0
        after += b1
        print("  ok  {:58s} {:>8} -> {:>8}  ({}x{})".format(
            dest_name[:58], "%.1fMB" % (b0 / 1048576), "%.0fKB" % (b1 / 1024), w, h))

        out = {"slug": slug, "post_id": row["post_id"], "file": dest_name,
               "order": i, "anchor": row.get("anchor"), "title": row["title"],
               "slack_id": row.get("id"), "defect": row.get("defect")}

        if args.upload:
            try:
                res = upload(creds[0], creds[1], creds[2], dest_name, dest_path,
                             row["post_id"], row.get("alt", ""))
                out["attachment_id"] = res.get("attachment_id")
                out["url"] = res.get("url")
                if res.get("renamed"):
                    print("      renamed by WP -> {}".format(res.get("filename")))
            except Exception as exc:  # noqa: BLE001
                out["error"] = str(exc)
                print("      UPLOAD FAILED: {}".format(exc))

        results.append(out)

    out_json = os.path.join(HERE, "uploaded.json")
    with open(out_json, "w", encoding="utf-8") as fh:
        json.dump(results, fh, indent=2)

    print("\n{} images converted, {} skipped".format(len(results), skipped))
    if before:
        print("{:.1f} MB -> {:.1f} MB  ({:.1f}% smaller)".format(
            before / 1048576, after / 1048576, 100 * (1 - after / before)))
    print("Wrote {}".format(out_json))


if __name__ == "__main__":
    main()
